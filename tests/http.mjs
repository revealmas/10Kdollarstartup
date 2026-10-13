import {spawn,spawnSync} from 'node:child_process';
import fs from 'node:fs';import os from 'node:os';import path from 'node:path';import crypto from 'node:crypto';import assert from 'node:assert/strict';
fs.mkdirSync('work',{recursive:true});
const temp=fs.mkdtempSync(path.join(os.tmpdir(),'applypilot-http-'));const priv=path.join(temp,'private');fs.cpSync('private',priv,{recursive:true});
const port=19000+Math.floor(Math.random()*1000);const origin=`https://127.0.0.1:${port}`;
const password='synthetic-owner-password-only-for-tests';
const hash=spawnSync('php',['-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password],{encoding:'utf8'}).stdout.trim();
fs.writeFileSync(path.join(priv,'config.php'),`<?php $c=require __DIR__.'/config.example.php'; $c['base_url']='${origin}';$c['admin_password_hash']='${hash}';$c['storage_key']='${crypto.randomBytes(32).toString('base64')}'; return $c;`);
const server=spawn('php',['-S',`127.0.0.1:${port}`,'-t','public','tests/router.php'],{env:{...process.env,APPLYPILOT_PRIVATE_DIR:priv,PHP_CLI_SERVER_WORKERS:'4'},stdio:'ignore'});
const base=`http://127.0.0.1:${port}`;let cookie='';let csrf;let pass=0;
const check=(condition,name)=>{assert(condition,name);console.log('PASS '+name);pass++;};
async function req(action,{admin=false,method='POST',body={},token=csrf,requestOrigin=origin}={}){
 const response=await fetch(`${base}/${admin?'admin-api':'api'}.php?action=${action}`,{method,headers:{'Content-Type':'application/json','Origin':requestOrigin,'X-CSRF-Token':token||'','Cookie':cookie},body:method==='GET'?undefined:JSON.stringify(body)});
 return {response,body:await response.json()};
}
try{
 let ready=false;for(let i=0;i<50;i++){try{await fetch(base);ready=true;break;}catch{await new Promise(r=>setTimeout(r,100));}}if(!ready)throw Error('PHP server failed to start');
 const boot=await req('bootstrap',{method:'GET'});check(boot.response.status===200,'HTTP bootstrap');csrf=boot.body.csrf;cookie=boot.response.headers.get('set-cookie').split(';')[0];
 check(boot.response.headers.get('set-cookie').includes('HttpOnly')&&/secure/i.test(boot.response.headers.get('set-cookie')),'Secure HttpOnly cookie');
 check(/no-store/.test(boot.response.headers.get('cache-control')),'Private responses are not cached');
 check((await req('result',{body:{order_id:'guess'}})).response.status===404,'Guessed package denied');
 check((await req('result',{token:'bad',body:{order_id:'guess'}})).response.status===403,'CSRF forgery denied');
 check((await req('result',{requestOrigin:'https://evil.example',body:{order_id:'guess'}})).response.status===403,'Cross-origin request denied');
 check((await req('prepare',{body:{resume:'x'.repeat(100),job_description:'y'.repeat(100),consent:true,adult:true}})).response.status===503,'Unconfigured AI cannot process CV');
 check((await req('webhook',{body:{id:'forged'}})).response.status===400,'HTTP forged webhook denied');
 const a=await req('bootstrap',{admin:true,method:'GET'});cookie=a.response.headers.get('set-cookie').split(';')[0];csrf=a.body.csrf;
 check((await req('sandbox-check',{admin:true})).response.status===401,'Anonymous sandbox diagnostic denied');
 check((await req('ai-check',{admin:true})).response.status===401,'Anonymous AI diagnostic denied');
 check((await req('sandbox-preview',{admin:true})).response.status===401,'Anonymous synthetic purchase denied');
 check((await req('snapshot',{admin:true})).response.status===401,'Unauthenticated admin data denied');
 check((await req('save-integrations',{admin:true,body:{stripe_key:'rk_test_'+ 'A'.repeat(24)}})).response.status===401,'Anonymous credential configuration denied');
 check((await req('login',{admin:true,body:{password:'wrong'}})).response.status===401,'Wrong owner credential denied');
 const login=await req('login',{admin:true,body:{password}});check(login.response.status===200,'Owner login succeeds');csrf=login.body.csrf;cookie=login.response.headers.get('set-cookie').split(';')[0];
 check((await req('sandbox-check',{admin:true})).response.status===503,'Missing sandbox settings fail before provider calls');
 check((await req('sandbox-preview',{admin:true})).response.status===503,'Missing sandbox settings block synthetic purchase');
 check((await req('sandbox-checkout',{admin:true,token:'bad'})).response.status===403,'Synthetic purchase CSRF forgery denied');
 check((await req('sandbox-check',{admin:true,token:'bad'})).response.status===403,'Sandbox diagnostic CSRF forgery denied');
 check((await req('ai-check',{admin:true})).response.status===503,'Missing AI settings fail before credit reservation');
 check((await req('ai-check',{admin:true,token:'bad'})).response.status===403,'AI diagnostic CSRF forgery denied');
 const snap=await req('snapshot',{admin:true});check(snap.response.status===200&&!!snap.body.gates,'Authenticated operational dashboard');
 check(!JSON.stringify(snap.body).includes(password),'Owner password absent from dashboard');
 const setting=await req('save-integrations',{admin:true,body:{stripe_key:'rk_test_'+'A'.repeat(24),support_email:'support@example.invalid'}});
 check(setting.response.status===200&&setting.body.stripe_test_configured===true&&!JSON.stringify(setting.body).includes('rk_test_'),'Owner settings save without returning credential');
 const publicConfig=await req('bootstrap',{method:'GET'});check(publicConfig.body.support_email==='support@example.invalid'&&!publicConfig.body.ai_enabled&&!publicConfig.body.checkout_enabled&&!JSON.stringify(publicConfig.body).includes('rk_test_'),'Public recovery contact propagates without keys or enabling launch');
 check((await req('save-integrations',{admin:true,body:{stripe_key:'rk_live_'+'B'.repeat(24)}})).response.status===422,'Live key denied by test setup');
 await req('logout',{admin:true});check((await req('snapshot',{admin:true})).response.status===401,'Logout revokes owner session');
 // Bounded local-only load, no provider calls and no public stress traffic.
 const latencies=[];const statuses=[];for(let batch=0;batch<10;batch++)await Promise.all(Array.from({length:10},async()=>{const start=performance.now();const r=await fetch(base+'/api.php?action=jobs');latencies.push(performance.now()-start);statuses.push(r.status);}));
 latencies.sort((a,b)=>a-b);const load={requests:100,concurrency:10,p50_ms:Math.round(latencies[49]),p95_ms:Math.round(latencies[94]),responses:Object.fromEntries([...new Set(statuses)].map(s=>[s,statuses.filter(x=>x===s).length])),scope:'Local PHP server only; not production capacity.'};
 check(statuses.every(s=>s===200||s===429),'Load requests safely succeed or rate-limit');fs.writeFileSync('work/load-results.json',JSON.stringify(load,null,2));console.log(JSON.stringify(load));console.log(`HTTP RESULT ${pass} checks passed.`);
}finally{server.kill('SIGTERM');fs.rmSync(temp,{recursive:true,force:true});}
