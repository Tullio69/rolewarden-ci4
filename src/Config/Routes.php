<?php

/**
 * Auto-included by CodeIgniter's route discovery as soon as the module is
 * loaded, so the install wizard answers before the host has added the
 * RouteRegistrar line to its own Routes.php (the wizard is what adds it).
 *
 * It coexists with RouteRegistrar because it declares no class: a file that
 * is included twice only registers the same route twice. Every other route
 * stays in RouteRegistrar, wired by the host.
 *
 * @var \CodeIgniter\Router\RouteCollection $routes
 */
$routes->match(['GET', 'POST'], 'rolewarden/setup', '\RoleWarden\Controllers\SetupController::index', ['filter' => 'csrf']);
