<?php

declare(strict_types=1);

namespace RoleWarden\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use RoleWarden\Install\Readiness;

/**
 * Route filter "rw-ready", first on the panel's routes: until the module's
 * migrations have run, every panel page is the "not installed yet" page (503),
 * pointing at the installer. The rest of the host app is left alone.
 */
class TablesReady implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (Readiness::tablesExist()) {
            return null;
        }

        return service('response')->setStatusCode(503)->setBody(view('RoleWarden\Views\install\not_installed'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
