"""Calibra o início pelo primeiro título visível na gravação, a 25 quadros/s."""
import json, subprocess, sys
from pathlib import Path
from PIL import Image, ImageChops, ImageStat

ROOT = Path(__file__).resolve().parents[2]
kind = sys.argv[1]
tmp = ROOT / ('tmp/tutorial-' + kind)
meta = json.loads((tmp / 'timeline.json').read_text(encoding='utf-8'))
source = (tmp / 'video-path.txt').read_text().strip()
ff = ROOT / 'tmp/tutoriais-2026/ffmpeg.exe'
target = Image.open(tmp / 'cena-0.png').crop((0, 0, 900, 54)).convert('L').resize((450, 27)).point(lambda p: 255 if p > 150 else 0)
raw = subprocess.run([str(ff), '-v', 'error', '-i', source, '-t', '20', '-vf',
    'fps=25,crop=900:54:0:0,scale=450:27,format=gray', '-f', 'rawvideo', '-'],
    check=True, capture_output=True).stdout
size = 450 * 27
matches = []
for i in range(len(raw) // size):
    frame = Image.frombytes('L', (450, 27), raw[i * size:(i + 1) * size]).point(lambda p: 255 if p > 150 else 0)
    error = ImageStat.Stat(ImageChops.difference(frame, target)).mean[0]
    matches.append((i / 25, error))
best_error = min(error for _, error in matches)
assert best_error < 4, 'Primeiro título não encontrado na captura.'
lead, error = next((at, error) for at, error in matches if error <= best_error + .25)
meta['lead'] = lead
meta['sync_calibration'] = {'method':'Primeiro quadro com o título da etapa inicial',
    'frame_seconds':0.04, 'image_mean_error':round(error, 3)}
for scene in meta['scenes']:
    assert scene['audioStart'] >= scene['start'] + .25, scene['id']
    assert scene['audioStart'] + scene['audioDuration'] <= scene['end'], scene['id']
assert len(meta['posts']) == 1 and not meta['errors'] and not meta['blocked']
(tmp / 'timeline.json').write_text(json.dumps(meta, ensure_ascii=False, indent=2), encoding='utf-8')
print(json.dumps({'kind':kind,'lead':lead,'calibration_error':error,'scenes':len(meta['scenes'])}))
