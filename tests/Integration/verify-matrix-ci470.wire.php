<?php

/**
 * Host setup for verify-matrix-ci470.sh, run from the app root after `shield:setup`.
 * Test-harness settings (not the module): mail sender, logger threshold 9, E_ALL in production.
 * Wiring: exactly the three lines the current README asks for, nothing else
 * ($userProvider, RouteRegistrar::register, the login view). Each edit must match exactly once.
 */

declare(strict_types=1);

function edit(string $file, string $pattern, string $replacement): void
{
    $s = preg_replace($pattern, $replacement, file_get_contents($file), 1, $n);
    if ($n !== 1) {
        fwrite(STDERR, "edit failed: $file $pattern\n");
        exit(1);
    }
    file_put_contents($file, $s);
}

edit('app/Config/Email.php', "/public string \\\$fromEmail\\s*= '';/", "public string \$fromEmail  = 'noreply@example.test';");
edit('app/Config/Email.php', "/public string \\\$fromName\\s*= '';/", "public string \$fromName   = 'RoleWarden Test';");
edit('app/Config/Logger.php', "/\\(ENVIRONMENT === 'production'\\) \\? 4 : 9/", '9');
edit('app/Config/Boot/production.php', '/error_reporting\(E_ALL & ~E_DEPRECATED\);/', 'error_reporting(E_ALL);');

copy('app/Config/Auth.php', 'app/Config/Auth.shield.bak');
copy('app/Config/Routes.php', 'app/Config/Routes.shield.bak');
copy('app/Config/AuthGroups.php', 'app/Config/AuthGroups.stock.bak');

// README "Wiring the admin panel", literally.
edit('app/Config/Auth.php', '/public string \$userProvider = [^;]+;/', 'public string $userProvider = \\\\RoleWarden\\\\Models\\\\UserModel::class;');
edit('app/Config/Routes.php', "/service\\('auth'\\)->routes\\(\\\$routes\\);/", "service('auth')->routes(\$routes);\n\\\\RoleWarden\\\\Config\\\\RouteRegistrar::register(\$routes);");
edit('app/Config/Auth.php', "/'login'(\\s*)=> '\\\\CodeIgniter\\\\Shield\\\\Views\\\\login'/", "'login'\$1=> '\\\\RoleWarden\\\\Views\\\\auth\\\\login'");

copy('app/Config/Auth.php', 'app/Config/Auth.wired.bak');
copy('app/Config/Routes.php', 'app/Config/Routes.wired.bak');
echo "wiring applied\n";
