<?php

require '../config/db.php';

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
    GROUP BY parameter_name
) x

ON m.id = x.maxid

";

$stmt = $conn->query($sql);

echo json_encode(
    $stmt->fetchAll(PDO::FETCH_ASSOC)
);

?>