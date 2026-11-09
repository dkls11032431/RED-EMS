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
if (empty($_SESSION['ems_csrf'])) {
    $_SESSION['ems_csrf'] = bin2hex(random_bytes(16));
}

$limitError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_admin()) {
        $limitError = 'Only an administrator can change limits.';
    } elseif (!hash_equals($_SESSION['ems_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $limitError = 'The form expired. Reload the page and try again.';
    } elseif (isset($_POST['remove'])) {
        $name = trim((string) $_POST['remove']);
        $conn->prepare('DELETE FROM electrical_limits WHERE parameter_name = ?')->execute([$name]);
        header('Location: alarm_limits.php?saved=1');
        exit;
    } elseif (isset($_POST['add'])) {
        $name = trim((string) ($_POST['parameter_name'] ?? ''));
        $unit = trim((string) ($_POST['unit_label'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9 ._-]{1,40}$/', $name)) {
            $limitError = 'Use a parameter name up to 40 letters, numbers, spaces, dots or dashes.';
        } elseif (!is_numeric($_POST['low_limit'] ?? '') || !is_numeric($_POST['high_limit'] ?? '')) {
            $limitError = 'Low and high limits must be numbers.';
        } else {
            try {
                $conn->prepare('INSERT INTO electrical_limits (parameter_name, low_limit, high_limit, unit_label, note) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$name, (float) $_POST['low_limit'], (float) $_POST['high_limit'], substr($unit, 0, 16), substr($note, 0, 255)]);
                header('Location: alarm_limits.php?saved=1');
                exit;
            } catch (Throwable $e) {
                $limitError = 'That parameter already has a limit.';
            }
        }
    } elseif (isset($_POST['save']) && is_array($_POST['low'] ?? null)) {
        $update = $conn->prepare('UPDATE electrical_limits SET low_limit = ?, high_limit = ? WHERE parameter_name = ?');
        foreach ($_POST['low'] as $name => $low) {
            if (!is_numeric($low) || !is_numeric($_POST['high'][$name] ?? '')) {
                $limitError = 'Every limit must be a number.';
                break;
            }
            $update->execute([(float) $low, (float) $_POST['high'][$name], (string) $name]);
        }
        if ($limitError === '') {
            header('Location: alarm_limits.php?saved=1');
            exit;
        }
    }
}

$limits = $conn->query('SELECT * FROM electrical_limits ORDER BY parameter_name')->fetchAll(PDO::FETCH_ASSOC);
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="content">
<h2>Electrical limits</h2>
<p class="text-muted">Phase voltage uses 230 V ± 10%. Power factor alarms below 0.85. Daily kWh compares yesterday with the previous 7 days. Current high should be set to each feeder’s rated current. A low limit of 0 turns that low alarm off.</p>
<?php if (isset($_GET['saved'])) { ?><div class="alert alert-success">Limits saved.</div><?php } ?>
<?php if ($limitError !== '') { ?><div class="alert alert-danger"><?= htmlspecialchars($limitError) ?></div><?php } ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['ems_csrf']) ?>">
<table class="table table-bordered bg-white">
<thead><tr><th>Parameter</th><th>Low</th><th>High</th><th>Unit</th><th>Standard</th><?php if (is_admin()) { ?><th></th><?php } ?></tr></thead>
<tbody>
<?php foreach ($limits as $row) { ?>
<tr>
    <td><?= htmlspecialchars($row['parameter_name']) ?></td>
    <td><input class="form-control" name="low[<?= htmlspecialchars($row['parameter_name']) ?>]" value="<?= htmlspecialchars($row['low_limit']) ?>" <?= is_admin() ? '' : 'readonly' ?>></td>
    <td><input class="form-control" name="high[<?= htmlspecialchars($row['parameter_name']) ?>]" value="<?= htmlspecialchars($row['high_limit']) ?>" <?= is_admin() ? '' : 'readonly' ?>></td>
    <td><?= htmlspecialchars($row['unit_label']) ?></td>
    <td><?= htmlspecialchars($row['note']) ?></td>
    <?php if (is_admin()) { ?>
    <td><button class="btn btn-sm btn-outline-danger" name="remove" value="<?= htmlspecialchars($row['parameter_name']) ?>" formaction="alarm_limits.php" formmethod="post" onclick="return confirm('Remove this limit?');">Delete</button></td>
    <?php } ?>
</tr>
<?php } ?>
</tbody>
</table>
<?php if (is_admin()) { ?><button class="btn btn-primary" name="save" value="1">Save limits</button><?php } ?>
</form>
<?php if (is_admin()) { ?>
<form method="post" class="panel mt-3" style="max-width:720px">
    <h2>New limit</h2>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['ems_csrf']) ?>">
    <div class="row g-2">
        <div class="col-md-3"><label class="form-label">Parameter</label><input class="form-control" name="parameter_name" required maxlength="40" placeholder="KW-Tot."></div>
        <div class="col-md-2"><label class="form-label">Low</label><input class="form-control" name="low_limit" required inputmode="decimal"></div>
        <div class="col-md-2"><label class="form-label">High</label><input class="form-control" name="high_limit" required inputmode="decimal"></div>
        <div class="col-md-2"><label class="form-label">Unit</label><input class="form-control" name="unit_label" maxlength="16" placeholder="kW"></div>
        <div class="col-md-3"><label class="form-label">Note</label><input class="form-control" name="note" maxlength="255"></div>
        <div class="col-12"><button class="btn btn-primary" name="add" value="1">Add limit</button></div>
    </div>
</form>
<?php } ?>
</div>
<?php include 'includes/footer.php'; ?>
