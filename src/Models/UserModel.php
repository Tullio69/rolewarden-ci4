<?php

declare(strict_types=1);

namespace RoleWarden\Models;

use CodeIgniter\Config\Factories;
use CodeIgniter\Shield\Entities\User as ShieldUser;
use CodeIgniter\Shield\Models\UserModel as ShieldUserModel;
use InvalidArgumentException;
use RoleWarden\Entities\User;
use RoleWarden\Settings\DefaultRole;

/**
 * Set as Config\Auth::$userProvider in the host application. Deleting or
 * deactivating a user goes through the last-super-admin protection, and any
 * update or delete drops that user's cached permissions.
 */
class UserModel extends ShieldUserModel
{
    protected $returnType = User::class;
    protected $beforeUpdate = ['guardDeactivate'];
    protected $beforeDelete = ['guardDelete'];
    protected $afterUpdate = ['saveEmailIdentity', 'forgetUsers'];
    protected $afterDelete = ['forgetUsers'];

    /**
     * When the host made this model Shield's userProvider, model(ShieldUserModel::class)
     * returns it too, so users loaded through Shield's model get RoleWarden's entity.
     * Covers model()/Factories only, not a direct `new ShieldUserModel()`.
     * Called from Config/Events.php on pre_system (web) and pre_command (spark).
     */
    public static function replaceShieldModel(): void
    {
        $provider = config('Auth')->userProvider;

        if (! is_a($provider, self::class, true)) {
            return;
        }

        try {
            Factories::define('models', ShieldUserModel::class, $provider);
        } catch (InvalidArgumentException $e) {
            // Something resolved Shield's model before us: keep the app running.
            log_message('warning', 'RoleWarden: ' . $e->getMessage());
        }
    }

    /**
     * Shield's version reads auth_groups_users; here the default is a RoleWarden role.
     */
    public function addToDefaultGroup(ShieldUser $user): void
    {
        DefaultRole::assignTo((int) $user->id);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function guardDeactivate(array $data): array
    {
        // Require the key before the write: afterUpdate would only fail once the rows changed.
        $ids = $this->ids($data);

        if (array_key_exists('active', $data['data'] ?? []) && ! (bool) $data['data']['active']) {
            foreach ($ids as $id) {
                service('rolewardenGuard')->assertUserRemovable($id);
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function guardDelete(array $data): array
    {
        foreach ($this->ids($data) as $id) {
            service('rolewardenGuard')->assertUserRemovable($id);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function forgetUsers(array $data): array
    {
        service('rolewarden')->forgetUsers($this->ids($data));

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<int>
     */
    private function ids(array $data): array
    {
        $ids = $data['id'] ?? null;

        if ($ids === null || $ids === []) {
            throw new InvalidArgumentException('Users must be written by primary key.');
        }

        return array_map('intval', (array) $ids);
    }
}
