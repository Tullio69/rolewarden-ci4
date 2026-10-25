<?php

declare(strict_types=1);

namespace RoleWarden\Dashboard;

/**
 * A dashboard block of the host application (SPEC, v1.1 decisions): public
 * API, stable for every 1.x release. Extend it in the application and list the
 * class in Config\RoleWarden::$dashboardBlocks; the block then shows only to
 * those who hold its permission and is hidden and reordered like the others.
 *
 * The module renders the section, its letter and the title; the view renders
 * the body and receives what data() returns plus $period and $userId.
 */
abstract class Block
{
    /** Unique key, lowercase letters, digits and dashes, e.g. 'sales'. */
    abstract public function key(): string;

    /** The block's title, already translated, e.g. lang('App.sales.title'). */
    abstract public function title(): string;

    /** The permission that shows the block, e.g. 'sales.view'. */
    abstract public function permission(): string;

    /** The namespaced view of the body, e.g. 'App\Views\dashboard\sales'. */
    abstract public function view(): string;

    /**
     * The view's data, read only when the block is shown.
     *
     * @param array{days: int, from: string, to: string, prevFrom: string, prevTo: string, dates: list<string>} $period see Dashboard::period()
     *
     * @return array<string, mixed>
     */
    public function data(array $period, int $userId): array
    {
        return [];
    }
}
