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

include 'includes/header.php';
include 'includes/sidebar.php';

?>

<div class="content">

<h2>Management summary</h2>
<form method="get" class="row g-2 mb-3">
    <div class="col-md-3"><input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control"></div>
    <div class="col-md-3"><input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control"></div>
    <div class="col-md-2"><button class="btn btn-primary">Apply</button></div>
</form>

<div class="row">

    <div class="col-md-3">
        <div class="card bg-primary text-white p-3">
            <h5>Total Cost</h5>
            <h3>
                ₹ <?= number_format($totalBill['total_cost'],0) ?>
            </h3>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card bg-success text-white p-3">
            <h5>Total Units</h5>
            <h3>
                <?= number_format($totalBill['total_units'],0) ?>
            </h3>
        </div>
    </div>

</div>

<br>

<div class="row">

    <div class="col-md-6">

        <div class="card p-3">

            <h5>KAL vs SAFL Cost</h5>

            <canvas id="companyChart"></canvas>

        </div>

    </div>

    <div class="col-md-6">
        <div class="card p-3">
            <h5>BECIL vs NON-BECIL</h5>
            <canvas id="typeChart"></canvas>
        </div>
    </div>

</div>

</div>

<script>

const ctx = document.getElementById('companyChart');

new Chart(ctx, {
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

new Chart(
document.getElementById('typeChart'),
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