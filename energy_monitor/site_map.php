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

if (sees_all_sites() && isset($_POST['save_geo'])) {
    $stmt = $conn->prepare('UPDATE site_geo SET latitude = ?, longitude = ? WHERE id = ?');
    $stmt->execute([(float) $_POST['latitude'], (float) $_POST['longitude'], (int) $_POST['id']]);
    header('Location: site_map.php?saved=1');
    exit;
}

$sql = 'SELECT g.*, s.company FROM site_geo g JOIN bill_sites s ON s.state_name = g.state_name AND s.branch_name = g.branch_name';
$params = [];
if (!sees_all_sites()) {
    [$clause, $params] = scope_branch_clause('g.branch_name');
    $sql .= ' WHERE 1=1' . $clause;
}
$sql .= ' ORDER BY s.company, g.state_name, g.branch_name';
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

$markers = [];
foreach ($sites as $site) {
    $meter = $conn->prepare('SELECT meter_id FROM meter_branches WHERE branch_name = ? LIMIT 1');
    $meter->execute([$site['branch_name']]);
    $meterId = $meter->fetchColumn();
    $source = 'UNKNOWN';
    if ($meterId) {
        $snapshot = ems_meter_snapshot($conn, (int) $meterId);
        $source = $snapshot['source'];
    }
    $markers[] = [
        'id' => (int) $site['id'],
        'branch' => $site['branch_name'],
        'state' => $site['state_name'],
        'company' => $site['company'],
        'lat' => $site['latitude'] !== null ? (float) $site['latitude'] : null,
        'lng' => $site['longitude'] !== null ? (float) $site['longitude'] : null,
        'source' => $source,
    ];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<div class="content">
<h2>Location map</h2>
<p class="text-muted">Green is EB, orange is DG, red blinking is no supply. Grey means the location has no live meter yet. Hover a marker for the location.</p>
<?php if (isset($_GET['saved'])) { ?><div class="alert alert-success">Coordinates saved.</div><?php } ?>
<div id="siteMap" style="height:520px;border:1px solid #d5dde6;border-radius:4px;"></div>

<?php if (sees_all_sites()) { ?>
<h3 class="mt-4">Set coordinates</h3>
<form method="post" class="row g-2">
    <div class="col-md-4">
        <select name="id" class="form-select">
            <?php foreach ($sites as $site) { ?>
            <option value="<?= (int) $site['id'] ?>"><?= htmlspecialchars($site['branch_name'] . ' · ' . $site['state_name']) ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="col-md-2"><input class="form-control" name="latitude" placeholder="Latitude" required></div>
    <div class="col-md-2"><input class="form-control" name="longitude" placeholder="Longitude" required></div>
    <div class="col-md-2"><button class="btn btn-primary" name="save_geo">Save</button></div>
</form>
<?php } ?>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const markers = <?= json_encode($markers, JSON_UNESCAPED_UNICODE) ?>;
const map = L.map('siteMap').setView([22.5, 79], 5);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
const colours = { EB: '#1f8a4c', DG: '#e07a00', NONE: '#b42318', UNKNOWN: '#8b97a3' };
const labels = { EB: 'EB supply', DG: 'DG supply', NONE: 'No supply', UNKNOWN: 'No telemetry' };
markers.forEach(marker => {
    if (marker.lat === null || marker.lng === null) return;
    const icon = L.divIcon({
        className: 'map-pin',
        html: '<span class="pin ' + (marker.source === 'NONE' ? 'pin-blink' : '') + '" style="background:' + colours[marker.source] + '"></span>',
        iconSize: [16, 16],
        iconAnchor: [8, 8]
    });
    L.marker([marker.lat, marker.lng], { icon })
        .addTo(map)
        .bindTooltip(marker.branch + ' · ' + marker.state + ' · ' + labels[marker.source]);
});
</script>
<style>
.pin { display:block; width:14px; height:14px; border-radius:50%; border:2px solid #fff; box-shadow:0 0 0 1px #333; }
.pin-blink { animation: supplyBlink 1s steps(1) infinite; }
</style>
<?php include 'includes/footer.php'; ?>
