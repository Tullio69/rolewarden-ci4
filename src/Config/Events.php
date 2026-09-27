<?php

declare(strict_types=1);

namespace RoleWarden\Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Shield\Entities\User;
use RoleWarden\Settings\DefaultRole;

// CodeIgniter includes Config/Events.php from every module namespace (Config\Modules
// discovers 'events' by default), so the host app has nothing to wire.

// A user who registers through Shield gets the default role from the Settings screen.
Events::on('register', static function (User $user): void {
    DefaultRole::assignTo((int) $user->id);
});
