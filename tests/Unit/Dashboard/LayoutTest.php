<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Unit\Dashboard;

use PHPUnit\Framework\TestCase;
use RoleWarden\Dashboard\Block;
use RoleWarden\Dashboard\Dashboard;
use RoleWarden\Dashboard\Layout;

final class LayoutTest extends TestCase
{
    private const KNOWN = ['security', 'figures', 'signins', 'roles', 'changes', 'sales'];

    public function testNothingSavedGivesTheKnownOrderAndThirtyDays(): void
    {
        $this->assertSame(['order' => self::KNOWN, 'hidden' => [], 'period' => 30], Layout::resolve(null, self::KNOWN));
    }

    public function testUnknownKeysAreDroppedAndMissingOnesAppended(): void
    {
        $layout = Layout::resolve(['order' => ['changes', 'gone', 'sales', 'changes'], 'hidden' => ['gone', 'roles'], 'period' => 7], self::KNOWN);

        $this->assertSame(['security', 'changes', 'sales', 'figures', 'signins', 'roles'], $layout['order']);
        $this->assertSame(['roles'], $layout['hidden']);
        $this->assertSame(7, $layout['period']);
    }

    public function testTheSecurityNoticeAlwaysComesFirstButCanBeHidden(): void
    {
        $layout = Layout::resolve(['order' => ['roles', 'security', 'figures'], 'hidden' => ['security']], self::KNOWN);

        $this->assertSame('security', $layout['order'][0]);
        $this->assertSame(['security'], $layout['hidden']);
    }

    public function testAnUnknownPeriodFallsBackToTheDefault(): void
    {
        $this->assertSame(Dashboard::DEFAULT_PERIOD, Layout::resolve(['period' => 12], self::KNOWN)['period']);
    }

    public function testRegisteredBlocksKeepOnlyValidClassesWithFreeKeys(): void
    {
        $blocks = Dashboard::registered([
            SalesBlock::class,
            'App\Missing\Block',
            \stdClass::class,
            TakenKeyBlock::class,
            BadKeyBlock::class,
            DuplicateSalesBlock::class,
        ]);

        $this->assertSame(['sales'], array_keys($blocks));
        $this->assertInstanceOf(SalesBlock::class, $blocks['sales']);
        $this->assertSame([], $blocks['sales']->data(Dashboard::period(7, time()), 1));
    }
}

class SalesBlock extends Block
{
    public function key(): string
    {
        return 'sales';
    }

    public function title(): string
    {
        return 'Sales';
    }

    public function permission(): string
    {
        return 'sales.view';
    }

    public function view(): string
    {
        return 'App\Views\sales';
    }
}

final class DuplicateSalesBlock extends SalesBlock
{
}

final class TakenKeyBlock extends SalesBlock
{
    public function key(): string
    {
        return 'roles';
    }
}

final class BadKeyBlock extends SalesBlock
{
    public function key(): string
    {
        return 'Sales Block';
    }
}
