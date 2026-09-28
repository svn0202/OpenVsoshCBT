#!/usr/bin/python3
import datetime, fcntl, hashlib, json, os, pathlib, subprocess

CONFIG_DIR = pathlib.Path('/etc/tcexam-log-archive')
CONFIG = json.loads((CONFIG_DIR / 'config.json').read_text())
MOUNT = pathlib.Path(CONFIG['mount'])
DEST = MOUNT / CONFIG['archive_subdirectory']
if not MOUNT.is_absolute() or os.path.commonpath([str(DEST.resolve()), str(MOUNT.resolve())]) != str(MOUNT.resolve()):
    raise ValueError('Archive destination must stay inside the mount')
SSH = ['ssh', '-T', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=15',
       '-o', 'StrictHostKeyChecking=yes',
       '-o', 'UserKnownHostsFile=' + str(CONFIG_DIR / 'known_hosts'),
       '-i', str(CONFIG_DIR / 'id_ed25519'), CONFIG['ssh_target']]

def mount_check():
    m = json.loads(subprocess.check_output(['findmnt', '-J', '-M', str(MOUNT)]))['filesystems'][0]
    if m['source'] != CONFIG['mount_source'] or m['fstype'] != CONFIG['mount_fstype'] or m['target'] != str(MOUNT):
        raise RuntimeError('Archive storage is not mounted correctly')

def remote(req, output=subprocess.PIPE):
    r = subprocess.run(SSH, input=(json.dumps(req)+'\n').encode(), stdout=output, check=True, timeout=1800)
    return r.stdout

def digest(p):
    h = hashlib.sha256()
    with p.open('rb') as f:
        for b in iter(lambda: f.read(1024 * 1024), b''):
            h.update(b)
    return h.hexdigest()

with open('/run/lock/tcexam-log-archive.lock', 'w') as lock:
    fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
    mount_check()
    DEST.mkdir(parents=True, exist_ok=True)
    entries = json.loads(remote({'op': 'list'}))
    for entry in entries:
        name = entry['name']
        if pathlib.Path(name).name != name:
            raise ValueError('Invalid filename')
        target = DEST / name
        mount_check()
        if not target.exists():
            partial = DEST / (name + '.partial')
            with partial.open('wb') as f:
                remote(dict(entry, op='read'), f)
                f.flush()
                os.fsync(f.fileno())
            if partial.stat().st_size != entry['size'] or digest(partial) != entry['sha256']:
                raise RuntimeError('Destination readback mismatch')
            subprocess.run(['gzip', '-t', str(partial)], check=True)
            partial.rename(target)
        if target.stat().st_size != entry['size'] or digest(target) != entry['sha256']:
            raise RuntimeError('Existing destination mismatch')
        mount_check()
        record = dict(entry, verified_at=datetime.datetime.now(datetime.timezone.utc).isoformat())
        with (DEST / 'manifest.jsonl').open('a') as f:
            f.write(json.dumps(record) + '\n')
            f.flush()
            os.fsync(f.fileno())
        print(remote(dict(entry, op='remove')).decode().strip(), flush=True)
    print('Verified and archived: ' + str(len(entries)), flush=True)
