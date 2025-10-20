<?php

namespace modules\bulkUserImport\services;

use RuntimeException;
use ZipArchive;
use SimpleXMLElement;

/**
 * Minimal XLSX parser without external dependencies.
 *
 * Only the features used by the bulk import workflow are supported.
 */
class XlsxParser
{
    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>}
     */
    public function parse(string $filePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('Unable to open XLSX archive.');
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetPath = $this->resolveFirstSheetPath($zip);
        $sheetXml = $zip->getFromName($sheetPath);

        if ($sheetXml === false) {
            $zip->close();
            throw new RuntimeException('Unable to read worksheet data from XLSX file.');
        }

        $sheet = new SimpleXMLElement($sheetXml);
        $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $headers = [];
        $rows = [];
        $isHeaderRow = true;

        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            $maxIndex = -1;

            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $colLetters = preg_replace('/\d+/', '', $ref);
                $colIndex = $this->columnToIndex($colLetters);
                $maxIndex = max($maxIndex, $colIndex);
                $cells[$colIndex] = $this->extractCellValue($cell, $sharedStrings);
            }

            if ($maxIndex < 0) {
                continue;
            }

            $normalizedRow = [];
            for ($i = 0; $i <= $maxIndex; $i++) {
                $normalizedRow[] = isset($cells[$i]) ? $cells[$i] : '';
            }

            if ($isHeaderRow) {
                $headers = $normalizedRow;
                $isHeaderRow = false;
                continue;
            }

            if ($this->isRowEmpty($normalizedRow)) {
                continue;
            }

            $rows[] = $normalizedRow;
        }

        $zip->close();

        if (empty($headers)) {
            throw new RuntimeException('The XLSX file does not contain header information.');
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return string[]
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $data = $zip->getFromName('xl/sharedStrings.xml');
        if ($data === false) {
            return [];
        }

        $xml = new SimpleXMLElement($data);
        $xml->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $strings = [];
        foreach ($xml->si as $si) {
            // Shared strings may contain multiple text nodes.
            $text = '';
            if (isset($si->t)) {
                $text = (string) $si->t;
            } else {
                foreach ($si->r as $run) {
                    if (isset($run->t)) {
                        $text .= (string) $run->t;
                    }
                }
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function resolveFirstSheetPath(ZipArchive $zip): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        if ($workbookXml === false) {
            throw new RuntimeException('The XLSX file is missing workbook metadata.');
        }

        $workbook = new SimpleXMLElement($workbookXml);
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $firstSheet = $workbook->sheets->sheet[0] ?? null;
        if ($firstSheet === null) {
            throw new RuntimeException('The XLSX workbook does not contain any sheets.');
        }

        $relationshipId = (string) $firstSheet['r:id'];
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($relsXml === false) {
            throw new RuntimeException('Unable to resolve worksheet relationship in XLSX file.');
        }

        $rels = new SimpleXMLElement($relsXml);
        $rels->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        foreach ($rels->Relationship as $rel) {
            if ((string) $rel['Id'] === $relationshipId) {
                $target = (string) $rel['Target'];
                if ($target === '') {
                    break;
                }

                // Normalize target path variations such as absolute and ../ references
                $target = ltrim($target, '/');
                if (strpos($target, '../') === 0) {
                    // Remove ../ prefixes
                    while (strpos($target, '../') === 0) {
                        $target = substr($target, 3);
                    }
                }

                if (strpos($target, 'xl/') === 0) {
                    return $target;
                }

                return 'xl/' . $target;
            }
        }

        $fallback = $this->findFirstWorksheet($zip);
        if ($fallback !== null) {
            return $fallback;
        }

        throw new RuntimeException('Unable to locate the first worksheet in XLSX file.');
    }

    private function columnToIndex(string $column): int
    {
        $column = strtoupper($column);
        $index = 0;
        $length = strlen($column);

        for ($i = 0; $i < $length; $i++) {
            $index *= 26;
            $index += ord($column[$i]) - ord('A') + 1;
        }

        return $index - 1;
    }

    private function extractCellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];

        if ($type === 's') {
            $index = isset($cell->v) ? (int) $cell->v : null;
            return $index !== null && isset($sharedStrings[$index]) ? $sharedStrings[$index] : '';
        }

        if ($type === 'inlineStr') {
            if (isset($cell->is->t)) {
                return (string) $cell->is->t;
            }

            $value = '';
            foreach ($cell->is->r as $run) {
                if (isset($run->t)) {
                    $value .= (string) $run->t;
                }
            }

            return $value;
        }

        if (isset($cell->v)) {
            return (string) $cell->v;
        }

        return '';
    }

    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function findFirstWorksheet(ZipArchive $zip): ?string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat || !isset($stat['name'])) {
                continue;
            }

            $name = $stat['name'];
            if (preg_match('~^xl/worksheets/[^/]+\.xml$~i', $name)) {
                return $name;
            }
        }

        return null;
    }
}
