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
$testDetail = '';
$stored = ems_mail_settings($conn);
$postedPassword = (string) ($_POST['password'] ?? '');
if ($postedPassword === '') {
    $postedPassword = (string) ($stored['password'] ?? '');
}
if (isset($_POST['test'])) {
    $settings = $stored;
    if (isset($_POST['host'])) {
        $settings['host'] = trim((string) $_POST['host']);
        $settings['port'] = (int) ($_POST['port'] ?? 587);
        $settings['username'] = trim((string) ($_POST['username'] ?? ''));
        $settings['password'] = $postedPassword;
        $settings['encryption'] = (string) ($_POST['encryption'] ?? 'tls');
        $settings['from_email'] = trim((string) ($_POST['from_email'] ?? ''));
        $settings['from_name'] = trim((string) ($_POST['from_name'] ?? 'EMS alarms'));
        $settings['admin_email'] = trim((string) ($_POST['admin_email'] ?? ''));
        $settings['supervisor_email'] = trim((string) ($_POST['supervisor_email'] ?? ''));
    }
    $to = trim((string) ($settings['admin_email'] ?? ''));
    if ($to === '') {
        $to = trim((string) ($settings['supervisor_email'] ?? ''));
    }
    $detail = '';
    $ok = false;
    if ($to === '') {
        $detail = 'Save an admin or supervisor email before sending a test.';
    } else {
        $ok = ems_smtp_send(
            $settings,
            $to,
            'RED SURYAN EMS mail test',
            "This is a connection test from RED SURYAN EMS.\nIf you can read it, alarm mail can be delivered.",
            $detail
        );
    }
    $conn->prepare('UPDATE mail_settings SET last_test_at = ?, last_test_ok = ?, last_test_detail = ? WHERE id = 1')
        ->execute([date('Y-m-d H:i:s'), $ok ? 1 : 0, substr($detail, 0, 255)]);
    $testDetail = $detail;
}
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
        $postedPassword,
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
<p class="text-muted">Admin and supervisor receive every location. A location user receives only the branch named on that account, and only if an email is stored on the user. Outlook on port 587 uses TLS. If the test says the authentication type is not recognized, turn on Authenticated SMTP for that mailbox in Microsoft 365 and test again. Leaving the password blank keeps the saved password.</p>
<?php
$connected = (int) ($settings['last_test_ok'] ?? 0) === 1;
?>
<?php if ($saved) { ?><div class="alert alert-success">Mail settings saved. Alarms are also written to energy_monitor/logs/alarms.log when mail is off.</div><?php } ?>
<?php if ($testDetail !== '') { ?><div class="alert <?= $connected ? 'alert-success' : 'alert-danger' ?>"><?= htmlspecialchars($testDetail) ?></div><?php } ?>
<div class="panel mb-3" style="max-width:640px">
    <h2>Connection</h2>
    <?php if ($connected) { ?>
    <span class="status-pill status-live">Mail connected</span>
    <?php } else { ?>
    <span class="status-pill status-offline">Not connected</span>
    <?php } ?>
    <div class="small text-muted mt-2">
        <?= !empty($settings['last_test_at']) ? 'Last test ' . htmlspecialchars((string) $settings['last_test_at']) . '. ' : 'No test has been sent. ' ?>
        <?= htmlspecialchars((string) ($settings['last_test_detail'] ?? '')) ?>
    </div>
</div>
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
    <button class="btn btn-outline-success" name="test" value="1">Send test mail</button>
</form>
</div>
<?php include 'includes/footer.php'; ?>
