import {cvDocxBytes} from './cv-docx.js?v=pack-1';
import {cvPdfBytes} from './cv-pdf.js?v=pack-1';
export function letterParts(pack,contact){return ['Dear Hiring Team,',...pack.cover_letter.map(p=>p.text),'I would welcome the opportunity to discuss how my experience relates to your requirements.','Kind regards,',contact.name||''];}
const doc=(pack)=>({headline:`Application for ${pack.target.role}${pack.target.company?' — '+pack.target.company:''}`});
export function letterDocxBytes(pack,contact,options={}){return cvDocxBytes(doc(pack),contact,{...options,letter:letterParts(pack,contact)});}
export function letterPdfBytes(pack,contact,options={}){return cvPdfBytes(doc(pack),contact,{...options,letter:letterParts(pack,contact)});}
export async function downloadLetter(pack,contact,type){const bytes=type==='pdf'?await letterPdfBytes(pack,contact):letterDocxBytes(pack,contact);const u=URL.createObjectURL(new Blob([bytes],{type:type==='pdf'?'application/pdf':'application/vnd.openxmlformats-officedocument.wordprocessingml.document'}));const a=document.createElement('a');a.href=u;a.download=`cover-letter.${type}`;a.click();setTimeout(()=>URL.revokeObjectURL(u),1000);}
