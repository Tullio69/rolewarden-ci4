<?php

declare(strict_types=1);

namespace RoleWardenDemo;

use CodeIgniter\Database\Config as Database;
use CodeIgniter\Shield\Entities\User;
use RoleWarden\Models\UserRoles;
use RuntimeException;

/**
 * Installs and removes the demo (SPEC, V8 decisions). Everything the demo
 * adds is listed here, so removing it leaves the application exactly as it
 * was: its table and migration rows, its roles and permissions, its accounts
 * with their sessions, sign-ins and log entries, and its settings.
 */
final class DemoInstaller
{
    public const NAMESPACE = 'RoleWardenDemo';

    /** action => description */
    public const PERMISSIONS = [
        'view' => 'See the demo documents',
        'create' => 'Write demo documents',
        'update' => 'Edit and submit your own demo documents',
        'approve' => 'Approve submitted demo documents',
        'delete' => 'Delete any demo document',
    ];

    /** slug => [name, description, parent slug, own actions] */
    public const ROLES = [
        'demo-reader' => ['Reader', 'Reads the demo documents.', null, ['view']],
        'demo-author' => ['Author', 'Writes demo documents and edits their own.', 'demo-reader', ['create', 'update']],
        'demo-approver' => ['Approver', 'Approves and deletes demo documents.', 'demo-author', ['approve', 'delete']],
    ];

    /** email => [username, role slug] */
    public const ACCOUNTS = [
        'reader@example.com' => ['demo-reader', 'demo-reader'],
        'author@example.com' => ['demo-author', 'demo-author'],
        'approver@example.com' => ['demo-approver', 'demo-approver'],
    ];

    public static function markerFile(): string
    {
        return WRITEPATH . 'rolewarden/demo-installed';
    }

    public static function installed(): bool
    {
        return is_file(self::markerFile());
    }

    /**
     * @throws RuntimeException when the demo or one of its accounts, roles or permissions already exists
     */
    public static function install(string $password): void
    {
        helper('setting');
        $db = db_connect();
        $cfg = config('RoleWarden');
        $identities = config('Auth')->tables['identities'];

        if (self::installed() || $db->tableExists(config('Demo')->table)) {
            throw new RuntimeException('The demo is already installed.');
        }

        foreach ([[$identities, 'secret', array_keys(self::ACCOUNTS)], [$cfg->table('roles'), 'slug', array_keys(self::ROLES)], [$cfg->table('permissions'), 'slug', self::permissionSlugs()]] as [$table, $column, $values]) {
            if ($db->table($table)->whereIn($column, $values)->countAllResults() > 0) {
                throw new RuntimeException("Something named like the demo already exists in {$table}: remove it first.");
            }
        }

        service('migrations', null, null, false)->setNamespace(self::NAMESPACE)->latest();

        $now = date('Y-m-d H:i:s');
        $permissionIds = [];

        foreach (self::PERMISSIONS as $action => $description) {
            $db->table($cfg->table('permissions'))->insert(['slug' => "documents.{$action}", 'area' => 'documents', 'description' => $description, 'is_system' => 0, 'created_at' => $now, 'updated_at' => $now]);
            $permissionIds[$action] = (int) $db->insertID();
        }

        $roleIds = [];

        foreach (self::ROLES as $slug => [$name, $description, $parent, $actions]) {
            $db->table($cfg->table('roles'))->insert(['slug' => $slug, 'name' => $name, 'description' => $description, 'parent_id' => $parent !== null ? $roleIds[$parent] : null, 'is_system' => 0, 'is_super_admin' => 0, 'created_at' => $now, 'updated_at' => $now]);
            $roleIds[$slug] = (int) $db->insertID();

            foreach ($actions as $action) {
                $db->table($cfg->table('role_permissions'))->insert(['role_id' => $roleIds[$slug], 'permission_id' => $permissionIds[$action]]);
            }
        }

        $users = auth()->getProvider();
        $userIds = [];

        foreach (self::ACCOUNTS as $email => [$username, $role]) {
            $users->save(new User(['username' => $username, 'email' => $email, 'password' => $password]));
            $id = (int) $users->getInsertID();
            $users->activate($users->findById($id));
            (new UserRoles())->assign($id, $roleIds[$role]);
            $userIds[$role] = $id;
        }

        self::sampleDocuments($userIds['demo-author'], $userIds['demo-approver']);
        setting('Demo.showcaseUsers', array_values($userIds));

        if (! is_dir(dirname(self::markerFile()))) {
            mkdir(dirname(self::markerFile()), 0775, true);
        }

        file_put_contents(self::markerFile(), date('c') . "\n");
    }

    public static function uninstall(): void
    {
        helper('setting');
        $db = db_connect();
        $cfg = config('RoleWarden');
        $auth = config('Auth');
        $resolver = service('rolewarden');

        $userIds = array_map('intval', array_column($db->table($auth->tables['identities'])->select('user_id')->where('type', 'email_password')->whereIn('secret', array_keys(self::ACCOUNTS))->get()->getResultArray(), 'user_id'));
        $roleIds = array_map('intval', array_column($db->table($cfg->table('roles'))->select('id')->whereIn('slug', array_keys(self::ROLES))->get()->getResultArray(), 'id'));
        $permissionIds = array_map('intval', array_column($db->table($cfg->table('permissions'))->select('id')->whereIn('slug', self::permissionSlugs())->get()->getResultArray(), 'id'));

        // Whoever held a demo role or an override on a demo permission loses it: forget their cached permissions.
        foreach ($roleIds as $roleId) {
            $resolver->forgetRole($roleId);
        }

        if ($permissionIds !== []) {
            $resolver->forgetUsers(array_map('intval', array_column($db->table($cfg->table('user_permissions'))->select('user_id')->whereIn('permission_id', $permissionIds)->get()->getResultArray(), 'user_id')));
        }

        if ($userIds !== []) {
            if ($db->tableExists($cfg->table('activity_log'))) {
                $db->table($cfg->table('activity_log'))->groupStart()->whereIn('actor_id', $userIds)->orGroupStart()->where('subject_type', 'user')->whereIn('subject_id', $userIds)->groupEnd()->groupEnd()->delete();
            }

            if ($db->tableExists($cfg->table('sessions'))) {
                $db->table($cfg->table('sessions'))->whereIn('user_id', $userIds)->delete();
            }

            $db->table($auth->tables['logins'])->whereIn('identifier', array_keys(self::ACCOUNTS))->delete();
            $db->table(config('Settings')->database['table'])->whereIn('context', array_map(static fn (int $id): string => 'user:' . $id, $userIds))->delete();

            foreach ($userIds as $id) {
                auth()->getProvider()->delete($id, true);
            }
        }

        // Children first: a role's parent_id points at the one before it.
        foreach (array_reverse(array_keys(self::ROLES)) as $slug) {
            $db->table($cfg->table('roles'))->where('slug', $slug)->delete();
        }

        $db->table($cfg->table('permissions'))->whereIn('slug', self::permissionSlugs())->delete();
        $db->table(config('Migrations')->table)->where('namespace', self::NAMESPACE)->delete();
        Database::forge()->dropTable(config('Demo')->table, true);
        setting()->forget('Demo.showcaseUsers');
        @unlink(self::markerFile());
    }

    /**
     * @return list<string>
     */
    public static function permissionSlugs(): array
    {
        return array_map(static fn (string $action): string => "documents.{$action}", array_keys(self::PERMISSIONS));
    }

    private static function sampleDocuments(int $author, int $approver): void
    {
        $documents = model(Models\DocumentModel::class);
        $samples = [
            ['Travel policy 2026', "Economy class for flights under six hours.\nReceipts within thirty days.", 'approved', $author],
            ['Onboarding checklist', "Laptop, badge and accounts ready on day one.\nA buddy for the first two weeks.", 'submitted', $author],
            ['Meeting room etiquette', 'Book the room, start on time, leave it as you found it.', 'draft', $author],
            ['Security reminders', 'Lock your screen. Report phishing. Never share your password.', 'approved', $approver],
        ];

        foreach ($samples as [$title, $body, $status, $owner]) {
            $documents->insert([
                'title' => $title,
                'body' => $body,
                'status' => $status,
                'owner_id' => $owner,
                'approved_by' => $status === 'approved' ? $approver : null,
                'approved_at' => $status === 'approved' ? date('Y-m-d H:i:s') : null,
            ]);
        }
    }
}
