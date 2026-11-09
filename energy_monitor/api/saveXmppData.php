<?php

require '../config/db.php';

$data = json_decode(
    file_get_contents("php://input"),
    true
);

foreach($data as $name => $value)
{
    $q = $conn->prepare("
    SELECT id
    FROM parameter_master_princek2
    WHERE parameter_name = ?
    ");

    $q->execute([$name]);

    $row = $q->fetch(PDO::FETCH_ASSOC);

    if($row)
    {
        $insert = $conn->prepare("
        INSERT INTO meter_readings
        (
            parameter_id,
            parameter_name,
            value
        )
        VALUES
        (
            ?, ?, ?
        )
        ");

        $insert->execute([
            $row['id'],
            $name,
            $value
        ]);
    }
}

echo json_encode([
    "status" => "success"
]);