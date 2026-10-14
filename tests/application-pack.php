<?php
declare(strict_types=1);
require_once __DIR__.'/../private/Core.php';require_once __DIR__.'/../private/SyntheticCv.php';
$f=ApplyPilot\syntheticCvFixture();$cv=ApplyPilot\validateCv($f['cv'],$f['resume']);$count=0;
function checkPack(bool $v,string $label):void{global $count;if(!$v)throw new RuntimeException($label);$count++;echo "PASS $label\n";}
function badPack($pack,string $label):void{global $f,$cv;try{ApplyPilot\validateApplicationPack($pack,$cv,$f['resume'],$f['job_description']);}catch(ApplyPilot\Problem $e){checkPack($e->status===502,$label);return;}throw new RuntimeException($label);}
$p=ApplyPilot\validateApplicationPack($f['application_pack'],$cv,$f['resume'],$f['job_description']);checkPack(count($p['interview_answers'])===3,'Full grounded application pack accepted');
badPack(null,'Missing pack rejected before purchase');$bad=$p;$bad['target']['company']='Invented Corporation';badPack($bad,'Fabricated target employer rejected');
$bad=$p;$bad['cover_letter'][0]['text'].=' I improved delivery by 45%.';badPack($bad,'Unsupported cover-letter metric rejected');
$bad=$p;$bad['interview_answers'][0]['quotes']=['Invented experience with SAP and Python.'];badPack($bad,'Untraceable interview evidence rejected');
$bad=$p;$bad['changes'][0]['revised']='Led global operations and managed all suppliers.';badPack($bad,'Preview not matching actual CV rejected');
$bad=$p;$bad['action_plan'][0]['job_quote']='Must have a medical qualification.';badPack($bad,'Invented job requirement rejected');
$bad=$p;$bad['interview_answers']=[];badPack($bad,'Empty interview preparation rejected');
$extra=$p;$extra['secret']='must strip';$clean=ApplyPilot\validateApplicationPack($extra,$cv,$f['resume'],$f['job_description']);checkPack(!isset($clean['secret']),'Unknown provider fields stripped');
$repair=$f['cv'];$repair['skills'][0]=['text'=>'Excel','quote'=>'Coordinated supplier communication and weekly delivery schedules.'];$fixed=ApplyPilot\validateCv($repair,$f['resume']);checkPack($fixed['skills'][0]['quote']==='Excel','Misassigned skill citation repaired only with literal CV evidence');
checkPack(ApplyPilot\cvEvidence('supplier communication and weekly delivery schedules',"supplier communication and weekly\n delivery schedules")==="supplier communication and weekly\n delivery schedules",'Whitespace-only quotation repair returns the exact source span');
echo "APPLICATION PACK RESULT $count checks passed\n";
