<?php

declare(strict_types=1);

namespace RoleWarden\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserPermissions extends Migration
{
    public function up(): void
    {
        $cfg = config('RoleWarden');

        $this->forge->addField([
            'user_id' => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            'permission_id' => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            // 1 = granted, 0 = denied
            'granted' => ['type' => 'tinyint', 'constraint' => 1],
            'created_at' => ['type' => 'datetime', 'null' => true],
            'updated_at' => ['type' => 'datetime', 'null' => true],
        ]);
        $this->forge->addPrimaryKey(['user_id', 'permission_id']);
        $this->forge->addKey('permission_id');
        $this->forge->addForeignKey('user_id', config('Auth')->tables['users'], 'id', '', 'CASCADE');
        $this->forge->addForeignKey('permission_id', $cfg->table('permissions'), 'id', '', 'CASCADE');
        $this->forge->createTable($cfg->table('user_permissions'));
    }

    public function down(): void
    {
        $this->forge->dropTable(config('RoleWarden')->table('user_permissions'), true);
    }
}
