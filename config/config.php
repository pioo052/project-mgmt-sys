<?php
declare(strict_types=1);

/**
 * URIMS – University Research & Innovation Management System
 * Core application configuration.
 */

define('APP_NAME', 'URIMS');
define('APP_FULL_NAME', 'University Research & Innovation Management System');
define('APP_INSTITUTION', 'Kampala International University in Tanzania');
define('APP_INSTITUTION_SHORT', 'KIUT');
define('APP_TAGLINE', 'Research & Innovation Management');
define('APP_VERSION', '1.0.0');

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

/* ---------------------------------------------------------------
 | Base path / URL resolution (works in subdirectories + CLI)
 --------------------------------------------------------------- */
$urimsBaseOverride = getenv('URIMS_BASE_PATH');
if ($urimsBaseOverride !== false && $urimsBaseOverride !== '') {
    $urimsBasePath = rtrim(str_replace('\\', '/', $urimsBaseOverride), '/');
} else {
    $urimsDocRoot  = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\', '/', (string) $_SERVER['DOCUMENT_ROOT']), '/') : '';
    $urimsRootPath = str_replace('\\', '/', ROOT_PATH);
    if ($urimsDocRoot !== '' && $urimsDocRoot !== '/' && str_starts_with($urimsRootPath, $urimsDocRoot)) {
        $urimsBasePath = rtrim(substr($urimsRootPath, strlen($urimsDocRoot)), '/');
    } else {
        $urimsBasePath = '';
    }
}
$urimsBasePath = rtrim($urimsBasePath, '/');
define('BASE_PATH', $urimsBasePath);

$urimsHttps  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$urimsScheme = $urimsHttps ? 'https' : 'http';
$urimsHost   = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $urimsScheme . '://' . $urimsHost . BASE_PATH);

/* ---------------------------------------------------------------
 | Runtime
 --------------------------------------------------------------- */
date_default_timezone_set('Africa/Dar_es_Salaam');
define('APP_TIMEZONE', 'Africa/Dar_es_Salaam');
define('APP_DATE_FORMAT', 'd M Y');
define('APP_CURRENCY', 'TZS');
define('DEFAULT_THEME', 'light');
define('CSRF_KEY', '_urims_csrf');
define('CSRF_TTL', 7200);
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('MAX_UPLOAD_BYTES', 10 * 1024 * 1024);

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $urimsHttps,
        'path'     => BASE_PATH === '' ? '/' : BASE_PATH . '/',
    ]);
    session_name('URIMSSESSID');
    session_start();
}

/* ---------------------------------------------------------------
 | Small helpers that the rest of the bootstrap relies on
 --------------------------------------------------------------- */
require_once __DIR__ . '/database.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/icons.php';
require_once __DIR__ . '/permissions.php';
require_once ROOT_PATH . '/includes/auth.php';
