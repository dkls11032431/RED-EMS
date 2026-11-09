<?php

session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

require 'config/db.php';
require 'fpdf/fpdf.php';

$year  = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

$stmt = $conn->prepare("
    SELECT
        DATE(reading_time) AS report_date,

        MAX(
            CASE
                WHEN parameter_name='EB-KWH'
                THEN value
            END
        ) AS eb_kwh,

        MAX(
            CASE
                WHEN parameter_name='DG-KWH'
                THEN value
            END
        ) AS dg_kwh

    FROM meter_readings

    WHERE YEAR(reading_time)=?
    AND MONTH(reading_time)=?

    GROUP BY DATE(reading_time)

    ORDER BY report_date DESC
");

$stmt->execute([$year, $month]);

$pdf = new FPDF();

$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(190, 10, 'Monthly Energy Report', 0, 1, 'C');

$pdf->SetFont('Arial', '', 12);
$pdf->Cell(190, 8, "Month: $month/$year", 0, 1, 'C');

$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 10);

$pdf->Cell(40, 10, 'Date', 1);
$pdf->Cell(50, 10, 'EB Energy', 1);
$pdf->Cell(50, 10, 'DG Energy', 1);
$pdf->Cell(50, 10, 'Total Energy', 1);

$pdf->Ln();

$pdf->SetFont('Arial', '', 10);

foreach ($stmt as $row) {

    $eb = $row['eb_kwh'] ?? 0;
    $dg = $row['dg_kwh'] ?? 0;

    $pdf->Cell(40, 10, $row['report_date'], 1);
    $pdf->Cell(50, 10, number_format($eb, 3), 1);
    $pdf->Cell(50, 10, number_format($dg, 3), 1);
    $pdf->Cell(50, 10, number_format($eb + $dg, 3), 1);

    $pdf->Ln();
}

$pdf->Output(
    'D',
    'Monthly_Energy_Report_'.$year.'_'.$month.'.pdf'
);