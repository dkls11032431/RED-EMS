<?php

date_default_timezone_set('Asia/Kolkata');

// Get raw request body
$raw = file_get_contents("php://input");

// Get client IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

// Log every request for troubleshooting
$log = "============================\n";
$log .= date('Y-m-d H:i:s') . "\n";
$log .= "METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? '') . "\n";
$log .= "REMOTE IP: " . $ip . "\n";
$log .= "CONTENT TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? '') . "\n";
$log .= "RAW: " . $raw . "\n\n";

file_put_contents(
    __DIR__ . '/getdata_debug.log',
    $log,
    FILE_APPEND
);

// Parse raw URL-encoded data
parse_str($raw, $params);

$db     = $params['db'] ?? '';
$siteno = $params['siteno'] ?? '';
$hex    = $params['data'] ?? '';

// If gateway sends multipart/form data, also check $_POST
if ($db === '' && isset($_POST['db'])) {
    $db = $_POST['db'];
}

if ($siteno === '' && isset($_POST['siteno'])) {
    $siteno = $_POST['siteno'];
}

if ($hex === '' && isset($_POST['data'])) {
    $hex = $_POST['data'];
}

// Only insert when data exists
if ($hex !== '') {

    $mysqli = new mysqli(
        "127.0.0.1",
        "root",
        "",
        "meterdb"
    );

    if ($mysqli->connect_error) {
        http_response_code(500);
        echo "Database connection failed";
        exit;
    }

    $mysqli->set_charset("utf8mb4");

    $sql = "INSERT INTO meter_packets
            (received_at, db_name, site_no, data_hex, source_ip)
            VALUES (NOW(), ?, ?, ?, ?)";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        http_response_code(500);
        echo "Prepare failed: " . $mysqli->error;
        exit;
    }

    $stmt->bind_param(
        "ssss",
        $db,
        $siteno,
        $hex,
        $ip
    );

    if (!$stmt->execute()) {
        http_response_code(500);
        echo "Insert failed: " . $stmt->error;
        exit;
    }

    $stmt->close();
    $mysqli->close();
}

// Always tell the gateway the HTTP request was accepted
http_response_code(200);
echo "OK";

?>
