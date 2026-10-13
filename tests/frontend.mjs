import assert from 'node:assert/strict';
import fs from 'node:fs';
import {localReview} from '../public/local-review.js';
import {docxBytes} from '../public/docx.js';
const review=localReview('Project communication reporting Excel','Project communication reporting Excel risk');
assert(review.present.includes('excel'));assert(review.missing.includes('risk'));
const empty=localReview('','');assert(empty.present[0].includes('No exact'));
const bundle={notice:'Synthetic review & <verification>',original_cv:'Synthetic résumé & <text>',cover_letter_template:'Dear team',analysis:{summary:'Example',strengths:[{quote:'Synthetic résumé',reason:'Only evidence'}],gaps:['Check tools'],interview_questions:['Discuss reporting'],tips:['Verify facts']}};
const bytes=docxBytes(bundle);assert.equal(new DataView(bytes.buffer).getUint32(0,true),0x04034b50);
fs.mkdirSync('work',{recursive:true});fs.writeFileSync('work/test-export.docx',bytes);
for(const path of ['public/app.js','public/return.js','public/admin.js']){
 const source=fs.readFileSync(path,'utf8');assert(!source.includes('innerHTML'),`${path} uses unsafe HTML rendering`);
}
assert(!fs.readFileSync('public/index.html','utf8').includes('sk_live_'));
console.log('PASS Local preview, Word ZIP generation and safe rendering checks');

// Exercise an owner session that expires while the dashboard remains open.
const {default:vm}=await import('node:vm');
const elements=new Map();
const element=id=>{if(!elements.has(id))elements.set(id,{hidden:false,value:'',children:[],dataset:{},replaceChildren(...items){this.children=items;},append(...items){this.children.push(...items);},addEventListener(type,handler){this[type]=handler;}});return elements.get(id);};
let expired=false;
const response=body=>({ok:true,status:200,json:async()=>body});
const context=vm.createContext({document:{getElementById:element,createElement:()=>element('generated-'+elements.size)},fetch:async url=>{
 if(url.includes('bootstrap'))return response({csrf:'synthetic-csrf',signed_in:false});
 if(expired)return {ok:false,status:401,json:async()=>({error:'Owner sign-in required.'})};
 if(url.includes('login'))return response({csrf:'synthetic-signed-csrf'});
 if(url.includes('snapshot'))return response({totals:{paid_orders:1},gates:{checkout_enabled:false},counts:{},orders:[],jobs:{count:0},ai_calls:{},storage_backend:'file',notice:'Synthetic'});
 return response({deepseek_configured:false,stripe_test_configured:false,webhook_configured:false});
},location:{reload(){}},Date,Error,String,Object});
await vm.runInContext('(async()=>{'+fs.readFileSync('public/admin.js','utf8')+'})()',context);
await element('login').submit({preventDefault(){}});
assert.equal(element('dashboard').hidden,false);assert.equal(element('login').hidden,true);
element('deepseek-key').value='synthetic-unsaved-sensitive-value';
expired=true;await element('refresh').click();
assert.equal(element('dashboard').hidden,true);assert.equal(element('logout').hidden,true);assert.equal(element('login').hidden,false);
assert.equal(element('totals').children.length,0);assert.equal(element('deepseek-key').value,'');
assert.equal(element('status').textContent,'Owner sign-in required.');
console.log('PASS Expired owner session clears dashboard and unsaved secrets, restoring sign-in');
