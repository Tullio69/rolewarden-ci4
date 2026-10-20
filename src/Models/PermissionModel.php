<?php

declare(strict_types=1);

namespace RoleWarden\Models;

use CodeIgniter\Model;
use InvalidArgumentException;

/**
 * Permissions with the system-record protection and cache invalidation.
 * Renaming or deleting one changes the answers of every role and every user
 * (through a direct override) holding it, so they are collected before the write.
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
    protected $afterFind = ['showSeeded'];

    /** @var list<int> */
    private array $holders = [];

    /** @var list<int> users holding the permission through a direct override */
    private array $overrideHolders = [];

    public function __construct()
    {
        $this->table = config('RoleWarden')->table('permissions');
        parent::__construct();
    }

    /**
     * Seeded descriptions come back in the reader's language.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function showSeeded(array $data): array
    {
        if (is_array($data['data'] ?? null)) {
            $data['data'] = ($data['singleton'] ?? false) ? SeededText::permission($data['data']) : array_map(SeededText::permission(...), $data['data']);
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

        $overrides = $this->db->table(config('RoleWarden')->table('user_permissions'))->select('user_id')->whereIn('permission_id', $ids)->get()->getResultArray();
        $this->overrideHolders = array_map('intval', array_column($overrides, 'user_id'));

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
        service('rolewarden')->forgetUsers(array_values(array_unique($this->overrideHolders)));
        $this->holders = [];
        $this->overrideHolders = [];

        return $data;
    }

    /**
     * Ids of the users who hold the permission, in id order, as the resolver
     * answers. Anyone with a role (a grant, an inherited grant or a super admin
     * role) or a positive override for it is asked; deleted users are left out.
     *
     * @return list<int>
     */
    public function holderIds(string $slug): array
    {
        $cfg = config('RoleWarden');
        $withRole = $this->db->table($cfg->table('user_roles'))->distinct()->select('user_id')->get()->getResultArray();
        $withOverride = $this->db->table($cfg->table('user_permissions') . ' up')
            ->select('up.user_id')
            ->join($cfg->table('permissions') . ' p', 'p.id = up.permission_id')
            ->where('p.slug', $slug)->where('up.granted', 1)
            ->get()->getResultArray();
        $ids = array_values(array_unique(array_map('intval', array_column([...$withRole, ...$withOverride], 'user_id'))));
        $ids = $ids === [] ? [] : array_map('intval', model(UserModel::class)->whereIn('id', $ids)->orderBy('id')->findColumn('id') ?? []);
        $resolver = service('rolewarden');

        // ponytail: asks the resolver about every user with a role or an override (cached per user); an index of grants if very large user bases make this slow.
        return array_values(array_filter($ids, static fn (int $id): bool => $resolver->can($id, $slug)));
    }
}
