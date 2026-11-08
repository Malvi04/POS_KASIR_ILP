<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\CashierShift;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class AccountingReportModulETest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        AppSetting::set('tax_enabled', '1', 'tax');
        AppSetting::set('tax_rate_percent', '11', 'tax');
    }

    /**
     * Test 1: Laporan Penjualan (Per-Shift, Per-Cabang, Konsolidasi 3 Cabang: Harian, Mingguan, Bulanan).
     */
    public function test_sales_report_filtering_and_consolidation(): void
    {
        // 1. Monthly consolidation
        $response = $this->getJson('/api/reports/sales?period=monthly&month=' . date('Y-m'));
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertArrayHasKey('summary', $response->json());
        $this->assertArrayHasKey('shifts_breakdown', $response->json());
        $this->assertArrayHasKey('branch_breakdown', $response->json());
        $this->assertCount(3, $response->json('branch_breakdown'));

        // 2. Daily filtering
        $dailyRes = $this->getJson('/api/reports/sales?period=daily&date=' . date('Y-m-d'));
        $dailyRes->assertStatus(200);
        $this->assertEquals('daily', $dailyRes->json('filters.period'));

        // 3. Weekly filtering
        $weeklyRes = $this->getJson('/api/reports/sales?period=weekly');
        $weeklyRes->assertStatus(200);
        $this->assertEquals('weekly', $weeklyRes->json('filters.period'));
    }

    /**
     * Test 2: Laporan Laba/Rugi Bersih (HPP, Penjualan Reguler, dan Bagi Hasil Komisi UMKM).
     */
    public function test_profit_loss_accounting_with_cogs_and_umkm_commission(): void
    {
        // Setup a supplier and consignment product
        $supplier = Supplier::firstOrCreate(
            ['code' => 'UMKM-TEST-PL'],
            [
                'name' => 'UMKM Kue Basah Lestari',
                'owner_name' => 'Ibu Lestari',
                'phone' => '081299990001',
                'status' => 'AKTIF',
            ]
        );

        $productConsignment = Product::updateOrCreate(
            ['sku' => 'KUE-TEST-01'],
            [
                'barcode' => '899999123401',
                'name' => 'Kue Lapis Legit Spesial UMKM',
                'category' => 'Makanan Ringan',
                'unit' => 'Pack',
                'cost_price' => 20000,
                'price' => 25000,
                'stock' => 100,
                'min_stock' => 10,
                'is_consignment' => true,
                'supplier_id' => $supplier->id,
                'commission_percent' => 20.0, // 20% komisi koperasi
                'status' => 'TERSEDIA',
            ]
        );

        $response = $this->getJson('/api/reports/profit-loss?start_date=' . date('Y-m-01') . '&end_date=' . date('Y-m-d'));

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $statement = $response->json('statement');
        $this->assertArrayHasKey('gross_sales', $statement);
        $this->assertArrayHasKey('regular_cogs', $statement);
        $this->assertArrayHasKey('umkm_partner_share', $statement);
        $this->assertArrayHasKey('cooperative_commission_earned', $statement);
        $this->assertArrayHasKey('net_profit', $statement);
        $this->assertArrayHasKey('profit_margin_percent', $statement);
    }

    /**
     * Test 3: Analisis Barang Terlaris (Fast Moving Items & Rekomendasi Restock).
     */
    public function test_fast_moving_items_analysis_and_restock_recommendations(): void
    {
        $response = $this->getJson('/api/reports/fast-moving?limit=5&sort_by=qty');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertArrayHasKey('data', $response->json());

        $items = $response->json('data');
        if (count($items) > 0) {
            $first = $items[0];
            $this->assertArrayHasKey('product_id', $first);
            $this->assertArrayHasKey('sku', $first);
            $this->assertArrayHasKey('name', $first);
            $this->assertArrayHasKey('total_qty_sold', $first);
            $this->assertArrayHasKey('total_revenue', $first);
            $this->assertArrayHasKey('current_stock', $first);
            $this->assertArrayHasKey('recommended_restock_qty', $first);
        }
    }

    /**
     * Test 4: Laporan Rekonsiliasi Kas (Cash Count / Shift Closing).
     */
    public function test_cash_reconciliation_shift_closing_audit(): void
    {
        // Create an audited closed shift with cash discrepancy
        $shift = CashierShift::create([
            'shift_code' => 'CS-TEST-AUDIT-' . time(),
            'terminal_code' => 'POS-01',
            'cashier_nik' => 'KASIR-001',
            'cashier_name' => 'Budi Kasir Audit',
            'opening_cash' => 200000,
            'cash_sales_total' => 500000,
            'qris_sales_total' => 150000,
            'credit_sales_total' => 0,
            'total_sales' => 650000,
            'transaction_count' => 5,
            'cash_in' => 0,
            'cash_out' => 50000,
            'expected_cash' => 650000, // 200.000 + 500.000 - 50.000
            'actual_cash' => 650000, // Matching!
            'variance' => 0,
            'variance_status' => 'SESUAI',
            'status' => 'CLOSED',
            'opened_at' => now()->subHours(8),
            'closed_at' => now(),
            'closing_notes' => 'Serah terima shift siang lancar.',
        ]);

        $response = $this->getJson('/api/reports/cash-reconciliation');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertArrayHasKey('summary', $response->json());
        $this->assertArrayHasKey('accuracy_rate_percent', $response->json('summary'));
        $this->assertGreaterThanOrEqual(1, $response->json('summary.total_audited_shifts'));
    }

    /**
     * Test 5: Pengaturan Pajak (Tax / Non-Tax Products Management & Automatic PPN Calculation).
     */
    public function test_taxable_vs_non_taxable_product_and_checkout_calculation(): void
    {
        // 1. Get Taxable vs Non-Taxable Product List
        $taxListRes = $this->getJson('/api/products/tax/list');
        $taxListRes->assertStatus(200);
        $this->assertTrue($taxListRes->json('success'));
        $this->assertArrayHasKey('summary', $taxListRes->json());
        $this->assertArrayHasKey('taxable_products', $taxListRes->json('summary'));
        $this->assertArrayHasKey('non_taxable_products', $taxListRes->json('summary'));

        // 2. Setup Non-Tax Product (Contoh: Beras Premium Bebas PPN)
        $nonTaxProduct = Product::updateOrCreate(
            ['sku' => 'BERAS-NON-TAX'],
            [
                'barcode' => '899123450001',
                'name' => 'Beras Pandan Wangi 5kg',
                'category' => 'Sembako',
                'unit' => 'Karung',
                'cost_price' => 65000,
                'price' => 75000,
                'stock' => 50,
                'min_stock' => 5,
                'is_taxable' => false, // Non-tax!
                'tax_rate' => 0,
                'status' => 'TERSEDIA',
            ]
        );

        // 3. Setup Taxable Product (Contoh: Sabun Mandi Kena PPN 11%)
        $taxableProduct = Product::updateOrCreate(
            ['sku' => 'SABUN-TAXABLE'],
            [
                'barcode' => '899123450002',
                'name' => 'Sabun Mandi Antiseptik 100g',
                'category' => 'Toiletries',
                'unit' => 'Pcs',
                'cost_price' => 3500,
                'price' => 5000,
                'stock' => 100,
                'min_stock' => 10,
                'is_taxable' => true, // Taxable!
                'tax_rate' => 11.0,
                'status' => 'TERSEDIA',
            ]
        );

        // 4. Update Product Tax Status via API
        $updateTaxRes = $this->putJson("/api/products/{$nonTaxProduct->id}/tax-status", [
            'is_taxable' => false,
            'tax_rate' => 0,
        ]);
        $updateTaxRes->assertStatus(200);
        $this->assertFalse($updateTaxRes->json('data.is_taxable'));

        // 5. Checkout containing both Taxable & Non-Taxable Items
        CashierShift::firstOrCreate(
            ['terminal_code' => 'POS-01', 'status' => 'OPEN'],
            [
                'shift_code' => 'CS-' . date('Ymd') . '-POS01-TAX',
                'cashier_nik' => '202409821002',
                'cashier_name' => 'Kasir Pajak',
                'opening_cash' => 500000,
                'status' => 'OPEN',
                'opened_at' => now(),
            ]
        );

        $checkoutRes = $this->postJson('/api/pos/checkout', [
            'terminal_code' => 'POS-01',
            'cashier_name' => 'Kasir Pajak',
            'payment_method' => 'TUNAI',
            'cash_received' => 100000,
            'items' => [
                [
                    'sku' => $nonTaxProduct->sku,
                    'barcode' => $nonTaxProduct->barcode,
                    'name' => $nonTaxProduct->name,
                    'price' => 75000,
                    'qty' => 1,
                ],
                [
                    'sku' => $taxableProduct->sku,
                    'barcode' => $taxableProduct->barcode,
                    'name' => $taxableProduct->name,
                    'price' => 5000,
                    'qty' => 2, // 10.000 -> PPN 11% = 1.100
                ],
            ],
        ]);

        $checkoutRes->assertStatus(201);
        $this->assertTrue($checkoutRes->json('success'));

        // Total subtotal = 75.000 + 10.000 = 85.000
        $this->assertEquals(85000, $checkoutRes->json('data.subtotal'));
        // PPN must ONLY be calculated on the taxable item: 10.000 * 11% = 1.100 (Non-tax beras has 0 PPN!)
        $this->assertEquals(1100, $checkoutRes->json('data.tax_total'));
        // Grand total = 85.000 + 1.100 = 86.100
        $this->assertEquals(86100, $checkoutRes->json('data.grand_total'));
    }
}
