<?php

declare(strict_types=1);

namespace RoleWarden\Settings;

use RoleWarden\Config\RoleWarden;
use RoleWarden\Install\Installer;
use Throwable;

/**
 * Settings saves every value under the class config('RoleWarden') answers with:
 * this module's until the application adds app/Config/RoleWarden.php,
 * Config\RoleWarden from then on. When that changes, the values saved so far
 * move to the new class, so neither the Settings screen nor anyone's language
 * or light and dark choice goes back to the default.
 */
final class SavedValues
{
    private const CACHE_KEY = 'rolewarden-settings-class';

    public static function follow(): void
    {
        $now = config('RoleWarden')::class;

        if (cache(self::CACHE_KEY) === $now || ! Installer::databaseConfigured()) {
            return;
        }

        $was = $now === RoleWarden::class ? 'Config\RoleWarden' : RoleWarden::class;
        $settings = config('Settings');

        try {
            $db = db_connect($settings->database['group'] ?? null);
            $table = $settings->database['table'] ?? 'settings';

            if (in_array('database', $settings->handlers, true) && $db->tableExists($table)) {
                // A value saved under both classes keeps the one under the current class.
                foreach ($db->table($table)->select('key, context')->where('class', $now)->get()->getResultArray() as $row) {
                    $db->table($table)->where(['class' => $was, 'key' => $row['key'], 'context' => $row['context']])->delete();
                }

                $db->table($table)->where('class', $was)->update(['class' => $now]);
                cache()->save(self::CACHE_KEY, $now, 0);
            }
        } catch (Throwable) {
            // No database yet (the installer) or no settings table: nothing saved to move.
        }
    }
}
