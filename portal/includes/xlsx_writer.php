<?php
/**
 * Lightweight multi-sheet XLSX writer — pure PHP, no Composer.
 * Uses ZipArchive (ext-zip, standard PHP 8) to assemble OOXML.
 *
 * CRITICAL FIX (v2): _sheetXml() must be called BEFORE _sharedStringsXml()
 * because string cells are indexed lazily while sheet XML is generated.
 * Prior version called them in the wrong order → empty shared-strings table
 * → all string cells blank in Excel (only numeric columns visible).
 *
 * Usage:
 *   $xls = new XlsxWriter('Results');
 *   $xls->setColumnWidths([25, 14, 10]);
 *   $xls->freezeRows(2);
 *   $xls->addRow(['Name', 'Marks'], 'header');
 *   $xls->addRow(['Ali', 85], 'data');
 *
 *   // Add a second sheet
 *   $xls->nextSheet('Summary');
 *   $xls->setColumnWidths([30, 15]);
 *   $xls->addRow(['Metric', 'Value'], 'header');
 *   $xls->addRow(['Total Students', 50], 'data');
 *
 *   $xls->download('results.xlsx');
 */
class XlsxWriter
{
    // ── Named style constants ──────────────────────────────────────────────────
    // These map to cellXfs index numbers in _stylesXml()
    const S_DEFAULT = 0;
    const S_HEADER  = 1;   // dark navy, white bold, centered
    const S_SUBHDR  = 2;   // medium blue, white bold, centered
    const S_DATA    = 3;   // white bg
    const S_ALTROW  = 4;   // light-blue tint alt row
    const S_FOOTER  = 5;   // dark navy, white bold
    const S_GOLD1   = 6;   // 1st place — gold bg, dark text
    const S_GOLD2   = 7;   // 2nd place — silver-grey bg
    const S_GOLD3   = 8;   // 3rd place — warm tint
    const S_TITLE   = 9;   // large bold navy (title row)
    const S_BOLD    = 10;  // bold black
    const S_PCT     = 11;  // data + "0.0%" number format
    const S_NUM     = 12;  // data + "0.00" number format
    const S_HEADER2 = 13;  // secondary header (very dark navy)
    const S_CENTER  = 14;  // data, centered
    const S_WARN    = 15;  // amber/orange tint (missing marks)

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
        'center'  => self::S_CENTER,
        'warn'    => self::S_WARN,
        ''        => self::S_DEFAULT,
    ];

    // ── Per-sheet state ────────────────────────────────────────────────────────
    /** @var array<int,array{name:string,rows:array,colWidths:array,freezeRow:int,autoFilter:bool,filterRow:int}> */
    private array $sheets        = [];
    private int   $currentIdx    = 0;
    // Per-sheet shared-string tables (built during _sheetXml generation)
    private array $_strings      = [];
    private array $_strIndex     = [];

    public function __construct(string $firstSheetName = 'Sheet1')
    {
        $this->_newSheet($firstSheetName);
    }

    /** Create and switch to a new sheet. Returns $this for chaining. */
    public function nextSheet(string $name): static
    {
        $this->_newSheet($name);
        return $this;
    }

    private function _newSheet(string $name): void
    {
        $this->sheets[] = [
            'name'       => $name,
            'rows'       => [],
            'colWidths'  => [],
            'freezeRow'  => 0,
            'autoFilter' => false,
            'filterRow'  => 1,
        ];
        $this->currentIdx = count($this->sheets) - 1;
    }

    // ── Sheet configuration (applies to current sheet) ─────────────────────────

    public function setColumnWidths(array $widths): void
    {
        $this->sheets[$this->currentIdx]['colWidths'] = $widths;
    }

    public function freezeRows(int $rows): void
    {
        $this->sheets[$this->currentIdx]['freezeRow'] = $rows;
    }

    public function enableAutoFilter(int $headerRow = 1): void
    {
        $this->sheets[$this->currentIdx]['autoFilter']  = true;
        $this->sheets[$this->currentIdx]['filterRow']   = $headerRow;
    }

    // ── Row building (applies to current sheet) ────────────────────────────────

    /**
     * @param array  $cells      Each entry: scalar, or ['v'=>value,'s'=>styleName]
     * @param string $rowStyle   Default style for cells that don't override
     */
    public function addRow(array $cells, string $rowStyle = ''): void
    {
        $this->sheets[$this->currentIdx]['rows'][] = [
            'cells' => $cells,
            'style' => $rowStyle,
        ];
    }

    public function addBlankRow(): void
    {
        $this->sheets[$this->currentIdx]['rows'][] = ['cells' => [], 'style' => ''];
    }

    // ── Output ─────────────────────────────────────────────────────────────────

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

    public function build(): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip     = new ZipArchive();
        $zip->open($tmpFile, ZipArchive::OVERWRITE);

        $numSheets = count($this->sheets);

        // ── Generate sheet XMLs FIRST so _strings is populated ──────────────
        $sheetXmls = [];
        foreach ($this->sheets as $idx => $sheet) {
            $sheetXmls[$idx] = $this->_sheetXml($sheet);
        }
        // NOW serialize shared strings (strings table is fully built)
        $zip->addFromString('xl/sharedStrings.xml', $this->_sharedStringsXml());

        // ── Fixed files ───────────────────────────────────────────────────────
        $zip->addFromString('[Content_Types].xml',        $this->_contentTypesXml($numSheets));
        $zip->addFromString('_rels/.rels',                $this->_relsXml());
        $zip->addFromString('xl/workbook.xml',            $this->_workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->_workbookRelsXml($numSheets));
        $zip->addFromString('xl/styles.xml',              $this->_stylesXml());

        // ── Sheet files ───────────────────────────────────────────────────────
        foreach ($sheetXmls as $idx => $xml) {
            $zip->addFromString('xl/worksheets/sheet' . ($idx + 1) . '.xml', $xml);
        }

        $zip->close();
        $data = file_get_contents($tmpFile);
        unlink($tmpFile);
        return $data;
    }

    // ── XML builders ──────────────────────────────────────────────────────────

    private function _contentTypesXml(int $numSheets): string
    {
        $overrides = '';
        for ($i = 1; $i <= $numSheets; $i++) {
            $overrides .= '  <Override PartName="/xl/worksheets/sheet' . $i . '.xml"'
                . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' . "\n";
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"          ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
' . $overrides . '  <Override PartName="/xl/styles.xml"          ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/xl/sharedStrings.xml"     ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
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
        $sheets = '';
        foreach ($this->sheets as $i => $sh) {
            $n   = $i + 1;
            $rid = 'rId' . $n;
            $sheets .= '<sheet name="' . $this->_esc($sh['name']) . '" sheetId="' . $n . '" r:id="' . $rid . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>' . $sheets . '</sheets>
</workbook>';
    }

    private function _workbookRelsXml(int $numSheets): string
    {
        $rels = '';
        for ($i = 1; $i <= $numSheets; $i++) {
            $rels .= '  <Relationship Id="rId' . $i . '"'
                . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
                . ' Target="worksheets/sheet' . $i . '.xml"/>' . "\n";
        }
        $stylesId  = $numSheets + 1;
        $stringsId = $numSheets + 2;
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
' . $rels
. '  <Relationship Id="rId' . $stylesId  . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"        Target="styles.xml"/>' . "\n"
. '  <Relationship Id="rId' . $stringsId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>' . "\n"
. '</Relationships>';
    }

    private function _stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <numFmts count="2">
    <numFmt numFmtId="164" formatCode="0.0&quot;%&quot;"/>
    <numFmt numFmtId="165" formatCode="0.00"/>
  </numFmts>
  <fonts count="7">
    <font><sz val="10"/><name val="Calibri"/></font>                                       <!-- 0 default -->
    <font><b/><sz val="10"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>            <!-- 1 white bold -->
    <font><b/><sz val="10"/><name val="Calibri"/><color rgb="FF1F3864"/></font>            <!-- 2 navy bold -->
    <font><b/><sz val="12"/><name val="Calibri"/><color rgb="FF1F3864"/></font>            <!-- 3 large navy bold (title) -->
    <font><b/><sz val="10"/><name val="Calibri"/><color rgb="FF7B3F00"/></font>            <!-- 4 brown bold (gold1) -->
    <font><b/><sz val="10"/><name val="Calibri"/></font>                                   <!-- 5 plain bold -->
    <font><sz val="10"/><name val="Calibri"/><color rgb="FF92400E"/></font>                <!-- 6 amber (warn) -->
  </fonts>
  <fills count="13">
    <fill><patternFill patternType="none"/></fill>                <!-- 0 none -->
    <fill><patternFill patternType="gray125"/></fill>             <!-- 1 gray125 (required) -->
    <fill><patternFill patternType="solid"><fgColor rgb="FF1F3864"/></patternFill></fill>  <!-- 2 dark navy -->
    <fill><patternFill patternType="solid"><fgColor rgb="FF2E5FA3"/></patternFill></fill>  <!-- 3 medium blue -->
    <fill><patternFill patternType="solid"><fgColor rgb="FFFFFFFF"/></patternFill></fill>  <!-- 4 white -->
    <fill><patternFill patternType="solid"><fgColor rgb="FFF0F4FF"/></patternFill></fill>  <!-- 5 light blue tint -->
    <fill><patternFill patternType="solid"><fgColor rgb="FF0F2456"/></patternFill></fill>  <!-- 6 very dark navy (footer) -->
    <fill><patternFill patternType="solid"><fgColor rgb="FFFFF3CD"/></patternFill></fill>  <!-- 7 gold1 bg -->
    <fill><patternFill patternType="solid"><fgColor rgb="FFF5F5F5"/></patternFill></fill>  <!-- 8 silver grey (gold2) -->
    <fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/></patternFill></fill>  <!-- 9 warm tint (gold3) -->
    <fill><patternFill patternType="solid"><fgColor rgb="FFE8F0FE"/></patternFill></fill>  <!-- 10 very light blue (center) -->
    <fill><patternFill patternType="solid"><fgColor rgb="FFFFF8DC"/></patternFill></fill>  <!-- 11 cornsilk (warn) -->
    <fill><patternFill patternType="solid"><fgColor rgb="FF344B8A"/></patternFill></fill>  <!-- 12 header2 bg -->
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>   <!-- 0 no border -->
    <border>
      <left   style="thin"><color rgb="FFB8C4D9"/></left>
      <right  style="thin"><color rgb="FFB8C4D9"/></right>
      <top    style="thin"><color rgb="FFB8C4D9"/></top>
      <bottom style="thin"><color rgb="FFB8C4D9"/></bottom>
    </border>                                                     <!-- 1 thin grey -->
  </borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="16">
    <!-- 0  default -->
    <xf numFmtId="0"   fontId="0" fillId="0"  borderId="1" xfId="0"><alignment wrapText="1" vertical="center"/></xf>
    <!-- 1  header: dark navy bg, white bold, centered -->
    <xf numFmtId="0"   fontId="1" fillId="2"  borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 2  subheader: medium blue, white bold -->
    <xf numFmtId="0"   fontId="1" fillId="3"  borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 3  data: white bg -->
    <xf numFmtId="0"   fontId="0" fillId="4"  borderId="1" xfId="0"><alignment wrapText="1" vertical="center"/></xf>
    <!-- 4  alt: light-blue tint -->
    <xf numFmtId="0"   fontId="0" fillId="5"  borderId="1" xfId="0"><alignment wrapText="1" vertical="center"/></xf>
    <!-- 5  footer: very dark navy, white bold -->
    <xf numFmtId="0"   fontId="1" fillId="6"  borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 6  gold1: gold bg, brown bold, centered -->
    <xf numFmtId="0"   fontId="4" fillId="7"  borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 7  gold2: grey bg, navy bold, centered -->
    <xf numFmtId="0"   fontId="2" fillId="8"  borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 8  gold3: warm tint, navy bold -->
    <xf numFmtId="0"   fontId="2" fillId="9"  borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 9  title: large navy bold, no border -->
    <xf numFmtId="0"   fontId="3" fillId="0"  borderId="0" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 10 bold black -->
    <xf numFmtId="0"   fontId="5" fillId="0"  borderId="1" xfId="0"><alignment vertical="center"/></xf>
    <!-- 11 pct: data bg + custom % format -->
    <xf numFmtId="164" fontId="0" fillId="4"  borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 12 num: data bg + 2dp format -->
    <xf numFmtId="165" fontId="0" fillId="4"  borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 13 header2: very dark navy -->
    <xf numFmtId="0"   fontId="1" fillId="12" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 14 center: light-blue bg, centered -->
    <xf numFmtId="0"   fontId="0" fillId="10" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 15 warn: cornsilk bg, amber text -->
    <xf numFmtId="0"   fontId="6" fillId="11" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
  </cellXfs>
</styleSheet>';
    }

    private function _sharedStringsXml(): string
    {
        $count = count($this->_strings);
        $xml   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
               . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
               . ' count="' . $count . '" uniqueCount="' . $count . '">';
        foreach ($this->_strings as $s) {
            $xml .= '<si><t xml:space="preserve">' . $this->_esc($s) . '</t></si>';
        }
        $xml .= '</sst>';
        return $xml;
    }

    private function _strIdx(string $s): int
    {
        if (!isset($this->_strIndex[$s])) {
            $this->_strIndex[$s] = count($this->_strings);
            $this->_strings[]    = $s;
        }
        return $this->_strIndex[$s];
    }

    private function _sheetXml(array $sheet): string
    {
        $colWidths   = $sheet['colWidths'];
        $rows        = $sheet['rows'];
        $freezeRow   = $sheet['freezeRow'];
        $autoFilter  = $sheet['autoFilter'];
        $filterRow   = $sheet['filterRow'];

        // Column widths
        $cols = '';
        foreach ($colWidths as $i => $w) {
            $n    = $i + 1;
            $cols .= '<col min="' . $n . '" max="' . $n . '" width="' . $w . '" customWidth="1"/>';
        }

        // Rows
        $sheetData = '';
        $maxCols   = 1;
        foreach ($rows as $rIdx => $row) {
            $rNum  = $rIdx + 1;
            $cells = $row['cells'];
            $rSty  = $row['style'];
            if (empty($cells)) {
                $sheetData .= '<row r="' . $rNum . '"/>';
                continue;
            }
            $maxCols   = max($maxCols, count($cells));
            $sheetData .= '<row r="' . $rNum . '">';
            foreach ($cells as $cIdx => $cell) {
                $colLetter = $this->_col($cIdx);
                $ref       = $colLetter . $rNum;

                $cellStyle = $rSty;
                $cellValue = $cell;
                if (is_array($cell)) {
                    $cellStyle = $cell['s'] ?? $rSty;
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

        $lastCol = $this->_col($maxCols - 1);
        $lastRow = count($rows);
        $dim     = 'A1:' . $lastCol . $lastRow;

        $freeze = '';
        if ($freezeRow > 0) {
            $topLeft = 'A' . ($freezeRow + 1);
            $freeze  = '<pane ySplit="' . $freezeRow . '" topLeftCell="' . $topLeft . '" activePane="bottomLeft" state="frozen"/>';
        }

        $af = '';
        if ($autoFilter && $lastRow >= $filterRow) {
            $af = '<autoFilter ref="A' . $filterRow . ':' . $lastCol . $filterRow . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <dimension ref="' . $dim . '"/>
  <sheetViews><sheetView workbookViewId="0">' . $freeze . '</sheetView></sheetViews>
  <sheetFormatPr defaultRowHeight="16" customHeight="1"/>
  ' . ($cols ? '<cols>' . $cols . '</cols>' : '') . '
  <sheetData>' . $sheetData . '</sheetData>
  ' . $af . '
</worksheet>';
    }

    /** Convert 0-based column index to Excel letter(s): 0→A, 25→Z, 26→AA */
    private function _col(int $idx): string
    {
        $col  = '';
        $n    = $idx + 1;
        while ($n > 0) {
            $n--;
            $col = chr(65 + ($n % 26)) . $col;
            $n   = intdiv($n, 26);
        }
        return $col;
    }

    private function _esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
