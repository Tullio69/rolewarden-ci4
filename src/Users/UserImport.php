<?php

declare(strict_types=1);

namespace RoleWarden\Users;

use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Authentication\Passwords;
use CodeIgniter\Shield\Models\UserIdentityModel;
use RoleWarden\Account\ActivityLog;
use RoleWarden\Entities\User;
use RoleWarden\Models\RoleModel;
use RoleWarden\Models\UserModel;
use RoleWarden\Models\UserRoles;
use RoleWarden\Settings\DefaultRole;
use Throwable;

/**
 * Users CSV import in two steps (SPEC, V6 decisions). preview() judges every
 * row and returns either errors per row, in which case the whole file is
 * refused, or the rows ready to create, passwords already hashed by Shield.
 * create() makes all of them in one transaction, or none.
 */
final class UserImport
{
    /**
     * @return array{
     *   fileErrors: list<array{0: string, 1: list<int|string>}>,
     *   rows: list<array{line: int, email: string, username: string, role: string, hasPassword: bool, errors: list<string>}>,
     *   payload: list<array{email: string, username: string, roleId: int|null, roleName: string, passwordHash: string|null}>
     * }
     */
    public static function preview(string $content): array
    {
        $parsed = CsvImport::parse($content);

        if ($parsed['errors'] !== []) {
            return ['fileErrors' => $parsed['errors'], 'rows' => [], 'payload' => []];
        }

        $auth = config('Auth');
        $defaultRole = DefaultRole::role();
        $roles = [];

        foreach (model(RoleModel::class)->findAll() as $role) {
            $roles[strtolower((string) $role['slug'])] = $role;
        }

        $emails = array_map(static fn (array $r): string => strtolower($r['values']['email']), $parsed['rows']);
        $usernames = array_map(static fn (array $r): string => strtolower($r['values']['username']), $parsed['rows']);
        $takenEmails = self::existing($auth->tables['identities'], 'secret', $emails, ['type' => Session::ID_TYPE_EMAIL_PASSWORD]);
        $takenUsernames = self::existing($auth->tables['users'], 'username', $usernames);
        $emailCount = array_count_values($emails);
        $usernameCount = array_count_values($usernames);

        $rows = [];
        $payload = [];

        foreach ($parsed['rows'] as $row) {
            $v = $row['values'];
            $errors = [];

            if (isset($v['_extra'])) {
                $errors[] = lang('RoleWarden.panel.import.extraFields', [(int) $v['_extra']]);
            }

            $validation = service('validation', null, false);
            $rules = [
                'email' => $auth->emailValidationRules,
                'username' => $auth->usernameValidationRules,
            ];

            if (($v['password'] ?? '') !== '') {
                $rules['password'] = ['label' => 'Auth.password', 'rules' => ['required', Passwords::getMaxLengthRule()]];
            }

            if (! $validation->setRules($rules)->run(['email' => $v['email'], 'username' => $v['username'], 'password' => $v['password'] ?? ''])) {
                $errors = [...$errors, ...array_values($validation->getErrors())];
            }

            // Shield's own strength check, with the row's user as context so that a password
            // built from the username or email is refused, exactly as on Shield's registration.
            if (($v['password'] ?? '') !== '' && ! isset($validation->getErrors()['password'])) {
                $check = service('passwords')->check($v['password'], new User(['username' => $v['username'], 'email' => $v['email']]));

                if (! $check->isOK()) {
                    $errors[] = (string) $check->reason();
                }
            }

            $email = strtolower($v['email']);
            $username = strtolower($v['username']);

            if ($email !== '' && isset($takenEmails[$email])) {
                $errors[] = lang('RoleWarden.panel.import.emailTaken');
            } elseif ($email !== '' && $emailCount[$email] > 1) {
                $errors[] = lang('RoleWarden.panel.import.emailTwice');
            }

            if ($username !== '' && isset($takenUsernames[$username])) {
                $errors[] = lang('RoleWarden.panel.import.usernameTaken');
            } elseif ($username !== '' && $usernameCount[$username] > 1) {
                $errors[] = lang('RoleWarden.panel.import.usernameTwice');
            }

            $slug = strtolower($v['role'] ?? '');
            $role = $slug === '' ? $defaultRole : ($roles[$slug] ?? null);

            if ($slug !== '' && $role === null) {
                $errors[] = lang('RoleWarden.panel.import.roleMissing', [$v['role']]);
            } elseif ($role !== null && (int) $role['is_super_admin'] === 1) {
                $errors[] = lang('RoleWarden.panel.import.roleSuperAdmin');
            }

            $rows[] = [
                'line' => $row['line'],
                'email' => $v['email'],
                'username' => $v['username'],
                'role' => $role['name'] ?? '',
                'hasPassword' => ($v['password'] ?? '') !== '',
                'errors' => $errors,
            ];

            if ($errors === []) {
                $payload[] = [
                    'email' => $v['email'],
                    'username' => $v['username'],
                    'roleId' => $role !== null ? (int) $role['id'] : null,
                    'roleName' => $role['name'] ?? '',
                    'passwordHash' => ($v['password'] ?? '') !== '' ? service('passwords')->hash($v['password']) : null,
                ];
            }
        }

        $valid = array_filter($rows, static fn (array $r): bool => $r['errors'] !== []) === [];

        return ['fileErrors' => [], 'rows' => $rows, 'payload' => $valid ? $payload : []];
    }

    /**
     * Creates every user of a valid preview, or none: one transaction, and
     * uniqueness checked again since someone may have created a user meanwhile.
     *
     * @param list<array{email: string, username: string, roleId: int|null, roleName: string, passwordHash: string|null}> $payload
     *
     * @return int|string the number created, or the reason nothing was (a RoleWarden.panel.import.* key)
     */
    public static function create(array $payload)
    {
        $auth = config('Auth');
        $emails = array_map(static fn (array $r): string => strtolower($r['email']), $payload);
        $usernames = array_map(static fn (array $r): string => strtolower($r['username']), $payload);

        if (self::existing($auth->tables['identities'], 'secret', $emails, ['type' => Session::ID_TYPE_EMAIL_PASSWORD]) !== []
            || self::existing($auth->tables['users'], 'username', $usernames) !== []) {
            return 'takenSince';
        }

        $db = db_connect();
        $users = model(UserModel::class);
        // Rows without a password share one hash of a random secret that is thrown away:
        // nobody can sign in with it, and hashing once keeps a large import fast.
        $unusable = service('passwords')->hash(bin2hex(random_bytes(32)));

        try {
            $db->transException(true)->transStart();

            $identities = model(UserIdentityModel::class);

            foreach ($payload as $row) {
                // The user row first, already active and without an email, so Shield does not
                // create the email identity itself: it would hash an empty password for each
                // user (about 0.2 s) before the real hash replaced it.
                $users->save(new User(['username' => $row['username'], 'active' => 1]));
                $id = (int) $users->getInsertID();

                // Then the email identity through Shield's own model, with the hash Shield made
                // at preview time. No password in the file: the shared unusable hash, and the
                // user signs in through Shield's password recovery or gets one from an admin.
                $identities->insert([
                    'user_id' => $id,
                    'type' => Session::ID_TYPE_EMAIL_PASSWORD,
                    'secret' => $row['email'],
                    'secret2' => $row['passwordHash'] ?? $unusable,
                ]);

                if ($row['roleId'] !== null) {
                    (new UserRoles())->assign($id, $row['roleId']);
                }

                ActivityLog::record('user.imported', 'user', $id, $row['username'], $row['roleName'] !== '' ? ['role' => $row['roleName']] : []);
            }

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'RoleWarden: users import rolled back: {message}', ['message' => $e->getMessage()]);

            return 'failed';
        }

        return count($payload);
    }

    /**
     * Values of $column already in $table, among $values (case-insensitive), as value => true.
     *
     * @param list<string>         $values
     * @param array<string, mixed> $where
     *
     * @return array<string, true>
     */
    private static function existing(string $table, string $column, array $values, array $where = []): array
    {
        $values = array_values(array_unique(array_filter($values, static fn (string $v): bool => $v !== '')));

        if ($values === []) {
            return [];
        }

        $found = [];

        foreach (array_chunk($values, 500) as $chunk) {
            $builder = db_connect()->table($table)->select($column)->where($where)->whereIn($column, $chunk);

            foreach ($builder->get()->getResultArray() as $row) {
                $found[strtolower((string) $row[$column])] = true;
            }
        }

        return $found;
    }
}
