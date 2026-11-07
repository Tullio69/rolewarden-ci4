<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Unit\Settings;

use CodeIgniter\Test\CIUnitTestCase;
use RoleWarden\Settings\Visibility;

final class VisibilityTest extends CIUnitTestCase
{
    public function testAllShowsToEveryone(): void
    {
        $this->assertTrue(Visibility::allows(Visibility::ALL, false));
    }

    public function testAdminsHidesFromUsersWithoutPanelPermission(): void
    {
        $this->assertFalse(Visibility::allows(Visibility::ADMINS, false));
    }

    public function testAdminsShowsToPanelPermissionHolders(): void
    {
        $this->assertTrue(Visibility::allows(Visibility::ADMINS, true));
    }

    public function testInvalidValueReadsAsAll(): void
    {
        $this->assertSame(Visibility::ALL, Visibility::normalize('nonsense'));
        $this->assertSame(Visibility::ALL, Visibility::normalize(null));
        $this->assertSame(Visibility::ADMINS, Visibility::normalize('admins'));
    }
}
