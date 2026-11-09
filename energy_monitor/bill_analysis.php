<?php

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require 'config/db.php';
require 'includes/auth_helpers.php';
require 'includes/bill_catalog.php';

bill_ensure($conn);

$company = strtoupper($_GET['company'] ?? '');
$type = strtoupper($_GET['type'] ?? '');
$branch = $_GET['branch'] ?? '';
$broadcaster = $_GET['broadcaster'] ?? '';
$transmitter = $_GET['transmitter'] ?? '';
$month = $_GET['month'] ?? '';

$where = ' WHERE 1=1 ';
$params = [];
if (in_array($company, ['KAL', 'SAFL'], true)) {
    $where .= ' AND w.company = ?';
    $params[] = $company;
}
if (in_array($type, ['BECIL', 'NON_BECIL', 'DG'], true)) {
    $where .= ' AND w.bill_type = ?';
    $params[] = $type;
}
if (!sees_all_sites()) {
    if ($branch !== '' && !user_can_branch($branch)) {
        $branch = user_branches()[0] ?? '';
    }
    if ($branch === '') {
        [$clause, $scope] = scope_branch_clause('w.branch_name');
        $where .= $clause;
        $params = array_merge($params, $scope);
    }
}
if ($branch !== '') {
    $where .= ' AND w.branch_name = ?';
    $params[] = $branch;
}
if ($broadcaster !== '') {
    $where .= ' AND l.broadcaster = ?';
    $params[] = $broadcaster;
}
if ($transmitter !== '') {
    $where .= ' AND l.transmitter = ?';
    $params[] = $transmitter;
}
if (preg_match('/^\d{4}-\d{2}$/', $month)) {
    $where .= ' AND w.bill_month = ?';
    $params[] = $month;
}
$fromMonth = $_GET['from_month'] ?? '';
$toMonth = $_GET['to_month'] ?? '';
if (preg_match('/^\d{4}-\d{2}$/', $fromMonth)) {
    $where .= ' AND w.bill_month >= ?';
    $params[] = $fromMonth;
}
if (preg_match('/^\d{4}-\d{2}$/', $toMonth)) {
    $where .= ' AND w.bill_month <= ?';
    $params[] = $toMonth;
}

$locationSql = "
    SELECT w.company, w.state_name, w.branch_name, w.bill_month, w.bill_date, w.bill_type,
           w.bill_amount, w.common_units,
           SUM(l.units) AS own_units,
           SUM(l.total_units) AS total_units,
           SUM(l.proportionate_amount) AS energy_amount,
           SUM(l.invoice_amount) AS management_invoice
    FROM eb_worksheets w
    INNER JOIN eb_worksheet_lines l ON l.worksheet_id = w.id
    $where
    GROUP BY w.id
    ORDER BY w.bill_month DESC, w.branch_name
";
$locationStmt = $conn->prepare($locationSql);
$locationStmt->execute($params);
$locations = $locationStmt->fetchAll(PDO::FETCH_ASSOC);

$broadcasterSql = "
    SELECT l.broadcaster, l.transmitter,
           SUM(l.units) AS units,
           SUM(l.total_units) AS total_units,
           SUM(l.proportionate_amount) AS energy_amount,
           COUNT(DISTINCT w.branch_name) AS locations
    FROM eb_worksheet_lines l
    INNER JOIN eb_worksheets w ON w.id = l.worksheet_id
    $where
    GROUP BY l.broadcaster, l.transmitter
    ORDER BY units DESC
";
$broadcasterStmt = $conn->prepare($broadcasterSql);
$broadcasterStmt->execute($params);
$byBroadcaster = $broadcasterStmt->fetchAll(PDO::FETCH_ASSOC);

$transmitterSql = "
    SELECT l.transmitter,
           SUM(l.units) AS units,
           SUM(l.proportionate_amount) AS energy_amount,
           COUNT(*) AS meters
    FROM eb_worksheet_lines l
    INNER JOIN eb_worksheets w ON w.id = l.worksheet_id
    $where
    GROUP BY l.transmitter
    ORDER BY units DESC
";
$txStmt = $conn->prepare($transmitterSql);
$txStmt->execute($params);
$byTransmitter = $txStmt->fetchAll(PDO::FETCH_ASSOC);

$intra = [];
if ($branch !== '') {
    $intraSql = "
        SELECT l.broadcaster, l.transmitter, w.bill_month,
               l.units, l.common_area_units, l.total_units, l.proportionate_amount
        FROM eb_worksheet_lines l
        INNER JOIN eb_worksheets w ON w.id = l.worksheet_id
        $where
        ORDER BY w.bill_month, l.line_no
    ";
    $intraStmt = $conn->prepare($intraSql);
    $intraStmt->execute($params);
    $intra = $intraStmt->fetchAll(PDO::FETCH_ASSOC);
}

$branches = $conn->query("SELECT DISTINCT branch_name FROM bill_sites ORDER BY branch_name")->fetchAll(PDO::FETCH_COLUMN);
$names = $conn->query("SELECT DISTINCT broadcaster FROM bill_site_broadcasters ORDER BY broadcaster")->fetchAll(PDO::FETCH_COLUMN);
$transmitters = $conn->query("SELECT DISTINCT transmitter FROM bill_site_broadcasters ORDER BY transmitter")->fetchAll(PDO::FETCH_COLUMN);

$projection = [];
if ($locations !== []) {
    $byBranch = [];
    foreach ($locations as $row) {
        $byBranch[$row['branch_name']][] = (float) $row['bill_amount'];
    }
    foreach ($byBranch as $name => $amounts) {
        $current = $amounts[0];
        $average = array_sum($amounts) / count($amounts);
        $projection[] = [
            'branch' => $name,
            'months' => count($amounts),
            'latest' => $current,
            'average' => $average,
            'variance' => $average > 0 ? (($current - $average) / $average) * 100 : 0,
        ];
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
.bill-hero { display:flex; justify-content:space-between; gap:16px; align-items:flex-end; margin-bottom:16px; }
.bill-hero h2 { margin:0; color:#0f2744; font-weight:750; }
.month-strip { display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:12px; margin-bottom:16px; }
.month-tile { background:#fff; border-radius:14px; padding:16px; box-shadow:0 8px 24px rgba(15,39,68,.06); border-top:4px solid #2563eb; }
.month-tile strong { display:block; font-size:1.35rem; color:#0f2744; font-variant-numeric:tabular-nums; }
.month-tile span { color:#64748b; font-size:.8rem; }
.delta { display:inline-block; margin-top:8px; padding:2px 8px; border-radius:999px; font-size:.75rem; font-weight:700; }
.delta-up { background:#fef2f2; color:#b42318; }
.delta-down { background:#ecfdf3; color:#15803d; }
.delta-flat { background:#f1f5f9; color:#475569; }
.compare-card { background:#fff; border:0; border-radius:14px; box-shadow:0 8px 24px rgba(15,39,68,.06); }
.compare-card h5 { color:#0f2744; font-weight:700; }
.compare-card table thead th { font-size:.72rem; letter-spacing:.06em; text-transform:uppercase; color:#64748b; }
</style>
<div class="content">

<div class="bill-hero">
    <div>
        <h2>Bill comparison</h2>
        <p class="text-muted mb-0">Cost and units by month, branch, broadcaster, and transmitter.</p>
    </div>
</div>

<form method="get" class="row g-2 mb-4">
    <div class="col-md-2">
        <label class="form-label">Company</label>
        <select name="company" class="form-select">
            <option value="">All</option>
            <option value="KAL" <?= $company === 'KAL' ? 'selected' : '' ?>>KAL (South)</option>
            <option value="SAFL" <?= $company === 'SAFL' ? 'selected' : '' ?>>SAFL (North)</option>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label">Bill</label>
        <select name="type" class="form-select">
            <option value="">All</option>
            <?php foreach (['BECIL' => 'EB BECIL', 'NON_BECIL' => 'EB non-BECIL', 'DG' => 'DG'] as $value => $label) { ?>
            <option value="<?= $value ?>" <?= $type === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label">Branch</label>
        <select name="branch" class="form-select">
            <option value="">All</option>
            <?php foreach ($branches as $name) { ?>
            <option value="<?= htmlspecialchars($name) ?>" <?= $branch === $name ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label">Broadcaster</label>
        <select name="broadcaster" class="form-select">
            <option value="">All</option>
            <?php foreach ($names as $name) { ?>
            <option value="<?= htmlspecialchars($name) ?>" <?= $broadcaster === $name ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label">Transmitter</label>
        <select name="transmitter" class="form-select">
            <option value="">All</option>
            <?php foreach ($transmitters as $name) { ?>
            <option value="<?= htmlspecialchars($name) ?>" <?= $transmitter === $name ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label">One month</label>
        <input type="month" name="month" value="<?= htmlspecialchars($month) ?>" class="form-control">
    </div>
    <div class="col-md-2">
        <label class="form-label">From month</label>
        <input type="month" name="from_month" value="<?= htmlspecialchars($fromMonth) ?>" class="form-control">
    </div>
    <div class="col-md-2">
        <label class="form-label">To month</label>
        <input type="month" name="to_month" value="<?= htmlspecialchars($toMonth) ?>" class="form-control">
    </div>
    <div class="col-12">
        <button class="btn btn-primary">Apply</button>
    </div>
</form>

<?php
$monthRollup = [];
foreach ($locations as $row) {
    $key = $row['bill_month'];
    if (!isset($monthRollup[$key])) {
        $monthRollup[$key] = ['amount' => 0, 'units' => 0, 'sites' => 0];
    }
    $monthRollup[$key]['amount'] += (float) $row['bill_amount'];
    $monthRollup[$key]['units'] += (float) $row['own_units'];
    $monthRollup[$key]['sites']++;
}
ksort($monthRollup);
$previousAmount = null;
?>
<?php if ($locations === []) { ?>
<div class="alert alert-info">No saved worksheets match these filters. Enter a BECIL, non-BECIL, or DG worksheet first.</div>
<?php } else { ?>

<div class="month-strip">
<?php foreach ($monthRollup as $label => $bucket) {
    $change = $previousAmount > 0 ? (($bucket['amount'] - $previousAmount) / $previousAmount) * 100 : null;
    $previousAmount = $bucket['amount'];
    $deltaClass = $change === null ? 'delta-flat' : ($change > 0.5 ? 'delta-up' : ($change < -0.5 ? 'delta-down' : 'delta-flat'));
    $monthName = date('M Y', strtotime($label . '-01'));
?>
    <div class="month-tile">
        <span><?= htmlspecialchars($monthName) ?> · <?= (int) $bucket['sites'] ?> site<?= (int) $bucket['sites'] === 1 ? '' : 's' ?></span>
        <strong>₹ <?= bill_inr($bucket['amount']) ?></strong>
        <div><span><?= number_format($bucket['units'], 0) ?> units</span></div>
        <div class="delta <?= $deltaClass ?>"><?= $change === null ? 'First month in view' : (($change > 0 ? '+' : '') . number_format($change, 1) . '% vs previous month') ?></div>
    </div>
<?php } ?>
</div>

<div class="row mb-4">
    <div class="col-lg-7">
        <div class="card compare-card p-3">
            <h5>Locations</h5>
            <canvas id="locationChart" height="140"></canvas>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card compare-card p-3">
            <h5>Against the branch average</h5>
            <p class="small text-muted">Latest bill versus the average of the months in this filter. A positive variance is above the branch’s own history.</p>
            <table class="table table-sm">
                <thead><tr><th>Branch</th><th>Latest</th><th>Average</th><th>Variance</th></tr></thead>
                <tbody>
                <?php foreach ($projection as $row) { ?>
                <tr>
                    <td><?= htmlspecialchars($row['branch']) ?></td>
                    <td><?= bill_inr($row['latest']) ?></td>
                    <td><?= bill_inr($row['average']) ?></td>
                    <?php $vClass = $row['variance'] > 0.5 ? 'delta-up' : ($row['variance'] < -0.5 ? 'delta-down' : 'delta-flat'); ?>
                    <td><span class="delta <?= $vClass ?>"><?= ($row['variance'] > 0 ? '+' : '') . number_format($row['variance'], 1) ?>%</span></td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card compare-card p-3 mb-4">
    <h5>Branch to branch</h5>
    <table class="table table-bordered table-sm">
        <thead>
            <tr>
                <th>Month</th><th>Bill date</th><th>Company</th><th>State</th><th>Branch</th><th>Type</th>
                <th>Own units</th><th>Common units</th><th>Bill</th><th>Management invoices</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($locations as $row) { ?>
        <tr>
            <td><?= htmlspecialchars($row['bill_month']) ?></td>
            <td><?= htmlspecialchars((string) ($row['bill_date'] ?? '')) ?></td>
            <td><?= htmlspecialchars($row['company']) ?></td>
            <td><?= htmlspecialchars($row['state_name']) ?></td>
            <td><?= htmlspecialchars($row['branch_name']) ?></td>
            <td><?= htmlspecialchars($row['bill_type']) ?></td>
            <td class="text-end"><?= number_format((float) $row['own_units'], 2) ?></td>
            <td class="text-end"><?= number_format((float) $row['common_units'], 2) ?></td>
            <td class="text-end"><?= bill_inr($row['bill_amount']) ?></td>
            <td class="text-end"><?= bill_inr($row['management_invoice']) ?></td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
</div>

<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card compare-card p-3">
            <h5>Broadcasters</h5>
            <canvas id="broadcasterChart" height="160"></canvas>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card compare-card p-3">
            <h5>Transmitters</h5>
            <canvas id="transmitterChart" height="160"></canvas>
        </div>
    </div>
</div>

<div class="card compare-card p-3 mb-4">
    <h5>Broadcaster and transmitter</h5>
    <table class="table table-bordered table-sm">
        <thead>
            <tr><th>Broadcaster</th><th>Transmitter</th><th>Locations</th><th>Own units</th><th>Total units</th><th>Energy amount</th></tr>
        </thead>
        <tbody>
        <?php foreach ($byBroadcaster as $row) { ?>
        <tr>
            <td><?= htmlspecialchars($row['broadcaster']) ?></td>
            <td><?= htmlspecialchars($row['transmitter']) ?></td>
            <td><?= (int) $row['locations'] ?></td>
            <td class="text-end"><?= number_format((float) $row['units'], 2) ?></td>
            <td class="text-end"><?= number_format((float) $row['total_units'], 2) ?></td>
            <td class="text-end"><?= bill_inr($row['energy_amount']) ?></td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
</div>

<?php if ($intra !== []) { ?>
<div class="card p-3 mb-4">
    <h5>Inside <?= htmlspecialchars($branch) ?></h5>
    <p class="small text-muted">Share of that month’s own units, so one broadcaster can be compared with the others at the same site.</p>
    <table class="table table-bordered table-sm">
        <thead>
            <tr><th>Month</th><th>Broadcaster</th><th>Transmitter</th><th>Own units</th><th>Share</th><th>Common share</th><th>Proportionate</th></tr>
        </thead>
        <tbody>
        <?php
        $monthTotals = [];
        foreach ($intra as $row) {
            $monthTotals[$row['bill_month']] = ($monthTotals[$row['bill_month']] ?? 0) + (float) $row['units'];
        }
        foreach ($intra as $row) {
            $base = $monthTotals[$row['bill_month']] ?? 0;
            $share = $base > 0 ? ((float) $row['units'] / $base) * 100 : 0;
        ?>
        <tr>
            <td><?= htmlspecialchars($row['bill_month']) ?></td>
            <td><?= htmlspecialchars($row['broadcaster']) ?></td>
            <td><?= htmlspecialchars($row['transmitter']) ?></td>
            <td class="text-end"><?= number_format((float) $row['units'], 2) ?></td>
            <td class="text-end"><?= number_format($share, 1) ?>%</td>
            <td class="text-end"><?= number_format((float) $row['common_area_units'], 2) ?></td>
            <td class="text-end"><?= bill_inr($row['proportionate_amount']) ?></td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
</div>
<?php } else { ?>
<div class="alert alert-secondary">Choose one branch to see the intra-site split between its broadcasters.</div>
<?php } ?>

<script>
const locationRows = <?= json_encode(array_map(function ($row) {
    return [
        'label' => $row['branch_name'] . ' ' . $row['bill_month'],
        'amount' => (float) $row['bill_amount'],
    ];
}, array_slice($locations, 0, 12)), JSON_UNESCAPED_UNICODE) ?>;

const broadcasterRows = <?= json_encode(array_map(function ($row) {
    return [
        'label' => $row['broadcaster'],
        'units' => (float) $row['units'],
    ];
}, array_slice($byBroadcaster, 0, 10)), JSON_UNESCAPED_UNICODE) ?>;

const transmitterRows = <?= json_encode(array_map(function ($row) {
    return [
        'label' => $row['transmitter'],
        'units' => (float) $row['units'],
    ];
}, array_slice($byTransmitter, 0, 10)), JSON_UNESCAPED_UNICODE) ?>;

function barChart(id, rows, field, color) {
    const canvas = document.getElementById(id);
    if (!canvas || rows.length === 0) return;
    canvas.parentElement.style.height = Math.max(240, rows.length * 36) + 'px';
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map(row => row.label),
            datasets: [{ data: rows.map(row => row[field]), backgroundColor: color }]
        },
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            datasets: { bar: { borderRadius: 6, barThickness: 16 } },
            scales: {
                x: { grid: { color: '#e8eef5' }, ticks: { color: '#64748b' } },
                y: { grid: { display: false }, ticks: { color: '#0f2744', font: { weight: 600 } } }
            }
        }
    });
}
barChart('locationChart', locationRows, 'amount', '#1f6feb');
barChart('broadcasterChart', broadcasterRows, 'units', '#198754');
barChart('transmitterChart', transmitterRows, 'units', '#b58105');
</script>

<?php } ?>

</div>

<?php include 'includes/footer.php'; ?>
