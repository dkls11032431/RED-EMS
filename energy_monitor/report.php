<?php

session_start();

if(!isset($_SESSION['user']))
{
    header("Location: login.php");
    exit;
}

require 'config/db.php';

$sql = "
SELECT
DATE(reading_time) report_date,
MAX(value) - MIN(value) consumption
FROM meter_readings
WHERE parameter_name='EB-KWH'
GROUP BY DATE(reading_time)
";

$stmt = $conn->query($sql);
?>

<!DOCTYPE html>
<html>

<head>

<title>Reports</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel=
<div class="container mt-4">

<h2>Daily Consumption Report</h2>

<table class="table table-bordered">

<tr>
<th>Date</th>
<th>Consumption</th>
</tr>

<?php foreach($stmt as $row){ ?>

<tr>

<td><?= $row['report_date']; ?></td>

<td><?= $row['consumption']; ?></td>

</tr>

<?php } ?>

</table>

</div>

</body>

</html>