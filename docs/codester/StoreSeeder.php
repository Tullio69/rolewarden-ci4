<?php
namespace App\Database\Seeds;
use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
class StoreSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['maya.lindqvist', 'maya@northwind.test', 'admin'],
            ['daniel.okafor', 'daniel@northwind.test', 'manager'],
            ['sofia.marquez', 'sofia@northwind.test', 'manager'],
            ['liam.chen', 'liam@northwind.test', 'support'],
            ['amara.nwosu', 'amara@northwind.test', 'support'],
            ['noah.berg', 'noah@northwind.test', 'support'],
            ['emma.rossi', 'emma@example.test', 'customer'],
            ['lucas.meyer', 'lucas@example.test', 'customer'],
            ['hana.sato', 'hana@example.test', 'customer'],
            ['oliver.grant', 'oliver@example.test', 'customer'],
            ['zoe.martin', 'zoe@example.test', 'customer'],
        ];
        $model = model(UserModel::class);
        foreach ($users as [$u, $e, $g]) {
            $user = new User(['username' => $u, 'email' => $e, 'password' => 'Shots-Pass-2026!']);
            $model->save($user);
            $user = $model->findById($model->getInsertID());
            $user->activate();
            $user->addGroup($g);
        }
        // A direct Shield permission on one user, so the wizard shows its warning.
        $model->findById(4)->addPermission('reports.view');
    }
}
