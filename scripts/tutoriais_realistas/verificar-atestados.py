"""Confere vídeos exportados, áudio, capítulos, decodificação e preservação dos tutoriais."""
import hashlib
import json
import re
import subprocess
from pathlib import Path

root = Path(__file__).resolve().parents[2]
ffmpeg = root / 'tmp/tutoriais-2026/ffmpeg.exe'
cleanup = json.loads((root / 'tmp/limpeza-videos-2026-10-24.json').read_text(encoding='utf-8-sig'))
for item in cleanup['preservados']:
    file = Path(item['Path'])
    assert hashlib.sha256(file.read_bytes()).hexdigest().upper() == item['Hash'], file
for file in cleanup['removidos']:
    assert not Path(file).exists(), file

results = []
for kind in ['clinico', 'dermatologico']:
    folder = root / f'output/tutorial-atestado-{kind}-realista'
    timeline = json.loads((root / f'tmp/tutorial-atestado-{kind}/timeline.json').read_text(encoding='utf-8'))
    assert timeline['posts'] == 1 and not timeline['errors'] and not timeline['blocked']
    chapters = json.loads((folder / 'capitulos.json').read_text(encoding='utf-8'))
    assert len(chapters) == 13
    assert (folder / 'legendas.srt').stat().st_size > 1000
    assert (folder / 'legendas.vtt').stat().st_size > 1000
    for name in ['tutorial.mp4', 'versao-leve-celular.mp4']:
        video = folder / name
        probe = subprocess.run([str(ffmpeg), '-i', str(video)], capture_output=True, text=True, encoding='utf-8', errors='replace').stderr
        assert 'Video: h264' in probe and 'Audio: aac' in probe, probe
        match = re.search(r'Duration: (\d+):(\d+):([\d.]+)', probe)
        assert match, probe
        seconds = int(match[1]) * 3600 + int(match[2]) * 60 + float(match[3])
        assert abs(seconds - timeline['scenes'][-1]['end']) < 2
        assert seconds > 180
        decode = subprocess.run([str(ffmpeg), '-v', 'error', '-threads', '2', '-i', str(video), '-f', 'null', '-'], capture_output=True, text=True)
        assert decode.returncode == 0 and not decode.stderr.strip(), decode.stderr
        results.append({'arquivo': str(video), 'duracao_segundos': seconds, 'tamanho_mb': round(video.stat().st_size / 1048576, 2), 'decodificacao': 'OK', 'video': 'H.264', 'audio': 'AAC'})
        print(json.dumps(results[-1], ensure_ascii=False), flush=True)

report = {'videos': results, 'preservados_sha256': len(cleanup['preservados']), 'antigos_removidos': len(cleanup['removidos'])}
(root / 'tmp/tutorial-atestados/verificacao-final.json').write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding='utf-8')
