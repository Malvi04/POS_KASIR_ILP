<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\ChartOfAccount;
use App\Models\CouponUnit;
use App\Models\DebtMutation;
use App\Models\Location;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with compact, clean dummy data.
     */
    public function run(): void
    {
        // ---------------------------------------------------------------------
        // 1. Data Dummy Users (5 Akun Representatif)
        // ---------------------------------------------------------------------
        $users = [
            [
                'name' => 'Budi Kurniawan',
                'username' => 'budi_kasir01',
                'email' => 'budi@minimarket.ilp',
                'nik' => '202409821002',
                'role' => 'Kasir Utama',
                'pin' => '123456',
                'branch' => 'Pusat',
                'password' => Hash::make('budi123'),
                'is_active' => true,
            ],
            [
                'name' => 'Andi Pratama',
                'username' => 'andi_kasir',
                'email' => 'andi@minimarket.ilp',
                'nik' => '202409821001',
                'role' => 'Kasir Operasional',
                'pin' => '123456',
                'branch' => 'Cabang 1 - Mill / Pabrik',
                'password' => Hash::make('andi123'),
                'is_active' => true,
            ],
            [
                'name' => 'Citra Dewi',
                'username' => 'citra_kasir',
                'email' => 'citra@minimarket.ilp',
                'nik' => '202409821003',
                'role' => 'Kasir Operasional',
                'pin' => '123456',
                'branch' => 'Cabang A',
                'password' => Hash::make('citra123'),
                'is_active' => true,
            ],
            [
                'name' => 'Dedi Santoso',
                'username' => 'dedi_kasir',
                'email' => 'dedi@minimarket.ilp',
                'nik' => '202409821004',
                'role' => 'Kasir Operasional',
                'pin' => '123456',
                'branch' => 'Cabang B',
                'password' => Hash::make('dedi123'),
                'is_active' => true,
            ],
            [
                'name' => 'Hendra Wijaya',
                'username' => 'spv_hendra',
                'email' => 'spv@minimarket.ilp',
                'nik' => '202409821010',
                'role' => 'Supervisor',
                'pin' => '123456',
                'branch' => 'Pusat',
                'password' => Hash::make('spv123'),
                'is_active' => true,
            ],
            [
                'name' => 'Andi Manager',
                'username' => 'andi_manager',
                'email' => 'manager@minimarket.ilp',
                'nik' => '202409821099',
                'role' => 'Manager',
                'pin' => '123456',
                'branch' => 'Pusat',
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
            [
                'name' => 'Ir. Hartono Wibowo',
                'username' => 'owner_ilp',
                'email' => 'owner@minimarket.ilp',
                'nik' => '198501012010001',
                'role' => 'Owner',
                'pin' => '123456',
                'branch' => 'Pusat',
                'password' => Hash::make('owner123'),
                'is_active' => true,
            ],
            [
                'name' => 'Dewi Sartika (Owner)',
                'username' => 'dewi_owner',
                'email' => 'dewi.owner@minimarket.ilp',
                'nik' => '198501012010002',
                'role' => 'Owner',
                'pin' => '123456',
                'branch' => 'Pusat',
                'password' => Hash::make('owner123'),
                'is_active' => true,
            ],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(['nik' => $u['nik']], $u);
        }

        // ---------------------------------------------------------------------
        // 2. Data Dummy Members (4 Anggota Koperasi Multi-Cabang)
        // ---------------------------------------------------------------------
        $members = [
            [
                'member_code' => 'MBR-001',
                'nik' => '1029384756',
                'name' => 'Santoso',
                'department' => 'Logistik & Distribusi',
                'branch' => 'Cabang 1 - Mill / Pabrik',
                'phone' => '0812-9841-2201',
                'join_date' => '2021-01-12',
                'credit_limit' => 2500000,
                'current_debt' => 500000,
                'status' => 'AKTIF',
                'notes' => 'Karyawan Divisi Logistik Pabrik Gula Pusat.',
            ],
            [
                'member_code' => 'MBR-002',
                'nik' => '327501002230004',
                'name' => 'Bambang Pamungkas',
                'department' => 'Produksi Gula A',
                'branch' => 'Cabang 1 - Mill / Pabrik',
                'phone' => '0812-3456-7890',
                'join_date' => '2020-03-15',
                'credit_limit' => 5000000,
                'current_debt' => 0,
                'status' => 'AKTIF',
                'notes' => 'Anggota teladan, plafon utuh 100%.',
            ],
            [
                'member_code' => 'MBR-003',
                'nik' => '327501440230012',
                'name' => 'Siti Lestari',
                'department' => 'Administrasi & Keuangan',
                'branch' => 'Cabang 2 - Kebun Barat',
                'phone' => '0813-8821-4412',
                'join_date' => '2022-05-05',
                'credit_limit' => 4000000,
                'current_debt' => 1250000,
                'status' => 'AKTIF',
                'notes' => 'Staff Keuangan Wilayah Kebun Barat.',
            ],
            [
                'member_code' => 'MBR-004',
                'nik' => '327501002230099',
                'name' => 'Andri Setiawan',
                'department' => 'Operasional Kebun',
                'branch' => 'Cabang 3 - Kebun Timur',
                'phone' => '0857-1120-9981',
                'join_date' => '2019-08-10',
                'credit_limit' => 3000000,
                'current_debt' => 3000000,
                'status' => 'DIBLOKIR',
                'notes' => 'Plafon kasbon habis 100%, status dibekukan otomatis.',
            ],
        ];

        foreach ($members as $m) {
            $createdMember = Member::updateOrCreate(['nik' => $m['nik']], $m);

            if ($createdMember->current_debt > 0 && $createdMember->debtMutations()->count() === 0) {
                DebtMutation::create([
                    'member_id' => $createdMember->id,
                    'member_nik' => $createdMember->nik,
                    'transaction_id' => null,
                    'invoice_number' => 'TRX-' . date('Ym') . '15-' . rand(1000, 9999),
                    'branch' => $createdMember->branch,
                    'mutation_type' => 'KASBON_POS',
                    'amount' => $createdMember->current_debt,
                    'previous_debt' => 0,
                    'new_debt' => $createdMember->current_debt,
                    'previous_limit' => $createdMember->credit_limit,
                    'new_limit' => $createdMember->credit_limit,
                    'notes' => 'Kasbon belanja sembako minimarket koperasi',
                    'processed_by' => 'Kasir Utama',
                    'created_at' => Carbon::now()->subDays(rand(2, 10)),
                ]);
            }
        }

        // ---------------------------------------------------------------------
        // 3. Data Dummy Mitra UMKM Konsinyasi (2 Mitra)
        // ---------------------------------------------------------------------
        $suppliers = [
            [
                'code' => 'UMKM-001',
                'name' => 'Dapur Bu Eni (Snack Tradisional)',
                'owner_name' => 'Eni Sulistiawati',
                'phone' => '0812-3456-7890',
                'address' => 'Perumahan Karyawan Divisi 1 PT ILP',
                'category' => 'Makanan Ringan',
                'commission_percent' => 15.00,
                'settlement_period' => 'MINGGUAN',
                'bank_name' => 'BRI',
                'bank_account' => '0123-01-098765-50-1',
                'status' => 'AKTIF',
                'notes' => 'Mitra binaan UMKM olahan singkong & pisang.',
            ],
            [
                'code' => 'UMKM-002',
                'name' => 'Kripik Berkah Lampung',
                'owner_name' => 'Haji Mansyur',
                'phone' => '0813-9876-5432',
                'address' => 'Jl. Lintas Timur KM 45 Tulang Bawang',
                'category' => 'Oleh-Oleh Khas',
                'commission_percent' => 20.00,
                'settlement_period' => 'BULANAN',
                'bank_name' => 'Mandiri',
                'bank_account' => '114-00-1928374-1',
                'status' => 'AKTIF',
                'notes' => 'Kripik pisang coklat lumer aneka rasa.',
            ],
        ];

        $supplierMap = [];
        foreach ($suppliers as $s) {
            $createdSup = Supplier::updateOrCreate(['code' => $s['code']], $s);
            $supplierMap[$s['code']] = $createdSup->id;
        }

        // ---------------------------------------------------------------------
        // 4. Data Dummy Produk (6 Produk Ringkas & Komprehensif)
        // ---------------------------------------------------------------------
        $today = Carbon::today();
        $products = [
            // 1. Minuman Reguler
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
                'rack_location' => 'Lorong A - Rak 01 (Minuman Dingin)',
                'is_consignment' => false,
                'status' => 'TERSEDIA',
                'units' => [
                    ['unit_name' => 'Dus', 'conversion_factor' => 24, 'price' => 120000, 'barcode' => '8991234567890-DUS'],
                ],
            ],
            // 2. Snack Stok Rendah
            [
                'sku' => 'PRD-002',
                'barcode' => '8992345678901',
                'name' => 'Chitato Sapi Panggang 68g',
                'category' => 'Snack',
                'unit' => 'Pcs',
                'cost_price' => 10000,
                'price' => 12800,
                'member_price' => 12000,
                'stock' => 8, // Di bawah min_stock 10 (Stok Rendah)
                'min_stock' => 10,
                'is_taxable' => true,
                'tax_rate' => 11.00,
                'expired_date' => $today->copy()->addMonths(3)->toDateString(),
                'clearance_discount_percent' => 20.00,
                'rack_location' => 'Lorong B - Rak 03',
                'is_consignment' => false,
                'status' => 'STOK_RENDAH',
            ],
            // 3. Susu Alarm Kritis H-6 (Auto-Diskon Expired 20%)
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
                'expired_date' => $today->copy()->addDays(6)->toDateString(), // H-6 Kritis!
                'clearance_discount_percent' => 20.00,
                'rack_location' => 'Chiller Susu 02',
                'is_consignment' => false,
                'status' => 'TERSEDIA',
            ],
            // 4. Makanan Multi-Satuan (1 Dus = 40 Pcs)
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
                'rack_location' => 'Lorong C - Mie Instan',
                'is_consignment' => false,
                'status' => 'TERSEDIA',
                'units' => [
                    ['unit_name' => 'Dus', 'conversion_factor' => 40, 'price' => 135000, 'barcode' => '899275321004-DUS'],
                ],
            ],
            // 5. Konsinyasi UMKM 1: Keripik Singkong Balado
            [
                'sku' => 'KNS-001',
                'barcode' => '899881122001',
                'name' => 'Keripik Singkong Balado Bu Eni 150g',
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
                'clearance_discount_percent' => 0.00,
                'rack_location' => 'Rak Display UMKM 01',
                'is_consignment' => true,
                'supplier_id' => $supplierMap['UMKM-001'] ?? null,
                'commission_percent' => 15.00,
                'status' => 'TERSEDIA',
            ],
            // 6. Konsinyasi UMKM 2: Kripik Pisang Coklat Lumer
            [
                'sku' => 'KNS-002',
                'barcode' => '899881122002',
                'name' => 'Kripik Pisang Coklat Berkah Lampung 200g',
                'category' => 'Snack UMKM',
                'unit' => 'Pouch',
                'cost_price' => 16000,
                'price' => 20000,
                'member_price' => 19000,
                'stock' => 25,
                'min_stock' => 8,
                'is_taxable' => false,
                'tax_rate' => 0.00,
                'expired_date' => $today->copy()->addMonths(5)->toDateString(),
                'clearance_discount_percent' => 0.00,
                'rack_location' => 'Rak Display UMKM 01',
                'is_consignment' => true,
                'supplier_id' => $supplierMap['UMKM-002'] ?? null,
                'commission_percent' => 20.00,
                'status' => 'TERSEDIA',
            ],
        ];

        foreach ($products as $p) {
            $units = $p['units'] ?? [];
            unset($p['units']);
            $createdProd = Product::updateOrCreate(['sku' => $p['sku']], $p);

            foreach ($units as $u) {
                ProductUnit::updateOrCreate(
                    ['product_id' => $createdProd->id, 'unit_name' => $u['unit_name']],
                    [
                        'conversion_factor' => $u['conversion_factor'],
                        'price' => $u['price'],
                        'barcode' => $u['barcode'] ?? null,
                    ]
                );
            }
        }

        // ---------------------------------------------------------------------
        // 5. Pengaturan Sistem (PPN 11%, Profil Toko & Keamanan)
        // ---------------------------------------------------------------------
        $defaultSettings = [
            'store_name' => ['value' => 'ILP Mart - Koperasi Pekerja Pusat', 'group' => 'store'],
            'store_code' => ['value' => 'CAB-JKT-001', 'group' => 'store'],
            'store_phone' => ['value' => '(021) 5589-2210', 'group' => 'store'],
            'store_address' => ['value' => 'Jl. Industri Raya No. 45, Kawasan Terpadu ILP', 'group' => 'store'],
            'store_tax_id' => ['value' => '01.882.991.4-012.000', 'group' => 'store'],
            'tax_rate_percent' => ['value' => '11', 'group' => 'tax'],
            'tax_enabled' => ['value' => '1', 'group' => 'tax'],
            'tax_calculation_method' => ['value' => 'EXCLUDE', 'group' => 'tax'],
            'void_require_supervisor_pin' => ['value' => '1', 'group' => 'security'],
            'manual_discount_require_pin' => ['value' => '1', 'group' => 'security'],
            'credit_limit_default' => ['value' => '2500000', 'group' => 'credit'],
            'branches_list' => ['value' => json_encode(['Pusat', 'Cabang 1 - Mill / Pabrik', 'Cabang 2 - Kebun Barat', 'Cabang 3 - Kebun Timur']), 'group' => 'store'],
        ];

        foreach ($defaultSettings as $key => $data) {
            AppSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $data['value'], 'group' => $data['group']]
            );
        }

        // ---------------------------------------------------------------------
        // 6. Master Lokasi 3 Cabang + DC Pusat
        // ---------------------------------------------------------------------
        $locations = [
            ['code' => 'LOC-01', 'name' => 'Cabang 1 - Mill / Pabrik', 'type' => 'STORE', 'is_active' => true],
            ['code' => 'LOC-02', 'name' => 'Cabang 2 - Kebun Barat', 'type' => 'STORE', 'is_active' => true],
            ['code' => 'LOC-03', 'name' => 'Cabang 3 - Kebun Timur', 'type' => 'STORE', 'is_active' => true],
            ['code' => 'LOC-DC', 'name' => 'DC Cikarang (Pusat Logistik)', 'type' => 'WAREHOUSE', 'is_active' => true],
        ];
        foreach ($locations as $loc) {
            Location::updateOrCreate(['code' => $loc['code']], $loc);
        }

        // ---------------------------------------------------------------------
        // 7. Master Kategori & Satuan Produk
        // ---------------------------------------------------------------------
        $categories = ['Kebutuhan Pokok', 'Makanan', 'Minuman', 'Snack', 'Snack UMKM', 'Pembersih', 'Kupon BBM'];
        foreach ($categories as $cat) {
            ProductCategory::updateOrCreate(['name' => $cat]);
        }

        $unitList = [
            ['name' => 'Pieces', 'symbol' => 'Pcs'],
            ['name' => 'Kardus', 'symbol' => 'Dus'],
            ['name' => 'Botol', 'symbol' => 'Botol'],
            ['name' => 'Pack', 'symbol' => 'Pack'],
            ['name' => 'Pouch', 'symbol' => 'Pouch'],
            ['name' => 'Tetrapack', 'symbol' => 'Tetra'],
            ['name' => 'Bungkus', 'symbol' => 'Bks'],
        ];
        foreach ($unitList as $u) {
            Unit::updateOrCreate(['name' => $u['name']], $u);
        }

        // ---------------------------------------------------------------------
        // 8. Bagan Akun Keuangan (Chart of Accounts / COA)
        // ---------------------------------------------------------------------
        $coas = [
            ['account_code' => '1-1001', 'account_name' => 'Kas di Tangan (Kasir)', 'account_type' => 'ASSET'],
            ['account_code' => '1-1002', 'account_name' => 'Kas di Bank / QRIS', 'account_type' => 'ASSET'],
            ['account_code' => '1-1003', 'account_name' => 'Piutang Kasbon Anggota (Payroll Debt)', 'account_type' => 'ASSET'],
            ['account_code' => '1-1004', 'account_name' => 'Persediaan Barang Dagang', 'account_type' => 'ASSET'],
            ['account_code' => '2-2001', 'account_name' => 'Hutang Konsinyasi Mitra UMKM', 'account_type' => 'LIABILITY'],
            ['account_code' => '2-2002', 'account_name' => 'Hutang PPN Masukan/Keluaran', 'account_type' => 'LIABILITY'],
            ['account_code' => '4-4001', 'account_name' => 'Pendapatan Penjualan Reguler', 'account_type' => 'REVENUE'],
            ['account_code' => '4-4002', 'account_name' => 'Pendapatan Komisi Titipan UMKM', 'account_type' => 'REVENUE'],
            ['account_code' => '5-5001', 'account_name' => 'Harga Pokok Penjualan (HPP)', 'account_type' => 'EXPENSE'],
        ];
        foreach ($coas as $coa) {
            ChartOfAccount::updateOrCreate(['account_code' => $coa['account_code']], $coa);
        }

        // ---------------------------------------------------------------------
        // 9. Kupon BBM & Voucher Operasional (Ringkas)
        // ---------------------------------------------------------------------
        $initialCoupons = [
            [
                'serial_number' => '#KPN-BBM-8841',
                'category' => 'bbm',
                'recipient_name' => 'Andi Pratama',
                'recipient_dept' => 'Logistik & Armada',
                'recipient_nik' => '327501002230004',
                'vehicle_no' => 'B 9481 UQ',
                'volume' => '20 Liter',
                'nominal_value' => 200000,
                'status' => 'TERVALIDASI',
                'notes' => 'BBM Pertalite Armada Toko',
            ],
            [
                'serial_number' => '#KPN-BRS-1092',
                'category' => 'beras',
                'recipient_name' => 'Santoso',
                'recipient_dept' => 'Logistik Pabrik',
                'recipient_nik' => '1029384756',
                'vehicle_no' => '-',
                'volume' => '1 Sak (5 kg)',
                'nominal_value' => 65000,
                'status' => 'SIAP_AMBIL',
                'notes' => 'Beras Subsidi Koperasi 5kg',
            ],
        ];
        foreach ($initialCoupons as $kpn) {
            CouponUnit::updateOrCreate(['serial_number' => $kpn['serial_number']], $kpn);
        }
    }
}
