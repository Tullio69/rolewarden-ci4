<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Unit\Settings;

use PHPUnit\Framework\TestCase;
use RoleWarden\Settings\Theme;

final class ThemeTest extends TestCase
{
    public function testAnEmptyOrUnknownFileMeansConsoleWithoutOverrides(): void
    {
        $theme = Theme::normalise(['base' => 'neon', 'radius' => 99, 'density' => 'huge']);

        $this->assertSame('console', $theme['base']);
        $this->assertNull($theme['radius']);
        $this->assertNull($theme['density']);
        $this->assertSame('', Theme::css($theme));
    }

    public function testOnlyKnownColoursWithAPlainHexValueSurvive(): void
    {
        $theme = Theme::normalise(['colors' => ['light' => [
            'accent' => '#4338CA',
            'surface' => 'red; } body { display: none',
            'ink' => '#fff',
            'shadow-float' => '#000000',
        ]]]);

        $this->assertSame(['accent' => '#4338ca'], $theme['colors']['light']);
        $this->assertSame([], $theme['colors']['dark']);
    }

    public function testTheOverridesBecomeSemanticCustomPropertiesForBothModes(): void
    {
        $css = Theme::css(Theme::normalise([
            'base' => 'clarity',
            'radius' => 8,
            'density' => 'compact',
            'colors' => ['light' => ['accent' => '#0b3d91'], 'dark' => ['accent' => '#facc15']],
        ]));

        $this->assertStringStartsWith(':root[data-rw-theme] {', $css);
        $this->assertStringContainsString('--l-accent: #0b3d91;', $css);
        $this->assertStringContainsString('--d-accent: #facc15;', $css);
        $this->assertStringContainsString('--radius-md: 8px; --radius-sm: 4px;', $css);
        $this->assertStringContainsString('--size-control: 32px;', $css);
    }
}
