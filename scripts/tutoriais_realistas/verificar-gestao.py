"""Verificação dos MP4s completos, áudio, legendas e requisições simuladas."""
import array,hashlib,json,re,subprocess,wave
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
KINDS=['criar-temporada','criar-modalidade','criar-cronograma','criar-turma']
ff=ROOT/'tmp/tutoriais-2026/ffmpeg.exe';results=[]
for kind in KINDS:
 folder=ROOT/f'output/tutorial-{kind}-realista';temp=ROOT/f'tmp/tutorial-{kind}'
 timeline=temp/'timeline-editado.json' if (temp/'timeline-editado.json').exists() else temp/'timeline.json'
 meta=json.loads(timeline.read_text(encoding='utf-8'))
 assert meta.get('synchronized'),'Execute a correção de sincronia antes de publicar.'
 assert meta['simulation'] and not meta['errors'] and not meta['blocked'] and len(meta['posts'])==1
 chapters=json.loads((folder/'capitulos.json').read_text(encoding='utf-8'));assert len(chapters)==len(meta['scenes'])
 assert (folder/'legendas.vtt').stat().st_size>1000
 for s in meta['scenes']:
  assert s['audioStart'] >= s['start']+.25 and s['audioStart']+s['audioDuration'] <= s['end'],s['id']
  with wave.open(str(temp/(s['id']+'.wav')),'rb') as wav:
   samples=array.array('h');samples.frombytes(wav.readframes(wav.getnframes()));assert max(map(abs,samples))>1000
 for name in ['tutorial.mp4','versao-leve-celular.mp4']:
  f=folder/name;probe=subprocess.run([str(ff),'-i',str(f)],capture_output=True,encoding='utf-8',errors='replace').stderr
  assert 'Video: h264' in probe and 'Audio: aac' in probe
  m=re.search(r'Duration: (\d+):(\d+):([\d.]+)',probe);duration=int(m[1])*3600+int(m[2])*60+float(m[3])
  assert abs(duration-meta['scenes'][-1]['end'])<2 and f.stat().st_size<40*1048576
  decoded=subprocess.run([str(ff),'-v','error','-threads','2','-i',str(f),'-f','null','-'],capture_output=True,text=True)
  assert decoded.returncode==0 and not decoded.stderr.strip(),decoded.stderr
  result={'kind':kind,'file':name,'seconds':duration,'mb':round(f.stat().st_size/1048576,2),'sha256':hashlib.sha256(f.read_bytes()).hexdigest(),'decode':'OK'}
  results.append(result);print(json.dumps(result),flush=True)
(ROOT/'output/tutoriais-gestao/verificacao.json').write_text(json.dumps(results,indent=2),encoding='utf-8')
