<?php

declare(strict_types=1);

namespace RoleWarden\Install;

use RuntimeException;

/** A host file was left with invalid content and could not be written back: restore it from the backup by hand. */
final class RestoreFailedException extends RuntimeException
{
    public function __construct(public readonly string $path, public readonly string $backup)
    {
        parent::__construct('Could not restore ' . $path . '; the original is in ' . $backup);
    }
}
