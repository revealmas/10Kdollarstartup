function node(tag,text,className){const el=document.createElement(tag);if(text!==undefined)el.textContent=text;if(className)el.className=className;return el;}
export function cvText(cv,contact){
 const lines=[contact.name||'Your name',cv.headline||'', [contact.location,contact.email,contact.phone,contact.website].filter(Boolean).join(' | '),'', 'PROFESSIONAL SUMMARY',cv.summary.text,''];
 if(cv.experience.length)lines.push('PROFESSIONAL EXPERIENCE');
 for(const job of cv.experience){lines.push(job.title,[job.company,job.dates].filter(Boolean).join(' | '),...job.bullets.map(x=>'• '+x.text),'');}
 if(cv.skills.length)lines.push('SKILLS',cv.skills.map(x=>x.text).join(' • '),'');
 if(cv.education.length){lines.push('EDUCATION');for(const item of cv.education)lines.push(item.qualification,[item.institution,item.dates].filter(Boolean).join(' | '));}
 return lines.filter(x=>x!==undefined).join('\n');
}
export function renderCv(cv,contact){
 const article=node('article',undefined,'cv-document');const header=node('header',undefined,'cv-header');
 header.append(node('h1',contact.name||'Your name'),node('p',cv.headline||'', 'cv-headline'),node('p',[contact.location,contact.email,contact.phone,contact.website].filter(Boolean).join('  •  '),'cv-contact'));article.append(header);
 function section(title){const el=node('section',undefined,'cv-section');el.append(node('h2',title));article.append(el);return el;}
 section('Professional Summary').append(node('p',cv.summary.text));
 const experience=cv.experience.length?section('Professional Experience'):null;
 for(const job of cv.experience){const el=node('div',undefined,'cv-job');const heading=node('div',undefined,'cv-job-heading');heading.append(node('h3',job.title||'Relevant experience'),node('p',job.dates||'', 'cv-dates'));el.append(heading);if(job.company)el.append(node('p',job.company,'cv-company'));const ul=node('ul');for(const bullet of job.bullets)ul.append(node('li',bullet.text));el.append(ul);experience.append(el);}
 if(cv.skills.length)section('Skills').append(node('p',cv.skills.map(x=>x.text).join('  •  '),'cv-skills'));
 if(cv.education.length){const education=section('Education');for(const item of cv.education){education.append(node('h3',item.qualification),node('p',[item.institution,item.dates].filter(Boolean).join('  |  ')));}}
 return article;
}
