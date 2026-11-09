<?php

session_start();
require 'config/db.php';
require 'includes/auth_helpers.php';
require 'includes/plant_standards.php';
if (!isset($_SESSION['user']) || !sees_all_sites()) {
    header('Location: dashboard.php');
    exit;
}
ems_ensure_plant($conn);

$saved = false;
if (isset($_POST['save'])) {
    $stmt = $conn->prepare("
        UPDATE mail_settings SET
            enabled = ?, host = ?, port = ?, username = ?, password = ?,
            encryption = ?, from_email = ?, from_name = ?, admin_email = ?, supervisor_email = ?
        WHERE id = 1
    ");
    $stmt->execute([
        isset($_POST['enabled']) ? 1 : 0,
        trim($_POST['host'] ?? ''),
        (int) ($_POST['port'] ?? 587),
        trim($_POST['username'] ?? ''),
        $_POST['password'] ?? '',
        $_POST['encryption'] ?? 'tls',
        trim($_POST['from_email'] ?? ''),
        trim($_POST['from_name'] ?? 'EMS alarms'),
        trim($_POST['admin_email'] ?? ''),
        trim($_POST['supervisor_email'] ?? ''),
    ]);
    $saved = true;
}
$settings = ems_mail_settings($conn);
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="content">
<h2>Alarm mail</h2>
<p class="text-muted">Admin and supervisor receive every location. A location user receives only the branch named on that account, and only if an email is stored on the user.</p>
<?php if ($saved) { ?><div class="alert alert-success">Mail settings saved. Alarms are also written to energy_monitor/logs/alarms.log when mail is off.</div><?php } ?>
<form method="post" class="panel" style="max-width:640px">
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="enabled" id="enabled" <?= (int) ($settings['enabled'] ?? 0) === 1 ? 'checked' : '' ?>>
        <label class="form-check-label" for="enabled">Send mail</label>
    </div>
    <div class="mb-2"><label>SMTP host</label><input class="form-control" name="host" value="<?= htmlspecialchars($settings['host'] ?? '') ?>"></div>
    <div class="mb-2"><label>Port</label><input class="form-control" name="port" value="<?= htmlspecialchars((string) ($settings['port'] ?? 587)) ?>"></div>
    <div class="mb-2"><label>Encryption</label>
        <select class="form-select" name="encryption">
            <?php foreach (['tls', 'ssl', 'none'] as $mode) { ?>
            <option value="<?= $mode ?>" <?= ($settings['encryption'] ?? '') === $mode ? 'selected' : '' ?>><?= $mode ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="mb-2"><label>Username</label><input class="form-control" name="username" value="<?= htmlspecialchars($settings['username'] ?? '') ?>"></div>
    <div class="mb-2"><label>Password</label><input class="form-control" type="password" name="password" value="<?= htmlspecialchars($settings['password'] ?? '') ?>"></div>
    <div class="mb-2"><label>From email</label><input class="form-control" name="from_email" value="<?= htmlspecialchars($settings['from_email'] ?? '') ?>"></div>
    <div class="mb-2"><label>From name</label><input class="form-control" name="from_name" value="<?= htmlspecialchars($settings['from_name'] ?? 'EMS alarms') ?>"></div>
    <div class="mb-2"><label>Admin email</label><input class="form-control" name="admin_email" value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>"></div>
    <div class="mb-2"><label>Supervisor email</label><input class="form-control" name="supervisor_email" value="<?= htmlspecialchars($settings['supervisor_email'] ?? '') ?>"></div>
    <button class="btn btn-primary" name="save">Save</button>
</form>
</div>
<?php include 'includes/footer.php'; ?>
