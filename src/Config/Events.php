<?php

declare(strict_types=1);

namespace RoleWarden\Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Shield\Entities\User;
use RoleWarden\Account\Sessions;
use RoleWarden\Settings\DefaultRole;

// CodeIgniter includes Config/Events.php from every module namespace (Config\Modules
// discovers 'events' by default), so the host app has nothing to wire.

// A user who registers through Shield gets the default role from the Settings screen.
Events::on('register', static function (User $user): void {
    DefaultRole::assignTo((int) $user->id);
});

// Signed-in sessions (RoleWarden\Account\Sessions): pre_system runs before routing and
// filters, so a revoked or idle session is signed out before anything checks it.
Events::on('pre_system', static function (): void {
    Sessions::track();
});

Events::on('post_system', static function (): void {
    Sessions::registerNew();
});

Events::on('logout', static function (): void {
    Sessions::forgetCurrent();
});
