<?php
/** Presentation planning only: never modifies scoring, submitted facts or priorities. */
function assessment_add_roadmap(array $report,array $catalogue): array {
    $metrics=$catalogue['measurement_catalogue'];$plans=[];$register=[];
    foreach($report['findings'] as $id=>$f){
        $metric=$metrics['questions'][$id]??null;if(!$metric)throw new InvalidArgumentException('Missing planning metric.');
        $mode=is_int($f['response'])?($f['response']<=3?'Improve':'Maintain and validate'):($f['response']==='na'?'Applicability review':'Confirm before planning');
        $metric['id']=$id;$metric['domain']=$f['domain'];$metric['response_label']=$f['response_label'];$metric['mode']=$mode;$metric['evidence_label']=$f['evidence_label'];$metric['role']=$f['role'];
        if($f['response']==='na')$metric['target_for_report']='Applicability must be reviewed before adopting this KPI or target.';
        elseif(!is_int($f['response']))$metric['target_for_report']='Verify the process and establish a baseline before agreeing a target. Candidate: '.$metric['proposed_target'];
        else $metric['target_for_report']=$metric['proposed_target'];
        $register[$id]=$metric;
    }
    foreach($report['domains'] as $code=>$domain){
        $findings=array_values(array_filter($report['findings'],fn($f)=>$f['domain']===$code));
        usort($findings,function($a,$b){
            $group=fn($f)=>$f['control_gap']?0:(!is_int($f['response'])?($f['important_control']?1:3):($f['response']<=3?2:4));
            return ($group($a)<=>$group($b))?:((is_int($a['response'])&&is_int($b['response']))?($a['response']<=>$b['response']):0)?:strcmp($a['id'],$b['id']);
        });
        $selected=array_slice($findings,0,3);$plans[$code]=['observations'=>$selected,'metrics'=>array_map(fn($f)=>$register[$f['id']],$selected)];
    }
    $report['versions']['roadmap']=$metrics['version'];$report['versions']['presentation']='4.0.0';
    $report['domain_plans']=$plans;$report['kpi_register']=$register;
    $report['roadmap_90']=[
        ['phase'=>'Within 30 days','theme'=>'Confirm and establish','action'=>'Confirm unknown answers and N/A applicability. Choose the supported priorities, agree responsibilities and measure baseline KPIs before changing the process.','evidence'=>'Applicability decisions, named responsibilities, baseline numerator/denominator or test result, and an agreed action list.'],
        ['phase'=>'Days 31-60','theme'=>'Practise and check','action'=>'Use the agreed process in daily clinic work. Review a small documented sample, address exceptions and help staff practise the steps.','evidence'=>'Dated sample checks, completed checklists and recorded exceptions with corrective actions.'],
        ['phase'=>'Days 61-90','theme'=>'Review and sustain','action'=>'Repeat the same measurement and compare it with the baseline and the target the clinic actually agreed. Keep effective routines and revise actions that have not worked.','evidence'=>'Day-90 actuals, comparable evidence, remaining gaps and an owner-approved next review date.'],
    ];
    $report['day90_review']=array_map(fn($f)=>['id'=>$f['id'],'outcome'=>$register[$f['id']]['kra'],'evidence'=>$f['completion_check'],'baseline'=>null,'agreed_target'=>null,'actual'=>null,'status'=>'Not yet reviewed'],$report['action_plan']);
    return $report;
}
