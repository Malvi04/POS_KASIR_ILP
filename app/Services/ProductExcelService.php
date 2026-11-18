<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductExcelService
{
    /**
     * Standard 25 column headers for ILP Master Barang.
     */
    public const EXPECTED_COLUMNS = [
        'Barang - ID',
        'Barang - Kode',
        'Barang - KodeBar',
        'Barang - Kel',
        'Barang - Nama',
        'Barang - Unit1',
        'Barang - HargaBeli',
        'Barang - HargaJualK1',
        'Barang - MinQty1',
        'Barang - MinQty2',
        'Barang - MinQty3',
        'Barang - HargaLevel1',
        'Barang - Disc',
        'Barang - StockMin',
        'Barang - ReOrder',
        'Barang - PPN_Out',
        'Barang - PPNChk',
        'Barang - HrgBeliTerakhir',
        'Barang - StatusBarang',
        'Barang - KoSup',
        'Barang - Act',
        'Barang - Masa',
        'Barang - Kadaluarsa',
        'Barang - Up',
        'Barang - Dump',
    ];

    /**
     * Normalize header name for tolerant yet accurate matching.
     */
    protected function normalizeHeader(string $header): string
    {
        $clean = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $clean = strtolower($clean);
        $clean = preg_replace('/\s*[-_–—]\s*/u', '-', $clean);
        $clean = preg_replace('/[\s\x{00a0}\x{200b}]+/u', ' ', $clean);
        return trim($clean);
    }

    /**
     * Validate structure of uploaded Excel/CSV file and generate preview rows.
     */
    public function validateAndPreview(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
            return [
                'valid' => false,
                'message' => 'Format file tidak didukung. Harap unggah file Excel (.xlsx, .xls) atau CSV.',
            ];
        }

        $tempDir = storage_path('app/temp_imports');
        if (!File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $token = 'import_' . Str::random(24) . '.' . $extension;
        $tempPath = $tempDir . DIRECTORY_SEPARATOR . $token;
        $file->move($tempDir, $token);

        try {
            $spreadsheet = IOFactory::load($tempPath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

            // Read Header Row (Row 1)
            $headers = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $val = trim((string) $sheet->getCell([$col, 1])->getValue());
                if ($val !== '') {
                    $headers[$col] = $val;
                }
            }

            // Validate Column Count & Names
            $normalizedExpected = array_map([$this, 'normalizeHeader'], self::EXPECTED_COLUMNS);
            $normalizedActual = array_map([$this, 'normalizeHeader'], $headers);

            $missingColumns = [];
            foreach (self::EXPECTED_COLUMNS as $idx => $expectedName) {
                $norm = $normalizedExpected[$idx];
                if (!in_array($norm, $normalizedActual)) {
                    $missingColumns[] = $expectedName;
                }
            }

            $unexpectedColumns = [];
            foreach ($headers as $actualName) {
                $norm = $this->normalizeHeader($actualName);
                if (!in_array($norm, $normalizedExpected)) {
                    $unexpectedColumns[] = $actualName;
                }
            }

            if (!empty($missingColumns)) {
                // Delete temp file if invalid structure
                if (file_exists($tempPath)) {
                    @unlink($tempPath);
                }

                return [
                    'valid' => false,
                    'message' => 'Format kolom Excel tidak sesuai dengan standar Master Barang ILP (25 Kolom). Ada ' . count($missingColumns) . ' kolom wajib yang tidak ditemukan.',
                    'expected_count' => count(self::EXPECTED_COLUMNS),
                    'actual_count' => count($headers),
                    'missing_columns' => $missingColumns,
                    'unexpected_columns' => $unexpectedColumns,
                    'expected_headers' => self::EXPECTED_COLUMNS,
                    'actual_headers' => array_values($headers),
                ];
            }

            // Map Column Index to Standard Keys
            $columnMap = [];
            foreach ($headers as $colNum => $headerName) {
                $norm = $this->normalizeHeader($headerName);
                $canonicalIdx = array_search($norm, $normalizedExpected);
                if ($canonicalIdx !== false) {
                    $columnMap[$canonicalIdx] = $colNum; // 1-based index
                }
            }

            // Read Rows & Validate Data
            $totalRows = 0;
            $validRows = 0;
            $errorRows = 0;
            $previewRows = [];
            $errors = [];
            $fileSkus = [];
            $duplicateSkusInFile = [];

            // Pre-load all existing SKUs & barcodes from DB to check duplicates fast
            $dbSkus = Product::pluck('sku')->map(fn($s) => strtoupper(trim($s)))->flip()->toArray();

            for ($row = 2; $row <= $highestRow; $row++) {
                // Check if row is completely empty
                $rowEmpty = true;
                $rowData = [];
                foreach (self::EXPECTED_COLUMNS as $canIdx => $canName) {
                    $colNum = $columnMap[$canIdx] ?? ($canIdx + 1);
                    $cell = $sheet->getCell([$colNum, $row]);
                    $val = $cell->getValue();
                    if ($val !== null && trim((string)$val) !== '') {
                        $rowEmpty = false;
                    }
                    $rowData[$canName] = $val;
                }

                if ($rowEmpty) {
                    continue;
                }

                $totalRows++;
                $rowNum = $row;
                $rowErrors = [];

                $sku = trim((string) ($rowData['Barang - Kode'] ?? ''));
                $barcode = trim((string) ($rowData['Barang - KodeBar'] ?? ''));
                $name = trim((string) ($rowData['Barang - Nama'] ?? ''));
                $category = trim((string) ($rowData['Barang - Kel'] ?? 'Umum')) ?: 'Umum';
                $unit = trim((string) ($rowData['Barang - Unit1'] ?? 'Pcs')) ?: 'Pcs';

                // Price & Cost Parsing
                $costPrice = $this->parseNumeric($rowData['Barang - HargaBeli'] ?? 0);
                $price = $this->parseNumeric($rowData['Barang - HargaJualK1'] ?? 0);
                $hargaLevel1 = $this->parseNumeric($rowData['Barang - HargaLevel1'] ?? 0);
                $memberPrice = $hargaLevel1 > 0 ? $hargaLevel1 : $price;

                // Min Qty, Disc, Reorder, Tax
                $minQty1 = $this->parseNumeric($rowData['Barang - MinQty1'] ?? 1);
                $minQty2 = $this->parseNumeric($rowData['Barang - MinQty2'] ?? null);
                $minQty3 = $this->parseNumeric($rowData['Barang - MinQty3'] ?? null);
                $disc = $this->parseNumeric($rowData['Barang - Disc'] ?? 0);
                $stockMin = (int) $this->parseNumeric($rowData['Barang - StockMin'] ?? 5);
                $reorder = (int) $this->parseNumeric($rowData['Barang - ReOrder'] ?? ($stockMin * 2));
                $ppnOut = $this->parseNumeric($rowData['Barang - PPN_Out'] ?? 11);
                $ppnChk = strtoupper(trim((string) ($rowData['Barang - PPNChk'] ?? 'Y')));
                $lastCostPrice = $this->parseNumeric($rowData['Barang - HrgBeliTerakhir'] ?? $costPrice);
                $statusBarang = trim((string) ($rowData['Barang - StatusBarang'] ?? 'TERSEDIA'));
                $koSup = trim((string) ($rowData['Barang - KoSup'] ?? ''));
                $act = strtoupper(trim((string) ($rowData['Barang - Act'] ?? 'Y')));
                $masa = trim((string) ($rowData['Barang - Masa'] ?? ''));
                $kadaluarsa = $this->parseDate($rowData['Barang - Kadaluarsa'] ?? null);
                $up = $this->parseNumeric($rowData['Barang - Up'] ?? 0);
                $dump = trim((string) ($rowData['Barang - Dump'] ?? ''));

                // Validations
                if ($sku === '') {
                    $rowErrors[] = 'Barang - Kode (SKU) wajib diisi.';
                } else {
                    $upperSku = strtoupper($sku);
                    if (isset($fileSkus[$upperSku])) {
                        $duplicateSkusInFile[] = $sku;
                        $rowErrors[] = "SKU '{$sku}' duplikat di dalam file Excel (baris {$fileSkus[$upperSku]} dan {$rowNum}).";
                    } else {
                        $fileSkus[$upperSku] = $rowNum;
                    }
                }

                if ($name === '') {
                    $rowErrors[] = 'Barang - Nama wajib diisi.';
                }

                if ($price < 0) {
                    $rowErrors[] = 'Harga Jual K1 tidak boleh negatif.';
                }

                $isExistingInDb = !empty($sku) && isset($dbSkus[strtoupper($sku)]);

                if (!empty($rowErrors)) {
                    $errorRows++;
                    $errors[] = [
                        'row' => $rowNum,
                        'sku' => $sku ?: '-',
                        'name' => $name ?: '-',
                        'messages' => $rowErrors,
                    ];
                } else {
                    $validRows++;
                }

                // Add to preview (up to first 25 rows)
                if (count($previewRows) < 25) {
                    $previewRows[] = [
                        'row' => $rowNum,
                        'ilp_id' => trim((string)($rowData['Barang - ID'] ?? '')),
                        'sku' => $sku ?: '-',
                        'barcode' => $barcode ?: $sku,
                        'category' => $category,
                        'name' => $name ?: '-',
                        'unit' => $unit,
                        'cost_price' => $costPrice,
                        'price' => $price,
                        'min_qty1' => $minQty1,
                        'min_qty2' => $minQty2,
                        'min_qty3' => $minQty3,
                        'harga_level1' => $hargaLevel1,
                        'disc' => $disc,
                        'stock_min' => $stockMin,
                        'reorder' => $reorder,
                        'ppn_out' => $ppnOut,
                        'ppn_chk' => $ppnChk,
                        'last_cost_price' => $lastCostPrice,
                        'status_barang' => $statusBarang,
                        'ko_sup' => $koSup,
                        'act' => $act,
                        'masa' => $masa,
                        'kadaluarsa' => $kadaluarsa ? $kadaluarsa->format('Y-m-d') : '',
                        'up' => $up,
                        'dump' => $dump,
                        'is_existing' => $isExistingInDb,
                        'has_error' => !empty($rowErrors),
                        'error_message' => !empty($rowErrors) ? implode(', ', $rowErrors) : null,
                    ];
                }
            }

            return [
                'valid' => true,
                'token' => $token,
                'file_name' => $file->getClientOriginalName(),
                'summary' => [
                    'total_rows' => $totalRows,
                    'valid_rows' => $validRows,
                    'error_rows' => $errorRows,
                    'existing_in_db_count' => count(array_intersect(array_keys($fileSkus), array_keys($dbSkus))),
                    'duplicates_in_file_count' => count(array_unique($duplicateSkusInFile)),
                ],
                'errors' => array_slice($errors, 0, 50),
                'preview_rows' => $previewRows,
            ];

        } catch (\Throwable $e) {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }

            return [
                'valid' => false,
                'message' => 'Gagal membaca file Excel: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Process the confirmed Excel import in transactional batches.
     * Rule: Master product import MUST NOT alter current store stock.
     */
    public function processImport(string $token, string $duplicateAction = 'update'): array
    {
        $tempPath = storage_path('app/temp_imports/' . basename($token));
        if (!file_exists($tempPath)) {
            return [
                'success' => false,
                'message' => 'File token import tidak ditemukan atau telah kedaluwarsa. Silakan unggah ulang.',
            ];
        }

        try {
            $spreadsheet = IOFactory::load($tempPath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

            // Read Header Row
            $headers = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $val = trim((string) $sheet->getCell([$col, 1])->getValue());
                if ($val !== '') {
                    $headers[$col] = $val;
                }
            }

            $normalizedExpected = array_map([$this, 'normalizeHeader'], self::EXPECTED_COLUMNS);
            $columnMap = [];
            foreach ($headers as $colNum => $headerName) {
                $norm = $this->normalizeHeader($headerName);
                $canonicalIdx = array_search($norm, $normalizedExpected);
                if ($canonicalIdx !== false) {
                    $columnMap[$canonicalIdx] = $colNum;
                }
            }

            $createdCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            $failedCount = 0;
            $failedDetails = [];

            // Process in batch chunks
            $batchData = [];

            for ($row = 2; $row <= $highestRow; $row++) {
                $rowEmpty = true;
                $rowData = [];
                foreach (self::EXPECTED_COLUMNS as $canIdx => $canName) {
                    $colNum = $columnMap[$canIdx] ?? ($canIdx + 1);
                    $val = $sheet->getCell([$colNum, $row])->getValue();
                    if ($val !== null && trim((string)$val) !== '') {
                        $rowEmpty = false;
                    }
                    $rowData[$canName] = $val;
                }

                if ($rowEmpty) {
                    continue;
                }

                $sku = trim((string) ($rowData['Barang - Kode'] ?? ''));
                $name = trim((string) ($rowData['Barang - Nama'] ?? ''));

                if ($sku === '' || $name === '') {
                    $failedCount++;
                    $failedDetails[] = "Baris {$row}: SKU atau Nama barang kosong.";
                    continue;
                }

                $batchData[] = [
                    'row_number' => $row,
                    'data' => $rowData,
                ];
            }

            // Process records in chunks of 100
            $chunks = array_chunk($batchData, 100);

            foreach ($chunks as $chunk) {
                DB::transaction(function () use ($chunk, $duplicateAction, &$createdCount, &$updatedCount, &$skippedCount, &$failedCount, &$failedDetails) {
                    foreach ($chunk as $item) {
                        $row = $item['row_number'];
                        $r = $item['data'];

                        $sku = trim((string) ($r['Barang - Kode'] ?? ''));
                        $barcode = trim((string) ($r['Barang - KodeBar'] ?? '')) ?: $sku;
                        $name = trim((string) ($r['Barang - Nama'] ?? ''));
                        $category = trim((string) ($r['Barang - Kel'] ?? 'Umum')) ?: 'Umum';
                        $unit = trim((string) ($r['Barang - Unit1'] ?? 'Pcs')) ?: 'Pcs';

                        $costPrice = $this->parseNumeric($r['Barang - HargaBeli'] ?? 0);
                        $lastCostPrice = $this->parseNumeric($r['Barang - HrgBeliTerakhir'] ?? $costPrice);
                        $price = $this->parseNumeric($r['Barang - HargaJualK1'] ?? 0);
                        $hargaLevel1 = $this->parseNumeric($r['Barang - HargaLevel1'] ?? 0);
                        $memberPrice = $hargaLevel1 > 0 ? $hargaLevel1 : $price;

                        $minQty1 = $this->parseNumeric($r['Barang - MinQty1'] ?? 1);
                        $minQty2 = $this->parseNumeric($r['Barang - MinQty2'] ?? null);
                        $minQty3 = $this->parseNumeric($r['Barang - MinQty3'] ?? null);

                        $stockMin = (int) $this->parseNumeric($r['Barang - StockMin'] ?? 5);
                        $reorder = (int) $this->parseNumeric($r['Barang - ReOrder'] ?? ($stockMin * 2));
                        $disc = $this->parseNumeric($r['Barang - Disc'] ?? 0);
                        $taxRate = $this->parseNumeric($r['Barang - PPN_Out'] ?? 11);

                        // PPN Checkbox (Y / 1 / true)
                        $ppnChk = strtoupper(trim((string) ($r['Barang - PPNChk'] ?? 'Y')));
                        $isTaxable = in_array($ppnChk, ['Y', '1', 'TRUE', 'YA']);

                        // Status Barang & Active flag
                        $statusRaw = strtoupper(trim((string) ($r['Barang - StatusBarang'] ?? 'ACTIVE')));
                        $actRaw = strtoupper(trim((string) ($r['Barang - Act'] ?? 'Y')));
                        $status = ($actRaw === 'N' || $statusRaw === 'NONAKTIF') ? 'NONAKTIF' : 'TERSEDIA';

                        $koSup = trim((string) ($r['Barang - KoSup'] ?? ''));
                        $masa = trim((string) ($r['Barang - Masa'] ?? ''));
                        $expiredDate = $this->parseDate($r['Barang - Kadaluarsa'] ?? null);
                        $markup = $this->parseNumeric($r['Barang - Up'] ?? 0);
                        $dump = trim((string) ($r['Barang - Dump'] ?? ''));
                        $ilpId = trim((string) ($r['Barang - ID'] ?? ''));

                        // Find existing product by SKU or Barcode
                        $existing = Product::where('sku', $sku)->orWhere('barcode', $barcode)->first();

                        if ($existing) {
                            if ($duplicateAction === 'skip') {
                                $skippedCount++;
                                continue;
                            }

                            // UPDATE existing product details WITHOUT changing current stock!
                            $existing->update([
                                'ilp_id' => $ilpId ?: $existing->ilp_id,
                                'barcode' => $barcode,
                                'name' => $name,
                                'category' => $category,
                                'unit' => $unit,
                                'cost_price' => $costPrice,
                                'last_cost_price' => $lastCostPrice,
                                'price' => $price,
                                'member_price' => $memberPrice,
                                'min_qty1' => $minQty1,
                                'min_qty2' => $minQty2,
                                'min_qty3' => $minQty3,
                                'harga_level1' => $hargaLevel1,
                                'min_stock' => $stockMin,
                                'reorder_point' => $reorder,
                                'clearance_discount_percent' => $disc > 0 ? $disc : $existing->clearance_discount_percent,
                                'tax_rate' => $taxRate,
                                'is_taxable' => $isTaxable,
                                'expired_date' => $expiredDate ?: $existing->expired_date,
                                'markup_percent' => $markup,
                                'status' => $existing->stock <= 0 ? 'HABIS' : ($existing->stock <= $stockMin ? 'STOK_RENDAH' : $status),
                                'ko_sup' => $koSup ?: $existing->ko_sup,
                                'act' => $actRaw,
                                'masa' => $masa ?: $existing->masa,
                                'dump' => $dump ?: $existing->dump,
                            ]);

                            $updatedCount++;
                        } else {
                            // CREATE new product with stock = 0
                            Product::create([
                                'ilp_id' => $ilpId,
                                'sku' => $sku,
                                'barcode' => $barcode,
                                'name' => $name,
                                'category' => $category,
                                'unit' => $unit,
                                'cost_price' => $costPrice,
                                'last_cost_price' => $lastCostPrice,
                                'price' => $price,
                                'member_price' => $memberPrice,
                                'min_qty1' => $minQty1,
                                'min_qty2' => $minQty2,
                                'min_qty3' => $minQty3,
                                'harga_level1' => $hargaLevel1,
                                'stock' => 0, // Master product import does NOT create physical stock out of thin air
                                'min_stock' => $stockMin,
                                'reorder_point' => $reorder,
                                'clearance_discount_percent' => $disc > 0 ? $disc : 20.0,
                                'tax_rate' => $taxRate,
                                'is_taxable' => $isTaxable,
                                'expired_date' => $expiredDate,
                                'markup_percent' => $markup,
                                'status' => 'HABIS',
                                'ko_sup' => $koSup,
                                'act' => $actRaw,
                                'masa' => $masa,
                                'dump' => $dump,
                                'is_consignment' => false,
                            ]);

                            $createdCount++;
                        }
                    }
                });
            }

            // Clean up temporary file
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }

            return [
                'success' => true,
                'message' => "Import master barang selesai. {$createdCount} produk baru ditambahkan, {$updatedCount} produk diperbarui, {$skippedCount} dilewati.",
                'summary' => [
                    'total_processed' => $createdCount + $updatedCount + $skippedCount,
                    'created' => $createdCount,
                    'updated' => $updatedCount,
                    'skipped' => $skippedCount,
                    'failed' => $failedCount,
                ],
                'errors' => $failedDetails,
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses import: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Export products from Laravel DB directly to Excel matching standard 25 ILP columns.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = Product::with(['supplier']);

        // Filters matching the manager inventory list
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category') && $request->category !== 'all' && $request->category !== 'Semua') {
            $query->where('category', $request->category);
        }

        if ($request->filled('stock_status')) {
            $status = $request->stock_status;
            if ($status === 'STOK_RENDAH') {
                $query->whereColumn('stock', '<=', 'min_stock')->where('stock', '>', 0);
            } elseif ($status === 'HABIS') {
                $query->where('stock', '<=', 0);
            } elseif ($status === 'TERSEDIA') {
                $query->whereColumn('stock', '>', 'min_stock');
            }
        }

        $products = $query->orderBy('name', 'asc')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Master Barang ILP');

        // Header Styling
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
                'name' => 'Calibri',
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E78'], // Navy Blue
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9'],
                ],
            ],
        ];

        // Write Headers
        foreach (self::EXPECTED_COLUMNS as $idx => $colName) {
            $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
            $sheet->setCellValue("{$colLetter}1", $colName);
        }
        $sheet->getStyle('A1:' . Coordinate::stringFromColumnIndex(count(self::EXPECTED_COLUMNS)) . '1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Write Product Rows
        $rowIdx = 2;
        foreach ($products as $p) {
            $sheet->setCellValue("A{$rowIdx}", $p->ilp_id ?: $p->id);
            $sheet->setCellValueExplicit("B{$rowIdx}", (string) $p->sku, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$rowIdx}", (string) ($p->barcode ?: $p->sku), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("D{$rowIdx}", $p->category ?: 'Umum');
            $sheet->setCellValue("E{$rowIdx}", $p->name);
            $sheet->setCellValue("F{$rowIdx}", $p->unit ?: 'Pcs');
            $sheet->setCellValue("G{$rowIdx}", (float) $p->cost_price);
            $sheet->setCellValue("H{$rowIdx}", (float) $p->price);
            $sheet->setCellValue("I{$rowIdx}", (float) ($p->min_qty1 ?? 1));
            $sheet->setCellValue("J{$rowIdx}", $p->min_qty2 !== null ? (float) $p->min_qty2 : '');
            $sheet->setCellValue("K{$rowIdx}", $p->min_qty3 !== null ? (float) $p->min_qty3 : '');
            $sheet->setCellValue("L{$rowIdx}", (float) ($p->harga_level1 ?? $p->member_price ?? $p->price));
            $sheet->setCellValue("M{$rowIdx}", (float) ($p->clearance_discount_percent ?: 0));
            $sheet->setCellValue("N{$rowIdx}", (int) $p->min_stock);
            $sheet->setCellValue("O{$rowIdx}", (int) ($p->reorder_point ?? ($p->min_stock * 2)));
            $sheet->setCellValue("P{$rowIdx}", (float) ($p->tax_rate ?: 11));
            $sheet->setCellValue("Q{$rowIdx}", $p->is_taxable ? 'Y' : 'N');
            $sheet->setCellValue("R{$rowIdx}", (float) ($p->last_cost_price ?: $p->cost_price));
            $sheet->setCellValue("S{$rowIdx}", $p->status ?: 'TERSEDIA');
            $sheet->setCellValue("T{$rowIdx}", $p->ko_sup ?: ($p->supplier?->code ?? ''));
            $sheet->setCellValue("U{$rowIdx}", $p->act ?: ($p->status === 'NONAKTIF' ? 'N' : 'Y'));
            $sheet->setCellValue("V{$rowIdx}", $p->masa ?? '');
            $sheet->setCellValue("W{$rowIdx}", $p->expired_date ? $p->expired_date->format('Y-m-d') : '');
            $sheet->setCellValue("X{$rowIdx}", $p->markup_percent ?? '');
            $sheet->setCellValue("Y{$rowIdx}", $p->dump ?? '');

            $rowIdx++;
        }

        // Auto-fit Column Widths
        for ($col = 1; $col <= count(self::EXPECTED_COLUMNS); $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $filename = 'Export_Master_Barang_ILP_25Kolom_' . date('Ymd_His') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Download blank template with all 25 canonical ILP headers and 1 sample row.
     */
    public function generateTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Master Barang');

        // Header Styling
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0052CC'], // Blue Brand
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];

        foreach (self::EXPECTED_COLUMNS as $idx => $colName) {
            $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
            $sheet->setCellValue("{$colLetter}1", $colName);
        }
        $sheet->getStyle('A1:' . Coordinate::stringFromColumnIndex(count(self::EXPECTED_COLUMNS)) . '1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Sample Data Row
        $sampleRow = [
            'BRG-001',
            'SKU-SAMPLE-01',
            '8991234567890',
            'Makanan & Minuman',
            'Kopi Susu Instan 10x20g',
            'Pack',
            12000,
            15000,
            1,
            5,
            10,
            14000,
            0,
            5,
            10,
            11,
            'Y',
            12000,
            'TERSEDIA',
            'SUP-001',
            'Y',
            '365',
            date('Y-12-31', strtotime('+1 year')),
            25,
            'Contoh catatan master barang',
        ];

        foreach ($sampleRow as $idx => $val) {
            $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
            if (is_numeric($val) && !is_string($val)) {
                $sheet->setCellValue("{$colLetter}2", $val);
            } else {
                $sheet->setCellValueExplicit("{$colLetter}2", (string)$val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
        }

        // Auto-fit
        for ($col = 1; $col <= count(self::EXPECTED_COLUMNS); $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $filename = 'Template_Master_Barang_ILP_25Kolom.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Helper to safely parse numeric value from Excel cells.
     */
    protected function parseNumeric(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[^0-9.,-]/', '', (string) $value);
        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            if (strrpos($clean, ',') > strrpos($clean, '.')) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        } elseif (str_contains($clean, ',')) {
            $clean = str_replace(',', '.', $clean);
        }

        return (float) $clean;
    }

    /**
     * Helper to safely parse Excel dates.
     */
    protected function parseDate(mixed $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
            }

            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
