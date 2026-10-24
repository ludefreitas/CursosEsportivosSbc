"""Integração HTTP com uploads reais em pasta isolada; nenhum banco é utilizado."""
import json
import os
from pathlib import Path
import socket
import subprocess
import time
import urllib.request
import urllib.error
import uuid
import shutil
import struct

root = Path(__file__).resolve().parents[1]
test_root = root / 'tmp' / ('tutorial-upload-test-' + uuid.uuid4().hex)
test_root.mkdir(parents=True)
with socket.socket() as sock:
    sock.bind(('127.0.0.1', 0))
    port = sock.getsockname()[1]
env = dict(os.environ, TUTORIAL_TEST_ROOT=str(test_root))
server = subprocess.Popen(['php', '-d', 'upload_tmp_dir=' + str(test_root), '-S', f'127.0.0.1:{port}', str(root / 'tests/fixtures/tutorial_page_upload_router.php')], cwd=root, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
base = f'http://127.0.0.1:{port}'

def send(fields, files=()):
    boundary = uuid.uuid4().hex
    chunks = []
    for key, value in fields.items():
        chunks.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
    for key, filename, content, mime in files:
        chunks.extend([f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode(), content, b'\r\n'])
    chunks.append(f'--{boundary}--\r\n'.encode())
    request = urllib.request.Request(base, data=b''.join(chunks), headers={'Content-Type': 'multipart/form-data; boundary=' + boundary})
    try:
        with urllib.request.urlopen(request) as response:
            content = response.read().decode()
            try: return response.status, json.loads(content)
            except ValueError: raise AssertionError(content[:2000])
    except urllib.error.HTTPError as error:
        return error.code, json.load(error)

def fields(rows):
    result = {'titulo': 'Ajuda ao usuário', 'texto_introdutorio': 'Vídeos de demonstração.'}
    for index, row in enumerate(rows):
        for key, value in row.items():
            result[f'video_{key}[{index}]'] = value
    return result

def stored_files():
    return set((test_root / 'public/uploads/tutorial-videos').glob('*'))

try:
    for _ in range(100):
        try:
            with urllib.request.urlopen(base, timeout=1): break
        except OSError: time.sleep(.1)
    else: raise AssertionError('Servidor de teste indisponível.')
    # Compatibilidade com registros anteriores que não possuem tipo.
    (test_root / 'state.json').write_text(json.dumps({'titulo': 'Ajuda', 'videos_json': json.dumps([{'titulo': 'Antigo', 'url': 'https://youtu.be/abcdefghijk'}])}))
    with urllib.request.urlopen(base) as response:
        old = json.load(response)['page']['videos'][0]
    assert old['tipo'] == 'youtube' and old['embed_url'].endswith('/abcdefghijk')
    # Contêiner mínimo com assinatura MP4, suficiente para validar o recebimento e o MIME.
    sample = struct.pack('>I4s4sI4s4s', 24, b'ftyp', b'isom', 512, b'isom', b'iso2') + struct.pack('>I4s', 8, b'mdat')
    youtube = {'titulo': 'YouTube', 'tipo': 'youtube', 'url': 'https://youtu.be/abcdefghijk'}
    local = {'titulo': 'Vídeo clínico', 'tipo': 'local'}
    status, response = send(fields([youtube, local]), [('video_arquivo[1]', 'clinico.mp4', sample, 'video/mp4')])
    assert status == 200, response
    video = response['page']['videos'][1]
    first = video['arquivo']
    assert len(stored_files()) == 1 and (test_root / 'public' / first.lstrip('/')).read_bytes() == sample
    # Reordenar mantém o vínculo entre título, origem e arquivo.
    local['arquivo_atual'] = first
    status, response = send(fields([local, youtube]))
    assert status == 200 and response['page']['videos'][0]['arquivo'] == first
    # Um nome arbitrário ou um caminho fora da pasta não pode ser reutilizado.
    for bad_path in ['/uploads/tutorial-videos/' + 'a' * 32 + '.mp4', '/../../config/database.php']:
        status, _ = send(fields([dict(local, arquivo_atual=bad_path)]))
        assert status == 422
    status, response = send(fields([local]), [('video_arquivo[0]', 'falso.mp4', b'<html>arquivo invalido</html>', 'video/mp4')])
    assert status == 422 and len(stored_files()) == 1, response
    # Falha de validação posterior ao upload remove o novo arquivo e preserva o anterior.
    bad_youtube = dict(youtube, url='https://example.com/video')
    status, _ = send(fields([local, bad_youtube]), [('video_arquivo[0]', 'novo.mp4', sample, 'video/mp4')])
    assert status == 422 and len(stored_files()) == 1
    failure = fields([local]); failure['simulate_failure'] = '1'
    status, _ = send(failure, [('video_arquivo[0]', 'novo.mp4', sample, 'video/mp4')])
    assert status == 422 and len(stored_files()) == 1
    # Substituição remove somente o vídeo antigo depois que o novo foi salvo.
    status, response = send(fields([local, youtube]), [('video_arquivo[0]', 'novo.mp4', sample, 'video/mp4')])
    assert status == 200 and len(stored_files()) == 1
    replacement = response['page']['videos'][0]['arquivo']
    assert replacement != first and not (test_root / 'public' / first.lstrip('/')).exists()
    status, response = send(fields([youtube]))
    assert status == 200 and not stored_files()
    status, _ = send(fields([youtube] * 31))
    assert status == 422
    print('OK: YouTube anterior, upload real, ordenação, preservação, rejeição de caminhos e arquivos inválidos, limpeza em falhas, substituição e remoção.')
finally:
    server.terminate()
    server.wait(timeout=10)
    # A pasta criada pelo próprio teste é resolvida e confinada a tmp antes da limpeza.
    assert test_root.resolve().parent == (root / 'tmp').resolve()
    assert test_root.name.startswith('tutorial-upload-test-')
    shutil.rmtree(test_root)
