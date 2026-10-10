<?php

declare(strict_types=1);

namespace RoleWardenDemo\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * The demo app's options. Each one can be set in .env with the "demo."
 * prefix (demo.showcase = true, demo.password = ...).
 */
class Demo extends BaseConfig
{
    /**
     * Showcase mode: a bar that switches the session between the demo
     * accounts without a password. Ignored when ENVIRONMENT is production.
     */
    public bool $showcase = false;

    /**
     * Password of the demo accounts created by demo:install. Empty: one is
     * generated and printed once.
     */
    public string $password = '';

    public string $table = 'demo_documents';

    /**
     * Ids of the demo accounts the showcase bar may switch to, written by
     * demo:install through the Settings library. Never set by hand.
     *
     * @var list<int>
     */
    public array $showcaseUsers = [];
}
