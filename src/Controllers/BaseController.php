<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use CodeIgniter\Controller;

abstract class BaseController extends Controller
{
    protected $helpers = ['rolewarden'];
}
