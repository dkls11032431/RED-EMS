<?php

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require 'config/db.php';
require 'includes/auth_helpers.php';
require 'includes/plant_standards.php';
require __DIR__ . '/../datacollector/decode_frame.php';
ems_ensure_plant($conn);

$groups = filter_location_groups(ems_location_groups($conn));
$classes = [
    'UPS' => 'UPS',
    'DG' => 'Diesel generator',
    'PUMP' => 'Pumps and motors',
    'LIGHT' => 'Lighting',
    'AC' => 'Air conditioning',
    'WATER' => 'Water and fuel',
    'TX' => 'Transformer',
    'SOLAR' => 'Solar',
];

function equipment_class(string $name): string
{
    $n = strtoupper($name);
    if (strpos($n, 'UPS') !== false) {
        return 'UPS';
    }
    if (preg_match('/PUMP|MOTOR/', $n)) {
        return 'PUMP';
    }
    if (preg_match('/LIGHT|LUMIN/', $n)) {
        return 'LIGHT';
    }
    if (preg_match('/WATER|FUEL TANK|DIESEL TANK|LEVEL/', $n)) {
        return 'WATER';
    }
    if (preg_match('/\bDG\b|DIESEL|GENERATOR/', $n)) {
        return 'DG';
    }
    if (preg_match('/\bAC\b|AHU|HVAC|CHILLER|CONDITION/', $n)) {
        return 'AC';
    }
    if (preg_match('/TRANSFORM|TXR|\bTX\b/', $n)) {
        return 'TX';
    }
    if (strpos($n, 'SOLAR') !== false || preg_match('/\bPV\b/', $n)) {
        return 'SOLAR';
    }
    return '';
}

$meters = [];
foreach ($groups['locations'] as $group) {
    foreach ($group['meters'] as $meter) {
        $class = equipment_class((string) $meter['meter_name']);
        if ($class === '' && stripos((string) $meter['meter_name'], 'DG') !== false) {
            $class = 'DG';
        }
        $meters[] = [
            'class' => $class,
            'meter_id' => (int) $meter['meter_id'],
            'meter_name' => $meter['meter_name'],
            'location' => $group['location'],
        ];
    }
}

$ids = array_column($meters, 'meter_id');
$last = [];
if ($ids !== []) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("
        SELECT meter_id, MAX(reading_time) AS reading_time
        FROM meter_readings
        WHERE meter_id IN ($placeholders)
        GROUP BY meter_id
    ");
    $stmt->execute($ids);
    $last = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="content">
    <h1 class="page-title">Equipment monitoring</h1>
    <p class="page-lead">UPS, DG, pumps, lighting, air conditioning, water, fuel, transformer and solar assets are recognised from the meter name. Status uses the last stored sample.</p>

    <div class="equip-grid">
    <?php foreach ($classes as $key => $title) {
        $rows = array_values(array_filter($meters, function ($meter) use ($key) {
            return $meter['class'] === $key;
        }));
    ?>
        <section class="panel">
            <h2><?= htmlspecialchars($title) ?></h2>
            <?php if ($rows === []) { ?>
            <div class="empty-state">No <?= htmlspecialchars($title) ?> meter is registered for your locations. A meter appears here when its name contains the equipment type.</div>
            <?php } else { ?>
            <table class="table table-sm mb-0">
                <thead><tr><th>Location</th><th>Meter</th><th>Last sample</th><th>Link</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row) {
                    $when = $last[$row['meter_id']] ?? null;
                    $age = $when ? (time() - strtotime((string) $when)) / 60 : null;
                    $state = $when === null ? 'No data' : ($age <= 5 ? 'Live' : 'Stale');
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['location']) ?></td>
                    <td><?= htmlspecialchars($row['meter_name']) ?></td>
                    <td><?= $when ? htmlspecialchars((string) $when) : '—' ?> <span class="status-pill <?= $state === 'Live' ? 'status-live' : ($state === 'Stale' ? 'status-stale' : 'status-offline') ?>"><?= $state ?></span></td>
                    <td><a href="dashboard.php?meter_id=<?= (int) $row['meter_id'] ?>">Open</a></td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
            <?php } ?>
        </section>
    <?php } ?>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
