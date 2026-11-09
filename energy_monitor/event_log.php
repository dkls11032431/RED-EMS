<?php

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
require 'config/db.php';
require 'includes/auth_helpers.php';
require 'includes/plant_standards.php';
require 'includes/activity_log.php';
ems_ensure_plant($conn);

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
$to = $_GET['to'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-d', strtotime('-7 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}
$userFilter = sees_all_sites() ? trim((string) ($_GET['username'] ?? '')) : (string) $_SESSION['user'];

$sql = 'SELECT * FROM event_log WHERE 1=1';
$params = [];
$columns = ems_table_columns($conn, 'event_log');
if (isset($columns['created_at'])) {
    $sql .= ' AND (created_at IS NULL OR DATE(created_at) BETWEEN ? AND ?)';
    $params[] = $from;
    $params[] = $to;
}
if ($userFilter !== '') {
    $sql .= ' AND username = ?';
    $params[] = $userFilter;
}
$sql .= isset($columns['id']) ? ' ORDER BY id DESC LIMIT 500' : ' ORDER BY username LIMIT 500';
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="content">
<h2>Event log</h2>
<p class="text-muted">Sign-in, sign-out with session length, and each field a user changed.</p>
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
        <label class="form-label">User</label>
        <input class="form-control" name="username" value="<?= htmlspecialchars($userFilter) ?>" placeholder="All users">
    </div>
    <?php } ?>
    <div class="col-md-2">
        <label class="form-label">&nbsp;</label>
        <button class="btn btn-primary form-control">Show</button>
    </div>
</form>
<table class="table table-bordered table-striped bg-white">
<thead>
<tr>
    <th>When</th>
    <th>User</th>
    <th>Activity</th>
    <th>Field</th>
    <th>Detail</th>
    <th>Duration</th>
    <th>IP</th>
</tr>
</thead>
<tbody>
<?php if ($rows === []) { ?>
<tr><td colspan="7" class="text-muted">No events in this window.</td></tr>
<?php } ?>
<?php foreach ($rows as $row) {
    $seconds = isset($row['duration_seconds']) && $row['duration_seconds'] !== null ? (int) $row['duration_seconds'] : null;
?>
<tr>
    <td><?= htmlspecialchars((string) ($row['created_at'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string) ($row['username'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string) ($row['activity'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string) ($row['field_name'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string) ($row['detail'] ?? '')) ?></td>
    <td><?= htmlspecialchars(ems_format_duration($seconds)) ?></td>
    <td><?= htmlspecialchars((string) ($row['ip_address'] ?? '')) ?></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
<?php include 'includes/footer.php'; ?>
