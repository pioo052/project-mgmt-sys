<?php
declare(strict_types=1);

/**
 * PDO connection factory. Credentials can be overridden with
 * config/database.local.php or the URIMS_DB_* environment variables.
 */

function db_config(): array
{
    $config = [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'urims',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ];

    $local = __DIR__ . '/database.local.php';
    if (is_file($local)) {
        $override = require $local;
        if (is_array($override)) {
            $config = array_merge($config, $override);
        }
    }

    $envMap = [
        'host' => 'URIMS_DB_HOST',
        'port' => 'URIMS_DB_PORT',
        'name' => 'URIMS_DB_NAME',
        'user' => 'URIMS_DB_USER',
        'pass' => 'URIMS_DB_PASS',
    ];
    foreach ($envMap as $key => $env) {
        $value = getenv($env);
        if ($value !== false && $value !== '') {
            $config[$key] = $value;
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

/**
 * Shared PDO handle. Throws RuntimeException when the server is unreachable
 * or the schema has not been installed yet.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = db_config();
    try {
        $pdo = new PDO(db_dsn(true), (string) $c['user'], (string) $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        throw new RuntimeException(
            'Database connection failed. Check config/database.php and run database/install.php.',
            0,
            $e
        );
    }

    return $pdo;
}

/** Non-throwing probe used by the installer and setup screen. */
function db_probe(): ?string
{
    try {
        db();
        return null;
    } catch (Throwable $e) {
        return $e->getMessage();
    }
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
