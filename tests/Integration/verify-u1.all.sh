#!/usr/bin/env bash
# U1 driver (Collaudatore ad Hoc): tappa U1 of 1.1 on RW_V8_NEW (default d73ce80, riverifica), host suites strictly one at a time.
#   U1 suite (verify-u1.harness.php, phases NAV, CNT, TODAY, RAIL, STYLE, LANG, ESC) in development and production;
#   regression: V8b phases L, M, P, I, G and S1 phases N, Q, Y, A, C, R, T, D, O, W, B, G (S2 loader order), V8 A-D, F, G,
#   V5 and V2 (test, extra, adapt) through verify-u1.regress.py. The Docker part (point 6 and S3) is verify-u1.s3run.py.
# Needs RW_DB_USERNAME / RW_DB_PASSWORD and RW_U1_SCRATCH (scratchpad folder) in the process environment, never in files.
# Usage: bash verify-u1.all.sh [step ...]   (no step: all)
cd "$(dirname "$0")" || exit 1
: "${RW_DB_USERNAME:?}" "${RW_DB_PASSWORD:?}" "${RW_U1_SCRATCH:?}"
export RW_V8_NEW=${RW_V8_NEW:-d73ce80} RW_S1_BASE=b1cca14 RW_V8_SCRATCH="$RW_U1_SCRATCH"
P=verify-u1.progress.txt
n=0
step() { # label, command...
  local l=$1; shift
  n=$((n+1))
  left=$(powershell -NoProfile -Command "(Get-CimInstance Win32_Process | Where-Object { (\$_.Name -in 'php.exe','python.exe' -and \$_.CommandLine -match 'verify-|8070|8071') -or (\$_.Name -eq 'chrome.exe' -and \$_.CommandLine -match 'u1w-|u1r-|u1a2-') }).Count")
  if [ "${left:-0}" != "0" ]; then echo "STOP before $l: $left leftover processes" >> "$P"; exit 3; fi
  echo "=== $l $(date +%H:%M:%S)" >> "$P"
  "$@" > "verify-u1.console.$l.txt" 2>&1
  echo "exit=$? $l $(date +%H:%M:%S)" >> "$P"
}
want() { [ -z "$STEPS" ] || [[ " $STEPS " == *" $1 "* ]]; }
STEPS="$*"
for e in development production; do
  want "u1.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U1_SCRATCH/u1w-$e" step "u1.$e" python verify-u1.suite.py s1 "$RW_V8_NEW.$e" NAV,CNT,TODAY,RAIL,STYLE,LANG,ESC
  want "a2.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U1_SCRATCH/u1a2-$e" step "a2.$e" python verify-u1.suite.py s1 "a2.$RW_V8_NEW.$e" A2
done
for e in development production; do
  want "v8b.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U1_SCRATCH/u1r-v8b-$e" step "v8b.$e" python verify-u1.suite.py s1 "v8b.$RW_V8_NEW.$e" L,M,P,I,G
  want "s1.$e" && RW_V8B_ENV=$e RW_V8B_WORK="$RW_U1_SCRATCH/u1r-s1-$e" step "s1.$e" python verify-u1.suite.py s1 "s1.$RW_V8_NEW.$e" N,Q,Y,A,C,R,T,D,O,W,B,G
done
want v8 && RW_V8B_WORK="$RW_U1_SCRATCH/u1r-v8" step v8 python verify-u1.suite.py v8 "$RW_V8_NEW" A,B,C,D,F,G
for job in v5:development v5:production test:development test:production extra:development extra:production adapt:development adapt:production; do
  mode=${job%%:*}; envn=${job#*:}
  want "$mode.$envn" && RW_V8_WORKNAME="$RW_U1_SCRATCH/u1r-$mode-$envn" step "$mode.$envn" env RW_U1_V5CSS="$PWD/verify-v5.css.py" RW_U1_V5SCRIPT=verify-u1.v5adapted.php RW_U1_V2SCRIPT=verify-u1.v2adapted.php python verify-u1.regress.py "$mode" "$envn"
done
echo "ALL DONE $(date +%H:%M:%S)" >> "$P"
