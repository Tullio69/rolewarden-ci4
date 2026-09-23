<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RoleWarden\Authorization\ProtectionException;
use RoleWarden\Models\PermissionModel;
use RoleWarden\Models\RoleModel;
use RoleWarden\Models\RolePermissions;
use Throwable;

class RolesController extends BaseController
{
    public function index(): string
    {
        $roles = model(RoleModel::class)->orderBy('name')->findAll();
        $namesById = array_column($roles, 'name', 'id');

        $counts = array_column(
            db_connect()->table(config('RoleWarden')->table('user_roles'))
                ->select('role_id, COUNT(*) AS total')->groupBy('role_id')->get()->getResultArray(),
            'total',
            'role_id',
        );

        $holders = [];

        foreach (db_connect()->table(config('RoleWarden')->table('user_roles') . ' ur')
            ->select('ur.role_id, u.username')
            ->join(config('Auth')->tables['users'] . ' u', 'u.id = ur.user_id')
            ->get()->getResultArray() as $row) {
            $holders[(int) $row['role_id']][] = (string) $row['username'];
        }

        $permissionCounts = [];

        foreach (db_connect()->table(config('RoleWarden')->table('role_permissions') . ' rp')
            ->select('rp.role_id, COUNT(*) AS total')->groupBy('rp.role_id')->get()->getResultArray() as $row) {
            $permissionCounts[(int) $row['role_id']] = (int) $row['total'];
        }

        $totalPermissions = db_connect()->table(config('RoleWarden')->table('permissions'))->countAllResults();

        $inheritedCounts = [];

        foreach ($roles as $role) {
            $inheritedCounts[(int) $role['id']] = count($this->inheritedPermissions((int) $role['id']));
        }

        return rw_panel('RoleWarden\Views\roles\index', [
            'roles' => $this->depthFirst($roles),
            'namesById' => $namesById,
            'counts' => $counts,
            'holders' => $holders,
            'permissionCounts' => $permissionCounts,
            'inheritedCounts' => $inheritedCounts,
            'totalPermissions' => $totalPermissions,
        ], 'roles', lang('RoleWarden.panel.roles.indexTitle'));
    }

    public function create(): string
    {
        $roles = model(RoleModel::class)->orderBy('name')->findAll();

        return rw_panel('RoleWarden\Views\roles\form', [
            'role' => null,
            'roles' => $roles,
            'errors' => session()->getFlashdata('rw_errors') ?? [],
        ], 'roles', lang('RoleWarden.panel.roles.createTitle'));
    }

    public function store(): RedirectResponse
    {
        $rules = [
            'slug' => 'required|regex_match[/^[a-z][a-z0-9-]*$/]|is_unique[' . config('RoleWarden')->table('roles') . '.slug]',
            'name' => 'required|min_length[2]',
            'parent_id' => 'permit_empty|is_natural_no_zero',
        ];

        if (! $this->validateData($this->request->getPost() ?? [], $rules)) {
            return redirect()->back()->withInput()->with('rw_errors', $this->validator->getErrors());
        }

        $parentId = $this->request->getPost('parent_id');

        try {
            model(RoleModel::class)->insert([
                'slug' => $this->request->getPost('slug'),
                'name' => $this->request->getPost('name'),
                'description' => $this->request->getPost('description'),
                'parent_id' => $parentId !== '' && $parentId !== null ? (int) $parentId : null,
                'is_super_admin' => $this->request->getPost('is_super_admin') === '1' ? 1 : 0,
            ]);
        } catch (ProtectionException $e) {
            return redirect()->back()->withInput()->with('rw_error', lang('RoleWarden.protection.' . $e->reason));
        }

        return redirect()->to(site_url('rolewarden/roles'))->with('rw_success', lang('RoleWarden.panel.roles.created'));
    }

    public function show(int $id): string
    {
        $matrix = $this->buildMatrix($id);

        if ($matrix === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $name = (string) $matrix['role']['name'];
        $crumbs = '<a href="' . esc(site_url('rolewarden/roles')) . '">' . lang('RoleWarden.panel.nav.roles') . '</a>'
            . '<span aria-hidden="true">/</span><span aria-current="page">' . esc($name) . '</span>';

        $matrix['allRoles'] = model(RoleModel::class)->orderBy('name')->findAll();

        return rw_panel('RoleWarden\Views\roles\show', $matrix, 'roles', $name, $crumbs);
    }

    public function edit(int $id): string
    {
        $roleModel = model(RoleModel::class);
        $role = $roleModel->find($id);

        if ($role === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $roles = $roleModel->where('id !=', $id)->orderBy('name')->findAll();

        return rw_panel('RoleWarden\Views\roles\form', [
            'role' => $role,
            'roles' => $roles,
            'errors' => session()->getFlashdata('rw_errors') ?? [],
        ], 'roles', lang('RoleWarden.panel.roles.editTitle'));
    }

    public function update(int $id): RedirectResponse
    {
        $roleModel = model(RoleModel::class);
        $role = $roleModel->find($id);

        if ($role === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $rules = [
            'name' => 'required|min_length[2]',
            'parent_id' => 'permit_empty|is_natural_no_zero',
        ];

        if (! $this->validateData($this->request->getPost() ?? [], $rules)) {
            return redirect()->back()->withInput()->with('rw_errors', $this->validator->getErrors());
        }

        $parentId = $this->request->getPost('parent_id');

        try {
            $roleModel->update($id, [
                'name' => $this->request->getPost('name'),
                'description' => $this->request->getPost('description'),
                'parent_id' => $parentId !== '' && $parentId !== null ? (int) $parentId : null,
                'is_super_admin' => $this->request->getPost('is_super_admin') === '1' ? 1 : 0,
            ]);
        } catch (ProtectionException $e) {
            return redirect()->back()->withInput()->with('rw_error', lang('RoleWarden.protection.' . $e->reason));
        }

        return redirect()->to(site_url('rolewarden/roles/' . $id))->with('rw_success', lang('RoleWarden.panel.roles.updated'));
    }

    public function delete(int $id): RedirectResponse
    {
        try {
            model(RoleModel::class)->delete($id);
        } catch (ProtectionException $e) {
            return redirect()->to(site_url('rolewarden/roles/' . $id))->with('rw_error', lang('RoleWarden.protection.' . $e->reason));
        }

        return redirect()->to(site_url('rolewarden/roles'))->with('rw_success', lang('RoleWarden.panel.roles.deleted'));
    }

    public function updatePermissions(int $id): ResponseInterface
    {
        $permissionId = (int) $this->request->getPost('permission_id');
        $granted = $this->request->getPost('granted') === '1';

        if (model(RoleModel::class)->find($id) === null || model(PermissionModel::class)->find($permissionId) === null) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => lang('RoleWarden.panel.roles.matrixNotFound')]);
        }

        $inherited = $this->inheritedPermissions($id);

        if (! $granted && isset($inherited[$permissionId])) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => sprintf(lang('RoleWarden.panel.roles.matrixInherited'), $inherited[$permissionId]), 'csrfHash' => csrf_hash()]);
        }

        try {
            $repository = new RolePermissions();

            if ($granted) {
                $repository->grant($id, $permissionId);
            } else {
                $repository->revoke($id, $permissionId);
            }
        } catch (Throwable) {
            return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'message' => lang('RoleWarden.panel.roles.matrixSaveFailed')]);
        }

        return $this->response->setJSON(['ok' => true, 'csrfHash' => csrf_hash()]);
    }

    /**
     * @return array{role: array<string, mixed>, parentName: ?string, userCount: int, areas: list<string>, actions: list<string>, rows: list<array<string, mixed>>, effective: array<string, int>, totalGranted: int, totalDefined: int}|null
     */
    private function buildMatrix(int $id): ?array
    {
        $roleModel = model(RoleModel::class);
        $role = $roleModel->find($id);

        if ($role === null) {
            return null;
        }

        $permissions = model(PermissionModel::class)->orderBy('area')->orderBy('slug')->findAll();
        $areas = array_values(array_unique(array_column($permissions, 'area')));
        $actions = array_values(array_unique(array_map(
            static fn (array $permission): string => substr((string) $permission['slug'], strlen((string) $permission['area']) + 1),
            $permissions,
        )));

        $direct = array_flip(array_map('intval', array_column(
            db_connect()->table(config('RoleWarden')->table('role_permissions'))->select('permission_id')->where('role_id', $id)->get()->getResultArray(),
            'permission_id',
        )));

        $inherited = $this->inheritedPermissions($id);
        $overrides = $this->holderOverrides($id);

        $byArea = [];

        foreach ($permissions as $permission) {
            $byArea[$permission['area']][substr((string) $permission['slug'], strlen((string) $permission['area']) + 1)] = $permission;
        }

        $rows = [];
        $effective = array_fill_keys($actions, 0);
        $totalGranted = 0;

        foreach ($areas as $area) {
            $cells = [];

            foreach ($actions as $action) {
                $permission = $byArea[$area][$action] ?? null;

                if ($permission === null) {
                    $cells[] = ['action' => $action, 'permissionId' => null, 'state' => 'none', 'editable' => false, 'tooltip' => ''];

                    continue;
                }

                $permissionId = (int) $permission['id'];
                $slug = (string) $permission['slug'];
                $origin = $inherited[$permissionId] ?? null;
                $roleGrants = $origin !== null || isset($direct[$permissionId]);
                $ov = $overrides[$permissionId] ?? ['granted' => [], 'denied' => []];

                if ($roleGrants && $ov['denied'] !== []) {
                    $state = 'deny';
                    $tooltip = sprintf('%s · Granted by %s, denied for %d user%s: %s', $slug, $origin ?? $role['name'], count($ov['denied']), count($ov['denied']) > 1 ? 's' : '', implode(', ', $ov['denied']));
                } elseif (! $roleGrants && $ov['granted'] !== []) {
                    $state = 'grant';
                    $tooltip = sprintf('%s · Override for %d user%s: %s', $slug, count($ov['granted']), count($ov['granted']) > 1 ? 's' : '', implode(', ', $ov['granted']));
                } elseif ($origin !== null) {
                    $state = 'inherit';
                    $tooltip = sprintf('%s · from %s', $slug, $origin);
                } elseif (isset($direct[$permissionId])) {
                    $state = 'role';
                    $tooltip = sprintf('%s · Granted by %s', $slug, $role['name']);
                } else {
                    $state = 'none';
                    $tooltip = sprintf('%s · Not granted', $slug);
                }

                if (in_array($state, ['role', 'inherit', 'deny'], true)) {
                    $effective[$action]++;
                    $totalGranted++;
                }

                $cells[] = [
                    'action' => $action,
                    'permissionId' => $permissionId,
                    'state' => $state,
                    'editable' => in_array($state, ['role', 'none'], true),
                    'tooltip' => $tooltip,
                ];
            }

            $rows[] = ['area' => $area, 'cells' => $cells];
        }

        return [
            'role' => $role,
            'parentName' => $role['parent_id'] !== null ? ($roleModel->find((int) $role['parent_id'])['name'] ?? null) : null,
            'userCount' => db_connect()->table(config('RoleWarden')->table('user_roles'))->where('role_id', $id)->countAllResults(),
            'areas' => $areas,
            'actions' => $actions,
            'rows' => $rows,
            'effective' => $effective,
            'totalGranted' => $totalGranted,
            'totalDefined' => count($permissions),
        ];
    }

    /**
     * Roles ordered depth-first (a child directly under its parent), each
     * carrying its indentation depth for the tree column.
     *
     * @param list<array<string, mixed>> $roles
     *
     * @return list<array<string, mixed>>
     */
    private function depthFirst(array $roles): array
    {
        $children = [];

        foreach ($roles as $role) {
            $children[$role['parent_id'] === null ? 0 : (int) $role['parent_id']][] = $role;
        }

        $out = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$out, $children): void {
            foreach ($children[$parentId] ?? [] as $role) {
                $out[] = $role + ['depth' => $depth];
                $walk((int) $role['id'], $depth + 1);
            }
        };
        $walk(0, 0);

        return $out;
    }

    /**
     * Per-user overrides held by users directly assigned this role, split by
     * permission and by grant/deny, as the usernames holding each.
     *
     * @return array<int, array{granted: list<string>, denied: list<string>}>
     */
    private function holderOverrides(int $roleId): array
    {
        $rows = db_connect()->table(config('RoleWarden')->table('user_permissions') . ' up')
            ->select('up.permission_id, up.granted, u.username')
            ->join(config('RoleWarden')->table('user_roles') . ' ur', 'ur.user_id = up.user_id')
            ->join(config('Auth')->tables['users'] . ' u', 'u.id = up.user_id')
            ->where('ur.role_id', $roleId)
            ->get()->getResultArray();

        $out = [];

        foreach ($rows as $row) {
            $bucket = (int) $row['granted'] === 1 ? 'granted' : 'denied';
            $out[(int) $row['permission_id']][$bucket][] = (string) $row['username'];
        }

        foreach ($out as &$entry) {
            $entry['granted'] ??= [];
            $entry['denied'] ??= [];
        }

        return $out;
    }

    /**
     * Permission slug => name of the ancestor role that grants it.
     *
     * @return array<int, string>
     */
    private function inheritedPermissions(int $roleId): array
    {
        $roleModel = model(RoleModel::class);
        $role = $roleModel->find($roleId);
        $origin = [];
        $seen = [];

        $parentId = $role['parent_id'] ?? null;

        while ($parentId !== null && ! isset($seen[$parentId])) {
            $seen[$parentId] = true;
            $parent = $roleModel->find($parentId);

            if ($parent === null) {
                break;
            }

            $permissionIds = array_column(
                db_connect()->table(config('RoleWarden')->table('role_permissions'))->select('permission_id')->where('role_id', $parentId)->get()->getResultArray(),
                'permission_id',
            );

            foreach ($permissionIds as $permissionId) {
                $origin[(int) $permissionId] ??= $parent['name'];
            }

            $parentId = $parent['parent_id'] ?? null;
        }

        return $origin;
    }
}
