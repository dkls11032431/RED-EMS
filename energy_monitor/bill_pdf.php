<?php

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
require 'config/db.php';
require 'includes/auth_helpers.php';
require 'includes/plant_standards.php';
ems_ensure_plant($conn);

$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT id, branch_name, company, bill_pdf FROM eb_worksheets WHERE id = ?');
$stmt->execute([$id]);
$sheet = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sheet || empty($sheet['bill_pdf'])) {
    http_response_code(404);
    echo 'Bill PDF not found.';
    exit;
}
if (!sees_all_sites() && !user_can_branch((string) $sheet['branch_name'])) {
    http_response_code(403);
    echo 'This bill is outside your locations.';
    exit;
}

$path = __DIR__ . '/' . ltrim((string) $sheet['bill_pdf'], '/');
$real = realpath($path);
$root = realpath(__DIR__ . '/uploads/bills');
if ($real === false || $root === false || strncmp($real, $root, strlen($root)) !== 0 || !is_file($real)) {
    http_response_code(404);
    echo 'Bill PDF not found.';
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="eb-bill-' . $id . '.pdf"');
header('Content-Length: ' . filesize($real));
readfile($real);
