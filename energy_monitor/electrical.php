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
$sites = $groups['locations'];
$siteIndex = isset($_GET['site']) ? (int) $_GET['site'] : 0;
if (!isset($sites[$siteIndex])) {
    $siteIndex = 0;
}
$site = $sites[$siteIndex] ?? null;
$meterId = (int) ($site['meters'][0]['meter_id'] ?? 0);
$dgMeter = (int) ($site['meters'][1]['meter_id'] ?? 0);

function electrical_latest(PDO $conn, int $meterId, string $name)
{
    if ($meterId <= 0) {
        return null;
    }
    $stmt = $conn->prepare("
        SELECT value, reading_time
        FROM meter_readings
        WHERE meter_id = ? AND parameter_name = ?
        ORDER BY reading_time DESC
        LIMIT 1
    ");
    $stmt->execute([$meterId, $name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function electrical_delta(PDO $conn, int $meterId, string $name, string $from, string $to): ?float
{
    if ($meterId <= 0) {
        return null;
    }
    $stmt = $conn->prepare("
        SELECT MAX(value) AS hi, MIN(value) AS lo, COUNT(*) AS n
        FROM meter_readings
        WHERE meter_id = ? AND parameter_name = ? AND reading_time BETWEEN ? AND ?
    ");
    $stmt->execute([$meterId, $name, $from, $to]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int) $row['n'] === 0) {
        return null;
    }
    return round((float) $row['hi'] - (float) $row['lo'], 2);
}

function electrical_solar(PDO $conn, int $meterId): ?array
{
    if ($meterId <= 0) {
        return null;
    }
    $stmt = $conn->prepare("
        SELECT parameter_name, value, reading_time
        FROM meter_readings
        WHERE meter_id = ?
          AND (parameter_name LIKE '%SOLAR%' OR parameter_name LIKE '%PV%')
        ORDER BY reading_time DESC
        LIMIT 1
    ");
    $stmt->execute([$meterId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

$phases = ['Vr', 'Vy', 'Vb', 'Ir', 'Iy', 'Ib', 'KW-Tot.', 'PF', 'EB-KWH'];
$latest = [];
foreach ($phases as $name) {
    $latest[$name] = electrical_latest($conn, $meterId, $name);
}
$dgLatest = electrical_latest($conn, $dgMeter > 0 ? $dgMeter : $meterId, 'DG-KWH');
$solar = electrical_solar($conn, $meterId);

$today = date('Y-m-d');
$monthStart = date('Y-m-01') . ' 00:00:00';
$yearStart = date('Y-01-01') . ' 00:00:00';
$now = date('Y-m-d H:i:s');
$periods = [
    'Today' => [date('Y-m-d 00:00:00'), $now],
    'This month' => [$monthStart, $now],
    'This year' => [$yearStart, $now],
];

$companyNote = '';
if ($site) {
    $branch = ems_match_branch((string) $site['location']);
    $companyStmt = $conn->prepare('SELECT company, state_name FROM bill_sites WHERE branch_name = ? LIMIT 1');
    $companyStmt->execute([$branch]);
    $companyRow = $companyStmt->fetch(PDO::FETCH_ASSOC);
    if ($companyRow) {
        $companyNote = $companyRow['company'] === 'KAL'
            ? $companyRow['state_name'] . ' is KAL (South). BECIL and NON BECIL worksheets are entered separately.'
            : $companyRow['state_name'] . ' is SAFL. BECIL and NON BECIL worksheets are entered separately.';
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="content">
    <h1 class="page-title">Electrical energy</h1>
    <p class="page-lead">EB, DG and solar registers, three-phase values, and consumption from stored meter samples.</p>

    <form method="get" class="panel mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label" for="site">Location</label>
                <select id="site" name="site" class="form-select" onchange="this.form.submit()">
                    <?php if ($sites === []) { ?>
                    <option value="0">No location assigned</option>
                    <?php } ?>
                    <?php foreach ($sites as $i => $group) { ?>
                    <option value="<?= $i ?>" <?= $i === $siteIndex ? 'selected' : '' ?>>
                        <?= htmlspecialchars($group['location'] . ' (' . $group['siteno'] . ')') ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-6">
                <a class="btn btn-outline-secondary" href="consumption_report.php?fuel=EB&period=daily&site=<?= $siteIndex ?>">Daily EB report</a>
                <a class="btn btn-outline-secondary" href="consumption_report.php?fuel=DG&period=monthly&site=<?= $siteIndex ?>">Monthly DG registers</a>
                <a class="btn btn-outline-secondary" href="consumption_report.php?fuel=BOTH&period=yearly&site=<?= $siteIndex ?>">Yearly</a>
            </div>
        </div>
        <?php if ($companyNote !== '') { ?>
        <p class="small text-muted mb-0 mt-2"><?= htmlspecialchars($companyNote) ?></p>
        <?php } ?>
    </form>

    <?php if (!$site || $meterId <= 0) { ?>
    <div class="empty-state">No meter is linked to a location you can open. Readings appear after the gateway stores a packet for your site.</div>
    <?php } else { ?>
    <div class="kpi-grid">
        <?php
        $cards = [
            ['EB register', $latest['EB-KWH'], 'kWh'],
            ['DG register', $dgLatest, 'kWh'],
            ['Demand', $latest['KW-Tot.'], 'kW'],
            ['Power factor', $latest['PF'], ''],
        ];
        foreach ($cards as [$label, $row, $unit]) {
        ?>
        <div class="panel">
            <h2><?= htmlspecialchars($label) ?></h2>
            <div class="kpi-value">
                <?php if ($row) { ?>
                    <?= number_format((float) $row['value'], 2) ?><span class="kpi-unit"><?= htmlspecialchars($unit) ?></span>
                <?php } else { ?>—<?php } ?>
            </div>
            <div class="kpi-note"><?= $row ? htmlspecialchars($row['reading_time']) : 'No sample stored' ?></div>
        </div>
        <?php } ?>
        <div class="panel">
            <h2>Solar</h2>
            <div class="kpi-value">
                <?php if ($solar) { ?>
                    <?= number_format((float) $solar['value'], 2) ?>
                <?php } else { ?>—<?php } ?>
            </div>
            <div class="kpi-note">
                <?php if ($solar) { ?>
                    <?= htmlspecialchars($solar['parameter_name'] . ' · ' . $solar['reading_time']) ?>
                <?php } else { ?>
                    No solar or PV register on this meter
                <?php } ?>
            </div>
        </div>
        <div class="panel">
            <h2>DG meter</h2>
            <div class="kpi-value"><?= $dgMeter > 0 ? 'Linked' : 'None' ?></div>
            <div class="kpi-note"><?= $dgMeter > 0 ? 'Diesel kWh is on the DG meter' : 'This location has no DG meter in the gateway map' ?></div>
        </div>
    </div>

    <div class="panel mb-3">
        <h2>Three-phase parameters</h2>
        <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Parameter</th><th class="text-end">Value</th><th>Sample time</th></tr></thead>
            <tbody>
            <?php foreach (['Vr' => 'V', 'Vy' => 'V', 'Vb' => 'V', 'Ir' => 'A', 'Iy' => 'A', 'Ib' => 'A'] as $name => $unit) {
                $row = $latest[$name];
            ?>
            <tr>
                <td><?= htmlspecialchars($name) ?></td>
                <td class="text-end"><?= $row ? number_format((float) $row['value'], 2) . ' ' . $unit : '—' ?></td>
                <td><?= $row ? htmlspecialchars($row['reading_time']) : 'No sample stored' ?></td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="panel">
        <h2>Consumption from register movement</h2>
        <table class="table table-sm mb-0">
            <thead><tr><th>Period</th><th class="text-end">EB kWh</th><th class="text-end">DG kWh</th></tr></thead>
            <tbody>
            <?php foreach ($periods as $label => [$from, $to]) {
                $eb = electrical_delta($conn, $meterId, 'EB-KWH', $from, $to);
                $dg = electrical_delta($conn, $dgMeter > 0 ? $dgMeter : 0, 'DG-KWH', $from, $to);
            ?>
            <tr>
                <td><?= htmlspecialchars($label) ?></td>
                <td class="text-end"><?= $eb === null ? '—' : number_format($eb, 2) ?></td>
                <td class="text-end"><?= $dg === null ? '—' : number_format($dg, 2) ?></td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
        <p class="small text-muted mb-0 mt-2">Each figure is the highest stored register minus the lowest in that window. A dash means the meter did not report that register.</p>
    </div>
    <?php } ?>
</div>
<?php include 'includes/footer.php'; ?>
