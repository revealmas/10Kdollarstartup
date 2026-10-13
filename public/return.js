import {ApplyPilot,packageText,downloadText} from './integration.js';
import {cvText,renderCv} from './cv-view.js';
import {downloadCvDocx} from './cv-docx.js';
import {downloadCvPdf} from './cv-pdf.js';
const api=new ApplyPilot();const $=id=>document.getElementById(id);const status=$('status'),retry=$('retry'),download=$('download');
const id=sessionStorage.getItem('applypilot_order');let bundle;
const contact=()=>({name:$('cv-name').value.trim(),email:$('cv-email').value.trim(),phone:$('cv-phone').value.trim(),location:$('cv-location').value.trim(),website:$('cv-website').value.trim()});
function clearPackage(){bundle=undefined;$('preview').textContent='';$('preview').hidden=true;$('cv-page').replaceChildren();for(const id of ['download','docx','pdf','cv-output','contact-form','review-details','review-download'])$(id).hidden=true;}
function clearContact(){for(const id of ['cv-name','cv-email','cv-phone','cv-location','cv-website'])$(id).value='';}
function showCv(){if(!bundle?.cv)return;$('cv-page').replaceChildren(renderCv(bundle.cv,contact()));}
function ready(){if(!bundle?.cv)return false;if(!contact().name){$('export-status').textContent='Add your name before downloading your CV.';$('cv-name').focus();return false;}$('export-status').textContent='';return true;}
async function check(){clearPackage();status.textContent='Checking payment status…';retry.disabled=true;try{const cfg=await api.start();if(cfg.support_email)$('support').textContent=`Support and refunds: ${cfg.support_email}. Provide your Stripe receipt; do not email your CV.`;if(!id)throw Error('Open the browser used for your preview. If access is lost, contact support with your Stripe receipt.');const state=await api.status(id);if(!state.paid){clearContact();status.textContent=state.state==='revoked'?'Access was revoked after a refund or payment review. Contact support if you need help.':`Payment state: ${state.state}. A return from checkout alone does not confirm payment. If you paid, wait briefly and check again.`;return;}
 bundle=(await api.result(id)).package;$('preview').textContent=packageText(bundle);$('preview').hidden=false;$('review-details').hidden=false;$('review-download').hidden=false;
 if(bundle.cv){$('delivery-title').textContent='Your job-specific CV';$('contact-form').hidden=false;$('cv-output').hidden=false;for(const id of ['download','docx','pdf'])$(id).hidden=false;showCv();status.textContent='Payment verified. Add your contact details and review your CV before applying.';}
 else{$('delivery-title').textContent='Application review';$('review-details').open=true;status.textContent='This older test package contains review notes only. Prepare a fresh CV preview to test the CV output.';}
 }catch(e){clearPackage();clearContact();status.textContent=e.message;}finally{retry.disabled=false;}}
retry.addEventListener('click',check);for(const id of ['cv-name','cv-email','cv-phone','cv-location','cv-website'])$(id).addEventListener('input',showCv);
$('review-download').addEventListener('click',()=>{if(bundle)downloadText(bundle);});
download.addEventListener('click',()=>{if(!ready())return;const u=URL.createObjectURL(new Blob([cvText(bundle.cv,contact())],{type:'text/plain;charset=utf-8'}));const a=document.createElement('a');a.href=u;a.download='tailored-cv.txt';a.click();setTimeout(()=>URL.revokeObjectURL(u),1000);});
$('docx').addEventListener('click',()=>{if(ready())downloadCvDocx(bundle.cv,contact());});
$('pdf').addEventListener('click',async()=>{if(!ready())return;try{await downloadCvPdf(bundle.cv,contact());}catch(e){$('export-status').textContent='PDF export could not encode this text. Download the Word version instead.';}});
check();
