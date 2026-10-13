<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');
function answer(int $status,array $body):void{http_response_code($status);echo json_encode($body);exit;}
$dir=getenv('APPLYPILOT_PRIVATE_DIR')?:'';
if(!$dir||!is_file($dir.'/Transport.php'))answer(503,['error'=>'Owner access is not configured.']);
try{
 require_once $dir.'/Transport.php';require_once $dir.'/Admin.php';
 [$config,$store]=$boot=\ApplyPilot\boot($dir);$admin=new \ApplyPilot\Admin($store,$config);
 if(($_SERVER['HTTPS']??'')!=='on')throw new \ApplyPilot\Problem(503,'HTTPS is required.');
 session_name('applypilot_owner');ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
 session_save_path(\ApplyPilot\privateSessionDirectory($dir,'owner'));ini_set('session.gc_maxlifetime','1800');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);session_start();
 if(!isset($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));
 $action=$_GET['action']??'';$method=$_SERVER['REQUEST_METHOD']??'';
 if($action==='bootstrap'&&$method==='GET')answer(200,['csrf'=>$_SESSION['csrf'],'signed_in'=>($_SESSION['until']??0)>time()]);
 if($action!=='login'&&($_SESSION['until']??0)<=time())throw new \ApplyPilot\Problem(401,'Owner sign-in required.');
 if($method!=='POST')throw new \ApplyPilot\Problem(405,'Use POST.');
 $origin=parse_url($config['base_url']);$expected=$origin['scheme'].'://'.$origin['host'].(isset($origin['port'])?':'.$origin['port']:'');
 if(($_SERVER['HTTP_ORIGIN']??'')!==$expected||!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))throw new \ApplyPilot\Problem(403,'Reload and try again.');
 if(!str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json'))throw new \ApplyPilot\Problem(415,'Use JSON.');
 $raw=file_get_contents('php://input',false,null,0,4097);if(strlen($raw)>4096)throw new \ApplyPilot\Problem(413,'Request too large.');
 $input=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
 if($action==='login'){
  if(!is_string($input['password']??null)||!$admin->login($input['password'],$_SERVER['REMOTE_ADDR']??'unknown'))throw new \ApplyPilot\Problem(401,'Sign-in failed.');
  session_regenerate_id(true);$_SESSION['until']=time()+1800;$_SESSION['csrf']=bin2hex(random_bytes(32));answer(200,['csrf'=>$_SESSION['csrf']]);
 }
 if(($_SESSION['until']??0)<=time())throw new \ApplyPilot\Problem(401,'Owner sign-in required.');
 if($action==='logout'){$_SESSION=[];session_destroy();answer(200,['ok'=>true]);}
 if($action==='snapshot'){session_write_close();answer(200,$admin->snapshot());}
 throw new \ApplyPilot\Problem(404,'Unknown action.');
}catch(\ApplyPilot\Problem $e){answer($e->status,['error'=>$e->getMessage()]);}catch(\Throwable $e){answer(503,['error'=>'Owner dashboard temporarily unavailable.']);}
