<?php

declare(strict_types=1);

namespace RoleWarden\Users;

/**
 * Reads a users CSV (SPEC, V6 decisions) into rows, without judging their
 * values: that is UserImport's job. UTF-8 with or without BOM, comma or
 * semicolon picked from the header, a header row naming the columns in any
 * order. File-level problems come back as [code, args] pairs, which the
 * caller turns into RoleWarden.panel.import.* messages.
 */
final class CsvImport
{
    public const MAX_ROWS = 1000;
    public const MAX_BYTES = 1048576;
    public const REQUIRED = ['email', 'username'];
    public const OPTIONAL = ['role', 'password'];

    /**
     * @return array{rows: list<array{line: int, values: array<string, string>}>, errors: list<array{0: string, 1: list<int|string>}>}
     */
    public static function parse(string $content): array
    {
        if (strlen($content) > self::MAX_BYTES) {
            return self::fail('tooLarge', [intdiv(self::MAX_BYTES, 1024)]);
        }

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            return self::fail('notUtf8');
        }

        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];

        while ($lines !== [] && trim((string) end($lines)) === '') {
            array_pop($lines);
        }

        if ($lines === []) {
            return self::fail('empty');
        }

        $header = $lines[0];
        $delimiter = substr_count($header, ';') > substr_count($header, ',') ? ';' : ',';
        $columns = array_map(static fn (?string $name): string => strtolower(trim((string) $name)), str_getcsv($header, $delimiter, '"', ''));

        foreach ($columns as $column) {
            if (! in_array($column, [...self::REQUIRED, ...self::OPTIONAL], true)) {
                return self::fail('unknownColumn', [$column === '' ? '(empty)' : $column]);
            }
        }

        if (count($columns) !== count(array_unique($columns))) {
            return self::fail('duplicateColumn');
        }

        foreach (self::REQUIRED as $column) {
            if (! in_array($column, $columns, true)) {
                return self::fail('missingColumn', [$column]);
            }
        }

        $rows = [];

        foreach (array_slice($lines, 1, null, true) as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line, $delimiter, '"', '');
            $row = ['line' => $index + 1, 'values' => []];

            foreach ($columns as $position => $column) {
                $row['values'][$column] = trim((string) ($values[$position] ?? ''));
            }

            // A row with more fields than the header has its extra values reported, not dropped.
            if (count($values) > count($columns)) {
                $row['values']['_extra'] = (string) (count($values) - count($columns));
            }

            $rows[] = $row;
        }

        if ($rows === []) {
            return self::fail('noRows');
        }

        if (count($rows) > self::MAX_ROWS) {
            return self::fail('tooManyRows', [self::MAX_ROWS]);
        }

        return ['rows' => $rows, 'errors' => []];
    }

    /**
     * @param list<int|string> $args
     *
     * @return array{rows: list<array{line: int, values: array<string, string>}>, errors: list<array{0: string, 1: list<int|string>}>}
     */
    private static function fail(string $code, array $args = []): array
    {
        return ['rows' => [], 'errors' => [[$code, $args]]];
    }
}
