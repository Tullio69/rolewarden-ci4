#!/bin/sh
# A1-A4 + v1.0 regression, black-box (Collaudatore ad Hoc). Usage: verify-v1-a1a4.sh <scratch-dir>
# Snapshots rolewarden_test, copies ../rolewarden-app-test into <scratch-dir>/app (junctions excluded),
# re-creates the Composer junction by hand, then per phase: fresh README install, ONE php -S on :8070.
#   development: verify-v1.php (full suite), then fresh install + verify-v1-anno.php, fresh install + verify-v1-a1a4.php
#   production : fresh install + verify-v1-anno.php, fresh install + verify-v1-a1a4.php, then verify-v1.prod.php
# Always: server stopped, rolewarden_test restored from the snapshot and re-dumped for the checksum,
# junction removed before the copy. Credentials only from RW_DB_* (never written to files).
set -u
SCR="$1"
HERE="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$HERE/../.." && pwd)"
HOST="$ROOT/../rolewarden-app-test"
APP="$SCR/app"
JCT="$APP/vendor/rolewarden/codeigniter4-rolewarden"
export RW_V1_APP="$(cygpath -m "$APP")"
DBENV="database.default.password=$RW_DB_PASSWORD database.default.username=$RW_DB_USERNAME database.default.hostname=$RW_DB_HOSTNAME database.default.port=$RW_DB_PORT database.default.database=rolewarden_test"
SRV=""

sql() { docker exec -e MYSQL_PWD="$RW_DB_PASSWORD" rolewarden-db mariadb -u"$RW_DB_USERNAME" -e "$1"; }
dump() { docker exec -e MYSQL_PWD="$RW_DB_PASSWORD" rolewarden-db mariadb-dump -u"$RW_DB_USERNAME" --skip-dump-date --skip-comments rolewarden_test; }

stop_server() {
  if [ -n "$SRV" ]; then
    WPID=$(cat /proc/$SRV/winpid 2>/dev/null)
    [ -n "$WPID" ] && taskkill //F //PID "$WPID" >/dev/null 2>&1
    kill "$SRV" 2>/dev/null
    SRV=""
  fi
}

cleanup() {
  stop_server
  echo "== restore rolewarden_test from snapshot"
  sql "DROP DATABASE IF EXISTS rolewarden_test; CREATE DATABASE rolewarden_test;"
  docker exec -i -e MYSQL_PWD="$RW_DB_PASSWORD" rolewarden-db mariadb -u"$RW_DB_USERNAME" rolewarden_test <"$SCR/snap.sql"
  AFTER=$(dump | md5sum | cut -d' ' -f1)
  echo "snapshot md5=$BEFORE restored md5=$AFTER $( [ "$BEFORE" = "$AFTER" ] && echo MATCH || echo MISMATCH )"
  rm -f "$SCR/snap.sql"
  [ -e "$JCT" ] && cmd //c rmdir "$(cygpath -w "$JCT")"
  [ -e "$JCT" ] && { echo "junction still present, copy NOT removed"; return; }
  rm -rf "$APP"
  rm -f "$(cygpath -u "$(php -r 'echo sys_get_temp_dir();')")"/rwv1-*.jar "$HERE"/verify-v1.server-*.log
  echo "temp copy removed: $( [ -e "$APP" ] && echo no || echo yes ); php.exe left: $(tasklist //FI 'IMAGENAME eq php.exe' | grep -c php.exe)"
}

start_server() {
  sed -i "s/^CI_ENVIRONMENT = .*/CI_ENVIRONMENT = $1/" "$APP/.env"
  (cd "$APP" && exec env $DBENV CI_ENVIRONMENT="$1" php -S localhost:8070 -t public vendor/codeigniter4/framework/system/rewrite.php >"$HERE/verify-v1.server-$1.log" 2>&1) &
  SRV=$!
  for i in 1 2 3 4 5 6 7 8 9 10; do
    curl -s -o /dev/null http://localhost:8070/index.php/login && return 0
    sleep 1
  done
  echo "server did not start"; exit 3
}

install() {
  sql "DROP DATABASE rolewarden_test; CREATE DATABASE rolewarden_test;"
  find "$APP"/writable/cache -type f ! -name index.html -delete; rm -f "$APP"/writable/session/ci_session* 2>/dev/null
  (cd "$APP" && for n in 'CodeIgniter\Settings' 'CodeIgniter\Shield' 'RoleWarden'; do env $DBENV php spark migrate -n "$n" | tail -1; done; env $DBENV php spark db:seed 'RoleWarden\Database\Seeds\RoleWardenSeeder' | tail -1)
}

echo "== snapshot rolewarden_test"
dump >"$SCR/snap.sql" || exit 2
BEFORE=$(md5sum <"$SCR/snap.sql" | cut -d' ' -f1)
echo "tables: $(grep -c '^CREATE TABLE' "$SCR/snap.sql") md5=$BEFORE"
trap cleanup EXIT
trap 'exit 130' INT TERM

echo "== isolated copy of the sibling app"
robocopy "$(cygpath -w "$HOST")" "$(cygpath -w "$APP")" //E //XJ //XD .git //NFL //NDL //NJH //NJS //NP >/dev/null
[ -e "$JCT" ] && { echo "unexpected: module path already present in the copy"; exit 4; }
mkdir -p "$APP/vendor/rolewarden"
cmd //c mklink //J "$(cygpath -w "$JCT")" "$(cygpath -w "$ROOT")" >/dev/null
find "$APP"/writable/cache -type f ! -name index.html -delete; rm -f "$APP"/writable/session/ci_session* 2>/dev/null
sed -i -e "s/^database.default.database = .*/database.default.database = rolewarden_test/" -e '/password/d' -e "s#^app.baseURL = .*#app.baseURL = 'http://localhost:8070/'#" "$APP/.env"
grep -q '^database.default.database = rolewarden_test' "$APP/.env" || exit 5

RC=0
if [ -n "${RW_PROBE:-}" ]; then # ad-hoc diagnostic script (scratchpad), development only
  install; start_server development; php "$RW_PROBE"; stop_server; exit 0
fi
echo "== DEVELOPMENT: full v1.0 suite"
install; start_server development
php "$HERE/verify-v1.php" >"$HERE/verify-v1.output.txt" 2>&1 || RC=1
tail -3 "$HERE/verify-v1.output.txt"; stop_server
echo "== DEVELOPMENT: marginal notes (UserDetail)"
install; start_server development
RW_ANNO_ENV=development php "$HERE/verify-v1-anno.php" >"$HERE/verify-v1-anno.development.output.txt" 2>&1 || RC=1
tail -1 "$HERE/verify-v1-anno.development.output.txt"; stop_server
echo "== DEVELOPMENT: A1-A4"
install; start_server development
php "$HERE/verify-v1-a1a4.php" >"$HERE/verify-v1-a1a4.development.output.txt" 2>&1 || RC=1
tail -2 "$HERE/verify-v1-a1a4.development.output.txt"; stop_server
echo "== PRODUCTION: marginal notes (UserDetail)"
install; start_server production
RW_ANNO_ENV=production php "$HERE/verify-v1-anno.php" >"$HERE/verify-v1-anno.production.output.txt" 2>&1 || RC=1
tail -1 "$HERE/verify-v1-anno.production.output.txt"; stop_server
echo "== PRODUCTION: A1-A4 + v1.0 production spot check"
install; start_server production
php "$HERE/verify-v1-a1a4.php" >"$HERE/verify-v1-a1a4.production.output.txt" 2>&1 || RC=1
tail -2 "$HERE/verify-v1-a1a4.production.output.txt"
php "$HERE/verify-v1.prod.php" >"$HERE/verify-v1.prod.output.txt" 2>&1 || RC=1
tail -2 "$HERE/verify-v1.prod.output.txt"; stop_server
exit $RC
