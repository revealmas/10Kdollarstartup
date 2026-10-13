import {packageText} from './integration.js';
const encoder=new TextEncoder();
const xml=text=>text.replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&apos;').replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f]/g,'');
function crc32(bytes){let crc=0xffffffff;for(const byte of bytes){crc^=byte;for(let i=0;i<8;i++)crc=(crc>>>1)^((crc&1)?0xedb88320:0);}return(crc^0xffffffff)>>>0;}
function header(size){const bytes=new Uint8Array(size);const view=new DataView(bytes.buffer);return {bytes,view};}
export function docxBytes(bundle){
  const paragraphs=packageText(bundle).split('\n').map(line=>`<w:p><w:r><w:t xml:space="preserve">${xml(line)}</w:t></w:r></w:p>`).join('');
  const files={
    '[Content_Types].xml':'<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
    '_rels/.rels':'<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
    'word/document.xml':`<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>${paragraphs}<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr></w:body></w:document>`
  };
  const locals=[];const centrals=[];let offset=0;let centralSize=0;
  for(const [name,content] of Object.entries(files)){
    const filename=encoder.encode(name);const data=encoder.encode(content);const crc=crc32(data);
    const local=header(30);local.view.setUint32(0,0x04034b50,true);local.view.setUint16(4,20,true);local.view.setUint16(6,0x800,true);local.view.setUint32(14,crc,true);local.view.setUint32(18,data.length,true);local.view.setUint32(22,data.length,true);local.view.setUint16(26,filename.length,true);
    const central=header(46);central.view.setUint32(0,0x02014b50,true);central.view.setUint16(4,20,true);central.view.setUint16(6,20,true);central.view.setUint16(8,0x800,true);central.view.setUint32(16,crc,true);central.view.setUint32(20,data.length,true);central.view.setUint32(24,data.length,true);central.view.setUint16(28,filename.length,true);central.view.setUint32(42,offset,true);
    locals.push(local.bytes,filename,data);centrals.push(central.bytes,filename);offset+=30+filename.length+data.length;centralSize+=46+filename.length;
  }
  const end=header(22);end.view.setUint32(0,0x06054b50,true);end.view.setUint16(8,3,true);end.view.setUint16(10,3,true);end.view.setUint32(12,centralSize,true);end.view.setUint32(16,offset,true);
  const bytes=new Uint8Array(offset+centralSize+22);let position=0;for(const chunk of [...locals,...centrals,end.bytes]){bytes.set(chunk,position);position+=chunk.length;}return bytes;
}
export function downloadDocx(bundle){const url=URL.createObjectURL(new Blob([docxBytes(bundle)],{type:'application/vnd.openxmlformats-officedocument.wordprocessingml.document'}));const a=document.createElement('a');a.href=url;a.download='applypilot-application-review.docx';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}
