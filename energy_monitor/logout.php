<?php

session_start();

$username = (string) ($_SESSION['user'] ?? '');
$started = isset($_SESSION['login_at']) ? (int) $_SESSION['login_at'] : null;

if ($username !== '') {
    require 'config/db.php';
    require 'includes/activity_log.php';
    $seconds = $started ? max(0, time() - $started) : null;
    $detail = $seconds !== null ? ('Session length ' . ems_format_duration($seconds)) : 'Signed out';
    ems_log_event($conn, $username, 'logout', 'session', $detail, $seconds);
}

session_destroy();

header("Location: login.php");

exit();
