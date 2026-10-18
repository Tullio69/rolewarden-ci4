<?php
// S2: the wiring lines of documentation/installation.html#wiring, applied to the new application (cwd).
$f = 'app/Config/Auth.php';
$s = file_get_contents($f);
$s = preg_replace('/public string \$userProvider = [^;]+;/', 'public string $userProvider = \RoleWarden\Models\UserModel::class;', $s, 1, $n1);
$pos = strpos($s, "'\CodeIgniter\Shield\Views\login'"); $n2 = 0;
if ($pos !== false) { $s = substr_replace($s, "'\RoleWarden\Views\auth\login'", $pos, strlen("'\CodeIgniter\Shield\Views\login'")); $n2 = 1; }
$s = preg_replace("/(public array \\\$redirects = \[.*?'login'\s*=>\s*)'[^']*'/s", "\${1}'rolewarden/users'", $s, 1, $n3);
file_put_contents($f, $s);
$r = 'app/Config/Routes.php';
$t = file_get_contents($r);
$t = str_replace("service('auth')->routes(\$routes);", "service('auth')->routes(\$routes);\n\\RoleWarden\\Config\\RouteRegistrar::register(\$routes);", $t, $n4);
file_put_contents($r, $t);
$g = 'app/Config/Filters.php';
$u = file_get_contents($g);
$u = preg_replace("#//\s*'csrf',#", "'csrf',", $u, 1, $n5);
file_put_contents($g, $u);
echo "wiring: userProvider=$n1 loginView=$n2 loginRedirect=$n3 routes=$n4 csrf=$n5\n";
preg_match("/'login'\s*=>\s*'[^']*Views[^']*'/", $s, $a); preg_match("/redirects = \[.*?'login'\s*=>\s*'[^']*'/s", $s, $b);
echo 'Auth.php: ', $a[0] ?? '?', ' | ', preg_replace('/\s+/', ' ', substr($b[0] ?? '?', -40)), "\n";
exit($n1 && $n2 && $n3 && $n4 && $n5 ? 0 : 1);
