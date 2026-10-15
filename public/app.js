import {ApplyPilot} from './integration.js';
import {localReview} from './local-review.js';
const $=id=>document.getElementById(id);
const api=new ApplyPilot(); let cfg=null; let order=null;
function items(id,values){const list=$(id);list.replaceChildren();for(const value of values){const li=document.createElement('li');li.textContent=value;list.append(li);}}
async function setup(){
  try{
    cfg=await api.start();
    if(cfg.ai_enabled){$('service-note').textContent='Free discovery and previews. Optional job-specific CV bundle: USD $9 total, paid only after your preview.';$('ai-consent').hidden=false;$('prepare').disabled=false;$('prepare').textContent='Prepare my AI preview ↗';}
    if(cfg.checkout_enabled){$('checkout').textContent='Unlock my CV · USD $9 ↗';}
    for(const country of cfg.billing_countries){const option=document.createElement('option');option.value=country;option.textContent=country;$('country').append(option);}
    $('merchant-disclosure').textContent=`Seller: MAFSAN GLOBAL SOLUTIONS FZC-LLC. Support: ${cfg.support_email||'not configured yet'}. Your card statement will show: ${cfg.merchant_descriptor||'to be confirmed before checkout'}.`;
    await jobs('');
  }catch(e){/* Local preview works without backend. No CV is sent automatically. */}
}
$('preview-form').addEventListener('submit',event=>{
  event.preventDefault(); const review=localReview($('resume').value,$('job-description').value);
  $('local-summary').textContent=review.summary;items('local-strengths',review.present);items('local-gaps',review.missing);
  $('local-preview').hidden=false; $('ai-preview').hidden=true; order=null;
  $('preview-status').textContent='Your local preview is ready. No text was sent to an AI provider.';
  $('local-preview').scrollIntoView({behavior:'smooth',block:'start'});
});
$('sample').addEventListener('click',async()=>{
  const {resume,job_description}=await import('./cv-example-data.js');$('resume').value=resume;$('job-description').value=job_description;
  $('preview-status').textContent='Sample filled. This is an illustrative CV and posting; choose Build my free preview to try it.';
});
$('clear').addEventListener('click',()=>{$('resume').value='';$('job-description').value='';$('local-preview').hidden=true;$('ai-preview').hidden=true;$('preview-status').textContent='Text cleared from this page.';});
$('prepare').addEventListener('click',async()=>{
  if(!cfg?.ai_enabled)return;
  if(!$('consent').checked||!$('adult').checked){$('preview-status').textContent='Please confirm consent and that you are 18 or older.';return;}
  $('prepare').disabled=true;$('preview-status').textContent='Preparing your CV draft and useful preview. No payment is required.';
  try{
    const result=await api.prepare($('resume').value,$('job-description').value,true,true);order=result.order_id;
    $('ai-summary').textContent=result.preview.summary;
    items('ai-strengths',result.preview.strengths.map(x=>`${x.quote} — ${x.reason}`));items('ai-gaps',result.preview.gaps);
    $('ai-preview').hidden=false;const readiness=result.preview.cv_readiness;$('checkout').disabled=!cfg.checkout_enabled||readiness?.ready!==true;$('cv-readiness').textContent=readiness?.ready?`CV structure prepared: ${readiness.experience_entries} experience entries and ${readiness.education_entries} education entries. Full CV stays private until payment.`:(readiness?.missing_details||[]).join(' ');$('cv-original').textContent=result.preview.cv_example?.original||'';$('cv-draft-example').textContent=result.preview.cv_example?.draft||'';$('cv-edit-why').textContent=result.preview.cv_example?.why||'';$('cv-job-evidence').textContent=result.preview.cv_example?.job_quote||'';await api.acknowledgePreview(order);
    $('preview-status').textContent='Your AI preview is ready. Review it before deciding whether to unlock your CV.';
    $('ai-preview').scrollIntoView({behavior:'smooth',block:'start'});
  }catch(e){$('preview-status').textContent=e.message;}finally{$('prepare').disabled=false;}
});
$('checkout').addEventListener('click',async()=>{
  if(!order)return;
  if(!$('terms').checked||!$('delivery').checked||!$('country').value){$('preview-status').textContent='Choose your billing country and confirm the terms and digital delivery.';return;}
  $('checkout').disabled=true;
  try{await api.checkout(order,$('country').value,true,true);}catch(e){$('preview-status').textContent=e.message;$('checkout').disabled=false;}
});
$('delete-package').addEventListener('click',async()=>{
  if(!order)return;try{await api.remove(order);order=null;$('ai-preview').hidden=true;$('preview-status').textContent='Your prepared package has been deleted.';}catch(e){$('preview-status').textContent=e.message;}
});
async function jobs(query){
  const url=`./api.php?action=jobs&q=${encodeURIComponent(query)}`;
  const response=await fetch(url,{cache:'no-store',credentials:'same-origin'});const result=await response.json();
  if(!response.ok)throw new Error(result.error||'Listings are temporarily unavailable.');
  $('jobs-list').replaceChildren();$('jobs-status').textContent=result.notice||`${result.jobs.length} listings · source checked ${new Date(result.checked_at).toLocaleString()}. Availability can change. Jobicy listings cover recent remote roles, may have location restrictions and are delayed by at least 3 hours.`;
  for(const job of result.jobs){
    const href=new URL(job.apply_url);if(href.protocol!=='https:'||!['boards.greenhouse.io','job-boards.greenhouse.io','jobs.lever.co','jobs.eu.lever.co','jobicy.com'].includes(href.hostname))continue;
    const article=document.createElement('article');article.className='job';const h=document.createElement('h3');h.textContent=job.title;
    const p=document.createElement('p');p.textContent=`${job.employer} · ${job.location} · ${job.source}`;
    const a=document.createElement('a');a.href=href.href;a.target='_blank';a.rel='noopener noreferrer';a.textContent=job.source==='Jobicy'?'View on Jobicy ↗':'View employer posting ↗';article.append(h,p,a);$('jobs-list').append(article);
  }
}
$('jobs-form').addEventListener('submit',async event=>{event.preventDefault();try{await jobs($('job-query').value);}catch(e){$('jobs-status').textContent='Live listings are not available yet. Browse the official employer boards below.';}});
setup();
