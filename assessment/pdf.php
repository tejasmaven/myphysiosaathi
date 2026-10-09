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

/** Customer report uses the existing FPDF library, fonts, navy/red brand and footer. */
class CustomerReportPDF extends AssessmentPDF {
    public function Paragraph(string $text,bool $bold=false): void {
        $this->SetFont('Helvetica',$bold?'B':'',10);
        $this->SetTextColor($bold?15:55,$bold?23:65,$bold?43:81);
        $this->MultiCell(0,5.2,pdf_text($text));$this->Ln(1.5);
    }
    public function Lines(string $text,bool $bold=false): int {
        $this->SetFont('Helvetica',$bold?'B':'',10);
        $s=str_replace("\r",'',pdf_text($text));$cw=$this->CurrentFont['cw'];
        $max=($this->w-$this->lMargin-$this->rMargin-2*$this->cMargin)*1000/$this->FontSize;
        $n=strlen($s);if($n && $s[$n-1]==="\n")$n--;
        $sep=-1;$i=0;$j=0;$length=0;$lines=1;
        while($i<$n){$c=$s[$i];if($c==="\n"){$i++;$sep=-1;$j=$i;$length=0;$lines++;continue;}
            if($c===' ')$sep=$i;$length+=$cw[$c]??0;
            if($length>$max){if($sep===-1){if($i===$j)$i++;}else $i=$sep+1;$sep=-1;$j=$i;$length=0;$lines++;}else $i++;
        }return $lines;
    }
    public function Block(array $paragraphs,string $continuation=''): void {
        $height=5;foreach($paragraphs as $i=>$p)$height+=$this->Lines($p,$i===0)*5.2+1.5;
        if($this->GetY()+min($height,210)>$this->h-$this->bMargin){$this->AddPage();if($continuation!=='')$this->Paragraph($continuation.' / continued',true);}
        $this->SetDrawColor(219,226,235);$this->Line(18,$this->GetY(),192,$this->GetY());$this->Ln(3);
        foreach($paragraphs as $i=>$p)$this->Paragraph($p,$i===0);$this->Ln(2);
    }
}
function report_number($value): string {return $value===null?'Not available':number_format($value,2);}
function customer_report_pdf(array $report,array $labels): string {
    $s=$report['submission'];$pdf=new CustomerReportPDF();$pdf->SetMargins(18,18,18);$pdf->SetAutoPageBreak(true,24);$pdf->AliasNbPages();
    $pdf->SetTitle('My Physio Saathi - Clinic Workflow Report');$pdf->SetAuthor('My Physio Saathi');
    $pdf->AddPage();$pdf->Ln(15);$pdf->Heading('Your clinic workflow report');$pdf->Heading($s['fields']['clinic_name']);
    $pdf->Paragraph('Assessment reference: '.$s['id']);$pdf->Paragraph('Submitted: '.$s['submitted_at']);
    $pdf->Paragraph('Report status: '.$report['status'],true);$pdf->Ln(5);
    foreach($labels as $key=>$label)$pdf->Paragraph($label.': '.($s['fields'][$key]??'Not supplied'));
    $pdf->Ln(5);$pdf->Paragraph('Assessment version: '.$report['versions']['assessment'].' | Scoring rules: '.$report['versions']['scoring'].' | Recommendation catalogue: '.$report['versions']['recommendations']);
    $pdf->Ln(5);$pdf->Paragraph($report['disclaimer']);
    $pdf->Paragraph('Ratings: 1 Very low; 2 Low; 3 Moderate; 4 High; 5 Very high. Unknown, missing and N/A responses are not numeric scores.');
    $pdf->AddPage();$pdf->Heading('Executive summary');
    $pdf->Paragraph('Report status: '.$report['status'],true);
    $pdf->Paragraph($report['status']==='Provisional'?'Unknown, missing or unreviewed N/A responses remain. Confirm these before relying on the assessment.':'All questions have numeric responses. These remain self-reported, rather than independently verified.');
    if($report['overall_average']!==null)$pdf->Paragraph(($report['overall_eligible']?'Overall average: ':'Available-response average: ').report_number($report['overall_average']).' / 5',true);
    else $pdf->Paragraph('Overall average: Not available (no numeric responses)',true);
    $pdf->Paragraph($report['overall_interpretation']);
    $pdf->Paragraph($report['coverage_label'].': '.($report['coverage']===null?'Not available (all responses N/A)':report_number($report['coverage']).'%'));
    $c=$report['counts'];$pdf->Paragraph('Numeric: '.$c['numeric'].' | Unknown: '.$c['unknown'].' | N/A: '.$c['na'].' | Missing: '.$c['missing']);
    $pdf->Paragraph('The average is the sum of numeric responses divided by their count. Coverage measures numeric responses among the questions not marked N/A. Interpretations use unrounded averages.');
    foreach(['strongest_domain'=>'Strongest scored domain','weakest_domain'=>'Weakest scored domain'] as $key=>$label){$d=$report[$key];$pdf->Paragraph($label.': '.($d?$d['code'].' / '.$d['title'].' ('.report_number($d['average']).' / 5)':'Not available with sufficient numeric responses'));}
    $pdf->Ln(4);$pdf->Paragraph('Numeric priorities to consider',true);
    if(!$report['priority_findings'])$pdf->Paragraph('No numeric gaps scoring 1, 2 or 3 were reported. Confirmation tasks may still be needed.');
    foreach($report['priority_findings'] as $f)$pdf->Block([$f['id'].' / '.$f['title'].($f['control_gap']?' / IMPORTANT CONTROL GAP':''),'Question: '.$f['question'],'Submitted response: '.$f['response_label'].'. '.$f['category'].'.','The response indicates a process to improve. Possible consequence: '.$f['consequence'],'Recommendation: '.$f['recommendation']],'Executive summary');
    $pdf->AddPage();$pdf->Heading('Domain scorecard');
    foreach($report['domains'] as $d)$pdf->Block([$d['code'].' / '.$d['title'],'Numeric responses: '.$d['counts']['numeric'].' / 5 | Average: '.report_number($d['average']).($d['average']!==null?' / 5':''),'Interpretation: '.$d['interpretation'],'Unresolved responses: '.$d['unresolved_count'].' (Unknown: '.$d['counts']['unknown'].'; N/A: '.$d['counts']['na'].'; Missing: '.$d['counts']['missing'].')']);
    $pdf->Paragraph('A domain needs at least three numeric responses for an interpretation. Unresolved responses include all respondent-selected N/A answers pending applicability review. A high overall average does not remove an important control gap.');
    foreach($report['domains'] as $d){
        $heading='Domain '.$d['code'].' / '.$d['title'];$pdf->AddPage();$pdf->Heading($heading);
        $pdf->Paragraph('Average: '.report_number($d['average']).($d['average']!==null?' / 5':'').' | '.$d['interpretation']);
        foreach($report['findings'] as $f){if($f['domain']!==$d['code'])continue;
            $parts=[$f['id'].' / '.$f['title'].($f['control_gap']?' / IMPORTANT CONTROL GAP':''),'Question: '.$f['question'],'Submitted response: '.$f['response_label'].'. '.$f['category'].'.'];
            if($f['note']!=='')$parts[]='Submitted note: '.$f['note'];
            if(is_int($f['response'])){
                $parts[]=$f['response']===5?'The response indicates a strong reported process. It has not been independently verified.':'The response indicates: '.$f['category'].'.';
                $parts[]='Possible consequence if the process is inconsistent: '.$f['consequence'];
            }else $parts[]='This response does not establish a process failure. Confirmation is required.';
            $parts[]='Recommendation: '.$f['recommendation'];
            if(isset($f['software_note']))$parts[]='Relevant verified capability: '.$f['software_note'];
            $parts[]='Suggested role: '.$f['role'].' | Suggested timing: '.$f['timing'];
            $parts[]='Completion check: '.$f['completion_check'];$pdf->Block($parts,$heading);
        }
    }
    $pdf->AddPage();$pdf->Heading('Items to confirm');$pdf->Paragraph('Unknown and missing answers are questions to check, not demonstrated failures. Important control questions are listed first.');
    if(!$report['items_to_confirm'])$pdf->Paragraph('No unknown or missing responses.');
    foreach($report['items_to_confirm'] as $f)$pdf->Block([$f['id'].' / '.$f['title'].($f['important_control']?' / Important control to confirm':''),'Question: '.$f['question'],'Submitted response: '.$f['response_label'],'Verification task: '.$f['recommendation']],'Items to confirm');
    $pdf->Paragraph('N/A applicability review',true);
    if(!$report['applicability_review'])$pdf->Paragraph('No N/A responses.');
    foreach($report['applicability_review'] as $f)$pdf->Block([$f['id'].' / '.$f['title'],'Question: '.$f['question'],'Submitted response: '.$f['response_label'],'Applicability review: '.$f['recommendation']],'Applicability review');
    $pdf->AddPage();$pdf->Heading('Suggested 30-day action plan');$pdf->Paragraph('These roles and timings are suggestions relative to report delivery, not commitments made by the clinic. The actions remain useful without purchasing software.');
    if(!$report['action_plan'])$pdf->Paragraph('No supported improvement or confirmation actions were identified. Continue periodic checks of the processes you reported as reliable.');
    foreach($report['action_plan'] as $i=>$f)$pdf->Block([($i+1).'. '.$f['id'].' / '.$f['title'],'Basis: '.$f['response_label'],'Action: '.$f['recommendation'],'Suggested responsible role: '.$f['role'],'Suggested timing: '.$f['timing'],'Completion check: '.$f['completion_check']],'Suggested 30-day action plan');
    $pdf->AddPage();$pdf->Heading('Optional next step');$pdf->Paragraph('Discuss your report');$pdf->Paragraph('If useful, discuss the findings or request a demonstration of the relevant current My Physio Saathi capabilities. The suggestions in this report do not depend on buying the system.');
    $pdf->Paragraph('Tejas P Mehta | Call: +91 98256 47083',true);$pdf->Paragraph('Website: https://www.myphysiosaathi.in/');$pdf->Ln(8);$pdf->Paragraph($report['disclaimer']);
    return $pdf->Output('S');
}
