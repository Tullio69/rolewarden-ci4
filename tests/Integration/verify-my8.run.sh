#!/usr/bin/env bash
# MY8 runner (Collaudatore ad Hoc): one MySQL version at a time.
#   bash verify-my8.run.sh 84|80 [phases]
# Starts the temporary container rolewarden-my8-<v> (image mysql:8.4 or mysql:8.0, image defaults, 127.0.0.1:3318) with
# passwords generated here and never written to a file (the harness reads them back from the container's environment),
# runs verify-my8.php, saves the container log, then removes the container and its anonymous volume.
# Needs MY8_WORK: the scratch folder holding app/ (copy of ../rolewarden-app-test wired to the worktree under test).
set -u
cd "$(dirname "$0")" || exit 1
V=${1:?84 or 80}
PH=${2:-A,RB,B,P,I,X,Z}
L=${MY8_LABEL:-$V}   # output label, e.g. MY8_LABEL=84-r2 for a re-run
: "${MY8_WORK:?}"
CT=rolewarden-my8-$V
IMG=mysql:${V:0:1}.${V:1:1}
if docker ps -a --format '{{.Names}}' | grep -qx "$CT"; then echo "ABORT: $CT exists"; exit 1; fi
docker run -d --name "$CT" -p 127.0.0.1:3318:3306 -e MYSQL_ROOT_PASSWORD="$(openssl rand -hex 16)" \
  -e MYSQL_DATABASE=rolewarden_test -e MYSQL_USER=rwapp -e MYSQL_PASSWORD="$(openssl rand -hex 16)" "$IMG" >/dev/null || exit 1
envv() { docker inspect -f '{{range .Config.Env}}{{println .}}{{end}}' "$CT" | sed -n "s/^$1=//p"; }
export MY8_CT=$CT RW_DB_USERNAME=root RW_DB_PASSWORD="$(envv MYSQL_ROOT_PASSWORD)" MY8_APP_USER=rwapp MY8_APP_PASS="$(envv MYSQL_PASSWORD)"
export MY8_APP="$(cygpath -m "$MY8_WORK/app")" TEMP="$(cygpath -w "$MY8_WORK")" TMP="$(cygpath -w "$MY8_WORK")"
for i in $(seq 1 90); do
  docker logs "$CT" 2>&1 | grep -q "ready for connections.*port: 3306" && break; sleep 2
done
sleep 3
php -d memory_limit=1G verify-my8.php phases="$PH" label="$L" > "verify-my8.$L.output.txt" 2>&1
RC=$?
docker logs "$CT" > "verify-my8.$L.container.log" 2>&1
docker rm -f -v "$CT" >/dev/null
echo "exit=$RC container_removed=$(docker ps -a --format '{{.Names}}' | grep -cx "$CT" | sed 's/^0$/yes/') $(grep -E '^TOTAL' "verify-my8.$L.output.txt")"
