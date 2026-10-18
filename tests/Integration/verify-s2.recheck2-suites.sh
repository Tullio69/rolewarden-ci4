#!/usr/bin/env bash
# S2 second re-verification driver (Collaudatore ad Hoc), 09e9df8 and the rc.s2c zips. One suite at a time.
# S1 phases A, M, T with the new phases C (matrix columns) and R (activity details); V8b L/M/P/I/G; V8 P/E on the zips
# (P06d adapted); V3 main, edges, arrays; then T01 on the rc.s2c complete application in Docker (PHP 8.3 and 8.5).
# Needs RW_DB_USERNAME / RW_DB_PASSWORD, RW_V8_SCRATCH and RW_S2_WORK in the process environment.
cd "$(dirname "$0")" || exit 1
: "${RW_DB_USERNAME:?}" "${RW_DB_PASSWORD:?}" "${RW_V8_SCRATCH:?}" "${RW_S2_WORK:?}"
export RW_V8_NEW=09e9df8 RW_V8_TAG=rc.s2c RW_S1_BASE=b1cca14
P=verify-s2.regression.progress.txt
step() {
  local l=$1; shift
  echo "=== $l $(date +%H:%M:%S)" >> "$P"
  "$@" > "verify-s2.regression.$l.console.txt" 2>&1
  echo "exit=$? $l $(date +%H:%M:%S)" >> "$P"
}
# sensitivity: the new phases on the previous commit must fail where the decision applies
RW_V8_NEW=449e259 RW_V8B_ENV=development RW_V8B_WORK="$RW_V8_SCRATCH/cr-sens" step s1.cr.sensitivity-449e259 python verify-s2.suite.py s1 "CR.sensitivity-449e259" C,R
for e in development production; do
  RW_V8B_ENV=$e RW_V8B_WORK="$RW_V8_SCRATCH/s1g-$e" step "s1.09e9df8.$e" python verify-s2.suite.py s1 "09e9df8.ACRMT.$e" A,C,R,M,T
  RW_V8B_ENV=$e RW_V8B_WORK="$RW_V8_SCRATCH/v8bg-$e" step "v8b.09e9df8.$e" python verify-s2.suite.py s1 "v8b.09e9df8.$e" L,M,P,I,G
done
RW_S2_V8SCRIPT=verify-s2.v8adapted.php RW_V8B_WORK="$RW_V8_SCRATCH/v8zip-g" step v8zip.09e9df8 python verify-s2.suite.py v8zip 09e9df8.rc.s2c.adapted P,E
bash verify-s2.regression-all.sh v3:development v3:production edges:development edges:production arrays:development arrays:production
for s in k83 k85; do
  RW_S2_TAG=rc.s2c RW_S2_COMMIT=09e9df8 step "docker.$s.09e9df8" python verify-s2.run.py "$s" "$s.09e9df8"
done
echo "RECHECK2 DONE $(date +%H:%M:%S)" >> "$P"
