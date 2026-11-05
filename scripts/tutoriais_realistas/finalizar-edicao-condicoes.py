"""Exporta gravação das views reais, narração, legendas, capítulos e versão leve."""
import array, html, json, re, subprocess, sys, wave
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
kind=sys.argv[1]
TITLES={'editar-pessoa':'Como editar os dados de uma pessoa ou dependente','condicao-pcd':'Como enviar a documentação de Pessoa com Deficiência (PCD)','condicao-plm':'Como enviar a documentação de Pessoa com Laudo Médico de Doença (PLM)','condicao-pvs':'Como enviar a documentação de Pessoa em Situação de Vulnerabilidade Social (PVS)'}
TITLES.update({'criar-temporada':'Como criar uma temporada','criar-modalidade':'Como criar uma modalidade','criar-cronograma':'Como criar um cronograma de modalidade','criar-turma':'Como criar uma turma'})
assert kind in TITLES
TMP=ROOT/('tmp/tutorial-'+kind);OUT=ROOT/('output/tutorial-'+kind+'-realista');OUT.mkdir(parents=True,exist_ok=True)
meta=json.loads((TMP/'timeline.json').read_text(encoding='utf-8-sig'));scenes=meta['scenes'];duration=scenes[-1]['end']+.6
assert meta['simulation'] and not meta['errors'] and not meta['blocked']
with wave.open(str(TMP/(scenes[0]['id']+'.wav')),'rb') as wav: rate=wav.getframerate();channels=wav.getnchannels();width=wav.getsampwidth()
assert width==2
samples=array.array('h',[0])*int((duration+1)*rate*channels)
for scene in scenes:
 with wave.open(str(TMP/(scene['id']+'.wav')),'rb') as wav:
  assert (wav.getframerate(),wav.getnchannels(),wav.getsampwidth())==(rate,channels,width)
  speech=array.array('h');speech.frombytes(wav.readframes(wav.getnframes()))
 assert scene['end']-scene['start']>=scene['audioDuration'],scene['id']
 start=int(scene['start']*rate*channels);samples[start:start+len(speech)]=speech
with wave.open(str(TMP/'narracao-completa.wav'),'wb') as wav:
 wav.setnchannels(channels);wav.setsampwidth(width);wav.setframerate(rate);wav.writeframes(samples.tobytes())
def stamp(value,comma=True):
 ms=round(value*1000);s,ms=divmod(ms,1000);m,s=divmod(s,60);h,m=divmod(m,60)
 return f'{h:02}:{m:02}:{s:02}'+(',' if comma else '.')+f'{ms:03}'
srt=[];vtt=['WEBVTT\n'];chapters=[];index=1
for scene in scenes:
 chapters.append({'seconds':round(scene['start'],2),'title':scene['title']})
 chunks=[];chunk=''
 for word in scene['speech'].split():
  if len(chunk)+len(word)>98:chunks.append(chunk);chunk=''
  chunk+=(' ' if chunk else '')+word
 if chunk:chunks.append(chunk)
 total=sum(map(len,chunks));elapsed=scene['start']
 for chunk in chunks:
  end=elapsed+scene['audioDuration']*len(chunk)/total
  srt.append(f'{index}\n{stamp(elapsed)} --> {stamp(end)}\n{chunk}\n');vtt.append(f'{stamp(elapsed,False)} --> {stamp(end,False)}\n{chunk}\n');index+=1;elapsed=end
(OUT/'legendas.srt').write_text('\n'.join(srt),encoding='utf-8');(OUT/'legendas.vtt').write_text('\n'.join(vtt),encoding='utf-8')
(OUT/'capitulos.json').write_text(json.dumps(chapters,ensure_ascii=False,indent=2),encoding='utf-8')
(OUT/'roteiro.txt').write_text('\n\n'.join(s['title']+'\n'+s['speech'] for s in scenes),encoding='utf-8')
ff=ROOT/'tmp/tutoriais-2026/ffmpeg.exe';source=Path((TMP/'video-path.txt').read_text().strip());output=OUT/'tutorial.mp4'
args=[str(ff),'-y','-ss',str(meta['lead']),'-i',str(source),'-i',str(TMP/'narracao-completa.wav'),'-map','0:v:0','-map','1:a:0','-t',str(duration),'-r','25','-c:v','libx264','-threads','2','-preset','veryfast','-crf','24','-pix_fmt','yuv420p','-af','loudnorm=I=-16:TP=-1.5:LRA=11','-c:a','aac','-ar','44100','-b:a','128k','-movflags','+faststart',str(output)]
subprocess.run(args,check=True,stdout=subprocess.DEVNULL,stderr=(TMP/'render.log').open('w'))
print(kind+': vídeo principal exportado.',flush=True)
subprocess.run([str(ff),'-y','-ss',str(scenes[min(len(scenes)-1,{'editar-pessoa':7,'condicao-pcd':10,'condicao-plm':8,'condicao-pvs':8}.get(kind,4))]['start']+2),'-i',str(output),'-frames:v','1',str(OUT/'capa.png')],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
subprocess.run([str(ff),'-y','-i',str(output),'-vf','scale=1280:720','-r','20','-c:v','libx264','-threads','2','-preset','veryfast','-crf','28','-c:a','aac','-ar','44100','-b:a','96k','-movflags','+faststart',str(OUT/'versao-leve-celular.mp4')],check=True,stdout=subprocess.DEVNULL,stderr=(TMP/'render-mobile.log').open('w'))
links=''.join(f'<button data-time="{c["seconds"]}">{int(c["seconds"]//60):02}:{int(c["seconds"]%60):02} — {html.escape(c["title"])}</button>' for c in chapters)
page='''<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TITULO</title><style>body{margin:24px auto;padding:0 18px;max-width:1120px;font:17px/1.5 Arial;background:#f0f5f8;color:#173e50}video{width:100%;border-radius:14px;background:#000}button{display:block;text-align:left;width:100%;padding:10px;background:white;border:1px solid #ccdce5;margin:5px 0;border-radius:7px;cursor:pointer}a{color:#116287}</style><h1>TITULO</h1><p>Telas e formulários reais do sistema, com narração em português, cliques destacados, rolagem, legendas e capítulos. Demonstração com dados fictícios e envios simulados.</p><video controls playsinline preload="metadata" poster="capa.png" src="tutorial.mp4"><track kind="subtitles" src="legendas.vtt" srclang="pt-BR" label="Português"></video><p><a href="tutorial.mp4" download>Baixar vídeo completo</a> · <a href="versao-leve-celular.mp4" download>Baixar versão leve</a> · <a href="legendas.srt" download>Legendas</a></p><h2>Ir para uma etapa</h2>CAPITULOS<script>document.querySelectorAll('[data-time]').forEach(b=>b.onclick=()=>{const v=document.querySelector('video');v.currentTime=Number(b.dataset.time);v.play().catch(()=>{});v.scrollIntoView({behavior:'smooth'});});</script></html>'''.replace('TITULO',html.escape(TITLES[kind])).replace('CAPITULOS',links)
(OUT/'assistir.html').write_text(page,encoding='utf-8')
info={'kind':kind,'title':TITLES[kind],'duration':round(duration,2),'main_mb':round(output.stat().st_size/1048576,2),'mobile_mb':round((OUT/'versao-leve-celular.mp4').stat().st_size/1048576,2),'chapters':len(chapters),'voice':'Microsoft Maria Desktop — pt-BR','simulated_submissions':True}
(OUT/'informacoes.json').write_text(json.dumps(info,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(info,ensure_ascii=False),flush=True)
