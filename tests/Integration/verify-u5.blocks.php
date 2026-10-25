<?php
/**
 * U5 helpers (Collaudatore ad Hoc, Claude): dashboard blocks of the host application, written into the throwaway copy
 * of the test application ($APP, under the work folder), never into the module nor into ../rolewarden-app-test.
 * Written only from the public contract: src/Dashboard/Block.php, Config\RoleWarden::$dashboardBlocks,
 * documentation/api.html ("Adding your own permissions", "Dashboard blocks of your own") and configuration.html.
 */
declare(strict_types=1);

const U5_STOCK_TITLE = 'U5 Stock <b>block</b> & co';
const U5_PERMS =['u5orders.view' => 'See U5 orders', 'u5stock.view' => 'See U5 stock'];

/** Block class source: $key, $title, $perm, $view; $data = PHP expression returned by data(), null = default. */
function u5_block_src(string $class, string $key, string $title, string $perm, string $view, ?string $data, bool $extends = true): string
{
    $ext = $extends ? ' extends Block' : '';
    $d = $data === null ? '' : "\n    public function data(array \$period, int \$userId): array\n    {\n        return $data;\n    }\n";
    return "<?php\n\nnamespace App\\Dashboard;\n\nuse RoleWarden\\Dashboard\\Block;\n\nfinal class $class$ext\n{\n    public function key(): string { return " . var_export($key, true) . "; }\n    public function title(): string { return " . var_export($title, true) . "; }\n    public function permission(): string { return " . var_export($perm, true) . "; }\n    public function view(): string { return " . var_export($view, true) . "; }\n$d}\n";
}
/** The view of a test block: prints what it receives, escaped, in a probe element. */
function u5_view_src(string $tag): string
{
    return "<?php // U5 test block view\n?>\n<div class=\"u5-probe\" data-tag=\"$tag\" data-days=\"<?= esc((string) (\$period['days'] ?? 'none')) ?>\" data-from=\"<?= esc((string) (\$period['from'] ?? 'none')) ?>\" data-to=\"<?= esc((string) (\$period['to'] ?? 'none')) ?>\" data-prevfrom=\"<?= esc((string) (\$period['prevFrom'] ?? 'none')) ?>\" data-ndates=\"<?= esc((string) count(\$period['dates'] ?? [])) ?>\" data-user=\"<?= esc(isset(\$userId) ? (string) \$userId : 'none') ?>\" data-marker=\"<?= esc(isset(\$marker) ? (string) \$marker : 'none') ?>\" data-gotuser=\"<?= esc(isset(\$gotUser) ? (string) \$gotUser : 'none') ?>\" data-gotdays=\"<?= esc(isset(\$gotDays) ? (string) \$gotDays : 'none') ?>\" data-vars=\"<?= esc(implode(',', array_keys(get_defined_vars()))) ?>\"><p class=\"rw-note\">U5 body $tag <?= esc(isset(\$hostile) ? \$hostile : '') ?></p></div>\n";
}

/**
 * Writes the app's permission migration, block classes, views and app/Config/RoleWarden.php into $app.
 * $list: the class names registered in $dashboardBlocks, in order (strings, as the app would write them).
 */
function u5_write_blocks(string $app, array $list): void
{
    @mkdir("$app/app/Dashboard", 0777, true);
    @mkdir("$app/app/Views/dashboard", 0777, true);
    $w = fn ($p, $s) => file_put_contents("$app/$p", $s);
    $w('app/Dashboard/U5OrdersBlock.php', u5_block_src('U5OrdersBlock', 'u5-orders', 'U5 Orders block', 'u5orders.view', 'App\Views\dashboard\u5orders',
        "['marker' => 'ORD-DATA', 'gotUser' => \$userId, 'gotDays' => \$period['days'], 'hostile' => '<script>alert(1)</script>&\"']"));
    $w('app/Dashboard/U5StockBlock.php', u5_block_src('U5StockBlock', 'u5stock2', U5_STOCK_TITLE, 'u5stock.view', 'App\Views\dashboard\u5stock', null));
    // invalid ones: not extending Block, duplicate key, a module key, malformed keys
    $w('app/Dashboard/U5NotABlock.php', u5_block_src('U5NotABlock', 'u5-notablock', 'U5 Not a block', 'u5orders.view', 'App\Views\dashboard\u5orders', null, false));
    $w('app/Dashboard/U5DupBlock.php', u5_block_src('U5DupBlock', 'u5-orders', 'U5 Duplicate block', 'u5orders.view', 'App\Views\dashboard\u5orders', null));
    $w('app/Dashboard/U5ModKeyBlock.php', u5_block_src('U5ModKeyBlock', 'figures', 'U5 Module key block', 'u5orders.view', 'App\Views\dashboard\u5orders', null));
    $w('app/Dashboard/U5SecKeyBlock.php', u5_block_src('U5SecKeyBlock', 'security', 'U5 Security key block', 'u5orders.view', 'App\Views\dashboard\u5orders', null));
    $w('app/Dashboard/U5BadKeyBlock.php', u5_block_src('U5BadKeyBlock', 'Bad_Key!', 'U5 Bad key block', 'u5orders.view', 'App\Views\dashboard\u5orders', null));
    $w('app/Dashboard/U5UpperKeyBlock.php', u5_block_src('U5UpperKeyBlock', 'U5Upper', 'U5 Upper key block', 'u5orders.view', 'App\Views\dashboard\u5orders', null));
    $w('app/Dashboard/U5EmptyKeyBlock.php', u5_block_src('U5EmptyKeyBlock', '', 'U5 Empty key block', 'u5orders.view', 'App\Views\dashboard\u5orders', null));
    $w('app/Dashboard/U5SpaceKeyBlock.php', u5_block_src('U5SpaceKeyBlock', 'u5 space', 'U5 Space key block', 'u5orders.view', 'App\Views\dashboard\u5orders', null));
    $w('app/Views/dashboard/u5orders.php', u5_view_src('orders'));
    $w('app/Views/dashboard/u5stock.php', u5_view_src('stock'));
    u5_set_list($app, $list);
}
function u5_set_list(string $app, array $list): void
{
    $items = implode('', array_map(fn ($c) => '        ' . var_export($c, true) . ",\n", $list));
    file_put_contents("$app/app/Config/RoleWarden.php", "<?php\n\nnamespace Config;\n\nclass RoleWarden extends \\RoleWarden\\Config\\RoleWarden\n{\n    public array \$dashboardBlocks = [\n$items    ];\n}\n");
    clearstatcache();
}
function u5_migration(string $app): void
{
    @mkdir("$app/app/Database/Migrations", 0777, true);
    $perms = var_export(U5_PERMS, true);
    file_put_contents("$app/app/Database/Migrations/2026-10-25-000001_AddU5Permissions.php", <<<PHP
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddU5Permissions extends Migration
{
    private const PERMISSIONS = $perms;

    public function up(): void
    {
        \$table = config('RoleWarden')->table('permissions');
        \$now = date('Y-m-d H:i:s');

        foreach (self::PERMISSIONS as \$slug => \$description) {
            \$this->db->table(\$table)->insert([
                'slug' => \$slug,
                'area' => strstr(\$slug, '.', true),
                'description' => \$description,
                'is_system' => 0,
                'created_at' => \$now,
                'updated_at' => \$now,
            ]);
        }
    }

    public function down(): void
    {
        \$this->db->table(config('RoleWarden')->table('permissions'))
            ->whereIn('slug', array_keys(self::PERMISSIONS))->delete();
    }
}

PHP);
}
/** Removes everything u5_write_blocks / u5_migration wrote, so the copy is back to the test application. */
function u5_remove_blocks(string $app): void
{
    foreach (glob("$app/app/Dashboard/U5*.php") ?: [] as $f) unlink($f);
    @rmdir("$app/app/Dashboard");
    foreach (['u5orders', 'u5stock'] as $v) @unlink("$app/app/Views/dashboard/$v.php");
    @rmdir("$app/app/Views/dashboard");
    @unlink("$app/app/Config/RoleWarden.php");
    @unlink("$app/app/Database/Migrations/2026-10-25-000001_AddU5Permissions.php");
}
const U5_ALL_INVALID = ['App\Dashboard\U5Missing', 'App\Dashboard\U5NotABlock', 'App\Dashboard\U5DupBlock', 'App\Dashboard\U5ModKeyBlock', 'App\Dashboard\U5SecKeyBlock', 'App\Dashboard\U5BadKeyBlock', 'App\Dashboard\U5UpperKeyBlock', 'App\Dashboard\U5EmptyKeyBlock', 'App\Dashboard\U5SpaceKeyBlock'];
