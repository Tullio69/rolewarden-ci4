<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Unit\Install;

use CodeIgniter\Test\CIUnitTestCase;
use RoleWarden\Install\SetupState;

final class SetupStateTest extends CIUnitTestCase
{
    private string $dir;
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir  = sys_get_temp_dir() . '/rw-state-' . bin2hex(random_bytes(4));
        $this->file = $this->dir . '/sub/install-manifest.json';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/sub/*') ?: [] as $f) {
            unlink($f);
        }
        @rmdir($this->dir . '/sub');
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function testFileUnderWritable(): void
    {
        $this->assertSame(WRITEPATH . 'rolewarden/install-manifest.json', SetupState::file());
    }

    public function testEmptyWhenMissing(): void
    {
        $s = SetupState::load($this->file);

        $this->assertSame([], $s->choices());
        $this->assertSame([], $s->edits());
        $this->assertFalse($s->done('migrations'));
        $this->assertFalse($s->completed());
    }

    public function testRoundTrip(): void
    {
        $s = new SetupState();
        $s->setChoice('lang', 'it');
        $s->setChoice('superAdmin', ['kind' => 'new', 'username' => 'a']);
        $s->markDone('migrations');
        $s->markDone('migrations');
        $s->recordEdit('app/Config/Filters.php', 'before', 'after');
        $s->recordEdit('app/Config/Routes.php', 'b2', 'a2', false);
        $s->save($this->file);

        $l = SetupState::load($this->file);

        $this->assertSame(['lang' => 'it', 'superAdmin' => ['kind' => 'new', 'username' => 'a']], $l->choices());
        $this->assertTrue($l->done('migrations'));
        $this->assertFalse($l->done('roles'));
        $edits = $l->edits();
        $this->assertCount(2, $edits);
        $this->assertSame('app/Config/Filters.php', $edits[0]['path']);
        $this->assertSame('before', $edits[0]['before']);
        $this->assertSame('after', $edits[0]['after']);
        $this->assertTrue($edits[0]['checked']);
        $this->assertFalse($edits[1]['checked']);
        $this->assertNotFalse(strtotime($edits[0]['at']));
        $this->assertSame([], glob($this->dir . '/sub/*.tmp*'));

        $json = json_decode((string) file_get_contents($this->file), true);
        $this->assertSame(1, $json['version']);
        $this->assertSame(['migrations'], $json['done']);
        $this->assertNull($json['completed_at']);
    }

    public function testCorruptFileIsEmptyState(): void
    {
        mkdir(dirname($this->file), 0777, true);
        file_put_contents($this->file, '{not json');

        $this->assertSame([], SetupState::load($this->file)->choices());

        file_put_contents($this->file, '"a string"');
        $this->assertSame([], SetupState::load($this->file)->edits());
    }

    public function testCompleted(): void
    {
        $s = new SetupState();
        $s->complete();
        $s->save($this->file);

        $this->assertTrue(SetupState::load($this->file)->completed());
    }
}
