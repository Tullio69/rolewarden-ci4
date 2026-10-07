<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Shield\Authentication\Passwords;
use RoleWarden\Install\Installer;

/**
 * The browser installer at /rolewarden/install. Not a panel screen: it runs
 * before there is a database or anyone to sign in, so it extends the plain
 * controller and touches neither auth() nor the Settings library. Each
 * request works out the current step from the application's state; a POST
 * only acts when it is for that step.
 */
class InstallController extends Controller
{
    private const VERIFIED = 'rw_install_verified';

    public function index(): string
    {
        if (Installer::installed()) {
            $this->response->setStatusCode(403);

            return $this->page('installed');
        }

        $checks = Installer::requirements();

        if (! Installer::requirementsMet($checks)) {
            return $this->page('requirements', ['checks' => $checks]);
        }

        $posted = $this->request->is('post') ? (string) $this->request->getPost('step') : '';

        if (session(self::VERIFIED) !== true) {
            return $posted === 'code' ? $this->verify($checks) : $this->page('code', ['checks' => $checks, 'codeFile' => $this->codePath()]);
        }

        $db = Installer::database();

        if ($db === null) {
            return $posted === 'database' ? $this->saveDatabase() : $this->page('database', ['values' => $this->databaseDefaults()]);
        }

        if (! Installer::migrated($db)) {
            if ($posted === 'migrate') {
                return Installer::migrate() ? $this->page('admin') : $this->page('migrate', ['error' => lang('RoleWarden.install.migrateFailed')]);
            }

            return $this->page('migrate');
        }

        return $posted === 'admin' ? $this->createAdmin() : $this->page('admin');
    }

    /**
     * @param list<array{key: string, ok: bool, detail: string, blocking: bool}> $checks
     */
    private function verify(array $checks): string
    {
        if (! hash_equals(Installer::code(), trim((string) $this->request->getPost('code')))) {
            return $this->page('code', ['checks' => $checks, 'codeFile' => $this->codePath(), 'error' => lang('RoleWarden.install.wrongCode')]);
        }

        session()->regenerate();
        session()->set(self::VERIFIED, true);

        return $this->index();
    }

    private function saveDatabase(): string
    {
        $post = $this->request->getPost() ?? [];
        $rules = [
            'baseURL' => ['label' => 'RoleWarden.install.baseURL', 'rules' => 'required|valid_url_strict'],
            'hostname' => ['label' => 'RoleWarden.install.hostname', 'rules' => 'required|max_length[255]'],
            'port' => ['label' => 'RoleWarden.install.port', 'rules' => 'required|is_natural_no_zero|less_than[65536]'],
            'database' => ['label' => 'RoleWarden.install.database', 'rules' => 'required|max_length[64]'],
            'username' => ['label' => 'RoleWarden.install.dbUsername', 'rules' => 'required|max_length[255]'],
            'password' => ['label' => 'RoleWarden.install.dbPassword', 'rules' => 'permit_empty|max_length[255]'],
        ];
        $values = array_map(static fn ($v): string => is_string($v) ? trim($v) : '', array_intersect_key($post, $rules)) + array_fill_keys(array_keys($rules), '');
        // A password keeps its spaces.
        $values['password'] = is_string($post['password'] ?? null) ? $post['password'] : '';

        if (! $this->validateData($values, $rules)) {
            return $this->page('database', ['values' => $values, 'errors' => $this->validator->getErrors()]);
        }

        $settings = ['port' => (int) $values['port']] + $values;

        if (! Installer::canConnect(array_intersect_key($settings, array_flip(['hostname', 'port', 'database', 'username', 'password'])))) {
            return $this->page('database', ['values' => $values, 'error' => lang('RoleWarden.install.connectFailed')]);
        }

        $file = Installer::envFile();
        $current = is_file($file) ? (string) file_get_contents($file) : '';
        $env = Installer::env($current, Installer::envValues($settings, $current));

        if (@file_put_contents($file, $env) === false) {
            return $this->page('envManual', ['env' => $env]);
        }

        return $this->page('envSaved');
    }

    private function createAdmin(): string
    {
        $auth = config('Auth');
        $rules = [
            'username' => $auth->usernameValidationRules,
            'email' => $auth->emailValidationRules,
            'password' => ['label' => 'Auth.password', 'rules' => ['required', Passwords::getMaxLengthRule(), 'strong_password[]']],
            'password_confirm' => ['label' => 'Auth.passwordConfirm', 'rules' => 'required|matches[password]'],
        ];
        $post = $this->request->getPost() ?? [];
        $values = array_map(static fn ($v): string => is_string($v) ? $v : '', array_intersect_key($post, $rules)) + array_fill_keys(array_keys($rules), '');

        if (! $this->validateData($values, $rules)) {
            return $this->page('admin', ['values' => $values, 'errors' => $this->validator->getErrors()]);
        }

        if (! Installer::createSuperAdmin(trim($values['username']), trim($values['email']), $values['password'])) {
            return $this->page('admin', ['values' => $values, 'error' => lang('RoleWarden.install.adminFailed')]);
        }

        session()->remove(self::VERIFIED);

        return $this->page('done', ['email' => trim($values['email'])]);
    }

    /**
     * @return array<string, string>
     */
    private function databaseDefaults(): array
    {
        $db = config('Database')->default;
        $uri = $this->request->getUri();
        // The address this page was opened at, up to the installer's own path.
        $path = preg_replace('#/(index\.php/)?rolewarden/install/?$#', '', $uri->getPath()) ?? '';
        $port = $uri->getPort();

        return [
            'baseURL' => $uri->getScheme() . '://' . $uri->getHost() . ($port !== null ? ':' . $port : '') . rtrim($path, '/') . '/',
            'hostname' => (string) ($db['hostname'] ?? 'localhost'),
            'port' => (string) ($db['port'] ?? 3306),
            'database' => (string) ($db['database'] ?? ''),
            'username' => (string) ($db['username'] ?? ''),
            'password' => '',
        ];
    }

    private function codePath(): string
    {
        Installer::code();

        return 'writable/rolewarden/' . basename(Installer::codeFile());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function page(string $step, array $data = []): string
    {
        return view('RoleWarden\Views\install\index', ['step' => $step] + $data + ['values' => [], 'errors' => [], 'error' => null]);
    }
}
