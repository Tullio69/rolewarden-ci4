<?php

declare(strict_types=1);

namespace RoleWardenDemo\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The demo's only table (SPEC, V8 decisions): documents that one role reads,
 * another writes, a third approves and deletes.
 */
class CreateDocuments extends Migration
{
    public function up(): void
    {
        $users = config('Auth')->tables['users'];

        $this->forge->addField([
            'id' => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'title' => ['type' => 'varchar', 'constraint' => 160],
            'body' => ['type' => 'text'],
            'status' => ['type' => 'varchar', 'constraint' => 16, 'default' => 'draft'],
            'owner_id' => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            'approved_by' => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'approved_at' => ['type' => 'datetime', 'null' => true],
            'created_at' => ['type' => 'datetime', 'null' => true],
            'updated_at' => ['type' => 'datetime', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('status');
        $this->forge->addKey('owner_id');
        $this->forge->addForeignKey('owner_id', $users, 'id', '', 'CASCADE');
        $this->forge->addForeignKey('approved_by', $users, 'id', '', 'SET NULL');
        $this->forge->createTable(config('Demo')->table);
    }

    public function down(): void
    {
        $this->forge->dropTable(config('Demo')->table, true);
    }
}
