<?php

declare(strict_types=1);

namespace RoleWarden\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRolePermissions extends Migration
{
    public function up(): void
    {
        $cfg = config('RoleWarden');

        $this->forge->addField([
            'role_id' => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            'permission_id' => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey(['role_id', 'permission_id']);
        $this->forge->addKey('permission_id');
        $this->forge->addForeignKey('role_id', $cfg->table('roles'), 'id', '', 'CASCADE');
        $this->forge->addForeignKey('permission_id', $cfg->table('permissions'), 'id', '', 'CASCADE');
        $this->forge->createTable($cfg->table('role_permissions'));
    }

    public function down(): void
    {
        $this->forge->dropTable(config('RoleWarden')->table('role_permissions'), true);
    }
}
