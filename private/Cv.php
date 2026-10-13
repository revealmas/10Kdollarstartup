<?php
declare(strict_types=1);
namespace ApplyPilot;
function cvTextValue($value,int $min,int $max):string{try{return boundedText($value,$min,$max);}catch(Problem $e){throw new Problem(502,'The CV draft has invalid text or structure. No payment is required.');}}
function cvEvidence($value,string $source,int $min=10):string{
    $quote=cvTextValue($value,$min,700);
    if(strpos($source,$quote)===false)throw new Problem(502,'The CV draft cited facts absent from your supplied CV. No payment is required.');
    return $quote;
}
function cvLiteral($value,string $quote,int $max=160):string{
    if($value==='')return '';
    $text=cvTextValue($value,1,$max);
    if(strpos($quote,$text)===false)throw new Problem(502,'A CV employer, qualification or date could not be verified. No payment is required.');
    return $text;
}
function cvGroundedText($value,array $quotes,string $source,int $max):array{
    $text=cvTextValue($value,10,$max);
    if(count($quotes)<1||count($quotes)>6)throw new Problem(502,'CV evidence is incomplete. No payment is required.');
    $evidence=array_map(fn($q)=>cvEvidence($q,$source),$quotes);
    preg_match_all('/\d+(?:[.,]\d+)*(?:%)?/',$text,$numbers);
    preg_match_all('/\d+(?:[.,]\d+)*(?:%)?/',implode(' ',$evidence),$sourceNumbers);
    foreach($numbers[0]as$n)if(!in_array($n,$sourceNumbers[0],true))throw new Problem(502,'The CV draft added a number not supported by its evidence. No payment is required.');
    return ['text'=>$text,'quotes'=>$evidence];
}
function validateCv(array $cv,string $source):array{
    $headline=cvTextValue($cv['headline']??null,2,100);
    if(stripos($source,$headline)===false)throw new Problem(502,'The CV headline must use a role or phrase in your supplied CV.');
    $summary=$cv['summary']??null;
    if(!is_array($summary)||!is_array($summary['quotes']??null))throw new Problem(502,'The CV summary could not be validated.');
    $out=['headline'=>$headline,'summary'=>cvGroundedText($summary['text']??null,$summary['quotes'],$source,700),'skills'=>[],'experience'=>[],'education'=>[],'missing_details'=>[]];
    foreach(['skills'=>24,'experience'=>12,'education'=>8]as$field=>$max)
        if(!is_array($cv[$field]??null)||count($cv[$field])>$max)throw new Problem(502,'The CV structure could not be validated.');
    foreach($cv['skills']as$item){
        if(!is_array($item))throw new Problem(502,'Invalid CV skill.');$quote=cvEvidence($item['quote']??null,$source,2);$text=cvTextValue($item['text']??null,2,90);
        if(!preg_match('/(?<![\pL\pN])'.preg_quote($text,'/').'(?![\pL\pN])/ui',$quote))throw new Problem(502,'A CV skill was not present in its source evidence.');
        $out['skills'][]=['text'=>$text,'quote'=>$quote];
    }
    $boundaries=[];
    foreach(array_merge($cv['experience'],$cv['education'])as$item)if(is_array($item)&&is_string($item['quote']??null)&&$item['quote']!==''){ $pos=strpos($source,$item['quote']);if($pos!==false)$boundaries[]=$pos;}
    preg_match_all('/\n\s*(?:Education|Skills|Certifications|Languages)\s*(?::|\n)/i',$source,$sectionBoundaries,PREG_OFFSET_CAPTURE);
    foreach($sectionBoundaries[0]as$match)$boundaries[]=$match[1];
    sort($boundaries,SORT_NUMERIC);
    foreach($cv['experience']as$item){
        if(!is_array($item)||!is_array($item['bullets']??null)||count($item['bullets'])<1||count($item['bullets'])>8)throw new Problem(502,'CV experience could not be validated.');
        $quote=($item['quote']??'')===''?'':cvEvidence($item['quote'],$source);
        $entry=['title'=>cvLiteral($item['title']??null,$quote),'company'=>cvLiteral($item['company']??null,$quote),'dates'=>cvLiteral($item['dates']??null,$quote,100),'quote'=>$quote,'bullets'=>[]];
        $block=$source;if($quote!==''){ $start=strpos($source,$quote);$end=strlen($source);foreach($boundaries as$boundary)if($boundary>$start){$end=$boundary;break;}$block=substr($source,$start,$end-$start);}
        foreach($item['bullets']as$bullet){
            if(!is_array($bullet))throw new Problem(502,'Invalid CV bullet.');
            $grounded=cvGroundedText($bullet['text']??null,[$bullet['quote']??null],$source,600);
            if($quote!==''&&strpos($block,$grounded['quotes'][0])===false)throw new Problem(502,'A CV bullet was attributed to a different source role. No payment is required.');$entry['bullets'][]=['text'=>$grounded['text'],'quote'=>$grounded['quotes'][0]];
        }
        foreach(['title','company','dates']as$key)if($entry[$key]==='')$out['missing_details'][]='Add the missing '.$key.' for an experience entry to your source CV.';
        $out['experience'][]=$entry;
    }
    foreach($cv['education']as$item){
        if(!is_array($item))throw new Problem(502,'Invalid CV education.');$quote=cvEvidence($item['quote']??null,$source);
        $entry=['qualification'=>cvLiteral($item['qualification']??null,$quote),'institution'=>cvLiteral($item['institution']??null,$quote),'dates'=>cvLiteral($item['dates']??null,$quote,100),'quote'=>$quote];
        if($entry['qualification']===''||$entry['institution']==='')$out['missing_details'][]='Add the missing qualification or institution to your source CV.';
        $out['education'][]=$entry;
    }
    if(!$out['experience']&&!$out['education'])$out['missing_details'][]='Add your experience or education to your source CV.';
    if(!$out['skills'])$out['missing_details'][]='Add your relevant skills to your source CV.';
    $out['missing_details']=array_values(array_unique($out['missing_details']));
    return $out;
}
