<?php

declare(strict_types=1);

namespace RoleWarden\Filters;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use RoleWarden\Settings\Visibility;

/**
 * Route filter "rw-account" on the signed-in user's own profile and sessions screens:
 * with the "Administrators only" setting, users without any panel permission get a 404.
 */
class AccountVisibility implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! Visibility::allowsUser((int) auth()->id())) {
            throw PageNotFoundException::forPageNotFound();
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
