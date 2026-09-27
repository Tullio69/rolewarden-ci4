<?php

declare(strict_types=1);

namespace RoleWarden\Config;

use RoleWarden\Filters\PermissionFilter;
use RoleWarden\Filters\SignInThrottle;

class Registrar
{
    /**
     * Registrars are merged with a flat array_merge, so only keys of our own
     * are added: the host's 'globals' and its other filters stay untouched.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function Filters(): array
    {
        return [
            'aliases' => ['can' => PermissionFilter::class, 'rw-signin' => SignInThrottle::class],
            'filters' => ['rw-signin' => ['before' => ['login']]],
        ];
    }
}
