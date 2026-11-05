import hashlib,json,re,subprocess,sys,wave,array
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
KINDS=['editar-pessoa','condicao-pcd','condicao-plm','condicao-pvs']
ff=ROOT/'tmp/tutoriais-2026/ffmpeg.exe'
if '--audio-only' in sys.argv:
 for kind in KINDS:
  scenes=json.loads((ROOT/f'scripts/tutoriais_realistas/{kind}-cenas.json').read_text(encoding='utf-8-sig'))
  seconds=0
  for scene in scenes:
   with wave.open(str(ROOT/f'tmp/tutorial-{kind}/{scene["id"]}.wav'),'rb') as wav:
    duration=wav.getnframes()/wav.getframerate();seconds+=duration
    samples=array.array('h');samples.frombytes(wav.readframes(wav.getnframes()))
    assert duration>3 and max(map(abs,samples))>1000,scene['id']
  print(kind+': '+str(len(scenes))+' cenas narradas; '+str(round(seconds))+' segundos de voz; áudio não silencioso.',flush=True)
 sys.exit(0)
old=json.loads((ROOT/'tmp/tutoriais-anteriores-preservar-2026-11-04.json').read_text(encoding='utf-8-sig'))
for item in old:
 assert hashlib.sha256(Path(item['Path']).read_bytes()).hexdigest().upper()==item['Hash'],item['Path']
results=[]
for kind in KINDS:
 folder=ROOT/f'output/tutorial-{kind}-realista';meta=json.loads((ROOT/f'tmp/tutorial-{kind}/timeline.json').read_text(encoding='utf-8-sig'))
 assert meta['simulation'] and not meta['errors'] and not meta['blocked']
 assert len(meta['posts'])==(3 if kind=='editar-pessoa' else 1)
 chapters=json.loads((folder/'capitulos.json').read_text(encoding='utf-8'))
 assert len(chapters)==len(meta['scenes'])
 assert (folder/'legendas.srt').stat().st_size>2000 and (folder/'legendas.vtt').stat().st_size>2000
 for filename in ['tutorial.mp4','versao-leve-celular.mp4']:
  file=folder/filename
  probe=subprocess.run([str(ff),'-i',str(file)],capture_output=True,encoding='utf-8',errors='replace').stderr
  assert 'Video: h264' in probe and 'Audio: aac' in probe,probe
  match=re.search(r'Duration: (\d+):(\d+):([\d.]+)',probe);assert match
  duration=int(match[1])*3600+int(match[2])*60+float(match[3]);assert abs(duration-meta['scenes'][-1]['end'])<2
  assert file.stat().st_size<40*1024*1024,'Vídeo acima do limite de 40 MB: '+str(file)
  decoded=subprocess.run([str(ff),'-v','error','-threads','2','-i',str(file),'-f','null','-'],capture_output=True,encoding='utf-8',errors='replace')
  assert decoded.returncode==0 and not decoded.stderr.strip(),decoded.stderr
  result={'kind':kind,'file':filename,'seconds':duration,'mb':round(file.stat().st_size/1048576,2),'sha256':hashlib.sha256(file.read_bytes()).hexdigest(),'decode':'OK'}
  results.append(result);print(json.dumps(result,ensure_ascii=False),flush=True)
(ROOT/'output/tutoriais-edicao-condicoes/verificacao.json').write_text(json.dumps({'previous_videos_preserved':len(old),'videos':results},ensure_ascii=False,indent=2),encoding='utf-8')
