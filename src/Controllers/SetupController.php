<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Shield\Authentication\Passwords;
use CodeIgniter\Shield\Entities\User;
use RoleWarden\Install\ConfigEditor;
use RoleWarden\Install\HostScan;
use RoleWarden\Install\Installer;
use RoleWarden\Install\Setup;
use RoleWarden\Install\SetupState;
use RoleWarden\Settings\UserLanguage;
use Throwable;

/**
 * The install wizard for an application that already runs Shield, at
 * /rolewarden/setup. Like InstallController it extends the plain controller
 * and touches neither auth() nor the Settings library: the module's tables
 * may not exist yet. Questions 1 to 6 write nothing but SetupState; only the
 * confirm step applies, through Setup::apply; a failed run is shown on the
 * confirm page and a new POST resumes it.
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

        try {
            return $this->flow($state, $post, $posted);
        } catch (Throwable $e) {
            // Reading the host's database (HostScan, the apply's checks) failed: the detail,
            // possibly SQL, goes to the log only, in every environment.
            log_message('error', 'RoleWarden setup: {message}', ['message' => $e->getMessage()]);
            $this->response->setStatusCode(500);

            return $this->page('scanFailed');
        }
    }

    /**
     * The verified part: the current step, the answer or the apply.
     *
     * @param array<string, mixed> $post
     */
    private function flow(SetupState $state, array $post, string $posted): string
    {
        $step = $this->current($state);

        // R15: once an apply step is done the answers are locked; the run can only be continued.
        // R20: the super admin alone stays open until its own step is done; Back and Continue
        // then go straight between it and confirm.
        $open = $this->open($state);

        if ($open !== self::STEPS) {
            $step = in_array($step, $open, true) ? $step : 'confirm';
            session()->set(self::STEP, $step);
            $allowed = in_array($posted, $open, true) && (! isset($post['back']) || ($posted === 'confirm' && in_array('superAdmin', $open, true)));

            if (in_array($posted, self::STEPS, true) && ! $allowed) {
                return $this->show($step, $state, ['step' => lang('RoleWarden.setup.locked')]);
            }
        }

        if ($posted === $step) {
            if (isset($post['back'])) {
                $step = $open === self::STEPS ? $this->move($state, $step, -1) : $this->go('superAdmin');
            } elseif ($step === 'confirm') {
                $result = Setup::apply($state, config('AuthGroups'), $this->configDir());

                if ($result['ok']) {
                    session()->remove([self::VERIFIED, self::STEP]);

                    return $this->done($state);
                }

                return $this->show($step, $state, [], [], $result);
            } else {
                $errors = $this->answer($step, $state, $post);

                if ($errors !== []) {
                    return $this->show($step, $state, $errors, $post);
                }

                $state->save();
                $step = $open === self::STEPS ? $this->move($state, $step, 1) : $this->go('confirm');
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
                $mode    = $value('mode');
                $slugs   = array_column($this->groups(), 'slug');
                $blocked = $mode === 'adapt' ? HostScan::adaptBlocked($slugs) : [];
                $errors  = match (true) {
                    ! in_array($mode, ['adapt', 'import'], true) => ['mode' => lang('RoleWarden.setup.modeRequired')],
                    // RoleSetup refuses "adapt" on a group named like a system role: only import resolves it.
                    $blocked !== [] => ['mode' => lang('RoleWarden.setup.adaptBlocked', [implode(', ', $blocked)])],
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
                if ($value('defaultRole') !== '' && ! in_array($value('defaultRole'), Setup::resultingRoles($choices, config('AuthGroups')), true)) {
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
     * An existing user must be able to sign in, checked in the database (HostScan::isActiveUser,
     * R20: a forged id is refused, a user past the listed 200 is not). A new one
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

            if (! HostScan::isActiveUser($id)) {
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
     * @param array{ok: bool, failedStep: ?string, messages: list<string>}|null $result a failed Setup::apply
     */
    private function show(string $step, SetupState $state, array $errors = [], array $posted = [], ?array $result = null): string
    {
        $choices = $state->choices();
        $data    = ['choices' => $choices, 'errors' => $errors, 'posted' => $posted, 'result' => $result];

        switch ($step) {
            case 'requirements':
                $data['checks'] = array_values(array_filter(Installer::requirements(), static fn (array $c): bool => $c['key'] !== 'env'));
                break;

            case 'filters':
                $filters            = (string) @file_get_contents($this->configDir() . 'Filters.php');
                $data['filter']     = ConfigEditor::globalFilterExcepts($filters);
                $data['excepts']    = Setup::EXCEPTS;
                $data['files']      = array_slice(Setup::filePlan($choices, $this->configDir()), 0, 1);
                break;

            case 'roles':
                $data['groups']    = $this->groups();
                $data['suggested'] = HostScan::suggestedMode($data['groups']);
                $data['direct']    = HostScan::directPermissions();
                break;

            case 'collisions':
                $data['groups'] = array_values(array_filter($this->groups(), fn (array $g): bool => in_array($g['slug'], $this->collisions(), true)));
                break;

            case 'newUsers':
                $default         = HostScan::defaultGroup(config('AuthGroups')) ?? '';
                $default         = ($choices['collisions'][$default]['action'] ?? null) === 'rename' ? $choices['collisions'][$default]['newSlug'] : $default;
                $data['roles']   = Setup::resultingRoles($choices, config('AuthGroups'));
                $data['current'] = array_key_exists('defaultRole', $choices) ? (string) $choices['defaultRole'] : (in_array($default, $data['roles'], true) ? $default : '');
                break;

            case 'superAdmin':
                $data['users']  = HostScan::activeUsers();
                $data['locked'] = Setup::started($state);
                break;

            case 'confirm':
                $data['users'] = array_column(HostScan::activeUsers(), null, 'id');
                $data['files']  = Setup::filePlan($choices, $this->configDir());
                $data['locked'] = Setup::started($state);
                $data['reopen'] = in_array('superAdmin', $this->open($state), true) && $data['locked'];
                $data['direct'] = HostScan::directPermissions();
                break;
        }

        return $this->page($step, $data);
    }

    /** The summary after a completed apply: the steps, the files changed, what is left to the owner. */
    private function done(SetupState $state): string
    {
        $edits = $state->edits();

        return $this->page('done', [
            'edits' => $edits,
            'providerChanged' => array_filter($edits, static fn (array $e): bool => ! ConfigEditor::hasUserProvider($e['before']) && ConfigEditor::hasUserProvider($e['after'])) !== [],
        ]);
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

    /** @return list<string> the steps that can still be answered or posted */
    private function open(SetupState $state): array
    {
        if (! Setup::started($state)) {
            return self::STEPS;
        }

        return $state->done('superAdmin') ? ['confirm'] : ['superAdmin', 'confirm'];
    }

    private function go(string $step): string
    {
        session()->set(self::STEP, $step);

        return $step;
    }

    private function move(SetupState $state, string $step, int $by): string
    {
        $i = array_search($step, self::STEPS, true);

        do {
            $i += $by;
        } while (isset(self::STEPS[$i]) && ! $this->applies(self::STEPS[$i], $state->choices()));

        return $this->go(self::STEPS[max(0, min($i, count(self::STEPS) - 1))]);
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
        return view('RoleWarden\Views\setup\index', ['step' => $step, 'languages' => UserLanguage::available()] + $data + ['choices' => [], 'errors' => [], 'posted' => [], 'error' => null, 'result' => null, 'locked' => false, 'reopen' => false]);
    }
}
