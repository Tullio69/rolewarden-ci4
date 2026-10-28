#!/usr/bin/env bash
# R1 Docker matrix driver (Collaudatore ad Hoc): every scenario of verify-r1.run.py, one at a time, consoles in
# verify-r1.console.<scenario>.txt, progress in verify-r1.progress.txt.
# Needs RW_DB_USERNAME / RW_DB_PASSWORD, RW_R1_WORK and RW_R1_BUILD in the process environment, never in files.
# Usage: bash verify-r1.all.sh [scenario ...]   (no scenario: all)
cd "$(dirname "$0")" || exit 1
: "${RW_DB_USERNAME:?}" "${RW_DB_PASSWORD:?}" "${RW_R1_WORK:?}" "${RW_R1_BUILD:?}"
P=verify-r1.progress.txt
ALL="v83 v84 v85 v83prod v84prod v85prod sub alias x2 x0 zdev zprod zprod83 m474p83 m474p85 m475p83 m475p84 m475p85"
for s in ${*:-$ALL}; do
  echo "=== $s $(date +%H:%M:%S)" >> "$P"
  python verify-r1.run.py "$s" > "verify-r1.console.$s.txt" 2>&1
  echo "exit=$? $s $(date +%H:%M:%S) $(grep -E '^TOTAL' "verify-r1.console.$s.txt")" >> "$P"
done
echo "ALL DONE $(date +%H:%M:%S)" >> "$P"
