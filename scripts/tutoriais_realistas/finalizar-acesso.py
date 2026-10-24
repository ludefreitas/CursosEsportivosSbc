import json, wave, array, subprocess, re, shutil, sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
kind=sys.argv[1]
TMP=ROOT/('tmp/tutorial-'+kind); OUT=ROOT/('output/tutorial-'+kind+'-realista'); OUT.mkdir(parents=True,exist_ok=True)
meta=json.loads((TMP/'timeline.json').read_text(encoding='utf-8-sig'))
scenes=meta['scenes'];duration=scenes[-1]['end']+.6
with wave.open(str(TMP/(scenes[0]['id']+'.wav')),'rb') as wav: rate=wav.getframerate(); channels=wav.getnchannels(); sample_width=wav.getsampwidth()
assert sample_width==2
samples=array.array('h',[0])*int((duration+1)*rate*channels)
for scene in scenes:
    with wave.open(str(TMP/(scene['id']+'.wav')),'rb') as wav:
        assert (wav.getframerate(),wav.getnchannels(),wav.getsampwidth())==(rate,channels,sample_width)
        speech=array.array('h');speech.frombytes(wav.readframes(wav.getnframes()))
    pos=int(scene['start']*rate*channels);samples[pos:pos+len(speech)]=speech
with wave.open(str(TMP/'narracao-completa.wav'),'wb') as wav:
    wav.setnchannels(channels);wav.setsampwidth(sample_width);wav.setframerate(rate);wav.writeframes(samples.tobytes())
def stamp(value,comma=True):
    ms=round(value*1000);seconds,ms=divmod(ms,1000);minutes,seconds=divmod(seconds,60);hours,minutes=divmod(minutes,60)
    return f'{hours:02}:{minutes:02}:{seconds:02}'+(',' if comma else '.')+f'{ms:03}'
srt=[];vtt=['WEBVTT\n'];chapters=[];index=1
for scene in scenes:
    chapters.append({'seconds':round(scene['start'],2),'title':scene['title']})
    chunks=[];chunk=''
    for word in scene['speech'].split():
        if len(chunk)+len(word)>105:chunks.append(chunk);chunk=''
        chunk+=((' ' if chunk else '')+word)
    if chunk:chunks.append(chunk)
    weights=sum(len(chunk) for chunk in chunks);elapsed=scene['start']
    for chunk in chunks:
        end=elapsed+scene['audioDuration']*len(chunk)/weights
        srt.append(f'{index}\n{stamp(elapsed)} --> {stamp(end)}\n{chunk}\n')
        vtt.append(f'{stamp(elapsed,False)} --> {stamp(end,False)}\n{chunk}\n')
        index+=1;elapsed=end
(OUT/'legendas.srt').write_text('\n'.join(srt),encoding='utf-8');(OUT/'legendas.vtt').write_text('\n'.join(vtt),encoding='utf-8')
(OUT/'capitulos.json').write_text(json.dumps(chapters,ensure_ascii=False,indent=2),encoding='utf-8')
(OUT/'roteiro.txt').write_text('\n\n'.join(s['title']+'\n'+s['speech'] for s in scenes),encoding='utf-8')
ff=ROOT/'tmp/tutoriais-2026/ffmpeg.exe'
source=Path((TMP/'video-path.txt').read_text().strip())
intervals=[f'between(t,{scene["start"]:.3f},{scene["end"]:.3f})' for i,scene in enumerate(scenes) if i in ([1,4,5] if kind=='login' else [5,6,7,8,9])]
enable='+'.join(intervals)
pickups=[]
for index,name in []:
    file=TMP/name
    if file.exists(): pickups.append((index,json.loads(file.read_text(encoding='utf-8-sig'))))
input_args=['-ss',str(meta['lead']),'-i',str(source)]
parts=[]
if pickups:
    parts.append('[0:v]split='+str(len(pickups)+1)+''.join(f'[raw{i}]' for i in range(len(pickups)+1)))
    labels=[];previous=0
    for n,(index,pickup) in enumerate(pickups):
        scene=scenes[index]
        parts.append(f'[raw{n}]trim=start={previous}:end={scene["start"]},setpts=PTS-STARTPTS,fps=25[before{n}]')
        input_args+=['-ss',str(pickup['start']),'-i',pickup['path']]
        parts.append(f'[{n+1}:v]trim=duration={scene["end"]-scene["start"]},setpts=PTS-STARTPTS,fps=25[pickup{n}]')
        labels += [f'[before{n}]',f'[pickup{n}]'];previous=scene['end']
    parts.append(f'[raw{len(pickups)}]trim=start={previous},setpts=PTS-STARTPTS,fps=25[after]')
    labels.append('[after]')
    parts.append(''.join(labels)+f'concat=n={len(labels)}:v=1:a=0[edited]')
    source_label='[edited]'
else: source_label='[0:v]'
parts.append(source_label+'split[base][detail]')
if kind.startswith('atestado-'):
    # Mantém o título e os botões do modal inteiros durante a ampliação.
    parts.append('[detail]crop=1120:700:240:80,scale=1176:735[zoom]')
    parts.append(f"[base][zoom]overlay=212:65:enable='{enable}'[video]")
else:
    parts.append('[detail]crop=1440:630:80:105,scale=1600:700[zoom]')
    parts.append(f"[base][zoom]overlay=0:70:enable='{enable}'[video]")
filter_graph=';'.join(parts)
output=OUT/'tutorial.mp4'
args=[str(ff),'-y','-filter_complex_threads','2']+input_args+['-i',str(TMP/'narracao-completa.wav'),'-filter_complex',filter_graph,'-map','[video]','-map',f'{len(pickups)+1}:a','-t',str(duration),'-r','25','-c:v','libx264','-threads','4','-preset','veryfast','-crf','21','-pix_fmt','yuv420p','-af','loudnorm=I=-16:TP=-1.5:LRA=11','-c:a','aac','-ar','44100','-b:a','128k','-movflags','+faststart',str(output)]
subprocess.run(args,check=True,stdout=subprocess.DEVNULL,stderr=(TMP/'render.log').open('w'))
subprocess.run([str(ff),'-y','-ss',str(scenes[4]['start']+2),'-i',str(output),'-frames:v','1',str(OUT/'capa.png')],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
subprocess.run([str(ff),'-y','-i',str(output),'-vf','scale=1280:720','-c:v','libx264','-preset','veryfast','-crf','25','-c:a','aac','-ar','44100','-b:a','96k','-movflags','+faststart',str(OUT/'versao-leve-celular.mp4')],check=True,stdout=subprocess.DEVNULL,stderr=(TMP/'render-mobile.log').open('w'))
links=''.join(f'<button data-time="{c["seconds"]}">{int(c["seconds"]//60):02}:{int(c["seconds"]%60):02} — {c["title"]}</button>' for c in chapters)
title='Entrar no site e recuperar a senha' if kind=='login' else 'Cadastrar um dependente'
title = {'atestado-clinico': 'Como adicionar um atestado clínico', 'atestado-dermatologico': 'Como inserir um atestado dermatológico'}.get(kind, title)
html='''<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Tutorial passo a passo</title><style>body{margin:24px auto;padding:0 18px;max-width:1100px;font:17px/1.5 Arial;background:#f0f5f8;color:#173e50}video{width:100%;border-radius:14px;background:#000}button{display:block;text-align:left;width:100%;padding:10px;background:white;border:1px solid #ccdce5;margin:5px 0;border-radius:7px;cursor:pointer}a{color:#116287}</style><h1>Tutorial passo a passo</h1><p>Telas reais do sistema, com cliques destacados, ampliação, rolagem, narração e legendas. Demonstração local com dados fictícios; nenhum cadastro foi enviado ao banco.</p><video controls poster="capa.png" src="tutorial.mp4"><track kind="subtitles" src="legendas.vtt" srclang="pt-BR" label="Português"></video><p><a href="tutorial.mp4" download>Baixar vídeo</a> · <a href="versao-leve-celular.mp4" download>Versão leve para celular</a> · <a href="legendas.srt" download>Baixar legendas</a></p><h2>Ir para uma etapa</h2>'''+links+'''<script>document.querySelectorAll('[data-time]').forEach(b=>b.onclick=()=>{const v=document.querySelector('video');v.currentTime=Number(b.dataset.time);v.play();v.scrollIntoView({behavior:'smooth'});});</script></html>'''
html=html.replace('Tutorial passo a passo',title)
(OUT/'assistir.html').write_text(html,encoding='utf-8')
print(json.dumps({'duration':round(duration,1),'main_mb':round(output.stat().st_size/1048576,1),'mobile_mb':round((OUT/'versao-leve-celular.mp4').stat().st_size/1048576,1)},ensure_ascii=False))
