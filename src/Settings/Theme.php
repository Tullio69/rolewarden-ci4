<?php

declare(strict_types=1);

namespace RoleWarden\Settings;

use JsonException;
use RuntimeException;

/**
 * The panel's theme (SPEC "Design system", V5 decisions): a starting theme
 * (Console, Clarity, Contrast) plus overrides of the semantic level only,
 * edited from the Appearance screen.
 *
 * It lives in a file of the host application, never in the module, so an
 * update leaves it alone: app/Config/RoleWarden/theme.json when the host keeps
 * one under version control (it wins), otherwise writable/rolewarden/theme.json,
 * which the panel writes. Missing or unreadable: Console, no overrides.
 *
 * The file is JSON:
 *   {"base": "clarity", "radius": 4, "density": "regular",
 *    "colors": {"light": {"accent": "#4338ca", ...}, "dark": {...}}}
 */
final class Theme
{
    public const BASES = ['console', 'clarity', 'contrast'];

    /** Corner radius choices, in px (controls; chips get half). */
    public const RADII = [0, 2, 4, 8];

    /** Density choices: row and control heights borrowed from the three themes. */
    public const DENSITIES = [
        'compact' => ['row' => 36, 'row-md' => 40, 'row-lg' => 48, 'row-xl' => 52, 'control' => 32, 'control-sm' => 26, 'square' => 30],
        'regular' => ['row' => 46, 'row-md' => 50, 'row-lg' => 56, 'row-xl' => 64, 'control' => 40, 'control-sm' => 32, 'square' => 36],
        'comfortable' => ['row' => 52, 'row-md' => 56, 'row-lg' => 64, 'row-xl' => 72, 'control' => 40, 'control-sm' => 32, 'square' => 36],
    ];

    /** Semantic colours the customiser sets per mode; hover, tint and on-accent derive from accent. */
    public const COLORS = ['surface', 'ink', 'accent', 'accent-hover', 'accent-tint', 'on-accent'];

    /**
     * The built-in themes plus the host's extra ones (Config\RoleWarden::$extraThemes),
     * as slug => label.
     *
     * @return array<string, string>
     */
    public static function bases(): array
    {
        $bases = [];

        foreach (self::BASES as $slug) {
            $bases[$slug] = lang('RoleWarden.panel.appearance.theme.' . $slug);
        }

        foreach (config('RoleWarden')->extraThemes ?? [] as $slug => $label) {
            if (is_string($slug) && preg_match('/^[a-z][a-z0-9-]*$/', $slug) === 1 && ! isset($bases[$slug])) {
                $bases[$slug] = (string) $label;
            }
        }

        return $bases;
    }

    public static function appFile(): string
    {
        return APPPATH . 'Config/RoleWarden/theme.json';
    }

    public static function writableFile(): string
    {
        return WRITEPATH . 'rolewarden/theme.json';
    }

    /**
     * Whether the host keeps its own theme file, which the panel cannot overwrite.
     */
    public static function isPinned(): bool
    {
        return is_file(self::appFile());
    }

    /**
     * The current theme, normalised.
     *
     * @return array{base: string, radius: int|null, density: string|null, colors: array{light: array<string, string>, dark: array<string, string>}}
     */
    public static function current(): array
    {
        foreach ([self::appFile(), self::writableFile()] as $file) {
            if (is_file($file)) {
                try {
                    $data = json_decode((string) file_get_contents($file), true, 8, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    log_message('warning', 'RoleWarden: theme file {file} is not valid JSON, using the default theme.', ['file' => $file]);

                    return self::normalise([]);
                }

                return self::normalise(is_array($data) ? $data : []);
            }
        }

        return self::normalise([]);
    }

    /**
     * Keeps only known keys with valid values, so a hand-edited file can
     * never inject CSS.
     *
     * @param array<string, mixed> $data
     *
     * @return array{base: string, radius: int|null, density: string|null, colors: array{light: array<string, string>, dark: array<string, string>}}
     */
    public static function normalise(array $data): array
    {
        $colors = ['light' => [], 'dark' => []];

        foreach (['light', 'dark'] as $mode) {
            foreach (self::COLORS as $key) {
                $value = $data['colors'][$mode][$key] ?? null;

                if (is_string($value) && self::isHex($value)) {
                    $colors[$mode][$key] = strtolower($value);
                }
            }
        }

        $radius = $data['radius'] ?? null;
        $density = $data['density'] ?? null;

        return [
            'base' => is_string($data['base'] ?? null) && isset(self::bases()[$data['base']]) ? $data['base'] : 'console',
            'radius' => is_int($radius) && in_array($radius, self::RADII, true) ? $radius : null,
            'density' => is_string($density) && isset(self::DENSITIES[$density]) ? $density : null,
            'colors' => $colors,
        ];
    }

    public static function isHex(string $value): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    /**
     * Writes the theme to writable/. Throws when the folder cannot be written.
     *
     * @param array<string, mixed> $theme
     */
    public static function save(array $theme): void
    {
        $dir = dirname(self::writableFile());

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Cannot create ' . $dir);
        }

        $json = json_encode(self::normalise($theme), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

        if (@file_put_contents(self::writableFile(), $json, LOCK_EX) === false) {
            throw new RuntimeException('Cannot write ' . self::writableFile());
        }
    }

    public static function reset(): void
    {
        if (is_file(self::writableFile())) {
            @unlink(self::writableFile());
        }
    }

    /**
     * The overrides as CSS, loaded after panel.css; '' when there are none.
     * Values come from normalise(), so they are known keys and plain hex/px.
     *
     * @param array{base: string, radius: int|null, density: string|null, colors: array{light: array<string, string>, dark: array<string, string>}} $theme
     */
    public static function css(array $theme): string
    {
        $declarations = [];

        foreach (['light' => 'l', 'dark' => 'd'] as $mode => $prefix) {
            foreach ($theme['colors'][$mode] as $key => $value) {
                $declarations[] = "--{$prefix}-{$key}: {$value};";
            }
        }

        if ($theme['radius'] !== null) {
            $declarations[] = '--radius-md: ' . $theme['radius'] . 'px;';
            $declarations[] = '--radius-sm: ' . intdiv($theme['radius'], 2) . 'px;';
        }

        if ($theme['density'] !== null) {
            foreach (self::DENSITIES[$theme['density']] as $key => $px) {
                $declarations[] = "--size-{$key}: {$px}px;";
            }
        }

        if ($declarations === []) {
            return '';
        }

        // Same specificity as the theme blocks in panel.css, loaded later, so these win.
        return ':root[data-rw-theme] { ' . implode(' ', $declarations) . " }\n";
    }
}
