<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_login();
require_once ROOT_PATH . '/includes/charts.php';

$user = current_user();
$role = current_role();
$userId = (int) $user['id'];
$name = (string) $user['name'];
$firstName = explode(' ', trim($name))[0] ?? $name;
$today = new DateTimeImmutable('now');

/* ---------------------------------------------------------------
 | Scope: supervisors and students only see their own research
 --------------------------------------------------------------- */
$projectWhere = '1 = 1';
$projectParams = [];
if ($role === 'supervisor') {
    $projectWhere = 'p.supervisor_id = ?';
    $projectParams = [$userId];
} elseif ($role === 'student') {
    $projectWhere = 'p.student_id IN (SELECT id FROM students WHERE user_id = ?)';
    $projectParams = [$userId];
}

$proposalWhere = '1 = 1';
$proposalParams = [];
if ($role === 'supervisor') {
    $proposalWhere = 'p.supervisor_id = ?';
    $proposalParams = [$userId];
} elseif ($role === 'student') {
    $proposalWhere = 'p.submitted_by = ?';
    $proposalParams = [$userId];
}

$budgetWhere = '1 = 1';
$budgetParams = [];
if ($role === 'supervisor') {
    $budgetWhere = 'b.project_id IN (SELECT id FROM research_projects WHERE supervisor_id = ?)';
    $budgetParams = [$userId];
} elseif ($role === 'student') {
    $budgetWhere = 'b.project_id IN (SELECT id FROM research_projects WHERE student_id IN (SELECT id FROM students WHERE user_id = ?))';
    $budgetParams = [$userId];
}

function dash_value(string $sql, array $params = []): float
{
    try {
        return (float) (db_value($sql, $params) ?? 0);
    } catch (Throwable) {
        return 0.0;
    }
}

$totalProjects = (int) dash_value("SELECT COUNT(*) FROM research_projects p WHERE $projectWhere", $projectParams);
$activeProjects = (int) dash_value("SELECT COUNT(*) FROM research_projects p WHERE $projectWhere AND p.status = 'active'", $projectParams);
$completedProjects = (int) dash_value("SELECT COUNT(*) FROM research_projects p WHERE $projectWhere AND p.status = 'completed'", $projectParams);
$overdueProjects = (int) dash_value(
    "SELECT COUNT(*) FROM research_projects p WHERE $projectWhere AND p.end_date IS NOT NULL AND p.end_date < CURDATE() AND p.status IN ('approved','active','proposed')",
    $projectParams
);
$pendingApprovals = can_access('proposals')
    ? (int) dash_value("SELECT COUNT(*) FROM proposals p WHERE $proposalWhere AND p.status IN ('submitted','under_review')", $proposalParams)
    : 0;
$innovationCount = can_access('innovation_projects')
    ? (int) dash_value('SELECT COUNT(*) FROM innovation_projects WHERE status <> "draft"')
    : 0;
$fundingAllocated = can_access('budgets')
    ? dash_value("SELECT COALESCE(SUM(b.allocated_amount),0) FROM budgets b WHERE $budgetWhere AND b.status = 'approved'", $budgetParams)
    : 0;
$publicationCount = can_access('publications')
    ? (int) dash_value('SELECT COUNT(*) FROM publications WHERE status = "published"')
    : 0;

/* ---------------------------------------------------------------
 | Charts
 --------------------------------------------------------------- */
$activity = [];
$activityRows = db_rows(
    "SELECT DATE_FORMAT(p.created_at, '%Y-%m') AS ym, COUNT(*) AS total
     FROM research_projects p
     WHERE $projectWhere AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 MONTH)
     GROUP BY ym ORDER BY ym",
    $projectParams
);
$activityMap = [];
foreach ($activityRows as $row) {
    $activityMap[$row['ym']] = (int) $row['total'];
}
for ($i = 7; $i >= 0; $i--) {
    $month = (clone $today)->modify("-$i month")->modify('first day of this month');
    $key = $month->format('Y-m');
    $activity[$month->format('M')] = $activityMap[$key] ?? 0;
}

$byDepartment = [];
foreach (db_rows(
    "SELECT COALESCE(d.name, 'Unassigned') AS label, COUNT(*) AS total
     FROM research_projects p LEFT JOIN departments d ON d.id = p.department_id
     WHERE $projectWhere GROUP BY label ORDER BY total DESC LIMIT 6",
    $projectParams
) as $row) {
    $label = $row['label'];
    if (mb_strlen($label) > 26) {
        $label = mb_substr($label, 0, 24) . '…';
    }
    $byDepartment[$label] = (int) $row['total'];
}

$byStatus = [];
foreach (db_rows(
    "SELECT p.status, COUNT(*) AS total FROM research_projects p WHERE $projectWhere GROUP BY p.status ORDER BY total DESC",
    $projectParams
) as $row) {
    $byStatus[$row['status']] = (int) $row['total'];
}

$innovationStages = [];
if (can_access('innovation_projects')) {
    foreach (db_rows('SELECT stage, COUNT(*) AS total FROM innovation_projects GROUP BY stage ORDER BY total DESC') as $row) {
        $innovationStages[$row['stage']] = (int) $row['total'];
    }
}

$fundingSources = [];
if (can_access('funding_applications')) {
    foreach (db_rows(
        "SELECT COALESCE(funding_source, 'Unspecified') AS source, SUM(amount_requested) AS total
         FROM funding_applications WHERE status IN ('approved','disbursed')
         GROUP BY source ORDER BY total DESC LIMIT 5"
    ) as $row) {
        $label = mb_strlen($row['source']) > 24 ? mb_substr($row['source'], 0, 22) . '…' : $row['source'];
        $fundingSources[$label] = (float) $row['total'];
    }
}

/* ---------------------------------------------------------------
 | Recent activity
 --------------------------------------------------------------- */
$recentProposals = can_access('proposals') ? db_rows(
    "SELECT p.id, p.title, p.status, p.created_at, u.name AS submitter
     FROM proposals p LEFT JOIN users u ON u.id = p.submitted_by
     WHERE $proposalWhere ORDER BY p.created_at DESC LIMIT 5",
    $proposalParams
) : [];

$pendingList = can_access('proposals') ? db_rows(
    "SELECT p.id, p.title, p.status, p.submitted_at, u.name AS submitter
     FROM proposals p LEFT JOIN users u ON u.id = p.submitted_by
     WHERE $proposalWhere AND p.status IN ('submitted','under_review')
     ORDER BY p.submitted_at ASC LIMIT 5",
    $proposalParams
) : [];

$feedback = can_access('evaluations') ? db_rows(
    "SELECT e.id, e.comments, e.total_score, e.evaluated_at, pr.title AS project_title, u.name AS evaluator
     FROM evaluations e
     LEFT JOIN research_projects pr ON pr.id = e.project_id
     LEFT JOIN users u ON u.id = e.evaluator_id
     WHERE 1 = 1 " . ($role === 'student' ? 'AND pr.student_id IN (SELECT id FROM students WHERE user_id = ?)' : ($role === 'supervisor' ? 'AND pr.supervisor_id = ?' : '')) . '
     ORDER BY e.evaluated_at DESC LIMIT 4',
    in_array($role, ['student', 'supervisor'], true) ? [$userId] : []
) : [];

$deadlines = can_access('milestones') ? db_rows(
    "SELECT m.id, m.title, m.due_date, m.status, p.title AS project_title
     FROM milestones m LEFT JOIN research_projects p ON p.id = m.project_id
     WHERE m.status <> 'completed' AND m.due_date IS NOT NULL AND m.due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
       AND $projectWhere
     ORDER BY m.due_date ASC LIMIT 5",
    $projectParams
) : [];

$notifications = db_rows(
    'SELECT id, title, message, type, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 4',
    [$userId]
);

$unreadNotifications = (int) dash_value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);

/* ---------------------------------------------------------------
 | Page setup
 --------------------------------------------------------------- */
$pageTitle = 'Dashboard';
$pageDescription = '';
$breadcrumbs = [['label' => 'Dashboard']];
$activeNav = 'dashboard';

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

$quickActions = [];
if (can_write('research_projects')) {
    $quickActions[] = ['label' => 'New project', 'icon' => 'plus', 'href' => 'modules/research_projects.php?action=create'];
}
if (can_write('proposals')) {
    $quickActions[] = ['label' => 'Submit proposal', 'icon' => 'proposal', 'href' => 'modules/proposals.php?action=create'];
}
if (can_write('publications')) {
    $quickActions[] = ['label' => 'Add publication', 'icon' => 'publication', 'href' => 'modules/publications.php?action=create'];
}
if (can_access('funding_applications')) {
    $quickActions[] = ['label' => 'Funding', 'icon' => 'funding', 'href' => 'modules/funding_applications.php'];
}
if (can_access('reports')) {
    $quickActions[] = ['label' => 'Analytics', 'icon' => 'analytics', 'href' => 'reports.php'];
}

include ROOT_PATH . '/includes/header.php';
?>

<section class="welcome" aria-labelledby="welcome-title">
    <div class="welcome__body">
        <p class="welcome__greeting"><?= e($greeting) ?>,</p>
        <h2 class="welcome__title" id="welcome-title"><?= e($firstName) ?></h2>
        <div class="welcome__meta">
            <span class="meta-chip"><?= icon('user', 13) ?><?= e(role_label($role)) ?></span>
            <span class="meta-chip"><?= icon('calendar', 13) ?><?= e($today->format('l, j F Y')) ?></span>
            <span class="meta-chip"><?= icon('semester', 13) ?><?= e((string) (db_value('SELECT name FROM semesters WHERE is_current = 1 LIMIT 1') ?? 'Current semester')) ?></span>
            <?php if ($unreadNotifications > 0): ?>
                <span class="meta-chip"><?= icon('bell', 13) ?><?= $unreadNotifications ?> unread notification<?= $unreadNotifications === 1 ? '' : 's' ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="welcome__actions">
        <?php foreach (array_slice($quickActions, 0, 3) as $action): ?>
            <a class="quick-link" href="<?= e(url($action['href'])) ?>"><?= icon($action['icon'], 16) ?><?= e($action['label']) ?></a>
        <?php endforeach; ?>
    </div>
</section>

<?php
$statCards = [
    ['label' => 'Total projects', 'value' => number_format($totalProjects), 'icon' => 'research', 'tone' => '', 'meta' => 'All research projects you can access', 'href' => 'modules/research_projects.php'],
    ['label' => 'Active projects', 'value' => number_format($activeProjects), 'icon' => 'arrow-up', 'tone' => 'success', 'meta' => 'Currently in progress', 'href' => 'modules/research_projects.php?f_status=active'],
    ['label' => 'Completed', 'value' => number_format($completedProjects), 'icon' => 'check', 'tone' => 'info', 'meta' => 'Successfully concluded', 'href' => 'modules/research_projects.php?f_status=completed'],
    ['label' => 'Pending approvals', 'value' => number_format($pendingApprovals), 'icon' => 'proposal', 'tone' => 'warning', 'meta' => 'Proposals awaiting decision', 'href' => 'modules/proposals.php?f_status=submitted'],
    ['label' => 'Overdue projects', 'value' => number_format($overdueProjects), 'icon' => 'alert', 'tone' => 'danger', 'meta' => 'Past planned end date', 'href' => 'modules/research_projects.php?f_status=active'],
    ['label' => 'Innovation projects', 'value' => number_format($innovationCount), 'icon' => 'innovation', 'tone' => 'info', 'meta' => 'In the innovation pipeline', 'href' => 'modules/innovation_projects.php'],
    ['label' => 'Funding allocated', 'value' => money($fundingAllocated, true), 'icon' => 'budget', 'tone' => 'success', 'meta' => 'Approved budget allocations', 'href' => can_access('budgets') ? 'modules/budgets.php' : 'modules/funding_applications.php'],
];

$statCards = array_values(array_filter($statCards, static function (array $card): bool {
    return can_access(basename((string) parse_url($card['href'], PHP_URL_PATH), '.php'));
}));
?>

<div class="stat-grid">
    <?php foreach ($statCards as $card): ?>
        <a class="stat-card <?= $card['tone'] ? 'stat-card--' . e($card['tone']) : '' ?>" href="<?= e(url($card['href'])) ?>" style="text-decoration:none;color:inherit">
            <span class="stat-card__top">
                <span class="stat-card__label"><?= e($card['label']) ?></span>
                <span class="stat-card__icon"><?= icon($card['icon'], 17) ?></span>
            </span>
            <span class="stat-card__value"><?= e($card['value']) ?></span>
            <span class="stat-card__meta"><?= e($card['meta']) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<div class="grid-wide" style="margin-bottom:18px">
    <section class="card" aria-labelledby="activity-title">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="activity-title">Research activity</h2>
                <p class="card__subtitle">New research projects over the last eight months</p>
            </div>
        </div>
        <div class="card__body">
            <?= chart_bars($activity, 'Research activity over the last eight months') ?>
        </div>
    </section>

    <section class="card" aria-labelledby="status-title">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="status-title">Project status</h2>
                <p class="card__subtitle">Distribution across your portfolio</p>
            </div>
        </div>
        <div class="card__body">
            <?= chart_donut($byStatus, 'Research project status distribution', 'Projects') ?>
        </div>
    </section>
</div>

<div class="grid-wide" style="margin-bottom:18px">
    <section class="card" aria-labelledby="dept-title">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="dept-title">Research by department</h2>
                <p class="card__subtitle">Top departments by number of projects</p>
            </div>
        </div>
        <div class="card__body">
            <?= chart_hbars($byDepartment, 'Research projects by department') ?>
        </div>
    </section>

    <div class="stack" style="gap:18px">
        <section class="card" aria-labelledby="innovation-chart-title">
            <div class="card__header">
                <div>
                    <h2 class="card__title" id="innovation-chart-title">Innovation pipeline</h2>
                    <p class="card__subtitle">Projects by development stage</p>
                </div>
            </div>
            <div class="card__body">
                <?= chart_donut($innovationStages, 'Innovation projects by stage', 'Innovations') ?>
            </div>
        </section>
    </div>
</div>

<?php if ($fundingSources): ?>
<section class="card" style="margin-bottom:18px" aria-labelledby="funding-chart-title">
    <div class="card__header">
        <div>
            <h2 class="card__title" id="funding-chart-title">Funding summary</h2>
            <p class="card__subtitle">Approved funding by source</p>
        </div>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('financial_reports.php')) ?>">Financial reports <?= icon('arrow-right', 15) ?></a>
    </div>
    <div class="card__body">
        <?= chart_hbars($fundingSources, 'Approved research funding by source', APP_CURRENCY . ' ') ?>
    </div>
</section>
<?php endif; ?>

<div class="grid-2">
    <?php if (can_access('proposals')): ?>
        <section class="card" aria-labelledby="recent-proposals-title">
            <div class="card__header">
                <div>
                    <h2 class="card__title" id="recent-proposals-title">Recent submissions</h2>
                    <p class="card__subtitle">Latest research proposals</p>
                </div>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('modules/proposals.php')) ?>">View all</a>
            </div>
            <div class="card__body card__body--tight">
                <?php if ($recentProposals): ?>
                    <div class="activity">
                        <?php foreach ($recentProposals as $proposal): ?>
                            <div class="activity__item">
                                <span class="activity__icon"><?= icon('proposal', 15) ?></span>
                                <div class="activity__body">
                                    <a class="activity__title" href="<?= e(url('modules/proposals.php?action=view&id=' . (int) $proposal['id'])) ?>"><?= e(truncate($proposal['title'], 70)) ?></a>
                                    <p class="activity__meta">
                                        <span><?= e($proposal['submitter'] ?: 'Unknown') ?></span>
                                        <?= status_badge($proposal['status']) ?>
                                    </p>
                                </div>
                                <span class="activity__time"><?= e(time_ago($proposal['created_at'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="activity__empty">No proposals have been submitted yet.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="card" aria-labelledby="pending-title">
            <div class="card__header">
                <div>
                    <h2 class="card__title" id="pending-title">Pending approvals</h2>
                    <p class="card__subtitle">Proposals awaiting a decision</p>
                </div>
                <span class="badge badge--warning"><?= count($pendingList) ?> waiting</span>
            </div>
            <div class="card__body card__body--tight">
                <?php if ($pendingList): ?>
                    <div class="activity">
                        <?php foreach ($pendingList as $proposal): ?>
                            <div class="activity__item">
                                <span class="activity__icon activity__icon--warning"><?= icon('clock', 15) ?></span>
                                <div class="activity__body">
                                    <a class="activity__title" href="<?= e(url('modules/proposals.php?action=view&id=' . (int) $proposal['id'])) ?>"><?= e(truncate($proposal['title'], 70)) ?></a>
                                    <p class="activity__meta">
                                        <span><?= e($proposal['submitter'] ?: 'Unknown') ?></span>
                                        <span>·</span>
                                        <span><?= e($proposal['submitted_at'] ? 'Submitted ' . time_ago($proposal['submitted_at']) : 'Not submitted') ?></span>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="activity__empty">Nothing is awaiting approval. All caught up.</p>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="card" aria-labelledby="deadlines-title">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="deadlines-title">Upcoming deadlines</h2>
                <p class="card__subtitle">Milestones due in the next 30 days</p>
            </div>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('modules/milestones.php')) ?>">All milestones</a>
        </div>
        <div class="card__body card__body--tight">
            <?php if ($deadlines): ?>
                <?php foreach ($deadlines as $deadline):
                    $due = strtotime((string) $deadline['due_date']);
                    $overdue = $due < strtotime('today');
                    ?>
                    <div class="deadline <?= $overdue ? 'deadline--overdue' : '' ?>">
                        <span class="deadline__date" aria-hidden="true">
                            <span class="deadline__day"><?= e(date('j', $due)) ?></span>
                            <span class="deadline__month"><?= e(date('M', $due)) ?></span>
                        </span>
                        <div class="deadline__body">
                            <p class="deadline__title"><?= e(truncate($deadline['title'], 52)) ?></p>
                            <p class="deadline__meta"><?= e(truncate((string) $deadline['project_title'], 46)) ?></p>
                        </div>
                        <?= status_badge($overdue ? 'overdue' : $deadline['status']) ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="activity__empty">No milestones are due in the next 30 days.</p>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($feedback): ?>
        <section class="card" aria-labelledby="feedback-title">
            <div class="card__header">
                <div>
                    <h2 class="card__title" id="feedback-title">Supervisor feedback</h2>
                    <p class="card__subtitle">Latest evaluation comments</p>
                </div>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('modules/evaluations.php')) ?>">View all</a>
            </div>
            <div class="card__body card__body--tight">
                <div class="activity">
                    <?php foreach ($feedback as $item): ?>
                        <div class="activity__item">
                            <span class="activity__icon activity__icon--info"><?= icon('evaluation', 15) ?></span>
                            <div class="activity__body">
                                <p class="activity__title"><?= e(truncate((string) $item['comments'], 80)) ?></p>
                                <p class="activity__meta">
                                    <span><?= e($item['evaluator'] ?: 'Supervisor') ?></span>
                                    <span class="badge badge--muted"><?= (int) $item['total_score'] ?> / 100</span>
                                </p>
                            </div>
                            <span class="activity__time"><?= e(time_ago($item['evaluated_at'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="card" aria-labelledby="notifications-title">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="notifications-title">Recent notifications</h2>
                <p class="card__subtitle">Messages relevant to your role</p>
            </div>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('notifications.php')) ?>">Open inbox</a>
        </div>
        <div class="card__body card__body--tight">
            <?php if ($notifications): ?>
                <div class="activity">
                    <?php foreach ($notifications as $note):
                        $tone = in_array($note['type'], ['success', 'warning', 'danger', 'info'], true) ? $note['type'] : 'info';
                        ?>
                        <div class="activity__item">
                            <span class="activity__icon activity__icon--<?= e($tone) ?>"><?= icon($tone === 'danger' ? 'alert' : ($tone === 'success' ? 'check' : 'bell'), 15) ?></span>
                            <div class="activity__body">
                                <p class="activity__title"><?= e($note['title']) ?><?= (int) $note['is_read'] === 0 ? ' <span class="badge badge--info">New</span>' : '' ?></p>
                                <p class="activity__meta"><?= e(truncate((string) $note['message'], 70)) ?></p>
                            </div>
                            <span class="activity__time"><?= e(time_ago($note['created_at'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="activity__empty">You have no notifications.</p>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
