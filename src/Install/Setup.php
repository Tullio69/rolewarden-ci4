<?php

declare(strict_types=1);

namespace RoleWarden\Install;

use Closure;
use InvalidArgumentException;
use RoleWarden\Database\Seeds\RoleWardenSeeder;
use RoleWarden\Entities\User;
use RoleWarden\Models\UserModel;
use RoleWarden\Models\UserRoles;
use RuntimeException;
use Throwable;

/**
 * The wizard's "Confirm and apply". Runs, in the binding order, only the steps
 * SetupState has not marked done: migrations, roles, super admin, filter
 * exception, wiring. Nothing touches a host file before the tables exist, and
 * the state is saved after every step and every file write, so the log only
 * ever holds what really happened and a second run resumes where one stopped.
 */
final class Setup
{
    /** Paths the host's global login filter must let through: the wizard itself and the module's assets. */
    public const EXCEPTS = ['rolewarden/assets/*', 'rolewarden/theme.css', 'rolewarden/setup*'];

    public const STEPS = ['migrations', 'roles', 'superAdmin', 'filters', 'wiring'];

    private const SYSTEM = ['super-admin', 'admin', 'user'];
    private const SLUG   = '/^[a-z][a-z0-9-]*$/';

    /**
     * failedStep is the apply step that stopped (one of STEPS), or the wizard question whose saved
     * answer no longer holds (roles, collisions, newUsers, superAdmin, visibility). messages[0] says
     * what happened; each further entry is a file name and the text to paste into it by hand.
     *
     * @return array{ok: bool, failedStep: ?string, messages: list<string>}
     */
    public static function apply(SetupState $state, object $authGroups, string $appConfigPath): array
    {
        $dir     = rtrim($appConfigPath, '/\\') . '/';
        $choices = $state->choices();
        $invalid = self::invalidChoice($state, $authGroups);

        if ($invalid !== null) {
            return self::fail($invalid, lang('RoleWarden.setup.invalidSaved', [lang('RoleWarden.setup.' . $invalid . 'Title')]));
        }

        foreach (self::STEPS as $step) {
            if ($state->done($step)) {
                continue;
            }

            try {
                $manual = match ($step) {
                    'migrations' => self::migrate($choices),
                    'roles' => self::roles($choices, $authGroups),
                    'superAdmin' => self::superAdmin($state),
                    'filters' => self::edit($state, $dir, array_intersect_key(self::transforms($choices), ['Filters.php' => 1])),
                    'wiring' => self::edit($state, $dir, array_diff_key(self::transforms($choices), ['Filters.php' => 1])),
                };
            } catch (RestoreFailedException $e) {
                $state->save();

                return self::fail($step, lang('RoleWarden.setup.restoreFailed', [$e->path, $e->backup]));
            } catch (Throwable $e) {
                // The detail (possibly SQL) goes to the log only.
                log_message('error', 'RoleWarden setup: step {step} failed: {message}', ['step' => $step, 'message' => $e->getMessage()]);
                $state->save();

                return self::fail($step, lang('RoleWarden.setup.applyFailed', [lang('RoleWarden.setup.step.' . $step)]));
            }

            if ($manual !== []) {
                $state->save();

                return self::fail($step, lang('RoleWarden.setup.applyManual', [lang('RoleWarden.setup.step.' . $step)]), $manual);
            }

            $state->markDone($step);
            $state->save();
        }

        $state->complete();
        $state->save();
        @unlink(Installer::codeFile()); // as Installer::finish()

        return ['ok' => true, 'failedStep' => null, 'messages' => []];
    }

    /**
     * What apply will do to each host file, without writing: the unified diff, or manual when
     * the file is missing or written in a way ConfigEditor does not recognise.
     *
     * @param array<string, mixed> $choices
     *
     * @return list<array{file: string, diff: string, manual: bool}>
     */
    public static function filePlan(array $choices, string $appConfigPath): array
    {
        $dir = rtrim($appConfigPath, '/\\') . '/';
        $out = [];

        foreach (self::transforms($choices) as $file => $transforms) {
            $before = @file_get_contents($dir . $file);
            [$after, $missing] = $before === false ? ['', [true]] : self::chain($before, $transforms);
            $out[] = ['file' => 'app/Config/' . $file, 'diff' => ConfigEditor::preview((string) $before, $after), 'manual' => $missing !== []];
        }

        return $out;
    }

    /**
     * The roles the choices will leave, for the new sign-ups' role (never super-admin).
     *
     * @param array<string, mixed> $choices
     *
     * @return list<string>
     */
    public static function resultingRoles(array $choices, object $authGroups): array
    {
        $import = ($choices['mode'] ?? null) === 'import';
        $roles  = $import ? ['admin', 'user'] : [];

        foreach (array_map('strval', array_keys($authGroups->groups ?? [])) as $slug) {
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
     * R12: every saved answer checked again against the current state, before anything is written.
     *
     * @return string|null the wizard question to go back to
     */
    private static function invalidChoice(SetupState $state, object $authGroups): ?string
    {
        $c      = $state->choices();
        $groups = array_map('strval', array_keys($authGroups->groups ?? []));
        $mode   = $c['mode'] ?? null;

        if (! in_array($mode, ['adapt', 'import'], true)
            || ($mode === 'adapt' && (in_array('super-admin', $groups, true) || ! in_array($c['panelRole'] ?? '', ['', ...$groups], true)))) {
            return 'roles';
        }

        $taken = [...$groups, ...self::SYSTEM];

        foreach ($mode === 'import' ? array_intersect($groups, self::SYSTEM) : [] as $slug) {
            $collision = $c['collisions'][$slug] ?? [];
            $action    = $collision['action'] ?? null;
            $newSlug   = (string) ($collision['newSlug'] ?? '');

            if (! in_array($action, ['merge', 'rename', 'skip'], true)
                || ($action === 'rename' && (preg_match(self::SLUG, $newSlug) !== 1 || in_array($newSlug, $taken, true)))) {
                return 'collisions';
            }

            $taken[] = $newSlug;
        }

        if (! in_array($c['defaultRole'] ?? null, ['', ...self::resultingRoles($c, $authGroups)], true)) {
            return 'newUsers';
        }

        $admin = $c['superAdmin'] ?? null;

        if (! $state->done('superAdmin') && ! (is_array($admin) && match ($admin['kind'] ?? null) {
            'existing' => in_array($admin['id'] ?? null, array_column(HostScan::activeUsers(), 'id'), true),
            'new' => is_string($admin['username'] ?? null) && is_string($admin['email'] ?? null) && is_string($admin['passwordHash'] ?? null) && $admin['passwordHash'] !== '',
            default => false,
        })) {
            return 'superAdmin';
        }

        return in_array($c['visibility'] ?? null, ['all', 'admins'], true) ? null : 'visibility';
    }

    /**
     * @param array<string, mixed> $choices
     *
     * @return list<string> always empty: a failure throws
     */
    private static function migrate(array $choices): array
    {
        foreach (Installer::NAMESPACES as $namespace) {
            // prepare() writes settings: once their table exists, before RoleWarden's seeder and import run.
            if ($namespace === 'RoleWarden') {
                RoleSetup::prepare($choices);
            }

            if (! service('migrations', null, null, false)->setNamespace($namespace)->latest()) {
                throw new RuntimeException('Migrations of ' . $namespace . ' did not complete.');
            }
        }

        // As Installer::migrate: the system roles even when the module's migrations ran before.
        config('Database')->seeder()->setSilent(true)->call(RoleWardenSeeder::class);
        Readiness::forget();

        return [];
    }

    /**
     * @param array<string, mixed> $choices
     *
     * @return list<string>
     */
    private static function roles(array $choices, object $authGroups): array
    {
        RoleSetup::apply($choices, $authGroups);

        return [];
    }

    /**
     * An existing user gets the super-admin role; a new one is created through Shield from the
     * saved hash (R2), all or nothing, and the hash is then dropped from the choices.
     *
     * @return list<string>
     */
    private static function superAdmin(SetupState $state): array
    {
        $admin = $state->choices()['superAdmin'];
        $db    = db_connect();
        $role  = $db->table(config('RoleWarden')->table('roles'))->select('id')->where('slug', 'super-admin')->get()->getRowArray();

        if ($role === null) {
            throw new InvalidArgumentException('No super-admin role.');
        }

        if ($admin['kind'] === 'existing') {
            (new UserRoles())->assign((int) $admin['id'], (int) $role['id']);

            return [];
        }

        try {
            $db->transException(true)->transStart();
            $users = model(UserModel::class);
            $user  = new User(['username' => $admin['username'], 'email' => $admin['email']]);
            $user->setPasswordHash($admin['passwordHash']);
            $users->save($user);
            $id = (int) $users->getInsertID();
            $users->activate($users->findById($id));
            (new UserRoles())->assign($id, (int) $role['id']);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        } finally {
            $db->transException(false);
        }

        unset($admin['passwordHash']);
        $state->setChoice('superAdmin', $admin + ['id' => $id]);

        return [];
    }

    /**
     * One ConfigEditor::apply per file with all its transforms (R5), the state saved after each.
     * A change already there needs nothing; one that cannot be made (file not writable, shape
     * not recognised, syntax check failed) comes back as text to paste by hand.
     *
     * @param array<string, list<array{Closure, Closure, string}>> $files
     *
     * @return list<string> "app/Config/<file>" and the text to paste, per file left to do by hand
     */
    private static function edit(SetupState $state, string $dir, array $files): array
    {
        $manual = [];

        foreach ($files as $file => $transforms) {
            $path   = $dir . $file;
            $before = @file_get_contents($path);

            if ($before === false) {
                $manual[] = 'app/Config/' . $file . "\n" . implode("\n", array_column($transforms, 2));

                continue;
            }

            [$after, $missing] = self::chain($before, $transforms);

            if ($after !== $before) {
                if (is_writable($path) && ConfigEditor::apply($path, $after, $state)) {
                    $state->save();
                } else {
                    $missing = [ConfigEditor::preview($before, $after), ...$missing];
                }
            }

            if ($missing !== []) {
                $manual[] = 'app/Config/' . $file . "\n" . implode("\n", $missing);
            }
        }

        return $manual;
    }

    /**
     * @param list<array{Closure, Closure, string}> $transforms
     *
     * @return array{string, list<string>} the new content and the manual text of each change it could not make
     */
    private static function chain(string $content, array $transforms): array
    {
        $missing = [];

        foreach ($transforms as [$with, $has, $text]) {
            $new = $with($content);

            if ($new !== null) {
                $content = $new;
            } elseif (! $has($content)) {
                $missing[] = $text;
            }
        }

        return [$content, $missing];
    }

    /**
     * Per host file: each change as [with, has, text to paste by hand].
     *
     * @param array<string, mixed> $choices
     *
     * @return array<string, list<array{Closure, Closure, string}>>
     */
    private static function transforms(array $choices): array
    {
        $auth = [[ConfigEditor::withUserProvider(...), ConfigEditor::hasUserProvider(...), 'public string $userProvider = \RoleWarden\Models\UserModel::class;']];

        if (($choices['visibility'] ?? null) === 'all') {
            $auth[] = [ConfigEditor::withLoginView(...), ConfigEditor::hasLoginView(...), "\$views: 'login' => '\\RoleWarden\\Views\\auth\\login',"];
            $auth[] = [ConfigEditor::withLoginRedirect(...), ConfigEditor::hasLoginRedirect(...), "\$redirects: 'login' => 'rolewarden',"];
        }

        return [
            'Filters.php' => [[
                static fn (string $c): ?string => ConfigEditor::withFilterExcept($c, self::EXCEPTS),
                // No global filter at all is not a failure: there is nothing to except.
                static fn (string $c): bool => ConfigEditor::hasFilterExcept($c, self::EXCEPTS) || ConfigEditor::globalFilterExcepts($c) === null,
                "'except' => ['" . implode("', '", self::EXCEPTS) . "']",
            ]],
            'Auth.php' => $auth,
            'Routes.php' => [[ConfigEditor::withRouteRegistrar(...), ConfigEditor::hasRouteRegistrar(...), '\RoleWarden\Config\RouteRegistrar::register($routes);']],
        ];
    }

    /**
     * @param list<string> $manual
     *
     * @return array{ok: false, failedStep: string, messages: list<string>}
     */
    private static function fail(string $step, string $message, array $manual = []): array
    {
        return ['ok' => false, 'failedStep' => $step, 'messages' => [$message, ...$manual]];
    }
}
