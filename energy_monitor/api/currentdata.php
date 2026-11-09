<?php

require '../config/db.php';

$meterId = isset($_GET['meter_id']) ? (int) $_GET['meter_id'] : 0;
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$data = ['labels' => [], 'Ir' => [], 'Iy' => [], 'Ib' => []];

$sql = "
    SELECT DATE_FORMAT(reading_time, '%d %b %H:%i') AS label, parameter_name, value
    FROM meter_readings
    WHERE parameter_name IN ('Ir', 'Iy', 'Ib')
";
$params = [];
if ($meterId > 0) {
    $sql .= ' AND meter_id = ?';
    $params[] = $meterId;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $sql .= ' AND reading_time >= ?';
    $params[] = $from . ' 00:00:00';
} else {
    $sql .= ' AND reading_time >= DATE_SUB(NOW(), INTERVAL 12 HOUR)';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $sql .= ' AND reading_time <= ?';
    $params[] = $to . ' 23:59:59';
}
$sql .= ' ORDER BY reading_time ASC, id ASC LIMIT 180';
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$byTime = [];
foreach ($stmt as $row) {
    $byTime[$row['label']][$row['parameter_name']] = (float) $row['value'];
}
foreach ($byTime as $label => $values) {
    $data['labels'][] = $label;
    foreach (['Ir', 'Iy', 'Ib'] as $name) {
        $data[$name][] = $values[$name] ?? null;
    }
}

header('Content-Type: application/json');
echo json_encode($data);
