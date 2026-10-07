#!/usr/bin/env bash
# V7 regression (Collaudatore ad Hoc): earlier suites on 2836697, one at a time.
# Usage: bash verify-v7.regression.sh "development production" "v6 v5 v4 test extra adapt v3 edges arrays"
# Needs RW_DB_USERNAME / RW_DB_PASSWORD in the process environment (never in files).
cd "$(dirname "$0")" || exit 1
: "${RW_DB_USERNAME:?}" "${RW_DB_PASSWORD:?}"
for envn in ${1:-development production}; do
  for mode in ${2:-v6 v5 v4 test extra adapt v3 edges arrays}; do
    echo "=== $mode $envn $(date +%H:%M:%S)"
    python verify-v7.run.py "$mode" "$envn" > "verify-v7.regression.$mode.$envn.console.txt" 2>&1
    echo "exit=$? $(date +%H:%M:%S)"
  done
done
