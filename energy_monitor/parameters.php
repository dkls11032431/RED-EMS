<?php

session_start();

if(!isset($_SESSION['user']))
{
    header("Location: login.php");
    exit;
}

require 'config/db.php';
require 'includes/auth_helpers.php';
if (!is_admin()) {
    header('Location: dashboard.php');
    exit;
}

$stmt = $conn->query("
SELECT *
FROM parameter_master_princek2
ORDER BY id
");

include 'includes/header.php';
include 'includes/sidebar.php';

?>

<div class="content">

<div class="card shadow">

<div class="card-header bg-primary text-white">

<h5>⚙️ Parameter Master</h5>

</div>

<div class="card-body">

<table class="table table-striped table-hover">

<thead>

<tr>

<th>ID</th>
<th>Parameter</th>
<th>Type</th>
<th>Divisor</th>
<th>Group</th>

</tr>

</thead>

<tbody>

<?php foreach($stmt as $row){ ?>

<tr>

<td><?= $row['id']; ?></td>

<td><?= $row['parameter_name']; ?></td>

<td><?= $row['param_type']; ?></td>

<td><?= $row['divisor']; ?></td>

<td><?= $row['meter_group_id']; ?></td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

</div>

<?php include 'includes/footer.php'; ?>