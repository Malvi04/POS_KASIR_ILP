<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductStock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
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
use XMLReader;
use ZipArchive;

class ProductExcelService
{
    /**
     * Standard 19 column headers for Master Produk / Gudang.
     */
    public const EXPECTED_COLUMNS = [
        'IdProduk',
        'SKU',
        'Kode Barcode',
        'KodeProduk',
        'QtyProduk',
        'NamaProduk',
        'SatuanUnit',
        'HargaBeli',
        'HargaJualUmum',
        'HargaJualMember',
        'ProdukDisc',
        'MinimumStock',
        'PPNOut',
        'PPNChk',
        'HargaBeliTerakhir',
        'StatusBarang',
        'KodeSuplier',
        'ManufacturingDate',
        'ExpDate',
    ];

    /**
     * Flexible alias mapping for each canonical column.
     */
    public const COLUMN_ALIASES = [
        'id_produk' => ['idproduk', 'id_produk', 'id produk', 'ilp_id', 'barang-id', 'id', 'product_id', 'productid', 'idbarang', 'id_barang'],
        'sku' => ['sku', 'barang-kode', 'kode', 'kodebarang', 'kode_barang', 'kode barang', 'plu', 'item_code', 'itemcode', 'kode_item', 'kodeproduk', 'kode_produk', 'kode produk'],
        'kode_barcode' => ['kode barcode', 'kode_barcode', 'kodebarcode', 'barcode', 'barang-kodebar', 'kodebar', 'ean', 'upc', 'kode_bar', 'no_barcode', 'nobarcode'],
        'kode_produk' => ['kodeproduk', 'kode_produk', 'kode produk', 'product_code', 'productcode', 'kodeprod', 'kode_prod', 'kode_item'],
        'qty_produk' => ['qtyproduk', 'qty_produk', 'qty produk', 'qty', 'stock', 'stok', 'jumlah', 'master_stock', 'kuantitas', 'quantity', 'saldo_stok', 'stok_awal', 'stok_gudang', 'saldo'],
        'nama_produk' => ['namaproduk', 'nama_produk', 'nama produk', 'nama barang', 'namabarang', 'nama_barang', 'barang-nama', 'name', 'nama', 'item_name', 'itemname', 'deskripsi', 'description', 'produk'],
        'satuan_unit' => ['satuanunit', 'satuan_unit', 'satuan unit', 'unit', 'satuan', 'barang-unit1', 'unit1', 'uom', 'kemasan', 'satuan_barang'],
        'harga_beli' => ['hargabeli', 'harga_beli', 'harga beli', 'cost_price', 'costprice', 'hpp', 'barang-hargabeli', 'harga_pokok', 'hargapokok', 'modal', 'harga_modal', 'cost'],
        'harga_jual_umum' => ['hargajualumum', 'harga_jual_umum', 'harga jual umum', 'harga_jual', 'harga jual', 'hargajual', 'price', 'selling_price', 'sellingprice', 'harga', 'barang-hargajualk1', 'hargajualk1', 'harga_eceran'],
        'harga_jual_member' => ['hargajualmember', 'harga_jual_member', 'harga jual member', 'harga_member', 'member_price', 'hargalevel1', 'barang-hargalevel1', 'hargakhusus', 'harga_khusus', 'hargagrosir', 'harga_grosir'],
        'produk_disc' => ['produkdisc', 'produk_disc', 'produk disc', 'disc', 'diskon', 'discount', 'barang-disc', 'diskon_persen', 'potongan'],
        'minimum_stock' => ['minimumstock', 'minimum_stock', 'minimum stock', 'min_stock', 'stockmin', 'stock_min', 'barang-stockmin', 'stok_minimum', 'stok_min', 'min_stok'],
        'ppn_out' => ['ppnout', 'ppn_out', 'ppn out', 'tax_rate', 'ppn', 'tarif_ppn', 'barang-ppn_out', 'pajak', 'ppn_persen', 'tarif_pajak'],
        'ppn_chk' => ['ppnchk', 'ppn_chk', 'ppn chk', 'is_taxable', 'kena_ppn', 'barang-ppnchk', 'kenapajak', 'pajak_chk', 'status_ppn', 'ppn_ya_tidak'],
        'harga_beli_terakhir' => ['hargabeliterakhir', 'harga_beli_terakhir', 'harga beli terakhir', 'last_cost_price', 'hrgbeliterakhir', 'barang-hrgbeliterakhir', 'beli_terakhir', 'hpp_terakhir'],
        'status_barang' => ['statusbarang', 'status_barang', 'status barang', 'status', 'barang-statusbarang', 'kondisi', 'status_produk'],
        'kode_suplier' => ['kodesuplier', 'kode_suplier', 'kode suplier', 'kode_supplier', 'kodesupplier', 'supplier_code', 'kosup', 'ko_sup', 'barang-kosup', 'supplier', 'suplier', 'pemasok'],
        'manufacturing_date' => ['manufacturingdate', 'manufacturing_date', 'manufacturing date', 'mfg_date', 'mfgdate', 'tgl_produksi', 'tanggal_produksi', 'tgl produksi', 'produksi'],
        'exp_date' => ['expdate', 'exp_date', 'exp date', 'expired_date', 'expireddate', 'expired date', 'kadaluarsa', 'barang-kadaluarsa', 'tgl_kadaluarsa', 'tanggal_kadaluarsa', 'exp', 'expired'],
    ];

    /**
     * Normalize header name for tolerant matching.
     */
    public function normalizeHeader(string $header): string
    {
        $clean = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $clean = strtolower($clean);
        $clean = preg_replace('/\s*[-_–—]\s*/u', '-', $clean);
        $clean = preg_replace('/[\s\x{00a0}\x{200b}]+/u', ' ', $clean);
        return trim($clean);
    }

    /**
     * Resolve column mappings from uploaded header array.
     */
    public function resolveColumnMapping(array $rawHeaders): array
    {
        $mapping = [];
        $normalizedHeaders = [];
        $assignedCols = [];

        foreach ($rawHeaders as $idx => $headerName) {
            $normalizedHeaders[$idx] = $this->normalizeHeader((string) $headerName);
        }

        // Pass 1: Match primary alias (exact canonical match)
        foreach (self::COLUMN_ALIASES as $key => $aliases) {
            $primary = str_replace([' ', '-', '_'], '', $aliases[0]);
            foreach ($normalizedHeaders as $colIdx => $normHeader) {
                if (in_array($colIdx, $assignedCols, true)) continue;
                $strippedHeader = str_replace([' ', '-', '_'], '', $normHeader);
                if ($strippedHeader === $primary) {
                    $mapping[$key] = $colIdx;
                    $assignedCols[] = $colIdx;
                    break;
                }
            }
        }

        // Pass 2: Match remaining aliases
        foreach (self::COLUMN_ALIASES as $key => $aliases) {
            if (isset($mapping[$key])) continue;
            foreach ($normalizedHeaders as $colIdx => $normHeader) {
                if (in_array($colIdx, $assignedCols, true)) continue;
                $strippedHeader = str_replace([' ', '-', '_'], '', $normHeader);
                foreach ($aliases as $alias) {
                    $strippedAlias = str_replace([' ', '-', '_'], '', $alias);
                    if ($normHeader === $alias || $strippedHeader === $strippedAlias) {
                        $mapping[$key] = $colIdx;
                        $assignedCols[] = $colIdx;
                        break 2;
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Validate structure of uploaded Excel/CSV file and generate preview rows.
     * Engineered to support huge files (ratusan ribu baris) with low-memory streaming.
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
            File::makeDirectory($tempDir, 0777, true, true);
        }
        @chmod($tempDir, 0777);

        $token = 'import_' . Str::random(24) . '.' . $extension;
        $tempPath = $tempDir . DIRECTORY_SEPARATOR . $token;
        $file->move($tempDir, $token);

        try {
            @set_time_limit(600);
            @ini_set('memory_limit', '1024M');

            $rowIterator = $this->streamRows($tempPath);
            $headerRow = null;
            $headerRowNum = 1;
            $columnMap = [];

            // Detect header row within first 10 rows
            foreach ($rowIterator as $rowNum => $rowCells) {
                if ($rowNum > 10) break;

                $hasContent = false;
                foreach ($rowCells as $cell) {
                    if (trim((string) $cell) !== '') {
                        $hasContent = true;
                        break;
                    }
                }
                if (!$hasContent) continue;

                $map = $this->resolveColumnMapping($rowCells);
                $hasIdentifier = isset($map['sku']) || isset($map['kode_produk']) || isset($map['kode_barcode']) || isset($map['id_produk']);
                $hasName = isset($map['nama_produk']);

                if (($hasIdentifier && $hasName) || count($map) >= 3) {
                    $headerRow = $rowCells;
                    $headerRowNum = $rowNum;
                    $columnMap = $map;
                    break;
                }
            }

            if (!$headerRow) {
                // Fallback: try row 1
                $rowIterator = $this->streamRows($tempPath);
                foreach ($rowIterator as $rowNum => $rowCells) {
                    $headerRow = $rowCells;
                    $headerRowNum = $rowNum;
                    $columnMap = $this->resolveColumnMapping($rowCells);
                    break;
                }
            }

            if (!$headerRow) {
                if (file_exists($tempPath)) @unlink($tempPath);
                return [
                    'valid' => false,
                    'message' => 'File kosong atau tidak memiliki baris judul (header).',
                ];
            }

            // Fallback aliasing for sku if missing but other identifier exists
            if (!isset($columnMap['sku'])) {
                if (isset($columnMap['kode_produk'])) {
                    $columnMap['sku'] = $columnMap['kode_produk'];
                } elseif (isset($columnMap['kode_barcode'])) {
                    $columnMap['sku'] = $columnMap['kode_barcode'];
                } elseif (isset($columnMap['id_produk'])) {
                    $columnMap['sku'] = $columnMap['id_produk'];
                }
            }

            // Check essential core columns: Must have SKU/Kode Barang and Nama Barang
            $missingCore = [];
            if (!isset($columnMap['sku']) && !isset($columnMap['kode_produk']) && !isset($columnMap['kode_barcode']) && !isset($columnMap['id_produk'])) {
                $missingCore[] = 'SKU / Kode Barang / Barcode';
            }
            if (!isset($columnMap['nama_produk'])) {
                $missingCore[] = 'Nama Produk / Nama Barang';
            }

            if (!empty($missingCore)) {
                if (file_exists($tempPath)) @unlink($tempPath);
                return [
                    'valid' => false,
                    'message' => 'Format kolom Excel tidak sesuai standar Master Produk. Kolom wajib yang belum ditemukan: ' . implode(', ', $missingCore) . '. Pastikan file memiliki kolom SKU / Kode Barang dan Nama Produk.',
                    'expected_count' => count(self::EXPECTED_COLUMNS),
                    'actual_count' => count(array_filter($headerRow, fn($v) => trim((string)$v) !== '')),
                    'missing_columns' => $missingCore,
                    'expected_headers' => self::EXPECTED_COLUMNS,
                    'actual_headers' => array_values(array_filter($headerRow, fn($v) => trim((string)$v) !== '')),
                ];
            }

            // Stream through rows: Count total rows and gather all preview rows
            $totalRows = 0;
            $previewRows = [];
            $allSkus = [];
            $errorRowsList = [];

            foreach ($rowIterator as $rowNum => $cells) {
                if ($rowNum <= $headerRowNum) continue;

                $rowEmpty = true;
                foreach ($cells as $c) {
                    if (trim((string)$c) !== '') {
                        $rowEmpty = false;
                        break;
                    }
                }
                if ($rowEmpty) continue;

                $totalRows++;

                $rawSku = trim((string)($cells[$columnMap['sku'] ?? -1] ?? ''));
                $rawKodeProduk = trim((string)($cells[$columnMap['kode_produk'] ?? -1] ?? ''));
                $rawBarcode = trim((string)($cells[$columnMap['kode_barcode'] ?? -1] ?? ''));
                $rawIdProduk = trim((string)($cells[$columnMap['id_produk'] ?? -1] ?? ''));

                // Smart identifier resolution: SKU -> KodeProduk -> Kode Barcode -> IdProduk
                $sku = $rawSku ?: ($rawKodeProduk ?: ($rawBarcode ?: $rawIdProduk));
                $kodeProduk = $rawKodeProduk ?: $sku;
                $barcode = $rawBarcode ?: ($kodeProduk ?: $sku);
                $idProduk = $rawIdProduk ?: ($sku ?: null);
                $namaProduk = trim((string)($cells[$columnMap['nama_produk'] ?? -1] ?? ''));
                $qtyProduk = $this->parseNumeric($cells[$columnMap['qty_produk'] ?? -1] ?? 0);
                $satuanUnit = trim((string)($cells[$columnMap['satuan_unit'] ?? -1] ?? 'Pcs')) ?: 'Pcs';
                $hargaBeli = $this->parseNumeric($cells[$columnMap['harga_beli'] ?? -1] ?? 0);
                $hargaJualUmum = $this->parseNumeric($cells[$columnMap['harga_jual_umum'] ?? -1] ?? 0);
                $hargaJualMember = $this->parseNumeric($cells[$columnMap['harga_jual_member'] ?? -1] ?? $hargaJualUmum);
                $produkDisc = $this->parseNumeric($cells[$columnMap['produk_disc'] ?? -1] ?? 0);
                $minimumStock = (int) $this->parseNumeric($cells[$columnMap['minimum_stock'] ?? -1] ?? 5);
                $ppnOut = $this->parseNumeric($cells[$columnMap['ppn_out'] ?? -1] ?? 11);
                $ppnChk = strtoupper(trim((string)($cells[$columnMap['ppn_chk'] ?? -1] ?? 'Y'))) ?: 'Y';
                $hargaBeliTerakhir = $this->parseNumeric($cells[$columnMap['harga_beli_terakhir'] ?? -1] ?? $hargaBeli);
                $statusBarang = strtoupper(trim((string)($cells[$columnMap['status_barang'] ?? -1] ?? 'TERSEDIA'))) ?: 'TERSEDIA';
                $kodeSuplier = trim((string)($cells[$columnMap['kode_suplier'] ?? -1] ?? ''));
                $manufacturingDate = $this->parseDate($cells[$columnMap['manufacturing_date'] ?? -1] ?? null);
                $expDate = $this->parseDate($cells[$columnMap['exp_date'] ?? -1] ?? null);

                $rowErrors = [];
                if ($sku === '' || $sku === '-') $rowErrors[] = 'SKU / KodeProduk wajib diisi';
                if ($namaProduk === '' || $namaProduk === '-') $rowErrors[] = 'NamaProduk wajib diisi';

                $hasError = !empty($rowErrors);
                $errorMsg = $hasError ? implode(', ', $rowErrors) : null;

                if ($hasError) {
                    $errorRowsList[] = [
                        'row' => $rowNum,
                        'sku' => $sku ?: '-',
                        'nama_produk' => $namaProduk ?: '-',
                        'name' => $namaProduk ?: '-',
                        'messages' => $rowErrors,
                        'error' => $errorMsg,
                    ];
                }

                if ($sku !== '' && $sku !== '-') {
                    $allSkus[$sku] = true;
                }

                $previewRows[] = [
                    'row' => $rowNum,
                    'id_produk' => $idProduk ?: '-',
                    'sku' => $sku ?: '-',
                    'kode_barcode' => $barcode ?: '-',
                    'kode_produk' => $kodeProduk ?: '-',
                    'qty_produk' => $qtyProduk,
                    'nama_produk' => $namaProduk ?: '-',
                    'satuan_unit' => $satuanUnit,
                    'harga_beli' => $hargaBeli,
                    'harga_jual_umum' => $hargaJualUmum,
                    'harga_jual_member' => $hargaJualMember,
                    'produk_disc' => $produkDisc,
                    'minimum_stock' => $minimumStock,
                    'ppn_out' => $ppnOut,
                    'ppn_chk' => $ppnChk,
                    'harga_beli_terakhir' => $hargaBeliTerakhir,
                    'status_barang' => $statusBarang,
                    'kode_suplier' => $kodeSuplier ?: '-',
                    'manufacturing_date' => $manufacturingDate ? $manufacturingDate->format('Y-m-d') : '-',
                    'exp_date' => $expDate ? $expDate->format('Y-m-d') : '-',
                    'name' => $namaProduk ?: '-',
                    'barcode' => $barcode ?: '-',
                    'cost_price' => $hargaBeli,
                    'price' => $hargaJualUmum,
                    'member_price' => $hargaJualMember,
                    'unit' => $satuanUnit,
                    'stock' => $qtyProduk,
                    'stock_min' => $minimumStock,
                    'has_error' => $hasError,
                    'error_message' => $errorMsg,
                    'is_existing' => false,
                ];
            }

            // Check existing products in DB in chunks of 1000
            $existingSkuSet = [];
            $uniqueSkus = array_keys($allSkus);
            if (!empty($uniqueSkus)) {
                foreach (array_chunk($uniqueSkus, 1000) as $chunk) {
                    $found = Product::whereIn('sku', $chunk)
                        ->orWhereIn('barcode', $chunk)
                        ->orWhereIn('kode_barcode', $chunk)
                        ->orWhereIn('kode_produk', $chunk)
                        ->get(['sku', 'barcode', 'kode_barcode', 'kode_produk']);

                    foreach ($found as $item) {
                        if ($item->sku) $existingSkuSet[strtoupper(trim($item->sku))] = true;
                        if ($item->barcode) $existingSkuSet[strtoupper(trim($item->barcode))] = true;
                        if ($item->kode_barcode) $existingSkuSet[strtoupper(trim($item->kode_barcode))] = true;
                        if ($item->kode_produk) $existingSkuSet[strtoupper(trim($item->kode_produk))] = true;
                    }
                }
            }

            $existingRowsList = [];
            foreach ($previewRows as &$pRow) {
                $skuUpper = strtoupper(trim((string)$pRow['sku']));
                $barcodeUpper = strtoupper(trim((string)$pRow['kode_barcode']));
                $kodeUpper = strtoupper(trim((string)$pRow['kode_produk']));

                $isExisting = ($skuUpper !== '-' && isset($existingSkuSet[$skuUpper]))
                    || ($barcodeUpper !== '-' && isset($existingSkuSet[$barcodeUpper]))
                    || ($kodeUpper !== '-' && isset($existingSkuSet[$kodeUpper]));

                $pRow['is_existing'] = $isExisting;

                if ($isExisting) {
                    $existingRowsList[] = [
                        'row' => $pRow['row'],
                        'sku' => $pRow['sku'],
                        'nama_produk' => $pRow['nama_produk'],
                    ];
                }
            }
            unset($pRow);

            $existingInDbCount = count($existingRowsList);
            $errorCount = count($errorRowsList);
            $validRows = max(0, $totalRows - $errorCount);

            return [
                'valid' => true,
                'token' => $token,
                'file_name' => $file->getClientOriginalName(),
                'summary' => [
                    'total_rows' => $totalRows,
                    'valid_rows' => $validRows,
                    'error_rows' => $errorCount,
                    'existing_in_db_count' => $existingInDbCount,
                    'is_high_volume' => $totalRows > 1000,
                ],
                'expected_headers' => self::EXPECTED_COLUMNS,
                'preview_rows' => $previewRows,
                'error_rows_list' => $errorRowsList,
                'existing_rows_list' => $existingRowsList,
                'errors' => $errorRowsList,
            ];

        } catch (\Throwable $e) {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }

            return [
                'valid' => false,
                'message' => 'Gagal membaca file: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Process the confirmed Excel import in high-performance streaming chunks.
     * Handles hundreds of thousands (ratusan ribu) records without memory exhaustion.
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
            @set_time_limit(1800); // 30 minutes for huge files
            @ini_set('memory_limit', '1536M');

            $rowIterator = $this->streamRows($tempPath);
            $headerRow = null;
            $headerRowNum = 1;
            $columnMap = [];

            // Detect header row within first 10 rows
            foreach ($rowIterator as $rowNum => $rowCells) {
                if ($rowNum > 10) break;
                $hasContent = false;
                foreach ($rowCells as $cell) {
                    if (trim((string) $cell) !== '') {
                        $hasContent = true;
                        break;
                    }
                }
                if (!$hasContent) continue;

                $map = $this->resolveColumnMapping($rowCells);
                $hasIdentifier = isset($map['sku']) || isset($map['kode_produk']) || isset($map['kode_barcode']) || isset($map['id_produk']);
                $hasName = isset($map['nama_produk']);

                if (($hasIdentifier && $hasName) || count($map) >= 3) {
                    $headerRow = $rowCells;
                    $headerRowNum = $rowNum;
                    $columnMap = $map;
                    break;
                }
            }

            if (!$headerRow) {
                $rowIterator = $this->streamRows($tempPath);
                foreach ($rowIterator as $rowNum => $rowCells) {
                    $headerRow = $rowCells;
                    $headerRowNum = $rowNum;
                    $columnMap = $this->resolveColumnMapping($rowCells);
                    break;
                }
            }

            if (!$headerRow) {
                if (file_exists($tempPath)) @unlink($tempPath);
                return [
                    'success' => false,
                    'message' => 'File import tidak memiliki header yang valid.',
                ];
            }

            if (!isset($columnMap['sku'])) {
                if (isset($columnMap['kode_produk'])) {
                    $columnMap['sku'] = $columnMap['kode_produk'];
                } elseif (isset($columnMap['kode_barcode'])) {
                    $columnMap['sku'] = $columnMap['kode_barcode'];
                } elseif (isset($columnMap['id_produk'])) {
                    $columnMap['sku'] = $columnMap['id_produk'];
                }
            }

            $warehouseLoc = Location::where('type', 'WAREHOUSE')->first()
                ?? Location::where('code', 'GDG-001')->first()
                ?? Location::where('type', 'STORE')->first()
                ?? Location::first();
            $warehouseLocId = $warehouseLoc?->id;

            $totalProcessed = 0;
            $chunkSize = 1000;
            $batchProducts = [];
            $now = now();

            foreach ($rowIterator as $rowNum => $cells) {
                if ($rowNum <= $headerRowNum) continue;

                $rowEmpty = true;
                foreach ($cells as $c) {
                    if (trim((string)$c) !== '') {
                        $rowEmpty = false;
                        break;
                    }
                }
                if ($rowEmpty) continue;

                $rawSku = trim((string)($cells[$columnMap['sku'] ?? -1] ?? ''));
                $rawKodeProduk = trim((string)($cells[$columnMap['kode_produk'] ?? -1] ?? ''));
                $rawBarcode = trim((string)($cells[$columnMap['kode_barcode'] ?? -1] ?? ''));
                $rawIdProduk = trim((string)($cells[$columnMap['id_produk'] ?? -1] ?? ''));

                // Smart identifier resolution: SKU -> KodeProduk -> Kode Barcode -> IdProduk
                $sku = $rawSku ?: ($rawKodeProduk ?: ($rawBarcode ?: $rawIdProduk));
                if ($sku === '') continue;

                $kodeProduk = $rawKodeProduk ?: $sku;
                $barcode = $rawBarcode ?: ($kodeProduk ?: $sku);
                $idProduk = $rawIdProduk ?: ($sku ?: null);
                $namaProduk = trim((string)($cells[$columnMap['nama_produk'] ?? -1] ?? '')) ?: 'Produk ' . $sku;
                $qtyProduk = $this->parseNumeric($cells[$columnMap['qty_produk'] ?? -1] ?? 0);
                $satuanUnit = trim((string)($cells[$columnMap['satuan_unit'] ?? -1] ?? 'Pcs')) ?: 'Pcs';
                $hargaBeli = $this->parseNumeric($cells[$columnMap['harga_beli'] ?? -1] ?? 0);
                $hargaJualUmum = $this->parseNumeric($cells[$columnMap['harga_jual_umum'] ?? -1] ?? 0);
                $hargaJualMember = $this->parseNumeric($cells[$columnMap['harga_jual_member'] ?? -1] ?? $hargaJualUmum);
                $produkDisc = $this->parseNumeric($cells[$columnMap['produk_disc'] ?? -1] ?? 0);
                $minimumStock = (int) $this->parseNumeric($cells[$columnMap['minimum_stock'] ?? -1] ?? 5);
                $ppnOut = $this->parseNumeric($cells[$columnMap['ppn_out'] ?? -1] ?? 11);
                $ppnChk = strtoupper(trim((string)($cells[$columnMap['ppn_chk'] ?? -1] ?? 'Y'))) ?: 'Y';
                $isTaxable = in_array($ppnChk, ['Y', '1', 'TRUE']);
                $hargaBeliTerakhir = $this->parseNumeric($cells[$columnMap['harga_beli_terakhir'] ?? -1] ?? $hargaBeli);
                $statusBarang = strtoupper(trim((string)($cells[$columnMap['status_barang'] ?? -1] ?? 'TERSEDIA'))) ?: 'TERSEDIA';
                $kodeSuplier = trim((string)($cells[$columnMap['kode_suplier'] ?? -1] ?? '')) ?: null;
                $manufacturingDate = $this->parseDate($cells[$columnMap['manufacturing_date'] ?? -1] ?? null);
                $expDate = $this->parseDate($cells[$columnMap['exp_date'] ?? -1] ?? null);

                // Build upsert payload with BOTH 19 standard columns AND legacy columns
                $batchProducts[$sku] = [
                    'sku' => $sku,
                    'id_produk' => $idProduk,
                    'kode_barcode' => $barcode,
                    'kode_produk' => $kodeProduk,
                    'qty_produk' => $qtyProduk,
                    'nama_produk' => $namaProduk,
                    'satuan_unit' => $satuanUnit,
                    'harga_beli' => $hargaBeli,
                    'harga_jual_umum' => $hargaJualUmum,
                    'harga_jual_member' => $hargaJualMember,
                    'produk_disc' => $produkDisc,
                    'minimum_stock' => $minimumStock,
                    'ppn_out' => $ppnOut,
                    'ppn_chk' => $ppnChk,
                    'harga_beli_terakhir' => $hargaBeliTerakhir,
                    'status_barang' => $statusBarang,
                    'kode_suplier' => $kodeSuplier,
                    'manufacturing_date' => $manufacturingDate ? $manufacturingDate->format('Y-m-d') : null,
                    'exp_date' => $expDate ? $expDate->format('Y-m-d') : null,

                    // Legacy columns synced with length/type safety
                    'ilp_id' => $idProduk ? substr($idProduk, 0, 50) : null,
                    'barcode' => substr($barcode, 0, 255),
                    'name' => substr($namaProduk, 0, 255),
                    'unit' => substr($satuanUnit, 0, 50),
                    'cost_price' => $hargaBeli,
                    'price' => $hargaJualUmum,
                    'member_price' => $hargaJualMember,
                    'clearance_discount_percent' => $produkDisc,
                    'min_stock' => $minimumStock,
                    'tax_rate' => $ppnOut,
                    'is_taxable' => $isTaxable,
                    'last_cost_price' => $hargaBeliTerakhir,
                    'status' => substr($statusBarang, 0, 50),
                    'ko_sup' => $kodeSuplier ? substr($kodeSuplier, 0, 50) : null,
                    'expired_date' => $expDate ? $expDate->format('Y-m-d') : null,
                    'stock' => (int) $qtyProduk,
                    'updated_at' => $now,
                    'created_at' => $now,
                ];

                $totalProcessed++;

                if (count($batchProducts) >= $chunkSize) {
                    $this->flushUpsertBatch($batchProducts, $duplicateAction, $warehouseLocId);
                    $batchProducts = [];
                    gc_collect_cycles();
                }
            }

            // Flush remaining records
            if (!empty($batchProducts)) {
                $this->flushUpsertBatch($batchProducts, $duplicateAction, $warehouseLocId);
                $batchProducts = [];
            }

            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }

            // Clear catalog and KPI caches
            Cache::forget('products_catalog');
            Cache::forget('products_kpi_summary');
            Cache::forget('products_kpi_summary_' . today()->toDateString());
            Cache::forget('products_categories_distinct');

            return [
                'success' => true,
                'message' => "Import master data barang berhasil diselesaikan! Sebanyak {$totalProcessed} produk berhasil diproses ke database.",
                'total_rows' => $totalProcessed,
                'created_count' => $totalProcessed,
                'updated_count' => $totalProcessed,
                'skipped_count' => 0,
                'failed_count' => 0,
                'summary' => [
                    'total_processed' => $totalProcessed,
                    'created' => $totalProcessed,
                    'updated' => $totalProcessed,
                    'skipped' => 0,
                ],
            ];

        } catch (\Throwable $e) {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }

            return [
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses import: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Bulk upsert a batch of product records into database with PostgreSQL native ON CONFLICT.
     */
    protected function flushUpsertBatch(array $batchProducts, string $duplicateAction, ?int $warehouseLocId): void
    {
        if (empty($batchProducts)) return;

        $records = array_values($batchProducts);

        $updateColumns = [
            'id_produk',
            'kode_barcode',
            'kode_produk',
            'qty_produk',
            'nama_produk',
            'satuan_unit',
            'harga_beli',
            'harga_jual_umum',
            'harga_jual_member',
            'produk_disc',
            'minimum_stock',
            'ppn_out',
            'ppn_chk',
            'harga_beli_terakhir',
            'status_barang',
            'kode_suplier',
            'manufacturing_date',
            'exp_date',
            'ilp_id',
            'barcode',
            'name',
            'unit',
            'cost_price',
            'price',
            'member_price',
            'clearance_discount_percent',
            'min_stock',
            'tax_rate',
            'is_taxable',
            'last_cost_price',
            'status',
            'ko_sup',
            'expired_date',
            'stock',
            'updated_at',
        ];

        DB::transaction(function () use ($records, $duplicateAction, $updateColumns, $warehouseLocId) {
            if ($duplicateAction === 'skip') {
                DB::table('products')->upsert($records, ['sku'], []);
            } else {
                DB::table('products')->upsert($records, ['sku'], $updateColumns);
            }

            // Sync product stocks across Gudang Pusat and retail store branches
            $skus = array_column($records, 'sku');
            $skuToId = DB::table('products')->whereIn('sku', $skus)->pluck('id', 'sku');

            $allLocations = DB::table('locations')->where('is_active', true)->get(['id', 'code', 'type']);
            $warehouseLoc = $allLocations->firstWhere('code', 'GDG-001') ?? $allLocations->firstWhere('type', 'WAREHOUSE');
            $mainStoreLoc = $allLocations->firstWhere('code', 'CAB-001');

            $stockBatch = [];
            $now = now();
            foreach ($records as $r) {
                $pId = $skuToId[$r['sku']] ?? null;
                if (!$pId) continue;

                $qty = (int) ($r['qty_produk'] ?? 0);
                $minStock = (int) ($r['minimum_stock'] ?? 5);

                // 1. Gudang Pusat (Warehouse / Master Stok Fisik Gudang)
                if ($warehouseLoc) {
                    $stockBatch[] = [
                        'product_id' => $pId,
                        'location_id' => $warehouseLoc->id,
                        'stock' => $qty,
                        'min_stock' => $minStock,
                        'is_active' => true,
                        'version' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // 2. Retail store branches (Toko Pusat, Cabang A, Cabang B):
                // Sesuai SOP sistem, stok hasil import master produk murni masuk ke Gudang Pusat (GDG-001).
                // Stok TIDAK boleh otomatis masuk ke cabang toko manapun. Manager harus mengirimkan barang
                // terlebih dahulu (Kirim Barang), dan kasir di cabang tujuan melakukan Approve (Goods In)
                // agar stok toko di cabang tersebut resmi bertambah.
                foreach ($allLocations as $loc) {
                    if ($warehouseLoc && $loc->id === $warehouseLoc->id) {
                        continue;
                    }
                    $stockBatch[] = [
                        'product_id' => $pId,
                        'location_id' => $loc->id,
                        'stock' => 0,
                        'min_stock' => $minStock,
                        'is_active' => true,
                        'version' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (!empty($stockBatch)) {
                DB::table('product_stocks')->upsert(
                    $stockBatch,
                    ['product_id', 'location_id'],
                    ['stock', 'min_stock', 'is_active', 'updated_at']
                );
            }
        });
    }

    /**
     * Stream rows from CSV or XLSX with low memory footprint.
     */
    public function streamRows(string $filePath): \Generator
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            yield from $this->streamCsv($filePath);
            return;
        }

        if ($extension === 'xlsx') {
            $yielded = false;
            try {
                foreach ($this->streamXlsx($filePath) as $rowNum => $row) {
                    $yielded = true;
                    yield $rowNum => $row;
                }
            } catch (\Throwable $e) {
                $yielded = false;
            }

            if ($yielded) {
                return;
            }
        }

        // Fallback for XLS or complex XLSX or when streamXlsx yielded no rows
        yield from $this->streamPhpSpreadsheet($filePath);
    }

    /**
     * Stream CSV rows via native PHP fgetcsv.
     */
    protected function streamCsv(string $filePath): \Generator
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception("Gagal membuka file CSV: {$filePath}");
        }

        $firstLine = fgets($handle);
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
        rewind($handle);

        $rowNum = 1;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            yield $rowNum => $row;
            $rowNum++;
        }

        fclose($handle);
    }

    /**
     * Stream XLSX rows via native ZipArchive + XMLReader for extreme scale and speed.
     */
    protected function streamXlsx(string $filePath): \Generator
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \Exception("Cannot open zip archive: {$filePath}");
        }

        // 1. Read shared strings if exists (case-insensitive)
        $sharedStrings = [];
        $ssIndex = $zip->locateName('xl/sharedStrings.xml', ZipArchive::FL_NOCASE);
        if ($ssIndex !== false) {
            $ssXml = $zip->getFromIndex($ssIndex);
            if ($ssXml) {
                preg_match_all('#<si>(.*?)</si>#s', $ssXml, $siMatches);
                foreach ($siMatches[1] as $si) {
                    if (preg_match_all('#<t(?:[^>]*)>(.*?)</t>#s', $si, $tMatches)) {
                        $sharedStrings[] = html_entity_decode(implode('', $tMatches[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                    } else {
                        $sharedStrings[] = '';
                    }
                }
                unset($ssXml, $siMatches);
            }
        }

        // 2. Locate worksheet
        $sheetIndex = $zip->locateName('xl/worksheets/sheet1.xml', ZipArchive::FL_NOCASE);
        if ($sheetIndex === false) {
            $sheetIndex = $zip->locateName('sheet1.xml', ZipArchive::FL_NODIR);
        }
        if ($sheetIndex === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (preg_match('#^xl/worksheets/sheet\d+\.xml$#i', $name)) {
                    $sheetIndex = $i;
                    break;
                }
            }
        }
        if ($sheetIndex === false) {
            $zip->close();
            throw new \Exception("Cannot find worksheet in xlsx");
        }

        $stream = $zip->getStream($zip->getNameIndex($sheetIndex));
        $tempSheet = tempnam(sys_get_temp_dir(), 'sht');
        file_put_contents($tempSheet, stream_get_contents($stream));
        fclose($stream);
        $zip->close();

        $reader = new XMLReader();
        $reader->open($tempSheet);

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'row') {
                $rowOuter = $reader->readOuterXML();
                $rowNum = 0;
                if (preg_match('/<row[^>]+r="(\d+)"/', $rowOuter, $rm)) {
                    $rowNum = (int)$rm[1];
                }
                $rowCells = [];

                preg_match_all('/<c([^>]*)>(.*?)<\/c>|<c([^>]*)\/>/s', $rowOuter, $allCells, PREG_SET_ORDER);
                foreach ($allCells as $cMatch) {
                    $attr = $cMatch[1] ?: $cMatch[3];
                    $inner = $cMatch[2] ?? '';

                    if (!preg_match('/r="([A-Z]+)(\d+)"/', $attr, $refM)) {
                        continue;
                    }
                    $colLetters = $refM[1];
                    $colIdx = 0;
                    $len = strlen($colLetters);
                    for ($i = 0; $i < $len; $i++) {
                        $colIdx = $colIdx * 26 + (ord($colLetters[$i]) - 64);
                    }
                    $colIdx -= 1; // 0-based index

                    $isString = preg_match('/t="s"/', $attr);
                    $isInline = preg_match('/t="inlineStr"/', $attr);
                    $val = '';

                    if (preg_match('/<v>(.*?)<\/v>/s', $inner, $vm)) {
                        $val = $vm[1];
                        if ($isString) {
                            $val = $sharedStrings[(int)$val] ?? '';
                        }
                    } elseif ($isInline && preg_match('/<t[^>]*>(.*?)<\/t>/s', $inner, $tm)) {
                        $val = html_entity_decode($tm[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                    }

                    $rowCells[$colIdx] = html_entity_decode((string)$val, ENT_QUOTES | ENT_XML1, 'UTF-8');
                }

                if (!empty($rowCells)) {
                    $maxCol = max(array_keys($rowCells));
                    $filled = [];
                    for ($i = 0; $i <= $maxCol; $i++) {
                        $filled[$i] = $rowCells[$i] ?? '';
                    }
                    yield $rowNum => $filled;
                }
            }
        }

        $reader->close();
        @unlink($tempSheet);
    }

    /**
     * Fallback stream parser using PhpSpreadsheet.
     */
    protected function streamPhpSpreadsheet(string $filePath): \Generator
    {
        $reader = IOFactory::createReaderForFile($filePath);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $highestCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            $cells = [];
            for ($col = 1; $col <= $highestCol; $col++) {
                $cells[] = (string) $sheet->getCell([$col, $row])->getValue();
            }
            yield $row => $cells;
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * Export products from database matching the exact 19 standard columns.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = Product::with(['supplier']);

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

        $products = $query->orderBy('name', 'asc')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Master Produk Gudang');

        // Professional Navy Blue Header
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
                'name' => 'Calibri',
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0052CC'],
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

        foreach (self::EXPECTED_COLUMNS as $idx => $colName) {
            $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
            $sheet->setCellValue("{$colLetter}1", $colName);
        }
        $sheet->getStyle('A1:' . Coordinate::stringFromColumnIndex(count(self::EXPECTED_COLUMNS)) . '1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $rowIdx = 2;
        foreach ($products as $p) {
            $sheet->setCellValue("A{$rowIdx}", $p->id_produk ?: ($p->ilp_id ?: $p->id));
            $sheet->setCellValueExplicit("B{$rowIdx}", (string) $p->sku, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$rowIdx}", (string) ($p->kode_barcode ?: ($p->barcode ?: $p->sku)), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("D{$rowIdx}", (string) ($p->kode_produk ?: $p->sku), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("E{$rowIdx}", (float) ($p->qty_produk ?? $p->stock ?? 0));
            $sheet->setCellValue("F{$rowIdx}", $p->nama_produk ?: $p->name);
            $sheet->setCellValue("G{$rowIdx}", $p->satuan_unit ?: ($p->unit ?: 'Pcs'));
            $sheet->setCellValue("H{$rowIdx}", (float) ($p->harga_beli ?? $p->cost_price ?? 0));
            $sheet->setCellValue("I{$rowIdx}", (float) ($p->harga_jual_umum ?? $p->price ?? 0));
            $sheet->setCellValue("J{$rowIdx}", (float) ($p->harga_jual_member ?? $p->member_price ?? $p->price ?? 0));
            $sheet->setCellValue("K{$rowIdx}", (float) ($p->produk_disc ?? $p->clearance_discount_percent ?? 0));
            $sheet->setCellValue("L{$rowIdx}", (int) ($p->minimum_stock ?? $p->min_stock ?? 5));
            $sheet->setCellValue("M{$rowIdx}", (float) ($p->ppn_out ?? $p->tax_rate ?? 11));
            $sheet->setCellValue("N{$rowIdx}", $p->ppn_chk ?: ($p->is_taxable ? 'Y' : 'N'));
            $sheet->setCellValue("O{$rowIdx}", (float) ($p->harga_beli_terakhir ?? $p->last_cost_price ?? $p->cost_price ?? 0));
            $sheet->setCellValue("P{$rowIdx}", $p->status_barang ?: ($p->status ?: 'TERSEDIA'));
            $sheet->setCellValue("Q{$rowIdx}", $p->kode_suplier ?: ($p->ko_sup ?: ($p->supplier?->code ?? '')));
            $sheet->setCellValue("R{$rowIdx}", $p->manufacturing_date ? Carbon::parse($p->manufacturing_date)->format('Y-m-d') : '');
            $sheet->setCellValue("S{$rowIdx}", $p->exp_date ? Carbon::parse($p->exp_date)->format('Y-m-d') : ($p->expired_date ? $p->expired_date->format('Y-m-d') : ''));

            $rowIdx++;
        }

        for ($col = 1; $col <= count(self::EXPECTED_COLUMNS); $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $filename = 'Export_Master_Barang_' . date('Ymd_His') . '.xlsx';

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
     * Download blank template with all 19 canonical columns and 2 sample rows.
     */
    public function generateTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Master Barang');

        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0052CC'],
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

        // 2 Sample Rows
        $sampleRows = [
            [
                'PRD-001',
                'SKU-MIA-01',
                '8992345678901',
                'SKU-MIA-01',
                150,
                'Minyak Goreng Sania 2L Pouch',
                'Pouch',
                32000,
                36500,
                35000,
                0,
                15,
                11,
                'Y',
                32000,
                'TERSEDIA',
                'SUP-001',
                date('Y-m-d', strtotime('-1 month')),
                date('Y-m-d', strtotime('+1 year')),
            ],
            [
                'PRD-002',
                'SKU-BKS-02',
                '8993456789012',
                'SKU-BKS-02',
                200,
                'Beras Ramos Setra Premium 5Kg',
                'Karung',
                68000,
                75000,
                73000,
                0,
                20,
                11,
                'Y',
                68000,
                'TERSEDIA',
                'SUP-002',
                date('Y-m-d', strtotime('-2 weeks')),
                date('Y-m-d', strtotime('+6 months')),
            ],
        ];

        foreach ($sampleRows as $rowOffset => $sampleRow) {
            $rowNum = $rowOffset + 2;
            foreach ($sampleRow as $idx => $val) {
                $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
                if (is_numeric($val) && !is_string($val)) {
                    $sheet->setCellValue("{$colLetter}{$rowNum}", $val);
                } else {
                    $sheet->setCellValueExplicit("{$colLetter}{$rowNum}", (string)$val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
        }

        for ($col = 1; $col <= count(self::EXPECTED_COLUMNS); $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $filename = 'Template_Master_Barang_19Kolom.xlsx';

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
     * Parse numeric values safely.
     */
    protected function parseNumeric(mixed $val, float $default = 0): float
    {
        if ($val === null || $val === '') {
            return $default;
        }

        if (is_numeric($val)) {
            return (float) $val;
        }

        $clean = preg_replace('/[^0-9.,-]/', '', (string) $val);

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

        return is_numeric($clean) ? (float) $clean : $default;
    }

    /**
     * Parse date values safely from Excel serial numbers or date strings.
     */
    protected function parseDate(mixed $val): ?Carbon
    {
        if ($val === null || trim((string)$val) === '' || trim((string)$val) === '-') {
            return null;
        }

        if (is_numeric($val) && (float)$val > 1000) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float)$val));
            } catch (\Throwable $e) {
                // fall through
            }
        }

        $str = trim((string) $val);

        $formats = [
            'Y-m-d',
            'd/m/Y',
            'd-m-Y',
            'Y/m/d',
            'd.m.Y',
            'Y.m.d',
            'd M Y',
            'd F Y',
        ];

        foreach ($formats as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $str);
            } catch (\Throwable $e) {
                continue;
            }
        }

        try {
            return Carbon::parse($str);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
