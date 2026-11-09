@extends('layouts.owner-admin', ['active' => 'audit-log'])

@section('title', 'Audit Log & Riwayat Aktivitas - ILP POS Owner Console')

@section('header')
<x-admin.header title="Koperasi Owner Portal" :showSearch="false">
    <x-slot:leftSlot>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ url('/owner') }}" class="text-xs font-black uppercase text-slate-800 tracking-wider hover:text-[#0052cc] transition-colors">Owner Console</a>
            <span class="text-slate-300">&gt;</span>
            <span class="text-xs font-bold text-slate-500">Audit &amp; Kepatuhan</span>
            <span class="text-slate-300">&gt;</span>
            <span class="text-xs font-bold text-[#0052cc]">Riwayat Aktivitas</span>
            
            <span class="ml-2 inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10.5px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200">
                <span class="material-symbols-rounded text-[13px]">history_edu</span>
                <span>Audit Trail Console</span>
            </span>
        </div>
    </x-slot:leftSlot>

    <!-- Top Action Buttons -->
    <div class="flex items-center gap-2">
        <button type="button" 
                @click="exportCsv()"
                class="px-3.5 py-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-2xs inline-flex items-center gap-1.5 transition-colors cursor-pointer">
            <span class="material-symbols-rounded text-[16px]">download</span>
            <span class="hidden sm:inline">Export CSV Log</span>
        </button>

        <button type="button" 
                @click="initLogs(); if (typeof triggerToast === 'function') triggerToast('Data audit log dimuat ulang!', 'info');"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-all cursor-pointer">
            <span class="material-symbols-rounded text-[16px]">refresh</span>
            <span class="hidden sm:inline">Muat Ulang</span>
        </button>
    </div>
</x-admin.header>
@endsection

@section('admin_content')
<div x-data="auditLogApp()" 
     x-init="initLogs()"
     class="space-y-6">

    <!-- Page Title -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-xl md:text-2xl font-black text-slate-900 tracking-tight">Audit Log &amp; Riwayat Aktivitas</h1>
        </div>
    </div>

    <!-- 4 Dynamic Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <!-- 1. Total Aktivitas Hari Ini -->
        <div class="bg-white rounded-2xl p-4.5 border border-slate-200/80 shadow-2xs flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <span class="text-[10.5px] font-extrabold uppercase tracking-wider text-slate-500">AKTIVITAS HARI INI</span>
                <span class="material-symbols-rounded text-[20px] text-[#0052cc]">history</span>
            </div>
            <div class="mt-2 text-2xl font-black text-slate-900 tracking-tight leading-none" x-text="todayActivitiesCount">0</div>
            <div class="mt-2.5 text-[11px] font-medium text-slate-500">
                <span class="font-bold text-[#0052cc]" x-text="todayActivitiesCount + ' aktivitas'"></span> tercatat per tanggal hari ini
            </div>
        </div>

        <!-- 2. Aktivitas Perubahan Data -->
        <div class="bg-white rounded-2xl p-4.5 border border-slate-200/80 shadow-2xs flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <span class="text-[10.5px] font-extrabold uppercase tracking-wider text-slate-500">PERUBAHAN DATA</span>
                <span class="material-symbols-rounded text-[20px] text-amber-600">edit_note</span>
            </div>
            <div class="mt-2 text-2xl font-black text-amber-600 tracking-tight leading-none" x-text="dataChangesCount">0</div>
            <div class="mt-2.5 text-[11px] font-medium text-slate-500">
                Mutasi stok, limit anggota, &amp; hak akses
            </div>
        </div>

        <!-- 3. Aktivitas Transaksi -->
        <div class="bg-white rounded-2xl p-4.5 border border-slate-200/80 shadow-2xs flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <span class="text-[10.5px] font-extrabold uppercase tracking-wider text-slate-500">AKTIVITAS TRANSAKSI</span>
                <span class="material-symbols-rounded text-[20px] text-emerald-600">receipt_long</span>
            </div>
            <div class="mt-2 text-2xl font-black text-emerald-600 tracking-tight leading-none" x-text="transactionActivitiesCount">0</div>
            <div class="mt-2.5 text-[11px] font-medium text-slate-500">
                Penjualan kasir, retur, &amp; void transaksi
            </div>
        </div>

        <!-- 4. Aktivitas Akses & Keamanan -->
        <div class="bg-white rounded-2xl p-4.5 border border-slate-200/80 shadow-2xs flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <span class="text-[10.5px] font-extrabold uppercase tracking-wider text-slate-500">AKSES &amp; KEAMANAN</span>
                <span class="material-symbols-rounded text-[20px] text-indigo-600">security</span>
            </div>
            <div class="mt-2 text-2xl font-black text-indigo-600 tracking-tight leading-none" x-text="securityActivitiesCount">0</div>
            <div class="mt-2.5 text-[11px] font-medium text-slate-500">
                Sesi login, logout, &amp; rotasi operator
            </div>
        </div>
    </div>

    <!-- Filter & Search Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-4.5 space-y-3.5">
        <!-- Top Filter Row: Search & Date Range -->
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
            <!-- Search Text -->
            <div class="relative flex-1">
                <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                <input type="text" 
                       x-model="searchQuery" 
                       placeholder="Cari nama pengguna, ID log, deskripsi, atau no. referensi (TRX, SKU, NIK)..." 
                       class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-[13px] text-slate-800 focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none transition-all shadow-2xs">
            </div>

            <!-- Date Range Selector -->
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs font-semibold text-slate-500">Periode:</span>
                <select x-model="dateFilter" class="px-3 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 outline-none shadow-2xs cursor-pointer">
                    <option value="all">Semua Waktu</option>
                    <option value="today">Hari Ini</option>
                    <option value="7d">7 Hari Terakhir</option>
                    <option value="30d">30 Hari Terakhir</option>
                    <option value="custom">Rentang Kustom...</option>
                </select>

                <!-- Custom Date Inputs (shown only if dateFilter === 'custom') -->
                <template x-if="dateFilter === 'custom'">
                    <div class="flex items-center gap-1.5 text-xs">
                        <input type="date" x-model="startDate" class="px-2.5 py-1.5 rounded-xl border border-slate-200 bg-white text-xs font-mono">
                        <span class="text-slate-400">s/d</span>
                        <input type="date" x-model="endDate" class="px-2.5 py-1.5 rounded-xl border border-slate-200 bg-white text-xs font-mono">
                    </div>
                </template>

                <!-- Reset Filter Button -->
                <button type="button" 
                        @click="resetFilters()"
                        class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold inline-flex items-center gap-1.5 transition-colors cursor-pointer"
                        title="Reset Semua Filter">
                    <span class="material-symbols-rounded text-[16px]">restart_alt</span>
                    <span>Reset</span>
                </button>
            </div>
        </div>

        <!-- Bottom Filter Row: 4 Dropdowns (Role, Location, Category, Status) -->
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2.5 pt-2 border-t border-slate-100 text-xs">
            <!-- Filter Role -->
            <div>
                <label class="block text-[10.5px] font-bold text-slate-500 mb-1">PERAN (ROLE)</label>
                <select x-model="roleFilter" class="w-full px-2.5 py-1.5 rounded-xl bg-white border border-slate-200 font-bold text-slate-700 outline-none shadow-2xs cursor-pointer">
                    <option value="all">Semua Role</option>
                    <option value="Owner">Owner</option>
                    <option value="Manager">Manager</option>
                    <option value="Kasir">Kasir</option>
                </select>
            </div>

            <!-- Filter Lokasi -->
            <div>
                <label class="block text-[10.5px] font-bold text-slate-500 mb-1">LOKASI / CABANG</label>
                <select x-model="locationFilter" class="w-full px-2.5 py-1.5 rounded-xl bg-white border border-slate-200 font-bold text-slate-700 outline-none shadow-2xs cursor-pointer">
                    <option value="all">Semua Lokasi</option>
                    <option value="Gudang Pusat">Gudang Pusat</option>
                    <option value="Pusat">Pusat</option>
                    <option value="Cabang A">Cabang A</option>
                    <option value="Cabang B">Cabang B</option>
                </select>
            </div>

            <!-- Filter Kategori -->
            <div class="col-span-2 sm:col-span-2">
                <label class="block text-[10.5px] font-bold text-slate-500 mb-1">KATEGORI TINDAKAN</label>
                <select x-model="categoryFilter" class="w-full px-2.5 py-1.5 rounded-xl bg-white border border-slate-200 font-bold text-slate-700 outline-none shadow-2xs cursor-pointer">
                    <option value="all">Semua Kategori Aktivitas</option>
                    <option value="Login &amp; Logout">Login &amp; Logout</option>
                    <option value="Transaksi Penjualan">Transaksi Penjualan</option>
                    <option value="Void / Pembatalan">Void / Pembatalan</option>
                    <option value="Refund / Retur">Refund / Retur</option>
                    <option value="Stok &amp; Persediaan">Stok &amp; Persediaan</option>
                    <option value="Barang Masuk / Distribusi / Mutasi">Barang Masuk / Distribusi / Mutasi</option>
                    <option value="Anggota &amp; Plafon">Anggota &amp; Plafon</option>
                    <option value="Supplier &amp; Mitra UMKM">Supplier &amp; Mitra UMKM</option>
                    <option value="User &amp; Hak Akses">User &amp; Hak Akses</option>
                    <option value="Pengaturan Sistem">Pengaturan Sistem</option>
                    <option value="Sesi Kasir">Sesi Kasir</option>
                </select>
            </div>

            <!-- Filter Status -->
            <div>
                <label class="block text-[10.5px] font-bold text-slate-500 mb-1">STATUS AUDIT</label>
                <select x-model="statusFilter" class="w-full px-2.5 py-1.5 rounded-xl bg-white border border-slate-200 font-bold text-slate-700 outline-none shadow-2xs cursor-pointer">
                    <option value="all">Semua Status</option>
                    <option value="BERHASIL">BERHASIL</option>
                    <option value="PERINGATAN">PERINGATAN</option>
                    <option value="PERLU_INVESTIGASI">PERLU INVESTIGASI</option>
                    <option value="GAGAL">GAGAL</option>
                </select>
            </div>

            <!-- Sort & Page Size -->
            <div>
                <label class="block text-[10.5px] font-bold text-slate-500 mb-1">URUTAN WAKTU</label>
                <select x-model="sortOrder" class="w-full px-2.5 py-1.5 rounded-xl bg-white border border-slate-200 font-bold text-slate-700 outline-none shadow-2xs cursor-pointer">
                    <option value="desc">Terkini (Terbaru)</option>
                    <option value="asc">Terlama</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Audit Activity Table Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden">
        <!-- Table Top Subheader -->
        <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="material-symbols-rounded text-[20px] text-[#0052cc]">receipt_long</span>
                <h3 class="text-base font-bold text-slate-900 tracking-tight">Catatan Kronologis Aktivitas Sistem</h3>
                <span class="text-xs text-slate-500 font-medium">
                    (Menampilkan <strong class="text-slate-800" x-text="sortedFilteredLogs.length"></strong> dari <span x-text="logs.length"></span> entri log)
                </span>
            </div>

            <div class="flex items-center gap-3 text-xs">
                <span class="text-slate-500 font-semibold">Tampilkan:</span>
                <select x-model.number="pageSize" @change="currentPage = 1" class="px-2 py-1 rounded-lg border border-slate-200 font-bold text-slate-700 bg-slate-50 outline-none shadow-2xs cursor-pointer">
                    <option :value="10">10 baris</option>
                    <option :value="25">25 baris</option>
                    <option :value="50">50 baris</option>
                </select>
            </div>
        </div>

        <!-- Table Container -->
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse" style="min-width: 1040px;">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/70 text-[11px] font-extrabold uppercase text-slate-500 tracking-wider">
                        <th class="py-3.5 px-5 text-left w-[170px]">WAKTU (WIB)</th>
                        <th class="py-3.5 px-4 text-left w-[180px]">PENGGUNA</th>
                        <th class="py-3.5 px-3 text-center w-[90px]">ROLE</th>
                        <th class="py-3.5 px-4 text-left w-[130px]">LOKASI</th>
                        <th class="py-3.5 px-4 text-left w-[160px]">KATEGORI</th>
                        <th class="py-3.5 px-5 text-left">AKTIVITAS &amp; DESKRIPSI</th>
                        <th class="py-3.5 px-4 text-left w-[140px]">REFERENSI</th>
                        <th class="py-3.5 px-3 text-center w-[120px]">STATUS</th>
                        <th class="py-3.5 px-4 text-right w-[90px]">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-[13px]">
                    <template x-for="item in paginatedLogs" :key="item.id">
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <!-- 1. Waktu -->
                            <td class="py-3.5 px-5 font-mono text-xs">
                                <span class="font-bold text-slate-800 block" x-text="formatDate(item.timestamp)"></span>
                                <span class="text-[11px] text-slate-400 block mt-0.5" x-text="formatTime(item.timestamp)"></span>
                            </td>

                            <!-- 2. Pengguna -->
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl flex items-center justify-center font-bold text-xs shrink-0 select-none shadow-2xs"
                                         :class="{
                                             'bg-blue-100 text-[#0052cc]': item.userRole === 'Owner',
                                             'bg-emerald-100 text-emerald-800': item.userRole === 'Manager',
                                             'bg-amber-100 text-amber-800': item.userRole === 'Kasir'
                                         }"
                                         x-text="item.userName.substring(0, 2).toUpperCase()">
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-bold text-slate-900 leading-tight truncate" x-text="item.userName"></div>
                                        <span class="text-[10px] text-slate-400 font-mono block mt-0.5" x-text="item.userId"></span>
                                    </div>
                                </div>
                            </td>

                            <!-- 3. Role -->
                            <td class="py-3.5 px-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase border inline-block"
                                      :class="{
                                          'bg-blue-50 text-[#0052cc] border-blue-200': item.userRole === 'Owner',
                                          'bg-emerald-50 text-emerald-700 border-emerald-200': item.userRole === 'Manager',
                                          'bg-amber-50 text-amber-700 border-amber-200': item.userRole === 'Kasir'
                                      }"
                                      x-text="item.userRole">
                                </span>
                            </td>

                            <!-- 4. Lokasi -->
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                                    <span class="material-symbols-rounded text-[15px] text-slate-400">
                                        <template x-if="item.location === 'Gudang Pusat'">warehouse</template>
                                        <template x-if="item.location !== 'Gudang Pusat'">storefront</template>
                                    </span>
                                    <span x-text="item.location"></span>
                                </div>
                            </td>

                            <!-- 5. Kategori -->
                            <td class="py-3.5 px-4">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                    <span class="material-symbols-rounded text-[13px] text-slate-500" x-text="getCategoryIcon(item.category)"></span>
                                    <span class="truncate max-w-[120px]" x-text="item.category" :title="item.category"></span>
                                </span>
                            </td>

                            <!-- 6. Aktivitas / Deskripsi -->
                            <td class="py-3.5 px-5">
                                <div class="font-bold text-slate-900 leading-snug" x-text="item.actionName"></div>
                                <p class="text-xs text-slate-500 mt-0.5 line-clamp-1" x-text="item.description" :title="item.description"></p>
                            </td>

                            <!-- 7. Referensi -->
                            <td class="py-3.5 px-4 font-mono text-xs">
                                <template x-if="item.reference">
                                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-800 font-bold border border-slate-200 text-[11px]" x-text="item.reference"></span>
                                </template>
                                <template x-if="!item.reference">
                                    <span class="text-slate-400 italic text-[11px]">-</span>
                                </template>
                            </td>

                            <!-- 8. Status -->
                            <td class="py-3.5 px-3 text-center">
                                <span class="px-2.5 py-0.5 rounded-full text-[9.5px] font-extrabold uppercase border inline-flex items-center gap-1"
                                      :class="{
                                          'bg-emerald-50 text-emerald-700 border-emerald-200': item.status === 'BERHASIL',
                                          'bg-amber-50 text-amber-700 border-amber-200': item.status === 'PERINGATAN',
                                          'bg-rose-50 text-rose-700 border-rose-200': item.status === 'PERLU_INVESTIGASI',
                                          'bg-red-50 text-red-700 border-red-200': item.status === 'GAGAL'
                                      }">
                                    <span class="w-1.5 h-1.5 rounded-full"
                                          :class="{
                                              'bg-emerald-500': item.status === 'BERHASIL',
                                              'bg-amber-500': item.status === 'PERINGATAN',
                                              'bg-rose-500': item.status === 'PERLU_INVESTIGASI',
                                              'bg-red-500': item.status === 'GAGAL'
                                          }"></span>
                                    <span x-text="item.status === 'PERLU_INVESTIGASI' ? 'INVESTIGASI' : item.status"></span>
                                </span>
                            </td>

                            <!-- 9. Aksi -->
                            <td class="py-3.5 px-4 text-right">
                                <button type="button" 
                                        @click="openDetail(item)"
                                        class="px-2.5 py-1 rounded-lg text-xs font-bold text-[#0052cc] hover:bg-blue-50 transition-colors cursor-pointer inline-flex items-center gap-1">
                                    <span>Detail</span>
                                    <span class="material-symbols-rounded text-[15px]">arrow_forward</span>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Empty State -->
        <template x-if="logs.length === 0">
            <div class="py-16 text-center text-slate-400 space-y-2">
                <span class="material-symbols-rounded text-5xl text-slate-300 block">history</span>
                <h4 class="font-bold text-slate-800 text-sm">Belum Ada Riwayat Aktivitas</h4>
                <p class="text-xs text-slate-400 max-w-sm mx-auto">Aktivitas sistem, transaksi, dan perubahan data akan dicatat secara otomatis di sini.</p>
            </div>
        </template>
        <template x-if="logs.length > 0 && sortedFilteredLogs.length === 0">
            <div class="py-16 text-center text-slate-400 space-y-2">
                <span class="material-symbols-rounded text-5xl text-slate-300 block">search_off</span>
                <h4 class="font-bold text-slate-800 text-sm">Tidak Ada Catatan Log Aktivitas</h4>
                <p class="text-xs text-slate-400 max-w-sm mx-auto">Tidak ada riwayat tindakan yang sesuai dengan kriteria pencarian dan kombinasi filter Anda saat ini.</p>
                <button type="button" 
                        @click="resetFilters()"
                        class="mt-3 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors cursor-pointer inline-flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[16px]">restart_alt</span>
                    <span>Reset Semua Filter</span>
                </button>
            </div>
        </template>

        <!-- Footer Pagination -->
        <div class="px-6 py-3.5 border-t border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-[12.5px] text-slate-500">
            <div>
                Menampilkan <span class="font-bold text-slate-800" x-text="sortedFilteredLogs.length ? ((currentPage - 1) * pageSize + 1) + ' - ' + Math.min(currentPage * pageSize, sortedFilteredLogs.length) : '0'"></span> dari <span class="font-bold text-slate-800" x-text="sortedFilteredLogs.length"></span> catatan aktivitas
            </div>
            <div class="flex items-center gap-1">
                <button type="button" 
                        @click="if (currentPage > 1) currentPage--" 
                        :disabled="currentPage <= 1"
                        :class="currentPage <= 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-100 text-slate-700'"
                        class="px-2.5 py-1 rounded-lg border border-slate-200 text-xs font-medium transition-colors cursor-pointer">
                    Sebelumnya
                </button>
                <template x-for="p in totalPages" :key="p">
                    <button type="button" 
                            @click="currentPage = p"
                            :class="currentPage === p ? 'bg-[#0052cc] text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-100'"
                            class="w-7 h-7 rounded-lg font-bold text-xs flex items-center justify-center transition-colors cursor-pointer"
                            x-text="p">
                    </button>
                </template>
                <button type="button" 
                        @click="if (currentPage < totalPages) currentPage++" 
                        :disabled="currentPage >= totalPages"
                        :class="currentPage >= totalPages ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-100 text-slate-700'"
                        class="px-2.5 py-1 rounded-lg border border-slate-200 text-xs font-medium transition-colors cursor-pointer">
                    Selanjutnya
                </button>
            </div>
        </div>
    </div>

    <!-- Detail Activity Modal -->
    <x-admin.modal show="showDetailModal" title="Detail Catatan Audit Log" maxWidth="max-w-2xl">
        <template x-if="selectedLog">
            <div class="space-y-4 text-[13px]">
                <!-- Header Badge Banner -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-3 bg-slate-50 rounded-xl border border-slate-200">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">ID LOG AKTIVITAS</span>
                        <span class="font-mono font-bold text-sm text-[#0052cc]" x-text="selectedLog.id"></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-full text-[10.5px] font-extrabold uppercase border"
                              :class="{
                                  'bg-emerald-50 text-emerald-700 border-emerald-200': selectedLog.status === 'BERHASIL',
                                  'bg-amber-50 text-amber-700 border-amber-200': selectedLog.status === 'PERINGATAN',
                                  'bg-rose-50 text-rose-700 border-rose-200': selectedLog.status === 'PERLU_INVESTIGASI',
                                  'bg-red-50 text-red-700 border-red-200': selectedLog.status === 'GAGAL'
                              }"
                              x-text="'STATUS: ' + selectedLog.status">
                        </span>
                        <span class="text-xs font-mono text-slate-500" x-text="selectedLog.timestamp"></span>
                    </div>
                </div>

                <!-- Grid Details: Pengguna, Lokasi, Terminal, Kategori -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- User info -->
                    <div class="p-3 rounded-xl border border-slate-200 bg-white space-y-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">IDENTITAS PELAKSANA</span>
                        <div class="font-bold text-slate-900" x-text="selectedLog.userName"></div>
                        <div class="text-xs text-slate-500 font-mono" x-text="'ID: ' + selectedLog.userId + ' (' + selectedLog.userRole + ')'"></div>
                    </div>

                    <!-- Location & Terminal -->
                    <div class="p-3 rounded-xl border border-slate-200 bg-white space-y-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">LOKASI &amp; TERMINAL</span>
                        <div class="font-bold text-slate-900" x-text="selectedLog.location"></div>
                        <div class="text-xs text-slate-500 font-mono" x-text="selectedLog.source || '127.0.0.1 / Web Portal'"></div>
                    </div>

                    <!-- Category -->
                    <div class="p-3 rounded-xl border border-slate-200 bg-white space-y-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">KATEGORI TINDAKAN</span>
                        <div class="font-bold text-slate-800" x-text="selectedLog.category"></div>
                        <div class="text-xs text-slate-500" x-text="selectedLog.actionName"></div>
                    </div>

                    <!-- Reference -->
                    <div class="p-3 rounded-xl border border-slate-200 bg-white space-y-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">NOMOR DOKUMEN / REFERENSI</span>
                        <div class="font-mono font-bold text-slate-900" x-text="selectedLog.reference || 'Tidak Tersedia'"></div>
                        <div class="text-[11px] text-slate-400" x-text="selectedLog.reference ? 'Terkait record operasional' : 'Aktivitas tanpa relasi dokumen'"></div>
                    </div>
                </div>

                <!-- Full Description Box -->
                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-1 text-xs">
                    <span class="font-extrabold uppercase tracking-wider text-slate-500 text-[10.5px] block">DESKRIPSI LENGKAP TINDAKAN</span>
                    <p class="text-slate-800 leading-relaxed font-medium" x-text="selectedLog.description"></p>
                </div>

                <!-- Changes Table: Nilai Sebelum vs Sesudah -->
                <div class="space-y-2">
                    <span class="font-extrabold uppercase tracking-wider text-slate-600 text-[11px] block">RINGKASAN PERUBAHAN DATA (SEBELUM &amp; SESUDAH)</span>
                    
                    <template x-if="selectedLog.changes && selectedLog.changes.length > 0">
                        <div class="border border-slate-200 rounded-xl overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="bg-slate-100/70 border-b border-slate-200 text-slate-600 font-bold">
                                        <th class="py-2.5 px-3 w-[150px]">Parameter / Field</th>
                                        <th class="py-2.5 px-3 text-rose-700 bg-rose-50/50">Nilai Sebelum</th>
                                        <th class="py-2.5 px-3 text-emerald-700 bg-emerald-50/50">Nilai Sesudah</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-mono">
                                    <template x-for="(ch, idx) in selectedLog.changes" :key="idx">
                                        <tr>
                                            <td class="py-2.5 px-3 font-sans font-bold text-slate-800" x-text="ch.field"></td>
                                            <td class="py-2.5 px-3 text-rose-600 bg-rose-50/30" x-text="ch.before"></td>
                                            <td class="py-2.5 px-3 text-emerald-600 font-bold bg-emerald-50/30" x-text="ch.after"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>

                    <template x-if="!selectedLog.changes || selectedLog.changes.length === 0">
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 text-xs text-slate-500 italic">
                            Tidak ada perubahan nilai data (hanya pencatatan event operasional / otorisasi akses).
                        </div>
                    </template>
                </div>

                <!-- Security & Privacy Assurance Callout -->
                <div class="p-3 bg-amber-50/70 rounded-xl border border-amber-200 text-[11.5px] text-amber-900 flex items-start gap-2">
                    <span class="material-symbols-rounded text-amber-700 text-[16px] shrink-0 mt-0.5">lock</span>
                    <p class="leading-relaxed">
                        <strong>Perlindungan Kredensial:</strong> Sistem tidak pernah mencatat atau menyimpan password, PIN kasir, token autentikasi, atau kredensial rahasia apa pun ke dalam catatan audit log.
                    </p>
                </div>

                <!-- Footer Close Button -->
                <div class="flex justify-end pt-2 border-t border-slate-100">
                    <button type="button" 
                            @click="showDetailModal = false" 
                            class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors cursor-pointer">
                        Tutup Detail
                    </button>
                </div>
            </div>
        </template>
    </x-admin.modal>

</div>

<script>
function auditLogApp() {
    return {
        searchQuery: '',
        dateFilter: 'all', // 'all', 'today', '7d', '30d', 'custom'
        startDate: '',
        endDate: '',
        roleFilter: 'all',
        locationFilter: 'all',
        categoryFilter: 'all',
        statusFilter: 'all',
        sortOrder: 'desc', // 'desc' (terkini), 'asc' (terlama)
        currentPage: 1,
        pageSize: 10,
        showDetailModal: false,
        selectedLog: null,

        logs: [],

        defaultLogs: [],

        initLogs() {
            try {
                const raw = localStorage.getItem('pos_ilp_audit_logs_v1');
                if (raw) {
                    const parsed = JSON.parse(raw);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        this.logs = parsed;
                        return;
                    }
                }
            } catch (e) {
                console.error('Failed to parse pos_ilp_audit_logs_v1', e);
            }
            this.logs = [];
        },

        saveLogs() {
            try {
                localStorage.setItem('pos_ilp_audit_logs_v1', JSON.stringify(this.logs));
            } catch (e) {
                console.error('Failed to save pos_ilp_audit_logs_v1', e);
            }
        },

        // Summary Calculations (Dynamic)
        get todayString() {
            const today = new Date();
            const y = today.getFullYear();
            const m = String(today.getMonth() + 1).padStart(2, '0');
            const d = String(today.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        },

        get todayActivitiesCount() {
            const todayPrefix = this.todayString;
            return this.logs.filter(item => item.timestamp && item.timestamp.startsWith(todayPrefix)).length;
        },

        get dataChangesCount() {
            return this.logs.filter(item => 
                ['Stok & Persediaan', 'Barang Masuk / Distribusi / Mutasi', 'Anggota & Plafon', 'Pengaturan Sistem', 'User & Hak Akses'].includes(item.category)
            ).length;
        },

        get transactionActivitiesCount() {
            return this.logs.filter(item => 
                ['Transaksi Penjualan', 'Void / Pembatalan', 'Refund / Retur'].includes(item.category)
            ).length;
        },

        get securityActivitiesCount() {
            return this.logs.filter(item => 
                ['Login & Logout', 'User & Hak Akses', 'Sesi Kasir'].includes(item.category)
            ).length;
        },

        // Filtering & Sorting
        get filteredLogs() {
            return this.logs.filter(item => {
                // Search Query
                if (this.searchQuery) {
                    const q = this.searchQuery.toLowerCase();
                    const matchQ = (item.userName && item.userName.toLowerCase().includes(q)) ||
                                   (item.id && item.id.toLowerCase().includes(q)) ||
                                   (item.description && item.description.toLowerCase().includes(q)) ||
                                   (item.actionName && item.actionName.toLowerCase().includes(q)) ||
                                   (item.reference && item.reference.toLowerCase().includes(q)) ||
                                   (item.userId && item.userId.toLowerCase().includes(q));
                    if (!matchQ) return false;
                }

                // Date Filter
                if (this.dateFilter === 'today') {
                    if (!item.timestamp.startsWith(this.todayString)) return false;
                } else if (this.dateFilter === '7d') {
                    const diffDays = (new Date() - new Date(item.timestamp.replace(' ', 'T'))) / (1000 * 3600 * 24);
                    if (diffDays > 7) return false;
                } else if (this.dateFilter === '30d') {
                    const diffDays = (new Date() - new Date(item.timestamp.replace(' ', 'T'))) / (1000 * 3600 * 24);
                    if (diffDays > 30) return false;
                } else if (this.dateFilter === 'custom') {
                    const itemDate = item.timestamp.slice(0, 10);
                    if (this.startDate && itemDate < this.startDate) return false;
                    if (this.endDate && itemDate > this.endDate) return false;
                }

                // Role Filter
                if (this.roleFilter !== 'all' && item.userRole !== this.roleFilter) return false;

                // Location Filter
                if (this.locationFilter !== 'all' && item.location !== this.locationFilter) return false;

                // Category Filter
                if (this.categoryFilter !== 'all' && item.category !== this.categoryFilter) return false;

                // Status Filter
                if (this.statusFilter !== 'all' && item.status !== this.statusFilter) return false;

                return true;
            });
        },

        get sortedFilteredLogs() {
            const list = [...this.filteredLogs];
            list.sort((a, b) => {
                const timeA = new Date(a.timestamp.replace(' ', 'T')).getTime() || 0;
                const timeB = new Date(b.timestamp.replace(' ', 'T')).getTime() || 0;
                return this.sortOrder === 'asc' ? timeA - timeB : timeB - timeA;
            });
            return list;
        },

        get totalPages() {
            return Math.max(1, Math.ceil(this.sortedFilteredLogs.length / this.pageSize));
        },

        get paginatedLogs() {
            const start = (this.currentPage - 1) * this.pageSize;
            return this.sortedFilteredLogs.slice(start, start + this.pageSize);
        },

        resetFilters() {
            this.searchQuery = '';
            this.dateFilter = 'all';
            this.startDate = '';
            this.endDate = '';
            this.roleFilter = 'all';
            this.locationFilter = 'all';
            this.categoryFilter = 'all';
            this.statusFilter = 'all';
            this.sortOrder = 'desc';
            this.currentPage = 1;
            if (typeof triggerToast === 'function') triggerToast('Filter audit log di-reset ke default.', 'info');
        },

        openDetail(log) {
            this.selectedLog = log;
            this.showDetailModal = true;
        },

        formatDate(ts) {
            if (!ts) return '-';
            try {
                const parts = ts.split(' ');
                const dateParts = parts[0].split('-');
                const d = dateParts[2];
                const mIdx = parseInt(dateParts[1], 10) - 1;
                const y = dateParts[0];
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                return `${d} ${months[mIdx] || ''} ${y}`;
            } catch(e) {
                return ts;
            }
        },

        formatTime(ts) {
            if (!ts) return '';
            try {
                const parts = ts.split(' ');
                return (parts[1] || '') + ' WIB';
            } catch(e) {
                return '';
            }
        },

        getCategoryIcon(cat) {
            const map = {
                'Login & Logout': 'lock_person',
                'Transaksi Penjualan': 'shopping_cart',
                'Void / Pembatalan': 'remove_shopping_cart',
                'Refund / Retur': 'assignment_return',
                'Stok & Persediaan': 'inventory',
                'Barang Masuk / Distribusi / Mutasi': 'local_shipping',
                'Anggota & Plafon': 'group',
                'Supplier & Mitra UMKM': 'handshake',
                'User & Hak Akses': 'admin_panel_settings',
                'Pengaturan Sistem': 'tune',
                'Sesi Kasir': 'schedule'
            };
            return map[cat] || 'info';
        },

        exportCsv() {
            try {
                const list = this.sortedFilteredLogs;
                if (list.length === 0) {
                    if (typeof triggerToast === 'function') triggerToast('Tidak ada data audit yang sesuai dengan filter untuk diekspor.', 'error');
                    return;
                }

                const sanitizeCell = (text) => {
                    if (text === null || text === undefined) return '""';
                    let str = String(text).replace(/"/g, '""');
                    // Prevent CSV injection: if starts with =, +, -, @, prepend single quote
                    if (/^[=+\-@]/.test(str)) {
                        str = "'" + str;
                    }
                    return `"${str}"`;
                };

                const headers = [
                    'ID Log',
                    'Waktu (WIB)',
                    'User ID',
                    'Nama Pengguna',
                    'Role',
                    'Lokasi',
                    'Kategori',
                    'Nama Tindakan',
                    'Deskripsi',
                    'Referensi Dokumen',
                    'Status',
                    'Sumber Terminal/IP'
                ];

                const rows = list.map(item => [
                    sanitizeCell(item.id),
                    sanitizeCell(item.timestamp),
                    sanitizeCell(item.userId),
                    sanitizeCell(item.userName),
                    sanitizeCell(item.userRole),
                    sanitizeCell(item.location),
                    sanitizeCell(item.category),
                    sanitizeCell(item.actionName),
                    sanitizeCell(item.description),
                    sanitizeCell(item.reference || '-'),
                    sanitizeCell(item.status),
                    sanitizeCell(item.source || '-')
                ]);

                const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + [headers.join(','), ...rows.map(r => r.join(','))].join('\r\n');
                const encodedUri = encodeURI(csvContent);
                const link = document.createElement('a');
                link.setAttribute('href', encodedUri);
                link.setAttribute('download', `audit_log_kopkar_ilp_${this.todayString}.csv`);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                if (typeof triggerToast === 'function') {
                    triggerToast(`Audit log berhasil diekspor (${list.length} entri).`, 'success');
                }
            } catch (e) {
                console.error('Failed to export CSV', e);
                if (typeof triggerToast === 'function') triggerToast('Gagal mengekspor CSV: ' + e.message, 'error');
            }
        }
    };
}
</script>
@endsection
