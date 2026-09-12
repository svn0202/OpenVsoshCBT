#!/usr/bin/env python3
"""Read-only legacy HTTP page contract; no database and no Docker."""
import json
import socket
import subprocess
import time
from pathlib import Path
from urllib.request import Request, urlopen
from urllib.parse import urlencode

ROOT = Path(__file__).resolve().parents[1]
with socket.socket() as sock:
    sock.bind(('127.0.0.1', 0))
    port = sock.getsockname()[1]
process = subprocess.Popen(['php', '-S', '127.0.0.1:%d' % port, '-t', str(ROOT)],
                           stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
url = 'http://127.0.0.1:%d/public/code/tmf_testend.php' % port
try:
    for _ in range(50):
        try:
            with urlopen(url, timeout=1) as response:
                assert response.status == 200
            break
        except OSError:
            time.sleep(.1)
    else:
        raise AssertionError('PHP test server did not start')
    with urlopen(url + '?testid=12') as response:
        html = response.read().decode()
        assert response.headers['Cache-Control'] == 'no-store'
        assert response.headers.get('Location') is None
        assert 'form-action' in response.headers['Content-Security-Policy']
        assert 'tce_test_execute.php?testid=12' in html
    data = urlencode({'testid': '12', 'answertext': '</textarea><script>alert(1)</script>',
                      'csrf_token': 'SECRET_TOKEN', 'xuser_password': 'SECRET_PASSWORD', 'answpos[2]': '1'})
    with urlopen(Request(url, data=data.encode())) as response:
        html = response.read().decode()
        assert response.status == 200 and response.headers.get('Set-Cookie') is None
        assert '<script>alert(1)</script>' not in html and '&lt;script&gt;' in html
        assert 'SECRET_TOKEN' not in html and 'SECRET_PASSWORD' not in html
        assert 'answpos' in html and 'textarea readonly' in html
    with urlopen(url + '?testid=' + '%2F%2Fevil.example') as response:
        html = response.read().decode()
        assert 'evil.example' not in html and 'href="index.php"' in html
    print('Legacy HTTP: GET, POST draft, escaping, secret exclusion and safe destination passed')
finally:
    process.terminate()
    process.wait(timeout=5)
