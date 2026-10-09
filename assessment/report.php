<?php
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
    foreach(['clinic_name','doctor_name','location','city','mobile','email','beds'] as $key)if(!isset($submission['fields'][$key]) || !is_string($submission['fields'][$key]) || trim($submission['fields'][$key])==='')throw new InvalidArgumentException('Missing clinic field.');
    $submission['notes']=$submission['notes']??[];
    if(!is_array($submission['notes']))throw new InvalidArgumentException('Invalid question notes.');
    foreach($submission['notes'] as $note)if(!is_string($note) || !preg_match('//u',$note) || strlen($note)>600)throw new InvalidArgumentException('Invalid question note.');
    $ids=[];foreach($domains as $domain)foreach($domain['questions'] as $id=>$title)$ids[]=$id;
    if(count($ids)!==25 || count(array_unique($ids))!==25)throw new InvalidArgumentException('Expected 25 unique questions.');
    if(array_diff(array_keys($submission['notes']),$ids))throw new InvalidArgumentException('Unexpected note question ID.');
    if(array_diff(array_keys($submission['answers']),$ids))throw new InvalidArgumentException('Unexpected question ID.');
    $answers=[];foreach($submission['answers'] as $id=>$value)$answers[$id]=assessment_normalize_answer($value);
    $submission['answers']=$answers;
    $submission['assessment_version']=$submission['assessment_version']??$rules['assessment_version'];
    if(!is_string($submission['assessment_version']) || $submission['assessment_version']==='')throw new InvalidArgumentException('Invalid assessment version.');
    $counts=['numeric'=>0,'unknown'=>0,'na'=>0,'missing'=>0];$sum=0;$scorecard=[];$findings=[];$gaps=[];$confirm=[];$applicability=[];
    $categories=[1=>'High priority; establish the process',2=>'Improvement priority; standardise the process',3=>'Strengthen consistency',4=>'Maintain and refine',5=>'Reported strength'];
    foreach($domains as $domain){
        $dc=['numeric'=>0,'unknown'=>0,'na'=>0,'missing'=>0];$ds=0;
        foreach($domain['questions'] as $id=>$question){
            $entry=$catalogue['questions'][$id]??null;
            if(!$entry || $entry['id']!==$id || $entry['domain']!==$domain['code'])throw new InvalidArgumentException('Incomplete recommendation catalogue.');
            $value=$answers[$id]??'missing';$numeric=is_int($value);$kind=$numeric?'numeric':$value;
            $dc[$kind]++;$counts[$kind]++;if($numeric){$ds+=$value;$sum+=$value;}
            $important=in_array($id,$rules['important_controls'],true);
            $finding=['id'=>$id,'domain'=>$domain['code'],'question'=>$question,'title'=>$entry['title'],'response'=>$value,'response_label'=>assessment_response_label($value),'note'=>$submission['notes'][$id]??'',
                'important_control'=>$important,'control_gap'=>$important && $numeric && $value<=2,
                'category'=>$numeric?$categories[$value]:'Requires confirmation',
                'consequence'=>$numeric?$entry['consequence']:'No failure is established by this response.',
                'recommendation'=>$numeric?($value<=3?$entry['recommendations'][$value]:$entry['maintenance']):$entry['verification'],
                'role'=>$entry['role'],'timing'=>$entry['timing'],'completion_check'=>$entry['completion_check']];
            // Product-specific additions require a deliberately configured, verified allowlist.
            if(isset($entry['software_capability'],$entry['software_note']) && in_array($entry['software_capability'],$catalogue['verified_capability_allowlist'],true))$finding['software_note']=$entry['software_note'];
            if($value==='na'){
                $finding['recommendation']=$id==='D5'?'Confirm why routine owner review is considered inapplicable to this clinic. Record the reason and review whether an owner review is still needed.':'Confirm why this question is considered inapplicable and record the reason before accepting the exclusion.';
                $finding['completion_check']='Record the applicability decision and reviewer for '.$id.'.';
                $applicability[]=$finding;
            }elseif(!$numeric)$confirm[]=$finding;
            elseif($value<=3){$finding['priority_group']=$important&&$value<=2?0:($value===1?1:($value===2?2:3));$gaps[]=$finding;}
            $findings[$id]=$finding;
        }
        $average=$dc['numeric']?$ds/$dc['numeric']:null;
        $scorecard[$domain['code']]=['code'=>$domain['code'],'title'=>$domain['title'],'counts'=>$dc,'numeric_sum'=>$ds,'average'=>$average,'interpretation'=>$dc['numeric']>=$rules['minimum_domain_numeric']?assessment_interpret($average,$rules):'Insufficient numeric responses','unresolved_count'=>$dc['unknown']+$dc['missing']+$dc['na']];
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
    return ['submission'=>$submission,'versions'=>['assessment'=>$submission['assessment_version'],'scoring'=>$rules['version'],'recommendations'=>$catalogue['version']],
        'status'=>($counts['unknown']+$counts['missing']+$counts['na'])?'Provisional':'Complete self-reported',
        'counts'=>$counts,'numeric_sum'=>$sum,'overall_average'=>$average,'coverage'=>$coverage,
        'coverage_label'=>$counts['na']?'Coverage excluding respondent-selected N/A answers (unreviewed)':'Numeric response coverage',
        'overall_interpretation'=>$eligible?assessment_interpret($average,$rules):'Insufficient information for an overall interpretation','overall_eligible'=>$eligible,
        'domains'=>$scorecard,'findings'=>$findings,'priority_findings'=>array_slice($gaps,0,3),'items_to_confirm'=>$confirm,'applicability_review'=>$applicability,'action_plan'=>$actions,
        'strongest_domain'=>$strongest,'weakest_domain'=>$weakest,
        'disclaimer'=>"This report is based on the clinic's self-reported answers. It identifies process improvement opportunities and is not a clinical, security or compliance certification."];
}
