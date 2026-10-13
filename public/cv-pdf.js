import {PDFDocument,StandardFonts,rgb} from './vendor/pdf-lib.esm.min.js';
export async function cvPdfBytes(cv,contact,{example=false}={}){
 const doc=await PDFDocument.create();const regular=await doc.embedFont(StandardFonts.Helvetica);const bold=await doc.embedFont(StandardFonts.HelveticaBold);
 const width=595.28,height=841.89,margin=48,usable=width-margin*2;let page,y;
 const color=rgb(0.07,0.08,0.10);const muted=rgb(.28,.3,.33);
 function newPage(){page=doc.addPage([width,height]);y=height-margin;}
 function need(h){if(y-h<margin)newPage();}
 function wrap(text,font,size,max=usable){const words=String(text).replace(/[‐‑‒–—―]/g,'-').split(/\s+/);const lines=[];let line='';for(const word of words){const candidate=line?line+' '+word:word;if(font.widthOfTextAtSize(candidate,size)<=max){line=candidate;continue;}if(line){lines.push(line);line='';}let part='';for(const char of word){if(font.widthOfTextAtSize(part+char,size)>max&&part){lines.push(part);part='';}part+=char;}line=part;}if(line)lines.push(line);return lines;}
 function text(value,{size=10.5,font=regular,indent=0,gap=5,tone=color}={}){if(!value)return;for(const line of wrap(value,font,size,usable-indent)){need(size*1.4);page.drawText(line,{x:margin+indent,y:y-size,size,font,color:tone});y-=size*1.4;}y-=gap;}
 function heading(value,contentHeight=30){need(28+contentHeight);y-=13;text(value.toUpperCase(),{font:bold,size:10.5,gap:8});}
 function bullet(value){const lines=wrap(value,regular,10.5,usable-12);need(lines.length*14.7+4);page.drawText('•',{x:margin,y:y-10.5,size:10.5,font:regular,color});for(const line of lines){page.drawText(line,{x:margin+12,y:y-10.5,size:10.5,font:regular,color});y-=14.7;}y-=4;}
 newPage();text(contact.name||'Your name',{font:bold,size:26,gap:5});text(cv.headline,{size:12.5,gap:7});text([contact.location,contact.email,contact.phone,contact.website].filter(Boolean).join(' | '),{size:9,tone:muted,gap:9});
 heading('Professional Summary',wrap(cv.summary.text,regular,10.5).length*14.7);text(cv.summary.text,{gap:5});
 if(cv.experience.length){heading('Professional Experience');for(const job of cv.experience){need(40+wrap(job.bullets[0]?.text||'',regular,10.5,usable-12).length*14.7);text(job.title||'Relevant experience',{font:bold,gap:3});text([job.company,job.dates].filter(Boolean).join(' | '),{size:9.5,tone:muted,gap:7});for(const b of job.bullets)bullet(b.text);y-=5;}}
 if(cv.skills.length){heading('Skills');text(cv.skills.map(s=>s.text).join(' • '));}
 if(cv.education.length){heading('Education');for(const e of cv.education){need(40);text(e.qualification,{font:bold,gap:3});text([e.institution,e.dates].filter(Boolean).join(' | '),{size:9.5,tone:muted});}}
 if(example){y-=15;text('Fictional design example. All candidate details are synthetic.',{size:8.5,tone:muted});}
 doc.setTitle((contact.name||'Applicant')+' CV');doc.setCreator('ApplyPilot');doc.setSubject('Curriculum vitae');return doc.save();
}
export async function downloadCvPdf(cv,contact){const bytes=await cvPdfBytes(cv,contact);const u=URL.createObjectURL(new Blob([bytes],{type:'application/pdf'}));const a=document.createElement('a');a.href=u;a.download='tailored-cv.pdf';a.click();setTimeout(()=>URL.revokeObjectURL(u),1000);}
