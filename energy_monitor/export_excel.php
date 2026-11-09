<?php

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require 'config/db.php';
require 'includes/auth_helpers.php';

$year  = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$meterId = isset($_GET['meter_id']) ? (int) $_GET['meter_id'] : 0;
if ($meterId > 0 && !meter_belongs_to_user($conn, $meterId)) {
    http_response_code(403);
    exit('That meter is outside your account.');
}
if (!preg_match('/^\d{4}$/', (string) $year) || !preg_match('/^\d{1,2}$/', (string) $month)) {
    http_response_code(400);
    exit('Choose a valid year and month.');
}

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
" . ($meterId > 0 ? 'AND meter_id = ?' : '') . "

GROUP BY DATE(reading_time)

ORDER BY report_date DESC
");

$stmt->execute($meterId > 0 ? [$year, $month, $meterId] : [$year, $month]);

foreach($stmt as $row)
{
    $eb = $row['eb_kwh'] ?? 0;
    $dg = $row['dg_kwh'] ?? 0;

    echo $row['report_date']."\t";
    echo $eb."\t";
    echo $dg."\t";
    echo ($eb + $dg)."\n";
}