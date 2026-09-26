#!/bin/bash
# Version matrix, PHP 8.3 + CodeIgniter 4.5 (Collaudatore ad Hoc A). Usage: verify-matrix-ci45.sh <scratch-dir>
#
# Why a fresh appstarter and not a copy of ../rolewarden-app-test: that app's app/ skeleton is the
# 4.7 one; with system/ downgraded to 4.5 it dies before any module code runs
# ("Undefined property: Config\Kint::$richSort", 4.7 removed a property 4.5 still reads). A buyer
# on 4.5 has the 4.5 skeleton, so the app is rebuilt the buyer's way: appstarter 4.5.*, framework
# pinned to 4.5.*, module through a Composer path repository (Composer makes the junction), Shield's
# own `shield:setup`, then the README wiring plus the two host settings the sibling app carries
# ($userProvider, redirect after login). The resulting app/Config/Auth.php is checked equal to the
# sibling's. Nothing in the module or in the sibling app is modified.
#
# Phases (each: fresh README install on rolewarden_test, ONE php -S on :8070, stopped after):
#   development: verify-v1.php, verify-v1-anno.php, verify-v1-a1a4.php
#   production : verify-v1-anno.php, verify-v1-a1a4.php + verify-v1.prod.php
#   graft      : verify-matrix-ci45-graft.php (existing Shield app with users and stock AuthGroups,
#                README install, HTTP in development and production, rollback to Shield and to 0)
# E_ALL: development boots with E_ALL; production's Boot file is set to E_ALL in the copy (display
# stays off) and the logger threshold to 9 in both, so warnings/notices/deprecations are logged.
# After each phase the php -S log and writable/logs are scanned (verify-matrix-ci45.logcheck.txt).
# Always: server stopped, rolewarden_test restored from the snapshot and re-dumped for the checksum,
# junction removed before the copy. Credentials only from RW_DB_* (never written to files).
set -u
SCR="$1"
HERE="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$HERE/../.." && pwd)"
HOST="$ROOT/../rolewarden-app-test"
APP="$SCR/app"
JCT="$APP/vendor/rolewarden/codeigniter4-rolewarden"
OUT="$HERE/verify-matrix-ci45"
LOGCHECK="$OUT.logcheck.txt"
export RW_V1_APP="$(cygpath -m "$APP")"
export RW_STATE="$(cygpath -m "$SCR")/graft-state.json"
DBENV="database.default.password=$RW_DB_PASSWORD database.default.username=$RW_DB_USERNAME database.default.hostname=$RW_DB_HOSTNAME database.default.port=$RW_DB_PORT database.default.database=rolewarden_test"
SRV=""
PHASE=""

sql() { docker exec -e MYSQL_PWD="$RW_DB_PASSWORD" rolewarden-db mariadb -u"$RW_DB_USERNAME" -e "$1"; }
dump() { docker exec -e MYSQL_PWD="$RW_DB_PASSWORD" rolewarden-db mariadb-dump -u"$RW_DB_USERNAME" --skip-dump-date --skip-comments rolewarden_test; }
spark() { (cd "$APP" && env $DBENV php spark "$@"); }

stop_server() {
  if [ -n "$SRV" ]; then
    WPID=$(cat /proc/$SRV/winpid 2>/dev/null)
    [ -n "$WPID" ] && taskkill //F //PID "$WPID" >/dev/null 2>&1
    kill "$SRV" 2>/dev/null
    SRV=""
    scan_logs
  fi
}

# Anything at warning level or above, or any PHP diagnostic on the server's stderr, is listed.
scan_logs() {
  {
    echo "== $PHASE"
    W=$(grep -hiE 'PHP (Warning|Notice|Deprecated|Fatal|Parse)|Deprecated:|Warning:|Notice:' "$SCR/server.log" 2>/dev/null | wc -l)
    echo "php -S stderr: $W PHP diagnostics"
    grep -hiE 'PHP (Warning|Notice|Deprecated|Fatal|Parse)|Deprecated:|Warning:|Notice:' "$SCR/server.log" 2>/dev/null | head -20
    for L in WARNING NOTICE DEPRECATED ERROR CRITICAL ALERT EMERGENCY; do
      echo "writable/logs $L: $(cat "$APP"/writable/logs/log-*.log 2>/dev/null | grep -c "^$L - ")"
    done
    cat "$APP"/writable/logs/log-*.log 2>/dev/null | grep -E '^(WARNING|NOTICE|DEPRECATED|ERROR|CRITICAL|ALERT|EMERGENCY) - ' | sed -E 's/^([A-Z]+) - [0-9-]+ [0-9:]+ --> /\1 /' | sort | uniq -c | sort -rn | head -30
  } >>"$LOGCHECK"
  rm -f "$APP"/writable/logs/log-*.log "$SCR/server.log"
}

cleanup() {
  stop_server
  echo "== restore rolewarden_test from snapshot"
  sql "DROP DATABASE IF EXISTS rolewarden_test; CREATE DATABASE rolewarden_test;"
  docker exec -i -e MYSQL_PWD="$RW_DB_PASSWORD" rolewarden-db mariadb -u"$RW_DB_USERNAME" rolewarden_test <"$SCR/snap.sql"
  AFTER=$(dump | md5sum | cut -d' ' -f1)
  echo "snapshot md5=$BEFORE restored md5=$AFTER $( [ "$BEFORE" = "$AFTER" ] && echo MATCH || echo MISMATCH )" | tee -a "$LOGCHECK"
  rm -f "$SCR/snap.sql" "$SCR/graft-state.json"
  rm -f "$(cygpath -u "$(php -r 'echo sys_get_temp_dir();')")"/rwv1-*.jar
  [ -n "${RW_KEEP_APP:-}" ] && { echo "app copy kept (RW_KEEP_APP)"; return; }
  [ -e "$JCT" ] && cmd //c rmdir "$(cygpath -w "$JCT")"
  [ -e "$JCT" ] && { echo "junction still present, copy NOT removed"; return; }
  rm -rf "$APP"
  echo "temp copy removed: $( [ -e "$APP" ] && echo no || echo yes ); php.exe left: $(tasklist //FI 'IMAGENAME eq php.exe' | grep -c php.exe)"
}

start_server() { # $1 = CI_ENVIRONMENT (set in the copy's .env: it wins over the process env in CI4)
  PHASE="$2"
  sed -i "s/^CI_ENVIRONMENT = .*/CI_ENVIRONMENT = $1/" "$APP/.env"
  (cd "$APP" && exec env $DBENV CI_ENVIRONMENT="$1" php -d error_reporting=E_ALL -d log_errors=1 -d display_startup_errors=1 -S localhost:8070 -t public vendor/codeigniter4/framework/system/rewrite.php >"$SCR/server.log" 2>&1) &
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
  for n in 'CodeIgniter\Settings' 'CodeIgniter\Shield' 'RoleWarden'; do spark migrate -n "$n" | tail -1; done
  spark db:seed 'RoleWarden\Database\Seeds\RoleWardenSeeder' | tail -1
}

build_app() {
  echo "== build: appstarter 4.5.*, framework 4.5.*, module via path repository"
  (cd "$SCR" && composer create-project "codeigniter4/appstarter:4.5.*" app --no-interaction -q) || exit 6
  (cd "$APP" && composer config repositories.rolewarden path "$(cygpath -m "$ROOT")" \
    && composer require "codeigniter4/framework:4.5.*" "rolewarden/codeigniter4-rolewarden:@dev" -W --no-interaction -q) || exit 6
  cp "$HOST/.env" "$APP/.env"
  sed -i -e "s/^database.default.database = .*/database.default.database = rolewarden_test/" -e '/password/d' -e "s#^app.baseURL = .*#app.baseURL = 'http://localhost:8070/'#" "$APP/.env"
  grep -q '^database.default.database = rolewarden_test' "$APP/.env" || exit 5
  printf 'n\nn\nn\n' | spark shield:setup >/dev/null 2>&1
  (cd "$APP" && php -r '
    $e = fn ($f, $a, $b) => file_put_contents($f, str_replace($a, $b, file_get_contents($f), $n)) && $n > 0 || exit("edit failed: $f\n");
    $e("app/Config/Email.php", ["public string \$fromEmail  = \x27\x27;", "public string \$fromName   = \x27\x27;"], ["public string \$fromEmail  = \x27noreply@example.test\x27;", "public string \$fromName   = \x27RoleWarden Test\x27;"]);
    $e("app/Config/Logger.php", "(ENVIRONMENT === \x27production\x27) ? 4 : 9", "9");
    $e("app/Config/Boot/production.php", "error_reporting(E_ALL & ~E_DEPRECATED);", "error_reporting(E_ALL);");
    copy("app/Config/Auth.php", "app/Config/Auth.shield.bak"); copy("app/Config/Routes.php", "app/Config/Routes.shield.bak"); copy("app/Config/AuthGroups.php", "app/Config/AuthGroups.stock.bak");
    $e("app/Config/Routes.php", "service(\x27auth\x27)->routes(\$routes);", "service(\x27auth\x27)->routes(\$routes);\n\\RoleWarden\\Config\\RouteRegistrar::register(\$routes);");
    $e("app/Config/Auth.php", ["use CodeIgniter\\Shield\\Models\\UserModel;", "=> \x27\\CodeIgniter\\Shield\\Views\\login\x27", "\x27login\x27             => \x27/\x27,"], ["use RoleWarden\\Models\\UserModel;", "=> \x27\\RoleWarden\\Views\\auth\\login\x27", "\x27login\x27             => \x27rolewarden/users\x27,"]);
    copy("app/Config/Auth.php", "app/Config/Auth.wired.bak"); copy("app/Config/Routes.php", "app/Config/Routes.wired.bak");
  ') || exit 6
  diff <(tr -d '\r' <"$APP/app/Config/Auth.php") <(tr -d '\r' <"$HOST/app/Config/Auth.php") >/dev/null && echo "Auth.php identical to the sibling app's" || echo "WARNING: Auth.php differs from the sibling app's"
}

echo "== snapshot rolewarden_test"
dump >"$SCR/snap.sql" || exit 2
BEFORE=$(md5sum <"$SCR/snap.sql" | cut -d' ' -f1)
echo "tables: $(grep -c '^CREATE TABLE' "$SCR/snap.sql") md5=$BEFORE"
trap cleanup EXIT
trap 'exit 130' INT TERM

[ -d "$APP" ] || build_app
: >"$LOGCHECK"
{
  echo "verify-matrix-ci45 run $(date '+%Y-%m-%d %H:%M')"
  php -v | head -1
  (cd "$APP" && composer show 2>/dev/null | grep -E '^(codeigniter4|rolewarden)/' | awk '{print $1, $2}')
  echo "module: $(git -C "$ROOT" rev-parse --short HEAD) (working tree: $(git -C "$ROOT" status --porcelain src | wc -l) changed files under src/)"
  echo "junction: $(cmd //c dir "$(cygpath -w "$APP/vendor/rolewarden")" | grep -o 'JUNCTION.*')"
  echo "rolewarden_test snapshot md5=$BEFORE"
} | tee -a "$LOGCHECK"
find "$APP"/writable/cache -type f ! -name index.html -delete; rm -f "$APP"/writable/session/ci_session* "$APP"/writable/logs/log-*.log 2>/dev/null

RC=0
echo "== DEVELOPMENT: full v1.0 suite"
install; start_server development "development: verify-v1.php"
php "$HERE/verify-v1.php" >"$OUT.v1.development.output.txt" 2>&1 || RC=1
tail -3 "$OUT.v1.development.output.txt"; stop_server
echo "== DEVELOPMENT: marginal notes"
install; start_server development "development: verify-v1-anno.php"
RW_ANNO_ENV=development php "$HERE/verify-v1-anno.php" >"$OUT.anno.development.output.txt" 2>&1 || RC=1
tail -1 "$OUT.anno.development.output.txt"; stop_server
echo "== DEVELOPMENT: A1-A4"
install; start_server development "development: verify-v1-a1a4.php"
php "$HERE/verify-v1-a1a4.php" >"$OUT.a1a4.development.output.txt" 2>&1 || RC=1
tail -2 "$OUT.a1a4.development.output.txt"; stop_server
echo "== PRODUCTION: marginal notes"
install; start_server production "production: verify-v1-anno.php"
RW_ANNO_ENV=production php "$HERE/verify-v1-anno.php" >"$OUT.anno.production.output.txt" 2>&1 || RC=1
tail -1 "$OUT.anno.production.output.txt"; stop_server
echo "== PRODUCTION: A1-A4 + v1.0 production spot check"
install; start_server production "production: verify-v1-a1a4.php + verify-v1.prod.php"
php "$HERE/verify-v1-a1a4.php" >"$OUT.a1a4.production.output.txt" 2>&1 || RC=1
tail -2 "$OUT.a1a4.production.output.txt"
php "$HERE/verify-v1.prod.php" >"$OUT.v1prod.production.output.txt" 2>&1 || RC=1
tail -2 "$OUT.v1prod.production.output.txt"; stop_server

echo "== GRAFT: existing Shield app, stock AuthGroups + one extra group, README install, rollback"
G="$OUT.graft.output.txt"
: >"$G"
sed -i "s/^CI_ENVIRONMENT = .*/CI_ENVIRONMENT = development/" "$APP/.env"
sql "DROP DATABASE rolewarden_test; CREATE DATABASE rolewarden_test;"
find "$APP"/writable/cache -type f ! -name index.html -delete; rm -f "$APP"/writable/session/ci_session* 2>/dev/null
# the "existing" app: Shield's own wiring, stock groups plus a non-colliding "editor" group
cp "$APP/app/Config/Auth.shield.bak" "$APP/app/Config/Auth.php"; cp "$APP/app/Config/Routes.shield.bak" "$APP/app/Config/Routes.php"
(cd "$APP" && php -r '
  $f = "app/Config/AuthGroups.php"; $s = file_get_contents("app/Config/AuthGroups.stock.bak");
  $s = str_replace("    public array \$groups = [\n", "    public array \$groups = [\n        \x27editor\x27 => [\x27title\x27 => \x27Editor\x27, \x27description\x27 => \x27Publishes content.\x27],\n", $s, $a);
  $s = str_replace("    public array \$permissions = [\n", "    public array \$permissions = [\n        \x27editor.publish\x27 => \x27Can publish content\x27,\n", $s, $b);
  $s = str_replace("    public array \$matrix = [\n", "    public array \$matrix = [\n        \x27editor\x27 => [\x27editor.publish\x27, \x27users.*\x27],\n", $s, $c);
  ($a && $b && $c) || exit("AuthGroups edit failed\n"); file_put_contents($f, $s);') || exit 7
{ for n in 'CodeIgniter\Settings' 'CodeIgniter\Shield'; do spark migrate -n "$n" | tail -1; done; } >>"$G" 2>&1
PWA=$(php -r 'echo "G-", bin2hex(random_bytes(6)), "-Aa1!";')
PWE=$(php -r 'echo "G-", bin2hex(random_bytes(6)), "-Aa1!";')
export RW_PWA="$PWA" RW_PWE="$PWE"
for u in adminuser:admin:$PWA editoruser:editor:$PWE; do
  N=${u%%:*}; R=${u#*:}; GR=${R%%:*}; P=${R#*:}
  printf '%s\n%s\n' "$P" "$P" | spark shield:user create -n "$N" -e "$N@example.test" 2>&1 | grep -E 'created|rror' >>"$G"
  printf 'y\n' | spark shield:user activate -n "$N" 2>&1 | grep -E 'activated|rror' >>"$G"
  printf 'y\n' | spark shield:user addgroup -n "$N" -g "$GR" 2>&1 | grep -E 'added|rror' >>"$G"
done
php "$HERE/verify-matrix-ci45-graft.php" pre >>"$G" 2>&1 || RC=1
# README wiring, then README install
cp "$APP/app/Config/Auth.wired.bak" "$APP/app/Config/Auth.php"; cp "$APP/app/Config/Routes.wired.bak" "$APP/app/Config/Routes.php"
# host-side probe route (application code, public Shield API only) for inGroup()
printf '%s\n' '$routes->get('"'"'rw-probe/ingroup/(:segment)'"'"', static fn (string $g) => json_encode(auth()->user()?->inGroup($g)), ['"'"'filter'"'"' => '"'"'session'"'"']);' >>"$APP/app/Config/Routes.php"
find "$APP"/writable/cache -type f ! -name index.html -delete
{ echo "-- php spark migrate -n RoleWarden"; spark migrate -n RoleWarden; } >>"$G" 2>&1
php "$HERE/verify-matrix-ci45-graft.php" migrated >>"$G" 2>&1 || RC=1
{ echo "-- php spark db:seed RoleWardenSeeder"; spark db:seed 'RoleWarden\Database\Seeds\RoleWardenSeeder'; } >>"$G" 2>&1
php "$HERE/verify-matrix-ci45-graft.php" seeded >>"$G" 2>&1 || RC=1
start_server development "graft: HTTP development"
php "$HERE/verify-matrix-ci45-graft.php" http development >>"$G" 2>&1 || RC=1
stop_server
start_server production "graft: HTTP production"
php "$HERE/verify-matrix-ci45-graft.php" http production >>"$G" 2>&1 || RC=1
stop_server
sed -i "s/^CI_ENVIRONMENT = .*/CI_ENVIRONMENT = development/" "$APP/.env"
PREV=$(docker exec -e MYSQL_PWD="$RW_DB_PASSWORD" rolewarden-db mariadb -u"$RW_DB_USERNAME" -N rolewarden_test -e "SELECT MAX(batch) FROM migrations WHERE namespace <> 'RoleWarden'")
{ echo "-- php spark migrate:rollback -b $PREV (batch before RoleWarden's)"; spark migrate:rollback -b "$PREV"; } >>"$G" 2>&1
php "$HERE/verify-matrix-ci45-graft.php" rolledback >>"$G" 2>&1 || RC=1
{ echo "-- php spark migrate:rollback -b 0"; spark migrate:rollback -b 0; } >>"$G" 2>&1
php "$HERE/verify-matrix-ci45-graft.php" rolledback0 >>"$G" 2>&1 || RC=1
cp "$APP/app/Config/AuthGroups.stock.bak" "$APP/app/Config/AuthGroups.php"; cp "$APP/app/Config/Routes.wired.bak" "$APP/app/Config/Routes.php"
grep -E '^(PASS|FAIL)  |TOTAL' "$G" | tail -1
exit $RC
