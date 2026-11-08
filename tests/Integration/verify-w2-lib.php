<?php
// W2 black-box helper: HTTP client with cookie jar, CSRF and form parsing. No credentials stored here.
final class W2Client
{
    public string $jar;
    public array $last = [];

    public function __construct(public string $base, ?string $jar = null, public string $lang = 'en')
    {
        $this->jar = $jar ?? tempnam(sys_get_temp_dir(), 'w2jar');
    }

    public function req(string $method, string $path, array $data = [], array $headers = []): array
    {
        $ch = curl_init(str_starts_with($path, 'http') ? $path : $this->base . $path);
        $h  = array_merge($this->lang !== '' ? ['Accept-Language: ' . $this->lang] : [], $headers);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_HTTPHEADER => $h,
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 180,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        $raw  = curl_exec($ch);
        if ($raw === false) {
            fwrite(STDERR, 'curl error: ' . curl_error($ch) . "
");
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $loc  = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);
        $this->last = ['code' => $code, 'location' => $loc, 'headers' => substr((string) $raw, 0, $hs), 'body' => substr((string) $raw, $hs), 'method' => $method, 'path' => $path];

        return $this->last;
    }

    public function get(string $p): array
    {
        return $this->req('GET', $p);
    }

    public function follow(string $method, string $p, array $d = []): array
    {
        $r = $this->req($method, $p, $d);
        for ($i = 0; $i < 5 && in_array($r['code'], [301, 302, 303], true); $i++) {
            $r = $this->req('GET', $r['location']);
        }

        return $r;
    }

    public static function csrf(string $html): array
    {
        if (preg_match('/<input[^>]+name="(csrf_[a-z_]+)"[^>]*value="([^"]*)"/i', $html, $m)) {
            return [$m[1] => $m[2]];
        }
        if (preg_match('/<input[^>]+value="([^"]*)"[^>]*name="(csrf_[a-z_]+)"/i', $html, $m)) {
            return [$m[2] => $m[1]];
        }

        return [];
    }

    public static function forms(string $html): array
    {
        $out = [];
        if (! preg_match_all('#<form\b([^>]*)>(.*?)</form>#is', $html, $fm, PREG_SET_ORDER)) {
            return [];
        }
        foreach ($fm as $f) {
            $attr = $f[1];
            $in   = $f[2];
            $form = ['action' => html_entity_decode(self::attr($attr, 'action') ?? ''), 'method' => strtoupper(self::attr($attr, 'method') ?? 'GET'), 'fields' => [], 'radios' => [], 'selects' => [], 'buttons' => [], 'html' => $in];
            preg_match_all('#<input\b([^>]*)>#is', $in, $im);
            foreach ($im[1] as $ia) {
                $n = self::attr($ia, 'name');
                if ($n === null) {
                    continue;
                }
                $t = strtolower(self::attr($ia, 'type') ?? 'text');
                $v = html_entity_decode(self::attr($ia, 'value') ?? '');
                if ($t === 'radio') {
                    $form['radios'][$n][] = $v;
                    if (preg_match('/\bchecked\b/i', $ia)) {
                        $form['fields'][$n] = $v;
                    }
                } elseif ($t === 'checkbox') {
                    if (preg_match('/\bchecked\b/i', $ia)) {
                        $form['fields'][$n] = $v;
                    }
                } elseif ($t === 'submit') {
                    $form['buttons'][$n][] = $v;
                } else {
                    $form['fields'][$n] = $v;
                }
            }
            preg_match_all('#<button\b([^>]*)>(.*?)</button>#is', $in, $bm, PREG_SET_ORDER);
            foreach ($bm as $b) {
                $n = self::attr($b[1], 'name');
                $form['buttonsText'][] = ['name' => $n, 'value' => html_entity_decode(self::attr($b[1], 'value') ?? ''), 'text' => trim(strip_tags($b[2]))];
                if ($n !== null) {
                    $form['buttons'][$n][] = html_entity_decode(self::attr($b[1], 'value') ?? '');
                }
            }
            preg_match_all('#<select\b([^>]*)>(.*?)</select>#is', $in, $sm, PREG_SET_ORDER);
            foreach ($sm as $s) {
                $n = self::attr($s[1], 'name');
                if ($n === null) {
                    continue;
                }
                preg_match_all('#<option\b([^>]*)>(.*?)</option>#is', $s[2], $om, PREG_SET_ORDER);
                foreach ($om as $o) {
                    $v = html_entity_decode(self::attr($o[1], 'value') ?? strip_tags($o[2]));
                    $form['selects'][$n][] = $v . ' | ' . trim(strip_tags($o[2]));
                    if (preg_match('/\bselected\b/i', $o[1])) {
                        $form['fields'][$n] = $v;
                    }
                }
                if (! isset($form['fields'][$n]) && isset($om[0])) {
                    $form['fields'][$n] = html_entity_decode(self::attr($om[0][1], 'value') ?? '');
                }
            }
            preg_match_all('#<textarea\b([^>]*)>(.*?)</textarea>#is', $in, $tm, PREG_SET_ORDER);
            foreach ($tm as $t) {
                $n = self::attr($t[1], 'name');
                if ($n !== null) {
                    $form['fields'][$n] = html_entity_decode($t[2]);
                }
            }
            $out[] = $form;
        }

        return $out;
    }

    public static function attr(string $s, string $a): ?string
    {
        if (preg_match('/(?<![\w-])' . preg_quote($a, '/') . '\s*=\s*"([^"]*)"/i', $s, $m)) {
            return $m[1];
        }

        return preg_match('/(?<![\w-])' . preg_quote($a, '/') . '\s*=\s*\'([^\']*)\'/i', $s, $m) ? $m[1] : null;
    }

    public static function text(string $html): string
    {
        $h = preg_replace('#<(script|style)\b.*?</\1>#is', '', $html);

        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($h))));
    }

    public function login(string $user, string $pw): array
    {
        $r   = $this->get('/index.php/login');
        $tok = self::csrf($r['body']);

        return $this->req('POST', '/index.php/login', $tok + ['email' => $user . '@host.test', 'password' => $pw]);
    }
}

function w2_sql_leak(string $body): bool
{
    return (bool) preg_match('/(SQLSTATE|mysqli_sql_exception|You have an error in your SQL|doesn\'t exist|Duplicate entry|DatabaseException|Unknown column|INSERT INTO|SELECT `)/i', $body);
}
