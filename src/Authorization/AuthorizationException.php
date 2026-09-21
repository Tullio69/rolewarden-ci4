<?php

declare(strict_types=1);

namespace RoleWarden\Authorization;

use RuntimeException;

class AuthorizationException extends RuntimeException
{
    public static function denied(string $permission): self
    {
        return new self(sprintf('Access denied: the "%s" permission is required. Ask an administrator to grant it.', $permission));
    }
}
