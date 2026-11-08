<?php

declare(strict_types=1);

namespace RoleWarden\Install;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use Throwable;
use RoleWarden\Database\Seeds\RoleWardenSeeder;
use RoleWarden\Settings\DefaultRole;
use RoleWarden\Settings\PanelRole;
use RoleWarden\Settings\Visibility;

/**
 * Applies the wizard's role choices: "adapt" turns every host group into a role and makes
 * one of them the panel role; "import" keeps the system roles and resolves each group that
 * collides with one (merge, rename, skip). Same slug and permission rules as ImportAuthGroups.
 */
final class RoleSetup
{
    /** Same rule as the panel's role form. */
    private const ROLE_SLUG = '/^[a-z][a-z0-9-]*$/';

    /** Before the migrations: the import is left to apply(), and "adapt" seeds no admin/user. */
    public static function prepare(array $choices): void
    {
        helper('setting');
        setting(GroupImport::DEFERRED, true);
        setting(RoleWardenSeeder::DEFAULT_ROLES_KEY, ($choices['mode'] ?? null) === 'import');
    }

    /**
     * After the migrations. Idempotent, and all or nothing.
     *
     * @param array<string, mixed> $choices as saved by SetupState
     *
     * @throws InvalidArgumentException on a group named like a system role without an explicit choice, a merge
     *                                  without a role to merge into, or a rename to a slug that is invalid or taken
     */
    public static function apply(array $choices, object $authGroups): void
    {
        $import     = ($choices['mode'] ?? null) === 'import';
        $collisions = $import ? ($choices['collisions'] ?? []) : [];
        $groups     = $authGroups->groups ?? [];
        $db         = db_connect();

        self::assertCollisions($db, $import, $collisions, array_map('strval', array_keys($groups)));

        // transException() only rolls back on a failed query: anything else thrown inside
        // must roll back here, and the shared connection must not keep throwing afterwards.
        $touched = [];

        try {
            $db->transException(true)->transStart();

            $permissionIds = GroupImport::importPermissions($db, $authGroups);

            foreach ($groups as $group => $info) {
                $group  = (string) $group;
                $roleId = match ($collisions[$group]['action'] ?? null) {
                    'skip' => null,
                    'merge' => self::roleId($db, $group),
                    'rename' => self::ensureRole($db, $collisions[$group]['newSlug'], $info),
                    default => self::ensureRole($db, $group, $info),
                };

                if ($roleId === null) {
                    continue;
                }

                GroupImport::grant($db, $roleId, $authGroups->matrix[$group] ?? [], $permissionIds);
                GroupImport::addMembers($db, $roleId, $group);
                $touched[] = $roleId;
            }

            helper('setting');

            if (! $import) {
                setting(PanelRole::KEY, (string) ($choices['panelRole'] ?? ''));
                $panelId = PanelRole::roleId();

                if ($panelId !== null) {
                    $slugs  = RoleWardenSeeder::slugs();
                    $rows   = $db->table(config('RoleWarden')->table('permissions'))->select('id, slug')->whereIn('slug', $slugs)->get()->getResultArray();
                    GroupImport::grant($db, $panelId, $slugs, array_map('intval', array_column($rows, 'id', 'slug')));
                    $touched[] = $panelId;
                }
            }

            setting(DefaultRole::KEY, (string) ($choices['defaultRole'] ?? ''));
            setting(Visibility::KEY, Visibility::normalize($choices['visibility'] ?? null));
            setting(GroupImport::DEFERRED, false);

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        } finally {
            $db->transException(false);
        }

        foreach (array_unique($touched) as $roleId) {
            service('rolewarden')->forgetRole($roleId);
        }
    }

    /**
     * Checked before anything is written.
     *
     * @param array<string, array{action?: string, newSlug?: string}> $collisions
     * @param list<string> $groups host group slugs
     */
    private static function assertCollisions(BaseConnection $db, bool $import, array $collisions, array $groups): void
    {
        // A group named like a system role needs an explicit choice (only import mode offers
        // one): left alone, its members would silently lose what the group gave them.
        foreach ($groups as $group) {
            $action = $collisions[$group]['action'] ?? null;

            if (self::isSystem($db, $group) && (! $import || ! in_array($action, ['merge', 'rename', 'skip'], true))) {
                throw new InvalidArgumentException("Group \"{$group}\" names a system role and needs a merge, rename or skip choice.");
            }
        }

        $targets = [];

        foreach ($collisions as $group => $collision) {
            $action = $collision['action'] ?? null;

            if ($action === 'merge' && self::roleId($db, (string) $group) === null) {
                throw new InvalidArgumentException("No role \"{$group}\" to merge into.");
            }

            if ($action !== 'rename') {
                continue;
            }

            $slug = (string) ($collision['newSlug'] ?? '');

            // Free means: not another host group, not chosen twice, not a system role. A
            // non-system role with that slug is this method's own earlier run, reused.
            if (preg_match(self::ROLE_SLUG, $slug) !== 1 || in_array($slug, $groups, true) || in_array($slug, $targets, true) || self::isSystem($db, $slug)) {
                throw new InvalidArgumentException("Role slug \"{$slug}\" is invalid or taken.");
            }

            $targets[] = $slug;
        }
    }

    /**
     * The role for a group: created, or the one an earlier run created. A system role
     * is never taken over this way (only an explicit merge does that).
     *
     * @param array<string, mixed> $info
     */
    private static function ensureRole(BaseConnection $db, string $slug, array $info): ?int
    {
        return GroupImport::createRole($db, $slug, $info) ?? (self::isSystem($db, $slug) ? null : self::roleId($db, $slug));
    }

    private static function roleId(BaseConnection $db, string $slug): ?int
    {
        $row = $db->table(config('RoleWarden')->table('roles'))->select('id')->where('slug', $slug)->get()->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }

    private static function isSystem(BaseConnection $db, string $slug): bool
    {
        return $db->table(config('RoleWarden')->table('roles'))->where('slug', $slug)->where('is_system', 1)->countAllResults() > 0;
    }
}
