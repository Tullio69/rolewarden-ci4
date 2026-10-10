<?php

declare(strict_types=1);

namespace RoleWardenDemo\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use RoleWardenDemo\Showcase;

class ShowcaseController extends Controller
{
    /**
     * Turns the session into another demo account. Only towards the accounts
     * demo:install created, only when showcase mode is on.
     */
    public function switch(int $userId): RedirectResponse
    {
        if (! Showcase::canSwitchTo($userId)) {
            throw PageNotFoundException::forPageNotFound();
        }

        auth()->logout();
        auth()->loginById($userId);

        return redirect()->to(site_url('demo'))->with('rw_success', lang('Demo.switched', [auth()->user()->username]));
    }
}
