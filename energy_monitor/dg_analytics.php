<?php

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require 'config/db.php';
require 'includes/auth_helpers.php';
require 'includes/plant_standards.php';
ems_ensure_plant($conn);

function dg_minutes(float $hmm): int
{
    $hours = (int) floor($hmm + 0.00001);
    $mins = (int) round(($hmm - $hours) * 100);
    if ($mins < 0) {
        $mins = 0;
    }
    return ($hours * 60) + $mins;
}

function dg_format_minutes(int $minutes): string
{
    if ($minutes < 0) {
        $minutes = 0;
    }
    return sprintf('%d.%02d', intdiv($minutes, 60), $minutes % 60);
}

$branch = trim((string) ($_GET['branch'] ?? ''));
if ($branch !== '' && !user_can_branch($branch)) {
    $branch = '';
}
$branchSql = 'SELECT branch_name, company FROM bill_sites';
$branchParams = [];
if (!sees_all_sites()) {
    [$branchClause, $branchParams] = scope_branch_clause('branch_name');
    $branchSql .= ' WHERE 1=1' . $branchClause;
}
$branchSql .= ' ORDER BY branch_name';
$branchStmt = $conn->prepare($branchSql);
$branchStmt->execute($branchParams);
$branchOptions = $branchStmt->fetchAll(PDO::FETCH_ASSOC);

$category = strtoupper((string) ($_GET['category'] ?? ''));
if (!in_array($category, ['', 'CTI', 'OFFICE'], true)) {
    $category = '';
}
$from = (string) ($_GET['from'] ?? date('Y-m-01'));
$to = (string) ($_GET['to'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}

$sql = 'SELECT * FROM dg_daily_log WHERE log_date BETWEEN ? AND ?';
$params = [$from, $to];
if (!sees_all_sites()) {
    [$clause, $scope] = scope_branch_clause('branch_name');
    $sql .= $clause;
    $params = array_merge($params, $scope);
}
if ($category !== '') {
    $sql .= ' AND category = ?';
    $params[] = $category;
}
if ($branch !== '') {
    $sql .= ' AND branch_name = ?';
    $params[] = $branch;
}
$sql .= ' ORDER BY branch_name, category, log_date';
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$groups = [];
$categoryTotals = ['CTI' => ['runs' => 0, 'kwh' => 0.0, 'minutes' => 0, 'diesel' => 0.0], 'OFFICE' => ['runs' => 0, 'kwh' => 0.0, 'minutes' => 0, 'diesel' => 0.0]];
foreach ($rows as $row) {
    $key = $row['branch_name'] . '|' . $row['category'];
    if (!isset($groups[$key])) {
        $groups[$key] = [
            'branch' => $row['branch_name'],
            'company' => $row['company'],
            'category' => $row['category'],
            'runs' => 0,
            'kwh' => 0.0,
            'minutes' => 0,
            'diesel' => 0.0,
            'fuel_qty' => 0.0,
        ];
    }
    $groups[$key]['runs']++;
    $groups[$key]['kwh'] += (float) $row['kwh'];
    $groups[$key]['minutes'] += dg_minutes((float) $row['running_hmm']);
    $groups[$key]['diesel'] += (float) ($row['consumption_litres'] ?? 0);
    $groups[$key]['fuel_qty'] += (float) ($row['fuel_qty'] ?? 0);
    $bucket = $row['category'] === 'OFFICE' ? 'OFFICE' : 'CTI';
    $categoryTotals[$bucket]['runs']++;
    $categoryTotals[$bucket]['kwh'] += (float) $row['kwh'];
    $categoryTotals[$bucket]['minutes'] += dg_minutes((float) $row['running_hmm']);
    $categoryTotals[$bucket]['diesel'] += (float) ($row['consumption_litres'] ?? 0);
}
$groups = array_values($groups);

$chartLabels = [];
$chartKwh = [];
foreach ($groups as $group) {
    $chartLabels[] = $group['branch'] . ' ' . ($group['category'] === 'OFFICE' ? 'Office' : 'CTI');
    $chartKwh[] = round($group['kwh'], 2);
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="content">
    <h1 class="page-title">DG operations analytics</h1>
    <p class="page-lead">Compare CTI and Office diesel runs across locations. Hours are added as hours and minutes, so 1.45 plus 0.15 is 2.00 hours. Figures come from saved log rows.</p>

    <form method="get" class="panel mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="branch">Location</label>
                <select id="branch" name="branch" class="form-select">
                    <option value="">All locations</option>
                    <?php foreach ($branchOptions as $site) { ?>
                    <option value="<?= htmlspecialchars($site['branch_name']) ?>" <?= strcasecmp($branch, $site['branch_name']) === 0 ? 'selected' : '' ?>>
                        <?= htmlspecialchars($site['branch_name'] . ' · ' . $site['company']) ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="category">Category</label>
                <select id="category" name="category" class="form-select">
                    <option value="">CTI and Office</option>
                    <option value="CTI" <?= $category === 'CTI' ? 'selected' : '' ?>>CTI</option>
                    <option value="OFFICE" <?= $category === 'OFFICE' ? 'selected' : '' ?>>Office</option>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label" for="from">From</label><input id="from" type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>"></div>
            <div class="col-md-3"><label class="form-label" for="to">To</label><input id="to" type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>"></div>
            <div class="col-md-3"><button class="btn btn-primary">Apply</button></div>
        </div>
    </form>

    <?php if ($rows === []) { ?>
    <div class="empty-state">No DG operations are saved for this filter. Enter runs on the DG operations log. The sample worksheet is not loaded as live data.</div>
    <?php } else { ?>
    <div class="kpi-grid">
        <?php foreach (['CTI' => 'CTI', 'OFFICE' => 'Office'] as $key => $title) { ?>
        <div class="panel">
            <h2><?= $title ?></h2>
            <div class="kpi-value"><?= number_format($categoryTotals[$key]['kwh'], 2) ?><span class="kpi-unit">kWh</span></div>
            <div class="kpi-note"><?= (int) $categoryTotals[$key]['runs'] ?> runs · <?= dg_format_minutes($categoryTotals[$key]['minutes']) ?> h · <?= number_format($categoryTotals[$key]['diesel'], 1) ?> L</div>
        </div>
        <?php } ?>
    </div>

    <div class="panel mb-3">
        <h2>kWh by location</h2>
        <div style="height:280px"><canvas id="dgCompare"></canvas></div>
    </div>

    <div class="table-card p-2">
        <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
            <thead>
                <tr><th>Location</th><th>Company</th><th>Category</th><th class="text-end">Runs</th><th class="text-end">kWh</th><th class="text-end">Running H.MM</th><th class="text-end">Diesel (L)</th><th class="text-end">Fuel sharing (L)</th></tr>
            </thead>
            <tbody>
            <?php foreach ($groups as $group) { ?>
            <tr>
                <td><?= htmlspecialchars($group['branch']) ?></td>
                <td><?= htmlspecialchars($group['company']) ?></td>
                <td><?= $group['category'] === 'OFFICE' ? 'Office' : 'CTI' ?></td>
                <td class="text-end"><?= (int) $group['runs'] ?></td>
                <td class="text-end"><?= number_format($group['kwh'], 2) ?></td>
                <td class="text-end"><?= dg_format_minutes($group['minutes']) ?></td>
                <td class="text-end"><?= number_format($group['diesel'], 2) ?></td>
                <td class="text-end"><?= number_format($group['fuel_qty'], 2) ?></td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
        </div>
    </div>
    <script>
    new Chart(document.getElementById('dgCompare'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{ label: 'kWh', data: <?= json_encode($chartKwh) ?>, backgroundColor: '#c2410c' }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });
    </script>
    <?php } ?>
    <p class="mt-3"><a href="dg_operations.php">Open the DG operations log</a> · <a href="dg_report.php">Saved DG worksheets</a></p>
</div>
<?php include 'includes/footer.php'; ?>
