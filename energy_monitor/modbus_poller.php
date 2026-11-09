<?php

/*
 | Run this on a schedule (e.g. Windows Task Scheduler every 30-60s)
 | once the USR gateway is switched from HTTPD Client to TCP Server
 | work mode with Modbus Type = Modbus TCP/RTU (gateway manual,
 | section 5.1.1).
 |
 | This polls the SPMG300's real, documented register map directly -
 | no more guessing at the proprietary "data=hex" push format.
*/

require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/modbus_client.php';
$registerMap = require __DIR__ . '/includes/spmg300_register_map.php';

// Defaults match the existing USR gateway. Override with MODBUS_HOST, MODBUS_PORT, MODBUS_METER_ID.
$gatewayHost = getenv('MODBUS_HOST') ?: '172.16.11.102';
$gatewayPort = (int) (getenv('MODBUS_PORT') ?: 502);
$meterId     = (int) (getenv('MODBUS_METER_ID') ?: 1);

$paramIdCache = [];

function get_parameter_id(PDO $conn, string $name, int $meterGroupId, array &$cache)
{
    $key = $name . '|' . $meterGroupId;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $stmt = $conn->prepare("
        SELECT id FROM parameter_master_princek2
        WHERE parameter_name = ? AND meter_group_id = ?
        LIMIT 1
    ");
    $stmt->execute([$name, $meterGroupId]);
    $id = $stmt->fetchColumn();

    $cache[$key] = $id ?: null;
    return $cache[$key];
}

$insertStmt = $conn->prepare("
    INSERT INTO meter_readings (meter_id, parameter_id, parameter_name, value, reading_time)
    VALUES (?, ?, ?, ?, NOW())
");

$successCount = 0;
$errorCount = 0;

foreach ($registerMap as $entry) {
    try {
        $value = modbus_read_32bit_scaled(
            $gatewayHost,
            $gatewayPort,
            $entry['address'],
            $entry['resolution'],
            1,        // unit id - the meter's Modbus slave address
            'hi-lo'   // if values look 65536x off, try 'lo-hi' instead
        );

        $parameterId = get_parameter_id($conn, $entry['parameter_name'], $meterId, $paramIdCache);

        $insertStmt->execute([
            $meterId,
            $parameterId, // may be NULL if this parameter_name isn't in parameter_master_princek2 for this meter_group_id yet
            $entry['parameter_name'],
            $value,
        ]);

        $successCount++;
        echo date('Y-m-d H:i:s') . " OK   addr={$entry['address']} {$entry['parameter_name']} = {$value}\n";

    } catch (ModbusTcpException $e) {
        $errorCount++;
        echo date('Y-m-d H:i:s') . " FAIL addr={$entry['address']} {$entry['parameter_name']}: " . $e->getMessage() . "\n";
    }
}

echo "\nDone: {$successCount} succeeded, {$errorCount} failed.\n";
