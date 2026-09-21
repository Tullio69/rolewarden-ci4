<?php

declare(strict_types=1);

namespace RoleWarden\Models;

use CodeIgniter\Model;
use InvalidArgumentException;

/**
 * Roles with the write-side protections and cache invalidation attached.
 * Write by primary key: a protection cannot vet an unbounded where().
 */
class RoleModel extends Model
{
    protected $primaryKey = 'id';
    protected $allowedFields = ['slug', 'name', 'description', 'parent_id', 'is_system', 'is_super_admin'];
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $beforeInsert = ['guardParent'];
    protected $beforeUpdate = ['guardUpdate'];
    protected $beforeDelete = ['guardDelete'];
    protected $afterUpdate = ['forgetRoles'];
    protected $afterDelete = ['forgetRoles'];

    public function __construct()
    {
        $this->table = config('RoleWarden')->table('roles');
        parent::__construct();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function guardParent(array $data): array
    {
        if (($data['data']['parent_id'] ?? null) !== null) {
            service('rolewardenGuard')->assertParent(0, (int) $data['data']['parent_id']);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function guardUpdate(array $data): array
    {
        foreach ($this->ids($data) as $id) {
            if (array_key_exists('parent_id', $data['data'])) {
                service('rolewardenGuard')->assertParent($id, $data['data']['parent_id'] === null ? null : (int) $data['data']['parent_id']);
            }

            if (array_key_exists('is_super_admin', $data['data'])) {
                service('rolewardenGuard')->assertSuperAdminFlag($id, (bool) $data['data']['is_super_admin']);
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function guardDelete(array $data): array
    {
        foreach ($this->ids($data) as $id) {
            service('rolewardenGuard')->assertRoleDeletable($id);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function forgetRoles(array $data): array
    {
        foreach ($this->ids($data) as $id) {
            service('rolewarden')->forgetRole($id);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<int>
     */
    private function ids(array $data): array
    {
        $ids = $data['id'] ?? null;

        if ($ids === null || $ids === []) {
            throw new InvalidArgumentException('Roles must be written by primary key.');
        }

        return array_map('intval', (array) $ids);
    }
}
