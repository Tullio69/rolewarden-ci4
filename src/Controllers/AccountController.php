<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Authentication\Passwords;
use CodeIgniter\Shield\Entities\User;
use RoleWarden\Account\Sessions;
use RoleWarden\Models\UserModel;

/**
 * The signed-in user's own account: profile with password change, and their
 * sessions. The users/{id}/sessions routes let an admin with sessions.revoke
 * do the same to someone else's sessions from the user detail screen.
 */
class AccountController extends BaseController
{
    public function profile(): string
    {
        $user = auth()->user();
        $sessions = Sessions::forUser((int) $user->id);

        return rw_panel('RoleWarden\Views\account\profile', [
            'user' => $user,
            'sessionCount' => count($sessions['sessions']),
            'errors' => session()->getFlashdata('rw_errors') ?? [],
        ], 'profile', lang('RoleWarden.panel.account.profileTitle'));
    }

    public function password(): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $back = site_url('rolewarden/profile');
        $rules = [
            'current_password' => ['label' => 'RoleWarden.panel.account.currentPassword', 'rules' => 'required'],
            'password' => ['label' => 'RoleWarden.panel.account.newPassword', 'rules' => 'required|' . Passwords::getMaxLengthRule() . '|strong_password[]'],
            'password_confirm' => ['label' => 'RoleWarden.panel.account.confirmPassword', 'rules' => 'required|matches[password]'],
        ];

        if (! $this->validateData($this->request->getPost() ?? [], $rules)) {
            return redirect()->to($back)->with('rw_errors', $this->validator->getErrors());
        }

        if (! service('passwords')->verify((string) $this->request->getPost('current_password'), (string) $user->getPasswordHash())) {
            return redirect()->to($back)->with('rw_errors', ['current_password' => lang('RoleWarden.panel.account.wrongPassword')]);
        }

        $user->password = (string) $this->request->getPost('password');
        model(UserModel::class)->save($user);

        // A changed password is often a reaction to someone else having it.
        Sessions::revokeAll((int) $user->id);

        return redirect()->to($back)->with('rw_success', lang('RoleWarden.panel.account.passwordChanged'));
    }

    public function sessions(): string
    {
        return rw_panel('RoleWarden\Views\account\sessions', [
            'list' => Sessions::forUser((int) auth()->id()),
            'base' => 'rolewarden/sessions',
        ], 'sessions', lang('RoleWarden.panel.account.sessionsTitle'));
    }

    public function revokeOwn(int $id): RedirectResponse
    {
        return $this->revoke((int) auth()->id(), $id, 'rolewarden/sessions');
    }

    public function revokeOwnRemembered(int $id): RedirectResponse
    {
        return $this->revokeRemembered((int) auth()->id(), $id, 'rolewarden/sessions');
    }

    public function revokeOwnAll(): RedirectResponse
    {
        return $this->revokeAll((int) auth()->id(), 'rolewarden/sessions');
    }

    public function revokeUser(int $userId, int $id): RedirectResponse
    {
        return $this->revoke($this->existing($userId), $id, 'rolewarden/users/' . $userId);
    }

    public function revokeUserRemembered(int $userId, int $id): RedirectResponse
    {
        return $this->revokeRemembered($this->existing($userId), $id, 'rolewarden/users/' . $userId);
    }

    public function revokeUserAll(int $userId): RedirectResponse
    {
        return $this->revokeAll($this->existing($userId), 'rolewarden/users/' . $userId);
    }

    private function revoke(int $userId, int $id, string $back): RedirectResponse
    {
        return Sessions::revokeSession($userId, $id)
            ? redirect()->to(site_url($back))->with('rw_success', lang('RoleWarden.panel.account.sessionRevoked'))
            : redirect()->to(site_url($back))->with('rw_error', lang('RoleWarden.panel.account.sessionGone'));
    }

    private function revokeRemembered(int $userId, int $id, string $back): RedirectResponse
    {
        return Sessions::revokeRemembered($userId, $id)
            ? redirect()->to(site_url($back))->with('rw_success', lang('RoleWarden.panel.account.rememberedRevoked'))
            : redirect()->to(site_url($back))->with('rw_error', lang('RoleWarden.panel.account.sessionGone'));
    }

    private function revokeAll(int $userId, string $back): RedirectResponse
    {
        Sessions::revokeAll($userId);

        return redirect()->to(site_url($back))->with('rw_success', lang('RoleWarden.panel.account.allRevoked'));
    }

    private function existing(int $userId): int
    {
        if (model(UserModel::class)->find($userId) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $userId;
    }
}
