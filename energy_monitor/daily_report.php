<?php

session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

require 'config/db.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

$stmt = $conn->prepare("
SELECT

    mm.meter_name,

    DATE(mr.reading_time) AS report_date,

    MAX(
        CASE
            WHEN mr.parameter_name = 'EB-KWH'
            THEN mr.value
        END
    )
    -
    MIN(
        CASE
            WHEN mr.parameter_name = 'EB-KWH'
            THEN mr.value
        END
    ) AS eb_consumption,

    MAX(
        CASE
            WHEN mr.parameter_name = 'DG-KWH'
            THEN mr.value
        END
    )
    -
    MIN(
        CASE
            WHEN mr.parameter_name = 'DG-KWH'
            THEN mr.value
        END
    ) AS dg_consumption

FROM meter_readings mr

LEFT JOIN meter_master mm
ON mr.meter_id = mm.meter_id

WHERE DATE(mr.reading_time)
BETWEEN ? AND ?

GROUP BY
    mm.meter_name,
    DATE(mr.reading_time)

ORDER BY
    report_date DESC,
    mm.meter_name
");

$stmt->execute([$from, $to]);

include 'includes/header.php';
include 'includes/sidebar.php';

?>

<div class="content">

    <h2>📊 Daily Energy Report</h2>

    <form method="get" class="row mb-3">

        <div class="col-md-3">
            <input
                type="date"
                name="from"
                value="<?= $from ?>"
                class="form-control">
        </div>

        <div class="col-md-3">
            <input
                type="date"
                name="to"
                value="<?= $to ?>"
                class="form-control">
        </div>

        <div class="col-md-2">
            <button
                class="btn btn-primary">
                Search
            </button>
        </div>

    </form>


<a href="export_pdf.php?from=<?= $from; ?>&to=<?= $to; ?>"
   class="btn btn-danger">
   📄 Export PDF
</a>

<a href="export_excel.php?from=<?= $from; ?>&to=<?= $to; ?>"
   class="btn btn-success">
   📊 Export Excel
</a>


<br><br>

    <table class="table table-bordered table-striped">

        <thead>
    <tr>
        <th>Meter NAME</th>
        <th>Date</th>
        <th>EB Consumption (kWh)</th>
        <th>DG Consumption (kWh)</th>
        <th>Total Consumption (kWh)</th>
    </tr>
</thead>

<tbody>

<?php foreach($stmt as $row){ ?>

<tr>

    <td><?= $row['meter_name'];?></td>

    <td><?= $row['report_date']; ?></td>

    <td>
        <?= number_format($row['eb_consumption'] ?? 0, 3); ?>
    </td>

    <td>
        <?= number_format($row['dg_consumption'] ?? 0, 3); ?>
    </td>

    <td>
        <?= number_format(
            ($row['eb_consumption'] ?? 0) +
            ($row['dg_consumption'] ?? 0),
            3
        ); ?>
    </td>

</tr>

<?php } ?>

</tbody>

    </table>

</div>

<?php include 'includes/footer.php'; ?>