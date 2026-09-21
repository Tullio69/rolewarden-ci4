<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Unit\Authorization;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoleWarden\Authorization\AuthorizationException;
use RoleWarden\Authorization\Resolver;
use RoleWarden\Tests\_support\ArrayCache;
use RoleWarden\Tests\_support\InMemoryStore;

final class ResolverTest extends TestCase
{
    private InMemoryStore $store;
    private ArrayCache $cache;
    private Resolver $resolver;

    protected function setUp(): void
    {
        $this->store = new InMemoryStore();
        $this->cache = new ArrayCache();
        $this->resolver = new Resolver($this->store, $this->cache);
    }

    /**
     * @param list<string> $permissions
     */
    private function role(int $id, array $permissions = [], ?int $parent = null, bool $super = false): void
    {
        $this->store->roles[$id] = ['parentId' => $parent, 'isSuperAdmin' => $super, 'permissions' => $permissions];
    }

    /**
     * @param list<int>            $roles
     * @param array<string, bool> $overrides
     */
    private function user(int $id, array $roles = [], array $overrides = [], bool $active = true): void
    {
        $this->store->active[$id] = $active;
        $this->store->userRoles[$id] = $roles;
        $this->store->overrides[$id] = $overrides;
    }

    public function testInactiveUserIsDeniedEvenWithOverrideAndSuperAdmin(): void
    {
        $this->role(1, [], null, true);
        $this->user(1, [1], ['users.view' => true], false);
        $this->assertFalse($this->resolver->can(1, 'users.view'));
    }

    public function testNegativeOverrideBeatsSuperAdminAndRole(): void
    {
        $this->role(1, ['users.delete'], null, true);
        $this->user(1, [1], ['users.delete' => false]);
        $this->assertFalse($this->resolver->can(1, 'users.delete'));
        $this->assertTrue($this->resolver->can(1, 'users.view'));
    }

    public function testPositiveOverrideGrantsWithoutRole(): void
    {
        $this->user(1, [], ['users.view' => true]);
        $this->assertTrue($this->resolver->can(1, 'users.view'));
    }

    public function testSuperAdminGrantsWithoutPermission(): void
    {
        $this->role(1, [], null, true);
        $this->user(1, [1]);
        $this->assertTrue($this->resolver->can(1, 'anything.goes'));
    }

    public function testPermissionComesFromRoleParentAndGrandparent(): void
    {
        $this->role(1, ['a.one']);
        $this->role(2, ['b.two'], 1);
        $this->role(3, ['c.three'], 2);
        $this->user(1, [3]);

        foreach (['a.one', 'b.two', 'c.three'] as $slug) {
            $this->assertTrue($this->resolver->can(1, $slug), $slug);
        }
        $this->assertFalse($this->resolver->can(1, 'd.four'));
    }

    public function testChildCannotRevokeWhatParentGrants(): void
    {
        $this->role(1, ['a.one']);
        $this->role(2, [], 1);
        $this->user(1, [2]);
        $this->assertTrue($this->resolver->can(1, 'a.one'));
    }

    public function testDeletedRoleAndDeletedParentGrantNothing(): void
    {
        $this->role(2, ['b.two'], 1); // role 1 is missing: soft deleted
        $this->user(1, [2, 9]);       // role 9 is missing: soft deleted
        $this->assertTrue($this->resolver->can(1, 'b.two'));
        $this->assertFalse($this->resolver->can(1, 'a.one'));
    }

    public function testSuperAdminFlagIsNotInheritedByChildren(): void
    {
        $this->role(1, [], null, true);
        $this->role(2, [], 1);
        $this->user(1, [2]);
        $this->user(2, [2, 1]); // parent also assigned directly: still super admin
        $this->assertFalse($this->resolver->can(1, 'a.one'));
        $this->assertTrue($this->resolver->can(2, 'a.one'));
    }

    public function testCycleInDataTerminates(): void
    {
        $this->role(1, ['a.one'], 2);
        $this->role(2, ['b.two'], 1);
        $this->user(1, [1]);
        $this->assertTrue($this->resolver->can(1, 'b.two'));
        $this->assertFalse($this->resolver->can(1, 'c.three'));
    }

    #[DataProvider('badSlugs')]
    public function testMalformedSlugThrows(string $slug): void
    {
        $this->user(1);
        $this->expectException(InvalidArgumentException::class);
        $this->resolver->can(1, $slug);
    }

    /**
     * @return list<list<string>>
     */
    public static function badSlugs(): array
    {
        return [[''], ['users'], ['Users.View'], ['users.*'], ['*'], ['users.view.all'], ['users. view']];
    }

    public function testCacheAvoidsSecondReadAndForgetUserAppliesRevocation(): void
    {
        $this->role(1, ['a.one']);
        $this->user(1, [1]);
        $this->assertTrue($this->resolver->can(1, 'a.one'));
        $this->assertTrue($this->resolver->can(1, 'a.one'));
        $this->assertSame(1, $this->store->reads);

        $this->store->roles[1]['permissions'] = [];
        $this->assertTrue($this->resolver->can(1, 'a.one'), 'stale until invalidated');
        $this->resolver->forgetUser(1);
        $this->assertFalse($this->resolver->can(1, 'a.one'));
    }

    public function testForgetRoleReachesDescendantsAndSparesOthers(): void
    {
        $this->role(1, ['a.one']);
        $this->role(2, [], 1);
        $this->role(3, ['z.zed']);
        $this->user(1, [2]);
        $this->user(2, [1]);
        $this->user(3, [3]);

        foreach ([1, 2, 3] as $id) {
            $this->resolver->permissions($id);
        }
        $this->store->roles[1]['permissions'] = [];
        $this->resolver->forgetRole(1);

        $this->assertFalse($this->resolver->can(1, 'a.one'));
        $this->assertFalse($this->resolver->can(2, 'a.one'));
        $this->assertNotNull($this->cache->get('rolewarden.user.3'));
    }

    public function testAnyAllAndAuthorize(): void
    {
        $this->role(1, ['a.one']);
        $this->user(1, [1]);
        $this->assertTrue($this->resolver->canAny(1, ['x.y', 'a.one']));
        $this->assertFalse($this->resolver->canAny(1, []));
        $this->assertTrue($this->resolver->canAll(1, ['a.one']));
        $this->assertFalse($this->resolver->canAll(1, ['a.one', 'x.y']));
        $this->assertTrue($this->resolver->canAll(1, []));
        $this->resolver->authorize(1, 'a.one');

        $this->expectException(AuthorizationException::class);
        $this->resolver->authorize(1, 'x.y');
    }

    public function testUserWithoutRolesAndUnknownUserAreDenied(): void
    {
        $this->user(1);
        $this->assertFalse($this->resolver->can(1, 'a.one'));
        $this->assertFalse($this->resolver->can(99, 'a.one'));
    }

    public function testPermissionsListsEffectiveGrants(): void
    {
        $this->role(1, ['a.one', 'b.two']);
        $this->user(1, [1], ['b.two' => false, 'c.three' => true]);
        $this->assertSame(['a.one', 'c.three'], $this->resolver->permissions(1));
    }
}
