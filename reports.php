<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_permission('reports');
require_once ROOT_PATH . '/includes/charts.php';

$yearId = (int) ($_GET['year'] ?? 0);
$yearWhere = $yearId > 0 ? ' AND p.academic_year_id = ?' : '';
$yearParams = $yearId > 0 ? [$yearId] : [];

$years = db_rows('SELECT id, name FROM academic_years ORDER BY start_date DESC');

$statusCounts = [];
foreach (db_rows("SELECT p.status, COUNT(*) AS total FROM research_projects p WHERE 1=1 $yearWhere GROUP BY p.status ORDER BY total DESC", $yearParams) as $row) {
    $statusCounts[$row['status']] = (int) $row['total'];
}
$byFaculty = [];
foreach (db_rows(
    "SELECT f.name AS label, COUNT(*) AS total FROM research_projects p
     LEFT JOIN faculties f ON f.id = p.faculty_id WHERE 1=1 $yearWhere
     GROUP BY label ORDER BY total DESC",
    $yearParams
) as $row) {
    $label = (string) ($row['label'] ?: 'Unassigned');
    $byFaculty[mb_strlen($label) > 26 ? mb_substr($label, 0, 24) . '…' : $label] = (int) $row['total'];
}
$byDepartment = [];
foreach (db_rows(
    "SELECT d.name AS label, COUNT(*) AS total FROM research_projects p
     LEFT JOIN departments d ON d.id = p.department_id WHERE 1=1 $yearWhere
     GROUP BY label ORDER BY total DESC LIMIT 8",
    $yearParams
) as $row) {
    $label = (string) ($row['label'] ?: 'Unassigned');
    $byDepartment[mb_strlen($label) > 30 ? mb_substr($label, 0, 28) . '…' : $label] = (int) $row['total'];
}
$byYear = [];
foreach (db_rows(
    "SELECT COALESCE(y.name, 'Unassigned') AS label, COUNT(*) AS total
     FROM research_projects p LEFT JOIN academic_years y ON y.id = p.academic_year_id
     WHERE 1=1 $yearWhere GROUP BY label ORDER BY label",
    $yearParams
) as $row) {
    $byYear[$row['label']] = (int) $row['total'];
}
$publicationsByYear = [];
foreach (db_rows('SELECT publication_year AS yr, COUNT(*) AS total FROM publications WHERE publication_year IS NOT NULL GROUP BY yr ORDER BY yr') as $row) {
    $publicationsByYear[(string) $row['yr']] = (int) $row['total'];
}
$supervisionLoad = [];
foreach (db_rows(
    "SELECT u.name AS label, COUNT(*) AS total FROM research_projects p
     JOIN users u ON u.id = p.supervisor_id WHERE 1=1 $yearWhere
     GROUP BY u.name ORDER BY total DESC LIMIT 7",
    $yearParams
) as $row) {
    $supervisionLoad[$row['label']] = (int) $row['total'];
}
$fundingBySource = [];
foreach (db_rows(
    "SELECT COALESCE(funding_source,'Unspecified') AS label, SUM(amount_requested) AS total
     FROM funding_applications WHERE status IN ('approved','disbursed') GROUP BY label ORDER BY total DESC LIMIT 6"
) as $row) {
    $label = mb_strlen($row['label']) > 26 ? mb_substr($row['label'], 0, 24) . '…' : $row['label'];
    $fundingBySource[$label] = (float) $row['total'];
}

$totalProjects = array_sum($statusCounts);
$completed = (int) ($statusCounts['completed'] ?? 0);
$active = (int) ($statusCounts['active'] ?? 0);
$completionRate = percentage($completed, $totalProjects);
$publicationTotal = (int) db_value('SELECT COUNT(*) FROM publications');
$innovationTotal = (int) db_value('SELECT COUNT(*) FROM innovation_projects');
$supervisorTotal = (int) db_value('SELECT COUNT(DISTINCT supervisor_id) FROM research_projects WHERE supervisor_id IS NOT NULL');
$externalFunding = (float) db_value("SELECT COALESCE(SUM(amount_requested),0) FROM funding_applications WHERE status IN ('approved','disbursed')");

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'urims-research-report-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['URIMS Research & Analytics Report', APP_INSTITUTION]);
    fputcsv($out, ['Generated', date('Y-m-d H:i:s')]);
    fputcsv($out, ['Academic year filter', $yearId > 0 ? (string) db_value('SELECT name FROM academic_years WHERE id = ?', [$yearId]) : 'All years']);
    fputcsv($out, []);
    fputcsv($out, ['Metric', 'Value']);
    fputcsv($out, ['Total research projects', $totalProjects]);
    fputcsv($out, ['Active projects', $active]);
    fputcsv($out, ['Completed projects', $completed]);
    fputcsv($out, ['Completion rate (%)', $completionRate]);
    fputcsv($out, ['Publications', $publicationTotal]);
    fputcsv($out, ['Innovation projects', $innovationTotal]);
    fputcsv($out, ['Active supervisors', $supervisorTotal]);
    fputcsv($out, ['Approved funding (' . APP_CURRENCY . ')', $externalFunding]);
    fputcsv($out, []);
    foreach ([
        'Projects by status' => $statusCounts,
        'Projects by faculty' => $byFaculty,
        'Projects by department' => $byDepartment,
        'Projects by academic year' => $byYear,
        'Publications by year' => $publicationsByYear,
        'Supervision load' => $supervisionLoad,
        'Approved funding by source' => $fundingBySource,
    ] as $section => $data) {
        fputcsv($out, [$section, 'Value']);
        foreach ($data as $label => $value) {
            fputcsv($out, [$label, $value]);
        }
        fputcsv($out, []);
    }
    fclose($out);
    audit_log('export', 'reports', 'Exported research analytics CSV');
    exit;
}

$pageTitle = 'Reports & Analytics';
$pageDescription = 'Live research, innovation and funding analytics across the university. Export or print for management reporting.';
$breadcrumbs = [['label' => 'System'], ['label' => 'Reports & Analytics']];
$activeNav = 'reports';

$pageActions = '<a class="btn btn--secondary" href="?' . e(query_string(['export' => 'csv'])) . '">' . icon('download', 17) . 'Export CSV</a>'
    . '<button class="btn btn--primary" type="button" onclick="window.print()">' . icon('report', 17) . 'Print report</button>';

include ROOT_PATH . '/includes/header.php';
?>

<div class="print-header">
    <strong><?= e(APP_NAME) ?> · <?= e(APP_FULL_NAME) ?></strong><br>
    <?= e(APP_INSTITUTION) ?> · Generated <?= e(date('d M Y, H:i')) ?>
    <?= $yearId > 0 ? ' · Academic year: ' . e((string) db_value('SELECT name FROM academic_years WHERE id = ?', [$yearId])) : '' ?>
</div>

<div class="card card--flush" style="margin-bottom:18px">
    <div class="toolbar">
        <form method="get" action="<?= e(url('reports.php')) ?>" class="toolbar__filters">
            <label class="sr-only" for="report-year">Academic year</label>
            <select class="select select--sm" id="report-year" name="year" onchange="this.form.submit()" style="max-width:240px">
                <option value="0">All academic years</option>
                <?php foreach ($years as $year): ?>
                    <option value="<?= (int) $year['id'] ?>" <?= $yearId === (int) $year['id'] ? 'selected' : '' ?>><?= e($year['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button class="btn btn--secondary btn--sm" type="submit">Apply</button></noscript>
        </form>
        <p class="text-xs text-muted">Data reflects records currently stored in MySQL.</p>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card stat-card--success">
        <span class="stat-card__top"><span class="stat-card__label">Research projects</span><span class="stat-card__icon"><?= icon('research', 17) ?></span></span>
        <span class="stat-card__value"><?= number_format($totalProjects) ?></span>
        <span class="stat-card__meta"><?= $active ?> active · <?= $completed ?> completed</span>
    </div>
    <div class="stat-card stat-card--info">
        <span class="stat-card__top"><span class="stat-card__label">Completion rate</span><span class="stat-card__icon"><?= icon('analytics', 17) ?></span></span>
        <span class="stat-card__value"><?= (int) $completionRate ?>%</span>
        <span class="stat-card__meta">Completed vs total projects</span>
    </div>
    <div class="stat-card">
        <span class="stat-card__top"><span class="stat-card__label">Publications</span><span class="stat-card__icon"><?= icon('publication', 17) ?></span></span>
        <span class="stat-card__value"><?= number_format($publicationTotal) ?></span>
        <span class="stat-card__meta">All recorded research outputs</span>
    </div>
    <div class="stat-card stat-card--warning">
        <span class="stat-card__top"><span class="stat-card__label">Innovation projects</span><span class="stat-card__icon"><?= icon('innovation', 17) ?></span></span>
        <span class="stat-card__value"><?= number_format($innovationTotal) ?></span>
        <span class="stat-card__meta">Across all incubation stages</span>
    </div>
    <div class="stat-card">
        <span class="stat-card__top"><span class="stat-card__label">Active supervisors</span><span class="stat-card__icon"><?= icon('supervisor', 17) ?></span></span>
        <span class="stat-card__value"><?= number_format($supervisorTotal) ?></span>
        <span class="stat-card__meta">Staff currently supervising</span>
    </div>
    <div class="stat-card stat-card--success">
        <span class="stat-card__top"><span class="stat-card__label">Approved funding</span><span class="stat-card__icon"><?= icon('funding', 17) ?></span></span>
        <span class="stat-card__value"><?= e(money($externalFunding, true)) ?></span>
        <span class="stat-card__meta">Approved and disbursed applications</span>
    </div>
</div>

<div class="report-grid">
    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Projects by faculty</h2>
                <p class="card__subtitle">Research portfolio distribution</p>
            </div>
        </div>
        <div class="card__body"><?= chart_hbars($byFaculty, 'Research projects by faculty') ?></div>
    </section>

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Project status</h2>
                <p class="card__subtitle">Current pipeline mix</p>
            </div>
        </div>
        <div class="card__body"><?= chart_donut($statusCounts, 'Research projects by status', 'Projects') ?></div>
    </section>

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Projects by department</h2>
                <p class="card__subtitle">Top eight departments</p>
            </div>
        </div>
        <div class="card__body"><?= chart_hbars($byDepartment, 'Research projects by department') ?></div>
    </section>

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Projects by academic year</h2>
                <p class="card__subtitle">Registration trend</p>
            </div>
        </div>
        <div class="card__body"><?= chart_bars($byYear, 'Research projects by academic year') ?></div>
    </section>

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Publications by year</h2>
                <p class="card__subtitle">Research output trend</p>
            </div>
        </div>
        <div class="card__body"><?= chart_bars($publicationsByYear, 'Publications by year') ?></div>
    </section>

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Supervision load</h2>
                <p class="card__subtitle">Projects per supervisor</p>
            </div>
        </div>
        <div class="card__body"><?= chart_hbars($supervisionLoad, 'Projects per supervisor') ?></div>
    </section>
</div>

<section class="card" style="margin-top:18px">
    <div class="card__header">
        <div>
            <h2 class="card__title">Approved funding by source</h2>
            <p class="card__subtitle">Cumulative approved and disbursed amounts</p>
        </div>
        <?php if (can_access('financial_reports')): ?>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('financial_reports.php')) ?>">Open financial reports <?= icon('arrow-right', 15) ?></a>
        <?php endif; ?>
    </div>
    <div class="card__body"><?= chart_hbars($fundingBySource, 'Approved research funding by source', APP_CURRENCY . ' ') ?></div>
</section>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
