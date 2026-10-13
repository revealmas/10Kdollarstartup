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
