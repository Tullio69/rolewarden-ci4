<?php

declare(strict_types=1);

namespace RoleWarden\Config;

use RoleWarden\Filters\AccountVisibility;
use RoleWarden\Filters\PanelLanguage;
use RoleWarden\Filters\PermissionFilter;
use RoleWarden\Filters\SignInThrottle;
use RoleWarden\Filters\TablesReady;

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
            'aliases' => ['can' => PermissionFilter::class, 'rw-signin' => SignInThrottle::class, 'rw-language' => PanelLanguage::class, 'rw-account' => AccountVisibility::class, 'rw-ready' => TablesReady::class],
            'filters' => ['rw-signin' => ['before' => ['login']]],
        ];
    }
}
