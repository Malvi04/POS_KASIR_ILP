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
     * Standard 36 column headers for ILP Master Barang.
     */
    public const EXPECTED_COLUMNS = [
        'Barang - ID',
        'Barang - Kode',
        'Barang - KodeBar',
        'Barang - Kel',
        'Barang - Rak',
        'Barang - Nama',
        'Barang - Unit1',
        'Barang - Konversi1',
        'Barang - Unit2',
        'Barang - Konversi2',
        'Barang - Unit3',
        'Barang - Konversi3',
        'Barang - HargaBeli',
        'Barang - HargaJualK1',
        'Barang - HargaJualK2',
        'Barang - HargaJualK3',
        'Barang - MinQty1',
        'Barang - MinQty2',
        'Barang - MinQty3',
        'Barang - HargaLevel1',
        'Barang - HargaLevel2',
        'Barang - HargaLevel3',
        'Barang - HargaLevel4',
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
        return trim(preg_replace('/\s+/', ' ', strtolower($header)));
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
                    $headers[] = $val;
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

            if (!empty($missingColumns) || count($headers) !== count(self::EXPECTED_COLUMNS)) {
                // Delete temp file if invalid structure
                if (file_exists($tempPath)) {
                    @unlink($tempPath);
                }

                return [
                    'valid' => false,
                    'message' => 'Format kolom Excel tidak sesuai dengan standar Master Barang ILP (36 Kolom).',
                    'expected_count' => count(self::EXPECTED_COLUMNS),
                    'actual_count' => count($headers),
                    'missing_columns' => $missingColumns,
                    'unexpected_columns' => $unexpectedColumns,
                    'expected_headers' => self::EXPECTED_COLUMNS,
                    'actual_headers' => $headers,
                ];
            }

            // Map Column Index to Standard Keys
            $columnMap = [];
            foreach ($headers as $colIdxZero => $headerName) {
                $norm = $this->normalizeHeader($headerName);
                $canonicalIdx = array_search($norm, $normalizedExpected);
                if ($canonicalIdx !== false) {
                    $columnMap[$canonicalIdx] = $colIdxZero + 1; // 1-based index
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
                $rack = trim((string) ($rowData['Barang - Rak'] ?? '')) ?: 'Lorong Toko';

                // Price & Cost Parsing
                $costPrice = $this->parseNumeric($rowData['Barang - HargaBeli'] ?? 0);
                $price = $this->parseNumeric($rowData['Barang - HargaJualK1'] ?? 0);
                $memberPrice = $this->parseNumeric($rowData['Barang - HargaJualK2'] ?? 0);

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
                        'name' => $name ?: '-',
                        'category' => $category,
                        'rack' => $rack,
                        'unit' => $unit,
                        'cost_price' => $costPrice,
                        'price' => $price,
                        'member_price' => $memberPrice > 0 ? $memberPrice : $price,
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
                'errors' => array_slice($errors, 0, 50), // Show top 50 errors if many
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
                $headers[] = trim((string) $sheet->getCell([$col, 1])->getValue());
            }

            $normalizedExpected = array_map([$this, 'normalizeHeader'], self::EXPECTED_COLUMNS);
            $columnMap = [];
            foreach ($headers as $colIdxZero => $headerName) {
                $norm = $this->normalizeHeader($headerName);
                $canonicalIdx = array_search($norm, $normalizedExpected);
                if ($canonicalIdx !== false) {
                    $columnMap[$canonicalIdx] = $colIdxZero + 1;
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
                        $rack = trim((string) ($r['Barang - Rak'] ?? '')) ?: 'Lorong Toko';
                        $unit = trim((string) ($r['Barang - Unit1'] ?? 'Pcs')) ?: 'Pcs';

                        $costPrice = $this->parseNumeric($r['Barang - HargaBeli'] ?? 0);
                        $lastCostPrice = $this->parseNumeric($r['Barang - HrgBeliTerakhir'] ?? $costPrice);
                        $price = $this->parseNumeric($r['Barang - HargaJualK1'] ?? 0);
                        $memberPrice = $this->parseNumeric($r['Barang - HargaJualK2'] ?? 0);
                        if ($memberPrice <= 0) $memberPrice = $price;

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

                        // Expired Date
                        $expiredDate = $this->parseDate($r['Barang - Kadaluarsa'] ?? null);

                        // Mark-up percent
                        $markup = $this->parseNumeric($r['Barang - Up'] ?? 0);

                        // Collect extra attributes to preserve all 36 fields losslessly
                        $extraAttributes = [
                            'ilp_id' => trim((string) ($r['Barang - ID'] ?? '')),
                            'konversi1' => $this->parseNumeric($r['Barang - Konversi1'] ?? 1),
                            'unit2' => trim((string) ($r['Barang - Unit2'] ?? '')),
                            'konversi2' => $this->parseNumeric($r['Barang - Konversi2'] ?? 0),
                            'unit3' => trim((string) ($r['Barang - Unit3'] ?? '')),
                            'konversi3' => $this->parseNumeric($r['Barang - Konversi3'] ?? 0),
                            'harga_jual_k3' => $this->parseNumeric($r['Barang - HargaJualK3'] ?? 0),
                            'min_qty1' => $this->parseNumeric($r['Barang - MinQty1'] ?? 1),
                            'min_qty2' => $this->parseNumeric($r['Barang - MinQty2'] ?? 0),
                            'min_qty3' => $this->parseNumeric($r['Barang - MinQty3'] ?? 0),
                            'harga_level1' => $this->parseNumeric($r['Barang - HargaLevel1'] ?? 0),
                            'harga_level2' => $this->parseNumeric($r['Barang - HargaLevel2'] ?? 0),
                            'harga_level3' => $this->parseNumeric($r['Barang - HargaLevel3'] ?? 0),
                            'harga_level4' => $this->parseNumeric($r['Barang - HargaLevel4'] ?? 0),
                            'kosup' => trim((string) ($r['Barang - KoSup'] ?? '')),
                            'masa' => trim((string) ($r['Barang - Masa'] ?? '')),
                            'dump' => trim((string) ($r['Barang - Dump'] ?? '')),
                        ];

                        // Find existing product by SKU or Barcode
                        $existing = Product::where('sku', $sku)->orWhere('barcode', $barcode)->first();

                        if ($existing) {
                            if ($duplicateAction === 'skip') {
                                $skippedCount++;
                                continue;
                            }

                            // UPDATE existing product details WITHOUT changing current stock!
                            $existing->update([
                                'ilp_id' => $extraAttributes['ilp_id'] ?: $existing->ilp_id,
                                'barcode' => $barcode,
                                'name' => $name,
                                'category' => $category,
                                'unit' => $unit,
                                'rack_location' => $rack,
                                'cost_price' => $costPrice,
                                'last_cost_price' => $lastCostPrice,
                                'price' => $price,
                                'member_price' => $memberPrice,
                                'min_stock' => $stockMin,
                                'reorder_point' => $reorder,
                                'clearance_discount_percent' => $disc > 0 ? $disc : $existing->clearance_discount_percent,
                                'tax_rate' => $taxRate,
                                'is_taxable' => $isTaxable,
                                'expired_date' => $expiredDate ?: $existing->expired_date,
                                'markup_percent' => $markup,
                                'status' => $existing->stock <= 0 ? 'HABIS' : ($existing->stock <= $stockMin ? 'STOK_RENDAH' : $status),
                                'extra_attributes' => array_merge($existing->extra_attributes ?? [], $extraAttributes),
                            ]);

                            $product = $existing;
                            $updatedCount++;
                        } else {
                            // CREATE new product with stock = 0 (Stock is maintained via Goods Receipt / Stock Opname)
                            $product = Product::create([
                                'ilp_id' => $extraAttributes['ilp_id'],
                                'sku' => $sku,
                                'barcode' => $barcode,
                                'name' => $name,
                                'category' => $category,
                                'unit' => $unit,
                                'rack_location' => $rack,
                                'cost_price' => $costPrice,
                                'last_cost_price' => $lastCostPrice,
                                'price' => $price,
                                'member_price' => $memberPrice,
                                'stock' => 0, // Master product import does NOT create physical stock out of thin air
                                'min_stock' => $stockMin,
                                'reorder_point' => $reorder,
                                'clearance_discount_percent' => $disc > 0 ? $disc : 20.0,
                                'tax_rate' => $taxRate,
                                'is_taxable' => $isTaxable,
                                'expired_date' => $expiredDate,
                                'markup_percent' => $markup,
                                'status' => 'HABIS', // initial stock 0 is HABIS until barang masuk
                                'is_consignment' => false,
                                'extra_attributes' => $extraAttributes,
                            ]);

                            $createdCount++;
                        }

                        // Sync Multi-Satuan (Unit2 & Unit3)
                        $this->syncProductUnits($product, $extraAttributes);
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
     * Synchronize Unit 2 and Unit 3 conversions into ProductUnit table.
     */
    protected function syncProductUnits(Product $product, array $extra): void
    {
        // Unit 2
        if (!empty($extra['unit2']) && ($extra['konversi2'] ?? 0) > 1) {
            $unitPrice = ($extra['harga_jual_k2'] ?? 0) > 0 ? $extra['harga_jual_k2'] : ($product->price * $extra['konversi2']);
            ProductUnit::updateOrCreate(
                ['product_id' => $product->id, 'unit_name' => $extra['unit2']],
                [
                    'conversion_factor' => (int) $extra['konversi2'],
                    'barcode' => $product->barcode . '-U2',
                    'price' => $unitPrice,
                    'member_price' => $product->member_price * $extra['konversi2'],
                    'is_base_unit' => false,
                ]
            );
        }

        // Unit 3
        if (!empty($extra['unit3']) && ($extra['konversi3'] ?? 0) > 1) {
            $unitPrice = ($extra['harga_jual_k3'] ?? 0) > 0 ? $extra['harga_jual_k3'] : ($product->price * $extra['konversi3']);
            ProductUnit::updateOrCreate(
                ['product_id' => $product->id, 'unit_name' => $extra['unit3']],
                [
                    'conversion_factor' => (int) $extra['konversi3'],
                    'barcode' => $product->barcode . '-U3',
                    'price' => $unitPrice,
                    'member_price' => $product->member_price * $extra['konversi3'],
                    'is_base_unit' => false,
                ]
            );
        }
    }

    /**
     * Export products from Laravel DB directly to Excel matching standard 36 ILP columns.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = Product::with(['units', 'supplier']);

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
            $extra = $p->extra_attributes ?? [];
            $units = $p->units;

            $unit2 = $units->first()?->unit_name ?? ($extra['unit2'] ?? '');
            $konv2 = $units->first()?->conversion_factor ?? ($extra['konversi2'] ?? '');
            $unit3 = $units->skip(1)->first()?->unit_name ?? ($extra['unit3'] ?? '');
            $konv3 = $units->skip(1)->first()?->conversion_factor ?? ($extra['konversi3'] ?? '');

            $sheet->setCellValue("A{$rowIdx}", $p->ilp_id ?: $p->id);
            $sheet->setCellValueExplicit("B{$rowIdx}", $p->sku, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$rowIdx}", $p->barcode ?: $p->sku, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("D{$rowIdx}", $p->category ?: 'Umum');
            $sheet->setCellValue("E{$rowIdx}", $p->rack_location ?: 'Lorong Toko');
            $sheet->setCellValue("F{$rowIdx}", $p->name);
            $sheet->setCellValue("G{$rowIdx}", $p->unit ?: 'Pcs');
            $sheet->setCellValue("H{$rowIdx}", 1);
            $sheet->setCellValue("I{$rowIdx}", $unit2);
            $sheet->setCellValue("J{$rowIdx}", $konv2);
            $sheet->setCellValue("K{$rowIdx}", $unit3);
            $sheet->setCellValue("L{$rowIdx}", $konv3);
            $sheet->setCellValue("M{$rowIdx}", (float) $p->cost_price);
            $sheet->setCellValue("N{$rowIdx}", (float) $p->price);
            $sheet->setCellValue("O{$rowIdx}", (float) ($p->member_price ?: $p->price));
            $sheet->setCellValue("P{$rowIdx}", $extra['harga_jual_k3'] ?? '');
            $sheet->setCellValue("Q{$rowIdx}", $extra['min_qty1'] ?? 1);
            $sheet->setCellValue("R{$rowIdx}", $extra['min_qty2'] ?? '');
            $sheet->setCellValue("S{$rowIdx}", $extra['min_qty3'] ?? '');
            $sheet->setCellValue("T{$rowIdx}", $extra['harga_level1'] ?? '');
            $sheet->setCellValue("U{$rowIdx}", $extra['harga_level2'] ?? '');
            $sheet->setCellValue("V{$rowIdx}", $extra['harga_level3'] ?? '');
            $sheet->setCellValue("W{$rowIdx}", $extra['harga_level4'] ?? '');
            $sheet->setCellValue("X{$rowIdx}", (float) ($p->clearance_discount_percent ?: 0));
            $sheet->setCellValue("Y{$rowIdx}", (int) $p->min_stock);
            $sheet->setCellValue("Z{$rowIdx}", $p->reorder_point ?? ($p->min_stock * 2));
            $sheet->setCellValue("AA{$rowIdx}", (float) ($p->tax_rate ?: 11));
            $sheet->setCellValue("AB{$rowIdx}", $p->is_taxable ? 'Y' : 'N');
            $sheet->setCellValue("AC{$rowIdx}", (float) ($p->last_cost_price ?: $p->cost_price));
            $sheet->setCellValue("AD{$rowIdx}", $p->status ?: 'Active');
            $sheet->setCellValue("AE{$rowIdx}", $p->supplier?->code ?? ($extra['kosup'] ?? ''));
            $sheet->setCellValue("AF{$rowIdx}", $p->status === 'NONAKTIF' ? 'N' : 'Y');
            $sheet->setCellValue("AG{$rowIdx}", $extra['masa'] ?? '');
            $sheet->setCellValue("AH{$rowIdx}", $p->expired_date ? $p->expired_date->format('Y-m-d') : '');
            $sheet->setCellValue("AI{$rowIdx}", $p->markup_percent ?? '');
            $sheet->setCellValue("AJ{$rowIdx}", $extra['dump'] ?? '');

            $rowIdx++;
        }

        // Auto-fit Column Widths
        for ($col = 1; $col <= count(self::EXPECTED_COLUMNS); $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $filename = 'Export_Master_Barang_ILP_' . date('Ymd_His') . '.xlsx';

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
     * Download blank template with all 36 canonical ILP headers.
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

        // Auto-fit
        for ($col = 1; $col <= count(self::EXPECTED_COLUMNS); $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $filename = 'Template_Master_Barang_ILP_36Kolom.xlsx';

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
     * Parse numeric values safely (handling Indonesian commas and dots).
     */
    protected function parseNumeric(mixed $val): float
    {
        if (is_numeric($val)) {
            return (float) $val;
        }
        if (empty($val)) {
            return 0.0;
        }

        $str = trim((string) $val);
        // Remove currency symbols, spaces
        $str = preg_replace('/[^\d.,-]/', '', $str);

        // If contains both . and , (e.g. 10.000,50 or 10,000.50)
        if (str_contains($str, '.') && str_contains($str, ',')) {
            if (strrpos($str, ',') > strrpos($str, '.')) {
                // Indonesian format: 10.000,00 -> 10000.00
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } else {
                // US format: 10,000.00 -> 10000.00
                $str = str_replace(',', '', $str);
            }
        } elseif (str_contains($str, ',')) {
            $str = str_replace(',', '.', $str);
        }

        return (float) $str;
    }

    /**
     * Parse Excel date or string date safely.
     */
    protected function parseDate(mixed $val): ?string
    {
        if (empty($val)) {
            return null;
        }

        if (is_numeric($val)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject($val);
                return $dt->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        try {
            $carbon = Carbon::parse(trim((string)$val));
            return $carbon->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
