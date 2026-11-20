<?php
declare(strict_types=1);

/**
 * Bootstrap for every page. Verifies the database connection and the schema,
 * and renders a setup screen with real diagnostics when either is unavailable.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/database/bootstrap.php';

$urimsDbError = null;
$urimsMissingTables = [];
$urimsDbReady = false;

try {
    $urimsPdo = db();
    $urimsMissingTables = urims_missing_tables($urimsPdo);
    $urimsDbReady = $urimsMissingTables === [];
} catch (Throwable $e) {
    $urimsDbError = $e->getMessage();
}

if ($urimsDbReady) {
    /* Connected and complete: continue normally into the page. */
    return;
}

$urimsConnection = db_connection_summary();
$urimsEnvReport = $urimsConnection['env'];
$urimsInstallUrl = url('database/install.php?confirm=install');
$urimsRetryUrl = url(basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'login.php')));
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
    <main class="setup-card" style="max-width:720px">
        <div class="brand brand--stack">
            <span class="brand__mark">U</span>
            <span class="brand__text"><strong>URIMS</strong><small>University Research &amp; Innovation Management System</small></span>
        </div>

        <h1><?= $urimsDbError !== null ? 'Database connection failed' : 'Database tables not installed' ?></h1>

        <?php if ($urimsDbError !== null): ?>
            <p class="text-secondary">
                URIMS could not open a MySQL connection with the configured credentials. The technical
                reason is shown below. Credentials are read from
                <code>DB_HOST</code>, <code>DB_PORT</code>, <code>DB_NAME</code>, <code>DB_USER</code> and
                <code>DB_PASS</code>, or from <code>config/database.local.php</code> locally.
            </p>
        <?php else: ?>
            <p class="text-secondary">
                The MySQL connection works, but <?= count($urimsMissingTables) ?> required table(s) do not exist yet.
                Install the schema to create them. Existing tables and rows are never dropped or overwritten.
            </p>
        <?php endif; ?>

        <ul class="setup-facts" style="list-style:none;padding:0;margin:18px 0;display:grid;gap:6px;font-size:13px;line-height:1.5">
            <li><strong>Host:</strong> <code><?= e($urimsConnection['host'] . ':' . $urimsConnection['port']) ?></code></li>
            <li><strong>Database:</strong> <code><?= e($urimsConnection['name']) ?></code></li>
            <li><strong>User:</strong> <code><?= e($urimsConnection['user']) ?></code>
                (<?= $urimsConnection['password_set'] ? 'password set' : 'no password set' ?>)</li>
            <li><strong>Credentials source:</strong> <?= e($urimsConnection['source']) ?></li>
            <li><strong>PDO MySQL driver:</strong> <?= $urimsConnection['pdo_mysql'] ? 'loaded' : 'NOT loaded' ?>
                (PHP <?= e($urimsConnection['php']) ?>)</li>
        </ul>

        <?php if ($urimsMissingTables !== []): ?>
            <p class="text-sm text-secondary"><strong>Missing tables:</strong>
                <code><?= e(implode(', ', array_slice($urimsMissingTables, 0, 10))) ?><?= count($urimsMissingTables) > 10 ? ', …' : '' ?></code>
            </p>
        <?php endif; ?>

        <details style="margin:14px 0">
            <summary class="text-sm">Connection variables detected in this process</summary>
            <ul style="list-style:none;padding:0;margin:10px 0;display:grid;gap:4px;font-size:12px">
                <?php foreach ($urimsEnvReport as $urimsKey => $urimsOrigin): ?>
                    <li><code><?= e($urimsKey) ?></code>:
                        <?= $urimsOrigin !== null ? e($urimsOrigin) : '<span style="opacity:.65">not set</span>' ?></li>
                <?php endforeach; ?>
            </ul>
        </details>

        <pre class="setup-code" style="white-space:pre-wrap;word-break:break-word"><?= e($urimsDbError ?? 'Missing tables: ' . implode(', ', $urimsMissingTables)) ?></pre>

        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:16px">
            <?php if ($urimsDbError === null): ?>
                <a class="btn btn--primary" href="<?= e($urimsInstallUrl) ?>">Install database schema</a>
                <a class="btn btn--ghost" href="<?= e($urimsRetryUrl) ?>">Retry</a>
            <?php else: ?>
                <a class="btn btn--primary" href="<?= e($urimsRetryUrl) ?>">Retry connection</a>
                <a class="btn btn--ghost" href="<?= e($urimsInstallUrl) ?>">Run installer</a>
            <?php endif; ?>
        </div>

        <p class="text-sm text-muted" style="margin-top:16px">
            On Railway, set the five variables above on the application service and deploy the MySQL
            service credentials into them. The installer can also be run from a shell:
            <code>php database/install.php</code>.
        </p>
    </main>
</body>
</html>
    <?php
    exit;
