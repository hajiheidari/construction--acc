#!/usr/bin/env python3
"""Build a private-data-free DirectAdmin artifact from tested local dependencies."""
from pathlib import Path
import hashlib, shutil, subprocess, tarfile
root=Path(__file__).resolve().parents[1]
stage=root/'.release-staging'/'application'
if stage.exists(): shutil.rmtree(stage)
stage.mkdir(parents=True)
for name in ['app','config','routes','resources','public','vendor','lang']:
    shutil.copytree(root/name,stage/name,ignore=shutil.ignore_patterns('.git','hot','storage'))
(stage/'bootstrap'/'cache').mkdir(parents=True)
for name in ['app.php','providers.php']: shutil.copy2(root/'bootstrap'/name,stage/'bootstrap'/name)
(stage/'database').mkdir()
shutil.copytree(root/'database'/'migrations',stage/'database'/'migrations')
for directory in ['app/private','app/public','framework/cache/data','framework/cache/locks','framework/sessions','framework/views','logs']:
    (stage/'storage'/directory).mkdir(parents=True,exist_ok=True)
for name in ['artisan','composer.json','composer.lock','DEPLOYMENT.md','DEVELOPMENT_STATUS.md']:
    shutil.copy2(root/name,stage/name)
shutil.copytree(root/'docs',stage/'docs')
shutil.copy2(root/'.env.production.example',stage/'.env.example')
subprocess.run([str(root/'scripts/dev-tool'),'composer','--working-dir='+str(stage),'install','--no-dev','--no-scripts','--classmap-authoritative','--no-interaction'],check=True)
subprocess.run([str(root/'scripts/dev-tool'),'php',str(stage/'artisan'),'package:discover'],check=True)
# Discovery caches reference the staging path; let production regenerate them locally.
for p in (stage/'bootstrap'/'cache').glob('*.php'): p.unlink()
for forbidden in ['.env','database/database.sqlite','auth.json','node_modules','tests']:
    assert not (stage/forbidden).exists(), forbidden
assert (stage/'public/build/manifest.json').is_file()
dist=root/'dist';dist.mkdir(exist_ok=True)
archive=dist/'construction-accounting-review.tar.gz'
with tarfile.open(archive,'w:gz') as tar: tar.add(stage,arcname='application')
digest=hashlib.sha256(archive.read_bytes()).hexdigest()
(dist/(archive.name+'.sha256')).write_text(digest+'  '+archive.name+'\n')
print('Review artifact:',archive)
print('SHA256:',digest)
