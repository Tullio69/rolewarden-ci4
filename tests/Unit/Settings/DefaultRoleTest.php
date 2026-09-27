<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Unit\Settings;

use PHPUnit\Framework\TestCase;
use RoleWarden\Settings\DefaultRole;

final class DefaultRoleTest extends TestCase
{
    public function testAnOrdinaryRoleCanBeTheDefault(): void
    {
        $this->assertNull(DefaultRole::reject(['id' => 4, 'slug' => 'viewer', 'is_super_admin' => 0]));
        $this->assertNull(DefaultRole::reject(['id' => 4, 'slug' => 'viewer', 'is_super_admin' => '0']));
    }

    public function testAMissingRoleIsRejected(): void
    {
        $this->assertSame('defaultRoleMissing', DefaultRole::reject(null));
    }

    public function testASuperAdminRoleIsRejectedWhateverTheDatabaseReturnsForTheFlag(): void
    {
        $this->assertSame('defaultRoleSuperAdmin', DefaultRole::reject(['id' => 1, 'slug' => 'super-admin', 'is_super_admin' => 1]));
        $this->assertSame('defaultRoleSuperAdmin', DefaultRole::reject(['id' => 1, 'slug' => 'super-admin', 'is_super_admin' => '1']));
    }
}
