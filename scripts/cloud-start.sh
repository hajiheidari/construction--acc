#!/usr/bin/env bash
set -euo pipefail
cd /workspace/construction--acc
container_name=construction-accounting-web
if docker --host=unix:///var/run/docker.sock container inspect "$container_name" >/dev/null 2>&1; then
 label=$(docker --host=unix:///var/run/docker.sock inspect -f '{{ index .Config.Labels "construction.accounting" }}' "$container_name")
 [ "$label" = development ] || { echo 'Container name belongs to another service.' >&2; exit 1; }
 docker --host=unix:///var/run/docker.sock start "$container_name" >/dev/null
else
 docker --host=unix:///var/run/docker.sock run -d --name "$container_name" --label construction.accounting=development \
 --user "$(id -u):$(id -g)" --mount type=bind,src=/workspace,dst=/workspace \
 -w /workspace/construction--acc -p 127.0.0.1:8000:8000 \
 construction-php:8.4 php artisan serve --host=0.0.0.0 --port=8000 >/dev/null
fi
for attempt in $(seq 1 20); do
 if curl --fail --silent http://127.0.0.1:8000/login -o /tmp/construction-accounting-login.html; then
  rg -q 'name="email"' /tmp/construction-accounting-login.html
  rg -q 'name="password"' /tmp/construction-accounting-login.html
  echo 'Login form responds successfully.'
  exit 0
 fi
 sleep 1
done
echo 'Application did not become ready; inspect Docker logs.' >&2
exit 1
