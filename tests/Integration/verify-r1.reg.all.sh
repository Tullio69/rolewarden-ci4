#!/usr/bin/env bash
# R1 regression driver (Collaudatore ad Hoc), copy of verify-motion.all.sh: the 1.1 suites (U1-U5 and motion) on 7e3730a with
# the application on PHP 8.5 in Docker (verify-r1.v7lib.php) and CodeIgniter 4.7.5 (RW_R1_APPTEST, a copy of
# rolewarden-app-test with vendor/codeigniter4/framework replaced by 4.7.5). Host suites strictly one at a time.
# Needs RW_DB_USERNAME / RW_DB_PASSWORD, RW_R1_SCRATCH and RW_R1_APPTEST in the process environment, never in files.
# Usage: bash verify-r1.reg.all.sh [step ...]   (no step: all)
cd "$(dirname "$0")" || exit 1
: "${RW_DB_USERNAME:?}" "${RW_DB_PASSWORD:?}" "${RW_R1_SCRATCH:?}" "${RW_R1_APPTEST:?}"
export RW_V8_NEW=${RW_V8_NEW:-7e3730a} RW_S1_BASE=b1cca14 RW_V8_SCRATCH="$RW_R1_SCRATCH" RW_U4_ROOT="$(cd ../.. && pwd)"
P=verify-r1.reg.progress.txt
step() { # label, command...
  local l=$1; shift
  left=$(powershell -NoProfile -Command "(Get-CimInstance Win32_Process | Where-Object { (\$_.Name -in 'php.exe','python.exe','node.exe' -and \$_.CommandLine -match 'verify-|8071') -or (\$_.Name -eq 'chrome.exe' -and \$_.CommandLine -match 'rw-r1-') }).Count")
  if [ "${left:-0}" != "0" ]; then echo "STOP before $l: $left leftover processes" >> "$P"; exit 3; fi
  if [ -n "$(docker ps -aq --filter name=^r1-)" ]; then echo "STOP before $l: r1-* containers left" >> "$P"; exit 3; fi
  echo "=== $l $(date +%H:%M:%S)" >> "$P"
  "$@" > "verify-r1.reg.console.$l.txt" 2>&1
  echo "exit=$? $l $(date +%H:%M:%S)" >> "$P"
}
want() { [ -z "$STEPS" ] || [[ " $STEPS " == *" $1 "* ]]; }
STEPS="$*"
run() { # label env phases
  want "$1.$2" && RW_V8B_ENV=$2 RW_V8B_WORK="$RW_R1_SCRATCH/rw-r1-$1-$2" step "$1.$2" python verify-r1.reg.suite.py s1 "$1.$RW_V8_NEW.$2" "$3"
}
run motion development MOTION
run motion production MOTION
run cust development CUST
run demo development DEMO
run dash development DASH
run u3 development SRCH,OV
run u2 development CHK,ST
run u1 development NAV,CNT,TODAY,RAIL,STYLE,LANG,ESC
echo "ALL DONE $(date +%H:%M:%S)" >> "$P"
