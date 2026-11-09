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

$fuel = strtoupper((string) ($_GET['fuel'] ?? 'BOTH'));
if (!in_array($fuel, ['EB', 'DG', 'BOTH'], true)) {
    $fuel = 'BOTH';
}
$period = strtolower((string) ($_GET['period'] ?? 'daily'));
if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) {
    $period = 'daily';
}

$groups = filter_location_groups(ems_location_groups($conn));
$sites = $groups['locations'];
$siteIndex = isset($_GET['site']) ? (int) $_GET['site'] : 0;
if (!isset($sites[$siteIndex])) {
    $siteIndex = 0;
}
$site = $sites[$siteIndex] ?? null;
$ebMeter = (int) ($site['meters'][0]['meter_id'] ?? 0);
$dgMeter = (int) ($site['meters'][1]['meter_id'] ?? 0);

if ($period === 'daily') {
    $fromRaw = (string) ($_GET['from'] ?? date('Y-m-d\T00:00'));
    $toRaw = (string) ($_GET['to'] ?? date('Y-m-d\TH:i'));
    $from = str_replace('T', ' ', $fromRaw);
    $to = str_replace('T', ' ', $toRaw);
    if (strlen($from) === 16) {
        $from .= ':00';
    }
    if (strlen($to) === 16) {
        $to .= ':00';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $from)) {
        $from = date('Y-m-d 00:00:00');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $to)) {
        $to = date('Y-m-d H:i:s');
    }
} elseif ($period === 'weekly') {
    $from = (string) ($_GET['from'] ?? date('Y-m-d', strtotime('-8 weeks')));
    $to = (string) ($_GET['to'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $from = date('Y-m-d', strtotime('-8 weeks'));
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $to = date('Y-m-d');
    }
    $from .= ' 00:00:00';
    $to .= ' 23:59:59';
} else {
    $fromMonth = (string) ($_GET['from'] ?? date('Y-m', strtotime('-5 months')));
    $toMonth = (string) ($_GET['to'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $fromMonth)) {
        $fromMonth = date('Y-m', strtotime('-5 months'));
    }
    if (!preg_match('/^\d{4}-\d{2}$/', $toMonth)) {
        $toMonth = date('Y-m');
    }
    $from = $fromMonth . '-01 00:00:00';
    $to = date('Y-m-t 23:59:59', strtotime($toMonth . '-01'));
}

function report_bucket(string $time, string $period): string
{
    $stamp = strtotime($time);
    if ($period === 'daily') {
        return date('Y-m-d H:00', $stamp);
    }
    if ($period === 'weekly') {
        $offset = (int) date('N', $stamp) - 1;
        return date('Y-m-d', strtotime('-' . $offset . ' days', strtotime(date('Y-m-d', $stamp))));
    }
    return date('Y-m', $stamp);
}

function report_label(string $bucket, string $period): string
{
    if ($period === 'daily') {
        return date('d M H:i', strtotime($bucket));
    }
    if ($period === 'weekly') {
        $start = strtotime($bucket);
        return date('d M', $start) . ' – ' . date('d M', strtotime('+6 days', $start));
    }
    return date('M Y', strtotime($bucket . '-01'));
}

function report_series(PDO $conn, int $meterId, string $parameter, string $from, string $to, string $period): array
{
    if ($meterId <= 0) {
        return [];
    }
    $stmt = $conn->prepare("
        SELECT value, reading_time
        FROM meter_readings
        WHERE meter_id = ? AND parameter_name = ? AND reading_time BETWEEN ? AND ?
        ORDER BY reading_time ASC
    ");
    $stmt->execute([$meterId, $parameter, $from, $to]);
    $buckets = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = report_bucket($row['reading_time'], $period);
        $value = (float) $row['value'];
        if (!isset($buckets[$key])) {
            $buckets[$key] = [
                'open' => $value,
                'close' => $value,
                'open_time' => $row['reading_time'],
                'close_time' => $row['reading_time'],
            ];
        } else {
            $buckets[$key]['close'] = $value;
            $buckets[$key]['close_time'] = $row['reading_time'];
        }
    }
    return $buckets;
}

$eb = $site ? report_series($conn, $ebMeter, 'EB-KWH', $from, $to, $period) : [];
$dg = $site ? report_series($conn, $dgMeter > 0 ? $dgMeter : $ebMeter, 'DG-KWH', $from, $to, $period) : [];
if ($dgMeter <= 0) {
    $dg = [];
}
$keys = array_values(array_unique(array_merge(array_keys($eb), array_keys($dg))));
sort($keys);

$rows = [];
$chartLabels = [];
$chartEb = [];
$chartDg = [];
$ebOpen = null;
$ebClose = null;
$dgOpen = null;
$dgClose = null;
foreach ($keys as $key) {
    $ebRow = $eb[$key] ?? null;
    $dgRow = $dg[$key] ?? null;
    $ebUnits = $ebRow ? round($ebRow['close'] - $ebRow['open'], 2) : null;
    $dgUnits = $dgRow ? round($dgRow['close'] - $dgRow['open'], 2) : null;
    $rows[] = [
        'label' => report_label($key, $period),
        'eb_open' => $ebRow['open'] ?? null,
        'eb_close' => $ebRow['close'] ?? null,
        'eb_units' => $ebUnits,
        'dg_open' => $dgRow['open'] ?? null,
        'dg_close' => $dgRow['close'] ?? null,
        'dg_units' => $dgUnits,
    ];
    $chartLabels[] = report_label($key, $period);
    $chartEb[] = $ebUnits ?? 0;
    $chartDg[] = $dgUnits ?? 0;
    if ($ebRow) {
        $ebOpen = $ebOpen === null ? $ebRow['open'] : $ebOpen;
        $ebClose = $ebRow['close'];
    }
    if ($dgRow) {
        $dgOpen = $dgOpen === null ? $dgRow['open'] : $dgOpen;
        $dgClose = $dgRow['close'];
    }
}

function report_num($value): string
{
    return $value === null ? '—' : number_format((float) $value, 2);
}

$title = $fuel === 'EB' ? 'EB report' : ($fuel === 'DG' ? 'DG report' : 'Energy overview');
$periodTitle = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'][$period];

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<style>
.report-wrap { background:#e8eef5; margin:-8px; padding:18px; min-height:100vh; }
.report-head { display:flex; justify-content:space-between; gap:16px; align-items:flex-end; flex-wrap:wrap; margin-bottom:14px; }
.report-title { margin:0; font-size:1.45rem; color:#0f2744; font-weight:700; }
.report-sub { color:#5c6b7a; font-size:.88rem; }
.period-tabs { display:flex; gap:8px; }
.period-tabs a { background:#fff; border-radius:999px; padding:6px 14px; text-decoration:none; color:#334155; font-weight:700; font-size:.85rem; }
.period-tabs a.active { background:#0f2744; color:#fff; }
.report-kpis { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px; margin:14px 0; }
.report-kpi { background:#fff; border-radius:12px; padding:14px 16px; border-top:4px solid #94a3b8; box-shadow:0 1px 2px rgba(15,39,68,.06); }
.report-kpi.eb { border-top-color:#16a34a; }
.report-kpi.dg { border-top-color:#e07a00; }
.report-kpi span { display:block; color:#64748b; font-size:.75rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
.report-kpi strong { font-size:1.35rem; color:#0f2744; }
.report-panel { background:#fff; border-radius:12px; padding:16px; margin-bottom:14px; box-shadow:0 1px 2px rgba(15,39,68,.06); }
.swatch { display:inline-block; width:10px; height:10px; border-radius:2px; margin-right:6px; }
.swatch.eb { background:#16a34a; }
.swatch.dg { background:#e07a00; }
@media (max-width:900px) { .report-kpis { grid-template-columns:1fr 1fr; } }
</style>
<div class="content">
<div class="report-wrap">
<div class="report-head">
    <div>
        <h1 class="report-title"><?= htmlspecialchars($title) ?></h1>
        <div class="report-sub">
            <?= htmlspecialchars($periodTitle) ?> opening and closing registers.
            <?php if ($site) { ?>
                <?= htmlspecialchars($site['location'] . ' · ' . $site['siteno']) ?>
            <?php } ?>
        </div>
    </div>
    <div class="period-tabs">
        <?php foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $key => $label) {
            $href = 'consumption_report.php?' . http_build_query(['fuel' => $fuel, 'period' => $key, 'site' => $siteIndex]);
        ?>
        <a class="<?= $period === $key ? 'active' : '' ?>" href="<?= htmlspecialchars($href) ?>"><?= $label ?></a>
        <?php } ?>
    </div>
</div>

<form method="get" class="report-panel">
    <input type="hidden" name="fuel" value="<?= htmlspecialchars($fuel) ?>">
    <input type="hidden" name="period" value="<?= htmlspecialchars($period) ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Location</label>
            <select name="site" class="form-select">
                <?php if ($sites === []) { ?><option value="0">No location assigned</option><?php } ?>
                <?php foreach ($sites as $i => $group) { ?>
                <option value="<?= $i ?>" <?= $i === $siteIndex ? 'selected' : '' ?>>
                    <?= htmlspecialchars($group['location'] . ' (' . $group['siteno'] . ')') ?>
                </option>
                <?php } ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">From <?= $period === 'daily' ? 'time' : '' ?></label>
            <?php if ($period === 'daily') { ?>
            <input type="datetime-local" name="from" class="form-control" value="<?= htmlspecialchars(str_replace(' ', 'T', substr($from, 0, 16))) ?>">
            <?php } elseif ($period === 'monthly') { ?>
            <input type="month" name="from" class="form-control" value="<?= htmlspecialchars(substr($from, 0, 7)) ?>">
            <?php } else { ?>
            <input type="date" name="from" class="form-control" value="<?= htmlspecialchars(substr($from, 0, 10)) ?>">
            <?php } ?>
        </div>
        <div class="col-md-3">
            <label class="form-label">To <?= $period === 'daily' ? 'time' : '' ?></label>
            <?php if ($period === 'daily') { ?>
            <input type="datetime-local" name="to" class="form-control" value="<?= htmlspecialchars(str_replace(' ', 'T', substr($to, 0, 16))) ?>">
            <?php } elseif ($period === 'monthly') { ?>
            <input type="month" name="to" class="form-control" value="<?= htmlspecialchars(substr($to, 0, 7)) ?>">
            <?php } else { ?>
            <input type="date" name="to" class="form-control" value="<?= htmlspecialchars(substr($to, 0, 10)) ?>">
            <?php } ?>
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100">Apply</button>
        </div>
    </div>
</form>

<div class="report-kpis">
    <div class="report-kpi eb"><span>EB opening</span><strong><?= report_num($ebOpen) ?></strong></div>
    <div class="report-kpi eb"><span>EB closing</span><strong><?= report_num($ebClose) ?></strong></div>
    <div class="report-kpi dg"><span>DG opening</span><strong><?= report_num($dgOpen) ?></strong></div>
    <div class="report-kpi dg"><span>DG closing</span><strong><?= report_num($dgClose) ?></strong></div>
</div>

<div class="report-panel">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <strong><?= htmlspecialchars($periodTitle) ?> consumption</strong>
        <div class="small">
            <span class="swatch eb"></span>EB
            <span class="swatch dg ms-2"></span>DG
        </div>
    </div>
    <div style="height:320px;"><canvas id="fuelChart"></canvas></div>
</div>

<div class="report-panel">
<table class="table table-bordered table-striped mb-0">
<thead>
<tr>
    <th><?= $period === 'daily' ? 'Hour' : ($period === 'weekly' ? 'Week' : 'Month') ?></th>
    <th>EB open</th>
    <th>EB close</th>
    <th>EB units</th>
    <th>DG open</th>
    <th>DG close</th>
    <th>DG units</th>
</tr>
</thead>
<tbody>
<?php if ($rows === []) { ?>
<tr><td colspan="7" class="text-muted">No EB or DG register samples in this window.</td></tr>
<?php } ?>
<?php foreach ($rows as $row) { ?>
<tr>
    <td><?= htmlspecialchars($row['label']) ?></td>
    <td><?= report_num($row['eb_open']) ?></td>
    <td><?= report_num($row['eb_close']) ?></td>
    <td><?= report_num($row['eb_units']) ?></td>
    <td><?= report_num($row['dg_open']) ?></td>
    <td><?= report_num($row['dg_close']) ?></td>
    <td><?= report_num($row['dg_units']) ?></td>
</tr>
<?php } ?>
</tbody>
</table>
<p class="small text-muted mt-2 mb-0">Opening is the first stored register in each hour, week, or month. Closing is the last. Units are closing minus opening. EB is green and DG is orange on the chart.</p>
<div class="mt-2">
    <a href="eb_bill_report.php">Saved EB worksheets</a>
    <span class="text-muted"> · </span>
    <a href="dg_report.php">Saved DG worksheets</a>
</div>
</div>
</div>
</div>
<script>
const fuelChart = document.getElementById('fuelChart');
if (fuelChart) {
    new Chart(fuelChart, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                { label: 'EB kWh', data: <?= json_encode($chartEb) ?>, backgroundColor: '#16a34a' },
                { label: 'DG kWh', data: <?= json_encode($chartDg) ?>, backgroundColor: '#e07a00' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { title: { display: true, text: <?= json_encode($period === 'daily' ? 'Time' : ($period === 'weekly' ? 'Week' : 'Month')) ?> } },
                y: { beginAtZero: true, title: { display: true, text: 'Units (kWh)' } }
            }
        }
    });
}
</script>
<?php include 'includes/footer.php'; ?>
