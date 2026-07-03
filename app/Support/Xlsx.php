<?php

namespace App\Support;

use ZipArchive;

/**
 * Minimal, dependency-free .xlsx writer (OOXML via ZipArchive).
 * Every cell is written as an inline string — fine for report exports.
 */
class Xlsx
{
    /**
     * @param  array<int,array{name:string,rows:array<int,array<int,string|int|float>>}>  $sheets
     * @return string binary .xlsx contents
     */
    public static function build(array $sheets): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', self::contentTypes(count($sheets)));
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', self::workbook($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels(count($sheets)));

        foreach (array_values($sheets) as $i => $sheet) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', self::sheet($sheet['rows']));
        }

        $zip->close();
        $data = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $data;
    }

    protected static function contentTypes(int $n): string
    {
        $overrides = '';
        for ($i = 1; $i <= $n; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .$overrides
            .'</Types>';
    }

    protected static function workbook(array $sheets): string
    {
        $s = '';
        foreach (array_values($sheets) as $i => $sheet) {
            $s .= '<sheet name="' . self::esc(mb_substr($sheet['name'], 0, 31)) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>' . $s . '</sheets></workbook>';
    }

    protected static function workbookRels(int $n): string
    {
        $r = '';
        for ($i = 1; $i <= $n; $i++) {
            $r .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $r . '</Relationships>';
    }

    protected static function sheet(array $rows): string
    {
        $body = '';
        foreach (array_values($rows) as $r => $cells) {
            $rowNum = $r + 1;
            $body .= '<row r="' . $rowNum . '">';
            foreach (array_values($cells) as $c => $val) {
                $ref = self::colLetter($c) . $rowNum;
                $body .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . self::esc((string) $val) . '</t></is></c>';
            }
            $body .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>' . $body . '</sheetData></worksheet>';
    }

    protected static function colLetter(int $i): string
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(65 + $m) . $s;
            $i = intdiv($i - 1, 26);
        }

        return $s;
    }

    protected static function esc(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
