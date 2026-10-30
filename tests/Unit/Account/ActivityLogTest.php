<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Unit\Account;

use PHPUnit\Framework\TestCase;
use RoleWarden\Account\ActivityLog;

final class ActivityLogTest extends TestCase
{
    public function testDiffKeepsOnlyChangedFieldsWithBeforeAndAfter(): void
    {
        $changes = ActivityLog::diff(
            ['name' => 'Editor', 'description' => 'Writes', 'parent' => null],
            ['name' => 'Author', 'description' => 'Writes', 'parent' => 'Viewer'],
        );

        $this->assertSame(['name' => ['Editor', 'Author'], 'parent' => [null, 'Viewer']], $changes);
    }

    public function testDiffComparesAsTextSoAFormStringEqualsAStoredInteger(): void
    {
        $this->assertSame([], ActivityLog::diff(['lock_attempts' => 5], ['lock_attempts' => '5']));
    }

    public function testCutoffIsRetentionDaysBeforeNow(): void
    {
        $now = mktime(12, 0, 0, 9, 28, 2026);

        $this->assertSame(date('Y-m-d H:i:s', $now - 90 * 86400), ActivityLog::cutoff(90, $now));
    }

    public function testZeroRetentionKeepsEverything(): void
    {
        $this->assertNull(ActivityLog::cutoff(0, time()));
    }

    public function testPeriodFilterIgnoresImpossibleDates(): void
    {
        $conditions = new \ReflectionMethod(ActivityLog::class, 'conditions');
        $filter = static fn (array $filters): array => $conditions->invoke(null, $filters, 'at', 'a', 'al', 's', 'sl');

        $this->assertSame([['at >= ?', 'at <= ?'], ['2026-02-28 00:00:00', '2026-02-28 23:59:59']], $filter(['from' => '2026-02-28', 'to' => '2026-02-28']));
        $this->assertSame([[], []], $filter(['from' => '2026-13-45', 'to' => '2026-02-30']));
    }
}
