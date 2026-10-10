#!/usr/bin/env bash
# V8 regression (Collaudatore ad Hoc): ONE earlier suite per call, never two in parallel.
# Usage: bash verify-v8.regression.sh <mode> <environment>   (mode: v6 v5 v4 test extra adapt v3 edges arrays | v7 | v7package)
# Needs RW_DB_USERNAME / RW_DB_PASSWORD, RW_V8_NEW, RW_V8_TAG, RW_V8_SCRATCH in the process environment (never in files).
cd "$(dirname "$0")" || exit 1
: "${RW_DB_USERNAME:?}" "${RW_DB_PASSWORD:?}" "${RW_V8_NEW:?}" "${RW_V8_TAG:?}" "${RW_V8_SCRATCH:?}"
P=verify-v8.regression.progress.txt
mode=$1; envn=${2:-}
echo "=== $mode $envn $RW_V8_NEW $(date +%H:%M:%S)" >> "$P"
case "$mode" in
  v7) python verify-v8.suite7.py "$RW_V8_NEW" > "verify-v8.v7.$RW_V8_NEW.console.txt" 2>&1 ;;
  v7package) RW_V7_NEW="$RW_V8_NEW" RW_V7_TAG="$RW_V8_TAG" python verify-v7.package.py > "verify-v8.v7package.$RW_V8_NEW.output.txt" 2>&1 ;;
  *) RW_V8_WORKNAME="$RW_V8_SCRATCH/reg-$mode-$envn" python verify-v8.regress.py "$mode" "$envn" > "verify-v8.regression.$mode.$envn.$RW_V8_NEW.console.txt" 2>&1 ;;
esac
rc=$?
echo "exit=$rc $mode $envn $(date +%H:%M:%S)" >> "$P"
exit $rc
