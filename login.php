<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$identifier = '';
$loginError = '';

if (is_post()) {
    $csrfError = verify_csrf();
    if ($csrfError) {
        $loginError = $csrfError;
    } else {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $remember = !empty($_POST['remember']);

        $result = attempt_login($identifier, $password);
        if ($result['ok']) {
            $intended = (string) ($_SESSION['intended_url'] ?? '');
            login_user($result['user'], $remember);
            if ($intended !== '' && !str_contains($intended, 'login.php') && !str_contains($intended, 'logout.php')) {
                header('Location: ' . $intended);
                exit;
            }
            redirect('dashboard.php');
        }
        $loginError = $result['message'];
    }
}

$signedOut = isset($_GET['signed_out']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Sign in · <?= e(APP_NAME) ?></title>
    <meta name="description" content="<?= e(APP_FULL_NAME) ?> – <?= e(APP_INSTITUTION) ?>">
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('urims-theme');
                if (stored === 'dark' || stored === 'light') {
                    document.documentElement.setAttribute('data-theme', stored);
                }
            } catch (e) {}
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= e(asset('assets/css/themes.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/responsive.css')) ?>">
</head>
<body>
<main class="auth">
    <section class="auth__brand" aria-labelledby="auth-brand-title">
        <div class="auth__brand-top">
            <span class="brand__mark" aria-hidden="true">U</span>
            <span class="brand__text">
                <strong><?= e(APP_NAME) ?></strong>
                <small>KIUT Research</small>
            </span>
        </div>

        <div class="auth__brand-body">
            <p class="auth__eyebrow"><?= icon('faculty', 13) ?> Kampala International University in Tanzania</p>
            <h1 class="auth__headline" id="auth-brand-title">One workspace for university research and innovation.</h1>
            <p class="auth__lede">Coordinate student research, proposals, supervision, publications, innovation and funding in a single secure platform.</p>

            <ul class="auth__features">
                <li class="auth__feature"><span class="auth__feature-icon"><?= icon('research', 15) ?></span> Track every research project from proposal to completion.</li>
                <li class="auth__feature"><span class="auth__feature-icon"><?= icon('budget', 15) ?></span> Monitor funding allocations, budgets and expenditure.</li>
                <li class="auth__feature"><span class="auth__feature-icon"><?= icon('innovation', 15) ?></span> Manage innovation competitions, judging and results.</li>
                <li class="auth__feature"><span class="auth__feature-icon"><?= icon('analytics', 15) ?></span> Report with live, role-aware analytics and audit trails.</li>
            </ul>
        </div>

        <p class="auth__brand-foot"><?= e(APP_FULL_NAME) ?> · Version <?= e(APP_VERSION) ?></p>
    </section>

    <section class="auth__panel" aria-labelledby="login-title">
        <button class="icon-btn auth__theme theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark mode" title="Switch colour theme">
            <span data-theme-icon-light><?= icon('moon', 19) ?></span>
            <span data-theme-icon-dark hidden><?= icon('sun', 19) ?></span>
        </button>

        <div class="auth__panel-inner">
            <div class="auth__mobile-brand">
                <span class="brand__mark" aria-hidden="true">U</span>
                <span>
                    <strong><?= e(APP_NAME) ?></strong>
                    <small>KIUT Research Management</small>
                </span>
            </div>

            <h2 class="auth__title" id="login-title">Sign in to URIMS</h2>
            <p class="auth__subtitle">Use your university email address or username.</p>

            <?php if ($loginError !== ''): ?>
                <div class="alert alert--danger" role="alert" style="margin-top:18px"><?= icon('alert', 18) ?><div><?= e($loginError) ?></div></div>
            <?php elseif ($signedOut): ?>
                <div class="alert alert--success" role="status" style="margin-top:18px"><?= icon('check', 18) ?><div>You have been signed out securely.</div></div>
            <?php endif; ?>

            <form class="auth__form" method="post" action="<?= e(url('login.php')) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="label" for="identifier">Email or username <span class="label__required" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
                    <input class="input" type="text" id="identifier" name="identifier" value="<?= e($identifier) ?>" required autocomplete="username" autofocus
                           placeholder="you@kiut.ac.tz" aria-describedby="identifier-help">
                    <p class="help-text" id="identifier-help">Staff and student accounts use the same sign-in.</p>
                </div>

                <div class="form-group">
                    <label class="label" for="password">Password <span class="label__required" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
                    <div class="input-group">
                        <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
                        <button class="input-group__btn" type="button" data-toggle-password="password" aria-label="Show password" aria-pressed="false"><?= icon('eye', 18) ?></button>
                    </div>
                </div>

                <div class="auth__row">
                    <label class="checkbox">
                        <input type="checkbox" name="remember" value="1">
                        Keep me signed in for 30 days
                    </label>
                    <span class="text-xs text-muted">Access is logged and audited.</span>
                </div>

                <button class="btn btn--primary btn--block" type="submit"><?= icon('lock', 17) ?>Sign in</button>
            </form>

            <div class="auth__demo">
                <strong>Demonstration accounts</strong>
                <p>Administrator: <code>admin@kiut.ac.tz</code> / <code>Admin@123</code></p>
                <p>Supervisor: <code>salma.juma@kiut.ac.tz</code> / <code>Super@123</code></p>
                <p>Student: <code>student@kiut.ac.tz</code> / <code>Student@123</code></p>
                <p>Research office: <code>research@kiut.ac.tz</code> / <code>Research@123</code></p>
            </div>

            <p class="auth__footer-note">Forgotten your password? Contact the research office at <?= e(setting('support_email', 'research@kiut.ac.tz')) ?>.</p>
        </div>
    </section>
</main>

<script src="<?= e(asset('assets/js/theme.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
