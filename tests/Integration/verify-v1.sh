#!/bin/sh
# v1.0 black-box run (Collaudatore ad Hoc). Usage: verify-v1.sh <isolated-app-copy>
# Reinstalls rolewarden_test per README, serves the copy on :8070 with ONE php -S,
# runs verify-v1.php, then a production-mode spot check, and always stops the server.
# DB credentials come only from RW_DB_* and are passed as CI4 env overrides.
set -u
APP="$1"
HERE="$(cd "$(dirname "$0")" && pwd)"
export RW_V1_APP="$(cygpath -m "$APP")"
DBENV="database.default.password=$RW_DB_PASSWORD database.default.username=$RW_DB_USERNAME database.default.hostname=$RW_DB_HOSTNAME database.default.port=$RW_DB_PORT database.default.database=rolewarden_test"
SRV=""

stop_server() {
  if [ -n "$SRV" ]; then
    WPID=$(cat /proc/$SRV/winpid 2>/dev/null)
    [ -n "$WPID" ] && taskkill //F //PID "$WPID" >/dev/null 2>&1
    kill "$SRV" 2>/dev/null
    SRV=""
  fi
}
trap stop_server EXIT INT TERM

start_server() { # $1 = CI_ENVIRONMENT (set in the copy's .env: CI4 lets .env win over the process env when $_ENV is not populated)
  sed -i "s/^CI_ENVIRONMENT = .*/CI_ENVIRONMENT = $1/" "$APP/.env"
  (cd "$APP" && exec env $DBENV CI_ENVIRONMENT="$1" php -S localhost:8070 -t public vendor/codeigniter4/framework/system/rewrite.php >"$HERE/verify-v1.server-$1.log" 2>&1) &
  SRV=$!
  for i in 1 2 3 4 5 6 7 8 9 10; do
    curl -s -o /dev/null http://localhost:8070/index.php/login && return 0
    sleep 1
  done
  echo "server did not start"; exit 3
}

sql() { docker exec -e MYSQL_PWD="$RW_DB_PASSWORD" rolewarden-db mariadb -u"$RW_DB_USERNAME" -e "$1"; }

echo "== install per README on a fresh rolewarden_test"
sql "DROP DATABASE rolewarden_test; CREATE DATABASE rolewarden_test;"
find "$APP"/writable/cache -type f ! -name index.html -delete; rm -f "$APP"/writable/session/ci_session* 2>/dev/null
(cd "$APP" && for n in 'CodeIgniter\Settings' 'CodeIgniter\Shield' 'RoleWarden'; do env $DBENV php spark migrate -n "$n" | tail -1; done; env $DBENV php spark db:seed 'RoleWarden\Database\Seeds\RoleWardenSeeder' | tail -1)

start_server development
php "$HERE/verify-v1.php"
RC=$?
stop_server

echo
echo "== production-mode spot check (same data)"
start_server production
php "$HERE/verify-v1.prod.php"
RC2=$?
stop_server
echo "exit codes: development=$RC production=$RC2"
[ $RC -eq 0 ] && [ $RC2 -eq 0 ]
