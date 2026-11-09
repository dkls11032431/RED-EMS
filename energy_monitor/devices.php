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
function device_probe(array $device): array
{
    $host = trim((string) ($device['host'] ?? ''));
    $port = (int) ($device['port'] ?? 0);
    if ($host === '' || $port <= 0) {
        return [0, 'Enter a host and port before checking connectivity.'];
    }
    if (($device['protocol'] ?? '') === 'MODBUS_TCP') {
        require_once __DIR__ . '/includes/modbus_client.php';
        try {
            $unit = (int) ($device['unit_id'] ?? 1);
            $registers = modbus_read_holding_registers($host, $port, 0, 1, $unit > 0 ? $unit : 1, 2.0);
            return [1, 'Modbus TCP replied (' . count($registers) . ' register).'];
        } catch (Throwable $e) {
            return [0, substr($e->getMessage(), 0, 180)];
        }
    }
    $errno = 0;
    $err = '';
    $socket = @fsockopen($host, $port, $errno, $err, 2.0);
    if ($socket === false) {
        return [0, substr('TCP connection failed: ' . $err, 0, 180)];
    }
    fclose($socket);
    if (($device['protocol'] ?? '') === 'MQTT') {
        return [1, 'TCP port answered. This application does not open an MQTT session.'];
    }
    if (($device['protocol'] ?? '') === 'MODBUS_RTU') {
        return [1, 'TCP port answered. A Modbus RTU frame was not sent.'];
    }
    return [1, 'TCP port answered.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sees_all_sites()) {
        $error = 'Only an administrator or supervisor can change devices.';
    } elseif (!hash_equals($_SESSION['ems_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $error = 'The form expired. Reload the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'check') {
        $id = (int) ($_POST['id'] ?? 0);
        $found = $conn->prepare('SELECT * FROM iot_devices WHERE id = ?');
        $found->execute([$id]);
        $device = $found->fetch(PDO::FETCH_ASSOC);
        if (!$device) {
            $error = 'That device is not registered.';
        } else {
            [$ok, $detail] = device_probe($device);
            $conn->prepare('UPDATE iot_devices SET last_check_at = ?, last_check_ok = ?, last_check_detail = ? WHERE id = ?')
                ->execute([date('Y-m-d H:i:s'), $ok, substr($detail, 0, 255), $id]);
            ems_log_event($conn, (string) $_SESSION['user'], 'device check', (string) $device['protocol'], $detail);
            $message = $device['device_name'] . ': ' . $detail;
        }
    } else {
        $name = trim((string) ($_POST['device_name'] ?? ''));
        $protocol = (string) ($_POST['protocol'] ?? '');
        $host = trim((string) ($_POST['host'] ?? ''));
        $port = trim((string) ($_POST['port'] ?? ''));
        $unit = trim((string) ($_POST['unit_id'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $branch = trim((string) ($_POST['branch_name'] ?? ''));
        $allowed = ['HTTP_GATEWAY', 'MODBUS_TCP', 'MODBUS_RTU', 'MQTT'];
        if ($name === '' || strlen($name) > 120) {
            $error = 'Enter a device name up to 120 characters.';
        } elseif (!in_array($protocol, $allowed, true)) {
            $error = 'Choose a supported protocol.';
        } elseif ($port !== '' && (!ctype_digit($port) || (int) $port > 65535)) {
            $error = 'Port must be a number from 1 to 65535.';
        } elseif ($unit !== '' && (!ctype_digit($unit) || (int) $unit > 247)) {
            $error = 'Modbus unit id must be from 0 to 247.';
        } elseif ($branch !== '' && !user_can_branch($branch)) {
            $error = 'Choose a location assigned to your account.';
        } else {
            $meterId = ems_find_or_create_meter($conn, $name);
            $stmt = $conn->prepare("
                INSERT INTO iot_devices
                    (meter_id, device_name, protocol, host, port, unit_id, notes, branch_name, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $meterId,
                $name,
                $protocol,
                substr($host, 0, 160),
                $port === '' ? null : (int) $port,
                $unit === '' ? null : (int) $unit,
                substr($notes, 0, 500),
                substr($branch, 0, 80),
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
        <?php if (sees_all_sites()) {
            $branchSql = 'SELECT branch_name, company FROM bill_sites ORDER BY branch_name';
            $branchRows = $conn->query($branchSql)->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <section class="panel">
            <h2>Register a device</h2>
            <form method="post" class="row g-2">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['ems_csrf']) ?>">
                <div class="col-md-4">
                    <label class="form-label" for="branch_name">Location</label>
                    <select id="branch_name" name="branch_name" class="form-select">
                        <option value="">Unassigned</option>
                        <?php foreach ($branchRows as $site) { ?>
                        <option value="<?= htmlspecialchars($site['branch_name']) ?>"><?= htmlspecialchars($site['branch_name'] . ' · ' . $site['company']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-4">
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

    <?php
    $shownDevices = [];
    foreach ($devices as $device) {
        $meterId = (int) $device['meter_id'];
        if (!sees_all_sites() && !isset($visible[$meterId]) && !meter_belongs_to_user($conn, $meterId)) {
            continue;
        }
        $when = $last[$meterId] ?? null;
        $age = $when ? (time() - strtotime((string) $when)) / 60 : null;
        $device['sample_time'] = $when;
        $device['state'] = $when === null ? 'No data' : ($age <= 5 ? 'Live' : 'Communication gap');
        $shownDevices[] = $device;
    }
    $gaps = array_values(array_filter($shownDevices, function ($device) {
        return $device['state'] !== 'Live';
    }));
    $gateways = array_values(array_filter($shownDevices, function ($device) {
        return in_array($device['protocol'], ['HTTP_GATEWAY', 'MODBUS_TCP', 'MODBUS_RTU'], true);
    }));
    $focusId = (int) ($_GET['device'] ?? 0);
    $sensors = [];
    $focusName = '';
    foreach ($shownDevices as $device) {
        if ((int) $device['id'] === $focusId) {
            $focusName = $device['device_name'];
            $sensorStmt = $conn->prepare('SELECT parameter_name, value, reading_time FROM meter_readings WHERE meter_id = ? ORDER BY reading_time DESC, id DESC LIMIT 40');
            $sensorStmt->execute([(int) $device['meter_id']]);
            foreach ($sensorStmt as $sample) {
                if (!isset($sensors[$sample['parameter_name']])) {
                    $sensors[$sample['parameter_name']] = $sample;
                }
            }
        }
    }
    ?>
    <section class="panel mb-3">
        <h2>Gateways</h2>
        <?php if ($gateways === []) { ?>
        <div class="empty-state">No gateway is registered. HTTP pushes still arrive at datacollector/getdata.php when the USR module is pointed there.</div>
        <?php } else { ?>
        <table class="table table-sm mb-0">
            <thead><tr><th>Gateway</th><th>Protocol</th><th>Location</th><th>Last sample</th><th>Last check</th></tr></thead>
            <tbody>
            <?php foreach ($gateways as $device) { ?>
            <tr>
                <td><?= htmlspecialchars($device['device_name']) ?></td>
                <td><?= htmlspecialchars($device['protocol']) ?></td>
                <td><?= htmlspecialchars((string) ($device['branch_name'] ?? '')) ?: '—' ?></td>
                <td><?= $device['sample_time'] ? htmlspecialchars((string) $device['sample_time']) : '—' ?></td>
                <td><?= htmlspecialchars((string) ($device['last_check_detail'] ?? '')) ?: 'Not checked' ?></td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
        <?php } ?>
    </section>

    <section class="panel mb-3">
        <h2>Devices</h2>
        <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
                <tr><th>Device</th><th>Protocol</th><th>Location</th><th>Endpoint</th><th>Last sample</th><th>State</th><th>Check</th><th></th></tr>
            </thead>
            <tbody>
            <?php if ($shownDevices === []) { ?>
            <tr><td colspan="8">No devices registered yet. Gateway meters still appear on the dashboard when packets are stored.</td></tr>
            <?php } ?>
            <?php foreach ($shownDevices as $device) { ?>
            <tr>
                <td><?= htmlspecialchars($device['device_name']) ?></td>
                <td><?= htmlspecialchars($device['protocol']) ?></td>
                <td><?= htmlspecialchars((string) ($device['branch_name'] ?? '')) ?: '—' ?></td>
                <td><?= htmlspecialchars(trim($device['host'] . ($device['port'] ? ':' . $device['port'] : ''))) ?: '—' ?></td>
                <td><?= $device['sample_time'] ? htmlspecialchars((string) $device['sample_time']) : '—' ?></td>
                <td><span class="status-pill <?= $device['state'] === 'Live' ? 'status-live' : 'status-offline' ?>"><?= htmlspecialchars($device['state']) ?></span></td>
                <td><?= htmlspecialchars((string) ($device['last_check_detail'] ?? '')) ?: '—' ?></td>
                <td>
                    <?php if (sees_all_sites()) { ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['ems_csrf']) ?>">
                        <input type="hidden" name="action" value="check">
                        <input type="hidden" name="id" value="<?= (int) $device['id'] ?>">
                        <button class="btn btn-sm btn-outline-secondary">Check</button>
                    </form>
                    <?php } ?>
                    <a href="devices.php?device=<?= (int) $device['id'] ?>">Sensors</a>
                </td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
        </div>
    </section>

    <div class="equip-grid">
        <section class="panel">
            <h2>Communication errors</h2>
            <?php if ($gaps === []) { ?>
            <div class="empty-state"><?= $shownDevices === [] ? 'Nothing to compare until a device is registered.' : 'Every registered device has a sample from the last five minutes.' ?></div>
            <?php } else { ?>
            <table class="table table-sm mb-0">
                <thead><tr><th>Device</th><th>State</th><th>Last sample</th><th>Last check</th></tr></thead>
                <tbody>
                <?php foreach ($gaps as $device) { ?>
                <tr>
                    <td><?= htmlspecialchars($device['device_name']) ?></td>
                    <td><?= htmlspecialchars($device['state']) ?></td>
                    <td><?= $device['sample_time'] ? htmlspecialchars((string) $device['sample_time']) : 'No sample stored' ?></td>
                    <td><?= htmlspecialchars((string) ($device['last_check_detail'] ?? '')) ?: '—' ?></td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
            <?php } ?>
        </section>
        <section class="panel">
            <h2>Sensor status<?= $focusName !== '' ? ' · ' . htmlspecialchars($focusName) : '' ?></h2>
            <?php if ($focusId === 0) { ?>
            <div class="empty-state">Choose Sensors on a device to list the latest stored registers for its meter.</div>
            <?php } elseif ($sensors === []) { ?>
            <div class="empty-state">No samples are stored for this device meter.</div>
            <?php } else { ?>
            <table class="table table-sm mb-0">
                <thead><tr><th>Register</th><th class="text-end">Value</th><th>Time</th></tr></thead>
                <tbody>
                <?php foreach ($sensors as $sample) { ?>
                <tr>
                    <td><?= htmlspecialchars($sample['parameter_name']) ?></td>
                    <td class="text-end"><?= htmlspecialchars((string) $sample['value']) ?></td>
                    <td><?= htmlspecialchars((string) $sample['reading_time']) ?></td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
            <?php } ?>
        </section>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
