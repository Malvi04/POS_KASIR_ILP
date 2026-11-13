@extends('layouts.owner-admin', ['active' => 'umkm'])

@section('title', 'Manajemen Mitra UMKM - ILP POS')

@section('header')
<x-admin.header title="Admin Console" searchPlaceholder="Cari mitra atau produk..." searchModel="searchQuery">
    <x-slot:leftSlot>
        <div class="flex items-center gap-3">
            <h2 class="text-base font-black text-slate-800 tracking-tight">Admin Console</h2>
            <span class="text-slate-300">|</span>
            <nav class="hidden md:flex items-center gap-2 text-[13px]">
                <a href="{{ url('/owner') }}" class="font-medium text-slate-500 hover:text-slate-800 transition-colors">Dashboard</a>
                <span class="text-slate-300">/</span>
                <span class="font-bold text-[#0052cc]">UMKM Partners</span>
                <span class="text-slate-300">/</span>
                <a href="{{ url('/owner/reports') }}" class="font-medium text-slate-500 hover:text-slate-800 transition-colors">Reports</a>
            </nav>
        </div>
    </x-slot:leftSlot>

    <div class="flex items-center gap-2">
        <button type="button" 
                @click="showAddModal = true"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-[13px] font-bold shadow-sm shadow-blue-600/20 transition-all cursor-pointer">
            <span class="material-symbols-rounded text-[18px]">add</span>
            <span>Tambah Mitra Baru</span>
        </button>
    </div>
</x-admin.header>
@endsection

@section('admin_content')
<div x-data="umkmPartnersApp()" 
     x-init="initUmkm()"
     @ilp-search.window="searchQuery = $event.detail"
     class="space-y-6">

    <!-- Page Title -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-xl md:text-2xl font-black text-slate-900 tracking-tight">Mitra UMKM</h1>
        </div>
    </div>

    <!-- Top 4 Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Total Mitra Aktif -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                <span class="material-symbols-rounded text-[24px]">local_offer</span>
            </div>
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 block">Total Mitra Aktif</span>
                <span class="text-2xl font-black text-slate-900 tracking-tight leading-none mt-1 block" x-text="totalMitraAktif + ' Mitra'">0 Mitra</span>
                <span class="text-[11.5px] font-medium text-slate-500 mt-1 block">Mitra status aktif</span>
            </div>
        </div>

        <!-- 2. Produk Titipan (SKU) -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">
                <span class="material-symbols-rounded text-[24px]">inventory_2</span>
            </div>
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 block">Produk Titipan (SKU)</span>
                <span class="text-2xl font-black text-slate-900 tracking-tight leading-none mt-1 block" x-text="totalSkuTitipan + ' SKU'">0 SKU</span>
                <span class="text-[11.5px] font-medium text-slate-500 mt-1 block">Total SKU konsinyasi</span>
            </div>
        </div>

        <!-- 3. Penjualan Konsinyasi -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                <span class="material-symbols-rounded text-[24px]">shopping_bag</span>
            </div>
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 block">Penjualan Konsinyasi</span>
                <span class="text-2xl font-black text-slate-900 tracking-tight leading-none mt-1 block" x-text="'Rp ' + totalPenjualanKonsinyasi.toLocaleString('id-ID')">Rp 0</span>
                <span class="text-[11.5px] font-medium text-slate-500 mt-1 block">Bulan Berjalan</span>
            </div>
        </div>

        <!-- 4. Tagihan Unpaid -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                <span class="material-symbols-rounded text-[24px]">payments</span>
            </div>
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 block">Tagihan Unpaid</span>
                <span class="text-2xl font-black text-slate-900 tracking-tight leading-none mt-1 block" x-text="'Rp ' + totalTagihanUnpaid.toLocaleString('id-ID')">Rp 0</span>
                <span class="text-[11.5px] font-medium text-slate-500 mt-1 block">Invoice jatuh tempo</span>
            </div>
        </div>
    </div>

    <!-- Filter & Action Bar (Matches Screenshot 2) -->
    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
        <!-- Search input -->
        <div class="relative flex-1 max-w-md">
            <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
            <input type="text" 
                   x-model="searchQuery" 
                   placeholder="Cari nama mitra atau nama produk..." 
                   class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-white border border-slate-200 text-[13px] text-slate-800 focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none shadow-2xs">
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <span class="text-xs font-bold text-slate-500">Filter:</span>
            
            <!-- Filter Kategori -->
            <select x-model="categoryFilter" class="px-3 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 outline-none shadow-2xs">
                <option value="all">Semua Kategori</option>
                <option value="Snack">Snack</option>
                <option value="Kerajinan">Kerajinan</option>
                <option value="Minuman">Minuman</option>
            </select>

            <!-- Filter Status -->
            <select x-model="statusFilter" class="px-3 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 outline-none shadow-2xs">
                <option value="all">Semua Status</option>
                <option value="aktif">Aktif</option>
                <option value="non-aktif">Non-Aktif</option>
            </select>

            <!-- Ekspor Excel -->
            <button type="button" 
                    @click="exportCsv()"
                    class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-2xs inline-flex items-center gap-1.5 transition-colors cursor-pointer">
                <span class="material-symbols-rounded text-[16px]">table_chart</span>
                <span>Ekspor Excel</span>
            </button>

            <!-- Refresh Button -->
            <button type="button" 
                    @click="initUmkm(); triggerToast('Data mitra diperbarui dari pos_ilp_umkm_partners_v1!')"
                    class="p-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 shadow-2xs transition-colors"
                    title="Refresh">
                <span class="material-symbols-rounded text-[18px]">refresh</span>
            </button>
        </div>
    </div>

    <!-- Table Card: Daftar Mitra UMKM (Matches Screenshot 2) -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden">
        <!-- Horizontal Scroll Wrapper with Min-Width for Columns -->
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse" style="min-width: 820px;">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/70 text-[11px] font-extrabold uppercase text-slate-500 tracking-wider">
                        <th class="py-3 px-5">Nama Mitra</th>
                        <th class="py-3 px-4">Kategori</th>
                        <th class="py-3 px-4">SKU</th>
                        <th class="py-3 px-4">Status Stok</th>
                        <th class="py-3 px-5">Total Penjualan</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-[13px]">
                    <template x-for="p in paginatedPartners" :key="p.id">
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <!-- Nama Mitra with Square Avatar -->
                            <td class="py-3.5 px-5">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl flex items-center justify-center font-black text-sm shrink-0 shadow-2xs"
                                         :class="p.initialColor"
                                         x-text="p.initial">
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-900 leading-tight" x-text="p.name"></span>
                                        <span class="text-[11.5px] text-slate-500" x-text="p.location"></span>
                                    </div>
                                </div>
                            </td>

                            <!-- Kategori -->
                            <td class="py-3.5 px-4 font-medium text-slate-700" x-text="p.category"></td>

                            <!-- SKU -->
                            <td class="py-3.5 px-4 font-bold text-slate-800" x-text="p.skuCount"></td>

                            <!-- Status Stok -->
                            <td class="py-3.5 px-4">
                                <div class="inline-flex items-center gap-1.5 text-[12px] font-semibold"
                                     :class="{
                                         'text-emerald-700': p.stockStatus === 'Aman',
                                         'text-amber-700': p.stockStatus === 'Perlu Restock',
                                         'text-slate-500': p.stockStatus === 'Kosong'
                                     }">
                                    <template x-if="p.stockStatus === 'Aman'">
                                        <span class="material-symbols-rounded text-[16px] text-emerald-600">check_circle</span>
                                    </template>
                                    <template x-if="p.stockStatus === 'Perlu Restock'">
                                        <span class="material-symbols-rounded text-[16px] text-amber-500">warning</span>
                                    </template>
                                    <template x-if="p.stockStatus === 'Kosong'">
                                        <span class="material-symbols-rounded text-[16px] text-slate-400">inventory_2</span>
                                    </template>
                                    <span x-text="p.stockStatus"></span>
                                </div>
                            </td>

                            <!-- Total Penjualan -->
                            <td class="py-3.5 px-5 font-bold text-slate-900" x-text="'Rp ' + (p.salesTotal || 0).toLocaleString('id-ID')"></td>

                            <!-- Status Pill -->
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-0.5 rounded-full text-[10.5px] font-extrabold uppercase border inline-block"
                                      :class="p.status === 'AKTIF' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'"
                                      x-text="p.status">
                                </span>
                            </td>

                            <!-- Aksi (Eye, Pencil, Trash) -->
                            <td class="py-3.5 px-5 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1">
                                    <button type="button" 
                                            @click="selectedPartner = p; showDetailModal = true"
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-[#0052cc] hover:bg-blue-50 transition-colors"
                                            title="Lihat Detail Produk">
                                        <span class="material-symbols-rounded text-[18px]">visibility</span>
                                    </button>
                                    <button type="button" 
                                            @click="openEditModal(p)"
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-800 hover:bg-slate-100 transition-colors"
                                            title="Edit Mitra">
                                        <span class="material-symbols-rounded text-[18px]">edit</span>
                                    </button>
                                    <button type="button" 
                                            @click="deletePartner(p)"
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                                            title="Hapus Mitra">
                                        <span class="material-symbols-rounded text-[18px]">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <template x-if="partners.length === 0">
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                <span class="material-symbols-rounded text-3xl mb-1 text-slate-300 block">handshake</span>
                                <p class="font-bold text-sm text-slate-700">Belum ada data mitra UMKM.</p>
                                <button type="button" @click="showAddModal = true" class="mt-3 px-4 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-xs font-bold inline-flex items-center gap-1.5 shadow-sm transition-colors cursor-pointer">
                                    <span class="material-symbols-rounded text-[16px]">add</span>
                                    <span>Tambah Mitra Baru</span>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <template x-if="partners.length > 0 && paginatedPartners.length === 0">
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                <span class="material-symbols-rounded text-3xl mb-1 text-slate-300 block">search_off</span>
                                <p class="font-bold text-sm text-slate-700">Tidak ada mitra UMKM yang cocok dengan filter pencarian.</p>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Footer Pagination -->
        <div class="px-6 py-3.5 border-t border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-[12.5px] text-slate-500">
            <div>
                Menampilkan <span class="font-bold text-slate-800" x-text="filteredPartners.length ? ((currentPage - 1) * pageSize + 1) + ' - ' + Math.min(currentPage * pageSize, filteredPartners.length) : '0'"></span> dari <span class="font-bold text-slate-800" x-text="filteredPartners.length"></span> mitra
            </div>
            <div class="flex items-center gap-1">
                <button type="button" 
                        @click="if (currentPage > 1) currentPage--" 
                        :disabled="currentPage <= 1"
                        :class="currentPage <= 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-100 text-slate-700'"
                        class="w-7 h-7 rounded-lg border border-slate-200 flex items-center justify-center transition-colors">
                    <span class="material-symbols-rounded text-[16px]">chevron_left</span>
                </button>
                <template x-for="p in totalPages" :key="p">
                    <button type="button" 
                            @click="currentPage = p"
                            :class="currentPage === p ? 'bg-[#0052cc] text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-100'"
                            class="w-7 h-7 rounded-lg font-bold text-xs flex items-center justify-center transition-colors"
                            x-text="p">
                    </button>
                </template>
                <button type="button" 
                        @click="if (currentPage < totalPages) currentPage++" 
                        :disabled="currentPage >= totalPages"
                        :class="currentPage >= totalPages ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-100 text-slate-700'"
                        class="w-7 h-7 rounded-lg border border-slate-200 flex items-center justify-center transition-colors">
                    <span class="material-symbols-rounded text-[16px]">chevron_right</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Bottom 2 Cards Grid (Matches Screenshot 2) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
        
        <!-- Left Card: Peringatan Restock Produk Konsinyasi (lg:col-span-8) -->
        <div class="lg:col-span-8 bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-6 space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                        <span class="material-symbols-rounded text-[22px]">campaign</span>
                    </div>
                    <div>
                        <h4 class="text-base font-bold text-slate-900 tracking-tight">Peringatan Restock Produk Konsinyasi</h4>
                        <p class="text-[12px] text-slate-500 mt-0.5">Mitra berikut memiliki stok produk yang menipis atau kosong di toko.</p>
                    </div>
                </div>

                <button type="button" 
                        @click="triggerToast('Menampilkan rekap draft pengiriman konsinyasi...')"
                        class="text-xs font-bold text-[#0052cc] hover:underline whitespace-nowrap">
                    Lihat Semua Draft PO
                </button>
            </div>

            <!-- Restock Alert Items List -->
            <div class="space-y-3 pt-1">
                <!-- Item 1 -->
                <div class="p-3.5 rounded-xl border border-amber-200 bg-amber-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-rounded text-[20px] text-amber-500">warning</span>
                        <div class="flex flex-col">
                            <span class="text-[13px] font-bold text-slate-900">Batik Cantik Arum - Kain Batik Parang</span>
                            <span class="text-[11.5px] font-bold text-rose-600">Sisa Stok: 2 pcs (Min: 10)</span>
                        </div>
                    </div>
                    <button type="button" 
                            @click="openContact('Batik Cantik Arum', 'Kain Batik Parang', 2)"
                            class="px-3.5 py-1.5 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-800 text-xs font-bold shadow-2xs transition-colors self-start sm:self-auto">
                        Hubungi Mitra
                    </button>
                </div>

                <!-- Item 2 -->
                <div class="p-3.5 rounded-xl border border-amber-200 bg-amber-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-rounded text-[20px] text-amber-500">warning</span>
                        <div class="flex flex-col">
                            <span class="text-[13px] font-bold text-slate-900">Keripik Ibu Siti - Keripik Singkong Pedas 250g</span>
                            <span class="text-[11.5px] font-bold text-rose-600">Sisa Stok: 5 pcs (Min: 20)</span>
                        </div>
                    </div>
                    <button type="button" 
                            @click="openContact('Keripik Ibu Siti', 'Keripik Singkong Pedas 250g', 5)"
                            class="px-3.5 py-1.5 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-800 text-xs font-bold shadow-2xs transition-colors self-start sm:self-auto">
                        Hubungi Mitra
                    </button>
                </div>
            </div>
            <p class="text-[11px] text-slate-400">
                * Pemberitahuan restock bersifat komunikatif langsung ke produsen tanpa pemotongan saldo atau penagihan otomatis.
            </p>
        </div>

        <!-- Right Card: Insight Bulan Ini (lg:col-span-4) (Dark Blue Background) -->
        <div class="lg:col-span-4 bg-gradient-to-br from-[#0052cc] to-[#1e40af] rounded-2xl p-6 text-white flex flex-col justify-between shadow-md">
            <div>
                <h4 class="text-base font-black tracking-tight text-white">Insight Bulan Ini</h4>
                <p class="text-[13px] text-blue-100 mt-2.5 leading-relaxed">
                    Produk Kerajinan mengalami kenaikan permintaan sebesar <strong class="text-white font-black">24%</strong> minggu ini.
                </p>
            </div>

            <div class="pt-6">
                <div class="flex items-center justify-between text-xs font-black uppercase tracking-wider mb-2 text-blue-200">
                    <span>TARGET PENJUALAN UMKM</span>
                    <span class="text-white text-sm">82%</span>
                </div>
                <div class="w-full bg-blue-950/40 rounded-full h-2.5 overflow-hidden">
                    <div class="bg-white h-2.5 rounded-full" style="width: 82%"></div>
                </div>
            </div>
        </div>

    </div>

    <!-- Modals -->
    <!-- Modal: Tambah Mitra Baru -->
    <x-admin.modal show="showAddModal" title="Tambah Mitra Konsinyasi Baru">
        <form @submit.prevent="saveNewPartner()" class="space-y-4 text-[13px]">
            <div>
                <label class="block font-bold text-slate-700 mb-1">Nama Usaha / Produsen *</label>
                <input type="text" x-model="newPartner.name" required placeholder="Contoh: Dapur Sambal Bu Eni" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-[#0052cc] outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Kota / Lokasi</label>
                    <input type="text" x-model="newPartner.location" placeholder="Sleman, Yogyakarta" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-[#0052cc] outline-none">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Kategori Produk</label>
                    <select x-model="newPartner.category" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white focus:border-[#0052cc] outline-none">
                        <option value="Snack">Snack</option>
                        <option value="Kerajinan">Kerajinan</option>
                        <option value="Minuman">Minuman</option>
                        <option value="Bumbu">Bumbu Dapur</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Nomor WhatsApp / Telp</label>
                    <input type="text" x-model="newPartner.phone" placeholder="0812-xxxx-xxxx" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-[#0052cc] outline-none">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Bagi Hasil Koperasi (%)</label>
                    <input type="number" x-model="newPartner.commission" placeholder="15" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-[#0052cc] outline-none">
                </div>
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
                <button type="button" @click="showAddModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs shadow-sm">Simpan Mitra</button>
            </div>
        </form>
    </x-admin.modal>

    <!-- Modal: Hubungi Mitra -->
    <x-admin.modal show="showContactModal" title="Hubungi Mitra untuk Restock">
        <template x-if="contactingPartner">
            <div class="space-y-4 text-[13px]">
                <p class="text-slate-600">
                    Kirimkan permohonan pengiriman restock konsinyasi kepada mitra:
                </p>
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-1">
                    <div>Mitra: <strong x-text="contactingPartner.name"></strong></div>
                    <div>Produk: <span class="font-semibold text-slate-800" x-text="contactingPartner.product"></span></div>
                    <div>Sisa Stok di Display: <strong class="text-rose-600" x-text="contactingPartner.stock + ' pcs'"></strong></div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showContactModal = false" class="px-4 py-2 rounded-xl border text-xs font-bold text-slate-600">Tutup</button>
                    <button type="button" @click="sendWhatsAppRestock()" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold inline-flex items-center gap-1.5 shadow-sm">
                        <span class="material-symbols-rounded text-[16px]">chat</span>
                        <span>Buka Chat WhatsApp</span>
                    </button>
                </div>
            </div>
        </template>
    </x-admin.modal>

    <!-- Modal: Edit Data Mitra UMKM -->
    <x-admin.modal show="showEditModal" title="Edit Data Kemitraan UMKM">
        <form @submit.prevent="saveEditPartner()" class="space-y-4 text-[13px]">
            <div>
                <label class="block font-bold text-slate-700 mb-1">Nama Usaha / Produsen *</label>
                <input type="text" x-model="editingPartner.name" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-[#0052cc] outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Kota / Lokasi</label>
                    <input type="text" x-model="editingPartner.location" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-[#0052cc] outline-none">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Kategori Produk</label>
                    <select x-model="editingPartner.category" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white focus:border-[#0052cc] outline-none">
                        <option value="Snack">Snack</option>
                        <option value="Kerajinan">Kerajinan</option>
                        <option value="Minuman">Minuman</option>
                        <option value="Bumbu">Bumbu Dapur</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Nomor WhatsApp / Telp</label>
                    <input type="text" x-model="editingPartner.phone" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-[#0052cc] outline-none">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Status Kemitraan</label>
                    <select x-model="editingPartner.status" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white focus:border-[#0052cc] outline-none">
                        <option value="AKTIF">Aktif</option>
                        <option value="NON-AKTIF">Non-Aktif</option>
                    </select>
                </div>
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
                <button type="button" @click="showEditModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs shadow-sm">Simpan Perubahan</button>
            </div>
        </form>
    </x-admin.modal>

    <!-- Modal: Detail Produk Konsinyasi -->
    <x-admin.modal show="showDetailModal" title="Detail Produk Konsinyasi Mitra">
        <template x-if="selectedPartner">
            <div class="space-y-4 text-[13px]">
                <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-base shadow-2xs"
                         :class="selectedPartner.initialColor"
                         x-text="selectedPartner.initial">
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-900" x-text="selectedPartner.name"></h4>
                        <span class="text-xs text-slate-500" x-text="selectedPartner.location + ' • Kategori: ' + selectedPartner.category"></span>
                    </div>
                </div>

                <div>
                    <h5 class="text-xs font-bold uppercase text-slate-500 mb-2">Daftar Produk Dititipkan:</h5>
                    <div class="space-y-2">
                        <template x-for="prod in selectedPartner.products" :key="prod.name">
                            <div class="p-2.5 rounded-lg border border-slate-200 flex items-center justify-between">
                                <span class="font-medium text-slate-800" x-text="prod.name"></span>
                                <div class="text-right">
                                    <span class="font-bold text-[#0052cc]" x-text="'Rp ' + prod.price.toLocaleString('id-ID')"></span>
                                    <span class="text-xs text-slate-500 block" x-text="'Stok: ' + prod.stock + ' unit'"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="button" @click="showDetailModal = false" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 font-bold text-xs">Tutup</button>
                </div>
            </div>
        </template>
    </x-admin.modal>

</div>

<script>
function umkmPartnersApp() {
    return {
        searchQuery: '',
        categoryFilter: 'all',
        statusFilter: 'all',
        currentPage: 1,
        pageSize: 4,
        showAddModal: false,
        showEditModal: false,
        showDetailModal: false,
        showContactModal: false,
        selectedPartner: null,
        contactingPartner: null,
        editingPartner: {},

        // Data Mitra UMKM - Terhubung dengan pos_ilp_umkm_partners_v1 (LocalStorage)
        partners: [],

        defaultPartners: [],

        newPartner: {
            name: '',
            location: '',
            category: 'Snack',
            phone: '',
            commission: 15
        },

        async initUmkm() {
            try {
                const res = await fetch('/api/consignment/partners');
                if (res.ok) {
                    const result = await res.json();
                    if (result.success && Array.isArray(result.data) && result.data.length > 0) {
                        this.partners = result.data.map(p => ({
                            id: p.id,
                            initial: (p.name || 'U').charAt(0).toUpperCase(),
                            initialColor: 'bg-blue-100 text-[#0052cc]',
                            name: p.name,
                            location: p.address || 'Bandung',
                            category: p.category || 'Snack',
                            skuCount: p.products_count || 1,
                            stockStatus: 'Aman',
                            salesTotal: (p.products_count || 0) * 250000,
                            status: p.status || 'AKTIF',
                            phone: p.phone || '0812-0000-0000',
                            commission: Number(p.commission_percent) || 15,
                            products: []
                        }));
                        this.saveUmkm();
                        return;
                    }
                }
            } catch (err) {
                console.warn('API error, reading local cache', err);
            }

            try {
                const stored = localStorage.getItem('pos_ilp_umkm_partners_v1');
                if (stored) {
                    const parsed = JSON.parse(stored);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        this.partners = parsed;
                        return;
                    }
                }
            } catch(e) {}
            this.partners = [];
        },

        get totalMitraAktif() {
            return this.partners.filter(p => p.status === 'AKTIF').length;
        },

        get totalSkuTitipan() {
            return this.partners.reduce((sum, p) => sum + (Number(p.skuCount) || 0), 0);
        },

        get totalPenjualanKonsinyasi() {
            return this.partners.reduce((sum, p) => sum + (Number(p.salesTotal) || 0), 0);
        },

        get totalTagihanUnpaid() {
            return 0;
        },

        saveUmkm() {
            try {
                localStorage.setItem('pos_ilp_umkm_partners_v1', JSON.stringify(this.partners));
            } catch(e) {}
        },

        get filteredPartners() {
            return this.partners.filter(p => {
                const query = this.searchQuery.toLowerCase();
                const matchSearch = p.name.toLowerCase().includes(query) ||
                                    p.location.toLowerCase().includes(query) ||
                                    p.category.toLowerCase().includes(query);
                const matchCat = this.categoryFilter === 'all' || p.category.toLowerCase() === this.categoryFilter.toLowerCase();
                const matchStatus = this.statusFilter === 'all' || p.status.toLowerCase() === this.statusFilter.toLowerCase();
                return matchSearch && matchCat && matchStatus;
            });
        },

        get totalPages() {
            return Math.max(1, Math.ceil(this.filteredPartners.length / this.pageSize));
        },

        get paginatedPartners() {
            const start = (this.currentPage - 1) * this.pageSize;
            return this.filteredPartners.slice(start, start + this.pageSize);
        },

        openEditModal(partner) {
            this.editingPartner = JSON.parse(JSON.stringify(partner));
            this.showEditModal = true;
        },

        async saveEditPartner() {
            if (!this.editingPartner.name) {
                if (window.triggerToast) window.triggerToast('Harap isi nama mitra usaha!', 'error');
                return;
            }

            if (typeof this.editingPartner.id === 'number') {
                try {
                    const res = await fetch(`/api/consignment/partners/${this.editingPartner.id}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            name: this.editingPartner.name,
                            address: this.editingPartner.location,
                            category: this.editingPartner.category,
                            phone: this.editingPartner.phone,
                            commission_percent: this.editingPartner.commission || 15,
                            settlement_period: 'BULANAN',
                            status: this.editingPartner.status || 'AKTIF'
                        })
                    });
                    if (res.ok) {
                        this.showEditModal = false;
                        if (window.triggerToast) window.triggerToast('Data mitra ' + this.editingPartner.name + ' berhasil diperbarui di database!', 'success');
                        await this.initUmkm();
                        return;
                    }
                } catch(e) {
                    console.warn('API error, updating locally', e);
                }
            }

            const idx = this.partners.findIndex(p => p.id === this.editingPartner.id);
            if (idx !== -1) {
                this.partners[idx] = { ...this.partners[idx], ...this.editingPartner };
                this.saveUmkm();
                this.showEditModal = false;
                if (window.triggerToast) window.triggerToast('Data mitra ' + this.editingPartner.name + ' berhasil diperbarui!', 'success');
            }
        },

        async saveNewPartner() {
            if (!this.newPartner.name) {
                if (window.triggerToast) window.triggerToast('Harap isi nama mitra produsen!', 'error');
                return;
            }

            try {
                const res = await fetch('/api/consignment/partners', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        name: this.newPartner.name,
                        address: this.newPartner.location || 'Bandung',
                        category: this.newPartner.category,
                        phone: this.newPartner.phone || '0812-0000-0000',
                        commission_percent: this.newPartner.commission || 15,
                        settlement_period: 'BULANAN'
                    })
                });
                if (res.ok) {
                    this.showAddModal = false;
                    if (window.triggerToast) window.triggerToast('Mitra konsinyasi baru berhasil disimpan ke database!', 'success');
                    this.newPartner = { name: '', location: '', category: 'Snack', phone: '', commission: 15 };
                    await this.initUmkm();
                    return;
                }
            } catch(e) {
                console.warn('API error, saving locally', e);
            }

            const initial = this.newPartner.name.charAt(0).toUpperCase();
            this.partners.unshift({
                id: 'UMKM-0' + (this.partners.length + 1),
                initial: initial,
                initialColor: 'bg-blue-100 text-[#0052cc]',
                name: this.newPartner.name,
                location: this.newPartner.location || 'Bandung',
                category: this.newPartner.category,
                skuCount: 1,
                stockStatus: 'Aman',
                salesTotal: 0,
                status: 'AKTIF',
                phone: this.newPartner.phone || '0812-0000-0000',
                products: []
            });
            this.saveUmkm();
            this.showAddModal = false;
            if (window.triggerToast) window.triggerToast('Mitra konsinyasi baru berhasil didaftarkan!', 'success');
            this.newPartner = { name: '', location: '', category: 'Snack', phone: '', commission: 15 };
        },

        async deletePartner(partner) {
            if (!confirm('Hapus mitra ' + partner.name + ' dari daftar konsinyasi?')) return;

            if (typeof partner.id === 'number') {
                try {
                    const res = await fetch(`/api/consignment/partners/${partner.id}`, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    if (res.ok) {
                        if (window.triggerToast) window.triggerToast('Mitra konsinyasi berhasil dihapus dari database.', 'info');
                        await this.initUmkm();
                        return;
                    }
                } catch(e) {
                    console.warn('API error, removing locally', e);
                }
            }

            this.partners = this.partners.filter(p => p.id !== partner.id);
            this.saveUmkm();
            if (window.triggerToast) window.triggerToast('Mitra konsinyasi berhasil dihapus.', 'info');
        },

        openContact(partnerName, productName, stockLeft) {
            this.contactingPartner = { name: partnerName, product: productName, stock: stockLeft };
            this.showContactModal = true;
        },

        sendWhatsAppRestock() {
            if (!this.contactingPartner) return;
            const msg = 'Halo ' + this.contactingPartner.name + ', stok produk ' + this.contactingPartner.product + ' di Kopkar ILP tersisa ' + this.contactingPartner.stock + ' unit. Mohon konfirmasi jadwal pengiriman restock konsinyasi. Terima kasih.';
            window.open('https://wa.me/?text=' + encodeURIComponent(msg), '_blank');
            this.showContactModal = false;
            if (window.triggerToast) window.triggerToast('Pesan WhatsApp permohonan restock telah disiapkan.', 'success');
        },

        exportCsv() {
            try {
                const headers = ['ID Mitra', 'Nama Produsen UMKM', 'Lokasi Sentra', 'Kategori Produk', 'Total SKU Konsinyasi', 'Status Stok', 'Total Omset Penjualan (Rp)', 'Status Kemitraan', 'No Telepon'];
                const rows = this.partners.map(p => [
                    p.id,
                    p.name,
                    p.location,
                    p.category,
                    Number(p.skuCount || 0),
                    p.stockStatus,
                    Number(p.salesTotal || 0),
                    p.status,
                    p.phone || '-'
                ]);

                const totalOmset = this.partners.reduce((sum, p) => sum + Number(p.salesTotal || 0), 0);
                const totals = ['TOTAL', '', '', '', '', '', totalOmset, '', ''];

                window.exportTableToExcel({
                    filename: 'rekap_mitra_umkm_ilp_' + new Date().toISOString().slice(0, 10) + '.xlsx',
                    sheetName: 'Mitra UMKM',
                    title: 'PT INDO LAMPUNG PERKASA - MINIMARKET KOPERASI',
                    subtitle: 'REKAPITULASI MITRA UMKM & PRODUK KONSINYASI',
                    metadata: [
                        ['Tanggal Generate', new Date().toLocaleString('id-ID')],
                        ['Total Mitra', this.partners.length + ' Produsen']
                    ],
                    headers: headers,
                    rows: rows,
                    totals: totals
                });

                if (window.triggerToast) window.triggerToast('File Excel rekap mitra UMKM berhasil diunduh.', 'success');
            } catch(e) {
                if (window.triggerToast) window.triggerToast('Gagal ekspor Excel: ' + e.message, 'error');
            }
        }
    };
}
</script>
@endsection
