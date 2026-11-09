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
require __DIR__ . '/../datacollector/decode_frame.php';
ems_ensure_plant($conn);

if (empty($_SESSION['ems_csrf'])) {
    $_SESSION['ems_csrf'] = bin2hex(random_bytes(16));
}

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_admin()) {
        $error = 'Only an administrator can register a device.';
    } elseif (!hash_equals($_SESSION['ems_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $error = 'The form expired. Reload the page and try again.';
    } else {
        $name = trim((string) ($_POST['device_name'] ?? ''));
        $protocol = (string) ($_POST['protocol'] ?? '');
        $host = trim((string) ($_POST['host'] ?? ''));
        $port = trim((string) ($_POST['port'] ?? ''));
        $unit = trim((string) ($_POST['unit_id'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $allowed = ['HTTP_GATEWAY', 'MODBUS_TCP', 'MODBUS_RTU', 'MQTT'];
        if ($name === '' || strlen($name) > 120) {
            $error = 'Enter a device name up to 120 characters.';
        } elseif (!in_array($protocol, $allowed, true)) {
            $error = 'Choose a supported protocol.';
        } elseif ($port !== '' && (!ctype_digit($port) || (int) $port > 65535)) {
            $error = 'Port must be a number from 1 to 65535.';
        } elseif ($unit !== '' && (!ctype_digit($unit) || (int) $unit > 247)) {
            $error = 'Modbus unit id must be from 0 to 247.';
        } else {
            $meterId = ems_find_or_create_meter($conn, $name);
            $stmt = $conn->prepare("
                INSERT INTO iot_devices
                    (meter_id, device_name, protocol, host, port, unit_id, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $meterId,
                $name,
                $protocol,
                substr($host, 0, 160),
                $port === '' ? null : (int) $port,
                $unit === '' ? null : (int) $unit,
                substr($notes, 0, 500),
                date('Y-m-d H:i:s'),
            ]);
            ems_log_event($conn, (string) $_SESSION['user'], 'device registered', $protocol, $name);
            $message = 'Device registered against meter ' . $meterId . '. It stays offline until a sample is stored.';
        }
    }
}

$groups = filter_location_groups(ems_location_groups($conn));
$visible = [];
foreach ($groups['locations'] as $group) {
    foreach ($group['meters'] as $meter) {
        $visible[(int) $meter['meter_id']] = $group['location'];
    }
}

$last = [];
try {
    foreach ($conn->query('SELECT meter_id, MAX(reading_time) AS reading_time FROM meter_readings GROUP BY meter_id') as $row) {
        $last[(int) $row['meter_id']] = $row['reading_time'];
    }
} catch (Throwable $e) {
    $last = [];
}

$devices = [];
try {
    $devices = $conn->query('SELECT * FROM iot_devices ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $devices = [];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="content">
    <h1 class="page-title">IoT devices</h1>
    <p class="page-lead">Gateways currently post HTTP form packets to <code>datacollector/getdata.php</code>. Modbus TCP polling is available through <code>modbus_poller.php</code>. This application does not run an MQTT client.</p>

    <?php if ($message !== '') { ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php } ?>
    <?php if ($error !== '') { ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php } ?>

    <div class="equip-grid">
        <section class="panel">
            <h2>Communication</h2>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr><td>HTTP gateway</td><td>Active path for the USR Httpd Client push</td></tr>
                    <tr><td>Modbus TCP / RTU</td><td>Poller uses <?= htmlspecialchars(getenv('MODBUS_HOST') ?: '172.16.11.102') ?>:<?= (int) (getenv('MODBUS_PORT') ?: 502) ?> unless MODBUS_HOST is set</td></tr>
                    <tr><td>MQTT</td><td>Not configured. Registering MQTT only stores the broker address for operators.</td></tr>
                </tbody>
            </table>
        </section>
        <?php if (is_admin()) { ?>
        <section class="panel">
            <h2>Register a device</h2>
            <form method="post" class="row g-2">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['ems_csrf']) ?>">
                <div class="col-md-6">
                    <label class="form-label" for="device_name">Name</label>
                    <input id="device_name" name="device_name" class="form-control" required maxlength="120">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="protocol">Protocol</label>
                    <select id="protocol" name="protocol" class="form-select" required>
                        <option value="HTTP_GATEWAY">HTTP gateway</option>
                        <option value="MODBUS_TCP">Modbus TCP</option>
                        <option value="MODBUS_RTU">Modbus RTU</option>
                        <option value="MQTT">MQTT</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="host">Host or broker</label>
                    <input id="host" name="host" class="form-control" maxlength="160">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="port">Port</label>
                    <input id="port" name="port" class="form-control" inputmode="numeric">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="unit_id">Unit id</label>
                    <input id="unit_id" name="unit_id" class="form-control" inputmode="numeric">
                </div>
                <div class="col-12">
                    <label class="form-label" for="notes">Notes</label>
                    <input id="notes" name="notes" class="form-control" maxlength="500">
                </div>
                <div class="col-12">
                    <button class="btn btn-primary">Save device</button>
                </div>
            </form>
        </section>
        <?php } ?>
    </div>

    <section class="panel">
        <h2>Registered devices and meter connectivity</h2>
        <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
                <tr><th>Device</th><th>Protocol</th><th>Endpoint</th><th>Meter</th><th>Last sample</th><th>State</th></tr>
            </thead>
            <tbody>
            <?php
            $shown = false;
            foreach ($devices as $device) {
                $meterId = (int) $device['meter_id'];
                if (!sees_all_sites() && !isset($visible[$meterId]) && !meter_belongs_to_user($conn, $meterId)) {
                    continue;
                }
                $shown = true;
                $when = $last[$meterId] ?? null;
                $age = $when ? (time() - strtotime((string) $when)) / 60 : null;
                $state = $when === null ? 'No data' : ($age <= 5 ? 'Live' : 'Communication gap');
            ?>
            <tr>
                <td><?= htmlspecialchars($device['device_name']) ?></td>
                <td><?= htmlspecialchars($device['protocol']) ?></td>
                <td><?= htmlspecialchars(trim($device['host'] . ($device['port'] ? ':' . $device['port'] : ''))) ?: '—' ?></td>
                <td><?= $meterId ?></td>
                <td><?= $when ? htmlspecialchars((string) $when) : '—' ?></td>
                <td><span class="status-pill <?= $state === 'Live' ? 'status-live' : 'status-offline' ?>"><?= htmlspecialchars($state) ?></span></td>
            </tr>
            <?php } ?>
            <?php if (!$shown) { ?>
            <tr><td colspan="6">No devices registered yet. Gateway meters still appear on the dashboard when packets are stored.</td></tr>
            <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
</div>
<?php include 'includes/footer.php'; ?>
