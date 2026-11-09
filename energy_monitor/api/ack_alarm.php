<?php

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

require '../config/db.php';

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if (!$id) {
    echo json_encode(['ok' => false, 'error' => 'Missing alarm id']);
    exit;
}

$stmt = $conn->prepare("
    UPDATE alarms
    SET status = 'ACKNOWLEDGED'
    WHERE id = ?
");
$stmt->execute([$id]);

echo json_encode(['ok' => true]);