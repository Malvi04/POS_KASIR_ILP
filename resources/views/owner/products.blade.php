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
     x-init="init()"
     @ilp-search.window="searchQuery = $event.detail"
     @open-add-product-modal.window="openCreateModal()"
     @pos-inventory-synced.window="initProducts()"
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
                    @click="exportProductCsv()"
                    class="px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs inline-flex items-center gap-2 shadow-2xs transition-all cursor-pointer">
                <span class="material-symbols-rounded text-[18px]">download</span>
                <span>Export CSV</span>
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
            <!-- Quick Location Filter Tabs (Gudang Pusat, Toko Pusat, Cabang A, Cabang B) -->
            <div class="px-5 pt-3.5 border-b border-slate-100 flex items-center gap-2 overflow-x-auto bg-slate-50/70">
                <button type="button" 
                        @click="selectedLocation = 'all'"
                        class="px-4 py-2.5 text-xs font-bold transition-all border-b-2 inline-flex items-center gap-2 whitespace-nowrap cursor-pointer"
                        :class="selectedLocation === 'all' ? 'border-[#0052cc] text-[#0052cc] bg-white rounded-t-xl shadow-2xs' : 'border-transparent text-slate-600 hover:text-slate-900'">
                    <span class="material-symbols-rounded text-[16px]">all_inclusive</span>
                    <span>Semua Lokasi (Katalog Master)</span>
                </button>

                <button type="button" 
                        @click="selectedLocation = 'GDG-001'"
                        class="px-4 py-2.5 text-xs font-bold transition-all border-b-2 inline-flex items-center gap-2 whitespace-nowrap cursor-pointer"
                        :class="selectedLocation === 'GDG-001' ? 'border-indigo-600 text-indigo-700 bg-white rounded-t-xl shadow-2xs' : 'border-transparent text-slate-600 hover:text-slate-900'">
                    <span class="material-symbols-rounded text-[16px]">warehouse</span>
                    <span>Gudang Pusat (Logistik DC)</span>
                </button>

                <button type="button" 
                        @click="selectedLocation = 'CAB-001'"
                        class="px-4 py-2.5 text-xs font-bold transition-all border-b-2 inline-flex items-center gap-2 whitespace-nowrap cursor-pointer"
                        :class="selectedLocation === 'CAB-001' ? 'border-[#0052cc] text-[#0052cc] bg-white rounded-t-xl shadow-2xs' : 'border-transparent text-slate-600 hover:text-slate-900'">
                    <span class="material-symbols-rounded text-[16px]">storefront</span>
                    <span>Toko Pusat</span>
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

    <!-- MODAL 1: TAMBAH MASTER SKU BARU -->
    <template x-teleport="body">
        <div x-show="showCreateModal" 
             x-transition.opacity
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4"
             style="display: none;">
            <div @click.away="showCreateModal = false"
                 class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-black text-slate-900 tracking-tight">Tambah Master SKU Baru</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Daftarkan SKU baru ke katalog master multi-cabang koperasi.</p>
                    </div>
                    <button type="button" @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <form @submit.prevent="saveNewProduct()" class="space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">SKU Produk *</label>
                            <input type="text" x-model="formProduct.sku" required placeholder="PRD-011" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 font-mono font-bold text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Barcode EAN-13 *</label>
                            <input type="text" x-model="formProduct.barcode" required placeholder="8991234567890" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 font-mono text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Produk Lengkap *</label>
                        <input type="text" x-model="formProduct.name" required placeholder="Contoh: Kopi Bubuk Robusta 250g" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none font-medium">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Brand / Produsen</label>
                            <input type="text" x-model="formProduct.brand" placeholder="Contoh: Indofood / Mitra Bu Eni" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Kategori Produk *</label>
                            <input type="text" x-model="formProduct.category" required placeholder="Minuman / Snack / Sembako" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Stok Total Master *</label>
                            <input type="number" x-model.number="formProduct.stock" min="0" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 font-mono font-bold text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Batas Min Stok *</label>
                            <input type="number" x-model.number="formProduct.minStock" min="0" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 font-mono text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Satuan</label>
                            <input type="text" x-model="formProduct.unit" placeholder="Pcs / Botol / Pack" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Harga Jual Umum (Rp) *</label>
                            <input type="number" x-model.number="formProduct.price" min="0" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 font-mono font-bold text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Harga Anggota Koperasi (Rp)</label>
                            <input type="number" x-model.number="formProduct.memberPrice" min="0" placeholder="Opsional (Khusus Koperasi)" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 font-mono font-bold text-[#0052cc] focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Lokasi Rak / Lorong</label>
                        <input type="text" x-model="formProduct.rackLocation" placeholder="Lorong A - Rak 01" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none">
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                        <button type="button" @click="showCreateModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-bold transition-colors">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold transition-colors shadow-2xs">
                            Simpan Master SKU
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
                 class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-lg w-full p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center">
                            <span class="material-symbols-rounded text-[22px]">inventory_2</span>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900" x-text="selectedProduct ? selectedProduct.name : ''"></h3>
                            <span class="text-[11px] font-mono text-slate-400" x-text="selectedProduct ? selectedProduct.sku + ' • ' + (selectedProduct.brand || 'Umum') : ''"></span>
                        </div>
                    </div>
                    <button type="button" @click="showDetailModal = false" class="text-slate-400 hover:text-slate-600">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <div class="space-y-3 text-xs" x-if="selectedProduct">
                    <!-- General Details -->
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 space-y-2">
                        <div class="flex justify-between">
                            <span class="text-slate-500 font-medium">Barcode EAN-13</span>
                            <span class="font-mono font-bold text-slate-800" x-text="selectedProduct?.barcode"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500 font-medium">Kategori &amp; Rak</span>
                            <span class="font-bold text-slate-800" x-text="selectedProduct?.category + ' • ' + (selectedProduct?.rackLocation || 'Rak Utama')"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500 font-medium">Harga Umum / Anggota</span>
                            <span class="font-mono font-bold text-slate-900" x-text="formatRupiah(selectedProduct?.price) + ' / ' + formatRupiah(selectedProduct?.memberPrice || selectedProduct?.price)"></span>
                        </div>
                    </div>

                    <!-- Multi-Location Stock Breakdown Table -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-xs font-black uppercase text-slate-800 tracking-wider">Sebaran Stok Multi-Lokasi</h4>
                            <span class="text-[11px] font-mono font-bold text-[#0052cc]" x-text="'Total Master: ' + (selectedProduct?.stock || 0) + ' ' + (selectedProduct?.unit || 'Pcs')"></span>
                        </div>
                        <div class="rounded-xl border border-slate-200 overflow-hidden divide-y divide-slate-100">
                            <!-- 1. Gudang Pusat (DC) -->
                            <div class="p-2.5 bg-indigo-50/40 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-rounded text-[16px] text-indigo-700">warehouse</span>
                                    <div>
                                        <div class="font-bold text-slate-800">Gudang Pusat</div>
                                        <div class="text-[10px] text-slate-400">Pusat Distribusi Logistik (DC)</div>
                                    </div>
                                </div>
                                <span class="font-mono font-black text-indigo-900 text-sm" x-text="getLocationStock(selectedProduct, 'GDG-001') + ' ' + (selectedProduct?.unit || 'Pcs')"></span>
                            </div>

                            <!-- 2. Toko Pusat -->
                            <div class="p-2.5 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-rounded text-[16px] text-slate-500">storefront</span>
                                    <div>
                                        <div class="font-bold text-slate-800">Toko Pusat</div>
                                        <div class="text-[10px] text-slate-400">Gerai Toko Ritel</div>
                                    </div>
                                </div>
                                <span class="font-mono font-bold text-slate-800" x-text="getLocationStock(selectedProduct, 'CAB-001') + ' ' + (selectedProduct?.unit || 'Pcs')"></span>
                            </div>

                            <!-- 3. Cabang A -->
                            <div class="p-2.5 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-rounded text-[16px] text-slate-500">storefront</span>
                                    <div>
                                        <div class="font-bold text-slate-800">Cabang A</div>
                                        <div class="text-[10px] text-slate-400">Gerai Toko Ritel</div>
                                    </div>
                                </div>
                                <span class="font-mono font-bold text-slate-800" x-text="getLocationStock(selectedProduct, 'CAB-002') + ' ' + (selectedProduct?.unit || 'Pcs')"></span>
                            </div>

                            <!-- 4. Cabang B -->
                            <div class="p-2.5 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-rounded text-[16px] text-slate-500">storefront</span>
                                    <div>
                                        <div class="font-bold text-slate-800">Cabang B</div>
                                        <div class="text-[10px] text-slate-400">Gerai Toko Ritel</div>
                                    </div>
                                </div>
                                <span class="font-mono font-bold text-slate-800" x-text="getLocationStock(selectedProduct, 'CAB-003') + ' ' + (selectedProduct?.unit || 'Pcs')"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="button" 
                            @click="showDetailModal = false; openEditModal(selectedProduct)"
                            class="flex-1 py-2.5 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-xs font-bold transition-colors cursor-pointer">
                        Edit Data SKU
                    </button>
                    <button type="button" 
                            @click="showDetailModal = false"
                            class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold transition-colors cursor-pointer">
                        Tutup
                    </button>
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
                        <button type="button" @click="scannerInput = '8991234567890'; handleScan()" class="px-2 py-1 bg-slate-100 hover:bg-blue-50 hover:text-[#0052cc] rounded font-mono text-[10.5px]">8991234567890</button>
                        <button type="button" @click="scannerInput = '8992345678901'; handleScan()" class="px-2 py-1 bg-slate-100 hover:bg-blue-50 hover:text-[#0052cc] rounded font-mono text-[10.5px]">8992345678901</button>
                        <button type="button" @click="scannerInput = '8994567890123'; handleScan()" class="px-2 py-1 bg-slate-100 hover:bg-blue-50 hover:text-[#0052cc] rounded font-mono text-[10.5px]">8994567890123</button>
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
        scannerInput: '',

        selectedProduct: null,
        formProduct: {
            sku: '',
            barcode: '',
            name: '',
            brand: '',
            category: 'Kebutuhan Pokok',
            stock: 50,
            minStock: 10,
            unit: 'Pcs',
            price: 15000,
            memberPrice: 14000,
            rackLocation: 'Lorong A - Rak 01'
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

        initProducts() {
            try {
                const raw = localStorage.getItem('pos_ilp_inventory_products_v1');
                if (raw) {
                    const parsed = JSON.parse(raw);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        const result = this.deduplicateProducts(parsed);
                        this.products = result.unique.map(p => this.ensureBranchStock(p));
                        if (result.hasDuplicates) {
                            this.saveProductsToStorage();
                        }
                        return;
                    }
                }
            } catch (e) {
                console.error('Failed to parse inventory products', e);
            }
            this.products = [];
        },

        ensureBranchStock(p) {
            const total = Number(p.stock) || 0;
            if (!p.branchStock || typeof p.branchStock !== 'object') {
                const gdg = Math.round(total * 0.5);
                const pusat = Math.round(total * 0.25);
                const cabA = Math.round(total * 0.15);
                const cabB = Math.max(0, total - gdg - pusat - cabA);
                p.branchStock = {
                    'GDG-001': gdg,
                    'CAB-001': pusat,
                    'CAB-002': cabA,
                    'CAB-003': cabB
                };
            }
            return p;
        },

        getLocationStock(p, locId) {
            if (!p || !p.branchStock) return 0;
            return p.branchStock[locId] !== undefined ? p.branchStock[locId] : 0;
        },

        getLocationName(locId) {
            switch(locId) {
                case 'GDG-001': return 'Gudang Pusat (DC)';
                case 'CAB-001': return 'Toko Pusat';
                case 'CAB-002': return 'Cabang A';
                case 'CAB-003': return 'Cabang B';
                default: return 'Semua Lokasi (Master)';
            }
        },

        saveProductsToStorage() {
            try {
                localStorage.setItem('pos_ilp_inventory_products_v1', JSON.stringify(this.products));
                window.dispatchEvent(new CustomEvent('pos-inventory-synced', { detail: { count: this.products.length } }));
            } catch (e) {
                console.error('Failed to save products:', e);
            }
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
            const nextIdx = this.products.length + 1;
            this.formProduct = {
                sku: 'PRD-' + String(nextIdx).padStart(3, '0'),
                barcode: '899' + Math.floor(1000000000 + Math.random() * 9000000000),
                name: '',
                brand: '',
                category: 'Kebutuhan Pokok',
                stock: 50,
                minStock: 10,
                unit: 'Pcs',
                price: 15000,
                memberPrice: 14000,
                rackLocation: 'Lorong A - Rak 01'
            };
            this.showCreateModal = true;
        },

        saveNewProduct() {
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

            let newP = {
                ...this.formProduct,
                sku: cleanSku,
                barcode: cleanBarcode,
                status: this.formProduct.stock <= 0 ? 'HABIS' : (this.formProduct.stock <= this.formProduct.minStock ? 'STOK_RENDAH' : 'TERSEDIA')
            };
            newP = this.ensureBranchStock(newP);
            this.products.unshift(newP);
            this.saveProductsToStorage();
            this.showCreateModal = false;
            if (typeof triggerToast === 'function') triggerToast(`Master SKU '${newP.name}' berhasil didaftarkan.`, 'success');
        },

        openEditModal(p) {
            this.selectedProduct = p;
            this.formEditProduct = JSON.parse(JSON.stringify(p));
            this.showEditModal = true;
        },

        updateProduct() {
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

            const idx = this.products.findIndex(p => p.sku === this.selectedProduct.sku);
            if (idx !== -1) {
                this.formEditProduct.status = this.formEditProduct.stock <= 0 ? 'HABIS' : (this.formEditProduct.stock <= this.formEditProduct.minStock ? 'STOK_RENDAH' : 'TERSEDIA');
                const oldTotal = this.products[idx].stock || 1;
                const newTotal = Number(this.formEditProduct.stock) || 0;
                const ratio = oldTotal > 0 ? (newTotal / oldTotal) : 1;
                const bs = this.products[idx].branchStock || {};
                this.formEditProduct.branchStock = {
                    'GDG-001': Math.round((bs['GDG-001'] || 0) * ratio),
                    'CAB-001': Math.round((bs['CAB-001'] || 0) * ratio),
                    'CAB-002': Math.round((bs['CAB-002'] || 0) * ratio),
                    'CAB-003': Math.max(0, newTotal - Math.round((bs['GDG-001'] || 0) * ratio) - Math.round((bs['CAB-001'] || 0) * ratio) - Math.round((bs['CAB-002'] || 0) * ratio))
                };
                this.products[idx] = { ...this.formEditProduct };
                this.saveProductsToStorage();
                this.showEditModal = false;
                if (typeof triggerToast === 'function') triggerToast(`Data master SKU '${this.formEditProduct.name}' berhasil diperbarui.`, 'success');
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

        formatRupiah(num) {
            return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
        },

        exportProductCsv() {
            const headers = ['SKU', 'Barcode', 'Nama Produk', 'Brand', 'Kategori', 'Total Stok Master', 'Gudang Pusat (DC)', 'Toko Pusat', 'Cabang A', 'Cabang B', 'Min Stok', 'Satuan', 'Harga Retail', 'Harga Anggota', 'Lokasi Rak'];
            const rows = this.products.map(p => [
                `"${p.sku}"`,
                `"${p.barcode}"`,
                `"${(p.name || '').replace(/"/g, '""')}"`,
                `"${(p.brand || '').replace(/"/g, '""')}"`,
                `"${(p.category || '').replace(/"/g, '""')}"`,
                p.stock,
                this.getLocationStock(p, 'GDG-001'),
                this.getLocationStock(p, 'CAB-001'),
                this.getLocationStock(p, 'CAB-002'),
                this.getLocationStock(p, 'CAB-003'),
                p.minStock,
                `"${p.unit || 'Pcs'}"`,
                p.price,
                p.memberPrice || p.price,
                `"${(p.rackLocation || '').replace(/"/g, '""')}"`
            ]);
            const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', `Master_Produk_ILP_Owner_${new Date().toISOString().slice(0,10)}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            if (typeof triggerToast === 'function') triggerToast('File CSV Master Produk berhasil diunduh.', 'success');
        }
    };
}
</script>
@endsection
