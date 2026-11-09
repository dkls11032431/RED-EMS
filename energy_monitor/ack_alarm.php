<?php

session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user']))
{
    http_response_code(401);

    echo json_encode([
        'ok' => false,
        'error' => 'User not logged in'
    ]);

    exit;
}

require 'config/db.php';

$id = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;

if ($id <= 0)
{
    echo json_encode([
        'ok' => false,
        'error' => 'Invalid alarm ID'
    ]);

    exit;
}

try
{
    $stmt = $conn->prepare("
        UPDATE alarms
        SET status = 'ACK'
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    echo json_encode([
        'ok' => true,
        'message' => 'Alarm acknowledged successfully'
    ]);
}
catch (Exception $e)
{
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
