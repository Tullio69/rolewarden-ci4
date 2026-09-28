<?php

declare(strict_types=1);

namespace RoleWarden\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Who changed what, on which object, when (RoleWarden\Account\ActivityLog).
 * The actor and subject names are copied into the row, so the history stays
 * readable after they are renamed or deleted; actor_id is set to NULL then.
 * Sign-ins are not here: Shield already records them in auth_logins.
 */
class CreateActivityLog extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'actor_id' => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'actor_label' => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'action' => ['type' => 'varchar', 'constraint' => 64],
            'subject_type' => ['type' => 'varchar', 'constraint' => 32],
            'subject_id' => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'subject_label' => ['type' => 'varchar', 'constraint' => 255],
            'details' => ['type' => 'text', 'null' => true],
            'ip_address' => ['type' => 'varchar', 'constraint' => 45, 'null' => true],
            'created_at' => ['type' => 'datetime'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('created_at');
        $this->forge->addKey('actor_id');
        $this->forge->addKey('action');
        $this->forge->addKey(['subject_type', 'subject_id']);
        $this->forge->addForeignKey('actor_id', config('Auth')->tables['users'], 'id', '', 'SET NULL');
        $this->forge->createTable(config('RoleWarden')->table('activity_log'));
    }

    public function down(): void
    {
        $this->forge->dropTable(config('RoleWarden')->table('activity_log'), true);
    }
}
