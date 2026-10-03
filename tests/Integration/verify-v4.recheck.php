<?php
// Independent recheck: expectations from D1/O1/O2/A4 and captured baseline messages,
// never from module code or email templates. Loaded only by the V4 suite.
function recheck_mail(string $tag, array $f, bool $capture = true): void
{
    global $ENVN, $WORKDIR;
    $text = (string) $f['Text'];
    if ($capture) file_put_contents("$WORKDIR/verify-v4.captured-mail.json", json_encode([
        'tag' => $tag, 'To' => $f['To'], 'From' => $f['From'],
        'Subject' => $f['Subject'], 'Text' => $text, 'HTML' => $f['HTML'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    check("R-TEXT-$tag", 'Plain text has separate nonempty lines', count(array_filter(explode("\n", $text), fn ($s) => trim($s) !== '')) >= 4);
    check("R-ENTITY-$tag", 'Plain text has no encoded angle brackets', ! preg_match('/&(?:lt|gt|#0*60|#0*62|#x0*3[cCeE]);/i', $text));
    if (in_array($tag, ['new-device', 'new-ip', 'markup', 'password-profile', 'password-admin'], true)) {
        check("R-TZ-$tag", 'Date has an explicit timezone in both MIME parts',
            preg_match('/\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}\s+UTC\b/', $text)
            && preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2} UTC\b/', html_entity_decode($f['HTML'], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
    if (in_array($tag, ['new-device', 'new-ip', 'markup'], true)) {
        check("R-LINES-$tag", 'When, Device and Address each start a separate text line',
            preg_match('/^\h*When: .+$/m', $text) && preg_match('/^\h*Device: .+$/m', $text)
            && preg_match('/^\h*Address: .+$/m', $text));
    }
    if ($tag === 'markup') check('R-DECODE', 'Markup name is decoded as literal text', str_contains($text, '<i>V4esc</i>'));
    $old = file_get_contents(dirname($WORKDIR) . "/verify-v4.v4.$ENVN.output.txt");
    $found = preg_match('/^\[INFO\] ' . preg_quote($tag, '/') . ' From=(.*?) To=(.*?) Subject=(.*?) HTML=\S+ Text=(.*)$/m', $old, $m);
    $normalize = static function (string $s): string {
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = preg_replace('/\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(?:\s+UTC)?/', '<DATE>', $s);
        return preg_replace('/\s+/', '', $s);
    };
    check("R-CONTENT-$tag", 'Sender, recipients, subject and words unchanged from captured 2028ea2 message (except date/timezone, spacing and entities)',
        $found && $m[1] === $f['From']['Name'] . ' <' . $f['From']['Address'] . '>'
        && json_decode($m[2], true) === array_column($f['To'], 'Address')
        && json_decode($m[3], true) === $f['Subject']
        && $normalize(json_decode($m[4], true)) === $normalize($text));
    preg_match('/<title[^>]*>(.*?)<\/title>/is', $f['HTML'], $title);
    $titleText = html_entity_decode(strip_tags($title[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $withoutTitle = trim($text);
    if ($titleText !== '' && str_starts_with($withoutTitle, $titleText)) $withoutTitle = substr($withoutTitle, strlen($titleText));
    check("R-BODY-$tag", 'Remaining text matches baseline after removing only the extra leading HTML title',
        $found && $normalize(json_decode($m[4], true)) === $normalize($withoutTitle));
    info("Recheck $tag HTML title=" . json_encode($titleText) . '; exact Text=' . json_encode($text));
}

function recheck_d1(): void
{
    global $APP;
    echo "== D1 recheck: literal form keys, POST and subsequent GET ==\n";
    // Preserve earlier publication diagnostics; inspect each D1 case without exclusions.
    logs_take('publication');
    $i = 0;
    foreach (['email[]', 'email[0]', 'email[_]', 'email[a][b]'] as $key) {
        foreach ([false, true] as $password) {
            foreach (['', '&remember[]=1', '&remember[_]=1'] as $remember) {
                cache_clear(); // Each case reaches validation, not the per-IP rate limit.
                $a = new Client('v4-recheck-' . ++$i);
                $a->get('login'); $f = matching(forms($a->body), 'Login');
                $body = http_build_query(['csrf_test_name' => $f['fields']['csrf_test_name']])
                    . '&' . $key . '=x%40v4.test' . ($password ? '&password=y' : '') . $remember;
                $a->req('POST', $f['action'], $body);
                $clean = fn ($c) => ! leaks_sql($c->body) && ! preg_match('/Array to string|TypeError|ErrorException|PHP Warning|Fatal error/', clean_html($c->body));
                check("R-D1-$i-POST", "$key password=" . ($password ? 'present' : 'absent') . " $remember: rejected without 500",
                    in_array($a->status, [302, 303, 400, 422], true) && ! ok_login($a) && $clean($a), 'HTTP ' . $a->status);
                $a->get('login');
                check("R-D1-$i-GET", 'Subsequent GET /login in the same session is clean and usable',
                    $a->status === 200 && $clean($a) && str_contains($a->body, 'name="email"'), 'HTTP ' . $a->status);
                $lines = logs_take('d1');
                $bad = array_values(array_filter($lines, fn ($l) => preg_match('/^(WARNING|NOTICE|DEPRECATED|ERROR|CRITICAL|ALERT|EMERGENCY) /', $l)));
                check("R-D1-$i-LOG", 'D1 logs at threshold 9 have no warning or error (no exclusions)', $bad === [], implode("\n", $bad));
            }
        }
    }
}

// Replay only the email assertions from immutable Mailpit captures: no DB, HTTP or writes.
// Usage: php tests/Integration/verify-v4.recheck.php development|production [revision]
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require __DIR__ . '/verify-v1.lib.php';
    function info(string $s): void { echo "[INFO] $s\n"; }
    $ENVN = $argv[1] ?? 'development';
    if (! in_array($ENVN, ['development', 'production'], true)) exit(2);
    $revision = $argv[2] ?? 'c0ab8fc';
    if (! preg_match('/^[a-f0-9]+$/', $revision)) exit(2);
    $WORKDIR = __DIR__ . '/verify-v4.work'; // Path anchor only; never created by this replay.
    $captureFile = __DIR__ . "/verify-v4.v4.$ENVN.$revision.verify-v4.captured-mail.json";
    info('Read-only capture SHA256=' . hash_file('sha256', $captureFile));
    $records = file($captureFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($records) !== 7) exit(2);
    foreach ($records as $line) {
        $f = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        recheck_mail($f['tag'], $f, false);
    }
    $pass = count(array_filter($GLOBALS['results'], fn ($r) => $r[2]));
    echo 'MAIL REPLAY RESULT ' . count($GLOBALS['results']) . ' checks ' . $pass . ' PASS ' . (count($GLOBALS['results']) - $pass) . " FAIL\n";
    exit($pass === count($GLOBALS['results']) ? 0 : 1);
}
