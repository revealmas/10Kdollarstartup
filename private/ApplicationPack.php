<?php
declare(strict_types=1);
namespace ApplyPilot;
// Allowlisted, bounded output. Evidence checks establish traceability, not semantic truth.
function interviewEvidenceAnswer(array $quotes):string {
    $parts=[];
    foreach(array_unique($quotes)as$q){
        $q=trim($q);
        if(preg_match('/^(?:I|My|We)\b/u',$q))$parts[]=$q;
        elseif(preg_match('/^(Coordinated|Prepared|Maintained|Organised|Organized|Developed|Managed|Built|Created|Led|Supported|Worked|Delivered|Analysed|Analyzed|Tracked|Wrote|Designed|Implemented|Reviewed|Monitored|Handled|Assisted|Conducted|Processed|Resolved|Tested|Collaborated|Communicated|Improved|Reduced|Increased|Achieved|Established|Launched|Oversaw)\b/u',$q,$m))$parts[]='I '.lcfirst($q);
        else $parts[]='My CV records: “'.$q.'”';
    }
    return implode(' ',$parts);
}
function validateApplicationPack($pack,array $cv,string $source,string $job):array {
    if(!is_array($pack))throw new Problem(502,'The application pack is incomplete. No payment is required.');
    $target=$pack['target']??[];$quote=cvEvidence($target['quote']??null,$job);
    $out=['target'=>['role'=>cvLiteral($target['role']??null,$quote),'company'=>cvLiteral($target['company']??null,$quote),'quote'=>$quote]];
    if($out['target']['role']==='')throw new Problem(502,'The target role could not be verified. No payment is required.');
    foreach(['cover_letter'=>[2,3],'interview_answers'=>[3,5],'action_plan'=>[0,5],'changes'=>[1,5]]as$key=>[$min,$max]){
        if(!is_array($pack[$key]??null)||!array_is_list($pack[$key])||count($pack[$key])<$min||count($pack[$key])>$max)throw new Problem(502,'The application pack is incomplete. No payment is required.');
        $out[$key]=[];
    }
    foreach($pack['cover_letter']as$p){
        if(!is_array($p)||!is_array($p['quotes']??null))throw new Problem(502,'Cover letter evidence is incomplete.');
        $paragraph=cvGroundedText($p['text']??null,$p['quotes'],$source,1000);
        // Absence from a CV is not proof of lack of experience. Drop an optional
        // paragraph making an unverified negative claim; never fabricate a repair.
        $negative='/\b(?:I (?:have not|have never|haven[’\']t|never|do not have|don[’\']t have|lack)|not yet (?:worked|used|had)|no (?:prior )?(?:experience|exposure))\b/iu';
        if(preg_match($negative,$paragraph['text'])&&strpos($source,$paragraph['text'])===false)continue;
        $out['cover_letter'][]=$paragraph;
    }
    if(count($out['cover_letter'])<2)throw new Problem(502,'The cover letter contains unsupported experience claims. No payment is required.');
    foreach($pack['interview_answers']as$p){
        if(!is_array($p)||!is_array($p['quotes']??null))throw new Problem(502,'Interview evidence is incomplete.');
        $answer=cvGroundedText($p['answer']??null,$p['quotes'],$source,1200);
        $out['interview_answers'][]=['question'=>cvTextValue($p['question']??null,10,300),'answer'=>interviewEvidenceAnswer($answer['quotes']),'quotes'=>$answer['quotes'],'follow_up'=>cvTextValue($p['follow_up']??null,10,400)];
    }
    foreach($pack['action_plan']as$p){
        if(!is_array($p)||!in_array($p['status']??null,['not_evidenced','partial','review'],true))throw new Problem(502,'Invalid application action.');
        $q=cvEvidence($p['job_quote']??null,$job);
        $out['action_plan'][]=['requirement'=>cvLiteral($p['requirement']??null,$q),'job_quote'=>$q,'status'=>$p['status'],'next_step'=>cvTextValue($p['next_step']??null,20,700)];
    }
    $bullets=[];foreach($cv['experience']as$role)foreach($role['bullets']as$b)$bullets[]=$b;
    // Education-only candidates may use a grounded summary edit instead of a work bullet.
    foreach($cv['summary']['quotes']as$q)$bullets[]=['quote'=>$q,'text'=>$cv['summary']['text']];
    foreach($pack['changes']as$p){
        if(!is_array($p))throw new Problem(502,'Invalid CV change.');
        $original=cvEvidence($p['original']??null,$source);$revised=cvTextValue($p['revised']??null,10,700);$found=false;
        foreach($bullets as$b)if($b['text']===$revised){
            // The actual validated draft decides the before/after evidence. A model
            // may quote a shorter source excerpt in its explanation than in the CV.
            $original=$b['quote'];$found=true;break;
        }
        if(!$found)throw new Problem(502,'The preview edit does not match the prepared CV. No payment is required.');
        $out['changes'][]=['original'=>$original,'revised'=>$revised,'why'=>cvTextValue($p['why']??null,20,500),'job_quote'=>cvEvidence($p['job_quote']??null,$job)];
    }
    return $out;
}
