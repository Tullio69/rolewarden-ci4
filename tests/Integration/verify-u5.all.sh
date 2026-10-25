#!/usr/bin/env bash
# U5 driver (Collaudatore ad Hoc): tappa U5 of 1.1 (dashboard customization and application blocks) on RW_V8_NEW
# (default 09b9159), host suites strictly one at a time.
#   U5 suite (verify-u5.harness.php): phase CUST in development and production; regression: U4 DASH and UPG, U3 SRCH,
#   U2 (CHK, ST), U1 (NAV, CNT, TODAY, RAIL, STYLE, LANG, ESC) through the U4 adapted copies.
# Needs RW_DB_USERNAME / RW_DB_PASSWORD and RW_U4_SCRATCH (scratchpad folder) in the process environment, never in files.
# Usage: bash verify-u5.all.sh [step ...]   (no step: all)
cd "$(dirname "$0")" || exit 1
: "${RW_DB_USERNAME:?}" "${RW_DB_PASSWORD:?}" "${RW_U4_SCRATCH:?}"
export RW_V8_NEW=${RW_V8_NEW:-09b9159} RW_S1_BASE=b1cca14 RW_V8_SCRATCH="$RW_U4_SCRATCH" RW_U4_ROOT="$(cd ../.. && pwd)"
P=verify-u5.progress.txt
step() { # label, command...
  local l=$1; shift
  left=$(powershell -NoProfile -Command "(Get-CimInstance Win32_Process | Where-Object { (\$_.Name -in 'php.exe','python.exe','node.exe' -and \$_.CommandLine -match 'verify-|8071') -or (\$_.Name -eq 'chrome.exe' -and \$_.CommandLine -match 'u5w-|u5r-') }).Count")
  if [ "${left:-0}" != "0" ]; then echo "STOP before $l: $left leftover processes" >> "$P"; exit 3; fi
  echo "=== $l $(date +%H:%M:%S)" >> "$P"
  "$@" > "verify-u5.console.$l.txt" 2>&1
  echo "exit=$? $l $(date +%H:%M:%S)" >> "$P"
}
want() { [ -z "$STEPS" ] || [[ " $STEPS " == *" $1 "* ]]; }
STEPS="$*"
R=${RW_U5_RUN:-}
for e in development production; do
  want "cust.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U4_SCRATCH/u5w-cust-$e$R" step "cust.$e" python verify-u5.suite.py s1 "cust.$RW_V8_NEW.$e" CUST
done
for e in development production; do
  want "dash.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U4_SCRATCH/u5r-dash-$e$R" step "dash.$e" python verify-u5.suite.py s1 "dash.$RW_V8_NEW.$e" DASH
  want "upg.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U4_SCRATCH/u5r-upg-$e$R" step "upg.$e" python verify-u5.suite.py s1 "upg.$RW_V8_NEW.$e" UPG
  want "u3.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U4_SCRATCH/u5r-u3-$e$R" step "u3.$e" python verify-u5.suite.py s1 "u3.$RW_V8_NEW.$e" SRCH
  want "u2.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U4_SCRATCH/u5r-u2-$e$R" step "u2.$e" python verify-u5.suite.py s1 "u2.$RW_V8_NEW.$e" CHK,ST
  want "u1.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U4_SCRATCH/u5r-u1-$e$R" step "u1.$e" python verify-u5.suite.py s1 "u1.$RW_V8_NEW.$e" NAV,CNT,TODAY,RAIL,STYLE,LANG,ESC
done
echo "ALL DONE $(date +%H:%M:%S)" >> "$P"
