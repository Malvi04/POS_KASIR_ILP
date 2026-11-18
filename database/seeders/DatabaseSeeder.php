<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\ChartOfAccount;
use App\Models\Location;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with clean production accounts and essential configuration only.
     */
    public function run(): void
    {
        // ---------------------------------------------------------------------
        // 1. Clean Production Users (1 Kasir, 1 Manager, 1 Owner)
        // ---------------------------------------------------------------------
        $users = [
            [
                'name' => 'Kasir 1',
                'username' => 'kasir1',
                'email' => 'kasir1@minimarket.ilp',
                'nik' => '202409820001',
                'role' => 'Kasir Utama',
                'pin' => '111222',
                'branch' => 'Pusat',
                'password' => Hash::make('kasir123'),
                'is_active' => true,
            ],
            [
                'name' => 'Manager 1',
                'username' => 'manager1',
                'email' => 'manager1@minimarket.ilp',
                'nik' => '202409820002',
                'role' => 'Manager',
                'pin' => '654321',
                'branch' => 'Pusat',
                'password' => Hash::make('manager123'),
                'is_active' => true,
            ],
            [
                'name' => 'Owner 1',
                'username' => 'owner1',
                'email' => 'owner1@minimarket.ilp',
                'nik' => '202409820003',
                'role' => 'Owner',
                'pin' => '882910',
                'branch' => 'Pusat',
                'password' => Hash::make('owner123'),
                'is_active' => true,
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(['username' => $user['username']], $user);
        }

        // ---------------------------------------------------------------------
        // 2. Essential System & Tax Settings
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
            'branches_list' => ['value' => json_encode(['Pusat']), 'group' => 'store'],
        ];

        foreach ($defaultSettings as $key => $data) {
            AppSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $data['value'], 'group' => $data['group']]
            );
        }

        // ---------------------------------------------------------------------
        // 3. Essential Master Product Categories & Units
        // ---------------------------------------------------------------------
        $categories = [
            'Kebutuhan Pokok',
            'Makanan',
            'Minuman',
            'Snack',
            'Snack UMKM',
            'Pembersih',
            'Kupon BBM',
        ];
        foreach ($categories as $category) {
            ProductCategory::updateOrCreate(['name' => $category]);
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
        foreach ($unitList as $unit) {
            Unit::updateOrCreate(['name' => $unit['name']], $unit);
        }

        // ---------------------------------------------------------------------
        // 4. Chart of Accounts (COA)
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
        // 5. Standard Facility Locations (Gudang Pusat, Toko Pusat, Cabang A, Cabang B)
        // ---------------------------------------------------------------------
        if (!app()->runningUnitTests() && Location::count() === 0) {
            $defaultLocations = [
                ['code' => 'GDG-001', 'name' => 'Gudang Pusat', 'type' => 'WAREHOUSE', 'status' => 'AKTIF', 'is_active' => true],
                ['code' => 'CAB-001', 'name' => 'Toko Pusat', 'type' => 'STORE', 'status' => 'AKTIF', 'is_active' => true],
                ['code' => 'CAB-002', 'name' => 'Cabang A', 'type' => 'STORE', 'status' => 'AKTIF', 'is_active' => true],
                ['code' => 'CAB-003', 'name' => 'Cabang B', 'type' => 'STORE', 'status' => 'AKTIF', 'is_active' => true],
            ];
            foreach ($defaultLocations as $loc) {
                Location::firstOrCreate(['code' => $loc['code']], $loc);
            }
        }
    }
}
