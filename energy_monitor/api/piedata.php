<?php

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['eb' => 0, 'dg' => 0]);
    exit;
}

require '../config/db.php';

/*
 | EB-KWH / DG-KWH are cumulative counters, and the same parameter_name
 | exists for multiple meters. To get a true system-wide total, sum
 | each meter's LATEST reading — never MAX() across all raw rows,
 | which would just return whichever single meter has the highest
 | running count instead of a real total.
*/
function total_across_meters(PDO $conn, $paramName)
{
    $stmt = $conn->prepare("
        SELECT SUM(latest.value) AS total
        FROM (
            SELECT mr.meter_id, mr.value
            FROM meter_readings mr
            INNER JOIN (
                SELECT meter_id, MAX(reading_time) AS max_time
                FROM meter_readings
                WHERE parameter_name = ?
                GROUP BY meter_id
            ) x ON mr.meter_id = x.meter_id AND mr.reading_time = x.max_time
            WHERE mr.parameter_name = ?
        ) latest
    ");
    $stmt->execute([$paramName, $paramName]);
    $total = $stmt->fetchColumn();

    return $total !== false ? (float) $total : 0;
}

echo json_encode([
    "eb" => total_across_meters($conn, 'EB-KWH'),
    "dg" => total_across_meters($conn, 'DG-KWH'),
]);