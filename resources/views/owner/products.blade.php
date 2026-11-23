@extends('layouts.owner-admin', ['active' => 'products'])

@section('title', 'Products & Monitoring Stok per Lokasi - ILP POS Owner Console')

@section('header')
<x-admin.header title="Owner Console" searchPlaceholder="Cari nama produk, barcode, SKU, brand..." searchModel="searchQuery">
    <x-slot:leftSlot>
        <div class="flex items-center gap-2.5">
            <a href="{{ url('/owner') }}" class="text-xs font-black uppercase text-slate-800 tracking-wider hover:text-[#0052cc] transition-colors">Owner Console</a>
            <span class="text-slate-300">|</span>
            <span class="text-xs font-bold text-[#0052cc]">Monitoring Stok Multi-Lokasi</span>
            <span class="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-[#0052cc] border border-blue-200 font-bold hidden sm:inline-block">
                Gudang Pusat &bull; Toko Pusat &bull; Cabang A &bull; Cabang B
            </span>
        </div>
    </x-slot:leftSlot>

    <div class="flex items-center gap-2">
        <button type="button" 
                @click="window.dispatchEvent(new CustomEvent('open-add-product-modal'))"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-[13px] font-bold shadow-sm shadow-blue-600/20 transition-all cursor-pointer">
            <span class="material-symbols-rounded text-[18px]">add</span>
            <span>Tambah Master SKU</span>
        </button>
    </div>
</x-admin.header>
@endsection

@section('admin_content')
<div x-data="ownerProductsApp()" 
     @ilp-search.window="searchQuery = $event.detail; onSearchInput()"
     @open-add-product-modal.window="openCreateModal()"
     @pos-inventory-synced.window="if ($event.detail?.source !== 'owner-products') initProducts()"
     class="space-y-6">

    <!-- Page Title & Header Action Row -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-[28px] font-black text-slate-900 tracking-tight">Katalog Produk &amp; Monitoring Stok per Lokasi</h1>
        </div>

        <div class="flex items-center gap-2.5">
            <button type="button" 
                    @click="showScannerModal = true"
                    class="px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs inline-flex items-center gap-2 shadow-2xs transition-all cursor-pointer">
                <span class="material-symbols-rounded text-[18px]">barcode_scanner</span>
                <span>Scan Barcode</span>
            </button>
            <button type="button" 
                    @click.stop="openImportModal()"
                    class="px-3.5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs inline-flex items-center gap-2 shadow-2xs transition-all cursor-pointer"
                    title="Import data master produk dari file Excel (19 Kolom, Dukung Ratusan Ribu Data)">
                <span class="material-symbols-rounded text-[18px]">upload_file</span>
                <span>Import Excel</span>
            </button>
            <button type="button" 
                    @click="exportProductCsv()"
                    class="px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs inline-flex items-center gap-2 shadow-2xs transition-all cursor-pointer"
                    title="Ekspor seluruh katalog master ke file Excel (19 Kolom Master)">
                <span class="material-symbols-rounded text-[18px]">table_chart</span>
                <span>Ekspor Excel</span>
            </button>
        </div>
    </div>

    <!-- Active Filter Indicator Banner if ?filter=low_stock -->
    <template x-if="statusFilter === 'STOK_RENDAH'">
        <div class="p-3.5 rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-between gap-3 text-xs text-amber-900">
            <div class="flex items-center gap-2 font-semibold">
                <span class="material-symbols-rounded text-amber-600 text-[20px]">warning</span>
                <span>Filter Aktif: Menampilkan daftar produk dengan stok menipis (&le; ambang batas aman).</span>
            </div>
            <button type="button" @click="statusFilter = 'all'" class="px-3 py-1 rounded-lg bg-amber-200/80 hover:bg-amber-200 font-bold text-amber-900 transition-colors cursor-pointer">
                Reset Filter
            </button>
        </div>
    </template>

    <!-- 4 Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Total SKU -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">TOTAL SKU AKTIF</span>
                <span class="w-8 h-8 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center">
                    <span class="material-symbols-rounded text-[18px]">inventory_2</span>
                </span>
            </div>
            <div class="text-2xl font-black text-slate-900 font-mono mt-2 tracking-tight">
                <span x-text="products.length">0</span> <span class="text-xs font-bold text-slate-400 font-sans">SKU</span>
            </div>
            <div class="text-xs font-semibold text-slate-500 mt-2 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>Terdaftar di 4 Lokasi Fasilitas</span>
            </div>
        </div>

        <!-- 2. Stok Rendah -->
        <div class="bg-white rounded-2xl p-5 border border-amber-200 shadow-2xs border-l-4 border-l-amber-500">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-amber-700 uppercase tracking-wider">STOK MENIPIS (&le; MIN)</span>
                <span class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <span class="material-symbols-rounded text-[18px]">warning</span>
                </span>
            </div>
            <div class="text-2xl font-black text-amber-600 font-mono mt-2 tracking-tight">
                <span x-text="lowStockCount">0</span> <span class="text-xs font-bold text-amber-600 font-sans">Produk</span>
            </div>
            <div class="text-xs font-semibold text-amber-700 mt-2">
                Perlu koordinasi restock / vendor
            </div>
        </div>

        <!-- 3. Habis / Out of Stock -->
        <div class="bg-white rounded-2xl p-5 border border-rose-200 shadow-2xs border-l-4 border-l-rose-500">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-rose-700 uppercase tracking-wider">STOK KOSONG (0)</span>
                <span class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                    <span class="material-symbols-rounded text-[18px]">production_quantity_limits</span>
                </span>
            </div>
            <div class="text-2xl font-black text-rose-600 font-mono mt-2 tracking-tight">
                <span x-text="outOfStockCount">0</span> <span class="text-xs font-bold text-rose-600 font-sans">Produk</span>
            </div>
            <div class="text-xs font-semibold text-rose-700 mt-2">
                Segera terbitkan PO pengadaan
            </div>
        </div>

        <!-- 4. Nilai Total Inventaris -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">ESTIMASI VALUASI MASTER</span>
                <span class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <span class="material-symbols-rounded text-[18px]">payments</span>
                </span>
            </div>
            <div class="text-xl font-black text-slate-900 font-mono mt-2 tracking-tight" x-text="formatRupiah(totalValuation)">
                Rp 0
            </div>
            <div class="text-xs font-semibold text-emerald-600 mt-2">
                Berdasarkan harga pokok / beli
            </div>
        </div>
    </div>

    <!-- Filter & Table Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden flex flex-col justify-between">
        <div>
            <!-- Quick Location Filter Tabs (Produk Master, Cabang Pusat, Cabang A, Cabang B, Gudang Pusat) -->
            <div class="px-5 pt-3.5 border-b border-slate-100 flex items-center gap-2 overflow-x-auto bg-slate-50/70">
                <button type="button" 
                        @click="selectedLocation = 'all'"
                        class="px-4 py-2.5 text-xs font-bold transition-all border-b-2 inline-flex items-center gap-2 whitespace-nowrap cursor-pointer"
                        :class="selectedLocation === 'all' ? 'border-[#0052cc] text-[#0052cc] bg-white rounded-t-xl shadow-2xs' : 'border-transparent text-slate-600 hover:text-slate-900'">
                    <span class="material-symbols-rounded text-[16px]">warehouse</span>
                    <span>Produk Master (Stok Gudang)</span>
                </button>

                <button type="button" 
                        @click="selectedLocation = 'CAB-001'"
                        class="px-4 py-2.5 text-xs font-bold transition-all border-b-2 inline-flex items-center gap-2 whitespace-nowrap cursor-pointer"
                        :class="selectedLocation === 'CAB-001' ? 'border-[#0052cc] text-[#0052cc] bg-white rounded-t-xl shadow-2xs' : 'border-transparent text-slate-600 hover:text-slate-900'">
                    <span class="material-symbols-rounded text-[16px]">storefront</span>
                    <span>Cabang Pusat (Toko Pusat)</span>
                </button>

                <button type="button" 
                        @click="selectedLocation = 'CAB-002'"
                        class="px-4 py-2.5 text-xs font-bold transition-all border-b-2 inline-flex items-center gap-2 whitespace-nowrap cursor-pointer"
                        :class="selectedLocation === 'CAB-002' ? 'border-[#0052cc] text-[#0052cc] bg-white rounded-t-xl shadow-2xs' : 'border-transparent text-slate-600 hover:text-slate-900'">
                    <span class="material-symbols-rounded text-[16px]">storefront</span>
                    <span>Cabang A</span>
                </button>

                <button type="button" 
                        @click="selectedLocation = 'CAB-003'"
                        class="px-4 py-2.5 text-xs font-bold transition-all border-b-2 inline-flex items-center gap-2 whitespace-nowrap cursor-pointer"
                        :class="selectedLocation === 'CAB-003' ? 'border-[#0052cc] text-[#0052cc] bg-white rounded-t-xl shadow-2xs' : 'border-transparent text-slate-600 hover:text-slate-900'">
                    <span class="material-symbols-rounded text-[16px]">storefront</span>
                    <span>Cabang B</span>
                </button>

                <button type="button" 
                        @click="selectedLocation = 'GDG-001'"
                        class="px-4 py-2.5 text-xs font-bold transition-all border-b-2 inline-flex items-center gap-2 whitespace-nowrap cursor-pointer"
                        :class="selectedLocation === 'GDG-001' ? 'border-indigo-600 text-indigo-700 bg-white rounded-t-xl shadow-2xs' : 'border-transparent text-slate-600 hover:text-slate-900'">
                    <span class="material-symbols-rounded text-[16px]">warehouse</span>
                    <span>Gudang Pusat (Logistik DC)</span>
                </button>
            </div>

            <!-- Transparency & Location Data Rule Callout -->
            <div class="px-5 py-2.5 bg-blue-50/50 border-b border-blue-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-[11px] text-slate-600">
                <div class="flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[16px] text-[#0052cc]">info</span>
                    <span>
                        <strong>Ketentuan Saldo:</strong> Saldo stok Gudang Pusat (DC) terisolasi dan tidak disamakan dengan saldo gerai ritel. Total Stok Master dihitung dari penjumlahan saldo di Gudang Pusat + Toko Pusat + Cabang A + Cabang B tanpa penghitungan ganda.
                    </span>
                </div>
                <span class="font-bold text-[#0052cc] shrink-0" x-text="'Lokasi: ' + getLocationName(selectedLocation)"></span>
            </div>

            <!-- Filter Bar -->
            <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col lg:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 w-full lg:w-auto flex-wrap sm:flex-nowrap">
                    <!-- Category Dropdown -->
                    <div class="relative w-full sm:w-48">
                        <select x-model="selectedCategory" 
                                class="w-full pl-3.5 pr-8 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700 focus:bg-white focus:border-[#0052cc] focus:outline-none appearance-none cursor-pointer">
                            <option value="all">Semua Kategori</option>
                            <template x-for="cat in availableCategories" :key="cat">
                                <option :value="cat" x-text="cat"></option>
                            </template>
                        </select>
                        <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px] pointer-events-none">expand_more</span>
                    </div>

                    <!-- Status Filter -->
                    <div class="relative w-full sm:w-44">
                        <select x-model="statusFilter" 
                                class="w-full pl-3.5 pr-8 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700 focus:bg-white focus:border-[#0052cc] focus:outline-none appearance-none cursor-pointer">
                            <option value="all">Semua Status</option>
                            <option value="TERSEDIA">Tersedia Normal</option>
                            <option value="STOK_RENDAH">Stok Rendah (&le; Min)</option>
                            <option value="HABIS">Habis / Kosong (0)</option>
                            <option value="NEAR_EXPIRED">Mendekati Expired</option>
                        </select>
                        <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px] pointer-events-none">expand_more</span>
                    </div>

                    <!-- Reset Filter Button -->
                    <button type="button" 
                            x-show="selectedLocation !== 'all' || selectedCategory !== 'all' || statusFilter !== 'all' || searchQuery !== ''"
                            @click="selectedLocation = 'all'; selectedCategory = 'all'; statusFilter = 'all'; searchQuery = ''"
                            class="px-2.5 py-2 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-100 text-xs font-bold transition-colors cursor-pointer"
                            title="Reset Semua Filter">
                        <span class="material-symbols-rounded text-[18px]">restart_alt</span>
                    </button>
                </div>

                <div class="text-xs text-slate-500 font-medium self-end lg:self-auto">
                    Menampilkan <span class="font-bold text-slate-800" x-text="filteredProducts.length"></span> dari <span class="font-bold text-slate-800" x-text="products.length"></span> produk
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto w-full">
                <table class="w-full text-left border-collapse text-[13px]" style="min-width: 950px;">
                    <thead>
                        <tr class="border-b border-slate-100 text-[10.5px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/50">
                            <th class="py-3.5 px-6">KODE &amp; PRODUK</th>
                            <th class="py-3.5 px-4">LOKASI</th>
                            <th class="py-3.5 px-4">KATEGORI &amp; RAK</th>
                            <th class="py-3.5 px-4 text-center">
                                <span x-text="selectedLocation === 'all' ? 'TOTAL STOK MASTER' : 'SALDO STOK LOKASI'"></span>
                            </th>
                            <th class="py-3.5 px-4 text-center">AMBANG MIN</th>
                            <th class="py-3.5 px-4 text-right">HARGA UMUM</th>
                            <th class="py-3.5 px-4 text-right">HARGA ANGGOTA</th>
                            <th class="py-3.5 px-4 text-center">STATUS</th>
                            <th class="py-3.5 px-6 text-right">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="p in paginatedProducts" :key="p.sku">
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <!-- 1. Produk & SKU -->
                                <td class="py-3 px-6">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-900" x-text="p.name"></span>
                                        <div class="flex items-center gap-2 mt-0.5 text-[11px] text-slate-400">
                                            <span class="font-mono text-slate-600 font-bold" x-text="p.sku"></span>
                                            <span>&bull;</span>
                                            <span x-text="p.brand || 'Umum'"></span>
                                            <span>&bull;</span>
                                            <span class="font-mono text-slate-400" x-text="p.barcode"></span>
                                        </div>
                                    </div>
                                </td>

                                <!-- 2. Lokasi Fisik -->
                                <td class="py-3 px-4">
                                    <template x-if="selectedLocation === 'all'">
                                        <div class="flex flex-col gap-1">
                                            <span class="px-2 py-0.5 rounded text-[10.5px] font-bold bg-blue-50 text-[#0052cc] border border-blue-100 inline-block w-fit">
                                                Multi-Lokasi
                                            </span>
                                            <span class="text-[10px] text-slate-400 font-mono" x-text="'GD: ' + getLocationStock(p, 'GDG-001') + ' | P: ' + getLocationStock(p, 'CAB-001')"></span>
                                        </div>
                                    </template>
                                    <template x-if="selectedLocation !== 'all'">
                                        <span class="px-2 py-0.5 rounded text-[10.5px] font-bold inline-block"
                                              :class="selectedLocation === 'GDG-001' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-slate-100 text-slate-700'"
                                              x-text="getLocationName(selectedLocation)">
                                        </span>
                                    </template>
                                </td>

                                <!-- 3. Kategori & Rak -->
                                <td class="py-3 px-4">
                                    <div class="flex flex-col">
                                        <span class="font-semibold text-slate-700" x-text="p.category"></span>
                                        <span class="text-[11px] text-slate-400 mt-0.5" x-text="p.rackLocation || 'Rak Utama'"></span>
                                    </div>
                                </td>

                                <!-- 4. Saldo Stok (Terpisah & Non-Duplikasi) -->
                                <td class="py-3 px-4 text-center">
                                    <template x-if="selectedLocation === 'all'">
                                        <div class="inline-flex flex-col items-center">
                                            <span class="text-sm font-black font-mono" 
                                                  :class="p.stock <= 0 ? 'text-rose-600' : (p.stock <= p.minStock ? 'text-amber-600' : 'text-slate-800')"
                                                  x-text="p.stock + ' ' + (p.unit || 'Pcs')"></span>
                                            <span class="text-[10px] text-slate-400 font-medium block">Total Konsolidasi</span>
                                        </div>
                                    </template>
                                    <template x-if="selectedLocation !== 'all'">
                                        <div class="inline-flex flex-col items-center">
                                            <span class="text-sm font-black font-mono text-[#0052cc]"
                                                  x-text="getLocationStock(p, selectedLocation) + ' ' + (p.unit || 'Pcs')"></span>
                                            <span class="text-[10px] text-slate-400 font-medium block" x-text="'Master: ' + p.stock"></span>
                                        </div>
                                    </template>
                                </td>

                                <!-- 5. Ambang Batas Min Stok -->
                                <td class="py-3 px-4 text-center font-mono text-slate-600 text-xs">
                                    <span x-text="selectedLocation === 'all' ? (p.minStock + ' ' + (p.unit || 'Pcs')) : (Math.round(p.minStock * 0.3) + ' ' + (p.unit || 'Pcs'))"></span>
                                </td>

                                <!-- 6. Harga Umum -->
                                <td class="py-3 px-4 text-right font-mono font-bold text-slate-800" x-text="formatRupiah(p.price)"></td>

                                <!-- 7. Harga Anggota -->
                                <td class="py-3 px-4 text-right font-mono font-bold text-[#0052cc]" x-text="formatRupiah(p.memberPrice || p.price)"></td>

                                <!-- 8. Status -->
                                <td class="py-3 px-4 text-center">
                                    <template x-if="selectedLocation === 'all'">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold border"
                                              :class="p.stock <= 0 ? 'bg-rose-50 text-rose-700 border-rose-200' : (p.stock <= p.minStock ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200')"
                                              x-text="p.stock <= 0 ? 'HABIS' : (p.stock <= p.minStock ? 'MENIPIS' : 'TERSEDIA')">
                                        </span>
                                    </template>
                                    <template x-if="selectedLocation !== 'all'">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold border"
                                              :class="getLocationStock(p, selectedLocation) <= 0 ? 'bg-rose-50 text-rose-700 border-rose-200' : (getLocationStock(p, selectedLocation) <= Math.round(p.minStock * 0.3) ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200')"
                                              x-text="getLocationStock(p, selectedLocation) <= 0 ? 'HABIS' : (getLocationStock(p, selectedLocation) <= Math.round(p.minStock * 0.3) ? 'MENIPIS' : 'TERSEDIA')">
                                        </span>
                                    </template>
                                </td>

                                <!-- 9. Aksi -->
                                <td class="py-3 px-6 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" 
                                                @click="openDetailModal(p)"
                                                class="p-1.5 rounded-lg text-slate-400 hover:text-[#0052cc] hover:bg-blue-50 transition-colors"
                                                title="Lihat Sebaran Multi-Lokasi">
                                            <span class="material-symbols-rounded text-[18px]">visibility</span>
                                        </button>
                                        <button type="button" 
                                                @click="openEditModal(p)"
                                                class="p-1.5 rounded-lg text-slate-400 hover:text-amber-600 hover:bg-amber-50 transition-colors"
                                                title="Edit Master Data SKU">
                                            <span class="material-symbols-rounded text-[18px]">edit</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <template x-if="products.length === 0">
                            <tr>
                                <td colspan="9" class="py-12 text-center text-slate-400">
                                    <span class="material-symbols-rounded text-3xl mb-1 text-slate-300 block">inventory_2</span>
                                    <p class="font-bold text-sm text-slate-700">Belum ada data produk.</p>
                                    <button type="button" @click="openCreateModal()" class="mt-3 px-4 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-xs font-bold inline-flex items-center gap-1.5 shadow-sm transition-colors cursor-pointer">
                                        <span class="material-symbols-rounded text-[16px]">add</span>
                                        <span>Tambah Master SKU</span>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <template x-if="products.length > 0 && paginatedProducts.length === 0">
                            <tr>
                                <td colspan="9" class="py-12 text-center text-slate-400">
                                    <span class="material-symbols-rounded text-3xl mb-1 text-slate-300 block">search_off</span>
                                    <p class="font-bold text-sm text-slate-700">Tidak ada produk yang cocok dengan pencarian atau filter.</p>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Footer -->
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-500">
            <div>
                Menampilkan <span class="font-bold text-slate-800" x-text="filteredProducts.length ? ((currentPage - 1) * pageSize + 1) + ' - ' + Math.min(currentPage * pageSize, filteredProducts.length) : '0'"></span> dari <span class="font-bold text-slate-800" x-text="filteredProducts.length"></span> produk
            </div>

            <div class="flex items-center gap-1.5">
                <button type="button" 
                        @click="if (currentPage > 1) currentPage--" 
                        :disabled="currentPage <= 1"
                        :class="currentPage <= 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-100 text-slate-700'"
                        class="px-2.5 py-1 rounded-lg border border-slate-200 text-xs font-bold transition-colors cursor-pointer">
                    Sebelumnya
                </button>

                <template x-for="p in totalPages" :key="p">
                    <button type="button" 
                            @click="currentPage = p"
                            :class="currentPage === p ? 'bg-[#0052cc] text-white font-bold' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'"
                            class="w-7 h-7 rounded-lg text-xs font-bold flex items-center justify-center transition-colors cursor-pointer"
                            x-text="p">
                    </button>
                </template>

                <button type="button" 
                        @click="if (currentPage < totalPages) currentPage++" 
                        :disabled="currentPage >= totalPages"
                        :class="currentPage >= totalPages ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-100 text-slate-700'"
                        class="px-2.5 py-1 rounded-lg border border-slate-200 text-xs font-bold transition-colors cursor-pointer">
                    Selanjutnya
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL 1: TAMBAH MASTER SKU BARU (STANDAR 25 KOLOM ILP) -->
    <template x-teleport="body">
        <div x-show="showCreateModal" 
             x-transition.opacity
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4"
             style="display: none;">
            <div @click.away="showCreateModal = false"
                 class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-4xl w-full p-6 space-y-4 max-h-[92vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-blue-50 text-[#0052cc] flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]">add_circle</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900 tracking-tight">Tambah Master SKU Baru (Standar 19 Kolom Master)</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Daftarkan SKU baru dengan form master lengkap sesuai struktur 19 kolom master produk.</p>
                        </div>
                    </div>
                    <button type="button" @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <form @submit.prevent="saveNewProduct()" class="space-y-4 text-xs">
                    <!-- SECTION 1: IDENTITAS & MASTER BARANG -->
                    <div class="p-4 bg-slate-50/70 rounded-2xl border border-slate-200/80 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-200/70 pb-2">
                            <span class="text-[11px] font-black uppercase text-slate-800 tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[17px] text-[#0052cc]">badge</span>
                                <span>1. Identitas &amp; Master Barang (ILP)</span>
                            </span>
                            <span class="text-[10.5px] font-mono text-slate-400">Kolom 1-6, 19, 21</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="md:col-span-3">
                                <label class="block font-bold text-slate-700 mb-1">Nama Produk Lengkap <span class="text-rose-500">*</span></label>
                                <input type="text" x-model="formProduct.name" required placeholder="Contoh: Kopi Bubuk Robusta 250g" class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 focus:border-[#0052cc] focus:outline-none font-medium">
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Barcode EAN-13 <span class="text-rose-500">*</span></label>
                                <div class="flex gap-2">
                                    <input type="text" x-model="formProduct.barcode" required placeholder="899..." class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 font-mono font-bold text-slate-800 focus:border-[#0052cc] focus:outline-none">
                                    <button type="button" @click="formProduct.barcode = '899' + Math.floor(1000000000 + Math.random() * 9000000000)" class="px-3 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl font-bold text-[11px] shrink-0 cursor-pointer">
                                        Acak
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">SKU Produk <span class="text-rose-500">*</span></label>
                                <input type="text" x-model="formProduct.sku" required placeholder="PRD-011" class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 font-mono font-bold text-slate-800 uppercase focus:border-[#0052cc] focus:outline-none">
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Barang - ID (ILP)</label>
                                <input type="text" x-model="formProduct.ilp_id" placeholder="ILP-011" class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 font-mono text-slate-800 focus:border-[#0052cc] focus:outline-none">
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Kategori Produk <span class="text-rose-500">*</span></label>
                                <input type="text" x-model="formProduct.category" required placeholder="Minuman / Snack / Sembako" class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 focus:border-[#0052cc] focus:outline-none font-semibold">
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Satuan Unit</label>
                                <select x-model="formProduct.unit" class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 focus:border-[#0052cc] focus:outline-none font-semibold">
                                    <option value="Pcs">Pcs</option>
                                    <option value="Botol">Botol</option>
                                    <option value="Pack">Pack</option>
                                    <option value="Karton">Karton</option>
                                    <option value="Kaleng">Kaleng</option>
                                    <option value="Sachet">Sachet</option>
                                    <option value="Kg">Kg</option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Brand / Produsen</label>
                                <input type="text" x-model="formProduct.brand" placeholder="Contoh: Indofood / Lokal" class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 focus:border-[#0052cc] focus:outline-none">
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Status Barang</label>
                                <select x-model="formProduct.statusBarang" class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 focus:border-[#0052cc] focus:outline-none font-bold">
                                    <option value="TERSEDIA">TERSEDIA</option>
                                    <option value="STOK_RENDAH">STOK_RENDAH</option>
                                    <option value="HABIS">HABIS</option>
                                    <option value="NONAKTIF">NONAKTIF</option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Status Aktif (Barang - Act)</label>
                                <select x-model="formProduct.act" class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 focus:border-[#0052cc] focus:outline-none font-bold">
                                    <option value="Y">Y - Aktif (Bisa Transaksi)</option>
                                    <option value="N">N - Nonaktif</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: HARGA BELI, PPN & MARGIN -->
                    <div class="p-4 bg-amber-50/40 rounded-2xl border border-amber-200/70 space-y-3">
                        <div class="flex items-center justify-between border-b border-amber-200/60 pb-2">
                            <span class="text-[11px] font-black uppercase text-amber-900 tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[17px] text-amber-700">payments</span>
                                <span>2. Harga Beli, PPN &amp; Margin</span>
                            </span>
                            <span class="text-[10.5px] font-mono text-amber-700/70">Kolom 7, 16, 17, 18, 24, 13</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Harga Beli Pokok (HPP) <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 font-bold font-mono">Rp</span>
                                    <input type="number" min="0" step="100" x-model.number="formProduct.costPrice" @input="if(formProduct.costPrice > 0 && formProduct.price >= formProduct.costPrice) { formProduct.up = Number((((formProduct.price - formProduct.costPrice)/formProduct.costPrice)*100).toFixed(1)); }" class="w-full pl-10 pr-3.5 py-2.5 rounded-xl bg-white border border-slate-200 font-mono font-bold text-slate-800 focus:border-[#0052cc] focus:outline-none">
                                </div>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Harga Beli Terakhir (HrgBeliTerakhir)</label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 font-bold font-mono">Rp</span>
                                    <input type="number" min="0" step="100" x-model.number="formProduct.lastCostPrice" class="w-full pl-10 pr-3.5 py-2.5 rounded-xl bg-white border border-slate-200 font-mono font-bold text-slate-800 focus:border-[#0052cc] focus:outline-none">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Kena PPN?</label>
                                    <select x-model="formProduct.ppnChk" @change="formProduct.ppnOut = (formProduct.ppnChk === 'Y' ? 11 : 0)" class="w-full px-3 py-2.5 rounded-xl bg-white border border-slate-200 font-bold text-slate-800 focus:border-[#0052cc] focus:outline-none">
                                        <option value="Y">Y - Kena PPN</option>
                                        <option value="N">N - Non PPN</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Tarif PPN (%)</label>
                                    <div class="relative">
                                        <input type="number" min="0" max="100" x-model.number="formProduct.ppnOut" class="w-full px-3 py-2.5 rounded-xl bg-white border border-slate-200 font-mono font-bold text-slate-800 focus:border-[#0052cc] focus:outline-none">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 font-bold">%</span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Markup Keuntungan (Barang - Up %)</label>
                                <div class="relative">
                                    <input type="number" step="0.5" x-model.number="formProduct.up" @input="if(formProduct.costPrice > 0 && formProduct.up > 0) { formProduct.price = Math.round(formProduct.costPrice * (1 + (formProduct.up/100))); if(!formProduct.memberPrice) formProduct.memberPrice = formProduct.price; }" class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 font-mono font-bold text-slate-800 focus:border-[#0052cc] focus:outline-none">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 font-bold">%</span>
                                </div>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Diskon Cuci Gudang (Barang - Disc %)</label>
                                <div class="relative">
                                    <input type="number" min="0" max="100" x-model.number="formProduct.disc" class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 font-mono font-bold text-slate-800 focus:border-[#0052cc] focus:outline-none">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 font-bold">%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: STRUKTUR HARGA JUAL & GROSIR / TINGKAT -->
                    <div class="p-4 bg-blue-50/50 rounded-2xl border border-blue-200/80 space-y-3">
                        <div class="flex items-center justify-between border-b border-blue-200/70 pb-2">
                            <span class="text-[11px] font-black uppercase text-[#0052cc] tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[17px]">price_change</span>
                                <span>3. Struktur Harga Jual &amp; Ketentuan Grosir / Level</span>
                            </span>
                            <span class="text-[10.5px] font-mono text-blue-600/70">Kolom 8, 9, 10, 11, 12</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Harga Jual Umum (HargaJualK1) <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 font-bold font-mono">Rp</span>
                                    <input type="number" x-model.number="formProduct.price" min="0" required class="w-full pl-10 pr-3.5 py-2.5 rounded-xl bg-white border border-slate-200 font-mono font-bold text-slate-800 focus:border-[#0052cc] focus:outline-none">
                                </div>
                            </div>

                            <div>
                                <label class="block font-bold text-[#0052cc] mb-1">Harga Anggota Koperasi (HargaLevel1)</label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#0052cc] font-bold font-mono">Rp</span>
                                    <input type="number" x-model.number="formProduct.memberPrice" min="0" placeholder="Opsional" class="w-full pl-10 pr-3.5 py-2.5 rounded-xl bg-white border border-slate-200 font-mono font-bold text-[#0052cc] focus:border-[#0052cc] focus:outline-none">
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3 pt-2 border-t border-blue-100">
                            <div>
                                <label class="block font-bold text-slate-600 mb-1">Min Qty 1 (Default)</label>
                                <input type="number" min="1" x-model.number="formProduct.minQty1" class="w-full px-3 py-2 rounded-xl bg-white border border-slate-200 font-mono font-bold text-xs">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-600 mb-1">Min Qty 2 (Grosir 2)</label>
                                <input type="number" min="1" x-model.number="formProduct.minQty2" placeholder="Opsional" class="w-full px-3 py-2 rounded-xl bg-white border border-slate-200 font-mono text-xs">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-600 mb-1">Min Qty 3 (Grosir 3)</label>
                                <input type="number" min="1" x-model.number="formProduct.minQty3" placeholder="Opsional" class="w-full px-3 py-2 rounded-xl bg-white border border-slate-200 font-mono text-xs">
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 4: INVENTARIS, BUFFER STOK & LOKASI DISPLAY -->
                    <div class="p-4 bg-emerald-50/40 rounded-2xl border border-emerald-200/70 space-y-3">
                        <div class="flex items-center justify-between border-b border-emerald-200/60 pb-2">
                            <span class="text-[11px] font-black uppercase text-emerald-900 tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[17px] text-emerald-700">warehouse</span>
                                <span>4. Inventaris, Buffer Stok &amp; Lokasi Display</span>
                            </span>
                            <span class="text-[10.5px] font-mono text-emerald-700/70">Kolom 14, 15 &amp; Rak</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Stok Fisik Total <span class="text-rose-500">*</span></label>
                                <input type="number" x-model.number="formProduct.stock" min="0" required class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 font-mono font-bold text-slate-800 focus:border-[#0052cc] focus:outline-none">
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Buffer Min Stok (StockMin) <span class="text-rose-500">*</span></label>
                                <input type="number" x-model.number="formProduct.minStock" min="0" required class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 font-mono font-bold text-slate-800 focus:border-[#0052cc] focus:outline-none">
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Titik ReOrder</label>
                                <input type="number" min="0" x-model.number="formProduct.reorder" placeholder="20" class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 font-mono font-bold text-slate-800 focus:border-[#0052cc] focus:outline-none">
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Lokasi Rak Display</label>
                                <input type="text" x-model="formProduct.rackLocation" placeholder="Lorong A - Rak 01" class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 focus:border-[#0052cc] focus:outline-none">
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 5: PEMASOK, BATCH & KEDALUWARSA -->
                    <div class="p-4 bg-purple-50/40 rounded-2xl border border-purple-200/70 space-y-3">
                        <div class="flex items-center justify-between border-b border-purple-200/60 pb-2">
                            <span class="text-[11px] font-black uppercase text-purple-900 tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[17px] text-purple-700">inventory</span>
                                <span>5. Pemasok, Kedaluwarsa &amp; Keterangan</span>
                            </span>
                            <span class="text-[10.5px] font-mono text-purple-700/70">Kolom 20, 22, 23, 25 &amp; Batch</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Kode Supplier (KoSup)</label>
                                <input type="text" x-model="formProduct.koSup" placeholder="SUP-001" class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 font-mono text-slate-800 focus:border-[#0052cc] focus:outline-none">
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Nomor Batch / Lot</label>
                                <input type="text" x-model="formProduct.batchLot" placeholder="BATCH-202611-01" class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 font-mono text-slate-800 focus:border-[#0052cc] focus:outline-none">
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Tanggal Kedaluwarsa</label>
                                <input type="date" x-model="formProduct.expiredDate" class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 focus:border-[#0052cc] focus:outline-none">
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Masa Garansi / Retur</label>
                                <div class="relative">
                                    <input type="text" x-model="formProduct.masa" placeholder="30" class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 font-mono text-slate-800 focus:border-[#0052cc] focus:outline-none">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 font-bold">Hari</span>
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block font-bold text-slate-700 mb-1">Keterangan / Dump</label>
                                <input type="text" x-model="formProduct.dump" placeholder="Catatan spesifikasi atau varian produk" class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 focus:border-[#0052cc] focus:outline-none">
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                        <button type="button" :disabled="isSavingProduct" @click="showCreateModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-bold transition-colors disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" :disabled="isSavingProduct" class="px-5 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold transition-colors shadow-2xs flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
                            <span x-show="isSavingProduct" class="material-symbols-rounded text-[15px] animate-spin">progress_activity</span>
                            <span x-text="isSavingProduct ? 'Menyimpan...' : 'Simpan Master SKU'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- MODAL 2: EDIT MASTER SKU -->
    <template x-teleport="body">
        <div x-show="showEditModal" 
             x-transition.opacity
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4"
             style="display: none;">
            <div @click.away="showEditModal = false"
                 class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-black text-slate-900 tracking-tight">Edit Data Master SKU</h3>
                        <p class="text-xs text-slate-500 mt-0.5" x-text="selectedProduct ? selectedProduct.sku + ' - ' + selectedProduct.name : ''"></p>
                    </div>
                    <button type="button" @click="showEditModal = false" class="text-slate-400 hover:text-slate-600">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <form @submit.prevent="updateProduct()" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Produk Lengkap *</label>
                        <input type="text" x-model="formEditProduct.name" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none font-medium">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Brand / Produsen</label>
                            <input type="text" x-model="formEditProduct.brand" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Kategori Produk</label>
                            <input type="text" x-model="formEditProduct.category" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Stok Total Master *</label>
                            <input type="number" x-model.number="formEditProduct.stock" min="0" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 font-mono font-bold text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Batas Minimum *</label>
                            <input type="number" x-model.number="formEditProduct.minStock" min="0" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 font-mono text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Satuan</label>
                            <input type="text" x-model="formEditProduct.unit" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Harga Jual Umum (Rp) *</label>
                            <input type="number" x-model.number="formEditProduct.price" min="0" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 font-mono font-bold text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Harga Anggota Koperasi (Rp)</label>
                            <input type="number" x-model.number="formEditProduct.memberPrice" min="0" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 font-mono font-bold text-[#0052cc] focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Lokasi Rak / Penempatan</label>
                        <input type="text" x-model="formEditProduct.rackLocation" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none">
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                        <button type="button" @click="showEditModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-bold transition-colors">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold transition-colors shadow-2xs">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- MODAL 3: DETAIL PRODUK & SEBARAN STOK MULTI-CABANG -->
    <template x-teleport="body">
        <div x-show="showDetailModal" 
             x-transition.opacity
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4"
             style="display: none;">
            <div @click.away="showDetailModal = false"
                 class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-3xl w-full max-h-[92vh] overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50 shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center font-bold shrink-0">
                            <span class="material-symbols-rounded text-[22px]">inventory_2</span>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900" x-text="selectedProduct ? selectedProduct.name : ''"></h3>
                            <span class="text-[11px] font-mono text-slate-400" x-text="selectedProduct ? 'ID: ' + (selectedProduct.ilp_id || selectedProduct.id) + ' • Barcode: ' + (selectedProduct.barcode || '-') + ' • Kode: ' + selectedProduct.sku : ''"></span>
                        </div>
                    </div>
                    <button type="button" @click="showDetailModal = false" class="text-slate-400 hover:text-slate-600 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs overflow-y-auto flex-1">
                    <!-- 1. Sebaran Stok Multi-Cabang (Produk Master, Cabang Pusat, Cabang A, Cabang B, Gudang Pusat) -->
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black uppercase text-slate-800 tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[16px] text-[#0052cc]">hub</span>
                                <span>Sebaran Stok Multi-Cabang</span>
                            </span>
                            <span class="text-[11px] font-mono font-bold text-[#0052cc]" x-text="'Total Master: ' + (selectedProduct?.masterStock ?? selectedProduct?.stock ?? 0) + ' ' + (selectedProduct?.unit || 'Pcs')"></span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                            <!-- Produk Master -->
                            <div class="p-2.5 rounded-xl bg-blue-50/70 border border-blue-200">
                                <span class="text-[10px] font-bold text-[#0052cc] uppercase block">Produk Master</span>
                                <div class="text-base font-black text-[#0052cc] font-mono mt-0.5" x-text="(selectedProduct?.masterStock ?? selectedProduct?.stock ?? 0) + ' ' + (selectedProduct?.unit || 'Pcs')"></div>
                                <span class="text-[9.5px] text-slate-400">Total Konsolidasi</span>
                            </div>
                            <!-- Cabang Pusat -->
                            <div class="p-2.5 rounded-xl bg-white border border-slate-200">
                                <span class="text-[10px] font-bold text-slate-700 uppercase block">Cabang Pusat</span>
                                <div class="text-base font-black text-slate-900 font-mono mt-0.5" x-text="getLocationStock(selectedProduct, 'CAB-001') + ' ' + (selectedProduct?.unit || 'Pcs')"></div>
                                <span class="text-[9.5px] text-slate-400">Toko Pusat</span>
                            </div>
                            <!-- Cabang A -->
                            <div class="p-2.5 rounded-xl bg-white border border-slate-200">
                                <span class="text-[10px] font-bold text-slate-700 uppercase block">Cabang A</span>
                                <div class="text-base font-black text-slate-900 font-mono mt-0.5" x-text="getLocationStock(selectedProduct, 'CAB-002') + ' ' + (selectedProduct?.unit || 'Pcs')"></div>
                                <span class="text-[9.5px] text-slate-400">Gerai Ritel A</span>
                            </div>
                            <!-- Cabang B -->
                            <div class="p-2.5 rounded-xl bg-white border border-slate-200">
                                <span class="text-[10px] font-bold text-slate-700 uppercase block">Cabang B</span>
                                <div class="text-base font-black text-slate-900 font-mono mt-0.5" x-text="getLocationStock(selectedProduct, 'CAB-003') + ' ' + (selectedProduct?.unit || 'Pcs')"></div>
                                <span class="text-[9.5px] text-slate-400">Gerai Ritel B</span>
                            </div>
                        </div>
                        <!-- Gudang Pusat DC row if exists -->
                        <div class="p-2.5 rounded-xl bg-indigo-50/50 border border-indigo-100 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-rounded text-[16px] text-indigo-700">warehouse</span>
                                <span class="font-bold text-slate-700">Gudang Pusat (Logistik DC):</span>
                            </div>
                            <span class="font-mono font-black text-indigo-900 text-sm" x-text="getLocationStock(selectedProduct, 'GDG-001') + ' ' + (selectedProduct?.unit || 'Pcs')"></span>
                        </div>
                    </div>

                    <!-- 2. TABEL LENGKAP DETAIL BARANG (STANDAR 19 KOLOM MASTER PRODUK) -->
                    <div class="border border-slate-200 rounded-2xl overflow-hidden shadow-2xs">
                        <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                            <span class="text-xs font-black uppercase text-slate-700 tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[16px] text-emerald-600">table_rows</span>
                                <span>Tabel Detail Barang (Struktur 19 Kolom Master Produk)</span>
                            </span>
                            <span class="text-[10.5px] font-mono text-slate-400">19 Kolom Terstandarisasi</span>
                        </div>
                        <div class="max-h-72 overflow-y-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead class="bg-slate-100 text-slate-600 font-bold sticky top-0 border-b border-slate-200 text-[11px]">
                                    <tr>
                                        <th class="py-2 px-3 w-12 text-center">No</th>
                                        <th class="py-2 px-4 w-52">Nama Kolom (Field)</th>
                                        <th class="py-2 px-4">Nilai Data Produk</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-slate-400 font-mono">1</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">IdProduk</td><td class="py-2 px-4 font-mono font-bold text-slate-900" x-text="selectedProduct?.id_produk || selectedProduct?.ilp_id || selectedProduct?.id || '-'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-slate-400 font-mono">2</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">SKU</td><td class="py-2 px-4 font-mono font-bold text-slate-900" x-text="selectedProduct?.sku || '-'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-slate-400 font-mono">3</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">Kode Barcode</td><td class="py-2 px-4 font-mono font-bold text-slate-900" x-text="selectedProduct?.kode_barcode || selectedProduct?.barcode || '-'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-slate-400 font-mono">4</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">KodeProduk</td><td class="py-2 px-4 font-mono font-bold text-slate-900" x-text="selectedProduct?.kode_produk || selectedProduct?.sku || '-'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-slate-400 font-mono">5</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">QtyProduk</td><td class="py-2 px-4 font-mono font-bold text-slate-900" x-text="(selectedProduct?.qty_produk !== undefined ? selectedProduct.qty_produk : (selectedProduct?.master_stock ?? selectedProduct?.stock ?? 0)) + ' ' + (selectedProduct?.satuan_unit || selectedProduct?.unit || 'Pcs')"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-slate-400 font-mono">6</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">NamaProduk</td><td class="py-2 px-4 font-bold text-slate-900" x-text="selectedProduct?.nama_produk || selectedProduct?.name || '-'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-slate-400 font-mono">7</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">SatuanUnit</td><td class="py-2 px-4 font-bold text-slate-800" x-text="selectedProduct?.satuan_unit || selectedProduct?.unit || 'Pcs'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-slate-400 font-mono">8</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">HargaBeli</td><td class="py-2 px-4 font-mono font-bold text-slate-800" x-text="formatRupiah(selectedProduct?.harga_beli ?? selectedProduct?.costPrice ?? selectedProduct?.cost_price)"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-slate-400 font-mono">9</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">HargaJualUmum</td><td class="py-2 px-4 font-mono font-bold text-emerald-700" x-text="formatRupiah(selectedProduct?.harga_jual_umum ?? selectedProduct?.price)"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-slate-400 font-mono">10</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">HargaJualMember</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]" x-text="formatRupiah(selectedProduct?.harga_jual_member ?? selectedProduct?.memberPrice ?? selectedProduct?.member_price ?? selectedProduct?.price)"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-slate-400 font-mono">11</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">ProdukDisc</td><td class="py-2 px-4 font-mono" x-text="(selectedProduct?.produk_disc ?? selectedProduct?.disc ?? selectedProduct?.clearance_discount_percent ?? 0) + '%'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-slate-400 font-mono">12</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">MinimumStock</td><td class="py-2 px-4 font-mono font-bold text-amber-700" x-text="(selectedProduct?.minimum_stock ?? selectedProduct?.min_stock ?? 5) + ' ' + (selectedProduct?.satuan_unit || selectedProduct?.unit || 'Pcs')"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-slate-400 font-mono">13</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">PPNOut</td><td class="py-2 px-4 font-mono" x-text="(selectedProduct?.ppn_out ?? selectedProduct?.tax_rate ?? 11) + '%'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-slate-400 font-mono">14</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">PPNChk</td><td class="py-2 px-4 font-mono font-bold" :class="(selectedProduct?.ppn_chk || (selectedProduct?.is_taxable ? 'Y' : 'N')) === 'Y' ? 'text-emerald-700' : 'text-slate-500'" x-text="selectedProduct?.ppn_chk || (selectedProduct?.is_taxable ? 'Y' : 'N')"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-slate-400 font-mono">15</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">HargaBeliTerakhir</td><td class="py-2 px-4 font-mono font-bold text-slate-800" x-text="formatRupiah(selectedProduct?.harga_beli_terakhir ?? selectedProduct?.last_cost_price ?? selectedProduct?.cost_price)"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-slate-400 font-mono">16</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">StatusBarang</td><td class="py-2 px-4 font-bold" x-text="selectedProduct?.status_barang || selectedProduct?.stock_status || selectedProduct?.status || 'TERSEDIA'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-slate-400 font-mono">17</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">KodeSuplier</td><td class="py-2 px-4 font-mono" x-text="selectedProduct?.kode_suplier || selectedProduct?.ko_sup || selectedProduct?.supplier_code || '-'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-slate-400 font-mono">18</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">ManufacturingDate</td><td class="py-2 px-4 font-mono" x-text="selectedProduct?.manufacturing_date || '-'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-slate-400 font-mono">19</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">ExpDate</td><td class="py-2 px-4 font-mono" x-text="selectedProduct?.exp_date || selectedProduct?.expired_date || '-'"></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50 shrink-0">
                    <button type="button" 
                            @click="showDetailModal = false; openEditModal(selectedProduct)"
                            class="px-4 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-xs font-bold transition-colors cursor-pointer inline-flex items-center gap-1.5 shadow-2xs">
                        <span class="material-symbols-rounded text-[16px]">edit</span>
                        <span>Edit Master SKU</span>
                    </button>
                    <button type="button" 
                            @click="showDetailModal = false"
                            class="px-5 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 text-xs font-bold transition-colors cursor-pointer">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- MODAL 6: IMPORT EXCEL MASTER PRODUK (25 KOLOM ILP) -->
    <template x-teleport="body">
        <div x-show="showImportModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs"
             style="display: none;">
            <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 w-full max-w-4xl max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150"
                 @click.away="if (importStep !== 'processing') showImportModal = false">
                
                <!-- Modal Header -->
                <div class="px-6 py-4.5 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center text-emerald-400">
                            <span class="material-symbols-rounded text-[22px]">upload_file</span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-white">Import Master Produk Excel</h3>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-500/20 border border-emerald-400/30 text-emerald-300">Format 19 Kolom (High-Volume Ready)</span>
                            </div>
                            <p class="text-xs text-slate-300 mt-0.5">Unggah dan sinkronisasi master barang hingga ratusan ribu data secara cepat dengan format 19 kolom</p>
                        </div>
                    </div>
                    <button type="button" 
                            x-show="importStep !== 'processing'"
                            @click="showImportModal = false" 
                            class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white flex items-center justify-center transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">close</span>
                    </button>
                </div>

                <!-- Modal Body: Step by Step -->
                <div class="flex-1 overflow-y-auto p-6 space-y-5">
                    
                    <!-- STEP 1: UPLOAD FILE -->
                    <div x-show="importStep === 'upload'" class="space-y-5">
                        <div class="p-4 bg-emerald-50/80 border border-emerald-200 rounded-2xl text-emerald-950 flex items-start gap-3">
                            <span class="material-symbols-rounded text-emerald-600 text-[22px] shrink-0 mt-0.5">verified</span>
                            <div class="text-xs space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-emerald-900 block text-sm">Standar Format Master Barang Gudang (19 Kolom)</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-emerald-200 text-emerald-900">Kapasitas Ratusan Ribu Data</span>
                                </div>
                                <p class="text-emerald-800 leading-relaxed">
                                    File Excel / CSV wajib memuat <strong>19 kolom terstandarisasi</strong>: <code>IdProduk, SKU, Kode Barcode, KodeProduk, QtyProduk, NamaProduk, SatuanUnit, HargaBeli, HargaJualUmum, HargaJualMember, ProdukDisc, MinimumStock, PPNOut, PPNChk, HargaBeliTerakhir, StatusBarang, KodeSuplier, ManufacturingDate, ExpDate</code>.
                                </p>
                            </div>
                        </div>

                        <!-- Drag and drop / File Picker Area -->
                        <div class="border-2 border-dashed rounded-2xl p-8 text-center transition-all cursor-pointer relative select-none"
                             :class="importFile ? 'border-emerald-400 bg-emerald-50/30' : (isDragging ? 'border-[#0052cc] bg-blue-50/50' : 'border-slate-300 hover:border-[#0052cc] hover:bg-slate-50/50')"
                             @click="triggerFileInput()"
                             @dragenter.prevent="isDragging = true"
                             @dragover.prevent="isDragging = true"
                             @dragleave.prevent="isDragging = false"
                             @drop.prevent="handleFileDrop($event)">
                            <input type="file" 
                                   id="excelFileInputOwner"
                                   x-ref="excelFileInputOwner" 
                                   @click.stop=""
                                   @change="onFileSelected($event)" 
                                   accept=".xlsx,.xls,.csv" 
                                   class="hidden">

                            <template x-if="!importFile">
                                <div class="space-y-3 pointer-events-none">
                                    <div class="w-14 h-14 mx-auto rounded-2xl bg-blue-50 text-[#0052cc] flex items-center justify-center">
                                        <span class="material-symbols-rounded text-[32px]">cloud_upload</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-800">Pilih file Excel Master Barang atau tarik file ke sini</p>
                                        <p class="text-xs text-slate-400 mt-1">Mendukung format .XLSX, .XLS, atau .CSV (Maksimal 25MB • Mendukung Ratusan Ribu Data)</p>
                                    </div>
                                    <button type="button" @click.stop="triggerFileInput()" class="pointer-events-auto px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors inline-flex items-center gap-1.5 cursor-pointer">
                                        <span class="material-symbols-rounded text-[16px]">folder_open</span>
                                        <span>Telusuri File di Komputer</span>
                                    </button>
                                </div>
                            </template>

                            <template x-if="importFile">
                                <div class="flex items-center justify-between p-4 bg-white rounded-xl border border-emerald-200 shadow-2xs text-left" @click.stop="">
                                    <div class="flex items-center gap-3.5">
                                        <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                            <span class="material-symbols-rounded text-[28px]">description</span>
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-800 text-sm" x-text="importFileName"></p>
                                            <p class="text-xs text-slate-400 font-mono mt-0.5" x-text="(importFile.size / 1024).toFixed(1) + ' KB'"></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" 
                                                @click.stop="resetFileInput()" 
                                                class="px-3 py-1.5 rounded-lg text-xs font-bold text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer">
                                            Ganti File
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Template Download & Safety Info Row -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2 border-t border-slate-100">
                            <div class="flex items-center gap-2 text-xs text-slate-500">
                                <span class="material-symbols-rounded text-blue-600 text-[18px]">info</span>
                                <span>Perlu contoh format struktur 19 kolom?</span>
                            </div>
                            <button type="button" 
                                    @click="downloadILPTemplate()"
                                    class="text-xs font-bold text-[#0052cc] hover:text-[#0043a8] hover:underline flex items-center gap-1 cursor-pointer">
                                <span class="material-symbols-rounded text-[16px]">download</span>
                                <span>Unduh Template Format Master (19 Kolom)</span>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 2: INVALID STRUCTURE -->
                    <div x-show="importStep === 'invalid_structure'" style="display: none;" class="space-y-5">
                        <div class="p-5 bg-rose-50 border border-rose-200 rounded-2xl flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-rose-500 text-white flex items-center justify-center shrink-0 mt-0.5 shadow-xs">
                                <span class="material-symbols-rounded text-[28px]">error</span>
                            </div>
                            <div class="flex-1 space-y-2">
                                <h4 class="font-bold text-rose-900 text-base">Format Kolom Excel Tidak Sesuai Standar Master Produk</h4>
                                <p class="text-xs text-rose-800 leading-relaxed" x-text="validationErrorDetails?.message || 'Jumlah atau nama kolom file yang diunggah tidak sesuai dengan 19 kolom master barang.'"></p>
                                <div class="flex items-center gap-4 text-xs font-semibold text-rose-700 pt-1">
                                    <span>Kolom Terdeteksi: <strong class="font-mono text-sm" x-text="validationErrorDetails?.actual_count || 0"></strong></span>
                                    <span>Kolom Wajib: <strong class="font-mono text-sm" x-text="validationErrorDetails?.expected_count || 19"></strong></span>
                                </div>
                            </div>
                        </div>

                        <template x-if="validationErrorDetails?.missing_columns && validationErrorDetails.missing_columns.length > 0">
                            <div class="bg-white p-4 rounded-xl border border-rose-200 shadow-2xs space-y-2">
                                <span class="text-xs font-extrabold text-rose-700 uppercase tracking-wider block">Kolom Wajib yang Hilang / Belum Ada (<span x-text="validationErrorDetails.missing_columns.length"></span> Kolom):</span>
                                <div class="flex flex-wrap gap-1.5 max-h-40 overflow-y-auto p-1">
                                    <template x-for="(col, idx) in validationErrorDetails.missing_columns" :key="idx">
                                        <span class="px-2.5 py-1 rounded-md bg-rose-100 text-rose-800 text-[11px] font-mono font-bold" x-text="col"></span>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <template x-if="validationErrorDetails?.unexpected_columns && validationErrorDetails.unexpected_columns.length > 0">
                            <div class="bg-white p-4 rounded-xl border border-amber-200 shadow-2xs space-y-2">
                                <span class="text-xs font-extrabold text-amber-700 uppercase tracking-wider block">Kolom Tak Dikenal / Di Luar Format Standar (<span x-text="validationErrorDetails.unexpected_columns.length"></span> Kolom):</span>
                                <div class="flex flex-wrap gap-1.5 max-h-32 overflow-y-auto p-1">
                                    <template x-for="(col, idx) in validationErrorDetails.unexpected_columns" :key="idx">
                                        <span class="px-2.5 py-1 rounded-md bg-amber-100 text-amber-800 text-[11px] font-mono font-bold" x-text="col"></span>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-600 flex items-start gap-2.5">
                            <span class="material-symbols-rounded text-slate-400 text-[18px] shrink-0 mt-0.5">shield</span>
                            <p class="leading-relaxed">
                                <strong>Sistem Menolak Import Ini:</strong> Untuk mencegah data corrupt atau salah kolom, seluruh data dibatalkan dan tidak ada yang masuk ke database. Silakan gunakan template resmi atau perbaiki nama kolom pada file Excel Anda.
                            </p>
                        </div>
                    </div>

                    <!-- STEP 3: PREVIEW DATA SEBELUM IMPORT -->
                    <div x-show="importStep === 'preview'" style="display: none;" class="space-y-5">
                        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center">
                                    <span class="material-symbols-rounded text-[22px]">check_circle</span>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-emerald-950 text-sm">Struktur 19 Kolom Valid & Terverifikasi</h4>
                                        <span x-show="validationData?.summary?.is_high_volume" class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-blue-100 text-[#0052cc] border border-blue-200">Mode Bulk Import (Ratusan Ribu Data)</span>
                                    </div>
                                    <p class="text-xs text-emerald-800" x-text="'File: ' + (validationData?.file_name || importFileName)"></p>
                                </div>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300">Siap Diimport</span>
                        </div>

                        <!-- Statistics Grid (Interactive Filter Cards) -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 transition-all cursor-pointer hover:border-slate-400"
                                 @click="importPreviewFilter = 'all'; importPreviewPage = 1"
                                 :class="importPreviewFilter === 'all' ? 'ring-2 ring-slate-700 bg-white shadow-xs' : ''">
                                <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Total Baris File</span>
                                <div class="text-lg font-black text-slate-800 font-mono mt-0.5" x-text="validationData?.summary?.total_rows || 0"></div>
                                <div class="text-[11px] text-slate-500 mt-1">Semua baris terdeteksi</div>
                            </div>
                            <div class="bg-emerald-50/70 p-3.5 rounded-xl border border-emerald-200 transition-all cursor-pointer hover:border-emerald-400"
                                 @click="importPreviewFilter = 'new'; importPreviewPage = 1"
                                 :class="importPreviewFilter === 'new' ? 'ring-2 ring-emerald-500 bg-emerald-50 shadow-xs' : ''">
                                <span class="text-[10px] font-extrabold text-emerald-700 uppercase tracking-wider block">Data Valid</span>
                                <div class="text-lg font-black text-emerald-700 font-mono mt-0.5" x-text="validationData?.summary?.valid_rows || 0"></div>
                                <div class="text-[11px] text-emerald-700 mt-1">Siap diproses sistem</div>
                            </div>
                            <div class="bg-blue-50/70 p-3.5 rounded-xl border border-blue-200 transition-all cursor-pointer hover:border-[#0052cc]"
                                 @click="importPreviewFilter = (importPreviewFilter === 'existing' ? 'all' : 'existing'); importPreviewPage = 1"
                                 :class="importPreviewFilter === 'existing' ? 'ring-2 ring-[#0052cc] bg-blue-100/60 shadow-xs' : ''">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-extrabold text-[#0052cc] uppercase tracking-wider block">Produk Terdaftar</span>
                                    <span class="text-[10px] px-1.5 py-0.2 rounded bg-blue-200 text-blue-900 font-bold" x-show="(validationData?.summary?.existing_in_db_count || 0) > 0">Klik Filter</span>
                                </div>
                                <div class="text-lg font-black text-[#0052cc] font-mono mt-0.5" x-text="validationData?.summary?.existing_in_db_count || 0"></div>
                                <template x-if="validationData?.existing_rows_list?.length > 0">
                                    <div class="text-[11px] text-blue-900 font-semibold mt-1 truncate" :title="validationData.existing_rows_list.map(e => 'Baris ' + e.row).join(', ')">
                                        <span>Baris: </span>
                                        <span class="font-mono text-[#0052cc]" x-text="validationData.existing_rows_list.slice(0, 4).map(e => e.row).join(', ') + (validationData.existing_rows_list.length > 4 ? ' (+' + (validationData.existing_rows_list.length - 4) + ')' : '')"></span>
                                    </div>
                                </template>
                                <template x-if="!validationData?.existing_rows_list?.length">
                                    <div class="text-[11px] text-slate-500 mt-1">Semua produk baru</div>
                                </template>
                            </div>
                            <div class="bg-rose-50/70 p-3.5 rounded-xl border border-rose-200 transition-all cursor-pointer hover:border-rose-400"
                                 @click="importPreviewFilter = (importPreviewFilter === 'error' ? 'all' : 'error'); importPreviewPage = 1"
                                 :class="importPreviewFilter === 'error' ? 'ring-2 ring-rose-500 bg-rose-100/60 shadow-xs' : ''">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-extrabold text-rose-700 uppercase tracking-wider block">Baris Error</span>
                                    <span class="text-[10px] px-1.5 py-0.2 rounded bg-rose-200 text-rose-900 font-bold" x-show="(validationData?.summary?.error_rows || 0) > 0">Klik Filter</span>
                                </div>
                                <div class="text-lg font-black text-rose-700 font-mono mt-0.5" x-text="validationData?.summary?.error_rows || 0"></div>
                                <template x-if="validationData?.error_rows_list?.length > 0">
                                    <div class="text-[11px] text-rose-900 font-semibold mt-1 truncate" :title="validationData.error_rows_list.map(e => 'Baris ' + e.row + ': ' + e.error).join('; ')">
                                        <span>Baris: </span>
                                        <span class="font-mono text-rose-700" x-text="validationData.error_rows_list.slice(0, 4).map(e => e.row).join(', ') + (validationData.error_rows_list.length > 4 ? ' (+' + (validationData.error_rows_list.length - 4) + ')' : '')"></span>
                                    </div>
                                </template>
                                <template x-if="!validationData?.error_rows_list?.length">
                                    <div class="text-[11px] text-emerald-600 font-medium mt-1 flex items-center gap-1">
                                        <span class="material-symbols-rounded text-[14px]">check_circle</span>
                                        <span>Tidak ada error</span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Card Rincian Baris Error (Jika Ada Error) -->
                        <template x-if="validationData?.error_rows_list?.length > 0">
                            <div class="bg-rose-50 border border-rose-300 rounded-xl p-3.5 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-rose-900 flex items-center gap-1.5">
                                        <span class="material-symbols-rounded text-rose-600 text-[18px]">error</span>
                                        <span>Daftar Rincian Baris Error (<span x-text="validationData.error_rows_list.length"></span> Baris Bermasalah):</span>
                                    </span>
                                    <button type="button" @click="importPreviewFilter = (importPreviewFilter === 'error' ? 'all' : 'error'); importPreviewPage = 1"
                                            class="text-[11px] font-bold text-rose-700 hover:text-rose-900 underline cursor-pointer">
                                        <span x-text="importPreviewFilter === 'error' ? 'Tampilkan Semua Baris' : 'Filter Tabel ke Baris Error Saja'"></span>
                                    </button>
                                </div>
                                <div class="max-h-36 overflow-y-auto space-y-1.5 pr-1 divide-y divide-rose-200">
                                    <template x-for="err in validationData.error_rows_list" :key="err.row">
                                        <div class="text-[11px] pt-1.5 flex items-start gap-2">
                                            <span class="px-2 py-0.5 rounded bg-rose-200 text-rose-900 font-mono font-bold shrink-0" x-text="'Baris ' + err.row"></span>
                                            <span class="font-bold text-slate-800" x-text="(err.sku !== '-' ? err.sku : (err.nama_produk !== '-' ? err.nama_produk : 'Data Kosong')) + ' — '"></span>
                                            <span class="text-rose-700 font-semibold" x-text="err.error || (err.messages ? err.messages.join(', ') : 'Format data tidak valid')"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <!-- Card Rincian Produk Terdaftar (Jika Ada Produk Terdaftar di Database) -->
                        <template x-if="validationData?.existing_rows_list?.length > 0">
                            <div class="bg-blue-50 border border-blue-200 rounded-xl p-3.5 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-blue-950 flex items-center gap-1.5">
                                        <span class="material-symbols-rounded text-[#0052cc] text-[18px]">verified</span>
                                        <span>Daftar Baris Produk Sudah Terdaftar di Database (<span x-text="validationData.existing_rows_list.length"></span> Produk):</span>
                                    </span>
                                    <button type="button" @click="importPreviewFilter = (importPreviewFilter === 'existing' ? 'all' : 'existing'); importPreviewPage = 1"
                                            class="text-[11px] font-bold text-[#0052cc] hover:underline cursor-pointer">
                                        <span x-text="importPreviewFilter === 'existing' ? 'Tampilkan Semua Baris' : 'Filter Tabel ke Produk Terdaftar Saja'"></span>
                                    </button>
                                </div>
                                <div class="text-xs text-blue-900 bg-white p-2.5 rounded-lg border border-blue-200 max-h-32 overflow-y-auto leading-relaxed">
                                    <div class="font-semibold text-slate-600 mb-1">Nomor Baris di File Excel:</div>
                                    <div class="flex flex-wrap gap-1.5 font-mono text-[11px]">
                                        <template x-for="ex in validationData.existing_rows_list" :key="ex.row">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-100 text-blue-900 border border-blue-200"
                                                  :title="ex.nama_produk">
                                                <strong x-text="'Baris ' + ex.row"></strong>
                                                <span class="text-blue-600" x-text="'(' + ex.sku + ')'"></span>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Duplicate Handling Selector -->
                        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs space-y-2">
                            <label class="text-xs font-bold text-slate-700 block">Tindakan Jika SKU / Barcode Sudah Terdaftar di Database:</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all"
                                       :class="duplicateAction === 'update' ? 'border-[#0052cc] bg-blue-50/30' : 'border-slate-200 hover:bg-slate-50'">
                                    <input type="radio" name="duplicateActionOwner" value="update" x-model="duplicateAction" class="mt-0.5 text-[#0052cc] focus:ring-[#0052cc]">
                                    <div class="text-xs">
                                        <span class="font-bold text-slate-800 block">Perbarui Data (Update)</span>
                                        <span class="text-slate-500">Perbarui harga, nama, PPN, dan atribut 19 kolom master. Stok tetap utuh.</span>
                                    </div>
                                </label>
                                <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all"
                                       :class="duplicateAction === 'skip' ? 'border-[#0052cc] bg-blue-50/30' : 'border-slate-200 hover:bg-slate-50'">
                                    <input type="radio" name="duplicateActionOwner" value="skip" x-model="duplicateAction" class="mt-0.5 text-[#0052cc] focus:ring-[#0052cc]">
                                    <div class="text-xs">
                                        <span class="font-bold text-slate-800 block">Lewati (Skip Duplikat)</span>
                                        <span class="text-slate-500">Hanya tambahkan produk baru, jangan ubah data produk yang sudah terdaftar.</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Preview Table with High-Volume Pagination and Color-Coded Rows -->
                        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-2xs space-y-0">
                            <div class="p-3 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2.5">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-extrabold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                        <span class="material-symbols-rounded text-[16px] text-slate-500">table_view</span>
                                        <span>Pratinjau Data (Struktur 19 Kolom Standar)</span>
                                    </span>
                                    <span class="text-[11px] font-mono px-2 py-0.5 rounded-full bg-slate-200 text-slate-700 font-bold"
                                          x-text="(getFilteredPreviewRows().length) + ' dari ' + (validationData?.summary?.total_rows || validationData?.preview_rows?.length || 0) + ' baris'"></span>
                                </div>

                                <!-- Filter Buttons, Search & Per Page -->
                                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                                    <!-- Search input -->
                                    <div class="relative">
                                        <input type="text" x-model="importPreviewSearch" @input="importPreviewPage = 1" placeholder="Cari baris/SKU/nama..."
                                               class="h-7 text-[11px] pl-6 pr-2 bg-white border border-slate-300 rounded-lg text-slate-700 focus:ring-1 focus:ring-[#0052cc] w-36">
                                        <span class="material-symbols-rounded absolute left-1.5 top-1.5 text-slate-400 text-[14px]">search</span>
                                    </div>

                                    <!-- Filter tabs -->
                                    <button type="button" @click="importPreviewFilter = 'all'; importPreviewPage = 1"
                                            class="px-2 py-1 rounded-lg font-bold text-[11px] transition-colors cursor-pointer"
                                            :class="importPreviewFilter === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                                        Semua
                                    </button>
                                    <button type="button" @click="importPreviewFilter = 'error'; importPreviewPage = 1"
                                            class="px-2 py-1 rounded-lg font-bold text-[11px] transition-colors cursor-pointer flex items-center gap-1"
                                            :class="importPreviewFilter === 'error' ? 'bg-rose-600 text-white' : 'bg-rose-50 text-rose-700 hover:bg-rose-100'">
                                        <span>Baris Error</span>
                                        <span class="px-1 py-0.2 rounded-full text-[10px]"
                                              :class="importPreviewFilter === 'error' ? 'bg-rose-800 text-white' : 'bg-rose-200 text-rose-800'"
                                              x-text="validationData?.summary?.error_rows || 0"></span>
                                    </button>
                                    <button type="button" @click="importPreviewFilter = 'existing'; importPreviewPage = 1"
                                            class="px-2 py-1 rounded-lg font-bold text-[11px] transition-colors cursor-pointer flex items-center gap-1"
                                            :class="importPreviewFilter === 'existing' ? 'bg-[#0052cc] text-white' : 'bg-blue-50 text-[#0052cc] hover:bg-blue-100'">
                                        <span>Produk Terdaftar</span>
                                        <span class="px-1 py-0.2 rounded-full text-[10px]"
                                              :class="importPreviewFilter === 'existing' ? 'bg-blue-900 text-white' : 'bg-blue-200 text-blue-900'"
                                              x-text="validationData?.summary?.existing_in_db_count || 0"></span>
                                    </button>
                                    <button type="button" @click="importPreviewFilter = 'new'; importPreviewPage = 1"
                                            class="px-2 py-1 rounded-lg font-bold text-[11px] transition-colors cursor-pointer flex items-center gap-1"
                                            :class="importPreviewFilter === 'new' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'">
                                        <span>Baru</span>
                                    </button>

                                    <!-- Per Page Selector -->
                                    <div class="flex items-center gap-1 ml-1 text-slate-500 text-[11px]">
                                        <span>Baris:</span>
                                        <select x-model="importPreviewPerPage" @change="importPreviewPage = 1"
                                                class="text-[11px] py-0.5 px-2 bg-white border border-slate-300 rounded-md font-semibold text-slate-700 focus:ring-1 focus:ring-[#0052cc]">
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                            <option value="250">250</option>
                                            <option value="500">500</option>
                                            <option value="all">Semua</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Table Scroll Container -->
                            <div class="max-h-80 overflow-x-auto overflow-y-auto">
                                <table class="w-full text-left text-xs whitespace-nowrap border-collapse">
                                    <thead class="bg-slate-100 text-slate-600 font-bold sticky top-0 border-b border-slate-200 text-[11px] z-10">
                                        <tr>
                                            <th class="p-2.5 text-center">Baris</th>
                                            <th class="p-2.5 font-mono text-[#0052cc]">IdProduk</th>
                                            <th class="p-2.5 font-mono">SKU</th>
                                            <th class="p-2.5 font-mono">Kode Barcode</th>
                                            <th class="p-2.5 font-mono">KodeProduk</th>
                                            <th class="p-2.5 text-center font-mono">QtyProduk</th>
                                            <th class="p-2.5">NamaProduk</th>
                                            <th class="p-2.5 text-center">SatuanUnit</th>
                                            <th class="p-2.5 text-right font-mono">HargaBeli</th>
                                            <th class="p-2.5 text-right font-mono text-emerald-700">HargaJualUmum</th>
                                            <th class="p-2.5 text-right font-mono text-[#0052cc]">HargaJualMember</th>
                                            <th class="p-2.5 text-center font-mono">ProdukDisc</th>
                                            <th class="p-2.5 text-center font-mono">MinimumStock</th>
                                            <th class="p-2.5 text-center font-mono">PPNOut</th>
                                            <th class="p-2.5 text-center font-mono">PPNChk</th>
                                            <th class="p-2.5 text-right font-mono">HargaBeliTerakhir</th>
                                            <th class="p-2.5 text-center">StatusBarang</th>
                                            <th class="p-2.5 text-center font-mono">KodeSuplier</th>
                                            <th class="p-2.5 text-center font-mono">ManufacturingDate</th>
                                            <th class="p-2.5 text-center font-mono">ExpDate</th>
                                            <th class="p-2.5 text-center sticky right-0 bg-slate-100 z-20">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="pRow in getPaginatedPreviewRows()" :key="pRow.row">
                                            <tr :class="{
                                                    'bg-rose-100 text-rose-950 font-bold border-l-4 border-l-rose-600 hover:bg-rose-200': pRow.has_error,
                                                    'bg-blue-100 text-blue-950 font-semibold border-l-4 border-l-[#0052cc] hover:bg-blue-200': !pRow.has_error && pRow.is_existing,
                                                    'hover:bg-slate-50/80 font-medium': !pRow.has_error && !pRow.is_existing
                                                }">
                                                <td class="p-2.5 text-center font-mono"
                                                    :class="{
                                                        'text-rose-900 font-black': pRow.has_error,
                                                        'text-[#0052cc] font-bold': !pRow.has_error && pRow.is_existing,
                                                        'text-slate-400': !pRow.has_error && !pRow.is_existing
                                                    }" x-text="pRow.row"></td>
                                                <td class="p-2.5 font-mono font-bold text-[#0052cc]" x-text="pRow.id_produk || pRow.ilp_id || '-'"></td>
                                                <td class="p-2.5 font-mono font-bold text-slate-800" x-text="pRow.sku"></td>
                                                <td class="p-2.5 font-mono text-slate-600" x-text="pRow.kode_barcode || pRow.barcode || '-'"></td>
                                                <td class="p-2.5 font-mono text-slate-600" x-text="pRow.kode_produk || pRow.sku || '-'"></td>
                                                <td class="p-2.5 text-center font-mono font-bold" x-text="pRow.qty_produk !== undefined ? pRow.qty_produk : (pRow.stock || 0)"></td>
                                                <td class="p-2.5 max-w-[220px] truncate" :title="pRow.nama_produk || pRow.name" x-text="pRow.nama_produk || pRow.name"></td>
                                                <td class="p-2.5 text-center text-slate-600 font-bold" x-text="pRow.satuan_unit || pRow.unit || 'Pcs'"></td>
                                                <td class="p-2.5 text-right font-mono" x-text="'Rp ' + (pRow.harga_beli ?? pRow.cost_price ?? 0).toLocaleString('id-ID')"></td>
                                                <td class="p-2.5 text-right font-mono font-bold text-emerald-700" x-text="'Rp ' + (pRow.harga_jual_umum ?? pRow.price ?? 0).toLocaleString('id-ID')"></td>
                                                <td class="p-2.5 text-right font-mono font-bold text-[#0052cc]" x-text="'Rp ' + (pRow.harga_jual_member ?? pRow.member_price ?? 0).toLocaleString('id-ID')"></td>
                                                <td class="p-2.5 text-center font-mono" x-text="(pRow.produk_disc ?? 0) + '%'"></td>
                                                <td class="p-2.5 text-center font-mono text-slate-600" x-text="pRow.minimum_stock ?? pRow.stock_min ?? 5"></td>
                                                <td class="p-2.5 text-center font-mono" x-text="(pRow.ppn_out ?? 11) + '%'"></td>
                                                <td class="p-2.5 text-center font-mono font-bold" :class="(pRow.ppn_chk || 'Y') === 'Y' ? 'text-emerald-700' : 'text-slate-400'" x-text="pRow.ppn_chk || 'Y'"></td>
                                                <td class="p-2.5 text-right font-mono" x-text="'Rp ' + (pRow.harga_beli_terakhir ?? pRow.cost_price ?? 0).toLocaleString('id-ID')"></td>
                                                <td class="p-2.5 text-center font-bold" x-text="pRow.status_barang || 'TERSEDIA'"></td>
                                                <td class="p-2.5 text-center font-mono" x-text="pRow.kode_suplier || '-'"></td>
                                                <td class="p-2.5 text-center font-mono" x-text="pRow.manufacturing_date || '-'"></td>
                                                <td class="p-2.5 text-center font-mono" x-text="pRow.exp_date || '-'"></td>
                                                <td class="p-2.5 text-center sticky right-0"
                                                    :class="{
                                                        'bg-rose-100': pRow.has_error,
                                                        'bg-blue-100': !pRow.has_error && pRow.is_existing,
                                                        'bg-white': !pRow.has_error && !pRow.is_existing
                                                    }">
                                                    <template x-if="pRow.has_error">
                                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-600 text-white shadow-2xs flex items-center gap-1 justify-center" :title="pRow.error_message">
                                                            <span class="material-symbols-rounded text-[12px]">error</span>
                                                            <span>Error</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="!pRow.has_error && pRow.is_existing">
                                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#0052cc] text-white shadow-2xs flex items-center gap-1 justify-center" title="Sudah terdaftar di database">
                                                            <span class="material-symbols-rounded text-[12px]">verified</span>
                                                            <span>Terdaftar</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="!pRow.has_error && !pRow.is_existing">
                                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-600 text-white shadow-2xs flex items-center gap-1 justify-center" title="Produk baru">
                                                            <span class="material-symbols-rounded text-[12px]">add_circle</span>
                                                            <span>Baru</span>
                                                        </span>
                                                    </template>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination Footer -->
                            <div class="p-3 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs">
                                <div class="text-slate-500 font-medium text-[11px]">
                                    <span>Halaman </span>
                                    <strong class="text-slate-800" x-text="importPreviewPage"></strong>
                                    <span> dari </span>
                                    <strong class="text-slate-800" x-text="getTotalPreviewPages()"></strong>
                                    <span class="ml-1 text-slate-400">
                                        (Menampilkan baris <span x-text="getFilteredPreviewRows().length ? ((importPreviewPage - 1) * (importPreviewPerPage === 'all' ? getFilteredPreviewRows().length : parseInt(importPreviewPerPage)) + 1) : 0"></span> -
                                        <span x-text="Math.min(importPreviewPage * (importPreviewPerPage === 'all' ? getFilteredPreviewRows().length : parseInt(importPreviewPerPage)), getFilteredPreviewRows().length)"></span>
                                        dari <span x-text="getFilteredPreviewRows().length"></span> data)
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5" x-show="getTotalPreviewPages() > 1">
                                    <button type="button" @click="importPreviewPage = 1" :disabled="importPreviewPage <= 1"
                                            class="px-2 py-1 rounded bg-white border border-slate-200 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer">
                                        « Awal
                                    </button>
                                    <button type="button" @click="importPreviewPage = Math.max(1, importPreviewPage - 1)" :disabled="importPreviewPage <= 1"
                                            class="px-2.5 py-1 rounded bg-white border border-slate-200 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer">
                                        ‹ Sebelumnya
                                    </button>
                                    <button type="button" @click="importPreviewPage = Math.min(getTotalPreviewPages(), importPreviewPage + 1)" :disabled="importPreviewPage >= getTotalPreviewPages()"
                                            class="px-2.5 py-1 rounded bg-white border border-slate-200 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer">
                                        Berikutnya ›
                                    </button>
                                    <button type="button" @click="importPreviewPage = getTotalPreviewPages()" :disabled="importPreviewPage >= getTotalPreviewPages()"
                                            class="px-2.5 py-1 rounded bg-white border border-slate-200 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer">
                                        Akhir »
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 4: PROCESSING -->
                    <div x-show="importStep === 'processing'" style="display: none;" class="py-12 flex flex-col items-center justify-center text-center space-y-4">
                        <div class="relative">
                            <div class="w-16 h-16 rounded-full border-4 border-emerald-100 border-t-emerald-600 animate-spin"></div>
                            <span class="material-symbols-rounded absolute inset-0 m-auto w-6 h-6 text-emerald-600 text-[24px]">sync</span>
                        </div>
                        <div>
                            <h4 class="text-base font-bold text-slate-800">Memproses Data Master Produk...</h4>
                            <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                                Mohon tunggu sebentar. Sistem sedang menyimpan data ke database dan menyinkronkan data katalog secara bertahap.
                            </p>
                        </div>
                    </div>

                    <!-- STEP 5: RESULT -->
                    <div x-show="importStep === 'result'" style="display: none;" class="py-6 space-y-6 text-center">
                        <div class="w-16 h-16 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto shadow-xs">
                            <span class="material-symbols-rounded text-[36px]">task_alt</span>
                        </div>
                        <div>
                            <h4 class="text-lg font-black text-slate-800">Import Master Produk Berhasil Selesai!</h4>
                            <p class="text-xs text-slate-500 mt-1" x-text="importResult?.message || 'Data master produk telah tersimpan ke dalam database aplikasi.'"></p>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 max-w-2xl mx-auto text-left">
                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                                <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Total Diproses</span>
                                <div class="text-xl font-black text-slate-800 font-mono mt-0.5" x-text="importResult?.summary?.total_processed || 0"></div>
                            </div>
                            <div class="bg-emerald-50 p-4 rounded-xl border border-emerald-200">
                                <span class="text-[10px] font-extrabold text-emerald-700 uppercase tracking-wider block">Produk Baru Dibuat</span>
                                <div class="text-xl font-black text-emerald-700 font-mono mt-0.5" x-text="importResult?.summary?.created || 0"></div>
                            </div>
                            <div class="bg-blue-50 p-4 rounded-xl border border-blue-200">
                                <span class="text-[10px] font-extrabold text-[#0052cc] uppercase tracking-wider block">Produk Diperbarui</span>
                                <div class="text-xl font-black text-[#0052cc] font-mono mt-0.5" x-text="importResult?.summary?.updated || 0"></div>
                            </div>
                            <div class="bg-amber-50 p-4 rounded-xl border border-amber-200">
                                <span class="text-[10px] font-extrabold text-amber-700 uppercase tracking-wider block">Dilewati (Skip)</span>
                                <div class="text-xl font-black text-amber-700 font-mono mt-0.5" x-text="importResult?.summary?.skipped || 0"></div>
                            </div>
                        </div>

                        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 max-w-xl mx-auto">
                            Tabel produk di halaman ini telah diperbarui secara otomatis dengan data terbaru.
                        </div>
                    </div>

                </div>

                <!-- Modal Footer Actions -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between shrink-0">
                    <!-- Footer: Step 1 (Upload) -->
                    <template x-if="importStep === 'upload'">
                        <div class="w-full flex items-center justify-between">
                            <button type="button" 
                                    @click="showImportModal = false" 
                                    class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-200 transition-colors cursor-pointer">
                                Batal
                            </button>
                            <button type="button" 
                                    @click="uploadAndValidateFile()" 
                                    :disabled="!importFile || isUploading"
                                    class="h-10 px-5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl text-xs font-bold flex items-center gap-2 shadow-xs transition-colors cursor-pointer">
                                <span x-show="isUploading" class="w-4 h-4 rounded-full border-2 border-white/30 border-t-white animate-spin"></span>
                                <span class="material-symbols-rounded text-[18px]" x-show="!isUploading">fact_check</span>
                                <span x-text="isUploading ? 'Memvalidasi 19 Kolom...' : 'Periksa & Validasi File'"></span>
                            </button>
                        </div>
                    </template>

                    <!-- Footer: Step 2 (Invalid Structure) -->
                    <template x-if="importStep === 'invalid_structure'">
                        <div class="w-full flex items-center justify-between">
                            <button type="button" 
                                    @click="downloadILPTemplate()" 
                                    class="px-4 py-2 rounded-xl text-xs font-bold text-[#0052cc] hover:bg-blue-50 border border-blue-200 transition-colors inline-flex items-center gap-1.5 cursor-pointer">
                                <span class="material-symbols-rounded text-[16px]">download</span>
                                <span>Unduh Format Template Resmi (19 Kolom)</span>
                            </button>
                            <button type="button" 
                                    @click="importStep = 'upload'; resetFileInput();" 
                                    class="px-5 py-2.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold transition-colors cursor-pointer inline-flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[16px]">replay</span>
                                <span>Unggah Ulang File Lain</span>
                            </button>
                        </div>
                    </template>

                    <!-- Footer: Step 3 (Preview) -->
                    <template x-if="importStep === 'preview'">
                        <div class="w-full flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <button type="button" 
                                        @click="showImportModal = false" 
                                        class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-200 transition-colors cursor-pointer">
                                    Batal
                                </button>
                                <button type="button" 
                                        @click="importStep = 'upload'; resetFileInput();" 
                                        class="px-4 py-2 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors cursor-pointer">
                                    Ganti File
                                </button>
                            </div>
                            <button type="button" 
                                    @click="confirmExecuteImport()" 
                                    :disabled="!validationData || (validationData?.summary?.valid_rows || 0) === 0"
                                    class="h-10 px-5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl text-xs font-bold flex items-center gap-2 shadow-xs transition-colors cursor-pointer">
                                <span class="material-symbols-rounded text-[18px]">cloud_done</span>
                                <span>Konfirmasi & Mulai Import (<span x-text="validationData?.summary?.valid_rows || 0"></span> Produk)</span>
                            </button>
                        </div>
                    </template>

                    <!-- Footer: Step 4 (Processing) -->
                    <template x-if="importStep === 'processing'">
                        <div class="w-full text-center text-xs text-slate-500 font-medium">
                            Harap jangan menutup browser selama proses import berlangsung...
                        </div>
                    </template>

                    <!-- Footer: Step 5 (Result) -->
                    <template x-if="importStep === 'result'">
                        <div class="w-full flex justify-end">
                            <button type="button" 
                                    @click="showImportModal = false" 
                                    class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-colors cursor-pointer inline-flex items-center gap-1.5 shadow-xs">
                                <span class="material-symbols-rounded text-[16px]">check</span>
                                <span>Selesai & Tutup</span>
                            </button>
                        </div>
                    </template>
                </div>

            </div>
        </div>
    </template>

    <!-- MODAL 5: BARCODE SCANNER SIMULATION -->
    <template x-teleport="body">
        <div x-show="showScannerModal" 
             x-transition.opacity
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4"
             style="display: none;">
            <div @click.away="showScannerModal = false"
                 class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-sm w-full p-6 space-y-4 text-center">
                <div class="w-12 h-12 rounded-2xl bg-blue-50 text-[#0052cc] flex items-center justify-center mx-auto">
                    <span class="material-symbols-rounded text-[28px]">barcode_scanner</span>
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-900 tracking-tight">Simulasi Barcode Scanner</h3>
                    <p class="text-xs text-slate-500 mt-1">Masukkan barcode atau klik barcode cepat untuk menyaring produk.</p>
                </div>
                <div class="space-y-2 text-xs">
                    <input type="text" x-model="scannerInput" @keydown.enter.prevent="handleScan()" placeholder="Ketik barcode EAN..." class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 font-mono text-center text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none">
                    <div class="flex flex-wrap gap-1.5 justify-center pt-1">
                        <template x-for="bp in products.filter(p => p.barcode).slice(0, 3)" :key="bp.id || bp.sku">
                            <button type="button" @click="scannerInput = bp.barcode; handleScan()" class="px-2 py-1 bg-slate-100 hover:bg-blue-50 hover:text-[#0052cc] rounded font-mono text-[10.5px]" x-text="bp.barcode"></button>
                        </template>
                    </div>
                </div>
                <div class="flex items-center gap-2 pt-2">
                    <button type="button" @click="showScannerModal = false" class="w-full py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold transition-colors">
                        Tutup
                    </button>
                    <button type="button" @click="handleScan()" class="w-full py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-xs font-bold transition-colors">
                        Terapkan
                    </button>
                </div>
            </div>
        </div>
    </template>


</div>

<script>
function ownerProductsApp() {
    return {
        searchQuery: '',
        selectedLocation: 'all', // 'all', 'GDG-001', 'CAB-001', 'CAB-002', 'CAB-003'
        selectedCategory: 'all',
        statusFilter: 'all',
        currentPage: 1,
        pageSize: 8,

        showCreateModal: false,
        showEditModal: false,
        showDetailModal: false,
        showScannerModal: false,
        showImportModal: false,
        importStep: 'upload', // 'upload', 'invalid_structure', 'preview', 'processing', 'result'
        importFile: null,
        importFileName: '',
        isUploading: false,
        validationData: null,
        validationErrorDetails: null,
        duplicateAction: 'update',
        importResult: null,
        importPreviewFilter: 'all', // 'all', 'error', 'existing', 'new'
        importPreviewPage: 1,
        importPreviewPerPage: 100,
        importPreviewSearch: '',
        showExistingDetailsBox: false,

        getFilteredPreviewRows() {
            if (!this.validationData || !this.validationData.preview_rows) return [];
            let rows = this.validationData.preview_rows;
            if (this.importPreviewFilter === 'error') {
                rows = rows.filter(r => r.has_error);
            } else if (this.importPreviewFilter === 'existing') {
                rows = rows.filter(r => !r.has_error && r.is_existing);
            } else if (this.importPreviewFilter === 'new') {
                rows = rows.filter(r => !r.has_error && !r.is_existing);
            }
            if (this.importPreviewSearch && this.importPreviewSearch.trim()) {
                const q = this.importPreviewSearch.toLowerCase().trim();
                rows = rows.filter(r => 
                    String(r.row).includes(q) ||
                    (r.sku && String(r.sku).toLowerCase().includes(q)) ||
                    (r.nama_produk && String(r.nama_produk).toLowerCase().includes(q)) ||
                    (r.kode_barcode && String(r.kode_barcode).toLowerCase().includes(q)) ||
                    (r.kode_produk && String(r.kode_produk).toLowerCase().includes(q))
                );
            }
            return rows;
        },

        getPaginatedPreviewRows() {
            const rows = this.getFilteredPreviewRows();
            if (this.importPreviewPerPage === 'all') return rows;
            const perPage = parseInt(this.importPreviewPerPage) || 100;
            const start = (this.importPreviewPage - 1) * perPage;
            return rows.slice(start, start + perPage);
        },

        getTotalPreviewPages() {
            const rows = this.getFilteredPreviewRows();
            if (this.importPreviewPerPage === 'all') return 1;
            const perPage = parseInt(this.importPreviewPerPage) || 100;
            return Math.max(1, Math.ceil(rows.length / perPage));
        },
        isSavingProduct: false,
        scannerInput: '',

        selectedProduct: null,
        formProduct: {
            sku: '',
            barcode: '',
            ilp_id: '',
            name: '',
            brand: '',
            category: 'Kebutuhan Pokok',
            stock: 0,
            minStock: 10,
            unit: 'Pcs',
            price: 0,
            memberPrice: 0,
            rackLocation: '',
            statusBarang: 'TERSEDIA',
            act: 'Y',
            costPrice: 0,
            lastCostPrice: 0,
            ppnChk: 'Y',
            ppnOut: 11,
            up: 0,
            disc: 0,
            hargaLevel1: 0,
            minQty1: 1,
            minQty2: '',
            minQty3: '',
            reorder: 20,
            koSup: '',
            batchLot: '',
            expiredDate: '',
            masa: '',
            dump: ''
        },
        formEditProduct: {},

        defaultProducts: [],

        products: [],

        init() {
            this.initProducts();

            // Detect URL parameter (e.g. ?filter=low_stock from Dashboard button)
            try {
                const params = new URLSearchParams(window.location.search);
                if (params.get('filter') === 'low_stock') {
                    this.statusFilter = 'STOK_RENDAH';
                    if (typeof triggerToast === 'function') {
                        triggerToast('Menampilkan produk dengan status stok menipis.', 'info');
                    }
                }
                if (params.get('location')) {
                    this.selectedLocation = params.get('location');
                }
            } catch (e) {
                console.warn('URL search params read error:', e);
            }
        },

        deduplicateProducts(items) {
            if (!Array.isArray(items)) return { unique: [], hasDuplicates: false };
            const unique = [];
            const seenSku = new Set();
            const seenBarcode = new Set();
            let hasDuplicates = false;

            items.forEach(p => {
                if (!p) return;
                const sku = p.sku !== undefined && p.sku !== null ? String(p.sku).trim().toUpperCase() : '';
                const barcode = p.barcode !== undefined && p.barcode !== null ? String(p.barcode).trim() : '';

                const isDupSku = Boolean(sku && seenSku.has(sku));
                const isDupBarcode = Boolean(barcode && seenBarcode.has(barcode));

                if (isDupSku || isDupBarcode) {
                    hasDuplicates = true;
                    return;
                }

                if (sku) seenSku.add(sku);
                if (barcode) seenBarcode.add(barcode);
                unique.push(p);
            });

            return { unique, hasDuplicates };
        },

        async initProducts() {
            try {
                const res = await fetch('/api/products?per_page=100', { headers: { 'Accept': 'application/json' } });
                if (res.ok) {
                    const result = await res.json();
                    if (result.success && Array.isArray(result.data)) {
                        const formatted = result.data.map(p => ({
                            id: p.id,
                            ilp_id: p.ilp_id || String(p.id),
                            sku: p.sku,
                            barcode: p.barcode,
                            name: p.name,
                            brand: p.supplier_name || p.brand || p.category || 'Lokal',
                            category: p.category || 'Kebutuhan Pokok',
                            stock: Number(p.stock) || 0,
                            masterStock: Number(p.master_stock !== undefined ? p.master_stock : p.stock) || 0,
                            minStock: Number(p.min_stock) || 10,
                            stockMin: Number(p.min_stock) || 10,
                            unit: p.unit || 'Pcs',
                            costPrice: Number(p.cost_price) || 0,
                            lastCostPrice: Number(p.last_cost_price ?? p.cost_price) || 0,
                            price: Number(p.price) || 0,
                            memberPrice: Number(p.member_price || p.price) || 0,
                            minQty1: p.min_qty1 !== null && p.min_qty1 !== undefined ? Number(p.min_qty1) : 1,
                            minQty2: p.min_qty2 !== null && p.min_qty2 !== undefined ? Number(p.min_qty2) : null,
                            minQty3: p.min_qty3 !== null && p.min_qty3 !== undefined ? Number(p.min_qty3) : null,
                            hargaLevel1: Number(p.harga_level1 ?? p.member_price ?? p.price) || 0,
                            disc: Number(p.clearance_discount_percent ?? p.disc) || 0,
                            reorder: Number(p.reorder_point ?? ((p.min_stock || 10) * 2)),
                            ppnOut: Number(p.tax_rate ?? p.ppn_out) || 11,
                            ppnChk: (p.is_taxable !== false && p.ppn_chk !== 'N') ? 'Y' : 'N',
                            statusBarang: p.status || 'TERSEDIA',
                            koSup: p.ko_sup || (p.supplier_code || ''),
                            act: p.act || 'Y',
                            masa: p.masa || '',
                            expiredDate: p.expired_date || '',
                            up: Number(p.markup_percent ?? p.up) || 0,
                            dump: p.dump || '',
                            rackLocation: p.rack_location || '-',
                            branchStock: (p.branch_stock && typeof p.branch_stock === 'object') ? p.branch_stock : {},
                            allocatedBranches: Array.isArray(p.allocated_branches) ? p.allocated_branches : Object.keys(p.branch_stock || {}),
                            status: Number(p.stock) <= 0 ? 'HABIS' : (Number(p.stock) <= Number(p.min_stock || 10) ? 'STOK_RENDAH' : 'TERSEDIA')
                        }));
                        const dedup = this.deduplicateProducts(formatted);
                        this.products = dedup.unique.map(p => this.ensureBranchStock(p));
                        return;
                    }
                }
            } catch (err) {
                console.error('API /api/products error', err);
            }
            this.products = [];
        },

        ensureBranchStock(p) {
            if (!p.branchStock || typeof p.branchStock !== 'object') {
                p.branchStock = {};
            }
            return p;
        },

        getLocationStock(p, locId) {
            if (!p || !p.branchStock) return 0;
            return p.branchStock[locId] !== undefined ? p.branchStock[locId] : 0;
        },

        getLocationName(locId) {
            switch(locId) {
                case 'GDG-001': return 'Gudang Pusat (Logistik DC)';
                case 'CAB-001': return 'Cabang Pusat (Toko Pusat)';
                case 'CAB-002': return 'Cabang A';
                case 'CAB-003': return 'Cabang B';
                default: return 'Produk Master (Stok Gudang)';
            }
        },

        saveProductsToStorage() {
            window.dispatchEvent(new CustomEvent('pos-inventory-synced', { detail: { count: this.products.length, source: 'owner-products' } }));
        },

        searchTimer: null,
        onSearchInput() {
            this.currentPage = 1;
            const q = (this.searchQuery || '').trim();
            if (this.searchTimer) clearTimeout(this.searchTimer);
            if (q.length >= 2) {
                this.searchTimer = setTimeout(() => {
                    this.fetchServerSearch(q);
                }, 300);
            }
        },
        async fetchServerSearch(q) {
            if (!q || q.length < 2) return;
            try {
                const res = await fetch(`/api/products?search=${encodeURIComponent(q)}&per_page=100`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const result = await res.json();
                    if (result.success && Array.isArray(result.data)) {
                        const formatted = result.data.map(p => ({
                            id: p.id,
                            ilp_id: p.ilp_id || String(p.id),
                            sku: p.sku,
                            barcode: p.barcode,
                            name: p.name,
                            brand: p.supplier_name || p.brand || p.category || 'Lokal',
                            category: p.category || 'Kebutuhan Pokok',
                            stock: Number(p.stock) || 0,
                            masterStock: Number(p.master_stock !== undefined ? p.master_stock : p.stock) || 0,
                            minStock: Number(p.min_stock) || 10,
                            stockMin: Number(p.min_stock) || 10,
                            unit: p.unit || 'Pcs',
                            costPrice: Number(p.cost_price) || 0,
                            lastCostPrice: Number(p.last_cost_price ?? p.cost_price) || 0,
                            price: Number(p.price) || 0,
                            memberPrice: Number(p.member_price || p.price) || 0,
                            minQty1: p.min_qty1 !== null && p.min_qty1 !== undefined ? Number(p.min_qty1) : 1,
                            minQty2: p.min_qty2 !== null && p.min_qty2 !== undefined ? Number(p.min_qty2) : null,
                            minQty3: p.min_qty3 !== null && p.min_qty3 !== undefined ? Number(p.min_qty3) : null,
                            hargaLevel1: Number(p.harga_level1 ?? p.member_price ?? p.price) || 0,
                            disc: Number(p.clearance_discount_percent ?? p.disc) || 0,
                            reorder: Number(p.reorder_point ?? ((p.min_stock || 10) * 2)),
                            ppnOut: Number(p.tax_rate ?? p.ppn_out) || 11,
                            ppnChk: (p.is_taxable !== false && p.ppn_chk !== 'N') ? 'Y' : 'N',
                            statusBarang: p.status || 'TERSEDIA',
                            koSup: p.ko_sup || (p.supplier_code || ''),
                            act: p.act || 'Y',
                            masa: p.masa || '',
                            expiredDate: p.expired_date || '',
                            up: Number(p.markup_percent ?? p.up) || 0,
                            dump: p.dump || '',
                            rackLocation: p.rack_location || '-',
                            branchStock: (p.branch_stock && typeof p.branch_stock === 'object') ? p.branch_stock : {},
                            allocatedBranches: Array.isArray(p.allocated_branches) ? p.allocated_branches : Object.keys(p.branch_stock || {}),
                            status: Number(p.stock) <= 0 ? 'HABIS' : (Number(p.stock) <= Number(p.min_stock || 10) ? 'STOK_RENDAH' : 'TERSEDIA')
                        }));
                        const existingSkus = new Set(this.products.map(p => p.sku));
                        const newItems = formatted.filter(p => !existingSkus.has(p.sku)).map(p => this.ensureBranchStock(p));
                        if (newItems.length > 0) {
                            this.products = [...newItems, ...this.products];
                        }
                    }
                }
            } catch (err) {}
        },

        get availableCategories() {
            const standard = ['Minuman', 'Snack', 'Kebutuhan Pokok', 'Pembersih', 'UMKM Konsinyasi'];
            const current = this.products.map(p => p.category).filter(Boolean);
            return Array.from(new Set([...standard, ...current]));
        },

        get lowStockCount() {
            return this.products.filter(p => p.stock > 0 && p.stock <= p.minStock).length;
        },

        get outOfStockCount() {
            return this.products.filter(p => p.stock <= 0).length;
        },

        get totalValuation() {
            return this.products.reduce((acc, p) => acc + (Number(p.stock || 0) * Number(p.price || 0)), 0);
        },

        get filteredProducts() {
            return this.products.filter(p => {
                // Location filter
                if (this.selectedLocation !== 'all') {
                    const isAllocated = Array.isArray(p.allocatedBranches)
                        ? p.allocatedBranches.includes(this.selectedLocation)
                        : (p.branchStock && Object.prototype.hasOwnProperty.call(p.branchStock, this.selectedLocation));
                    if (!isAllocated) return false;
                    const locStock = this.getLocationStock(p, this.selectedLocation);
                    if (this.statusFilter === 'HABIS' && locStock > 0) return false;
                    if (this.statusFilter === 'STOK_RENDAH' && (locStock <= 0 || locStock > Math.round(p.minStock * 0.3))) return false;
                    if (this.statusFilter === 'TERSEDIA' && locStock <= Math.round(p.minStock * 0.3)) return false;
                } else {
                    if (this.statusFilter !== 'all') {
                        if (this.statusFilter === 'HABIS' && p.stock > 0) return false;
                        if (this.statusFilter === 'STOK_RENDAH' && (p.stock <= 0 || p.stock > p.minStock)) return false;
                        if (this.statusFilter === 'TERSEDIA' && p.stock <= p.minStock) return false;
                        if (this.statusFilter === 'NEAR_EXPIRED' && p.status !== 'NEAR_EXPIRED') return false;
                    }
                }

                // Category filter
                if (this.selectedCategory !== 'all' && p.category !== this.selectedCategory) return false;

                // Search query
                if (this.searchQuery.trim() !== '') {
                    const q = this.searchQuery.toLowerCase();
                    const matchName = p.name && p.name.toLowerCase().includes(q);
                    const matchSku = p.sku && p.sku.toLowerCase().includes(q);
                    const matchBarcode = p.barcode && p.barcode.toLowerCase().includes(q);
                    const matchBrand = p.brand && p.brand.toLowerCase().includes(q);
                    const matchCat = p.category && p.category.toLowerCase().includes(q);
                    if (!matchName && !matchSku && !matchBarcode && !matchBrand && !matchCat) return false;
                }
                return true;
            });
        },

        get totalPages() {
            return Math.ceil(this.filteredProducts.length / this.pageSize) || 1;
        },

        get paginatedProducts() {
            const start = (this.currentPage - 1) * this.pageSize;
            return this.filteredProducts.slice(start, start + this.pageSize);
        },

        openCreateModal() {
            this.formProduct = {
                sku: '',
                barcode: '',
                ilp_id: '',
                name: '',
                brand: '',
                category: 'Kebutuhan Pokok',
                stock: 0,
                minStock: 10,
                unit: 'Pcs',
                price: 0,
                memberPrice: 0,
                rackLocation: '',
                statusBarang: 'TERSEDIA',
                act: 'Y',
                costPrice: 0,
                lastCostPrice: 0,
                ppnChk: 'Y',
                ppnOut: 11,
                up: 0,
                disc: 0,
                hargaLevel1: 0,
                minQty1: 1,
                minQty2: '',
                minQty3: '',
                reorder: 20,
                koSup: '',
                batchLot: '',
                expiredDate: '',
                masa: '',
                dump: ''
            };
            this.showCreateModal = true;
        },

        async saveNewProduct() {
            if (this.isSavingProduct) return;
            if (!this.formProduct.name.trim() || !this.formProduct.sku.trim()) return;
            const cleanSku = this.formProduct.sku.trim().toUpperCase();
            const cleanBarcode = (this.formProduct.barcode || '').trim();

            const dupSku = this.products.find(p => (p.sku || '').trim().toUpperCase() === cleanSku);
            if (dupSku) {
                alert('SKU sudah terdaftar. Silakan gunakan data produk yang sudah ada.');
                return;
            }
            if (cleanBarcode) {
                const dupBarcode = this.products.find(p => (p.barcode || '').trim() === cleanBarcode);
                if (dupBarcode) {
                    alert('Barcode sudah digunakan oleh produk lain.');
                    return;
                }
            }

            this.isSavingProduct = true;
            const f = this.formProduct;
            const idemKey = window.createIdempotencyKey ? window.createIdempotencyKey() : ('prd-' + Date.now());
            try {
                const apiPayload = {
                    sku: cleanSku,
                    ilp_id: (f.ilp_id || '').trim() || null,
                    barcode: cleanBarcode,
                    name: f.name.trim(),
                    brand: (f.brand || '').trim() || 'Lokal Mandiri',
                    category: f.category || 'Kebutuhan Pokok',
                    unit: f.unit || 'Pcs',
                    cost_price: Number(f.costPrice) || 0,
                    last_cost_price: Number(f.lastCostPrice) || Number(f.costPrice) || 0,
                    price: Number(f.price) || 0,
                    member_price: Number(f.memberPrice || f.price) || 0,
                    harga_level1: Number(f.hargaLevel1 || f.memberPrice || f.price) || 0,
                    min_qty1: Number(f.minQty1) || 1,
                    min_qty2: f.minQty2 !== '' && f.minQty2 !== null && f.minQty2 !== undefined ? Number(f.minQty2) : null,
                    min_qty3: f.minQty3 !== '' && f.minQty3 !== null && f.minQty3 !== undefined ? Number(f.minQty3) : null,
                    stock: Number(f.stock) || 0,
                    min_stock: Number(f.minStock) || 0,
                    reorder_point: Number(f.reorder) || ((Number(f.minStock) || 10) * 2),
                    markup_percent: Number(f.up) || 0,
                    clearance_discount_percent: Number(f.disc) || 0,
                    is_taxable: f.ppnChk === 'Y',
                    tax_rate: f.ppnChk === 'Y' ? (Number(f.ppnOut) || 11) : 0,
                    ko_sup: (f.koSup || '').trim() || null,
                    act: f.act || 'Y',
                    masa: (f.masa || '').trim() || null,
                    dump: (f.dump || '').trim() || null,
                    rack_location: (f.rackLocation || '').trim() || 'Display Rak',
                    expired_date: f.expiredDate || null
                };

                const res = await fetch('/api/products', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Idempotency-Key': idemKey
                    },
                    body: JSON.stringify(apiPayload)
                });
                if (res.ok) {
                    if (typeof triggerToast === 'function') triggerToast(`Master SKU '${this.formProduct.name}' berhasil disimpan ke database.`, 'success');
                    this.showCreateModal = false;
                    await this.initProducts();
                    return;
                }
                const errJson = await res.json().catch(() => ({}));
                if (typeof triggerToast === 'function') triggerToast(errJson.message || 'Gagal menyimpan produk ke server.', 'error');
            } catch (err) {
                console.error('API /api/products create error', err);
                if (typeof triggerToast === 'function') triggerToast('Gagal terhubung ke server saat menyimpan produk.', 'error');
            } finally {
                this.isSavingProduct = false;
            }
        },

        openEditModal(p) {
            this.selectedProduct = p;
            this.formEditProduct = JSON.parse(JSON.stringify(p));
            this.showEditModal = true;
        },

        async updateProduct() {
            if (!this.selectedProduct) return;
            const cleanSku = (this.formEditProduct.sku || '').trim().toUpperCase();
            const cleanBarcode = (this.formEditProduct.barcode || '').trim();

            const dupSku = this.products.find(p => (p.sku || '').trim().toUpperCase() === cleanSku && (p.sku || '').trim().toUpperCase() !== (this.selectedProduct.sku || '').trim().toUpperCase());
            if (dupSku) {
                alert('SKU sudah terdaftar. Silakan gunakan data produk yang sudah ada.');
                return;
            }
            if (cleanBarcode) {
                const dupBarcode = this.products.find(p => (p.barcode || '').trim() === cleanBarcode && (p.sku || '').trim().toUpperCase() !== (this.selectedProduct.sku || '').trim().toUpperCase());
                if (dupBarcode) {
                    alert('Barcode sudah digunakan oleh produk lain.');
                    return;
                }
            }

            try {
                const editCostPrice = this.formEditProduct.cost_price ?? this.formEditProduct.costPrice ?? Math.round(Number(this.formEditProduct.price) * 0.75);
                const res = await fetch(`/api/products/${this.selectedProduct.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        name: this.formEditProduct.name,
                        sku: cleanSku,
                        barcode: cleanBarcode,
                        category: this.formEditProduct.category,
                        unit: this.formEditProduct.unit,
                        cost_price: Number(editCostPrice),
                        price: Number(this.formEditProduct.price),
                        member_price: Number(this.formEditProduct.memberPrice || this.formEditProduct.price),
                        stock: Number(this.formEditProduct.stock),
                        min_stock: Number(this.formEditProduct.minStock),
                        rack_location: this.formEditProduct.rackLocation
                    })
                });
                if (res.ok) {
                    this.showEditModal = false;
                    if (typeof triggerToast === 'function') triggerToast(`Data master SKU '${this.formEditProduct.name}' berhasil diperbarui di database.`, 'success');
                    await this.initProducts();
                    return;
                }
                const errJson = await res.json().catch(() => ({}));
                if (typeof triggerToast === 'function') triggerToast(errJson.message || 'Gagal memperbarui produk di server.', 'error');
            } catch (err) {
                console.error('API update error', err);
                if (typeof triggerToast === 'function') triggerToast('Gagal terhubung ke server saat memperbarui produk.', 'error');
            }
        },

        openDetailModal(p) {
            this.selectedProduct = p;
            this.showDetailModal = true;
        },

        handleScan() {
            if (!this.scannerInput.trim()) return;
            this.searchQuery = this.scannerInput.trim();
            this.scannerInput = '';
            this.showScannerModal = false;
            if (typeof triggerToast === 'function') triggerToast(`Menyaring barcode: ${this.searchQuery}`, 'info');
        },

        openImportModal() {
            this.showImportModal = true;
            this.importStep = 'upload';
            this.importFile = null;
            this.importFileName = '';
            this.isUploading = false;
            this.isDragging = false;
            this.validationData = null;
            this.validationErrorDetails = null;
            this.duplicateAction = 'update';
            this.importResult = null;
            this.importPreviewFilter = 'all';
            this.importPreviewPage = 1;
            this.importPreviewPerPage = 100;
            this.importPreviewSearch = '';
            this.showExistingDetailsBox = false;
            this.$nextTick(() => {
                const f = document.getElementById('excelFileInputOwner');
                if (f) f.value = '';
            });
        },

        triggerFileInput() {
            const f = document.getElementById('excelFileInputOwner');
            if (f) {
                f.click();
            }
        },

        resetFileInput() {
            this.importFile = null;
            this.importFileName = '';
            this.isDragging = false;
            const f = document.getElementById('excelFileInputOwner');
            if (f) f.value = '';
        },

        onFileSelected(event) {
            const file = event.target.files && event.target.files[0];
            if (file) {
                this.importFile = file;
                this.importFileName = file.name;
            }
        },

        handleFileDrop(event) {
            this.isDragging = false;
            const files = event.dataTransfer ? event.dataTransfer.files : null;
            if (files && files.length > 0) {
                this.importFile = files[0];
                this.importFileName = files[0].name;
            }
        },

        async uploadAndValidateFile() {
            if (!this.importFile) {
                if (typeof triggerToast === 'function') triggerToast('Silakan pilih file Excel terlebih dahulu.', 'warning');
                return;
            }

            this.isUploading = true;
            const formData = new FormData();
            formData.append('file', this.importFile);

            try {
                const res = await fetch('/api/products/import/validate', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: formData
                });

                const json = await res.json();
                if (res.ok && json.success) {
                    this.validationData = json.data;
                    this.importStep = 'preview';
                    this.importPreviewFilter = 'all';
                    this.importPreviewPage = 1;
                    this.importPreviewSearch = '';
                    this.showExistingDetailsBox = false;
                    if (typeof triggerToast === 'function') triggerToast('Struktur 19 kolom Excel valid! Menampilkan pratinjau data...', 'success');
                } else {
                    this.validationErrorDetails = json.details || { message: json.message || 'Format file tidak sesuai standar Master Produk.' };
                    this.importStep = 'invalid_structure';
                    if (typeof triggerToast === 'function') triggerToast(json.message || 'Struktur file Excel tidak sesuai standar Master Produk (19 Kolom).', 'error');
                }
            } catch (e) {
                console.error('Validation error:', e);
                if (typeof triggerToast === 'function') triggerToast('Gagal memvalidasi file: ' + e.message, 'error');
            } finally {
                this.isUploading = false;
            }
        },

        async confirmExecuteImport() {
            if (!this.validationData || !this.validationData.token) {
                if (typeof triggerToast === 'function') triggerToast('Token data import tidak ditemukan.', 'error');
                return;
            }

            this.importStep = 'processing';

            try {
                const res = await fetch('/api/products/import/process', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        token: this.validationData.token,
                        duplicate_action: this.duplicateAction
                    })
                });

                const json = await res.json();
                if (res.ok && json.success) {
                    this.importResult = json;
                    this.importStep = 'result';
                    await this.initProducts();
                    if (typeof triggerToast === 'function') triggerToast('Import master produk berhasil diselesaikan!', 'success');
                } else {
                    if (typeof triggerToast === 'function') triggerToast('Gagal memproses import: ' + (json.message || 'Terjadi kesalahan sistem.'), 'error');
                    this.importStep = 'preview';
                }
            } catch (e) {
                console.error('Import execution error:', e);
                if (typeof triggerToast === 'function') triggerToast('Terjadi kesalahan jaringan saat import: ' + e.message, 'error');
                this.importStep = 'preview';
            }
        },

        downloadILPTemplate() {
            window.location.href = '/manager/products/template';
            if (typeof triggerToast === 'function') triggerToast('Mengunduh Template Excel Master Barang (19 Kolom)...', 'info');
        },

        formatRupiah(num) {
            return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
        },

        exportProductCsv() {
            window.location.href = '/api/products/export/excel';
            if (typeof triggerToast === 'function') triggerToast('Mengunduh Master Produk format 19 Kolom...', 'info');
        }
    };
}
</script>
@endsection
