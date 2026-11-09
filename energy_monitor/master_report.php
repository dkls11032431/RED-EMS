<?php

session_start();

if(!isset($_SESSION['user']))
{
    header("Location: login.php");
    exit;
}

require 'config/db.php';
require 'includes/auth_helpers.php';
require 'includes/plant_standards.php';
ems_ensure_plant($conn);
if (!sees_all_sites()) {
    header('Location: consumption_report.php');
    exit;
}

try {
$totalBill = $conn->query("
    SELECT
        SUM(bill_amount) total_cost,
        SUM(common_units) total_units
    FROM eb_worksheets
")->fetch();
} catch (Throwable $e) {
$totalBill = ['total_cost' => 0, 'total_units' => 0];
}

$from = $_GET['from'] ?? date('Y-m-01', strtotime('-5 months'));
$to = $_GET['to'] ?? date('Y-m-d');
$companyData = $conn->prepare("
    SELECT company, SUM(bill_amount) total_cost
    FROM eb_worksheets
    WHERE bill_date IS NULL OR bill_date BETWEEN ? AND ?
    GROUP BY company
");
$companyData->execute([$from, $to]);

$companies = [];
$costs = [];

foreach($companyData as $row)
{
    $companies[] = $row['company'];
    $costs[] = $row['total_cost'];
}

$typeData = $conn->prepare("
    SELECT bill_type, SUM(bill_amount) total_cost
    FROM eb_worksheets
    WHERE bill_date IS NULL OR bill_date BETWEEN ? AND ?
    GROUP BY bill_type
");
$typeData->execute([$from, $to]);

$types = [];
$typeCosts = [];

foreach($typeData as $row)
{
    $types[] = $row['bill_type'];
    $typeCosts[] = $row['total_cost'];
}

/*
 | TODO: this query is fetched but nothing renders it yet.
 | Was this meant to be a third chart (units per state)?
 | Left the query here but not wired to any output until confirmed.
*/
$stateStmt = $conn->prepare("
    SELECT state_name, SUM(common_units) units
    FROM eb_worksheets
    WHERE bill_date IS NULL OR bill_date BETWEEN ? AND ?
    GROUP BY state_name
");
$stateStmt->execute([$from, $to]);
$stateData = $stateStmt->fetchAll(PDO::FETCH_ASSOC);

$dgSummary = ['CTI' => ['runs' => 0, 'kwh' => 0], 'OFFICE' => ['runs' => 0, 'kwh' => 0]];
try {
    $dgStmt = $conn->prepare("
        SELECT category, COUNT(*) runs, COALESCE(SUM(kwh), 0) kwh
        FROM dg_daily_log
        WHERE log_date BETWEEN ? AND ?
        GROUP BY category
    ");
    $dgStmt->execute([$from, $to]);
    foreach ($dgStmt as $row) {
        $key = $row['category'] === 'OFFICE' ? 'OFFICE' : 'CTI';
        $dgSummary[$key] = ['runs' => (int) $row['runs'], 'kwh' => (float) $row['kwh']];
    }
} catch (Throwable $e) {
    $dgSummary = ['CTI' => ['runs' => 0, 'kwh' => 0], 'OFFICE' => ['runs' => 0, 'kwh' => 0]];
}

include 'includes/header.php';
include 'includes/sidebar.php';

?>

<div class="content">

<h1 class="page-title">Management summary</h1>
<p class="page-lead">Saved EB and DG worksheets by company and bill type, plus CTI and Office diesel runs. South states are KAL. Other states are SAFL. BECIL and NON BECIL stay separate.</p>
<form method="get" class="panel mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label">From</label><input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">To</label><input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control"></div>
        <div class="col-md-2"><button class="btn btn-primary">Apply</button></div>
    </div>
</form>

<div class="kpi-grid">
    <div class="panel">
        <h2>Worksheet cost</h2>
        <div class="kpi-value">₹ <?= number_format((float) ($totalBill['total_cost'] ?? 0), 0) ?></div>
        <div class="kpi-note">All saved worksheets</div>
    </div>
    <div class="panel">
        <h2>Common units</h2>
        <div class="kpi-value"><?= number_format((float) ($totalBill['total_units'] ?? 0), 0) ?><span class="kpi-unit">kWh</span></div>
        <div class="kpi-note">All saved worksheets</div>
    </div>
    <div class="panel">
        <h2>CTI diesel runs</h2>
        <div class="kpi-value"><?= number_format($dgSummary['CTI']['kwh'], 1) ?><span class="kpi-unit">kWh</span></div>
        <div class="kpi-note"><?= (int) $dgSummary['CTI']['runs'] ?> saved runs in the date window</div>
    </div>
    <div class="panel">
        <h2>Office diesel runs</h2>
        <div class="kpi-value"><?= number_format($dgSummary['OFFICE']['kwh'], 1) ?><span class="kpi-unit">kWh</span></div>
        <div class="kpi-note"><?= (int) $dgSummary['OFFICE']['runs'] ?> saved runs in the date window</div>
    </div>
</div>

<div class="chart-split">
    <div class="panel">
        <h2>KAL and SAFL cost</h2>
        <?php if ($companies === []) { ?>
        <div class="empty-state">No worksheet amounts in this window.</div>
        <?php } else { ?>
        <canvas id="companyChart" height="160"></canvas>
        <?php } ?>
    </div>
    <div class="panel">
        <h2>BECIL, NON BECIL and DG</h2>
        <?php if ($types === []) { ?>
        <div class="empty-state">No bill types in this window.</div>
        <?php } else { ?>
        <canvas id="typeChart" height="160"></canvas>
        <?php } ?>
    </div>
</div>

<div class="panel">
    <h2>Units by state</h2>
    <?php if ($stateData === []) { ?>
    <div class="empty-state">No state totals in this window.</div>
    <?php } else { ?>
    <div class="table-responsive">
    <table class="table table-sm mb-0">
        <thead><tr><th>State</th><th class="text-end">Common units</th></tr></thead>
        <tbody>
        <?php foreach ($stateData as $row) { ?>
        <tr>
            <td><?= htmlspecialchars((string) $row['state_name']) ?></td>
            <td class="text-end"><?= number_format((float) $row['units'], 2) ?></td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
    </div>
    <?php } ?>
    <p class="small text-muted mb-0 mt-2"><a href="dg_analytics.php">DG location comparison</a> · <a href="bill_analysis.php">Bill comparison</a></p>
</div>

</div>

<script>

const ctx = document.getElementById('companyChart');

if (ctx) new Chart(ctx, {
    type: 'pie',
    data: {
        labels: <?= json_encode($companies); ?>,
        datasets: [{
            label: 'Cost',
            data: <?= json_encode($costs); ?>,
            backgroundColor: [
                '#0d6efd',
                '#198754',
                '#ffc107',
                '#dc3545'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

const typeCanvas = document.getElementById('typeChart');
if (typeCanvas) new Chart(
typeCanvas,
{
    type: 'doughnut',
    data: {
        labels: <?= json_encode($types); ?>,
        datasets: [{
            data: <?= json_encode($typeCosts); ?>
        }]
    }
});

</script>

<?php include 'includes/footer.php'; ?>