<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Unit\Install;

use PHPUnit\Framework\TestCase;
use RoleWarden\Install\ConfigEditor;
use RoleWarden\Install\SetupState;

/**
 * The fixtures are real host config files (helpdesk, commit 544b9eb) plus two
 * hand-made variants. They are only ever read as strings, never included.
 */
final class ConfigEditorTest extends TestCase
{
    private const PATHS = ['rolewarden/assets/*', 'rolewarden/theme.css', 'rolewarden/setup*'];

    private static function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../../_support/fixtures/host/' . $name . '.php');
    }

    private static function assertLints(string $php): void
    {
        $file = tempnam(sys_get_temp_dir(), 'rw-lint');
        file_put_contents($file, $php);
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
        unlink($file);
        self::assertSame(0, $code, implode("\n", $out));
    }

    /** Lines that differ, keyed by 1-based line number; files must have the same line count. */
    private static function changedLines(string $before, string $after): array
    {
        $b = explode("\n", $before);
        $a = explode("\n", $after);
        self::assertCount(count($b), $a);

        return array_filter(array_combine(range(1, count($a)), $a), static fn ($l, $i) => $l !== $b[$i - 1], ARRAY_FILTER_USE_BOTH);
    }

    public function testGlobalFilterExceptsOnRealFilters(): void
    {
        $this->assertSame(
            ['filter' => 'session', 'except' => ['login*', 'register', 'auth/a/*', 'logout']],
            ConfigEditor::globalFilterExcepts(self::fixture('Filters')),
        );
    }

    public function testWithFilterExceptOnRealFilters(): void
    {
        $before = self::fixture('Filters');
        $after  = ConfigEditor::withFilterExcept($before, self::PATHS);

        $this->assertNotNull($after);
        $this->assertSame([
            78 => "            'session' => ['except' => ['login*', 'register', 'auth/a/*', 'logout', 'rolewarden/assets/*', 'rolewarden/theme.css', 'rolewarden/setup*']],",
        ], self::changedLines($before, $after));
        self::assertLints($after);

        $this->assertNull(ConfigEditor::withFilterExcept($after, self::PATHS), 'idempotent');
        $this->assertNull(ConfigEditor::withFilterExcept($after, ['rolewarden/setup*', 'logout']), 'nothing new');
    }

    public function testExceptStringBecomesArray(): void
    {
        $before = self::fixture('Filters-except-string');
        $this->assertSame(['filter' => 'session', 'except' => ['login*']], ConfigEditor::globalFilterExcepts($before));

        $after = ConfigEditor::withFilterExcept($before, self::PATHS);

        $this->assertNotNull($after);
        $this->assertSame([
            78 => "            'session' => ['except' => ['login*', 'rolewarden/assets/*', 'rolewarden/theme.css', 'rolewarden/setup*']],",
        ], self::changedLines($before, $after));
        self::assertLints($after);
    }

    public function testNoGlobalFilter(): void
    {
        $php = self::fixture('Filters-no-session');

        $this->assertNull(ConfigEditor::globalFilterExcepts($php));
        $this->assertNull(ConfigEditor::withFilterExcept($php, self::PATHS));
    }

    public function testBareGlobalFilterGetsExcepts(): void
    {
        $php   = "<?php\nclass Filters {\n    public array \$globals = [\n        'before' => ['csrf', 'session'],\n    ];\n}\n";
        $this->assertSame(['filter' => 'session', 'except' => []], ConfigEditor::globalFilterExcepts($php));

        $after = ConfigEditor::withFilterExcept($php, ['a*']);
        $this->assertSame(str_replace("'session'", "'session' => ['except' => ['a*']]", $php), $after);
    }

    public function testShieldFilterWithArgumentAndMultilineExcept(): void
    {
        $php = "<?php\nclass Filters {\n    public array \$globals = [\n        'before' => [\n"
            . "            'group:admin' => [\n                'except' => [\n                    'login*', // auth\n                    'logout'\n                ],\n            ],\n"
            . "        ],\n    ];\n}\n";
        $this->assertSame(['filter' => 'group:admin', 'except' => ['login*', 'logout']], ConfigEditor::globalFilterExcepts($php));

        $after = ConfigEditor::withFilterExcept($php, ['x/*', 'login*']);
        $this->assertSame(str_replace("'logout'\n", "'logout',\n                    'x/*'\n", $php), $after);
        self::assertLints((string) $after);
    }

    public function testMultilineExceptWithTrailingCommaAndComment(): void
    {
        $php   = "<?php\nclass Filters {\n    public array \$globals = ['before' => [\n        'session' => ['except' => [\n            'login*', // auth\n        ]],\n    ]];\n}\n";
        $after = ConfigEditor::withFilterExcept($php, ['a*', 'b']);

        $this->assertSame(str_replace("// auth\n", "// auth\n            'a*',\n            'b',\n", $php), $after);
        self::assertLints((string) $after);
    }

    public function testUnrecognisedExceptGivesNull(): void
    {
        $php = "<?php\nclass Filters {\n    public array \$globals = ['before' => ['session' => ['except' => self::EXCEPT]]];\n}\n";

        $this->assertNull(ConfigEditor::globalFilterExcepts($php));
        $this->assertNull(ConfigEditor::withFilterExcept($php, self::PATHS));
    }

    public function testPreservesCrlf(): void
    {
        $before = str_replace("\n", "\r\n", self::fixture('Filters'));
        $after  = (string) ConfigEditor::withFilterExcept($before, self::PATHS);

        $this->assertSame(substr_count($before, "\r\n"), substr_count($after, "\r\n"));
        $this->assertSame(substr_count($before, "\n"), substr_count($after, "\n"));
    }

    public function testUserProviderOnRealAuth(): void
    {
        $before = self::fixture('Auth');
        $after  = ConfigEditor::withUserProvider($before);

        $this->assertNotNull($after);
        $this->assertSame([437 => '    public string $userProvider = \RoleWarden\Models\UserModel::class;'], self::changedLines($before, $after));
        self::assertLints($after);
        $this->assertNull(ConfigEditor::withUserProvider($after));
    }

    public function testUserProviderAlreadyImported(): void
    {
        $php = "<?php\nuse RoleWarden\\Models\\UserModel;\nclass Auth {\n    public string \$userProvider = UserModel::class;\n}\n";

        $this->assertNull(ConfigEditor::withUserProvider($php));
    }

    public function testLoginViewAndRedirectOnRealAuth(): void
    {
        $before = self::fixture('Auth');

        $view = ConfigEditor::withLoginView($before);
        $this->assertNotNull($view);
        $this->assertSame([49 => "        'login'                       => '\\RoleWarden\\Views\\auth\\login',"], self::changedLines($before, $view));
        $this->assertNull(ConfigEditor::withLoginView($view));

        $redirect = ConfigEditor::withLoginRedirect($before);
        $this->assertNotNull($redirect);
        $this->assertSame([78 => "        'login'             => 'rolewarden',"], self::changedLines($before, $redirect));
        $this->assertNull(ConfigEditor::withLoginRedirect($redirect));

        // R5: all of a file's transforms combined.
        $all = ConfigEditor::withLoginRedirect((string) ConfigEditor::withLoginView((string) ConfigEditor::withUserProvider($before)));
        $this->assertNotNull($all);
        $this->assertSame([49, 78, 437], array_keys(self::changedLines($before, $all)));
        self::assertLints($all);
    }

    public function testMultilinePropertyAndComments(): void
    {
        $php = "<?php\nclass Auth {\n    // public string \$userProvider = Old::class;\n    public string \$userProvider =\n        /* legacy */ \\App\\Models\\Users::class;\n"
            . "    public function f(string \$userProvider = 'x') { \$userProvider = 1; }\n}\n";

        $this->assertSame(
            str_replace('\\App\\Models\\Users::class', '\\RoleWarden\\Models\\UserModel::class', $php),
            ConfigEditor::withUserProvider($php),
        );
    }

    public function testMissingPropertyGivesNull(): void
    {
        $php = "<?php\nclass Auth {\n    public array \$views = ['register' => 'x'];\n}\n";

        $this->assertNull(ConfigEditor::withUserProvider($php));
        $this->assertNull(ConfigEditor::withLoginView($php));
        $this->assertNull(ConfigEditor::withLoginRedirect($php));
    }

    public function testRouteRegistrarAfterShieldRoutes(): void
    {
        $before = self::fixture('Routes');
        $after  = ConfigEditor::withRouteRegistrar($before);

        $this->assertSame(
            str_replace("service('auth')->routes(\$routes);\n", "service('auth')->routes(\$routes);\n\\RoleWarden\\Config\\RouteRegistrar::register(\$routes);\n", $before),
            $after,
        );
        self::assertLints((string) $after);
        $this->assertNull(ConfigEditor::withRouteRegistrar((string) $after));
    }

    public function testRouteRegistrarAtEndWhenShieldRoutesMissing(): void
    {
        $php = "<?php\n\n\$routes->get('/', 'Home::index');";

        $this->assertSame($php . "\n\n\\RoleWarden\\Config\\RouteRegistrar::register(\$routes);\n", ConfigEditor::withRouteRegistrar($php));
    }

    public function testPreview(): void
    {
        $diff = ConfigEditor::preview("a\nb\nc\nd\ne\nf\ng\nh\n", "a\nb\nc\nd\nE\nf\ng\nh\n");

        $this->assertSame("--- before\n+++ after\n@@ -2,7 +2,7 @@\n b\n c\n d\n-e\n+E\n f\n g\n h\n", $diff);
        $this->assertSame('', ConfigEditor::preview("x\n", "x\n"));
    }

    public function testApplyWritesBackupAndLogs(): void
    {
        $path   = tempnam(sys_get_temp_dir(), 'rw-cfg');
        $before = self::fixture('Filters');
        file_put_contents($path, $before);
        $after = (string) ConfigEditor::withFilterExcept($before, self::PATHS);
        $state = new SetupState();

        try {
            $this->assertTrue(ConfigEditor::apply($path, $after, $state));
            $this->assertSame($after, file_get_contents($path));
            $this->assertSame($before, file_get_contents($path . '.rolewarden-bak'));
            $edits = $state->edits();
            $this->assertCount(1, $edits);
            $this->assertSame([$path, $before, $after, true], [$edits[0]['path'], $edits[0]['before'], $edits[0]['after'], $edits[0]['checked']]);
        } finally {
            @unlink($path);
            @unlink($path . '.rolewarden-bak');
        }
    }

    public function testApplyRestoresOnSyntaxError(): void
    {
        $path   = tempnam(sys_get_temp_dir(), 'rw-cfg');
        $before = self::fixture('Routes');
        file_put_contents($path, $before);
        $state = new SetupState();

        try {
            $this->assertFalse(ConfigEditor::apply($path, "<?php\nnope(;\n", $state));
            $this->assertSame($before, file_get_contents($path));
            $this->assertSame([], $state->edits());
        } finally {
            @unlink($path);
            @unlink($path . '.rolewarden-bak');
        }
    }

    public function testApplyFailsOnMissingFile(): void
    {
        $this->assertFalse(ConfigEditor::apply(sys_get_temp_dir() . '/rw-missing-' . bin2hex(random_bytes(4)) . '.php', '<?php', new SetupState()));
    }
}
