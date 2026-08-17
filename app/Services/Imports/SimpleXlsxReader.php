<?php

namespace App\Services\Imports;

use ZipArchive;

class SimpleXlsxReader
{
    /** @return array<string, list<array<string, string|null>>> */
    public function readSheets(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new \RuntimeException("Unable to open spreadsheet: {$path}");
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetNames = $this->readSheetNames($zip);
        $sheets = [];

        foreach ($sheetNames as $name => $sheetPath) {
            $sheets[$name] = $this->readSheetRows($zip, $sheetPath, $sharedStrings);
        }

        $zip->close();

        return $sheets;
    }

    /** @return list<string> */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $document = simplexml_load_string($xml);
        $strings = [];

        foreach ($document->si as $si) {
            if (isset($si->t)) {
                $strings[] = (string) $si->t;

                continue;
            }

            $text = '';

            foreach ($si->r as $run) {
                $text .= (string) $run->t;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    /** @return array<string, string> */
    private function readSheetNames(ZipArchive $zip): array
    {
        $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
        $targets = [];

        foreach ($rels->Relationship as $relationship) {
            $targets[(string) $relationship['Id']] = (string) $relationship['Target'];
        }

        $namespaces = $workbook->getNamespaces(true);
        $mainNs = $namespaces[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $relNs = $namespaces['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $sheets = [];

        foreach ($workbook->children($mainNs)->sheets->sheet as $sheet) {
            $attributes = $sheet->attributes();
            $name = (string) ($attributes['name'] ?? '');
            $relationshipAttributes = $sheet->attributes($relNs);
            $rid = (string) ($relationshipAttributes['id'] ?? '');
            $target = ltrim($targets[$rid] ?? '', '/');

            if ($target === '') {
                continue;
            }

            if (! str_starts_with($target, 'xl/')) {
                $target = 'xl/'.$target;
            }

            $sheets[$name] = $target;
        }

        return $sheets;
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return list<array<string, string|null>>
     */
    private function readSheetRows(ZipArchive $zip, string $sheetPath, array $sharedStrings): array
    {
        $xml = $zip->getFromName($sheetPath);

        if ($xml === false) {
            return [];
        }

        $document = simplexml_load_string($xml);
        $rows = [];

        foreach ($document->sheetData->row as $row) {
            $rowNumber = (int) $row['r'];
            $values = [];

            foreach ($row->c as $cell) {
                [$column] = $this->splitCellReference((string) $cell['r']);
                $values[$column] = $this->cellValue($cell, $sharedStrings);
            }

            $rows[$rowNumber] = $values;
        }

        ksort($rows);

        return array_values($rows);
    }

    /** @return array{0: string, 1: int} */
    private function splitCellReference(string $reference): array
    {
        if (! preg_match('/^([A-Z]+)(\d+)$/', $reference, $matches)) {
            throw new \RuntimeException("Invalid cell reference: {$reference}");
        }

        return [$matches[1], (int) $matches[2]];
    }

    /** @param  list<string>  $sharedStrings */
    private function cellValue(\SimpleXMLElement $cell, array $sharedStrings): ?string
    {
        $type = (string) ($cell['t'] ?? '');

        if ($type === 'inlineStr') {
            return isset($cell->is->t) ? (string) $cell->is->t : null;
        }

        if (! isset($cell->v)) {
            return null;
        }

        $value = (string) $cell->v;

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? null;
        }

        return $value;
    }
}
