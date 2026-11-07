<?php

declare(strict_types=1);

namespace RoleWarden\Install;

/**
 * Decisions and messages of the Shield group import (ImportAuthGroups).
 */
final class GroupImport
{
    /** When true the migration imports nothing: the installer wizard does it. */
    public const DEFERRED = 'RoleWarden.importDeferred';

    /**
     * @param list<string> $groups group slugs
     * @param array<string, int> $matrixCounts permissions granted to each group
     * @param array<string, int> $memberCounts users in each group
     * @param list<string> $existingRoleSlugs
     *
     * @return array{import: list<string>, skipped: list<array{slug: string, users: int, permissions: int}>}
     */
    public static function plan(array $groups, array $matrixCounts, array $memberCounts, array $existingRoleSlugs): array
    {
        $plan = ['import' => [], 'skipped' => []];

        foreach ($groups as $slug) {
            if (in_array($slug, $existingRoleSlugs, true)) {
                $plan['skipped'][] = [
                    'slug' => $slug,
                    'users' => $memberCounts[$slug] ?? 0,
                    'permissions' => $matrixCounts[$slug] ?? 0,
                ];
            } else {
                $plan['import'][] = $slug;
            }
        }

        return $plan;
    }

    /**
     * @param array{import: list<string>, skipped: list<array{slug: string, users: int, permissions: int}>} $plan
     *
     * @return list<string>
     */
    public static function report(array $plan, string $locale): array
    {
        if ($plan['skipped'] === []) {
            return [];
        }

        $lines = [];

        foreach ($plan['skipped'] as $group) {
            $key = $group['users'] > 0 && $group['permissions'] > 0 ? 'skipped' : 'skippedNoLoss';
            $lines[] = lang("RoleWarden.install.import.{$key}", [$group['slug'], $group['users'], $group['permissions']], $locale);
        }

        $lines[] = lang('RoleWarden.install.import.summary', [
            implode(', ', $plan['import']),
            implode(', ', array_column($plan['skipped'], 'slug')),
        ], $locale);

        return $lines;
    }
}
