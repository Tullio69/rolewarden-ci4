<?php

declare(strict_types=1);

namespace RoleWarden\Config;

use CodeIgniter\Config\BaseConfig;

class RoleWarden extends BaseConfig
{
    /**
     * Prefix of every table created by the module. Override it in .env
     * (rolewarden.tablePrefix = 'myapp_') or by extending this class in
     * app/Config/RoleWarden.php. Set it before running the migrations.
     */
    public string $tablePrefix = 'acl_';

    /**
     * Full name of a module table, e.g. table('roles') => 'acl_roles'.
     */
    public function table(string $name): string
    {
        return $this->tablePrefix . $name;
    }
}
