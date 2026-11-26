"""Confere os títulos do MP4 no início das falas e gera uma folha de revisão."""
import io, json, subprocess, sys
from pathlib import Path
from PIL import Image, ImageChops, ImageStat, ImageDraw

ROOT = Path(__file__).resolve().parents[2]
kind = sys.argv[1]
tmp = ROOT / ('tmp/tutorial-' + kind)
out = ROOT / ('output/tutorial-' + kind + '-realista')
meta = json.loads((tmp / 'timeline.json').read_text(encoding='utf-8'))
ff = ROOT / 'tmp/tutoriais-2026/ffmpeg.exe'
scenes = meta['scenes']
sheet = Image.new('RGB', (1600, ((len(scenes)+3)//4)*250), '#edf3f5')
draw = ImageDraw.Draw(sheet)
checks = []
for i, scene in enumerate(scenes):
    at = scene['audioStart'] + .2
    raw = subprocess.run([str(ff), '-v', 'error', '-ss', str(at), '-i', str(out/'tutorial.mp4'),
        '-frames:v', '1', '-f', 'image2pipe', '-vcodec', 'png', '-'], capture_output=True, check=True).stdout
    frame = Image.open(io.BytesIO(raw)).convert('RGB')
    target = Image.open(tmp/f'cena-{i}.png').convert('RGB')
    box = (0, 0, 900, 55)
    mask = lambda image: image.crop(box).convert('L').point(lambda p: 255 if p > 150 else 0)
    error = ImageStat.Stat(ImageChops.difference(mask(frame), mask(target))).mean
    assert max(error) < 6, (scene['id'], error)
    assert scene['audioStart'] + scene['audioDuration'] <= scene['end']
    x, y = i%4*400, i//4*250
    sheet.paste(frame.resize((400, 225)), (x, y))
    draw.text((x+8, y+229), f'{i+1:02} · {scene["title"]}', fill='#173e50')
    checks.append({'etapa':scene['id'],'inicio_fala':round(scene['audioStart'],3),
        'erro_titulo':round(max(error),3),'fala_contida_na_etapa':True})
sheet.save(out/'revisao-visual.jpg', quality=90)
subprocess.run([str(ff), '-v', 'error', '-i', str(out/'tutorial.mp4'), '-f', 'null', '-'], check=True)
report = {'video':kind,'calibracao':meta['sync_calibration'], 'etapas':checks,
    'decodificacao_completa':'OK','salvamentos_simulados':len(meta['posts'])}
(out/'verificacao-sincronia.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
print(f'{kind}: {len(checks)} etapas sincronizadas; MP4 decodificado sem erros.')
