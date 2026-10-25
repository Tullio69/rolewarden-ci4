<?php

declare(strict_types=1);

namespace RoleWardenDemo\Config;

use RoleWardenDemo\Dashboard\DocumentsBlock;

/**
 * Found by CodeIgniter's auto-discovery: adds the demo's block to the
 * dashboard without a line in the host's app/Config/RoleWarden.php. An
 * application of yours lists its blocks in $dashboardBlocks instead.
 */
class Registrar
{
    /** @return array{dashboardBlocks: list<class-string>} */
    public static function RoleWarden(): array
    {
        return ['dashboardBlocks' => [DocumentsBlock::class]];
    }
}
