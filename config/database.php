<?php
declare(strict_types=1);

/**
 * Database configuration and PDO factory.
 *
 * Credential resolution order (later wins):
 *   1. built-in XAMPP defaults
 *   2. config/database.local.php (optional, never committed)
 *   3. KEY=VALUE pairs from the project-root .env file (optional)
 *   4. real process environment variables
 *
 * Environment names, in priority order per setting:
 *   host -> DB_HOST, MYSQLHOST, MYSQL_HOST, URIMS_DB_HOST
 *   port -> DB_PORT, MYSQLPORT, MYSQL_PORT, URIMS_DB_PORT
 *   name -> DB_NAME, DB_DATABASE, MYSQLDATABASE, MYSQL_DATABASE, URIMS_DB_NAME
 *   user -> DB_USER, DB_USERNAME, MYSQLUSER, MYSQL_USER, URIMS_DB_USER
 *   pass -> DB_PASS, DB_PASSWORD, MYSQLPASSWORD, MYSQL_PASSWORD, URIMS_DB_PASS
 * plus DATABASE_URL / MYSQL_URL / MYSQL_PUBLIC_URL / DB_URL connection strings.
 *
 * The process environment is read from $_ENV, $_SERVER and getenv(). PHP-FPM
 * ships with clear_env = yes, which wipes the environment of worker processes,
 * so getenv() alone returns false on many managed hosts even though the
 * platform did inject DB_HOST and friends.
 */

function db_root_path(): string
{
    return defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__);
}

/** Read a key from the real process environment, or null when empty/absent. */
function db_process_env(string $key): ?string
{
    foreach ([$_ENV, $_SERVER] as $bag) {
        if (isset($bag[$key]) && is_scalar($bag[$key])) {
            $value = trim((string) $bag[$key]);
            if ($value !== '') {
                return $value;
            }
        }
    }

    $value = getenv($key);
    if ($value !== false && trim($value) !== '') {
        return trim($value);
    }

    return null;
}

/** Whether a key exists in the process environment at all (even if empty). */
function db_process_env_defined(string $key): bool
{
    if (array_key_exists($key, $_ENV) || array_key_exists($key, $_SERVER)) {
        return true;
    }

    return getenv($key) !== false;
}

/**
 * Parse the optional project-root .env file. Values are cached for the request
 * and are never written to disk by the application.
 */
function db_env_file(): array
{
    static $values = null;
    if (is_array($values)) {
        return $values;
    }

    $values = [];
    $path = db_root_path() . '/.env';
    if (!is_file($path) || !is_readable($path)) {
        return $values;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return $values;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
            continue;
        }
        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }
        $separator = strpos($line, '=');
        if ($separator === false) {
            continue;
        }

        $key = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));
        if ($key === '') {
            continue;
        }

        $length = strlen($value);
        $quoted = $length >= 2
            && (($value[0] === '"' && $value[$length - 1] === '"')
                || ($value[0] === "'" && $value[$length - 1] === "'"));
        if ($quoted) {
            $quote = $value[0];
            $value = substr($value, 1, -1);
            if ($quote === '"') {
                $value = str_replace(['\\n', '\\r', '\\"', '\\\\'], ["\n", "\r", '"', '\\'], $value);
            }
        } else {
            $comment = strpos($value, ' #');
            if ($comment !== false) {
                $value = rtrim(substr($value, 0, $comment));
            }
        }

        $values[$key] = $value;
    }

    return $values;
}

function db_file_env(string $key): ?string
{
    $values = db_env_file();
    if (!array_key_exists($key, $values)) {
        return null;
    }

    $value = trim((string) $values[$key]);
    return $value === '' ? null : $value;
}

function db_file_env_defined(string $key): bool
{
    return array_key_exists($key, db_env_file());
}

/** Process environment first, then .env. */
function db_env_value(string $key): ?string
{
    return db_process_env($key) ?? db_file_env($key);
}

/** Split a mysql://user:pass@host:port/database URL into config keys. */
function db_config_from_url(string $url): array
{
    $url = trim($url);
    if ($url === '') {
        return [];
    }

    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return [];
    }

    $config = ['host' => (string) $parts['host']];
    if (!empty($parts['port'])) {
        $config['port'] = (int) $parts['port'];
    }
    if (!empty($parts['path']) && $parts['path'] !== '/') {
        $config['name'] = ltrim((string) $parts['path'], '/');
    }
    if (isset($parts['user']) && $parts['user'] !== '') {
        $config['user'] = rawurldecode((string) $parts['user']);
    }
    if (isset($parts['pass'])) {
        $config['pass'] = rawurldecode((string) $parts['pass']);
    }

    return $config;
}

function db_config(): array
{
    $config = [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'urims',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
        'source'  => 'built-in defaults (XAMPP: 127.0.0.1, root, urims)',
    ];

    $local = __DIR__ . '/database.local.php';
    if (is_file($local)) {
        $override = require $local;
        if (is_array($override)) {
            $config = array_merge($config, $override);
            $config['source'] = 'config/database.local.php';
        }
    }

    /* A .env file is treated as configuration, not as a live environment. */
    $fileKeys = [
        'host' => ['DB_HOST', 'MYSQLHOST', 'MYSQL_HOST', 'URIMS_DB_HOST'],
        'port' => ['DB_PORT', 'MYSQLPORT', 'MYSQL_PORT', 'URIMS_DB_PORT'],
        'name' => ['DB_NAME', 'DB_DATABASE', 'MYSQLDATABASE', 'MYSQL_DATABASE', 'URIMS_DB_NAME'],
        'user' => ['DB_USER', 'DB_USERNAME', 'MYSQLUSER', 'MYSQL_USER', 'URIMS_DB_USER'],
        'pass' => ['DB_PASS', 'DB_PASSWORD', 'MYSQLPASSWORD', 'MYSQL_PASSWORD', 'URIMS_DB_PASS'],
    ];

    foreach (['DATABASE_URL', 'MYSQL_URL', 'MYSQL_PUBLIC_URL', 'DB_URL'] as $urlKey) {
        $url = db_file_env($urlKey);
        if ($url !== null) {
            $fromUrl = db_config_from_url($url);
            if ($fromUrl !== []) {
                $config = array_merge($config, $fromUrl);
                $config['source'] = '.env (' . $urlKey . ')';
            }
        }
    }

    foreach ($fileKeys as $key => $names) {
        foreach ($names as $name) {
            $value = db_file_env($name);
            if ($value !== null) {
                $config[$key] = $value;
                $config['source'] = '.env (' . $name . ')';
                break;
            }
        }
    }

    /* Real environment variables always win: this is what Railway injects. */
    $envKeys = [
        'host' => ['DB_HOST', 'MYSQLHOST', 'MYSQL_HOST', 'URIMS_DB_HOST'],
        'port' => ['DB_PORT', 'MYSQLPORT', 'MYSQL_PORT', 'URIMS_DB_PORT'],
        'name' => ['DB_NAME', 'DB_DATABASE', 'MYSQLDATABASE', 'MYSQL_DATABASE', 'URIMS_DB_NAME'],
        'user' => ['DB_USER', 'DB_USERNAME', 'MYSQLUSER', 'MYSQL_USER', 'URIMS_DB_USER'],
        'pass' => ['DB_PASS', 'DB_PASSWORD', 'MYSQLPASSWORD', 'MYSQL_PASSWORD', 'URIMS_DB_PASS'],
    ];

    foreach (['DATABASE_URL', 'MYSQL_URL', 'MYSQL_PUBLIC_URL', 'DB_URL'] as $urlKey) {
        $url = db_process_env($urlKey);
        if ($url !== null) {
            $fromUrl = db_config_from_url($url);
            if ($fromUrl !== []) {
                $config = array_merge($config, $fromUrl);
                $config['source'] = 'environment (' . $urlKey . ')';
            }
        }
    }

    foreach ($envKeys as $key => $names) {
        foreach ($names as $name) {
            $value = db_process_env($name);
            if ($value !== null) {
                $config[$key] = $value;
                $config['source'] = 'environment (' . $name . ')';
                break;
            }
        }
    }

    $config['port'] = (int) $config['port'];
    if ($config['port'] <= 0) {
        $config['port'] = 3306;
    }

    /* Allow host values such as "db.railway.internal:3306" or "localhost:3307". */
    $host = (string) $config['host'];
    if (str_contains($host, ':') && !str_contains($host, ']')) {
        [$hostName, $hostPort] = explode(':', $host, 2);
        if ($hostName !== '' && ctype_digit($hostPort)) {
            $config['host'] = $hostName;
            $config['port'] = (int) $hostPort;
        }
    }

    return $config;
}

function db_dsn(bool $withDatabase = true): string
{
    $c = db_config();
    $dsn = "mysql:host={$c['host']};port={$c['port']};charset={$c['charset']}";
    if ($withDatabase) {
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
    }

    return $dsn;
}

function db_error_number(Throwable $e): int
{
    if ($e instanceof PDOException && isset($e->errorInfo[1])) {
        return (int) $e->errorInfo[1];
    }

    if (preg_match('/\[(\d{3,5})\]/', $e->getMessage(), $match)) {
        return (int) $match[1];
    }

    return 0;
}

/** Build an actionable message without ever exposing the password. */
function db_failure(Throwable $e, array $c = []): RuntimeException
{
    $c = $c === [] ? db_config() : $c;
    $code = db_error_number($e);

    $hints = [
        1044 => 'The database user is not allowed to use that database. Check DB_NAME and the MySQL service grants.',
        1045 => 'The username or password was rejected. Copy DB_USER and DB_PASS from the MySQL service variables; do not use the XAMPP root account.',
        1049 => 'The database does not exist and the user cannot create it. Create it in the MySQL service, or grant CREATE on it.',
        1130 => 'The MySQL server does not accept connections from this application host. Use the internal service hostname.',
        2002 => 'No MySQL server answered at ' . $c['host'] . ':' . $c['port'] . '. Use the internal host and port from the MySQL service variables, not 127.0.0.1.',
        2005 => 'The hostname could not be resolved. Check DB_HOST for typos.',
        2006 => 'The MySQL server closed the connection; it may still be starting. Retry in a few seconds.',
    ];

    $message = 'Database connection failed' . ($code !== 0 ? ' (error ' . $code . ')' : '') . ': ' . $e->getMessage();
    $message .= ' [host ' . $c['host'] . ':' . $c['port']
        . ', database "' . $c['name'] . '"'
        . ', user "' . $c['user'] . '"'
        . ', credentials from ' . ($c['source'] ?? 'unknown') . ']';
    if (isset($hints[$code])) {
        $message .= ' ' . $hints[$code];
    }

    return new RuntimeException($message, 0, $e);
}

/**
 * Connect without a database name and create the database. Only used when the
 * server answers but the database is missing, so a fresh managed MySQL service
 * works even when the user has no shell access.
 */
function db_create_database(array $c, array $options): PDO
{
    $name = (string) $c['name'];
    if (!preg_match('/^[A-Za-z0-9_$]+$/', $name)) {
        throw new RuntimeException('The configured database name "' . $name . '" contains unsupported characters.');
    }

    try {
        $server = new PDO(db_dsn(false), (string) $c['user'], (string) $c['pass'], $options);
    } catch (PDOException $e) {
        throw db_failure($e, $c);
    }

    try {
        $server->exec('CREATE DATABASE IF NOT EXISTS `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    } catch (PDOException $e) {
        throw new RuntimeException(
            'Connected to MySQL at ' . $c['host'] . ':' . $c['port'] . ', but database "' . $name
            . '" is missing and user "' . $c['user'] . '" cannot create it (error ' . db_error_number($e) . '). '
            . 'Create the database in the MySQL service or grant CREATE on it.',
            0,
            $e
        );
    }

    try {
        return new PDO(db_dsn(true), (string) $c['user'], (string) $c['pass'], $options);
    } catch (PDOException $e) {
        throw db_failure($e, $c);
    }
}

/**
 * Shared PDO handle. Throws RuntimeException with a diagnostic message when
 * the server is unreachable or the credentials are rejected.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = db_config();
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 10,
    ];

    try {
        $pdo = new PDO(db_dsn(true), (string) $c['user'], (string) $c['pass'], $options);
    } catch (PDOException $e) {
        if (db_error_number($e) !== 1049) {
            throw db_failure($e, $c);
        }
        $pdo = db_create_database($c, $options);
    }

    return $pdo;
}

/** Non-throwing probe used by the installer and the setup screen. */
function db_probe(): ?string
{
    try {
        db();

        return null;
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

/** Safe diagnostics for the setup screen (never includes the password). */
function db_connection_summary(): array
{
    $c = db_config();

    return [
        'host'         => (string) $c['host'],
        'port'         => (string) $c['port'],
        'name'         => (string) $c['name'],
        'user'         => (string) $c['user'],
        'source'       => (string) ($c['source'] ?? 'unknown'),
        'password_set' => (string) $c['pass'] !== '',
        'pdo_mysql'    => extension_loaded('pdo_mysql'),
        'php'          => PHP_VERSION,
        'env'          => db_env_report(),
    ];
}

/** Which connection variables exist and where they came from. */
function db_env_report(): array
{
    $keys = [
        'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS',
        'DATABASE_URL', 'MYSQL_URL', 'MYSQLHOST', 'MYSQLPORT', 'MYSQLDATABASE',
        'MYSQLUSER', 'MYSQLPASSWORD',
    ];

    $report = [];
    foreach ($keys as $key) {
        if (db_process_env_defined($key)) {
            $report[$key] = 'process environment';
        } elseif (db_file_env_defined($key)) {
            $report[$key] = '.env file';
        } else {
            $report[$key] = null;
        }
    }

    return $report;
}

/** Quick scalar helpers. */
function db_value(string $sql, array $params = []): mixed
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return $value === false ? null : $value;
}

function db_rows(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_row(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_exec(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}
