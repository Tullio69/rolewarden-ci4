<?php

declare(strict_types=1);

namespace RoleWarden\Dashboard;

/**
 * Which dashboard blocks show, in what order, and the period (SPEC, v1.1
 * decisions): a global default chosen with settings.update and each user's
 * own choice, both kept with CodeIgniter's Settings library, the user's in
 * the "user:{id}" context, which falls back to the global value.
 *
 * Permissions are never applied here: a block hidden by the default stays
 * available to whoever can see it, and the page filters at render time.
 */
final class Layout
{
    public const KEY = 'RoleWarden.dashboardLayout';

    /** The notice strip: it may be hidden, but it always comes first. */
    public const PINNED = 'security';

    /**
     * Every block's key in page order, the module's then the application's.
     *
     * @return list<string>
     */
    public static function known(): array
    {
        return array_merge(array_keys(Dashboard::BLOCKS), array_keys(Dashboard::registered()));
    }

    /**
     * The layout that applies to $userId: theirs, else the global default,
     * else the order of known().
     *
     * @return array{order: list<string>, hidden: list<string>, period: int}
     */
    public static function for(int $userId): array
    {
        helper('setting');
        $saved = setting()->get(self::KEY, 'user:' . $userId);

        return self::resolve(is_array($saved) ? $saved : null, self::known());
    }

    /**
     * The global default alone.
     *
     * @return array{order: list<string>, hidden: list<string>, period: int}
     */
    public static function global(): array
    {
        helper('setting');
        $saved = setting()->get(self::KEY);

        return self::resolve(is_array($saved) ? $saved : null, self::known());
    }

    /**
     * A saved layout made safe against $known: unknown keys dropped, known
     * keys it doesn't name appended in their own order (a block registered
     * after the save shows at the end), the pinned block first, the period
     * one of Dashboard::PERIODS.
     *
     * @param array<string, mixed>|null $saved
     * @param list<string>              $known
     *
     * @return array{order: list<string>, hidden: list<string>, period: int}
     */
    public static function resolve(?array $saved, array $known): array
    {
        $order = array_values(array_unique(array_intersect(array_map('strval', (array) ($saved['order'] ?? [])), $known)));
        $order = array_merge($order, array_values(array_diff($known, $order)));

        if (in_array(self::PINNED, $order, true)) {
            $order = array_merge([self::PINNED], array_values(array_diff($order, [self::PINNED])));
        }

        $period = (int) ($saved['period'] ?? Dashboard::DEFAULT_PERIOD);

        return [
            'order' => $order,
            'hidden' => array_values(array_intersect($order, array_map('strval', (array) ($saved['hidden'] ?? [])))),
            'period' => in_array($period, Dashboard::PERIODS, true) ? $period : Dashboard::DEFAULT_PERIOD,
        ];
    }

    /**
     * Saves a layout for $userId, or as the global default when null.
     *
     * @param array{order: list<string>, hidden: list<string>, period: int} $layout
     */
    public static function save(?int $userId, array $layout): void
    {
        helper('setting');
        setting()->set(self::KEY, self::resolve($layout, self::known()), $userId === null ? null : 'user:' . $userId);
    }

    /** Drops the user's own choice, so the global default applies again. */
    public static function reset(int $userId): void
    {
        helper('setting');
        setting()->forget(self::KEY, 'user:' . $userId);
    }
}
