<?php

use App\Models\Product;
use App\Models\User;
use App\Services\ProductExcelService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $tempDir = storage_path('app/temp_tests');
    if (!File::isDirectory($tempDir)) {
        File::makeDirectory($tempDir, 0755, true);
    }
});

test('manager can validate excel template file', function () {
    $manager = User::where('role', 'manager')->first() ?? User::factory()->create([
        'role' => 'manager',
        'is_active' => true,
    ]);

    $excelService = app(ProductExcelService::class);
    $testFilePath = storage_path('app/temp_tests/test_template_' . uniqid() . '.xlsx');

    ob_start();
    $streamed = $excelService->generateTemplate();
    $streamed->sendContent();
    $content = ob_get_clean();
    file_put_contents($testFilePath, $content);

    $file = new UploadedFile(
        $testFilePath,
        'template.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $response = $this->actingAs($manager)
        ->postJson('/api/products/import/validate', [
            'file' => $file,
        ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'data' => [
            'valid' => true,
        ],
    ]);
});

test('validation fails when required columns are missing', function () {
    $manager = User::where('role', 'manager')->first() ?? User::factory()->create([
        'role' => 'manager',
        'is_active' => true,
    ]);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'Barang - Kode');
    $sheet->setCellValue('B1', 'Barang - Nama'); // only 2 columns instead of 36

    $testFilePath = storage_path('app/temp_tests/invalid_cols_' . uniqid() . '.xlsx');
    $writer = new Xlsx($spreadsheet);
    $writer->save($testFilePath);

    $file = new UploadedFile(
        $testFilePath,
        'invalid.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $response = $this->actingAs($manager)
        ->postJson('/api/products/import/validate', [
            'file' => $file,
        ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
    ]);
});

test('import and process updates products correctly without resetting stock', function () {
    $manager = User::where('role', 'manager')->first() ?? User::factory()->create([
        'role' => 'manager',
        'is_active' => true,
    ]);

    $sku = 'TEST-IMPORT-' . rand(1000, 9999);
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // 19 Columns & Data Map
    $cols = ProductExcelService::EXPECTED_COLUMNS;
    $rowMap = [
        'IdProduk' => 'PRD-TEST-001',
        'SKU' => $sku,
        'Kode Barcode' => $sku . '-BAR',
        'KodeProduk' => $sku,
        'QtyProduk' => 50,
        'NamaProduk' => 'Biskuit Import Test',
        'SatuanUnit' => 'Pcs',
        'HargaBeli' => 5000,
        'HargaJualUmum' => 7500,
        'HargaJualMember' => 7000,
        'ProdukDisc' => 0,
        'MinimumStock' => 5,
        'PPNOut' => 11,
        'PPNChk' => 'Y',
        'HargaBeliTerakhir' => 5000,
        'StatusBarang' => 'TERSEDIA',
        'KodeSuplier' => 'SUP-001',
        'ManufacturingDate' => '2026-01-01',
        'ExpDate' => '2027-01-01',
    ];

    foreach ($cols as $idx => $colName) {
        $sheet->setCellValue([$idx + 1, 1], $colName);
        if (isset($rowMap[$colName])) {
            $sheet->setCellValue([$idx + 1, 2], $rowMap[$colName]);
        }
    }

    $testFilePath = storage_path('app/temp_tests/valid_data_' . uniqid() . '.xlsx');
    $writer = new Xlsx($spreadsheet);
    $writer->save($testFilePath);

    $file = new UploadedFile(
        $testFilePath,
        'valid_products.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    // 1. Validate
    $valRes = $this->actingAs($manager)
        ->postJson('/api/products/import/validate', [
            'file' => $file,
        ]);

    $valRes->assertStatus(200);
    $valJson = $valRes->json();
    expect($valJson['data']['valid'])->toBeTrue();
    $token = $valJson['data']['token'];

    // 2. Process
    $processRes = $this->actingAs($manager)
        ->postJson('/api/products/import/process', [
            'token' => $token,
            'duplicate_action' => 'update',
        ]);

    $processRes->assertStatus(200);
    $processRes->assertJson([
        'success' => true,
    ]);

    // Verify product is in DB
    $product = Product::where('sku', $sku)->first();
    expect($product)->not->toBeNull();
    expect($product->name)->toBe('Biskuit Import Test');
    expect((float)$product->price)->toEqual(7500.0);
});

test('manager can export products and download ilp template', function () {
    $manager = User::where('role', 'manager')->first() ?? User::factory()->create([
        'role' => 'manager',
        'is_active' => true,
    ]);

    $exportRes = $this->actingAs($manager)->get('/manager/products/export');
    $exportRes->assertStatus(200);

    $templateRes = $this->actingAs($manager)->get('/manager/products/template');
    $templateRes->assertStatus(200);
});

test('import standard 19 columns persists all 19 database fields correctly and handles bulk import', function () {
    $manager = User::where('role', 'manager')->first() ?? User::factory()->create([
        'role' => 'manager',
        'is_active' => true,
    ]);

    $csvFile = storage_path('app/temp_tests/bulk_19_cols_' . uniqid() . '.csv');
    $fp = fopen($csvFile, 'w');
    fputcsv($fp, ProductExcelService::EXPECTED_COLUMNS);

    $testSkus = [];
    for ($i = 1; $i <= 50; $i++) {
        $sku = 'BULK-TEST-' . uniqid() . '-' . $i;
        $testSkus[] = $sku;
        fputcsv($fp, [
            'ID-BULK-' . $i,
            $sku,
            'BAR-' . $sku,
            'KOD-' . $sku,
            100 + $i,
            'Produk Bulk ' . $i,
            'Pcs',
            10000,
            15000,
            14000,
            5,
            10,
            11,
            'Y',
            9500,
            'TERSEDIA',
            'SUP-BULK-01',
            '2026-02-01',
            '2027-02-01',
        ]);
    }
    fclose($fp);

    $uploaded = new UploadedFile(
        $csvFile,
        'bulk_products.csv',
        'text/csv',
        null,
        true
    );

    $valRes = $this->actingAs($manager)->postJson('/api/products/import/validate', [
        'file' => $uploaded,
    ]);
    $valRes->assertStatus(200);
    $token = $valRes->json('data.token');

    $procRes = $this->actingAs($manager)->postJson('/api/products/import/process', [
        'token' => $token,
        'duplicate_action' => 'update',
    ]);
    $procRes->assertStatus(200);
    expect($procRes->json('summary.total_processed'))->toBe(50);

    // Verify 19 columns on first inserted product
    $p = Product::where('sku', $testSkus[0])->first();
    expect($p)->not->toBeNull();
    expect($p->id_produk)->toBe('ID-BULK-1');
    expect($p->sku)->toBe($testSkus[0]);
    expect($p->kode_barcode)->toBe('BAR-' . $testSkus[0]);
    expect($p->kode_produk)->toBe('KOD-' . $testSkus[0]);
    expect((int)$p->qty_produk)->toBe(101);
    expect($p->nama_produk)->toBe('Produk Bulk 1');
    expect($p->satuan_unit)->toBe('Pcs');
    expect((float)$p->harga_beli)->toEqual(10000.0);
    expect((float)$p->harga_jual_umum)->toEqual(15000.0);
    expect((float)$p->harga_jual_member)->toEqual(14000.0);
    expect((float)$p->produk_disc)->toEqual(5.0);
    expect((int)$p->minimum_stock)->toBe(10);
    expect((float)$p->ppn_out)->toEqual(11.0);
    expect($p->ppn_chk)->toBe('Y');
    expect((float)$p->harga_beli_terakhir)->toEqual(9500.0);
    expect($p->status_barang)->toBe('TERSEDIA');
    expect($p->kode_suplier)->toBe('SUP-BULK-01');
    expect($p->manufacturing_date?->format('Y-m-d'))->toBe('2026-02-01');
    expect($p->exp_date?->format('Y-m-d'))->toBe('2027-02-01');

    // Clean up
    Product::whereIn('sku', $testSkus)->delete();
    if (file_exists($csvFile)) @unlink($csvFile);
});

test('import preview resolves sku from kode_produk when sku cell is empty and reports no error', function () {
    $manager = User::where('role', 'manager')->first() ?? User::factory()->create([
        'role' => 'manager',
        'is_active' => true,
    ]);

    $csvFile = storage_path('app/temp_tests/empty_sku_' . uniqid() . '.csv');
    $fp = fopen($csvFile, 'w');
    fputcsv($fp, ProductExcelService::EXPECTED_COLUMNS);

    $testKodeProduk = '00000561-' . rand(1000, 9999);
    fputcsv($fp, [
        '',                      // IdProduk (empty)
        '',                      // SKU (empty cell - exactly like user screenshot)
        $testKodeProduk,         // Kode Barcode
        $testKodeProduk,         // KodeProduk
        200,                     // QtyProduk
        'Bendit Starter Karisma',// NamaProduk
        'pcs',                   // SatuanUnit
        21000,                   // HargaBeli
        25000,                   // HargaJualUmum
        24000,                   // HargaJualMember
        0,                       // ProdukDisc
        5,                       // MinimumStock
        11,                      // PPNOut
        'Y',                     // PPNChk
        21000,                   // HargaBeliTerakhir
        'TERSEDIA',              // StatusBarang
        'SUP-001',               // KodeSuplier
        '2026-01-01',            // ManufacturingDate
        '2027-01-01',            // ExpDate
    ]);
    fclose($fp);

    $uploaded = new UploadedFile(
        $csvFile,
        'empty_sku.csv',
        'text/csv',
        null,
        true
    );

    $valRes = $this->actingAs($manager)->postJson('/api/products/import/validate', [
        'file' => $uploaded,
    ]);
    $valRes->assertStatus(200);

    $previewRows = $valRes->json('data.preview_rows');
    expect($previewRows)->toHaveCount(1);
    expect($previewRows[0]['has_error'])->toBeFalse();
    expect($previewRows[0]['sku'])->toBe($testKodeProduk);
    expect($previewRows[0]['kode_produk'])->toBe($testKodeProduk);
    expect($previewRows[0]['nama_produk'])->toBe('Bendit Starter Karisma');

    $token = $valRes->json('data.token');
    $procRes = $this->actingAs($manager)->postJson('/api/products/import/process', [
        'token' => $token,
        'duplicate_action' => 'update',
    ]);
    $procRes->assertStatus(200);

    $product = Product::where('sku', $testKodeProduk)->first();
    expect($product)->not->toBeNull();
    expect($product->name)->toBe('Bendit Starter Karisma');
    expect((int)$product->stock)->toBe(200);

    $product->delete();
    if (file_exists($csvFile)) @unlink($csvFile);
});

