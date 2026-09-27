<?php

declare(strict_types=1);

namespace RoleWarden\Config;

use CodeIgniter\Router\RouteCollection;

/**
 * Deliberately not named Config/Routes.php: that path is auto-included by
 * CI4's route discovery, which would run this a second time and crash on
 * the re-declared class. The host adds one line to its own Routes.php
 * instead, the same way it already wires Shield's
 * `service('auth')->routes($routes)`.
 *
 *   \RoleWarden\Config\RouteRegistrar::register($routes);
 */
class RouteRegistrar
{
    public static function register(RouteCollection &$routes): void
    {
        $routes->get('rolewarden/assets/(:any)', '\RoleWarden\Controllers\AssetController::serve/$1');

        $routes->group(
            'rolewarden',
            ['namespace' => 'RoleWarden\Controllers', 'filter' => ['session', 'csrf']],
            static function (RouteCollection $routes): void {
                $routes->get('users', 'UsersController::index', ['filter' => 'can:users.view']);
                $routes->get('users/create', 'UsersController::create', ['filter' => 'can:users.create']);
                $routes->post('users', 'UsersController::store', ['filter' => 'can:users.create']);
                $routes->get('users/(:num)', 'UsersController::show/$1', ['filter' => 'can:users.view']);
                $routes->get('users/(:num)/edit', 'UsersController::edit/$1', ['filter' => 'can:users.update']);
                $routes->post('users/(:num)', 'UsersController::update/$1', ['filter' => 'can:users.update']);
                $routes->post('users/(:num)/delete', 'UsersController::delete/$1', ['filter' => 'can:users.delete']);
                $routes->post('users/(:num)/activate', 'UsersController::activate/$1', ['filter' => 'can:users.activate']);
                $routes->post('users/(:num)/deactivate', 'UsersController::deactivate/$1', ['filter' => 'can:users.activate']);
                $routes->post('users/(:num)/roles', 'UsersController::assignRole/$1', ['filter' => 'can:roles.assign']);
                $routes->post('users/(:num)/roles/(:num)/revoke', 'UsersController::revokeRole/$1/$2', ['filter' => 'can:roles.assign']);
                $routes->post('users/(:num)/permissions', 'UsersController::setOverride/$1', ['filter' => 'can:permissions.override']);

                $routes->get('roles', 'RolesController::index', ['filter' => 'can:roles.view']);
                $routes->get('roles/create', 'RolesController::create', ['filter' => 'can:roles.create']);
                $routes->post('roles', 'RolesController::store', ['filter' => 'can:roles.create']);
                $routes->get('roles/(:num)', 'RolesController::show/$1', ['filter' => 'can:roles.view']);
                $routes->get('roles/(:num)/edit', 'RolesController::edit/$1', ['filter' => 'can:roles.update']);
                $routes->post('roles/(:num)', 'RolesController::update/$1', ['filter' => 'can:roles.update']);
                $routes->post('roles/(:num)/delete', 'RolesController::delete/$1', ['filter' => 'can:roles.delete']);
                $routes->post('roles/(:num)/permissions', 'RolesController::updatePermissions/$1', ['filter' => 'can:roles.update']);

                $routes->get('permissions', 'PermissionsController::index', ['filter' => 'can:permissions.view']);

                $routes->get('settings', 'SettingsController::index', ['filter' => 'can:settings.view']);
                $routes->post('settings', 'SettingsController::update', ['filter' => 'can:settings.update']);
            },
        );
    }
}
