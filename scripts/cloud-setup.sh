#!/usr/bin/env bash
set -euo pipefail
cd /workspace/construction--acc
# The task already has an isolated checkout. Never create a worktree here.
env -u DOCKER_HOST -u DOCKER_CONTEXT -u DOCKER_TLS -u DOCKER_TLS_VERIFY -u DOCKER_CERT_PATH \
 docker --host=unix:///var/run/docker.sock info >/dev/null
docker --host=unix:///var/run/docker.sock build --secret id=proxy_ca,src=/etc/ssl/certs/ca-certificates.crt \
 -f .cloud/Dockerfile -t construction-php:8.4 .cloud
# Git source is supported by the active policy. Dist installation can be used after api.github.com is applied.
scripts/dev-tool composer install --prefer-source --no-interaction --no-progress
npm ci --ignore-scripts --no-audit --no-fund
npm run build
if [ ! -f .env ]; then cp .env.example .env; fi
if ! rg -q '^APP_KEY=base64:.+' .env; then scripts/dev-tool php artisan key:generate; fi
if [ ! -f database/database.sqlite ]; then touch database/database.sqlite; fi
scripts/dev-tool php artisan migrate --force
scripts/dev-tool php artisan test --compact
