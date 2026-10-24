<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Unit\Dashboard;

use PHPUnit\Framework\TestCase;
use RoleWarden\Dashboard\Dashboard;

final class DashboardTest extends TestCase
{
    public function testPeriodIsCalendarDaysTodayIncludedAndTheSameNumberBefore(): void
    {
        $period = Dashboard::period(7, mktime(15, 30, 0, 10, 21, 2026));

        $this->assertSame('2026-10-15 00:00:00', $period['from']);
        $this->assertSame('2026-10-21 15:30:00', $period['to']);
        $this->assertSame('2026-10-08 00:00:00', $period['prevFrom']);
        $this->assertSame('2026-10-14 23:59:59', $period['prevTo']);
        $this->assertSame(['2026-10-15', '2026-10-16', '2026-10-17', '2026-10-18', '2026-10-19', '2026-10-20', '2026-10-21'], $period['dates']);
    }

    public function testAnUnknownPeriodFallsBackToThirtyDays(): void
    {
        $period = Dashboard::period(12, mktime(9, 0, 0, 10, 21, 2026));

        $this->assertSame(30, $period['days']);
        $this->assertCount(30, $period['dates']);
        $this->assertSame('2026-09-22 00:00:00', $period['from']);
    }

    public function testHeatLevelSplitsTheBusiestCellIntoFourSteps(): void
    {
        $this->assertSame(0, Dashboard::heatLevel(0, 40));
        $this->assertSame(1, Dashboard::heatLevel(1, 40));
        $this->assertSame(1, Dashboard::heatLevel(10, 40));
        $this->assertSame(2, Dashboard::heatLevel(11, 40));
        $this->assertSame(4, Dashboard::heatLevel(40, 40));
        $this->assertSame(0, Dashboard::heatLevel(0, 0));
    }

    public function testBucketsCountPerDayAndSuccessfulOnesPerWeekdayAndHour(): void
    {
        $buckets = Dashboard::buckets([
            ['date' => '2026-10-19 09:15:00', 'success' => 1], // Monday
            ['date' => '2026-10-19 09:45:00', 'success' => '1'],
            ['date' => '2026-10-19 10:00:00', 'success' => 0],
            ['date' => '2026-10-25 23:59:59', 'success' => 1], // Sunday
            ['date' => '2026-10-10 12:00:00', 'success' => 1], // outside the period
        ], ['2026-10-19', '2026-10-25']);

        $this->assertSame(['2026-10-19' => 2, '2026-10-25' => 1], $buckets['ok']);
        $this->assertSame(['2026-10-19' => 1, '2026-10-25' => 0], $buckets['failed']);
        $this->assertSame(2, $buckets['heat'][0][9]);
        $this->assertSame(1, $buckets['heat'][6][23]);
        $this->assertSame(3, array_sum(array_map('array_sum', $buckets['heat'])));
    }

    public function testAddressesNeedTheThresholdOnOneDay(): void
    {
        $failures = [
            ['date' => '2026-10-16 08:00:00', 'ip_address' => '203.0.113.42'],
            ['date' => '2026-10-16 08:01:00', 'ip_address' => '203.0.113.42'],
            ['date' => '2026-10-17 08:02:00', 'ip_address' => '203.0.113.42'],
            ['date' => '2026-10-17 09:00:00', 'ip_address' => '198.51.100.7'],
            ['date' => '2026-10-17 09:01:00', 'ip_address' => null],
        ];

        $this->assertSame([], Dashboard::addresses($failures, 3));
        $this->assertSame([['ip' => '203.0.113.42', 'day' => '2026-10-16', 'count' => 2]], Dashboard::addresses($failures, 2));
    }

    public function testAddressesListEveryOneLatestDayFirst(): void
    {
        $failures = [
            ['date' => '2026-10-16 08:00:00', 'ip_address' => '203.0.113.42'],
            ['date' => '2026-10-18 08:00:00', 'ip_address' => '198.51.100.7'],
            ['date' => '2026-10-18 08:01:00', 'ip_address' => '192.0.2.1'],
            ['date' => '2026-10-18 08:02:00', 'ip_address' => '192.0.2.1'],
        ];

        $this->assertSame(
            [['192.0.2.1', '2026-10-18'], ['198.51.100.7', '2026-10-18'], ['203.0.113.42', '2026-10-16']],
            array_map(static fn (array $a): array => [$a['ip'], $a['day']], Dashboard::addresses($failures, 1)),
        );
    }

    public function testSharesKeepTheLargestRolesAndSumTheRest(): void
    {
        $roles = [['name' => 'Viewer', 'total' => 9], ['name' => 'Author', 'total' => 5], ['name' => 'Editor', 'total' => 3], ['name' => 'Admin', 'total' => 2], ['name' => 'Auditor', 'total' => 1], ['name' => 'Guest', 'total' => 1]];

        $this->assertSame(
            [['name' => 'Viewer', 'total' => 9], ['name' => 'Author', 'total' => 5], ['name' => 'Editor', 'total' => 3], ['name' => 'Admin', 'total' => 2], ['name' => null, 'total' => 2]],
            Dashboard::shares($roles, 4),
        );
        $this->assertSame(array_slice($roles, 0, 2), Dashboard::shares(array_slice($roles, 0, 2), 4));
    }
}
