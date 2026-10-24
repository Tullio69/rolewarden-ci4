#!/usr/bin/env bash
# U4 driver (Collaudatore ad Hoc): tappa U4 of 1.1 (dashboard) on RW_V8_NEW (default cc26ffb), host suites strictly one at a time.
#   U4 suite (verify-u4.harness.php): phase DASH (blocks, numbers, notice, colours, languages, redirects) and UPG (seed,
#   update from 1.0.0, rollback) in development and production; regression: U3 SRCH, U2 (CHK, ST), U1 (NAV, CNT, TODAY,
#   RAIL, STYLE, LANG, ESC) with verify-u4.u1lib.adapted.php (NAVMAP gains the Dashboard item).
# Needs RW_DB_USERNAME / RW_DB_PASSWORD and RW_U4_SCRATCH (scratchpad folder) in the process environment, never in files.
# Usage: bash verify-u4.all.sh [step ...]   (no step: all)
cd "$(dirname "$0")" || exit 1
: "${RW_DB_USERNAME:?}" "${RW_DB_PASSWORD:?}" "${RW_U4_SCRATCH:?}"
export RW_V8_NEW=${RW_V8_NEW:-cc26ffb} RW_S1_BASE=b1cca14 RW_V8_SCRATCH="$RW_U4_SCRATCH" RW_U4_ROOT="$(cd ../.. && pwd)"
P=verify-u4.progress.txt
step() { # label, command...
  local l=$1; shift
  left=$(powershell -NoProfile -Command "(Get-CimInstance Win32_Process | Where-Object { (\$_.Name -in 'php.exe','python.exe','node.exe' -and \$_.CommandLine -match 'verify-|8071') -or (\$_.Name -eq 'chrome.exe' -and \$_.CommandLine -match 'u4w-|u4r-') }).Count")
  if [ "${left:-0}" != "0" ]; then echo "STOP before $l: $left leftover processes" >> "$P"; exit 3; fi
  echo "=== $l $(date +%H:%M:%S)" >> "$P"
  "$@" > "verify-u4.console.$l.txt" 2>&1
  echo "exit=$? $l $(date +%H:%M:%S)" >> "$P"
}
want() { [ -z "$STEPS" ] || [[ " $STEPS " == *" $1 "* ]]; }
STEPS="$*"
for e in development production; do
  want "dash.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U4_SCRATCH/u4w-dash-$e" step "dash.$e" python verify-u4.suite.py s1 "dash.$RW_V8_NEW.$e" DASH
  want "upg.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U4_SCRATCH/u4w-upg-$e" step "upg.$e" python verify-u4.suite.py s1 "upg.$RW_V8_NEW.$e" UPG
done
for e in development production; do
  want "u3.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U4_SCRATCH/u4r-u3-$e" step "u3.$e" python verify-u4.suite.py s1 "u3.$RW_V8_NEW.$e" SRCH
  want "u2.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U4_SCRATCH/u4r-u2-$e" step "u2.$e" python verify-u4.suite.py s1 "u2.$RW_V8_NEW.$e" CHK,ST
  want "u1.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U4_SCRATCH/u4r-u1-$e" step "u1.$e" python verify-u4.suite.py s1 "u1.$RW_V8_NEW.$e" NAV,CNT,TODAY,RAIL,STYLE,LANG,ESC
done
echo "ALL DONE $(date +%H:%M:%S)" >> "$P"
