<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RoleWarden\Users\CsvImport;
use RoleWarden\Users\UserImport;

/**
 * Users import from CSV (permission users.import): upload, preview with the
 * errors of every row, confirm. The upload redirects back to the import page
 * (so a reload never posts the file again) with the preview as flashdata; a
 * valid one waits in the session, passwords already hashed, under a one-time
 * token for 30 minutes.
 */
class ImportController extends BaseController
{
    private const SESSION_KEY = 'rw_import';
    private const PREVIEW_KEY = 'rw_import_preview';
    private const TTL = 1800;

    public function form(): string
    {
        $preview = session()->getFlashdata(self::PREVIEW_KEY);
        $stored = session(self::SESSION_KEY);

        return rw_panel('RoleWarden\Views\users\import', [
            'preview' => is_array($preview) ? $preview : null,
            'token' => is_array($preview) && is_array($stored) ? $stored['token'] : null,
            'errors' => session()->getFlashdata('rw_errors') ?? [],
        ], 'users', lang('RoleWarden.panel.import.title'), $this->crumbs());
    }

    public function preview(): RedirectResponse
    {
        $back = redirect()->to(site_url('rolewarden/users/import'));
        $file = $this->request->getFile('csv');
        session()->remove(self::SESSION_KEY);

        if ($file === null || ! $file->isValid()) {
            return $back->with('rw_errors', ['csv' => lang('RoleWarden.panel.import.noFile')]);
        }

        if ($file->getSize() > CsvImport::MAX_BYTES) {
            return $back->with('rw_errors', ['csv' => lang('RoleWarden.panel.import.fileErrors.tooLarge', [intdiv(CsvImport::MAX_BYTES, 1024)])]);
        }

        $result = UserImport::preview((string) file_get_contents($file->getTempName()));
        $preview = ['fileErrors' => $result['fileErrors'], 'rows' => $result['rows'], 'fileName' => $file->getClientName()];

        if ($result['payload'] === []) {
            return $back->with(self::PREVIEW_KEY, $preview)->with('rw_error', lang('RoleWarden.panel.import.refused'));
        }

        session()->set(self::SESSION_KEY, ['token' => bin2hex(random_bytes(16)), 'at' => time(), 'rows' => $result['payload']]);

        return $back->with(self::PREVIEW_KEY, $preview);
    }

    public function confirm(): RedirectResponse
    {
        $stored = session(self::SESSION_KEY);
        $token = $this->request->getPost('token');
        session()->remove(self::SESSION_KEY);

        if (! is_array($stored) || ! is_string($token) || ! hash_equals((string) $stored['token'], $token) || time() - (int) $stored['at'] > self::TTL) {
            return redirect()->to(site_url('rolewarden/users/import'))->with('rw_error', lang('RoleWarden.panel.import.expired'));
        }

        $result = UserImport::create($stored['rows']);

        if (is_string($result)) {
            return redirect()->to(site_url('rolewarden/users/import'))->with('rw_error', lang('RoleWarden.panel.import.' . $result));
        }

        return redirect()->to(site_url('rolewarden/users'))->with('rw_success', lang('RoleWarden.panel.import.done', [$result]));
    }

    /**
     * A sample file with the expected header and two example rows.
     */
    public function sample(): ResponseInterface
    {
        $csv = "email,username,role,password\r\n"
            . "dana@northwind-studio.test,dana.whitfield,,\r\n"
            . "marco@northwind-studio.test,marco.leone,admin,\r\n";

        return $this->response->download('rolewarden-users.csv', $csv)->setContentType('text/csv');
    }

    private function crumbs(): string
    {
        return '<a href="' . esc(site_url('rolewarden/users')) . '">' . lang('RoleWarden.panel.nav.users') . '</a>'
            . '<span aria-hidden="true">/</span><span aria-current="page">' . lang('RoleWarden.panel.import.title') . '</span>';
    }
}
