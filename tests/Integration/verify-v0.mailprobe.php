<?php
// Used by verify-v0.docker.py inside the web container: php verify-v0.mailprobe.php <env> <token>.
// Boots the app from the CLI with the framework's own Boot class and sends one message
// through CI4's Email service, using the app's configuration as it is.
[, $env, $token] = $argv;
define('FCPATH', "/srv/rolewarden/{$env}/public/");
chdir(FCPATH);
require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
define('ENVIRONMENT', 'production');
CodeIgniter\Boot::bootConsole($paths);
$email = service('email');
$email->setFrom('v0-probe@example.com');
$email->setTo('v0-ci4@example.com');
$email->setSubject('v0 ci4 ' . $token);
$email->setMessage('v0 probe ' . $token);
$sent = $email->send(false);
echo 'protocol=', $email->protocol, ' sent=', var_export($sent, true), PHP_EOL;
