#!/usr/bin/env bash
# U2 driver (Collaudatore ad Hoc): tappa U2 of 1.1 on RW_V8_NEW (default b921f97), host suites strictly one at a time.
#   U2 suite (verify-u2.harness.php, phases CHK and ST) in development and production;
#   regression: U1 suite (NAV, CNT, TODAY, RAIL, STYLE, LANG, ESC; NAV and RAIL adapted, verify-u2.u1lib.adapted.php) and A2,
#   V8b phases L, M, P, I, G, S1 phase Y (layout), V5 (adapted copy of U1) and V2 main suite through verify-u2.regress.py.
# Needs RW_DB_USERNAME / RW_DB_PASSWORD and RW_U2_SCRATCH (scratchpad folder) in the process environment, never in files.
# Usage: bash verify-u2.all.sh [step ...]   (no step: all)
cd "$(dirname "$0")" || exit 1
: "${RW_DB_USERNAME:?}" "${RW_DB_PASSWORD:?}" "${RW_U2_SCRATCH:?}"
export RW_V8_NEW=${RW_V8_NEW:-b921f97} RW_S1_BASE=b1cca14 RW_V8_SCRATCH="$RW_U2_SCRATCH"
P=verify-u2.progress.txt
step() { # label, command...
  local l=$1; shift
  left=$(powershell -NoProfile -Command "(Get-CimInstance Win32_Process | Where-Object { (\$_.Name -in 'php.exe','python.exe','node.exe' -and \$_.CommandLine -match 'verify-|8070|8071') -or (\$_.Name -eq 'chrome.exe' -and \$_.CommandLine -match 'u2w-|u2r-') }).Count")
  if [ "${left:-0}" != "0" ]; then echo "STOP before $l: $left leftover processes" >> "$P"; exit 3; fi
  echo "=== $l $(date +%H:%M:%S)" >> "$P"
  "$@" > "verify-u2.console.$l.txt" 2>&1
  echo "exit=$? $l $(date +%H:%M:%S)" >> "$P"
}
want() { [ -z "$STEPS" ] || [[ " $STEPS " == *" $1 "* ]]; }
STEPS="$*"
for e in development production; do
  want "u2.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U2_SCRATCH/u2w-$e" step "u2.$e" python verify-u2.suite.py s1 "u2.$RW_V8_NEW.$e" CHK,ST
done
for e in development production; do
  want "u1.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U2_SCRATCH/u2w-u1-$e" step "u1.$e" python verify-u2.suite.py s1 "u1.$RW_V8_NEW.$e" NAV,CNT,TODAY,RAIL,STYLE,LANG,ESC
  want "a2.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U2_SCRATCH/u2w-a2-$e" step "a2.$e" python verify-u2.suite.py s1 "a2.$RW_V8_NEW.$e" A2
  want "v8b.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U2_SCRATCH/u2r-v8b-$e" step "v8b.$e" python verify-u2.suite.py s1 "v8b.$RW_V8_NEW.$e" L,M,P,I,G
  want "s1y.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U2_SCRATCH/u2r-s1y-$e" step "s1y.$e" python verify-u2.suite.py s1 "s1y.$RW_V8_NEW.$e" Y
done
for job in v5:development v5:production test:development test:production; do
  mode=${job%%:*}; envn=${job#*:}
  want "$mode.$envn" && RW_V8_WORKNAME="$RW_U2_SCRATCH/u2r-$mode-$envn" step "$mode.$envn" env RW_U1_V5CSS="$PWD/verify-v5.css.py" RW_U1_V5SCRIPT=verify-u1.v5adapted.php RW_U1_V2SCRIPT=verify-u1.v2adapted.php python verify-u2.regress.py "$mode" "$envn"
done
echo "ALL DONE $(date +%H:%M:%S)" >> "$P"
