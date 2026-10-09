<?php
require_once __DIR__.'/report.php';
/** Private storage must be outside the web document root, even on non-Apache servers. */
function assessment_report_directory(array $config): string {
    $path=$config['report_directory'];
    if(!is_dir($path) && !@mkdir($path,0700,true))throw new RuntimeException('Private report storage unavailable.');
    $dir=realpath($path);$root=realpath($_SERVER['DOCUMENT_ROOT']??dirname(__DIR__));
    if(!$dir || !$root || $dir===$root || strncmp($dir,$root.DIRECTORY_SEPARATOR,strlen($root)+1)===0)throw new RuntimeException('Report storage must be outside the web document root.');
    @chmod($dir,0700);return $dir;
}
function assessment_report_path(array $config,string $ref): string {
    if(!preg_match('/^[A-F0-9]{32}$/D',$ref))throw new InvalidArgumentException('Invalid report reference.');
    return assessment_report_directory($config).'/'.$ref;
}
function assessment_atomic_write(string $path,string $data): void {
    $temp=$path.'.'.bin2hex(random_bytes(8)).'.tmp';
    $handle=@fopen($temp,'xb');if(!$handle)throw new RuntimeException('Report storage unavailable.');
    try{
        if(!chmod($temp,0600))throw new RuntimeException('Cannot secure report file.');
        $offset=0;while($offset<strlen($data)){$written=fwrite($handle,substr($data,$offset));if(!$written)throw new RuntimeException('Incomplete report write.');$offset+=$written;}
        fflush($handle);fclose($handle);$handle=null;
        if(!rename($temp,$path))throw new RuntimeException('Cannot commit report file.');
    }finally{if(is_resource($handle))fclose($handle);if(is_file($temp))unlink($temp);}
}
function assessment_store_record(string $base,array $record): void {
    assessment_atomic_write($base.'.json',json_encode($record,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}
function assessment_read_record(array $config,string $ref): array {
    return json_decode(file_get_contents(assessment_report_path($config,$ref).'.json'),true,512,JSON_THROW_ON_ERROR);
}
/** Allocate a human reference in India time. The durable counter is never a public filename. */
function assessment_allocate_reference(array $config,DateTimeImmutable $submitted): string {
    $day=$submitted->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('dmY');
    $path=assessment_report_directory($config).'/reference-'.$day.'.seq';
    $lock=fopen($path.'.lock','c');
    if(!$lock || !chmod($path.'.lock',0600) || !flock($lock,LOCK_EX))throw new RuntimeException('Reference counter unavailable.');
    try{
        $current=is_file($path)?file_get_contents($path):'0';
        if(!is_string($current) || !preg_match('/^(0|[1-9][0-9]*)$/D',$current) || strlen($current)>15)throw new RuntimeException('Invalid reference counter.');
        $next=(int)$current+1;
        assessment_atomic_write($path,(string)$next);
        return $day.'-'.$next;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function assessment_create_record(array $config,array $submission,array $domains,array $rules,array $catalogue): string {
    $ref=strtoupper(bin2hex(random_bytes(16)));$base=assessment_report_path($config,$ref);
    // Validate before allocating. Retries use this private ID and the same public reference.
    assessment_build_report($submission,$domains,$rules,$catalogue);
    $submitted=new DateTimeImmutable($submission['submitted_at_iso']??$submission['submitted_at'],new DateTimeZone('Asia/Kolkata'));
    $submission['storage_id']=$ref;
    $submission['id']=assessment_allocate_reference($config,$submitted);
    $lock=fopen($base.'.lock','x');if(!$lock)throw new RuntimeException('Assessment reference already exists.');chmod($base.'.lock',0600);fclose($lock);
    $report=assessment_build_report($submission,$domains,$rules,$catalogue);
    $record=['reference'=>$submission['id'],'storage_id'=>$ref,'submission'=>$report['submission'],'versions'=>$report['versions'],
        'snapshots'=>['domains'=>$domains,'rules'=>$rules,'catalogue'=>$catalogue],
        'report'=>$report,'pdf'=>['status'=>'pending','sha256'=>null],'email'=>['admin'=>['status'=>'pending'],'user'=>['status'=>'pending']]];
    assessment_store_record($base,$record);return $ref;
}
/** Callable seams let regression tests exercise the real persistence and retry lifecycle. */
function assessment_process_record(array $config,string $ref,callable $generator,callable $sender): array {
    $base=assessment_report_path($config,$ref);$lock=fopen($base.'.lock','c');
    if(!$lock || !flock($lock,LOCK_EX))throw new RuntimeException('Report lock unavailable.');
    try{
        $record=assessment_read_record($config,$ref);
        if($record['pdf']['status']!=='generated'){
            try{
                $pdf=$generator($record['report']);
                if(strncmp($pdf,'%PDF-',5)!==0)throw new RuntimeException('Invalid PDF output.');
                assessment_atomic_write($base.'.pdf',$pdf);
                $record['pdf']=['status'=>'generated','sha256'=>hash('sha256',$pdf),'generated_at'=>gmdate('c')];
            }catch(Throwable $e){$record['pdf']['status']='failed';assessment_store_record($base,$record);throw $e;}
            assessment_store_record($base,$record);
        }
        $pdf=file_get_contents($base.'.pdf');
        if(!is_string($pdf) || !hash_equals($record['pdf']['sha256'],hash('sha256',$pdf)))throw new RuntimeException('Stored report integrity check failed.');
        foreach(['admin','user'] as $target){
            if($record['email'][$target]['status']==='sent')continue;
            try{
                $sender($record['submission'],$target,$pdf);
                $record['email'][$target]=['status'=>'sent','sent_at'=>gmdate('c')];
            }catch(Throwable $e){$record['email'][$target]=['status'=>'failed','attempted_at'=>gmdate('c')];error_log('Assessment '.$ref.' delivery failed: '.$target);}
            assessment_store_record($base,$record);
        }
        return $record;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
