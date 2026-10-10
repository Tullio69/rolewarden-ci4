<?php

declare(strict_types=1);

namespace RoleWardenDemo\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RoleWardenDemo\DemoInstaller;
use Throwable;

class DemoInstall extends BaseCommand
{
    protected $group = 'RoleWarden';
    protected $name = 'demo:install';
    protected $description = 'Installs the RoleWarden demo: documents table, three roles, three accounts, sample documents.';

    public function run(array $params): int
    {
        $password = config('Demo')->password;
        $generated = $password === '';

        if ($generated) {
            $password = bin2hex(random_bytes(9));
        }

        try {
            DemoInstaller::install($password);
        } catch (Throwable $e) {
            CLI::error($e->getMessage());

            return 1;
        }

        CLI::write('Demo installed. Open /demo and sign in as one of:', 'green');

        foreach (array_keys(DemoInstaller::ACCOUNTS) as $email) {
            CLI::write('  ' . $email);
        }

        CLI::write($generated ? 'Password, shown only now: ' . $password : 'Password: the one in demo.password.');
        CLI::write('Remove it all with: php spark demo:uninstall');

        return 0;
    }
}
