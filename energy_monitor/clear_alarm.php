<?php

session_start();

if (!isset($_SESSION['user']))
{
    header("Location: login.php");
    exit;
}

require 'config/db.php';

$id = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;

if ($id <= 0)
{
    die("Invalid Alarm ID");
}

$stmt = $conn->prepare("
    UPDATE alarms
    SET
        status = 'CLEARED',
        cleared_time = NOW()
    WHERE id = ?
");

$stmt->execute([$id]);

header("Location: active_alarms.php");
exit;