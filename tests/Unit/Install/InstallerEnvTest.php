<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Unit\Install;

use CodeIgniter\Config\DotEnv;
use PHPUnit\Framework\TestCase;
use RoleWarden\Install\Installer;

final class InstallerEnvTest extends TestCase
{
    public function testKeysAreReplacedInPlaceOrAppended(): void
    {
        $current = "# app.baseURL = ''\nCI_ENVIRONMENT = development\ndatabase.default.hostname = old\nkeep = 1\ndatabase.default.hostname = twice\n";

        $env = Installer::env($current, ['app.baseURL' => 'https://x.test/', 'database.default.hostname' => 'db', 'encryption.key' => 'k']);

        $this->assertSame("app.baseURL = \"https://x.test/\"\nCI_ENVIRONMENT = development\ndatabase.default.hostname = \"db\"\nkeep = 1\nencryption.key = \"k\"\n", $env);
    }

    public function testValuesSurviveCodeIgnitersDotEnvParser(): void
    {
        $password = 'pa"ss\\w o#rd\' $HOME';
        $dir = sys_get_temp_dir() . '/rwenv' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/.env', Installer::env('', ['database.default.password' => $password]));

        $parsed = (new DotEnv($dir))->parse();
        unlink($dir . '/.env');
        rmdir($dir);

        $this->assertSame(['database.default.password' => $password], $parsed);
    }
}
