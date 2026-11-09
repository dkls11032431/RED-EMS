<?php

session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

require 'config/db.php';

$year  = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

$stmt = $conn->prepare("
    SELECT
        DATE(reading_time) AS report_date,

        MAX(
            CASE
                WHEN parameter_name = 'EB-KWH'
                THEN value
            END
        ) AS eb_kwh,

        MAX(
            CASE
                WHEN parameter_name = 'DG-KWH'
                THEN value
            END
        ) AS dg_kwh

    FROM meter_readings

    WHERE YEAR(reading_time) = ?
    AND MONTH(reading_time) = ?

    GROUP BY DATE(reading_time)

    ORDER BY report_date DESC
");

$stmt->execute([$year, $month]);

include 'includes/header.php';
include 'includes/sidebar.php';

?>

<div class="content">

    <h2>📈 Monthly Energy Report</h2>

    <form method="get" class="mb-3">

        <div class="row">

            <div class="col-md-3">

                <select name="month" class="form-control">

                    <?php
                    for($m = 1; $m <= 12; $m++)
                    {
                        $monthValue = sprintf('%02d', $m);
                    ?>

                    <option
                        value="<?= $monthValue ?>"
                        <?= ($month == $monthValue) ? 'selected' : '' ?>>

                        <?= date('F', mktime(0,0,0,$m,1)); ?>

                    </option>

                    <?php } ?>

                </select>

            </div>

            <div class="col-md-3">

                <select name="year" class="form-control">

                    <?php
                    for($y = date('Y'); $y >= 2020; $y--)
                    {
                    ?>

                    <option
                        value="<?= $y ?>"
                        <?= ($year == $y) ? 'selected' : '' ?>>

                        <?= $y ?>

                    </option>

                    <?php } ?>

                </select>

            </div>

            <div class="col-md-2">

                <button
                    type="submit"
                    class="btn btn-primary">

                    Filter

                </button>

            </div>

        </div>

   </form>

<a href="export_pdf.php?year=<?= $year; ?>&month=<?= $month; ?>"
   class="btn btn-danger">

    📄 Export PDF

</a>

<a href="export_excel.php?year=<?= $year; ?>&month=<?= $month; ?>"
   class="btn btn-success">
   📄 Export Excel
</a>

<table class="table table-bordered table-striped">
        <thead>

            <tr>
                <th>Date</th>
                <th>EB Energy</th>
                <th>DG Energy</th>
                <th>Total Energy</th>
            </tr>

        </thead>

        <tbody>

            <?php foreach($stmt as $row){ ?>

            <tr>

                <td><?= $row['report_date']; ?></td>

                <td>
                    <?= number_format($row['eb_kwh'] ?? 0, 3); ?>
                </td>

                <td>
                    <?= number_format($row['dg_kwh'] ?? 0, 3); ?>
                </td>

                <td>
                    <?= number_format(
                        ($row['eb_kwh'] ?? 0) +
                        ($row['dg_kwh'] ?? 0),
                        3
                    ); ?>
                </td>

            </tr>

            <?php } ?>

        </tbody>

    </table>

</div>

<?php include 'includes/footer.php'; ?>