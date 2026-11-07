<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Unit\Settings;

use CodeIgniter\Test\CIUnitTestCase;
use RoleWarden\Settings\PanelRole;

final class PanelRoleTest extends CIUnitTestCase
{
    public function testUnsetMeansAdmin(): void
    {
        $this->assertSame('admin', PanelRole::resolve(null));
    }

    public function testEmptyMeansNobody(): void
    {
        $this->assertNull(PanelRole::resolve(''));
    }

    public function testAStoredSlugIsKept(): void
    {
        $this->assertSame('helpdesk-admin', PanelRole::resolve('helpdesk-admin'));
    }
}
