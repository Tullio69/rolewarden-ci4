<?php
/**
 * V6 helpers shared by the probe and the suite (Collaudatore ad Hoc): multipart upload with the
 * actor's cookie jar, as a browser sends an <input type="file">. Nothing here knows the module.
 */
declare(strict_types=1);

/**
 * POST a form with one file field. $fields are the form's own fields (CSRF included) as served;
 * $fileField is the name of the file input; $content the raw bytes; $filename what the browser sends.
 */
function upload(Client $c, string $action, array $fields, string $fileField, ?string $content, string $filename = 'users.csv', string $type = 'text/csv'): void
{
    $url = str_starts_with($action, 'http') ? $action : BASE . ltrim($action, '/');
    $boundary = '----rwv6' . bin2hex(random_bytes(8));
    $body = '';
    foreach ($fields as $k => $v) {
        if ($k === $fileField || $v === null) continue;
        $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n";
    }
    if ($content !== null) {
        $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$fileField\"; filename=\"$filename\"\r\nContent-Type: $type\r\n\r\n$content\r\n";
    } else {
        // A form sent with no file chosen: the browser sends an empty part with an empty filename.
        $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$fileField\"; filename=\"\"\r\nContent-Type: application/octet-stream\r\n\r\n\r\n";
    }
    $body .= "--$boundary--\r\n";
    $c->headers = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $c->jar, CURLOPT_COOKIEFILE => $c->jar,
        CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 120,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: multipart/form-data; boundary=' . $boundary, 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'Expect:'],
        CURLOPT_HEADERFUNCTION => function ($ch, $h) use ($c) {
            $p = explode(':', $h, 2);
            if (count($p) === 2) $c->headers[strtolower(trim($p[0]))] = trim($p[1]);
            return strlen($h);
        },
    ]);
    $c->body = (string) curl_exec($ch);
    $c->status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $c->location = $c->headers['location'] ?? '';
    curl_close($ch);
    $GLOBALS['SEEN'][] = [$action . ' (upload)', $c->status, $c->body];
}

/** Every form on the page, including file inputs (forms() lists them too, with an empty value). */
function form_with_file(string $h): ?array
{
    foreach (forms($h) as $f) {
        if (preg_match('/<input[^>]*type="file"[^>]*name="([^"]+)"|<input[^>]*name="([^"]+)"[^>]*type="file"/', $f['html'], $m)) {
            $f['file'] = $m[1] !== '' ? $m[1] : $m[2];
            return $f;
        }
    }
    return null;
}
