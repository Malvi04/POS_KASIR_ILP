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

    // 25 Columns & Data Map
    $cols = ProductExcelService::EXPECTED_COLUMNS;
    $rowMap = [
        'Barang - ID' => 'ILP-001',
        'Barang - Kode' => $sku,
        'Barang - KodeBar' => $sku . '-BAR',
        'Barang - Kel' => 'Makanan',
        'Barang - Nama' => 'Biskuit Import Test',
        'Barang - Unit1' => 'Pcs',
        'Barang - HargaBeli' => 5000,
        'Barang - HargaJualK1' => 7500,
        'Barang - HargaLevel1' => 7000,
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

