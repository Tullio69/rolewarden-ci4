<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Integration;

use RoleWarden\Tests\_support\DatabaseTestCase;

/**
 * @internal
 */
final class DatabaseSmokeTest extends DatabaseTestCase
{
    public function testMigrationsCreateTheSystemRoles(): void
    {
        $this->assertNotNull($this->roleId('super-admin'));
        $this->assertNotNull($this->roleId('admin'));
        $this->assertNotNull($this->roleId('user'));
    }

    public function testMakeUserCreatesAnActiveUser(): void
    {
        $id = $this->makeUser('smoke@example.test');

        $this->assertGreaterThan(0, $id);
        $this->seeInDatabase('users', ['id' => $id, 'active' => 1]);
        $this->assertIsArray($this->rolesOf($id));
    }
}
