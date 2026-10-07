<?php

declare(strict_types=1);

namespace RoleWarden\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use RoleWarden\Settings\UserLanguage;

/**
 * Route filter "rw-language" on the panel's routes: the signed-in user's
 * chosen language for everything the request says. Only there, so assets
 * and the installer never look up a user or the database.
 */
class PanelLanguage implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        UserLanguage::use(UserLanguage::forUser(auth()->loggedIn() ? (int) auth()->id() : null));

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
