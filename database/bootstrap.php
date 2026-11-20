<?php
declare(strict_types=1);

/**
 * Schema bootstrap helpers shared by database/install.php and the setup screen.
 *
 * The bootstrap is idempotent: by default it only creates missing tables and
 * never drops or rewrites an existing one, so running it against a live
 * deployment preserves every existing table and row.
 */

function urims_required_tables(): array
{
    return [
        'faculties', 'departments', 'programmes', 'academic_years', 'semesters',
        'users', 'students', 'research_projects', 'proposals', 'milestones',
        'documents', 'evaluations', 'lecturer_research', 'research_teams',
        'publications', 'innovation_projects', 'innovation_teams', 'competitions',
        'judges', 'evaluation_results', 'funding_applications', 'budgets',
        'expenditure', 'notifications', 'audit_logs', 'settings', 'remember_tokens',
    ];
}

function urims_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
    );
    $stmt->execute([$table]);

    return $stmt->fetchColumn() !== false;
}

/** @return string[] required tables that are not present yet */
function urims_missing_tables(PDO $pdo): array
{
    $missing = [];
    foreach (urims_required_tables() as $table) {
        if (!urims_table_exists($pdo, $table)) {
            $missing[] = $table;
        }
    }

    return $missing;
}

/**
 * Split a SQL script into individual statements. PDO_MYSQL::exec() does not
 * reliably run multi-statement scripts (and plain PDO refuses them when
 * emulated prepares are off), so every statement is executed separately.
 *
 * @return string[]
 */
function urims_split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($inLineComment) {
            if ($char === "\n") {
                $inLineComment = false;
                $buffer .= "\n";
            }
            continue;
        }

        if ($inBlockComment) {
            if ($char === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }

        if (!$inSingle && !$inDouble && !$inBacktick) {
            if (($char === '-' && $next === '-') || $char === '#') {
                $inLineComment = true;
                if ($char === '-') {
                    $i++;
                }
                continue;
            }
            if ($char === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }
        }

        if ($char === '\\' && ($inSingle || $inDouble)) {
            $buffer .= $char . $next;
            $i++;
            continue;
        }

        if ($char === "'" && !$inDouble && !$inBacktick) {
            $inSingle = !$inSingle;
        } elseif ($char === '"' && !$inSingle && !$inBacktick) {
            $inDouble = !$inDouble;
        } elseif ($char === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
        }

        if ($char === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }

    return $statements;
}

function urims_sql_error_number(PDOException $e): int
{
    if (isset($e->errorInfo[1])) {
        return (int) $e->errorInfo[1];
    }
    if (preg_match('/\[(\d{3,5})\]/', $e->getMessage(), $match)) {
        return (int) $match[1];
    }

    return 0;
}

/**
 * Apply database/schema.sql.
 *
 * @param bool $fresh Drop and recreate every table. Never use on live data.
 * @return array{executed:int,total:int,fresh:bool,skipped:int}
 */
function urims_run_schema(PDO $pdo, bool $fresh = false): array
{
    $path = __DIR__ . '/schema.sql';
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('schema.sql could not be read from ' . $path);
    }

    $statements = urims_split_sql($sql);
    if ($statements === []) {
        throw new RuntimeException('schema.sql contains no executable statements.');
    }

    $executed = 0;
    $skipped = 0;

    foreach ($statements as $statement) {
        if (!$fresh && preg_match('/^DROP\s+(TABLE|VIEW|DATABASE)\b/i', $statement)) {
            $skipped++;
            continue;
        }

        $prepared = $statement;
        if (!$fresh && preg_match('/^CREATE\s+TABLE\s+(?!IF\s+NOT\s+EXISTS)/i', $prepared)) {
            $prepared = preg_replace('/^CREATE\s+TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', $prepared, 1) ?? $prepared;
        }

        try {
            $pdo->exec($prepared);
            $executed++;
        } catch (PDOException $e) {
            $code = urims_sql_error_number($e);
            /* Object already exists / index already exists / duplicate column. */
            if (!$fresh && in_array($code, [1050, 1060, 1061, 1091, 1826], true)) {
                $skipped++;
                continue;
            }

            $snippet = preg_replace('/\s+/', ' ', $statement) ?? $statement;
            throw new RuntimeException(
                'Schema statement failed (error ' . $code . '): ' . $e->getMessage()
                . ' | ' . substr($snippet, 0, 180),
                0,
                $e
            );
        }
    }

    return [
        'executed' => $executed,
        'total'    => count($statements),
        'fresh'    => $fresh,
        'skipped'  => $skipped,
    ];
}

/** Demo data is only loaded into a database that has no users yet. */
function urims_seed_is_needed(PDO $pdo): bool
{
    if (!urims_table_exists($pdo, 'users')) {
        return true;
    }

    $count = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

    return (int) $count === 0;
}
