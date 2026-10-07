<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Unit\Language;

use MessageFormatter;
use PHPUnit\Framework\TestCase;

/**
 * Every translation has exactly the English keys, with the same placeholders,
 * and each line is a valid ICU message.
 */
final class TranslationsTest extends TestCase
{
    public function testEveryLanguageMatchesEnglish(): void
    {
        $english = self::flatten(require __DIR__ . '/../../../src/Language/en/RoleWarden.php');

        foreach (glob(__DIR__ . '/../../../src/Language/*/RoleWarden.php') as $file) {
            $locale = basename(dirname($file));
            $lines = self::flatten(require $file);

            $this->assertSame(array_keys($english), array_keys($lines), $locale);

            foreach ($english as $key => $line) {
                $this->assertSame(self::placeholders($line), self::placeholders($lines[$key]), "{$locale}: {$key}");
                $this->assertNotNull(MessageFormatter::create($locale, $lines[$key]), "{$locale}: {$key}");
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private static function flatten(array $lines, string $prefix = ''): array
    {
        $flat = [];

        foreach ($lines as $key => $line) {
            $flat += is_array($line) ? self::flatten($line, $prefix . $key . '.') : [$prefix . $key => $line];
        }

        return $flat;
    }

    /**
     * @return list<string>
     */
    private static function placeholders(string $line): array
    {
        preg_match_all('/%[sd]|\{\d+/', $line, $found);
        sort($found[0]);

        return $found[0];
    }
}
