<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Integration;

use RoleWarden\Install\HostScan;
use RoleWarden\Tests\_support\DatabaseTestCase;

final class HostScanTest extends DatabaseTestCase
{
    public function testMemberCountsPerGroup(): void
    {
        $a = $this->makeUser('a@example.test');
        $b = $this->makeUser('b@example.test');
        $now = date('Y-m-d H:i:s');

        foreach ([[$a, 'cliente'], [$b, 'cliente'], [$b, 'operatore']] as [$id, $group]) {
            $this->db->table('auth_groups_users')->insert(['user_id' => $id, 'group' => $group, 'created_at' => $now]);
        }

        $this->assertSame(['cliente' => 2, 'operatore' => 1], HostScan::memberCounts());
    }

    public function testMemberCountsEmptyWithoutTable(): void
    {
        $this->db->query('DROP TABLE auth_groups_users');

        $this->assertSame([], HostScan::memberCounts());
    }

    public function testActiveUsersExcludesInactiveBannedAndDeleted(): void
    {
        $ok = $this->makeUser('ok@example.test');
        $inactive = $this->makeUser('off@example.test');
        $banned = $this->makeUser('ban@example.test');
        $deleted = $this->makeUser('del@example.test');
        $this->db->table('users')->where('id', $inactive)->update(['active' => 0]);
        $this->db->table('users')->where('id', $banned)->update(['status' => 'banned']);
        $this->db->table('users')->where('id', $deleted)->update(['deleted_at' => date('Y-m-d H:i:s')]);

        $users = HostScan::activeUsers();

        $this->assertSame([$ok], array_column($users, 'id'));
        $this->assertSame('ok@example.test', $users[0]['email']);
    }
}
