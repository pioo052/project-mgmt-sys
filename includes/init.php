<?php
declare(strict_types=1);

/**
 * Bootstrap for every page. Verifies the database connection and renders a
 * friendly setup screen when the schema has not been installed yet.
 */

require_once dirname(__DIR__) . '/config/config.php';

try {
    db();
} catch (Throwable $e) {
    http_response_code(503);
    ?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>URIMS · Database setup required</title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/themes.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/components.css')) ?>">
</head>
<body class="setup-page">
    <main class="setup-card">
        <div class="brand brand--stack">
            <span class="brand__mark">U</span>
            <span class="brand__text"><strong>URIMS</strong><small>University Research &amp; Innovation Management System</small></span>
        </div>
        <h1>Database setup required</h1>
        <p class="text-secondary">URIMS could not connect to MySQL. Start MySQL in the XAMPP control panel, check your credentials in <code>config/database.php</code>, then run the installer.</p>
        <pre class="setup-code">php database/install.php</pre>
        <p class="text-sm text-muted">Technical detail: <?= e($e->getMessage()) ?></p>
        <a class="btn btn--primary" href="<?= e(url('login.php')) ?>">Retry connection</a>
    </main>
</body>
</html>
    <?php
    exit;
}
