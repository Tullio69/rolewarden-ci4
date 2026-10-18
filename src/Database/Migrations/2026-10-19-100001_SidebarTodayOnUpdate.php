<?php

declare(strict_types=1);

namespace RoleWarden\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * v1.1: the sidebar's "Today" panel starts on for a new installation and off
 * for one updated from 1.0, so an update does not change how the sidebar
 * looks. An installation that already has users is an update: the installer
 * runs the migrations before it creates the first super admin.
 */
class SidebarTodayOnUpdate extends Migration
{
    private const KEY = 'RoleWarden.sidebarToday';

    public function up(): void
    {
        if ($this->db->table(config('Auth')->tables['users'])->countAllResults() > 0) {
            helper('setting');
            setting(self::KEY, false);
        }
    }

    public function down(): void
    {
        helper('setting');
        setting()->forget(self::KEY);
    }
}
