<?php
declare(strict_types=1);

/**
 * Metadata driven module controller.
 * A module file only sets $RESOURCE then includes this file.
 */

$resourceKey = $RESOURCE ?? '';
$resources   = require ROOT_PATH . '/config/resources.php';
$res         = $resources[$resourceKey] ?? null;

if (!$res) {
    http_response_code(404);
    $pageTitle = 'Module not found';
    include ROOT_PATH . '/includes/header.php';
    echo '<div class="card empty-state"><h2>Module not found</h2><p>The requested module does not exist.</p>'
        . '<a class="btn btn--primary" href="' . e(url('dashboard.php')) . '">Back to dashboard</a></div>';
    include ROOT_PATH . '/includes/footer.php';
    exit;
}

$perm = $res['perm'] ?? $resourceKey;
require_permission($perm);

$self       = 'modules/' . $resourceKey . '.php';
$action     = (string) ($_GET['action'] ?? 'list');
$id         = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$canWrite   = can_write($perm) && empty($res['no_edit']);
$canCreate  = $canWrite && empty($res['no_create']);
$canDelete  = can_delete($perm) && empty($res['no_delete']);
$canEdit    = $canWrite;
$perPage    = (int) setting('records_per_page', 10);

/* ---------------------------------------------------------------
 | Option resolution for select inputs and filters
 --------------------------------------------------------------- */
function field_options(array $spec): array
{
    if (isset($spec['options']) && is_array($spec['options'])) {
        return $spec['options'];
    }
    if (isset($spec['options_from'])) {
        $source = $spec['options_from'];
        $table  = (string) $source[0];
        $label  = (string) $source[1];
        $where  = (string) ($source[2] ?? '');
        $expr   = (string) ($source[3] ?? $label);
        try {
            $sql = 'SELECT id, ' . $expr . ' AS label FROM `' . $table . '`'
                . ($where !== '' ? ' WHERE ' . $where : '')
                . ' ORDER BY label ASC';
            $options = [];
            foreach (db_rows($sql) as $row) {
                $options[(string) $row['id']] = (string) $row['label'];
            }
            return $options;
        } catch (Throwable) {
            return [];
        }
    }
    return [];
}

function form_select_options(array $spec): array
{
    return field_options($spec);
}

/* ---------------------------------------------------------------
 | Cell renderers
 --------------------------------------------------------------- */
function render_cell(array $col, array $row): string
{
    $field = $col['field'];
    $value = $row[$field] ?? null;
    $render = $col['render'] ?? 'text';
    $secondary = $col['secondary'] ?? null;
    $secondaryValue = $secondary ? ($row[$secondary] ?? '') : '';

    switch ($render) {
        case 'person':
            return '<div class="cell-person"><span class="avatar avatar--sm" aria-hidden="true">' . e(initials((string) $value)) . '</span>'
                . '<span class="cell-person__text"><span class="cell-person__name">' . e((string) ($value ?: '—')) . '</span>'
                . ($secondaryValue !== '' ? '<span class="cell-person__meta">' . e((string) $secondaryValue) . '</span>' : '')
                . '</span></div>';
        case 'title':
            return '<div class="cell-title"><span class="cell-title__main">' . e(truncate((string) $value, 74)) . '</span>'
                . ($secondaryValue !== '' ? '<span class="cell-title__meta">' . e((string) $secondaryValue) . '</span>' : '')
                . '</div>';
        case 'status':
            return status_badge((string) $value);
        case 'role':
            return role_badge((string) $value);
        case 'money':
            return '<span class="cell-money">' . e(money($value)) . '</span>';
        case 'date':
            return e(format_date((string) $value));
        case 'datetime':
            return $value ? '<span title="' . e((string) $value) . '">' . e(format_datetime((string) $value)) . '</span>' : '<span class="text-muted">—</span>';
        case 'due':
            if (!$value) {
                return '<span class="text-muted">—</span>';
            }
            $overdue = is_overdue((string) $value, (string) ($row['status'] ?? ''));
            return '<span class="' . ($overdue ? 'text-danger' : '') . '">' . e(format_date((string) $value)) . '</span>'
                . ($overdue ? ' <span class="text-xs text-danger">(overdue)</span>' : '');
        case 'progress':
            $pct = max(0, min(100, (int) $value));
            return '<div class="progress-cell"><div class="progress" role="progressbar" aria-valuenow="' . $pct . '" aria-valuemin="0" aria-valuemax="100" aria-label="Progress">'
                . '<span class="progress__bar" style="width:' . $pct . '%"></span></div><span class="progress-cell__value">' . $pct . '%</span></div>';
        case 'count':
            return '<span class="badge badge--muted">' . e((string) (int) $value) . '</span>';
        case 'score':
            $score = (int) $value;
            $tone = $score >= 80 ? 'success' : ($score >= 60 ? 'info' : ($score >= 40 ? 'warning' : 'danger'));
            return '<span class="badge badge--' . $tone . '">' . $score . ' / 100</span>';
        case 'filesize':
            return $value ? e(human_filesize((int) $value)) : '<span class="text-muted">—</span>';
        case 'daterange':
            if (!$value) {
                return '<span class="text-muted">—</span>';
            }
            $end = $row['end_date'] ?? null;
            return e(format_date((string) $value) . ($end ? ' – ' . format_date((string) $end) : ''));
        case 'utilisation':
            $allocated = (float) ($row['allocated_amount'] ?? 0);
            $spent = (float) $value;
            $pct = min(100, (int) percentage($spent, $allocated));
            $tone = $pct >= 90 ? 'warning' : 'success';
            return '<div class="progress-cell"><div class="progress progress--' . $tone . '" role="progressbar" aria-valuenow="' . $pct . '" aria-valuemin="0" aria-valuemax="100" aria-label="Budget utilisation">'
                . '<span class="progress__bar" style="width:' . $pct . '%"></span></div><span class="progress-cell__value">' . $pct . '%</span></div>';
        case 'yesno':
            return (int) $value === 1 ? status_badge('active', 'Yes') : status_badge('inactive', 'No');
        case 'code':
            return '<code class="code-chip">' . e((string) ($value ?: '—')) . '</code>';
        case 'truncate':
            return e(truncate((string) $value, 60));
        case 'years':
            return e((string) (int) $value . ' year' . ((int) $value === 1 ? '' : 's'));
        default:
            $text = (string) ($value ?? '');
            return $text === '' ? '<span class="text-muted">—</span>' : e($text);
    }
}

/* ---------------------------------------------------------------
 | Validation
 --------------------------------------------------------------- */
function validate_resource_fields(array $fields, bool $isEdit): array
{
    $errors = [];
    foreach ($fields as $name => $spec) {
        if (($spec['type'] ?? 'text') === 'file') {
            continue;
        }
        $label = $spec['label'] ?? humanize($name);
        $value = $_POST[$name] ?? '';
        $value = is_string($value) ? trim($value) : $value;

        if (($spec['type'] ?? '') === 'password') {
            if (!$isEdit && empty($spec['optional']) && $value === '') {
                $errors[$name] = $label . ' is required.';
            } elseif ($value !== '' && mb_strlen((string) $value) < 8) {
                $errors[$name] = $label . ' must be at least 8 characters.';
            }
            continue;
        }

        if (!empty($spec['required']) && ($value === '' || $value === null)) {
            $errors[$name] = $label . ' is required.';
            continue;
        }
        if ($value === '' || $value === null) {
            continue;
        }
        if (($spec['type'] ?? '') === 'email' && !filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
            $errors[$name] = 'Enter a valid email address.';
        }
        if (($spec['type'] ?? '') === 'number') {
            if (!is_numeric($value)) {
                $errors[$name] = $label . ' must be a number.';
            } else {
                if (isset($spec['min']) && (float) $value < (float) $spec['min']) {
                    $errors[$name] = $label . ' must be at least ' . $spec['min'] . '.';
                }
                if (isset($spec['max']) && (float) $value > (float) $spec['max']) {
                    $errors[$name] = $label . ' must not exceed ' . $spec['max'] . '.';
                }
            }
        }
    }
    return $errors;
}

/* ---------------------------------------------------------------
 | File upload
 --------------------------------------------------------------- */
function store_upload(string $fieldName): array
{
    if (empty($_FILES[$fieldName]) || ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null, null];
    }
    $file = $_FILES[$fieldName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, null, 'The file could not be uploaded. Please try again.'];
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        return [null, null, 'Files must be 10 MB or smaller.'];
    }
    $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'png', 'jpg', 'jpeg', 'txt', 'csv'];
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        return [null, null, 'Allowed file types: ' . implode(', ', $allowed) . '.'];
    }
    if (!is_dir(UPLOAD_PATH) && !mkdir(UPLOAD_PATH, 0775, true) && !is_dir(UPLOAD_PATH)) {
        return [null, null, 'The upload folder is not writable.'];
    }
    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $file['name']);
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safe;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_PATH . '/' . $name)) {
        return [null, null, 'The file could not be saved on the server.'];
    }
    return ['uploads/' . $name, (int) $file['size'], null];
}

/* ---------------------------------------------------------------
 | Save (create + update)
 --------------------------------------------------------------- */
$formValues = [];
$formErrors = [];
$currentRow = null;

if ($action === 'edit' || $action === 'view' || $action === 'delete' || $action === 'download') {
    if ($id > 0) {
        $currentRow = db_row('SELECT ' . $res['select'] . ' FROM ' . $res['from'] . ' WHERE t.id = ?', [$id]);
    }
    if (!$currentRow && $action !== 'list') {
        flash('danger', 'That record could not be found.');
        redirect($self);
    }
}

if (is_post() && in_array($action, ['create', 'edit'], true)) {
    $csrfError = verify_csrf();
    if ($csrfError) {
        $formErrors['_form'] = $csrfError;
    } else {
        $fields = $res['fields'] ?? [];
        $formErrors = validate_resource_fields($fields, $action === 'edit');
        $values = [];

        foreach ($fields as $name => $spec) {
            $type = $spec['type'] ?? 'text';
            if ($type === 'file') {
                continue;
            }
            $raw = $_POST[$name] ?? null;
            $raw = is_string($raw) ? trim($raw) : $raw;

            if ($type === 'password') {
                if ($raw !== '' && $raw !== null) {
                    $values['password_hash'] = password_hash((string) $raw, PASSWORD_DEFAULT);
                }
                continue;
            }
            if ($raw === '' || $raw === null) {
                $values[$name] = !empty($spec['nullable']) ? null : (isset($spec['default']) && $action === 'create' ? $spec['default'] : null);
                continue;
            }
            if ($type === 'number') {
                $values[$name] = $raw + 0;
            } elseif ($type === 'datetime-local') {
                $values[$name] = date('Y-m-d H:i:s', strtotime((string) $raw));
            } else {
                $values[$name] = $raw;
            }
        }

        if (isset($res['compute_sum'])) {
            $total = 0;
            foreach ($res['compute_sum']['parts'] as $part) {
                $total += (int) ($values[$part] ?? 0);
            }
            $values[$res['compute_sum']['target']] = $total;
        }

        foreach ($fields as $name => $spec) {
            if (($spec['type'] ?? '') === 'file') {
                [$path, $size, $uploadError] = store_upload($name);
                if ($uploadError) {
                    $formErrors[$name] = $uploadError;
                }
                if ($path) {
                    $values[$res['file_column'] ?? 'file_path'] = $path;
                    $values['file_size'] = $size;
                }
            }
        }

        if (!$formErrors) {
            try {
                if ($action === 'create') {
                    $columns = array_keys($values);
                    $sql = 'INSERT INTO `' . $res['table'] . '` (`' . implode('`,`', $columns) . '`) VALUES ('
                        . implode(',', array_fill(0, count($columns), '?')) . ')';
                    db_exec($sql, array_values($values));
                    audit_log('create', $resourceKey, 'Created ' . strtolower($res['singular']) . ' record #' . db()->lastInsertId());
                    flash('success', $res['singular'] . ' created successfully.');
                } else {
                    $assignments = [];
                    foreach (array_keys($values) as $column) {
                        $assignments[] = '`' . $column . '` = ?';
                    }
                    $params = array_values($values);
                    $params[] = $id;
                    db_exec('UPDATE `' . $res['table'] . '` SET ' . implode(', ', $assignments) . ' WHERE id = ?', $params);
                    audit_log('update', $resourceKey, 'Updated ' . strtolower($res['singular']) . ' #' . $id);
                    flash('success', $res['singular'] . ' updated successfully.');
                }
                redirect($res['return_to'] ?? $self);
            } catch (PDOException $e) {
                $message = 'The record could not be saved.';
                if ($e->getCode() === '23000') {
                    $message = 'A record with these unique details already exists (email, username or code).';
                }
                $formErrors['_form'] = $message;
            }
        }
        $formValues = $_POST;
    }
}

if (is_post() && $action === 'delete') {
    $csrfError = verify_csrf();
    if ($csrfError) {
        flash('danger', $csrfError);
        redirect($self);
    }
    if (!$canDelete) {
        flash('danger', 'You do not have permission to delete this record.');
        redirect($self);
    }
    try {
        db_exec('DELETE FROM `' . $res['table'] . '` WHERE id = ?', [$id]);
        audit_log('delete', $resourceKey, 'Deleted ' . strtolower($res['singular']) . ' #' . $id);
        flash('success', $res['singular'] . ' deleted.');
    } catch (PDOException $e) {
        flash('danger', 'This record is linked to other data and cannot be deleted.');
    }
    redirect($self);
}

/* ---------------------------------------------------------------
 | Download
 --------------------------------------------------------------- */
if ($action === 'download' && !empty($res['download'])) {
    $relative = (string) ($currentRow[$res['download']] ?? '');
    $absolute = realpath(ROOT_PATH . '/' . ltrim($relative, '/'));
    if ($relative !== '' && $absolute && str_starts_with($absolute, realpath(UPLOAD_PATH) ?: UPLOAD_PATH) && is_file($absolute)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($absolute) . '"');
        header('Content-Length: ' . (string) filesize($absolute));
        readfile($absolute);
        exit;
    }
    flash('danger', 'The file for this record is not available.');
    redirect($self);
}

/* ---------------------------------------------------------------
 | List query
 --------------------------------------------------------------- */
$where  = [];
$params = [];
$where[] = $res['base_where'] ?? '1 = 1';

if (!empty($res['scope'])) {
    [$scopeSql, $scopeParams] = $res['scope'](current_user() ?? [], current_role(), 't');
    $where[] = $scopeSql;
    $params  = array_merge($params, $scopeParams);
}

$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '' && !empty($res['search'])) {
    $like = [];
    foreach ($res['search'] as $col) {
        $like[] = $col . ' LIKE ?';
        $params[] = '%' . $q . '%';
    }
    $where[] = '(' . implode(' OR ', $like) . ')';
}

$activeFilters = [];
foreach (($res['filters'] ?? []) as $key => $filter) {
    $value = (string) ($_GET['f_' . $key] ?? '');
    if ($value === '') {
        continue;
    }
    $options = field_options($filter);
    if ($options && !array_key_exists($value, $options)) {
        continue;
    }
    $activeFilters[$key] = $value;
    $where[] = $filter['field'] . ' = ?';
    $params[] = $value;
}

$whereSql = implode(' AND ', $where);

$sorts = $res['sortable'] ?? [];
$sortKey = (string) ($_GET['sort'] ?? '');
$sortDir = strtolower((string) ($_GET['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
$orderSql = $res['order'] ?? 't.id DESC';
if ($sortKey !== '' && isset($sorts[$sortKey])) {
    $orderSql = $sortKey . ' ' . $sortDir;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$total = (int) db_value('SELECT COUNT(*) FROM ' . $res['from'] . ' WHERE ' . $whereSql, $params);
$pager = paginate($total, $perPage, $page);
$rows = db_rows(
    'SELECT ' . $res['select'] . ' FROM ' . $res['from'] . ' WHERE ' . $whereSql
    . ' ORDER BY ' . $orderSql . ' LIMIT ' . $pager['per_page'] . ' OFFSET ' . $pager['offset'],
    $params
);

/* ---------------------------------------------------------------
 | Page setup
 --------------------------------------------------------------- */
$groupLabels = [
    'university' => 'University Management',
    'academic' => 'Academic Research',
    'institutional' => 'Institutional Research',
    'innovation' => 'Innovation',
    'finance' => 'Finance',
    'system' => 'System',
];
$pageTitle = $res['title'];
$pageDescription = $res['description'];
$breadcrumbs = [
    ['label' => $groupLabels[$res['group'] ?? ''] ?? 'Modules', 'url' => null],
    ['label' => $res['title']],
];

$singular = $res['singular'];
$formTitle = $action === 'edit' ? 'Edit ' . $singular : 'New ' . $singular;

$pageActions = '';
if ($action === 'list' && $canCreate) {
    $pageActions = '<a class="btn btn--primary" href="' . e(url($self . '?action=create')) . '">' . icon('plus', 18) . 'Add ' . e($singular) . '</a>';
}

include ROOT_PATH . '/includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <div class="card card--flush">
        <div class="toolbar">
            <form class="toolbar__search" method="get" action="<?= e(url($self)) ?>" role="search">
                <label class="sr-only" for="module-search">Search <?= e(strtolower($res['title'])) ?></label>
                <span class="field-icon"><?= icon('search', 18) ?></span>
                <input id="module-search" class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search <?= e(strtolower($res['title'])) ?>…">
                <?php foreach ($activeFilters as $key => $value): ?>
                    <input type="hidden" name="f_<?= e($key) ?>" value="<?= e($value) ?>">
                <?php endforeach; ?>
                <?php if ($sortKey !== ''): ?><input type="hidden" name="sort" value="<?= e($sortKey) ?>"><input type="hidden" name="dir" value="<?= e(strtolower($sortDir)) ?>"><?php endif; ?>
                <button class="btn btn--secondary" type="submit">Search</button>
                <?php if ($q !== '' || $activeFilters): ?>
                    <a class="btn btn--ghost" href="<?= e(url($self)) ?>">Clear</a>
                <?php endif; ?>
            </form>

            <?php if (!empty($res['filters'])): ?>
                <form class="toolbar__filters" method="get" action="<?= e(url($self)) ?>">
                    <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
                    <?php foreach ($res['filters'] as $key => $filter):
                        $options = field_options($filter);
                        if (!$options) { continue; }
                        ?>
                        <label class="sr-only" for="filter-<?= e($key) ?>"><?= e($filter['label']) ?></label>
                        <select class="select select--sm" id="filter-<?= e($key) ?>" name="f_<?= e($key) ?>" onchange="this.form.submit()">
                            <option value=""><?= e($filter['label']) ?>: All</option>
                            <?php foreach ($options as $value => $label): ?>
                                <option value="<?= e((string) $value) ?>" <?= ($activeFilters[$key] ?? '') === (string) $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endforeach; ?>
                    <noscript><button class="btn btn--secondary btn--sm" type="submit">Apply</button></noscript>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($rows): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <?php foreach ($res['columns'] as $col):
                                $sortable = isset($col['sort'], $sorts[$col['sort']]);
                                $isSorted = $sortable && $sortKey === $col['sort'];
                                $nextDir = $isSorted && $sortDir === 'ASC' ? 'desc' : 'asc';
                                ?>
                                <th scope="col" <?= $col['render'] === 'money' || $col['render'] === 'count' || $col['render'] === 'score' ? 'class="text-right"' : '' ?>>
                                    <?php if ($sortable): ?>
                                        <a class="th-sort <?= $isSorted ? 'is-sorted' : '' ?>" href="?<?= e(query_string(['sort' => $col['sort'], 'dir' => $nextDir, 'page' => 1])) ?>">
                                            <?= e($col['label']) ?>
                                            <?= $isSorted ? icon($sortDir === 'ASC' ? 'arrow-up' : 'arrow-down', 14) : '' ?>
                                        </a>
                                    <?php else: ?>
                                        <?= e($col['label']) ?>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                            <th scope="col" class="text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach ($res['columns'] as $col): ?>
                                    <td data-label="<?= e($col['label']) ?>" class="<?= in_array($col['render'] ?? '', ['money', 'count', 'score'], true) ? 'text-right' : '' ?>">
                                        <?= render_cell($col, $row) ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="text-right" data-label="Actions">
                                    <div class="row-actions">
                                        <a class="icon-btn" href="<?= e(url($self . '?action=view&id=' . (int) $row['id'])) ?>" title="View <?= e(strtolower($singular)) ?>" aria-label="View <?= e($row['id']) ?>"><?= icon('eye', 17) ?></a>
                                        <?php if ($canEdit): ?>
                                            <a class="icon-btn" href="<?= e(url($self . '?action=edit&id=' . (int) $row['id'])) ?>" title="Edit <?= e(strtolower($singular)) ?>" aria-label="Edit <?= e($row['id']) ?>"><?= icon('edit', 17) ?></a>
                                        <?php endif; ?>
                                        <?php if (!empty($res['download']) && !empty($row[$res['download']])): ?>
                                            <a class="icon-btn" href="<?= e(url($self . '?action=download&id=' . (int) $row['id'])) ?>" title="Download file" aria-label="Download file"><?= icon('download', 17) ?></a>
                                        <?php endif; ?>
                                        <?php if ($canDelete): ?>
                                            <button type="button" class="icon-btn icon-btn--danger" title="Delete <?= e(strtolower($singular)) ?>" aria-label="Delete <?= e($row['id']) ?>"
                                                data-confirm-delete
                                                data-confirm-title="Delete <?= e($singular) ?>?"
                                                data-confirm-message="This action cannot be undone. The record will be permanently removed."
                                                data-form="delete-form-<?= (int) $row['id'] ?>"><?= icon('trash', 17) ?></button>
                                            <form id="delete-form-<?= (int) $row['id'] ?>" method="post" action="<?= e(url($self . '?action=delete&id=' . (int) $row['id'])) ?>" class="hidden">
                                                <?= csrf_field() ?>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <p class="text-sm text-secondary">Showing <strong><?= $pager['from'] ?>–<?= $pager['to'] ?></strong> of <strong><?= $pager['total'] ?></strong> <?= e(strtolower($res['title'])) ?></p>
                <?= pagination_links($pager) ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state__icon"><?= icon($res['icon'] ?? 'folder', 26) ?></div>
                <h2><?= $q !== '' || $activeFilters ? 'No matching records' : 'No ' . e(strtolower($res['title'])) . ' yet' ?></h2>
                <p class="text-secondary">
                    <?= $q !== '' || $activeFilters
                        ? 'Try a different search term or clear the active filters.'
                        : 'Records you add will appear here with full search, filter and status tracking.' ?>
                </p>
                <?php if ($q !== '' || $activeFilters): ?>
                    <a class="btn btn--secondary" href="<?= e(url($self)) ?>">Clear search &amp; filters</a>
                <?php elseif ($canCreate): ?>
                    <a class="btn btn--primary" href="<?= e(url($self . '?action=create')) ?>"><?= icon('plus', 18) ?>Add <?= e($singular) ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($action === 'create' || $action === 'edit'): ?>
    <?php if (!$canWrite): ?>
        <div class="alert alert--danger"><?= icon('lock', 18) ?><div>Your role can view this module but not change its records.</div></div>
    <?php else: ?>
        <form method="post" action="<?= e(url($self . '?action=' . $action . ($action === 'edit' ? '&id=' . $id : ''))) ?>" class="form-layout" novalidate>
            <?= csrf_field() ?>
            <div class="form-layout__main">
                <?php if (!empty($formErrors['_form'])): ?>
                    <div class="alert alert--danger" role="alert"><?= icon('alert', 18) ?><div><?= e($formErrors['_form']) ?></div></div>
                <?php endif; ?>

                <?php
                $sections = [];
                foreach (($res['fields'] ?? []) as $name => $spec) {
                    $sections[$spec['section'] ?? 'Details'][$name] = $spec;
                }
                foreach ($sections as $sectionName => $fields):
                    ?>
                    <section class="card">
                        <div class="card__header">
                            <div>
                                <h2 class="card__title"><?= e($sectionName) ?></h2>
                                <p class="card__subtitle"><?= e(in_array($sectionName, ['Access', 'Account details'], true) ? 'Account and access information for this user.' : 'Complete the fields below. Required fields are marked with an asterisk.') ?></p>
                            </div>
                        </div>
                        <div class="card__body">
                            <div class="form-grid">
                                <?php foreach ($fields as $name => $spec):
                                    $type = $spec['type'] ?? 'text';
                                    $fieldId = 'field-' . $name;
                                    $value = $formValues[$name] ?? ($currentRow[$name] ?? ($spec['default'] ?? ''));
                                    $error = $formErrors[$name] ?? '';
                                    $col = (int) ($spec['col'] ?? 6);
                                    $required = !empty($spec['required']);
                                    ?>
                                    <div class="form-group form-group--span-<?= $col ?>">
                                        <label class="label" for="<?= e($fieldId) ?>">
                                            <?= e($spec['label'] ?? humanize($name)) ?>
                                            <?php if ($required): ?><span class="label__required" aria-hidden="true">*</span><span class="sr-only">(required)</span><?php endif; ?>
                                        </label>

                                        <?php if ($type === 'textarea'): ?>
                                            <textarea class="textarea <?= $error ? 'is-invalid' : '' ?>" id="<?= e($fieldId) ?>" name="<?= e($name) ?>" rows="4"
                                                <?= $required ? 'required' : '' ?> aria-describedby="<?= e($fieldId) ?>-help"><?= e((string) $value) ?></textarea>
                                        <?php elseif ($type === 'select'): ?>
                                            <?php $options = form_select_options($spec); ?>
                                            <select class="select <?= $error ? 'is-invalid' : '' ?>" id="<?= e($fieldId) ?>" name="<?= e($name) ?>" <?= $required ? 'required' : '' ?>>
                                                <option value=""><?= !empty($spec['required']) ? 'Select ' . e(strtolower($spec['label'] ?? $name)) . '…' : '— None —' ?></option>
                                                <?php foreach ($options as $optionValue => $optionLabel): ?>
                                                    <option value="<?= e((string) $optionValue) ?>" <?= (string) $value === (string) $optionValue ? 'selected' : '' ?>><?= e($optionLabel) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php elseif ($type === 'password'): ?>
                                            <div class="input-group">
                                                <input class="input <?= $error ? 'is-invalid' : '' ?>" type="password" id="<?= e($fieldId) ?>" name="<?= e($name) ?>" autocomplete="new-password" <?= $required && $action === 'create' ? 'required' : '' ?>>
                                                <button class="input-group__btn" type="button" data-toggle-password="<?= e($fieldId) ?>" aria-label="Show password" aria-pressed="false"><?= icon('eye', 18) ?></button>
                                            </div>
                                        <?php elseif ($type === 'file'): ?>
                                            <input class="input input--file" type="file" id="<?= e($fieldId) ?>" name="<?= e($name) ?>" aria-describedby="<?= e($fieldId) ?>-help">
                                            <?php if (!empty($currentRow['file_path'])): ?>
                                                <p class="help-text">Current file: <a href="<?= e(url($self . '?action=download&id=' . $id)) ?>"><?= e(basename((string) $currentRow['file_path'])) ?></a></p>
                                            <?php endif; ?>
                                        <?php elseif ($type === 'number'): ?>
                                            <input class="input <?= $error ? 'is-invalid' : '' ?>" type="number" id="<?= e($fieldId) ?>" name="<?= e($name) ?>" value="<?= e((string) $value) ?>"
                                                <?= isset($spec['min']) ? 'min="' . e((string) $spec['min']) . '"' : '' ?>
                                                <?= isset($spec['max']) ? 'max="' . e((string) $spec['max']) . '"' : '' ?>
                                                <?= isset($spec['step']) ? 'step="' . e((string) $spec['step']) . '"' : '' ?>
                                                <?= $required ? 'required' : '' ?> <?= !empty($spec['readonly']) ? 'readonly' : '' ?>
                                                <?= !empty($spec['readonly']) ? 'data-computed-total' : 'data-validate-number' ?>>
                                        <?php else: ?>
                                            <input class="input <?= $error ? 'is-invalid' : '' ?>" type="<?= e($type) ?>" id="<?= e($fieldId) ?>" name="<?= e($name) ?>"
                                                value="<?= $type === 'password' ? '' : e((string) $value) ?>"
                                                <?= $required ? 'required' : '' ?>
                                                <?= !empty($spec['placeholder']) ? 'placeholder="' . e((string) $spec['placeholder']) . '"' : '' ?>
                                                <?= $type === 'email' ? 'autocomplete="email"' : '' ?>
                                                <?= !empty($spec['readonly']) ? 'readonly' : '' ?>>
                                        <?php endif; ?>

                                        <?php if ($error): ?>
                                            <p class="field-error" id="<?= e($fieldId) ?>-error" role="alert"><?= e($error) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($spec['help'])): ?>
                                            <p class="help-text" id="<?= e($fieldId) ?>-help"><?= e($spec['help']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>

            <aside class="form-layout__aside">
                <div class="card">
                    <div class="card__header"><h2 class="card__title">Save changes</h2></div>
                    <div class="card__body">
                        <p class="text-sm text-secondary">Fields marked with an asterisk are required. Changes are recorded in the audit trail.</p>
                        <div class="stack">
                            <button class="btn btn--primary btn--block" type="submit"><?= icon('check', 18) ?><?= $action === 'edit' ? 'Save changes' : 'Create ' . e($singular) ?></button>
                            <a class="btn btn--secondary btn--block" href="<?= e(url($self)) ?>">Cancel</a>
                        </div>
                    </div>
                </div>
            </aside>
        </form>
    <?php endif; ?>

<?php elseif ($action === 'view'): ?>
    <div class="detail-layout">
        <div class="card">
            <div class="card__header">
                <div>
                    <p class="eyebrow"><?= e($singular) ?> #<?= (int) $currentRow['id'] ?></p>
                    <h2 class="card__title"><?= e(truncate((string) ($currentRow['title'] ?? $currentRow['name'] ?? $currentRow['category'] ?? $currentRow['reference'] ?? ($singular . ' #' . $id)), 110)) ?></h2>
                </div>
                <div class="cluster">
                    <?php if ($canEdit): ?>
                        <a class="btn btn--secondary btn--sm" href="<?= e(url($self . '?action=edit&id=' . $id)) ?>"><?= icon('edit', 16) ?>Edit</a>
                    <?php endif; ?>
                    <a class="btn btn--ghost btn--sm" href="<?= e(url($self)) ?>"><?= icon('chevron-left', 16) ?>Back to list</a>
                </div>
            </div>
            <div class="card__body">
                <?php if (!empty($res['detail'])): ?>
                    <dl class="detail-list">
                        <?php foreach ($res['detail'] as $label => $fieldName):
                            $value = $currentRow[$fieldName] ?? null;
                            $isMoney = in_array($fieldName, $res['detail_money'] ?? [], true);
                            ?>
                            <div class="detail-list__item">
                                <dt><?= e($label) ?></dt>
                                <dd>
                                    <?php if (in_array($fieldName, ['status', 'recommendation', 'stage'], true)): ?>
                                        <?= status_badge((string) $value) ?>
                                    <?php elseif ($isMoney): ?>
                                        <?= e(money($value)) ?>
                                    <?php elseif (in_array($fieldName, ['created_at', 'submitted_at', 'decision_at', 'evaluated_at', 'start_date', 'end_date', 'due_date', 'spent_on', 'last_login_at'], true)): ?>
                                        <?= e(in_array($fieldName, ['start_date', 'end_date', 'due_date', 'spent_on'], true) ? format_date((string) $value) : format_datetime((string) $value)) ?>
                                    <?php elseif ($fieldName === 'progress'): ?>
                                        <?= (int) $value ?>%
                                    <?php elseif (in_array($fieldName, ['abstract', 'comments', 'description', 'purpose', 'remarks', 'notes'], true)): ?>
                                        <p class="prose"><?= nl2br(e((string) ($value ?: '—'))) ?></p>
                                    <?php else: ?>
                                        <?= e((string) ($value ?? '') !== '' ? (string) $value : '—') ?>
                                    <?php endif; ?>
                                </dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php else: ?>
                    <p class="text-secondary">No additional details are recorded for this module.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
