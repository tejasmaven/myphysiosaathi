<?php
require_once __DIR__.'/../assessment/report-store.php';
require_once __DIR__.'/../assessment/pdf.php';
function check($ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
function close_to($actual,$expected,string $message): void {check(abs($actual-$expected)<0.000001,$message);}
$domains=json_decode(file_get_contents(__DIR__.'/../assessment/questions.json'),true,512,JSON_THROW_ON_ERROR);
$rules=require __DIR__.'/../assessment/scoring-rules.php';$catalogue=require __DIR__.'/../assessment/recommendations.php';
$labels=['clinic_name'=>'Clinic name','doctor_name'=>'Doctor full name','location'=>'Location / area / address','city'=>'City','mobile'=>'Mobile number','email'=>'Email address','beds'=>'Number of beds / treatment couches'];
$submission=['id'=>strtoupper(bin2hex(random_bytes(16))),'submitted_at'=>'09 Oct 2026, 12:00 IST','submitted_at_iso'=>'2026-10-09T12:00:00+05:30','assessment_version'=>$rules['assessment_version'],
    'fields'=>['clinic_name'=>'Example Physiotherapy Clinic','doctor_name'=>'Dr Example','location'=>'Example area','city'=>'Surat','mobile'=>'9876543210','email'=>'clinic@example.test','beds'=>'0'],'answers'=>[],'domain_notes'=>[]];
$fixture=['A'=>[3,3,3,4,4],'B'=>[4,4,5,5,'unknown'],'C'=>['unknown',4,3,2,1],'D'=>[2,3,4,5,'na'],'E'=>[4,3,5,3,4]];
foreach($fixture as $domain=>$values)foreach($values as $i=>$value)$submission['answers'][$domain.($i+1)]=$value;
$report=assessment_build_report($submission,$domains,$rules,$catalogue);
foreach(['A'=>3.4,'B'=>4.5,'C'=>2.5,'D'=>3.5,'E'=>3.8] as $id=>$average)close_to($report['domains'][$id]['average'],$average,'Domain '.$id);
check($report['numeric_sum']===78,'Numeric sum');check($report['counts']===['numeric'=>22,'unknown'=>2,'na'=>1,'missing'=>0],'Counts');
check(report_number($report['overall_average'])==='3.55','Question-weighted average');check(report_number($report['coverage'])==='91.67','Coverage');
check($report['status']==='Provisional' && $report['overall_eligible'],'Provisional but eligible interpretation');
check($report['versions']['roadmap']==='2.0.0' && $report['versions']['presentation']==='4.0.0','Versioned roadmap');
check(count($report['kpi_register'])===25 && count($report['domain_plans'])===5,'Complete domain and KPI planning coverage');
foreach($report['kpi_register'] as $m){check($m['actual_day90']===null,'No invented actuals');check($m['baseline']==='Baseline to be measured','No inferred KPI baseline');}
check($report['kpi_register']['C1']['mode']==='Confirm before planning','Unknown KPI requires confirmation');
check(str_contains($report['kpi_register']['D5']['target_for_report'],'Applicability'),'N/A target not activated');
check(count($report['roadmap_90'])===3 && count($report['day90_review'])===5,'Phased 90-day plan and selected outcomes');
foreach($report['day90_review'] as $row)check($row['baseline']===null && $row['agreed_target']===null && $row['actual']===null && $row['status']==='Not yet reviewed','No invented baseline, agreement or achievement');


check(array_column($report['priority_findings'],'id')===['C4','C5','D1'],'Stable numeric priorities');
check(array_column($report['items_to_confirm'],'id')===['C1','B5'],'Unknown control first');
check(array_column($report['applicability_review'],'id')===['D5'],'N/A review');
check(str_contains($report['findings']['D5']['recommendation'],'checking that an earlier clinic problem'),'D5 review');
check(array_column($report['action_plan'],'id')===['C4','C5','D1','C1','D5'],'Supported action plan includes verification');
foreach([1,5] as $value){$s=$submission;$s['answers']=array_fill_keys(array_keys($s['answers']),$value);$r=assessment_build_report($s,$domains,$rules,$catalogue);
    check($r['overall_average']===$value*1.0 || $r['overall_average']===$value,'Complete average');check($r['coverage']===100 || $r['coverage']===100.0,'Complete coverage');check($r['status']==='Complete self-reported assessment','Full numeric self-reported status');
    check($r['overall_interpretation']===($value===5?'Strong reported practice':'Process needs to be established'),'Complete interpretation');check(count($r['priority_findings'])===($value===5?0:3),'No invented gaps');check(str_starts_with(customer_report_pdf($r,$labels),'%PDF-'),'Extreme answer PDF renders');
    if($value===1)check(array_column($r['priority_findings'],'id')===['C1','C2','C4'],'Control-first sorting');
}
$s=$submission;$s['answers']=array_fill_keys(array_keys($s['answers']),'unknown');$r=assessment_build_report($s,$domains,$rules,$catalogue);
check($r['overall_average']===null && $r['domains']['A']['average']===null,'No numeric score');check(!$r['overall_eligible'] && count($r['priority_findings'])===0,'No unknown failures');check(str_starts_with(customer_report_pdf($r,$labels),'%PDF-'),'No numeric PDF renders');
$s['answers']=[];$rejected=false;try{assessment_build_report($s,$domains,$rules,$catalogue);}catch(InvalidArgumentException $e){$rejected=true;}check($rejected,'Incomplete submission rejected');
$s['answers']=array_fill_keys(array_keys($submission['answers']),'na');$r=assessment_build_report($s,$domains,$rules,$catalogue);check($r['coverage']===null,'All N/A denominator');
$s=$submission;$s['answers']=array_fill_keys(array_keys($s['answers']),5);$s['answers']['A1']='unknown';$s['answers']['A2']='unknown';$s['answers']['A3']='unknown';$r=assessment_build_report($s,$domains,$rules,$catalogue);
check(report_number($r['coverage'])==='88.00' && !$r['overall_eligible'],'High coverage still requires each domain');check($r['domains']['A']['interpretation']==='Insufficient information','Insufficient domain');
$s['answers']['A3']=5;$r=assessment_build_report($s,$domains,$rules,$catalogue);check($r['overall_eligible'],'Three per domain');
$s=$submission;$s['answers']=array_fill_keys(array_keys($s['answers']),5);$s['answers']['E1']=1;$r=assessment_build_report($s,$domains,$rules,$catalogue);check($r['overall_average']>4.5 && $r['priority_findings'][0]['control_gap'],'Control gap despite high average');check(count($r['priority_findings'])===1,'Do not invent three gaps');
$s=$submission;$s['answers']=array_fill_keys(array_keys($s['answers']),4);foreach(['A','B','C','D','E'] as $d)$s['answers'][$d.'5']='unknown';$r=assessment_build_report($s,$domains,$rules,$catalogue);check($r['coverage']==80 && $r['overall_eligible'],'Exactly 80 percent coverage');foreach(['A','B','C','D','E'] as $d)$s['answers'][$d.'4']='unknown';$r=assessment_build_report($s,$domains,$rules,$catalogue);check($r['coverage']==60 && !$r['overall_eligible'],'Insufficient overall coverage with sufficient domains');
$customCatalogue=$catalogue;$customCatalogue['questions']['A1']['software_capability']='verified_patient_records';$customCatalogue['questions']['A1']['software_note']='Patient records capability confirmed by clinic owner.';$r=assessment_build_report($submission,$domains,$rules,$customCatalogue);check(!isset($r['findings']['A1']['software_note']),'Unverified capability suppressed');$customCatalogue['verified_capability_allowlist']=['verified_patient_records'];$r=assessment_build_report($submission,$domains,$rules,$customCatalogue);check(isset($r['findings']['A1']['software_note']),'Explicit capability allowlist');
foreach([[1.999999,'Process needs to be established'],[2.0,'Practice is inconsistent'],[2.999999,'Practice is inconsistent'],[3.0,'Working foundation'],[3.999999,'Working foundation'],[4.0,'Reliable reported practice'],[4.499999,'Reliable reported practice'],[4.5,'Strong reported practice']] as [$v,$text])check(assessment_interpret($v,$rules)===$text,'Unrounded threshold');
$custom=$rules;$custom['bands'][0]['below']=2.5;check(assessment_interpret(2.4,$custom)==='Process needs to be established','Configurable threshold');
foreach([0,6,-1,1.0,1.2,true,false,null,'',' ','N/A','2.0','6',[],new stdClass()] as $invalid){$rejected=false;try{assessment_normalize_answer($invalid);}catch(InvalidArgumentException $e){$rejected=true;}check($rejected,'Invalid value rejected');}
check(assessment_normalize_answer('unsure')==='unknown' && assessment_normalize_answer('3')===3,'Supported string normalization');
$s=$submission;$s['answers']['Z1']=3;$rejected=false;try{assessment_build_report($s,$domains,$rules,$catalogue);}catch(InvalidArgumentException $e){$rejected=true;}check($rejected,'Unexpected ID');
foreach($catalogue['questions'] as $id=>$entry){foreach(['id','domain','title','consequence','question','control','recommendations','na_review','verification','role','timing','completion_check'] as $key)check(isset($entry[$key]),'Complete catalogue '.$id);check(count($entry['recommendations'])===5,'Actions for all five ratings');}
check(count($catalogue['questions'])===25,'All questions configured');
check(str_contains($catalogue['questions']['B5']['question'],'progress reviewed') && str_contains($catalogue['questions']['B5']['recommendations'][3],'progress review'),'B5 progress-review mapping');
check(str_contains($catalogue['questions']['A5']['recommendations'][3],'end-of-day check'),'A5 file-return mapping');
check(str_contains($catalogue['questions']['E1']['recommendations'][3],'public view') && str_contains($catalogue['questions']['E2']['recommendations'][3],'access'),'E1/E2 meaning');
check(str_contains($report['kpi_register']['C4']['formula'],'operating days'),'C4 KPI denominator');
foreach([3,4,5] as $score){
    $s=$submission;$s['answers']=array_fill_keys(array_keys($s['answers']),$score);$s['domain_notes']=$score===4?array_fill_keys(['A','B','C','D','E'],'We check recent records and address gaps.'):[];
    $r=assessment_build_report($s,$domains,$rules,$catalogue);check($r['overall_average']==$score,'Uncapped score');
    check($r['overall_interpretation']===[3=>'Working foundation',4=>'Reliable reported practice',5=>'Strong reported practice'][$score],'Revised scale interpretation');
    foreach($r['findings'] as $f){if($score>=4)check($f['evidence_label']===($score===4?'Domain explanation supplied; not independently verified':'Reported score, example not provided'),'Evidence label with unchanged high scores');if($score===3)check(str_contains($f['recommendation'],'Add a completion check'),'Score 3 adds checks');}
    if($score>=4)check(!$r['priority_findings'] && !$r['action_plan'],'High-scoring low-need lead without invented gaps');
}
$s=$submission;$s['domain_notes']=['A'=>str_repeat('é',2000)];$r=assessment_build_report($s,$domains,$rules,$catalogue);check($r['submission']['domain_notes']['A']===$s['domain_notes']['A'],'2000 Unicode characters retained');
$s['domain_notes']['A'].='é';$rejected=false;try{assessment_build_report($s,$domains,$rules,$catalogue);}catch(InvalidArgumentException $e){$rejected=true;}check($rejected,'2001-character explanation rejected');
foreach([-1,'-1','1.2',1.0,true,null] as $beds){$rejected=false;try{assessment_normalize_beds($beds);}catch(InvalidArgumentException $e){$rejected=true;}check($rejected,'Invalid beds rejected');}check(assessment_normalize_beds('0')===0,'Zero beds');
$s=$submission;$s['answers']=array_fill_keys(array_keys($s['answers']),'unknown');$r=assessment_build_report($s,$domains,$rules,$catalogue);check($r['status']==='Insufficient information','Insufficient status precedence');

// Mixed answers and maximum-length explanations must also stay within the page budget.
mt_srand(20261009);
for($case=0;$case<100;$case++){
    $s=$submission;$s['id']='09102026-1';$choices=[1,2,3,4,5,'unknown','na'];
    foreach($s['answers'] as $id=>$value)$s['answers'][$id]=$choices[$case<7?$case:mt_rand(0,6)];
    $s['domain_notes']=array_fill_keys(['A','B','C','D','E'],substr(str_repeat('Anonymised explanation. ',100),0,2000));
    $bytes=customer_report_pdf(assessment_build_report($s,$domains,$rules,$catalogue),$labels);
    check(preg_match_all('/\/Type \/Page\b/',$bytes)===8,'Eight-page mixed response case '.$case);
}
// Optional layout fixtures for the release's explicit PDF acceptance review.
if(getenv('REPORT_TEST_DIRECTORY')){
    $dir=getenv('REPORT_TEST_DIRECTORY');if(!is_dir($dir))mkdir($dir,0700,true);
    foreach(['all3'=>3,'all4'=>4,'all5'=>5,'all1'=>1,'unknown'=>'unknown','na'=>'na','long-notes'=>4,'max-profile'=>3,'wide-profile'=>1] as $name=>$answer){
        $s=$submission;$s['id']='09102026-1';$s['answers']=array_fill_keys(array_keys($s['answers']),$answer);
        if($name==='all4')$s['domain_notes']=array_fill_keys(['A','B','C','D','E'],'We check a recent sample and record any gaps for review.');
        if($name==='long-notes'){$s['fields']['clinic_name']=str_repeat('Example Clinic ',10);$s['fields']['location']=str_repeat('Example area ',30);$s['domain_notes']=array_fill_keys(['A','B','C','D','E'],substr(str_repeat('We check anonymised records and follow up on exceptions. ',45),0,2000));}
        if($name==='max-profile' || $name==='wide-profile'){foreach(['clinic_name','doctor_name','city'] as $key)$s['fields'][$key]=substr(str_repeat('Example details ',20),0,160);$s['fields']['location']=substr(str_repeat('Example address ',40),0,500);$s['fields']['email']=str_repeat('a',64).'@'.str_repeat('b',60).'.'.str_repeat('c',60).'.'.str_repeat('d',60).'.test';}
        if($name==='wide-profile'){foreach(['clinic_name','doctor_name','city'] as $key)$s['fields'][$key]=str_repeat('W',160);$s['fields']['location']=str_repeat('W',500);}
        $r=assessment_build_report($s,$domains,$rules,$catalogue);$bytes=customer_report_pdf($r,$labels);check(preg_match_all('/\/Type \/Page\b/',$bytes)===8,'Eight pages for '.$name);file_put_contents($dir.'/'.$name.'.pdf',$bytes);
    }
}
// Private persistence, generation failure, partial delivery and retry use the real lifecycle.
$temp=sys_get_temp_dir().'/mps-report-regression-'.bin2hex(random_bytes(8));$config=['report_directory'=>$temp];$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__.'/..');
$ref=assessment_create_record($config,$submission,$domains,$rules,$catalogue);$generation=0;$sends=[];$pdfs=[];
$generator=function($r)use(&$generation,$labels){$generation++;if($generation===1)throw new RuntimeException('Simulated PDF failure');return customer_report_pdf($r,$labels);};
$sender=function($s,$target,$pdf)use(&$sends,&$pdfs){$sends[]=$target;$pdfs[]=$pdf;if($target==='admin' && count($sends)===1)throw new RuntimeException('Simulated SMTP rejection');};
try{assessment_process_record($config,$ref,$generator,$sender);}catch(RuntimeException $e){}
$record=assessment_read_record($config,$ref);check($record['pdf']['status']==='failed' && !$sends,'Separate PDF failure');
$record=assessment_process_record($config,$ref,$generator,$sender);check($record['pdf']['status']==='generated' && $record['email']['admin']['status']==='failed' && $record['email']['user']['status']==='sent','Partial delivery');
$record=assessment_process_record($config,$ref,$generator,$sender);check($generation===2 && $sends===['admin','user','admin'],'Retry without regeneration or duplicate user email');
check($pdfs[0]===$pdfs[1] && $pdfs[1]===$pdfs[2],'Same PDF bytes on retry');check(count(glob($temp.'/*.json'))===1 && $record['reference']==='09102026-1' && $record['storage_id']===$ref && $record['submission']['id']===$record['reference'],'Single record/reference');
$reproduced=assessment_build_report($record['submission'],$record['snapshots']['domains'],$record['snapshots']['rules'],$record['snapshots']['catalogue']);check($reproduced===$record['report'],'Reproducible snapshot');
check((fileperms($temp)&0777)===0700 && (fileperms($temp.'/'.$ref.'.pdf')&0777)===0600,'Private permissions');
$badDir=__DIR__.'/../assessment/reports-test';$rejected=false;try{assessment_report_directory(['report_directory'=>$badDir]);}catch(RuntimeException $e){$rejected=true;}check($rejected,'Public storage rejected');if(is_dir($badDir))rmdir($badDir);
if(getenv('REPORT_TEST_OUTPUT'))file_put_contents(getenv('REPORT_TEST_OUTPUT'),$pdfs[0]);
check(assessment_allocate_reference($config,new DateTimeImmutable('2026-10-09T12:00:00+05:30'))==='09102026-2','Second daily reference');
check(assessment_allocate_reference($config,new DateTimeImmutable('2026-10-09T20:00:00+00:00'))==='10102026-1','India midnight resets daily sequence');
check(assessment_allocate_reference($config,new DateTimeImmutable('2026-10-09T12:00:00+05:30'))==='09102026-3','Daily counter survives calls and retries do not allocate');
foreach(glob($temp.'/*') as $f)unlink($f);rmdir($temp);
echo "PASS: supplied fixture, extremes, missing/N/A, invalid values, unrounded configurable thresholds, control priorities, catalogue, private storage, reproducibility, PDF failure and email retry.\n";
