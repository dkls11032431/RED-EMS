<?php

require 'config/db.php';

require('fpdf/fpdf.php');

$pdf = new FPDF();

$pdf->AddPage();

$pdf->SetFont('Arial','B',14);

$pdf->Cell(190,10,'Energy Monitoring Report',0,1,'C');

$pdf->Ln(5);

$pdf->SetFont('Arial','B',10);

$pdf->Cell(60,10,'Parameter',1);
$pdf->Cell(40,10,'Value',1);
$pdf->Cell(70,10,'Reading Time',1);

$pdf->Ln();

$stmt = $conn->query("
SELECT
parameter_name,
value,
reading_time
FROM meter_readings
ORDER BY reading_time DESC
");

$pdf->SetFont('Arial','',10);

while($row = $stmt->fetch(PDO::FETCH_ASSOC))
{
    $pdf->Cell(60,10,$row['parameter_name'],1);
    $pdf->Cell(40,10,$row['value'],1);
    $pdf->Cell(70,10,$row['reading_time'],1);
    $pdf->Ln();
}

$pdf->Output();

?>