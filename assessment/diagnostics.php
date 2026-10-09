<?php
/** Safe operational logging. Never log answers, customer fields, credentials or raw exception text. */
function assessment_failure_code(Throwable $error): string {
    $codes=[
        'Private report storage unavailable.'=>'storage.unavailable',
        'Report storage must be outside the web document root.'=>'storage.public_path',
        'Report storage unavailable.'=>'storage.write',
        'Cannot secure report file.'=>'storage.permissions',
        'Incomplete report write.'=>'storage.write',
        'Cannot commit report file.'=>'storage.rename',
        'Reference counter unavailable.'=>'reference.permissions',
        'Invalid reference counter.'=>'reference.corrupt_counter',
        'Report lock unavailable.'=>'storage.lock',
        'Assessment reference already exists.'=>'reference.collision',
        'Invalid report reference.'=>'reference.invalid',
        'Stored report integrity check failed.'=>'storage.integrity',
        'Compact report page overflow.'=>'pdf.layout',
        'Compact table overflow.'=>'pdf.layout',
        'Domain content exceeds the compact report layout.'=>'pdf.layout',
        'Compact report layout exceeded the eight-page limit.'=>'pdf.layout',
        'Invalid PDF output.'=>'pdf.output',
        'Incomplete recommendation catalogue.'=>'catalogue.mismatch',
        'Expected 25 unique questions.'=>'catalogue.questions',
        'Interpretation bands do not cover the score.'=>'catalogue.scoring',
        'Rate limit storage unavailable'=>'rate_limit.storage',
        'Rate limit lock unavailable'=>'rate_limit.lock',
        'Too many submissions. Please try again in one hour.'=>'rate_limit.exceeded',
        'Call to undefined function iconv()'=>'runtime.iconv',
        'Call to undefined function gzcompress()'=>'runtime.zlib',
    ];
    if(isset($codes[$error->getMessage()]))return $codes[$error->getMessage()];
    if($error instanceof JsonException)return 'data.json';
    if($error instanceof InvalidArgumentException)return 'data.validation';
    if(strpos($error->getMessage(),'Failed opening required ')===0)return 'runtime.missing_file';
    return 'processing.unclassified';
}
function assessment_log_failure(Throwable $error,string $stage): string {
    $support='AS-'.strtoupper(bin2hex(random_bytes(5)));
    $stage=in_array($stage,['create_record','generate_or_deliver','load_pending'],true)?$stage:'processing';
    error_log('MPS_ASSESSMENT_FAILURE '.json_encode([
        'support'=>$support,'stage'=>$stage,'code'=>assessment_failure_code($error),
        'exception'=>get_class($error),'source'=>basename($error->getFile()),'line'=>$error->getLine(),
    ],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE));
    return $support;
}
