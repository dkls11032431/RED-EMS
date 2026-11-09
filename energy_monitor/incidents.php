<?php

require 'config/db.php';

$incidents = $conn->query("
SELECT *
FROM incidents
ORDER BY opened_at DESC
");

include 'includes/header.php';
?>

<div class="container">

<h2>🎫 Incident Management</h2>

<table class="table table-bordered table-striped">

<tr>
    <th>ID</th>
    <th>Site</th>
    <th>Alarm</th>
    <th>Severity</th>
    <th>Assigned To</th>
    <th>Status</th>
    <th>Opened</th>
    <th>Resolved</th>
</tr>

<?php foreach($incidents as $row){ ?>

<tr>

<td><?= $row['id'] ?></td>
<td><?= $row['site_name'] ?></td>
<td><?= $row['alarm_type'] ?></td>
<td><?= $row['severity'] ?></td>
<td><?= $row['assigned_to'] ?></td>
<td><?= $row['status'] ?></td>
<td><?= $row['opened_at'] ?></td>
<td><?= $row['resolved_at'] ?></td>

</tr>

<?php } ?>

</table>

</div>