<?php
declare(strict_types=1);

/**
 * Permission aware primary navigation.
 */
$navGroups = [
    'Overview' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => 'dashboard.php'],
    ],
    'University Management' => [
        ['key' => 'users', 'label' => 'Users', 'icon' => 'users', 'href' => 'modules/users.php'],
        ['key' => 'faculties', 'label' => 'Faculties', 'icon' => 'faculty', 'href' => 'modules/faculties.php'],
        ['key' => 'departments', 'label' => 'Departments', 'icon' => 'department', 'href' => 'modules/departments.php'],
        ['key' => 'programmes', 'label' => 'Programmes', 'icon' => 'programme', 'href' => 'modules/programmes.php'],
        ['key' => 'academic_years', 'label' => 'Academic Years', 'icon' => 'calendar', 'href' => 'modules/academic_years.php'],
        ['key' => 'semesters', 'label' => 'Semesters', 'icon' => 'semester', 'href' => 'modules/semesters.php'],
    ],
    'Academic Research' => [
        ['key' => 'students', 'label' => 'Students', 'icon' => 'student', 'href' => 'modules/students.php'],
        ['key' => 'research_projects', 'label' => 'Research Projects', 'icon' => 'research', 'href' => 'modules/research_projects.php'],
        ['key' => 'proposals', 'label' => 'Proposals', 'icon' => 'proposal', 'href' => 'modules/proposals.php'],
        ['key' => 'supervisors', 'label' => 'Supervisors', 'icon' => 'supervisor', 'href' => 'modules/supervisors.php'],
        ['key' => 'milestones', 'label' => 'Milestones', 'icon' => 'milestone', 'href' => 'modules/milestones.php'],
        ['key' => 'documents', 'label' => 'Documents', 'icon' => 'document', 'href' => 'modules/documents.php'],
        ['key' => 'evaluations', 'label' => 'Evaluations', 'icon' => 'evaluation', 'href' => 'modules/evaluations.php'],
    ],
    'Institutional Research' => [
        ['key' => 'lecturer_research', 'label' => 'Lecturer Research', 'icon' => 'lecturer', 'href' => 'modules/lecturer_research.php'],
        ['key' => 'research_teams', 'label' => 'Research Teams', 'icon' => 'team', 'href' => 'modules/research_teams.php'],
        ['key' => 'publications', 'label' => 'Publications', 'icon' => 'publication', 'href' => 'modules/publications.php'],
    ],
    'Innovation' => [
        ['key' => 'innovation_projects', 'label' => 'Innovation Projects', 'icon' => 'innovation', 'href' => 'modules/innovation_projects.php'],
        ['key' => 'innovation_teams', 'label' => 'Innovation Teams', 'icon' => 'team', 'href' => 'modules/innovation_teams.php'],
        ['key' => 'competitions', 'label' => 'Competitions', 'icon' => 'competition', 'href' => 'modules/competitions.php'],
        ['key' => 'judges', 'label' => 'Judges', 'icon' => 'judge', 'href' => 'modules/judges.php'],
        ['key' => 'evaluation_results', 'label' => 'Evaluation Results', 'icon' => 'results', 'href' => 'modules/evaluation_results.php'],
    ],
    'Finance' => [
        ['key' => 'funding_applications', 'label' => 'Funding Applications', 'icon' => 'funding', 'href' => 'modules/funding_applications.php'],
        ['key' => 'budgets', 'label' => 'Budgets', 'icon' => 'budget', 'href' => 'modules/budgets.php'],
        ['key' => 'expenditure', 'label' => 'Expenditure', 'icon' => 'expenditure', 'href' => 'modules/expenditure.php'],
        ['key' => 'financial_reports', 'label' => 'Financial Reports', 'icon' => 'report', 'href' => 'financial_reports.php'],
    ],
    'System' => [
        ['key' => 'notifications', 'label' => 'Notifications', 'icon' => 'notification', 'href' => 'notifications.php'],
        ['key' => 'reports', 'label' => 'Reports & Analytics', 'icon' => 'analytics', 'href' => 'reports.php'],
        ['key' => 'audit_logs', 'label' => 'Audit Logs', 'icon' => 'audit', 'href' => 'modules/audit_logs.php'],
        ['key' => 'settings', 'label' => 'Settings', 'icon' => 'settings', 'href' => 'settings.php'],
    ],
];

$user = current_user();
$activeNav = $activeNav ?? '';
$visibleGroups = 0;
foreach ($navGroups as $items) {
    foreach ($items as $item) {
        if (can_access($item['key'])) {
            $visibleGroups++;
            break;
        }
    }
}
?>
<aside class="sidebar" id="sidebar" aria-label="Primary navigation">
    <div class="sidebar__brand">
        <a class="brand" href="<?= e(url('dashboard.php')) ?>" aria-label="URIMS dashboard">
            <span class="brand__mark" aria-hidden="true">U</span>
            <span class="brand__text">
                <strong><?= e(APP_NAME) ?></strong>
                <small>KIUT Research</small>
            </span>
        </a>
        <button class="sidebar__collapse" type="button" data-sidebar-collapse aria-label="Collapse sidebar" aria-expanded="true" title="Collapse sidebar">
            <?= icon('collapsed', 18) ?>
        </button>
    </div>

    <nav class="sidebar__nav" aria-label="Modules">
        <?php foreach ($navGroups as $groupLabel => $items):
            $visible = array_values(array_filter($items, static fn (array $item): bool => can_access($item['key'])));
            if (!$visible) {
                continue;
            }
            ?>
            <div class="nav-group">
                <p class="nav-group__label"><?= e($groupLabel) ?></p>
                <?php foreach ($visible as $item): ?>
                    <a class="nav-item <?= $activeNav === $item['key'] ? 'is-active' : '' ?>"
                       href="<?= e(url($item['href'])) ?>"
                       <?= $activeNav === $item['key'] ? 'aria-current="page"' : '' ?>
                       title="<?= e($item['label']) ?>">
                        <span class="nav-item__icon"><?= icon($item['icon'], 19) ?></span>
                        <span class="nav-item__label"><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar__footer">
        <div class="sidebar__user">
            <span class="avatar avatar--sm" aria-hidden="true"><?= e(initials((string) ($user['name'] ?? 'U'))) ?></span>
            <span class="sidebar__user-text">
                <span class="sidebar__user-name"><?= e($user['name'] ?? '') ?></span>
                <span class="sidebar__user-role"><?= e(role_label($user['role'] ?? '')) ?></span>
            </span>
            <a class="icon-btn icon-btn--ghost" href="<?= e(url('logout.php')) ?>" title="Sign out" aria-label="Sign out"><?= icon('logout', 17) ?></a>
        </div>
        <p class="sidebar__institution"><?= e(APP_INSTITUTION_SHORT) ?> · <?= date('Y') ?></p>
    </div>
</aside>
