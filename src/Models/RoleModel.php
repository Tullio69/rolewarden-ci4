<?php

declare(strict_types=1);

namespace RoleWarden\Models;

use CodeIgniter\Model;
use InvalidArgumentException;
use RoleWarden\Authorization\ProtectionException;

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
    protected $beforeUpdate = ['guardUpdate', 'keepSeeded'];
    protected $beforeDelete = ['guardDelete'];
    protected $afterUpdate = ['forgetRoles'];
    protected $afterDelete = ['forgetRoles'];
    protected $afterFind = ['showSeeded'];

    public function __construct()
    {
        $this->table = config('RoleWarden')->table('roles');
        parent::__construct();
    }

    /**
     * Declared so callers may catch what the beforeUpdate guard throws.
     *
     * @throws ProtectionException
     */
    public function update($id = null, $row = null): bool
    {
        return parent::update($id, $row);
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
     * Seeded names and descriptions come back in the reader's language.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function showSeeded(array $data): array
    {
        if (is_array($data['data'] ?? null)) {
            $data['data'] = ($data['singleton'] ?? false) ? SeededText::role($data['data']) : array_map(SeededText::role(...), $data['data']);
        }

        return $data;
    }

    /**
     * A form saved with the translated text unchanged keeps the stored one, so
     * the role is not renamed into the language of whoever saved it.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function keepSeeded(array $data): array
    {
        foreach ($this->ids($data) as $id) {
            $stored = $this->db->table($this->table)->select('slug, name, description')->where('id', $id)->get()->getRowArray();

            foreach ($stored === null ? [] : ['name', 'description'] as $field) {
                if (isset($data['data'][$field]) && $data['data'][$field] === SeededText::show("roles.{$stored['slug']}.{$field}", $stored[$field])) {
                    $data['data'][$field] = $stored[$field];
                }
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
