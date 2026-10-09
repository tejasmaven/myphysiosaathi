<?php
ini_set('session.use_strict_mode','1');
session_name('MPS_ASSESSMENT');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/']);
session_start();
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
$domains=json_decode(file_get_contents(__DIR__.'/questions.json'),true,512,JSON_THROW_ON_ERROR);
$labels=['clinic_name'=>'Clinic name','doctor_name'=>'Doctor full name','location'=>'Location / area / address','city'=>'City','mobile'=>'Mobile number','email'=>'Email address','beds'=>'Number of beds / treatment couches'];
$ratingScale=require __DIR__.'/rating-scale.php';$ratings=[];foreach($ratingScale as $value=>$item)$ratings[$value]=$value.' - '.$item['level'];$ratings['unknown']='Not sure';$ratings['na']='Not applicable';
$values=array_fill_keys(array_keys($labels),'');$answers=[];$domainNotes=array_fill_keys(['A','B','C','D','E'],'');$errors=[];$status='';
function esc($value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function input_text($value,int $max,bool $multiline=false): string {
    if(!is_string($value))throw new InvalidArgumentException('Invalid input type.');
    if(!preg_match('//u',$value) || strlen($value)>$max)throw new InvalidArgumentException('Input too long or invalid.');
    return trim(preg_replace($multiline?'/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/':'/[\x00-\x1F\x7F]/','',$value));
}
function permit_submission(array $config): bool {
    $dir=$config['state_directory'];
    if(!is_dir($dir)&&!@mkdir($dir,0700,true))throw new RuntimeException('Rate limit storage unavailable');
    $file=$dir.'/'.hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown').'.json';
    $fh=fopen($file,'c+');if(!$fh||!flock($fh,LOCK_EX))throw new RuntimeException('Rate limit lock unavailable');
    @chmod($file,0600);$entries=json_decode(stream_get_contents($fh),true)?:[];
    $entries=array_values(array_filter($entries,fn($t)=>is_int($t)&&$t>time()-3600));
    $ok=count($entries)<6;if($ok)$entries[]=time();
    ftruncate($fh,0);rewind($fh);fwrite($fh,json_encode($entries));flock($fh,LOCK_UN);fclose($fh);return $ok;
}
require_once __DIR__.'/config-loader.php';
require_once __DIR__.'/report-store.php';
$config=assessment_load_config();
$ready=assessment_config_ready($config);
if(!isset($_SESSION['token'])){$_SESSION['token']=bin2hex(random_bytes(24));$_SESSION['opened_at']=time();}
if($_SERVER['REQUEST_METHOD']==='POST'){
    $postedToken=is_string($_POST['csrf']??null)?$_POST['csrf']:'';
    if(!hash_equals($_SESSION['token'],$postedToken))$errors['form']='This form session has expired. Reload the page and try again.';
    elseif(!$ready)$errors['form']='Assessment email delivery is not configured yet. Please call +91 98256 47083.';
    elseif(!empty($_POST['website']) || time()-($_SESSION['opened_at']??time())<3)$errors['form']='Please review your answers and submit again.';
    else {
        try {
            foreach($labels as $key=>$label){
                $values[$key]=input_text($_POST[$key]??'', $key==='location'?500:($key==='email'?254:160));
                if($values[$key]==='')$errors[$key]='Please enter '.strtolower($label).'.';
            }
            if($values['email']!==''&&!filter_var($values['email'],FILTER_VALIDATE_EMAIL))$errors['email']='Please enter a valid email address.';
            if($values['mobile']!==''&&!preg_match('/^(?:\+91[ -]?)?[6-9][0-9]{9}$/',preg_replace('/[ ()-]/','',$values['mobile'])))$errors['mobile']='Enter a valid Indian mobile number, with or without +91.';
            try{assessment_normalize_beds($values['beds']);}catch(InvalidArgumentException $e){$errors['beds']='Enter a non-negative whole number; 0 is valid.';}
            $postedAnswers=$_POST['answers']??[];$postedNotes=$_POST['domain_notes']??[];
            if(!is_array($postedAnswers)||!is_array($postedNotes))throw new InvalidArgumentException('Invalid answers or domain notes.');
            $expected=[];foreach($domains as $d)foreach($d['questions'] as $id=>$q)$expected[]=$id;
            if(array_diff(array_keys($postedAnswers),$expected))$errors['form']='Unexpected question IDs were submitted. Reload the form.';
            if(array_diff(array_keys($postedNotes),array_column($domains,'code')))$errors['form']='Unexpected domain explanations were submitted. Reload the form.';
            foreach($domains as $d){
                foreach($d['questions'] as $id=>$q){
                    try{$answers[$id]=assessment_normalize_answer($postedAnswers[$id]??'');}
                    catch(InvalidArgumentException $e){$answers[$id]='';$errors[$id]='Choose one valid response for '.$id.'.';}
                }
                $code=$d['code'];try{$note=input_text($postedNotes[$code]??'',8000,true);assessment_validate_domain_note($note);$domainNotes[$code]=$note;}
                catch(InvalidArgumentException $e){$errors['domain-note-'.$code]='Enter an optional explanation of at most 2,000 characters for domain '.$code.'.';}
            }
        } catch(InvalidArgumentException $e){$errors['form']='Some submitted information is invalid or too long. Please check your entries.';}
        if(!$errors){
            try {
                // Persist the immutable structured submission before PDF generation or delivery.
                if(!isset($_SESSION['pending_reference'])){
                    if(!permit_submission($config))throw new RuntimeException('Too many submissions. Please try again in one hour.');
                    $rules=require __DIR__.'/scoring-rules.php';$catalogue=require __DIR__.'/recommendations.php';
                    $submitted=new DateTimeImmutable('now',new DateTimeZone('Asia/Kolkata'));
                    $submission=['id'=>strtoupper(bin2hex(random_bytes(16))),'submitted_at'=>$submitted->format('d M Y, H:i').' IST','submitted_at_iso'=>$submitted->format('c'),'assessment_version'=>$rules['assessment_version'],'fields'=>$values,'answers'=>$answers,'domain_notes'=>$domainNotes];
                    $_SESSION['pending_reference']=assessment_create_record($config,$submission,$domains,$rules,$catalogue);
                }
                require_once __DIR__.'/pdf.php';require_once __DIR__.'/mail.php';
                $pending=assessment_process_record($config,$_SESSION['pending_reference'],
                    fn($report)=>customer_report_pdf($report,$labels),
                    function($submission,$target,$pdf)use($config){deliver_assessment($config,$submission,$target==='admin'?$config['admin_email']:$submission['fields']['email'],$target==='admin',$pdf);});
                if($pending['email']['admin']['status']==='sent' && $pending['email']['user']['status']==='sent'){
                    $_SESSION['receipt']=['id'=>$pending['reference'],'email'=>$pending['submission']['fields']['email']];
                    unset($_SESSION['pending_reference']);$_SESSION['token']=bin2hex(random_bytes(24));$_SESSION['opened_at']=time();
                    header('Location: free-assesment.php?submitted=1',true,303);exit;
                }
                $errors['form']='Email delivery could not complete. Your report is saved privately. Use Retry report delivery below; copies already sent will not be sent again.';
            }catch(Throwable $e){error_log('Assessment processing failed');$errors['form']=$e->getMessage()==='Too many submissions. Please try again in one hour.'?$e->getMessage():'We could not process your assessment. Please try again or call +91 98256 47083.';}
        }
    }
}
if(isset($_SESSION['pending_reference'])){try{$saved=assessment_read_record($config,$_SESSION['pending_reference']);$values=$saved['submission']['fields'];$answers=$saved['submission']['answers'];$domainNotes=$saved['submission']['domain_notes'];}catch(Throwable $e){$errors['form']='Your saved report is currently unavailable. Please call +91 98256 47083 and quote reference '.$_SESSION['pending_reference'].'.';}}
$receipt=($_GET['submitted']??'')==='1'?($_SESSION['receipt']??null):null;
