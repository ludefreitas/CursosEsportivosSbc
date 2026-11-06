"""Localiza etapas pelo cabeçalho gravado e detecta telas brancas/loading no vídeo real."""
import json,subprocess,sys
from pathlib import Path
import numpy as np
from PIL import Image
ROOT=Path(__file__).resolve().parents[2];FF=ROOT/'tmp/tutoriais-2026/ffmpeg.exe'
kind=sys.argv[1];folder=ROOT/f'tmp/tutorial-{kind}'
edited='--edited' in sys.argv
meta=json.loads((folder/('timeline-editado.json' if edited else 'timeline.json')).read_text(encoding='utf-8-sig'))
source=ROOT/f'output/tutorial-{kind}-realista/tutorial.mp4' if edited else Path((folder/'video-path.txt').read_text().strip());fps=5
templates=[]
for i,s in enumerate(meta['scenes']):
 im=np.asarray(Image.open(folder/f'cena-{i}.png').convert('RGB').resize((400,225)))
 templates.append((im[2:12,3:240].mean(axis=2)>180).reshape(-1))
templates=np.array(templates)
loading=np.array(json.loads((ROOT/'scripts/tutoriais_realistas/loading-referencia.json').read_text()),dtype=float)
command=[str(FF),'-v','error','-threads','2','-i',str(source),'-vf',f'fps={fps},scale=400:225','-f','rawvideo','-pix_fmt','rgb24','-']
process=subprocess.Popen(command,stdout=subprocess.PIPE)
frames=[];size=400*225*3;i=0
while True:
 b=process.stdout.read(size)
 if not b:break
 assert len(b)==size
 im=np.frombuffer(b,dtype=np.uint8).reshape(225,400,3)
 ink=(im[2:12,3:240].mean(axis=2)>180).reshape(-1)
 scores=np.logical_and(templates,ink).sum(axis=1)/np.maximum(1,np.logical_or(templates,ink).sum(axis=1))
 match=int(scores.argmax());quality=float(scores[match])
 blank=bool(im[:14].mean()>240 or quality<.4)
 loading_difference=float(np.abs(im[117:122,185:215].astype(float)-loading).mean())
 is_loading=loading_difference<10
 frames.append({'t':round(i/fps,3),'scene':match,'quality':round(quality,3),'blank':blank,'loading':is_loading,'loading_difference':round(loading_difference,2)})
 i+=1
assert process.wait()==0
segments=[]
for f in frames:
 if not segments or any(segments[-1][key]!=f[key] for key in ['scene','blank','loading']):segments.append({key:f[key] for key in ['scene','blank','loading']}|{'start':f['t'],'end':f['t']+1/fps})
 else:segments[-1]['end']=round(f['t']+1/fps,3)
result={'kind':kind,'fps':fps,'source':str(source),'frames':frames,'segments':segments}
(folder/('sincronia-editada.json' if edited else 'sincronia-analisada.json')).write_text(json.dumps(result,indent=2),encoding='utf-8')
for i,s in enumerate(meta['scenes']):
 good=[f for f in frames if f['scene']==i and not f['blank'] and not f['loading']]
 print(s['id'],f'{good[0]["t"]:.1f}–{good[-1]["t"]:.1f}' if good else 'SEM QUADROS',flush=True)
print('Cortes:',[(round(s['start'],1),round(s['end'],1),'branco' if s['blank'] else 'loading') for s in segments if s['blank'] or s['loading']],flush=True)
if edited:
 assert not any(f['blank'] or f['loading'] for f in frames),'Ainda há branco/loading no vídeo exportado.'
 for i,s in enumerate(meta['scenes']):
  t=s['audioStart'];sample=min(frames,key=lambda f:abs(f['t']-t))
  assert sample['scene']==i,(s['id'],sample)
 print('OK: nenhuma tela branca/loading e cabeçalho correto antes de cada narração.',flush=True)
