<?php

use App\Models\CashierShift;
use App\Models\Location;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\MemberExcelService;
use App\Services\ProductExcelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'pos_sync.node_role' => 'central',
        'pos_sync.central_api_url' => 'https://central.pos-ilp.test',
        'pos_sync.central_api_token' => 'secret-central-sync-token-2026',
        'pos_sync.batch_size' => 50,
    ]);
});

test('models and excel imports automatically generate valid UUIDv7 while preserving integer primary keys and existing UUIDs on upsert', function () {
    // 1. Eloquent Model creation via HasUuidV7 trait
    $user = User::create([
        'name' => 'Kasir Test UUID',
        'username' => 'kasir_uuid',
        'email' => 'kasir_uuid@ilp.test',
        'nik' => 'NIK-UUID-001',
        'role' => 'Kasir',
        'pin' => '123456',
        'branch' => 'Pusat',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    expect($user->id)->toBeInt()
        ->and(Str::isUuid($user->uuid))->toBeTrue();

    $product = Product::create([
        'sku' => 'SKU-UUID-001',
        'barcode' => '8991234567890',
        'name' => 'Kopi Bubuk ILP',
        'price' => 15000,
        'cost_price' => 10000,
        'stock' => 100,
        'unit' => 'Pcs',
    ]);

    expect($product->id)->toBeInt()
        ->and(Str::isUuid($product->uuid))->toBeTrue();

    // 2. Bulk Product Excel Import (DB::table()->upsert) generates UUIDv7 and preserves existing UUID on update
    $originalProductUuid = $product->uuid;
    $tempDir = storage_path('app/temp_tests');
    if (!File::isDirectory($tempDir)) {
        File::makeDirectory($tempDir, 0777, true, true);
    }

    $csvFile = $tempDir . '/products_uuid_' . uniqid() . '.csv';
    $fp = fopen($csvFile, 'w');
    fputcsv($fp, ProductExcelService::EXPECTED_COLUMNS);
    // Row 1: Existing SKU (should update stock/price but preserve original UUID)
    fputcsv($fp, [
        'PRD-001', 'SKU-UUID-001', '8991234567890', 'SKU-UUID-001', 150,
        'Kopi Bubuk ILP Updated', 'Pcs', 11000, 16000, 15500, 0, 5, 11, 'Y', 11000, 'TERSEDIA', 'SUP-01', '2026-01-01', '2027-01-01',
    ]);
    // Row 2: Brand new SKU via bulk upsert (must receive a valid UUIDv7)
    fputcsv($fp, [
        'PRD-002', 'SKU-UUID-002', '8991234567891', 'SKU-UUID-002', 80,
        'Gula Pasir ILP', 'Kg', 12000, 17500, 17000, 0, 10, 11, 'Y', 12000, 'TERSEDIA', 'SUP-01', '2026-01-01', '2027-01-01',
    ]);
    fclose($fp);

    $uploadedProductFile = new UploadedFile($csvFile, 'products.csv', 'text/csv', null, true);
    $productService = app(ProductExcelService::class);
    $preview = $productService->validateAndPreview($uploadedProductFile);
    expect($preview['valid'])->toBeTrue();

    $importRes = $productService->processImport($preview['token'], 'update');
    expect($importRes['success'])->toBeTrue();

    $updatedProduct = Product::where('sku', 'SKU-UUID-001')->first();
    $newImportedProduct = Product::where('sku', 'SKU-UUID-002')->first();

    expect($updatedProduct->uuid)->toBe($originalProductUuid)
        ->and((int) $updatedProduct->stock)->toBe(150)
        ->and($newImportedProduct)->not->toBeNull()
        ->and(Str::isUuid($newImportedProduct->uuid))->toBeTrue();

    // 3. Bulk Member/Karyawan Excel Import generates UUIDv7
    $memberCsv = $tempDir . '/members_uuid_' . uniqid() . '.csv';
    $mfp = fopen($memberCsv, 'w');
    fputcsv($mfp, MemberExcelService::EXPECTED_COLUMNS);
    fputcsv($mfp, [
        '101', 'MBR-UUID-001', 'OUT-01', '19990101', 'Budi Santoso', 'Produksi', 'Pabrik',
        'Lampung', '08123456789', '-', '-', '30', 'IDTK-1', '1500000', '1234567890', 'Pusat',
    ]);
    fclose($mfp);

    $uploadedMemberFile = new UploadedFile($memberCsv, 'members.csv', 'text/csv', null, true);
    $memberService = app(MemberExcelService::class);
    $mPreview = $memberService->validateAndPreview($uploadedMemberFile);
    expect($mPreview['valid'])->toBeTrue();

    $mImport = $memberService->processImport($mPreview['token'], 'update');
    expect($mImport['success'])->toBeTrue();

    $importedMember = Member::where('member_code', 'MBR-UUID-001')->first();
    expect($importedMember)->not->toBeNull()
        ->and($importedMember->id)->toBeInt()
        ->and(Str::isUuid($importedMember->uuid))->toBeTrue();

    @unlink($csvFile);
    @unlink($memberCsv);
});

test('central sync endpoint syncs new transaction and atomically decrements product stock', function () {
    $cashier = User::create([
        'name' => 'Kasir Cabang 1',
        'username' => 'kasir_c1',
        'email' => 'kasir_c1@ilp.test',
        'nik' => '20260001',
        'role' => 'Kasir',
        'pin' => '111222',
        'branch' => 'Pusat',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    $productA = Product::create([
        'sku' => 'PROD-SYNC-A',
        'barcode' => '899000000001',
        'name' => 'Minyak Goreng 1L',
        'price' => 20000,
        'cost_price' => 16000,
        'stock' => 50,
        'unit' => 'Pcs',
    ]);

    $productB = Product::create([
        'sku' => 'PROD-SYNC-B',
        'barcode' => '899000000002',
        'name' => 'Mie Instan Goreng',
        'price' => 3500,
        'cost_price' => 2800,
        'stock' => 100,
        'unit' => 'Pcs',
    ]);

    $transactionUuid = (string) Str::uuid7();
    $shiftUuid = (string) Str::uuid7();

    $payload = [
        'transactions' => [
            [
                'transaction_uuid' => $transactionUuid,
                'invoice_number' => 'TRX-20261126-0001-SYN',
                'employee_uuid' => $cashier->uuid,
                'cashier_name' => $cashier->name,
                'shift_uuid' => $shiftUuid,
                'shift_code' => 'CS-20261126-POS01-001',
                'terminal_code' => 'POS-01',
                'branch' => 'Pusat',
                'payment_method' => 'TUNAI',
                'subtotal' => 50500,
                'discount_total' => 0,
                'tax_total' => 0,
                'grand_total' => 50500,
                'cash_received' => 55000,
                'change_returned' => 4500,
                'status' => 'SUCCESS',
                'created_at' => '2026-11-26T08:15:00+07:00',
                'items' => [
                    [
                        'item_uuid' => (string) Str::uuid7(),
                        'product_uuid' => $productA->uuid,
                        'sku' => $productA->sku,
                        'barcode' => $productA->barcode,
                        'product_name' => $productA->name,
                        'unit' => 'Pcs',
                        'qty' => 2,
                        'unit_price' => 20000,
                        'total_price' => 40000,
                    ],
                    [
                        'item_uuid' => (string) Str::uuid7(),
                        'product_uuid' => $productB->uuid,
                        'sku' => $productB->sku,
                        'barcode' => $productB->barcode,
                        'product_name' => $productB->name,
                        'unit' => 'Pcs',
                        'qty' => 3,
                        'unit_price' => 3500,
                        'total_price' => 10500,
                    ],
                ],
            ],
        ],
    ];

    $response = $this->withToken('secret-central-sync-token-2026')
        ->postJson('/api/v1/sync/transactions', $payload);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('summary.synced', 1)
        ->assertJsonPath('summary.failed', 0)
        ->assertJsonPath('results.0.transaction_uuid', $transactionUuid)
        ->assertJsonPath('results.0.status', 'synced');

    // Verify Transaction & Items persisted with resolved integer foreign keys
    $syncedTrx = Transaction::where('uuid', $transactionUuid)->first();
    expect($syncedTrx)->not->toBeNull()
        ->and($syncedTrx->user_id)->toBe($cashier->id)
        ->and($syncedTrx->synced_at)->not->toBeNull()
        ->and($syncedTrx->items()->count())->toBe(2);

    // Verify atomic stock decrement (50 - 2 = 48, 100 - 3 = 97)
    expect($productA->fresh()->stock)->toBe(48)
        ->and((int) $productA->fresh()->qty_produk)->toBe(48)
        ->and($productB->fresh()->stock)->toBe(97)
        ->and((int) $productB->fresh()->qty_produk)->toBe(97);
});

test('retrying sync with same transaction_uuid is strictly idempotent and does not decrement stock twice', function () {
    $cashier = User::create([
        'name' => 'Kasir Idempotent',
        'username' => 'kasir_idem',
        'email' => 'kasir_idem@ilp.test',
        'nik' => '20260002',
        'role' => 'Kasir',
        'pin' => '111222',
        'branch' => 'Pusat',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    $product = Product::create([
        'sku' => 'PROD-IDEM-01',
        'barcode' => '899000000099',
        'name' => 'Susu UHT 1L',
        'price' => 18000,
        'cost_price' => 14000,
        'stock' => 30,
        'unit' => 'Pcs',
    ]);

    $transactionUuid = (string) Str::uuid7();

    $payload = [
        'transactions' => [
            [
                'transaction_uuid' => $transactionUuid,
                'invoice_number' => 'TRX-20261126-IDEM-001',
                'employee_uuid' => $cashier->uuid,
                'terminal_code' => 'POS-01',
                'payment_method' => 'TUNAI',
                'grand_total' => 72000,
                'items' => [
                    [
                        'product_uuid' => $product->uuid,
                        'qty' => 4,
                        'unit_price' => 18000,
                        'total_price' => 72000,
                    ],
                ],
            ],
        ],
    ];

    // First send: creates transaction and decrements stock from 30 -> 26
    $firstRes = $this->withToken('secret-central-sync-token-2026')
        ->postJson('/api/v1/sync/transactions', $payload);

    $firstRes->assertStatus(200)
        ->assertJsonPath('results.0.status', 'synced');
    expect($product->fresh()->stock)->toBe(26)
        ->and(Transaction::where('uuid', $transactionUuid)->count())->toBe(1);

    // Second send (Retry with exact same UUID): returns 200 already_synced, stock MUST remain 26!
    $secondRes = $this->withToken('secret-central-sync-token-2026')
        ->postJson('/api/v1/sync/transactions', $payload);

    $secondRes->assertStatus(200)
        ->assertJsonPath('results.0.status', 'already_synced')
        ->assertJsonPath('summary.already_synced', 1);

    expect($product->fresh()->stock)->toBe(26)
        ->and(Transaction::where('uuid', $transactionUuid)->count())->toBe(1)
        ->and(TransactionItem::count())->toBe(1);
});

test('partial batch failure rolls back only invalid transaction while committing valid ones', function () {
    $cashier = User::create([
        'name' => 'Kasir Batch',
        'username' => 'kasir_batch',
        'email' => 'kasir_batch@ilp.test',
        'nik' => '20260003',
        'role' => 'Kasir',
        'pin' => '111222',
        'branch' => 'Pusat',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    $product = Product::create([
        'sku' => 'PROD-BATCH-01',
        'barcode' => '899000000101',
        'name' => 'Teh Celup ILP',
        'price' => 8000,
        'cost_price' => 5500,
        'stock' => 20,
        'unit' => 'Box',
    ]);

    $validTrxUuid = (string) Str::uuid7();
    $invalidTrxUuid = (string) Str::uuid7();
    $unknownProductUuid = (string) Str::uuid7();

    $payload = [
        'transactions' => [
            [
                'transaction_uuid' => $validTrxUuid,
                'invoice_number' => 'TRX-VALID-01',
                'employee_uuid' => $cashier->uuid,
                'grand_total' => 16000,
                'items' => [
                    [
                        'product_uuid' => $product->uuid,
                        'qty' => 2,
                        'unit_price' => 8000,
                    ],
                ],
            ],
            [
                'transaction_uuid' => $invalidTrxUuid,
                'invoice_number' => 'TRX-INVALID-02',
                'employee_uuid' => $cashier->uuid,
                'grand_total' => 24000,
                'items' => [
                    [
                        'product_uuid' => $product->uuid,
                        'qty' => 1,
                        'unit_price' => 8000,
                    ],
                    [
                        'product_uuid' => $unknownProductUuid,
                        'qty' => 2,
                        'unit_price' => 8000,
                    ],
                ],
            ],
        ],
    ];

    $res = $this->withToken('secret-central-sync-token-2026')
        ->postJson('/api/v1/sync/transactions', $payload);

    $res->assertStatus(200)
        ->assertJsonPath('summary.synced', 1)
        ->assertJsonPath('summary.failed', 1)
        ->assertJsonPath('results.0.status', 'synced')
        ->assertJsonPath('results.1.status', 'failed');

    // Valid transaction committed (20 - 2 = 18), invalid transaction rolled back completely (did not deduct 1 more)
    expect(Transaction::where('uuid', $validTrxUuid)->exists())->toBeTrue()
        ->and(Transaction::where('uuid', $invalidTrxUuid)->exists())->toBeFalse()
        ->and($product->fresh()->stock)->toBe(18);
});

test('pos:sync-transactions command sends strictly UUID payload and updates synced_at on confirmation', function () {
    config(['pos_sync.node_role' => 'local']);

    $cashier = User::create([
        'name' => 'Kasir Lokal',
        'username' => 'kasir_lokal',
        'email' => 'kasir_lokal@ilp.test',
        'nik' => '20260004',
        'role' => 'Kasir',
        'pin' => '111222',
        'branch' => 'Pusat',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    $product = Product::create([
        'sku' => 'PROD-CMD-01',
        'barcode' => '899000000201',
        'name' => 'Sabun Mandi ILP',
        'price' => 5000,
        'cost_price' => 3500,
        'stock' => 40,
        'unit' => 'Pcs',
    ]);

    $shift = CashierShift::create([
        'shift_code' => 'CS-LOCAL-001',
        'terminal_code' => 'POS-01',
        'branch' => 'Pusat',
        'user_id' => $cashier->id,
        'cashier_nik' => $cashier->nik,
        'cashier_name' => $cashier->name,
        'opening_cash' => 200000,
        'status' => 'OPEN',
        'opened_at' => now(),
    ]);

    $transaction = Transaction::create([
        'invoice_number' => 'TRX-LOCAL-0001',
        'shift_id' => $shift->id,
        'user_id' => $cashier->id,
        'cashier_name' => $cashier->name,
        'terminal_code' => 'POS-01',
        'branch' => 'Pusat',
        'payment_method' => 'TUNAI',
        'subtotal' => 15000,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 15000,
        'cash_received' => 20000,
        'change_returned' => 5000,
        'status' => 'SUCCESS',
    ]);

    TransactionItem::create([
        'transaction_id' => $transaction->id,
        'product_id' => $product->id,
        'sku' => $product->sku,
        'barcode' => $product->barcode,
        'product_name' => $product->name,
        'unit' => 'Pcs',
        'unit_price' => 5000,
        'qty' => 3,
        'total_price' => 15000,
    ]);

    Http::fake([
        'https://central.pos-ilp.test/api/v1/sync/transactions' => Http::response([
            'success' => true,
            'summary' => ['total_received' => 1, 'synced' => 1, 'already_synced' => 0, 'failed' => 0],
            'results' => [
                [
                    'transaction_uuid' => $transaction->uuid,
                    'success' => true,
                    'status' => 'synced',
                ],
            ],
        ], 200),
    ]);

    $this->artisan('pos:sync-transactions')
        ->assertExitCode(0);

    Http::assertSent(function (HttpClientRequest $request) use ($transaction, $cashier, $product) {
        $body = $request->data();
        $sentTrx = $body['transactions'][0] ?? [];
        $sentItem = $sentTrx['items'][0] ?? [];

        // Ensure payload uses UUIDs and NEVER leaks local auto-increment IDs
        return $request->url() === 'https://central.pos-ilp.test/api/v1/sync/transactions'
            && $request->hasHeader('Authorization', 'Bearer secret-central-sync-token-2026')
            && ($sentTrx['transaction_uuid'] ?? null) === $transaction->uuid
            && ($sentTrx['employee_uuid'] ?? null) === $cashier->uuid
            && ($sentItem['product_uuid'] ?? null) === $product->uuid
            && !array_key_exists('user_id', $sentTrx)
            && !array_key_exists('employee_id', $sentTrx)
            && !array_key_exists('product_id', $sentItem);
    });

    expect($transaction->fresh()->synced_at)->not->toBeNull()
        ->and($transaction->fresh()->sync_error)->toBeNull();
});
