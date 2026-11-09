<?php

require __DIR__ . '/../includes/api_guard.php';
require '../config/db.php';

$stmt = $conn->query("
SELECT
    value,
    DATE_FORMAT(reading_time,'%H:%i') AS time_label
FROM meter_readings
WHERE parameter_name='EB-KWH'
ORDER BY id ASC
LIMIT 20
");

echo json_encode(
    $stmt->fetchAll(PDO::FETCH_ASSOC)
);

?>
``