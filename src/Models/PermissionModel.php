<?php

declare(strict_types=1);

namespace RoleWarden\Models;

use CodeIgniter\Model;
use InvalidArgumentException;

/**
 * Permissions with the system-record protection and cache invalidation.
 * Deleting one revokes it from every role holding it, so those roles are
 * collected before the row (and its role links) disappear.
 */
class PermissionModel extends Model
{
    protected $primaryKey = 'id';
    protected $allowedFields = ['slug', 'area', 'description', 'is_system'];
    protected $useTimestamps = true;
    protected $beforeUpdate = ['collectHolders'];
    protected $beforeDelete = ['guardDelete'];
    protected $afterUpdate = ['forgetHolders'];
    protected $afterDelete = ['forgetHolders'];

    /** @var list<int> */
    private array $holders = [];

    public function __construct()
    {
        $this->table = config('RoleWarden')->table('permissions');
        parent::__construct();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function guardDelete(array $data): array
    {
        $ids = array_map('intval', (array) ($data['id'] ?? []));

        if ($ids === []) {
            throw new InvalidArgumentException('Permissions must be deleted by primary key.');
        }

        $slugs = $this->db->table($this->table)->select('slug')->whereIn('id', $ids)->get()->getResultArray();

        foreach ($slugs as $row) {
            service('rolewardenGuard')->assertPermissionDeletable($row['slug']);
        }

        return $this->collectHolders($data);
    }

    /**
     * Renaming or deleting a permission changes what its holders can do, so
     * remember them before the write and forget them after.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function collectHolders(array $data): array
    {
        $ids = array_map('intval', (array) ($data['id'] ?? []));

        if ($ids === []) {
            throw new InvalidArgumentException('Permissions must be written by primary key.');
        }

        $links = $this->db->table(config('RoleWarden')->table('role_permissions'))->select('role_id')->whereIn('permission_id', $ids)->get()->getResultArray();
        $this->holders = array_map('intval', array_column($links, 'role_id'));

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function forgetHolders(array $data): array
    {
        foreach (array_unique($this->holders) as $roleId) {
            service('rolewarden')->forgetRole($roleId);
        }
        $this->holders = [];

        return $data;
    }
}
