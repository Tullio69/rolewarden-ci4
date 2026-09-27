<?php

declare(strict_types=1);

namespace RoleWarden\Settings;

/**
 * The Sign-in section of the Settings screen. Session lifetime and the lock
 * rules live in CodeIgniter's Settings library under RoleWarden.*, starting
 * from Config\RoleWarden; "Remember me" is Shield's own Auth.sessionConfig,
 * which Shield already reads through setting(), so nothing of Shield is
 * rewritten.
 */
final class SignIn
{
    /** Session lifetime choices, in seconds. */
    public const LIFETIMES = [1800, 7200, 28800, 86400];

    /** "Remember me" choices, in seconds; 0 turns it off. */
    public const REMEMBER = [0, 604800, 2592000, 7776000];

    /** Number settings: post field => [setting key, min, max]. */
    public const NUMBERS = [
        'lock_attempts' => ['RoleWarden.lockAttempts', 3, 20],
        'lock_minutes' => ['RoleWarden.lockMinutes', 1, 1440],
        'sign_in_rate' => ['RoleWarden.signInRate', 5, 100],
    ];

    /**
     * Current values, keyed like the form fields.
     *
     * @return array<string, int>
     */
    public static function values(): array
    {
        helper('setting');
        $session = setting('Auth.sessionConfig');
        $values = [
            'session_lifetime' => (int) setting('RoleWarden.sessionLifetime'),
            'remember_length' => $session['allowRemembering'] ? (int) $session['rememberLength'] : 0,
        ];

        foreach (self::NUMBERS as $field => [$key]) {
            $values[$field] = (int) setting($key);
        }

        return $values;
    }

    /**
     * Validation rules for the form. A value set in code outside the offered
     * choices stays accepted, so saving another field does not reject it.
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        $current = self::values();
        $rules = [
            'session_lifetime' => 'required|in_list[' . implode(',', self::choices(self::LIFETIMES, $current['session_lifetime'])) . ']',
            'remember_length' => 'required|in_list[' . implode(',', self::choices(self::REMEMBER, $current['remember_length'])) . ']',
        ];

        foreach (self::NUMBERS as $field => [, $min, $max]) {
            $rules[$field] = "required|is_natural|greater_than_equal_to[{$min}]|less_than_equal_to[{$max}]";
        }

        return $rules;
    }

    /**
     * @param list<int> $offered
     *
     * @return list<int>
     */
    public static function choices(array $offered, int $current): array
    {
        $choices = in_array($current, $offered, true) ? $offered : [...$offered, $current];
        sort($choices);

        return $choices;
    }

    /**
     * Saves validated values.
     *
     * @param array<string, mixed> $post
     */
    public static function save(array $post): void
    {
        helper('setting');
        setting('RoleWarden.sessionLifetime', (int) $post['session_lifetime']);

        $remember = (int) $post['remember_length'];
        $session = setting('Auth.sessionConfig');
        $session['allowRemembering'] = $remember > 0;

        if ($remember > 0) {
            $session['rememberLength'] = $remember;
        }

        setting('Auth.sessionConfig', $session);

        foreach (self::NUMBERS as $field => [$key]) {
            setting($key, (int) $post[$field]);
        }
    }

    /**
     * Seconds until an email whose failed sign-ins are $failures unlocks; 0
     * when it is not locked. $failures are the timestamps of the failures
     * since its last successful sign-in within the last $minutes. Attempts
     * made while locked are refused before Shield sees them, so they are not
     * recorded, and the lock ends $minutes after the failure that set it.
     *
     * @param list<int> $failures
     */
    public static function lockedFor(array $failures, int $attempts, int $minutes, int $now): int
    {
        if (count($failures) < $attempts) {
            return 0;
        }

        return max(0, max($failures) + $minutes * 60 - $now);
    }
}
