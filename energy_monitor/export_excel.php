<?php

session_start();

if (!isset($_SESSION['user'])) {
    exit;
}

require 'config/db.php';

$year  = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Monthly_Report.xls");

echo "Date\tEB Energy\tDG Energy\tTotal Energy\n";

$stmt = $conn->prepare("
SELECT
    DATE(reading_time) AS report_date,

    MAX(
        CASE
            WHEN parameter_name='EB-KWH'
            THEN value
        END
    ) AS eb_kwh,

    MAX(
        CASE
            WHEN parameter_name='DG-KWH'
            THEN value
        END
    ) AS dg_kwh

FROM meter_readings

WHERE YEAR(reading_time)=?
AND MONTH(reading_time)=?

GROUP BY DATE(reading_time)

ORDER BY report_date DESC
");

$stmt->execute([$year, $month]);

foreach($stmt as $row)
{
    $eb = $row['eb_kwh'] ?? 0;
    $dg = $row['dg_kwh'] ?? 0;

    echo $row['report_date']."\t";
    echo $eb."\t";
    echo $dg."\t";
    echo ($eb + $dg)."\n";
}