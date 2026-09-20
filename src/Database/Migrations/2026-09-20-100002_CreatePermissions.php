<?php

declare(strict_types=1);

namespace RoleWarden\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePermissions extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'slug' => ['type' => 'varchar', 'constraint' => 100],
            'area' => ['type' => 'varchar', 'constraint' => 50],
            'description' => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'is_system' => ['type' => 'tinyint', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'datetime', 'null' => true],
            'updated_at' => ['type' => 'datetime', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey('area');
        $this->forge->createTable(config('RoleWarden')->table('permissions'));
    }

    public function down(): void
    {
        $this->forge->dropTable(config('RoleWarden')->table('permissions'), true);
    }
}
