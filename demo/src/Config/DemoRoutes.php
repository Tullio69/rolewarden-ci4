<?php

declare(strict_types=1);

namespace RoleWardenDemo\Config;

use CodeIgniter\Router\RouteCollection;
use RoleWardenDemo\DemoInstaller;

/**
 * One line in the host's app/Config/Routes.php, as for the module:
 *
 *   \RoleWardenDemo\Config\DemoRoutes::register($routes);
 *
 * Nothing is registered until `php spark demo:install` has run. Each action
 * is gated by a permission, never by a role.
 */
class DemoRoutes
{
    public static function register(RouteCollection &$routes): void
    {
        if (! DemoInstaller::installed()) {
            return;
        }

        $routes->group(
            'demo',
            ['namespace' => 'RoleWardenDemo\Controllers', 'filter' => ['session', 'csrf', 'rw-language']],
            static function (RouteCollection $routes): void {
                $routes->get('/', 'DocumentsController::index', ['filter' => 'can:documents.view']);
                $routes->get('create', 'DocumentsController::create', ['filter' => 'can:documents.create']);
                $routes->post('/', 'DocumentsController::store', ['filter' => 'can:documents.create']);
                $routes->get('(:num)/edit', 'DocumentsController::edit/$1', ['filter' => 'can:documents.update']);
                $routes->post('(:num)', 'DocumentsController::update/$1', ['filter' => 'can:documents.update']);
                $routes->post('(:num)/submit', 'DocumentsController::submit/$1', ['filter' => 'can:documents.update']);
                $routes->post('(:num)/approve', 'DocumentsController::approve/$1', ['filter' => 'can:documents.approve']);
                $routes->post('(:num)/delete', 'DocumentsController::delete/$1', ['filter' => 'can:documents.delete']);
                // Showcase mode checks itself: off by default, never in production.
                $routes->post('showcase/(:num)', 'ShowcaseController::switch/$1');
            },
        );
    }
}
