#!/usr/bin/env python3
"""Build an immutable overlay package from committed files; never deploy it."""
import argparse, hashlib, json, pathlib, subprocess, tarfile
ROOT = pathlib.Path(__file__).resolve().parents[1]
BASE = 'c0fbd7849348a1ce5d724f6d556d49852c7a0dd1'
BASE_IMAGE = 'localhost/openvsoshcbt:9e95c25a-clientip'
BASE_IMAGE_ID = 'a83998812db43aeb33e100eec31cc49c24ab573b3e00b073b07b8b55e94c6eda'
p = argparse.ArgumentParser(); p.add_argument('--ref', default='HEAD'); p.add_argument('--output', required=True)
args = p.parse_args()
def git(*parts): return subprocess.check_output(['git', *parts], cwd=ROOT)
commit = git('rev-parse', args.ref+'^{commit}').decode().strip()
subprocess.run(['git','merge-base','--is-ancestor',BASE,commit],cwd=ROOT,check=True)
output = pathlib.Path(args.output).resolve(); output.mkdir(parents=True, exist_ok=False)
paths = git('diff','--name-only','--diff-filter=ACMRT',BASE,commit).decode().splitlines()
runtime = [s for s in paths if not s.startswith(('doc/','test/','tools/','docker/'))
           and (s.endswith(('.php','.js','.css','.png','.webmanifest'))
                or (s.startswith('install/') and s.endswith('.sql')))]
if git('diff','--name-only','--diff-filter=D',BASE,commit).strip():
    raise RuntimeError('Deleted files require an explicit overlay migration')
for name in runtime:
    dest=output/'payload'/name;dest.parent.mkdir(parents=True,exist_ok=True)
    dest.write_bytes(git('show',commit+':'+name))
for name in ['docker/tcexam-apache.conf','docker/production/vsosh-request-log.conf','docker/production/vsosh-request-location.inc']:
    dest=output/name;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(git('show',commit+':'+name))
image='localhost/openvsoshcbt:'+commit[:8]+'-incidents'
(output/'Dockerfile').write_text('FROM '+BASE_IMAGE+'\nCOPY payload/ /var/www/html/\n'
    'COPY docker/tcexam-apache.conf /etc/apache2/conf-available/tcexam.conf\n'
    'RUN apache2ctl -t\n'+''.join('RUN php -l /var/www/html/'+s+'\n' for s in runtime if s.endswith('.php')))
(output/'release.json').write_text(json.dumps({'commit':commit,'base_commit':BASE,'base_image':BASE_IMAGE,
    'required_base_image_id':BASE_IMAGE_ID,'candidate_image':image,'runtime_files':runtime},indent=2)+'\n')
# This drop-in is an artifact, never installed by this script.
(output/'candidate-service.conf').write_text('[Service]\nExecStart=\nExecStart=/usr/bin/podman run --name openvsoshcbt '
    '--env-file /etc/openvsoshcbt/app.env --env OPENVSOSHCBT_SOURCE_URL=https://github.com/svn0202/OpenVsoshCBT/commit/'+commit+
    ' -p 127.0.0.1:18080:80 '
    '-v /var/lib/openvsoshcbt/config/shared:/var/www/html/shared/config:Z '
    '-v /var/lib/openvsoshcbt/config/admin:/var/www/html/admin/config:Z '
    '-v /var/lib/openvsoshcbt/config/public:/var/www/html/public/config:Z '
    '-v /var/lib/openvsoshcbt/cache:/var/www/html/cache:Z '
    '-v /var/lib/openvsoshcbt/pdf-font-target:/var/www/html/vendor/tecnickcom/tc-lib-pdf-font/target:Z '+image+'\n')
files=sorted(x for x in output.rglob('*') if x.is_file())
(output/'SHA256SUMS').write_text(''.join(hashlib.sha256(f.read_bytes()).hexdigest()+'  '+str(f.relative_to(output))+'\n' for f in files))
archive=output.with_suffix('.tar.gz')
with tarfile.open(archive,'w:gz') as tar:
    for f in sorted(output.rglob('*')):
        if f.is_file(): tar.add(f,arcname=str(f.relative_to(output)))
print(json.dumps({'package':str(archive),'image':image,'files':len(runtime),'commit':commit}))
