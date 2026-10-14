// Shared paid-results and explicitly fictional demo renderer. Customer pack data only
// arrives from the authorized result endpoint; it is never embedded in public assets.
const el=(tag,text,cls)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;};
export function coverLetterText(pack,contact={}){
 return [contact.name||'', [contact.email,contact.phone,contact.location,contact.website].filter(Boolean).join(' | '),`Application for ${pack.target.role}${pack.target.company?' — '+pack.target.company:''}`,'Dear Hiring Team,',...pack.cover_letter.map(p=>p.text),'I would welcome the opportunity to discuss how my experience relates to your requirements.','Kind regards,',contact.name||''].filter(Boolean).join('\n\n');
}
export function preparationText(pack){return ['INTERVIEW PREPARATION','Answer outlines based on your CV. Add only examples and outcomes that are true.',...pack.interview_answers.flatMap((a,i)=>[`${i+1}. ${a.question}`,a.answer,`Before the interview: ${a.follow_up}`,`Source evidence: ${a.quotes.join(' / ')}`]),'REQUIREMENT ACTION PLAN',...pack.action_plan.flatMap(a=>[`${a.requirement} — ${a.status.replaceAll('_',' ')}`,a.next_step,`Posting: ${a.job_quote}`]),'WHY YOUR CV CHANGED',...pack.changes.flatMap(c=>[`Before: ${c.original}`,`After: ${c.revised}`,c.why,`Posting: ${c.job_quote}`])].join('\n\n');}
export function renderApplicationPack(pack,contact={}){
 const root=el('section',undefined,'application-pack');
 const header=el('div',undefined,'pack-heading');header.append(el('p','YOUR APPLICATION WORKSPACE','eyebrow'),el('h2',pack.target.role),el('p',pack.target.company||'Your selected role'));root.append(header);
 const tabs=el('div',undefined,'pack-tabs');tabs.setAttribute('role','tablist');tabs.setAttribute('aria-label','Application pack');root.append(tabs);
 const panels=[];const buttons=[];
 for(const [i,label] of ['Cover letter','Interview preparation','Action plan','Why these edits'].entries()){
  const b=el('button',label);b.type='button';b.setAttribute('role','tab');b.id=`pack-tab-${i}`;b.setAttribute('aria-controls',`pack-panel-${i}`);b.setAttribute('aria-selected',String(i===0));b.tabIndex=i===0?0:-1;
  const panel=el('section',undefined,'pack-panel');panel.id=`pack-panel-${i}`;panel.setAttribute('role','tabpanel');panel.setAttribute('aria-labelledby',b.id);panel.hidden=i!==0;panel.tabIndex=0;
  const activate=()=>{buttons.forEach((v,j)=>{v.setAttribute('aria-selected',String(j===i));v.tabIndex=j===i?0:-1;panels[j].hidden=j!==i;});};b.addEventListener('click',activate);
  b.addEventListener('keydown',e=>{const delta=e.key==='ArrowRight'?1:e.key==='ArrowLeft'?-1:0;let n=delta?(i+delta+4)%4:e.key==='Home'?0:e.key==='End'?3:null;if(n!==null){e.preventDefault();buttons[n].click();buttons[n].focus();}});
  buttons.push(b);panels.push(panel);tabs.append(b);root.append(panel);
 }
 const letter=el('article',undefined,'letter-page');for(const para of coverLetterText(pack,contact).split('\n\n'))letter.append(el('p',para));panels[0].append(letter);
 panels[1].append(el('p','These starting answers use the facts quoted from your CV. Add a real example, your own actions and an outcome you can substantiate; never invent missing details.','pack-note'));
 pack.interview_answers.forEach((a,i)=>{const card=el('article',undefined,'answer-card');card.append(el('span',String(i+1).padStart(2,'0'),'item-number'),el('h3',a.question),el('p',a.answer),el('h4','Make it specific'),el('p',a.follow_up));const evidence=el('details');evidence.append(el('summary','See the CV evidence'),el('p',a.quotes.join('\n')));card.append(evidence);panels[1].append(card);});
 panels[2].append(el('p','Use only examples you actually experienced. If a requirement is not evidenced, verify it before adding a claim to your application.','pack-note'));
 if(!pack.action_plan.length)panels[2].append(el('p','No additional requirement gaps were identified. Check the full posting and all application instructions before submitting.'));
 pack.action_plan.forEach(a=>{const card=el('article',undefined,'answer-card');card.append(el('span',a.status.replaceAll('_',' '),'tag'),el('h3',a.requirement),el('p',a.next_step),el('blockquote',a.job_quote));panels[2].append(card);});
 pack.changes.forEach(c=>{const card=el('article',undefined,'change-card');card.append(el('h3','A clearer connection to the role'),el('span','BEFORE','eyebrow'),el('p',c.original,'before'),el('span','AFTER','eyebrow'),el('p',c.revised,'after'),el('p',c.why),el('blockquote',c.job_quote));panels[3].append(card);});
 return root;
}
export function saveText(text,name){const u=URL.createObjectURL(new Blob([text],{type:'text/plain;charset=utf-8'}));const a=document.createElement('a');a.href=u;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(u),1000);}
