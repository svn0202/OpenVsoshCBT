"""Synthetic transfer checks; no SSH, production paths or mounted shares used."""
import gzip
import hashlib
import json
import pathlib
import subprocess
import tempfile
import unittest
from unittest import mock


class CollectorTest(unittest.TestCase):
    def transfer(self, *, corrupt=False, wrong_mount=False, existing=False):
        with tempfile.TemporaryDirectory() as tmp:
            root = pathlib.Path(tmp)
            mount = root / 'mount'
            mount.mkdir()
            config = {
                'mount': str(mount), 'mount_source': '//storage.example.org/archives',
                'mount_fstype': 'cifs', 'archive_subdirectory': 'httpd',
                'ssh_target': 'deploy@tcexam.example.org',
            }
            (root / 'config.json').write_text(json.dumps(config))
            data = gzip.compress(b'synthetic request log\n' * 100)
            entry = {'name': 'access_log-20260101.gz', 'size': len(data),
                     'sha256': hashlib.sha256(data).hexdigest()}
            target = mount / 'httpd' / entry['name']
            if existing:
                target.parent.mkdir()
                target.write_bytes(b'conflicting archive')
            removed = []
            real_run = subprocess.run

            def run(args, **kwargs):
                if args[0] != 'ssh':
                    return real_run(args, **kwargs)
                req = json.loads(kwargs['input'])
                if req['op'] == 'list':
                    return subprocess.CompletedProcess(args, 0, json.dumps([entry]).encode())
                if req['op'] == 'read':
                    kwargs['stdout'].write(b'broken' if corrupt else data)
                    return subprocess.CompletedProcess(args, 0)
                self.assertEqual(target.read_bytes(), data)
                self.assertTrue((target.parent / 'manifest.jsonl').exists())
                removed.append(req['name'])
                return subprocess.CompletedProcess(args, 0, b'removed\n')

            info = {'filesystems': [{
                'target': str(mount), 'source': '/dev/sda1' if wrong_mount else config['mount_source'],
                'fstype': 'ext4' if wrong_mount else 'cifs',
            }]}
            code = pathlib.Path(__file__).with_name('collector.py').read_text()
            code = code.replace("pathlib.Path('/etc/tcexam-log-archive')", f'pathlib.Path({str(root)!r})')
            code = code.replace("'/run/lock/tcexam-log-archive.lock'", repr(str(root / 'lock')))
            with mock.patch('subprocess.check_output', return_value=json.dumps(info).encode()), \
                    mock.patch('subprocess.run', side_effect=run):
                if corrupt or wrong_mount or existing:
                    with self.assertRaises(RuntimeError):
                        exec(compile(code, 'collector.py', 'exec'), {})
                    self.assertEqual(removed, [])
                    if existing:
                        self.assertEqual(target.read_bytes(), b'conflicting archive')
                else:
                    exec(compile(code, 'collector.py', 'exec'), {})
                    self.assertEqual(removed, [entry['name']])

    def test_verified_transfer(self):
        self.transfer()

    def test_corrupt_copy_preserves_source(self):
        self.transfer(corrupt=True)

    def test_wrong_mount_preserves_source(self):
        self.transfer(wrong_mount=True)

    def test_conflicting_archive_is_not_overwritten(self):
        self.transfer(existing=True)


if __name__ == '__main__':
    unittest.main()
