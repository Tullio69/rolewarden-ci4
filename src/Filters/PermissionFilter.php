<?php

declare(strict_types=1);

namespace RoleWarden\Filters;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Filters\AbstractAuthFilter;

/**
 * Route filter "can": passes when the logged-in user holds at least one of
 * the listed permissions, e.g. ['filter' => 'can:users.view,users.update'].
 */
class PermissionFilter extends AbstractAuthFilter
{
    protected function isAuthorized(array $arguments): bool
    {
        return service('rolewarden')->canAny((int) auth()->id(), array_map('strtolower', array_values($arguments)));
    }

    protected function redirectToDeniedUrl(): RedirectResponse
    {
        return redirect()->to(config('Auth')->permissionDeniedRedirect())
            ->with('error', lang('RoleWarden.accessDenied'));
    }
}
