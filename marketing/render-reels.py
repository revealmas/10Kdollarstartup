from pathlib import Path
from PIL import Image,ImageDraw,ImageFont
import sys,subprocess,json
sys.path.insert(0,'work/video-deps')
import imageio_ffmpeg
out=Path('outputs/reels'); ff=imageio_ffmpeg.get_ffmpeg_exe()
font='/System/Library/Fonts/Helvetica.ttc'
F=lambda s:ImageFont.truetype(font,s)
reels=[
('Your title is not the evidence.','Operations Coordinator tells us the role.','Scheduling deliveries. Updating records. Preparing reports. Show the work.','Keep the facts. Make the relevance clear.'),
('A better CV needs no fake numbers.','No evidence for “improved efficiency 30%”? Leave it out.','Try: action + tool + output.','Collected delivery updates. Used Excel. Prepared monthly reports.'),
('Before I spend $9, show me the work.','BEFORE\nPrepared monthly reports using Excel.','AFTER\nConsolidated delivery and purchase-order updates into monthly Excel reports.','Fictional example. A clearer edit must stay within the source facts.'),
('Missing from your CV ≠ missing experience.','A job asks for SAP. Your CV does not mention it.','Check your actual experience. Have you used it? For which task?','Add the true context. Never let AI guess your history.'),
('Could this letter belong to anyone?','“Passionate. Motivated. Hardworking.”\nWhat does that prove?','Pick a job requirement. Connect it to work you actually did.','One specific example says more than a page of adjectives.'),
('Ask your CV three questions.','1\nWhat did I actually do?','2\nWhat tool or information did I use?','3\nWhat did I produce? Make the sentence useful.'),
('Do not interview with a fictional story.','Start with a real responsibility from your CV.','Recall one occasion. What did YOU do? What happened?','No remembered metric? Do not invent one.'),
('Your CV says “supported”. Your letter says “led”.','Which one is true?','Tailoring should not promote you into a role you never held.','Keep the dates, employer and responsibility level consistent.'),
('A score is not an application.','A useful pack needs documents you can use.','CV + cover letter. Interview starting answers. Specific next steps.','Explore the fictional example. Planned pack: $9 once. Not on sale yet.'),
('Before you press submit…','Check your contact details, dates and employers.','Verify every skill, tool and qualification.','Open the exported file. Read every sentence. Own every claim.')]
accents=['#B8F36B','#76D8EF','#FFCB83','#C4B5FD','#F5A9C4']
contact=Image.new('RGB',(1080,960),'#e9edf1'); manifests=[]
def wrap(text,draw,font,width):
 lines=[]
 for para in text.split('\n'):
  line=''
  for word in para.split():
   candidate=(line+' '+word).strip()
   if draw.textlength(candidate,font=font)>width and line:lines.append(line);line=word
   else:line=candidate
  lines.append(line)
 return lines
for i,scenes in enumerate(reels,1):
 folder=out/f'{i:02d}';folder.mkdir(exist_ok=True); accent=accents[(i-1)%5]
 for j,txt in enumerate(scenes+('Explore the full example.\nmybillflow.online',)):
  im=Image.new('RGB',(1080,1920),'#101F30');d=ImageDraw.Draw(im)
  d.rounded_rectangle((72,110,400,177),radius=25,fill=accent);d.text((98,124),'APPLYPILOT',font=F(35),fill='#101F30')
  d.text((78,245),f'APPLICATION NOTES  /  {i:02d}',font=F(27),fill='#A7B7C8')
  d.text((78,390),['THE HOOK','THE PROBLEM','MAKE IT SPECIFIC','THE TAKEAWAY','EXPLORE'][j],font=F(30),fill=accent)
  size=82 if j==0 else 70;f=F(size);lines=wrap(txt,d,f,895)
  y=500
  for line in lines:d.text((78,y),line,font=f,fill='#F8FAFC');y+=int(size*1.24)
  d.line((78,1430,1000,1430),fill='#3C4D60',width=2)
  d.text((78,1485),'Digital CV & job-search assistance',font=F(30),fill='#B9C7D6')
  d.text((78,1538),'Preview-stage product · No job guarantees',font=F(27),fill='#B9C7D6')
  d.text((78,1605),'Fictional examples only',font=F(27),fill=accent)
  for k in range(5):d.rounded_rectangle((78+k*185,1710,238+k*185,1718),radius=4,fill=accent if k<=j else '#3C4D60')
  p=folder/f'{j+1}.png';im.save(p)
  if j==0:contact.paste(im.resize((216,384)),(((i-1)%5)*216,((i-1)//5)*480))
 listing=folder/'frames.txt';listing.write_text(''.join(f"file '{j+1}.png'\nduration 5\n" for j in range(5))+"file '5.png'\n")
 video=out/f'ApplyPilot-Reel-{i:02d}.mp4'
 subprocess.run([ff,'-hide_banner','-loglevel','error','-y','-f','concat','-safe','0','-i',str(listing),'-vf','fps=24,format=yuv420p','-t','25','-c:v','libx264','-preset','veryfast','-crf','21','-movflags','+faststart',str(video)],check=True)
 # Decode every frame; fail on corrupted output.
 subprocess.run([ff,'-v','error','-xerror','-i',str(video),'-f','null','-'],check=True)
 manifests.append({'file':video.name,'duration_seconds':25,'width':1080,'height':1920,'fps':24,'audio':'none','full_decode':'passed','hook':scenes[0]})
 print(f'Rendered and decoded {video.name}',flush=True)
contact.save(out/'contact-sheet.png');(out/'manifest.json').write_text(json.dumps(manifests,indent=2)+'\n')
