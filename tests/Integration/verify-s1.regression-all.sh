#!/usr/bin/env bash
# S1 regression driver (copy of verify-v8b.regression-all.sh). V8b header: V8b regression driver (Collaudatore ad Hoc): earlier suites (V2-V6) strictly one after the other on RW_V8_NEW.
# Usage: bash verify-s1.regression-all.sh v6:development v6:production ...
# Needs RW_DB_USERNAME / RW_DB_PASSWORD, RW_V8_NEW, RW_V8_SCRATCH in the process environment (never in files).
cd "$(dirname "$0")" || exit 1
: "${RW_DB_USERNAME:?}" "${RW_DB_PASSWORD:?}" "${RW_V8_NEW:?}" "${RW_V8_SCRATCH:?}"
P=verify-s1.regression.progress.txt
for job in "$@"; do
  mode=${job%%:*}; envn=${job#*:}
  left=$(powershell -NoProfile -Command "(Get-CimInstance Win32_Process | Where-Object { (\$_.Name -in 'php.exe','python.exe' -and \$_.CommandLine -match 'verify-v|8070|8071') -or (\$_.Name -eq 'chrome.exe' -and \$_.CommandLine -match 's1w|v8b') }).Count")
  if [ "${left:-0}" != "0" ]; then echo "STOP before $job: $left leftover processes" >> "$P"; exit 3; fi
  echo "=== $mode $envn $RW_V8_NEW $(date +%H:%M:%S)" >> "$P"
  RW_V8_WORKNAME="$RW_V8_SCRATCH/reg-$mode-$envn" python verify-s1.regress.py "$mode" "$envn" > "verify-s1.regression.$mode.$envn.$RW_V8_NEW.console.txt" 2>&1
  echo "exit=$? $mode $envn $(date +%H:%M:%S)" >> "$P"
done
echo "ALL DONE $(date +%H:%M:%S)" >> "$P"
