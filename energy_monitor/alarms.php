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

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
$to = $_GET['to'] ?? date('Y-m-d');
$branch = sees_all_sites() ? ($_GET['branch'] ?? '') : '';

$sql = "SELECT * FROM alarms WHERE status = 'ACTIVE'";
$params = [];
$columns = ems_table_columns($conn, 'alarms');
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
$sql .= ' ORDER BY alarm_time DESC';
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="content">
<h2>Active alarms</h2>
<p class="text-muted">Phase voltage outside 207–253 V, current outside the feeder limits, power factor below 0.85, EB failure, DG running, no supply, and a daily total outside the 7-day band.</p>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
        <label class="form-label">From</label>
        <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control">
    </div>
    <div class="col-md-3">
        <label class="form-label">To</label>
        <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control">
    </div>
    <?php if (sees_all_sites()) { ?>
    <div class="col-md-3">
        <label class="form-label">Location</label>
        <input type="text" name="branch" value="<?= htmlspecialchars($branch) ?>" class="form-control" placeholder="All locations">
    </div>
    <?php } ?>
    <div class="col-md-2">
        <label class="form-label">&nbsp;</label>
        <button class="btn btn-primary form-control">Apply</button>
    </div>
</form>

<table class="table table-bordered bg-white">
<thead>
<tr><th>Time</th><th>Location</th><th>Alarm</th><th>Parameter</th><th>Limit</th><th>Actual</th><th>Severity</th><th></th></tr>
</thead>
<tbody>
<?php if ($rows === []) { ?>
<tr><td colspan="8">No active alarms for this filter.</td></tr>
<?php } ?>
<?php foreach ($rows as $row) {
    $when = $row['alarm_time'] ?? '';
    if ($when !== '' && ($when < $from || substr($when, 0, 10) > $to)) {
        continue;
    }
    $severity = $row['severity'] ?? '';
?>
<tr>
    <td><?= htmlspecialchars((string) $when) ?></td>
    <td><?= htmlspecialchars((string) ($row['branch_name'] ?? $row['site_name'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string) ($row['alarm_message'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string) ($row['parameter_name'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string) ($row['alarm_value'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string) ($row['actual_value'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string) $severity) ?></td>
    <td><button class="btn btn-sm btn-outline-secondary" type="button" onclick="ackAlarm(<?= (int) $row['id'] ?>)">Acknowledge</button></td>
</tr>
<?php } ?>
</tbody>
</table>
<p><a href="alarm_history.php">Alarm history</a> · <a href="alarm_limits.php">Limits</a> · <a href="site_map.php">Supply map</a></p>
</div>
<script>
function ackAlarm(id) {
    fetch('api/ack_alarm.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'id=' + id })
        .then(() => location.reload());
}
</script>
<?php include 'includes/footer.php'; ?>
