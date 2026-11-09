<?php

require __DIR__ . '/../includes/api_guard.php';
require '../config/db.php';
require __DIR__ . '/../includes/auth_helpers.php';

$meterId = isset($_GET['meter_id']) ? (int) $_GET['meter_id'] : 0;
if ($meterId <= 0 || !meter_belongs_to_user($conn, $meterId)) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}
$sql = "
SELECT
    id,
    DATE_FORMAT(reading_time, '%d %b %H:%i') AS label,
    value
FROM meter_readings
WHERE parameter_name='KW-Tot.'
AND reading_time >= DATE_SUB(NOW(), INTERVAL 12 HOUR)
";
if ($meterId > 0) {
    $sql .= " AND meter_id = " . $meterId;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '')) {
    $sql = str_replace(
        "AND reading_time >= DATE_SUB(NOW(), INTERVAL 12 HOUR)",
        "AND reading_time >= " . $conn->quote($_GET['from'] . ' 00:00:00'),
        $sql
    );
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '')) {
    $sql .= ' AND reading_time <= ' . $conn->quote($_GET['to'] . ' 23:59:59');
}
$sql .= " ORDER BY reading_time DESC, id DESC LIMIT 80";

$data = array_reverse($conn->query($sql)->fetchAll(PDO::FETCH_ASSOC));

header('Content-Type: application/json');

echo json_encode($data);

exit;