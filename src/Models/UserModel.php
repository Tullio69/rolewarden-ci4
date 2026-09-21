<?php

declare(strict_types=1);

namespace RoleWarden\Models;

use CodeIgniter\Shield\Models\UserModel as ShieldUserModel;
use InvalidArgumentException;
use RoleWarden\Entities\User;

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
