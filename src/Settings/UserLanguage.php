<?php

declare(strict_types=1);

namespace RoleWarden\Settings;

use Locale;

/**
 * The panel language chosen by each user on their profile, kept by
 * CodeIgniter's Settings library in that user's context ("user:{id}").
 * Empty (the default) follows the application's own locale. The choices are
 * the locales with a RoleWarden language file, in the module or in the
 * application (app/Language/{locale}/RoleWarden.php).
 */
final class UserLanguage
{
    public const KEY = 'RoleWarden.locale';

    /**
     * @return array<string, string> locale => its name in that language
     */
    public static function available(): array
    {
        $files = [...glob(__DIR__ . '/../Language/*/RoleWarden.php') ?: [], ...glob(APPPATH . 'Language/*/RoleWarden.php') ?: []];
        $names = [];

        foreach ($files as $file) {
            $locale = basename(dirname($file));
            $names[$locale] = mb_convert_case(Locale::getDisplayName($locale, $locale), MB_CASE_TITLE, 'UTF-8');
        }

        ksort($names);

        return $names;
    }

    public static function forUser(?int $userId): string
    {
        if ($userId === null) {
            return '';
        }

        helper('setting');
        $value = setting()->get(self::KEY, 'user:' . $userId);

        return is_string($value) && isset(self::available()[$value]) ? $value : '';
    }

    public static function set(int $userId, string $locale): void
    {
        helper('setting');
        setting()->set(self::KEY, $locale, 'user:' . $userId);
    }

    /**
     * Switches lang() and date formatting to $locale; '' leaves them alone.
     */
    public static function use(string $locale): void
    {
        if ($locale !== '') {
            service('language')->setLocale($locale);
            Locale::setDefault($locale);
        }
    }
}
