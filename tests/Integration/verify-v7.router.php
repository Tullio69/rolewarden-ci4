<?php
/**
 * V7 (Collaudatore ad Hoc): router for `php -S` that serves the application's public/ folder at a
 * path prefix, as a web server does for an application installed in a subfolder (RW7_PREFIX, e.g.
 * "/shop"; empty for the root). The document root holds a junction <prefix> -> app/public.
 * Same job as CodeIgniter's own system/rewrite.php, plus the prefix.
 */
$prefix = rtrim((string) getenv('RW7_PREFIX'), '/');
$uri = urldecode((string) parse_url('https://x' . $_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($prefix !== '' && $uri !== $prefix && ! str_starts_with($uri, $prefix . '/')) {
    http_response_code(404);
    echo 'Not under ' . htmlspecialchars($prefix);
    return true;
}
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
$path = $root . $uri;
if ($uri !== $prefix . '/' && $uri !== $prefix && is_file($path) && ! str_ends_with($uri, '.php')) {
    return false; // static file, served by the built-in server
}
$_SERVER['SCRIPT_NAME'] = $prefix . '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . $prefix . '/index.php';
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
chdir($root . $prefix);
require $root . $prefix . '/index.php';
