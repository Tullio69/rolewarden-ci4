<?php

declare(strict_types=1);

namespace RoleWarden\Config;

use RoleWarden\Filters\PermissionFilter;

class Registrar
{
    /**
     * @return array<string, array<string, class-string>>
     */
    public static function Filters(): array
    {
        return ['aliases' => ['can' => PermissionFilter::class]];
    }
}
