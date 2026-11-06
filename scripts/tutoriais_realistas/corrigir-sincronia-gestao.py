"""Corta branco/loading da gravação e sincroniza cada etapa com sua narração.
Conserva as ações gravadas; prolonga somente o último quadro limpo quando a fala exige.
"""
import json,math,shutil,subprocess,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2];FF=ROOT/'tmp/tutoriais-2026/ffmpeg.exe'
kind=sys.argv[1];folder=ROOT/f'tmp/tutorial-{kind}';work=folder/'edicao-sincronizada';work.mkdir(exist_ok=True)
meta=json.loads((folder/'timeline.json').read_text(encoding='utf-8-sig'))
analysis=json.loads((folder/'sincronia-analisada.json').read_text());frames=analysis['frames'];step=1/analysis['fps']
source=analysis['source'];scenes=meta['scenes'];starts=[];cursor=0;still_scenes=set()
for i,scene in enumerate(scenes):
 for n in range(cursor,len(frames)-2):
  if all(f['scene']==i and not f['blank'] for f in frames[n:n+3]):
   starts.append(frames[n]['t']);cursor=n+3;break
 else:
  matches=[n for n in range(cursor,len(frames)) if frames[n]['scene']==i and not frames[n]['blank']]
  if not matches:raise RuntimeError('Cabeçalho da etapa não localizado: '+scene['id'])
  n=matches[0];starts.append(frames[n]['t']);cursor=n+1;still_scenes.add(i)
end=frames[-1]['t']+step
bad=[(s['start']-.25,s['end']+.25) for s in analysis['segments'] if s['blank'] or s['loading']]
backup=ROOT/f'tmp/videos-gestao-antes-correcao/{kind}'
if not backup.exists():
 backup.mkdir(parents=True)
 for name in ['tutorial.mp4','versao-leve-celular.mp4','legendas.srt','legendas.vtt','capitulos.json','informacoes.json','assistir.html','capa.png']:
  f=ROOT/f'output/tutorial-{kind}-realista/{name}'
  if f.exists():shutil.copy2(f,backup/name)
edited=[];zero=0;clips=[]
for i,scene in enumerate(scenes):
 start=starts[i]+.08;stop=(starts[i+1]-.08) if i+1<len(scenes) else end-.08
 ranges=[(start,stop)]
 for b,e in bad:
  updated=[]
  for a,z in ranges:
   if b>=z or e<=a:updated.append((a,z))
   else:
    if b>a:updated.append((a,b))
    if e<z:updated.append((e,z))
  ranges=updated
 ranges=[(a,b) for a,b in ranges if b-a>=.08]
 assert ranges,scene['id']
 length=sum(b-a for a,b in ranges)
 duration=math.ceil(max(length,scene['audioDuration']+1.0)*25)/25
 clip=work/f'{i:02}.mp4';clips.append(clip)
 # Uma entrada por trecho permite seek antes da decodificação, sem ler o vídeo inteiro por cena.
 inputs=[];filters=[]
 for j,(a,b) in enumerate(ranges):
  inputs+=['-ss',str(a),'-t',str(b-a),'-i',source]
  filters.append(f'[{j}:v]setpts=PTS-STARTPTS,fps=25,format=yuv420p[v{j}]')
 joined=''.join(f'[v{j}]' for j in range(len(ranges)))
 filters.append(joined+f'concat=n={len(ranges)}:v=1:a=0,tpad=stop_mode=clone:stop_duration={duration},trim=duration={duration},setpts=PTS-STARTPTS[out]')
 command=[str(FF),'-y','-v','error','-threads','2']+inputs+['-filter_complex_threads','1','-filter_complex',';'.join(filters),'-map','[out]','-an','-c:v','libx264','-threads','2','-preset','ultrafast','-crf','18','-movflags','+faststart',str(clip)]
 if i in still_scenes:
  # O screencast reteve o título anterior nesta explicação sem preenchimento.
  # Utiliza a captura real da etapa, sem reconstituir ou inventar a interface.
  duration=math.ceil((scene['audioDuration']+1.0)*25)/25
  command=[str(FF),'-y','-v','error','-loop','1','-i',str(folder/f'cena-{i}.png'),'-t',str(duration),'-r','25','-pix_fmt','yuv420p','-an','-c:v','libx264','-threads','2','-preset','ultrafast','-crf','18',str(clip)]
 subprocess.run(command,check=True,stdout=subprocess.DEVNULL,stderr=(work/f'{i:02}.log').open('w'))
 edited.append(scene|{'start':round(zero,3),'end':round(zero+duration,3),'audioStart':round(zero+.3,3),'source_ranges':ranges,'visible_before_audio':.3,'captured_still':i in still_scenes,'held_final_frame':round(max(0,duration-length),3)})
 zero+=duration;print(kind+' '+scene['id']+' OK',flush=True)
(work/'concat.txt').write_text(''.join("file '"+p.as_posix()+"'\n" for p in clips),encoding='utf-8')
output=work/'video-sincronizado.mp4'
subprocess.run([str(FF),'-y','-v','error','-f','concat','-safe','0','-i',str(work/'concat.txt'),'-c','copy','-movflags','+faststart',str(output)],check=True)
result=meta|{'scenes':edited,'lead':0,'video_source':str(output),'synchronized':True,'removed_segments':[s for s in analysis['segments'] if s['blank'] or s['loading']],'original_video_source':source}
(folder/'timeline-editado.json').write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf-8')
print(kind+': linha do tempo corrigida; '+str(round(zero,2))+' segundos.',flush=True)
