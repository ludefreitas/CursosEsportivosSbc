import json,subprocess,wave,math,sys
from pathlib import Path
from PIL import Image,ImageDraw,ImageFont
ROOT=Path(__file__).resolve().parents[2];TMP=ROOT/'tmp/tutoriais-2026';OUT=ROOT/'output/tutoriais-2026'
FF=str(TMP/'ffmpeg.exe');W,H=1280,720
videos=json.loads((TMP/'videos.json').read_text(encoding='utf-8'));fonts={}
def font(size,bold=False):
 key=(size,bold)
 if key not in fonts:fonts[key]=ImageFont.truetype('C:/Windows/Fonts/arial'+('bd' if bold else '')+'.ttf',size)
 return fonts[key]
def wrap(value,size,width,bold=False):
 lines=[];line=''
 for word in value.split():
  test=(line+' '+word).strip()
  if line and font(size,bold).getlength(test)>width:lines.append(line);line=word
  else:line=test
 if line:lines.append(line)
 return lines
def txt(d,x,y,value,size=21,color='#193942',bold=False,width=None,leading=None):
 lines=wrap(value,size,width,bold) if width else [value]
 for line in lines:d.text((x,y),line,font=font(size,bold),fill=color);y+=leading or size+7
 return y
def card(d,box,color,outline=None):d.rounded_rectangle(box,radius=15,fill=color,outline=outline,width=2)
def image(v,s,index):
 im=Image.new('RGB',(W,H),'#edf3f7');d=ImageDraw.Draw(im)
 d.rectangle((0,0,W,86),fill='#14536a');txt(d,35,18,'CURSOS ESPORTIVOS SBC',25,'white',True)
 txt(d,35,53,'Tutoriais do sistema • Português do Brasil',16,'#d6eef5')
 txt(d,930,28,'GUIA ILUSTRADO',21,'white',True)
 card(d,(32,111,492,558),'#edf0fc')
 txt(d,53,132,f'PASSO {index+1:02} DE {len(v["scenes"]):02}',17,'#4169e1',True)
 y=txt(d,53,177,s['title'],32,'#14536a',True,410,39)+22
 bullets=s['bullets'] or [f'{a}: {b}' for a,b in s['fields'][:3]]
 for b in bullets:
  d.ellipse((54,y+8,61,y+15),fill='#d96b1f');y=txt(d,76,y,b,21,width=386,leading=28)+17
  assert y<560,(v['slug'],index,'coluna esquerda excedida',y)
 card(d,(518,111,1247,558),'white','#c7d8e5')
 txt(d,542,127,'Demonstração • dados fictícios',18,'#526970')
 d.line((519,164,1245,164),fill='#c7d8e5',width=2)
 for j,(label,value) in enumerate(s['fields']):
  y=184+j*77
  txt(d,543,y,label,18,'#14536a',True)
  card(d,(541,y+25,1222,y+70),'#f5f8fc','#cbd9e7')
  lines=wrap(value,18,650)
  assert len(lines)<=2,(v['slug'],index,'campo excedido',value)
  txt(d,554,y+30,value,18,width=650,leading=20)
 card(d,(541,510,1222,548),'#14536a')
 txt(d,558,517,s['action'],20,'white',True)
 card(d,(32,580,1247,683),'#173b49')
 lines=wrap(s['speech'],17,1175)
 assert len(lines)<=4,(v['slug'],index,'legenda excedida',len(lines))
 for n,line in enumerate(lines):txt(d,51,588+n*21,line,17,'white')
 txt(d,34,694,'Demonstração ilustrada baseada nos formulários e nas regras do projeto.',13,'#526970')
 d.rectangle((920,698,1246,704),fill='#cad8e5');d.rectangle((920,698,920+round(326*(index+1)/len(v['scenes'])),704),fill='#4169e1')
 return im
def stamp(seconds):
 ms=round(seconds*1000);return f'{ms//3600000:02}:{ms//60000%60:02}:{ms//1000%60:02},{ms%1000:03}'
summary=[]
for v in videos:
 if len(sys.argv)>1 and v['slug'] not in sys.argv[1:]: continue
 target=OUT/v['slug'];target.mkdir(exist_ok=True);parts=[];subtitles=[];elapsed=0;audio_params=None
 with wave.open(str(target/'narracao.wav'),'wb') as dest:
  for i,s in enumerate(v['scenes']):
   im=image(v,s,i);pic=target/f'cena-{i+1:02}.png';im.save(pic)
   with wave.open(str(TMP/f'{v["slug"]}-{i:02}.wav'),'rb') as audio:
    params=(audio.getnchannels(),audio.getsampwidth(),audio.getframerate());raw=audio.readframes(audio.getnframes())
   if audio_params is None:audio_params=params;dest.setparams((*params,0,'NONE','not compressed'))
   assert audio_params==params
   rate=params[2];unit=params[0]*params[1];duration=math.ceil((len(raw)/(rate*unit)+1)*8)/8
   dest.writeframes(b'\0'*int(.25*rate)*unit+raw+b'\0'*max(0,(round(duration*rate)-int(.25*rate)-len(raw)//unit))*unit)
   subtitles.append(f'{i+1}\n{stamp(elapsed)} --> {stamp(elapsed+duration)}\n{s["speech"]}')
   parts += ["file '"+pic.resolve().as_posix()+"'",f'duration {duration:.3f}'];elapsed+=duration
  parts.append("file '"+pic.resolve().as_posix()+"'")
 (target/'cenas.txt').write_text('\n'.join(parts),encoding='utf-8');(target/'legendas.srt').write_text('\n\n'.join(subtitles)+'\n',encoding='utf-8')
 (target/'roteiro.md').write_text('# '+v['title']+'\n\nGuia ilustrado com narração em português e dados fictícios.\n\n'+'\n\n'.join(f'## {i+1}. {s["title"]}\n\n{s["speech"]}' for i,s in enumerate(v['scenes'])),encoding='utf-8')
 cmd=[FF,'-y','-v','error','-f','concat','-safe','0','-i',str(target/'cenas.txt'),'-i',str(target/'narracao.wav'),'-t',str(elapsed),'-vf','fps=8','-c:v','libx264','-preset','ultrafast','-crf','23','-pix_fmt','yuv420p','-c:a','aac','-b:a','96k','-movflags','+faststart',str(target/(v['slug']+'.mp4'))]
 with (target/'render.log').open('wb') as log:subprocess.run(cmd,stdout=log,stderr=log,check=True)
 contact=Image.new('RGB',(1280,180*math.ceil(len(v['scenes'])/4)),'#cad8e5')
 for i in range(len(v['scenes'])):
  im=Image.open(target/f'cena-{i+1:02}.png');im.thumbnail((320,180));contact.paste(im,((i%4)*320,(i//4)*180))
 contact.save(target/'resumo.jpg',quality=92)
 summary.append(dict(slug=v['slug'],title=v['title'],duration=round(elapsed,2),file=str(target/(v['slug']+'.mp4'))))
 print(json.dumps(summary[-1],ensure_ascii=False),flush=True)
if len(sys.argv)>1:
 previous=json.loads((OUT/'indice.json').read_text(encoding='utf-8')) if (OUT/'indice.json').exists() else []
 changed={v['slug']:v for v in summary}
 summary=[changed.get(v['slug'],v) for v in previous] if previous else summary
(OUT/'indice.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),encoding='utf-8')
(OUT/'LEIA-ME.md').write_text('# Tutoriais narrados — Cursos Esportivos SBC\n\nNove guias ilustrados, em português, com dados fictícios, narração e legendas na imagem. Cada pasta inclui MP4, SRT, roteiro e imagens das cenas.\n\n'+'\n'.join(f'- [{v["title"]}]({v["slug"]}/{v["slug"]}.mp4) — {v["duration"]:.0f} segundos.' for v in summary),encoding='utf-8')
