@extends('layouts.owner-admin', ['active' => 'members'])

@section('title', 'Tata Kelola Anggota & Plafon Piutang Koperasi - ILP POS')

@section('header')
<x-admin.header title="Koperasi Owner Portal" :showSearch="false">
    <x-slot:leftSlot>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ url('/owner') }}" class="text-xs font-black uppercase text-slate-800 tracking-wider hover:text-[#0052cc] transition-colors">Owner Console</a>
            <span class="text-slate-300">&gt;</span>
            <span class="text-xs font-bold text-slate-500">Anggota &amp; Kredit</span>
            <span class="text-slate-300">&gt;</span>
            <span class="text-xs font-bold text-[#0052cc]">Kontrol Plafon &amp; Piutang</span>
            
            <span class="ml-2 inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10.5px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200">
                <span class="material-symbols-rounded text-[13px]">shield</span>
                <span>Multi-Branch Governance</span>
            </span>
        </div>
    </x-slot:leftSlot>

    <!-- Top Action Buttons -->
    <div class="flex items-center gap-2">
        <button type="button" 
                @click="exportRekapPiutang()"
                class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-2xs inline-flex items-center gap-1.5 transition-colors cursor-pointer">
            <span class="material-symbols-rounded text-[16px]">table_chart</span>
            <span class="hidden sm:inline">Ekspor Excel Piutang</span>
        </button>

        <button type="button" 
                @click="showRegisterModal = true"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-xs font-bold shadow-sm shadow-blue-600/20 transition-all cursor-pointer">
            <span class="material-symbols-rounded text-[17px]">person_add</span>
            <span>+ Registrasi Anggota</span>
        </button>
    </div>
</x-admin.header>
@endsection

@section('admin_content')
<div x-data="membersApp()" 
     x-init="initMembers()"
     class="space-y-6">

    <!-- Page Title & Subtitle -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-xl md:text-2xl font-black text-slate-900 tracking-tight">Members</h1>
        </div>
    </div>

    <!-- Top 5 Metric Cards (All Dynamic Formulas) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
        <!-- 1. TOTAL ANGGOTA -->
        <div class="bg-white rounded-2xl p-4.5 border border-slate-200/80 shadow-2xs flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <span class="text-[10.5px] font-extrabold uppercase tracking-wider text-slate-500">TOTAL ANGGOTA</span>
                <span class="material-symbols-rounded text-[18px] text-blue-600">group</span>
            </div>
            <div class="mt-2 text-2xl font-black text-slate-900 tracking-tight leading-none" x-text="members.length">0</div>
            <div class="mt-2.5 flex items-center gap-2 text-[11px] font-bold">
                <span class="text-emerald-600" x-text="activeMembersPercentage + ' Aktif'"></span>
                <span class="text-slate-400">&bull;</span>
                <span class="text-slate-500" x-text="(members.length - activeMembersCount) + ' Nonaktif'"></span>
            </div>
        </div>

        <!-- 2. TOTAL PLAFON DIALOKASIKAN (Dynamic) -->
        <div class="bg-white rounded-2xl p-4.5 border border-slate-200/80 shadow-2xs flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <span class="text-[10.5px] font-extrabold uppercase tracking-wider text-slate-500">TOTAL PLAFON DIALOKASIKAN</span>
                <span class="material-symbols-rounded text-[18px] text-blue-600">account_balance_wallet</span>
            </div>
            <div class="mt-2 text-xl font-black text-slate-900 tracking-tight leading-none" x-text="formatRupiah(totalPlafonDialokasikan)">Rp 0</div>
            <div class="mt-2.5 text-[11px] text-slate-500 font-medium">
                Pagu kredit limit &bull; <strong class="text-blue-600 font-bold" x-text="'Tersedia ' + availablePlafonPercentage"></strong>
            </div>
            <div class="mt-1 text-[10px] text-slate-400 font-mono">
                Dedup NIK (Aktif &amp; Unblocked), Pending Excluded
            </div>
        </div>

        <!-- 3. PIUTANG BERJALAN (OUTSTANDING) (Dynamic) -->
        <div class="bg-white rounded-2xl p-4.5 border border-slate-200/80 shadow-2xs flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <span class="text-[10.5px] font-extrabold uppercase tracking-wider text-slate-500">PIUTANG BERJALAN</span>
                <span class="material-symbols-rounded text-[18px] text-amber-600">receipt_long</span>
            </div>
            <div class="mt-2 text-xl font-black text-amber-600 tracking-tight leading-none" x-text="formatRupiah(totalPiutangBerjalan)">Rp 0</div>
            <div class="mt-2.5 text-[11px] text-slate-500 font-medium">
                <span class="font-bold text-amber-700" x-text="usedPlafonPercentage"></span> terutilisasi dari pagu
            </div>
            <div class="mt-1 text-[10px] text-slate-400 font-mono">
                Saldo piutang aktif non-void/refund
            </div>
        </div>

        <!-- 4. KOLEKTIBILITAS KREDIT (Dynamic) -->
        <div class="bg-white rounded-2xl p-4.5 border border-slate-200/80 shadow-2xs flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <span class="text-[10.5px] font-extrabold uppercase tracking-wider text-slate-500">KOLEKTIBILITAS</span>
                <span class="material-symbols-rounded text-[18px] text-emerald-600">verified_user</span>
            </div>
            <div class="mt-2 text-xl font-black text-emerald-600 tracking-tight leading-none" x-text="kolektibilitasRate + ' Lancar'">100% Lancar</div>
            <div class="mt-2.5 text-[11px] text-slate-500 font-medium">
                Berdasarkan status kredit anggota
            </div>
        </div>

        <!-- 5. AKUMULASI SHU YTD (Dynamic) -->
        <div class="bg-white rounded-2xl p-4.5 border border-slate-200/80 shadow-2xs flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <span class="text-[10.5px] font-extrabold uppercase tracking-wider text-slate-500">AKUMULASI SHU YTD</span>
                <span class="material-symbols-rounded text-[18px] text-blue-600">savings</span>
            </div>
            <div class="mt-2 text-xl font-black text-[#0052cc] tracking-tight leading-none" x-text="formatRupiah(totalShu)">Rp 0</div>
            <div class="mt-2.5 text-[11px] font-bold text-emerald-600">
                Alokasi SHU anggota berjalan
            </div>
        </div>
    </div>

    <!-- Navigation Tabs Bar (3 Active Tabs) -->
    <div class="flex items-center gap-2 border-b border-slate-200 overflow-x-auto pb-0.5">
        <!-- Tab 1: Daftar Anggota & Piutang -->
        <button type="button" 
                @click="activeTab = 'daftar'"
                class="px-4 py-2.5 text-xs font-bold transition-all border-b-2 inline-flex items-center gap-2 whitespace-nowrap cursor-pointer"
                :class="activeTab === 'daftar' ? 'border-[#0052cc] text-[#0052cc] bg-blue-50/50 rounded-t-xl' : 'border-transparent text-slate-600 hover:text-slate-900'">
            <span class="material-symbols-rounded text-[16px]">group</span>
            <span>Daftar Anggota &amp; Piutang</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#0052cc] text-white" x-text="members.length"></span>
        </button>

        <!-- Tab 2: Permintaan Kenaikan Plafon -->
        <button type="button" 
                @click="activeTab = 'permintaan'"
                class="px-4 py-2.5 text-xs font-bold transition-all border-b-2 inline-flex items-center gap-2 whitespace-nowrap cursor-pointer"
                :class="activeTab === 'permintaan' ? 'border-[#0052cc] text-[#0052cc] bg-blue-50/50 rounded-t-xl' : 'border-transparent text-slate-600 hover:text-slate-900'">
            <span class="material-symbols-rounded text-[16px]">trending_up</span>
            <span>Permintaan Kenaikan Plafon</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold text-white"
                  :class="pendingApprovals.length > 0 ? 'bg-rose-500' : 'bg-slate-400'"
                  x-text="pendingApprovals.length + ' Approval'">
            </span>
        </button>

        <!-- Tab 3: Analisis Belanja per Departemen -->
        <button type="button" 
                @click="activeTab = 'analisis'"
                class="px-4 py-2.5 text-xs font-bold transition-all border-b-2 inline-flex items-center gap-2 whitespace-nowrap cursor-pointer"
                :class="activeTab === 'analisis' ? 'border-[#0052cc] text-[#0052cc] bg-blue-50/50 rounded-t-xl' : 'border-transparent text-slate-600 hover:text-slate-900'">
            <span class="material-symbols-rounded text-[16px]">pie_chart</span>
            <span>Analisis Belanja per Departemen</span>
        </button>
    </div>

    <!-- ========================================== -->
    <!-- TAB 1: DAFTAR ANGGOTA & PIUTANG -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'daftar'" class="space-y-4">
        <!-- Filter Bar -->
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
            <!-- Search input -->
            <div class="relative flex-1 max-w-md">
                <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                <input type="text" 
                       x-model="searchQuery" 
                       placeholder="Cari NIK, Nama Anggota, Departemen..." 
                       class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-white border border-slate-200 text-[13px] text-slate-800 focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none shadow-2xs">
            </div>

            <!-- 3 Filter Dropdowns -->
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Filter Cabang -->
                <select x-model="branchFilter" class="px-3 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 outline-none shadow-2xs cursor-pointer">
                    <option value="all">Semua Cabang Toko</option>
                    <option value="Pusat">Pusat</option>
                    <option value="Cabang A">Cabang A</option>
                    <option value="Cabang B">Cabang B</option>
                </select>

                <!-- Filter Status Kredit -->
                <select x-model="creditStatusFilter" class="px-3 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 outline-none shadow-2xs cursor-pointer">
                    <option value="all">Semua Status Kredit</option>
                    <option value="lancar">Lancar</option>
                    <option value="hampir limit">Hampir Limit</option>
                    <option value="dibekukan">Dibekukan</option>
                </select>

                <!-- Filter Departemen -->
                <select x-model="deptFilter" class="px-3 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 outline-none shadow-2xs cursor-pointer">
                    <option value="all">Semua Departemen</option>
                    <template x-for="dept in availableDepartments" :key="dept">
                        <option :value="dept" x-text="dept"></option>
                    </template>
                </select>

                <!-- Refresh Button -->
                <button type="button" 
                        @click="initMembers(); triggerToast('Data plafon dan piutang anggota diperbarui!', 'info')"
                        class="p-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 shadow-2xs transition-colors cursor-pointer"
                        title="Refresh Data">
                    <span class="material-symbols-rounded text-[18px]">refresh</span>
                </button>
            </div>
        </div>

        <!-- Table Card: Daftar Audit Plafon Piutang Anggota Aktif -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden">
            <!-- Table Top Subheader with Functional Sorting -->
            <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-rounded text-[20px] text-[#0052cc]">badge</span>
                    <h3 class="text-base font-bold text-slate-900 tracking-tight">Daftar Audit Plafon Piutang Anggota Aktif</h3>
                    <span class="text-xs text-slate-500 font-medium">(Menampilkan <span x-text="sortedFilteredMembers.length"></span> dari <span x-text="members.length"></span> data)</span>
                </div>

                <div class="flex items-center gap-2 text-xs relative">
                    <span class="text-slate-500 font-semibold">Sortir Berdasarkan:</span>
                    <div class="relative">
                        <button type="button" 
                                @click="showSortMenu = !showSortMenu"
                                class="px-3 py-1.5 rounded-lg border border-slate-200 font-bold text-slate-800 bg-slate-50 hover:bg-slate-100 transition-colors inline-flex items-center gap-1.5 shadow-2xs cursor-pointer">
                            <span x-text="sortBy === 'utilitas_desc' ? 'Pemakaian Plafon Tertinggi' : (sortBy === 'piutang_desc' ? 'Piutang Berjalan Tertinggi' : 'Nama Anggota (A-Z)')"></span>
                            <span class="material-symbols-rounded text-[16px] text-slate-400">expand_more</span>
                        </button>

                        <!-- Sort Popover Menu -->
                        <div x-show="showSortMenu"
                             @click.away="showSortMenu = false"
                             x-cloak
                             class="absolute right-0 mt-1.5 w-56 rounded-2xl bg-white border border-slate-200 shadow-xl py-2 z-30 text-[12.5px] space-y-0.5">
                            <button type="button" 
                                    @click="sortBy = 'utilitas_desc'; showSortMenu = false" 
                                    class="w-full text-left px-3.5 py-2 hover:bg-slate-50 font-medium transition-colors"
                                    :class="sortBy === 'utilitas_desc' ? 'text-[#0052cc] font-bold bg-blue-50/50' : 'text-slate-700'">
                                Pemakaian Plafon Tertinggi (%)
                            </button>
                            <button type="button" 
                                    @click="sortBy = 'piutang_desc'; showSortMenu = false" 
                                    class="w-full text-left px-3.5 py-2 hover:bg-slate-50 font-medium transition-colors"
                                    :class="sortBy === 'piutang_desc' ? 'text-[#0052cc] font-bold bg-blue-50/50' : 'text-slate-700'">
                                Piutang Berjalan Tertinggi (Rp)
                            </button>
                            <button type="button" 
                                    @click="sortBy = 'name_asc'; showSortMenu = false" 
                                    class="w-full text-left px-3.5 py-2 hover:bg-slate-50 font-medium transition-colors"
                                    :class="sortBy === 'name_asc' ? 'text-[#0052cc] font-bold bg-blue-50/50' : 'text-slate-700'">
                                Nama Anggota (A-Z)
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Container without profile photos -->
            <div class="overflow-x-auto w-full">
                <table class="w-full text-left border-collapse" style="min-width: 980px;">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-[11px] font-extrabold uppercase text-slate-500 tracking-wider">
                            <th class="py-3.5 px-5 text-left w-[240px]">NAMA ANGGOTA</th>
                            <th class="py-3.5 px-4 text-left w-[160px]">NIK / ID</th>
                            <th class="py-3.5 px-4 text-left w-[180px]">DEPARTEMEN</th>
                            <th class="py-3.5 px-4 text-right w-[150px]">SISA PLAFON</th>
                            <th class="py-3.5 px-5 text-left w-[220px]">PIUTANG BERJALAN &amp; UTILITAS</th>
                            <th class="py-3.5 px-4 text-center w-[120px]">STATUS</th>
                            <th class="py-3.5 px-5 text-right w-[140px]">AKSI OWNER</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-[13px]">
                        <template x-for="m in paginatedMembers" :key="m.id">
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <!-- 1. NAMA ANGGOTA (Tanpa Foto Profil) -->
                                <td class="py-3.5 px-5">
                                    <div class="flex flex-col min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-slate-900 leading-tight truncate" x-text="m.name"></span>
                                            <template x-if="m.locked || m.status === 'DIBEKUKAN' || m.status === 'DIBLOKIR'">
                                                <span class="material-symbols-rounded text-[14px] text-rose-600 shrink-0" title="Akun Dibekukan">lock</span>
                                            </template>
                                        </div>
                                        <span class="text-[11px] text-slate-500 mt-0.5" x-text="m.phone || '0812-9841-2201'"></span>
                                    </div>
                                </td>

                                <!-- 2. NIK / ID -->
                                <td class="py-3.5 px-4 font-mono">
                                    <span class="block font-bold text-xs text-slate-800" x-text="m.nik"></span>
                                    <span class="text-[10px] text-slate-400 block mt-0.5 font-medium" x-text="m.id"></span>
                                </td>

                                <!-- 3. DEPARTEMEN -->
                                <td class="py-3.5 px-4">
                                    <div class="font-semibold text-xs text-slate-700 leading-tight" x-text="m.dept"></div>
                                    <span class="inline-block mt-1 text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-50 text-[#0052cc] border border-blue-100" x-text="'Cab. ' + (m.branch || 'Pusat')"></span>
                                </td>

                                <!-- 4. SISA PLAFON -->
                                <td class="py-3.5 px-4 text-right">
                                    <div class="font-black font-mono text-[13.5px]"
                                         :class="m.sisaPlafon > 0 ? 'text-emerald-600' : 'text-rose-600'"
                                         x-text="'Rp ' + (m.sisaPlafon || 0).toLocaleString('id-ID')">
                                    </div>
                                    <span class="text-[10px] font-mono text-slate-400 block mt-0.5" x-text="'Plafon: Rp ' + (m.plafon || 5000000).toLocaleString('id-ID')"></span>
                                </td>

                                <!-- 5. PIUTANG BERJALAN & UTILITAS -->
                                <td class="py-3.5 px-5">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="font-bold font-mono text-slate-800" x-text="'Rp ' + (m.piutang || 0).toLocaleString('id-ID')"></span>
                                        <span class="font-bold text-[10.5px]" 
                                              :class="{
                                                  'text-emerald-600': m.utilitas < 70,
                                                  'text-amber-600': m.utilitas >= 70 && m.utilitas < 100,
                                                  'text-rose-600': m.utilitas >= 100
                                              }"
                                              x-text="m.utilitas + '% ' + (m.utilitas >= 100 ? '(HOLD)' : 'used')">
                                        </span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                        <div class="h-1.5 rounded-full transition-all duration-300"
                                             :class="{
                                                 'bg-emerald-500': m.utilitas < 70,
                                                 'bg-amber-500': m.utilitas >= 70 && m.utilitas < 100,
                                                 'bg-rose-500': m.utilitas >= 100
                                             }"
                                             :style="'width: ' + Math.min(100, m.utilitas) + '%'">
                                        </div>
                                    </div>
                                </td>

                                <!-- 6. STATUS -->
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border inline-block"
                                          :class="{
                                              'bg-emerald-50 text-emerald-700 border-emerald-200': m.status === 'LANCAR' || m.status === 'AKTIF',
                                              'bg-amber-50 text-amber-700 border-amber-200': m.status === 'HAMPIR LIMIT',
                                              'bg-rose-50 text-rose-700 border-rose-200': m.status === 'DIBEKUKAN' || m.status === 'DIBLOKIR'
                                          }"
                                          x-text="m.status === 'DIBLOKIR' ? 'DIBEKUKAN' : (m.status === 'AKTIF' ? 'LANCAR' : m.status)">
                                    </span>
                                </td>

                                <!-- 7. AKSI OWNER -->
                                <td class="py-3.5 px-5 text-right whitespace-nowrap relative">
                                    <div class="inline-flex items-center gap-1.5">
                                        <template x-if="m.status === 'DIBEKUKAN' || m.status === 'DIBLOKIR' || m.locked">
                                            <button type="button" 
                                                    @click="unlockMember(m)"
                                                    class="px-3 py-1 rounded-lg bg-[#0052cc] hover:bg-[#0043a8] text-white text-xs font-bold shadow-2xs transition-colors cursor-pointer">
                                                Buka Blokir
                                            </button>
                                        </template>
                                        <template x-if="m.status !== 'DIBEKUKAN' && m.status !== 'DIBLOKIR' && !m.locked">
                                            <button type="button" 
                                                    @click="selectedMember = m; showDetailModal = true"
                                                    class="px-2.5 py-1 rounded-lg text-xs font-bold text-[#0052cc] hover:bg-blue-50 transition-colors cursor-pointer">
                                                Detail
                                            </button>
                                        </template>

                                        <!-- More Actions Dropdown -->
                                        <div class="relative inline-block text-left">
                                            <button type="button" 
                                                    @click="showActionMemberId = showActionMemberId === m.id ? null : m.id"
                                                    class="p-1 rounded-lg text-slate-400 hover:text-slate-800 hover:bg-slate-100 transition-colors cursor-pointer"
                                                    title="Pilihan Aksi">
                                                <span class="material-symbols-rounded text-[18px]">more_vert</span>
                                            </button>

                                            <!-- Popover Menu -->
                                            <div x-show="showActionMemberId === m.id"
                                                 @click.away="showActionMemberId = null"
                                                 x-cloak
                                                 class="absolute right-0 mt-1 w-52 rounded-2xl bg-white border border-slate-200 shadow-xl py-2 z-30 text-[12.5px] text-left space-y-0.5">
                                                <button type="button" 
                                                        @click="selectedMember = m; showDetailModal = true; showActionMemberId = null"
                                                        class="w-full text-left flex items-center gap-2 px-3.5 py-2 text-slate-700 hover:bg-slate-50 hover:text-[#0052cc] font-medium transition-colors">
                                                    <span class="material-symbols-rounded text-[17px]">visibility</span>
                                                    <span>Detail Histori &amp; Plafon</span>
                                                </button>
                                                <button type="button" 
                                                        @click="requestLimitIncrease(m)"
                                                        class="w-full text-left flex items-center gap-2 px-3.5 py-2 text-slate-700 hover:bg-slate-50 hover:text-emerald-600 font-medium transition-colors">
                                                    <span class="material-symbols-rounded text-[17px]">trending_up</span>
                                                    <span>Ajukan Kenaikan Limit</span>
                                                </button>
                                                <button type="button" 
                                                        @click="toggleLockMember(m)"
                                                        class="w-full text-left flex items-center gap-2 px-3.5 py-2 text-slate-700 hover:bg-slate-50 font-medium transition-colors"
                                                        :class="m.locked ? 'hover:text-emerald-600' : 'hover:text-rose-600'">
                                                    <span class="material-symbols-rounded text-[17px]" x-text="m.locked ? 'lock_open' : 'lock'"></span>
                                                    <span x-text="m.locked ? 'Buka Kunci Plafon' : 'Bekukan Plafon Anggota'"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="members.length === 0">
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <span class="material-symbols-rounded text-3xl mb-1 text-slate-300 block">group_off</span>
                                    <p class="font-bold text-sm text-slate-700">Belum ada data member.</p>
                                    <button type="button" @click="showRegisterModal = true" class="mt-3 px-4 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-xs font-bold inline-flex items-center gap-1.5 shadow-sm transition-colors cursor-pointer">
                                        <span class="material-symbols-rounded text-[16px]">person_add</span>
                                        <span>Registrasi Anggota Baru</span>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <template x-if="members.length > 0 && sortedFilteredMembers.length === 0">
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <span class="material-symbols-rounded text-3xl mb-1 text-slate-300 block">search_off</span>
                                    <p class="font-bold text-sm text-slate-700">Tidak ada anggota yang cocok dengan filter pencarian.</p>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Footer Pagination -->
            <div class="px-6 py-3.5 border-t border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-[12.5px] text-slate-500">
                <div>
                    Menampilkan <span class="font-bold text-slate-800" x-text="sortedFilteredMembers.length ? ((currentPage - 1) * pageSize + 1) + ' - ' + Math.min(currentPage * pageSize, sortedFilteredMembers.length) : '0'"></span> dari <span class="font-bold text-slate-800" x-text="sortedFilteredMembers.length"></span> data anggota koperasi
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" 
                            @click="if (currentPage > 1) currentPage--" 
                            :disabled="currentPage <= 1"
                            :class="currentPage <= 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-100 text-slate-700'"
                            class="px-2.5 py-1 rounded-lg border border-slate-200 text-xs font-medium transition-colors">
                        Sebelumnya
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
                            class="px-2.5 py-1 rounded-lg border border-slate-200 text-xs font-medium transition-colors">
                        Selanjutnya
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- TAB 2: PERMINTAAN KENAIKAN PLAFON (FULL PANEL) -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'permintaan'" class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-black text-slate-900 tracking-tight">Otorisasi Permintaan Kenaikan Plafon Anggota</h3>
            </div>
            <button type="button" 
                    @click="activeTab = 'daftar'"
                    class="px-3.5 py-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold inline-flex items-center gap-1.5 shadow-2xs transition-colors cursor-pointer">
                <span class="material-symbols-rounded text-[18px]">arrow_back</span>
                <span>Kembali ke Daftar Anggota</span>
            </button>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden">
            <div class="p-6 divide-y divide-slate-100">
                <template x-for="req in pendingApprovals" :key="req.id">
                    <div class="py-4 first:pt-0 last:pb-0 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-blue-50 text-[#0052cc] flex items-center justify-center font-black text-sm shrink-0"
                                     x-text="req.initials">
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-slate-900 text-sm" x-text="req.name"></h4>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 text-slate-600" x-text="'NIK: ' + req.nik"></span>
                                    </div>
                                    <div class="text-xs text-slate-500 mt-0.5" x-text="req.dept + ' • ' + (req.tenure || 'Anggota Aktif')"></div>
                                </div>
                            </div>

                            <!-- Limit Change Box -->
                            <div class="flex items-center gap-2 text-xs font-bold bg-slate-50 px-3.5 py-2 rounded-xl border border-slate-200/80 self-start sm:self-auto">
                                <span class="text-slate-500" x-text="formatRupiah(req.oldLimit)"></span>
                                <span class="material-symbols-rounded text-[16px] text-[#0052cc]">trending_flat</span>
                                <span class="text-[#0052cc] font-black" x-text="formatRupiah(req.newLimit)"></span>
                                <span class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10.5px]" x-text="'+' + formatRupiah(req.newLimit - req.oldLimit)"></span>
                            </div>
                        </div>

                        <!-- Reason & Action Row -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-xl bg-slate-50/70 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-rounded text-slate-400 text-[16px]">chat_bubble_outline</span>
                                <span class="text-slate-700 italic" x-text="'&ldquo;' + req.reason + '&rdquo;'"></span>
                            </div>

                            <div class="flex items-center gap-2 self-end sm:self-auto shrink-0">
                                <span class="px-2.5 py-1 rounded-full text-[10.5px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                    BLOCKED: Butuh Verifikasi Backend
                                </span>
                                <button type="button" 
                                        @click="openAuditModal(req)"
                                        class="px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors cursor-pointer inline-flex items-center gap-1">
                                    <span class="material-symbols-rounded text-[15px]">fact_check</span>
                                    <span>Periksa Berkas</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <template x-if="pendingApprovals.length === 0">
                    <div class="py-12 text-center text-slate-400">
                        <span class="material-symbols-rounded text-4xl mb-2 text-emerald-500 block">check_circle</span>
                        <h4 class="font-bold text-slate-800 text-sm">Semua Pengajuan Selesai</h4>
                        <p class="text-xs text-slate-400 mt-1">Saat ini tidak ada permintaan kenaikan plafon yang menunggu audit.</p>
                        <button type="button" 
                                @click="activeTab = 'daftar'"
                                class="mt-4 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors">
                            Kembali ke Daftar Anggota
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- TAB 3: ANALISIS BELANJA PER DEPARTEMEN -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'analisis'" class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-black text-slate-900 tracking-tight">Analisis Belanja per Departemen</h3>
            </div>
            <button type="button" 
                    @click="activeTab = 'daftar'"
                    class="px-3.5 py-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold inline-flex items-center gap-1.5 shadow-2xs transition-colors cursor-pointer">
                <span class="material-symbols-rounded text-[18px]">arrow_back</span>
                <span>Kembali ke Daftar Anggota</span>
            </button>
        </div>

        <!-- Department Table Card -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden">
            <div class="overflow-x-auto w-full">
                <table class="w-full text-left border-collapse" style="min-width: 800px;">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-[11px] font-extrabold uppercase text-slate-500 tracking-wider">
                            <th class="py-3.5 px-6">DEPARTEMEN / UNIT KERJA</th>
                            <th class="py-3.5 px-4 text-center">JUMLAH ANGGOTA</th>
                            <th class="py-3.5 px-4 text-right">TOTAL PLAFON</th>
                            <th class="py-3.5 px-4 text-right">PIUTANG BERJALAN</th>
                            <th class="py-3.5 px-6 text-left">UTILITAS KREDIT</th>
                            <th class="py-3.5 px-4 text-right">KONTRIBUSI SHU</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-[13px]">
                        <template x-for="dept in departmentSummary" :key="dept.name">
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="py-3.5 px-6">
                                    <div class="font-bold text-slate-900" x-text="dept.name"></div>
                                    <span class="text-[11px] text-slate-400" x-text="'Rata-rata limit: ' + formatRupiah(dept.avgPlafon) + '/org'"></span>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2.5 py-1 rounded-full bg-blue-50 text-[#0052cc] font-black text-xs" x-text="dept.memberCount + ' Orang'"></span>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-800" x-text="formatRupiah(dept.totalPlafon)"></td>
                                <td class="py-3.5 px-4 text-right font-mono font-bold text-amber-600" x-text="formatRupiah(dept.totalPiutang)"></td>
                                <td class="py-3.5 px-6">
                                    <div class="flex items-center justify-between text-xs mb-1 font-semibold">
                                        <span class="text-slate-700" x-text="dept.utilitas + '%'"></span>
                                        <span class="text-[10.5px]" :class="dept.utilitas > 80 ? 'text-amber-600' : 'text-emerald-600'" x-text="dept.utilitas > 80 ? 'Tinggi' : 'Normal'"></span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                        <div class="h-1.5 rounded-full" 
                                             :class="dept.utilitas > 80 ? 'bg-amber-500' : 'bg-[#0052cc]'"
                                             :style="'width: ' + Math.min(100, dept.utilitas) + '%'"></div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-bold text-[#0052cc]" x-text="formatRupiah(dept.totalShu)"></td>
                            </tr>
                        </template>
                        <template x-if="members.length === 0">
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <span class="material-symbols-rounded text-3xl mb-1 text-slate-300 block">group_off</span>
                                    <p class="font-bold text-sm text-slate-700">Belum ada data member.</p>
                                    <button type="button" @click="showRegisterModal = true" class="mt-3 px-4 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-xs font-bold inline-flex items-center gap-1.5 shadow-sm transition-colors cursor-pointer">
                                        <span class="material-symbols-rounded text-[16px]">person_add</span>
                                        <span>Registrasi Anggota Baru</span>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <template x-if="members.length > 0 && sortedFilteredMembers.length === 0">
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <span class="material-symbols-rounded text-3xl mb-1 text-slate-300 block">search_off</span>
                                    <p class="font-bold text-sm text-slate-700">Tidak ada anggota yang cocok dengan filter pencarian.</p>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <template x-if="departmentSummary.length === 0">
                <div class="py-12 text-center text-slate-400">
                    <span class="material-symbols-rounded text-4xl mb-2 text-slate-300 block">domain_disabled</span>
                    <h4 class="font-bold text-slate-800 text-sm">Data Departemen Belum Tersedia</h4>
                    <p class="text-xs text-slate-400 mt-1">Belum ada data anggota yang terdaftar dengan unit kerja atau departemen valid.</p>
                </div>
            </template>
        </div>
    </div>

    <!-- Modals -->
    <!-- Modal: Registrasi Anggota Baru -->
    <x-admin.modal show="showRegisterModal" title="Registrasi Anggota Koperasi Baru">
        <form @submit.prevent="saveNewMember()" class="space-y-4 text-[13px]">
            <div>
                <label class="block font-bold text-slate-700 mb-1">Nama Lengkap Anggota *</label>
                <input type="text" x-model="newMember.name" required placeholder="Contoh: Hendra Kurniawan" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-[#0052cc] outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Nomor Induk Karyawan (NIK) *</label>
                    <input type="text" x-model="newMember.nik" required placeholder="327501xxxxxx" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-[#0052cc] outline-none font-mono">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Departemen</label>
                    <select x-model="newMember.dept" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white focus:border-[#0052cc] outline-none">
                        <option value="Logistik & DC">Logistik &amp; DC</option>
                        <option value="Administrasi Pusat">Administrasi Pusat</option>
                        <option value="Divisi Maintenance">Divisi Maintenance</option>
                        <option value="Keuangan">Keuangan</option>
                        <option value="Fabrikasi Logam">Fabrikasi Logam</option>
                        <option value="Produksi & Plant">Produksi &amp; Plant</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Cabang Toko Belanja</label>
                    <select x-model="newMember.branch" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white focus:border-[#0052cc] outline-none">
                        <option value="Pusat">Pusat</option>
                        <option value="Cabang A">Cabang A</option>
                        <option value="Cabang B">Cabang B</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Plafon Kredit Bulanan (Rp)</label>
                    <input type="number" x-model.number="newMember.plafon" placeholder="5000000" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-[#0052cc] outline-none font-mono">
                </div>
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
                <button type="button" @click="showRegisterModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs shadow-sm">Simpan Anggota</button>
            </div>
        </form>
    </x-admin.modal>

    <!-- Modal: Detail Plafon & Riwayat Anggota -->
    <x-admin.modal show="showDetailModal" title="Detail Plafon &amp; Histori Anggota">
        <template x-if="selectedMember">
            <div class="space-y-4 text-[13px]">
                <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200">
                    <div class="w-12 h-12 rounded-2xl bg-blue-100 text-[#0052cc] flex items-center justify-center font-black text-base shrink-0"
                         x-text="selectedMember.name.substring(0, 2).toUpperCase()">
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-900" x-text="selectedMember.name"></h4>
                        <span class="text-xs text-slate-500" x-text="'NIK: ' + selectedMember.nik + ' • ' + selectedMember.dept"></span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <span class="text-xs text-slate-500 block">Plafon Bulanan</span>
                        <span class="text-base font-bold text-slate-900 mt-0.5 block font-mono" x-text="formatRupiah(selectedMember.plafon)"></span>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <span class="text-xs text-slate-500 block">Sisa Plafon Kasir</span>
                        <span class="text-base font-bold text-emerald-600 mt-0.5 block font-mono" x-text="formatRupiah(selectedMember.sisaPlafon)"></span>
                    </div>
                </div>

                <div class="p-3 bg-blue-50/50 rounded-xl border border-blue-200 text-xs text-slate-700 space-y-1">
                    <div class="font-bold text-[#0052cc]">Otorisasi &amp; Status Kasir:</div>
                    <p>Anggota dapat menggunakan kartu anggota / NIK untuk transaksi belanja kredit di kasir sampai batas limit yang tersedia.</p>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="button" @click="showDetailModal = false" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 font-bold text-xs">Tutup</button>
                </div>
            </div>
        </template>
    </x-admin.modal>

    <!-- Modal: Audit Pengajuan Kenaikan Plafon (Governance & BLOCKED Alert) -->
    <x-admin.modal show="showAuditModal" title="Audit Berkas Pengajuan Kenaikan Plafon">
        <template x-if="selectedAuditRequest">
            <div class="space-y-4 text-[13px]">
                <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200">
                    <div class="w-12 h-12 rounded-2xl bg-blue-100 text-[#0052cc] flex items-center justify-center font-black text-base shrink-0"
                         x-text="selectedAuditRequest.initials || 'AG'">
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-900" x-text="selectedAuditRequest.name"></h4>
                        <span class="text-xs text-slate-500" x-text="'NIK: ' + selectedAuditRequest.nik + ' • ' + selectedAuditRequest.dept"></span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <span class="text-xs text-slate-500 block">Plafon Eksisting</span>
                        <span class="text-base font-bold text-slate-900 mt-0.5 block font-mono" x-text="formatRupiah(selectedAuditRequest.oldLimit)"></span>
                    </div>
                    <div class="p-3 bg-blue-50/50 rounded-xl border border-blue-200">
                        <span class="text-xs text-blue-700 font-semibold block">Plafon Diajukan</span>
                        <span class="text-base font-bold text-[#0052cc] mt-0.5 block font-mono" x-text="formatRupiah(selectedAuditRequest.newLimit)"></span>
                    </div>
                </div>

                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs">
                    <span class="font-bold text-slate-700 block mb-1">Alasan Pengajuan Anggota:</span>
                    <p class="text-slate-600 italic" x-text="'&ldquo;' + selectedAuditRequest.reason + '&rdquo;'"></p>
                </div>

                <!-- Governance Callout -->
                <div class="p-3.5 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-900 space-y-2">
                    <div class="font-bold text-amber-900 flex items-center gap-1.5">
                        <span class="material-symbols-rounded text-[18px] text-amber-700">security</span>
                        <span>Status Prosedur: Approval Langsung Dibatasi (BLOCKED)</span>
                    </div>
                    <p class="text-[12px] text-amber-800 leading-relaxed">
                        Sesuai pedoman akuntansi Koperasi Kopkar ILP, pengesahan kenaikan limit membutuhkan verifikasi berkas fisik dan otorisasi komite pengurus cabang. Mutasi pagu kredit tidak dapat dilakukan sepihak dari frontend browser tanpa sinkronisasi backend core API.
                    </p>
                    <div class="pt-2 border-t border-amber-200/80 space-y-1 font-medium text-[11.5px]">
                        <div class="flex items-center gap-2">
                            <span class="text-emerald-600 font-bold">&#10003;</span>
                            <span>Data Anggota terdaftar valid di database koperasi</span>
                        </div>
                        <div class="flex items-center gap-2 text-slate-600">
                            <span class="text-amber-600 font-bold">&bull;</span>
                            <span>Verifikasi Slip Gaji 3 Bulan Terakhir: Menunggu penyerahan fisik</span>
                        </div>
                        <div class="flex items-center gap-2 text-slate-600">
                            <span class="text-amber-600 font-bold">&bull;</span>
                            <span>API Sync Server Pengurus: BLOCKED (Belum Terhubung)</span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="button" @click="showAuditModal = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">Tutup Audit</button>
                </div>
            </div>
        </template>
    </x-admin.modal>

</div>

<script>
function membersApp() {
    return {
        searchQuery: '',
        branchFilter: 'all',
        creditStatusFilter: 'all',
        deptFilter: 'all',
        activeTab: 'daftar', // 'daftar', 'permintaan', 'analisis'
        showRegisterModal: false,
        showDetailModal: false,
        showAuditModal: false,
        selectedAuditRequest: null,
        showSortMenu: false,
        showActionMemberId: null,
        selectedMember: null,
        currentPage: 1,
        pageSize: 6,
        sortBy: 'utilitas_desc', // 'utilitas_desc', 'piutang_desc', 'name_asc'

        members: [],

        defaultMembers: [],

        pendingApprovals: [],

        newMember: {
            name: '',
            nik: '',
            dept: 'Logistik & DC',
            branch: 'Pusat',
            plafon: 5000000
        },

        async initMembers() {
            try {
                const res = await fetch('/api/members?per_page=100');
                if (res.ok) {
                    const result = await res.json();
                    if (result.success && Array.isArray(result.data) && result.data.length > 0) {
                        this.members = result.data.map(m => {
                            const plafon = Number(m.credit_limit) || Number(m.plafon) || 5000000;
                            const piutang = Number(m.current_debt) || Number(m.piutang) || 0;
                            const util = plafon > 0 ? Math.min(100, Math.round((piutang / plafon) * 100)) : 0;
                            const sisa = Math.max(0, plafon - piutang);
                            const isLocked = m.status === 'DIBLOKIR' || m.status === 'DIBEKUKAN' || m.locked === true;
                            return {
                                _raw: m,
                                id: m.id,
                                name: m.name,
                                nik: m.nik,
                                phone: m.phone || '-',
                                dept: m.department || m.dept || 'Logistik & DC',
                                branch: m.branch || 'Pusat',
                                plafon: plafon,
                                piutang: piutang,
                                utilitas: util,
                                sisaPlafon: sisa,
                                shu: Number(m.shu) || 0,
                                trxCount: Number(m.trx_count) || 0,
                                status: isLocked ? 'DIBEKUKAN' : (util >= 90 ? 'HAMPIR LIMIT' : 'LANCAR'),
                                locked: isLocked
                            };
                        });
                        this.saveMembers();
                        this.loadStoredRequests();
                        return;
                    }
                }
            } catch (err) {
                console.warn('API /api/members error, loading local cache', err);
            }

            try {
                const raw = localStorage.getItem('pos_ilp_members_v1');
                if (raw) {
                    const parsed = JSON.parse(raw);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        this.members = parsed.map((m, idx) => {
                            const plafon = Number(m.creditLimit) || Number(m.plafon) || 5000000;
                            const piutang = Number(m.currentDebt) || Number(m.piutang) || 0;
                            const util = plafon > 0 ? Math.min(100, Math.round((piutang / plafon) * 100)) : 0;
                            const sisa = Math.max(0, plafon - piutang);
                            const isLocked = m.status === 'DIBLOKIR' || m.status === 'DIBEKUKAN' || m.locked === true;
                            return {
                                _raw: m,
                                id: m.id || ('MBR-00' + (idx + 1)),
                                name: m.name,
                                nik: m.nik || ('32750100' + idx),
                                phone: m.phone || ('0812-' + (9841 + idx) + '-2201'),
                                dept: m.dept || 'Logistik & DC',
                                branch: m.branch || (idx % 2 === 0 ? 'Pusat' : 'Cabang A'),
                                plafon: plafon,
                                piutang: piutang,
                                utilitas: util,
                                sisaPlafon: sisa,
                                shu: Number(m.shu) || 0,
                                trxCount: Number(m.trxCount) || 0,
                                status: isLocked ? 'DIBEKUKAN' : (util >= 90 ? 'HAMPIR LIMIT' : 'LANCAR'),
                                locked: isLocked
                            };
                        });
                        this.loadStoredRequests();
                        return;
                    }
                }
            } catch(e) {
                console.error('Failed to load pos_ilp_members_v1', e);
            }

            this.members = [];
            this.loadStoredRequests();
        },

        loadStoredRequests() {
            try {
                const reqRaw = localStorage.getItem('pos_ilp_credit_limit_requests_v1');
                if (reqRaw) {
                    const parsedReq = JSON.parse(reqRaw);
                    if (Array.isArray(parsedReq) && parsedReq.length > 0) {
                        this.pendingApprovals = parsedReq;
                        return;
                    }
                }
            } catch(e) {
                console.error('Failed to load pos_ilp_credit_limit_requests_v1', e);
            }
            this.pendingApprovals = [];
        },

        saveMembers() {
            try {
                const payload = this.members.map(m => {
                    const base = m._raw ? { ...m._raw } : {};
                    return {
                        ...base,
                        id: m.id,
                        nik: m.nik,
                        name: m.name,
                        phone: m.phone || base.phone || '',
                        dept: m.dept,
                        branch: m.branch,
                        creditLimit: m.plafon,
                        currentDebt: m.piutang,
                        status: m.locked ? 'DIBLOKIR' : 'AKTIF',
                        plafon: m.plafon,
                        piutang: m.piutang,
                        shu: m.shu,
                        trxCount: m.trxCount,
                        locked: m.locked
                    };
                });
                localStorage.setItem('pos_ilp_members_v1', JSON.stringify(payload));
            } catch(e) {
                console.error('Failed to save pos_ilp_members_v1', e);
            }
        },

        // Dynamic Calculations (Formula strictly audited: Deduplikasi NIK, Aktif Only, Pending Excluded)
        get totalPlafonDialokasikan() {
            const seenNiks = new Set();
            return this.members.reduce((sum, m) => {
                if (!m.nik || seenNiks.has(m.nik)) return sum;
                seenNiks.add(m.nik);
                if (m.locked || m.status === 'DIBEKUKAN' || m.status === 'DIBLOKIR') return sum;
                return sum + (Number(m.plafon) || 0);
            }, 0);
        },

        get totalPiutangBerjalan() {
            const seenNiks = new Set();
            return this.members.reduce((sum, m) => {
                if (!m.nik || seenNiks.has(m.nik)) return sum;
                seenNiks.add(m.nik);
                if (m.locked || m.status === 'DIBEKUKAN' || m.status === 'DIBLOKIR') return sum;
                return sum + (Number(m.piutang) || 0);
            }, 0);
        },

        get availablePlafonPercentage() {
            if (this.totalPlafonDialokasikan <= 0) return '100%';
            const avail = Math.max(0, this.totalPlafonDialokasikan - this.totalPiutangBerjalan);
            return ((avail / this.totalPlafonDialokasikan) * 100).toFixed(1) + '%';
        },

        get usedPlafonPercentage() {
            if (this.totalPlafonDialokasikan <= 0) return '0%';
            return ((this.totalPiutangBerjalan / this.totalPlafonDialokasikan) * 100).toFixed(1) + '%';
        },

        get activeMembersCount() {
            const seenNiks = new Set();
            return this.members.filter(m => {
                if (!m.nik || seenNiks.has(m.nik)) return false;
                seenNiks.add(m.nik);
                return !m.locked && m.status !== 'DIBLOKIR' && m.status !== 'DIBEKUKAN';
            }).length;
        },

        get activeMembersPercentage() {
            if (this.members.length === 0) return '100%';
            return Math.round((this.activeMembersCount / this.members.length) * 100) + '%';
        },

        get kolektibilitasRate() {
            if (this.members.length === 0) return '100%';
            const lancar = this.members.filter(m => m.status === 'LANCAR' || m.status === 'AKTIF').length;
            return ((lancar / this.members.length) * 100).toFixed(1) + '%';
        },

        get totalShu() {
            return this.members.reduce((sum, m) => sum + (Number(m.shu) || 0), 0);
        },

        get availableDepartments() {
            const depts = this.members.map(m => m.dept).filter(Boolean);
            return Array.from(new Set(depts));
        },

        get departmentSummary() {
            const seenNiks = new Set();
            const map = {};
            this.members.forEach(m => {
                if (!m.nik || seenNiks.has(m.nik)) return;
                seenNiks.add(m.nik);

                const deptName = m.dept || 'Umum';
                if (!map[deptName]) {
                    map[deptName] = {
                        name: deptName,
                        memberCount: 0,
                        totalPlafon: 0,
                        totalPiutang: 0,
                        totalShu: 0
                    };
                }
                map[deptName].memberCount++;
                if (!m.locked && m.status !== 'DIBEKUKAN' && m.status !== 'DIBLOKIR') {
                    map[deptName].totalPlafon += (Number(m.plafon) || 0);
                }
                map[deptName].totalPiutang += (Number(m.piutang) || 0);
                map[deptName].totalShu += (Number(m.shu) || 0);
            });

            return Object.values(map).map(d => ({
                ...d,
                avgPlafon: d.memberCount > 0 ? Math.round(d.totalPlafon / d.memberCount) : 0,
                utilitas: d.totalPlafon > 0 ? Math.round((d.totalPiutang / d.totalPlafon) * 100) : 0
            }));
        },

        get filteredMembers() {
            return this.members.filter(m => {
                const query = this.searchQuery.toLowerCase();
                const matchSearch = m.name.toLowerCase().includes(query) ||
                                    m.nik.includes(query) ||
                                    m.dept.toLowerCase().includes(query);
                const matchBranch = this.branchFilter === 'all' || m.branch.toLowerCase().includes(this.branchFilter.toLowerCase());
                const matchStatus = this.creditStatusFilter === 'all' || m.status.toLowerCase() === this.creditStatusFilter.toLowerCase();
                const matchDept = this.deptFilter === 'all' || m.dept.toLowerCase().includes(this.deptFilter.toLowerCase());
                return matchSearch && matchBranch && matchStatus && matchDept;
            });
        },

        get sortedFilteredMembers() {
            const list = [...this.filteredMembers];
            if (this.sortBy === 'utilitas_desc') {
                list.sort((a,b) => (b.utilitas || 0) - (a.utilitas || 0));
            } else if (this.sortBy === 'piutang_desc') {
                list.sort((a,b) => (b.piutang || 0) - (a.piutang || 0));
            } else if (this.sortBy === 'name_asc') {
                list.sort((a,b) => a.name.localeCompare(b.name));
            }
            return list;
        },

        get totalPages() {
            return Math.max(1, Math.ceil(this.sortedFilteredMembers.length / this.pageSize));
        },

        get paginatedMembers() {
            const start = (this.currentPage - 1) * this.pageSize;
            return this.sortedFilteredMembers.slice(start, start + this.pageSize);
        },

        async saveNewMember() {
            if (!this.newMember.name || !this.newMember.nik) {
                if (typeof triggerToast === 'function') triggerToast('Harap lengkapi Nama dan NIK anggota!', 'error');
                return;
            }
            const plafon = Number(this.newMember.plafon) || 5000000;

            try {
                const res = await fetch('/api/members', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        name: this.newMember.name,
                        nik: this.newMember.nik,
                        department: this.newMember.dept,
                        branch: this.newMember.branch,
                        credit_limit: plafon
                    })
                });
                if (res.ok) {
                    if (typeof triggerToast === 'function') triggerToast('Anggota baru berhasil diregistrasi ke sistem koperasi!', 'success');
                    this.showRegisterModal = false;
                    this.newMember = { name: '', nik: '', dept: 'Logistik & DC', branch: 'Pusat', plafon: 5000000 };
                    await this.initMembers();
                    return;
                }
            } catch (err) {
                console.warn('API error, saving locally', err);
            }

            this.members.unshift({
                id: 'MBR-00' + (this.members.length + 1),
                name: this.newMember.name,
                nik: this.newMember.nik,
                dept: this.newMember.dept,
                branch: this.newMember.branch,
                plafon: plafon,
                piutang: 0,
                utilitas: 0,
                sisaPlafon: plafon,
                shu: 0,
                trxCount: 0,
                status: 'LANCAR',
                locked: false
            });
            this.saveMembers();
            this.showRegisterModal = false;
            if (typeof triggerToast === 'function') triggerToast('Anggota baru berhasil diregistrasi!', 'success');
            this.newMember = { name: '', nik: '', dept: 'Logistik & DC', branch: 'Pusat', plafon: 5000000 };
        },

        async unlockMember(member) {
            member.locked = false;
            member.status = 'LANCAR';
            if (typeof member.id === 'number') {
                try {
                    await fetch(`/api/members/${member.id}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ status: 'AKTIF' })
                    });
                } catch(e){}
            }
            this.saveMembers();
            if (typeof triggerToast === 'function') triggerToast('Blokir plafon anggota ' + member.name + ' berhasil dibuka!', 'success');
        },

        async toggleLockMember(member) {
            member.locked = !member.locked;
            member.status = member.locked ? 'DIBEKUKAN' : 'LANCAR';
            if (typeof member.id === 'number') {
                try {
                    await fetch(`/api/members/${member.id}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ status: member.locked ? 'DIBLOKIR' : 'AKTIF' })
                    });
                } catch(e){}
            }
            this.saveMembers();
            this.showActionMemberId = null;
            if (typeof triggerToast === 'function') triggerToast('Status kredit anggota ' + member.name + ' diubah ke: ' + member.status, 'info');
        },

        openAuditModal(req) {
            this.selectedAuditRequest = req;
            this.showAuditModal = true;
        },

        requestLimitIncrease(member) {
            const existingReq = this.pendingApprovals.find(r => r.name.toLowerCase() === member.name.toLowerCase());
            if (existingReq) {
                if (typeof triggerToast === 'function') triggerToast('Pengajuan limit untuk ' + member.name + ' sudah ada di daftar audit.', 'info');
            } else {
                const newReq = {
                    id: 'REQ-0' + (this.pendingApprovals.length + 1),
                    name: member.name,
                    initials: member.name.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase(),
                    nik: member.nik,
                    dept: member.dept,
                    tenure: 'Masa Kerja: Aktif',
                    oldLimit: member.plafon,
                    newLimit: member.plafon + 2000000,
                    reason: 'Pengajuan eskalasi limit khusus anggota via Owner Console',
                    status: 'MENUNGGU_VERIFIKASI_BERKAS'
                };
                this.pendingApprovals.unshift(newReq);
                try {
                    localStorage.setItem('pos_ilp_credit_limit_requests_v1', JSON.stringify(this.pendingApprovals));
                } catch(e){}
                if (typeof triggerToast === 'function') triggerToast('Pengajuan dicatat ke tab Permintaan (Status: Menunggu Audit Cabang).', 'info');
            }
            this.showActionMemberId = null;
        },

        formatRupiah(num) {
            return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
        },

        exportRekapPiutang() {
            try {
                const headers = ['ID Anggota', 'NIK Karyawan', 'Nama Lengkap', 'No Telepon', 'Departemen', 'Cabang Belanja', 'Plafon Kredit (Rp)', 'Piutang Berjalan (Rp)', 'Utilitas (%)', 'Sisa Plafon (Rp)', 'Status Kredit'];
                const rows = this.members.map(m => [
                    m.id,
                    String(m.nik),
                    m.name,
                    m.phone || '-',
                    m.dept,
                    m.branch || 'Pusat',
                    Number(m.plafon || 0),
                    Number(m.piutang || 0),
                    (m.utilitas || 0) + '%',
                    Number(m.sisaPlafon || 0),
                    m.status
                ]);

                const totalPlafon = this.members.reduce((sum, m) => sum + Number(m.plafon || 0), 0);
                const totalPiutang = this.members.reduce((sum, m) => sum + Number(m.piutang || 0), 0);
                const totalSisa = this.members.reduce((sum, m) => sum + Number(m.sisaPlafon || 0), 0);
                const totals = ['TOTAL', '', '', '', '', '', totalPlafon, totalPiutang, '', totalSisa, ''];

                window.exportTableToExcel({
                    filename: 'rekap_piutang_anggota_ilp_' + new Date().toISOString().slice(0, 10) + '.xlsx',
                    sheetName: 'Rekap Piutang',
                    title: 'PT INDO LAMPUNG PERKASA - MINIMARKET KOPERASI',
                    subtitle: 'LAPORAN REKAPITULASI PIUTANG KASBON ANGGOTA KOPERASI',
                    metadata: [
                        ['Tanggal Generate', new Date().toLocaleString('id-ID')],
                        ['Total Anggota', this.members.length + ' Orang']
                    ],
                    headers: headers,
                    rows: rows,
                    totals: totals
                });

                if (typeof triggerToast === 'function') triggerToast('File Excel rekap piutang anggota berhasil diunduh.', 'success');
            } catch(e) {
                if (typeof triggerToast === 'function') triggerToast('Gagal ekspor rekap piutang: ' + e.message, 'error');
            }
        }
    };
}
</script>
@endsection
