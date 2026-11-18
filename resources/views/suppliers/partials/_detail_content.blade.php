@php
    $canManage = $canManage ?? true;
    $listUrl = $listUrl ?? url('/suppliers');
    $supplierId = (int) ($supplierId ?? 0);
@endphp

<div x-data="supplierDetailApp({
         supplierId: @js($supplierId),
         canManage: @js((bool) $canManage),
         listUrl: @js($listUrl),
         csrfToken: @js(csrf_token())
     })"
     x-init="initDetail()"
     @refresh-supplier-detail.window="refreshAll()"
     class="space-y-6">

    <!-- Back & Top Breadcrumb Action -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ $listUrl }}"
           class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white border border-gray-200 hover:bg-gray-50 text-xs font-bold text-gray-700 shadow-2xs transition-colors">
            <span class="material-symbols-rounded text-[17px]">arrow_back</span>
            <span>Kembali ke List Supplier</span>
        </a>

        @if($canManage)
            <div class="flex items-center gap-2" x-show="supplier">
                <button type="button"
                        @click="toggleSupplierStatus()"
                        :disabled="isSaving"
                        :class="supplier?.status === 'AKTIF' ? 'bg-rose-50 hover:bg-rose-100 text-rose-700 border-rose-200' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border-emerald-200'"
                        class="px-3.5 py-2 rounded-xl border text-xs font-bold inline-flex items-center gap-1.5 cursor-pointer transition-colors disabled:opacity-50">
                    <span class="material-symbols-rounded text-[17px]" x-text="supplier?.status === 'AKTIF' ? 'block' : 'check_circle'"></span>
                    <span x-text="supplier?.status === 'AKTIF' ? 'Nonaktifkan Supplier' : 'Aktifkan Supplier'"></span>
                </button>
                <button type="button"
                        @click="openEditModal()"
                        class="px-4 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-xs font-bold inline-flex items-center gap-1.5 shadow-xs cursor-pointer transition-colors">
                    <span class="material-symbols-rounded text-[17px]">edit</span>
                    <span>Edit Supplier</span>
                </button>
            </div>
        @endif
    </div>

    <!-- Loading Skeleton -->
    <template x-if="isLoading && !supplier">
        <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
            <div class="inline-flex items-center gap-3 text-gray-500 text-sm font-bold">
                <span class="material-symbols-rounded animate-spin text-[26px] text-[#0052cc]">progress_activity</span>
                <span>Memuat profil &amp; riwayat supplier...</span>
            </div>
        </div>
    </template>

    <!-- Not Found State -->
    <template x-if="!isLoading && !supplier">
        <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center space-y-3">
            <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center mx-auto">
                <span class="material-symbols-rounded text-[26px]">error_outline</span>
            </div>
            <h3 class="text-base font-black text-gray-900">Data supplier tidak ditemukan</h3>
            <p class="text-xs text-gray-500">Supplier yang Anda cari tidak tersedia atau telah dihapus.</p>
            <a href="{{ $listUrl }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#0052cc] text-white text-xs font-bold">
                Kembali ke Daftar Supplier
            </a>
        </div>
    </template>

    <template x-if="supplier">
        <div class="space-y-6">

            <!-- Header Card -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-xs">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div class="flex items-start gap-4 min-w-0">
                        <div class="w-14 h-14 rounded-2xl bg-blue-50 text-[#0052cc] border border-blue-100 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[28px]">store</span>
                        </div>
                        <div class="min-w-0 space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <span class="px-2.5 py-0.5 rounded-lg bg-slate-100 text-slate-700 font-mono font-bold text-[11px] border border-slate-200"
                                      x-text="supplier.code"></span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border"
                                      :class="supplier.status === 'AKTIF' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'"
                                      x-text="supplier.status === 'AKTIF' ? 'Aktif' : 'Nonaktif'"></span>
                            </div>
                            <h2 class="text-xl sm:text-2xl font-black text-gray-900 tracking-tight break-words" x-text="supplier.name"></h2>
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 font-medium">
                                <span x-show="supplier.company_name" class="inline-flex items-center gap-1">
                                    <span class="material-symbols-rounded text-[15px] text-gray-400">apartment</span>
                                    <span x-text="supplier.company_name"></span>
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <span class="material-symbols-rounded text-[15px] text-gray-400">call</span>
                                    <span class="font-mono font-semibold text-gray-700" x-text="supplier.phone || '-'"></span>
                                </span>
                                <span x-show="supplier.pic_name" class="inline-flex items-center gap-1">
                                    <span class="material-symbols-rounded text-[15px] text-gray-400">person</span>
                                    <span x-text="'PIC: ' + supplier.pic_name"></span>
                                </span>
                                <span x-show="supplier.city" class="inline-flex items-center gap-1">
                                    <span class="material-symbols-rounded text-[15px] text-gray-400">location_on</span>
                                    <span x-text="supplier.city"></span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Kategori Bisnis Badges -->
                    <div class="flex flex-wrap items-center gap-1.5 lg:justify-end">
                        <template x-if="supplier.business_categories && supplier.business_categories.length > 0">
                            <template x-for="cat in supplier.business_categories" :key="cat">
                                <span class="px-3 py-1 rounded-xl bg-blue-50 text-[#0052cc] border border-blue-200 text-[11px] font-bold"
                                      x-text="cat"></span>
                            </template>
                        </template>
                        <template x-if="!supplier.business_categories || supplier.business_categories.length === 0">
                            <span class="px-3 py-1 rounded-xl bg-slate-100 text-slate-600 border border-slate-200 text-[11px] font-bold"
                                  x-text="supplier.category || 'Supplier Umum'"></span>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Summary Cards (4 Cards) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
                <div class="bg-white rounded-2xl border border-blue-200 border-l-4 border-l-[#0052cc] p-5 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-[#0052cc] tracking-wider uppercase">TOTAL PRODUK</span>
                        <span class="material-symbols-rounded text-[22px] text-[#0052cc]">inventory_2</span>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between">
                        <span class="text-3xl font-black text-[#0052cc] font-mono" x-text="supplier.summary.total_products">0</span>
                        <span class="text-[11px] font-semibold text-blue-700">Pernah dipasok</span>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">TOTAL TRANSAKSI PEMBELIAN</span>
                        <span class="material-symbols-rounded text-[22px] text-slate-600">receipt_long</span>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between">
                        <span class="text-3xl font-black text-gray-900 font-mono" x-text="supplier.summary.total_purchases">0</span>
                        <span class="text-[11px] font-semibold text-gray-400">Transaksi PO</span>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">TOTAL NILAI PEMBELIAN</span>
                        <span class="material-symbols-rounded text-[22px] text-emerald-600">payments</span>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between gap-2">
                        <span class="text-xl sm:text-2xl font-black text-gray-900 font-mono truncate" x-text="formatRupiah(supplier.summary.total_purchase_value)">Rp0</span>
                        <span class="text-[11px] font-bold text-gray-400">IDR</span>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">PEMBELIAN TERAKHIR</span>
                        <span class="material-symbols-rounded text-[22px] text-amber-500">event</span>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between">
                        <span class="text-lg sm:text-xl font-black text-gray-900" x-text="supplier.summary.last_purchase_date_label || '-'">-</span>
                    </div>
                </div>
            </div>

            <!-- Internal Tab Navigation (Consistent with Pembelian Barang) -->
            <div class="bg-white border border-gray-200 rounded-2xl p-2 shadow-xs overflow-x-auto">
                <div class="flex items-center gap-2 min-w-max">
                    <button type="button"
                            @click="activeTab = 'info'"
                            :class="activeTab === 'info' ? 'bg-[#0052cc] text-white shadow-xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600'"
                            class="px-4 py-2.5 rounded-xl text-xs font-bold flex items-center gap-2 transition-all cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">info</span>
                        <span>Informasi Supplier</span>
                    </button>

                    <button type="button"
                            @click="activeTab = 'products'; loadSuppliedProducts();"
                            :class="activeTab === 'products' ? 'bg-[#0052cc] text-white shadow-xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600'"
                            class="px-4 py-2.5 rounded-xl text-xs font-bold flex items-center gap-2 transition-all cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">inventory_2</span>
                        <span>Produk Dipasok</span>
                        <span :class="activeTab === 'products' ? 'bg-white text-[#0052cc]' : 'bg-slate-200 text-slate-700'"
                              class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full"
                              x-text="supplier.summary.total_products">0</span>
                    </button>

                    <button type="button"
                            @click="activeTab = 'purchases'; loadPurchases();"
                            :class="activeTab === 'purchases' ? 'bg-[#0052cc] text-white shadow-xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600'"
                            class="px-4 py-2.5 rounded-xl text-xs font-bold flex items-center gap-2 transition-all cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">receipt_long</span>
                        <span>Riwayat Pembelian</span>
                        <span :class="activeTab === 'purchases' ? 'bg-white text-[#0052cc]' : 'bg-slate-200 text-slate-700'"
                              class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full"
                              x-text="supplier.summary.total_purchases">0</span>
                    </button>

                    <button type="button"
                            @click="activeTab = 'prices'; loadPriceHistory();"
                            :class="activeTab === 'prices' ? 'bg-[#0052cc] text-white shadow-xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600'"
                            class="px-4 py-2.5 rounded-xl text-xs font-bold flex items-center gap-2 transition-all cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">trending_up</span>
                        <span>Riwayat Harga</span>
                    </button>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- TAB 1: INFORMASI SUPPLIER                                 -->
            <!-- ========================================================= -->
            <div x-show="activeTab === 'info'" class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-rounded text-[20px] text-[#0052cc]">badge</span>
                        <h3 class="text-sm font-bold text-gray-900">Profil &amp; Informasi Lengkap Supplier</h3>
                    </div>
                    @if($canManage)
                        <button type="button"
                                @click="openEditModal()"
                                class="px-3.5 py-1.5 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 text-xs font-bold inline-flex items-center gap-1.5 cursor-pointer">
                            <span class="material-symbols-rounded text-[16px]">edit</span>
                            <span>Edit Supplier</span>
                        </button>
                    @endif
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 text-xs">
                    <div class="p-4 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[10px] font-bold text-gray-400 uppercase block">NAMA SUPPLIER</span>
                        <span class="text-sm font-bold text-gray-900 mt-1 block" x-text="supplier.name"></span>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[10px] font-bold text-gray-400 uppercase block">KODE SUPPLIER</span>
                        <span class="text-sm font-mono font-bold text-[#0052cc] mt-1 block" x-text="supplier.code"></span>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[10px] font-bold text-gray-400 uppercase block">NAMA PERUSAHAAN</span>
                        <span class="text-sm font-semibold text-gray-900 mt-1 block" x-text="supplier.company_name || '-'"></span>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[10px] font-bold text-gray-400 uppercase block">PIC (PERSON IN CHARGE)</span>
                        <span class="text-sm font-semibold text-gray-900 mt-1 block" x-text="supplier.pic_name || '-'"></span>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[10px] font-bold text-gray-400 uppercase block">NOMOR TELEPON</span>
                        <span class="text-sm font-mono font-semibold text-gray-900 mt-1 block" x-text="supplier.phone || '-'"></span>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[10px] font-bold text-gray-400 uppercase block">EMAIL</span>
                        <span class="text-sm font-semibold text-gray-900 mt-1 block break-all" x-text="supplier.email || '-'"></span>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 border border-gray-200 md:col-span-2">
                        <span class="text-[10px] font-bold text-gray-400 uppercase block">ALAMAT LENGKAP &amp; KOTA</span>
                        <p class="text-sm font-medium text-gray-800 mt-1" x-text="(supplier.address || '-') + (supplier.city ? ' • ' + supplier.city : '')"></p>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[10px] font-bold text-gray-400 uppercase block">STATUS SUPPLIER</span>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border mt-1.5"
                              :class="supplier.status === 'AKTIF' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'"
                              x-text="supplier.status === 'AKTIF' ? 'Aktif (Dapat dipilih di Pembelian)' : 'Nonaktif (Riwayat tetap tersimpan)'"></span>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 border border-gray-200 md:col-span-2">
                        <span class="text-[10px] font-bold text-gray-400 uppercase block mb-1.5">KATEGORI BISNIS SUPPLIER</span>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-if="supplier.business_categories && supplier.business_categories.length > 0">
                                <template x-for="cat in supplier.business_categories" :key="cat">
                                    <span class="px-2.5 py-1 rounded-lg bg-blue-50 text-[#0052cc] border border-blue-200 font-bold text-[11px]" x-text="cat"></span>
                                </template>
                            </template>
                            <template x-if="!supplier.business_categories || supplier.business_categories.length === 0">
                                <span class="text-gray-600 font-semibold" x-text="supplier.category || 'Supplier Umum'"></span>
                            </template>
                        </div>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 border border-gray-200">
                        <span class="text-[10px] font-bold text-gray-400 uppercase block">CATATAN</span>
                        <p class="text-gray-700 font-medium mt-1" x-text="supplier.notes || '-'"></p>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- TAB 2: PRODUK DIPASOK                                     -->
            <!-- ========================================================= -->
            <div x-show="activeTab === 'products'" style="display: none;" class="space-y-4">
                <!-- Filter Bar -->
                <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 flex-1">
                        <div class="relative">
                            <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-[19px]">search</span>
                            <input type="text"
                                   x-model="prodFilters.search"
                                   @input="onProdSearchInput()"
                                   placeholder="Cari nama produk atau SKU..."
                                   class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] text-xs font-medium bg-slate-50/50">
                        </div>
                        <div class="relative">
                            <select x-model="prodFilters.category"
                                    @change="prodFilters.page = 1; loadSuppliedProducts();"
                                    class="appearance-none w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2.5 pr-9 text-xs text-gray-700 font-semibold focus:outline-none focus:border-[#0052cc] cursor-pointer">
                                <option value="all">Semua Kategori Produk</option>
                                <template x-for="cat in suppliedCategories" :key="cat">
                                    <option :value="cat" x-text="cat"></option>
                                </template>
                            </select>
                            <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[18px]">expand_more</span>
                        </div>
                        <div class="relative">
                            <select x-model="prodFilters.supply_status"
                                    @change="prodFilters.page = 1; loadSuppliedProducts();"
                                    class="appearance-none w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2.5 pr-9 text-xs text-gray-700 font-semibold focus:outline-none focus:border-[#0052cc] cursor-pointer">
                                <option value="all">Semua Status Pasokan</option>
                                <option value="AKTIF">Aktif Dipasok</option>
                                <option value="TIDAK_AKTIF">Tidak Aktif Dipasok</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[18px]">expand_more</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-[11px] font-semibold text-blue-800 bg-blue-50 border border-blue-200 rounded-xl px-3.5 py-2">
                        <span class="material-symbols-rounded text-[16px] text-[#0052cc]">info</span>
                        <span>Harga beli terakhir di sini khusus berasal dari supplier ini.</span>
                    </div>
                </div>

                <!-- Table Produk Dipasok -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                        <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                            <span class="material-symbols-rounded text-[20px] text-[#0052cc]">inventory_2</span>
                            <span>Daftar Produk yang Pernah Dipasok</span>
                        </h3>
                        <span class="text-xs text-gray-500 font-medium" x-text="'Menampilkan ' + suppliedProducts.length + ' dari ' + prodMeta.total + ' produk'"></span>
                    </div>

                    <div class="overflow-x-auto min-w-0 w-full">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold tracking-wider uppercase text-[11px] whitespace-nowrap">
                                    <th class="px-4 py-3.5">Nama Produk</th>
                                    <th class="px-4 py-3.5">SKU</th>
                                    <th class="px-4 py-3.5">Kategori</th>
                                    <th class="px-4 py-3.5 text-right">Harga Beli Terakhir</th>
                                    <th class="px-4 py-3.5">Tanggal Pembelian Terakhir</th>
                                    <th class="px-4 py-3.5 text-center">Total Pembelian</th>
                                    <th class="px-4 py-3.5 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 font-medium text-slate-700">
                                <template x-if="suppliedProducts.length === 0">
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center bg-white text-gray-500">
                                            Belum ada produk yang tercatat dipasok. Produk otomatis terhubung setelah transaksi pembelian dikonfirmasi penerimaannya di gudang.
                                        </td>
                                    </tr>
                                </template>

                                <template x-for="item in suppliedProducts" :key="item.id">
                                    <tr class="hover:bg-blue-50/30 transition-colors">
                                        <td class="px-4 py-3.5 font-bold text-gray-900">
                                            <span x-text="item.product_name"></span>
                                            <span class="text-[10.5px] font-normal text-gray-400 block" x-text="'Satuan dasar: ' + item.unit"></span>
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap font-mono text-gray-600" x-text="item.sku"></td>
                                        <td class="px-4 py-3.5 whitespace-nowrap">
                                            <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-700 text-[11px] font-semibold" x-text="item.category"></span>
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap text-right">
                                            <span class="font-mono font-black text-[#0052cc]" x-text="formatRupiah(item.last_purchase_price)"></span>
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap text-gray-600" x-text="item.last_purchase_at_label || item.last_purchase_date || '-'"></td>
                                        <td class="px-4 py-3.5 whitespace-nowrap text-center font-mono font-bold text-gray-900" x-text="item.total_transactions + ' Transaksi'"></td>
                                        <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                            @if($canManage)
                                                <button type="button"
                                                        @click="toggleSupplyStatus(item)"
                                                        title="Klik untuk ubah status pasokan"
                                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border cursor-pointer transition-colors"
                                                        :class="item.supply_status === 'AKTIF' ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-slate-100 text-slate-600 border-slate-300 hover:bg-slate-200'"
                                                        x-text="item.supply_status === 'AKTIF' ? 'Aktif Dipasok' : 'Tidak Aktif Dipasok'"></button>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border"
                                                      :class="item.supply_status === 'AKTIF' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-300'"
                                                      x-text="item.supply_status === 'AKTIF' ? 'Aktif Dipasok' : 'Tidak Aktif Dipasok'"></span>
                                            @endif
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-200 px-6 py-3.5 flex items-center justify-between bg-white text-xs text-gray-500">
                        <span x-text="'Halaman ' + prodMeta.current_page + ' dari ' + prodMeta.last_page"></span>
                        <div class="flex items-center gap-1.5" x-show="prodMeta.last_page > 1">
                            <button type="button" @click="goProdPage(prodMeta.current_page - 1)" :disabled="prodMeta.current_page <= 1" class="px-3 py-1.5 rounded-lg border border-gray-200 disabled:opacity-40 font-semibold cursor-pointer">Sebelumnya</button>
                            <button type="button" @click="goProdPage(prodMeta.current_page + 1)" :disabled="prodMeta.current_page >= prodMeta.last_page" class="px-3 py-1.5 rounded-lg border border-gray-200 disabled:opacity-40 font-semibold cursor-pointer">Berikutnya</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- TAB 3: RIWAYAT PEMBELIAN                                  -->
            <!-- ========================================================= -->
            <div x-show="activeTab === 'purchases'" style="display: none;" class="space-y-4">
                <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 flex-1">
                        <div class="relative">
                            <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-[19px]">search</span>
                            <input type="text"
                                   x-model="purFilters.search"
                                   @input="onPurSearchInput()"
                                   placeholder="Cari no. pembelian atau invoice..."
                                   class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] text-xs font-medium bg-slate-50/50">
                        </div>

                        <div class="relative">
                            <select x-model="purFilters.status"
                                    @change="purFilters.page = 1; loadPurchases();"
                                    class="appearance-none w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2.5 pr-9 text-xs text-gray-700 font-semibold focus:outline-none focus:border-[#0052cc] cursor-pointer">
                                <option value="all">Semua Status Penerimaan</option>
                                <option value="DRAFT">Draft</option>
                                <option value="MENUNGGU_PENERIMAAN">Menunggu Penerimaan</option>
                                <option value="DITERIMA_SEBAGIAN">Diterima Sebagian</option>
                                <option value="SELESAI">Selesai</option>
                                <option value="DIBATALKAN">Dibatalkan</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[18px]">expand_more</span>
                        </div>

                        <input type="date"
                               x-model="purFilters.date_from"
                               @change="purFilters.page = 1; loadPurchases();"
                               class="w-full px-3 py-2 rounded-xl border border-gray-200 text-xs font-medium text-gray-700">

                        <input type="date"
                               x-model="purFilters.date_to"
                               @change="purFilters.page = 1; loadPurchases();"
                               class="w-full px-3 py-2 rounded-xl border border-gray-200 text-xs font-medium text-gray-700">
                    </div>
                    <button type="button"
                            @click="resetPurFilters()"
                            class="px-3.5 py-2.5 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-bold cursor-pointer shrink-0">
                        Reset
                    </button>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                        <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                            <span class="material-symbols-rounded text-[20px] text-[#0052cc]">receipt_long</span>
                            <span>Riwayat Transaksi Pembelian Supplier</span>
                        </h3>
                        <span class="text-xs text-gray-500 font-medium" x-text="'Menampilkan ' + purchases.length + ' dari ' + purMeta.total + ' transaksi'"></span>
                    </div>

                    <div class="overflow-x-auto min-w-0 w-full">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold tracking-wider uppercase text-[11px] whitespace-nowrap">
                                    <th class="px-4 py-3.5">Nomor Pembelian</th>
                                    <th class="px-4 py-3.5">Tanggal Pembelian</th>
                                    <th class="px-4 py-3.5 text-center">Jumlah Produk</th>
                                    <th class="px-4 py-3.5 text-center">Total Kuantitas</th>
                                    <th class="px-4 py-3.5 text-right">Total Pembelian</th>
                                    <th class="px-4 py-3.5 text-center">Status Penerimaan</th>
                                    <th class="px-4 py-3.5 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 font-medium text-slate-700">
                                <template x-if="purchases.length === 0">
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center bg-white text-gray-500">
                                            Belum ada transaksi pembelian untuk supplier ini.
                                        </td>
                                    </tr>
                                </template>

                                <template x-for="p in purchases" :key="p.id">
                                    <tr class="hover:bg-blue-50/30 transition-colors">
                                        <td class="px-4 py-3.5 whitespace-nowrap">
                                            <button type="button"
                                                    @click="openPurchaseDetail(p.id)"
                                                    class="font-mono font-bold text-[#0052cc] hover:underline cursor-pointer block"
                                                    x-text="p.purchase_number"></button>
                                            <span x-show="p.supplier_invoice_number" class="text-[10.5px] text-gray-400 font-mono" x-text="'Inv: ' + p.supplier_invoice_number"></span>
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap text-gray-600" x-text="p.purchase_date_label || p.purchase_date"></td>
                                        <td class="px-4 py-3.5 whitespace-nowrap text-center font-mono font-bold" x-text="p.total_items + ' Produk'"></td>
                                        <td class="px-4 py-3.5 whitespace-nowrap text-center font-mono font-bold">
                                            <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-800" x-text="formatQty(p.total_qty)"></span>
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap text-right font-mono font-bold text-gray-900" x-text="formatRupiah(p.total_amount)"></td>
                                        <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border"
                                                  :class="getStatusBadge(p.status).class"
                                                  x-text="getStatusBadge(p.status).label"></span>
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                            <button type="button"
                                                    @click="openPurchaseDetail(p.id)"
                                                    class="px-3 py-1.5 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-100 font-bold text-[11px] cursor-pointer transition-colors">
                                                Detail
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-200 px-6 py-3.5 flex items-center justify-between bg-white text-xs text-gray-500">
                        <span x-text="'Halaman ' + purMeta.current_page + ' dari ' + purMeta.last_page"></span>
                        <div class="flex items-center gap-1.5" x-show="purMeta.last_page > 1">
                            <button type="button" @click="goPurPage(purMeta.current_page - 1)" :disabled="purMeta.current_page <= 1" class="px-3 py-1.5 rounded-lg border border-gray-200 disabled:opacity-40 font-semibold cursor-pointer">Sebelumnya</button>
                            <button type="button" @click="goPurPage(purMeta.current_page + 1)" :disabled="purMeta.current_page >= purMeta.last_page" class="px-3 py-1.5 rounded-lg border border-gray-200 disabled:opacity-40 font-semibold cursor-pointer">Berikutnya</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- TAB 4: RIWAYAT HARGA                                      -->
            <!-- ========================================================= -->
            <div x-show="activeTab === 'prices'" style="display: none;" class="space-y-4">
                <!-- Filter Bar -->
                <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 flex-1">
                        <div class="relative">
                            <select x-model="priceFilters.product_id"
                                    @change="priceFilters.page = 1; loadPriceHistory();"
                                    class="appearance-none w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2.5 pr-9 text-xs text-gray-700 font-semibold focus:outline-none focus:border-[#0052cc] cursor-pointer">
                                <option value="all">Semua Produk Supplier Ini</option>
                                <template x-for="prod in priceFilterProducts" :key="prod.id">
                                    <option :value="prod.id" x-text="prod.name + ' (' + prod.sku + ')'"></option>
                                </template>
                            </select>
                            <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[18px]">expand_more</span>
                        </div>

                        <div class="relative">
                            <select x-model="priceFilters.change_type"
                                    @change="priceFilters.page = 1; loadPriceHistory();"
                                    class="appearance-none w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2.5 pr-9 text-xs text-gray-700 font-semibold focus:outline-none focus:border-[#0052cc] cursor-pointer">
                                <option value="all">Semua Perubahan Harga</option>
                                <option value="NAIK">Kenaikan Harga</option>
                                <option value="TURUN">Penurunan Harga</option>
                                <option value="TETAP">Harga Tetap</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[18px]">expand_more</span>
                        </div>

                        <input type="date"
                               x-model="priceFilters.date_from"
                               @change="priceFilters.page = 1; loadPriceHistory();"
                               class="w-full px-3 py-2 rounded-xl border border-gray-200 text-xs font-medium text-gray-700">

                        <input type="date"
                               x-model="priceFilters.date_to"
                               @change="priceFilters.page = 1; loadPriceHistory();"
                               class="w-full px-3 py-2 rounded-xl border border-gray-200 text-xs font-medium text-gray-700">
                    </div>

                    <button type="button"
                            @click="resetPriceFilters()"
                            class="px-3.5 py-2.5 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-bold cursor-pointer shrink-0">
                        Reset
                    </button>
                </div>

                <!-- Grafik Perubahan Harga -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-xs">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                <span class="material-symbols-rounded text-[20px] text-[#0052cc]">show_chart</span>
                                <span>Grafik Perubahan Harga Beli dari Supplier Ini</span>
                            </h3>
                            <p class="text-xs text-gray-500 mt-0.5">Filter produk di atas untuk melihat tren harga spesifik produk. Klik titik grafik untuk detail riwayat produk.</p>
                        </div>
                        <template x-if="hoveredPoint">
                            <div class="px-3 py-1.5 rounded-xl bg-blue-50 border border-blue-200 text-xs flex items-center gap-2">
                                <span class="font-bold text-gray-900" x-text="hoveredPoint.product_name"></span>
                                <span class="text-gray-500" x-text="'(' + hoveredPoint.full_date + ')'"></span>
                                <span class="font-mono font-black text-[#0052cc]" x-text="formatRupiah(hoveredPoint.price)"></span>
                            </div>
                        </template>
                    </div>

                    <template x-if="priceChartSeries.length === 0">
                        <div class="h-44 flex items-center justify-center rounded-xl bg-slate-50 border border-dashed border-gray-200 text-xs text-gray-400">
                            Grafik akan tampil otomatis setelah penerimaan barang dari supplier ini tercatat.
                        </div>
                    </template>

                    <template x-if="priceChartSeries.length > 0">
                        <div class="w-full overflow-x-auto" x-data="{ get chart() { return buildSvgChart(priceChartSeries, 'price'); } }">
                            <svg viewBox="0 0 680 190" class="w-full min-w-[540px] h-48 overflow-visible">
                                <defs>
                                    <linearGradient id="supPriceAreaGrad" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#0052cc" stop-opacity="0.18" />
                                        <stop offset="100%" stop-color="#0052cc" stop-opacity="0.01" />
                                    </linearGradient>
                                </defs>
                                <line x1="36" y1="24" x2="644" y2="24" stroke="#e2e8f0" stroke-dasharray="4 4" />
                                <line x1="36" y1="95" x2="644" y2="95" stroke="#e2e8f0" stroke-dasharray="4 4" />
                                <line x1="36" y1="166" x2="644" y2="166" stroke="#cbd5e1" />

                                <text x="32" y="28" text-anchor="end" class="fill-gray-400 text-[9px] font-mono" x-text="formatRupiah(chart.max)"></text>
                                <text x="32" y="99" text-anchor="end" class="fill-gray-400 text-[9px] font-mono" x-text="formatRupiah(chart.mid)"></text>
                                <text x="32" y="170" text-anchor="end" class="fill-gray-400 text-[9px] font-mono" x-text="formatRupiah(chart.min)"></text>

                                <path x-show="chart.areaPath" :d="chart.areaPath" fill="url(#supPriceAreaGrad)" />
                                <polyline x-show="chart.points.length > 1" :points="chart.polyline" fill="none" stroke="#0052cc" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />

                                <template x-for="(pt, i) in chart.points" :key="i">
                                    <g class="cursor-pointer"
                                       @click="if(pt.product_id) openProductPriceDetail(pt.product_id)"
                                       @mouseenter="hoveredPoint = pt"
                                       @mouseleave="hoveredPoint = null">
                                        <circle :cx="pt.x" :cy="pt.y" r="5"
                                                :fill="pt.change_type === 'NAIK' ? '#e11d48' : (pt.change_type === 'TURUN' ? '#059669' : '#0052cc')"
                                                stroke="#ffffff" stroke-width="2" />
                                        <text :x="pt.x" y="182" text-anchor="middle" class="fill-gray-400 text-[9px] font-mono" x-text="pt.label"></text>
                                    </g>
                                </template>
                            </svg>
                        </div>
                    </template>
                </div>

                <!-- Tabel Riwayat Harga -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                        <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                            <span class="material-symbols-rounded text-[20px] text-[#0052cc]">history</span>
                            <span>Riwayat Perubahan Harga Beli Produk</span>
                        </h3>
                        <span class="text-xs text-gray-500 font-medium" x-text="'Menampilkan ' + priceHistories.length + ' dari ' + priceMeta.total + ' catatan'"></span>
                    </div>

                    <div class="overflow-x-auto min-w-0 w-full">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold tracking-wider uppercase text-[11px] whitespace-nowrap">
                                    <th class="px-4 py-3.5">Tanggal</th>
                                    <th class="px-4 py-3.5">Nama Produk</th>
                                    <th class="px-4 py-3.5">SKU</th>
                                    <th class="px-4 py-3.5 text-right">Harga Sebelumnya</th>
                                    <th class="px-4 py-3.5 text-right">Harga Beli Terbaru</th>
                                    <th class="px-4 py-3.5 text-center">Perubahan Harga</th>
                                    <th class="px-4 py-3.5">Nomor Pembelian</th>
                                    <th class="px-4 py-3.5 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 font-medium text-slate-700">
                                <template x-if="priceHistories.length === 0">
                                    <tr>
                                        <td colspan="8" class="px-6 py-12 text-center bg-white text-gray-500">
                                            Belum ada riwayat perubahan harga beli untuk supplier ini.
                                        </td>
                                    </tr>
                                </template>

                                <template x-for="h in priceHistories" :key="h.id">
                                    <tr class="hover:bg-blue-50/30 transition-colors">
                                        <td class="px-4 py-3.5 whitespace-nowrap text-gray-600" x-text="h.recorded_date_label || h.recorded_date"></td>
                                        <td class="px-4 py-3.5 font-bold text-gray-900" x-text="h.product_name"></td>
                                        <td class="px-4 py-3.5 whitespace-nowrap font-mono text-gray-600" x-text="h.sku"></td>
                                        <td class="px-4 py-3.5 whitespace-nowrap text-right font-mono text-gray-500" x-text="formatRupiah(h.previous_price)"></td>
                                        <td class="px-4 py-3.5 whitespace-nowrap text-right font-mono font-bold text-gray-900" x-text="formatRupiah(h.new_price)"></td>
                                        <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                            <template x-if="h.change_type === 'NAIK'">
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                    <span class="material-symbols-rounded text-[14px]">arrow_upward</span>
                                                    <span x-text="'+' + h.change_percent + '%'"></span>
                                                </span>
                                            </template>
                                            <template x-if="h.change_type === 'TURUN'">
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <span class="material-symbols-rounded text-[14px]">arrow_downward</span>
                                                    <span x-text="h.change_percent + '%'"></span>
                                                </span>
                                            </template>
                                            <template x-if="h.change_type === 'TETAP'">
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                                    <span>0% (Tetap)</span>
                                                </span>
                                            </template>
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap">
                                            <button type="button"
                                                    @click="if(h.purchase_id) openPurchaseDetail(h.purchase_id)"
                                                    class="font-mono font-bold text-[#0052cc] hover:underline cursor-pointer"
                                                    x-text="h.purchase_number"></button>
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                            <button type="button"
                                                    @click="openProductPriceDetail(h.product_id)"
                                                    class="px-3 py-1.5 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-100 font-bold text-[11px] cursor-pointer transition-colors">
                                                Detail
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-200 px-6 py-3.5 flex items-center justify-between bg-white text-xs text-gray-500">
                        <span x-text="'Halaman ' + priceMeta.current_page + ' dari ' + priceMeta.last_page"></span>
                        <div class="flex items-center gap-1.5" x-show="priceMeta.last_page > 1">
                            <button type="button" @click="goPricePage(priceMeta.current_page - 1)" :disabled="priceMeta.current_page <= 1" class="px-3 py-1.5 rounded-lg border border-gray-200 disabled:opacity-40 font-semibold cursor-pointer">Sebelumnya</button>
                            <button type="button" @click="goPricePage(priceMeta.current_page + 1)" :disabled="priceMeta.current_page >= priceMeta.last_page" class="px-3 py-1.5 rounded-lg border border-gray-200 disabled:opacity-40 font-semibold cursor-pointer">Berikutnya</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    @if($canManage)
    <!-- MODAL: EDIT SUPPLIER -->
    <template x-teleport="body">
        <div x-show="showEditModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <form @submit.prevent="saveEditSupplier()"
                  @click.away="if (!isSaving) showEditModal = false"
                  class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] overflow-hidden border border-gray-200 flex flex-col">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50 shrink-0">
                    <h3 class="text-base font-black text-gray-900">Edit Informasi Supplier</h3>
                    <button type="button" :disabled="isSaving" @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[22px]">close</span>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-4 text-xs flex-1">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1.5">Kode Supplier</label>
                            <input type="text" x-model="editForm.code" readonly class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 bg-slate-100 font-mono font-bold text-gray-700">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block font-bold text-gray-700 mb-1.5">Nama Supplier <span class="text-rose-500">*</span></label>
                            <input type="text" x-model="editForm.name" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] font-semibold">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1.5">Nama Perusahaan</label>
                            <input type="text" x-model="editForm.company_name" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc]">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 mb-1.5">Nama PIC</label>
                            <input type="text" x-model="editForm.pic_name" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc]">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1.5">Nomor Telepon</label>
                            <input type="text" x-model="editForm.phone" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] font-mono">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 mb-1.5">Email</label>
                            <input type="email" x-model="editForm.email" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc]">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block font-bold text-gray-700 mb-1.5">Alamat Lengkap</label>
                            <textarea x-model="editForm.address" rows="2" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc]"></textarea>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <label class="block font-bold text-gray-700 mb-1.5">Kota</label>
                                <input type="text" x-model="editForm.city" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc]">
                            </div>
                            <div>
                                <label class="block font-bold text-gray-700 mb-1.5">Status Supplier</label>
                                <select x-model="editForm.status" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 bg-white font-bold">
                                    <option value="AKTIF">Aktif</option>
                                    <option value="NONAKTIF">Nonaktif</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 border border-gray-200 space-y-2.5">
                        <label class="font-bold text-gray-800 block">Kategori Bisnis Supplier (Multi-Kategori, Opsional)</label>
                        <div class="flex flex-wrap gap-1.5" x-show="editForm.business_categories.length > 0">
                            <template x-for="(cat, idx) in editForm.business_categories" :key="cat">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-blue-50 text-[#0052cc] border border-blue-200 font-bold text-[11px]">
                                    <span x-text="cat"></span>
                                    <button type="button" @click="editForm.business_categories.splice(idx, 1)" class="hover:text-rose-600 cursor-pointer flex items-center">
                                        <span class="material-symbols-rounded text-[14px]">close</span>
                                    </button>
                                </span>
                            </template>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="text"
                                   x-model="editCategoryInput"
                                   @keydown.enter.prevent="addEditCategoryTag(editCategoryInput)"
                                   placeholder="Ketik kategori lalu tekan Enter..."
                                   class="flex-1 px-3 py-2 rounded-xl border border-gray-300 bg-white focus:outline-none focus:border-[#0052cc]">
                            <button type="button" @click="addEditCategoryTag(editCategoryInput)" class="px-3.5 py-2 rounded-xl bg-white border border-gray-300 hover:bg-gray-100 font-bold cursor-pointer">+ Tambah</button>
                        </div>
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            <template x-for="preset in suggestedCategories" :key="preset">
                                <button type="button"
                                        @click="toggleEditSuggestedCategory(preset)"
                                        :class="hasEditCategory(preset) ? 'bg-[#0052cc] text-white border-[#0052cc]' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-100'"
                                        class="px-2.5 py-1 rounded-lg border text-[11px] font-semibold cursor-pointer"
                                        x-text="preset"></button>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 mb-1.5">Catatan</label>
                        <textarea x-model="editForm.notes" rows="2" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc]"></textarea>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 bg-slate-50 flex justify-end gap-2.5 shrink-0">
                    <button type="button" :disabled="isSaving" @click="showEditModal = false" class="px-4 py-2 rounded-xl border border-gray-300 text-gray-700 font-bold text-xs cursor-pointer">Batal</button>
                    <button type="submit" :disabled="isSaving" class="px-5 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs cursor-pointer flex items-center gap-1.5">
                        <span x-show="isSaving" class="material-symbols-rounded text-[15px] animate-spin">progress_activity</span>
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </form>
        </div>
    </template>
    @endif

    <!-- MODAL: DETAIL TRANSAKSI PEMBELIAN -->
    <template x-teleport="body">
        <div x-show="showPurchaseModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div @click.away="showPurchaseModal = false"
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden border border-gray-200 flex flex-col">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50 shrink-0">
                    <div>
                        <h3 class="text-base font-black text-gray-900" x-text="'Detail Pembelian ' + (selectedPurchase?.purchase_number || '')"></h3>
                        <p class="text-xs text-gray-500 font-medium" x-text="(selectedPurchase?.supplier_name || '') + ' • ' + (selectedPurchase?.purchase_date_label || '')"></p>
                    </div>
                    <button type="button" @click="showPurchaseModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[22px]">close</span>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-4 text-xs flex-1">
                    <template x-if="selectedPurchase">
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div class="p-3 rounded-xl bg-slate-50 border border-gray-200">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase block">NO. INVOICE</span>
                                    <span class="font-mono font-bold text-gray-900 mt-0.5 block" x-text="selectedPurchase.supplier_invoice_number || '-'"></span>
                                </div>
                                <div class="p-3 rounded-xl bg-slate-50 border border-gray-200">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase block">GUDANG TUJUAN</span>
                                    <span class="font-bold text-gray-900 mt-0.5 block" x-text="selectedPurchase.warehouse_name"></span>
                                </div>
                                <div class="p-3 rounded-xl bg-slate-50 border border-gray-200">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase block">STATUS</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-bold border mt-1"
                                          :class="getStatusBadge(selectedPurchase.status).class"
                                          x-text="getStatusBadge(selectedPurchase.status).label"></span>
                                </div>
                                <div class="p-3 rounded-xl bg-blue-50 border border-blue-200">
                                    <span class="text-[10px] font-bold text-[#0052cc] uppercase block">TOTAL PEMBELIAN</span>
                                    <span class="font-mono font-black text-[#0052cc] text-sm mt-0.5 block" x-text="formatRupiah(selectedPurchase.total_amount)"></span>
                                </div>
                            </div>

                            <div class="overflow-x-auto border border-gray-200 rounded-xl">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 font-bold uppercase text-[10.5px]">
                                            <th class="px-3.5 py-2.5">Produk</th>
                                            <th class="px-3 py-2.5">SKU</th>
                                            <th class="px-3 py-2.5 text-center">Qty Beli</th>
                                            <th class="px-3 py-2.5 text-center">Diterima</th>
                                            <th class="px-3 py-2.5 text-right">Harga Beli</th>
                                            <th class="px-3.5 py-2.5 text-right">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-for="it in selectedPurchase.items" :key="it.id">
                                            <tr>
                                                <td class="px-3.5 py-2.5 font-bold text-gray-900" x-text="it.product_name"></td>
                                                <td class="px-3 py-2.5 font-mono text-gray-600" x-text="it.sku"></td>
                                                <td class="px-3 py-2.5 text-center font-mono font-bold" x-text="formatQty(it.qty) + ' ' + it.unit"></td>
                                                <td class="px-3 py-2.5 text-center font-mono font-bold text-emerald-700" x-text="formatQty(it.received_qty)"></td>
                                                <td class="px-3 py-2.5 text-right font-mono" x-text="formatRupiah(it.purchase_price)"></td>
                                                <td class="px-3.5 py-2.5 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(it.subtotal)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 bg-slate-50 flex justify-end shrink-0">
                    <button type="button" @click="showPurchaseModal = false" class="px-5 py-2 rounded-xl border border-gray-300 text-gray-700 font-bold text-xs cursor-pointer">Tutup</button>
                </div>
            </div>
        </div>
    </template>

    <!-- MODAL: DETAIL RIWAYAT HARGA PRODUK -->
    <template x-teleport="body">
        <div x-show="showPriceDetailModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div @click.away="showPriceDetailModal = false"
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden border border-gray-200 flex flex-col">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50 shrink-0">
                    <div>
                        <h3 class="text-base font-black text-gray-900" x-text="'Riwayat Harga Beli: ' + (productPriceDetail?.product?.name || '')"></h3>
                        <p class="text-xs text-gray-500 font-mono" x-text="'SKU: ' + (productPriceDetail?.product?.sku || '-') + ' • Harga Beli Global Terakhir: ' + formatRupiah(productPriceDetail?.product?.last_cost_price || 0)"></p>
                    </div>
                    <button type="button" @click="showPriceDetailModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[22px]">close</span>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-4 text-xs flex-1">
                    <template x-if="productPriceDetail">
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div class="p-3 rounded-xl bg-slate-50 border border-gray-200">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase block">HARGA PERTAMA</span>
                                    <span class="font-mono font-black text-gray-900 text-sm mt-0.5 block" x-text="formatRupiah(productPriceDetail.stats.first_price)"></span>
                                </div>
                                <div class="p-3 rounded-xl bg-blue-50 border border-blue-200">
                                    <span class="text-[10px] font-bold text-[#0052cc] uppercase block">HARGA TERAKHIR</span>
                                    <span class="font-mono font-black text-[#0052cc] text-sm mt-0.5 block" x-text="formatRupiah(productPriceDetail.stats.last_price)"></span>
                                </div>
                                <div class="p-3 rounded-xl bg-rose-50 border border-rose-200">
                                    <span class="text-[10px] font-bold text-rose-700 uppercase block">TERTINGGI</span>
                                    <span class="font-mono font-black text-rose-700 text-sm mt-0.5 block" x-text="formatRupiah(productPriceDetail.stats.highest_price)"></span>
                                </div>
                                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-200">
                                    <span class="text-[10px] font-bold text-emerald-700 uppercase block">TERENDAH</span>
                                    <span class="font-mono font-black text-emerald-700 text-sm mt-0.5 block" x-text="formatRupiah(productPriceDetail.stats.lowest_price)"></span>
                                </div>
                            </div>

                            <div class="overflow-x-auto border border-gray-200 rounded-xl">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 font-bold uppercase text-[10.5px]">
                                            <th class="px-4 py-3">Tanggal</th>
                                            <th class="px-4 py-3">Supplier</th>
                                            <th class="px-4 py-3">No. Pembelian</th>
                                            <th class="px-4 py-3 text-right">Harga Beli</th>
                                            <th class="px-4 py-3 text-center">Perubahan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-for="row in productPriceDetail.history" :key="row.id">
                                            <tr>
                                                <td class="px-4 py-3 text-gray-700" x-text="row.date_label"></td>
                                                <td class="px-4 py-3 font-semibold text-gray-900" x-text="row.supplier_name"></td>
                                                <td class="px-4 py-3 font-mono font-bold text-[#0052cc]" x-text="row.purchase_number"></td>
                                                <td class="px-4 py-3 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(row.new_price)"></td>
                                                <td class="px-4 py-3 text-center">
                                                    <span class="px-2 py-0.5 rounded-full text-[10.5px] font-bold border"
                                                          :class="row.change_type === 'NAIK' ? 'bg-rose-50 text-rose-700 border-rose-200' : (row.change_type === 'TURUN' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200')"
                                                          x-text="row.change_type === 'NAIK' ? ('+' + row.change_percent + '%') : (row.change_type === 'TURUN' ? (row.change_percent + '%') : '0%')"></span>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 bg-slate-50 flex justify-end shrink-0">
                    <button type="button" @click="showPriceDetailModal = false" class="px-5 py-2 rounded-xl border border-gray-300 text-gray-700 font-bold text-xs cursor-pointer">Tutup</button>
                </div>
            </div>
        </div>
    </template>
</div>

@once
<script>
function supplierDetailApp(config) {
    return {
        supplierId: Number(config.supplierId),
        canManage: Boolean(config.canManage),
        listUrl: config.listUrl,
        csrfToken: config.csrfToken,

        activeTab: (['info', 'products', 'purchases', 'prices'].includes(new URLSearchParams(window.location.search).get('tab'))
            ? new URLSearchParams(window.location.search).get('tab')
            : 'info'),

        supplier: null,
        isLoading: true,
        isSaving: false,

        // Tab 2: Produk Dipasok
        suppliedProducts: [],
        suppliedCategories: [],
        prodMeta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
        prodFilters: { search: '', category: 'all', supply_status: 'all', page: 1 },
        prodSearchTimer: null,

        // Tab 3: Riwayat Pembelian
        purchases: [],
        purMeta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
        purFilters: { search: '', status: 'all', date_from: '', date_to: '', page: 1 },
        purSearchTimer: null,
        showPurchaseModal: false,
        selectedPurchase: null,

        // Tab 4: Riwayat Harga
        priceHistories: [],
        priceFilterProducts: [],
        priceChartSeries: [],
        priceMeta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
        priceFilters: { product_id: 'all', change_type: 'all', date_from: '', date_to: '', page: 1 },
        hoveredPoint: null,
        showPriceDetailModal: false,
        productPriceDetail: null,

        // Edit Modal
        showEditModal: false,
        editCategoryInput: '',
        suggestedCategories: [
            'Distributor Makanan',
            'Distributor Minuman',
            'Distributor Sembako',
            'Distributor Perlengkapan Kebersihan',
            'Distributor Peralatan',
            'Supplier Umum'
        ],
        editForm: {
            code: '',
            name: '',
            company_name: '',
            pic_name: '',
            phone: '',
            email: '',
            address: '',
            city: '',
            business_categories: [],
            status: 'AKTIF',
            notes: ''
        },

        notify(msg, type = 'success') {
            if (typeof window.triggerToast === 'function') {
                window.triggerToast(msg, type);
            }
        },

        formatRupiah(val) {
            const n = Number(val) || 0;
            const abs = Math.abs(n);
            const formatted = new Intl.NumberFormat('id-ID').format(abs);
            return (n < 0 ? '-Rp' : 'Rp') + formatted;
        },

        formatQty(val) {
            const n = Number(val) || 0;
            return Number.isInteger(n) ? n.toString() : n.toFixed(2).replace(/\.?0+$/, '');
        },

        getStatusBadge(status) {
            switch (status) {
                case 'DRAFT': return { label: 'Draft', class: 'bg-slate-100 text-slate-700 border-slate-300' };
                case 'MENUNGGU_PENERIMAAN': return { label: 'Menunggu Penerimaan', class: 'bg-amber-50 text-amber-700 border-amber-300' };
                case 'DITERIMA_SEBAGIAN': return { label: 'Diterima Sebagian', class: 'bg-blue-50 text-[#0052cc] border-blue-200' };
                case 'SELESAI': return { label: 'Selesai', class: 'bg-emerald-50 text-emerald-700 border-emerald-300' };
                case 'DIBATALKAN': return { label: 'Dibatalkan', class: 'bg-rose-50 text-rose-700 border-rose-200' };
                default: return { label: status || '-', class: 'bg-gray-100 text-gray-700 border-gray-300' };
            }
        },

        async initDetail() {
            await this.loadSupplier();
            if (this.supplier) {
                await Promise.all([
                    this.loadSuppliedProducts(),
                    this.loadPurchases(),
                    this.loadPriceHistory()
                ]);
            }
        },

        async refreshAll() {
            await this.initDetail();
            this.notify('Data detail supplier berhasil disinkronkan.', 'info');
        },

        async loadSupplier() {
            this.isLoading = true;
            try {
                const res = await fetch(`/api/suppliers/${this.supplierId}`, { headers: { 'Accept': 'application/json' } });
                if (res.ok) {
                    const json = await res.json();
                    if (json.success) {
                        this.supplier = json.data;
                    }
                } else {
                    this.supplier = null;
                }
            } catch (e) {
                this.notify('Gagal memuat profil supplier.', 'error');
            } finally {
                this.isLoading = false;
            }
        },

        async loadSuppliedProducts() {
            try {
                const params = new URLSearchParams({ page: this.prodFilters.page, per_page: 15 });
                if (this.prodFilters.search.trim()) params.set('search', this.prodFilters.search.trim());
                if (this.prodFilters.category !== 'all') params.set('category', this.prodFilters.category);
                if (this.prodFilters.supply_status !== 'all') params.set('supply_status', this.prodFilters.supply_status);

                const res = await fetch(`/api/suppliers/${this.supplierId}/products?` + params.toString(), { headers: { 'Accept': 'application/json' } });
                if (res.ok) {
                    const json = await res.json();
                    if (json.success) {
                        this.suppliedProducts = json.data || [];
                        this.suppliedCategories = json.categories || [];
                        this.prodMeta = json.meta || this.prodMeta;
                    }
                }
            } catch (e) {}
        },

        onProdSearchInput() {
            this.prodFilters.page = 1;
            if (this.prodSearchTimer) clearTimeout(this.prodSearchTimer);
            this.prodSearchTimer = setTimeout(() => this.loadSuppliedProducts(), 300);
        },

        goProdPage(p) {
            if (p < 1 || p > this.prodMeta.last_page) return;
            this.prodFilters.page = p;
            this.loadSuppliedProducts();
        },

        async toggleSupplyStatus(item) {
            if (!this.canManage) return;
            const nextStatus = item.supply_status === 'AKTIF' ? 'TIDAK_AKTIF' : 'AKTIF';
            try {
                const res = await fetch(`/api/suppliers/${this.supplierId}/products/${item.product_id}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    body: JSON.stringify({ supply_status: nextStatus })
                });
                const json = await res.json();
                if (res.ok && json.success) {
                    item.supply_status = json.data.supply_status;
                    this.notify(json.message, 'success');
                } else {
                    this.notify(json.message || 'Gagal memperbarui status pasokan.', 'error');
                }
            } catch (e) {
                this.notify('Gagal terhubung ke server.', 'error');
            }
        },

        async loadPurchases() {
            try {
                const params = new URLSearchParams({ page: this.purFilters.page, per_page: 15 });
                if (this.purFilters.search.trim()) params.set('search', this.purFilters.search.trim());
                if (this.purFilters.status !== 'all') params.set('status', this.purFilters.status);
                if (this.purFilters.date_from) params.set('date_from', this.purFilters.date_from);
                if (this.purFilters.date_to) params.set('date_to', this.purFilters.date_to);

                const res = await fetch(`/api/suppliers/${this.supplierId}/purchases?` + params.toString(), { headers: { 'Accept': 'application/json' } });
                if (res.ok) {
                    const json = await res.json();
                    if (json.success) {
                        this.purchases = json.data || [];
                        this.purMeta = json.meta || this.purMeta;
                    }
                }
            } catch (e) {}
        },

        onPurSearchInput() {
            this.purFilters.page = 1;
            if (this.purSearchTimer) clearTimeout(this.purSearchTimer);
            this.purSearchTimer = setTimeout(() => this.loadPurchases(), 300);
        },

        goPurPage(p) {
            if (p < 1 || p > this.purMeta.last_page) return;
            this.purFilters.page = p;
            this.loadPurchases();
        },

        resetPurFilters() {
            this.purFilters = { search: '', status: 'all', date_from: '', date_to: '', page: 1 };
            this.loadPurchases();
        },

        async openPurchaseDetail(purchaseId) {
            try {
                const res = await fetch(`/api/purchases/${purchaseId}`, { headers: { 'Accept': 'application/json' } });
                if (res.ok) {
                    const json = await res.json();
                    if (json.success) {
                        this.selectedPurchase = json.data;
                        this.showPurchaseModal = true;
                    }
                }
            } catch (e) {
                this.notify('Gagal memuat detail transaksi pembelian.', 'error');
            }
        },

        async loadPriceHistory() {
            try {
                const params = new URLSearchParams({ page: this.priceFilters.page, per_page: 15 });
                if (this.priceFilters.product_id !== 'all') params.set('product_id', this.priceFilters.product_id);
                if (this.priceFilters.change_type !== 'all') params.set('change_type', this.priceFilters.change_type);
                if (this.priceFilters.date_from) params.set('date_from', this.priceFilters.date_from);
                if (this.priceFilters.date_to) params.set('date_to', this.priceFilters.date_to);

                const res = await fetch(`/api/suppliers/${this.supplierId}/price-history?` + params.toString(), { headers: { 'Accept': 'application/json' } });
                if (res.ok) {
                    const json = await res.json();
                    if (json.success) {
                        this.priceHistories = json.data || [];
                        this.priceFilterProducts = json.products || [];
                        this.priceChartSeries = json.chart_series || [];
                        this.priceMeta = json.meta || this.priceMeta;
                    }
                }
            } catch (e) {}
        },

        goPricePage(p) {
            if (p < 1 || p > this.priceMeta.last_page) return;
            this.priceFilters.page = p;
            this.loadPriceHistory();
        },

        resetPriceFilters() {
            this.priceFilters = { product_id: 'all', change_type: 'all', date_from: '', date_to: '', page: 1 };
            this.loadPriceHistory();
        },

        async openProductPriceDetail(productId) {
            try {
                const res = await fetch(`/api/purchases/price-history/product/${productId}`, { headers: { 'Accept': 'application/json' } });
                if (res.ok) {
                    const json = await res.json();
                    if (json.success) {
                        this.productPriceDetail = json.data;
                        this.showPriceDetailModal = true;
                    }
                }
            } catch (e) {
                this.notify('Gagal memuat riwayat harga produk.', 'error');
            }
        },

        buildSvgChart(series, priceKey = 'price') {
            if (!Array.isArray(series) || series.length === 0) {
                return { points: [], polyline: '', areaPath: '', min: 0, max: 0, mid: 0 };
            }
            const width = 680;
            const height = 190;
            const padX = 36;
            const padY = 24;
            const values = series.map(s => Number(s[priceKey]) || 0);
            let minVal = Math.min(...values);
            let maxVal = Math.max(...values);
            if (minVal === maxVal) {
                minVal = Math.max(0, minVal * 0.9);
                maxVal = maxVal * 1.1 || 1000;
            } else {
                const padding = (maxVal - minVal) * 0.15;
                minVal = Math.max(0, minVal - padding);
                maxVal = maxVal + padding;
            }
            const range = Math.max(1, maxVal - minVal);
            const usableW = width - (padX * 2);
            const usableH = height - (padY * 2);

            const points = series.map((item, idx) => {
                const x = series.length === 1 ? width / 2 : padX + (idx / (series.length - 1)) * usableW;
                const val = Number(item[priceKey]) || 0;
                const y = padY + usableH - ((val - minVal) / range) * usableH;
                return { ...item, x: Math.round(x * 10) / 10, y: Math.round(y * 10) / 10, val };
            });

            const polyline = points.map(p => `${p.x},${p.y}`).join(' ');
            const first = points[0];
            const last = points[points.length - 1];
            const baselineY = height - padY;
            const areaPath = points.length > 1
                ? `M ${first.x},${baselineY} L ${points.map(p => `${p.x},${p.y}`).join(' L ')} L ${last.x},${baselineY} Z`
                : '';

            return {
                points,
                polyline,
                areaPath,
                min: Math.round(minVal),
                max: Math.round(maxVal),
                mid: Math.round((minVal + maxVal) / 2)
            };
        },

        openEditModal() {
            if (!this.canManage || !this.supplier) return;
            this.editCategoryInput = '';
            this.editForm = {
                code: this.supplier.code,
                name: this.supplier.name || '',
                company_name: this.supplier.company_name || '',
                pic_name: this.supplier.pic_name || '',
                phone: this.supplier.phone || '',
                email: this.supplier.email || '',
                address: this.supplier.address || '',
                city: this.supplier.city || '',
                business_categories: Array.isArray(this.supplier.business_categories) ? [...this.supplier.business_categories] : [],
                status: this.supplier.status || 'AKTIF',
                notes: this.supplier.notes || ''
            };
            this.showEditModal = true;
        },

        hasEditCategory(cat) {
            const lower = String(cat || '').trim().toLowerCase();
            return this.editForm.business_categories.some(c => c.toLowerCase() === lower);
        },

        addEditCategoryTag(raw) {
            const val = String(raw || '').trim();
            if (!val) return;
            if (!this.hasEditCategory(val)) {
                this.editForm.business_categories.push(val);
            }
            this.editCategoryInput = '';
        },

        toggleEditSuggestedCategory(cat) {
            const lower = String(cat || '').trim().toLowerCase();
            const idx = this.editForm.business_categories.findIndex(c => c.toLowerCase() === lower);
            if (idx >= 0) {
                this.editForm.business_categories.splice(idx, 1);
            } else {
                this.editForm.business_categories.push(cat);
            }
        },

        async saveEditSupplier() {
            if (this.isSaving || !this.canManage) return;
            const cleanName = (this.editForm.name || '').trim();
            if (!cleanName) {
                this.notify('Nama supplier wajib diisi.', 'error');
                return;
            }
            if (this.editCategoryInput.trim()) {
                this.addEditCategoryTag(this.editCategoryInput);
            }
            this.isSaving = true;
            try {
                const res = await fetch(`/api/suppliers/${this.supplierId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    body: JSON.stringify({
                        name: cleanName,
                        company_name: this.editForm.company_name || null,
                        pic_name: this.editForm.pic_name || null,
                        phone: this.editForm.phone || null,
                        email: this.editForm.email || null,
                        address: this.editForm.address || null,
                        city: this.editForm.city || null,
                        business_categories: this.editForm.business_categories,
                        status: this.editForm.status,
                        notes: this.editForm.notes || null
                    })
                });
                const json = await res.json();
                if (!res.ok || !json.success) {
                    this.notify(json.message || 'Gagal memperbarui supplier.', 'error');
                    return;
                }
                this.supplier = json.data;
                this.showEditModal = false;
                this.notify(json.message, 'success');
            } catch (e) {
                this.notify('Terjadi kesalahan koneksi saat memperbarui supplier.', 'error');
            } finally {
                this.isSaving = false;
            }
        },

        async toggleSupplierStatus() {
            if (this.isSaving || !this.canManage || !this.supplier) return;
            const target = this.supplier.status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
            if (target === 'NONAKTIF' && !confirm(`Nonaktifkan supplier [${this.supplier.name}]? Supplier nonaktif tidak akan muncul pada pembelian baru, namun seluruh riwayat tetap tersimpan.`)) {
                return;
            }
            this.isSaving = true;
            try {
                const res = await fetch(`/api/suppliers/${this.supplierId}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    body: JSON.stringify({ status: target })
                });
                const json = await res.json();
                if (res.ok && json.success) {
                    this.supplier = json.data;
                    this.notify(json.message, 'success');
                } else {
                    this.notify(json.message || 'Gagal mengubah status supplier.', 'error');
                }
            } catch (e) {
                this.notify('Gagal terhubung ke server.', 'error');
            } finally {
                this.isSaving = false;
            }
        }
    };
}
</script>
@endonce
