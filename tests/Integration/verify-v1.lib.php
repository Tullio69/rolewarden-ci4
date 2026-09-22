<?php

/**
 * Black-box helpers for the v1.0 panel verification (Collaudatore ad Hoc).
 * Talks to the panel only over HTTP and to rolewarden_test only through mysqli.
 * Credentials come from RW_DB_* environment variables, never from files.
 */

declare(strict_types=1);

const BASE = 'http://localhost:8070/index.php/';

function db(): mysqli
{
    static $db = null;
    if ($db === null) {
        foreach (['RW_DB_HOSTNAME', 'RW_DB_USERNAME', 'RW_DB_PASSWORD', 'RW_DB_PORT'] as $v) {
            if (getenv($v) === false) {
                fwrite(STDERR, "Missing environment variable $v\n");
                exit(2);
            }
        }
        $db = new mysqli(getenv('RW_DB_HOSTNAME'), getenv('RW_DB_USERNAME'), getenv('RW_DB_PASSWORD'), 'rolewarden_test', (int) getenv('RW_DB_PORT'));
        if ($db->query('SELECT DATABASE()')->fetch_row()[0] !== 'rolewarden_test') {
            exit("Refusing to run outside rolewarden_test\n");
        }
    }
    return $db;
}

function q(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    if ($params) {
        $params = array_map(fn ($p) => $p === null ? null : (string) $p, $params);
        $st->bind_param(str_repeat('s', count($params)), ...$params);
    }
    $st->execute();
    $r = $st->get_result();
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}

function q1(string $sql, array $params = [])
{
    $rows = q($sql, $params);
    return $rows ? array_values($rows[0])[0] : null;
}

final class Client
{
    public string $jar;
    public int $status = 0;
    public string $body = '';
    public array $headers = [];
    public string $location = '';

    public function __construct(public string $name)
    {
        $this->jar = sys_get_temp_dir() . '/rwv1-' . $name . '-' . getmypid() . '.jar';
        @unlink($this->jar);
    }

    public function req(string $method, string $path, array|string|null $data = null, array $headers = []): self
    {
        $url = str_starts_with($path, 'http') ? $path : BASE . ltrim($path, '/');
        // Behave like a browser unless the caller (AJAX save) sets its own Accept.
        if (! preg_grep('/^Accept:/i', $headers)) {
            $headers[] = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
        }
        $ch = curl_init($url);
        $this->headers = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => function ($ch, $h) {
                $p = explode(':', $h, 2);
                if (count($p) === 2) {
                    $this->headers[strtolower(trim($p[0]))] = trim($p[1]);
                }
                return strlen($h);
            },
        ]);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
        }
        $this->body = (string) curl_exec($ch);
        $this->status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $this->location = $this->headers['location'] ?? '';
        curl_close($ch);
        return $this;
    }

    public function get(string $p): self
    {
        return $this->req('GET', $p);
    }

    /** Fresh CSRF token read from a page just fetched (session CSRF rotates after every POST). */
    public function token(string $fromPath): string
    {
        $this->get($fromPath);
        return csrf_of($this->body) ?: $this->sessionToken();
    }

    /**
     * Fallback when the page renders no form (e.g. a users.view-only actor, or the users list):
     * the app copy uses CI4's session CSRF with the file session handler, so the current token
     * is read from the actor's own session file. Without this, such POSTs carried an empty token
     * and were refused by CSRF before reaching the check under test (script error found in the
     * re-verification: P10, G05-G08 of the first run).
     */
    public function sessionToken(): string
    {
        $jar = @file_get_contents($this->jar) ?: '';
        if (! preg_match('/\tci_session\t([^\s]+)/', $jar, $m)) {
            return '';
        }
        $file = getenv('RW_V1_APP') . '/writable/session/ci_session' . $m[1];
        $data = @file_get_contents($file) ?: '';
        return preg_match('/csrf_test_name\|s:\d+:"([^"]+)"/', $data, $t) ? $t[1] : '';
    }

    public function post(string $p, array $data, ?string $tokenFrom = null, array $headers = []): self
    {
        if ($tokenFrom !== null) {
            $data['csrf_test_name'] = $this->token($tokenFrom);
        }
        return $this->req('POST', $p, $data, $headers);
    }

    public function login(string $email, string $password): bool
    {
        $t = $this->token('login');
        $this->req('POST', 'login', ['csrf_test_name' => $t, 'email' => $email, 'password' => $password]);
        return in_array($this->status, [302, 303], true) && ! str_contains($this->location, '/login');
    }

    public function clean(): string
    {
        return clean_html($this->body);
    }
}

function csrf_of(string $html): string
{
    if (preg_match('/name="csrf_test_name"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/<meta name="[^"]*csrf[^"]*" content="([^"]+)"/i', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/content="([0-9a-f]{32})"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

function clean_html(string $h): string
{
    $h = preg_replace('/<script[^>]*id="debugbar[^>]*>.*?<\/script>/s', '', $h);
    $h = preg_replace('/<script class="kint[^>]*>.*?<\/script>/s', '', $h);
    $h = preg_replace('/<style[^>]*(debugbar|kint)[^>]*>.*?<\/style>/s', '', $h);
    $h = preg_replace('/<style class="kint[^>]*>.*?<\/style>/s', '', $h);
    return preg_replace('/<!-- DEBUG-VIEW[^>]*-->/', '', $h);
}

/** True when a page body leaks SQL/driver error text. */
function leaks_sql(string $body): bool
{
    $b = clean_html($body);
    // Debug toolbar panels (development only) list the DB driver; strip them before looking.
    $b = preg_replace('/<div id="debug-bar".*$/s', '', $b);
    return (bool) preg_match('/(SQLSTATE|mysqli_sql_exception|You have an error in your SQL syntax|DatabaseException|Duplicate entry|foreign key constraint|Unknown column|Incorrect integer value|Truncated incorrect)/i', $b);
}

$GLOBALS['results'] = [];
$GLOBALS['ambiguities'] = [];

/** A spec conflict observed while testing: reported, never decided, not counted as PASS or FAIL. */
function amb(string $id, string $label, string $detail): void
{
    $GLOBALS['ambiguities'][] = [$id, $label, $detail];
    printf("[AMB]  %-6s %s
         -> %s
", $id, $label, $detail);
}

function check(string $id, string $label, bool $ok, string $detail = ''): bool
{
    $GLOBALS['results'][] = [$id, $label, $ok, $detail];
    printf("[%s] %-6s %s%s\n", $ok ? 'PASS' : 'FAIL', $id, $label, $ok ? '' : "\n         -> $detail");
    return $ok;
}

/** True when the response is CI4's CSRF rejection, i.e. the request never reached the code under test. */
function csrf_refused(Client $c): bool
{
    return str_contains($c->body, 'SecurityException') || str_contains($c->body, 'The action you requested is not allowed');
}
