<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Unit\Users;

use PHPUnit\Framework\TestCase;
use RoleWarden\Users\CsvImport;

final class CsvImportTest extends TestCase
{
    public function testColumnsInAnyOrderWithCommasOrSemicolonsAndABom(): void
    {
        foreach (["\xEF\xBB\xBFusername,email\r\ndana,dana@x.test\r\n", "Username;EMAIL\ndana;dana@x.test\n"] as $csv) {
            $result = CsvImport::parse($csv);

            $this->assertSame([], $result['errors']);
            $this->assertSame([['line' => 2, 'values' => ['username' => 'dana', 'email' => 'dana@x.test']]], $result['rows']);
        }
    }

    public function testQuotedValuesKeepTheirSeparatorAndBlankLinesAreSkipped(): void
    {
        $result = CsvImport::parse("email,username,password\n\"a@x.test\",ann,\"p,ss;word\"\n\n");

        $this->assertSame('p,ss;word', $result['rows'][0]['values']['password']);
        $this->assertCount(1, $result['rows']);
    }

    public function testFileProblemsRefuseTheWholeFile(): void
    {
        $this->assertSame([['empty', []]], CsvImport::parse("\n\n")['errors']);
        $this->assertSame([['unknownColumn', ['phone']]], CsvImport::parse("email,username,phone\na@x.test,ann,1\n")['errors']);
        $this->assertSame([['missingColumn', ['username']]], CsvImport::parse("email\na@x.test\n")['errors']);
        $this->assertSame([['duplicateColumn', []]], CsvImport::parse("email,username,email\n")['errors']);
        $this->assertSame([['noRows', []]], CsvImport::parse("email,username\n")['errors']);
        $this->assertSame([['notUtf8', []]], CsvImport::parse("email,username\n\xC3\x28,ann\n")['errors']);
    }

    public function testTooManyRowsOrBytesAreRefused(): void
    {
        $rows = "email,username\n" . str_repeat("a@x.test,ann\n", CsvImport::MAX_ROWS + 1);

        $this->assertSame([['tooManyRows', [CsvImport::MAX_ROWS]]], CsvImport::parse($rows)['errors']);
        $this->assertSame('tooLarge', CsvImport::parse(str_repeat('x', CsvImport::MAX_BYTES + 1))['errors'][0][0]);
    }

    public function testAtMostFiftyRowsMayCarryAPassword(): void
    {
        $header = "email,username,password\n";
        $fifty = str_repeat("a@x.test,ann,secret\n", CsvImport::MAX_PASSWORD_ROWS);

        $this->assertSame([], CsvImport::parse($header . $fifty . "b@x.test,bob,\n")['errors']);
        $this->assertSame([['tooManyPasswords', [CsvImport::MAX_PASSWORD_ROWS]]], CsvImport::parse($header . $fifty . "b@x.test,bob,secret\n")['errors']);
    }

    public function testExtraValuesOnARowAreReportedNotDropped(): void
    {
        $result = CsvImport::parse("email,username\na@x.test,ann,surplus\n");

        $this->assertSame('1', $result['rows'][0]['values']['_extra']);
    }
}
