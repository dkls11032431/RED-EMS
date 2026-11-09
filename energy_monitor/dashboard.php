<?php

session_start();

if(!isset($_SESSION['user']))
{
    header("Location: login.php");
    exit;
}

require 'config/db.php';
require 'includes/auth_helpers.php';
require 'includes/plant_standards.php';
require __DIR__ . '/../datacollector/decode_frame.php';
ems_ensure_plant($conn);

function getLatestValue($conn, $parameter)
{
    $stmt = $conn->prepare("
        SELECT value
        FROM meter_readings
        WHERE parameter_name = ?
        ORDER BY reading_time DESC
        LIMIT 1
    ");

    $stmt->execute([$parameter]);

    return $stmt->fetchColumn();
}

$vr = getLatestValue($conn, 'Vr');
$vy = getLatestValue($conn, 'Vy');
$vb = getLatestValue($conn, 'Vb');

$ir = getLatestValue($conn, 'Ir');
$iy = getLatestValue($conn, 'Iy');
$ib = getLatestValue($conn, 'Ib');

$kw = getLatestValue($conn, 'KW-Tot.');

$ebkwh = getLatestValue($conn, 'EB-KWH');
$dgkwh = getLatestValue($conn, 'DG-KWH');

$openIncidents = $conn->query("
SELECT COUNT(*)
FROM incidents
WHERE status='OPEN'
")->fetchColumn();

$availability = $conn->query("
SELECT ROUND(AVG(availability_percent),2)
FROM site_availability
")->fetchColumn();

$mttr = $conn->query("
SELECT ROUND(
AVG(
TIMESTAMPDIFF(
MINUTE,
opened_at,
resolved_at
)
),2)
FROM incidents
WHERE resolved_at IS NOT NULL
")->fetchColumn();

$meterCount = $conn->query("
    SELECT COUNT(*) AS cnt
    FROM meter_master
")->fetch(PDO::FETCH_ASSOC);

$locationGroups = filter_location_groups(ems_location_groups($conn));
$usedMeterIds = $locationGroups['used'];

$meter_id = isset($_GET['meter_id']) ? (int) $_GET['meter_id'] : 0;
if ($meter_id <= 0 || !meter_belongs_to_user($conn, $meter_id)) {
    $meter_id = 0;
    foreach ($locationGroups['locations'] as $group) {
        $meter_id = (int) ($group['meters'][0]['meter_id'] ?? 0);
        if ($meter_id > 0) {
            break;
        }
    }
    if ($meter_id === 0 && sees_all_sites()) {
        $meter_id = 1;
    }
}

$meterReadingStmt = $conn->prepare("
    SELECT MAX(reading_time) FROM meter_readings WHERE meter_id = ?
");
$meterReadingStmt->execute([$meter_id]);
$meterLastReading = $meterReadingStmt->fetchColumn() ?: null;
$meterAgeMinutes = $meterLastReading
    ? (time() - strtotime($meterLastReading)) / 60
    : null;

$activeAlarms = $conn->prepare("
    SELECT COUNT(*)
    FROM alarms
    WHERE status='ACTIVE' AND meter_id = ?
");
$activeAlarms->execute([$meter_id]);
$activeAlarms = $activeAlarms->fetchColumn();

// Critical / Warning active alarm counts, scoped to the selected meter
// (never system-wide — a client should never see another company's alarm counts)
$critical = $conn->prepare("
    SELECT COUNT(*) FROM alarms
    WHERE status='ACTIVE' AND severity='CRITICAL' AND meter_id = ?
");
$critical->execute([$meter_id]);
$critical = $critical->fetchColumn();

$warning = $conn->prepare("
    SELECT COUNT(*) FROM alarms
    WHERE status='ACTIVE' AND severity='WARNING' AND meter_id = ?
");
$warning->execute([$meter_id]);
$warning = $warning->fetchColumn();

// Copy missing dashboard cards (Vr, kW, EB kWh, …) onto location meters
// before the card query runs. Trichy Main is created without them.
$stmt = $conn->prepare("
SELECT *
FROM parameter_master_princek2
WHERE displayindashboard = 1
AND meter_group_id = ?
ORDER BY priority IS NULL, priority
LIMIT 24
");
$stmt->execute([$meter_id]);
$parameterCards = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selectedIsDg = false;
foreach ($locationGroups['locations'] as $group) {
    $dgMeter = $group['meters'][1]['meter_id'] ?? null;
    if ($dgMeter !== null && (int) $dgMeter === (int) $meter_id) {
        $selectedIsDg = true;
    }
}

if ($parameterCards === []) {
    $template = $conn->prepare("
        SELECT *
        FROM parameter_master_princek2
        WHERE displayindashboard = 1
        AND meter_group_id = 1
        ORDER BY priority IS NULL, priority
        LIMIT 24
    ");
    $template->execute();
    foreach ($template->fetchAll(PDO::FETCH_ASSOC) as $card) {
        if (!$selectedIsDg && ($card['parameter_name'] ?? '') === 'DG-KWH') {
            continue;
        }
        if ($selectedIsDg && ($card['parameter_name'] ?? '') !== 'DG-KWH') {
            continue;
        }
        $parameterCards[] = $card;
    }
}

/*
 | Consumption profile — EB/DG are cumulative counters, so
 | consumption over a period is MAX(value) - MIN(value) within it.
*/
function consumption_for(PDO $conn, $meter_id, $from, $to)
{
    $stmt = $conn->prepare("
        SELECT
            MAX(CASE WHEN parameter_name = 'EB-KWH' THEN value END)
            - MIN(CASE WHEN parameter_name = 'EB-KWH' THEN value END) AS eb,
            MAX(CASE WHEN parameter_name = 'DG-KWH' THEN value END)
            - MIN(CASE WHEN parameter_name = 'DG-KWH' THEN value END) AS dg
        FROM meter_readings
        WHERE meter_id = ?
        AND DATE(reading_time) BETWEEN ? AND ?
    ");
    $stmt->execute([$meter_id, $from, $to]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'eb' => $row['eb'] ?? 0,
        'dg' => $row['dg'] ?? 0,
    ];
}

$todayDate      = date('Y-m-d');
$yesterdayDate  = date('Y-m-d', strtotime('-1 day'));
$thisMonthStart = date('Y-m-01');
$lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
$lastMonthEnd   = date('Y-m-t', strtotime('first day of last month'));

$today     = consumption_for($conn, $meter_id, $todayDate, $todayDate);
$yesterday = consumption_for($conn, $meter_id, $yesterdayDate, $yesterdayDate);
$thisMonth = consumption_for($conn, $meter_id, $thisMonthStart, $todayDate);
$lastMonth = consumption_for($conn, $meter_id, $lastMonthStart, $lastMonthEnd);

/*
 | Live / offline meter status — a meter counts as "live" if it has
 | reported a reading in the last 5 minutes.
*/
$liveStmt = $conn->query("
    SELECT COUNT(DISTINCT meter_id) AS live_count
    FROM meter_readings
    WHERE reading_time >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
");
$liveCount = $liveStmt->fetch(PDO::FETCH_ASSOC)['live_count'];

$totalStmt = $conn->query("
    SELECT COUNT(*) AS total_meters
    FROM meter_master
");
$totalMeters = $totalStmt->fetch(PDO::FETCH_ASSOC)['total_meters'];

$offlineCount = $totalMeters - $liveCount;

$lastStmt = $conn->query("
    SELECT MAX(reading_time) AS last_connected
    FROM meter_readings
");
$lastConnected = $lastStmt->fetch(PDO::FETCH_ASSOC)['last_connected'];

// Minutes since the most recent reading from ANY meter, for the ONLINE/OFFLINE badge
$minutesSinceLastReading = $lastConnected
    ? (time() - strtotime($lastConnected)) / 60
    : null;

$selectedMeterName = 'Meter ' . $meter_id;
$selectedLocation = '';
$selectedCity = '';
foreach ($locationGroups['locations'] as $group) {
    foreach ($group['meters'] as $meter) {
        if ((int) $meter['meter_id'] === (int) $meter_id) {
            $selectedMeterName = $meter['meter_name'];
            $selectedLocation = $group['location'] . ' · ' . $group['siteno'];
            $selectedCity = $group['location'];
        }
    }
}
if ($selectedLocation === '') {
    $nameStmt = $conn->prepare('SELECT meter_name FROM meter_master WHERE meter_id = ?');
    $nameStmt->execute([$meter_id]);
    $fetchedName = $nameStmt->fetchColumn();
    if ($fetchedName) {
        $selectedMeterName = $fetchedName;
        $selectedLocation = 'Unassigned meter';
    }
}

if ($meterLastReading === null) {
    $commsLabel = 'NO DATA';
    $commsClass = 'offline';
} elseif ($meterAgeMinutes > 5) {
    $commsLabel = 'STALE';
    $commsClass = 'stale';
} else {
    $commsLabel = 'LIVE';
    $commsClass = 'live';
}

function dash_unit(string $name): string
{
    if (preg_match('/KWH/i', $name)) {
        return 'kWh';
    }
    if (preg_match('/^KW/i', $name)) {
        return 'kW';
    }
    if (preg_match('/^V/i', $name)) {
        return 'V';
    }
    if (preg_match('/^I/i', $name)) {
        return 'A';
    }
    if (preg_match('/^Fr/i', $name)) {
        return 'Hz';
    }
    return '';
}

function dash_digits(string $name): int
{
    if (preg_match('/^KW/i', $name)) {
        return 3;
    }
    if (preg_match('/^V/i', $name)) {
        return 1;
    }
    return 2;
}

include 'includes/header.php';
include 'includes/sidebar.php';

?>

<style>
.content { background: radial-gradient(900px 280px at 100% 0, rgba(22,163,74,.12), transparent 55%), radial-gradient(700px 240px at 0 0, rgba(37,99,235,.10), transparent 50%), #e8eef5; }
.ops-bar { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:14px; }
.ops-title { font-size:1.45rem; font-weight:700; margin:0; color:#0f2744; }
.ops-sub { color:#5c6b7a; font-size:.85rem; margin-top:2px; }
.ops-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:flex-end; }
.ops-clock { font-variant-numeric:tabular-nums; font-weight:700; color:#0f2744; }
.meter-select { min-width:260px; max-width:420px; border-radius:10px; }
.comms { display:inline-flex; align-items:center; gap:8px; background:#fff; border-radius:999px; padding:6px 12px; font-size:.78rem; font-weight:700; box-shadow:0 1px 2px rgba(15,39,68,.08); }
.comms i { width:9px; height:9px; border-radius:50%; display:inline-block; background:#8b97a3; }
.comms.live { color:#146c39; } .comms.live i { background:#16a34a; box-shadow:0 0 0 4px rgba(22,163,74,.18); }
.comms.stale { color:#8a4b08; } .comms.stale i { background:#d97706; }
.comms.offline { color:#9b1c1c; } .comms.offline i { background:#dc2626; }
.supply-banner { display:flex; justify-content:space-between; gap:12px; align-items:center; padding:12px 16px; margin-bottom:14px; border-radius:12px; font-weight:700; box-shadow:0 8px 20px rgba(15,39,68,.06); }
.supply-meta { font-weight:600; font-size:.85rem; }
.supply-eb { background:linear-gradient(90deg,#ecfdf3,#fff); color:#146c39; border:1px solid #86efac; }
.supply-dg { background:linear-gradient(90deg,#fff7ed,#fff); color:#9a3412; border:1px solid #fdba74; }
.supply-none { background:linear-gradient(90deg,#fef2f2,#fff); color:#9b1c1c; border:1px solid #fca5a5; animation:supplyBlink 1s steps(1) infinite; }
.supply-unknown { background:#f8fafc; color:#5c6b7a; border:1px solid #d5dde6; }
@keyframes supplyBlink { 50% { background:#b42318; color:#fff; } }
.kpi-grid, .param-grid, .ops-split { display:grid; gap:12px; margin-bottom:16px; }
.kpi-grid { grid-template-columns:repeat(6, minmax(0, 1fr)); }
.param-grid { grid-template-columns:repeat(4, minmax(0, 1fr)); }
.ops-split { grid-template-columns:1.4fr 1fr; }
.chart-split { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px; }
.panel { background:#fff; border:0; border-radius:14px; padding:14px 16px; box-shadow:0 8px 24px rgba(15,39,68,.06); }
.panel h2, .panel h3 { font-size:.72rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#64748b; margin:0 0 8px; }
.kpi-value, .metric-value { font-size:1.45rem; font-weight:750; font-variant-numeric:tabular-nums; line-height:1.1; }
.kpi-unit, .metric-unit { margin-left:4px; font-size:.72rem; font-weight:700; color:#64748b; }
.kpi-note { margin-top:6px; color:#94a3b8; font-size:.75rem; }
.kpi-power { background:linear-gradient(180deg,#eff6ff,#fff); } .kpi-power .kpi-value { color:#1d4ed8; }
.kpi-volt { background:linear-gradient(180deg,#fffbeb,#fff); } .kpi-volt .kpi-value { color:#b45309; }
.kpi-amp { background:linear-gradient(180deg,#f0fdfa,#fff); } .kpi-amp .kpi-value { color:#0f766e; }
.kpi-eb { background:linear-gradient(180deg,#ecfdf3,#fff); } .kpi-eb .kpi-value { color:#15803d; }
.kpi-sum { background:linear-gradient(180deg,#f5f3ff,#fff); } .kpi-sum .kpi-value { color:#6d28d9; }
.kpi-alarm { background:linear-gradient(180deg,#fef2f2,#fff); } .kpi-alarm .kpi-value { color:#b42318; }
.tone-kwh { box-shadow:inset 4px 0 0 #16a34a, 0 8px 24px rgba(15,39,68,.06); } .tone-kwh .metric-value { color:#15803d; }
.tone-kw { box-shadow:inset 4px 0 0 #2563eb, 0 8px 24px rgba(15,39,68,.06); } .tone-kw .metric-value { color:#1d4ed8; }
.tone-v { box-shadow:inset 4px 0 0 #d97706, 0 8px 24px rgba(15,39,68,.06); } .tone-v .metric-value { color:#b45309; }
.tone-a { box-shadow:inset 4px 0 0 #0f766e, 0 8px 24px rgba(15,39,68,.06); } .tone-a .metric-value { color:#0f766e; }
.tone-hz { box-shadow:inset 4px 0 0 #7c3aed, 0 8px 24px rgba(15,39,68,.06); } .tone-hz .metric-value { color:#6d28d9; }
.tone-pf { box-shadow:inset 4px 0 0 #be185d, 0 8px 24px rgba(15,39,68,.06); } .tone-pf .metric-value { color:#be185d; }
.param-name { font-size:.78rem; color:#334155; font-weight:700; }
@media (max-width:1100px) {
  .kpi-grid { grid-template-columns:repeat(3, minmax(0, 1fr)); }
  .ops-split, .chart-split { grid-template-columns:1fr; }
}
@media (max-width:768px) {
  .ops-bar, .ops-actions { flex-direction:column; align-items:stretch; }
  .kpi-grid, .param-grid { grid-template-columns:1fr 1fr; }
}
</style>
<div class="content">

<div class="ops-bar">
    <div>
        <h1 class="ops-title">Energy dashboard</h1>
        <div class="ops-sub"><?= htmlspecialchars($selectedMeterName) ?><?= $selectedLocation !== '' ? ' · ' . htmlspecialchars($selectedLocation) : '' ?></div>
        <form method="get" class="mt-2">
            <select name="meter_id" class="form-select meter-select" onchange="this.form.submit()">

        <?php

        foreach ($locationGroups['locations'] as $group)
        {
        ?>
        <optgroup label="<?= htmlspecialchars($group['location'] . ' (' . $group['siteno'] . ')') ?>">
        <?php foreach ($group['meters'] as $meter) { ?>
        <option
            value="<?= $meter['meter_id']; ?>"
            <?= $meter_id == $meter['meter_id'] ? 'selected' : ''; ?>>
            <?= htmlspecialchars($meter['meter_name']); ?>
        </option>
        <?php } ?>
        </optgroup>
        <?php
        }

        if (sees_all_sites())
        {
            $meters = $conn->query("
                SELECT meter_id,meter_name
                FROM meter_master
                ORDER BY meter_id
            ");
        }
        else
        {
            $meters = [];
        }

        $otherMeters = [];
        foreach ($meters as $meter)
        {
            if (!isset($usedMeterIds[(int) $meter['meter_id']]))
            {
                $otherMeters[] = $meter;
            }
        }

        if ($otherMeters)
        {
        ?>
        <optgroup label="Other">
        <?php foreach ($otherMeters as $meter) { ?>
        <option
            value="<?= $meter['meter_id']; ?>"
            <?= $meter_id == $meter['meter_id'] ? 'selected' : ''; ?>>
            <?= htmlspecialchars($meter['meter_name']); ?>
        </option>
        <?php } ?>
        </optgroup>
        <?php } ?>

            </select>
        </form>
    </div>

    <div class="ops-actions">
        <div class="comms <?= $commsClass ?>">
            <i></i>
            <span><?= $commsLabel ?></span>
            <span>
                <?php if ($meterLastReading === null): ?>
                    No sample stored
                <?php else: ?>
                    <?= htmlspecialchars($meterLastReading) ?>
                <?php endif; ?>
            </span>
        </div>
        <div id="clock" class="ops-clock"></div>
        <div class="ops-sub"><?= htmlspecialchars($_SESSION['user']) ?></div>
        <button class="btn btn-outline-dark btn-sm" type="button" onclick="toggleTheme()">Display</button>
        <a class="btn btn-outline-secondary btn-sm" href="export_excel.php?meter_id=<?= $meter_id; ?>">Excel</a>
        <a class="btn btn-outline-secondary btn-sm" href="export_pdf.php?meter_id=<?= $meter_id; ?>">PDF</a>
        <a class="btn btn-outline-danger btn-sm" href="logout.php">Sign out</a>
    </div>
</div>

<?php
$supply = $meter_id > 0 ? ems_sync_meter($conn, (int) $meter_id) : ['source' => 'UNKNOWN', 'avg_v' => 0, 'kw' => 0, 'pf' => null];
$supplyClass = ['EB' => 'supply-eb', 'DG' => 'supply-dg', 'NONE' => 'supply-none'][$supply['source']] ?? 'supply-unknown';
$supplyText = [
    'EB' => 'Running on EB',
    'DG' => 'Running on DG',
    'NONE' => 'No supply — EB and DG are both unavailable',
    'UNKNOWN' => 'No telemetry for this meter yet',
][$supply['source']] ?? 'No telemetry';
?>
<div class="supply-banner <?= $supplyClass ?>">
    <?= htmlspecialchars($supplyText) ?>
    <span class="supply-meta">Voltage <?= number_format((float) $supply['avg_v'], 1) ?> V · Power <?= number_format((float) $supply['kw'], 3) ?> kW<?php if ($supply['pf'] !== null) { ?> · PF <?= number_format((float) $supply['pf'], 2) ?><?php } ?></span>
</div>

<?php
$focusBranch = ems_match_branch($selectedCity);
try {
    $linkedBranch = $conn->prepare('SELECT branch_name FROM meter_branches WHERE meter_id = ?');
    $linkedBranch->execute([$meter_id]);
    $linkedName = $linkedBranch->fetchColumn();
    if ($linkedName) {
        $focusBranch = (string) $linkedName;
    }
} catch (Throwable $e) {
    $linkedName = false;
}
$mapMarkers = [];
try {
    $geoSql = 'SELECT g.branch_name, g.state_name, g.latitude, g.longitude FROM site_geo g';
    $geoParams = [];
    if (!sees_all_sites()) {
        [$clause, $geoParams] = scope_branch_clause('g.branch_name');
        $geoSql .= ' WHERE 1=1' . $clause;
    }
    $geoStmt = $conn->prepare($geoSql);
    $geoStmt->execute($geoParams);
    foreach ($geoStmt as $geo) {
        if ($geo['latitude'] === null || $geo['longitude'] === null) {
            continue;
        }
        $isFocus = strcasecmp((string) $geo['branch_name'], $focusBranch) === 0;
        $mapMarkers[] = [
            'branch' => $geo['branch_name'],
            'state' => $geo['state_name'],
            'lat' => (float) $geo['latitude'],
            'lng' => (float) $geo['longitude'],
            'focus' => $isFocus,
            'source' => $isFocus ? $supply['source'] : 'UNKNOWN',
        ];
    }
} catch (Throwable $e) {
    $mapMarkers = [];
}
$focusPoint = null;
foreach ($mapMarkers as $marker) {
    if ($marker['focus']) {
        $focusPoint = $marker;
        break;
    }
}
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<div class="panel" style="margin-bottom:16px;">
    <h2 style="margin:0 0 8px;font-size:.95rem;">
        <?= $focusPoint ? htmlspecialchars($focusPoint['branch'] . ', ' . $focusPoint['state']) : 'Location map' ?>
    </h2>
    <div id="cityMap" style="height:280px;border-radius:10px;"></div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const cityMarkers = <?= json_encode($mapMarkers, JSON_UNESCAPED_UNICODE) ?>;
const focus = cityMarkers.find(marker => marker.focus) || null;
const cityMap = L.map('cityMap').setView(focus ? [focus.lat, focus.lng] : [22.5, 79], focus ? 11 : 5);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(cityMap);
const pinColours = { EB: '#16a34a', DG: '#e07a00', NONE: '#dc2626', UNKNOWN: '#8b97a3' };
cityMarkers.forEach(marker => {
    const colour = pinColours[marker.source] || pinColours.UNKNOWN;
    const icon = L.divIcon({
        className: 'map-pin',
        html: '<span style="display:block;width:' + (marker.focus ? 16 : 10) + 'px;height:' + (marker.focus ? 16 : 10) + 'px;border-radius:50%;background:' + colour + ';border:2px solid #fff;box-shadow:0 0 0 1px #334155;"></span>',
        iconSize: [marker.focus ? 16 : 10, marker.focus ? 16 : 10],
        iconAnchor: [marker.focus ? 8 : 5, marker.focus ? 8 : 5]
    });
    L.marker([marker.lat, marker.lng], { icon }).addTo(cityMap).bindTooltip(marker.branch + ' · ' + marker.state);
});
</script>

<div class="kpi-grid">
    <div class="panel kpi-power">
        <h2>Active power</h2>
        <div class="kpi-value"><span id="power">--</span><span class="kpi-unit">kW</span></div>
        <div class="kpi-note">This meter</div>
    </div>
    <div class="panel kpi-volt">
        <h2>Voltage</h2>
        <div class="kpi-value"><span id="voltage">--</span><span class="kpi-unit">V</span></div>
        <div class="kpi-note">Average of Vr, Vy, Vb</div>
    </div>
    <div class="panel kpi-amp">
        <h2>Current</h2>
        <div class="kpi-value"><span id="current">--</span><span class="kpi-unit">A</span></div>
        <div class="kpi-note">Average of Ir, Iy, Ib</div>
    </div>
    <div class="panel kpi-eb">
        <h2>EB register</h2>
        <div class="kpi-value"><span id="kwh">--</span><span class="kpi-unit">kWh</span></div>
        <div class="kpi-note">This meter</div>
    </div>
    <div class="panel kpi-sum">
        <h2>All-meter EB</h2>
        <div class="kpi-value"><span id="ebTotal">--</span></div>
        <div class="kpi-note">Sum of latest EB registers</div>
    </div>
    <div class="panel kpi-alarm">
        <h2>Active alarms</h2>
        <div class="kpi-value"><span id="alarmSummary"><?= (int) $activeAlarms ?></span></div>
        <div class="kpi-note"><?= (int) $critical ?> critical · <?= (int) $warning ?> warning</div>
    </div>
</div>

<div class="param-grid">
<?php foreach ($parameterCards as $row) {
    $pname = (string) $row['parameter_name'];
    $unit = dash_unit($pname);
    $tone = 'tone-misc';
    if (stripos($pname, 'PF') !== false) {
        $tone = 'tone-pf';
    } elseif ($unit === 'kWh') {
        $tone = 'tone-kwh';
    } elseif ($unit === 'kW') {
        $tone = 'tone-kw';
    } elseif ($unit === 'V') {
        $tone = 'tone-v';
    } elseif ($unit === 'A') {
        $tone = 'tone-a';
    } elseif ($unit === 'Hz') {
        $tone = 'tone-hz';
    }
?>
    <div class="panel <?= $tone ?>">
        <div class="param-name"><?= htmlspecialchars($pname) ?></div>
        <div class="metric-value" id="p<?= preg_replace('/[^A-Za-z0-9]/', '', $pname) ?>" data-digits="<?= dash_digits($pname) ?>">--</div>
        <?php if ($unit !== '') { ?><div class="metric-unit"><?= htmlspecialchars($unit) ?></div><?php } ?>
    </div>
<?php } ?>
</div>

<div class="ops-split">
    <div class="panel">
        <h2>Consumption · kWh</h2>
        <table class="table table-sm mb-0">

        <thead>

        <tr>
            <th>Period</th>
            <th class="text-end">EB kWh</th>
            <th class="text-end">DG kWh</th>
        </tr>

        </thead>

        <tbody>

        <tr>
            <td>Today</td>
            <td class="text-end"><?= number_format((float) $today['eb'], 2) ?></td>
            <td class="text-end"><?= number_format((float) $today['dg'], 2) ?></td>
        </tr>

        <tr>
            <td>Yesterday</td>
            <td class="text-end"><?= number_format((float) $yesterday['eb'], 2) ?></td>
            <td class="text-end"><?= number_format((float) $yesterday['dg'], 2) ?></td>
        </tr>

        <tr>
            <td>This month</td>
            <td class="text-end"><?= number_format((float) $thisMonth['eb'], 2) ?></td>
            <td class="text-end"><?= number_format((float) $thisMonth['dg'], 2) ?></td>
        </tr>

        <tr>
            <td>Last month</td>
            <td class="text-end"><?= number_format((float) $lastMonth['eb'], 2) ?></td>
            <td class="text-end"><?= number_format((float) $lastMonth['dg'], 2) ?></td>
        </tr>

        </tbody>
        </table>
    </div>

    <div class="panel">
        <h2>Plant status</h2>
        <table class="table table-sm mb-0">
            <tbody>
                <tr><td>Meters in service</td><td class="text-end" id="meterCount"><?= (int) $meterCount['cnt'] ?></td></tr>
                <tr><td>Reporting in the last 5 min</td><td class="text-end"><?= (int) $liveCount ?></td></tr>
                <tr><td>Not reporting</td><td class="text-end"><?= (int) $offlineCount ?></td></tr>
                <tr><td>Latest sample, any meter</td><td class="text-end"><?= $lastConnected ? htmlspecialchars($lastConnected) : '—' ?></td></tr>
                <tr><td>All-meter DG register</td><td class="text-end"><span id="dgTotal">--</span> kWh</td></tr>
                <tr><td>Open incidents</td><td class="text-end"><?= (int) $openIncidents ?></td></tr>
                <tr><td>Site availability</td><td class="text-end"><?= $availability !== null && $availability !== false ? htmlspecialchars((string) $availability) . '%' : '—' ?></td></tr>
                <tr><td>MTTR</td><td class="text-end"><?= $mttr !== null && $mttr !== false ? htmlspecialchars((string) $mttr) . ' min' : '—' ?></td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="panel mb-3">
    <h2>Active power · kW</h2>
    <canvas id="kwChart" height="90"></canvas>
</div>

<div class="chart-split">
    <div class="panel">
        <h2>Phase voltage · V</h2>
        <canvas id="voltageChart" height="140"></canvas>
    </div>
    <div class="panel">
        <h2>Phase current · A</h2>
        <canvas id="currentChart" height="140"></canvas>
    </div>
</div>

<div class="chart-split">
    <div class="panel">
        <h2>EB and DG registers</h2>
        <div style="max-width:280px;margin:0 auto;">
            <canvas id="pieChart"></canvas>
        </div>
    </div>
    <div class="panel">
        <h2>Alarm list · <span id="alarmCount"><?= (int) $activeAlarms ?></span></h2>
        <div id="alarmContainer" class="alarm-list">
            <div class="text-muted">Checking alarms.</div>
        </div>
    </div>
</div>

    

<!-- Live KPI Values -->

<script>

if (localStorage.getItem('ems-theme') === 'dark') {
    document.body.classList.add('dark');
}

function toggleTheme()
{
    document.body.classList.toggle('dark');
    localStorage.setItem('ems-theme', document.body.classList.contains('dark') ? 'dark' : 'light');
}

function fmtReading(value, digits)
{
    const number = parseFloat(value);
    if (!Number.isFinite(number)) {
        return '--';
    }
    return number.toLocaleString('en-IN', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits
    });
}

function loadData()
{
    fetch('api/getLiveData.php?meter_id=<?=$meter_id;?>')

    .then(response => response.json())
    .then(data => {

        let vSum = 0, vCount = 0;
        let iSum = 0, iCount = 0;

        data.forEach(item => {

            let key = item.parameter_name
                .replace(/ /g,'')
                .replace(/\./g,'')
                .replace(/-/g,'');

            let box =
            document.getElementById('p' + key);

            if(box)
            {
                const digits = box.dataset.digits ? parseInt(box.dataset.digits, 10) : 2;
                box.textContent = fmtReading(item.value, digits);
            }

            if(['Vr','Vy','Vb'].includes(item.parameter_name))
            {
                vSum += parseFloat(item.value);
                vCount++;
            }

            if(['Ir','Iy','Ib'].includes(item.parameter_name))
            {
                iSum += parseFloat(item.value);
                iCount++;
            }

            if(item.parameter_name === 'KW-Tot.')
            {
                document.getElementById('power').textContent = fmtReading(item.value, 3);
            }

            if(item.parameter_name === 'EB-KWH')
            {
                document.getElementById('kwh').textContent = fmtReading(item.value, 2);
            }

        });

        if(vCount > 0)
        {
            document.getElementById('voltage').textContent =
                fmtReading(vSum / vCount, 1);
        }

        if(iCount > 0)
        {
            document.getElementById('current').textContent =
                fmtReading(iSum / iCount, 2);
        }

    });
}

loadData();
setInterval(loadData,5000);

</script>

<!-- KW Trend -->

<script>

let kwChart = new Chart(
document.getElementById('kwChart'),
{
    type:'line',

    data:{
        labels:[],
        datasets:[{
            label:'KW-Tot.',
            data:[],
            borderColor:'#1f4b7a',
            backgroundColor:'rgba(31,75,122,0.15)',
            fill:true,
            tension:0.4
        }]
    },
    options: {
        scales: {
            x: { title: { display: true, text: 'Time' } },
            y: { title: { display: true, text: 'Active power (kW)' } }
        }
    }
});

function loadKwTrend()
{
    fetch('api/kwdata.php?meter_id=<?=$meter_id;?>')

    .then(response => response.json())
    .then(data => {

        kwChart.data.labels =
            data.map(row => row.label || row.id);

        kwChart.data.datasets[0].data =
            data.map(row => Number(row.value));

        kwChart.update();
    });
}

loadKwTrend();
setInterval(loadKwTrend,5000);

</script>

<!-- Voltage Chart -->

<script>

fetch('api/voltagedata.php?meter_id=<?=$meter_id;?>')
.then(response => response.json())
.then(data => {

    new Chart(
        document.getElementById('voltageChart'),
        {
            type:'line',

            data:{
                labels: data.labels || [],
                datasets:[
                { label:'Vr', data:data.Vr, borderColor:'#c0392b', spanGaps:true },
                { label:'Vy', data:data.Vy, borderColor:'#c4a000', spanGaps:true },
                { label:'Vb', data:data.Vb, borderColor:'#1f6feb', spanGaps:true }
                ]
            },
            options:{
                scales:{
                    x:{ title:{ display:true, text:'Time' } },
                    y:{ title:{ display:true, text:'Voltage (V)' } }
                }
            }
        }
    );

});

</script>

<!-- Current Chart -->

<script>

fetch('api/currentdata.php?meter_id=<?=$meter_id;?>')
.then(response => response.json())
.then(data => {

    new Chart(
        document.getElementById('currentChart'),
        {
            type:'line',

            data:{
                labels: data.labels || [],
                datasets:[
                { label:'Ir', data:data.Ir, borderColor:'#c0392b', spanGaps:true },
                { label:'Iy', data:data.Iy, borderColor:'#c4a000', spanGaps:true },
                { label:'Ib', data:data.Ib, borderColor:'#1f6feb', spanGaps:true }
                ]
            },
            options:{
                scales:{
                    x:{ title:{ display:true, text:'Time' } },
                    y:{ title:{ display:true, text:'Current (A)' } }
                }
            }
        }
    );

});

</script>

<!-- Pie Chart -->

<script>

fetch('api/piedata.php?meter_id=<?=$meter_id;?>')
.then(response => response.json())
.then(data => {

    console.log("PIE DATA:", data);

    document.getElementById('ebTotal').textContent = fmtReading(data.eb, 2);
    document.getElementById('dgTotal').textContent = fmtReading(data.dg, 2);

    const ctx = document.getElementById('pieChart');

    new Chart(ctx, {
        type: 'pie',

        data: {
            labels: [
                'EB Energy',
                'DG Energy'
            ],

            datasets: [{
                data: [
                    parseFloat(data.eb),
                    parseFloat(data.dg)
                ],

                backgroundColor: [
                    '#28a745',
                    '#fd7e14'
                ]
            }]
        },

        options: {
            responsive: true
        }

    });

})
.catch(error => console.error(error));

</script>

<script>

function ackAlarm(id)
{
    fetch('api/ack_alarm.php',
    {
        method:'POST',

        headers:{
            'Content-Type':
            'application/x-www-form-urlencoded'
        },

        body:'id=' + id
    })
    .then(response => response.text())
    .then(result => {

        loadAlarms();

    });
}

function loadAlarms()
{
    fetch('api/alarmdata.php?meter_id=<?=$meter_id;?>')
    .then(response => response.json())
    .then(data => {

        document.getElementById('alarmSummary').innerHTML =
            data.length;

        let html = '';

        if(data.length === 0)
        {
            html =
                '<div class="text-muted">No active alarms on this meter.</div>';

            document.title = 'Energy dashboard';
        }
        else
        {
            document.title = data.length + ' active alarms · Energy dashboard';
        }

        data.forEach(alarm => {

            html += `
            <div class="alert alert-danger">

                <strong>${alarm.parameter_name}</strong>

                | Limit: ${alarm.alarm_value}

                | Actual: ${alarm.actual_value}

                <button
                    class="btn btn-sm btn-light float-end"
                    onclick="ackAlarm(${alarm.id})">

                    Acknowledge

                </button>

            </div>
            `;

        });

        document.getElementById('alarmCount').innerHTML =
            data.length;

        document.getElementById('alarmContainer').innerHTML =
            html;

    });
}

loadAlarms();

setInterval(loadAlarms,5000);

</script>

<script>

function updateClock()
{
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    document.getElementById('clock').textContent =
        pad(now.getDate()) + ' ' + months[now.getMonth()] + ' ' + now.getFullYear()
        + '  ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
}

updateClock();

setInterval(updateClock, 1000);

</script>

<?php include 'includes/footer.php'; ?>
