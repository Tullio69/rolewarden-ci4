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
}
