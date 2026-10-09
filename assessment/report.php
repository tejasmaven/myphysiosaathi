<?php
require_once __DIR__.'/roadmap.php';
function assessment_normalize_beds($value): int {
    if(is_int($value) && $value>=0)return $value;
    if(is_string($value) && preg_match('/^[0-9]+$/D',$value)){
        $digits=ltrim($value,'0');if($digits==='')return 0;
        $max=(string)PHP_INT_MAX;if(strlen($digits)<strlen($max) || (strlen($digits)===strlen($max) && strcmp($digits,$max)<=0))return (int)$digits;
    }
    throw new InvalidArgumentException('Beds must be a non-negative integer.');
}
function assessment_validate_domain_note($value): void {
    if(!is_string($value) || !preg_match('//u',$value) || preg_match_all('/./us',$value)>2000)throw new InvalidArgumentException('Domain explanation must be plain text of at most 2,000 characters.');
}
/** Deterministic report engine. No SMTP, filesystem, or current-time dependency. */
function assessment_normalize_answer($value) {
    if(is_int($value) && $value>=1 && $value<=5)return $value;
    if(is_string($value)){
        if(preg_match('/^[1-5]$/D',$value))return (int)$value;
        if($value==='unsure' || $value==='unknown')return 'unknown';
        if($value==='na')return 'na';
    }
    throw new InvalidArgumentException('Unsupported assessment response.');
}
function assessment_interpret(float $average,array $rules): string {
    foreach($rules['bands'] as $band)if($average<$band['below'])return $band['label'];
    throw new InvalidArgumentException('Interpretation bands do not cover the score.');
}
function assessment_response_label($value): string {
    if(is_int($value))return $value.' / 5';
    return ['unknown'=>'Unknown / not sure','na'=>'N/A (respondent-selected, unreviewed)','missing'=>'Missing response'][$value];
}
function assessment_build_report(array $submission,array $domains,array $rules,array $catalogue): array {
    foreach(['id','submitted_at'] as $key)if(!isset($submission[$key]) || !is_string($submission[$key]) || $submission[$key]==='')throw new InvalidArgumentException('Missing submission metadata.');
    if(!isset($submission['fields'],$submission['answers']) || !is_array($submission['fields']) || !is_array($submission['answers']))throw new InvalidArgumentException('Invalid structured submission.');
    foreach(['clinic_name','doctor_name','location','city','mobile','email'] as $key)if(!isset($submission['fields'][$key]) || !is_string($submission['fields'][$key]) || trim($submission['fields'][$key])==='')throw new InvalidArgumentException('Missing clinic field.');
    if(!filter_var($submission['fields']['email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Invalid clinic email.');
    $submission['fields']['beds']=assessment_normalize_beds($submission['fields']['beds']??null);
    $domainNotes=$submission['domain_notes']??[];
    if(!is_array($domainNotes) || array_diff(array_keys($domainNotes),array_column($domains,'code')))throw new InvalidArgumentException('Invalid domain notes.');
    $submission['domain_notes']=[];
    foreach($domains as $domain){$code=$domain['code'];$note=$domainNotes[$code]??'';assessment_validate_domain_note($note);$submission['domain_notes'][$code]=$note;}
    $ids=[];foreach($domains as $domain)foreach($domain['questions'] as $id=>$title)$ids[]=$id;
    if(count($ids)!==25 || count(array_unique($ids))!==25)throw new InvalidArgumentException('Expected 25 unique questions.');
    if(count($submission['answers'])!==25 || array_diff($ids,array_keys($submission['answers'])) || array_diff(array_keys($submission['answers']),$ids))throw new InvalidArgumentException('Exactly 25 expected question IDs are required.');
    $answers=[];foreach($submission['answers'] as $id=>$value)$answers[$id]=assessment_normalize_answer($value);
    $submission['answers']=$answers;
    $submission['assessment_version']=$submission['assessment_version']??$rules['assessment_version'];
    if(!is_string($submission['assessment_version']) || $submission['assessment_version']==='')throw new InvalidArgumentException('Invalid assessment version.');
    $counts=['numeric'=>0,'unknown'=>0,'na'=>0,'missing'=>0];$sum=0;$scorecard=[];$findings=[];$gaps=[];$confirm=[];$applicability=[];
    $categories=[1=>'Establish the routine',2=>'Make it consistent and assign responsibility',3=>'Add completion checks and address exceptions',4=>'Maintain checks and review recurring gaps',5=>'Reported strength; maintain periodic review'];
    foreach($domains as $domain){
        $dc=['numeric'=>0,'unknown'=>0,'na'=>0,'missing'=>0];$ds=0;
        foreach($domain['questions'] as $id=>$question){
            $entry=$catalogue['questions'][$id]??null;
            if(!$entry || $entry['id']!==$id || $entry['domain']!==$domain['code'] || $entry['question']!==$question || $entry['control']!==in_array($id,$rules['important_controls'],true))throw new InvalidArgumentException('Incomplete recommendation catalogue.');
            $value=$answers[$id]??'missing';$numeric=is_int($value);$kind=$numeric?'numeric':$value;
            $dc[$kind]++;$counts[$kind]++;if($numeric){$ds+=$value;$sum+=$value;}
            $important=in_array($id,$rules['important_controls'],true);
            $finding=['id'=>$id,'domain'=>$domain['code'],'question'=>$question,'title'=>$entry['title'],'response'=>$value,'response_label'=>assessment_response_label($value),'evidence_label'=>($numeric && $value>=4)?(trim($submission['domain_notes'][$domain['code']])===''?'Reported score, example not provided':'Domain explanation supplied; not independently verified'):null,
                'important_control'=>$important,'control_gap'=>$important && $numeric && $value<=2,
                'category'=>$numeric?$categories[$value]:'Requires confirmation',
                'consequence'=>$numeric?$entry['consequence']:'No failure is established by this response.',
                'recommendation'=>$numeric?$entry['recommendations'][$value]:$entry['verification'],
                'role'=>$entry['role'],'timing'=>$entry['timing'],'completion_check'=>$entry['completion_check']];
            // Product-specific additions require a deliberately configured, verified allowlist.
            if(isset($entry['software_capability'],$entry['software_note']) && in_array($entry['software_capability'],$catalogue['verified_capability_allowlist'],true))$finding['software_note']=$entry['software_note'];
            if($value==='na'){
                $finding['recommendation']=$entry['na_review'];
                $finding['completion_check']='Record the applicability decision and reviewer for '.$id.'.';
                $applicability[]=$finding;
            }elseif(!$numeric)$confirm[]=$finding;
            elseif($value<=3){$finding['priority_group']=$important&&$value<=2?0:($value===1?1:($value===2?2:3));$gaps[]=$finding;}
            $findings[$id]=$finding;
        }
        $average=$dc['numeric']?$ds/$dc['numeric']:null;
        $scorecard[$domain['code']]=['code'=>$domain['code'],'title'=>$domain['title'],'counts'=>$dc,'numeric_sum'=>$ds,'average'=>$average,'interpretation'=>$dc['numeric']>=$rules['minimum_domain_numeric']?assessment_interpret($average,$rules):'Insufficient information','unresolved_count'=>$dc['unknown']+$dc['missing']+$dc['na']];
    }
    usort($gaps,fn($a,$b)=>($a['priority_group']<=>$b['priority_group'])?:($a['response']<=>$b['response'])?:strcmp($a['id'],$b['id']));
    usort($confirm,fn($a,$b)=>($b['important_control']<=>$a['important_control'])?:strcmp($a['id'],$b['id']));
    $denominator=25-$counts['na'];$coverage=$denominator?$counts['numeric']/$denominator*100:null;
    $average=$counts['numeric']?$sum/$counts['numeric']:null;
    $eligible=$coverage!==null && $coverage>=$rules['minimum_overall_coverage'];
    foreach($scorecard as $d)if($d['counts']['numeric']<$rules['minimum_domain_numeric'])$eligible=false;
    $scored=array_values(array_filter($scorecard,fn($d)=>$d['counts']['numeric']>=$rules['minimum_domain_numeric']));
    usort($scored,fn($a,$b)=>($b['average']<=>$a['average'])?:strcmp($a['code'],$b['code']));
    $strongest=$scored[0]??null;
    usort($scored,fn($a,$b)=>($a['average']<=>$b['average'])?:strcmp($a['code'],$b['code']));$weakest=$scored[0]??null;
    // Reserve space for important verification and applicability tasks, not just numeric gaps.
    $actions=array_slice($gaps,0,3);
    foreach($confirm as $f)if($f['important_control'] && count($actions)<5)$actions[]=$f;
    foreach($applicability as $f)if(count($actions)<5)$actions[]=$f;
    foreach(array_merge($confirm,array_slice($gaps,3)) as $f){if(count($actions)>=5)break;if(!in_array($f['id'],array_column($actions,'id'),true))$actions[]=$f;}
    $report = ['rating_scale'=>$rules['rating_scale'],'submission'=>$submission,'versions'=>['questions'=>$submission['assessment_version'],'assessment'=>$submission['assessment_version'],'scoring'=>$rules['version'],'recommendations'=>$catalogue['version']],
        'status'=>!$eligible?'Insufficient information':(($counts['unknown']+$counts['na'])?'Provisional':'Complete self-reported assessment'),
        'counts'=>$counts,'numeric_sum'=>$sum,'overall_average'=>$average,'coverage'=>$coverage,
        'coverage_label'=>'Numeric coverage excluding respondent-selected N/A',
        'overall_interpretation'=>$eligible?assessment_interpret($average,$rules):'Insufficient information','overall_eligible'=>$eligible,
        'domains'=>$scorecard,'findings'=>$findings,'priority_findings'=>array_slice($gaps,0,3),'items_to_confirm'=>$confirm,'applicability_review'=>$applicability,'action_plan'=>$actions,
        'strongest_domain'=>$strongest,'weakest_domain'=>$weakest,
        'disclaimer'=>"This report is based on the clinic's self-reported answers. It identifies process improvement opportunities and is not a clinical, security or compliance certification."];
    return assessment_add_roadmap($report,$catalogue);
}
