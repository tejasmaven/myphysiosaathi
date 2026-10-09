<?php
// Same-origin homepage enquiry endpoint. Reuses the assessment's private SMTP settings.
ini_set('session.use_strict_mode','1');
session_name('MPS_ENQUIRY');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/']);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
function respond(int $status,array $data): void { http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit; }
function value($v,int $limit,bool $multiline=false): string {
    if(!is_string($v)||!preg_match('//u',$v))throw new InvalidArgumentException();
    $v=trim($v);preg_match_all('/./us',$v,$matches);
    if(count($matches[0])>$limit)throw new InvalidArgumentException();
    return preg_replace($multiline?'/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/':'/[\x00-\x1F\x7F]/','',$v);
}
function rate_allowed(array $config): bool {
    $dir=$config['state_directory']??sys_get_temp_dir().'/myphysiosaathi-assessment';
    if(!is_dir($dir)&&!@mkdir($dir,0700,true))throw new RuntimeException();
    $fh=fopen($dir.'/enquiry-'.hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown').'.json','c+');
    if(!$fh||!flock($fh,LOCK_EX))throw new RuntimeException();
    $items=json_decode(stream_get_contents($fh),true)?:[];
    $items=array_values(array_filter($items,fn($time)=>is_int($time)&&$time>time()-3600));
    $allowed=count($items)<6;if($allowed)$items[]=time();
    ftruncate($fh,0);rewind($fh);fwrite($fh,json_encode($items));flock($fh,LOCK_UN);fclose($fh);return $allowed;
}
try {
    require_once __DIR__.'/assessment/config-loader.php';
    $config=assessment_load_config();
    $ready=assessment_config_ready($config);
    if(!isset($_SESSION['csrf'])){$_SESSION['csrf']=bin2hex(random_bytes(24));$_SESSION['opened_at']=time();}
    $method=$_SERVER['REQUEST_METHOD'];
    if($method==='GET')respond(200,['ok'=>true,'configured'=>(bool)$ready,'csrf'=>$_SESSION['csrf']]);
    if($method!=='POST'){header('Allow: GET, POST');respond(405,['ok'=>false,'message'=>'Method not allowed.']);}
    if(!$ready)respond(503,['ok'=>false,'message'=>'Online enquiries are temporarily unavailable. Please call +91 98256 47083.']);
    if((int)($_SERVER['CONTENT_LENGTH']??0)>40000)respond(413,['ok'=>false,'message'=>'Your message is too long.']);
    $input=json_decode(file_get_contents('php://input',false,null,0,40001),true,32);
    if(!is_array($input))respond(400,['ok'=>false,'message'=>'Invalid enquiry.']);
    if(!is_string($input['csrf']??null)||!hash_equals($_SESSION['csrf'],$input['csrf']))respond(403,['ok'=>false,'message'=>'Your form session expired. Reload the page and try again.']);
    if(!empty($input['website'])||time()-($_SESSION['opened_at']??time())<2)respond(422,['ok'=>false,'message'=>'Please review your enquiry and try again.']);
    $limits=['full_name'=>100,'clinic_name'=>150,'city'=>100,'mobile_number'=>20,'email'=>254,'enquiry_type'=>40,'message'=>5000];$fields=[];$errors=[];
    foreach($limits as $key=>$limit){
        try{$fields[$key]=value($input[$key]??'',$limit,$key==='message');}
        catch(InvalidArgumentException $e){$fields[$key]='';$errors[$key]='Please enter valid information within '.$limit.' characters.';}
        if($key!=='message'&&$fields[$key]==='')$errors[$key]='Please complete this field.';
    }
    if($fields['email']!==''&&!filter_var($fields['email'],FILTER_VALIDATE_EMAIL))$errors['email']='Enter a valid email address.';
    $digits=preg_replace('/\D/','',$fields['mobile_number']);
    if(!preg_match('/^[+\d ().-]+$/',$fields['mobile_number'])||strlen($digits)<7||strlen($digits)>15)$errors['mobile_number']='Enter a mobile number with 7–15 digits.';
    if(!in_array($fields['enquiry_type'],['Product demo','Free clinic workflow audit','Free clinic workflow assessment'],true))$errors['enquiry_type']='Select a valid enquiry type.';
    if($errors)respond(422,['ok'=>false,'message'=>'Please check the highlighted fields.','errors'=>$errors]);
    $requestId=$input['request_id']??'';
    if(!is_string($requestId)||!preg_match('/^[a-zA-Z0-9-]{16,80}$/',$requestId))respond(400,['ok'=>false,'message'=>'Reload this page before submitting.']);
    // A retry after an uncertain network response does not send the same email again.
    $hash=hash('sha256',json_encode($fields));
    $sent=$_SESSION['sent'][$requestId]??null;
    $needsAssessment=$fields['enquiry_type']!=='Product demo';
    if($sent && $sent['hash']!==$hash)respond(409,['ok'=>false,'message'=>'Reload the page to send a changed enquiry.']);
    // Older completed session entries have no per-recipient flags. Do not resend them.
    if($sent && !array_key_exists('admin_sent',$sent))respond(200,['ok'=>true,'message'=>'Your enquiry has been sent. Tejas will contact you using the details provided.']);
    if(!$sent){
        if(!rate_allowed($config))respond(429,['ok'=>false,'message'=>'Too many requests. Please try again in one hour or call Tejas.']);
        $_SESSION['sent'][$requestId]=['hash'=>$hash,'admin_sent'=>false,'assessment_sent'=>!$needsAssessment];
    }
    require_once __DIR__.'/assessment/mail.php';
    require_once __DIR__.'/includes/enquiry-mail.php';
    if(!$_SESSION['sent'][$requestId]['admin_sent']){
        $mail=assessment_mailer($config);
        $mail->addAddress($config['admin_email']);$mail->addReplyTo($fields['email'],$fields['full_name']);
        $mail->Subject='My Physio Saathi | '.$fields['enquiry_type'];
        $mail->Body="New website enquiry\n\n";
        foreach(['full_name'=>'Full name','clinic_name'=>'Clinic name','city'=>'City','mobile_number'=>'Mobile number','email'=>'Email address','enquiry_type'=>'Enquiry type','message'=>'Message'] as $key=>$label)$mail->Body.=$label.': '.$fields[$key]."\n";
        $mail->Body.="\nReceived: ".(new DateTimeImmutable('now',new DateTimeZone('Asia/Kolkata')))->format('d M Y, H:i').' IST';
        $mail->send();
        $_SESSION['sent'][$requestId]['admin_sent']=true;
    }
    if($needsAssessment && !$_SESSION['sent'][$requestId]['assessment_sent']){
        try{
            deliver_assessment_invitation($config,$fields);
            $_SESSION['sent'][$requestId]['assessment_sent']=true;
        }catch(Throwable $e){
            error_log('Assessment invitation delivery failed.');
            respond(503,['ok'=>false,'message'=>'Your enquiry has been received, but the assessment-link email could not be sent. Please retry this submission to resend the link, or call +91 98256 47083.']);
        }
    }
    if(count($_SESSION['sent'])>20){
        // Retain failed deliveries; only evict old completed requests.
        foreach($_SESSION['sent'] as $id=>$entry){if(count($_SESSION['sent'])<=20)break;if(($entry['admin_sent']??true)&&($entry['assessment_sent']??true))unset($_SESSION['sent'][$id]);}
    }
    respond(200,['ok'=>true,'message'=>$needsAssessment?'Your enquiry has been sent. We have also emailed your clinic assessment link. Check your inbox and spam folder.':'Your enquiry has been sent. Tejas will contact you using the details provided.']);
}catch(Throwable $e){error_log('Homepage enquiry delivery failed.');respond(500,['ok'=>false,'message'=>'Your request could not be sent. Please try again later or call +91 98256 47083.']);}
