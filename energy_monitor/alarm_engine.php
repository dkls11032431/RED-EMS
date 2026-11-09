<?php

require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/plant_standards.php';

ems_ensure_plant($conn);

$meters = $conn->query('SELECT meter_id, meter_name FROM meter_master ORDER BY meter_id');
echo "Alarm check\n";
foreach ($meters as $meter) {
    $branchStmt = $conn->prepare('SELECT branch_name FROM meter_branches WHERE meter_id = ?');
    $branchStmt->execute([$meter['meter_id']]);
    $branch = (string) ($branchStmt->fetchColumn() ?: $meter['meter_name']);
    $snapshot = ems_sync_meter($conn, (int) $meter['meter_id'], $branch);
    echo $meter['meter_name'] . ' | ' . $branch . ' | ' . $snapshot['source'] . ' | ' . round($snapshot['avg_v'], 1) . " V\n";
}
echo "Done\n";
