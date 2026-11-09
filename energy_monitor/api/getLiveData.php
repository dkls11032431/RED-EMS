<?php

require __DIR__ . '/../includes/api_guard.php';
require '../config/db.php';
require __DIR__ . '/../includes/auth_helpers.php';

$meterId = isset($_GET['meter_id']) ? (int) $_GET['meter_id'] : 0;
if ($meterId > 0 && !meter_belongs_to_user($conn, $meterId)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Meter is outside your account']);
    exit;
}
if ($meterId <= 0) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}
$meterSql = $meterId > 0 ? ' AND meter_id = ' . $meterId : '';

$sql = "

SELECT
    m.parameter_name,
    m.value

FROM meter_readings m

INNER JOIN
(
    SELECT
        parameter_name,
        MAX(id) AS maxid
    FROM meter_readings
    WHERE id > 0 {$meterSql}
    GROUP BY parameter_name
) x

ON m.id = x.maxid

";

$stmt = $conn->query($sql);

header('Content-Type: application/json');

echo json_encode(
    $stmt->fetchAll(PDO::FETCH_ASSOC)
);

?>