<?php
/** MY8 phases B, P, I, X, Z (included by verify-my8.php). */
declare(strict_types=1);

/* ================================================================== B: browser installer on an empty database */
if (in_array('B', $PH, true)) {
    echo "\n== B: browser installer on an empty database (installation.html#installer)\n";
    // the logs of A and RB are kept aside and scanned by Z with the rest
    $GLOBALS['ALOG'] = logs_of(APP);
    $GLOBALS['ASQL'] = sqlerr_lines();
    empty_db();
    clean_writable();
    foreach (glob(APP . '/writable/logs/*.log') ?: [] as $f) unlink($f);
    // The application under test is ../rolewarden-app-test (the module in an existing application, not connected yet).
    // troubleshooting.html: in development every page answers 500 before the tables exist; open the installer in
    // production and switch back afterwards. The installer keeps an existing CI_ENVIRONMENT.
    file_put_contents(APP . '/.env', "CI_ENVIRONMENT = development\n");
    server_start(APP . '/public', '', __DIR__ . '/verify-my8.' . $O['label'] . '.server.log');
    $c = c('B-dev');
    G($c, 'rolewarden/install', 'install in development before the tables');
    info("installer in development before any database: {$c->status} (troubleshooting.html: 500 is the documented symptom)");
    $GLOBALS['SEEN'] = [];
    file_put_contents(APP . '/.env', "CI_ENVIRONMENT = production\n");
    foreach (glob(APP . '/writable/logs/*.log') ?: [] as $f) { copy($f, __DIR__ . '/verify-my8.' . $O['label'] . '.b-dev-probe.log'); unlink($f); }
    $c = c('B');
    $h = G($c, 'rolewarden/install', 'install first');
    check('B02', 'Installer answers 200 with the requirements met', $c->status === 200 && form_with($h, 'step') !== null, short(text_of($h), 400));
    $code = trim((string) @file_get_contents(APP . '/writable/rolewarden/install-code.txt'));
    $ip = function (array $v, string $tag) use ($c): string {
        $h = G($c, 'rolewarden/install', "install before $tag");
        $f = form_with($h, 'step');
        return P($c, $f ? abs_url($c->url, $f['action']) : BASE . 'rolewarden/install', ['csrf_test_name' => csrf_of($h)] + $v, $tag);
    };
    $h = $ip(['step' => 'code', 'code' => $code], 'code');
    $f = form_with($h, 'hostname');
    check('B03', 'Setup code accepted: database step', $f !== null, short(text_of($h), 300));
    // wrong password first: the failure must not show database details (installation.html)
    $h = $ip(['step' => 'database', 'baseURL' => BASE, 'hostname' => '127.0.0.1', 'port' => (string) DBPORT8, 'database' => 'rolewarden_test', 'username' => getenv('MY8_APP_USER'), 'password' => 'wrong-' . bin2hex(random_bytes(4))], 'database wrong');
    check('B04', 'Wrong database password: refused, no database details on screen', form_with($h, 'hostname') !== null && ! leaks_sql($h) && $c->status < 500, short(text_of($h), 300));
    $h = $ip(['step' => 'database', 'baseURL' => BASE, 'hostname' => '127.0.0.1', 'port' => (string) DBPORT8, 'database' => 'rolewarden_test', 'username' => getenv('MY8_APP_USER'), 'password' => getenv('MY8_APP_PASS')], 'database');
    $env = (string) @file_get_contents(APP . '/.env');
    check('B05', 'Database step with the MySQL 8 user (default authentication plugin): connection accepted, .env written', str_contains($env, 'database.default.port') && str_contains($env, (string) DBPORT8) && ! leaks_sql($h) && $c->status < 500, short(text_of($h), 300));
    $h = $ip(['step' => 'migrate'], 'migrate');
    $mg = migrations();
    info('installer migrations: ' . json_encode($mg));
    check('B06', 'Tables step: Settings, Shield and RoleWarden tables and the system roles, no SQL on screen', in_array('acl_roles', tables(), true) && role_id('super-admin') > 0 && role_id('admin') > 0 && ! leaks_sql($h) && $c->status < 500, short(text_of($h), 300));
    $h = $ip(['step' => 'admin', 'username' => 'my8owner', 'email' => OWNER, 'password' => OPW, 'password_confirm' => OPW], 'admin');
    check('B07', 'First super admin created (active, super-admin role); lock written; setup code deleted', is_file(APP . '/writable/rolewarden/installed.lock') && ! is_file(APP . '/writable/rolewarden/install-code.txt') && (int) q1('SELECT active FROM users WHERE id=?', [uid(OWNER)]) === 1 && q1('SELECT r.slug FROM acl_user_roles ur JOIN acl_roles r ON r.id=ur.role_id WHERE ur.user_id=?', [uid(OWNER)]) === 'super-admin', short(text_of($h), 300));
    $after = G(c('B-after'), 'rolewarden/install', 'install after');
    check('B08', 'Installer refuses to run again', forms($after) === [] || form_with($after, 'step') === null, short(text_of($after), 200));
    // back to development for the rest of the run (error_reporting -1); demo as in (a); every log line kept
    set_env_value('CI_ENVIRONMENT', 'development');
    set_env_value('logger.threshold', '9');
    set_env_value('demo.password', DPW);
    file_put_contents(APP . '/.env', MAILENV, FILE_APPEND);
    [$rc, $o] = sp(['demo:install']);
    check('B09', 'php spark demo:install after the installer (exit 0)', $rc === 0 && in_array('demo_documents', tables(), true), "rc=$rc " . short($o, 300));
    server_stop();
}

require __DIR__ . '/verify-my8.phase-p.php';

require __DIR__ . '/verify-my8.phase-ix.php';
