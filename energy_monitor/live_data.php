<?php

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(null);
    exit;
}

require 'config/db.php';

$sql = "
SELECT *
FROM meter_readings
ORDER BY reading_time DESC
LIMIT 1
";

$row = $conn->query($sql)->fetch(PDO::FETCH_ASSOC);

echo json_encode($row ?: null);