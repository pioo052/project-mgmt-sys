<?php
declare(strict_types=1);

$user = current_user();
$pageTitle = $pageTitle ?? 'Dashboard';
$pageDescription = $pageDescription ?? '';
$breadcrumbs = $breadcrumbs ?? [['label' => 'Dashboard']];
$pageActions = $pageActions ?? '';
$bodyClass = $bodyClass ?? '';

$unreadCount = 0;
if ($user) {
    try {
        $unreadCount = (int) db_value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [(int) $user['id']]);
    } catch (Throwable) {
        $unreadCount = 0;
    }
}
$flashes = flashes();
$currentFile = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$activeNav = $activeNav ?? (str_ends_with($currentFile, '.php') ? str_replace('.php', '', $currentFile) : '');
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#FFFFFF">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <meta name="description" content="<?= e(APP_FULL_NAME) ?> – <?= e(APP_INSTITUTION) ?>">
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('urims-theme');
                var theme = stored === 'dark' || stored === 'light' ? stored : 'light';
                document.documentElement.setAttribute('data-theme', theme);
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
<body class="<?= e($bodyClass) ?>">
<script>
    (function () {
        try {
            if (localStorage.getItem('urims-sidebar') === 'collapsed') {
                document.body.classList.add('sidebar-collapsed');
            }
        } catch (e) {}
    })();
</script>
<a class="skip-link" href="#main-content">Skip to main content</a>
<div class="app-shell">
    <?php include ROOT_PATH . '/includes/sidebar.php'; ?>
    <div class="sidebar-backdrop" data-sidebar-close hidden></div>

    <div class="app-main">
        <header class="topbar" role="banner">
            <div class="topbar__left">
                <button class="icon-btn topbar__menu" type="button" data-sidebar-open aria-label="Open navigation menu" aria-controls="sidebar" aria-expanded="false">
                    <?= icon('menu', 22) ?>
                </button>
                <div class="topbar__titles">
                    <nav class="breadcrumbs" aria-label="Breadcrumb">
                        <ol>
                            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                                <li>
                                    <?php if (!empty($crumb['url'])): ?>
                                        <a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
                                    <?php else: ?>
                                        <span aria-current="page"><?= e($crumb['label']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($index < count($breadcrumbs) - 1): ?><?= icon('chevron-right', 13, 'breadcrumbs__sep') ?><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                    <h1 class="topbar__title"><?= e($pageTitle) ?></h1>
                </div>
            </div>

            <div class="topbar__right">
                <a class="icon-btn topbar__bell" href="<?= e(url('notifications.php')) ?>" aria-label="Notifications<?= $unreadCount ? ' (' . $unreadCount . ' unread)' : '' ?>">
                    <?= icon('bell', 21) ?>
                    <?php if ($unreadCount > 0): ?>
                        <span class="topbar__badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
                    <?php endif; ?>
                </a>

                <button class="icon-btn theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark mode" title="Switch colour theme">
                    <span data-theme-icon-light><?= icon('moon', 20) ?></span>
                    <span data-theme-icon-dark hidden><?= icon('sun', 20) ?></span>
                </button>

                <div class="dropdown" data-dropdown>
                    <button class="user-menu" type="button" data-dropdown-toggle aria-haspopup="true" aria-expanded="false" aria-controls="user-dropdown">
                        <span class="avatar" aria-hidden="true"><?= e(initials((string) ($user['name'] ?? 'U'))) ?></span>
                        <span class="user-menu__text">
                            <span class="user-menu__name"><?= e($user['name'] ?? 'User') ?></span>
                            <span class="user-menu__role"><?= e(role_label($user['role'] ?? '')) ?></span>
                        </span>
                        <?= icon('chevron-down', 16) ?>
                    </button>
                    <div class="dropdown__menu" id="user-dropdown" role="menu" hidden>
                        <div class="dropdown__header">
                            <p class="dropdown__name"><?= e($user['name'] ?? '') ?></p>
                            <p class="dropdown__meta"><?= e($user['email'] ?? '') ?></p>
                        </div>
                        <a class="dropdown__item" role="menuitem" href="<?= e(url('profile.php')) ?>"><?= icon('user', 17) ?>My profile</a>
                        <?php if (can_access('settings')): ?>
                            <a class="dropdown__item" role="menuitem" href="<?= e(url('settings.php')) ?>"><?= icon('settings', 17) ?>Settings</a>
                        <?php endif; ?>
                        <a class="dropdown__item" role="menuitem" href="<?= e(url('notifications.php')) ?>"><?= icon('bell', 17) ?>Notifications</a>
                        <div class="dropdown__divider"></div>
                        <a class="dropdown__item dropdown__item--danger" role="menuitem" href="<?= e(url('logout.php')) ?>"><?= icon('logout', 17) ?>Sign out</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="main" id="main-content" tabindex="-1">
            <div class="content">
                <?php if ($pageDescription !== '' || $pageActions !== ''): ?>
                    <div class="page-head">
                        <p class="page-head__desc"><?= e($pageDescription) ?></p>
                        <?php if ($pageActions !== ''): ?>
                            <div class="page-head__actions"><?= $pageActions ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($flashes): ?>
                    <div class="toast-stack" data-toast-stack aria-live="polite" aria-atomic="false">
                        <?php foreach ($flashes as $message):
                            $tone = in_array($message['type'], ['success', 'danger', 'warning', 'info'], true) ? $message['type'] : 'info';
                            $toneIcon = ['success' => 'check', 'danger' => 'alert', 'warning' => 'alert', 'info' => 'info'][$tone];
                            ?>
                            <div class="toast toast--<?= e($tone) ?>" role="<?= $tone === 'danger' ? 'alert' : 'status' ?>">
                                <span class="toast__icon"><?= icon($toneIcon, 18) ?></span>
                                <p class="toast__message"><?= e($message['message']) ?></p>
                                <button class="toast__close" type="button" data-toast-close aria-label="Dismiss notification"><?= icon('close', 16) ?></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
