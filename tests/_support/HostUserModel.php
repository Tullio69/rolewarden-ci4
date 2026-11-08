<?php

declare(strict_types=1);

namespace RoleWarden\Tests\_support;

use RoleWarden\Models\UserModel;

/** A host's own user model that already extends RoleWarden's, as the wizard asks (R18). */
class HostUserModel extends UserModel
{
}
