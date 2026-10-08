<?php
require_once __DIR__ . '/lib/fpdf/fpdf.php';
function pdf_text(string $text): string {
    // Standard FPDF fonts use Windows-1252. Keep original UTF-8 in the email body.
    $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
    return $converted !== false ? $converted : '[Text unavailable]';
}
class AssessmentPDF extends FPDF {
    public function Header() {
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(15,23,43);
        $this->Cell(0,8,'MY PHYSIO SAATHI',0,1);
        $this->SetDrawColor(225,32,27);
        $this->Line(18,29,192,29);
        $this->Ln(8);
    }
    public function Footer() {
        $this->SetY(-18);
        $this->SetTextColor(69,85,108);
        $this->SetFont('Helvetica','',9);
        $this->Cell(145,7,'Tejas P Mehta | Call: +91 98256 47083');
        $this->Cell(29,7,$this->PageNo().'/{nb}',0,0,'R');
    }
    public function Heading(string $text): void {
        $this->SetTextColor(15,23,43);
        $this->SetFont('Helvetica','B',19);
        $this->MultiCell(0,9,pdf_text($text));
        $this->Ln(4);
    }
    public function Body(string $text): void {
        $this->SetFont('Helvetica','',11);
        $this->SetTextColor(55,65,81);
        $this->MultiCell(0,6,pdf_text($text));
    }
}
function assessment_pdf(array $submission, array $domains, array $labels, array $ratings): string {
    $pdf=new AssessmentPDF();
    $pdf->SetMargins(18,18,18);$pdf->SetAutoPageBreak(true,24);$pdf->AliasNbPages();
    $pdf->SetTitle('My Physio Saathi - Completed Clinic Assessment');
    $pdf->SetAuthor('My Physio Saathi');
    $pdf->AddPage();$pdf->Heading('Clinic workflow assessment');
    $pdf->Body('Submitted answers | 25 questions across 5 domains');$pdf->Ln(4);
    $pdf->Body('Reference: '.$submission['id'].' | '.$submission['submitted_at']);$pdf->Ln(6);
    foreach($labels as $key=>$label){
        $pdf->SetFont('Helvetica','B',10);$pdf->SetTextColor(15,23,43);$pdf->MultiCell(0,6,pdf_text($label));
        $pdf->Body((string)$submission['fields'][$key]);$pdf->Ln(3);
    }
    $pdf->Ln(4);$pdf->Body('This document records the clinic\'s self-reported answers. It is not a clinical, security or compliance certification. No assessment score has been assigned.');
    $pdf->Ln(4);$pdf->Body('Rating scale: 1 Very low; 2 Low; 3 Moderate; 4 High; 5 Very high. Not sure and N/A are separate responses, not numeric scores.');
    foreach($domains as $domain){
        $pdf->AddPage();$pdf->Heading('Domain '.$domain['code'].' / '.$domain['title']);$pdf->Body($domain['description']);$pdf->Ln(5);
        foreach($domain['questions'] as $id=>$question){
            if($pdf->GetY()>225)$pdf->AddPage();
            $pdf->SetTextColor(15,23,43);$pdf->SetFont('Helvetica','B',11);$pdf->MultiCell(0,6,pdf_text($id.'. '.$question));
            $pdf->SetFillColor(248,250,252);$pdf->SetTextColor(183,25,22);$pdf->SetFont('Helvetica','B',10);
            $pdf->Cell(0,9,pdf_text('Answer: '.$ratings[$submission['answers'][$id]]),0,1,'L',true);
            if($submission['notes'][$id]!==''){$pdf->Body('Notes: '.$submission['notes'][$id]);}
            $pdf->Ln(6);
        }
    }
    return $pdf->Output('S');
}
