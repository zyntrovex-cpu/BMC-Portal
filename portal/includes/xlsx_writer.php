<?php
/**
 * Minimal XLSX writer — pure PHP, no Composer required.
 * Uses ZipArchive (ext-zip, standard in PHP 8) to assemble
 * the OOXML package.
 *
 * Usage:
 *   $xls = new XlsxWriter('Sheet1');
 *   $xls->setColumnWidths([25, 20, 12, 12, 8, 8, 10]);
 *   $xls->addRow(['Name','Marks','Grade'], 'header');
 *   $xls->addRow(['Ali', 85, 'A'], 'data');
 *   $xls->addRow(['TOTAL', 85, ''], 'footer');
 *   $xls->download('results.xlsx');
 */
class XlsxWriter
{
    private string $sheetName;
    /** @var array<int,array{cells:array,style:string}> */
    private array $rows = [];
    private array $colWidths = [];
    private int $freezeRow = 0;
    private bool $autoFilter = false;
    private int $filterRow  = 1;

    // Built-in style indexes (keep in sync with _stylesXml)
    const S_DEFAULT = 0;
    const S_HEADER  = 1;
    const S_SUBHDR  = 2;
    const S_DATA    = 3;
    const S_ALTROW  = 4;
    const S_FOOTER  = 5;
    const S_GOLD1   = 6;  // 1st place
    const S_GOLD2   = 7;  // 2nd place
    const S_GOLD3   = 8;  // 3rd place
    const S_TITLE   = 9;
    const S_BOLD    = 10;
    const S_PCT     = 11; // data + number format "0.0%"
    const S_NUM     = 12; // data + number format "0.00"
    const S_HEADER2 = 13; // secondary header (darker blue)

    private static array $styleMap = [
        'header'  => self::S_HEADER,
        'header2' => self::S_HEADER2,
        'subhdr'  => self::S_SUBHDR,
        'data'    => self::S_DATA,
        'alt'     => self::S_ALTROW,
        'footer'  => self::S_FOOTER,
        'gold1'   => self::S_GOLD1,
        'gold2'   => self::S_GOLD2,
        'gold3'   => self::S_GOLD3,
        'title'   => self::S_TITLE,
        'bold'    => self::S_BOLD,
        'pct'     => self::S_PCT,
        'num'     => self::S_NUM,
        ''        => self::S_DEFAULT,
    ];

    public function __construct(string $sheetName = 'Sheet1')
    {
        $this->sheetName = $sheetName;
    }

    public function setColumnWidths(array $widths): void
    {
        $this->colWidths = $widths;
    }

    public function freezeRows(int $rows): void
    {
        $this->freezeRow = $rows;
    }

    /** Enable auto-filter on the given 1-based header row */
    public function enableAutoFilter(int $headerRow = 1): void
    {
        $this->autoFilter = true;
        $this->filterRow  = $headerRow;
    }

    /**
     * @param array $cells  Each cell: scalar value, or ['v'=>value,'s'=>styleName]
     * @param string $rowStyle  applied to cells that don't have their own style
     */
    public function addRow(array $cells, string $rowStyle = ''): void
    {
        $this->rows[] = ['cells' => $cells, 'style' => $rowStyle];
    }

    public function addBlankRow(): void
    {
        $this->rows[] = ['cells' => [], 'style' => ''];
    }

    /** Send XLSX to browser as a file download */
    public function download(string $filename): void
    {
        $data = $this->build();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Cache-Control: max-age=0');
        header('Content-Length: ' . strlen($data));
        echo $data;
        exit;
    }

    /** Return raw XLSX binary */
    public function build(): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive();
        $zip->open($tmpFile, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',       $this->_contentTypesXml());
        $zip->addFromString('_rels/.rels',               $this->_relsXml());
        $zip->addFromString('xl/workbook.xml',           $this->_workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels',$this->_workbookRelsXml());
        $zip->addFromString('xl/styles.xml',             $this->_stylesXml());
        $zip->addFromString('xl/sharedStrings.xml',      $this->_sharedStringsXml());
        $zip->addFromString('xl/worksheets/sheet1.xml',  $this->_sheetXml());
        $zip->close();

        $data = file_get_contents($tmpFile);
        unlink($tmpFile);
        return $data;
    }

    // ── XML builders ─────────────────────────────────────────────────────────

    private function _contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"            ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml"   ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml"              ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/xl/sharedStrings.xml"       ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>';
    }

    private function _relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    }

    private function _workbookXml(): string
    {
        $sn = $this->_esc($this->sheetName);
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="' . $sn . '" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';
    }

    private function _workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"        Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"           Target="styles.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings"    Target="sharedStrings.xml"/>
</Relationships>';
    }

    private function _stylesXml(): string
    {
        // numFmtId 164 = "0.0%", 165 = "0.00"
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <numFmts count="2">
    <numFmt numFmtId="164" formatCode="0.0&quot;%&quot;"/>
    <numFmt numFmtId="165" formatCode="0.00"/>
  </numFmts>
  <fonts count="6">
    <font><sz val="10"/><name val="Calibri"/></font>
    <font><b/><sz val="10"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>
    <font><b/><sz val="10"/><name val="Calibri"/><color rgb="FF1F3864"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/><color rgb="FF1F3864"/></font>
    <font><b/><sz val="10"/><name val="Calibri"/><color rgb="FF7B3F00"/></font>
    <font><b/><sz val="10"/><name val="Calibri"/></font>
  </fonts>
  <fills count="10">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1F3864"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF2E5FA3"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFFFFFF"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF0F4FF"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF0F2456"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFFF8DC"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF5F5F5"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left   style="thin"><color rgb="FFD0D8E8"/></left>
      <right  style="thin"><color rgb="FFD0D8E8"/></right>
      <top    style="thin"><color rgb="FFD0D8E8"/></top>
      <bottom style="thin"><color rgb="FFD0D8E8"/></bottom>
    </border>
  </borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="14">
    <!-- 0: default -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"><alignment wrapText="1"/></xf>
    <!-- 1: header (dark navy bg, white bold) -->
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 2: subheader (medium blue) -->
    <xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 3: data (white) -->
    <xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0"><alignment wrapText="1" vertical="center"/></xf>
    <!-- 4: alt row (light blue tint) -->
    <xf numFmtId="0" fontId="0" fillId="5" borderId="1" xfId="0"><alignment wrapText="1" vertical="center"/></xf>
    <!-- 5: footer (navy bg, white bold) -->
    <xf numFmtId="0" fontId="1" fillId="6" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 6: gold1 — 1st place (gold bg, brown bold) -->
    <xf numFmtId="0" fontId="4" fillId="7" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 7: gold2 — 2nd place (grey bg) -->
    <xf numFmtId="0" fontId="2" fillId="8" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 8: gold3 — 3rd place (warm tint bg) -->
    <xf numFmtId="0" fontId="2" fillId="9" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 9: title (large navy bold) -->
    <xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 10: bold -->
    <xf numFmtId="0" fontId="5" fillId="0" borderId="1" xfId="0"><alignment vertical="center"/></xf>
    <!-- 11: pct (data + percent format) -->
    <xf numFmtId="164" fontId="0" fillId="4" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 12: num (data + decimal format) -->
    <xf numFmtId="165" fontId="0" fillId="4" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 13: header2 (darker navy) -->
    <xf numFmtId="0" fontId="1" fillId="6" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
  </cellXfs>
</styleSheet>';
    }

    // Collect all string values for the shared-strings table
    private array $_strings    = [];
    private array $_strIndex   = [];

    private function _strIdx(string $s): int
    {
        if (!isset($this->_strIndex[$s])) {
            $this->_strIndex[$s] = count($this->_strings);
            $this->_strings[] = $s;
        }
        return $this->_strIndex[$s];
    }

    private function _sharedStringsXml(): string
    {
        // Must be called AFTER _sheetXml() populates _strings
        $count = count($this->_strings);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
             . ' count="' . $count . '" uniqueCount="' . $count . '">';
        foreach ($this->_strings as $s) {
            $xml .= '<si><t xml:space="preserve">' . $this->_esc($s) . '</t></si>';
        }
        $xml .= '</sst>';
        return $xml;
    }

    private function _sheetXml(): string
    {
        $cols  = '';
        foreach ($this->colWidths as $i => $w) {
            $n = $i + 1;
            $cols .= '<col min="' . $n . '" max="' . $n . '" width="' . $w . '" customWidth="1"/>';
        }

        $sheetData = '';
        $maxCols   = 1;
        foreach ($this->rows as $rIdx => $row) {
            $rNum = $rIdx + 1;
            $cells = $row['cells'];
            $rowStyle = $row['style'];
            if (empty($cells)) { $sheetData .= '<row r="' . $rNum . '"/>'; continue; }
            $maxCols = max($maxCols, count($cells));
            $sheetData .= '<row r="' . $rNum . '">';
            foreach ($cells as $cIdx => $cell) {
                $colLetter = $this->_col($cIdx);
                $ref       = $colLetter . $rNum;

                // Cell-level style overrides row style
                $cellStyle  = $rowStyle;
                $cellValue  = $cell;
                if (is_array($cell)) {
                    $cellStyle = $cell['s'] ?? $rowStyle;
                    $cellValue = $cell['v'] ?? '';
                }
                $sIdx = self::$styleMap[$cellStyle] ?? self::S_DEFAULT;

                if ($cellValue === null || $cellValue === '') {
                    $sheetData .= '<c r="' . $ref . '" s="' . $sIdx . '"/>';
                } elseif (is_numeric($cellValue) && !is_string($cellValue)) {
                    $sheetData .= '<c r="' . $ref . '" t="n" s="' . $sIdx . '"><v>' . $cellValue . '</v></c>';
                } else {
                    $si = $this->_strIdx((string)$cellValue);
                    $sheetData .= '<c r="' . $ref . '" t="s" s="' . $sIdx . '"><v>' . $si . '</v></c>';
                }
            }
            $sheetData .= '</row>';
        }

        $lastCol  = $this->_col($maxCols - 1);
        $lastRow  = count($this->rows);
        $dim      = 'A1:' . $lastCol . $lastRow;

        $freeze = '';
        if ($this->freezeRow > 0) {
            $topLeft = 'A' . ($this->freezeRow + 1);
            $freeze  = '<pane ySplit="' . $this->freezeRow . '" topLeftCell="' . $topLeft . '" activePane="bottomLeft" state="frozen"/>';
        }

        $af = '';
        if ($this->autoFilter) {
            $af = '<autoFilter ref="A' . $this->filterRow . ':' . $lastCol . $this->filterRow . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <dimension ref="' . $dim . '"/>
  <sheetViews><sheetView workbookViewId="0">' . $freeze . '</sheetView></sheetViews>
  <sheetFormatPr defaultRowHeight="15"/>
  ' . ($cols ? '<cols>' . $cols . '</cols>' : '') . '
  <sheetData>' . $sheetData . '</sheetData>
  ' . $af . '
</worksheet>';
    }

    private function _col(int $idx): string
    {
        $col = '';
        $idx++;
        while ($idx > 0) {
            $idx--;
            $col = chr(65 + ($idx % 26)) . $col;
            $idx = intdiv($idx, 26);
        }
        return $col;
    }

    private function _esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
