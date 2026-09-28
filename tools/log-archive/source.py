#!/usr/bin/python3
import fcntl, glob, hashlib, json, os, pathlib, re, subprocess, sys

ROOT = pathlib.Path('/var/log/httpd')
PATTERN = r'[A-Za-z0-9_.-]+log-[0-9]{8}(?:\.gz)?'

def digest(p):
    h = hashlib.sha256()
    with p.open('rb') as f:
        for b in iter(lambda: f.read(1024 * 1024), b''):
            h.update(b)
    return h.hexdigest()

def opened(p):
    s = p.stat()
    for fd in glob.glob('/proc/[0-9]*/fd/*'):
        try:
            t = os.stat(fd)
            if (s.st_dev, s.st_ino) == (t.st_dev, t.st_ino):
                return True
        except OSError:
            pass
    return False

req = json.loads(sys.stdin.readline())
with open('/var/lib/logrotate/logrotate.status', 'r') as lock:
    fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
    if req['op'] == 'list':
        result = []
        for p in sorted(ROOT.iterdir()):
            if not re.fullmatch(PATTERN, p.name) or p.is_symlink() or not p.is_file() or opened(p):
                continue
            if not p.name.endswith('.gz'):
                subprocess.run(['nice', '-n', '15', 'gzip', '-1', str(p)], check=True)
                p = pathlib.Path(str(p) + '.gz')
            result.append({'name': p.name, 'size': p.stat().st_size, 'sha256': digest(p)})
        print(json.dumps(result))
    else:
        name = req['name']
        if not re.fullmatch(PATTERN, name) or not name.endswith('.gz'):
            raise ValueError('Invalid archive name')
        p = ROOT / name
        if p.is_symlink() or not p.is_file() or opened(p):
            raise ValueError('Not a closed regular archive')
        before = p.stat()
        if digest(p) != req['sha256']:
            raise ValueError('Source checksum mismatch')
        if req['op'] == 'read':
            with p.open('rb') as f:
                for b in iter(lambda: f.read(1024 * 1024), b''):
                    sys.stdout.buffer.write(b)
        elif req['op'] == 'remove':
            after = p.stat()
            if (before.st_ino, before.st_size, before.st_mtime_ns) != (after.st_ino, after.st_size, after.st_mtime_ns):
                raise ValueError('Source changed')
            p.unlink()
            print('removed ' + name)
        else:
            raise ValueError('Unknown operation')
