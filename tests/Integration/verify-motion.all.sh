#!/usr/bin/env bash
# MOTION driver (Collaudatore ad Hoc): motion across the panel on RW_V8_NEW (default b38e350), host suites strictly one at a time.
#   phase MOTION (verify-motion.phase-motion.php) in development and production; regression in development: U5 CUST,
#   U4 DASH, U3 SRCH, U1 (NAV, RAIL, STYLE, ESC) through the same harness (verify-motion.harness.php, outputs verify-motion.*).
# Needs RW_DB_USERNAME / RW_DB_PASSWORD and RW_U4_SCRATCH (scratchpad folder) in the process environment, never in files.
# Usage: bash verify-motion.all.sh [step ...]   (no step: all)
cd "$(dirname "$0")" || exit 1
: "${RW_DB_USERNAME:?}" "${RW_DB_PASSWORD:?}" "${RW_U4_SCRATCH:?}"
export RW_V8_NEW=${RW_V8_NEW:-b38e350} RW_S1_BASE=b1cca14 RW_V8_SCRATCH="$RW_U4_SCRATCH" RW_U4_ROOT="$(cd ../.. && pwd)"
P=verify-motion.progress.txt
step() { # label, command...
  local l=$1; shift
  left=$(powershell -NoProfile -Command "(Get-CimInstance Win32_Process | Where-Object { (\$_.Name -in 'php.exe','python.exe','node.exe' -and \$_.CommandLine -match 'verify-|8071') -or (\$_.Name -eq 'chrome.exe' -and \$_.CommandLine -match 'mw-|mr-') }).Count")
  if [ "${left:-0}" != "0" ]; then echo "STOP before $l: $left leftover processes" >> "$P"; exit 3; fi
  echo "=== $l $(date +%H:%M:%S)" >> "$P"
  "$@" > "verify-motion.console.$l$R.txt" 2>&1
  echo "exit=$? $l $(date +%H:%M:%S)" >> "$P"
}
want() { [ -z "$STEPS" ] || [[ " $STEPS " == *" $1 "* ]]; }
STEPS="$*"
R=${RW_MOTION_RUN:-}
for e in development production; do
  want "motion.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U4_SCRATCH/mw-motion-$e$R" step "motion.$e" python verify-motion.suite.py s1 "motion.$RW_V8_NEW.$e$R" MOTION
done
e=development
want "cust.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U4_SCRATCH/mr-cust-$e$R" step "cust.$e" python verify-motion.suite.py s1 "cust.$RW_V8_NEW.$e$R" CUST
want "dash.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U4_SCRATCH/mr-dash-$e$R" step "dash.$e" python verify-motion.suite.py s1 "dash.$RW_V8_NEW.$e$R" DASH
want "u3.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U4_SCRATCH/mr-u3-$e$R" step "u3.$e" python verify-motion.suite.py s1 "u3.$RW_V8_NEW.$e$R" SRCH
want "u1.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U4_SCRATCH/mr-u1-$e$R" step "u1.$e" python verify-motion.suite.py s1 "u1.$RW_V8_NEW.$e$R" NAV,RAIL,STYLE,ESC
echo "ALL DONE $(date +%H:%M:%S)" >> "$P"
