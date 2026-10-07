<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use RoleWarden\Settings\UserLanguage;

abstract class BaseController extends Controller
{
    protected $helpers = ['rolewarden'];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        // Every panel screen speaks the signed-in user's chosen language.
        UserLanguage::use(UserLanguage::forUser(auth()->loggedIn() ? (int) auth()->id() : null));
    }
}
