<?php

namespace Tests\Traits;

use App\Models\Location;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Supplier;
use Carbon\Carbon;

trait SeedsTestFixtures
{
    /**
     * Seed test-only fixture records inside the isolated test database transaction.
     */
    protected function seedTestFixtures(): void
    {
        $this->seed();

        $locations = [
            ['code' => 'LOC-01', 'name' => 'Cabang 1 - Mill / Pabrik', 'type' => 'STORE', 'is_active' => true],
            ['code' => 'LOC-02', 'name' => 'Cabang 2 - Kebun Barat', 'type' => 'STORE', 'is_active' => true],
            ['code' => 'LOC-03', 'name' => 'Cabang 3 - Kebun Timur', 'type' => 'STORE', 'is_active' => true],
            ['code' => 'LOC-DC', 'name' => 'DC Cikarang (Pusat Logistik)', 'type' => 'WAREHOUSE', 'is_active' => true],
        ];
        foreach ($locations as $loc) {
            Location::updateOrCreate(['code' => $loc['code']], $loc);
        }

        $members = [
            [
                'member_code' => 'MBR-001',
                'nik' => '1029384756',
                'name' => 'Santoso',
                'department' => 'Logistik & Distribusi',
                'branch' => 'Cabang 1 - Mill / Pabrik',
                'phone' => '0812-0000-0001',
                'join_date' => '2021-01-12',
                'credit_limit' => 2500000,
                'current_debt' => 500000,
                'status' => 'AKTIF',
            ],
            [
                'member_code' => 'MBR-003',
                'nik' => '327501440230012',
                'name' => 'Siti Lestari',
                'department' => 'Administrasi & Keuangan',
                'branch' => 'Cabang 2 - Kebun Barat',
                'phone' => '0812-0000-0002',
                'join_date' => '2022-05-05',
                'credit_limit' => 4000000,
                'current_debt' => 1250000,
                'status' => 'AKTIF',
            ],
            [
                'member_code' => 'MBR-004',
                'nik' => '327501002230099',
                'name' => 'Andri Setiawan',
                'department' => 'Operasional Kebun',
                'branch' => 'Cabang 3 - Kebun Timur',
                'phone' => '0812-0000-0003',
                'join_date' => '2019-08-10',
                'credit_limit' => 3000000,
                'current_debt' => 3000000,
                'status' => 'DIBLOKIR',
            ],
        ];
        foreach ($members as $m) {
            Member::updateOrCreate(['nik' => $m['nik']], $m);
        }

        $supplier = Supplier::updateOrCreate(
            ['code' => 'UMKM-001'],
            [
                'name' => 'Supplier Test UMKM 01',
                'owner_name' => 'Mitra Test',
                'phone' => '0812-0000-0010',
                'address' => 'Alamat Test',
                'category' => 'Makanan Ringan',
                'commission_percent' => 15.00,
                'settlement_period' => 'MINGGUAN',
                'status' => 'AKTIF',
            ]
        );

        $today = Carbon::today();
        $products = [
            [
                'sku' => 'PRD-001',
                'barcode' => '8991234567890',
                'name' => 'Aqua Mineral 600ml',
                'category' => 'Minuman',
                'unit' => 'Botol',
                'cost_price' => 4000,
                'price' => 5500,
                'member_price' => 5000,
                'stock' => 120,
                'min_stock' => 20,
                'is_taxable' => true,
                'tax_rate' => 11.00,
                'expired_date' => $today->copy()->addMonths(6)->toDateString(),
                'clearance_discount_percent' => 20.00,
                'is_consignment' => false,
                'status' => 'TERSEDIA',
            ],
            [
                'sku' => 'PRD-002',
                'barcode' => '8992345678901',
                'name' => 'Chitato Sapi Panggang 68g',
                'category' => 'Snack',
                'unit' => 'Pcs',
                'cost_price' => 10000,
                'price' => 12800,
                'member_price' => 12000,
                'stock' => 8,
                'min_stock' => 10,
                'is_taxable' => true,
                'tax_rate' => 11.00,
                'expired_date' => $today->copy()->addMonths(3)->toDateString(),
                'clearance_discount_percent' => 20.00,
                'is_consignment' => false,
                'status' => 'STOK_RENDAH',
            ],
            [
                'sku' => 'PRD-003',
                'barcode' => '8993456789012',
                'name' => 'Ultra Milk Full Cream 1L',
                'category' => 'Minuman',
                'unit' => 'Tetra',
                'cost_price' => 15000,
                'price' => 19200,
                'member_price' => 18500,
                'stock' => 25,
                'min_stock' => 10,
                'is_taxable' => true,
                'tax_rate' => 11.00,
                'expired_date' => $today->copy()->addDays(6)->toDateString(),
                'clearance_discount_percent' => 20.00,
                'is_consignment' => false,
                'status' => 'TERSEDIA',
            ],
            [
                'sku' => 'PRD-006',
                'barcode' => '899275321004',
                'name' => 'Indomie Goreng Spesial 85g',
                'category' => 'Makanan',
                'unit' => 'Pcs',
                'cost_price' => 2800,
                'price' => 3500,
                'member_price' => 3200,
                'stock' => 200,
                'min_stock' => 40,
                'is_taxable' => true,
                'tax_rate' => 11.00,
                'expired_date' => $today->copy()->addMonths(8)->toDateString(),
                'clearance_discount_percent' => 20.00,
                'is_consignment' => false,
                'status' => 'TERSEDIA',
                'units' => [
                    ['unit_name' => 'Dus', 'conversion_factor' => 40, 'price' => 135000, 'barcode' => '899275321004-DUS'],
                ],
            ],
            [
                'sku' => 'KNS-001',
                'barcode' => '899881122001',
                'name' => 'Produk Konsinyasi Test 150g',
                'category' => 'Snack UMKM',
                'unit' => 'Bks',
                'cost_price' => 8500,
                'price' => 10000,
                'member_price' => 10000,
                'stock' => 40,
                'min_stock' => 10,
                'is_taxable' => false,
                'tax_rate' => 0.00,
                'expired_date' => $today->copy()->addMonths(4)->toDateString(),
                'is_consignment' => true,
                'supplier_id' => $supplier->id,
                'commission_percent' => 15.00,
                'status' => 'TERSEDIA',
            ],
        ];

        foreach ($products as $p) {
            $units = $p['units'] ?? [];
            unset($p['units']);
            $prod = Product::updateOrCreate(['sku' => $p['sku']], $p);
            foreach ($units as $u) {
                ProductUnit::updateOrCreate(
                    ['product_id' => $prod->id, 'unit_name' => $u['unit_name']],
                    [
                        'conversion_factor' => $u['conversion_factor'],
                        'price' => $u['price'],
                        'barcode' => $u['barcode'] ?? null,
                    ]
                );
            }
        }
    }
}
