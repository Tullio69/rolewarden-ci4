<?php

declare(strict_types=1);

namespace RoleWarden\Adapters;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RoleWarden\Authorization\Contracts\AuthorizationStore;
use RoleWarden\Config\RoleWarden;

/**
 * CI4 database implementation of the store contract. Queries are built with
 * the query builder, so every value is bound. Soft-deleted roles are hidden.
 */
class DatabaseStore implements AuthorizationStore
{
    private readonly BaseConnection $db;

    public function __construct(private readonly RoleWarden $config = new RoleWarden(), ?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function isActive(int $userId): bool
    {
        $row = $this->db->table('users')->select('active')->where('id', $userId)->get()->getRowArray();

        return $row !== null && (int) $row['active'] === 1;
    }

    public function userOverrides(int $userId): array
    {
        $rows = $this->db->table($this->t('user_permissions') . ' up')
            ->select('p.slug, up.granted')
            ->join($this->t('permissions') . ' p', 'p.id = up.permission_id')
            ->where('up.user_id', $userId)
            ->get()->getResultArray();

        $out = [];

        foreach ($rows as $row) {
            $out[$row['slug']] = (int) $row['granted'] === 1;
        }

        return $out;
    }

    public function userRoleIds(int $userId): array
    {
        return $this->ids($this->db->table($this->t('user_roles') . ' ur')
            ->select('r.id')
            ->join($this->t('roles') . ' r', 'r.id = ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('r.deleted_at', null)
            ->get()->getResultArray());
    }

    public function role(int $roleId): ?array
    {
        $role = $this->db->table($this->t('roles'))->where('id', $roleId)->where('deleted_at', null)->get()->getRowArray();

        if ($role === null) {
            return null;
        }

        $slugs = $this->db->table($this->t('role_permissions') . ' rp')
            ->select('p.slug')
            ->join($this->t('permissions') . ' p', 'p.id = rp.permission_id')
            ->where('rp.role_id', $roleId)
            ->get()->getResultArray();

        return [
            'parentId' => $role['parent_id'] === null ? null : (int) $role['parent_id'],
            'isSuperAdmin' => (int) $role['is_super_admin'] === 1,
            'permissions' => array_column($slugs, 'slug'),
        ];
    }

    public function childRoleIds(int $roleId): array
    {
        return $this->ids($this->db->table($this->t('roles'))->select('id')
            ->where('parent_id', $roleId)->where('deleted_at', null)->get()->getResultArray());
    }

    public function userIdsWithRole(int $roleId): array
    {
        return $this->ids($this->db->table($this->t('user_roles'))->select('user_id AS id')
            ->where('role_id', $roleId)->get()->getResultArray());
    }

    /**
     * Slugs of the roles directly assigned to the user, for Shield group compatibility.
     *
     * @return list<string>
     */
    public function userRoleSlugs(int $userId): array
    {
        return array_column($this->db->table($this->t('user_roles') . ' ur')
            ->select('r.slug')
            ->join($this->t('roles') . ' r', 'r.id = ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('r.deleted_at', null)
            ->get()->getResultArray(), 'slug');
    }

    private function t(string $name): string
    {
        return $this->config->table($name);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<int>
     */
    private function ids(array $rows): array
    {
        return array_map(static fn (array $r): int => (int) $r['id'], $rows);
    }
}
