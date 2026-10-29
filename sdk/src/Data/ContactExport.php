<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Data;

/**
 * A downloaded contact export: the CSV as the API sent it, with a header row, and its rows read
 * by that header.
 */
final readonly class ContactExport
{
    public function __construct(
        public string $id,
        public string $csv,
    ) {}

    /**
     * Each contact, keyed by the header row's column names; an empty cell is an empty string.
     *
     * @return list<array<string, string>>
     */
    public function rows(): array
    {
        $lines = array_values(array_filter(explode("\n", $this->csv), static fn (string $line): bool => $line !== ''));

        if ($lines === []) {
            return [];
        }

        $header = array_map(strval(...), str_getcsv($lines[0], escape: ''));
        $rows = [];

        foreach (array_slice($lines, 1) as $line) {
            $cells = array_map(strval(...), str_getcsv($line, escape: ''));
            $rows[] = array_combine($header, array_pad($cells, count($header), ''));
        }

        return $rows;
    }
}
