@php
    $canManage = $canManage ?? true;
    $detailBaseUrl = $detailBaseUrl ?? url('/suppliers');
@endphp

<div x-data="supplierListApp({
         canManage: @js((bool) $canManage),
         detailBaseUrl: @js($detailBaseUrl),
         csrfToken: @js(csrf_token())
     })"
     x-init="initList()"
     @open-create-supplier.window="openCreateModal()"
     @refresh-suppliers.window="refreshAll()"
     class="space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-gray-900 tracking-tight">Master Supplier</h2>
            <p class="text-xs sm:text-[13px] text-gray-500 font-medium mt-1">
                Kelola data pemasok barang, produk yang pernah dipasok, riwayat pembelian, dan pergerakan harga beli.
            </p>
        </div>
        @if($canManage)
            <button type="button"
                    @click="openCreateModal()"
                    class="px-4 py-2.5 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold shadow-xs inline-flex items-center gap-1.5 cursor-pointer transition-colors self-start md:self-auto">
                <span class="material-symbols-rounded text-[18px]">add_business</span>
                <span>Tambah Supplier</span>
            </button>
        @else
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-purple-50 text-purple-800 border border-purple-200 text-xs font-bold self-start md:self-auto">
                <span class="material-symbols-rounded text-[16px]">visibility</span>
                <span>Mode Pemantauan Owner (Read-Only)</span>
            </span>
        @endif
    </div>

    <!-- Summary Cards (4 Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">TOTAL SUPPLIER</span>
                <span class="material-symbols-rounded text-[22px] text-[#0052cc]">store</span>
            </div>
            <div class="mt-3 flex items-baseline justify-between">
                <span class="text-3xl font-black text-gray-900 font-mono" x-text="summary.total_suppliers">0</span>
                <span class="text-[11px] font-semibold text-gray-400">Terdaftar</span>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-emerald-200 border-l-4 border-l-emerald-500 p-5 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-emerald-700 tracking-wider uppercase">SUPPLIER AKTIF</span>
                <span class="material-symbols-rounded text-[22px] text-emerald-600">verified</span>
            </div>
            <div class="mt-3 flex items-baseline justify-between">
                <span class="text-3xl font-black text-emerald-600 font-mono" x-text="summary.active_suppliers">0</span>
                <span class="text-[11px] font-semibold text-emerald-700">Siap transaksi</span>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-rose-200 border-l-4 border-l-rose-500 p-5 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-rose-700 tracking-wider uppercase">SUPPLIER NONAKTIF</span>
                <span class="material-symbols-rounded text-[22px] text-rose-500">block</span>
            </div>
            <div class="mt-3 flex items-baseline justify-between">
                <span class="text-3xl font-black text-rose-600 font-mono" x-text="summary.inactive_suppliers">0</span>
                <span class="text-[11px] font-semibold text-rose-700">Arsip tetap aman</span>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-blue-200 border-l-4 border-l-[#0052cc] p-5 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-[#0052cc] tracking-wider uppercase">PRODUK PERNAH DIPASOK</span>
                <span class="material-symbols-rounded text-[22px] text-[#0052cc]">inventory_2</span>
            </div>
            <div class="mt-3 flex items-baseline justify-between">
                <span class="text-3xl font-black text-[#0052cc] font-mono" x-text="summary.total_supplied_products">0</span>
                <span class="text-[11px] font-semibold text-blue-700">SKU terhubung</span>
            </div>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs">
        <div class="flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 flex-1">
                <!-- Search -->
                <div class="relative lg:col-span-2">
                    <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-[19px]">search</span>
                    <input type="text"
                           x-model="filters.search"
                           @input="onSearchInput()"
                           placeholder="Cari kode, nama supplier, perusahaan, PIC, telepon, kota..."
                           class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 text-xs font-medium bg-slate-50/50">
                </div>

                <!-- Status Filter -->
                <div class="relative">
                    <select x-model="filters.status"
                            @change="filters.page = 1; loadSuppliers();"
                            class="appearance-none w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2.5 pr-9 text-xs text-gray-700 font-semibold focus:outline-none focus:border-[#0052cc] cursor-pointer">
                        <option value="all">Semua Status</option>
                        <option value="AKTIF">Aktif</option>
                        <option value="NONAKTIF">Nonaktif</option>
                    </select>
                    <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[18px]">expand_more</span>
                </div>

                <!-- Category Filter -->
                <div class="relative">
                    <select x-model="filters.category"
                            @change="filters.page = 1; loadSuppliers();"
                            class="appearance-none w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2.5 pr-9 text-xs text-gray-700 font-semibold focus:outline-none focus:border-[#0052cc] cursor-pointer">
                        <option value="all">Semua Kategori</option>
                        <template x-for="cat in availableCategories" :key="cat">
                            <option :value="cat" x-text="cat"></option>
                        </template>
                    </select>
                    <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[18px]">expand_more</span>
                </div>

                <!-- Sort -->
                <div class="relative">
                    <select x-model="filters.sort"
                            @change="filters.page = 1; loadSuppliers();"
                            class="appearance-none w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2.5 pr-9 text-xs text-gray-700 font-semibold focus:outline-none focus:border-[#0052cc] cursor-pointer">
                        <option value="name_asc">Urutkan: Nama A-Z</option>
                        <option value="name_desc">Nama Z-A</option>
                        <option value="code_asc">Kode A-Z</option>
                        <option value="products_desc">Produk Terbanyak</option>
                        <option value="newest">Terbaru Ditambahkan</option>
                    </select>
                    <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[18px]">expand_more</span>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <button type="button"
                        @click="resetFilters()"
                        class="px-3.5 py-2.5 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-bold cursor-pointer transition-colors">
                    Reset
                </button>
            </div>
        </div>
    </div>

    <!-- Tabel List Supplier -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
            <div class="flex items-center gap-2">
                <span class="material-symbols-rounded text-[20px] text-[#0052cc]">local_shipping</span>
                <h3 class="text-sm font-bold text-gray-900">Daftar Master Supplier</h3>
            </div>
            <span class="text-xs text-gray-500 font-medium" x-text="'Menampilkan ' + suppliers.length + ' dari ' + meta.total + ' supplier'"></span>
        </div>

        <div class="overflow-x-auto min-w-0 w-full">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold tracking-wider uppercase text-[11px] whitespace-nowrap">
                        <th class="px-4 py-3.5">Kode Supplier</th>
                        <th class="px-4 py-3.5">Nama Supplier</th>
                        <th class="px-4 py-3.5">Kontak</th>
                        <th class="px-4 py-3.5">Alamat</th>
                        <th class="px-4 py-3.5">Jenis Barang</th>
                        <th class="px-4 py-3.5 text-center">Total Produk</th>
                        <th class="px-4 py-3.5 text-center">Status</th>
                        <th class="px-4 py-3.5 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-medium text-slate-700">
                    <template x-if="!isLoading && suppliers.length === 0">
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center bg-white">
                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto space-y-2.5">
                                    <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center">
                                        <span class="material-symbols-rounded text-[26px]">store</span>
                                    </div>
                                    <p class="font-bold text-gray-800 text-sm">Belum ada data supplier</p>
                                    <p class="text-xs text-gray-500">Tambahkan supplier baru untuk mulai mencatat pemasok produk dan transaksi pembelian.</p>
                                    @if($canManage)
                                        <button type="button"
                                                @click="openCreateModal()"
                                                class="mt-2 px-4 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold flex items-center gap-1.5 cursor-pointer">
                                            <span class="material-symbols-rounded text-[16px]">add</span>
                                            <span>Tambah Supplier</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </template>

                    <template x-for="sup in suppliers" :key="sup.id">
                        <tr class="hover:bg-blue-50/30 transition-colors align-top">
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <a :href="detailBaseUrl + '/' + sup.id"
                                   class="font-mono font-bold text-[#0052cc] hover:underline block"
                                   x-text="sup.code"></a>
                            </td>
                            <td class="px-4 py-3.5 min-w-[200px] max-w-[260px]">
                                <a :href="detailBaseUrl + '/' + sup.id"
                                   class="font-bold text-gray-900 hover:text-[#0052cc] transition-colors block leading-snug break-words"
                                   x-text="sup.name"></a>
                                <span x-show="sup.company_name"
                                      class="text-[11px] text-gray-500 block mt-0.5 break-words"
                                      x-text="sup.company_name"></span>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <div class="font-mono font-semibold text-gray-800" x-text="sup.phone || '-'"></div>
                                <div x-show="sup.pic_name" class="text-[11px] text-gray-500 mt-0.5" x-text="'PIC: ' + sup.pic_name"></div>
                            </td>
                            <td class="px-4 py-3.5 min-w-[180px] max-w-[250px]">
                                <p class="text-gray-700 leading-snug line-clamp-2 break-words"
                                   :title="sup.address || ''"
                                   x-text="sup.address || '-'"></p>
                                <span x-show="sup.city" class="text-[11px] text-gray-400 block mt-0.5" x-text="sup.city"></span>
                            </td>
                            <td class="px-4 py-3.5 min-w-[160px] max-w-[220px]">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 border border-slate-200 text-[11px] font-semibold leading-snug break-words"
                                      x-text="sup.jenis_barang_summary || 'Supplier Umum'"></span>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-blue-50 text-[#0052cc] border border-blue-200 font-mono font-bold text-[11px]"
                                      x-text="sup.total_products + ' Produk'"></span>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border"
                                      :class="sup.status === 'AKTIF' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'"
                                      x-text="sup.status === 'AKTIF' ? 'Aktif' : 'Nonaktif'"></span>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                <div class="inline-flex items-center gap-1.5">
                                    <a :href="detailBaseUrl + '/' + sup.id"
                                       class="px-2.5 py-1.5 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-100 font-bold text-[11px] transition-colors">
                                        Detail
                                    </a>
                                    @if($canManage)
                                        <button type="button"
                                                @click="openEditModal(sup)"
                                                class="px-2.5 py-1.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 font-bold text-[11px] cursor-pointer transition-colors">
                                            Edit
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="border-t border-gray-200 px-6 py-3.5 flex flex-col sm:flex-row items-center justify-between gap-3 bg-white text-xs text-gray-500">
            <span x-text="'Halaman ' + meta.current_page + ' dari ' + meta.last_page + ' (' + meta.total + ' total supplier)'"></span>
            <div class="flex items-center gap-1.5" x-show="meta.last_page > 1">
                <button type="button"
                        @click="goToPage(meta.current_page - 1)"
                        :disabled="meta.current_page <= 1"
                        class="px-3 py-1.5 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed font-semibold cursor-pointer">
                    Sebelumnya
                </button>
                <button type="button"
                        @click="goToPage(meta.current_page + 1)"
                        :disabled="meta.current_page >= meta.last_page"
                        class="px-3 py-1.5 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed font-semibold cursor-pointer">
                    Berikutnya
                </button>
            </div>
        </div>
    </div>

    @if($canManage)
    <!-- MODAL: TAMBAH / EDIT SUPPLIER -->
    <template x-teleport="body">
        <div x-show="showModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <form @submit.prevent="saveSupplier()"
                  @click.away="if (!isSaving) showModal = false"
                  class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] overflow-hidden border border-gray-200 flex flex-col">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50 shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center">
                            <span class="material-symbols-rounded text-[22px]" x-text="modalMode === 'create' ? 'add_business' : 'edit_square'"></span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-gray-900" x-text="modalMode === 'create' ? 'Tambah Supplier Baru' : 'Edit Informasi Supplier'"></h3>
                            <p class="text-xs text-gray-500">Produk dipasok akan terbentuk otomatis saat penerimaan pembelian barang dikonfirmasi.</p>
                        </div>
                    </div>
                    <button type="button" :disabled="isSaving" @click="showModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[22px]">close</span>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-4 text-xs flex-1">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1.5">Kode Supplier</label>
                            <div class="relative">
                                <input type="text"
                                       x-model="form.code"
                                       readonly
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 bg-slate-100 text-gray-800 font-mono font-bold focus:outline-none">
                                <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] font-bold bg-blue-100 text-[#0052cc] px-2 py-0.5 rounded">Otomatis</span>
                            </div>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block font-bold text-gray-700 mb-1.5">Nama Supplier <span class="text-rose-500">*</span></label>
                            <input type="text"
                                   x-model="form.name"
                                   placeholder="Contoh: PT Sumber Makmur Distribusi"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-semibold">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1.5">Nama Perusahaan</label>
                            <input type="text"
                                   x-model="form.company_name"
                                   placeholder="Contoh: PT Sumber Makmur Jaya Tbk"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20">
                        </div>

                        <div>
                            <label class="block font-bold text-gray-700 mb-1.5">Nama PIC (Person in Charge)</label>
                            <input type="text"
                                   x-model="form.pic_name"
                                   placeholder="Contoh: Bapak Andi Wijaya"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1.5">Nomor Telepon</label>
                            <input type="text"
                                   x-model="form.phone"
                                   placeholder="Contoh: 0812-3456-7890"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono">
                        </div>

                        <div>
                            <label class="block font-bold text-gray-700 mb-1.5">Email</label>
                            <input type="email"
                                   x-model="form.email"
                                   placeholder="Contoh: order@sumbermakmur.co.id"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block font-bold text-gray-700 mb-1.5">Alamat Lengkap</label>
                            <textarea x-model="form.address"
                                      rows="2"
                                      placeholder="Alamat kantor / gudang supplier..."
                                      class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20"></textarea>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <label class="block font-bold text-gray-700 mb-1.5">Kota</label>
                                <input type="text"
                                       x-model="form.city"
                                       placeholder="Contoh: Bandar Lampung"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20">
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 mb-1.5">Status Supplier</label>
                                <select x-model="form.status"
                                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 bg-white font-bold text-gray-800 focus:outline-none focus:border-[#0052cc]">
                                    <option value="AKTIF">Aktif</option>
                                    <option value="NONAKTIF">Nonaktif</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Kategori Bisnis Supplier (Multi-category manual & opsional) -->
                    <div class="p-4 rounded-xl bg-slate-50 border border-gray-200 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <label class="font-bold text-gray-800">Kategori Bisnis Supplier (Opsional, Multi-Kategori)</label>
                            <span class="text-[10.5px] text-gray-500">Hanya informasi umum, bukan pembatas produk</span>
                        </div>

                        <!-- Selected Tags -->
                        <div class="flex flex-wrap gap-1.5" x-show="form.business_categories.length > 0">
                            <template x-for="(cat, idx) in form.business_categories" :key="cat">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-blue-50 text-[#0052cc] border border-blue-200 font-bold text-[11px]">
                                    <span x-text="cat"></span>
                                    <button type="button" @click="removeCategoryTag(idx)" class="hover:text-rose-600 cursor-pointer flex items-center">
                                        <span class="material-symbols-rounded text-[14px]">close</span>
                                    </button>
                                </span>
                            </template>
                        </div>

                        <!-- Manual Input -->
                        <div class="flex items-center gap-2">
                            <input type="text"
                                   x-model="categoryInput"
                                   @keydown.enter.prevent="addCategoryTag(categoryInput)"
                                   placeholder="Ketik kategori bisnis lalu tekan Enter atau Tambah..."
                                   class="flex-1 px-3 py-2 rounded-xl border border-gray-300 bg-white focus:outline-none focus:border-[#0052cc]">
                            <button type="button"
                                    @click="addCategoryTag(categoryInput)"
                                    class="px-3.5 py-2 rounded-xl bg-white border border-gray-300 hover:bg-gray-100 text-gray-700 font-bold cursor-pointer">
                                + Tambah
                            </button>
                        </div>

                        <!-- Preset Chips -->
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            <template x-for="preset in suggestedCategories" :key="preset">
                                <button type="button"
                                        @click="toggleSuggestedCategory(preset)"
                                        :class="hasCategory(preset) ? 'bg-[#0052cc] text-white border-[#0052cc]' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-100'"
                                        class="px-2.5 py-1 rounded-lg border text-[11px] font-semibold cursor-pointer transition-colors"
                                        x-text="preset"></button>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 mb-1.5">Catatan</label>
                        <textarea x-model="form.notes"
                                  rows="2"
                                  placeholder="Termin pembayaran, jadwal pengiriman, atau catatan tambahan..."
                                  class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20"></textarea>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 bg-slate-50 flex items-center justify-end gap-2.5 shrink-0">
                    <button type="button"
                            :disabled="isSaving"
                            @click="showModal = false"
                            class="px-4 py-2.5 rounded-xl border border-gray-300 text-gray-700 font-bold text-xs hover:bg-gray-100 cursor-pointer disabled:opacity-50">
                        Batal
                    </button>
                    <button type="submit"
                            :disabled="isSaving"
                            class="px-5 py-2.5 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs cursor-pointer flex items-center gap-1.5 disabled:opacity-50">
                        <span x-show="isSaving" class="material-symbols-rounded text-[16px] animate-spin">progress_activity</span>
                        <span x-text="isSaving ? 'Menyimpan...' : (modalMode === 'create' ? 'Simpan Supplier' : 'Simpan Perubahan')"></span>
                    </button>
                </div>
            </form>
        </div>
    </template>
    @endif
</div>

@once
<script>
function supplierListApp(config) {
    return {
        canManage: Boolean(config.canManage),
        detailBaseUrl: config.detailBaseUrl,
        csrfToken: config.csrfToken,

        suppliers: [],
        availableCategories: [],
        suggestedCategories: [
            'Distributor Makanan',
            'Distributor Minuman',
            'Distributor Sembako',
            'Distributor Perlengkapan Kebersihan',
            'Distributor Peralatan',
            'Supplier Umum'
        ],
        nextCode: 'SUP-001',
        summary: {
            total_suppliers: 0,
            active_suppliers: 0,
            inactive_suppliers: 0,
            total_supplied_products: 0
        },
        meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 },
        filters: {
            search: '',
            status: 'all',
            category: 'all',
            sort: 'name_asc',
            page: 1
        },
        searchTimer: null,
        isLoading: false,

        // Form Modal
        showModal: false,
        modalMode: 'create',
        isSaving: false,
        submitKey: '',
        categoryInput: '',
        form: {
            id: null,
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

        async initList() {
            await Promise.all([this.loadMeta(), this.loadSuppliers()]);
        },

        async refreshAll() {
            await Promise.all([this.loadMeta(), this.loadSuppliers()]);
            this.notify('Data Master Supplier berhasil disinkronkan.', 'info');
        },

        async loadMeta() {
            try {
                const res = await fetch('/api/suppliers/meta', { headers: { 'Accept': 'application/json' } });
                if (res.ok) {
                    const json = await res.json();
                    if (json.success && json.data) {
                        this.nextCode = json.data.next_code || 'SUP-001';
                        if (Array.isArray(json.data.suggested_categories) && json.data.suggested_categories.length > 0) {
                            this.suggestedCategories = json.data.suggested_categories;
                        }
                    }
                }
            } catch (e) {}
        },

        async loadSuppliers() {
            this.isLoading = true;
            try {
                const params = new URLSearchParams({
                    page: this.filters.page,
                    per_page: 10,
                    sort: this.filters.sort
                });
                if (this.filters.search.trim()) params.set('search', this.filters.search.trim());
                if (this.filters.status !== 'all') params.set('status', this.filters.status);
                if (this.filters.category !== 'all') params.set('category', this.filters.category);

                const res = await fetch('/api/suppliers?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                if (res.ok) {
                    const json = await res.json();
                    if (json.success) {
                        this.suppliers = json.data || [];
                        this.summary = json.summary || this.summary;
                        this.meta = json.meta || this.meta;
                        this.availableCategories = json.available_categories || [];
                    }
                }
            } catch (e) {
                this.notify('Gagal memuat daftar supplier.', 'error');
            } finally {
                this.isLoading = false;
            }
        },

        onSearchInput() {
            this.filters.page = 1;
            if (this.searchTimer) clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.loadSuppliers(), 300);
        },

        goToPage(page) {
            if (page < 1 || page > this.meta.last_page) return;
            this.filters.page = page;
            this.loadSuppliers();
        },

        resetFilters() {
            this.filters = { search: '', status: 'all', category: 'all', sort: 'name_asc', page: 1 };
            this.loadSuppliers();
        },

        openCreateModal() {
            if (!this.canManage) return;
            this.modalMode = 'create';
            this.submitKey = window.createIdempotencyKey ? window.createIdempotencyKey() : ('sup-' + Date.now());
            this.categoryInput = '';
            this.form = {
                id: null,
                code: this.nextCode,
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
            };
            this.showModal = true;
            this.loadMeta().then(() => {
                if (this.modalMode === 'create' && !this.form.id) {
                    this.form.code = this.nextCode;
                }
            });
        },

        openEditModal(sup) {
            if (!this.canManage) return;
            this.modalMode = 'edit';
            this.categoryInput = '';
            this.form = {
                id: sup.id,
                code: sup.code,
                name: sup.name || '',
                company_name: sup.company_name || '',
                pic_name: sup.pic_name || '',
                phone: sup.phone || '',
                email: sup.email || '',
                address: sup.address || '',
                city: sup.city || '',
                business_categories: Array.isArray(sup.business_categories) ? [...sup.business_categories] : [],
                status: sup.status || 'AKTIF',
                notes: sup.notes || ''
            };
            this.showModal = true;
        },

        hasCategory(cat) {
            const lower = String(cat || '').trim().toLowerCase();
            return this.form.business_categories.some(c => c.toLowerCase() === lower);
        },

        addCategoryTag(raw) {
            const val = String(raw || '').trim();
            if (!val) return;
            if (!this.hasCategory(val)) {
                this.form.business_categories.push(val);
            }
            this.categoryInput = '';
        },

        toggleSuggestedCategory(cat) {
            const lower = String(cat || '').trim().toLowerCase();
            const idx = this.form.business_categories.findIndex(c => c.toLowerCase() === lower);
            if (idx >= 0) {
                this.form.business_categories.splice(idx, 1);
            } else {
                this.form.business_categories.push(cat);
            }
        },

        removeCategoryTag(idx) {
            this.form.business_categories.splice(idx, 1);
        },

        async saveSupplier() {
            if (this.isSaving || !this.canManage) return;
            const cleanName = (this.form.name || '').trim();
            if (!cleanName) {
                this.notify('Nama supplier wajib diisi.', 'error');
                return;
            }
            if (this.categoryInput.trim()) {
                this.addCategoryTag(this.categoryInput);
            }

            this.isSaving = true;
            try {
                const isCreate = this.modalMode === 'create';
                const url = isCreate ? '/api/suppliers' : `/api/suppliers/${this.form.id}`;
                const method = isCreate ? 'POST' : 'PUT';
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                };
                if (isCreate && this.submitKey) {
                    headers['X-Idempotency-Key'] = this.submitKey;
                }

                const res = await fetch(url, {
                    method,
                    headers,
                    body: JSON.stringify({
                        name: cleanName,
                        company_name: this.form.company_name || null,
                        pic_name: this.form.pic_name || null,
                        phone: this.form.phone || null,
                        email: this.form.email || null,
                        address: this.form.address || null,
                        city: this.form.city || null,
                        business_categories: this.form.business_categories,
                        status: this.form.status || 'AKTIF',
                        notes: this.form.notes || null
                    })
                });

                const json = await res.json();
                if (!res.ok || !json.success) {
                    this.notify(json.message || 'Gagal menyimpan data supplier.', 'error');
                    return;
                }

                this.showModal = false;
                this.submitKey = '';
                this.notify(json.message, 'success');
                await Promise.all([this.loadMeta(), this.loadSuppliers()]);
            } catch (e) {
                this.notify('Terjadi kesalahan koneksi saat menyimpan supplier.', 'error');
            } finally {
                this.isSaving = false;
            }
        }
    };
}
</script>
@endonce
