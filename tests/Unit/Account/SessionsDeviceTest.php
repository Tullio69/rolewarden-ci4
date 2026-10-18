<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Unit\Account;

use PHPUnit\Framework\TestCase;
use RoleWarden\Account\Sessions;

final class SessionsDeviceTest extends TestCase
{
    public function testARecognisedAgentGivesBrowserAndSystem(): void
    {
        $this->assertSame(['Chrome', 'Windows 10'], Sessions::device('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36'));
    }

    public function testAnUnknownSystemIsEmptyNotCodeIgnitersEnglishFallback(): void
    {
        $this->assertSame(['', ''], Sessions::device('curl/8.4.0'));
        $this->assertSame(['', ''], Sessions::device(''));
    }
}
