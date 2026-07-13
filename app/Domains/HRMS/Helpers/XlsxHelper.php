<?php

namespace App\Domains\HRMS\Helpers;

use ZipArchive;
use SimpleXMLElement;

class XlsxHelper
{
    /**
     * Export array of rows to Excel (.xlsx) file response
     */
    public static function export(array $headers, array $data, string $filename)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        
        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Could not create temporary zip file");
        }

        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/markup-compatibility/2006">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // 2. _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // 3. xl/workbook.xml
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Sheet1" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);

        // 4. xl/_rels/workbook.xml.rels
        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);

        // 5. xl/styles.xml
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>
  <fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>
  <borders count="1"><border><left/><right/><top/><bottom/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>
</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styles);

        // 6. xl/worksheets/sheet1.xml
        $sheetData = '';
        
        // Add headers
        $rowIndex = 1;
        $sheetData .= '<row r="' . $rowIndex . '">';
        foreach ($headers as $colIndex => $header) {
            $colLetter = self::getColLetter($colIndex);
            $cellRef = $colLetter . $rowIndex;
            $cleanHeader = htmlspecialchars($header, ENT_QUOTES, 'UTF-8');
            $sheetData .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . $cleanHeader . '</t></is></c>';
        }
        $sheetData .= '</row>';

        // Add data rows
        foreach ($data as $rowData) {
            $rowIndex++;
            $sheetData .= '<row r="' . $rowIndex . '">';
            $colIndex = 0;
            foreach ($rowData as $val) {
                $colLetter = self::getColLetter($colIndex);
                $cellRef = $colLetter . $rowIndex;
                if (is_numeric($val) && !is_string($val)) {
                    $sheetData .= '<c r="' . $cellRef . '"><v>' . $val . '</v></c>';
                } else {
                    $cleanVal = htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
                    $sheetData .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . $cleanVal . '</t></is></c>';
                }
                $colIndex++;
            }
            $sheetData .= '</row>';
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>' . $sheetData . '</sheetData>
</worksheet>';
        
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Import Excel (.xlsx) file and return raw array of rows
     */
    public static function import(string $filePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \Exception("Unable to open XLSX file.");
        }

        // Parse shared strings
        $sharedStrings = [];
        if ($zip->locateName('xl/sharedStrings.xml') !== false) {
            $sstXmlContent = $zip->getFromName('xl/sharedStrings.xml');
            $sstXml = simplexml_load_string($sstXmlContent);
            if ($sstXml) {
                foreach ($sstXml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } else {
                        $text = '';
                        if (isset($si->r)) {
                            foreach ($si->r as $r) {
                                $text .= (string) $r->t;
                            }
                        }
                        $sharedStrings[] = $text;
                    }
                }
            }
        }

        // Parse sheet1
        $sheetXmlContent = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXmlContent === false) {
            $zip->close();
            throw new \Exception("XLSX does not contain worksheets/sheet1.xml");
        }

        $sheetXml = simplexml_load_string($sheetXmlContent);
        $zip->close();

        if (!$sheetXml) {
            throw new \Exception("Invalid worksheets/sheet1.xml content.");
        }

        $rows = [];
        foreach ($sheetXml->sheetData->row as $row) {
            $rowIndex = (int) $row['r'];
            $rowData = [];
            
            foreach ($row->c as $cell) {
                $cellRef = (string) $cell['r'];
                $colLetter = preg_replace('/[0-9]/', '', $cellRef);
                $colIndex = self::getColIndex($colLetter);

                $val = '';
                if (isset($cell->v)) {
                    $type = (string) $cell['t'];
                    if ($type === 's') {
                        $idx = (int) $cell->v;
                        $val = $sharedStrings[$idx] ?? '';
                    } else {
                        $val = (string) $cell->v;
                    }
                } elseif (isset($cell->is->t)) {
                    $val = (string) $cell->is->t;
                }

                $rowData[$colIndex] = $val;
            }

            // Fill in missing columns with empty string
            if (!empty($rowData)) {
                $maxCol = max(array_keys($rowData));
                for ($i = 0; $i <= $maxCol; $i++) {
                    if (!isset($rowData[$i])) {
                        $rowData[$i] = '';
                    }
                }
                ksort($rowData);
                $rows[$rowIndex] = $rowData;
            }
        }

        ksort($rows);
        return array_values($rows);
    }

    private static function getColLetter(int $colIndex): string
    {
        $letter = '';
        while ($colIndex >= 0) {
            $letter = chr(($colIndex % 26) + 65) . $letter;
            $colIndex = intval($colIndex / 26) - 1;
        }
        return $letter;
    }

    private static function getColIndex(string $colLetter): int
    {
        $colLetter = strtoupper($colLetter);
        $index = 0;
        $len = strlen($colLetter);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($colLetter[$i]) - 64);
        }
        return $index - 1;
    }
}
