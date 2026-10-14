<?php
require_once __DIR__.'/../assessment/report-store.php';
require_once __DIR__.'/../assessment/pdf.php';
function verify_storage($ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$base=sys_get_temp_dir().'/mps-storage-security-'.bin2hex(random_bytes(8));
mkdir($base,0700);mkdir($base.'/site',0700);
$oldRoot=$_SERVER['DOCUMENT_ROOT']??null;$oldServer=$_SERVER['SERVER_SOFTWARE']??null;
$_SERVER['DOCUMENT_ROOT']=$base.'/site';$_SERVER['SERVER_SOFTWARE']='Apache/2.4.62';
$config=['report_directory'=>$base.'/site/reports'];
try{
    $path=assessment_report_directory($config);
    verify_storage(file_get_contents($path.'/.htaccess')===assessment_report_deny_rules(),'Deny file created before records');
    verify_storage((fileperms($path)&0777)===0700,'Directory permissions');
    verify_storage(file_get_contents(__DIR__.'/../reports/.htaccess')===assessment_report_deny_rules(),'Shipped guard matches runtime guard');
    $rules=require __DIR__.'/../assessment/scoring-rules.php';$catalogue=require __DIR__.'/../assessment/recommendations.php';
    $domains=json_decode(file_get_contents(__DIR__.'/../assessment/questions.json'),true,512,JSON_THROW_ON_ERROR);
    $answers=[];foreach($domains as $domain)foreach($domain['questions'] as $id=>$question)$answers[$id]=3;
    $submission=['id'=>'test','submitted_at'=>'14 Oct 2026, 12:00 IST','submitted_at_iso'=>'2026-10-14T12:00:00+05:30','assessment_version'=>$rules['assessment_version'],
        'fields'=>['clinic_name'=>'Example Clinic','doctor_name'=>'Dr Example','location'=>'Example area','city'=>'Surat','mobile'=>'9876543210','email'=>'clinic@example.test','beds'=>1],'answers'=>$answers,'domain_notes'=>[]];
    $storageId=assessment_create_record($config,$submission,$domains,$rules,$catalogue);
    $sent=[];$record=assessment_process_record($config,$storageId,fn($report)=>customer_report_pdf($report,[]),function($submission,$recipient,$pdf)use(&$sent){$sent[]=$recipient;});
    verify_storage($record['reference']==='14102026-1' && preg_match('/^[A-F0-9]{32}$/D',$storageId),'Human reference separate from private ID');
    verify_storage($record['pdf']['status']==='generated' && $sent===['admin','user'],'Protected in-site generation and delivery');
    verify_storage((fileperms($path.'/'.$storageId.'.pdf')&0777)===0600,'PDF permissions');
    file_put_contents($path.'/.htaccess',"Require all granted\n");
    $failed=false;try{assessment_report_directory($config);}catch(RuntimeException $e){$failed=$e->getMessage()==='Report directory protection is invalid.';}
    verify_storage($failed,'Altered guard rejected');
    unlink($path.'/.htaccess');symlink($base.'/external-guard',$path.'/.htaccess');
    $failed=false;try{assessment_report_directory($config);}catch(RuntimeException $e){$failed=true;}
    verify_storage($failed,'Symlink guard rejected');unlink($path.'/.htaccess');
    $_SERVER['SERVER_SOFTWARE']='nginx';$failed=false;
    try{assessment_report_directory($config);}catch(RuntimeException $e){$failed=$e->getMessage()==='In-site report storage requires Apache protection.';}
    verify_storage($failed && !file_exists($path.'/.htaccess'),'Non-Apache in-site storage rejected');
    $_SERVER['SERVER_SOFTWARE']='Apache/2.4.62';
    foreach([$base.'/site',$base.'/site/other'] as $invalid){$failed=false;try{assessment_report_directory(['report_directory'=>$invalid]);}catch(RuntimeException $e){$failed=true;}verify_storage($failed,'Root and unrelated public folders rejected');}
    foreach(['../bad','14102026-1','ABC'] as $invalid){$failed=false;try{assessment_report_path($config,$invalid);}catch(InvalidArgumentException $e){$failed=true;}verify_storage($failed,'Traversal and public references rejected as storage IDs');}
    $_SERVER['SERVER_SOFTWARE']='nginx';verify_storage(assessment_report_directory(['report_directory'=>$base.'/private'])===$base.'/private','Outside-root path remains server independent');
}finally{
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as $file){if($file->isDir() && !$file->isLink())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($base);
    if($oldRoot===null)unset($_SERVER['DOCUMENT_ROOT']);else $_SERVER['DOCUMENT_ROOT']=$oldRoot;
    if($oldServer===null)unset($_SERVER['SERVER_SOFTWARE']);else $_SERVER['SERVER_SOFTWARE']=$oldServer;
}
echo "PASS: dedicated Apache report path, deny-file creation and validation, permissions, full report generation, private filenames, invalid paths and non-Apache rejection. HTTP enforcement must be checked on the deployed Apache server.\n";
