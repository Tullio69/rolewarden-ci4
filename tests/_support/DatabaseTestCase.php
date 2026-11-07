<?php

declare(strict_types=1);

namespace RoleWarden\Tests\_support;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RoleWarden\Models\UserModel;

/**
 * Base for tests that need the real schema: runs every migration (Settings, Shield, RoleWarden)
 * on the `rolewarden_test` MariaDB of docker compose, refreshed before every test.
 */
abstract class DatabaseTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = null;

    /** Active Shield user with an email identity; returns the id. */
    protected function makeUser(string $email): int
    {
        $users = new UserModel();
        $user  = $users->createNewUser(['email' => $email, 'password' => 'Str0ng-Passw0rd-for-tests', 'active' => 1]);

        return (int) $users->insert($user, true);
    }

    protected function roleId(string $slug): ?int
    {
        $row = $this->db->table(config('RoleWarden')->table('roles'))->where('slug', $slug)->get()->getRow();

        return $row === null ? null : (int) $row->id;
    }

    /** @return list<string> */
    protected function rolesOf(int $userId): array
    {
        $cfg  = config('RoleWarden');
        $rows = $this->db->table($cfg->table('user_roles') . ' ur')
            ->select('r.slug')
            ->join($cfg->table('roles') . ' r', 'r.id = ur.role_id')
            ->where('ur.user_id', $userId)
            ->orderBy('r.slug')
            ->get()->getResultArray();

        return array_column($rows, 'slug');
    }
}
