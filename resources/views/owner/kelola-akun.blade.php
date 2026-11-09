@extends('layouts.owner-admin', ['active' => 'kelola-akun'])

@section('title', 'Manajemen User & Hak Akses - ILP POS Owner Console')

@section('header')
<x-admin.header title="Admin Console" :showSearch="true" searchPlaceholder="Search users or roles..." searchModel="searchQuery">
    <x-slot:leftSlot>
        <div class="flex items-center gap-2.5">
            <span class="text-xs font-black uppercase text-slate-800 tracking-wider">Admin Console</span>
            <span class="text-slate-300">|</span>
            <a href="{{ url('/owner') }}" class="text-xs font-bold text-slate-500 hover:text-[#0052cc] transition-colors">Dashboard</a>
            <a href="{{ url('/owner/reports') }}" class="text-xs font-bold text-slate-500 hover:text-[#0052cc] transition-colors">Reports</a>
        </div>
    </x-slot:leftSlot>
</x-admin.header>
@endsection

@section('admin_content')
<div x-data="ownerKelolaAkunApp()" x-init="init()" @ilp-search.window="searchQuery = $event.detail" class="space-y-6">

    <!-- Subheader: Page Title & Action Button -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-[28px] font-black text-slate-900 tracking-tight">Manajemen User &amp; Akses</h1>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ url('/owner/audit-log') }}" 
               class="px-3.5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs inline-flex items-center gap-1.5 transition-colors"
               title="Buka Audit Log &amp; Riwayat Aktivitas Koperasi">
                <span class="material-symbols-rounded text-[18px] text-slate-600">history_edu</span>
                <span>Audit Log Aktivitas</span>
            </a>

            <button type="button" 
                    @click="showResetHistoryModal = true; loadResetHistory();"
                    class="px-3.5 py-2.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-[#0052cc] border border-blue-200 font-bold text-xs inline-flex items-center gap-1.5 transition-colors cursor-pointer"
                    title="Lihat Rekap Reset Kredensial Seluruh Cabang">
                <span class="material-symbols-rounded text-[18px]">lock_reset</span>
                <span>Riwayat Reset Akun</span>
            </button>

            <button type="button" 
                    @click="openAddStaffModal()"
                    class="px-4 py-2.5 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs inline-flex items-center gap-2 shadow-sm shadow-blue-600/20 transition-all cursor-pointer">
                <span class="material-symbols-rounded text-[18px]">person_add</span>
                <span>Tambah Staf Baru</span>
            </button>
        </div>
    </div>

    <!-- Main Two-Column Layout (Matches Screenshot 3) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- Left Column: Daftar Staf Aktif Table Card (lg:col-span-8) -->
        <div class="lg:col-span-8 bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden flex flex-col justify-between">
            <div>
                <!-- Card Header with Table Filter & Export Icons -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-rounded text-[#0052cc] text-[20px]">badge</span>
                        <h2 class="text-base font-bold text-slate-900 tracking-tight">Daftar Staf Aktif</h2>
                    </div>

                    <div class="flex items-center gap-1.5 text-slate-400">
                        <!-- Filter Role Menu Trigger -->
                        <div class="relative" x-data="{ openRoleFilter: false }">
                            <button type="button" 
                                    @click="openRoleFilter = !openRoleFilter"
                                    class="p-2 rounded-lg hover:bg-slate-100 hover:text-slate-700 transition-colors"
                                    title="Filter Peran / Role">
                                <span class="material-symbols-rounded text-[19px]">filter_list</span>
                            </button>

                            <div x-show="openRoleFilter" 
                                 @click.away="openRoleFilter = false"
                                 x-cloak
                                 class="absolute right-0 mt-1 w-44 rounded-xl bg-white border border-slate-200 shadow-xl py-1.5 z-30 text-xs">
                                <button type="button" @click="roleFilter = 'all'; openRoleFilter = false" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 font-medium" :class="roleFilter === 'all' ? 'text-[#0052cc] font-bold' : 'text-slate-700'">Semua Role</button>
                                <button type="button" @click="roleFilter = 'kasir'; openRoleFilter = false" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 font-medium" :class="roleFilter === 'kasir' ? 'text-[#0052cc] font-bold' : 'text-slate-700'">Kasir</button>
                                <button type="button" @click="roleFilter = 'supervisor'; openRoleFilter = false" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 font-medium" :class="roleFilter === 'supervisor' ? 'text-[#0052cc] font-bold' : 'text-slate-700'">Supervisor</button>
                                <button type="button" @click="roleFilter = 'admin'; openRoleFilter = false" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 font-medium" :class="roleFilter === 'admin' ? 'text-[#0052cc] font-bold' : 'text-slate-700'">Admin / Manager</button>
                            </div>
                        </div>

                        <!-- Export CSV Trigger -->
                        <button type="button" 
                                @click="exportStaffCsv()"
                                class="p-2 rounded-lg hover:bg-slate-100 hover:text-slate-700 transition-colors"
                                title="Unduh CSV Staf">
                            <span class="material-symbols-rounded text-[19px]">download</span>
                        </button>
                    </div>
                </div>

                <!-- Table Content Container -->
                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left border-collapse text-[13px]" style="min-width: 620px;">
                        <thead>
                            <tr class="border-b border-slate-100 text-[10.5px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/50">
                                <th class="py-3.5 px-6">NAMA &amp; ID</th>
                                <th class="py-3.5 px-4">ROLE</th>
                                <th class="py-3.5 px-4">CABANG PENUGASAN</th>
                                <th class="py-3.5 px-4">STATUS</th>
                                <th class="py-3.5 px-6 text-right">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="staf in paginatedStaff" :key="staf.id">
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <!-- 1. NAMA & ID -->
                                    <td class="py-3.5 px-6">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-xs shrink-0 select-none"
                                                 :class="staf.avatarBg || 'bg-blue-100 text-[#0052cc]'"
                                                 x-text="staf.initials">
                                            </div>
                                            <div class="flex flex-col min-w-0">
                                                <span class="font-bold text-slate-900 leading-tight truncate" x-text="staf.name"></span>
                                                <span class="text-[11px] font-mono text-slate-400 mt-0.5" x-text="staf.empId || staf.id"></span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 2. ROLE BADGE -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold border inline-block"
                                              :class="{
                                                  'bg-blue-50 text-blue-700 border-blue-200': staf.role.toLowerCase() === 'kasir',
                                                  'bg-purple-50 text-purple-700 border-purple-200': staf.role.toLowerCase() === 'supervisor',
                                                  'bg-sky-50 text-sky-700 border-sky-200': staf.role.toLowerCase() === 'admin' || staf.role.toLowerCase() === 'manager',
                                                  'bg-amber-50 text-amber-700 border-amber-200': staf.role.toLowerCase() === 'owner'
                                              }"
                                              x-text="staf.role">
                                        </span>
                                    </td>

                                    <!-- 3. CABANG PENUGASAN -->
                                    <td class="py-3.5 px-4 text-slate-600 font-medium">
                                        <span x-text="staf.branch"></span>
                                    </td>

                                    <!-- 4. STATUS (Online / Offline Dot) -->
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1.5 text-xs font-bold"
                                             :class="staf.status === 'Online' || staf.status === 'AKTIF' ? 'text-emerald-600' : (staf.status === 'Nonaktif' ? 'text-rose-600' : 'text-slate-400')">
                                            <span class="w-2 h-2 rounded-full"
                                                  :class="staf.status === 'Online' || staf.status === 'AKTIF' ? 'bg-emerald-500' : (staf.status === 'Nonaktif' ? 'bg-rose-500' : 'bg-slate-300')"></span>
                                            <span x-text="staf.status"></span>
                                        </div>
                                    </td>

                                    <!-- 5. AKSI (3 Dots Dropdown Menu) -->
                                    <td class="py-3.5 px-6 text-right whitespace-nowrap relative">
                                        <div class="relative inline-block text-left" x-data="{ openMenu: false }">
                                            <button type="button" 
                                                    @click="openMenu = !openMenu"
                                                    class="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
                                                    title="Menu Aksi">
                                                <span class="material-symbols-rounded text-[18px]">more_vert</span>
                                            </button>

                                            <div x-show="openMenu" 
                                                 @click.away="openMenu = false"
                                                 x-cloak
                                                 class="absolute right-0 mt-1 w-48 rounded-xl bg-white border border-slate-200 shadow-xl py-1.5 z-30 text-xs text-left">
                                                <button type="button" 
                                                        @click="openEditStaff(staf); openMenu = false"
                                                        class="w-full px-3.5 py-2 hover:bg-slate-50 flex items-center gap-2 text-slate-700 font-medium">
                                                    <span class="material-symbols-rounded text-[16px] text-slate-400">edit</span>
                                                    <span>Ubah Role &amp; Cabang</span>
                                                </button>

                                                <button type="button" 
                                                        @click="resetStaffPin(staf); openMenu = false"
                                                        class="w-full px-3.5 py-2 hover:bg-slate-50 flex items-center gap-2 text-slate-700 font-medium">
                                                    <span class="material-symbols-rounded text-[16px] text-amber-600">pin</span>
                                                    <span>Reset PIN Kasir</span>
                                                </button>

                                                <button type="button" 
                                                        @click="resetStaffPassword(staf); openMenu = false"
                                                        class="w-full px-3.5 py-2 hover:bg-slate-50 flex items-center gap-2 text-slate-700 font-medium">
                                                    <span class="material-symbols-rounded text-[16px] text-[#0052cc]">key</span>
                                                    <span>Reset Password Portal</span>
                                                </button>

                                                <button type="button" 
                                                        @click="toggleStaffStatus(staf); openMenu = false"
                                                        class="w-full px-3.5 py-2 hover:bg-slate-50 flex items-center gap-2 text-slate-700 font-medium">
                                                    <span class="material-symbols-rounded text-[16px] text-slate-400">power_settings_new</span>
                                                    <span x-text="staf.status === 'Online' ? 'Set Offline' : 'Set Online'"></span>
                                                </button>

                                                <div class="my-1 border-t border-slate-100"></div>

                                                <button type="button" 
                                                        @click="deleteStaff(staf); openMenu = false"
                                                        class="w-full px-3.5 py-2 hover:bg-rose-50 flex items-center gap-2 text-rose-600 font-semibold">
                                                    <span class="material-symbols-rounded text-[16px]">delete</span>
                                                    <span>Hapus Staf</span>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <template x-if="staffList.length === 0">
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-slate-400">
                                        <span class="material-symbols-rounded text-3xl mb-1 text-slate-300 block">group_off</span>
                                        <p class="font-bold text-sm text-slate-700">Belum ada data staf/akun pengguna.</p>
                                        <button type="button" @click="openAddStaffModal()" class="mt-3 px-4 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-xs font-bold inline-flex items-center gap-1.5 shadow-sm transition-colors cursor-pointer">
                                            <span class="material-symbols-rounded text-[16px]">person_add</span>
                                            <span>Tambah Staf Baru</span>
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            <template x-if="staffList.length > 0 && paginatedStaff.length === 0">
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-slate-400">
                                        <span class="material-symbols-rounded text-3xl mb-1 text-slate-300 block">search_off</span>
                                        <p class="font-bold text-sm text-slate-700">Tidak ada staf yang cocok dengan filter pencarian.</p>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Table Pagination Footer (Matches Screenshot 3) -->
            <div class="px-6 py-3.5 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-500">
                <div>
                    Menampilkan <span class="font-bold text-slate-800" x-text="paginatedStaff.length"></span> dari <span class="font-bold text-slate-800" x-text="filteredStaff.length"></span> staf terdaftar
                </div>

                <div class="flex items-center gap-1.5">
                    <button type="button" 
                            @click="if (currentPage > 1) currentPage--"
                            :disabled="currentPage <= 1"
                            class="px-2.5 py-1 rounded-lg border border-slate-200 text-xs font-bold transition-colors"
                            :class="currentPage <= 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-50 text-slate-700'">
                        Prev
                    </button>

                    <template x-for="p in totalPages" :key="p">
                        <button type="button" 
                                @click="currentPage = p"
                                class="w-7 h-7 rounded-lg text-xs font-bold flex items-center justify-center transition-colors"
                                :class="currentPage === p ? 'bg-[#0052cc] text-white shadow-2xs' : 'border border-slate-200 text-slate-700 hover:bg-slate-50'"
                                x-text="p">
                        </button>
                    </template>

                    <button type="button" 
                            @click="if (currentPage < totalPages) currentPage++"
                            :disabled="currentPage >= totalPages"
                            class="px-2.5 py-1 rounded-lg border border-slate-200 text-xs font-bold transition-colors"
                            :class="currentPage >= totalPages ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-50 text-slate-700'">
                        Next
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Column: Izin Akses Per Role Card (lg:col-span-4) (Matches Screenshot 3) -->
        <div class="lg:col-span-4 bg-slate-100/70 rounded-2xl border border-slate-200/90 p-5 space-y-4">
            
            <!-- Card Header: Blue Shield Icon + Title -->
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-[#0052cc] text-white flex items-center justify-center shrink-0">
                    <span class="material-symbols-rounded text-[18px]">security</span>
                </div>
                <h3 class="text-sm font-black text-slate-900 tracking-tight">Izin Akses Per Role</h3>
            </div>

            <!-- Role Tabs Selector (KASIR | SPV | OWNER) -->
            <div class="p-1 rounded-xl bg-slate-200/70 flex items-center text-xs font-bold text-slate-600">
                <button type="button" 
                        @click="selectedRoleTab = 'KASIR'"
                        class="flex-1 py-1.5 rounded-lg transition-all text-center"
                        :class="selectedRoleTab === 'KASIR' ? 'bg-white text-[#0052cc] shadow-xs' : 'hover:text-slate-900'">
                    KASIR
                </button>
                <button type="button" 
                        @click="selectedRoleTab = 'SPV'"
                        class="flex-1 py-1.5 rounded-lg transition-all text-center"
                        :class="selectedRoleTab === 'SPV' ? 'bg-white text-[#0052cc] shadow-xs' : 'hover:text-slate-900'">
                    SPV
                </button>
                <button type="button" 
                        @click="selectedRoleTab = 'OWNER'"
                        class="flex-1 py-1.5 rounded-lg transition-all text-center"
                        :class="selectedRoleTab === 'OWNER' ? 'bg-white text-[#0052cc] shadow-xs' : 'hover:text-slate-900'">
                    OWNER
                </button>
            </div>

            <!-- Section 1: TRANSAKSI KASIR -->
            <div class="space-y-3 pt-2">
                <h4 class="text-[10px] font-black uppercase tracking-wider text-slate-500">TRANSAKSI KASIR</h4>

                <div class="space-y-2.5 text-xs text-slate-800 font-medium">
                    <!-- Perm 1: Buka / Tutup Shift -->
                    <div class="flex items-center justify-between py-1">
                        <span>Buka / Tutup Shift</span>
                        <button type="button" 
                                @click="togglePermission('bukaTutupShift')"
                                class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                :class="currentPermissions.bukaTutupShift ? 'bg-[#0052cc]' : 'bg-slate-300'">
                            <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                  :class="currentPermissions.bukaTutupShift ? 'translate-x-4' : 'translate-x-0'"></span>
                        </button>
                    </div>

                    <!-- Perm 2: Melakukan Void (Pembatalan) -->
                    <div class="flex items-center justify-between py-1">
                        <span>Melakukan Void (Pembatalan)</span>
                        <button type="button" 
                                @click="togglePermission('melakukanVoid')"
                                class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                :class="currentPermissions.melakukanVoid ? 'bg-[#0052cc]' : 'bg-slate-300'">
                            <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                  :class="currentPermissions.melakukanVoid ? 'translate-x-4' : 'translate-x-0'"></span>
                        </button>
                    </div>

                    <!-- Perm 3: Edit Harga Satuan Manual -->
                    <div class="flex items-center justify-between py-1">
                        <span>Edit Harga Satuan Manual</span>
                        <button type="button" 
                                @click="togglePermission('editHargaManual')"
                                class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                :class="currentPermissions.editHargaManual ? 'bg-[#0052cc]' : 'bg-slate-300'">
                            <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                  :class="currentPermissions.editHargaManual ? 'translate-x-4' : 'translate-x-0'"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Section 2: LAPORAN & DATA -->
            <div class="space-y-3 pt-3 border-t border-slate-200/80">
                <h4 class="text-[10px] font-black uppercase tracking-wider text-slate-500">LAPORAN &amp; DATA</h4>

                <div class="space-y-2.5 text-xs text-slate-800 font-medium">
                    <!-- Perm 4: Lihat Laporan Penjualan -->
                    <div class="flex items-center justify-between py-1">
                        <span>Lihat Laporan Penjualan</span>
                        <button type="button" 
                                @click="togglePermission('lihatLaporanPenjualan')"
                                class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                :class="currentPermissions.lihatLaporanPenjualan ? 'bg-[#0052cc]' : 'bg-slate-300'">
                            <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                  :class="currentPermissions.lihatLaporanPenjualan ? 'translate-x-4' : 'translate-x-0'"></span>
                        </button>
                    </div>

                    <!-- Perm 5: Akses Data Member -->
                    <div class="flex items-center justify-between py-1">
                        <span>Akses Data Member</span>
                        <button type="button" 
                                @click="togglePermission('aksesDataMember')"
                                class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                :class="currentPermissions.aksesDataMember ? 'bg-[#0052cc]' : 'bg-slate-300'">
                            <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                  :class="currentPermissions.aksesDataMember ? 'translate-x-4' : 'translate-x-0'"></span>
                        </button>
                    </div>

                    <!-- Perm 6: Lihat Laporan Laba (Owner Locked) -->
                    <div class="flex items-center justify-between py-1 text-slate-400">
                        <span>Lihat Laporan Laba (Owner)</span>
                        <template x-if="selectedRoleTab === 'OWNER'">
                            <span class="material-symbols-rounded text-emerald-600 text-[18px]" title="Akses Penuh Owner">check_circle</span>
                        </template>
                        <template x-if="selectedRoleTab !== 'OWNER'">
                            <span class="material-symbols-rounded text-rose-500 text-[18px]" title="Terkunci Khusus Owner">lock</span>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Submit Button (Matches Screenshot 3) -->
            <div class="pt-3">
                <button type="button" 
                        @click="saveRolePermissions()"
                        class="w-full py-2.5 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs shadow-sm shadow-blue-600/20 transition-all cursor-pointer">
                    Simpan Perubahan Role
                </button>
            </div>
        </div>

    </div>

    <!-- Bottom 3 Metric Stat Cards (Matches Screenshot 3) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
        <!-- Stat 1: Total Staf Aktif -->
        <div class="bg-white rounded-2xl p-4.5 border border-slate-200/80 shadow-2xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <span class="material-symbols-rounded text-[26px]">group</span>
            </div>
            <div>
                <div class="text-2xl font-black text-slate-900 tracking-tight leading-none" x-text="staffList.length > 5 ? staffList.length : '38'"></div>
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400 mt-1">TOTAL STAF AKTIF</div>
            </div>
        </div>

        <!-- Stat 2: Cabang Terdaftar -->
        <div class="bg-white rounded-2xl p-4.5 border border-slate-200/80 shadow-2xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                <span class="material-symbols-rounded text-[26px]">storefront</span>
            </div>
            <div>
                <div class="text-2xl font-black text-slate-900 tracking-tight leading-none">12</div>
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400 mt-1">CABANG TERDAFTAR</div>
            </div>
        </div>

        <!-- Stat 3: Update Izin Hari Ini -->
        <div class="bg-white rounded-2xl p-4.5 border border-slate-200/80 shadow-2xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                <span class="material-symbols-rounded text-[26px]">sync</span>
            </div>
            <div>
                <div class="text-2xl font-black text-slate-900 tracking-tight leading-none" x-text="updateIzinCount"></div>
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400 mt-1">UPDATE IZIN HARI INI</div>
            </div>
        </div>
    </div>

    <!-- Modal 1: Tambah Staf Baru -->
    <x-admin.modal show="showAddModal" title="Tambah Staf &amp; Hak Akses Baru">
        <form @submit.prevent="saveNewStaff()" class="space-y-4 text-xs">
            <!-- Pilihan Sumber: Dari Member Koperasi atau Buat Baru -->
            <div class="p-3 bg-blue-50/60 rounded-xl border border-blue-100 space-y-2">
                <label class="block font-bold text-slate-800">Pilih dari Anggota Koperasi (Opsional):</label>
                <select x-model="selectedMemberId" @change="onMemberSelect()" class="w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-slate-700 outline-none">
                    <option value="">-- Buat Staf Baru Secara Manual --</option>
                    <template x-for="m in membersList" :key="m.id">
                        <option :value="m.id" x-text="m.name + ' (' + m.dept + ' - ' + m.id + ')'"></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Nama Lengkap Staf *</label>
                <input type="text" x-model="formStaff.name" required placeholder="Contoh: Rian Hidayat" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 outline-none focus:border-[#0052cc]">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">ID Karyawan / EMP ID *</label>
                    <input type="text" x-model="formStaff.empId" required placeholder="EMP-00xxx" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 outline-none focus:border-[#0052cc]">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Peran / Role *</label>
                    <select x-model="formStaff.role" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white outline-none focus:border-[#0052cc]">
                        <option value="Kasir">Kasir</option>
                        <option value="Supervisor">Supervisor</option>
                        <option value="Admin">Admin / Manager</option>
                        <option value="Owner">Owner</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Cabang Penugasan *</label>
                    <select x-model="formStaff.branch" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white outline-none focus:border-[#0052cc]">
                        <option value="Cabang Pusat - Jakarta">Cabang Pusat - Jakarta</option>
                        <option value="Cabang Bandung Selatan">Cabang Bandung Selatan</option>
                        <option value="Cabang Jakarta Selatan">Cabang Jakarta Selatan</option>
                        <option value="Kantor Pusat (Global)">Kantor Pusat (Global)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Status Awal</label>
                    <select x-model="formStaff.status" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white outline-none focus:border-[#0052cc]">
                        <option value="Online">Online</option>
                        <option value="Offline">Offline</option>
                        <option value="Nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Sandi Akses Portal</label>
                    <input type="password" x-model="formStaff.password" placeholder="kasir123" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 outline-none focus:border-[#0052cc]">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">PIN Kasir (6 Digit)</label>
                    <input type="password" maxlength="6" x-model="formStaff.pin" placeholder="123456" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 outline-none focus:border-[#0052cc]">
                </div>
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
                <button type="button" @click="showAddModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 font-bold">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold">Simpan Staf</button>
            </div>
        </form>
    </x-admin.modal>

    <!-- Modal 2: Edit Staf -->
    <x-admin.modal show="showEditModal" title="Ubah Data &amp; Penugasan Staf">
        <form @submit.prevent="saveEditStaff()" class="space-y-4 text-xs">
            <div>
                <label class="block font-bold text-slate-700 mb-1">Nama Staf</label>
                <input type="text" x-model="editingStaff.name" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 outline-none focus:border-[#0052cc]">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Role Staf</label>
                    <select x-model="editingStaff.role" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white outline-none focus:border-[#0052cc]">
                        <option value="Kasir">Kasir</option>
                        <option value="Supervisor">Supervisor</option>
                        <option value="Admin">Admin</option>
                        <option value="Owner">Owner</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Cabang Penugasan</label>
                    <select x-model="editingStaff.branch" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white outline-none focus:border-[#0052cc]">
                        <option value="Cabang Pusat - Jakarta">Cabang Pusat - Jakarta</option>
                        <option value="Cabang Bandung Selatan">Cabang Bandung Selatan</option>
                        <option value="Cabang Jakarta Selatan">Cabang Jakarta Selatan</option>
                        <option value="Kantor Pusat (Global)">Kantor Pusat (Global)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Status Staf</label>
                <select x-model="editingStaff.status" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white outline-none focus:border-[#0052cc]">
                    <option value="Online">Online</option>
                    <option value="Offline">Offline</option>
                    <option value="Nonaktif">Nonaktif</option>
                </select>
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
                <button type="button" @click="showEditModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 font-bold">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold">Simpan Perubahan</button>
            </div>
        </form>
    </x-admin.modal>

    <!-- MODAL REKAP RIWAYAT RESET KREDENSIAL SELURUH CABANG (AKSES OWNER) -->
    <template x-teleport="body">
        <div x-show="showResetHistoryModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.away="showResetHistoryModal = false"
                 class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-3xl w-full overflow-hidden animate-scale-up text-left">
                
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-blue-100 text-[#0052cc] flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]">lock_reset</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-900 text-sm">Pengawasan Riwayat Reset Kredensial Kasir (Semua Cabang)</h3>
                            <p class="text-[11px] text-slate-500">Log audit khusus penerbitan kredensial sementara operasional koperasi</p>
                        </div>
                    </div>
                    <button type="button" @click="showResetHistoryModal = false" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-[10.5px] font-extrabold uppercase text-slate-400 tracking-wider border-b border-slate-200">
                                    <th class="py-3 px-3">WAKTU</th>
                                    <th class="py-3 px-3">KASIR / NIK</th>
                                    <th class="py-3 px-3">JENIS KREDENSIAL</th>
                                    <th class="py-3 px-3">DIPROSES OLEH</th>
                                    <th class="py-3 px-3">CABANG</th>
                                    <th class="py-3 px-3 text-center">STATUS</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="item in resetHistoryList" :key="item.id">
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="py-3 px-3 font-mono text-[11px] text-slate-500" x-text="item.timeFormatted || item.timestamp"></td>
                                        <td class="py-3 px-3">
                                            <div class="font-bold text-slate-900" x-text="item.userName"></div>
                                            <div class="text-[10px] text-slate-400 font-mono" x-text="'NIK: ' + (item.userNik || '-')"></div>
                                        </td>
                                        <td class="py-3 px-3">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border inline-flex items-center gap-1"
                                                  :class="item.resetTypeKey === 'password' ? 'bg-blue-50 text-[#0052cc] border-blue-200' : 'bg-amber-50 text-amber-800 border-amber-200'">
                                                <span class="material-symbols-rounded text-[12px]" x-text="item.resetTypeKey === 'password' ? 'key' : 'pin'"></span>
                                                <span x-text="item.resetType"></span>
                                            </span>
                                        </td>
                                        <td class="py-3 px-3 font-medium text-slate-700" x-text="item.processedBy"></td>
                                        <td class="py-3 px-3 font-semibold text-slate-800" x-text="item.branch"></td>
                                        <td class="py-3 px-3 text-center">
                                            <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[9.5px] font-bold uppercase">
                                                BERHASIL
                                            </span>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="resetHistoryList.length === 0">
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-slate-400 text-xs">
                                            Belum ada data riwayat reset kredensial kasir.
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                    <a href="{{ url('/owner/audit-log') }}" class="text-xs font-bold text-[#0052cc] hover:underline flex items-center gap-1">
                        <span>Buka Audit Log Lengkap</span>
                        <span class="material-symbols-rounded text-[14px]">arrow_forward</span>
                    </a>
                    <button type="button" @click="showResetHistoryModal = false" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl text-xs">
                        Tutup
                    </button>
                </div>

            </div>
        </div>
    </template>

    <!-- MODAL HASIL RESET DARURAT OWNER -->
    <template x-teleport="body">
        <div x-show="showOwnerResetSuccessModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.away="showOwnerResetSuccessModal = false"
                 class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-md w-full overflow-hidden animate-scale-up text-left p-6 space-y-4">
                
                <div class="text-center space-y-2">
                    <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto">
                        <span class="material-symbols-rounded text-[28px]">check_circle</span>
                    </div>
                    <h3 class="font-black text-slate-900 text-base">Reset Kredensial Darurat Berhasil</h3>
                    <p class="text-xs text-slate-500">
                        Kredensial untuk kasir <strong class="text-slate-800" x-text="ownerResetTarget?.name"></strong> telah diterbitkan secara darurat oleh Owner.
                    </p>
                </div>

                <div class="p-4 bg-slate-900 text-white rounded-2xl text-center space-y-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 block"
                          x-text="ownerResetType === 'password' ? 'PASSWORD SEMENTARA PORTAL' : 'PIN SEMENTARA POS (6 DIGIT)'"></span>
                    <div class="text-2xl font-black font-mono text-emerald-400 select-all" x-text="ownerResetGeneratedCredential"></div>
                    <button type="button" 
                            @click="copyOwnerResetCredential()"
                            :class="ownerCopiedToast ? 'bg-emerald-600 text-white' : 'bg-slate-800 hover:bg-slate-700 text-slate-200'"
                            class="w-full py-1.5 px-3 rounded-lg text-xs font-bold inline-flex items-center justify-center gap-1.5 transition-colors cursor-pointer border border-slate-700">
                        <span class="material-symbols-rounded text-[15px]" x-text="ownerCopiedToast ? 'done' : 'content_copy'"></span>
                        <span x-text="ownerCopiedToast ? 'Tersalin!' : 'Salin Kredensial'"></span>
                    </button>
                </div>

                <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-[11px] text-amber-900 leading-relaxed">
                    Aktivitas reset darurat ini telah dicatat pada <strong>Audit Log Koperasi</strong>. Sampaikan kredensial sementara ini kepada staf kasir.
                </div>

                <div class="pt-2">
                    <button type="button" @click="showOwnerResetSuccessModal = false" class="w-full py-2.5 bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs rounded-xl shadow-xs">
                        Selesai &amp; Tutup
                    </button>
                </div>
            </div>
        </div>
    </template>

</div>

<script>
function ownerKelolaAkunApp() {
    return {
        searchQuery: '',
        roleFilter: 'all',
        currentPage: 1,
        pageSize: 5,
        selectedRoleTab: 'KASIR',
        showAddModal: false,
        showEditModal: false,
        selectedMemberId: '',
        membersList: [],
        updateIzinCount: 14,
        editingStaff: { id: '', name: '', role: 'Kasir', branch: 'Cabang Pusat - Jakarta', status: 'Online' },

        // State Pemulihan & Reset Kredensial Owner
        showResetHistoryModal: false,
        showOwnerResetSuccessModal: false,
        ownerResetTarget: null,
        ownerResetType: 'password', // 'password' | 'pin'
        ownerResetGeneratedCredential: '',
        ownerCopiedToast: false,
        resetHistoryList: [],

        formStaff: {
            name: '',
            empId: '',
            role: 'Kasir',
            branch: 'Cabang Pusat - Jakarta',
            status: 'Online',
            password: 'kasir123',
            pin: '123456'
        },

        // Default Staff
        defaultStaff: [],

        staffList: [],

        // Role-Based Permissions Matrix
        rbacPermissions: {
            'KASIR': {
                bukaTutupShift: true,
                melakukanVoid: false,
                editHargaManual: false,
                lihatLaporanPenjualan: true,
                aksesDataMember: true,
                lihatLaporanLaba: false
            },
            'SPV': {
                bukaTutupShift: true,
                melakukanVoid: true,
                editHargaManual: false,
                lihatLaporanPenjualan: true,
                aksesDataMember: true,
                lihatLaporanLaba: false
            },
            'OWNER': {
                bukaTutupShift: true,
                melakukanVoid: true,
                editHargaManual: true,
                lihatLaporanPenjualan: true,
                aksesDataMember: true,
                lihatLaporanLaba: true
            }
        },

        init() {
            // 1. Load Staf dari pos_ilp_users_v1
            try {
                const stored = localStorage.getItem('pos_ilp_users_v1');
                if (stored) {
                    const parsed = JSON.parse(stored);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        this.staffList = parsed.map((u, i) => {
                            const name = u.name || 'Staf ' + (i+1);
                            const initials = name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                            return {
                                id: u.id || ('USR-0' + (i+1)),
                                empId: u.nik || u.empId || ('EMP-00' + (400 + i)),
                                name: name,
                                initials: initials,
                                avatarBg: i % 3 === 0 ? 'bg-blue-100 text-[#0052cc]' : (i % 3 === 1 ? 'bg-purple-100 text-purple-700' : 'bg-slate-800 text-white'),
                                role: u.role || 'Kasir',
                                branch: u.branch || (u.assignedBranches && u.assignedBranches[0] ? u.assignedBranches[0].branch : 'Cabang Pusat - Jakarta'),
                                status: u.status === 'AKTIF' || u.status === 'Online' ? 'Online' : (u.status === 'DIBLOKIR' ? 'Nonaktif' : 'Offline'),
                                password: u.password || '123456',
                                pin: u.pin || '123456'
                            };
                        });
                        return;
                    }
                }
            } catch(e) {}
            this.staffList = [];

            // 2. Load Permissions RBAC dari pos_ilp_rbac_v1
            try {
                const storedRbac = localStorage.getItem('pos_ilp_rbac_v1');
                if (storedRbac) {
                    this.rbacPermissions = { ...this.rbacPermissions, ...JSON.parse(storedRbac) };
                }
            } catch(e) {}

            // 3. Load Members List untuk integrasi pemilihan karyawan
            try {
                const storedMembers = localStorage.getItem('pos_ilp_members_v1');
                if (storedMembers) {
                    this.membersList = JSON.parse(storedMembers);
                }
            } catch(e) {}
        },

        saveStaff() {
            try {
                localStorage.setItem('pos_ilp_users_v1', JSON.stringify(this.staffList));
            } catch(e) {}
        },

        get currentPermissions() {
            return this.rbacPermissions[this.selectedRoleTab] || this.rbacPermissions['KASIR'];
        },

        togglePermission(key) {
            if (this.selectedRoleTab === 'OWNER' && key === 'lihatLaporanLaba') return;
            this.currentPermissions[key] = !this.currentPermissions[key];
        },

        saveRolePermissions() {
            try {
                localStorage.setItem('pos_ilp_rbac_v1', JSON.stringify(this.rbacPermissions));
                this.updateIzinCount++;
                if (window.triggerToast) {
                    window.triggerToast('Hak akses role ' + this.selectedRoleTab + ' berhasil diperbarui dan disinkronkan ke seluruh terminal!', 'success');
                }
            } catch(e) {
                if (window.triggerToast) window.triggerToast('Gagal menyimpan izin role: ' + e.message, 'error');
            }
        },

        get filteredStaff() {
            return this.staffList.filter(s => {
                const q = this.searchQuery.toLowerCase();
                const matchSearch = s.name.toLowerCase().includes(q) || 
                                    (s.empId && s.empId.toLowerCase().includes(q)) || 
                                    s.branch.toLowerCase().includes(q) ||
                                    s.role.toLowerCase().includes(q);
                const matchRole = this.roleFilter === 'all' || s.role.toLowerCase() === this.roleFilter.toLowerCase();
                return matchSearch && matchRole;
            });
        },

        get totalPages() {
            return Math.max(1, Math.ceil(this.filteredStaff.length / this.pageSize));
        },

        get paginatedStaff() {
            const start = (this.currentPage - 1) * this.pageSize;
            return this.filteredStaff.slice(start, start + this.pageSize);
        },

        openAddStaffModal() {
            this.formStaff = {
                name: '',
                empId: 'EMP-00' + (this.staffList.length + 420),
                role: 'Kasir',
                branch: 'Cabang Pusat - Jakarta',
                status: 'Online',
                password: 'kasir123',
                pin: '123456'
            };
            this.selectedMemberId = '';
            this.showAddModal = true;
        },

        onMemberSelect() {
            if (!this.selectedMemberId) return;
            const found = this.membersList.find(m => m.id === this.selectedMemberId);
            if (found) {
                this.formStaff.name = found.name;
                this.formStaff.empId = found.nik || found.id;
                this.formStaff.branch = found.branch ? ('Cabang ' + found.branch) : 'Cabang Pusat - Jakarta';
            }
        },

        saveNewStaff() {
            if (!this.formStaff.name) {
                if (window.triggerToast) window.triggerToast('Harap masukkan nama staf!', 'error');
                return;
            }
            const initials = this.formStaff.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
            const newStaff = {
                id: 'USR-0' + (this.staffList.length + 1),
                empId: this.formStaff.empId || ('EMP-00' + (this.staffList.length + 421)),
                name: this.formStaff.name,
                initials: initials,
                avatarBg: 'bg-blue-100 text-[#0052cc]',
                role: this.formStaff.role,
                branch: this.formStaff.branch,
                status: this.formStaff.status,
                password: this.formStaff.password || 'kasir123',
                pin: this.formStaff.pin || '123456'
            };
            this.staffList.unshift(newStaff);
            this.saveStaff();
            this.showAddModal = false;
            if (window.triggerToast) window.triggerToast('Staf ' + newStaff.name + ' (' + newStaff.role + ') berhasil ditambahkan!', 'success');
        },

        openEditStaff(staf) {
            this.editingStaff = JSON.parse(JSON.stringify(staf));
            this.showEditModal = true;
        },

        saveEditStaff() {
            if (!this.editingStaff) return;
            const idx = this.staffList.findIndex(s => s.id === this.editingStaff.id);
            if (idx !== -1) {
                this.staffList[idx] = { ...this.staffList[idx], ...this.editingStaff };
                this.saveStaff();
                this.showEditModal = false;
                if (window.triggerToast) window.triggerToast('Data penugasan ' + this.editingStaff.name + ' berhasil diperbarui!', 'success');
            }
        },

        resetStaffPin(staf) {
            if (!confirm(`Konfirmasi Tindakan Darurat Owner:\nReset PIN Kasir untuk ${staf.name} (${staf.branch})?\n\nTindakan ini HANYA akan mengubah PIN terminal tanpa menyentuh password portal.`)) {
                return;
            }
            const newPin = String(Math.floor(100000 + Math.random() * 900000));
            staf.pin = newPin;
            staf.mustChangePin = true;
            this.saveStaff();

            // Catat ke pos_ilp_reset_history_v1 & pos_ilp_audit_logs_v1
            this.recordOwnerResetHistory(staf, 'pin');
            this.recordOwnerAuditLog(staf, 'pin');

            this.ownerResetTarget = staf;
            this.ownerResetType = 'pin';
            this.ownerResetGeneratedCredential = newPin;
            this.showOwnerResetSuccessModal = true;
            if (window.triggerToast) window.triggerToast('PIN Kasir ' + staf.name + ' berhasil direset!', 'success');
        },

        resetStaffPassword(staf) {
            if (!confirm(`Konfirmasi Tindakan Darurat Owner:\nReset Password Portal untuk ${staf.name} (${staf.branch})?\n\nTindakan ini HANYA akan mengubah password login portal tanpa menyentuh PIN terminal.`)) {
                return;
            }
            const newPass = 'ILP-Kasir' + Math.floor(100 + Math.random() * 900);
            staf.password = newPass;
            staf.mustChangePassword = true;
            this.saveStaff();

            // Catat ke pos_ilp_reset_history_v1 & pos_ilp_audit_logs_v1
            this.recordOwnerResetHistory(staf, 'password');
            this.recordOwnerAuditLog(staf, 'password');

            this.ownerResetTarget = staf;
            this.ownerResetType = 'password';
            this.ownerResetGeneratedCredential = newPass;
            this.showOwnerResetSuccessModal = true;
            if (window.triggerToast) window.triggerToast('Password portal ' + staf.name + ' berhasil direset!', 'success');
        },

        recordOwnerResetHistory(staf, type) {
            try {
                let history = [];
                const stored = localStorage.getItem('pos_ilp_reset_history_v1');
                if (stored) history = JSON.parse(stored);

                const now = new Date();
                const dateStr = now.toISOString().slice(0, 10);
                const timeStr = now.toTimeString().slice(0, 8);
                const timeFormatted = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ', ' + now.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });

                history.unshift({
                    id: 'RST-' + Date.now(),
                    timestamp: `${dateStr} ${timeStr}`,
                    timeFormatted: timeFormatted,
                    userId: staf.id,
                    userName: staf.name,
                    userUsername: staf.username || staf.empId,
                    userNik: staf.empId || '-',
                    branch: staf.branch,
                    resetType: type === 'password' ? 'Password Portal' : 'PIN Terminal (6-Digit)',
                    resetTypeKey: type,
                    processedBy: 'Hendra Gunawan (Owner)',
                    processedByRole: 'Owner',
                    processedBranch: 'Semua Cabang (Akses Penuh)',
                    status: 'BERHASIL',
                    mustChangeNextLogin: true
                });
                localStorage.setItem('pos_ilp_reset_history_v1', JSON.stringify(history));
            } catch(e) {}
        },

        recordOwnerAuditLog(staf, type) {
            try {
                let logs = [];
                const stored = localStorage.getItem('pos_ilp_audit_logs_v1');
                if (stored) logs = JSON.parse(stored);

                const now = new Date();
                const dateStr = now.toISOString().slice(0, 10);
                const timeStr = now.toTimeString().slice(0, 8);
                const logId = 'LOG-' + dateStr.replace(/-/g, '') + '-' + Math.floor(1000 + Math.random() * 9000);

                logs.unshift({
                    id: logId,
                    timestamp: `${dateStr} ${timeStr}`,
                    userId: 'usr_05',
                    userName: 'Hendra Gunawan',
                    userRole: 'Owner',
                    location: staf.branch || 'Pusat',
                    category: 'User & Hak Akses',
                    actionName: type === 'password' ? 'Reset Password Kasir (Darurat Owner)' : 'Reset PIN Kasir (Darurat Owner)',
                    description: `Penerbitan kredensial sementara (${type === 'password' ? 'Password Portal' : 'PIN Terminal 6-Digit'}) oleh Owner Hendra untuk staf [${staf.name}] pada ${staf.branch}. Akun ditandai wajib ganti kredensial.`,
                    reference: 'RST-OWNER-' + (staf.empId || staf.id),
                    status: 'BERHASIL',
                    source: 'Web Owner Console / Darurat Operasional',
                    changes: [
                        {
                            field: type === 'password' ? 'Status Password Portal' : 'Status PIN Terminal POS',
                            before: 'LUPA / TERKUNCI',
                            after: 'RESET DARURAT OWNER (Wajib Ganti)'
                        }
                    ]
                });
                if (logs.length > 200) logs = logs.slice(0, 200);
                localStorage.setItem('pos_ilp_audit_logs_v1', JSON.stringify(logs));
            } catch(e) {}
        },

        loadResetHistory() {
            try {
                const stored = localStorage.getItem('pos_ilp_reset_history_v1');
                if (stored) {
                    this.resetHistoryList = JSON.parse(stored);
                    return;
                }
            } catch(e) {}
            this.resetHistoryList = [];
        },

        copyOwnerResetCredential() {
            if (!this.ownerResetGeneratedCredential) return;
            if (navigator && navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(this.ownerResetGeneratedCredential).then(() => {
                    this.ownerCopiedToast = true;
                    setTimeout(() => { this.ownerCopiedToast = false; }, 2500);
                    if (window.triggerToast) window.triggerToast('Kredensial disalin ke clipboard!', 'info');
                });
            }
        },

        toggleStaffStatus(staf) {
            staf.status = staf.status === 'Online' ? 'Offline' : 'Online';
            this.saveStaff();
            if (window.triggerToast) window.triggerToast('Status ' + staf.name + ' diubah ke ' + staf.status, 'info');
        },

        deleteStaff(staf) {
            if (confirm('Hapus staf ' + staf.name + ' dari daftar akses sistem?')) {
                this.staffList = this.staffList.filter(s => s.id !== staf.id);
                this.saveStaff();
                if (window.triggerToast) window.triggerToast('Staf ' + staf.name + ' berhasil dihapus dari sistem.', 'info');
            }
        },

        exportStaffCsv() {
            try {
                const headers = ['ID Staf', 'EMP ID', 'Nama Staf', 'Role', 'Cabang Penugasan', 'Status'];
                const rows = this.staffList.map(s => [
                    '"' + s.id + '"',
                    '"' + (s.empId || '') + '"',
                    '"' + s.name + '"',
                    '"' + s.role + '"',
                    '"' + s.branch + '"',
                    '"' + s.status + '"'
                ]);
                const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
                const encodedUri = encodeURI(csvContent);
                const link = document.createElement('a');
                link.setAttribute('href', encodedUri);
                link.setAttribute('download', 'daftar_staf_rbac_' + new Date().toISOString().slice(0, 10) + '.csv');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                if (window.triggerToast) window.triggerToast('File CSV daftar staf berhasil diunduh.', 'success');
            } catch(e) {
                if (window.triggerToast) window.triggerToast('Gagal ekspor data: ' + e.message, 'error');
            }
        }
    };
}
</script>
@endsection
