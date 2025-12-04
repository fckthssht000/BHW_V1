<?php
require('fpdf.php');

class PDF extends FPDF {
    function Header() {
        $this->SetFont('Times', 'B', 16);
        $this->Cell(0, 10, 'BRGYCare Confirmation Keys', 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Times', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }

    function AddPurokKeys($purok, $keys) {
        $this->AddPage();
        $this->SetFont('Times', 'B', 14);
        $this->Cell(0, 10, "Purok {$purok}", 0, 1);
        $this->SetFont('Times', '', 12);
        
        $this->SetFillColor(200, 200, 200);
        $this->Cell(60, 8, 'Confirmation Key', 1, 0, 'C', 1);
        $this->Cell(30, 8, 'Status', 1, 1, 'C', 1);
        
        foreach ($keys as $key => $data) {
            $this->Cell(60, 8, $key, 1, 0);
            $this->Cell(30, 8, $data['used'] ? 'Used' : 'Unused', 1, 1);
        }
    }
}

$pdf = new PDF();
$pdf->SetTitle('BRGYCare Confirmation Keys');
$puroks = ['P1', 'P2', 'P3', 'P4A', 'P4B', 'P5', 'P6', 'P7'];

foreach ($puroks as $purok) {
    $purok_clean = str_replace(['A', 'B'], '', $purok);
    $filename = "keys/{$purok_clean}_confirmation_key.json";
    if (file_exists($filename)) {
        $keys = json_decode(file_get_contents($filename), true);
        if ($keys) {
            $pdf->AddPurokKeys($purok, $keys);
        }
    }
}

$pdf->Output('F', 'BRGYCare_confirmation_keys.pdf');
echo "PDF generated successfully as BRGYCare_confirmation_keys.pdf\n";
?>