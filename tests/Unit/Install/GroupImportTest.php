<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Unit\Install;

use CodeIgniter\Test\CIUnitTestCase;
use RoleWarden\Install\GroupImport;

final class GroupImportTest extends CIUnitTestCase
{
    private function plan(int $users = 1): array
    {
        return GroupImport::plan(
            ['admin', 'operatore', 'cliente'],
            ['admin' => 14, 'operatore' => 3],
            ['admin' => $users, 'operatore' => 5],
            ['admin', 'user', 'super-admin'],
        );
    }

    public function testCollidingGroupIsSkippedWithCounts(): void
    {
        $plan = $this->plan();

        $this->assertSame(['operatore', 'cliente'], $plan['import']);
        $this->assertSame(['slug' => 'admin', 'users' => 1, 'permissions' => 14], $plan['skipped'][0]);
    }

    public function testReportItalian(): void
    {
        $this->assertSame([
            'Gruppo "admin" non importato: esiste già il ruolo "admin". 1 utente perde 14 permessi: concedili dal pannello (Ruoli).',
            'Importati: operatore, cliente. Saltati: admin.',
        ], GroupImport::report($this->plan(), 'it'));
    }

    public function testReportPlural(): void
    {
        $this->assertStringContainsString('3 utenti perdono 14 permessi', GroupImport::report($this->plan(3), 'it')[0]);
        $this->assertStringContainsString('3 users lose 14 permissions', GroupImport::report($this->plan(3), 'en')[0]);
        $this->assertStringContainsString('1 user loses 14 permissions', GroupImport::report($this->plan(1), 'en')[0]);
    }

    public function testSkippedWithoutLossHasShortLine(): void
    {
        $plan = GroupImport::plan(['admin'], [], [], ['admin']);

        $this->assertSame([
            'Gruppo "admin" non importato: esiste già il ruolo "admin".',
            'Importati: . Saltati: admin.',
        ], GroupImport::report($plan, 'it'));
    }

    public function testSkippedWithMembersButNoPermissionsNamesTheMembers(): void
    {
        $plan = GroupImport::plan(['user'], [], ['user' => 3], ['user']);

        $this->assertSame('Gruppo "user" non importato: esiste già il ruolo "user". 3 utenti non avranno più quel gruppo.', GroupImport::report($plan, 'it')[0]);
        $this->assertSame('Group "user" not imported: the role "user" already exists. 3 users will no longer be in that group.', GroupImport::report($plan, 'en')[0]);
        $this->assertStringContainsString('1 utente non avrà più quel gruppo.', GroupImport::report(GroupImport::plan(['user'], [], ['user' => 1], ['user']), 'it')[0]);
        $this->assertStringContainsString('1 user will no longer be in that group.', GroupImport::report(GroupImport::plan(['user'], [], ['user' => 1], ['user']), 'en')[0]);
    }

    public function testNoCollisionNoSkippedLine(): void
    {
        $plan = GroupImport::plan(['operatore'], [], [], ['admin']);

        $this->assertSame([], $plan['skipped']);
        $this->assertSame([], GroupImport::report($plan, 'it'));
    }

    /** The line the migration prints when the wizard has the import: an abandoned wizard is not silent. */
    public function testDeferredNotice(): void
    {
        $this->assertStringContainsString('/rolewarden/setup', GroupImport::deferredNotice('en'));
        $this->assertStringStartsWith('Shield groups not imported', GroupImport::deferredNotice('en'));
        $this->assertStringStartsWith('Gruppi di Shield non importati', GroupImport::deferredNotice('it'));
    }
}
