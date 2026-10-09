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
        $this->MultiCell(0,9,pdf_text($text),0,'L');
        $this->Ln(4);
    }
    public function Body(string $text): void {
        $this->SetFont('Helvetica','',11);
        $this->SetTextColor(55,65,81);
        $this->MultiCell(0,6,pdf_text($text));
    }
}
/** Customer report uses the existing FPDF library, fonts, navy/red brand and footer. */
class CustomerReportPDF extends AssessmentPDF {
    public function Header() {
        $this->SetFillColor(15,23,43);$this->Rect(0,0,210,16,'F');
        $this->SetFillColor(225,32,27);$this->Rect(0,0,210,2,'F');
        $this->SetXY(18,5);$this->SetFont('Helvetica','B',8);$this->SetTextColor(255,255,255);
        $this->Cell(0,6,'MY PHYSIO SAATHI | CLINIC WORKFLOW REPORT');$this->SetXY(18,26);
    }
    public function Section(string $label,string $title): void {
        $this->SetTextColor(183,25,22);$this->SetFont('Helvetica','B',9);$this->MultiCell(0,5,pdf_text(strtoupper($label)));$this->Ln(2);$this->Heading($title);
    }
    public function Panel(string $title,string $text,bool $attention=false,bool $compact=false): void {
        $height=($this->Lines($title,true,168)+$this->Lines($text,false,168))*5.2+($compact?6:10);
        if($this->GetY()+$height>273)$this->AddPage();
        $y=$this->GetY();$this->SetFillColor($attention?255:239,$attention?244:245,$attention?242:250);$this->Rect(18,$y,174,$height,'F');
        $this->SetFillColor($attention?225:15,$attention?32:23,$attention?27:43);$this->Rect(18,$y,1.2,$height,'F');
        $this->SetY($y+3);$this->SetLeftMargin(21);$this->SetRightMargin(21);$this->SetX(21);$this->Paragraph($title,true);$this->Paragraph($text);$this->SetLeftMargin(18);$this->SetRightMargin(18);$this->SetXY(18,$y+$height+($compact?2:4));
    }
    public function Tiles(array $tiles): void {
        $width=174/count($tiles);$y=$this->GetY();
        foreach($tiles as $i=>$tile){$x=18+$i*$width;
            $this->SetFillColor(239,245,250);$this->Rect($x,$y,$width-3,29,'F');
            $this->SetXY($x+3,$y+3);$this->SetFont('Helvetica','B',8);$this->SetTextColor(55,65,81);$this->MultiCell($width-9,4,pdf_text($tile[0]));
            $this->SetXY($x+3,$y+13);$this->SetFont('Helvetica','B',14);$this->SetTextColor(15,23,43);$this->MultiCell($width-9,6,pdf_text($tile[1]));
        }$this->SetXY(18,$y+34);
    }
    public function Keep(float $height): void {if($this->GetY()+$height>273)$this->AddPage();}
    public function Table(array $headers,array $rows,array $widths,string $continuation): void {
        $draw=function(array $cells,bool $head=false,int $index=0)use($widths){
            $height=0;foreach($cells as $i=>$cell)$height=max($height,$this->Lines((string)$cell,$head,$widths[$i]-2,9)*4.6+5);
            $y=$this->GetY();$x=18;
            foreach($cells as $i=>$cell){
                $this->SetFillColor($head?15:($index%2?248:239),$head?23:($index%2?250:245),$head?43:($index%2?252:250));
                $this->SetDrawColor(218,226,235);$this->Rect($x,$y,$widths[$i],$height,'DF');
                $this->SetXY($x+1,$y+2);$this->SetFont('Helvetica',$head?'B':'',9);$this->SetTextColor($head?255:55,$head?255:65,$head?255:81);
                $this->MultiCell($widths[$i]-2,4.6,pdf_text((string)$cell),0,'L');$x+=$widths[$i];
            }$this->SetXY(18,$y+$height);return $height;
        };
        $headerHeight=0;foreach($headers as $i=>$cell)$headerHeight=max($headerHeight,$this->Lines($cell,true,$widths[$i]-2,9)*4.6+5);
        $firstHeight=0;if($rows)foreach($rows[0] as $i=>$cell)$firstHeight=max($firstHeight,$this->Lines((string)$cell,false,$widths[$i]-2,9)*4.6+5);
        if($this->GetY()+$headerHeight+$firstHeight>273){$this->AddPage();$this->Paragraph($continuation.' / continued',true);}
        $draw($headers,true);
        foreach($rows as $index=>$row){$height=0;foreach($row as $i=>$cell)$height=max($height,$this->Lines((string)$cell,false,$widths[$i]-2,9)*4.6+5);
            if($this->GetY()+$height>273){$this->AddPage();$this->Paragraph($continuation.' / continued',true);$draw($headers,true);}
            $draw($row,false,$index);
        }$this->Ln(5);
    }
    public function Paragraph(string $text,bool $bold=false): void {
        $this->SetFont('Helvetica',$bold?'B':'',10);
        $this->SetTextColor($bold?15:55,$bold?23:65,$bold?43:81);
        $this->MultiCell(0,5.2,pdf_text($text),0,'L');$this->Ln(1.5);
    }
    public function Lines(string $text,bool $bold=false,?float $width=null,float $size=10): int {
        $this->SetFont('Helvetica',$bold?'B':'',$size);
        $s=str_replace("\r",'',pdf_text($text));$cw=$this->CurrentFont['cw'];
        $max=(($width??($this->w-$this->lMargin-$this->rMargin))-2*$this->cMargin)*1000/$this->FontSize;
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
    $pdf->AddPage();$pdf->Ln(12);$pdf->Section('Self-reported assessment',isset($report['domain_plans'])?'Clinic workflow improvement roadmap':'Your clinic workflow report');$pdf->Heading($s['fields']['clinic_name']);
    $pdf->Paragraph('Assessment reference: '.$s['id']);$pdf->Paragraph('Submitted: '.$s['submitted_at']);
    $pdf->Tiles([['STATUS',$report['status']==='Insufficient information'?'Insufficient':($report['status']==='Provisional'?'Provisional':'Self-reported')],['HORIZON',isset($report['domain_plans'])?'90 days':'Assessment'],['ASSESSMENT','25 questions']]);$pdf->Ln(3);
    $pdf->Paragraph('Report status: '.$report['status'],true);
    foreach($labels as $key=>$label)$pdf->Paragraph($label.': '.($s['fields'][$key]??'Not supplied'));
    $pdf->Ln(5);$pdf->Paragraph('Assessment version: '.$report['versions']['assessment'].' | Scoring rules: '.$report['versions']['scoring'].' | Recommendation catalogue: '.$report['versions']['recommendations']);
    if(isset($report['versions']['roadmap']))$pdf->Paragraph('Roadmap measures: '.$report['versions']['roadmap'].' | Presentation: '.$report['versions']['presentation']);
    $pdf->Ln(5);$pdf->Paragraph($report['disclaimer']);
    $pdf->Paragraph('Scoring and eligibility rules are internal product rules, not validated industry thresholds. Paper-based and digital processes are assessed on the same basis.');
    $scaleLabels=[];foreach($report['rating_scale'] as $score=>$scale)$scaleLabels[]=$score.' '.$scale['level'];$pdf->Paragraph('Ratings: '.implode('; ',$scaleLabels).'. Not sure and Not applicable are not numeric scores.');
    $pdf->AddPage();$pdf->Section('Executive summary','Where to focus first');
    $pdf->Tiles([['NUMERIC AVERAGE',$report['overall_eligible']?report_number($report['overall_average']).' / 5':'Not available'],['RESPONSE COVERAGE',$report['coverage']===null?'Not available':report_number($report['coverage']).'%'],['REPORT STATUS',$report['status']==='Insufficient information'?'Insufficient':($report['status']==='Provisional'?'Provisional':'Self-reported')]]);
    $pdf->Paragraph('Report status: '.$report['status'],true);
    $pdf->Paragraph(($report['counts']['unknown']+$report['counts']['na'])>0?'Unknown, missing or unreviewed N/A responses remain. Confirm these before relying on the assessment.':'All questions have numeric responses. These remain self-reported, rather than independently verified.');
    if(!$report['overall_eligible'])$pdf->Paragraph('An overall average is not displayed because coverage or domain requirements are not met.',true);
    $pdf->Paragraph($report['overall_interpretation']);
    $pdf->Paragraph($report['coverage_label'].': '.($report['coverage']===null?'Not available (all responses N/A)':report_number($report['coverage']).'%'));
    $c=$report['counts'];$pdf->Paragraph('Numeric: '.$c['numeric'].' | Unknown: '.$c['unknown'].' | N/A: '.$c['na'].' | Missing: '.$c['missing']);
    $pdf->Paragraph('The average is the sum of numeric responses divided by their count. Coverage measures numeric responses among the questions not marked N/A. Interpretations use unrounded averages.');
    foreach(['strongest_domain'=>'Strongest scored domain','weakest_domain'=>'Weakest scored domain'] as $key=>$label){$d=$report[$key];$pdf->Paragraph($label.': '.($d?$d['code'].' / '.$d['title'].' ('.report_number($d['average']).' / 5)':'Not available with sufficient numeric responses'));}
    $pdf->Ln(4);$pdf->Paragraph('Numeric priorities to consider',true);
    if(!$report['priority_findings'])$pdf->Paragraph('No numeric gaps scoring 1, 2 or 3 were reported. Confirmation tasks may still be needed.');
    foreach($report['priority_findings'] as $f)$pdf->Panel($f['id'].' / '.$f['title'].($f['control_gap']?' / IMPORTANT CONTROL GAP':''),'Submitted response: '.$f['response_label'].'. '.$f['category'].'. Possible consequence: '.$f['consequence'],$f['control_gap']);
    $pdf->AddPage();$pdf->Section('Baseline','Domain scorecard');
    foreach($report['domains'] as $d)$pdf->Block([$d['code'].' / '.$d['title'],'Numeric responses: '.$d['counts']['numeric'].' / 5 | Average: '.report_number($d['average']).($d['average']!==null?' / 5':''),'Interpretation: '.$d['interpretation'],'Unresolved responses: '.$d['unresolved_count'].' (Unknown: '.$d['counts']['unknown'].'; N/A: '.$d['counts']['na'].'; Missing: '.$d['counts']['missing'].')']);
    $pdf->Paragraph('A domain needs at least three numeric responses for an interpretation. Unresolved responses include all respondent-selected N/A answers pending applicability review. A high overall average does not remove an important control gap.');
    if(isset($report['domain_plans']))foreach($report['domains'] as $d)report_domain_overview($pdf,$report,$d);
    foreach($report['domains'] as $d){
        $heading='Domain '.$d['code'].' / '.$d['title'];$pdf->AddPage();$pdf->Section('Detailed observation register',$heading);
        $pdf->Paragraph('Average: '.report_number($d['average']).($d['average']!==null?' / 5':'').' | '.$d['interpretation']);
        $domainNote=$report['submission']['domain_notes'][$d['code']];if(trim($domainNote)!=='')$pdf->Block(['Submitted domain explanation (not independently verified)',$domainNote,'This domain-level explanation is not assumed to support every question.'],$heading);
        foreach($report['findings'] as $f){if($f['domain']!==$d['code'])continue;
            $parts=[$f['id'].' / '.$f['title'].($f['control_gap']?' / IMPORTANT CONTROL GAP':''),'Question: '.$f['question'],(is_int($f['response'])?'The clinic rated this process ':'Submitted response: ').$f['response_label'].'. '.$f['category'].'.'];
            if($f['evidence_label']!==null)$parts[]='Evidence: '.$f['evidence_label'];
            if(is_int($f['response'])){
                $parts[]=$f['response']===5?'The response indicates a strong reported process. It has not been independently verified.':'The response indicates: '.$f['category'].'.';
                $parts[]='This may lead to: '.$f['consequence'];
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
    $actionRows=[];foreach($report['action_plan'] as $i=>$f)$actionRows[]=[($i+1).'. '.$f['id'].' / '.$f['title'].' | '.$f['response_label'],$f['recommendation'],$f['role'],$f['timing'],$f['completion_check']];
    if($actionRows)$pdf->Table(['Priority / basis','Suggested action','Suggested owner','Timing','Completion check'],$actionRows,[30,62,32,22,28],'Suggested 30-day action plan');
    if(isset($report['kpi_register']))report_roadmap_sections($pdf,$report);
    $pdf->AddPage();$pdf->Heading('Optional next step');$pdf->Paragraph('Discuss your report');$pdf->Paragraph('If useful, discuss the findings or request a demonstration of the relevant current My Physio Saathi capabilities. The suggestions in this report do not depend on buying the system.');
    $pdf->Paragraph('Tejas P Mehta | Call: +91 98256 47083',true);$pdf->Paragraph('Website: https://www.myphysiosaathi.in/');$pdf->Ln(8);$pdf->Paragraph($report['disclaimer']);
    return $pdf->Output('S');
}

function report_domain_overview(CustomerReportPDF $pdf,array $report,array $domain): void {
    $code=$domain['code'];$plan=$report['domain_plans'][$code];$heading='Domain '.$code.' / '.$domain['title'];
    $pdf->AddPage();$pdf->Section('Domain improvement plan',$heading);
    $pdf->Paragraph('Average: '.report_number($domain['average']).($domain['average']!==null?' / 5':'').' | Numeric: '.$domain['counts']['numeric'].' of 5 | Unresolved: '.$domain['unresolved_count'],true);
    $pdf->Paragraph($domain['interpretation']);$pdf->Paragraph('Priority observations and confirmations',true);
    foreach($plan['observations'] as $f){
        $label=$f['control_gap']?'IMPORTANT CONTROL GAP':(is_int($f['response'])?$f['category']:'Confirmation required, not a demonstrated failure');
        $pdf->Panel($f['id'].' / '.$f['title'],'Submitted response: '.$f['response_label'].'. '.$label.'.'.($f['evidence_label']!==null?' Evidence: '.$f['evidence_label'].'.':''),$f['control_gap'],true);
    }
    $pdf->Paragraph('Suggested delivery plan',true);$rows=[];
    foreach($plan['observations'] as $f)$rows[]=[$f['id'],$f['recommendation'],$f['role'],$f['timing']];
    $pdf->Table(['ID','Action / workable process','Suggested owner','Suggested timing'],$rows,[12,86,42,34],$heading.' / Delivery plan');
    $pdf->Paragraph('Suggested KRAs and KPIs',true);$rows=[];
    foreach($plan['metrics'] as $m)$rows[]=[$m['id'].' / '.$m['kra'],$m['kpi'],($m['mode']==='Confirm before planning'?'Verify process and baseline before agreeing a target.':$m['target_for_report'])];
    $pdf->Table(['KRA / area of responsibility','KPI / measure','Proposed target, subject to review'],$rows,[48,54,72],$heading.' / KRAs and KPIs');
    $pdf->Paragraph('Suggested measures only. Establish baselines and agree targets before use.');
}
function report_roadmap_sections(CustomerReportPDF $pdf,array $report): void {
    $pdf->AddPage();$pdf->Section('Management scorecard','Full KRA and KPI register');
    $pdf->Panel('How to use this register','KRA means the area to improve. High reported scores can instead call for maintenance. KPI means the measure used to check progress. Suggested targets are planning proposals. Ratings out of 5 are self-reported process scores, not measured KPI baselines.');
    $pdf->Paragraph('For percentage measures, retain the numerator, denominator and review period. For zero opportunities, report No eligible cases, not 0% or 100%. If no relevant events occur, agree a documented process test where appropriate. Keep patient-level evidence private.');
    foreach($report['domains'] as $domain){
        $pdf->Keep(65);$pdf->Paragraph('Domain '.$domain['code'].' / '.$domain['title'],true);$rows=[];
        foreach($report['kpi_register'] as $m)if($m['domain']===$domain['code'])$rows[]=[$m['id']."\n".$m['mode'],$m['kra']."\nKPI: ".$m['kpi']."\nMeasure: ".$m['formula'],$m['target_for_report']."\nBaseline to be measured.",$m['role']."\nEvidence: ".$m['evidence']];
        $pdf->Table(['ID / purpose','KRA, KPI and measurement','Proposed target / baseline','Suggested owner / evidence'],$rows,[20,60,49,45],'Full KRA and KPI register');
    }
    $pdf->Paragraph('Suggested cadence: weekly checks during the first 30 days, then agree a suitable review frequency. Record the actual baseline and clinic-agreed target separately from these proposals.');
    $pdf->AddPage();$pdf->Section('First 90 days','Suggested 90-day roadmap');
    $pdf->Paragraph('The first 30 days focus on the priority actions already listed. The next phases support consistent use and evidence review. Urgent control gaps should be addressed within their suggested early timings, rather than deferred until Day 90.');
    foreach($report['roadmap_90'] as $phase)$pdf->Panel($phase['phase'].' / '.$phase['theme'],$phase['action']."\nEvidence to retain: ".$phase['evidence']);
    $pdf->Panel('Proposed 90-day outcome','If the agreed actions are carried out, the clinic should have clearer responsibilities, checked processes and an evidence-based review of the selected priorities. This is an intended outcome, not a reported achievement or a guaranteed score improvement.');
    $pdf->AddPage();$pdf->Section('Review template','Day-90 outcome review');
    $pdf->Paragraph('Complete this section at the later review. Actual results are intentionally unfilled. Verify applicability and use the same measurement definition and review period for baseline and follow-up.');
    $rows=[];foreach($report['day90_review'] as $row)$rows[]=[$row['id'].' / '.$row['outcome'],"Baseline to be measured\nAgreed target: To agree\nDay-90 actual: Not yet measured",$row['evidence']."\nStatus: ".$row['status']];
    if(!$rows)$pdf->Paragraph('No supported improvement or confirmation actions were identified. Agree maintenance checks and use the KPI register to select appropriate review measures.');
    else $pdf->Table(['Selected outcome','Baseline / agreed target / actual','Completion evidence and review status'],$rows,[48,52,74],'Day-90 outcome review');
    $pdf->Panel('Review decision','For each selected action, record completed, in progress, not started, or inapplicable after review. Record the evidence, reviewer and next step. Repeat the assessment if useful, but do not treat a higher self-reported score as independent verification.');
}
