<?php

declare(strict_types=1);

namespace RoleWarden\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserRoles extends Migration
{
    public function up(): void
    {
        $cfg = config('RoleWarden');

        $this->forge->addField([
            'user_id' => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            'role_id' => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey(['user_id', 'role_id']);
        $this->forge->addKey('role_id');
        $this->forge->addForeignKey('user_id', config('Auth')->tables['users'], 'id', '', 'CASCADE');
        $this->forge->addForeignKey('role_id', $cfg->table('roles'), 'id', '', 'CASCADE');
        $this->forge->createTable($cfg->table('user_roles'));
    }

    public function down(): void
    {
        $this->forge->dropTable(config('RoleWarden')->table('user_roles'), true);
    }
}
