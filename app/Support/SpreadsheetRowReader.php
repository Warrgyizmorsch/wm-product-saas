<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use XMLReader;
use ZipArchive;

/**
 * Reads a spreadsheet one row at a time, so memory stays flat however many
 * rows the file has. PhpSpreadsheet's load() + toArray() needed ~1.9 GB for a
 * 1,00,000-row .xlsx; this streams the sheet XML instead.
 *
 * Each yielded row is a 0-based list of cell values as strings (or null),
 * in the same shape Worksheet::toArray() produced. Dates come back as
 * "Y-m-d" (or "Y-m-d H:i:s" when they carry a time).
 *
 * .xlsx and .csv/.txt are streamed; .xls (old binary format) falls back to
 * PhpSpreadsheet, which is fine for the sizes that format allows.
 */
class SpreadsheetRowReader
{
    /** Built-in Excel number formats that are dates/times. */
    private const BUILTIN_DATE_FORMATS = [14, 15, 16, 17, 18, 19, 20, 21, 22, 45, 46, 47];

    /**
     * @return \Generator<int, array<int, string|null>>
     */
    public static function rows(string $path, ?string $extension = null): \Generator
    {
        $extension = strtolower($extension ?? pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'xlsx') {
            yield from self::xlsxRows($path);
        } elseif (in_array($extension, ['csv', 'txt'], true)) {
            yield from self::csvRows($path);
        } else {
            foreach (IOFactory::load($path)->getActiveSheet()->toArray() as $row) {
                yield array_map(fn ($v) => $v === null ? null : (string) $v, $row);
            }
        }
    }

    /**
     * Last used row number without reading the rows: an .xlsx states it in
     * the sheet's <dimension ref="A1:U100001"> tag. Returns null when the file
     * doesn't say (other formats, or writers that omit the tag).
     */
    public static function lastRowNumber(string $path, ?string $extension = null): ?int
    {
        if (strtolower($extension ?? pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx') {
            return null;
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return null;
        }
        $sheetPath = self::activeSheetPath($zip);
        $zip->close();

        $reader = new XMLReader();
        if (! $reader->open('zip://' . str_replace('\\', '/', $path) . '#' . $sheetPath, null, LIBXML_NONET)) {
            return null;
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }
                if ($reader->localName === 'dimension') {
                    $ref = (string) $reader->getAttribute('ref');

                    return preg_match('/(\d+)$/', $ref, $m) ? (int) $m[1] : null;
                }
                if ($reader->localName === 'sheetData') {
                    return null; // the tag always comes before the data when present
                }
            }
        } finally {
            $reader->close();
        }

        return null;
    }

    private static function csvRows(string $path): \Generator
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Could not open the uploaded file.');
        }

        try {
            $first = true;
            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                if ($first && isset($row[0])) {
                    $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]); // strip UTF-8 BOM
                    $first = false;
                }
                yield array_map(fn ($v) => $v === null || $v === '' ? null : (string) $v, $row);
            }
        } finally {
            fclose($handle);
        }
    }

    private static function xlsxRows(string $path): \Generator
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('The uploaded file is not a valid .xlsx file.');
        }
        $sheetPath = self::activeSheetPath($zip);
        $sharedStrings = self::sharedStrings($zip);
        $dateStyles = self::dateStyleIndexes($zip);
        $zip->close();

        $reader = new XMLReader();
        if (! $reader->open('zip://' . str_replace('\\', '/', $path) . '#' . $sheetPath, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new \RuntimeException('Could not read the worksheet in the uploaded file.');
        }

        try {
            // One pass over the sheet XML: one parser, no per-row sub-parsers.
            $expectedRow = 1;
            $cells = [];
            $nextCol = 0;
            $col = 0;
            $type = 'n';
            $style = 0;
            $text = null;      // text of the current <v> or inline <t>
            $capture = false;  // inside a <v> or <t> whose text we want

            while ($reader->read()) {
                $node = $reader->nodeType;

                if ($node === XMLReader::ELEMENT) {
                    $name = $reader->localName;
                    if ($name === 'c') {
                        $ref = $reader->getAttribute('r');
                        $col = $ref !== null ? self::columnIndex($ref) : $nextCol;
                        $nextCol = $col + 1;
                        $type = $reader->getAttribute('t') ?? 'n';
                        $style = (int) $reader->getAttribute('s');
                        $text = null;
                        if ($reader->isEmptyElement) {
                            $cells[$col] = null;
                        }
                    } elseif ($name === 'v' || ($name === 't' && $type === 'inlineStr')) {
                        $capture = ! $reader->isEmptyElement;
                        $text ??= '';
                    } elseif ($name === 'row') {
                        // Empty rows are omitted from the XML; keep numbering like toArray() did.
                        $rowNumber = (int) ($reader->getAttribute('r') ?: $expectedRow);
                        for (; $expectedRow < $rowNumber; $expectedRow++) {
                            yield [];
                        }
                        $expectedRow = $rowNumber + 1;
                        $cells = [];
                        $nextCol = 0;
                        if ($reader->isEmptyElement) {
                            yield [];
                        }
                    }
                } elseif ($capture && ($node === XMLReader::TEXT || $node === XMLReader::CDATA || $node === XMLReader::SIGNIFICANT_WHITESPACE || $node === XMLReader::WHITESPACE)) {
                    $text .= $reader->value;
                } elseif ($node === XMLReader::END_ELEMENT) {
                    $name = $reader->localName;
                    if ($name === 'v' || $name === 't') {
                        $capture = false;
                    } elseif ($name === 'c') {
                        $value = match ($type) {
                            's' => $text === null ? null : ($sharedStrings[(int) $text] ?? null),
                            'inlineStr', 'str', 'e' => $text,
                            'b' => $text === null ? null : ($text === '1' ? 'TRUE' : 'FALSE'),
                            default => self::numericValue($text, isset($dateStyles[$style])),
                        };
                        $cells[$col] = ($value === null || $value === '') ? null : $value;
                    } elseif ($name === 'row') {
                        if ($cells === []) {
                            yield [];
                            continue;
                        }
                        $row = array_fill(0, max(array_keys($cells)) + 1, null);
                        foreach ($cells as $c => $value) {
                            $row[$c] = $value;
                        }
                        yield $row;
                    }
                }
            }
        } finally {
            $reader->close();
        }
    }

    private static function numericValue(?string $raw, bool $isDate): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if ($isDate && is_numeric($raw)) {
            $date = ExcelDate::excelToDateTimeObject((float) $raw);

            return fmod((float) $raw, 1.0) === 0.0 ? $date->format('Y-m-d') : $date->format('Y-m-d H:i:s');
        }

        // Whole numbers without a trailing ".0", like toArray() showed them.
        if (is_numeric($raw) && (float) $raw == (int) $raw && ! str_contains(strtolower($raw), 'e') && abs((float) $raw) < PHP_INT_MAX) {
            return (string) (int) $raw;
        }

        return $raw;
    }

    /** "AB12" -> 27 (0-based). */
    private static function columnIndex(string $cellRef): int
    {
        static $cache = [];
        $letters = rtrim(strtoupper($cellRef), '0123456789');
        if (isset($cache[$letters])) {
            return $cache[$letters];
        }
        $index = 0;
        foreach (str_split($letters) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }

        return $cache[$letters] = $index - 1;
    }

    private static function activeSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $fallback = $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;

        if ($workbook === false || $rels === false) {
            return $fallback ?? throw new \RuntimeException('The uploaded file has no worksheet.');
        }

        $wb = simplexml_load_string($workbook, \SimpleXMLElement::class, LIBXML_NONET);
        $activeTab = (int) ($wb->bookViews->workbookView['activeTab'] ?? 0);
        $sheets = $wb->sheets->sheet;
        $sheet = $sheets[$activeTab] ?? $sheets[0] ?? null;
        $relId = $sheet ? (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : '';

        $relsXml = simplexml_load_string($rels, \SimpleXMLElement::class, LIBXML_NONET);
        foreach ($relsXml->Relationship as $rel) {
            if ((string) $rel['Id'] === $relId) {
                $target = ltrim((string) $rel['Target'], '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
            }
        }

        return $fallback ?? throw new \RuntimeException('The uploaded file has no worksheet.');
    }

    /** @return array<int, string> */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $reader = new XMLReader();
        $reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        unset($xml);

        $strings = [];
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
                $text = '';
                $depth = $reader->depth;
                if (! $reader->isEmptyElement) {
                    $phoneticDepth = null;
                    while ($reader->read() && $reader->depth > $depth) {
                        if ($phoneticDepth !== null && $reader->depth <= $phoneticDepth) {
                            $phoneticDepth = null;
                        }
                        if ($reader->nodeType !== XMLReader::ELEMENT) {
                            continue;
                        }
                        // Plain <t> and rich-text <r><t>, but not phonetic <rPh> runs.
                        if ($reader->localName === 'rPh' && ! $reader->isEmptyElement) {
                            $phoneticDepth = $reader->depth;
                        } elseif ($reader->localName === 't' && $phoneticDepth === null) {
                            $text .= $reader->readString();
                        }
                    }
                }
                $strings[] = $text;
            }
        }

        return $strings;
    }

    /**
     * Indexes of cellXfs styles whose number format is a date/time.
     *
     * @return array<int, true>
     */
    private static function dateStyleIndexes(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/styles.xml');
        if ($xml === false) {
            return [];
        }

        $styles = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
        if (! $styles) {
            return [];
        }

        $customDate = [];
        foreach ($styles->numFmts->numFmt ?? [] as $fmt) {
            // Strip quoted text and [colour]/[locale] blocks before looking for d/m/y/h/s.
            $code = preg_replace('/"[^"]*"|\[[^\]]*\]|\\\\./', '', (string) $fmt['formatCode']);
            if (preg_match('/[dmyhs]/i', $code)) {
                $customDate[(int) $fmt['numFmtId']] = true;
            }
        }

        $dateStyles = [];
        $index = 0;
        foreach ($styles->cellXfs->xf ?? [] as $xf) {
            $numFmtId = (int) $xf['numFmtId'];
            if (in_array($numFmtId, self::BUILTIN_DATE_FORMATS, true) || isset($customDate[$numFmtId])) {
                $dateStyles[$index] = true;
            }
            $index++;
        }

        return $dateStyles;
    }
}
