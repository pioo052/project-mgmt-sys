<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_login();

$user = current_user();
$userId = (int) $user['id'];
$profileErrors = [];
$passwordErrors = [];

if (is_post()) {
    $csrfError = verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($csrfError) {
        if ($action === 'change_password') {
            $passwordErrors['_form'] = $csrfError;
        } else {
            $profileErrors['_form'] = $csrfError;
        }
    } elseif ($action === 'update_profile') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));

        if ($name === '') {
            $profileErrors['name'] = 'Full name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profileErrors['email'] = 'Enter a valid email address.';
        } else {
            $existing = db_row('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $userId]);
            if ($existing) {
                $profileErrors['email'] = 'That email address is already used by another account.';
            }
        }

        if (!$profileErrors) {
            db_exec('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?', [$name, $email, $phone, $userId]);
            audit_log('update', 'profile', 'Updated personal profile details');
            flash('success', 'Your profile has been updated.');
            redirect('profile.php');
        }
    } elseif ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if (!password_verify($current, (string) $user['password_hash'])) {
            $passwordErrors['current_password'] = 'Your current password is not correct.';
        }
        if (mb_strlen($new) < 8) {
            $passwordErrors['new_password'] = 'The new password must be at least 8 characters.';
        }
        if ($new !== $confirm) {
            $passwordErrors['confirm_password'] = 'The two new passwords do not match.';
        }

        if (!$passwordErrors) {
            db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $userId]);
            db_exec('DELETE FROM remember_tokens WHERE user_id = ?', [$userId]);
            audit_log('update', 'profile', 'Changed account password');
            flash('success', 'Your password has been changed. Other devices will need to sign in again.');
            redirect('profile.php');
        }
    }
}

$user = current_user(true);
$projectCount = 0;
if (in_array((string) $user['role'], ['student', 'supervisor'], true)) {
    $projectCount = (int) db_value(
        'SELECT COUNT(*) FROM research_projects WHERE ' . ($user['role'] === 'supervisor' ? 'supervisor_id' : 'student_id IN (SELECT id FROM students WHERE user_id = ?)') . '',
        [(int) $user['id']]
    );
}

$pageTitle = 'My Profile';
$pageDescription = 'Manage your personal details and account password.';
$breadcrumbs = [['label' => 'Account'], ['label' => 'My Profile']];
$activeNav = 'profile';

include ROOT_PATH . '/includes/header.php';
?>

<div class="profile-head">
    <span class="avatar avatar--lg" aria-hidden="true"><?= e(initials((string) $user['name'])) ?></span>
    <div class="profile-head__body">
        <h2 class="profile-head__name"><?= e($user['name']) ?></h2>
        <p class="profile-head__meta">
            <?= e(role_label((string) $user['role'])) ?>
            <?php if (!empty($user['department_name'])): ?> · <?= e($user['department_name']) ?><?php endif; ?>
            <?php if (!empty($user['faculty_name'])): ?> · <?= e($user['faculty_name']) ?><?php endif; ?>
        </p>
        <div class="cluster" style="margin-top:8px">
            <span class="badge badge--<?= (int) $user['status'] === 1 || $user['status'] === 'active' ? 'success' : 'muted' ?>"><?= e(humanize((string) $user['status'])) ?></span>
            <span class="text-xs text-muted">Member since <?= e(format_date((string) $user['created_at'])) ?></span>
            <span class="text-xs text-muted">Last login <?= e(format_datetime((string) ($user['last_login_at'] ?? ''))) ?></span>
            <?php if ($projectCount > 0): ?><span class="text-xs text-muted"><?= $projectCount ?> research project<?= $projectCount === 1 ? '' : 's' ?></span><?php endif; ?>
        </div>
    </div>
</div>

<div class="grid-2">
    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Personal details</h2>
                <p class="card__subtitle">Used across research records and notifications.</p>
            </div>
        </div>
        <div class="card__body">
            <?php if (isset($profileErrors['_form'])): ?>
                <div class="alert alert--danger" role="alert"><?= icon('alert', 18) ?><div><?= e($profileErrors['_form']) ?></div></div>
            <?php endif; ?>
            <form method="post" action="<?= e(url('profile.php')) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_profile">
                <div class="form-grid">
                    <div class="form-group form-group--span-12">
                        <label class="label" for="profile-name">Full name <span class="label__required" aria-hidden="true">*</span></label>
                        <input class="input <?= isset($profileErrors['name']) ? 'is-invalid' : '' ?>" id="profile-name" name="name" type="text" value="<?= e($user['name']) ?>" required>
                        <?php if (isset($profileErrors['name'])): ?><p class="field-error" role="alert"><?= e($profileErrors['name']) ?></p><?php endif; ?>
                    </div>
                    <div class="form-group form-group--span-12">
                        <label class="label" for="profile-email">Email address <span class="label__required" aria-hidden="true">*</span></label>
                        <input class="input <?= isset($profileErrors['email']) ? 'is-invalid' : '' ?>" id="profile-email" name="email" type="email" value="<?= e($user['email']) ?>" required>
                        <?php if (isset($profileErrors['email'])): ?><p class="field-error" role="alert"><?= e($profileErrors['email']) ?></p><?php endif; ?>
                    </div>
                    <div class="form-group form-group--span-12">
                        <label class="label" for="profile-phone">Phone number</label>
                        <input class="input" id="profile-phone" name="phone" type="tel" value="<?= e((string) ($user['phone'] ?? '')) ?>">
                    </div>
                    <div class="form-group form-group--span-12">
                        <label class="label" for="profile-username">Username</label>
                        <input class="input" id="profile-username" type="text" value="<?= e((string) $user['username']) ?>" readonly>
                        <p class="help-text">Usernames are managed by the research office.</p>
                    </div>
                </div>
                <button class="btn btn--primary" type="submit" style="margin-top:16px"><?= icon('check', 17) ?>Save profile</button>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Change password</h2>
                <p class="card__subtitle">Use at least 8 characters. Other sessions will be signed out.</p>
            </div>
        </div>
        <div class="card__body">
            <?php if (isset($passwordErrors['_form'])): ?>
                <div class="alert alert--danger" role="alert"><?= icon('alert', 18) ?><div><?= e($passwordErrors['_form']) ?></div></div>
            <?php endif; ?>
            <form method="post" action="<?= e(url('profile.php')) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">
                <div class="form-grid">
                    <div class="form-group form-group--span-12">
                        <label class="label" for="current-password">Current password <span class="label__required" aria-hidden="true">*</span></label>
                        <div class="input-group">
                            <input class="input <?= isset($passwordErrors['current_password']) ? 'is-invalid' : '' ?>" id="current-password" name="current_password" type="password" autocomplete="current-password" required>
                            <button class="input-group__btn" type="button" data-toggle-password="current-password" aria-label="Show password" aria-pressed="false"><?= icon('eye', 18) ?></button>
                        </div>
                        <?php if (isset($passwordErrors['current_password'])): ?><p class="field-error" role="alert"><?= e($passwordErrors['current_password']) ?></p><?php endif; ?>
                    </div>
                    <div class="form-group form-group--span-12">
                        <label class="label" for="new-password">New password <span class="label__required" aria-hidden="true">*</span></label>
                        <div class="input-group">
                            <input class="input <?= isset($passwordErrors['new_password']) ? 'is-invalid' : '' ?>" id="new-password" name="new_password" type="password" autocomplete="new-password" required>
                            <button class="input-group__btn" type="button" data-toggle-password="new-password" aria-label="Show password" aria-pressed="false"><?= icon('eye', 18) ?></button>
                        </div>
                        <?php if (isset($passwordErrors['new_password'])): ?><p class="field-error" role="alert"><?= e($passwordErrors['new_password']) ?></p><?php endif; ?>
                    </div>
                    <div class="form-group form-group--span-12">
                        <label class="label" for="confirm-password">Confirm new password <span class="label__required" aria-hidden="true">*</span></label>
                        <div class="input-group">
                            <input class="input <?= isset($passwordErrors['confirm_password']) ? 'is-invalid' : '' ?>" id="confirm-password" name="confirm_password" type="password" autocomplete="new-password" required>
                            <button class="input-group__btn" type="button" data-toggle-password="confirm-password" aria-label="Show password" aria-pressed="false"><?= icon('eye', 18) ?></button>
                        </div>
                        <?php if (isset($passwordErrors['confirm_password'])): ?><p class="field-error" role="alert"><?= e($passwordErrors['confirm_password']) ?></p><?php endif; ?>
                    </div>
                </div>
                <button class="btn btn--primary" type="submit" style="margin-top:16px"><?= icon('lock', 17) ?>Update password</button>
            </form>
        </div>
    </section>
</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
