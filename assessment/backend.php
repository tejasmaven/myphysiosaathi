<?php
ini_set('session.use_strict_mode','1');
session_name('MPS_ASSESSMENT');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/']);
session_start();
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
$domains=json_decode(file_get_contents(__DIR__.'/questions.json'),true,512,JSON_THROW_ON_ERROR);
$labels=['clinic_name'=>'Clinic name','doctor_name'=>'Dr full name','location'=>'Location / area / address','city'=>'City','mobile'=>'Mobile number','email'=>'Email address','beds'=>'Number of beds / treatment couches'];
$ratings=['1'=>'1 - Very low','2'=>'2 - Low','3'=>'3 - Moderate','4'=>'4 - High','5'=>'5 - Very high','unsure'=>'Not sure','na'=>'N/A'];
$values=array_fill_keys(array_keys($labels),'');$answers=[];$notes=[];$errors=[];$status='';
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
$configPath=getenv('ASSESSMENT_CONFIG_PATH') ?: __DIR__.'/config.local.php';
$config=is_file($configPath)?require $configPath:[];
$config=is_array($config)?$config:[];
$ready=($config['enabled']??false) && !empty($config['smtp_host']) && !empty($config['smtp_username']) && !empty($config['smtp_password']) && filter_var($config['admin_email']??'',FILTER_VALIDATE_EMAIL) && filter_var($config['from_email']??'',FILTER_VALIDATE_EMAIL);
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
            if(!preg_match('/^\d{1,4}$/',$values['beds']))$errors['beds']='Enter a whole number from 0 to 9999.';
            $postedAnswers=$_POST['answers']??[];$postedNotes=$_POST['notes']??[];
            if(!is_array($postedAnswers)||!is_array($postedNotes))throw new InvalidArgumentException('Invalid answers.');
            foreach($domains as $d)foreach($d['questions'] as $id=>$q){
                $answers[$id]=input_text($postedAnswers[$id]??'',10);
                $notes[$id]=input_text($postedNotes[$id]??'',600,true);
                if(!isset($ratings[$answers[$id]]))$errors[$id]='Choose one response for this question.';
            }
        } catch(InvalidArgumentException $e){$errors['form']='Some submitted information is invalid or too long. Please check your entries.';}
        if(!$errors){
            try {
                // Freeze validated answers after the first send attempt. Retry only failed recipients.
                if(!isset($_SESSION['pending'])){
                    if(!permit_submission($config))throw new RuntimeException('Too many submissions. Please try again in one hour.');
                    $submission=['id'=>strtoupper(bin2hex(random_bytes(6))),'submitted_at'=>(new DateTimeImmutable('now',new DateTimeZone('Asia/Kolkata')))->format('d M Y, H:i').' IST','fields'=>$values,'answers'=>$answers,'notes'=>$notes];
                    require_once __DIR__.'/pdf.php';
                    $_SESSION['pending']=['submission'=>$submission,'pdf'=>assessment_pdf($submission,$domains,$labels,$ratings),'admin_sent'=>false,'user_sent'=>false];
                }
                require_once __DIR__.'/mail.php';
                $pending=&$_SESSION['pending'];$failures=[];
                foreach(['admin','user'] as $target){
                    if($pending[$target.'_sent'])continue;
                    try{deliver_assessment($config,$pending['submission'],$target==='admin'?$config['admin_email']:$pending['submission']['fields']['email'],$target==='admin',$pending['pdf']);$pending[$target.'_sent']=true;}
                    catch(Throwable $e){$failures[]=$target;error_log('Assessment '.$pending['submission']['id'].' delivery failed: '.$target);}
                }
                if(!$failures){
                    $_SESSION['receipt']=['id'=>$pending['submission']['id'],'email'=>$pending['submission']['fields']['email']];
                    unset($_SESSION['pending']);$_SESSION['token']=bin2hex(random_bytes(24));$_SESSION['opened_at']=time();
                    header('Location: free-assesment.php?submitted=1',true,303);exit;
                }
                $errors['form']='Email delivery could not complete. Your answers are retained for this session. Use Retry email delivery below; copies already sent will not be sent again.';
            }catch(Throwable $e){error_log('Assessment processing failed');$errors['form']=$e->getMessage()==='Too many submissions. Please try again in one hour.'?$e->getMessage():'We could not process your assessment. Please try again or call +91 98256 47083.';}
        }
    }
}
if(isset($_SESSION['pending'])){$values=$_SESSION['pending']['submission']['fields'];$answers=$_SESSION['pending']['submission']['answers'];$notes=$_SESSION['pending']['submission']['notes'];}
$receipt=($_GET['submitted']??'')==='1'?($_SESSION['receipt']??null):null;
