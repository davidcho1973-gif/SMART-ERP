<?php

namespace App\Support;

use ZipArchive;

/**
 * Fully-styled bi-weekly payroll register (.xlsx) that reproduces the NAHSHON
 * MEP LLC timesheet/payroll workbook: same colors, fonts, borders, merged
 * header blocks, live formulas, company logo, bank-info footer, and a
 * landscape fit-to-one-page print setup.
 *
 * Layout per row (0-based columns):
 *   0 Count · 1 ID · 2 Name · 3 Position · 4 Reg.Rate · 5 OT rate,
 *   then per week: 7 day columns + weekTotal + weekReg + weekOT,
 *   then TotalHrs · TotalReg · TotalAmount · OTHrs · OTAmount · GrandTotal.
 */
class PayrollXlsx
{
    // fills from the original workbook
    private const BLUE_LT = 'FFD9E2F3';

    private const BLUE_MD = 'FFB4C6E7';

    private const WEEK_YELLOW = 'FFFFE598';

    private const PEACH = 'FFF7CAAC';

    private const GREEN = 'FFE2EFD9';

    private const ORANGE_LT = 'FFFBE4D5';

    private const YELLOW = 'FFFFFF00';

    private const GREY = 'FFF2F2F2';

    private const CURRENCY_FMT = '_(&quot;$&quot;* #,##0.00_);_(&quot;$&quot;* \\(#,##0.00\\);_(&quot;$&quot;* &quot;-&quot;??_);_(@_)';

    private const HOURS_FMT = '0.0_);[Red]\\(0.0\\)';

    /** @var array<string,int> style-signature → cellXfs index */
    private array $xfIndex = [];

    private array $fonts = [];

    private array $fills = [];

    private array $borders = [];

    private array $xfs = [];

    /** @var array<int,array<int,array{v:mixed,f:?string,s:int,str:bool}>> [row][col] */
    private array $cells = [];

    private array $merges = [];

    private array $rowHeights = [];

    /**
     * @param array{
     *   sheetName:string, project:string, rangeLabel:string, companyLine:string,
     *   weeks:array<int,array{month:string,monthEnd:string,days:array<int,array{num:int,dow:string,date:string}>}>,
     *   workers:array<int,array{tag:string,name:string,position:string,rate:?float,hours:array<string,float>}>,
     *   bankInfo:array<int,string>, logo:?string
     * } $o
     */
    public static function build(array $o): string
    {
        return (new self)->render($o);
    }

    private function render(array $o): string
    {
        // base styles — index 0 of each collection is the workbook default
        $this->fonts = ['<font><sz val="11"/><name val="Calibri"/></font>'];
        $this->fills = ['<fill><patternFill patternType="none"/></fill>', '<fill><patternFill patternType="gray125"/></fill>'];
        $this->borders = ['<border><left/><right/><top/><bottom/><diagonal/></border>'];
        $this->xfs = [];

        $weeks = $o['weeks'];
        $W = count($weeks);
        $slots = max(16, count($o['workers']) + 2);
        $firstData = 6;
        $lastData = $firstData + $slots - 1;
        $sumRow = $lastData + 1;
        $countRow = $lastData + 2;

        $col = fn (int $i) => Xlsx::colLetter($i);
        $wkBase = fn (int $w) => 6 + $w * 10;           // first day column of week w
        $T0 = 6 + $W * 10;                               // TotalHrs column
        [$cTot, $cReg, $cAmt, $cOtH, $cOtA, $cGrand] = [$T0, $T0 + 1, $T0 + 2, $T0 + 3, $T0 + 4, $T0 + 5];

        // ---------- row 1: address + project title ----------
        $this->rowHeights[1] = 37.8;
        $this->set(1, 3, $o['companyLine'], $this->st('Calibri', 11, true, null, null, null, false, 'center', 'center', true));
        $this->merges[] = 'D1:E1';
        $this->set(1, 11, 'Project : '.$o['project'], $this->st('Calibri', 26, true, null, null, null, false, 'center', 'center'));
        $this->merges[] = 'L1:'.$col($cGrand).'1';

        // ---------- row 2: date range ----------
        $this->rowHeights[2] = 39.0;
        $sRange = $this->st('Arial', 24, true, null, null, null, false, 'center', 'center', false, 'b');
        $this->set(2, $T0, $o['rangeLabel'], $sRange);
        for ($i = $T0 + 1; $i <= $cGrand; $i++) {
            $this->set(2, $i, null, $sRange);
        }
        $this->merges[] = $col($T0).'2:'.$col($cGrand).'2';

        // ---------- rows 3-5: table header ----------
        foreach ([3, 4, 5] as $r) {
            $this->rowHeights[$r] = 25.2;
        }
        $hdr = fn (?string $fill, string $font = 'Arial', float $size = 10, ?string $color = null) => $this->st($font, $size, true, $color, $fill, 'thin', false, 'center', 'center', true);

        // blank bordered corner A3:D4
        for ($r = 3; $r <= 4; $r++) {
            for ($c = 0; $c <= 3; $c++) {
                $this->set($r, $c, null, $hdr(null));
            }
        }
        // months over the rate columns + Date row
        $this->set(3, 4, $weeks[0]['month'], $hdr(self::BLUE_LT));
        $this->set(3, 5, $weeks[0]['month'], $hdr(self::BLUE_LT));
        $this->set(4, 4, 'Date', $hdr(self::BLUE_LT));
        $this->set(4, 5, 'Date', $hdr(self::BLUE_LT));

        $ord = ['1ST', '2ND', '3RD', '4TH'];
        $ordLc = ['1st', '2nd', '3rd', '4th'];
        foreach ($weeks as $w => $wk) {
            $b = $wkBase($w);
            if ($w === 0) {
                $this->set(3, $b, null, $hdr(self::BLUE_LT));
                $this->set(3, $b + 1, ($ord[$w] ?? ($w + 1).'TH').' WEEK', $hdr(null));
                for ($i = $b + 2; $i <= $b + 9; $i++) {
                    $this->set(3, $i, null, $hdr(null));
                }
                $this->merges[] = $col($b + 1).'3:'.$col($b + 9).'3';
            } else {
                $this->set(3, $b, $wk['month'], $hdr(self::BLUE_LT));
                $this->set(3, $b + 1, null, $hdr(self::BLUE_LT));
                $this->set(3, $b + 2, $wk['monthEnd'], $hdr(self::BLUE_LT));
                $this->set(3, $b + 3, ($ord[$w] ?? ($w + 1).'TH').' WEEK', $hdr(null));
                for ($i = $b + 4; $i <= $b + 9; $i++) {
                    $this->set(3, $i, null, $hdr(null));
                }
                $this->merges[] = $col($b + 3).'3:'.$col($b + 9).'3';
            }
            // day numbers (first two light blue, rest mid blue — as in the original)
            foreach ($wk['days'] as $i => $d) {
                $this->set(4, $b + $i, $d['num'], $hdr($i < 2 ? self::BLUE_LT : self::BLUE_MD));
            }
            // vertical week-summary headers spanning rows 4-5
            $this->set(4, $b + 7, ($ordLc[$w] ?? ($w + 1).'th')." Week\nTotal Hrs", $hdr(self::WEEK_YELLOW, 'Arial', 10, 'FFFF0000'));
            $this->set(4, $b + 8, ($ordLc[$w] ?? ($w + 1).'th')." Week\nReg Hrs", $hdr(self::WEEK_YELLOW));
            $this->set(4, $b + 9, ($ordLc[$w] ?? ($w + 1).'th')." Week\nOver Time", $hdr(self::WEEK_YELLOW));
            foreach ([7, 8, 9] as $k) {
                $this->set(5, $b + $k, null, $hdr(self::WEEK_YELLOW));
                $this->merges[] = $col($b + $k).'4:'.$col($b + $k).'5';
            }
            foreach ($wk['days'] as $i => $d) {
                $this->set(5, $b + $i, $d['dow'], $hdr(null));
            }
        }

        // row-5 lead headers
        $this->set(5, 0, 'Count', $hdr(null, 'Calibri', 11));
        $this->set(5, 1, 'ID', $hdr(null));
        $this->set(5, 2, 'Name', $hdr(null));
        $this->set(5, 3, 'Position', $hdr(null));
        $this->set(5, 4, "Reg.Rate\nHrs", $hdr(null));
        $this->set(5, 5, 'over time', $hdr(null));

        // grand-summary vertical headers rows 3-5
        $tots = [
            [$cTot, "Total\nHrs", self::PEACH, 10.0],
            [$cReg, "Total\nReg.Hrs", self::GREEN, 10.0],
            [$cAmt, "Total\nAmount", self::GREEN, 11.0],
            [$cOtH, "Over\nTime\nHrs", self::ORANGE_LT, 10.0],
            [$cOtA, "Over Time\nTotal\nAmount", self::ORANGE_LT, 11.0],
            [$cGrand, '2Wks Total by Hours', self::YELLOW, 20.0],
        ];
        foreach ($tots as [$c, $label, $fill, $size]) {
            $s = $hdr($fill, 'Arial', $size);
            $this->set(3, $c, $label, $s);
            $this->set(4, $c, null, $s);
            $this->set(5, $c, null, $s);
            $this->merges[] = $col($c).'3:'.$col($c).'5';
        }

        // ---------- data rows ----------
        $sTag = $this->st('Arial', 11, true, null, self::GREY, 'thin', false, 'center', 'center');
        $sName = $this->st('Arial', 11, false, null, self::GREY, 'thin', false, 'left', 'center');
        $sTagE = $this->st('Arial', 11, true, null, null, 'thin', false, 'center', 'center');
        $sNameE = $this->st('Arial', 11, false, null, null, 'thin', false, 'left', 'center');
        $sPos = $this->st('Arial', 11, true, null, null, 'thin', false, 'center', 'center');
        $sMoney = $this->st('Arial', 11, true, null, null, 'thin', true, 'center', 'center');
        $sHours = $this->st('Arial', 11, true, null, null, 'thin', false, 'center', 'center');
        $sWkTot = $this->st('Calibri', 11, true, 'FFFF0000', null, 'thin', false, 'center', 'center');
        $sWkReg = $this->st('Calibri', 11, true, null, null, 'thin', false, 'center', 'center');
        $sTotH = $this->st('Arial', 11, true, null, self::PEACH, 'thin', false, 'center', 'center');
        $sTotR = $this->st('Arial', 11, true, null, self::GREEN, 'thin', false, 'center', 'center');
        $sTotA = $this->st('Arial', 11, true, null, self::GREEN, 'thin', true, 'center', 'center');
        $sOtH = $this->st('Arial', 11, true, null, self::ORANGE_LT, 'thin', false, 'center', 'center');
        $sOtA = $this->st('Arial', 11, true, null, self::ORANGE_LT, 'thin', true, 'center', 'center');
        $sGrand = $this->st('Arial', 20, true, null, self::YELLOW, 'thin', true, 'center', 'center');
        $sCount = $this->st('Calibri', 11, false, null, null, 'thin', false, 'center', 'center');

        for ($i = 0; $i < $slots; $i++) {
            $r = $firstData + $i;
            $this->rowHeights[$r] = 25.2;
            $wkr = $o['workers'][$i] ?? null;

            $this->set($r, 0, $wkr ? $i + 1 : null, $sCount);
            $this->set($r, 1, $wkr['tag'] ?? null, $wkr ? $sTag : $sTagE);
            $this->set($r, 2, $wkr['name'] ?? null, $wkr ? $sName : $sNameE);
            $this->set($r, 3, $wkr['position'] ?? null, $sPos);
            $this->set($r, 4, $wkr['rate'] ?? null, $sMoney);
            $this->setF($r, 5, $wkr ? 'SUM(E'.$r.'*1.5)' : null, $sMoney);

            foreach ($weeks as $w => $wk) {
                $b = $wkBase($w);
                foreach ($wk['days'] as $di => $d) {
                    $h = $wkr['hours'][$d['date']] ?? null;
                    $this->set($r, $b + $di, $h, $sHours);
                }
                $d1 = $col($b).$r;
                $d7 = $col($b + 6).$r;
                $tc = $col($b + 7).$r;
                $this->setF($r, $b + 7, $wkr ? "SUM($d1:$d7)" : null, $sWkTot);
                $this->setF($r, $b + 8, $wkr ? "MIN($tc, 40)" : null, $sWkReg);
                $this->setF($r, $b + 9, $wkr ? "MAX($tc - 40, 0)" : null, $sWkReg);
            }

            $wkTotRefs = implode(',', array_map(fn ($w) => $col($wkBase($w) + 7).$r, array_keys($weeks)));
            $regSum = implode('+', array_map(fn ($w) => $col($wkBase($w) + 8).$r, array_keys($weeks)));
            $otSum = implode('+', array_map(fn ($w) => $col($wkBase($w) + 9).$r, array_keys($weeks)));
            $this->setF($r, $cTot, $wkr ? "SUM($wkTotRefs)" : null, $sTotH);
            $this->setF($r, $cReg, $wkr ? $regSum : null, $sTotR);
            $this->setF($r, $cAmt, $wkr ? 'E'.$r.'*'.$col($cReg).$r : null, $sTotA);
            $this->setF($r, $cOtH, $wkr ? $otSum : null, $sOtH);
            $this->setF($r, $cOtA, $wkr ? 'F'.$r.'*'.$col($cOtH).$r : null, $sOtA);
            $this->setF($r, $cGrand, $wkr ? $col($cAmt).$r.'+'.$col($cOtA).$r : null, $sGrand);
        }

        // ---------- footer: per-day totals + counts ----------
        $this->rowHeights[$sumRow] = 19.8;
        $this->rowHeights[$countRow] = 19.8;
        $sFootLbl = $this->st('Arial', 10, false, null, null, 'thin', false, null, 'center');
        $sFootDay = $this->st('Arial', 9, true, 'FFFF6D01', null, 'thin', false, 'center', 'center', false, null, self::HOURS_FMT);
        $sFootWk = $this->st('Arial', 9, true, 'FFFF0000', self::WEEK_YELLOW, 'thin', false, 'center', 'center', false, null, self::HOURS_FMT);
        $sFootPlain = $this->st('Arial', 10, false, null, null, 'thin', false, null, 'center');

        $this->set($sumRow, 1, '# of Hours Worked', $sFootLbl);
        $this->set($countRow, 1, ' ', $sFootLbl);
        foreach ([0, 2, 3, 4, 5] as $c) {
            $this->set($sumRow, $c, null, $sFootPlain);
            $this->set($countRow, $c, null, $sFootPlain);
        }
        foreach ($weeks as $w => $wk) {
            $b = $wkBase($w);
            for ($i = 0; $i < 7; $i++) {
                $L = $col($b + $i);
                $this->setF($sumRow, $b + $i, "SUM({$L}{$firstData}:{$L}{$lastData})", $sFootDay);
                $this->setF($countRow, $b + $i, "COUNT({$L}{$firstData}:{$L}{$lastData})", $sFootDay);
            }
            $L = $col($b + 7);
            $this->setF($sumRow, $b + 7, "SUM({$L}{$firstData}:{$L}{$lastData})", $sFootWk);
            $this->set($countRow, $b + 7, null, $sFootPlain);
            foreach ([8, 9] as $k) {
                $this->set($sumRow, $b + $k, null, $sFootPlain);
                $this->set($countRow, $b + $k, null, $sFootPlain);
            }
        }
        $L = $col($cTot);
        $this->setF($sumRow, $cTot, "SUM({$L}{$firstData}:{$L}{$lastData})", $sFootWk);

        // TOTAL label + grand total on the count row
        $sTotalLbl = $this->st('Calibri', 11, true, 'FFFF0000', self::YELLOW, 'thin', false, 'center', 'center');
        $this->set($countRow, $cOtH, 'TOTAL', $sTotalLbl);
        $this->set($countRow, $cOtA, null, $sTotalLbl);
        $this->merges[] = $col($cOtH).$countRow.':'.$col($cOtA).$countRow;
        $G = $col($cGrand);
        $this->setF($countRow, $cGrand, "SUM({$G}{$firstData}:{$G}{$lastData})",
            $this->st('Calibri', 20, true, 'FFFF0000', self::YELLOW, 'thin', true, 'right', 'center'));

        // ---------- bank info (starts right under the count row, like the original) ----------
        $r = $countRow + 1;
        $sBank = $this->st('Calibri', 11, false, null, null, null, false, null, null);
        foreach ($o['bankInfo'] as $line) {
            $this->set($r++, 0, $line, $sBank);
        }
        $lastRow = $r - 1;

        return $this->zip($o, $W, $cGrand, $lastRow);
    }

    // =============== workbook assembly ===============

    private function zip(array $o, int $W, int $lastCol, int $lastRow): string
    {
        $hasLogo = ! empty($o['logo']);
        $sheetName = mb_substr(str_replace(['\\', '/', '*', '[', ']', ':', '?'], '.', $o['sheetName']), 0, 31);

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .($hasLogo ? '<Default Extension="png" ContentType="image/png"/>' : '')
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .($hasLogo ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '')
            .'</Types>';
        $zip->addFromString('[Content_Types].xml', $ct);

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$this->esc($sheetName).'" sheetId="1" r:id="rId1"/></sheets>'
            .'<definedNames><definedName name="_xlnm.Print_Area" localSheetId="0">\''.$this->esc($sheetName).'\'!$A$1:$'.Xlsx::colLetter($lastCol).'$'.$lastRow.'</definedName></definedNames>'
            .'</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($o, $W, $hasLogo));

        if ($hasLogo) {
            $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
                .'</Relationships>');
            // same one-cell anchor as the original workbook (col B, sized ~2.06"×1.34")
            $zip->addFromString('xl/drawings/drawing1.xml',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
                .'<xdr:oneCellAnchor><xdr:from><xdr:col>1</xdr:col><xdr:colOff>258263</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>19594</xdr:rowOff></xdr:from>'
                .'<xdr:ext cx="1981200" cy="1285875"/>'
                .'<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="2" name="logo.png"/><xdr:cNvPicPr preferRelativeResize="0"/></xdr:nvPicPr>'
                .'<xdr:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
                .'<xdr:spPr><a:xfrm><a:off x="788942" y="19594"/><a:ext cx="1981200" cy="1285875"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/></xdr:spPr></xdr:pic>'
                .'<xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>');
            $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image1.png"/>'
                .'</Relationships>');
            $zip->addFromString('xl/media/image1.png', $o['logo']);
        }

        $zip->close();
        $data = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $data;
    }

    private function sheetXml(array $o, int $W, bool $hasLogo): string
    {
        // column widths mirroring the original workbook
        $cols = '<cols>'
            .'<col min="1" max="1" width="7.78" customWidth="1"/>'
            .'<col min="2" max="2" width="10.66" customWidth="1"/>'
            .'<col min="3" max="3" width="26.78" customWidth="1"/>'
            .'<col min="4" max="4" width="14.22" customWidth="1"/>'
            .'<col min="5" max="5" width="9.66" customWidth="1"/>'
            .'<col min="6" max="6" width="9.0" customWidth="1"/>';
        for ($w = 0; $w < $W; $w++) {
            $b = 6 + $w * 10;
            $cols .= '<col min="'.($b + 1).'" max="'.($b + 7).'" width="6.6" customWidth="1"/>';
            $cols .= '<col min="'.($b + 8).'" max="'.($b + 10).'" width="8.55" customWidth="1"/>';
        }
        $T0 = 6 + $W * 10;
        foreach ([[1, 7.66], [2, 8.44], [3, 13.0], [4, 10.44], [5, 13.11], [6, 22.89]] as [$off, $wd]) {
            $cols .= '<col min="'.($T0 + $off).'" max="'.($T0 + $off).'" width="'.$wd.'" customWidth="1"/>';
        }
        $cols .= '</cols>';

        ksort($this->cells);
        $body = '';
        foreach ($this->cells as $r => $row) {
            ksort($row);
            $ht = isset($this->rowHeights[$r]) ? ' ht="'.$this->rowHeights[$r].'" customHeight="1"' : '';
            $body .= '<row r="'.$r.'"'.$ht.'>';
            foreach ($row as $c => $cell) {
                $ref = Xlsx::colLetter($c).$r;
                $s = ' s="'.$cell['s'].'"';
                if ($cell['f'] !== null) {
                    $body .= '<c r="'.$ref.'"'.$s.'><f>'.$this->esc($cell['f']).'</f>'
                        .($cell['v'] !== null ? '<v>'.$this->esc((string) $cell['v']).'</v>' : '').'</c>';
                } elseif ($cell['v'] === null) {
                    $body .= '<c r="'.$ref.'"'.$s.'/>';
                } elseif ($cell['str']) {
                    $body .= '<c r="'.$ref.'"'.$s.' t="inlineStr"><is><t xml:space="preserve">'.$this->esc((string) $cell['v']).'</t></is></c>';
                } else {
                    $body .= '<c r="'.$ref.'"'.$s.'><v>'.$this->esc((string) $cell['v']).'</v></c>';
                }
            }
            $body .= '</row>';
        }

        $mergeXml = '';
        if ($this->merges !== []) {
            $mergeXml = '<mergeCells count="'.count($this->merges).'">';
            foreach ($this->merges as $m) {
                $mergeXml .= '<mergeCell ref="'.$m.'"/>';
            }
            $mergeXml .= '</mergeCells>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<sheetViews><sheetView showGridLines="0" workbookViewId="0"/></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="14.4"/>'
            .$cols
            .'<sheetData>'.$body.'</sheetData>'
            .$mergeXml
            .'<pageMargins left="0.25" right="0.25" top="0.75" bottom="0.75" header="0" footer="0"/>'
            .'<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="1" paperSize="1"/>'
            .($hasLogo ? '<drawing r:id="rId1"/>' : '')
            .'</worksheet>';
    }

    private function stylesXml(): string
    {
        $numFmts = '<numFmts count="2">'
            .'<numFmt numFmtId="164" formatCode="'.self::CURRENCY_FMT.'"/>'
            .'<numFmt numFmtId="165" formatCode="'.self::HOURS_FMT.'"/>'
            .'</numFmts>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$numFmts
            .'<fonts count="'.count($this->fonts).'">'.implode('', $this->fonts).'</fonts>'
            .'<fills count="'.count($this->fills).'">'.implode('', $this->fills).'</fills>'
            .'<borders count="'.count($this->borders).'">'.implode('', $this->borders).'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="'.count($this->xfs).'">'.implode('', $this->xfs).'</cellXfs>'
            .'</styleSheet>';
    }

    // =============== style + cell helpers ===============

    /** Register (or reuse) a cell style; returns the cellXfs index. */
    private function st(
        string $font, float $size, bool $bold, ?string $color = null,
        ?string $fill = null, ?string $border = null, bool $currency = false,
        ?string $hAlign = null, ?string $vAlign = null, bool $wrap = false,
        ?string $borderSides = null, ?string $numFmt = null,
    ): int {
        $key = implode('|', [$font, $size, (int) $bold, $color ?? '', $fill ?? '', $border ?? '', (int) $currency, $hAlign ?? '', $vAlign ?? '', (int) $wrap, $borderSides ?? '', $numFmt ?? '']);
        if (isset($this->xfIndex[$key])) {
            return $this->xfIndex[$key];
        }

        $fx = '<font><sz val="'.$size.'"/>'.($bold ? '<b/>' : '')
            .($color ? '<color rgb="'.$color.'"/>' : '')
            .'<name val="'.$font.'"/></font>';
        $fontId = array_search($fx, $this->fonts, true);
        if ($fontId === false) {
            $fontId = count($this->fonts);
            $this->fonts[] = $fx;
        }

        $fillId = 0;
        if ($fill !== null) {
            $fl = '<fill><patternFill patternType="solid"><fgColor rgb="'.$fill.'"/><bgColor indexed="64"/></patternFill></fill>';
            $fillId = array_search($fl, $this->fills, true);
            if ($fillId === false) {
                $fillId = count($this->fills);
                $this->fills[] = $fl;
            }
        }

        $borderId = 0;
        if ($border !== null || $borderSides !== null) {
            if ($borderSides !== null) {
                $sides = ['left' => '', 'right' => '', 'top' => '', 'bottom' => ''];
                foreach (['l' => 'left', 'r' => 'right', 't' => 'top', 'b' => 'bottom'] as $ch => $side) {
                    if (str_contains($borderSides, $ch)) {
                        $sides[$side] = '<'.$side.' style="thin"><color indexed="64"/></'.$side.'>';
                    } else {
                        $sides[$side] = '<'.$side.'/>';
                    }
                }
                $bd = '<border>'.$sides['left'].$sides['right'].$sides['top'].$sides['bottom'].'<diagonal/></border>';
            } else {
                $bd = '<border>'
                    .'<left style="thin"><color indexed="64"/></left>'
                    .'<right style="thin"><color indexed="64"/></right>'
                    .'<top style="thin"><color indexed="64"/></top>'
                    .'<bottom style="thin"><color indexed="64"/></bottom>'
                    .'<diagonal/></border>';
            }
            $borderId = array_search($bd, $this->borders, true);
            if ($borderId === false) {
                $borderId = count($this->borders);
                $this->borders[] = $bd;
            }
        }

        $numFmtId = $currency ? 164 : ($numFmt !== null ? 165 : 0);
        $align = '';
        if ($hAlign || $vAlign || $wrap) {
            $align = '<alignment'.($hAlign ? ' horizontal="'.$hAlign.'"' : '').($vAlign ? ' vertical="'.$vAlign.'"' : '').($wrap ? ' wrapText="1"' : '').'/>';
        }
        $xf = '<xf numFmtId="'.$numFmtId.'" fontId="'.$fontId.'" fillId="'.$fillId.'" borderId="'.$borderId.'"'
            .' applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"'.($align !== '' ? ' applyAlignment="1">'.$align.'</xf>' : '/>');

        $this->xfs[] = $xf;
        $idx = count($this->xfs) - 1;
        $this->xfIndex[$key] = $idx;

        return $idx;
    }

    private function set(int $row, int $c, mixed $v, int $style): void
    {
        $this->cells[$row][$c] = ['v' => $v, 'f' => null, 's' => $style, 'str' => is_string($v)];
    }

    private function setF(int $row, int $c, ?string $formula, int $style): void
    {
        $this->cells[$row][$c] = ['v' => null, 'f' => $formula, 's' => $style, 'str' => false];
    }

    private function esc(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
