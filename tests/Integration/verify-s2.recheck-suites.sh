#!/usr/bin/env bash
# S2 re-verification driver (Collaudatore ad Hoc), f5027a3 and the rc.s2b zips: V8 P/E (verify-s2.v8adapted.php, P06d
# adapted), V7 adapted, S1 phases N/Q/Y, then V2 main/extra/adapt and V4 adapted through verify-s2.regression-all.sh.
# Strictly one suite at a time. Needs RW_DB_USERNAME / RW_DB_PASSWORD and RW_V8_SCRATCH in the process environment.
cd "$(dirname "$0")" || exit 1
: "${RW_DB_USERNAME:?}" "${RW_DB_PASSWORD:?}" "${RW_V8_SCRATCH:?}"
export RW_V8_NEW=f5027a3 RW_V8_TAG=rc.s2b RW_S1_BASE=b1cca14
P=verify-s2.regression.progress.txt
step() {
  local l=$1; shift
  echo "=== $l $(date +%H:%M:%S)" >> "$P"
  "$@" > "verify-s2.regression.$l.console.txt" 2>&1
  echo "exit=$? $l $(date +%H:%M:%S)" >> "$P"
}
RW_S2_V8SCRIPT=verify-s2.v8adapted.php RW_V8B_WORK="$RW_V8_SCRATCH/v8zip-f" step v8zip.f5027a3 python verify-s2.suite.py v8zip f5027a3.rc.s2b.adapted P,E
RW_V8B_V7SCRIPT=verify-v8b.v7adapted.php RW_S2_V7WORK="$RW_V8_SCRATCH/v7-f" step v7.f5027a3 python verify-s2.suite7.py f5027a3.rc.s2b.adapted
for e in development production; do
  RW_V8B_ENV=$e RW_V8B_WORK="$RW_V8_SCRATCH/s1f-$e" step "s1.f5027a3.$e" python verify-s2.suite.py s1 "f5027a3.NQY.$e" N,Q,Y
done
bash verify-s2.regression-all.sh test:development test:production extra:development extra:production adapt:development adapt:production v4:development v4:production
echo "RECHECK DONE $(date +%H:%M:%S)" >> "$P"
