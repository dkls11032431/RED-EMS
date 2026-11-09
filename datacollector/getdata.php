<?php

/*
 | Device endpoint. No session check.
 | Each push is decoded onto meter_readings, which is what the
 | dashboard, live page, and reports query.
*/

$raw = file_get_contents('php://input');
if (!is_string($raw)) {
    $raw = '';
}

$logFile = __DIR__ . '/raw_capture.log';
$stamp = date('Y-m-d H:i:s');
file_put_contents(
    $logFile,
    $stamp
        . ' | hit method=' . ($_SERVER['REQUEST_METHOD'] ?? '')
        . ' bytes=' . strlen($raw)
        . "\n",
    FILE_APPEND
);
register_shutdown_function(function () use ($logFile, $stamp) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR], true)) {
        file_put_contents(
            $logFile,
            $stamp . ' | fatal=' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line'] . "\n",
            FILE_APPEND
        );
    }
});

require __DIR__ . '/gateway_request.php';
require __DIR__ . '/decode_frame.php';
require __DIR__ . '/../energy_monitor/config/db.php';

[$db, $siteno, $dataHex] = ems_gateway_fields($raw, $_POST, $_GET);
$receivedLen = strlen($dataHex);

if ($siteno === '' || $dataHex === '') {
    $logLine = date('Y-m-d H:i:s')
        . ' | db=' . $db
        . ' | siteno=' . $siteno
        . ' | raw_len=' . strlen($raw)
        . ' | data_len=0'
        . "\n";
    file_put_contents(__DIR__ . '/raw_capture.log', $logLine, FILE_APPEND);
    http_response_code(200);
    echo 'OK (nothing to store)';
    exit;
}

$dataHex = ems_assemble_frame($siteno, $dataHex);
if ($dataHex === '') {
    $logLine = date('Y-m-d H:i:s')
        . ' | db=' . $db
        . ' | siteno=' . $siteno
        . ' | raw_len=' . strlen($raw)
        . ' | data_len=' . $receivedLen
        . ' | stored=0 | incomplete=1'
        . "\n";
    file_put_contents(__DIR__ . '/raw_capture.log', $logLine, FILE_APPEND);
    http_response_code(200);
    echo 'OK (incomplete frame)';
    exit;
}

$prefix = substr($dataHex, 0, 4);
$payloadType = ($prefix === '0BB8') ? 'A' : (($prefix === '000D') ? 'B' : 'UNKNOWN');
$stored = 0;
$meterId = 0;
$readings = [];
$note = '';
$bound = ['location' => '', 'main' => 0, 'dg' => null];
try {
    $readings = ems_readings_from_hex($dataHex);
    if (!ems_site_profile($siteno)['dg']) {
        unset($readings['DG-KWH']);
    }
} catch (Throwable $e) {
    $readings = [];
    $note = ' | decode_error=' . $e->getMessage();
}

try {
    $bound = ems_bind_location_meters($conn, $siteno);
    $meterId = $bound['main'];
    $mainReadings = $readings;
    unset($mainReadings['DG-KWH']);
    $stored = ems_store_readings($conn, $bound['main'], $mainReadings);
    if ($bound['dg'] !== null && isset($readings['DG-KWH'])) {
        $stored += ems_store_readings($conn, $bound['dg'], [
            'DG-KWH' => $readings['DG-KWH'],
        ]);
    }
} catch (Throwable $e) {
    $note .= ' | readings_error=' . $e->getMessage();
}

$summary = '';
foreach (['KW-Tot.', 'Vr', 'Fr.', 'EB-KWH', 'DG-KWH'] as $key) {
    if (isset($readings[$key])) {
        $summary .= ' ' . $key . '=' . $readings[$key];
    }
}

$logLine = date('Y-m-d H:i:s')
    . ' | db=' . $db
    . ' | siteno=' . $siteno
    . ' | location=' . ($bound['location'] ?? '')
    . ' | meter=' . $meterId
    . ' | dg_meter=' . ($bound['dg'] ?? '')
    . ' | raw_len=' . strlen($raw)
    . ' | data_len=' . strlen($dataHex)
    . ' | stored=' . $stored
    . ' |' . $summary
    . ' | data=' . $dataHex
    . $note
    . "\n";
file_put_contents(__DIR__ . '/raw_capture.log', $logLine, FILE_APPEND);

http_response_code(200);
echo "OK ({$stored} readings stored, type {$payloadType})";
