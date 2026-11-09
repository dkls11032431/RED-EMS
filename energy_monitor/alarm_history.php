<?php

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
require 'config/db.php';
require 'includes/auth_helpers.php';
require 'includes/plant_standards.php';
ems_ensure_plant($conn);

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to = $_GET['to'] ?? date('Y-m-d');
$branch = sees_all_sites() ? ($_GET['branch'] ?? '') : '';

$sql = 'SELECT * FROM alarms WHERE 1=1';
$params = [];
$columns = ems_table_columns($conn, 'alarms');
if (isset($columns['alarm_time'])) {
    $sql .= ' AND DATE(alarm_time) BETWEEN ? AND ?';
    $params[] = $from;
    $params[] = $to;
}
if (isset($columns['branch_name'])) {
    if (!sees_all_sites()) {
        [$clause, $scope] = scope_branch_clause('branch_name');
        $sql .= $clause;
        $params = array_merge($params, $scope);
    } elseif ($branch !== '') {
        $sql .= ' AND branch_name = ?';
        $params[] = $branch;
    }
}
$sql .= isset($columns['alarm_time']) ? ' ORDER BY alarm_time DESC' : ' ORDER BY id DESC';
$stmt = $conn->prepare($sql);
$stmt->execute($params);

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="content">
<h2>Alarm history</h2>
<form method="get" class="row g-2 mb-3">
    <div class="col-md-3"><label class="form-label">From</label><input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control"></div>
    <div class="col-md-3"><label class="form-label">To</label><input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control"></div>
    <?php if (sees_all_sites()) { ?>
    <div class="col-md-3"><label class="form-label">Location</label><input type="text" name="branch" value="<?= htmlspecialchars($branch) ?>" class="form-control"></div>
    <?php } ?>
    <div class="col-md-2"><label class="form-label">&nbsp;</label><button class="btn btn-primary form-control">Apply</button></div>
</form>
<table class="table table-bordered table-striped bg-white">
<thead><tr><th>Time</th><th>Location</th><th>Alarm</th><th>Parameter</th><th>Actual</th><th>Limit</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($stmt as $row) { ?>
<tr>
    <td><?= htmlspecialchars((string) ($row['alarm_time'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string) ($row['branch_name'] ?? $row['site_name'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string) ($row['alarm_message'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string) ($row['parameter_name'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string) ($row['actual_value'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string) ($row['alarm_value'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string) ($row['status'] ?? '')) ?></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
<?php include 'includes/footer.php'; ?>
