<?php

declare(strict_types=1);

namespace RoleWarden\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Signed-in sessions, one row per browser, so they can be listed and revoked
 * (RoleWarden\Account\Sessions). token_hash is the SHA-256 of a random token
 * kept in the session itself: CodeIgniter rotates the session id, the token
 * stays. remember_selector links the row to the Shield remember-me token that
 * browser holds, so revoking one revokes the other.
 */
class CreateSessions extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            'token_hash' => ['type' => 'char', 'constraint' => 64],
            'remember_selector' => ['type' => 'varchar', 'constraint' => 24, 'null' => true],
            'ip_address' => ['type' => 'varchar', 'constraint' => 45],
            'user_agent' => ['type' => 'varchar', 'constraint' => 255],
            'created_at' => ['type' => 'datetime'],
            'last_seen_at' => ['type' => 'datetime'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey('user_id');
        $this->forge->addKey('remember_selector');
        $this->forge->addForeignKey('user_id', config('Auth')->tables['users'], 'id', '', 'CASCADE');
        $this->forge->createTable(config('RoleWarden')->table('sessions'));
    }

    public function down(): void
    {
        $this->forge->dropTable(config('RoleWarden')->table('sessions'), true);
    }
}
