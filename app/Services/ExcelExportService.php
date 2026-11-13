<?php

namespace App\Services;

use App\Models\StockOpname;
use Carbon\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelExportService
{
    /**
     * Primary Corporate Color Palette (PT Indo Lampung Perkasa / Koperasi).
     */
    const COLOR_HEADER_BG = '0052CC';       // Deep Royal Blue
    const COLOR_HEADER_TEXT = 'FFFFFF';     // White
    const COLOR_ZEBRA_ROW = 'F8FAFC';       // Very Light Slate
    const COLOR_BORDER = 'CBD5E1';          // Muted Border Gray
    const COLOR_TOTAL_BG = 'E2E8F0';        // Soft Gray for Summary
    const COLOR_META_BG = 'F1F5F9';         // Light Slate for Metadata
    const COLOR_RED_TEXT = 'DC2626';        // Highlight for Negative Variance

    /**
     * Generate Stock Opname Variance Report Spreadsheet (.xlsx).
     */
    public function createStockOpnameSpreadsheet(StockOpname $opname): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('POS Koperasi PT Indo Lampung Perkasa')
            ->setLastModifiedBy('Auditor / Manager Toko')
            ->setTitle('Laporan Variance Stock Opname - ' . $opname->opname_code)
            ->setSubject('Stock Opname Variance Report')
            ->setDescription('Laporan resmi audit fisik persediaan dan selisih stok minimarket koperasi PT Indo Lampung Perkasa.');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr('Variance_' . $opname->opname_code, 0, 31));
        $sheet->setShowGridLines(true);

        // ----------------------------------------------------
        // 1. COMPANY HEADER & REPORT TITLE
        // ----------------------------------------------------
        $sheet->setCellValue('A1', 'PT INDO LAMPUNG PERKASA - MINIMARKET KOPERASI');
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('003399');
        $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->setCellValue('A2', 'LAPORAN VARIANCE STOCK OPNAME & AUDIT FISIK');
        $sheet->mergeCells('A2:J2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('334155');
        $sheet->getStyle('A2')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(2)->setRowHeight(20);

        // ----------------------------------------------------
        // 2. METADATA SECTION (Rows 4 - 7)
        // ----------------------------------------------------
        $metaDataLeft = [
            ['Kode Opname', $opname->opname_code],
            ['Judul Audit', $opname->title],
            ['Auditor', $opname->conducted_by],
            ['Status Audit', $opname->status],
        ];

        $completedStr = $opname->completed_at ? $opname->completed_at->format('d/m/Y H:i') . ' WIB' : 'Sedang Berlangsung';
        $totalItems = count($opname->items);
        $totalVarianceUnits = $opname->items->sum('variance');

        $metaDataRight = [
            ['Tanggal Selesai', $completedStr],
            ['Total Item Diaudit', $totalItems . ' SKU Produk'],
            ['Total Selisih Fisik', $totalVarianceUnits . ' Unit'],
            ['Nilai Selisih Netto', 'Rp ' . number_format($opname->total_variance_value, 2, ',', '.')],
        ];

        $rowIdx = 4;
        foreach ($metaDataLeft as $idx => $m) {
            $sheet->setCellValue("A{$rowIdx}", $m[0] . ' :');
            $sheet->setCellValue("B{$rowIdx}", $m[1]);
            $sheet->mergeCells("B{$rowIdx}:C{$rowIdx}");

            $rRight = $metaDataRight[$idx];
            $sheet->setCellValue("E{$rowIdx}", $rRight[0] . ' :');
            $sheet->setCellValue("F{$rowIdx}", $rRight[1]);
            $sheet->mergeCells("F{$rowIdx}:G{$rowIdx}");

            // Styles
            $sheet->getStyle("A{$rowIdx}")->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('475569');
            $sheet->getStyle("B{$rowIdx}")->getFont()->setBold($idx === 0 || $idx === 3)->setSize(10);
            $sheet->getStyle("E{$rowIdx}")->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('475569');
            $sheet->getStyle("F{$rowIdx}")->getFont()->setBold($idx === 3)->setSize(10);

            $sheet->getRowDimension($rowIdx)->setRowHeight(18);
            $rowIdx++;
        }

        // Apply soft box styling to metadata
        $metaRange = 'A4:G7';
        $sheet->getStyle($metaRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_META_BG);
        $sheet->getStyle($metaRange)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::COLOR_BORDER);

        // ----------------------------------------------------
        // 3. TABLE HEADERS (Row 9)
        // ----------------------------------------------------
        $headers = [
            'A' => 'No',
            'B' => 'SKU Produk',
            'C' => 'Nama Produk',
            'D' => 'Stok Sistem',
            'E' => 'Stok Fisik',
            'F' => 'Selisih (Unit)',
            'G' => 'Harga Pokok (Rp)',
            'H' => 'Nominal Selisih (Rp)',
            'I' => 'Status Selisih',
            'J' => 'Catatan Auditor',
        ];

        $headerRow = 9;
        $sheet->getRowDimension($headerRow)->setRowHeight(28);

        foreach ($headers as $col => $label) {
            $sheet->setCellValue("{$col}{$headerRow}", $label);
        }

        $headerRange = "A{$headerRow}:J{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => self::COLOR_HEADER_TEXT],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::COLOR_HEADER_BG],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '1E40AF'],
                ],
            ],
        ]);

        // Specific alignments for headers
        $sheet->getStyle("C{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("D{$headerRow}:H{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("J{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // ----------------------------------------------------
        // 4. DATA ROWS (Row 10+)
        // ----------------------------------------------------
        $currRow = 10;
        $no = 1;

        foreach ($opname->items as $item) {
            $sheet->getRowDimension($currRow)->setRowHeight(21);

            $sheet->setCellValue("A{$currRow}", $no++);
            // Force SKU as string format so leading zeros / hyphens never convert to numbers
            $sheet->setCellValueExplicit("B{$currRow}", $item->product_sku, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("C{$currRow}", $item->product_name);
            $sheet->setCellValue("D{$currRow}", (int)$item->system_stock);
            $sheet->setCellValue("E{$currRow}", (int)$item->physical_stock);
            $sheet->setCellValue("F{$currRow}", (int)$item->variance);
            $sheet->setCellValue("G{$currRow}", (float)$item->unit_cost);
            $sheet->setCellValue("H{$currRow}", (float)$item->variance_value);
            $sheet->setCellValue("I{$currRow}", $item->variance_reason ?: 'Sesuai');
            $sheet->setCellValue("J{$currRow}", $item->notes ?: '-');

            // Alignments
            $sheet->getStyle("A{$currRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$currRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$currRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("D{$currRow}:H{$currRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("I{$currRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("J{$currRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            // Vertical center all
            $sheet->getStyle("A{$currRow}:J{$currRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

            // Number Formats
            $sheet->getStyle("D{$currRow}:E{$currRow}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("F{$currRow}")->getNumberFormat()->setFormatCode('+#,##0;-#,##0;0');
            $sheet->getStyle("G{$currRow}:H{$currRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0.00;("Rp "#,##0.00);"-"');

            // Soft Red text if variance < 0
            if ($item->variance < 0) {
                $sheet->getStyle("F{$currRow}")->getFont()->getColor()->setRGB(self::COLOR_RED_TEXT);
                $sheet->getStyle("F{$currRow}")->getFont()->setBold(true);
                $sheet->getStyle("H{$currRow}")->getFont()->getColor()->setRGB(self::COLOR_RED_TEXT);
                $sheet->getStyle("H{$currRow}")->getFont()->setBold(true);
                $sheet->getStyle("I{$currRow}")->getFont()->getColor()->setRGB(self::COLOR_RED_TEXT);
            } elseif ($item->variance > 0) {
                $sheet->getStyle("F{$currRow}")->getFont()->getColor()->setRGB('16A34A');
                $sheet->getStyle("F{$currRow}")->getFont()->setBold(true);
                $sheet->getStyle("H{$currRow}")->getFont()->getColor()->setRGB('16A34A');
                $sheet->getStyle("H{$currRow}")->getFont()->setBold(true);
                $sheet->getStyle("I{$currRow}")->getFont()->getColor()->setRGB('16A34A');
            }

            // Zebra row background
            if ($no % 2 === 1) {
                $sheet->getStyle("A{$currRow}:J{$currRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_ZEBRA_ROW);
            }

            // Cell borders
            $sheet->getStyle("A{$currRow}:J{$currRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::COLOR_BORDER);

            $currRow++;
        }

        // ----------------------------------------------------
        // 5. SUMMARY / TOTAL ROW
        // ----------------------------------------------------
        $totalRow = $currRow;
        $sheet->getRowDimension($totalRow)->setRowHeight(25);

        $sheet->setCellValue("A{$totalRow}", 'TOTAL NILAI SELISIH (RP)');
        $sheet->mergeCells("A{$totalRow}:G{$totalRow}");

        // Formula for total variance value
        if ($currRow > 10) {
            $sheet->setCellValue("H{$totalRow}", "=SUM(H10:H" . ($totalRow - 1) . ")");
        } else {
            $sheet->setCellValue("H{$totalRow}", (float)$opname->total_variance_value);
        }
        $sheet->setCellValue("I{$totalRow}", $opname->status === 'COMPLETED' ? 'AUDIT CLOSED' : 'PROSES');
        $sheet->setCellValue("J{$totalRow}", '');

        // Summary Styles
        $sheet->getStyle("A{$totalRow}:J{$totalRow}")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::COLOR_TOTAL_BG],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'top' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '64748B'],
                ],
                'bottom' => [
                    'borderStyle' => Border::BORDER_DOUBLE,
                    'color' => ['rgb' => '0F172A'],
                ],
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => self::COLOR_BORDER],
                ],
            ],
        ]);

        $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("H{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("H{$totalRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0.00;("Rp "#,##0.00);"-"');
        $sheet->getStyle("I{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ----------------------------------------------------
        // 6. AUTO-FIT COLUMN WIDTHS WITH PADDING
        // ----------------------------------------------------
        $colWidths = [
            'A' => 6,   // No
            'B' => 18,  // SKU
            'C' => 38,  // Nama Produk
            'D' => 14,  // Stok Sistem
            'E' => 14,  // Stok Fisik
            'F' => 15,  // Selisih
            'G' => 18,  // Harga Pokok
            'H' => 20,  // Nominal Selisih
            'I' => 20,  // Status
            'J' => 28,  // Catatan
        ];

        foreach ($colWidths as $col => $minWidth) {
            $sheet->getColumnDimension($col)->setWidth($minWidth);
        }

        // Freeze pane so headers stay fixed during scroll
        $sheet->freezePane('A10');

        return $spreadsheet;
    }

    /**
     * Generate Rekapitulasi Piutang Karyawan Spreadsheet (.xlsx).
     */
    public function createMemberDebtSpreadsheet($query, string $month, Carbon $startDate, ?string $branch, ?string $department): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('POS Koperasi PT Indo Lampung Perkasa')
            ->setLastModifiedBy('Finance & HRD Koperasi')
            ->setTitle('Rekapitulasi Piutang Kasbon Karyawan - ' . $month)
            ->setSubject('Laporan Pemotongan Gaji Karyawan')
            ->setDescription('Laporan resmi pemotongan gaji (payroll debt deduction) karyawan PT Indo Lampung Perkasa.');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr('Kasbon_' . $month, 0, 31));
        $sheet->setShowGridLines(true);

        // ----------------------------------------------------
        // 1. COMPANY HEADER & REPORT TITLE
        // ----------------------------------------------------
        $sheet->setCellValue('A1', 'PT INDO LAMPUNG PERKASA');
        $sheet->mergeCells('A1:L1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('003399');
        $sheet->getRowDimension(1)->setRowHeight(22);

        $sheet->setCellValue('A2', 'KOPERASI KARYAWAN INDO LAMPUNG PERKASA (KOPKAR ILP)');
        $sheet->mergeCells('A2:L2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('1E293B');
        $sheet->getRowDimension(2)->setRowHeight(18);

        $sheet->setCellValue('A3', 'LAPORAN REKAPITULASI PIUTANG KASBON BULANAN UNTUK PEMOTONGAN GAJI (PAYROLL DEBT DEDUCTION)');
        $sheet->mergeCells('A3:L3');
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('475569');
        $sheet->getRowDimension(3)->setRowHeight(18);

        // ----------------------------------------------------
        // 2. METADATA SECTION (Rows 5 - 8)
        // ----------------------------------------------------
        $meta = [
            ['Periode Pemotongan Gaji', $startDate->translatedFormat('F Y') . ' (' . $month . ')'],
            ['Filter Cabang Penugasan', $branch && $branch !== 'all' ? $branch : 'Semua Cabang (Pusat & Kebun)'],
            ['Filter Departemen', $department && $department !== 'all' ? $department : 'Semua Departemen'],
            ['Tanggal Cetak Dokumen', Carbon::now()->format('d/m/Y H:i:s') . ' WIB'],
        ];

        $rIdx = 5;
        foreach ($meta as $m) {
            $sheet->setCellValue("A{$rIdx}", $m[0] . ' :');
            $sheet->setCellValue("C{$rIdx}", $m[1]);
            $sheet->mergeCells("C{$rIdx}:F{$rIdx}");

            $sheet->getStyle("A{$rIdx}")->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('475569');
            $sheet->getStyle("C{$rIdx}")->getFont()->setSize(10);
            $sheet->getRowDimension($rIdx)->setRowHeight(18);
            $rIdx++;
        }

        $sheet->getStyle('A5:F8')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_META_BG);
        $sheet->getStyle('A5:F8')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::COLOR_BORDER);

        // ----------------------------------------------------
        // 3. TABLE HEADERS (Row 10)
        // ----------------------------------------------------
        $headers = [
            'A' => 'No',
            'B' => 'Kode Anggota',
            'C' => 'NIK Karyawan',
            'D' => 'Nama Lengkap Karyawan',
            'E' => 'Departemen / Divisi',
            'F' => 'Cabang Penugasan',
            'G' => 'Plafon Kredit (Rp)',
            'H' => 'Total Kasbon Berjalan (Rp)',
            'I' => 'Sisa Plafon (Rp)',
            'J' => 'Utilisasi (%)',
            'K' => 'Status Anggota',
            'L' => 'Rekomendasi HRD / Payroll',
        ];

        $headerRow = 10;
        $sheet->getRowDimension($headerRow)->setRowHeight(28);

        foreach ($headers as $col => $title) {
            $sheet->setCellValue("{$col}{$headerRow}", $title);
        }

        $headerRange = "A{$headerRow}:L{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => self::COLOR_HEADER_TEXT],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::COLOR_HEADER_BG],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '1E40AF'],
                ],
            ],
        ]);

        $sheet->getStyle("D{$headerRow}:F{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("G{$headerRow}:I{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("L{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // ----------------------------------------------------
        // 4. DATA ROWS
        // ----------------------------------------------------
        $currRow = 11;
        $no = 1;

        $members = $query->orderBy('current_debt', 'desc')->orderBy('name', 'asc')->get();

        foreach ($members as $m) {
            $remaining = max(0, $m->credit_limit - $m->current_debt);
            $utilization = $m->credit_limit > 0 ? ($m->current_debt / $m->credit_limit) : 0;

            $rekomendasi = 'Normal';
            if ($m->status !== 'AKTIF') {
                $rekomendasi = 'Akun Dibekukan - Konfirmasi HRD';
            } elseif ($m->current_debt >= $m->credit_limit) {
                $rekomendasi = 'Plafon 100% Penuh - Potong Gaji Penuh';
            } elseif ($utilization >= 0.75) {
                $rekomendasi = 'Perhatian: Utilisasi di atas 75%';
            }

            $sheet->getRowDimension($currRow)->setRowHeight(21);

            $sheet->setCellValue("A{$currRow}", $no++);
            $sheet->setCellValueExplicit("B{$currRow}", $m->member_code, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            // NIK as explicit string so it never converts to scientific notation
            $sheet->setCellValueExplicit("C{$currRow}", (string)$m->nik, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("D{$currRow}", $m->name);
            $sheet->setCellValue("E{$currRow}", $m->department);
            $sheet->setCellValue("F{$currRow}", $m->branch ?: 'Cabang 1 - Mill / Pabrik');
            $sheet->setCellValue("G{$currRow}", (float)$m->credit_limit);
            $sheet->setCellValue("H{$currRow}", (float)$m->current_debt);
            $sheet->setCellValue("I{$currRow}", (float)$remaining);
            $sheet->setCellValue("J{$currRow}", $utilization);
            $sheet->setCellValue("K{$currRow}", $m->status);
            $sheet->setCellValue("L{$currRow}", $rekomendasi);

            // Alignments
            $sheet->getStyle("A{$currRow}:C{$currRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$currRow}:F{$currRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("G{$currRow}:I{$currRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("J{$currRow}:K{$currRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("L{$currRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("A{$currRow}:L{$currRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

            // Formats
            $sheet->getStyle("G{$currRow}:I{$currRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0.00;("Rp "#,##0.00);"-"');
            $sheet->getStyle("J{$currRow}")->getNumberFormat()->setFormatCode('0.0%');

            if ($utilization >= 0.75) {
                $sheet->getStyle("H{$currRow}")->getFont()->getColor()->setRGB(self::COLOR_RED_TEXT);
                $sheet->getStyle("H{$currRow}")->getFont()->setBold(true);
                $sheet->getStyle("J{$currRow}")->getFont()->getColor()->setRGB(self::COLOR_RED_TEXT);
                $sheet->getStyle("J{$currRow}")->getFont()->setBold(true);
            }

            // Zebra
            if ($no % 2 === 1) {
                $sheet->getStyle("A{$currRow}:L{$currRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_ZEBRA_ROW);
            }

            $sheet->getStyle("A{$currRow}:L{$currRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::COLOR_BORDER);

            $currRow++;
        }

        // ----------------------------------------------------
        // 5. TOTALS ROW
        // ----------------------------------------------------
        $totalRow = $currRow;
        $sheet->getRowDimension($totalRow)->setRowHeight(25);

        $sheet->setCellValue("A{$totalRow}", 'TOTAL KESELURUHAN');
        $sheet->mergeCells("A{$totalRow}:F{$totalRow}");

        if ($currRow > 11) {
            $sheet->setCellValue("G{$totalRow}", "=SUM(G11:G" . ($totalRow - 1) . ")");
            $sheet->setCellValue("H{$totalRow}", "=SUM(H11:H" . ($totalRow - 1) . ")");
            $sheet->setCellValue("I{$totalRow}", "=SUM(I11:I" . ($totalRow - 1) . ")");
            $sheet->setCellValue("J{$totalRow}", "=IF(G{$totalRow}>0, H{$totalRow}/G{$totalRow}, 0)");
        } else {
            $sheet->setCellValue("G{$totalRow}", 0);
            $sheet->setCellValue("H{$totalRow}", 0);
            $sheet->setCellValue("I{$totalRow}", 0);
            $sheet->setCellValue("J{$totalRow}", 0);
        }

        $sheet->setCellValue("K{$totalRow}", '');
        $sheet->setCellValue("L{$totalRow}", 'Total Kasbon Potong Gaji');

        $sheet->getStyle("A{$totalRow}:L{$totalRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::COLOR_TOTAL_BG],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '64748B']],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '0F172A']],
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_BORDER]],
            ],
        ]);

        $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("G{$totalRow}:I{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("G{$totalRow}:I{$totalRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0.00;("Rp "#,##0.00);"-"');
        $sheet->getStyle("J{$totalRow}")->getNumberFormat()->setFormatCode('0.0%');
        $sheet->getStyle("J{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ----------------------------------------------------
        // 6. SIGNOFF SECTION FOR HRD & FINANCE
        // ----------------------------------------------------
        $signRow = $totalRow + 3;
        $sheet->setCellValue("B{$signRow}", 'Disiapkan Oleh:');
        $sheet->setCellValue("F{$signRow}", 'Diverifikasi Oleh:');
        $sheet->setCellValue("J{$signRow}", 'Disetujui Oleh (Payroll):');

        $sheet->getStyle("B{$signRow}")->getFont()->setBold(true);
        $sheet->getStyle("F{$signRow}")->getFont()->setBold(true);
        $sheet->getStyle("J{$signRow}")->getFont()->setBold(true);

        $titleRow = $signRow + 1;
        $sheet->setCellValue("B{$titleRow}", 'Pengurus Koperasi Karyawan');
        $sheet->setCellValue("F{$titleRow}", 'Bagian Keuangan Koperasi');
        $sheet->setCellValue("J{$titleRow}", 'HRD / Finance PT Indo Lampung Perkasa');

        $nameRow = $signRow + 5;
        $sheet->setCellValue("B{$nameRow}", '( ............................................... )');
        $sheet->setCellValue("F{$nameRow}", '( ............................................... )');
        $sheet->setCellValue("J{$nameRow}", '( ............................................... )');

        $dateRow = $nameRow + 1;
        $sheet->setCellValue("B{$dateRow}", 'Tanggal: .......................................');
        $sheet->setCellValue("F{$dateRow}", 'Tanggal: .......................................');
        $sheet->setCellValue("J{$dateRow}", 'Tanggal: .......................................');

        // Column widths
        $widths = [
            'A' => 6,   // No
            'B' => 16,  // Kode
            'C' => 20,  // NIK
            'D' => 30,  // Nama
            'E' => 22,  // Dept
            'F' => 24,  // Cabang
            'G' => 20,  // Plafon
            'H' => 22,  // Kasbon
            'I' => 20,  // Sisa
            'J' => 14,  // Utilisasi
            'K' => 15,  // Status
            'L' => 32,  // Rekomendasi
        ];

        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $sheet->freezePane('A11');

        return $spreadsheet;
    }

    /**
     * Helper to stream download Spreadsheet as .xlsx.
     */
    public function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ];

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, $headers);
    }
}
