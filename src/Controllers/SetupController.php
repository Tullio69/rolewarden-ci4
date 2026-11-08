<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Shield\Authentication\Passwords;
use CodeIgniter\Shield\Entities\User;
use RoleWarden\Install\ConfigEditor;
use RoleWarden\Install\HostScan;
use RoleWarden\Install\Installer;
use RoleWarden\Install\SetupState;
use RoleWarden\Settings\UserLanguage;

/**
 * The install wizard for an application that already runs Shield, at
 * /rolewarden/setup. Like InstallController it extends the plain controller
 * and touches neither auth() nor the Settings library: the module's tables
 * may not exist yet. Questions 1 to 6 write nothing but SetupState; only the
 * confirm step applies (Task 7).
 *
 * The current step lives in the session; without one (a new session) it is
 * the first question SetupState has no answer for. A POST only acts when its
 * `step` is the current step: an answer is saved and the wizard moves on,
 * `back` moves back and keeps every answer.
 */
class SetupController extends Controller
{
    private const VERIFIED = 'rw_setup_verified';
    private const STEP     = 'rw_setup_step';
    private const LOCALE   = 'rw_setup_locale';
    private const STEPS    = ['requirements', 'filters', 'roles', 'collisions', 'newUsers', 'superAdmin', 'visibility', 'confirm'];
    private const SYSTEM   = ['super-admin', 'admin', 'user'];
    private const EXCEPTS  = ['rolewarden/assets/*', 'rolewarden/theme.css', 'rolewarden/setup*'];
    private const SLUG     = '/^[a-z][a-z0-9-]*$/';

    private static ?string $configPath = null;

    /**
     * Tests only: read the host's config files from another folder. Null restores APPPATH/Config.
     *
     * @internal
     */
    public static function useConfigPath(?string $path): void
    {
        self::$configPath = $path;
    }

    public function index(): string
    {
        $state  = SetupState::load();
        $post   = $this->request->is('post') ? ($this->request->getPost() ?? []) : [];
        $posted = is_string($post['step'] ?? null) ? $post['step'] : '';
        $locale = $this->locale($state, $posted === 'locale' ? $post['locale'] ?? null : null);

        if ($state->completed()) {
            $this->response->setStatusCode(403);

            return $this->page('completed');
        }

        if (session(self::VERIFIED) !== true) {
            if ($posted !== 'code') {
                return $this->page('code', ['codeFile' => $this->codePath()]);
            }

            if (! hash_equals(Installer::code(), trim(is_string($post['code'] ?? null) ? $post['code'] : ''))) {
                return $this->page('code', ['codeFile' => $this->codePath(), 'error' => lang('RoleWarden.setup.wrongCode')]);
            }

            session()->regenerate();
            session()->set(self::VERIFIED, true);
            session()->remove(self::STEP);
            $posted = 'locale';
        }

        if ($posted === 'locale') {
            $state->setChoice('locale', $locale);
            $state->save();
        }

        $step = $this->current($state);

        if ($posted === $step) {
            if (isset($post['back'])) {
                $step = $this->move($state, $step, -1);
            } elseif ($step !== 'confirm') { // confirm applies: Task 7
                $errors = $this->answer($step, $state, $post);

                if ($errors !== []) {
                    return $this->show($step, $state, $errors, $post);
                }

                $state->save();
                $step = $this->move($state, $step, 1);
            }
        }

        return $this->show($step, $state);
    }

    /**
     * Saves the answer of $step into $state (not yet to disk).
     *
     * @param array<string, mixed> $post
     *
     * @return array<string, string> field => message; empty when the answer is valid
     */
    private function answer(string $step, SetupState $state, array $post): array
    {
        $choices = $state->choices();
        $value   = static fn (string $key): string => is_string($post[$key] ?? null) ? trim($post[$key]) : '';
        $invalid = lang('RoleWarden.setup.invalidChoice');

        switch ($step) {
            case 'requirements':
                return Installer::requirementsMet(Installer::requirements()) ? [] : ['step' => lang('RoleWarden.setup.requirementsMissing')];

            case 'roles':
                $mode   = $value('mode');
                $slugs  = array_column($this->groups(), 'slug');
                $errors = match (true) {
                    ! in_array($mode, ['adapt', 'import'], true) => ['mode' => lang('RoleWarden.setup.modeRequired')],
                    // RoleSetup refuses "adapt" on a group named like the one system role it creates.
                    $mode === 'adapt' && in_array('super-admin', $slugs, true) => ['mode' => lang('RoleWarden.setup.adaptBlocked')],
                    $mode === 'adapt' && $value('panelRole') !== '' && ! in_array($value('panelRole'), $slugs, true) => ['panelRole' => $invalid],
                    default => [],
                };

                if ($errors === []) {
                    $state->setChoice('mode', $mode);
                    $state->setChoice('panelRole', $mode === 'adapt' ? $value('panelRole') : '');
                    $state->setChoice('collisions', $mode === 'import' ? array_intersect_key($choices['collisions'] ?? [], array_flip($this->collisions())) : []);
                }

                return $errors;

            case 'collisions':
                $posted = is_array($post['collisions'] ?? null) ? $post['collisions'] : [];
                $taken  = [...array_column($this->groups(), 'slug'), ...self::SYSTEM];
                $out    = [];
                $errors = [];

                foreach ($this->collisions() as $slug) {
                    $action  = is_string($posted[$slug]['action'] ?? null) ? $posted[$slug]['action'] : '';
                    $newSlug = is_string($posted[$slug]['newSlug'] ?? null) ? trim($posted[$slug]['newSlug']) : '';

                    if (! in_array($action, ['merge', 'rename', 'skip'], true)) {
                        $errors[$slug] = lang('RoleWarden.setup.collisionRequired');
                    } elseif ($action === 'rename' && (preg_match(self::SLUG, $newSlug) !== 1 || in_array($newSlug, $taken, true))) {
                        $errors[$slug] = lang('RoleWarden.setup.slugInvalid');
                    }

                    $out[$slug] = $action === 'rename' ? ['action' => $action, 'newSlug' => $newSlug] : ['action' => $action];
                    $taken[]    = $newSlug;
                }

                if ($errors === []) {
                    $state->setChoice('collisions', $out);
                }

                return $errors;

            case 'newUsers':
                if ($value('defaultRole') !== '' && ! in_array($value('defaultRole'), $this->resultingRoles($choices), true)) {
                    return ['defaultRole' => $invalid];
                }

                $state->setChoice('defaultRole', $value('defaultRole'));

                return [];

            case 'superAdmin':
                return $this->answerSuperAdmin($state, $post);

            case 'visibility':
                if (! in_array($value('visibility'), ['all', 'admins'], true)) {
                    return ['visibility' => $invalid];
                }

                $state->setChoice('visibility', $value('visibility'));

                return [];
        }

        return []; // filters: nothing to choose, the change is shown and applied at the end
    }

    /**
     * An existing user must be one of HostScan::activeUsers (a forged id is refused). A new one
     * is checked with Shield's own rules, as InstallController does; only the hash is kept (R2).
     *
     * @param array<string, mixed> $post
     *
     * @return array<string, string>
     */
    private function answerSuperAdmin(SetupState $state, array $post): array
    {
        $kind = $post['kind'] ?? null;

        if ($kind === 'existing') {
            $id = is_string($post['id'] ?? null) && ctype_digit($post['id']) ? (int) $post['id'] : 0;

            if (! in_array($id, array_column(HostScan::activeUsers(), 'id'), true)) {
                return ['id' => lang('RoleWarden.setup.invalidChoice')];
            }

            $state->setChoice('superAdmin', ['kind' => 'existing', 'id' => $id]);

            return [];
        }

        if ($kind !== 'new') {
            return ['kind' => lang('RoleWarden.setup.invalidChoice')];
        }

        $auth  = config('Auth');
        $rules = [
            'username' => $auth->usernameValidationRules,
            'email' => $auth->emailValidationRules,
            'password' => ['label' => 'Auth.password', 'rules' => ['required', Passwords::getMaxLengthRule()]],
            'password_confirm' => ['label' => 'Auth.passwordConfirm', 'rules' => 'required|matches[password]'],
        ];
        $values = array_map(static fn ($v): string => is_string($v) ? $v : '', array_intersect_key($post, $rules)) + array_fill_keys(array_keys($rules), '');

        if (! $this->validateData($values, $rules)) {
            return $this->validator->getErrors();
        }

        // What strong_password[] checks, but against the new account: the rule itself
        // would use the host user signed in to reach this page.
        $check = service('passwords')->check($values['password'], new User(['username' => trim($values['username']), 'email' => trim($values['email'])]));

        if (! $check->isOK()) {
            return ['password' => (string) $check->reason()];
        }

        $state->setChoice('superAdmin', [
            'kind' => 'new',
            'username' => trim($values['username']),
            'email' => trim($values['email']),
            'passwordHash' => service('passwords')->hash($values['password']),
        ]);

        return [];
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed>  $posted what the failed POST sent, shown back
     */
    private function show(string $step, SetupState $state, array $errors = [], array $posted = []): string
    {
        $choices = $state->choices();
        $data    = ['choices' => $choices, 'errors' => $errors, 'posted' => $posted];

        switch ($step) {
            case 'requirements':
                $data['checks'] = array_values(array_filter(Installer::requirements(), static fn (array $c): bool => $c['key'] !== 'env'));
                break;

            case 'filters':
                $filters            = (string) @file_get_contents($this->configDir() . 'Filters.php');
                $data['filter']     = ConfigEditor::globalFilterExcepts($filters);
                $data['excepts']    = self::EXCEPTS;
                $data['files']      = array_slice($this->filePlan($choices), 0, 1);
                break;

            case 'roles':
                $data['groups']    = $this->groups();
                $data['suggested'] = HostScan::suggestedMode($data['groups']);
                break;

            case 'collisions':
                $data['groups'] = array_values(array_filter($this->groups(), fn (array $g): bool => in_array($g['slug'], $this->collisions(), true)));
                break;

            case 'newUsers':
                $default         = HostScan::defaultGroup(config('AuthGroups')) ?? '';
                $default         = ($choices['collisions'][$default]['action'] ?? null) === 'rename' ? $choices['collisions'][$default]['newSlug'] : $default;
                $data['roles']   = $this->resultingRoles($choices);
                $data['current'] = array_key_exists('defaultRole', $choices) ? (string) $choices['defaultRole'] : (in_array($default, $data['roles'], true) ? $default : '');
                break;

            case 'superAdmin':
                $data['users'] = HostScan::activeUsers();
                break;

            case 'confirm':
                $data['users'] = array_column(HostScan::activeUsers(), null, 'id');
                $data['files'] = $this->filePlan($choices);
                break;
        }

        return $this->page($step, $data);
    }

    /** The session's step when it still applies, else the first unanswered question. */
    private function current(SetupState $state): string
    {
        $choices = $state->choices();
        $step    = session(self::STEP);

        if (is_string($step) && in_array($step, self::STEPS, true) && $this->applies($step, $choices)) {
            return $step;
        }

        $answered = [
            'roles' => isset($choices['mode']),
            'collisions' => array_diff($this->collisions(), array_keys($choices['collisions'] ?? [])) === [],
            'newUsers' => isset($choices['defaultRole']),
            'superAdmin' => isset($choices['superAdmin']),
            'visibility' => isset($choices['visibility']),
        ];

        foreach ($answered as $step => $done) {
            if (! $done && $this->applies($step, $choices)) {
                return $step === 'roles' ? 'requirements' : $step;
            }
        }

        return 'confirm';
    }

    /** @param array<string, mixed> $choices */
    private function applies(string $step, array $choices): bool
    {
        return $step !== 'collisions' || (($choices['mode'] ?? null) === 'import' && $this->collisions() !== []);
    }

    private function move(SetupState $state, string $step, int $by): string
    {
        $i = array_search($step, self::STEPS, true);

        do {
            $i += $by;
        } while (isset(self::STEPS[$i]) && ! $this->applies(self::STEPS[$i], $state->choices()));

        $step = self::STEPS[max(0, min($i, count(self::STEPS) - 1))];
        session()->set(self::STEP, $step);

        return $step;
    }

    /** @return list<array{slug: string, title: string, users: int, permissions: int}> */
    private function groups(): array
    {
        return HostScan::groups(config('AuthGroups'), HostScan::memberCounts());
    }

    /** @return list<string> */
    private function collisions(): array
    {
        return HostScan::collisions($this->groups(), self::SYSTEM);
    }

    /**
     * The roles the choices will leave, for the new sign-ups' role (never super-admin).
     *
     * @param array<string, mixed> $choices
     *
     * @return list<string>
     */
    private function resultingRoles(array $choices): array
    {
        $import = ($choices['mode'] ?? null) === 'import';
        $roles  = $import ? ['admin', 'user'] : [];

        foreach (array_column($this->groups(), 'slug') as $slug) {
            $collision = $import ? ($choices['collisions'][$slug] ?? null) : null;
            $roles[]   = match ($collision['action'] ?? null) {
                'rename' => (string) $collision['newSlug'],
                'skip' => '',
                default => $slug,
            };
        }

        return array_values(array_unique(array_diff($roles, ['', 'super-admin'])));
    }

    /**
     * What the apply step will do to each host file: the unified diff, or manual when the file
     * is missing or written in a way ConfigEditor does not recognise. Writes nothing.
     *
     * @param array<string, mixed> $choices
     *
     * @return list<array{file: string, diff: string, manual: bool}>
     */
    private function filePlan(array $choices): array
    {
        $auth = [[ConfigEditor::withUserProvider(...), ConfigEditor::hasUserProvider(...)]];

        if (($choices['visibility'] ?? null) === 'all') {
            $auth[] = [ConfigEditor::withLoginView(...), ConfigEditor::hasLoginView(...)];
            $auth[] = [ConfigEditor::withLoginRedirect(...), ConfigEditor::hasLoginRedirect(...)];
        }

        $plan = [
            'Filters.php' => [[
                static fn (string $c): ?string => ConfigEditor::withFilterExcept($c, self::EXCEPTS),
                // No global filter at all is not a failure: there is nothing to except.
                static fn (string $c): bool => ConfigEditor::hasFilterExcept($c, self::EXCEPTS) || ConfigEditor::globalFilterExcepts($c) === null,
            ]],
            'Auth.php' => $auth,
            'Routes.php' => [[ConfigEditor::withRouteRegistrar(...), ConfigEditor::hasRouteRegistrar(...)]],
        ];
        $out = [];

        foreach ($plan as $file => $transforms) {
            $before = @file_get_contents($this->configDir() . $file);
            $after  = (string) $before;
            $manual = $before === false;

            foreach ($manual ? [] : $transforms as [$with, $has]) {
                $new = $with($after);

                if ($new !== null) {
                    $after = $new;
                } elseif (! $has($after)) {
                    $manual = true;
                }
            }

            $out[] = ['file' => 'app/Config/' . $file, 'diff' => ConfigEditor::preview((string) $before, $after), 'manual' => $manual];
        }

        return $out;
    }

    private function configDir(): string
    {
        return self::$configPath ?? APPPATH . 'Config/';
    }

    /**
     * The saved choice, else the one made before the code (session), else the browser's.
     * A posted locale replaces both; it reaches SetupState once the code is verified.
     */
    private function locale(SetupState $state, mixed $posted): string
    {
        $available = array_keys(UserLanguage::available());

        if (is_string($posted) && in_array($posted, $available, true)) {
            session()->set(self::LOCALE, $posted);
            $locale = $posted;
        } else {
            $saved  = $state->choices()['locale'] ?? session(self::LOCALE);
            $locale = is_string($saved) && in_array($saved, $available, true) ? $saved : service('negotiator', $this->request, false)->language($available);
        }

        UserLanguage::use($locale);

        return $locale;
    }

    private function codePath(): string
    {
        Installer::code();

        return 'writable/rolewarden/' . basename(Installer::codeFile());
    }

    /** @param array<string, mixed> $data */
    private function page(string $step, array $data = []): string
    {
        return view('RoleWarden\Views\setup\index', ['step' => $step, 'languages' => UserLanguage::available()] + $data + ['choices' => [], 'errors' => [], 'posted' => [], 'error' => null]);
    }
}
