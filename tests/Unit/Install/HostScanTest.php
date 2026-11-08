<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Unit\Install;

use PHPUnit\Framework\TestCase;
use RoleWarden\Install\GroupImport;
use RoleWarden\Install\HostScan;

final class HostScanTest extends TestCase
{
    /** Mirrors the helpdesk's app/Config/AuthGroups.php. */
    private function helpdesk(): object
    {
        return new class () {
            public string $defaultGroup = 'cliente';
            public array $groups = [
                'admin' => ['title' => 'Admin'],
                'operatore' => ['title' => 'Operatore'],
                'cliente' => [],
            ];
            public array $permissions = [
                'tickets.create' => 'a', 'tickets.view-own' => 'a', 'tickets.view-assigned' => 'a',
                'tickets.view-unassigned' => 'a', 'tickets.view-all' => 'a', 'tickets.reply' => 'a',
                'tickets.internal-notes' => 'a', 'tickets.change-status' => 'a', 'tickets.change-priority' => 'a',
                'tickets.take' => 'a', 'tickets.assign' => 'a', 'tickets.close-own' => 'a',
                'admin.categories' => 'a', 'admin.users' => 'a',
                'Bad Slug' => 'ignored',
            ];
            public array $matrix = [
                'admin' => ['tickets.*', 'admin.*'],
                'operatore' => ['tickets.create', 'tickets.reply', 'nope.missing'],
                'cliente' => ['tickets.create'],
            ];
        };
    }

    public function testGroupsExpandWildcardsAndCountMembers(): void
    {
        $groups = HostScan::groups($this->helpdesk(), ['admin' => 2, 'cliente' => 7]);

        $this->assertSame(['admin', 'operatore', 'cliente'], array_column($groups, 'slug'));
        $this->assertSame(['slug' => 'admin', 'title' => 'Admin', 'users' => 2, 'permissions' => 14], $groups[0]);
        $this->assertSame(2, $groups[1]['permissions']);
        $this->assertSame(0, $groups[1]['users']);
        $this->assertSame('cliente', $groups[2]['title']);
    }

    public function testCollisionsWithSystemSlugs(): void
    {
        $groups = HostScan::groups($this->helpdesk(), []);

        $this->assertSame(['admin'], HostScan::collisions($groups, ['super-admin', 'admin', 'user']));
    }

    public function testSuggestedMode(): void
    {
        $this->assertSame('adapt', HostScan::suggestedMode(HostScan::groups($this->helpdesk(), [])));
        $stock = [['slug' => 'user', 'title' => 'User', 'users' => 0, 'permissions' => 0], ['slug' => 'superadmin', 'title' => 'S', 'users' => 0, 'permissions' => 0]];
        $this->assertSame('import', HostScan::suggestedMode($stock));
        $this->assertSame('import', HostScan::suggestedMode([]));
        $all = array_map(static fn (string $s): array => ['slug' => $s], ['superadmin', 'admin', 'developer', 'user', 'beta']);
        $this->assertSame('import', HostScan::suggestedMode($all));
    }

    public function testDefaultGroup(): void
    {
        $this->assertSame('cliente', HostScan::defaultGroup($this->helpdesk()));
        $this->assertNull(HostScan::defaultGroup(new \stdClass()));
    }

    public function testExpandIsPureOnSlugs(): void
    {
        $this->assertSame(['a.x', 'b.y'], GroupImport::expand(['a.*', 'b.y', 'b.y', 'c.z'], ['a.x', 'b.y', 'b.z']));
    }
}
