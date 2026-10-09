<?php
/** Shared server-side SMTP configuration for both assessment and homepage enquiries. */
function assessment_bool($value): bool {
    if(is_bool($value))return $value;
    return filter_var($value,FILTER_VALIDATE_BOOLEAN) === true;
}
function assessment_load_config(): array {
    $config=[];
    $explicit=getenv('ASSESSMENT_CONFIG_PATH');
    $explicit=is_string($explicit)?trim($explicit):'';
    if($explicit!==''){
        // An explicitly chosen path must work; never silently switch to another credential set.
        $paths=[$explicit];
    } else {
        $paths=[__DIR__.'/config.local.php',__DIR__.'/config.php'];
    }
    foreach($paths as $path){
        if(!is_file($path))continue;
        try{$loaded=require $path;}
        catch(Throwable $e){error_log('SMTP configuration file could not be loaded.');return ['enabled'=>false];}
        if(!is_array($loaded)){error_log('SMTP configuration file must return an array.');return ['enabled'=>false];}
        $config=$loaded;break;
    }
    if($explicit!==''&&!is_file($explicit)){error_log('ASSESSMENT_CONFIG_PATH does not point to an existing file.');return ['enabled'=>false];}
    $environment=[
        'ASSESSMENT_ENABLED'=>'enabled',
        'SMTP_HOST'=>'smtp_host',
        'SMTP_PORT'=>'smtp_port',
        'SMTP_ENCRYPTION'=>'smtp_encryption',
        'SMTP_AUTH'=>'smtp_auth',
        'SMTP_USERNAME'=>'smtp_username',
        'SMTP_PASSWORD'=>'smtp_password',
        'SMTP_FROM_EMAIL'=>'from_email',
        'SMTP_FROM_NAME'=>'from_name',
        'SMTP_ADMIN_EMAIL'=>'admin_email',
        'ASSESSMENT_STATE_DIRECTORY'=>'state_directory',
    ];
    foreach($environment as $name=>$key){
        $value=getenv($name);
        if($value!==false)$config[$key]=$value;
    }
    $config['enabled']=assessment_bool($config['enabled']??false);
    $config['smtp_auth']=assessment_bool($config['smtp_auth']??true);
    $config['smtp_encryption']=strtolower(trim((string)($config['smtp_encryption']??'tls')));
    $config['smtp_host']=trim((string)($config['smtp_host']??'smtp.gmail.com'));
    $config['smtp_port']=(int)($config['smtp_port']??($config['smtp_encryption']==='ssl'?465:587));
    $config['smtp_username']=trim((string)($config['smtp_username']??''));
    $config['smtp_password']=(string)($config['smtp_password']??'');
    $config['from_email']=trim((string)($config['from_email']??''));
    $config['admin_email']=trim((string)($config['admin_email']??''));
    $config['from_name']=trim((string)($config['from_name']??'My Physio Saathi'));
    $config['state_directory']=(string)($config['state_directory']??sys_get_temp_dir().'/myphysiosaathi-assessment');
    return $config;
}
function assessment_config_ready(array $config): bool {
    return ($config['enabled']??false) === true
        && !empty($config['smtp_host'])
        && (!($config['smtp_auth']??true)||(!empty($config['smtp_username'])&&!empty($config['smtp_password'])))
        && ($config['smtp_port']??0)>0 && $config['smtp_port']<=65535
        && in_array($config['smtp_encryption']??null,['tls','ssl',''],true)
        && (bool)filter_var($config['admin_email']??'',FILTER_VALIDATE_EMAIL)
        && (bool)filter_var($config['from_email']??'',FILTER_VALIDATE_EMAIL);
}
