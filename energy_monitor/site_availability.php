<?php

require 'config/db.php';

$data = $conn->query("
SELECT *
FROM site_availability
ORDER BY availability_percent DESC
");

?>

<div class="container-fluid">

<h3>🟢 Site Availability Dashboard</h3>

<table class="table table-bordered table-striped">

<thead>
<tr>
    <th>Site</th>
    <th>Date</th>
    <th>Uptime</th>
    <th>Downtime</th>
    <th>Availability %</th>
</tr>
</thead>

<tbody>

<?php foreach($data as $row){ ?>

<tr>
    <td><?= $row['site_name']; ?></td>
    <td><?= $row['report_date']; ?></td>
    <td><?= $row['uptime_minutes']; ?></td>
    <td><?= $row['downtime_minutes']; ?></td>
    <td><?= $row['availability_percent']; ?>%</td>
</tr>

<?php } ?>

</tbody>

</table>

</div>