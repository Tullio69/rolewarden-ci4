#!/usr/bin/env bash
# S2 driver (Collaudatore ad Hoc): S1, V8b phases, V8 (A-D, F, G and P/E on the rc.s2 zips), V7 adapted on the zips,
# then V6, V5, V4, V2, V3 through verify-s2.regression-all.sh. Strictly one suite at a time.
# Needs RW_DB_USERNAME / RW_DB_PASSWORD and RW_V8_SCRATCH (scratchpad folder) in the process environment.
cd "$(dirname "$0")" || exit 1
: "${RW_DB_USERNAME:?}" "${RW_DB_PASSWORD:?}" "${RW_V8_SCRATCH:?}"
export RW_V8_NEW=728d7a7 RW_V8_TAG=rc.s2 RW_S1_BASE=b1cca14
P=verify-s2.regression.progress.txt
step() { # label, command...
  local l=$1; shift
  echo "=== $l $(date +%H:%M:%S)" >> "$P"
  "$@" > "verify-s2.regression.$l.console.txt" 2>&1
  echo "exit=$? $l $(date +%H:%M:%S)" >> "$P"
}
for e in development production; do
  RW_V8B_ENV=$e RW_V8B_WORK="$RW_V8_SCRATCH/s1-$e" step "s1.$e" python verify-s2.suite.py s1 "728d7a7.$e" N,Q,Y,A,T,D,O,W,B,G
  RW_V8B_ENV=$e RW_V8B_WORK="$RW_V8_SCRATCH/v8b-$e" step "v8b.$e" python verify-s2.suite.py s1 "v8b.728d7a7.$e" L,M,P,I,G
done
RW_V8B_WORK="$RW_V8_SCRATCH/v8" step v8 python verify-s2.suite.py v8 728d7a7 A,B,C,D,F,G
RW_V8B_WORK="$RW_V8_SCRATCH/v8zip" step v8zip python verify-s2.suite.py v8zip 728d7a7.rc.s2 P,E
RW_V8B_V7SCRIPT=verify-v8b.v7adapted.php RW_S2_V7WORK="$RW_V8_SCRATCH/v7" step v7 python verify-s2.suite7.py 728d7a7.rc.s2.adapted
bash verify-s2.regression-all.sh v6:development v6:production v5:development v5:production v4:development v4:production \
  test:development test:production extra:development extra:production adapt:development adapt:production \
  v3:development v3:production edges:development edges:production arrays:development arrays:production
echo "SUITES DONE $(date +%H:%M:%S)" >> "$P"
