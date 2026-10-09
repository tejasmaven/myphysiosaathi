<?php
// Change the version whenever scoring or interpretation rules change.
return [
    'version'=>'1.0.0', 'assessment_version'=>'2026-10-09.1',
    'minimum_domain_numeric'=>3, 'minimum_overall_coverage'=>80,
    'important_controls'=>['C1','C2','C4','E1','E2','E3','E4','E5'],
    'bands'=>[
        ['below'=>2.0,'label'=>'Priority attention'],
        ['below'=>3.0,'label'=>'Significant gaps'],
        ['below'=>4.0,'label'=>'Working foundation'],
        ['below'=>4.5,'label'=>'Mostly reliable'],
        ['below'=>5.01,'label'=>'Strong reported process'],
    ],
];
