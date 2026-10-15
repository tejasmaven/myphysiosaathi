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
        if($this->PageNo()===1){
            $this->SetFillColor(15,23,43);$this->Rect(0,0,210,297,'F');
            $this->SetFillColor(225,32,27);$this->Rect(0,0,210,3,'F');$this->Rect(0,294,210,3,'F');
            $this->SetXY(18,15);$this->SetFont('Helvetica','B',12);$this->SetTextColor(255,255,255);$this->Cell(0,7,'MY PHYSIO SAATHI');
            $this->SetXY(18,24);$this->SetFont('Helvetica','',9);$this->SetTextColor(202,213,226);$this->Cell(0,5,'Every patient visit, connected.');$this->SetXY(18,39);return;
        }
        $this->SetFillColor(15,23,43);$this->Rect(0,0,210,16,'F');
        $this->SetFillColor(225,32,27);$this->Rect(0,0,210,2,'F');
        $this->SetXY(18,5);$this->SetFont('Helvetica','B',8);$this->SetTextColor(255,255,255);
        $this->Cell(0,6,'MY PHYSIO SAATHI | CLINIC WORKFLOW REPORT');$this->SetXY(18,26);
    }
    public function Footer() {
        if($this->PageNo()!==1){parent::Footer();return;}
        $this->SetY(-17);$this->SetTextColor(202,213,226);$this->SetFont('Helvetica','',9);
        $this->Cell(145,7,'Tejas P Mehta | Call: +91 98256 47083');$this->Cell(29,7,$this->PageNo().'/{nb}',0,0,'R');
    }
    public function CoverText(string $text,bool $bold=false,float $size=9): void {
        $this->SetFont('Helvetica',$bold?'B':'',$size);$this->SetTextColor(222,231,242);$this->MultiCell(0,4.4,pdf_text($text),0,'L');$this->Ln(1.5);
        if($this->GetY()>273)throw new RuntimeException('Compact report page overflow.');
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
            $this->SetFillColor(239,245,250);$this->Rect($x,$y,$width-3,18,'F');
            $this->SetXY($x+3,$y+3);$this->SetFont('Helvetica','B',8);$this->SetTextColor(55,65,81);$this->MultiCell($width-9,4,pdf_text($tile[0]));
            $this->SetXY($x+3,$y+9);$this->SetFont('Helvetica','B',14);$this->SetTextColor(15,23,43);$this->MultiCell($width-9,6,pdf_text($tile[1]));
        }$this->SetXY(18,$y+22);
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
    public function Profile(array $rows,bool $cover=false): void {
        foreach($rows as $index=>[$label,$value]){
            $cap=[1,1,3,3,3,2,1,4,2][$index]??3;
            if($this->Lines($value,false,111,9)>$cap){
                preg_match_all('/./us',$value,$chars);$parts=$chars[0];
                do{array_pop($parts);$value=rtrim(implode('',$parts)).' [excerpt]';}while($parts && $this->Lines($value,false,111,9)>$cap);
            }
            $height=max($this->Lines($label,true,51,9),$this->Lines($value,false,111,9))*4.4+6;$y=$this->GetY();
            $this->SetFillColor(...($cover?[36,56,82]:[229,237,246]));$this->Rect(18,$y,57,$height,'F');$this->SetFillColor(...($cover?[27,43,64]:[248,250,252]));$this->Rect(75,$y,117,$height,'F');
            $this->SetDrawColor(...($cover?[51,70,94]:[216,226,237]));$this->Rect(18,$y,174,$height);
            $this->SetXY(21,$y+3);$this->SetFont('Helvetica','B',9);$this->SetTextColor(...($cover?[255,255,255]:[15,23,43]));$this->MultiCell(51,4.4,pdf_text($label),0,'L');
            $this->SetXY(78,$y+3);$this->SetFont('Helvetica','',9);$this->SetTextColor(...($cover?[222,231,242]:[55,65,81]));$this->MultiCell(111,4.4,pdf_text($value),0,'L');$this->SetXY(18,$y+$height);
        }
    }
    public function CompactHeight(array $rows,array $widths,float $size,float $line): float {
        $height=0;foreach($rows as $row){$lines=1;foreach($row as $i=>$cell)$lines=max($lines,$this->Lines($cell,false,$widths[$i]-5,$size));$height+=$lines*$line+4;}return $height;
    }
    public function CompactTable(array $headers,array $rows,array $widths,float $size=9,float $line=4): void {
        $draw=function(array $cells,bool $head=false,int $index=0)use($widths,$size,$line){
            $attention=!$head && strpos(implode(' ',$cells),'CONTROL GAP')!==false;
            $lines=1;foreach($cells as $i=>$cell)$lines=max($lines,$this->Lines($cell,$head,$widths[$i]-5,$size));$height=$lines*$line+4;$y=$this->GetY();
            if($y+$height>273)throw new RuntimeException('Compact table overflow.');$x=18;
            foreach($cells as $i=>$cell){$this->SetFillColor($head?15:($index%2?248:239),$head?23:($index%2?250:245),$head?43:($index%2?252:250));$this->SetDrawColor(218,226,235);if(isset($attention) && $attention)$this->SetFillColor(255,244,240);$this->Rect($x,$y,$widths[$i],$height,'DF');
                $this->SetXY($x+2.5,$y+2);$this->SetFont('Helvetica',$head?'B':'',$size);$this->SetTextColor($head?255:55,$head?255:65,$head?255:81);$this->MultiCell($widths[$i]-5,$line,pdf_text($cell),0,'L');$x+=$widths[$i];}
            $this->SetXY(18,$y+$height);
        };$draw($headers,true);foreach($rows as $i=>$row)$draw($row,false,$i);$this->Ln(2);
    }
    public function Circle(float $x,float $y,float $r): void {
        $c=0.5522847498*$r;$k=$this->k;$h=$this->h;
        $this->_out(sprintf('%.3F %.3F m',($x+$r)*$k,($h-$y)*$k));
        foreach([[$x+$r,$y+$c,$x+$c,$y+$r,$x,$y+$r],[$x-$c,$y+$r,$x-$r,$y+$c,$x-$r,$y],[$x-$r,$y-$c,$x-$c,$y-$r,$x,$y-$r],[$x+$c,$y-$r,$x+$r,$y-$c,$x+$r,$y]] as $p)
            $this->_out(sprintf('%.3F %.3F %.3F %.3F %.3F %.3F c',$p[0]*$k,($h-$p[1])*$k,$p[2]*$k,($h-$p[3])*$k,$p[4]*$k,($h-$p[5])*$k));
        $this->_out('S');
    }
    public function RatingStrip($response,float $x,float $y,float $width): void {
        $colours=[[183,25,22],[174,78,0],[128,85,0],[0,109,119],[22,101,52]];
        $step=$width/5;
        for($i=1;$i<=5;$i++){
            $this->SetFillColor(...(is_int($response)?$colours[$i-1]:[226,232,240]));$this->Rect($x+($i-1)*$step,$y,$step-0.8,7.5,'F');
            $this->SetTextColor(...(is_int($response)?[255,255,255]:[69,85,108]));$this->SetFont('Helvetica','B',9);
            $this->SetXY($x+($i-1)*$step,$y+1);$this->Cell($step-0.8,5,(string)$i,0,0,'C');
            if($response===$i){$this->SetDrawColor(255,255,255);$this->SetLineWidth(0.55);$this->Circle($x+($i-0.5)*$step-0.4,$y+3.75,2.9);$this->SetLineWidth(0.2);}
        }
    }
    public function Scorecard(array $domains): void {
        $widths=[56,18,22,57,21];$this->CompactTable(['Domain','Scored','Avg / 5','Interpretation','Open'],[],$widths,9,4);$this->Ln(-2);
        foreach(array_values($domains) as $index=>$domain){
            $cells=[$domain['code'].' / '.$domain['title'],$domain['counts']['numeric'].' / 5',report_number($domain['average']),$domain['interpretation'],(string)$domain['unresolved_count']];
            $height=max(12,$this->CompactHeight([$cells],$widths,9,4));$y=$this->GetY();$x=18;
            foreach($cells as $i=>$cell){
                $this->SetFillColor(...($index%2?[248,250,252]:[239,245,250]));$this->SetDrawColor(218,226,235);$this->Rect($x,$y,$widths[$i],$height,'DF');
                $this->SetXY($x+2.5,$y+2);$this->SetFont('Helvetica',$i===2?'B':'',9);$this->SetTextColor(15,23,43);$this->MultiCell($widths[$i]-5,4,pdf_text($cell),0,'L');
                if($i===2 && $domain['average']!==null){$this->SetFillColor(211,222,234);$this->Rect($x+2.5,$y+8,$widths[$i]-5,2,'F');$this->SetFillColor(15,23,43);$this->Rect($x+2.5,$y+8,($widths[$i]-5)*$domain['average']/5,2,'F');}
                $x+=$widths[$i];
            }$this->SetXY(18,$y+$height);
        }$this->Ln(3);
    }
    /** Wrap mixed-weight labels once, using the same glyph widths for measurement and drawing. */
    public function LabelLines(string $text,float $width): array {
        $characters=[];$text=rtrim(str_replace("\r",'',pdf_text($text)),"\n");
        foreach(preg_split('/(Owner:|Check:|KRA:|KPI:)/',$text,-1,PREG_SPLIT_DELIM_CAPTURE) as $part){
            $bold=in_array($part,['Owner:','Check:','KRA:','KPI:'],true);$this->SetFont('Helvetica',$bold?'B':'',9);
            foreach(str_split($part) as $char)$characters[]=[$char,($this->CurrentFont['cw'][$char]??0)*$this->FontSize/1000,$bold];
        }
        $maximum=$width-2*$this->cMargin;$lines=[];$start=0;$i=0;$space=-1;$length=0;$count=count($characters);
        while($i<$count){
            [$char,$widthOfChar]=$characters[$i];
            if($char==="\n"){$lines[]=array_slice($characters,$start,$i-$start);$start=++$i;$space=-1;$length=0;continue;}
            if($char===' ')$space=$i;
            if($length+$widthOfChar>$maximum){
                if($space>=$start){$end=$space;$next=$space+1;}else{$end=$i>$start?$i:$i+1;$next=$end;}
                $lines[]=array_slice($characters,$start,$end-$start);$start=$i=$next;$space=-1;$length=0;continue;
            }$length+=$widthOfChar;$i++;
        }
        if($start<$count || !$lines)$lines[]=array_slice($characters,$start);
        return $lines;
    }
    public function LabelCell(string $text,float $width,float $line): void {
        $x=$this->GetX();$y=$this->GetY();$lines=$this->LabelLines($text,$width);$margin=$this->cMargin;$this->cMargin=0;
        foreach($lines as $offset=>$characters){
            $this->SetXY($x+$margin,$y+$offset*$line);$chunk='';$bold=null;
            foreach($characters as [$char,$charWidth,$isBold]){
                if($bold!==null && $bold!==$isBold){$this->SetFont('Helvetica',$bold?'B':'',9);$this->Cell($this->GetStringWidth($chunk),$line,$chunk);$chunk='';}
                $bold=$isBold;$chunk.=$char;
            }
            if($chunk!==''){$this->SetFont('Helvetica',$bold?'B':'',9);$this->Cell($this->GetStringWidth($chunk),$line,$chunk);}
        }
        $this->cMargin=$margin;$this->SetXY($x,$y+count($lines)*$line);
    }
    public function GraphicDomainHeight(array $row,float $line): float {
        $caption=is_int($row[3])?'':($row[3]==='na'?'N/A (unreviewed)':$row[4]);
        $captionHeight=$caption===''?0:$this->Lines($caption,true,77,9)*$line;
        $questionHeight=$this->Lines($row[0],true,77,9)*$line+9.5+$captionHeight+$this->Lines($row[5],false,77,9)*$line;
        $other=max(count($this->LabelLines($row[1],51)),count($this->LabelLines($row[2],31)))*$line;
        return max($questionHeight,$other)+4;
    }
    public function FitDomain(array $rows,string $note): void {
        if($this->Lines($note,false,174,9)>2){
            preg_match_all('/./us',preg_replace('/\s*\[excerpt\]$/','',$note),$characters);$parts=$characters[0];
            do{array_pop($parts);$note=rtrim(implode('',$parts)).' [excerpt]';}while($parts && $this->Lines($note,false,174,9)>2);
        }

        $widths=[82,56,36];$line=null;
        foreach([4.0,3.8,3.6] as $candidate){
            $height=array_sum(array_map(fn($row)=>$this->GraphicDomainHeight($row,$candidate),$rows));
            $reserve=$this->Lines($note,false,174,9)*4.1+3.2;
            if($this->GetY()+$height+8+$reserve<=273){$line=$candidate;break;}
        }
        if($line===null)throw new RuntimeException('Domain content exceeds the compact report layout.');
        $this->CompactTable(['Question / selected response','Suggested plan / check','KRA / KPI'],[],$widths,9,4);$this->Ln(-2);
        foreach($rows as $index=>$row){
            $height=$this->GraphicDomainHeight($row,$line);$y=$this->GetY();$x=18;
            $attention=$row[6];
            foreach($widths as $i=>$width){
                $this->SetFillColor(...($attention?[255,244,240]:($index%2?[248,250,252]:[239,245,250])));$this->SetDrawColor(218,226,235);$this->Rect($x,$y,$width,$height,'DF');
                if($i===0){
                    $this->SetXY($x+2.5,$y+2);$this->SetFont('Helvetica','B',9);$this->SetTextColor(15,23,43);$this->MultiCell($width-5,$line,pdf_text($row[0]),0,'L');
                    $stripY=$this->GetY()+1;$this->RatingStrip($row[3],$x+2.5,$stripY,$width-5);
                    $this->SetXY($x+2.5,$stripY+8.5);$this->SetTextColor(15,23,43);
                    if(!is_int($row[3])){$this->SetFont('Helvetica','B',9);$this->MultiCell($width-5,$line,pdf_text($row[3]==='na'?'N/A (unreviewed)':$row[4]),0,'L');}
                    $this->SetX($x+2.5);$this->SetFont('Helvetica','',9);$this->MultiCell($width-5,$line,pdf_text($row[5]),0,'L');
                }else{
                    $this->SetXY($x+2.5,$y+2);$this->SetTextColor(55,65,81);$this->LabelCell($row[$i],$width-5,$line);
                }$x+=$width;
            }$this->SetXY(18,$y+$height);
        }$this->Ln(2);$this->Small($note);
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
    $pdf->AddPage();$pdf->SetFont('Helvetica','B',29);$pdf->SetTextColor(255,255,255);$pdf->Cell(0,12,'CLINIC WORKFLOW',0,1);
    $pdf->SetTextColor(255,128,122);$pdf->Cell(0,12,'ASSESSMENT REPORT',0,1);$pdf->Ln(4);
    $pdf->CoverText('A practical review of patient records, treatment continuity, payments and clinic routines.',false,10);$pdf->SetY(90);
    $rows=[['Assessment reference',$s['id']],['Submission date',$s['submitted_at']]];
    foreach($labels as $key=>$label)$rows[]=[$label,report_excerpt((string)($s['fields'][$key]??'Not supplied'),$key==='location'?250:254)];
    $pdf->Profile($rows,true);$pdf->Ln(5);
    $pdf->CoverText('Report status: '.$report['status'],true);
    $pdf->CoverText($report['disclaimer']);
    $pdf->CoverText('Assessment version: '.$report['versions']['assessment'].' | Scoring rules: '.$report['versions']['scoring'].' | Recommendation catalogue: '.$report['versions']['recommendations'],false,8);
    $pdf->CoverText('Roadmap measures: '.$report['versions']['roadmap'].' | Presentation: '.$report['versions']['presentation'].'. Longer fields may appear as labelled excerpts; full originals are retained privately.',false,8);

    $pdf->AddPage();$pdf->Section('Executive summary','Scoring summary');
    $pdf->Tiles([['NUMERIC AVERAGE',$report['overall_eligible']?report_number($report['overall_average']).' / 5':'Not available'],['RESPONSE COVERAGE',$report['coverage']===null?'Not available':report_number($report['coverage']).'%'],['REPORT STATUS',$report['status']==='Insufficient information'?'Insufficient':($report['status']==='Provisional'?'Provisional':'Self-reported')]]);
    $pdf->Small('Report status: '.$report['status'].' | '.$report['overall_interpretation'],true);
    $c=$report['counts'];$pdf->Small('Numeric: '.$c['numeric'].' | Unknown: '.$c['unknown'].' | N/A: '.$c['na'].' | Missing: '.$c['missing']);
    $pdf->Small($report['coverage_label'].'. Average = numeric sum / numeric count. An overall interpretation needs 80% coverage and three numeric answers per domain.');
    $pdf->Ln(2);$pdf->Scorecard($report['domains']);
    $extremes=[];foreach(['strongest_domain'=>'Strongest scored domain','weakest_domain'=>'Weakest scored domain'] as $key=>$label){$d=$report[$key];$extremes[]=$label.': '.($d?$d['code'].' ('.report_number($d['average']).' / 5)':'Not available');}$pdf->Small(implode(' | ',$extremes));
    $pdf->Ln(2);$pdf->Small('Priorities for improvement',true);
    $priorityRows=[];
    foreach($report['priority_findings'] as $i=>$f)$priorityRows[]=[(string)($i+1),$f['id'].' / '.$f['title'].($f['control_gap']?"\nIMPORTANT CONTROL GAP":''),$f['response_label'],preg_split('/(?<=[.!?])\s+/u',$f['recommendation'],2)[0]];
    if($priorityRows)$pdf->CompactTable(['#','Supported finding','Score','Suggested first action'],$priorityRows,[10,66,22,76],9,3.8);
    else $pdf->Small('No numeric gaps scoring 1, 2 or 3 were reported. Confirmation tasks may still be needed.');
    $pdf->Ln(2);$pdf->Small('Items to confirm',true);
    $unknown=$report['items_to_confirm'];$controls=array_filter($unknown,fn($f)=>$f['important_control']);$others=array_filter($unknown,fn($f)=>!$f['important_control']);
    $pdf->Small('Unknown / missing, not demonstrated failures. Important controls: '.($controls?implode(', ',array_column($controls,'id')):'None').'. Other items: '.($others?implode(', ',array_column($others,'id')):'None').'.');
    $nas=$report['applicability_review'];$pdf->Small('Respondent-selected N/A for applicability review: '.($nas?implode(', ',array_column($nas,'id')):'None').'. See each domain for the question and task.');
    if(isset($report['findings']['D5']) && $report['findings']['D5']['response']==='na')$pdf->Small('D5: Why is checking that an earlier clinic problem has been corrected considered inapplicable? Record and review the reason.');
    $scale=[];foreach($report['rating_scale'] as $score=>$rating)$scale[]=$score.' '.$rating['level'];$pdf->Small('Rating key: '.implode('; ',$scale).'. The selected response is circled. Unknown and N/A have no selected numeric score.');
    $pdf->Small('Self-reported results; bands are not industry benchmarks. Open = unknown, missing or unreviewed N/A.');

    foreach($report['domains'] as $d){
        $pdf->AddPage();$pdf->Section('Domain '.$d['code'].' / questions, ratings and improvements',$d['title']);
        $pdf->Small('Average: '.report_number($d['average']).($d['average']!==null?' / 5':'').' | Numeric: '.$d['counts']['numeric'].' / 5 | Unresolved: '.$d['unresolved_count'].' | '.$d['interpretation'],true);

        $rows=[];
        foreach($report['findings'] as $f){if($f['domain']!==$d['code'])continue;$m=$report['kpi_register'][$f['id']];
            $observation=$f['id'].'. '.$f['question'];$afterAnswer='';
            if($f['control_gap'])$afterAnswer.='IMPORTANT CONTROL GAP';
            if(is_int($f['response']) && $f['response']<=3)$afterAnswer.=($afterAnswer!==''?"\n":'').'Possible consequence: '.$f['consequence'];
            elseif(!is_int($f['response']))$afterAnswer.='Confirmation required, not a proven failure.';
            else $afterAnswer.=$f['evidence_label'];
            $action=report_short_action($f).'
Owner: '.$f['role'].' | '.$f['timing'].'
Check: '.$f['completion_check'];
            // N/A and unknown need the complete verification instruction, never an active numeric target.
            if(!is_int($f['response']))$action=$f['recommendation'].'
Owner: '.$f['role'].' | '.$f['timing'];
            $measure='KRA: '.$m['kra'].'
KPI: '.$m['kpi'].(!is_int($f['response'])?'
'.($f['response']==='na'?'Review applicability first.':'Verify before planning.'):'');
            $rows[]=[$observation,$action,$measure,$f['response'],$f['response_label'],$afterAnswer,$f['control_gap']];
        }
        $note=$s['domain_notes'][$d['code']]??'';
        $endText=trim($note)===''?'No domain explanation supplied. Scores are self-reported, not independently verified.':'Submitted domain note (unverified): '.report_excerpt($note,180);
        $pdf->FitDomain($rows,$endText);
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
