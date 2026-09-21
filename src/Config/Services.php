<?php

declare(strict_types=1);

namespace RoleWarden\Config;

use CodeIgniter\Config\BaseService;
use RoleWarden\Adapters\CiCache;
use RoleWarden\Adapters\DatabaseStore;
use RoleWarden\Authorization\Guard;
use RoleWarden\Authorization\Resolver;

class Services extends BaseService
{
    public static function rolewarden(bool $getShared = true): Resolver
    {
        if ($getShared) {
            return static::getSharedInstance('rolewarden');
        }

        return new Resolver(new DatabaseStore(), new CiCache(service('cache')));
    }

    public static function rolewardenGuard(bool $getShared = true): Guard
    {
        if ($getShared) {
            return static::getSharedInstance('rolewardenGuard');
        }

        $store = new DatabaseStore();

        return new Guard($store, $store);
    }
}
