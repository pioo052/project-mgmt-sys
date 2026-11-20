<?php
declare(strict_types=1);

/**
 * Session authentication, remember-me tokens and permission guards.
 */

function current_user(bool $refresh = false): ?array
{
    static $cached = null;
    if ($refresh) {
        $cached = null;
    }
    if ($cached !== null) {
        return $cached;
    }

    if (!empty($_SESSION['user_id'])) {
        try {
            $user = db_row(
                'SELECT u.*, f.name AS faculty_name, d.name AS department_name
                 FROM users u
                 LEFT JOIN faculties f ON f.id = u.faculty_id
                 LEFT JOIN departments d ON d.id = u.department_id
                 WHERE u.id = ? AND u.status = "active"',
                [(int) $_SESSION['user_id']]
            );
            if ($user) {
                return $cached = $user;
            }
        } catch (Throwable) {
            return null;
        }
        unset($_SESSION['user_id']);
    }

    return remember_login();
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['intended_url'] = (string) ($_SERVER['REQUEST_URI'] ?? '');
        redirect('login.php');
    }
}

function login_user(array $user, bool $remember = false): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['login_at']  = time();
    unset($_SESSION['failed_logins'], $_SESSION['intended_url']);

    db_exec('UPDATE users SET last_login_at = NOW() WHERE id = ?', [(int) $user['id']]);
    audit_log('login', 'authentication', 'Signed in as ' . $user['email']);

    if ($remember) {
        create_remember_token((int) $user['id']);
    }
}

function logout_user(): void
{
    $user = current_user();
    if ($user) {
        audit_log('logout', 'authentication', 'Signed out');
    }
    if (!empty($_COOKIE['urims_remember'])) {
        [$selector] = array_pad(explode(':', (string) $_COOKIE['urims_remember'], 2), 2, '');
        try {
            db_exec('DELETE FROM remember_tokens WHERE selector = ?', [$selector]);
        } catch (Throwable) {
        }
        setcookie('urims_remember', '', [
            'expires'  => time() - 3600,
            'path'     => BASE_PATH === '' ? '/' : BASE_PATH . '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function create_remember_token(int $userId): void
{
    $selector = bin2hex(random_bytes(9));
    $validator = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);

    db_exec('DELETE FROM remember_tokens WHERE user_id = ? OR expires_at < NOW()', [$userId]);
    db_exec(
        'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at, created_at)
         VALUES (?, ?, ?, ?, NOW())',
        [$userId, $selector, hash('sha256', $validator), $expires]
    );

    setcookie('urims_remember', $selector . ':' . $validator, [
        'expires'  => time() + 60 * 60 * 24 * 30,
        'path'     => BASE_PATH === '' ? '/' : BASE_PATH . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
}

function remember_login(): ?array
{
    if (empty($_COOKIE['urims_remember'])) {
        return null;
    }
    $parts = explode(':', (string) $_COOKIE['urims_remember'], 2);
    if (count($parts) !== 2) {
        return null;
    }
    [$selector, $validator] = $parts;

    try {
        $token = db_row('SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > NOW()', [$selector]);
        if (!$token || !hash_equals($token['token_hash'], hash('sha256', $validator))) {
            return null;
        }
        $user = db_row('SELECT * FROM users WHERE id = ? AND status = "active"', [(int) $token['user_id']]);
        if (!$user) {
            return null;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['login_at'] = time();
        db_exec('UPDATE users SET last_login_at = NOW() WHERE id = ?', [(int) $user['id']]);
        return current_user(true);
    } catch (Throwable) {
        return null;
    }
}

function attempt_login(string $identifier, string $password): array
{
    $identifier = trim($identifier);
    if ($identifier === '' || $password === '') {
        return ['ok' => false, 'message' => 'Enter your email or username and password.'];
    }

    $attempts = (int) ($_SESSION['failed_logins'] ?? 0);
    if ($attempts >= 5) {
        $until = (int) ($_SESSION['lock_until'] ?? 0);
        if ($until > time()) {
            return ['ok' => false, 'message' => 'Too many failed attempts. Please wait ' . ceil(($until - time()) / 60) . ' minute(s) before trying again.'];
        }
        $_SESSION['failed_logins'] = 0;
        unset($_SESSION['lock_until']);
    }

    $user = db_row(
        'SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1',
        [$identifier, $identifier]
    );

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $_SESSION['failed_logins'] = $attempts + 1;
        if ($_SESSION['failed_logins'] >= 5) {
            $_SESSION['lock_until'] = time() + 300;
        }
        return ['ok' => false, 'message' => 'Invalid credentials. Please check your email or username and password.'];
    }

    if ($user['status'] !== 'active') {
        return ['ok' => false, 'message' => 'This account is ' . $user['status'] . '. Please contact the research office.'];
    }

    return ['ok' => true, 'user' => $user];
}

/* ---------------------------------------------------------------
 | Guards
 --------------------------------------------------------------- */
function current_role(): string
{
    return (string) (current_user()['role'] ?? 'student');
}

function can_access(string $page): bool
{
    if (!is_logged_in()) {
        return false;
    }
    return role_can(current_role(), $page, 'pages');
}

function can_write(string $page): bool
{
    if (!is_logged_in()) {
        return false;
    }
    return role_can(current_role(), $page, 'write');
}

function can_delete(string $page): bool
{
    if (!is_logged_in()) {
        return false;
    }
    return role_can(current_role(), $page, 'delete');
}

function require_permission(string $page): void
{
    require_login();
    if (!can_access($page)) {
        http_response_code(403);
        $pageTitle = 'Access denied';
        $pageDescription = 'Your role does not include access to this module.';
        include ROOT_PATH . '/includes/header.php';
        echo '<div class="card empty-state"><div class="empty-state__icon">' . icon('lock', 26) . '</div>'
            . '<h2>Access denied</h2><p class="text-secondary">Your account (' . e(role_label(current_role())) . ') does not have permission to view this page.</p>'
            . '<a class="btn btn--primary" href="' . e(url('dashboard.php')) . '">Back to dashboard</a></div>';
        include ROOT_PATH . '/includes/footer.php';
        exit;
    }
}

/**
 * Row level scope for resources that belong to a single supervisor/student.
 * Returns [sqlFragment, params].
 */
function row_scope(): array
{
    $user = current_user();
    $scope = role_permissions(current_role())['scope'] ?? 'all';
    if (!$user) {
        return ['1 = 0', []];
    }
    if ($scope === 'own_supervision') {
        return ['supervisor_id = ?', [(int) $user['id']]];
    }
    if ($scope === 'own_student') {
        return ['student_id = ?', [(int) $user['id']]];
    }
    return ['1 = 1', []];
}
