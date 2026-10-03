<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Unit\Account;

use PHPUnit\Framework\TestCase;
use RoleWarden\Account\SecurityMail;

final class SecurityMailTest extends TestCase
{
    private const SEEN = [['10.0.0.1', 'Chrome / Windows 10'], ['10.0.0.2', 'Safari / iOS']];

    public function testTheFirstSignInEverIsNotReported(): void
    {
        $this->assertFalse(SecurityMail::isNewAccess('10.0.0.9', 'Firefox / Linux', []));
    }

    public function testAKnownAddressAndDeviceIsNotReported(): void
    {
        $this->assertFalse(SecurityMail::isNewAccess('10.0.0.1', 'Chrome / Windows 10', self::SEEN));
        // Address and device need not have been seen together.
        $this->assertFalse(SecurityMail::isNewAccess('10.0.0.2', 'Chrome / Windows 10', self::SEEN));
    }

    public function testANewAddressOrANewDeviceIsReported(): void
    {
        $this->assertTrue(SecurityMail::isNewAccess('10.0.0.9', 'Chrome / Windows 10', self::SEEN));
        $this->assertTrue(SecurityMail::isNewAccess('10.0.0.1', 'Firefox / Linux', self::SEEN));
    }

    public function testTheLockAlertFiresOnlyWhenTheThresholdIsReached(): void
    {
        $this->assertFalse(SecurityMail::lockJustStarted(4, 5));
        $this->assertTrue(SecurityMail::lockJustStarted(5, 5));
        $this->assertFalse(SecurityMail::lockJustStarted(6, 5));
    }

    public function testThePlainTextPartKeepsOneLinePerLineAndDecodesEntities(): void
    {
        $html = '<html><head><title>Title</title></head><body><h1>Title</h1><p>When: 21:35<br>Device: &lt;i&gt;Chrome</p></body></html>';

        $this->assertSame('Title
When: 21:35
Device: <i>Chrome', SecurityMail::text($html));
    }
}
