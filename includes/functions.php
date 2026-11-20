<?php
declare(strict_types=1);

/**
 * Shared helpers used by the application shell and every module.
 */

/* ---------------------------------------------------------------
 | Output / URLs
 --------------------------------------------------------------- */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return BASE_PATH . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $relative = ltrim($path, '/');
    $absolute = ROOT_PATH . '/' . $relative;
    $version  = is_file($absolute) ? substr((string) filemtime($absolute), -6) : APP_VERSION;
    return url($relative) . '?v=' . $version;
}

function app_url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    $target = preg_match('#^https?://#i', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

function current_url(): string
{
    $self = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?');
    $query = $_GET;
    unset($query['page']);
    $qs = http_build_query($query);
    return $self . ($qs !== '' ? '?' . $qs : '');
}

function is_post(): bool
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
}

function input(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function query_string(array $overrides = []): string
{
    $query = array_merge($_GET, $overrides);
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query[$key]);
        }
    }
    return http_build_query($query);
}

/* ---------------------------------------------------------------
 | CSRF
 --------------------------------------------------------------- */
function csrf_token(): string
{
    if (empty($_SESSION[CSRF_KEY])) {
        $_SESSION[CSRF_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_KEY];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): ?string
{
    $sent = (string) ($_POST['csrf_token'] ?? '');
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        return 'Your session has expired. Please refresh the page and try again.';
    }
    return null;
}

/* ---------------------------------------------------------------
 | Flash messages
 --------------------------------------------------------------- */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

/* ---------------------------------------------------------------
 | Formatting
 --------------------------------------------------------------- */
function humanize(string $value): string
{
    return ucwords(str_replace(['_', '-'], ' ', $value));
}

function format_date(?string $date, string $format = APP_DATE_FORMAT): string
{
    if ($date === null || $date === '' || str_starts_with($date, '0000')) {
        return '—';
    }
    try {
        return (new DateTime($date))->format($format);
    } catch (Exception) {
        return '—';
    }
}

function format_datetime(?string $value): string
{
    return format_date($value, 'd M Y, H:i');
}

function time_ago(?string $value): string
{
    if (!$value) {
        return '—';
    }
    try {
        $then = new DateTime($value);
    } catch (Exception) {
        return '—';
    }
    $diff = time() - $then->getTimestamp();
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' min ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' hour' . (floor($diff / 3600) === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 604800) {
        return floor($diff / 86400) . ' day' . (floor($diff / 86400) === 1 ? '' : 's') . ' ago';
    }
    return format_date($value);
}

function money(float|int|string|null $amount, bool $compact = false): string
{
    $value = (float) ($amount ?? 0);
    if ($compact) {
        if (abs($value) >= 1_000_000_000) {
            return APP_CURRENCY . ' ' . rtrim(rtrim(number_format($value / 1_000_000_000, 1), '0'), '.') . 'B';
        }
        if (abs($value) >= 1_000_000) {
            return APP_CURRENCY . ' ' . rtrim(rtrim(number_format($value / 1_000_000, 1), '0'), '.') . 'M';
        }
        if (abs($value) >= 1_000) {
            return APP_CURRENCY . ' ' . rtrim(rtrim(number_format($value / 1_000, 1), '0'), '.') . 'K';
        }
    }
    return APP_CURRENCY . ' ' . number_format($value, 0);
}

function initials(string $name, int $length = 2): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $parts = array_values(array_filter($parts));
    if (!$parts) {
        return '?';
    }
    $letters = '';
    foreach (array_slice($parts, 0, $length) as $part) {
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $letters;
}

function role_label(?string $role): string
{
    $labels = [
        'admin'            => 'System Administrator',
        'research_officer' => 'Research Officer',
        'dean'             => 'Faculty Dean',
        'hod'              => 'Head of Department',
        'supervisor'       => 'Research Supervisor',
        'student'          => 'Student Researcher',
        'finance'          => 'Finance Officer',
        'judge'            => 'Innovation Judge',
    ];
    return $labels[$role ?? ''] ?? humanize((string) $role);
}

/* ---------------------------------------------------------------
 | Status badges (colour is never the only signal)
 --------------------------------------------------------------- */
function status_tone(string $status): string
{
    $map = [
        'draft'          => 'muted',
        'planned'        => 'muted',
        'pending'        => 'warning',
        'submitted'      => 'warning',
        'under_review'   => 'info',
        'in_review'      => 'info',
        'review'         => 'info',
        'shortlisted'    => 'info',
        'open'           => 'success',
        'approved'       => 'success',
        'accepted'       => 'success',
        'active'         => 'success',
        'ongoing'        => 'success',
        'published'      => 'success',
        'verified'       => 'success',
        'paid'           => 'success',
        'disbursed'      => 'success',
        'completed'      => 'primary',
        'awarded'        => 'primary',
        'featured'       => 'primary',
        'overdue'        => 'danger',
        'rejected'       => 'danger',
        'declined'       => 'danger',
        'cancelled'      => 'danger',
        'disqualified'   => 'danger',
        'suspended'      => 'muted',
        'closed'         => 'muted',
        'inactive'       => 'muted',
        'archived'       => 'muted',
        'unpublished'    => 'muted',
        'not_started'    => 'muted',
    ];
    return $map[strtolower($status)] ?? 'muted';
}

function status_badge(?string $status, ?string $label = null): string
{
    $status = (string) ($status ?? '');
    if ($status === '') {
        return '<span class="text-muted">—</span>';
    }
    $tone = status_tone($status);
    return '<span class="badge badge--' . $tone . '"><span class="badge__dot" aria-hidden="true"></span>'
        . e($label ?? humanize($status)) . '</span>';
}

function role_badge(?string $role): string
{
    $map = [
        'admin'            => 'primary',
        'research_officer' => 'info',
        'dean'             => 'primary',
        'hod'              => 'info',
        'supervisor'       => 'success',
        'student'          => 'muted',
        'finance'          => 'warning',
        'judge'            => 'info',
    ];
    $tone = $map[(string) $role] ?? 'muted';
    return '<span class="badge badge--' . $tone . '">' . e(role_label($role)) . '</span>';
}

/* ---------------------------------------------------------------
 | Pagination
 --------------------------------------------------------------- */
function paginate(int $total, int $perPage, int $page): array
{
    $perPage  = max(1, $perPage);
    $pages    = max(1, (int) ceil($total / $perPage));
    $page     = min(max(1, $page), $pages);
    return [
        'total'    => $total,
        'per_page' => $perPage,
        'page'     => $page,
        'pages'    => $pages,
        'offset'   => ($page - 1) * $perPage,
        'from'     => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
        'to'       => min($total, $page * $perPage),
    ];
}

function pagination_links(array $p): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $html = '<nav class="pagination" aria-label="Pagination">';
    $prevDisabled = $p['page'] <= 1;
    $html .= $prevDisabled
        ? '<span class="pagination__btn is-disabled" aria-disabled="true">' . icon('chevron-left', 16) . ' Prev</span>'
        : '<a class="pagination__btn" href="?' . e(query_string(['page' => $p['page'] - 1])) . '" rel="prev">' . icon('chevron-left', 16) . ' Prev</a>';

    $window = 1;
    $shown = [];
    for ($i = 1; $i <= $p['pages']; $i++) {
        if ($i === 1 || $i === $p['pages'] || abs($i - $p['page']) <= $window) {
            $shown[] = $i;
        }
    }
    $last = 0;
    foreach ($shown as $i) {
        if ($last && $i - $last > 1) {
            $html .= '<span class="pagination__ellipsis">…</span>';
        }
        if ($i === $p['page']) {
            $html .= '<span class="pagination__btn is-active" aria-current="page">' . $i . '</span>';
        } else {
            $html .= '<a class="pagination__btn" href="?' . e(query_string(['page' => $i])) . '">' . $i . '</a>';
        }
        $last = $i;
    }

    $nextDisabled = $p['page'] >= $p['pages'];
    $html .= $nextDisabled
        ? '<span class="pagination__btn is-disabled" aria-disabled="true">Next ' . icon('chevron-right', 16) . '</span>'
        : '<a class="pagination__btn" href="?' . e(query_string(['page' => $p['page'] + 1])) . '" rel="next">Next ' . icon('chevron-right', 16) . '</a>';
    $html .= '</nav>';
    return $html;
}

/* ---------------------------------------------------------------
 | Audit trail
 --------------------------------------------------------------- */
function audit_log(string $action, string $module, string $description): void
{
    try {
        $user = current_user();
        db_exec(
            'INSERT INTO audit_logs (user_id, action, module, description, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())',
            [$user['id'] ?? null, $action, $module, $description, $_SERVER['REMOTE_ADDR'] ?? 'cli']
        );
    } catch (Throwable) {
        // Auditing must never break a user action.
    }
}

function setting(string $key, mixed $default = null): mixed
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db_rows('SELECT setting_key, setting_value FROM settings') as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

/* ---------------------------------------------------------------
 | Misc
 --------------------------------------------------------------- */
function truncate(?string $text, int $length = 80): string
{
    $text = trim((string) $text);
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length - 1) . '…';
}

function percentage(float|int $part, float|int $total, int $decimals = 0): float
{
    if ((float) $total <= 0) {
        return 0.0;
    }
    return round(((float) $part / (float) $total) * 100, $decimals);
}

function is_overdue(?string $date, ?string $status = null): bool
{
    if (!$date || in_array($status, ['completed', 'cancelled', 'closed'], true)) {
        return false;
    }
    return strtotime($date) < strtotime('today');
}

function human_filesize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
    $power = min($power, count($units) - 1);
    return round($bytes / (1024 ** $power), $power ? 1 : 0) . ' ' . $units[$power];
}
