<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Login, registrazione, logout ecc. di Shield
service('auth')->routes($routes);

// Tutta l'app richiede login (filtro "session" applicato in Config\Filters)
$routes->get('/', 'Dashboard::index', ['as' => 'dashboard']);

$routes->group('tickets', static function (RouteCollection $routes): void {
    $routes->get('/', 'Tickets::index', ['as' => 'tickets']);
    $routes->get('new', 'Tickets::new', ['as' => 'tickets.new']);
    $routes->post('/', 'Tickets::create', ['as' => 'tickets.create']);
    $routes->get('(:num)', 'Tickets::show/$1', ['as' => 'tickets.show']);
    $routes->post('(:num)/reply', 'Tickets::reply/$1', ['as' => 'tickets.reply']);
    $routes->post('(:num)/update', 'Tickets::update/$1', ['as' => 'tickets.update']);
    $routes->post('(:num)/take', 'Tickets::take/$1', ['as' => 'tickets.take']);
    $routes->post('(:num)/close', 'Tickets::close/$1', ['as' => 'tickets.close']);
});

$routes->get('attachments/(:num)', 'Attachments::download/$1', ['as' => 'attachments.download']);

$routes->group('admin', ['namespace' => 'App\Controllers\Admin'], static function (RouteCollection $routes): void {
    $routes->get('categories', 'Categories::index', ['as' => 'admin.categories']);
    $routes->get('categories/new', 'Categories::new', ['as' => 'admin.categories.new']);
    $routes->post('categories', 'Categories::create', ['as' => 'admin.categories.create']);
    $routes->get('categories/(:num)/edit', 'Categories::edit/$1', ['as' => 'admin.categories.edit']);
    $routes->post('categories/(:num)', 'Categories::update/$1', ['as' => 'admin.categories.update']);

    $routes->get('users', 'Users::index', ['as' => 'admin.users']);
    $routes->post('users/(:num)', 'Users::update/$1', ['as' => 'admin.users.update']);
});
