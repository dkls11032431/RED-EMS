<?php

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require 'config/db.php';
require 'includes/auth_helpers.php';
require 'fpdf/fpdf.php';

$meterId = isset($_GET['meter_id']) ? (int) $_GET['meter_id'] : 0;
if ($meterId > 0 && !meter_belongs_to_user($conn, $meterId)) {
    http_response_code(403);
    exit('That meter is outside your account.');
}

$where = [];
$params = [];
if ($meterId > 0) {
    $where[] = 'meter_id = ?';
    $params[] = $meterId;
}
$year = $_GET['year'] ?? '';
$month = $_GET['month'] ?? '';
if (preg_match('/^\d{4}$/', (string) $year) && preg_match('/^\d{1,2}$/', (string) $month)) {
    $where[] = 'YEAR(reading_time) = ? AND MONTH(reading_time) = ?';
    $params[] = (int) $year;
    $params[] = (int) $month;
}
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $to)) {
    $where[] = 'DATE(reading_time) BETWEEN ? AND ?';
    $params[] = $from;
    $params[] = $to;
}

$sql = 'SELECT parameter_name, value, reading_time FROM meter_readings';
if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY reading_time DESC LIMIT 250';
$stmt = $conn->prepare($sql);
$stmt->execute($params);

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(190, 10, 'RED SURYAN EMS', 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(190, 6, 'Stored readings' . ($meterId > 0 ? ' for meter ' . $meterId : ''), 0, 1, 'C');
$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(70, 8, 'Parameter', 1);
$pdf->Cell(40, 8, 'Value', 1);
$pdf->Cell(70, 8, 'Reading time', 1);
$pdf->Ln();
$pdf->SetFont('Arial', '', 9);
$count = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $pdf->Cell(70, 7, substr((string) $row['parameter_name'], 0, 32), 1);
    $pdf->Cell(40, 7, (string) $row['value'], 1);
    $pdf->Cell(70, 7, (string) $row['reading_time'], 1);
    $pdf->Ln();
    $count++;
}
if ($count === 0) {
    $pdf->Ln(4);
    $pdf->Cell(180, 8, 'No readings stored for this filter.', 0, 1);
}
$pdf->Output('I', 'energy-report.pdf');
