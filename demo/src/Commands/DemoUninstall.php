<?php

declare(strict_types=1);

namespace RoleWardenDemo\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RoleWardenDemo\DemoInstaller;

class DemoUninstall extends BaseCommand
{
    protected $group = 'RoleWarden';
    protected $name = 'demo:uninstall';
    protected $description = 'Removes everything the RoleWarden demo added, and nothing else.';

    public function run(array $params): int
    {
        DemoInstaller::uninstall();
        CLI::write('Demo removed: its table, roles, permissions, accounts and settings are gone.', 'green');
        CLI::write('Now delete the demo folder and its two lines in app/Config (see the demo README).');

        return 0;
    }
}
