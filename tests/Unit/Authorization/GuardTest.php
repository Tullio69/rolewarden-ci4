<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Unit\Authorization;

use PHPUnit\Framework\TestCase;
use RoleWarden\Authorization\Guard;
use RoleWarden\Authorization\ProtectionException;
use RoleWarden\Tests\_support\InMemoryProtection;
use RoleWarden\Tests\_support\InMemoryStore;

final class GuardTest extends TestCase
{
    private InMemoryStore $store;
    private InMemoryProtection $protection;
    private Guard $guard;

    protected function setUp(): void
    {
        $this->store = new InMemoryStore();
        $this->protection = new InMemoryProtection($this->store);
        $this->guard = new Guard($this->store, $this->protection);
    }

    private function role(int $id, ?int $parent = null, bool $super = false): void
    {
        $this->store->roles[$id] = ['parentId' => $parent, 'isSuperAdmin' => $super, 'permissions' => []];
    }

    /**
     * @param list<int> $roles
     */
    private function user(int $id, array $roles, bool $active = true): void
    {
        $this->store->userRoles[$id] = $roles;
        $this->store->active[$id] = $active;
    }

    private function refused(callable $call): void
    {
        $this->expectException(ProtectionException::class);
        $call();
    }

    public function testParentCycles(): void
    {
        $this->role(1);
        $this->role(2, 1);
        $this->role(3, 2);
        $this->guard->assertParent(3, 1);
        $this->guard->assertParent(1, null);
        $this->guard->assertParent(2, null);

        foreach ([[1, 1], [1, 2], [1, 3]] as [$role, $parent]) {
            try {
                $this->guard->assertParent($role, $parent);
                $this->fail("cycle $role -> $parent accepted");
            } catch (ProtectionException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testParentMustExist(): void
    {
        $this->role(1);
        $this->refused(fn () => $this->guard->assertParent(1, 99));
    }

    public function testExistingCycleInDataDoesNotLoopForever(): void
    {
        $this->role(1, 2);
        $this->role(2, 1);
        $this->role(3);
        // Attaching to a chain that already loops adds no new cycle through role 3, and must terminate.
        $this->guard->assertParent(3, 1);
        $this->addToAssertionCount(1);
    }

    public function testSystemRoleCannotBeDeleted(): void
    {
        $this->role(1);
        $this->protection->systemRoles = [1];
        $this->refused(fn () => $this->guard->assertRoleDeletable(1));
    }

    public function testOrdinaryRoleCanBeDeleted(): void
    {
        $this->role(1);
        $this->guard->assertRoleDeletable(1);
        $this->addToAssertionCount(1);
    }

    public function testRoleWithActiveChildrenCannotBeDeleted(): void
    {
        $this->role(1);
        $this->role(2, 1);
        try {
            $this->guard->assertRoleDeletable(1);
            $this->fail('parent with a child was deletable');
        } catch (ProtectionException $e) {
            $this->assertSame(ProtectionException::ROLE_HAS_CHILDREN, $e->reason);
        }

        $this->guard->assertRoleDeletable(2);
        $this->addToAssertionCount(1);
    }

    public function testSystemPermissionCannotBeDeleted(): void
    {
        $this->protection->systemPermissions = ['users.view'];
        $this->guard->assertPermissionDeletable('users.create');
        $this->refused(fn () => $this->guard->assertPermissionDeletable('users.view'));
    }

    public function testLastSuperAdminCannotBeDeletedOrDeactivated(): void
    {
        $this->role(1, null, true);
        $this->user(10, [1]);
        $this->refused(fn () => $this->guard->assertUserRemovable(10));
    }

    public function testSuperAdminCanGoWhenAnotherActiveOneRemains(): void
    {
        $this->role(1, null, true);
        $this->user(10, [1]);
        $this->user(11, [1]);
        $this->guard->assertUserRemovable(10);
        $this->addToAssertionCount(1);
    }

    public function testInactiveSuperAdminDoesNotCountAsRemaining(): void
    {
        $this->role(1, null, true);
        $this->user(10, [1]);
        $this->user(11, [1], false);
        $this->refused(fn () => $this->guard->assertUserRemovable(10));
    }

    public function testOrdinaryOrInactiveUserIsAlwaysRemovable(): void
    {
        $this->role(1, null, true);
        $this->role(2);
        $this->user(10, [1]);
        $this->user(11, [2]);
        $this->user(12, [1], false);
        $this->guard->assertUserRemovable(11);
        $this->guard->assertUserRemovable(12);
        $this->addToAssertionCount(2);
    }

    public function testUnassigningTheLastSuperAdminRoleIsRefused(): void
    {
        $this->role(1, null, true);
        $this->user(10, [1]);
        $this->refused(fn () => $this->guard->assertRoleUnassignable(10, 1));
    }

    public function testUnassigningIsFineWhenUserKeepsAnotherSuperRole(): void
    {
        $this->role(1, null, true);
        $this->role(2, null, true);
        $this->user(10, [1, 2]);
        $this->guard->assertRoleUnassignable(10, 1);
        $this->addToAssertionCount(1);
    }

    public function testRemovingTheOnlyRoleThatGrantsThePermissionIsRefused(): void
    {
        $this->role(1);
        $this->role(2, 1);
        $this->store->roles[1]['permissions'] = ['roles.assign'];
        $this->user(10, [2]);
        $this->refused(fn () => $this->guard->assertKeepsPermission(10, 2, 'roles.assign'));
    }

    public function testRemovingARoleIsFineWhenAnotherRoleOrSuperRoleGrantsThePermission(): void
    {
        $this->role(1);
        $this->role(2);
        $this->role(3, 1);
        $this->role(4, null, true);
        $this->store->roles[1]['permissions'] = ['roles.assign'];
        $this->store->roles[2]['permissions'] = ['roles.assign'];
        $this->user(10, [2, 3]);
        $this->user(11, [2, 4]);
        $this->guard->assertKeepsPermission(10, 2, 'roles.assign');
        $this->guard->assertKeepsPermission(11, 2, 'roles.assign');
        $this->addToAssertionCount(2);
    }

    public function testRemovingASuperRoleThatAloneGrantsThePermissionIsRefused(): void
    {
        $this->role(1, null, true);
        $this->user(10, [1]);
        $this->refused(fn () => $this->guard->assertKeepsPermission(10, 1, 'roles.assign'));
    }

    public function testUserOverrideOrRoleWithoutThePermissionAllowsRemoval(): void
    {
        $this->role(1);
        $this->role(2);
        $this->store->roles[1]['permissions'] = ['roles.assign'];
        $this->user(10, [1]);
        $this->user(11, [1]);
        $this->user(12, [2]);
        $this->store->overrides[10] = ['roles.assign' => true];
        $this->store->overrides[11] = ['roles.assign' => false];
        $this->guard->assertKeepsPermission(10, 1, 'roles.assign');
        $this->guard->assertKeepsPermission(11, 1, 'roles.assign');
        $this->guard->assertKeepsPermission(12, 2, 'roles.assign');
        $this->addToAssertionCount(3);
    }

    public function testDeletingTheLastSuperAdminRoleIsRefused(): void
    {
        $this->role(1, null, true);
        $this->user(10, [1]);
        $this->refused(fn () => $this->guard->assertRoleDeletable(1));
    }

    public function testDeletingSuperRoleIsFineWhenHoldersKeepAnotherOne(): void
    {
        $this->role(1, null, true);
        $this->role(2, null, true);
        $this->user(10, [1, 2]);
        $this->guard->assertRoleDeletable(1);
        $this->addToAssertionCount(1);
    }

    public function testClearingTheSuperFlagOfTheLastSuperRoleIsRefused(): void
    {
        $this->role(1, null, true);
        $this->user(10, [1]);
        $this->guard->assertSuperAdminFlag(1, true);
        $this->refused(fn () => $this->guard->assertSuperAdminFlag(1, false));
    }
}
