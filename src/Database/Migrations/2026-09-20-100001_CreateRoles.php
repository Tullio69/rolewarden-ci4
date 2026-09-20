<?php

declare(strict_types=1);

namespace RoleWarden\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRoles extends Migration
{
    public function up(): void
    {
        $table = config('RoleWarden')->table('roles');

        $this->forge->addField([
            'id' => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'slug' => ['type' => 'varchar', 'constraint' => 100],
            'name' => ['type' => 'varchar', 'constraint' => 150],
            'description' => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'parent_id' => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'is_system' => ['type' => 'tinyint', 'constraint' => 1, 'default' => 0],
            'is_super_admin' => ['type' => 'tinyint', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'datetime', 'null' => true],
            'updated_at' => ['type' => 'datetime', 'null' => true],
            'deleted_at' => ['type' => 'datetime', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        // ponytail: slug is unique across soft-deleted rows too, so a deleted role's slug cannot be reused.
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey('parent_id');
        $this->forge->addForeignKey('parent_id', $table, 'id', '', 'SET NULL');
        $this->forge->createTable($table);
    }

    public function down(): void
    {
        $this->forge->dropTable(config('RoleWarden')->table('roles'), true);
    }
}
