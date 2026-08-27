<?php
/**
 * Minimal dependency-free XLSX reader (uses only PHP's built-in ZipArchive + DOM).
 * No Composer / PhpSpreadsheet required, so it works on any plain PHP hosting.
 */
class XlsxReader
{
    private ZipArchive $zip;
    private array $sharedStrings = [];
    private array $sheetNameToTarget = [];

    public function __construct(string $filePath)
    {
        $this->zip = new ZipArchive();
        if ($this->zip->open($filePath) !== true) {
            throw new RuntimeException("ไม่สามารถเปิดไฟล์ xlsx ได้: $filePath");
        }
        $this->loadSharedStrings();
        $this->loadSheetMap();
    }

    public function __destruct()
    {
        $this->zip->close();
    }

    /** @return string[] list of sheet names in workbook order */
    public function sheetNames(): array
    {
        return array_keys($this->sheetNameToTarget);
    }

    private function xmlFromZip(string $entryName): ?DOMDocument
    {
        $xml = $this->zip->getFromName($entryName);
        if ($xml === false) {
            return null;
        }
        $doc = new DOMDocument();
        $doc->loadXML($xml, LIBXML_NOENT);
        return $doc;
    }

    private function loadSharedStrings(): void
    {
        $doc = $this->xmlFromZip('xl/sharedStrings.xml');
        if (!$doc) {
            return;
        }
        foreach ($doc->getElementsByTagName('si') as $si) {
            $text = '';
            foreach ($si->getElementsByTagName('t') as $t) {
                $text .= $t->textContent;
            }
            $this->sharedStrings[] = $text;
        }
    }

    private function loadSheetMap(): void
    {
        $wb = $this->xmlFromZip('xl/workbook.xml');
        $rels = $this->xmlFromZip('xl/_rels/workbook.xml.rels');
        if (!$wb || !$rels) {
            throw new RuntimeException('ไฟล์ xlsx ไม่ถูกต้อง (ไม่พบ workbook.xml)');
        }
        $ridToTarget = [];
        foreach ($rels->getElementsByTagName('Relationship') as $rel) {
            $ridToTarget[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
        }
        foreach ($wb->getElementsByTagName('sheet') as $sheet) {
            $name = $sheet->getAttribute('name');
            $rid = $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
            if ($rid === '') {
                // fallback for namespace-prefixed attribute access
                foreach ($sheet->attributes as $attr) {
                    if (str_ends_with($attr->nodeName, ':id')) {
                        $rid = $attr->nodeValue;
                        break;
                    }
                }
            }
            $target = $ridToTarget[$rid] ?? null;
            if ($target !== null) {
                $target = 'xl/' . ltrim($target, '/');
                $this->sheetNameToTarget[$name] = $target;
            }
        }
    }

    private static function colLettersToIndex(string $letters): int
    {
        $n = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $n = $n * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return $n;
    }

    /**
     * Read a sheet into a dense grid: [rowNum => [colIndex => value]], 1-based.
     * Merged cells are filled so every covered cell carries the top-left value.
     *
     * @return array{grid: array<int, array<int, string>>, maxRow: int, maxCol: int, hiddenRows: array<int, true>, hiddenCols: array<int, true>}
     */
    public function readGrid(string $sheetName, ?int $maxRow = null): array
    {
        if (!isset($this->sheetNameToTarget[$sheetName])) {
            throw new RuntimeException("ไม่พบชีทชื่อ: $sheetName");
        }
        $doc = $this->xmlFromZip($this->sheetNameToTarget[$sheetName]);
        if (!$doc) {
            throw new RuntimeException("อ่านชีทไม่ได้: $sheetName");
        }

        $grid = [];
        $maxColSeen = 0;
        $maxRowSeen = 0;
        $hiddenRows = [];
        $hiddenCols = [];

        // <col min="X" max="Y" hidden="1"/> marks a whole range of columns hidden.
        foreach ($doc->getElementsByTagName('col') as $colEl) {
            if ($colEl->getAttribute('hidden') !== '1') {
                continue;
            }
            $min = (int)$colEl->getAttribute('min');
            $max = (int)$colEl->getAttribute('max');
            for ($c = $min; $c <= $max; $c++) {
                $hiddenCols[$c] = true;
            }
        }

        foreach ($doc->getElementsByTagName('row') as $rowEl) {
            $rowNum = (int)$rowEl->getAttribute('r');
            if ($maxRow !== null && $rowNum > $maxRow) {
                break;
            }
            $maxRowSeen = max($maxRowSeen, $rowNum);
            if ($rowEl->getAttribute('hidden') === '1') {
                $hiddenRows[$rowNum] = true;
            }
            foreach ($rowEl->getElementsByTagName('c') as $cEl) {
                $ref = $cEl->getAttribute('r');
                if (!preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) {
                    continue;
                }
                $colIdx = self::colLettersToIndex($m[1]);
                $maxColSeen = max($maxColSeen, $colIdx);
                $type = $cEl->getAttribute('t');
                $value = '';
                if ($type === 's') {
                    $vEl = $cEl->getElementsByTagName('v')->item(0);
                    if ($vEl !== null) {
                        $idx = (int)$vEl->textContent;
                        $value = $this->sharedStrings[$idx] ?? '';
                    }
                } elseif ($type === 'inlineStr') {
                    $isEl = $cEl->getElementsByTagName('is')->item(0);
                    if ($isEl !== null) {
                        foreach ($isEl->getElementsByTagName('t') as $t) {
                            $value .= $t->textContent;
                        }
                    }
                } else {
                    $vEl = $cEl->getElementsByTagName('v')->item(0);
                    $value = $vEl !== null ? $vEl->textContent : '';
                }
                $grid[$rowNum][$colIdx] = $value;
            }
        }

        // Merge-fill: propagate the top-left value across merged ranges so
        // header rows read correctly regardless of how the template merged cells.
        foreach ($doc->getElementsByTagName('mergeCell') as $mc) {
            $ref = $mc->getAttribute('ref');
            if (!preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $ref, $m)) {
                continue;
            }
            [$c1, $r1, $c2, $r2] = [self::colLettersToIndex($m[1]), (int)$m[2], self::colLettersToIndex($m[3]), (int)$m[4]];
            if ($maxRow !== null && $r1 > $maxRow) {
                continue;
            }
            $topLeft = $grid[$r1][$c1] ?? '';
            for ($r = $r1; $r <= $r2; $r++) {
                if ($maxRow !== null && $r > $maxRow) {
                    break;
                }
                for ($c = $c1; $c <= $c2; $c++) {
                    if (($grid[$r][$c] ?? '') === '') {
                        $grid[$r][$c] = $topLeft;
                    }
                }
            }
        }

        // Some real-world files end up with trailing "phantom" columns — cells that exist in the
        // XML (often just from selecting/formatting past the real data, or a copy-paste artifact)
        // but carry no value in ANY row, header or data. Excel still counts these in its "used
        // range", which would otherwise fail the reference-template structure check for no real
        // reason on every file exported from that person's machine — trim them off the end.
        while ($maxColSeen > 0) {
            $hasContent = false;
            for ($r = 1; $r <= $maxRowSeen; $r++) {
                if (($grid[$r][$maxColSeen] ?? '') !== '') {
                    $hasContent = true;
                    break;
                }
            }
            if ($hasContent) {
                break;
            }
            $maxColSeen--;
        }

        return ['grid' => $grid, 'maxRow' => $maxRowSeen, 'maxCol' => $maxColSeen, 'hiddenRows' => $hiddenRows, 'hiddenCols' => $hiddenCols];
    }
}
