import array,html,json,re,subprocess,wave,zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2];OUT=ROOT/'output/tutoriais-2026';FF=ROOT/'tmp/tutoriais-2026/ffmpeg.exe'
items=json.loads((OUT/'indice.json').read_text(encoding='utf-8'));report=[]
for item in items:
 folder=OUT/item['slug'];video=folder/(item['slug']+'.mp4')
 probe=subprocess.run([str(FF),'-hide_banner','-i',str(video)],capture_output=True,text=True,errors='replace').stderr
 assert '1280x720' in probe and 'Video: h264' in probe and 'Audio: aac' in probe,probe
 result=subprocess.run([str(FF),'-v','error','-i',str(video),'-f','null','-'],capture_output=True)
 assert result.returncode==0 and not result.stderr,(item['slug'],result.stderr)
 with wave.open(str(folder/'narracao.wav'),'rb') as f:
  duration=f.getnframes()/f.getframerate();data=array.array('h',f.readframes(f.getnframes()))
 assert abs(duration-item['duration'])<.2 and max(abs(v) for v in data)>1000
 assert (folder/'legendas.srt').read_text(encoding='utf-8').count(' --> ')>=6
 report.append(dict(video=item['slug'],duration=round(duration,2),bytes=video.stat().st_size,decode='OK',audio='OK',subtitles='OK'))
 print(item['slug']+' OK',flush=True)
(OUT/'validacao.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
with zipfile.ZipFile(OUT/'tutoriais-cursos-esportivos-sbc.zip','w',zipfile.ZIP_DEFLATED) as archive:
 for item in items:
  folder=OUT/item['slug']
  for name in [item['slug']+'.mp4','legendas.srt','roteiro.md','resumo.jpg']:
   archive.write(folder/name,arcname=item['slug']+'/'+name)
 archive.write(OUT/'LEIA-ME.md',arcname='LEIA-ME.md')
content='''<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tutoriais — Cursos Esportivos SBC</title><style>body{font:16px Arial;background:#edf3f7;color:#193942;margin:0}header{padding:32px;background:#14536a;color:white}main{padding:24px;display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:24px}article{background:white;padding:20px;border-radius:16px;border-top:5px solid #4169e1}h2{font-size:21px}video{width:100%;border-radius:10px}a{color:#14536a;font-weight:bold}small{display:block;margin:12px 0}nav{padding:20px}</style><header><h1>Tutoriais do sistema</h1><p>Nove guias ilustrados em português, com narração, legendas e dados fictícios.</p></header><nav><a href="tutoriais-cursos-esportivos-sbc.zip">Baixar todos os vídeos e legendas</a></nav><main>'''
for item in items:
 slug=item['slug'];title=html.escape(item['title']);seconds=round(item['duration'])
 content+=f'<article><h2>{title}</h2><small>{seconds//60}:{seconds%60:02} • Narração em português • 1280 × 720</small><video controls preload="metadata" poster="{slug}/cena-01.png"><source src="{slug}/{slug}.mp4" type="video/mp4"><track kind="subtitles" srclang="pt-BR" label="Português" src="{slug}/legendas.vtt"></video><p><a href="{slug}/{slug}.mp4" download>Baixar vídeo</a> · <a href="{slug}/roteiro.md">Roteiro</a> · <a href="{slug}/legendas.srt">Legendas</a></p></article>'
 srt=(OUT/slug/'legendas.srt').read_text(encoding='utf-8');vtt='WEBVTT\n\n'+re.sub(r'(\d{2}:\d{2}:\d{2}),(\d{3})',r'\1.\2',srt);(OUT/slug/'legendas.vtt').write_text(vtt,encoding='utf-8')
(OUT/'index.html').write_text(content+'</main></html>',encoding='utf-8')
print('Galeria, validação e pacote ZIP concluídos.',flush=True)
