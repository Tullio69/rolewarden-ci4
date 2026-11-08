<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Unit\Install;

use PHPUnit\Framework\TestCase;
use RoleWarden\Install\ConfigEditor;
use RoleWarden\Install\RestoreFailedException;
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

    private static function assertNoLoneLf(string $php): void
    {
        self::assertSame(0, preg_match('/(?<!\r)\n/', $php), 'lone LF in CRLF file');
    }

    public function testPreservesCrlf(): void
    {
        $crlf = static fn (string $s) => str_replace("\n", "\r\n", $s);

        $filters = (string) ConfigEditor::withFilterExcept($crlf(self::fixture('Filters')), self::PATHS);
        self::assertNoLoneLf($filters);
        $this->assertStringContainsString("'logout', 'rolewarden/assets/*'", $filters);

        $multi = $crlf("<?php\nclass Filters {\n    public array \$globals = ['before' => [\n        'session' => ['except' => [\n            'login*',\n        ]],\n    ]];\n}\n");
        $after = (string) ConfigEditor::withFilterExcept($multi, ['a*', 'b']);
        $this->assertSame(str_replace("'login*',\r\n", "'login*',\r\n            'a*',\r\n            'b',\r\n", $multi), $after);

        $routes = (string) ConfigEditor::withRouteRegistrar($crlf(self::fixture('Routes')));
        self::assertNoLoneLf($routes);
        $this->assertStringContainsString("routes(\$routes);\r\n\RoleWarden\Config\RouteRegistrar::register(\$routes);\r\n", $routes);

        $end = (string) ConfigEditor::withRouteRegistrar("<?php\r\n\r\n\$routes->get('/', 'Home::index');");
        $this->assertSame("<?php\r\n\r\n\$routes->get('/', 'Home::index');\r\n\r\n\RoleWarden\Config\RouteRegistrar::register(\$routes);\r\n", $end);
    }

    public function testHasChecksBeforeAndAfter(): void
    {
        $filters = self::fixture('Filters');
        $this->assertFalse(ConfigEditor::hasFilterExcept($filters, self::PATHS));
        $this->assertTrue(ConfigEditor::hasFilterExcept($filters, ['login*', 'logout']));
        $this->assertTrue(ConfigEditor::hasFilterExcept((string) ConfigEditor::withFilterExcept($filters, self::PATHS), self::PATHS));
        $this->assertFalse(ConfigEditor::hasFilterExcept(self::fixture('Filters-no-session'), self::PATHS));
        $this->assertNull(ConfigEditor::globalFilterExcepts(self::fixture('Filters-no-session')), 'no global filter: nothing needed');

        $auth = self::fixture('Auth');
        foreach (['UserProvider', 'LoginView', 'LoginRedirect'] as $what) {
            $this->assertFalse(ConfigEditor::{'has' . $what}($auth), $what);
            $this->assertTrue(ConfigEditor::{'has' . $what}((string) ConfigEditor::{'with' . $what}($auth)), $what);
        }
        $this->assertFalse(ConfigEditor::hasLoginView("<?php\nclass Auth {}\n"), 'unrecognised is not applied');

        $routes = self::fixture('Routes');
        $this->assertFalse(ConfigEditor::hasRouteRegistrar($routes));
        $this->assertTrue(ConfigEditor::hasRouteRegistrar((string) ConfigEditor::withRouteRegistrar($routes)));
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

    public function testApplyKeepsPristineBackup(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rw-cfg');
        file_put_contents($path, "<?php\n// v0\n");
        $state = new SetupState();

        try {
            $this->assertTrue(ConfigEditor::apply($path, "<?php\n// v1\n", $state));
            $this->assertTrue(ConfigEditor::apply($path, "<?php\n// v2\n", $state));
            $this->assertSame("<?php\n// v0\n", file_get_contents($path . '.rolewarden-bak'));
            $this->assertSame("<?php\n// v1\n", $state->edits()[1]['before']);
        } finally {
            @unlink($path);
            @unlink($path . '.rolewarden-bak');
        }
    }

    public function testApplyUncheckedWhenLintCannotRun(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rw-cfg');
        file_put_contents($path, "<?php\n");
        $state = new SetupState();
        ConfigEditor::useLinter(static fn (string $p): ?bool => null);

        try {
            $this->assertTrue(ConfigEditor::apply($path, "<?php\n// x\n", $state));
            $this->assertFalse($state->edits()[0]['checked']);
        } finally {
            ConfigEditor::useLinter(null);
            @unlink($path);
            @unlink($path . '.rolewarden-bak');
        }
    }

    public function testApplyThrowsWhenRestoreFails(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rw-cfg');
        file_put_contents($path, "<?php\n");
        // The check fails and leaves a directory where the file was: the restore cannot write.
        ConfigEditor::useLinter(static function (string $p): bool {
            unlink($p);
            mkdir($p);

            return false;
        });

        try {
            ConfigEditor::apply($path, '<?php broken(', new SetupState());
            $this->fail('expected RestoreFailedException');
        } catch (RestoreFailedException $e) {
            $this->assertSame([$path, $path . '.rolewarden-bak'], [$e->path, $e->backup]);
            $this->assertSame("<?php\n", file_get_contents($path . '.rolewarden-bak'));
        } finally {
            ConfigEditor::useLinter(null);
            @rmdir($path);
            @unlink($path);
            @unlink($path . '.rolewarden-bak');
        }
    }

    public function testApplyFailsOnMissingFile(): void
    {
        $this->assertFalse(ConfigEditor::apply(sys_get_temp_dir() . '/rw-missing-' . bin2hex(random_bytes(4)) . '.php', '<?php', new SetupState()));
    }
}
