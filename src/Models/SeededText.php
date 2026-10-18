<?php

declare(strict_types=1);

namespace RoleWarden\Models;

/**
 * Names and descriptions of seeded roles and permissions in the reader's
 * language. The English text is the one stored; a translation lives in
 * Language/{locale}/RoleWardenData.php under the same key. Once someone
 * changes the stored text, it is shown as written, in every language.
 */
final class SeededText
{
    /**
     * @param string $key e.g. 'roles.admin.name', 'permissions.users.delete'
     */
    public static function show(string $key, ?string $stored): ?string
    {
        $line = 'RoleWardenData.' . $key;
        $english = lang($line, [], 'en');

        return $stored !== null && $english === $stored ? lang($line) : $stored;
    }

    /**
     * The name of a permission area: its translation under areas.{area}, else the slug capitalised.
     */
    public static function area(string $area): string
    {
        $line = 'RoleWardenData.areas.' . $area;
        $name = lang($line);

        return $name !== $line ? $name : ucfirst($area);
    }

    /**
     * The name of a permission action (a matrix column): its translation under actions.{action}, else the slug part.
     */
    public static function action(string $action): string
    {
        $line = 'RoleWardenData.actions.' . $action;
        $name = lang($line);

        return $name !== $line ? $name : $action;
    }

    /**
     * Applies show() to a role row, when it carries its slug.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function role(array $row): array
    {
        if (isset($row['slug'])) {
            foreach (['name', 'description'] as $field) {
                if (array_key_exists($field, $row)) {
                    $row[$field] = self::show("roles.{$row['slug']}.{$field}", $row[$field]);
                }
            }
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function permission(array $row): array
    {
        if (isset($row['slug']) && array_key_exists('description', $row)) {
            $row['description'] = self::show("permissions.{$row['slug']}", $row['description']);
        }

        return $row;
    }
}
