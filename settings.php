<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_permission('settings');

$settingFields = [
    'site_name' => ['label' => 'Application short name', 'type' => 'text', 'required' => true, 'col' => 4],
    'site_full_name' => ['label' => 'Application full name', 'type' => 'text', 'col' => 8],
    'institution' => ['label' => 'Institution', 'type' => 'text', 'col' => 6],
    'support_email' => ['label' => 'Support email', 'type' => 'email', 'col' => 6],
    'research_office_email' => ['label' => 'Research office email', 'type' => 'email', 'col' => 6],
    'financial_year' => ['label' => 'Current financial year', 'type' => 'text', 'col' => 3],
    'default_currency' => ['label' => 'Currency code', 'type' => 'text', 'col' => 3],
    'records_per_page' => ['label' => 'Records per page', 'type' => 'number', 'min' => 5, 'max' => 100, 'col' => 4],
    'submission_notice' => ['label' => 'Submission notice', 'type' => 'textarea', 'col' => 12],
];

$errors = [];

if (is_post()) {
    $csrfError = verify_csrf();
    if ($csrfError) {
        $errors['_form'] = $csrfError;
    } else {
        foreach ($settingFields as $key => $field) {
            $value = trim((string) ($_POST[$key] ?? ''));
            if (!empty($field['required']) && $value === '') {
                $errors[$key] = $field['label'] . ' is required.';
                continue;
            }
            if ($field['type'] === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$key] = 'Enter a valid email address.';
                continue;
            }
            if ($field['type'] === 'number') {
                if (!is_numeric($value) || (int) $value < (int) $field['min'] || (int) $value > (int) $field['max']) {
                    $errors[$key] = $field['label'] . ' must be between ' . $field['min'] . ' and ' . $field['max'] . '.';
                    continue;
                }
                $value = (string) (int) $value;
            }
        }

        if (!$errors) {
            foreach (array_keys($settingFields) as $key) {
                db_exec(
                    'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                    [$key, trim((string) ($_POST[$key] ?? ''))]
                );
            }
            audit_log('update', 'settings', 'Updated system settings');
            flash('success', 'System settings saved.');
            redirect('settings.php');
        }
    }
}

$pageTitle = 'Settings';
$pageDescription = 'Institution details, contact addresses and application defaults used across URIMS.';
$breadcrumbs = [['label' => 'System'], ['label' => 'Settings']];
$activeNav = 'settings';

include ROOT_PATH . '/includes/header.php';
?>

<?php if (isset($errors['_form'])): ?>
    <div class="alert alert--danger" role="alert"><?= icon('alert', 18) ?><div><?= e($errors['_form']) ?></div></div>
<?php endif; ?>

<form method="post" action="<?= e(url('settings.php')) ?>" class="form-layout" novalidate>
    <?= csrf_field() ?>
    <div class="form-layout__main">
        <section class="card">
            <div class="card__header">
                <div>
                    <h2 class="card__title">Institution &amp; contact</h2>
                    <p class="card__subtitle">Displayed on the sign-in screen, navigation and printable reports.</p>
                </div>
            </div>
            <div class="card__body">
                <div class="form-grid">
                    <?php foreach ($settingFields as $key => $field):
                        $value = (string) setting($key, '');
                        $error = $errors[$key] ?? '';
                        $id = 'setting-' . $key;
                        ?>
                        <div class="form-group form-group--span-<?= (int) $field['col'] ?>">
                            <label class="label" for="<?= e($id) ?>"><?= e($field['label']) ?><?= !empty($field['required']) ? ' <span class="label__required" aria-hidden="true">*</span>' : '' ?></label>
                            <?php if ($field['type'] === 'textarea'): ?>
                                <textarea class="textarea <?= $error ? 'is-invalid' : '' ?>" id="<?= e($id) ?>" name="<?= e($key) ?>" rows="3"><?= e($value) ?></textarea>
                            <?php else: ?>
                                <input class="input <?= $error ? 'is-invalid' : '' ?>" type="<?= e($field['type']) ?>" id="<?= e($id) ?>" name="<?= e($key) ?>"
                                       value="<?= e($value) ?>"
                                       <?= isset($field['min']) ? 'min="' . (int) $field['min'] . '"' : '' ?>
                                       <?= isset($field['max']) ? 'max="' . (int) $field['max'] . '"' : '' ?>
                                       <?= !empty($field['required']) ? 'required' : '' ?>>
                            <?php endif; ?>
                            <?php if ($error): ?><p class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card__header">
                <div>
                    <h2 class="card__title">System information</h2>
                    <p class="card__subtitle">Runtime details for support and diagnostics.</p>
                </div>
            </div>
            <div class="card__body">
                <dl class="detail-list">
                    <div class="detail-list__item"><dt>Application version</dt><dd><?= e(APP_VERSION) ?></dd></div>
                    <div class="detail-list__item"><dt>PHP version</dt><dd><?= e(PHP_VERSION) ?></dd></div>
                    <div class="detail-list__item"><dt>Database</dt><dd>MySQL <?= e((string) (db_value('SELECT VERSION()') ?? 'unknown')) ?></dd></div>
                    <div class="detail-list__item"><dt>Database name</dt><dd><?= e((string) db_config()['name']) ?></dd></div>
                    <div class="detail-list__item"><dt>Server software</dt><dd><?= e((string) ($_SERVER['SERVER_SOFTWARE'] ?? 'CLI')) ?></dd></div>
                    <div class="detail-list__item"><dt>Timezone</dt><dd><?= e(APP_TIMEZONE) ?></dd></div>
                    <div class="detail-list__item"><dt>Maximum upload size</dt><dd><?= e(ini_get('upload_max_filesize') ?: 'unknown') ?></dd></div>
                    <div class="detail-list__item"><dt>Environment</dt><dd><?= e((string) ($_SERVER['SERVER_NAME'] ?? 'local')) ?></dd></div>
                </dl>
            </div>
        </section>
    </div>

    <aside class="form-layout__aside">
        <div class="card">
            <div class="card__header"><h2 class="card__title">Save settings</h2></div>
            <div class="card__body">
                <p class="text-sm text-secondary">Changes apply immediately across every module and both colour themes.</p>
                <div class="stack">
                    <button class="btn btn--primary btn--block" type="submit"><?= icon('check', 18) ?>Save settings</button>
                    <a class="btn btn--secondary btn--block" href="<?= e(url('dashboard.php')) ?>">Cancel</a>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card__header"><h2 class="card__title">Data &amp; access</h2></div>
            <div class="card__body">
                <p class="text-sm text-secondary">Access to this module is restricted to system administrators. All configuration changes are written to the audit trail.</p>
                <a class="btn btn--secondary btn--block btn--sm" href="<?= e(url('modules/audit_logs.php')) ?>"><?= icon('audit', 16) ?>View audit logs</a>
            </div>
        </div>
    </aside>
</form>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
