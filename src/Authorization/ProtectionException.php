<?php

declare(strict_types=1);

namespace RoleWarden\Authorization;

use RuntimeException;

/**
 * A write was refused because it would break an invariant. The message key is
 * stable so the framework layer can translate it.
 */
class ProtectionException extends RuntimeException
{
    public const LAST_SUPER_ADMIN = 'lastSuperAdmin';
    public const HIERARCHY_CYCLE = 'hierarchyCycle';
    public const PARENT_MISSING = 'parentMissing';
    public const SYSTEM_RECORD = 'systemRecord';
    public const ROLE_HAS_CHILDREN = 'roleHasChildren';
    public const LAST_ADMIN_ROLE = 'lastAdminRole';

    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
