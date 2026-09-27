<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Unit\Settings;

use PHPUnit\Framework\TestCase;
use RoleWarden\Settings\SignIn;

final class SignInTest extends TestCase
{
    private const NOW = 1_800_000_000;

    public function testFewerFailuresThanTheLimitDoNotLock(): void
    {
        $this->assertSame(0, SignIn::lockedFor([self::NOW - 10, self::NOW - 20], 3, 15, self::NOW));
    }

    public function testTheLockLastsItsDurationFromTheLatestFailure(): void
    {
        $failures = [self::NOW - 60, self::NOW - 120, self::NOW - 300];

        $this->assertSame(15 * 60 - 60, SignIn::lockedFor($failures, 3, 15, self::NOW));
    }

    public function testTheLockEndsOnceTheDurationHasPassed(): void
    {
        $failures = [self::NOW - 15 * 60, self::NOW - 16 * 60, self::NOW - 17 * 60];

        $this->assertSame(0, SignIn::lockedFor($failures, 3, 15, self::NOW));
    }

    public function testAValueSetOutsideTheOfferedChoicesStaysSelectable(): void
    {
        $this->assertSame([1800, 3600, 7200], SignIn::choices([1800, 7200], 3600));
        $this->assertSame([1800, 7200], SignIn::choices([1800, 7200], 7200));
    }
}
