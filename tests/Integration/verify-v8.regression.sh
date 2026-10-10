#!/usr/bin/env bash
# V8 regression (Collaudatore ad Hoc): the earlier suites on f06a29d, one at a time, then the V7 suite,
# package and language checks on the rc.v8 zips.
# Usage: bash verify-v8.regression.sh "development production" "v6 v5 v4 test extra adapt v3 edges arrays" [v7]
# Needs RW_DB_USERNAME / RW_DB_PASSWORD in the process environment (never in files).
cd "$(dirname "$0")" || exit 1
: "${RW_DB_USERNAME:?}" "${RW_DB_PASSWORD:?}"
P=verify-v8.regression.progress.txt
for envn in ${1:-development production}; do
  for mode in ${2:-v6 v5 v4 test extra adapt v3 edges arrays}; do
    echo "=== $mode $envn $(date +%H:%M:%S)" | tee -a "$P"
    RW_V8_WORKNAME="${RW_V8_SCRATCH:?}/reg-$mode-$envn" python verify-v8.regress.py "$mode" "$envn" > "verify-v8.regression.$mode.$envn.f06a29d.console.txt" 2>&1
    echo "exit=$? $(date +%H:%M:%S)" | tee -a "$P"
  done
done
if [ "${3:-v7}" = "v7" ]; then
  echo "=== v7 suite $(date +%H:%M:%S)" | tee -a "$P"
  python verify-v8.suite7.py f06a29d > verify-v8.v7.f06a29d.console.txt 2>&1
  echo "exit=$? $(date +%H:%M:%S)" | tee -a "$P"
  echo "=== v7 package $(date +%H:%M:%S)" | tee -a "$P"
  RW_V7_NEW=f06a29d RW_V7_TAG=rc.v8 python verify-v7.package.py > verify-v8.v7package.f06a29d.output.txt 2>&1
  echo "exit=$? $(date +%H:%M:%S)" | tee -a "$P"
fi
