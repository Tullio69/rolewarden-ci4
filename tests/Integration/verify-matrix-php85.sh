#!/bin/sh
# Version matrix, PHP 8.5 + latest CodeIgniter 4.x + Shield ^1.4 (Collaudatore ad Hoc B).
# Black-box, same protocol as verify-v1-a1a4.sh, but against a PERSISTENT isolated app copy
# (prepared in phase 1: composer update run under PHP 8.5, Composer junction recreated by hand)
# served by the portable PHP 8.5 on :8085. The copy and PHP are never created or removed here.
#
# Usage (Git Bash):
#   verify-matrix-php85.sh static   no database: php -l on src/, class linking, spark routes/namespaces
#   verify-matrix-php85.sh full     static + every DB phase below (needs RW_DB_* and rolewarden_test free)
#
# DB phases, each on a fresh README install of rolewarden_test done with PHP 8.5's spark:
#   development: verify-v1.php, verify-v1-anno.php, verify-v1-a1a4.php
#   production : verify-v1-anno.php, verify-v1-a1a4.php + verify-v1.prod.php
#   development: M6 "existing Shield app" scenario (verify-matrix-php85-m6.php), incl. rollback
# The v1 scripts are NOT modified: patched copies (port 8070 -> 8085, docs path absolute) are
# generated in a temp dir at run time. After every phase the CI4 log and the php -S log are
# scanned: any DEPRECATED/WARNING/NOTICE (or PHP Deprecated/Warning/Notice/Fatal) is a FAIL.
# Always: server stopped, rolewarden_test restored from the snapshot (md5 compared),
# AuthGroups.php and .env of the copy restored. Credentials only from RW_DB_*.
#
# Overridable: RW_APP85 (app copy), RW_PHP85 (php.exe 8.5), RW_CLIENT_PHP (PHP running the curl
# clients; default the system `php`, because verify-v1.lib.php calls curl_close(), deprecated in 8.5:
# the clients are not under test, the module is).
set -u
MODE="${1:-full}"
SCR_DEFAULT="C:/Users/fabio/AppData/Local/Temp/claude/E--progetti-lavoro-ASP-rolewarden-ci4/e4eb42b6-9af8-45b2-9069-e8785e84a866/scratchpad"
APP="${RW_APP85:-$SCR_DEFAULT/app85}"
PHP85="${RW_PHP85:-$SCR_DEFAULT/php85/php.exe}"
CPHP="${RW_CLIENT_PHP:-php}"
PORT=8085
HERE="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$HERE/../.." && pwd)"
OUT="$HERE/verify-matrix-php85"
export RW_V1_APP="$(cygpath -m "$APP")"
SRV=""
FAILS=0
WORK="$(mktemp -d)"

[ -f "$APP/spark" ] || { echo "app copy not found: $APP"; exit 2; }
[ -x "$PHP85" ] || { echo "PHP 8.5 not found: $PHP85"; exit 2; }
"$PHP85" -v | head -1
"$PHP85" -r 'exit(error_reporting() === E_ALL ? 0 : 1);' || { echo "PHP 8.5 ini is not E_ALL"; exit 2; }
[ -e "$APP/vendor/rolewarden/codeigniter4-rolewarden/src" ] || { echo "Composer junction missing in the copy"; exit 2; }

fail() { echo "FAIL: $*"; FAILS=$((FAILS + 1)); }

# ---------------------------------------------------------------- static (no DB)
static_checks() {
  echo "== STATIC: php -l on src/ with PHP 8.5"
  n=0; bad=0
  for f in $(find "$ROOT/src" -name '*.php'); do
    n=$((n + 1))
    o=$("$PHP85" -l "$f" 2>&1)
    { [ "$(echo "$o" | wc -l)" -eq 1 ] && echo "$o" | grep -q '^No syntax errors detected'; } || { bad=$((bad + 1)); echo "$o"; }
  done
  echo "lint: $n files, $bad with output"; [ $bad -eq 0 ] || fail "php -l"
  echo "== STATIC: link every class-like declaration under src/ (E_ALL, no app boot)"
  o=$("$PHP85" "$HERE/verify-matrix-php85.load.php" "$RW_V1_APP" "$(cygpath -m "$ROOT/src")" 2>&1); echo "$o"
  echo "$o" | grep -q '^issues=0$' || fail "class linking"
  echo "== STATIC: spark under PHP 8.5"
  for c in "" routes namespaces; do
    o=$(cd "$APP" && MSYS_NO_PATHCONV=1 "$PHP85" spark $c --no-header 2>&1); rc=$?
    echo "spark $c: rc=$rc lines=$(echo "$o" | wc -l)"
    [ $rc -eq 0 ] || fail "spark $c rc=$rc"
    echo "$o" | grep -i -E 'deprecat|warning|notice|fatal|exception' && fail "spark $c printed the lines above"
  done
  for r in /rolewarden/users /rolewarden/roles /rolewarden/permissions; do
    o=$(cd "$APP" && MSYS_NO_PATHCONV=1 "$PHP85" spark filter:check GET $r --no-header 2>&1)
    echo "$o" | grep -q 'RoleWarden\\Filters\\PermissionFilter' || fail "filter:check $r: $o"
  done
}

# ---------------------------------------------------------------- DB helpers
DBENV="database.default.password=${RW_DB_PASSWORD:-} database.default.username=${RW_DB_USERNAME:-} database.default.hostname=${RW_DB_HOSTNAME:-} database.default.port=${RW_DB_PORT:-} database.default.database=rolewarden_test"
sql() { docker exec -e MYSQL_PWD="$RW_DB_PASSWORD" rolewarden-db mariadb -u"$RW_DB_USERNAME" -N -B -e "$1"; }
sqlt() { docker exec -e MYSQL_PWD="$RW_DB_PASSWORD" rolewarden-db mariadb -u"$RW_DB_USERNAME" -N -B rolewarden_test -e "$1"; }
dump() { docker exec -e MYSQL_PWD="$RW_DB_PASSWORD" rolewarden-db mariadb-dump -u"$RW_DB_USERNAME" --skip-dump-date --skip-comments rolewarden_test; }
spark() { (cd "$APP" && env $DBENV "$PHP85" spark "$@"); }

stop_server() {
  if [ -n "$SRV" ]; then
    WPID=$(cat /proc/$SRV/winpid 2>/dev/null)
    [ -n "$WPID" ] && taskkill //F //PID "$WPID" >/dev/null 2>&1
    kill "$SRV" 2>/dev/null
    SRV=""
  fi
  # The MSYS wrapper pid is not always php.exe's: also kill whatever still listens on our port.
  for p in $(netstat -ano | grep -E "[:.]$PORT[[:space:]].*LISTEN" | awk '{print $NF}' | sort -u); do
    taskkill //F //PID "$p" >/dev/null 2>&1
  done
}

start_server() { # $1 = CI_ENVIRONMENT (written in the copy's .env: .env wins over the process env in CI4)
  sed -i "s/^CI_ENVIRONMENT = .*/CI_ENVIRONMENT = $1/" "$APP/.env"
  (cd "$APP" && exec env $DBENV CI_ENVIRONMENT="$1" "$PHP85" -S localhost:$PORT -t public vendor/codeigniter4/framework/system/rewrite.php >"$WORK/server-$1.log" 2>&1) &
  SRV=$!
  for i in 1 2 3 4 5 6 7 8 9 10; do
    curl -s -o /dev/null http://localhost:$PORT/index.php/login && return 0
    sleep 1
  done
  echo "server did not start"; exit 3
}

clear_writable() {
  find "$APP"/writable/cache "$APP"/writable/session "$APP"/writable/logs -type f ! -name index.html -delete 2>/dev/null
}

# FAIL on any PHP-level diagnostic, list CRITICAL/ERROR lines for the report (some are provoked on purpose).
scan_logs() { # $1 = phase label
  L=$(cat "$APP"/writable/logs/log-*.log 2>/dev/null)
  d=$(echo "$L" | grep -E '^(DEPRECATED|WARNING|NOTICE)' | cut -c1-300 | sort | uniq -c)
  s=$(grep -E 'PHP (Deprecated|Warning|Notice|Fatal)|^(Deprecated|Warning|Notice|Fatal error):' "$WORK"/server-*.log 2>/dev/null | cut -c1-300 | sort | uniq -c)
  e=$(echo "$L" | grep -E '^(CRITICAL|ERROR|ALERT|EMERGENCY)' | cut -c1-240 | sed 's/ - [0-9-]* [0-9:]* --> / /' | sort | uniq -c)
  echo "-- log scan [$1]: diagnostics=$(echo "$d$s" | grep -c .) errors=$(echo "$e" | grep -c .)"
  [ -n "$d$s" ] && { echo "$d"; echo "$s"; fail "PHP diagnostics in phase $1"; }
  [ -n "$e" ] && echo "$e"
  rm -f "$WORK"/server-*.log
  return 0
}

install() {
  sql "DROP DATABASE rolewarden_test; CREATE DATABASE rolewarden_test;"
  clear_writable
  for n in 'CodeIgniter\Settings' 'CodeIgniter\Shield' 'RoleWarden'; do spark migrate -n "$n" | tail -1; done
  spark db:seed 'RoleWarden\Database\Seeds\RoleWardenSeeder' | tail -1
}

run() { # $1 = output name, rest = env assignments + script
  name=$1; shift
  env "$@" >"$OUT.$name.output.txt" 2>&1 || fail "$name (see $OUT.$name.output.txt)"
  tail -2 "$OUT.$name.output.txt"
}

cleanup() {
  stop_server
  [ -f "$WORK/AuthGroups.php.bak" ] && cp "$WORK/AuthGroups.php.bak" "$APP/app/Config/AuthGroups.php"
  [ -f "$WORK/production.php.bak" ] && cp "$WORK/production.php.bak" "$APP/app/Config/Boot/production.php"
  [ -f "$WORK/Logger.php.bak" ] && cp "$WORK/Logger.php.bak" "$APP/app/Config/Logger.php"
  sed -i "s/^CI_ENVIRONMENT = .*/CI_ENVIRONMENT = development/" "$APP/.env"
  if [ -n "${BEFORE:-}" ]; then
    echo "== restore rolewarden_test from snapshot"
    sql "DROP DATABASE IF EXISTS rolewarden_test; CREATE DATABASE rolewarden_test;"
    docker exec -i -e MYSQL_PWD="$RW_DB_PASSWORD" rolewarden-db mariadb -u"$RW_DB_USERNAME" rolewarden_test <"$WORK/snap.sql"
    AFTER=$(dump | md5sum | cut -d' ' -f1)
    echo "snapshot md5=$BEFORE restored md5=$AFTER $( [ "$BEFORE" = "$AFTER" ] && echo MATCH || echo MISMATCH )"
  fi
  clear_writable
  rm -rf "$WORK"
  rm -f "$(cygpath -u "$("$CPHP" -r 'echo sys_get_temp_dir();')")"/rwv1-*.jar
  echo "php.exe still running: $(tasklist //FI 'IMAGENAME eq php.exe' | grep -c php.exe)"
  echo "copy left in place: $APP"
}
trap cleanup EXIT
trap 'exit 130' INT TERM

static_checks
if [ "$MODE" = static ]; then
  echo "static FAILS=$FAILS"; exit $([ $FAILS -eq 0 ] && echo 0 || echo 1)
fi

# ---------------------------------------------------------------- DB phases
for v in RW_DB_HOSTNAME RW_DB_USERNAME RW_DB_PASSWORD RW_DB_PORT; do eval "[ -n \"\${$v:-}\" ]" || { echo "missing $v"; exit 2; }; done
grep -q '^database.default.database = rolewarden_test' "$APP/.env" || { echo ".env of the copy is not on rolewarden_test"; exit 5; }
grep -qi 'password' "$APP/.env" && { echo ".env of the copy contains a password line"; exit 5; }
netstat -ano | grep -qE "[:.]$PORT[[:space:]].*LISTEN" && { echo "port $PORT busy"; exit 3; }

echo "== patched client copies (8070 -> $PORT) in $WORK"
ROOTM="$(cygpath -m "$ROOT")"
for f in verify-v1.lib.php verify-v1.fixtures.php verify-v1.php verify-v1.prod.php verify-v1-anno.php verify-v1-a1a4.php verify-matrix-php85-m6.php; do
  sed -e "s#localhost:8070#localhost:$PORT#g" -e "s#__DIR__ . '/../../#'$ROOTM/#g" "$HERE/$f" >"$WORK/$f"
done
grep -q "localhost:$PORT" "$WORK/verify-v1.lib.php" || { echo "port patch failed"; exit 4; }

echo "== snapshot rolewarden_test"
dump >"$WORK/snap.sql" || exit 2
BEFORE=$(md5sum <"$WORK/snap.sql" | cut -d' ' -f1)
echo "tables: $(grep -c '^CREATE TABLE' "$WORK/snap.sql") md5=$BEFORE"

# Same as the PHP 8.3 + CI4 4.5 run: E_ALL and log threshold 9 in production too (display stays off),
# so deprecations/warnings raised in production are logged and caught by scan_logs.
cp "$APP/app/Config/Boot/production.php" "$WORK/production.php.bak"; cp "$APP/app/Config/Logger.php" "$WORK/Logger.php.bak"
sed -i 's/^error_reporting(E_ALL & ~E_DEPRECATED);/error_reporting(E_ALL);/' "$APP/app/Config/Boot/production.php"
sed -i "s/public \$threshold = (ENVIRONMENT === 'production') ? 4 : 9;/public \$threshold = 9;/" "$APP/app/Config/Logger.php"
grep -q '^error_reporting(E_ALL);' "$APP/app/Config/Boot/production.php" && grep -q 'public $threshold = 9;' "$APP/app/Config/Logger.php" || { echo "production logging patch failed"; exit 4; }

if [ "$MODE" = probe ]; then # ad-hoc diagnostic client script ($2, from the scratchpad), development only
  sed -e "s#localhost:8070#localhost:$PORT#g" -e "s#__DIR__ . '/../../#'$ROOTM/#g" "$2" >"$WORK/probe.php"
  install; start_server development; "$CPHP" "$WORK/probe.php"; stop_server; scan_logs probe; exit 0
fi

echo "== DEVELOPMENT: full v1.0 suite"
install; start_server development
run v1.development "$CPHP" "$WORK/verify-v1.php"
echo "last successful logins (U03 diagnostic):"; sqlt "SELECT user_id, MAX(date) FROM auth_logins WHERE success=1 GROUP BY user_id ORDER BY 2 DESC, 1 LIMIT 4"
stop_server; scan_logs dev-v1
echo "== DEVELOPMENT: marginal notes"
install; start_server development
run anno.development RW_ANNO_ENV=development "$CPHP" "$WORK/verify-v1-anno.php"; stop_server; scan_logs dev-anno
echo "== DEVELOPMENT: A1-A4"
install; start_server development
run a1a4.development "$CPHP" "$WORK/verify-v1-a1a4.php"; stop_server; scan_logs dev-a1a4
echo "== PRODUCTION: marginal notes"
install; start_server production
run anno.production RW_ANNO_ENV=production "$CPHP" "$WORK/verify-v1-anno.php"; stop_server; scan_logs prod-anno
echo "== PRODUCTION: A1-A4 + v1.0 production spot check"
install; start_server production
run a1a4.production "$CPHP" "$WORK/verify-v1-a1a4.php"
run v1.production "$CPHP" "$WORK/verify-v1.prod.php"; stop_server; scan_logs prod-a1a4

echo "== DEVELOPMENT: M6 existing-Shield-app scenario + rollback"
sql "DROP DATABASE rolewarden_test; CREATE DATABASE rolewarden_test;"; clear_writable
spark migrate -n 'CodeIgniter\Settings' | tail -1; spark migrate -n 'CodeIgniter\Shield' | tail -1
cp "$APP/app/Config/AuthGroups.php" "$WORK/AuthGroups.php.bak"
sed -i \
  -e "/public array \$groups = \[/a\\        'editor' => ['title' => 'Editor', 'description' => 'Matrix M6 scenario']," \
  -e "/public array \$permissions = \[/a\\        'editor.publish' => 'Can publish'," \
  -e "/public array \$matrix = \[/a\\        'editor' => ['editor.publish', 'users.*']," \
  "$APP/app/Config/AuthGroups.php"
[ "$(grep -c "'editor" "$APP/app/Config/AuthGroups.php")" = 3 ] || fail "AuthGroups.php patch did not apply"
"$PHP85" -l "$APP/app/Config/AuthGroups.php" >/dev/null || fail "AuthGroups.php patch"
RW_M6_PW="M6p-$(od -An -N6 -tx1 /dev/urandom | tr -d ' \n')-Aa1!"; export RW_M6_PW
for u in adminuser editoruser; do
  printf '%s\n%s\n' "$RW_M6_PW" "$RW_M6_PW" | spark shield:user create -n $u -e $u@m6.test >/dev/null 2>&1
  printf 'y\n' | spark shield:user activate -e $u@m6.test >/dev/null 2>&1
done
[ "$(sqlt "SELECT COUNT(*) FROM auth_identities WHERE secret IN ('adminuser@m6.test','editoruser@m6.test')")" = 2 ] || fail "M6 Shield users not created"
sqlt "UPDATE users SET active=1 WHERE id IN (SELECT user_id FROM auth_identities WHERE secret LIKE '%@m6.test');
      INSERT INTO auth_groups_users (user_id, \`group\`, created_at) SELECT user_id, 'admin', NOW() FROM auth_identities WHERE secret='adminuser@m6.test';
      INSERT INTO auth_groups_users (user_id, \`group\`, created_at) SELECT user_id, 'editor', NOW() FROM auth_identities WHERE secret='editoruser@m6.test';"
# Baseline of Shield's own group rows (shield:user create also adds the default group): compared after install/rollback.
RW_M6_AGU="$(sqlt 'SELECT user_id, `group` FROM auth_groups_users ORDER BY user_id, `group`' | tr '\t\n' ':,')"; export RW_M6_AGU
echo "auth_groups_users before install: $RW_M6_AGU"
spark migrate -n RoleWarden
run m6.installed "$CPHP" "$WORK/verify-matrix-php85-m6.php" installed
spark db:seed 'RoleWarden\Database\Seeds\RoleWardenSeeder' | tail -1
spark db:seed 'RoleWarden\Database\Seeds\RoleWardenSeeder' | tail -1
start_server development
run m6.reseeded "$CPHP" "$WORK/verify-matrix-php85-m6.php" reseeded; stop_server
RWB=$(sqlt "SELECT MAX(batch) FROM migrations WHERE namespace='RoleWarden'")
spark migrate:rollback -b $((RWB - 1)) -f | tail -2
run m6.rolledback "$CPHP" "$WORK/verify-matrix-php85-m6.php" rolledback
spark migrate:rollback -b 0 -f | tail -2
run m6.rolledback0 "$CPHP" "$WORK/verify-matrix-php85-m6.php" rolledback0
scan_logs dev-m6

echo "== matrix PHP 8.5: FAILS=$FAILS"
[ $FAILS -eq 0 ]
