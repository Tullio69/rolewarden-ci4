#!/usr/bin/env bash
# V8 regression driver (Collaudatore ad Hoc): the suites strictly one after the other. Before each one it
# checks that no php -S / php suite / Chrome of a previous run is still alive (by command line), and stops
# if one is. Same environment variables as verify-v8.regression.sh.
cd "$(dirname "$0")" || exit 1
for job in "$@"; do
  mode=${job%%:*}; envn=${job#*:}; [ "$envn" = "$job" ] && envn=""
  left=$(powershell -NoProfile -Command "(Get-CimInstance Win32_Process | Where-Object { (\$_.Name -in 'php.exe','python.exe' -and \$_.CommandLine -match 'verify-v|8070|8071') -or (\$_.Name -eq 'chrome.exe' -and \$_.CommandLine -match 'rwv|v8reg|v8rv') }).Count")
  if [ "${left:-0}" != "0" ]; then echo "STOP before $job: $left leftover processes" >> verify-v8.regression.progress.txt; exit 3; fi
  bash verify-v8.regression.sh "$mode" "$envn"
done
echo "ALL DONE $(date +%H:%M:%S)" >> verify-v8.regression.progress.txt
