"""Confere entrega HTTP real pelos dois front controllers, sem iniciar banco ou sessão."""
from pathlib import Path
import socket
import subprocess
import time
import urllib.request
import urllib.error
import uuid

root = Path(__file__).resolve().parents[1]
folder = root / 'public/uploads/tutorial-videos'
folder.mkdir(parents=True, exist_ok=True)
file = folder / (uuid.uuid4().hex + '.mp4')
payload = bytes(range(256)) * 16
file.write_bytes(payload)
video_path = '/uploads/tutorial-videos/' + file.name

try:
    for entry in ['index.php', 'public/index.php']:
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0)); port = sock.getsockname()[1]
        process = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', str(root / entry)], cwd=root, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        base = f'http://127.0.0.1:{port}'
        def request(path=video_path, method='GET', headers=None):
            req = urllib.request.Request(base + path, method=method, headers=headers or {})
            try: response = urllib.request.urlopen(req, timeout=10)
            except urllib.error.HTTPError as error: response = error
            with response: return response.status, response.headers, response.read()
        try:
            for _ in range(100):
                try:
                    status, headers, body = request(method='HEAD')
                    break
                except OSError: time.sleep(.1)
            else: raise AssertionError('Servidor de teste indisponível.')
            assert status == 200 and body == b'' and headers['Content-Length'] == str(len(payload))
            assert headers.get('Set-Cookie') is None
            assert headers['Content-Type'] == 'video/mp4' and headers['Accept-Ranges'] == 'bytes'
            status, headers, body = request()
            assert status == 200 and body == payload
            etag = headers['ETag']
            for range_value, start, end in [('bytes=0-31', 0, 31), ('bytes=128-511', 128, 511), ('bytes=4080-', 4080, 4095), ('bytes=-12', 4084, 4095), ('bytes=4090-99999', 4090, 4095)]:
                status, headers, body = request(headers={'Range': range_value})
                assert status == 206 and body == payload[start:end + 1], range_value
                assert headers['Content-Range'] == f'bytes {start}-{end}/{len(payload)}'
                assert int(headers['Content-Length']) == len(body)
            for range_value in ['bytes=4096-', 'bytes=300-200', 'bytes=-0', 'bytes=-', 'bytes=0-1,4-8', 'bytes=invalido']:
                status, headers, body = request(headers={'Range': range_value})
                assert status == 416 and body == b'' and headers['Content-Range'] == f'bytes */{len(payload)}'
            status, _, body = request(headers={'Range': 'bytes=0-31', 'If-Range': '"antigo"'})
            assert status == 200 and body == payload
            status, _, body = request(headers={'Range': 'bytes=0-31', 'If-Range': etag})
            assert status == 206 and body == payload[:32]
            status, _, body = request(method='HEAD', headers={'Range': 'bytes=0-31'})
            assert status == 200 and body == b''
            status, headers, _ = request(method='POST')
            assert status == 405 and headers['Allow'] == 'GET, HEAD'
            status, _, _ = request(path='/uploads/tutorial-videos/' + uuid.uuid4().hex + '.mp4')
            assert status == 404
            print(entry + ': GET, HEAD, intervalos, avanço, erros e ausência de sessão OK', flush=True)
        finally:
            process.terminate(); process.wait(timeout=10)
    # A lista permitida não atende documentos particulares ou caminhos arbitrários.
    php_check = 'require "app/Services/TutorialVideoStreamService.php"; foreach (["/uploads/certificados/teste.pdf", "/uploads/tutorial-videos/../../config/database.php", "/uploads/tutorial-videos/arquivo.php"] as $path) { if (App\\Services\\TutorialVideoStreamService::serve(getcwd(), $path)) throw new RuntimeException("Caminho indevido"); }'
    subprocess.run(['php', '-r', php_check], cwd=root, check=True)
    print('Caminhos privados e fora da pasta não são atendidos.')
finally:
    assert file.resolve().parent == folder.resolve() and file.stem.isalnum() and len(file.stem) == 32
    file.unlink()
