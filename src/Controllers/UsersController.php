<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RoleWarden\Authorization\ProtectionException;
use RoleWarden\Entities\User;
use RoleWarden\Models\PermissionModel;
use RoleWarden\Models\RoleModel;
use RoleWarden\Models\UserModel;
use RoleWarden\Models\UserPermissions;
use RoleWarden\Models\UserRoles;
use Throwable;

class UsersController extends BaseController
{
    public function index(): string
    {
        $userModel = model(UserModel::class);
        $search = (string) $this->request->getGet('q');
        $roleId = $this->request->getGet('role');
        $status = (string) $this->request->getGet('status');

        if ($search !== '') {
            $userModel->groupStart()->like('username', $search)->orLike('email', $search)->groupEnd();
        }

        if ($roleId !== null && $roleId !== '') {
            $memberIds = array_column(
                db_connect()->table(config('RoleWarden')->table('user_roles'))
                    ->select('user_id')->where('role_id', (int) $roleId)->get()->getResultArray(),
                'user_id',
            );
            $userModel->whereIn('id', $memberIds !== [] ? $memberIds : [0]);
        }

        if ($status === 'active') {
            $userModel->where('active', 1);
        } elseif ($status === 'inactive') {
            $userModel->where('active', 0);
        }

        $sort = (string) $this->request->getGet('sort') === 'asc' ? 'ASC' : 'DESC';
        $users = $userModel->orderBy('last_active', $sort)->orderBy('id', 'DESC')->paginate(20);
        $pager = $userModel->pager;
        $pager->only(['q', 'role', 'status', 'sort']);
        $roles = model(RoleModel::class)->orderBy('name')->findAll();

        $userIds = array_column($users, 'id');
        $roleNames = [];

        foreach ($userIds === [] ? [] : db_connect()->table(config('RoleWarden')->table('user_roles') . ' ur')
            ->select('ur.user_id, r.name')
            ->join(config('RoleWarden')->table('roles') . ' r', 'r.id = ur.role_id')
            ->whereIn('ur.user_id', $userIds)
            ->orderBy('r.name')
            ->get()->getResultArray() as $row) {
            $roleNames[(int) $row['user_id']][] = (string) $row['name'];
        }

        $overrideCounts = [];

        foreach ($userIds === [] ? [] : db_connect()->table(config('RoleWarden')->table('user_permissions'))
            ->select('user_id, COUNT(*) AS total')->whereIn('user_id', $userIds)->groupBy('user_id')->get()->getResultArray() as $row) {
            $overrideCounts[(int) $row['user_id']] = (int) $row['total'];
        }

        $counts = [
            'total' => db_connect()->table(config('Auth')->tables['users'])->countAllResults(),
            'active' => db_connect()->table(config('Auth')->tables['users'])->where('active', 1)->countAllResults(),
            'inactive' => db_connect()->table(config('Auth')->tables['users'])->where('active', 0)->countAllResults(),
        ];

        return rw_panel('RoleWarden\Views\users\index', [
            'users' => $users,
            'pager' => $pager,
            'roles' => $roles,
            'roleNames' => $roleNames,
            'overrideCounts' => $overrideCounts,
            'counts' => $counts,
            'search' => $search,
            'roleId' => $roleId,
            'status' => $status,
            'sort' => $sort,
        ], 'users', lang('RoleWarden.panel.users.indexTitle'));
    }

    public function create(): string
    {
        return rw_panel('RoleWarden\Views\users\form', [
            'user' => null,
            'errors' => session()->getFlashdata('rw_errors') ?? [],
        ], 'users', lang('RoleWarden.panel.users.createTitle'));
    }

    public function store(): RedirectResponse
    {
        $rules = [
            'username' => 'required|min_length[3]|is_unique[' . config('Auth')->tables['users'] . '.username]',
            'email' => 'required|valid_email|is_unique[' . config('Auth')->tables['identities'] . '.secret]',
            'password' => 'required|strong_password',
        ];

        if (! $this->validateData($this->request->getPost() ?? [], $rules)) {
            return redirect()->back()->withInput()->with('rw_errors', $this->validator->getErrors());
        }

        $userModel = model(UserModel::class);
        $user = new User([
            'username' => $this->request->getPost('username'),
            'email' => $this->request->getPost('email'),
            'password' => $this->request->getPost('password'),
        ]);
        $userModel->save($user);
        $userModel->activate($userModel->findById($userModel->getInsertID()));

        return redirect()->to(site_url('rolewarden/users'))->with('rw_success', lang('RoleWarden.panel.users.created'));
    }

    public function show(int $id): string
    {
        $userModel = model(UserModel::class);
        $user = $userModel->find($id);

        if (! $user instanceof User) {
            throw PageNotFoundException::forPageNotFound();
        }

        $roleModel = model(RoleModel::class);
        $assignedRoleIds = array_column(
            db_connect()->table(config('RoleWarden')->table('user_roles'))->select('role_id')->where('user_id', $id)->get()->getResultArray(),
            'role_id',
        );
        $assignedRoles = $assignedRoleIds === [] ? [] : $roleModel->whereIn('id', $assignedRoleIds)->orderBy('name')->findAll();
        $availableRoles = $roleModel->whereNotIn('id', $assignedRoleIds === [] ? [0] : $assignedRoleIds)->orderBy('name')->findAll();

        $matrix = $this->buildUserMatrix($id, $assignedRoles, (string) ($user->username ?? $user->email));

        $lastSuperAdminRoleId = null;

        if (count($assignedRoles) === 1 && (int) $assignedRoles[0]['is_super_admin'] === 1) {
            $activeSuperAdmins = db_connect()->table(config('RoleWarden')->table('user_roles') . ' ur')
                ->distinct()->select('ur.user_id')
                ->join(config('RoleWarden')->table('roles') . ' r', 'r.id = ur.role_id')
                ->join(config('Auth')->tables['users'] . ' u', 'u.id = ur.user_id')
                ->where('r.is_super_admin', 1)->where('u.active', 1)
                ->countAllResults();

            if ($activeSuperAdmins <= 1) {
                $lastSuperAdminRoleId = (int) $assignedRoles[0]['id'];
            }
        }

        $name = (string) ($user->username ?? $user->email);
        $crumbs = '<a href="' . esc(site_url('rolewarden/users')) . '">' . lang('RoleWarden.panel.nav.users') . '</a>'
            . '<span aria-hidden="true">/</span><span aria-current="page">' . esc($name) . '</span>';

        $roleSummaries = array_map(fn (array $role): array => $this->roleSummary($role), $assignedRoles);

        $overrideCount = 0;

        foreach ($matrix['rows'] as $row) {
            foreach ($row['cells'] as $cell) {
                if (in_array($cell['state'], ['grant', 'deny'], true)) {
                    $overrideCount++;
                }
            }
        }

        return rw_panel('RoleWarden\Views\users\show', [
            'user' => $user,
            'assignedRoles' => $assignedRoles,
            'roleSummaries' => $roleSummaries,
            'availableRoles' => $availableRoles,
            'matrix' => $matrix,
            'overrideCount' => $overrideCount,
            'lastSuperAdminRoleId' => $lastSuperAdminRoleId,
            'isSelf' => (int) auth()->id() === $id,
        ], 'users', $name, $crumbs);
    }

    public function edit(int $id): string
    {
        $user = model(UserModel::class)->find($id);

        if ($user === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return rw_panel('RoleWarden\Views\users\form', [
            'user' => $user,
            'errors' => session()->getFlashdata('rw_errors') ?? [],
        ], 'users', lang('RoleWarden.panel.users.editTitle'));
    }

    public function update(int $id): RedirectResponse
    {
        $userModel = model(UserModel::class);
        $user = $userModel->find($id);

        if (! $user instanceof User) {
            throw PageNotFoundException::forPageNotFound();
        }

        $rules = [
            'username' => 'required|min_length[3]|is_unique[' . config('Auth')->tables['users'] . '.username,id,' . $id . ']',
            'email' => 'required|valid_email|is_unique[' . config('Auth')->tables['identities'] . '.secret,user_id,' . $id . ']',
        ];

        if (! $this->validateData($this->request->getPost() ?? [], $rules)) {
            return redirect()->back()->withInput()->with('rw_errors', $this->validator->getErrors());
        }

        $user->fill([
            'username' => $this->request->getPost('username'),
            'email' => $this->request->getPost('email'),
        ]);

        $newPassword = (string) $this->request->getPost('password');

        if ($newPassword !== '') {
            $user->password = $newPassword;
        }

        $userModel->save($user);

        return redirect()->to(site_url('rolewarden/users/' . $id))->with('rw_success', lang('RoleWarden.panel.users.updated'));
    }

    public function delete(int $id): RedirectResponse
    {
        try {
            model(UserModel::class)->delete($id);
        } catch (ProtectionException $e) {
            return redirect()->to(site_url('rolewarden/users/' . $id))->with('rw_error', lang('RoleWarden.protection.' . $e->reason));
        }

        return redirect()->to(site_url('rolewarden/users'))->with('rw_success', lang('RoleWarden.panel.users.deleted'));
    }

    public function activate(int $id): RedirectResponse
    {
        $userModel = model(UserModel::class);
        $user = $userModel->find($id);

        if ($user instanceof User) {
            $userModel->activate($user);
        }

        return redirect()->to(site_url('rolewarden/users/' . $id))->with('rw_success', lang('RoleWarden.panel.users.activated'));
    }

    public function deactivate(int $id): RedirectResponse
    {
        try {
            model(UserModel::class)->update($id, ['active' => false]);
        } catch (ProtectionException $e) {
            return redirect()->to(site_url('rolewarden/users/' . $id))->with('rw_error', lang('RoleWarden.protection.' . $e->reason));
        }

        return redirect()->to(site_url('rolewarden/users/' . $id))->with('rw_success', lang('RoleWarden.panel.users.deactivated'));
    }

    public function assignRole(int $id): RedirectResponse
    {
        $roleId = (int) $this->request->getPost('role_id');

        if ($roleId > 0) {
            (new UserRoles())->assign($id, $roleId);
        }

        return redirect()->to(site_url('rolewarden/users/' . $id))->with('rw_success', lang('RoleWarden.panel.users.roleAssigned'));
    }

    public function revokeRole(int $id, int $roleId): RedirectResponse
    {
        try {
            (new UserRoles())->revoke($id, $roleId);
        } catch (ProtectionException $e) {
            return redirect()->to(site_url('rolewarden/users/' . $id))->with('rw_error', lang('RoleWarden.protection.' . $e->reason));
        }

        return redirect()->to(site_url('rolewarden/users/' . $id))->with('rw_success', lang('RoleWarden.panel.users.roleRevoked'));
    }

    public function setOverride(int $id): ResponseInterface
    {
        $permissionId = (int) $this->request->getPost('permission_id');
        $granted = $this->request->getPost('granted');

        if (model(UserModel::class)->find($id) === null || model(PermissionModel::class)->find($permissionId) === null) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => lang('RoleWarden.panel.users.overrideNotFound')]);
        }

        try {
            if ($granted === null) {
                (new UserPermissions())->clear($id, $permissionId);
            } else {
                (new UserPermissions())->set($id, $permissionId, $granted === '1');
            }
        } catch (Throwable) {
            return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'message' => lang('RoleWarden.panel.users.overrideSaveFailed')]);
        }

        return $this->response->setJSON(['ok' => true, 'csrfHash' => csrf_hash()]);
    }

    /**
     * @param array<string, mixed> $role
     *
     * @return array{id: int, name: string, parentChain: list<string>, ownCount: int, inheritedCount: int}
     */
    private function roleSummary(array $role): array
    {
        $roleModel = model(RoleModel::class);
        $roleId = (int) $role['id'];

        $ownCount = db_connect()->table(config('RoleWarden')->table('role_permissions'))->where('role_id', $roleId)->countAllResults();

        $parentChain = [];
        $inheritedPermissionIds = [];
        $parentId = $role['parent_id'] ?? null;
        $seen = [];

        while ($parentId !== null && ! isset($seen[$parentId])) {
            $seen[$parentId] = true;
            $parent = $roleModel->find($parentId);

            if ($parent === null) {
                break;
            }

            $parentChain[] = $parent['name'];

            foreach (array_column(
                db_connect()->table(config('RoleWarden')->table('role_permissions'))->select('permission_id')->where('role_id', $parentId)->get()->getResultArray(),
                'permission_id',
            ) as $pid) {
                $inheritedPermissionIds[(int) $pid] = true;
            }

            $parentId = $parent['parent_id'] ?? null;
        }

        return [
            'id' => $roleId,
            'name' => (string) $role['name'],
            'parentChain' => $parentChain,
            'ownCount' => $ownCount,
            'inheritedCount' => count($inheritedPermissionIds),
        ];
    }

    /**
     * What this user's roles grant, with her own overrides laid on top. Same
     * cell shape as RolesController::buildMatrix, from the user's side: a
     * 'grant' or 'deny' state here is always her own override, since the
     * matrix only ever offers a denial on top of a grant or a grant on top
     * of nothing (RoleWarden.panel.js mirrors that rule when it edits).
     *
     * @param list<array<string, mixed>> $assignedRoles
     *
     * @return array{areas: list<string>, actions: list<string>, rows: list<array<string, mixed>>}
     */
    private function buildUserMatrix(int $userId, array $assignedRoles, string $username): array
    {
        $roleModel = model(RoleModel::class);
        $permissions = model(PermissionModel::class)->orderBy('area')->orderBy('slug')->findAll();
        $areas = array_values(array_unique(array_column($permissions, 'area')));
        $actions = array_values(array_unique(array_map(
            static fn (array $permission): string => substr((string) $permission['slug'], strlen((string) $permission['area']) + 1),
            $permissions,
        )));

        $directBy = [];
        $inheritBy = [];

        foreach ($assignedRoles as $role) {
            $roleId = (int) $role['id'];

            foreach (array_column(
                db_connect()->table(config('RoleWarden')->table('role_permissions'))->select('permission_id')->where('role_id', $roleId)->get()->getResultArray(),
                'permission_id',
            ) as $pid) {
                $directBy[(int) $pid][] = $role['name'];
            }

            $parentId = $role['parent_id'] ?? null;
            $seen = [];

            while ($parentId !== null && ! isset($seen[$parentId])) {
                $seen[$parentId] = true;
                $parent = $roleModel->find($parentId);

                if ($parent === null) {
                    break;
                }

                foreach (array_column(
                    db_connect()->table(config('RoleWarden')->table('role_permissions'))->select('permission_id')->where('role_id', $parentId)->get()->getResultArray(),
                    'permission_id',
                ) as $pid) {
                    $inheritBy[(int) $pid]['ancestor'] ??= $parent['name'];
                    $inheritBy[(int) $pid]['via'][] = $role['name'];
                }

                $parentId = $parent['parent_id'] ?? null;
            }
        }

        $overrides = [];

        foreach (db_connect()->table(config('RoleWarden')->table('user_permissions'))
            ->select('permission_id, granted')->where('user_id', $userId)->get()->getResultArray() as $row) {
            $overrides[(int) $row['permission_id']] = (int) $row['granted'] === 1;
        }

        $byArea = [];

        foreach ($permissions as $permission) {
            $byArea[$permission['area']][substr((string) $permission['slug'], strlen((string) $permission['area']) + 1)] = $permission;
        }

        $rows = [];

        foreach ($areas as $area) {
            $cells = [];

            foreach ($actions as $action) {
                $permission = $byArea[$area][$action] ?? null;

                if ($permission === null) {
                    $cells[] = ['action' => $action, 'permissionId' => null, 'state' => 'none', 'tooltip' => ''];

                    continue;
                }

                $permissionId = (int) $permission['id'];
                $slug = (string) $permission['slug'];

                if (array_key_exists($permissionId, $overrides)) {
                    $state = $overrides[$permissionId] ? 'grant' : 'deny';
                    $tooltip = $state === 'grant'
                        ? sprintf('%s · Override: granted to %s only', $slug, $username)
                        : sprintf('%s · Override: denied. %s', $slug, isset($directBy[$permissionId]) ? 'Granted by ' . implode(', ', $directBy[$permissionId]) : (isset($inheritBy[$permissionId]) ? 'From ' . $inheritBy[$permissionId]['ancestor'] : ''));
                } elseif (isset($directBy[$permissionId])) {
                    $state = 'role';
                    $tooltip = sprintf('%s · Granted by %s', $slug, implode(', ', $directBy[$permissionId]));
                } elseif (isset($inheritBy[$permissionId])) {
                    $state = 'inherit';
                    $tooltip = sprintf('%s · from %s, via %s', $slug, $inheritBy[$permissionId]['ancestor'], implode(', ', array_unique($inheritBy[$permissionId]['via'])));
                } else {
                    $state = 'none';
                    $tooltip = sprintf('%s · Not granted', $slug);
                }

                $cells[] = ['action' => $action, 'permissionId' => $permissionId, 'state' => $state, 'tooltip' => $tooltip];
            }

            $rows[] = ['area' => $area, 'cells' => $cells];
        }

        return ['areas' => $areas, 'actions' => $actions, 'rows' => $rows];
    }
}
