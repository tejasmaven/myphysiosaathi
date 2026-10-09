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
        $this->SetTextColor(183,25,22);$this->SetFont('Helvetica','B',9);$this->MultiCell(0,5,pdf_text(strtoupper($label)));$this->Ln(2);$this->SetFont('Helvetica','B',16);$this->SetTextColor(15,23,43);$this->MultiCell(0,7,pdf_text($title),0,'L');$this->Ln(3);
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
                $this->SetDrawColor(218,226,235);if(isset($attention) && $attention)$this->SetFillColor(255,244,240);$this->Rect($x,$y,$widths[$i],$height,'DF');
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
    public function Small(string $text,bool $bold=false): void {
        $this->SetFont('Helvetica',$bold?'B':'',9);$this->SetTextColor($bold?15:55,$bold?23:65,$bold?43:81);
        $this->MultiCell(0,4.1,pdf_text($text),0,'L');$this->Ln(1.2);
        if($this->GetY()>273)throw new RuntimeException('Compact report page overflow.');
    }
    public function Profile(array $rows): void {
        foreach($rows as $index=>[$label,$value]){
            $cap=[1,1,3,3,3,2,1,4,2][$index]??3;
            if($this->Lines($value,false,111,9)>$cap){
                preg_match_all('/./us',$value,$chars);$parts=$chars[0];
                do{array_pop($parts);$value=rtrim(implode('',$parts)).' [excerpt]';}while($parts && $this->Lines($value,false,111,9)>$cap);
            }
            $height=max($this->Lines($label,true,51,9),$this->Lines($value,false,111,9))*4.4+6;$y=$this->GetY();
            $this->SetFillColor(229,237,246);$this->Rect(18,$y,57,$height,'F');$this->SetFillColor(248,250,252);$this->Rect(75,$y,117,$height,'F');
            $this->SetDrawColor(216,226,237);$this->Rect(18,$y,174,$height);
            $this->SetXY(21,$y+3);$this->SetFont('Helvetica','B',9);$this->SetTextColor(15,23,43);$this->MultiCell(51,4.4,pdf_text($label),0,'L');
            $this->SetXY(78,$y+3);$this->SetFont('Helvetica','',9);$this->SetTextColor(55,65,81);$this->MultiCell(111,4.4,pdf_text($value),0,'L');$this->SetXY(18,$y+$height);
        }
    }
    public function CompactHeight(array $rows,array $widths,float $size,float $line): float {
        $height=0;foreach($rows as $row){$lines=1;foreach($row as $i=>$cell)$lines=max($lines,$this->Lines($cell,false,$widths[$i]-5,$size));$height+=$lines*$line+4;}return $height;
    }
    public function CompactTable(array $headers,array $rows,array $widths,float $size=9,float $line=4): void {
        $draw=function(array $cells,bool $head=false,int $index=0)use($widths,$size,$line){
            $attention=!$head && strpos($cells[0],'IMPORTANT CONTROL GAP')!==false;
            $lines=1;foreach($cells as $i=>$cell)$lines=max($lines,$this->Lines($cell,$head,$widths[$i]-5,$size));$height=$lines*$line+4;$y=$this->GetY();
            if($y+$height>273)throw new RuntimeException('Compact table overflow.');$x=18;
            foreach($cells as $i=>$cell){$this->SetFillColor($head?15:($index%2?248:239),$head?23:($index%2?250:245),$head?43:($index%2?252:250));$this->SetDrawColor(218,226,235);if(isset($attention) && $attention)$this->SetFillColor(255,244,240);$this->Rect($x,$y,$widths[$i],$height,'DF');
                $this->SetXY($x+2.5,$y+2);$this->SetFont('Helvetica',$head?'B':'',$size);$this->SetTextColor($head?255:55,$head?255:65,$head?255:81);$this->MultiCell($widths[$i]-5,$line,pdf_text($cell),0,'L');$x+=$widths[$i];}
            $this->SetXY(18,$y+$height);
        };$draw($headers,true);foreach($rows as $i=>$row)$draw($row,false,$i);$this->Ln(2);
    }
    public function FitDomain(array $rows,string $note): void {
        $widths=[74,60,40];$headers=['Observation','Suggested plan / check','KRA / KPI'];
        foreach([[9,4],[9,3.8]] as [$size,$line]){
            $reserve=$this->Lines($note,false,174,9)*4.1+17;
            if($this->GetY()+$this->CompactHeight($rows,$widths,$size,$line)+8.6+$reserve<=273){$this->CompactTable($headers,$rows,$widths,$size,$line);$this->Small($note);return;}
        }
        throw new RuntimeException('Domain content exceeds the compact report layout.');
    }
    public function Block(array $paragraphs,string $continuation=''): void {
        $height=5;foreach($paragraphs as $i=>$p)$height+=$this->Lines($p,$i===0)*5.2+1.5;
        if($this->GetY()+min($height,210)>$this->h-$this->bMargin){$this->AddPage();if($continuation!=='')$this->Paragraph($continuation.' / continued',true);}
        $this->SetDrawColor(219,226,235);$this->Line(18,$this->GetY(),192,$this->GetY());$this->Ln(3);
        foreach($paragraphs as $i=>$p)$this->Paragraph($p,$i===0);$this->Ln(2);
    }
}
function report_number($value): string {return $value===null?'Not available':number_format($value,2);}
/** Short excerpts are labelled in the PDF; the complete submitted text stays in private storage. */
function report_excerpt(string $text,int $limit): string {
    $text=preg_replace('/\s+/u',' ',trim($text));
    preg_match_all('/./us',$text,$chars);
    return count($chars[0])<=$limit?$text:implode('',array_slice($chars[0],0,$limit)).' [excerpt]';
}
function report_short_action(array $finding): string {
    // The first sentence contains the catalogue's question-specific action; score intent is displayed separately.
    $action=preg_split('/(?<=[.!?])\s+/u',$finding['recommendation'],2)[0];
    $intent=[1=>'Establish the routine. ',2=>'Standardise and assign responsibility. ',3=>'Add completion checks. '];
    return (is_int($finding['response'])?($intent[$finding['response']]??''):'').$action;
}
/** Exactly eight pages: profile, summary, five domains, action plan and review. */
function customer_report_pdf(array $report,array $labels): string {
    $s=$report['submission'];$pdf=new CustomerReportPDF();$pdf->SetMargins(18,18,18);$pdf->SetAutoPageBreak(false);$pdf->AliasNbPages();
    $pdf->SetTitle('My Physio Saathi - Clinic Workflow Report');$pdf->SetAuthor('My Physio Saathi');
    $pdf->AddPage();$pdf->Ln(8);$pdf->Section('Your clinic assessment','Clinic workflow improvement roadmap');
    $pdf->Small('A practical review of your reported routines, with priorities and suggested checks.');$pdf->Ln(4);
    $rows=[['Assessment reference',$s['id']],['Submission date',$s['submitted_at']]];
    foreach($labels as $key=>$label)$rows[]=[$label,report_excerpt((string)($s['fields'][$key]??'Not supplied'),$key==='location'?250:254)];
    $pdf->Profile($rows);
    $pdf->Ln(6);$pdf->Panel('Report status: '.$report['status'],$report['disclaimer']);
    if($pdf->GetY()<220){$pdf->Small('Read this report in eight pages',true);
    $pdf->Small('2  Summary, scores and items to confirm
3-7  One page per domain: observations, actions and measures
8  Suggested action plan and 90-day review');}
    $pdf->Ln(3);
    $pdf->Small('Assessment version: '.$report['versions']['assessment'].' | Scoring rules: '.$report['versions']['scoring'].' | Recommendation catalogue: '.$report['versions']['recommendations']);
    $pdf->Small('Roadmap measures: '.$report['versions']['roadmap'].' | Presentation: '.$report['versions']['presentation']);
    $pdf->Small('Long profile fields or explanations may appear as labelled excerpts. Full original text is retained in the private assessment record.');

    $pdf->AddPage();$pdf->Section('Executive summary','Where to focus first');
    $pdf->Tiles([['NUMERIC AVERAGE',$report['overall_eligible']?report_number($report['overall_average']).' / 5':'Not available'],['RESPONSE COVERAGE',$report['coverage']===null?'Not available':report_number($report['coverage']).'%'],['REPORT STATUS',$report['status']==='Insufficient information'?'Insufficient':($report['status']==='Provisional'?'Provisional':'Self-reported')]]);
    $pdf->Small('Report status: '.$report['status'].' | '.$report['overall_interpretation'],true);
    $c=$report['counts'];$pdf->Small('Numeric: '.$c['numeric'].' | Unknown: '.$c['unknown'].' | N/A: '.$c['na'].' | Missing: '.$c['missing']);
    $pdf->Small($report['coverage_label'].'. Average = numeric sum / numeric count. An overall interpretation needs 80% coverage and three numeric answers per domain.');
    $pdf->Ln(2);$rows=[];
    foreach($report['domains'] as $d)$rows[]=[$d['code'].' / '.$d['title'],(string)$d['counts']['numeric'].' / 5',report_number($d['average']),$d['interpretation'],(string)$d['unresolved_count']];
    $pdf->CompactTable(['Domain','Scored','Avg / 5','Interpretation','Open'],$rows,[56,18,22,57,21],9,4);
    foreach(['strongest_domain'=>'Strongest scored domain','weakest_domain'=>'Weakest scored domain'] as $key=>$label){$d=$report[$key];$pdf->Small($label.': '.($d?$d['code'].' / '.$d['title'].' ('.report_number($d['average']).' / 5)':'Not available'));}
    $pdf->Ln(2);$pdf->Small('Numeric priorities',true);
    if(!$report['priority_findings'])$pdf->Small('No numeric gaps scoring 1, 2 or 3 were reported. Confirmation tasks may still be needed.');
    foreach($report['priority_findings'] as $f)$pdf->Small($f['id'].' / '.$f['title'].' | '.$f['response_label'].($f['control_gap']?' | IMPORTANT CONTROL GAP':'').'. '.$f['category'].'.',true);
    $pdf->Ln(2);$pdf->Small('Items to confirm',true);
    $unknown=$report['items_to_confirm'];$controls=array_filter($unknown,fn($f)=>$f['important_control']);$others=array_filter($unknown,fn($f)=>!$f['important_control']);
    $pdf->Small('Unknown / missing, not demonstrated failures. Important controls: '.($controls?implode(', ',array_column($controls,'id')):'None').'. Other items: '.($others?implode(', ',array_column($others,'id')):'None').'.');
    $nas=$report['applicability_review'];$pdf->Small('Respondent-selected N/A for applicability review: '.($nas?implode(', ',array_column($nas,'id')):'None').'. See each domain for the question and task.');
    if(isset($report['findings']['D5']) && $report['findings']['D5']['response']==='na')$pdf->Small('D5: Why is checking that an earlier clinic problem has been corrected considered inapplicable? Record and review the reason.');
    $pdf->Small('Scoring rules are internal product rules, not validated industry thresholds. Paper and digital processes are assessed on the same basis.');
    $pdf->Small('Unresolved counts include unknown, missing and unreviewed N/A. High averages do not remove control gaps. All scores remain self-reported. KRA = area of responsibility. KPI = progress measure.');

    foreach($report['domains'] as $d){
        $pdf->AddPage();$pdf->Section('Domain '.$d['code'].' / observation, plan and measures',$d['title']);
        $pdf->Small('Average: '.report_number($d['average']).($d['average']!==null?' / 5':'').' | Numeric: '.$d['counts']['numeric'].' / 5 | Unresolved: '.$d['unresolved_count'].' | '.$d['interpretation'],true);

        $rows=[];
        foreach($report['findings'] as $f){if($f['domain']!==$d['code'])continue;$m=$report['kpi_register'][$f['id']];
            $observation=$f['id'].' / '.$f['title'].'
'.$f['question'].'
Response: '.$f['response_label'];
            if($f['control_gap'])$observation.='
IMPORTANT CONTROL GAP';
            if(is_int($f['response']) && $f['response']<=3)$observation.='
This may lead to: '.$f['consequence'];
            elseif(!is_int($f['response']))$observation.='
Confirmation required, not a proven failure.';
            else $observation.='
'.$f['evidence_label'];
            $action=report_short_action($f).'
Owner: '.$f['role'].' | '.$f['timing'].'
Check: '.$f['completion_check'];
            // N/A and unknown need the complete verification instruction, never an active numeric target.
            if(!is_int($f['response']))$action=$f['recommendation'].'
Owner: '.$f['role'].' | '.$f['timing'];
            $measure='KRA: '.$m['kra'].'
KPI: '.$m['kpi'].(!is_int($f['response'])?'
'.($f['response']==='na'?'Review applicability first.':'Verify before planning.'):'');
            $rows[]=[$observation,$action,$measure];
        }
        $note=$s['domain_notes'][$d['code']]??'';
        $endText=trim($note)===''?'No domain explanation supplied. Scores are self-reported, not independently verified.':'Submitted domain explanation, not independently verified: '.report_excerpt($note,180).'. A domain note does not verify every question.';
        $pdf->FitDomain($rows,$endText);
        $pdf->Small('Baseline to be measured; targets to agree; actual outcomes not yet measured. For percentage KPIs, retain numerator, denominator and review period. Zero opportunities: No eligible cases. Keep patient-level evidence private.');
    }

    $pdf->AddPage();$pdf->Section('Suggested next steps','Suggested 30-day action plan');
    $pdf->Small('Up to five supported actions. Suggested owners and timing run from report delivery and are not clinic commitments. These actions are useful with paper records or software.');
    $rows=[];foreach($report['action_plan'] as $i=>$f)$rows[]=[($i+1).'. '.$f['id'].' / '.$f['title'].'
'.$f['response_label'],report_short_action($f),$f['role'].'
'.$f['timing'],$f['completion_check']];
    if($rows)$pdf->CompactTable(['Priority / basis','Suggested action','Owner / timing','Completion check'],$rows,[38,57,36,43],9,4);
    else $pdf->Small('No supported improvement or confirmation actions were identified. Maintain periodic checks of the processes reported as reliable.');
    $pdf->Ln(3);$pdf->Small('Suggested 90-day roadmap',true);
    foreach(['Within 30 days: Confirm unknowns and applicability. Choose priorities, name owners and measure baselines.','Days 31-60: Practise the agreed routines. Check a small sample and address exceptions.','Days 61-90: Repeat the same measures. Compare baseline, agreed target and actual results; record next steps.'] as $phase)$pdf->Small($phase);
    $pdf->Ln(3);$pdf->Small('Day-90 outcome review',true);
    $pdf->Small('For each selected action, record: baseline, clinic-agreed target, day-90 actual, evidence, reviewer and next step. Baseline: To measure. Agreed target: To agree. Actual: Not yet measured. Status: Not yet reviewed.');
    $pdf->Small('Intended outcome: clearer responsibilities and checked routines, subject to carrying out the agreed actions. This is not an achieved outcome or a guaranteed score improvement.');
    $pdf->Ln(3);$pdf->Small('Optional next step',true);$pdf->Small('Discuss the findings or request a demonstration of relevant current My Physio Saathi capabilities. Buying software is optional.');
    $pdf->Small('Tejas P Mehta | Call: +91 98256 47083 | www.myphysiosaathi.in/',true);
    $pdf->Small($report['disclaimer']);
    if($pdf->PageNo()!==8 || $pdf->GetY()>273)throw new RuntimeException('Compact report layout exceeded the eight-page limit.');
    return $pdf->Output('S');
}
