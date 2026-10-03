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
     * Slug of the role given to every new user (registered through Shield or
     * created from the panel); null for none. This is only the starting value:
     * the Settings screen of the panel changes it at runtime.
     */
    public ?string $defaultRole = null;

    /**
     * Sign-in rules, starting values of the Settings screen (which changes them
     * at runtime). Seconds a signed-in session may stay idle before it ends.
     */
    public int $sessionLifetime = 7200;

    /**
     * Failed sign-ins for one email, since its last successful one, that lock
     * it for $lockMinutes. Counted from Shield's auth_logins, so Shield must
     * record failures (Config\Auth::$recordLoginAttempt, on by default).
     */
    public int $lockAttempts = 5;

    public int $lockMinutes = 15;

    /**
     * Sign-in form posts accepted per minute from one IP address.
     */
    public int $signInRate = 10;

    /**
     * Days the activity log keeps its entries; 0 keeps them forever. Starting
     * value of the Settings screen. Shield's own auth_logins is not pruned.
     */
    public int $activityRetentionDays = 365;

    /**
     * Light or dark for a user who has not chosen: 'system' follows the
     * browser. Each user changes it from the top bar (stored per user).
     */
    public string $colorScheme = 'system';

    /**
     * Themes of the host application, offered by the Appearance screen next to
     * Console, Clarity and Contrast: slug => label, e.g. ['ocean' => 'Ocean'].
     * Each one needs a :root[data-rw-theme="ocean"] block in $themeStylesheet
     * (see the design guide, "A fourth theme"). Slugs: lowercase, digits, dashes.
     *
     * @var array<string, string>
     */
    public array $extraThemes = [];

    /**
     * URL of a stylesheet of the host application loaded after the panel's own,
     * for extra themes and for components the host adds; null for none.
     * Example: base_url('css/rolewarden-theme.css').
     */
    public ?string $themeStylesheet = null;

    /**
     * Full name of a module table, e.g. table('roles') => 'acl_roles'.
     */
    public function table(string $name): string
    {
        return $this->tablePrefix . $name;
    }
}
