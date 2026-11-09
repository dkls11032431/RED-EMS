<?php

$host = getenv('EMS_DB_HOST') ?: '127.0.0.1';
$port = getenv('EMS_DB_PORT') ?: '3306';
$name = getenv('EMS_DB_NAME') ?: 'princek2';
$user = getenv('EMS_DB_USER') ?: 'root';
$pass = getenv('EMS_DB_PASS');
if ($pass === false) {
    $pass = '';
}

$conn = new PDO(
    'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4',
    $user,
    $pass
);

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
