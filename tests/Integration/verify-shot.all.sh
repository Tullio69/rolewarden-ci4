#!/usr/bin/env bash
# SHOT driver (Collaudatore ad Hoc): panel polish of 782fb64 (parent 603a94c), host suites strictly one at a time, each step
# under 10 minutes in the foreground. Module and demo from git worktrees in the session scratchpad (RW_SHOT_WT_NEW checked out
# at 782fb64, RW_SHOT_WT_PARENT at 603a94c), never from the main repository.
# Needs RW_DB_USERNAME / RW_DB_PASSWORD, RW_SHOT_SCRATCH, RW_SHOT_WT_NEW and RW_SHOT_WT_PARENT in the process environment, never in files.
# Steps: shot.parent (measures only), shot.a then shot.b (the commit in development, in two parts under 10 minutes; b runs the checks), and the 1.1 regression (development):
#   dash (U4 DASH), cust (U5 CUST, motion copy), u2 (CHK,ST), u1a (NAV,RAIL,STYLE,ESC), u1b (NAV,CNT,TODAY,LANG: NAV creates the users the others sign in with), u3 (SRCH), motion (MOTION).
# Usage: bash verify-shot.all.sh step [step ...]
cd "$(dirname "$0")" || exit 1
: "${RW_DB_USERNAME:?}" "${RW_DB_PASSWORD:?}" "${RW_SHOT_SCRATCH:?}" "${RW_SHOT_WT_NEW:?}" "${RW_SHOT_WT_PARENT:?}"
export RW_SHOT_PART RW_S1_BASE=b1cca14 RW_V8_SCRATCH="$RW_SHOT_SCRATCH" RW_U4_ROOT="$(cd ../.. && pwd)"
P=verify-shot.progress.txt
step() { # label, command...
  local l=$1; shift
  left=$(powershell -NoProfile -Command "(Get-CimInstance Win32_Process | Where-Object { (\$_.Name -in 'php.exe','python.exe','node.exe' -and \$_.CommandLine -match 'verify-|8071') -or (\$_.Name -eq 'chrome.exe' -and \$_.CommandLine -match 'sh-work-') }).Count")
  if [ "${left:-0}" != "0" ]; then echo "STOP before $l: $left leftover processes" >> "$P"; exit 3; fi
  echo "=== $l $(date +%H:%M:%S)" >> "$P"
  "$@" > "verify-shot.console.$l$R.txt" 2>&1
  echo "exit=$? $l $(date +%H:%M:%S)" >> "$P"
}
R=${RW_SHOT_RUN:-}
run() { # label commit-worktree env phases [shot label]
  RW_SHOT_WT=$2 RW_V8_NEW=$(git -C "$2" rev-parse --short HEAD) RW_V8B_ENV=$3 RW_SHOT_LABEL=${5:-commit} RW_V8B_WORK="$RW_SHOT_SCRATCH/sh-work-$1$R" \
    step "$1" python verify-shot.suite.py s1 "$1.$(git -C "$2" rev-parse --short HEAD)$R" "$4"
}
for s in "$@"; do
  case $s in
    shot.parent) run shot.parent "$RW_SHOT_WT_PARENT" development SHOT parent ;;
    shot.a) RW_SHOT_PART=a run shot.a "$RW_SHOT_WT_NEW" development SHOT ;;
    shot.b) RW_SHOT_PART=b run shot.b "$RW_SHOT_WT_NEW" development SHOT ;;
    dash) run dash "$RW_SHOT_WT_NEW" development DASH ;;
    cust) run cust "$RW_SHOT_WT_NEW" development CUST ;;
    u2) run u2 "$RW_SHOT_WT_NEW" development CHK,ST ;;
    u1a) run u1a "$RW_SHOT_WT_NEW" development NAV,RAIL,STYLE,ESC ;;
    u1b) run u1b "$RW_SHOT_WT_NEW" development NAV,CNT,TODAY,LANG ;;
    u3) run u3 "$RW_SHOT_WT_NEW" development SRCH ;;
    motion) run motion "$RW_SHOT_WT_NEW" development MOTION ;;
    *) echo "unknown step $s" >> "$P" ;;
  esac
done
echo "DONE $* $(date +%H:%M:%S)" >> "$P"
