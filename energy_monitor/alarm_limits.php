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

if (!sees_all_sites()) {
    header('Location: dashboard.php');
    exit;
}

if (isset($_POST['save'])) {
    $update = $conn->prepare('UPDATE electrical_limits SET low_limit = ?, high_limit = ? WHERE parameter_name = ?');
    foreach ($_POST['low'] as $name => $low) {
        $update->execute([(float) $low, (float) ($_POST['high'][$name] ?? 0), $name]);
    }
    header('Location: alarm_limits.php?saved=1');
    exit;
}

$limits = $conn->query('SELECT * FROM electrical_limits ORDER BY parameter_name')->fetchAll(PDO::FETCH_ASSOC);
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="content">
<h2>Electrical limits</h2>
<p class="text-muted">Phase voltage uses 230 V ± 10%. Power factor alarms below 0.85. Daily kWh compares yesterday with the previous 7 days. Current high should be set to each feeder’s rated current. A low limit of 0 turns that low alarm off.</p>
<?php if (isset($_GET['saved'])) { ?><div class="alert alert-success">Limits saved.</div><?php } ?>
<form method="post">
<table class="table table-bordered bg-white">
<thead><tr><th>Parameter</th><th>Low</th><th>High</th><th>Unit</th><th>Standard</th></tr></thead>
<tbody>
<?php foreach ($limits as $row) { ?>
<tr>
    <td><?= htmlspecialchars($row['parameter_name']) ?></td>
    <td><input class="form-control" name="low[<?= htmlspecialchars($row['parameter_name']) ?>]" value="<?= htmlspecialchars($row['low_limit']) ?>" <?= sees_all_sites() ? '' : 'readonly' ?>></td>
    <td><input class="form-control" name="high[<?= htmlspecialchars($row['parameter_name']) ?>]" value="<?= htmlspecialchars($row['high_limit']) ?>" <?= sees_all_sites() ? '' : 'readonly' ?>></td>
    <td><?= htmlspecialchars($row['unit_label']) ?></td>
    <td><?= htmlspecialchars($row['note']) ?></td>
</tr>
<?php } ?>
</tbody>
</table>
<?php if (sees_all_sites()) { ?><button class="btn btn-primary" name="save">Save limits</button><?php } ?>
</form>
</div>
<?php include 'includes/footer.php'; ?>
