<?php
require_once __DIR__.'/../assessment/diagnostics.php';
$path=sys_get_temp_dir().'/mps-diagnostics-'.bin2hex(random_bytes(8)).'.log';
$previous=ini_get('error_log');ini_set('error_log',$path);
try{
    foreach(['Report storage must be outside the web document root.'=>'storage.public_path',
        'Compact table overflow.'=>'pdf.layout',
        'Incomplete recommendation catalogue.'=>'catalogue.mismatch',
        'Call to undefined function iconv()'=>'runtime.iconv',
        'Clinic secret; SMTP password secret'=>'processing.unclassified'] as $message=>$code){
        $error=new RuntimeException($message);
        if(assessment_failure_code($error)!==$code)throw new RuntimeException('Wrong failure classification.');
        $support=assessment_log_failure($error,'create_record');
        if(!preg_match('/^AS-[A-F0-9]{10}$/D',$support))throw new RuntimeException('Invalid support code.');
    }
    $text=file_get_contents($path);
    if(strpos($text,'secret')!==false || substr_count($text,'MPS_ASSESSMENT_FAILURE')!==5)throw new RuntimeException('Unsafe diagnostics.');
}finally{ini_set('error_log',$previous);if(is_file($path))unlink($path);}
echo "PASS: safe error categories, correlated support codes and no raw customer/credential text.\n";
