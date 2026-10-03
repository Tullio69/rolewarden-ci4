<?php

declare(strict_types=1);

namespace RoleWarden\Settings;

/**
 * Light or dark, chosen by each user from the top bar and kept by
 * CodeIgniter's Settings library in that user's context ("user:{id}").
 * "system" (the default) follows the browser's prefers-color-scheme.
 */
final class ColorScheme
{
    public const KEY = 'RoleWarden.colorScheme';
    public const CHOICES = ['system', 'light', 'dark'];

    public static function forUser(?int $userId): string
    {
        if ($userId === null) {
            return 'system';
        }

        helper('setting');
        $value = setting()->get(self::KEY, 'user:' . $userId);

        return in_array($value, self::CHOICES, true) ? $value : 'system';
    }

    public static function set(int $userId, string $choice): void
    {
        helper('setting');
        setting()->set(self::KEY, $choice, 'user:' . $userId);
    }
}
