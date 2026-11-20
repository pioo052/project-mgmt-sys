<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_permission('financial_reports');
require_once ROOT_PATH . '/includes/charts.php';

$role = current_role();
$userId = (int) (current_user()['id'] ?? 0);
$scope = '1 = 1';
$scopeParams = [];
if ($role === 'supervisor') {
    $scope = 'p.supervisor_id = ?';
    $scopeParams = [$userId];
} elseif ($role === 'student') {
    $scope = 'p.student_id IN (SELECT id FROM students WHERE user_id = ?)';
    $scopeParams = [$userId];
}

$fiscalYears = array_column(db_rows('SELECT DISTINCT fiscal_year FROM budgets WHERE fiscal_year IS NOT NULL ORDER BY fiscal_year DESC'), 'fiscal_year');
$fiscalYear = (string) ($_GET['fiscal_year'] ?? '');
$yearWhere = $fiscalYear !== '' ? ' AND b.fiscal_year = ?' : '';
$yearParams = $fiscalYear !== '' ? [$fiscalYear] : [];
$params = array_merge($scopeParams, $yearParams);

$allocated = (float) db_value(
    "SELECT COALESCE(SUM(b.allocated_amount),0) FROM budgets b
     LEFT JOIN research_projects p ON p.id = b.project_id
     WHERE $scope AND b.status = 'approved' $yearWhere",
    $params
);
$spent = (float) db_value(
    "SELECT COALESCE(SUM(e.amount),0) FROM expenditure e
     JOIN budgets b ON b.id = e.budget_id
     LEFT JOIN research_projects p ON p.id = b.project_id
     WHERE $scope AND e.status = 'approved' $yearWhere",
    $params
);
$pendingSpend = (float) db_value(
    "SELECT COALESCE(SUM(e.amount),0) FROM expenditure e
     JOIN budgets b ON b.id = e.budget_id
     LEFT JOIN research_projects p ON p.id = b.project_id
     WHERE $scope AND e.status = 'pending' $yearWhere",
    $params
);
$remaining = max(0, $allocated - $spent);
$utilisation = percentage($spent, $allocated);
$approvedFunding = (float) db_value("SELECT COALESCE(SUM(amount_requested),0) FROM funding_applications WHERE status IN ('approved','disbursed')");

$byCategory = [];
foreach (db_rows(
    "SELECT b.category AS label, b.allocated_amount AS allocated,
            (SELECT COALESCE(SUM(e.amount),0) FROM expenditure e WHERE e.budget_id = b.id AND e.status = 'approved') AS spent
     FROM budgets b LEFT JOIN research_projects p ON p.id = b.project_id
     WHERE $scope AND b.status = 'approved' $yearWhere
     ORDER BY b.category",
    $params
) as $row) {
    $label = $row['label'];
    if (!isset($byCategory[$label])) {
        $byCategory[$label] = ['allocated' => 0.0, 'spent' => 0.0];
    }
    $byCategory[$label]['allocated'] += (float) $row['allocated'];
    $byCategory[$label]['spent'] += (float) $row['spent'];
}

$byProject = db_rows(
    "SELECT p.code, p.title,
            COALESCE(SUM(b.allocated_amount),0) AS allocated,
            COALESCE((SELECT SUM(e.amount) FROM expenditure e JOIN budgets bb ON bb.id = e.budget_id
                      WHERE bb.project_id = p.id AND e.status = 'approved'),0) AS spent
     FROM research_projects p
     LEFT JOIN budgets b ON b.project_id = p.id AND b.status = 'approved' $yearWhere
     WHERE $scope
     GROUP BY p.id, p.code, p.title
     ORDER BY allocated DESC LIMIT 10",
    $params
);

$bySource = [];
foreach (db_rows(
    "SELECT COALESCE(funding_source,'Unspecified') AS label, SUM(amount_requested) AS total
     FROM funding_applications WHERE status IN ('approved','disbursed') GROUP BY label ORDER BY total DESC LIMIT 6"
) as $row) {
    $bySource[mb_strlen($row['label']) > 26 ? mb_substr($row['label'], 0, 24) . '…' : $row['label']] = (float) $row['total'];
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="urims-financial-report-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['URIMS Financial Report', APP_INSTITUTION]);
    fputcsv($out, ['Generated', date('Y-m-d H:i:s')]);
    fputcsv($out, ['Fiscal year', $fiscalYear !== '' ? $fiscalYear : 'All years']);
    fputcsv($out, []);
    fputcsv($out, ['Metric', 'Amount (' . APP_CURRENCY . ')']);
    fputcsv($out, ['Allocated budget', $allocated]);
    fputcsv($out, ['Approved expenditure', $spent]);
    fputcsv($out, ['Pending expenditure', $pendingSpend]);
    fputcsv($out, ['Remaining balance', $remaining]);
    fputcsv($out, ['Utilisation (%)', $utilisation]);
    fputcsv($out, []);
    fputcsv($out, ['Category', 'Allocated', 'Spent', 'Utilisation (%)']);
    foreach ($byCategory as $label => $totals) {
        fputcsv($out, [$label, $totals['allocated'], $totals['spent'], percentage($totals['spent'], $totals['allocated'])]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Project', 'Allocated', 'Spent']);
    foreach ($byProject as $project) {
        fputcsv($out, [$project['code'] ?: $project['title'], $project['allocated'], $project['spent']]);
    }
    fclose($out);
    audit_log('export', 'financial_reports', 'Exported financial report CSV');
    exit;
}

$pageTitle = 'Financial Reports';
$pageDescription = 'Budget allocations, expenditure and utilisation across research projects.';
$breadcrumbs = [['label' => 'Finance'], ['label' => 'Financial Reports']];
$activeNav = 'financial_reports';

$pageActions = '<a class="btn btn--secondary" href="?' . e(query_string(['export' => 'csv'])) . '">' . icon('download', 17) . 'Export CSV</a>'
    . '<button class="btn btn--primary" type="button" onclick="window.print()">' . icon('report', 17) . 'Print</button>';

include ROOT_PATH . '/includes/header.php';
?>

<div class="print-header">
    <strong><?= e(APP_NAME) ?> · Financial Report</strong><br>
    <?= e(APP_INSTITUTION) ?> · Generated <?= e(date('d M Y, H:i')) ?> · Fiscal year: <?= e($fiscalYear !== '' ? $fiscalYear : 'All years') ?>
</div>

<div class="card card--flush" style="margin-bottom:18px">
    <div class="toolbar">
        <form method="get" action="<?= e(url('financial_reports.php')) ?>" class="toolbar__filters">
            <label class="sr-only" for="fiscal-year">Fiscal year</label>
            <select class="select select--sm" id="fiscal-year" name="fiscal_year" onchange="this.form.submit()" style="max-width:220px">
                <option value="">All fiscal years</option>
                <?php foreach ($fiscalYears as $year): ?>
                    <option value="<?= e($year) ?>" <?= $fiscalYear === $year ? 'selected' : '' ?>><?= e($year) ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button class="btn btn--secondary btn--sm" type="submit">Apply</button></noscript>
        </form>
        <p class="text-xs text-muted">Approved funding applications: <strong><?= e(money($approvedFunding)) ?></strong></p>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <span class="stat-card__top"><span class="stat-card__label">Allocated budget</span><span class="stat-card__icon"><?= icon('budget', 17) ?></span></span>
        <span class="stat-card__value"><?= e(money($allocated, true)) ?></span>
        <span class="stat-card__meta">Approved budget lines</span>
    </div>
    <div class="stat-card stat-card--success">
        <span class="stat-card__top"><span class="stat-card__label">Approved expenditure</span><span class="stat-card__icon"><?= icon('expenditure', 17) ?></span></span>
        <span class="stat-card__value"><?= e(money($spent, true)) ?></span>
        <span class="stat-card__meta">Recorded and approved</span>
    </div>
    <div class="stat-card stat-card--warning">
        <span class="stat-card__top"><span class="stat-card__label">Pending expenditure</span><span class="stat-card__icon"><?= icon('clock', 17) ?></span></span>
        <span class="stat-card__value"><?= e(money($pendingSpend, true)) ?></span>
        <span class="stat-card__meta">Awaiting approval</span>
    </div>
    <div class="stat-card stat-card--info">
        <span class="stat-card__top"><span class="stat-card__label">Remaining balance</span><span class="stat-card__icon"><?= icon('funding', 17) ?></span></span>
        <span class="stat-card__value"><?= e(money($remaining, true)) ?></span>
        <span class="stat-card__meta">Allocated minus approved spend</span>
    </div>
    <div class="stat-card stat-card--success">
        <span class="stat-card__top"><span class="stat-card__label">Utilisation</span><span class="stat-card__icon"><?= icon('analytics', 17) ?></span></span>
        <span class="stat-card__value"><?= (int) $utilisation ?>%</span>
        <span class="stat-card__meta">Share of allocated budget spent</span>
    </div>
</div>

<div class="grid-wide" style="margin-bottom:18px">
    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Budget utilisation by category</h2>
                <p class="card__subtitle">Allocated versus approved expenditure</p>
            </div>
        </div>
        <div class="card__body">
            <?php if ($byCategory): ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th scope="col">Category</th><th scope="col" class="text-right">Allocated</th><th scope="col" class="text-right">Spent</th><th scope="col">Utilisation</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($byCategory as $label => $totals):
                                $pct = min(100, (int) percentage($totals['spent'], $totals['allocated']));
                                ?>
                                <tr>
                                    <td data-label="Category"><?= e($label) ?></td>
                                    <td data-label="Allocated" class="text-right"><?= e(money($totals['allocated'])) ?></td>
                                    <td data-label="Spent" class="text-right"><?= e(money($totals['spent'])) ?></td>
                                    <td data-label="Utilisation">
                                        <div class="progress-cell">
                                            <div class="progress progress--<?= $pct >= 90 ? 'warning' : 'success' ?>" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= e($label) ?> utilisation">
                                                <span class="progress__bar" style="width:<?= $pct ?>%"></span>
                                            </div>
                                            <span class="progress-cell__value"><?= $pct ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state"><p class="text-secondary">No approved budget lines have been recorded yet.</p></div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <div class="card__header">
            <div>
                <h2 class="card__title">Funding by source</h2>
                <p class="card__subtitle">Approved and disbursed applications</p>
            </div>
        </div>
        <div class="card__body"><?= chart_donut($bySource, 'Approved funding by source', 'Total', APP_CURRENCY . ' ') ?></div>
    </section>
</div>

<section class="card">
    <div class="card__header">
        <div>
            <h2 class="card__title">Top projects by allocation</h2>
            <p class="card__subtitle">Largest approved budgets and their spend</p>
        </div>
    </div>
    <?php if ($byProject): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th scope="col">Project</th><th scope="col" class="text-right">Allocated</th><th scope="col" class="text-right">Spent</th><th scope="col">Utilisation</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($byProject as $project):
                        $pct = min(100, (int) percentage((float) $project['spent'], (float) $project['allocated']));
                        ?>
                        <tr>
                            <td data-label="Project">
                                <div class="cell-title">
                                    <span class="cell-title__main"><?= e(truncate($project['title'], 70)) ?></span>
                                    <span class="cell-title__meta"><?= e($project['code'] ?: 'No project code') ?></span>
                                </div>
                            </td>
                            <td data-label="Allocated" class="text-right"><?= e(money($project['allocated'])) ?></td>
                            <td data-label="Spent" class="text-right"><?= e(money($project['spent'])) ?></td>
                            <td data-label="Utilisation">
                                <div class="progress-cell">
                                    <div class="progress progress--<?= $pct >= 90 ? 'warning' : 'success' ?>" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Project utilisation">
                                        <span class="progress__bar" style="width:<?= $pct ?>%"></span>
                                    </div>
                                    <span class="progress-cell__value"><?= $pct ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state"><p class="text-secondary">No project budgets are available for this filter.</p></div>
    <?php endif; ?>
</section>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
