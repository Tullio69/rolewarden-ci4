<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use RoleWarden\Authorization\ProtectionException;
use RoleWarden\Entities\User;
use RoleWarden\Models\PermissionModel;
use RoleWarden\Models\RoleModel;
use RoleWarden\Models\UserModel;
use RoleWarden\Models\UserPermissions;
use RoleWarden\Models\UserRoles;

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

        $users = $userModel->orderBy('id', 'DESC')->paginate(20);
        $pager = $userModel->pager;
        $pager->only(['q', 'role', 'status']);
        $roles = model(RoleModel::class)->orderBy('name')->findAll();

        return rw_panel('RoleWarden\Views\users\index', [
            'users' => $users,
            'pager' => $pager,
            'roles' => $roles,
            'search' => $search,
            'roleId' => $roleId,
            'status' => $status,
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

        $permissionModel = model(PermissionModel::class);
        $overrideRows = db_connect()->table(config('RoleWarden')->table('user_permissions') . ' up')
            ->select('p.id, p.slug, up.granted')
            ->join(config('RoleWarden')->table('permissions') . ' p', 'p.id = up.permission_id')
            ->where('up.user_id', $id)
            ->orderBy('p.slug')
            ->get()->getResultArray();
        $overriddenIds = array_column($overrideRows, 'id');
        $availablePermissions = $permissionModel->whereNotIn('id', $overriddenIds === [] ? [0] : $overriddenIds)->orderBy('slug')->findAll();

        return rw_panel('RoleWarden\Views\users\show', [
            'user' => $user,
            'assignedRoles' => $assignedRoles,
            'availableRoles' => $availableRoles,
            'overrides' => $overrideRows,
            'availablePermissions' => $availablePermissions,
        ], 'users', $user->username ?? $user->email);
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

    public function setOverride(int $id): RedirectResponse
    {
        $permissionId = (int) $this->request->getPost('permission_id');
        $granted = $this->request->getPost('granted') === '1';

        if ($permissionId > 0) {
            (new UserPermissions())->set($id, $permissionId, $granted);
        }

        return redirect()->to(site_url('rolewarden/users/' . $id))->with('rw_success', lang('RoleWarden.panel.users.overrideSaved'));
    }

    public function clearOverride(int $id, int $permissionId): RedirectResponse
    {
        (new UserPermissions())->clear($id, $permissionId);

        return redirect()->to(site_url('rolewarden/users/' . $id))->with('rw_success', lang('RoleWarden.panel.users.overrideCleared'));
    }
}
