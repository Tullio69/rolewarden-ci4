"""V8 documentation offline render (Collaudatore ad Hoc). No database, no PHP server.
Usage: python tests/Integration/verify-v8.docs.py
Extracts the module zip into a throwaway folder (RW_V8_WORK + '-docs'), opens every documentation page from
disk (file://) in headless Chrome with a throwaway --user-data-dir, saves a screenshot per page as
verify-v8.docs.<page>.png, and checks with --dump-dom that the page parsed. Chrome is started and closed by PID.
"""
import os
from pathlib import Path
import shutil
import subprocess as sp
import sys
import zipfile

sys.stdout.reconfigure(encoding='utf-8', errors='replace')
HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
WORK = Path(os.environ['RW_V8_WORK'] + '-docs')
CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe'


def chrome(args, timeout=60):
    p = sp.Popen([CHROME, '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--user-data-dir=' + str(WORK / 'profile')] + args, stdout=sp.PIPE, stderr=sp.DEVNULL)
    try:
        out, _ = p.communicate(timeout=timeout)
    except sp.TimeoutExpired:
        sp.run(['taskkill', '/PID', str(p.pid), '/T', '/F'], stdout=sp.DEVNULL, stderr=sp.DEVNULL)
        out = b''
    return out.decode('utf-8', 'replace')


def main():
    if WORK.exists():
        raise SystemExit('ABORT: work folder exists')
    WORK.mkdir(parents=True)
    try:
        with zipfile.ZipFile(ROOT / 'build' / 'rolewarden-module-1.0.0-rc.v8.zip') as z:
            z.extractall(WORK / 'zip')
        docs = WORK / 'zip' / 'rolewarden' / 'documentation'
        for page in sorted(docs.glob('*.html')):
            url = page.resolve().as_uri()
            png = HERE / ('verify-v8.docs.' + page.stem + '.png')
            chrome(['--window-size=1280,1400', '--hide-scrollbars', '--screenshot=' + str(png), url])
            dom = chrome(['--dump-dom', url])
            ok = png.exists() and png.stat().st_size > 20000 and '</html>' in dom
            print('[%s] %-22s screenshot %s bytes, DOM %d chars' % ('PASS' if ok else 'FAIL', page.name, png.stat().st_size if png.exists() else 0, len(dom)), flush=True)
    finally:
        try:
            shutil.rmtree(WORK)
            print('[INFO] work folder removed', flush=True)
        except Exception as e:
            print('[INFO] work folder could not be removed entirely:', e, flush=True)


if __name__ == '__main__':
    main()
