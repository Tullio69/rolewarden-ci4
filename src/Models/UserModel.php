<?php

declare(strict_types=1);

namespace RoleWarden\Models;

use CodeIgniter\Shield\Models\UserModel as ShieldUserModel;
use RoleWarden\Entities\User;

/**
 * Set as Config\Auth::$userProvider in the host application.
 */
class UserModel extends ShieldUserModel
{
    protected $returnType = User::class;
}
