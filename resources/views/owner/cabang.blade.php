@extends('layouts.owner-admin', ['active' => 'cabang'])

@section('title', 'Manajemen Cabang & Gudang - ILP POS')

@section('header')
<x-admin.header title="Manajemen Cabang &amp; Gudang" searchPlaceholder="Cari cabang atau penanggung jawab..." searchModel="searchQuery">
    <button type="button" 
            @click="window.dispatchEvent(new CustomEvent('open-add-branch-modal'))"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-[13px] font-bold shadow-sm shadow-blue-600/20 transition-all cursor-pointer">
        <span class="material-symbols-rounded text-[18px]">add</span>
        <span>Tambah Cabang</span>
    </button>
</x-admin.header>
@endsection

@section('admin_content')
<div x-data="cabangApp()" 
     x-init="init()"
     @open-add-branch-modal.window="showAddBranchModal = true" 
     @ilp-search.window="searchQuery = $event.detail" 
     class="space-y-6">

    <!-- Top 4 Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Cabang Toko Ritel -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs relative overflow-hidden flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">CABANG TOKO RITEL</span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-50 text-[#0052cc]" x-text="warehouseCount + ' Gudang'"></span>
            </div>
            <div class="mt-2 text-3xl font-black text-[#0052cc] tracking-tight">
                <span x-text="storeCount">0</span> <span class="text-xs font-bold text-slate-500 font-sans">Gerai Toko</span>
            </div>
            <span class="text-[11px] text-slate-500 font-medium mt-1">Total unit cabang ritel</span>
            <div class="absolute -right-3 -bottom-3 text-slate-100 pointer-events-none">
                <span class="material-symbols-rounded text-[75px] opacity-70">storefront</span>
            </div>
        </div>

        <!-- 2. Cabang Aktif -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs relative overflow-hidden flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">CABANG TOKO AKTIF</span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">Operasional</span>
            </div>
            <div class="mt-2 text-3xl font-black text-emerald-600 tracking-tight">
                <span x-text="storeActiveCount">0</span> <span class="text-xs font-bold text-slate-500 font-sans">dari <span x-text="storeCount">0</span> Toko</span>
            </div>
            <span class="text-[11px] text-slate-500 font-medium mt-1">Status operasional aktif</span>
            <div class="absolute -right-3 -bottom-3 text-emerald-50 pointer-events-none">
                <span class="material-symbols-rounded text-[75px] opacity-70">verified</span>
            </div>
        </div>

        <!-- 3. Total Stok Master -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs relative overflow-hidden flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">TOTAL STOK MASTER</span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">Konsolidasi</span>
            </div>
            <div class="mt-2 text-3xl font-black text-slate-900 tracking-tight">
                <span x-text="totalStokMaster.toLocaleString('id-ID')">0</span> <span class="text-sm font-bold text-slate-500">unit</span>
            </div>
            <span class="text-[11px] text-slate-500 font-medium mt-1">Konsolidasi seluruh gerai &amp; gudang</span>
            <div class="absolute -right-3 -bottom-3 text-slate-100 pointer-events-none">
                <span class="material-symbols-rounded text-[75px] opacity-70">inventory</span>
            </div>
        </div>

        <!-- 4. Profitabilitas AVG -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs relative overflow-hidden flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">PROFITABILITAS AVG</span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">Margin Ritel</span>
            </div>
            <div class="mt-2 text-3xl font-black text-slate-800 tracking-tight">
                <span x-text="avgProfitability">0%</span>
            </div>
            <span class="text-[11px] text-slate-500 font-medium mt-1">Rata-rata margin ritel</span>
            <div class="absolute -right-3 -bottom-3 text-blue-50 pointer-events-none">
                <span class="material-symbols-rounded text-[75px] opacity-70">trending_up</span>
            </div>
        </div>
    </div>

    <!-- Main Content: Split Grid (Left: Table 60%, Right: Branch Detail & Map 40%) -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
        
        <!-- Left: Daftar Cabang & Gudang Table (xl:col-span-7) -->
        <div class="xl:col-span-7">
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden">
                <!-- Table Header & Action Controls -->
                <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 tracking-tight">Daftar Cabang &amp; Gudang</h3>
                    </div>

                    <div class="flex items-center gap-2 relative">
                        <!-- Filter Button -->
                        <div class="relative">
                            <button type="button" 
                                    @click="showFilterDropdown = !showFilterDropdown"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-[12.5px] font-bold transition-colors">
                                <span class="material-symbols-rounded text-[16px]">filter_list</span>
                                <span>Filter Tipe/Status</span>
                            </button>
                            <!-- Dropdown -->
                            <div x-show="showFilterDropdown" 
                                 @click.away="showFilterDropdown = false"
                                 x-cloak
                                 class="absolute right-0 mt-2 w-52 rounded-xl bg-white border border-slate-200 shadow-lg py-2 z-20 text-[12.5px] space-y-1">
                                <div class="px-3 py-1 text-[10.5px] font-extrabold uppercase text-slate-400">Tipe Fasilitas</div>
                                <button type="button" @click="typeFilter = 'all'; showFilterDropdown = false" class="w-full text-left px-3.5 py-1 hover:bg-slate-50 font-medium" :class="typeFilter === 'all' ? 'text-[#0052cc] font-bold' : 'text-slate-700'">Semua Lokasi</button>
                                <button type="button" @click="typeFilter = 'store'; showFilterDropdown = false" class="w-full text-left px-3.5 py-1 hover:bg-slate-50 font-medium" :class="typeFilter === 'store' ? 'text-[#0052cc] font-bold' : 'text-slate-700'">Cabang Toko Saja</button>
                                <button type="button" @click="typeFilter = 'warehouse'; showFilterDropdown = false" class="w-full text-left px-3.5 py-1 hover:bg-slate-50 font-medium" :class="typeFilter === 'warehouse' ? 'text-[#0052cc] font-bold' : 'text-slate-700'">Gudang Logistik Saja</button>
                                <div class="border-t border-slate-100 my-1"></div>
                                <div class="px-3 py-1 text-[10.5px] font-extrabold uppercase text-slate-400">Status Operasional</div>
                                <button type="button" @click="statusFilter = 'all'; showFilterDropdown = false" class="w-full text-left px-3.5 py-1 hover:bg-slate-50 font-medium" :class="statusFilter === 'all' ? 'text-[#0052cc] font-bold' : 'text-slate-700'">Semua Status</button>
                                <button type="button" @click="statusFilter = 'aktif'; showFilterDropdown = false" class="w-full text-left px-3.5 py-1 hover:bg-slate-50 font-medium" :class="statusFilter === 'aktif' ? 'text-[#0052cc] font-bold' : 'text-slate-700'">Aktif</button>
                                <button type="button" @click="statusFilter = 'maintenance'; showFilterDropdown = false" class="w-full text-left px-3.5 py-1 hover:bg-slate-50 font-medium" :class="statusFilter === 'maintenance' ? 'text-[#0052cc] font-bold' : 'text-slate-700'">Maintenance</button>
                            </div>
                        </div>

                        <!-- Ekspor Button -->
                        <button type="button" 
                                @click="exportData()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-[12.5px] font-bold transition-colors">
                            <span class="material-symbols-rounded text-[16px]">download</span>
                            <span>Ekspor</span>
                        </button>
                    </div>
                </div>

                <!-- Table Content (Horizontal scroll wrapper with min-width) -->
                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left border-collapse" style="min-width: 580px;">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/70 text-[11px] font-extrabold uppercase text-slate-500 tracking-wider">
                                <th class="py-3 px-5">Nama Lokasi</th>
                                <th class="py-3 px-4">Manager / PJ</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-[13px]">
                            <template x-for="b in filteredBranches" :key="b.id">
                                <tr @click="selectBranch(b.id)" 
                                    class="transition-colors cursor-pointer"
                                    :class="selectedBranchId === b.id ? 'bg-blue-50/70 font-semibold' : 'hover:bg-slate-50/80'">
                                    <!-- Nama Cabang (Hanya Pusat, Cabang A, Cabang B, Gudang Pusat - Tanpa Label Geografis) -->
                                    <td class="py-3.5 px-5">
                                        <div class="flex flex-col">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-slate-900" x-text="b.name"></span>
                                                <span class="text-[10px] px-1.5 py-0.2 rounded font-extrabold uppercase"
                                                      :class="b.facilityType === 'warehouse' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-blue-50 text-[#0052cc] border border-blue-200'"
                                                      x-text="b.facilityType === 'warehouse' ? 'Gudang' : 'Toko'">
                                                </span>
                                            </div>
                                            <span class="text-[11.5px] text-slate-500 truncate max-w-[240px]" x-text="b.address"></span>
                                        </div>
                                    </td>

                                    <!-- Manager -->
                                    <td class="py-3.5 px-4 text-slate-700 font-medium" x-text="b.manager"></td>

                                    <!-- Status Pill -->
                                    <td class="py-3.5 px-4">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10.5px] font-extrabold uppercase tracking-wide border inline-block"
                                              :class="{
                                                  'bg-emerald-50 text-emerald-700 border-emerald-200': b.status === 'AKTIF',
                                                  'bg-amber-50 text-amber-700 border-amber-200': b.status === 'MAINTENANCE',
                                                  'bg-rose-50 text-rose-700 border-rose-200': b.status === 'TUTUP'
                                              }"
                                              x-text="b.status">
                                        </span>
                                    </td>

                                    <!-- Aksi Buttons -->
                                    <td class="py-3.5 px-5 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1" @click.stop>
                                            <button type="button" 
                                                    @click="openEditModal(b)"
                                                    class="p-1.5 rounded-lg text-slate-400 hover:text-[#0052cc] hover:bg-blue-50 transition-colors"
                                                    title="Edit Lokasi">
                                                <span class="material-symbols-rounded text-[18px]">edit</span>
                                            </button>
                                            <button type="button" 
                                                    @click="selectBranch(b.id); triggerToast('Menampilkan data ' + b.name)"
                                                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-800 hover:bg-slate-100 transition-colors"
                                                    title="Pilih Titik di Peta &amp; Detail">
                                                <span class="material-symbols-rounded text-[18px]">pin_drop</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="branches.length === 0">
                                <tr>
                                    <td colspan="4" class="py-12 text-center text-slate-400">
                                        <span class="material-symbols-rounded text-3xl mb-1 text-slate-300 block">storefront</span>
                                        <p class="font-bold text-sm text-slate-700">Belum ada cabang atau gudang terdaftar.</p>
                                        <button type="button" @click="showAddBranchModal = true" class="mt-3 px-4 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-xs font-bold inline-flex items-center gap-1.5 shadow-sm transition-colors cursor-pointer">
                                            <span class="material-symbols-rounded text-[16px]">add</span>
                                            <span>Tambah Cabang</span>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="branches.length > 0 && filteredBranches.length === 0">
                                <tr>
                                    <td colspan="4" class="py-12 text-center text-slate-400">
                                        <span class="material-symbols-rounded text-3xl mb-1 text-slate-300 block">search_off</span>
                                        <p class="font-bold text-sm text-slate-700">Tidak ada cabang yang cocok dengan filter pencarian.</p>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Pagination -->
                <div class="px-6 py-3 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between text-xs text-slate-500">
                    <div>
                        Menampilkan <span class="font-bold text-slate-800" x-text="filteredBranches.length">4</span> fasilitas operasional terdaftar
                    </div>
                    <span class="font-bold text-[#0052cc]">Konsistensi Multi-Lokasi Aktif</span>
                </div>
            </div>
        </div>

        <!-- Right: Branch Detail, Stylized Map (4 Titik) & Inventory/Sales Trend (xl:col-span-5) -->
        <div class="xl:col-span-5 space-y-4">
            
            <template x-if="branches.length === 0">
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-8 text-center text-slate-400">
                    <span class="material-symbols-rounded text-4xl mb-2 text-slate-300 block">pin_drop</span>
                    <h4 class="font-bold text-slate-800 text-sm">Belum Ada Lokasi Terpilih</h4>
                    <p class="text-xs text-slate-400 mt-1">Tambahkan cabang atau gudang untuk melihat detail lokasi.</p>
                </div>
            </template>

            <!-- Detail Card -->
            <template x-if="branches.length > 0 && selectedBranch && selectedBranch.id">
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-5 space-y-4">
                <!-- Location Header -->
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h4 class="text-base font-extrabold text-slate-900 tracking-tight" x-text="selectedBranch.name"></h4>
                            <span class="text-[10px] px-2 py-0.5 rounded font-bold uppercase"
                                  :class="selectedBranch.facilityType === 'warehouse' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-blue-50 text-[#0052cc] border border-blue-200'"
                                  x-text="selectedBranch.typeName">
                            </span>
                        </div>
                        <p class="text-[12px] text-slate-500 mt-0.5 flex items-center gap-1">
                            <span class="material-symbols-rounded text-[14px] text-slate-400 shrink-0">pin_drop</span>
                            <span x-text="selectedBranch.address"></span>
                        </p>
                    </div>
                    <div class="w-8 h-8 rounded-full bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                        <span class="material-symbols-rounded text-[20px]" x-text="selectedBranch.facilityType === 'warehouse' ? 'warehouse' : 'storefront'"></span>
                    </div>
                </div>

                <!-- Stylized Illustrative Map with 4 Interactive Points (Pusat, Cabang A, Cabang B, Gudang Pusat) -->
                <div class="relative w-full h-44 rounded-xl overflow-hidden border border-slate-200 bg-[#e8eef5] shadow-inner select-none">
                    <!-- SVG Background Map -->
                    <svg class="w-full h-full object-cover" viewBox="0 0 400 170" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="400" height="170" fill="#E8EEF5"/>
                        <!-- Roads & River lines -->
                        <path d="M-10 40 Q 120 45 200 90 T 410 110" stroke="#FFFFFF" stroke-width="12" stroke-linecap="round"/>
                        <path d="M150 -10 L 220 180" stroke="#FFFFFF" stroke-width="8"/>
                        <path d="M60 -10 L 80 180" stroke="#FFFFFF" stroke-width="6"/>
                        <path d="M280 -10 L 320 180" stroke="#FFFFFF" stroke-width="7"/>
                        <path d="M-10 130 L 410 140" stroke="#FFFFFF" stroke-width="6"/>
                        <path d="M-10 15 Q 160 30 240 10 T 410 35" stroke="#BFDBFE" stroke-width="14" stroke-linecap="round"/>
                        <!-- Buildings Blocks -->
                        <rect x="90" y="55" width="45" height="30" rx="3" fill="#D1DCE8"/>
                        <rect x="235" y="40" width="35" height="40" rx="3" fill="#D1DCE8"/>
                        <rect x="170" y="105" width="40" height="25" rx="3" fill="#D1DCE8"/>
                        <rect x="15" y="60" width="35" height="35" rx="3" fill="#D1DCE8"/>
                        <rect x="290" y="95" width="40" height="30" rx="3" fill="#D1DCE8"/>

                        <!-- TITIK 1: Pusat (x: 135, y: 70) -->
                        <g class="cursor-pointer transition-transform" 
                           @click="selectBranch('CAB-001')"
                           style="transform-origin: 135px 70px;">
                            <circle cx="135" cy="70" r="14" 
                                    :fill="selectedBranchId === 'CAB-001' ? '#0052cc' : '#3b82f6'" 
                                    :fill-opacity="selectedBranchId === 'CAB-001' ? '0.35' : '0.15'"/>
                            <circle cx="135" cy="70" r="7" 
                                    :fill="selectedBranchId === 'CAB-001' ? '#0052cc' : '#3b82f6'" 
                                    stroke="#FFFFFF" stroke-width="2"/>
                            <circle cx="135" cy="70" r="2.5" fill="#FFFFFF"/>
                            <!-- Label tag -->
                            <rect x="110" y="44" width="50" height="18" rx="4" fill="#0f172a" fill-opacity="0.85"/>
                            <text x="135" y="56" fill="#FFFFFF" font-size="9" font-family="sans-serif" font-weight="bold" text-anchor="middle">Pusat</text>
                        </g>

                        <!-- TITIK 2: Cabang A (x: 255, y: 50) -->
                        <g class="cursor-pointer transition-transform" 
                           @click="selectBranch('CAB-002')"
                           style="transform-origin: 255px 50px;">
                            <circle cx="255" cy="50" r="14" 
                                    :fill="selectedBranchId === 'CAB-002' ? '#0052cc' : '#3b82f6'" 
                                    :fill-opacity="selectedBranchId === 'CAB-002' ? '0.35' : '0.15'"/>
                            <circle cx="255" cy="50" r="7" 
                                    :fill="selectedBranchId === 'CAB-002' ? '#0052cc' : '#3b82f6'" 
                                    stroke="#FFFFFF" stroke-width="2"/>
                            <circle cx="255" cy="50" r="2.5" fill="#FFFFFF"/>
                            <!-- Label tag -->
                            <rect x="225" y="24" width="60" height="18" rx="4" fill="#0f172a" fill-opacity="0.85"/>
                            <text x="255" y="36" fill="#FFFFFF" font-size="9" font-family="sans-serif" font-weight="bold" text-anchor="middle">Cabang A</text>
                        </g>

                        <!-- TITIK 3: Cabang B (x: 320, y: 110) -->
                        <g class="cursor-pointer transition-transform" 
                           @click="selectBranch('CAB-003')"
                           style="transform-origin: 320px 110px;">
                            <circle cx="320" cy="110" r="14" 
                                    :fill="selectedBranchId === 'CAB-003' ? '#d97706' : '#f59e0b'" 
                                    :fill-opacity="selectedBranchId === 'CAB-003' ? '0.35' : '0.15'"/>
                            <circle cx="320" cy="110" r="7" 
                                    :fill="selectedBranchId === 'CAB-003' ? '#d97706' : '#f59e0b'" 
                                    stroke="#FFFFFF" stroke-width="2"/>
                            <circle cx="320" cy="110" r="2.5" fill="#FFFFFF"/>
                            <!-- Label tag -->
                            <rect x="290" y="84" width="60" height="18" rx="4" fill="#0f172a" fill-opacity="0.85"/>
                            <text x="320" y="96" fill="#FFFFFF" font-size="9" font-family="sans-serif" font-weight="bold" text-anchor="middle">Cabang B</text>
                        </g>

                        <!-- TITIK 4: Gudang Pusat (x: 70, y: 115) -->
                        <g class="cursor-pointer transition-transform" 
                           @click="selectBranch('GDG-001')"
                           style="transform-origin: 70px 115px;">
                            <circle cx="70" cy="115" r="15" 
                                    :fill="selectedBranchId === 'GDG-001' ? '#4338ca' : '#6366f1'" 
                                    :fill-opacity="selectedBranchId === 'GDG-001' ? '0.35' : '0.15'"/>
                            <circle cx="70" cy="115" r="7.5" 
                                    :fill="selectedBranchId === 'GDG-001' ? '#4338ca' : '#6366f1'" 
                                    stroke="#FFFFFF" stroke-width="2"/>
                            <circle cx="70" cy="115" r="2.5" fill="#FFFFFF"/>
                            <!-- Label tag -->
                            <rect x="35" y="89" width="70" height="18" rx="4" fill="#1e1b4b" fill-opacity="0.9"/>
                            <text x="70" y="101" fill="#FFFFFF" font-size="8.5" font-family="sans-serif" font-weight="bold" text-anchor="middle">Gudang Pusat</text>
                        </g>
                    </svg>

                    <!-- Hint Overlay -->
                    <div class="absolute bottom-2 left-2 px-2 py-0.5 rounded bg-white/85 text-[10px] font-bold text-slate-600 border border-slate-200">
                        Klik titik pada peta untuk memilih lokasi
                    </div>
                </div>

                <!-- Manager Contact Card -->
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/70 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-[#0052cc] text-white flex items-center justify-center font-bold text-xs shadow-2xs"
                             x-text="(selectedBranch.manager || 'PJ').substring(0, 2).toUpperCase()">
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[13px] font-bold text-slate-900 leading-tight" x-text="selectedBranch.manager"></span>
                            <span class="text-[11px] text-slate-500" x-text="selectedBranch.managerRole"></span>
                        </div>
                    </div>
                    <a :href="'mailto:' + (selectedBranch.managerEmail || 'manager@ilp-pos.co.id')" 
                       class="p-2 rounded-lg text-slate-500 hover:text-[#0052cc] hover:bg-blue-50 transition-colors"
                       title="Kirim Email">
                        <span class="material-symbols-rounded text-[18px]">mail</span>
                    </a>
                </div>

                <!-- ========================================================================= -->
                <!-- KONDISIONAL: JIKA GUDANG PUSAT DIPILIH (Bagian E: Detail Gudang Pusat)     -->
                <!-- ========================================================================= -->
                <template x-if="selectedBranch.facilityType === 'warehouse'">
                    <div class="space-y-3 pt-2 border-t border-slate-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <h5 class="text-[11px] font-black uppercase tracking-wider text-indigo-700">
                                    RINGKASAN STOK MASTER GUDANG PUSAT
                                </h5>
                                <p class="text-[11px] text-slate-500">10 item stok terendah di gudang distribusi utama (diurutkan paling kritis)</p>
                            </div>
                            <a href="{{ url('/owner/products?filter=low_stock') }}" class="text-[12px] font-bold text-[#0052cc] hover:underline">Kelola Master</a>
                        </div>

                        <!-- Top 10 Lowest Stock Items Table (Strictly <= 10 items) -->
                        <div class="border border-slate-200 rounded-xl overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-extrabold uppercase text-slate-500">
                                    <tr>
                                        <th class="py-2 px-2.5">No</th>
                                        <th class="py-2 px-2.5">SKU &amp; Produk</th>
                                        <th class="py-2 px-2.5 text-right">Stok Fisik</th>
                                        <th class="py-2 px-2.5 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(item, idx) in warehouseLowestItems" :key="item.sku">
                                        <tr class="hover:bg-slate-50/70">
                                            <td class="py-2 px-2.5 font-bold text-slate-400" x-text="idx + 1"></td>
                                            <td class="py-2 px-2.5">
                                                <div class="font-bold text-slate-800 leading-tight" x-text="item.name"></div>
                                                <span class="text-[10px] font-mono text-slate-400" x-text="item.sku"></span>
                                            </td>
                                            <td class="py-2 px-2.5 text-right font-mono font-bold"
                                                :class="item.stock <= 0 ? 'text-rose-600' : 'text-amber-600'"
                                                x-text="item.stock + ' ' + item.unit"></td>
                                            <td class="py-2 px-2.5 text-center">
                                                <span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold uppercase"
                                                      :class="item.stock <= 0 ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-amber-50 text-amber-700 border border-amber-200'"
                                                      x-text="item.status"></span>
                                            </td>
                                        </tr>
                                    </template>
                                    <template x-if="warehouseLowestItems.length === 0">
                                        <tr>
                                            <td colspan="4" class="py-6 text-center text-slate-400">
                                                <p class="text-xs">Belum ada data stok produk gudang.</p>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>

                <!-- ========================================================================= -->
                <!-- KONDISIONAL: JIKA CABANG TOKO DIPILIH (Bagian D: Detail Cabang Toko)       -->
                <!-- ========================================================================= -->
                <template x-if="selectedBranch.facilityType === 'store'">
                    <div class="space-y-3 pt-2 border-t border-slate-100">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <h5 class="text-[11px] font-black uppercase tracking-wider text-[#0052cc]">
                                    TREN PRODUK PALING BANYAK DIBELI &bull; <span x-text="selectedBranch.name.toUpperCase()"></span>
                                </h5>
                                <p class="text-[11px] text-slate-500">Berdasarkan data transaksi penjualan valid khusus cabang ini</p>
                            </div>
                            <!-- Period Filter for Branch Trend -->
                            <div class="flex items-center gap-1.5 self-start sm:self-auto">
                                <span class="text-[10.5px] font-semibold text-slate-400">Periode:</span>
                                <select x-model="branchTrendPeriod" 
                                        class="px-2 py-1 rounded-lg border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 outline-none cursor-pointer">
                                    <option value="today">Hari Ini</option>
                                    <option value="7days">7 Hari Terakhir</option>
                                    <option value="month">Bulan Berjalan</option>
                                </select>
                            </div>
                        </div>

                        <!-- Top Selling Products List for this Selected Branch -->
                        <template x-if="currentBranchTopProducts.length > 0">
                            <div class="space-y-2">
                                <template x-for="(p, pIdx) in currentBranchTopProducts" :key="p.id">
                                    <div class="p-2.5 rounded-xl border border-slate-100 bg-slate-50/60 flex items-center justify-between gap-3 text-xs">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <span class="w-5 h-5 rounded-md bg-[#0052cc] text-white text-[10px] font-bold flex items-center justify-center shrink-0" x-text="pIdx + 1"></span>
                                            <div class="truncate">
                                                <div class="font-bold text-slate-800 truncate" x-text="p.name"></div>
                                                <div class="text-[10.5px] text-slate-400" x-text="p.sku + ' • Terjual: ' + p.qtySold + ' ' + (p.unit || 'Unit')"></div>
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="font-mono font-bold text-[#0052cc] block" x-text="'Rp ' + Number(p.revenue || 0).toLocaleString('id-ID')"></span>
                                            <span class="text-[9.5px] text-slate-400 block" x-text="'Periode ' + (branchTrendPeriod === 'today' ? 'Hari Ini' : (branchTrendPeriod === '7days' ? '7 Hari' : 'Bulan Ini'))"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Empty State if no sales data for this branch -->
                        <template x-if="currentBranchTopProducts.length === 0">
                            <div class="p-6 text-center text-xs text-slate-400 border border-dashed border-slate-200 rounded-xl">
                                <span class="material-symbols-rounded text-2xl text-slate-300 block mb-1">shopping_basket</span>
                                Belum ada data penjualan tercatat untuk cabang ini.
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Actions Button -->
                <div class="pt-2 border-t border-slate-100 flex items-center gap-2">
                    <button type="button" 
                            @click="openEditModal(selectedBranch)"
                            class="flex-1 py-2 px-3 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-xs font-bold transition-all text-center">
                        Edit Informasi Lokasi
                    </button>
                    <a href="{{ url('/owner/products') }}" class="py-2 px-3 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold transition-colors">
                        Lihat Stok
                    </a>
                </div>
            </div>
            </template>

        </div>
    </div>

    <!-- Modals -->
    <!-- Modal: Tambah Cabang / Gudang Baru -->
    <x-admin.modal show="showAddBranchModal" title="Tambah Cabang Toko / Gudang Baru">
        <form @submit.prevent="saveNewBranch()" class="space-y-4">
            <div>
                <label class="block text-[12.5px] font-bold text-slate-700 mb-1">Nama Lokasi Operasional *</label>
                <input type="text" x-model="newBranch.name" required placeholder="Contoh: Cabang C" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] focus:border-[#0052cc] outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[12.5px] font-bold text-slate-700 mb-1">Tipe Lokasi</label>
                    <select x-model="newBranch.facilityType" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] bg-white focus:border-[#0052cc] outline-none">
                        <option value="store">Cabang Toko Ritel</option>
                        <option value="warehouse">Gudang Logistik / DC</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[12.5px] font-bold text-slate-700 mb-1">Status Operasional</label>
                    <select x-model="newBranch.status" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] bg-white focus:border-[#0052cc] outline-none">
                        <option value="AKTIF">Aktif</option>
                        <option value="MAINTENANCE">Maintenance</option>
                        <option value="TUTUP">Tutup</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-[12.5px] font-bold text-slate-700 mb-1">Alamat Lengkap</label>
                <input type="text" x-model="newBranch.address" placeholder="Contoh: Jl. Diponegoro No. 88" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] focus:border-[#0052cc] outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[12.5px] font-bold text-slate-700 mb-1">Penanggung Jawab (PJ) *</label>
                    <input type="text" x-model="newBranch.manager" required placeholder="Nama Manager / PJ" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] focus:border-[#0052cc] outline-none">
                </div>
                <div>
                    <label class="block text-[12.5px] font-bold text-slate-700 mb-1">Email Penanggung Jawab</label>
                    <input type="email" x-model="newBranch.managerEmail" placeholder="pj@ilp-pos.co.id" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] focus:border-[#0052cc] outline-none">
                </div>
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
                <button type="button" @click="showAddBranchModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold text-[12.5px]">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-[12.5px]">Daftarkan Lokasi</button>
            </div>
        </form>
    </x-admin.modal>

    <!-- Modal: Edit Cabang / Gudang -->
    <x-admin.modal show="showEditBranchModal" title="Ubah Informasi Lokasi Operasional">
        <form @submit.prevent="saveEditBranch()" class="space-y-4">
            <div>
                <label class="block text-[12.5px] font-bold text-slate-700 mb-1">Nama Lokasi</label>
                <input type="text" x-model="editingBranch.name" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] focus:border-[#0052cc] outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[12.5px] font-bold text-slate-700 mb-1">Penanggung Jawab</label>
                    <input type="text" x-model="editingBranch.manager" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] focus:border-[#0052cc] outline-none">
                </div>
                <div>
                    <label class="block text-[12.5px] font-bold text-slate-700 mb-1">Status Operasional</label>
                    <select x-model="editingBranch.status" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] bg-white focus:border-[#0052cc] outline-none">
                        <option value="AKTIF">Aktif</option>
                        <option value="MAINTENANCE">Maintenance</option>
                        <option value="TUTUP">Tutup</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-[12.5px] font-bold text-slate-700 mb-1">Alamat</label>
                <input type="text" x-model="editingBranch.address" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] focus:border-[#0052cc] outline-none">
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
                <button type="button" @click="showEditBranchModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold text-[12.5px]">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-[12.5px]">Simpan Perubahan</button>
            </div>
        </form>
    </x-admin.modal>
</div>

<script>
function cabangApp() {
    return {
        searchQuery: '',
        statusFilter: 'all',
        typeFilter: 'all',
        showAddBranchModal: false,
        showEditBranchModal: false,
        showFilterDropdown: false,
        selectedBranchId: null,
        branchTrendPeriod: '7days',

        defaultBranches: [],
        warehouseLowestItems: [],

        branches: [],
        newBranch: { name: '', facilityType: 'store', address: '', manager: '', managerEmail: '', status: 'AKTIF' },
        editingBranch: {},

        init() {
            this.initBranches();
        },

        initBranches() {
            try {
                const raw = localStorage.getItem('pos_ilp_branches_v1');
                if (raw) {
                    const parsed = JSON.parse(raw);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        this.branches = parsed;
                        this.selectedBranchId = this.branches[0].id;
                        this.loadWarehouseLowest();
                        return;
                    }
                }
            } catch(e) {}
            this.branches = [];
            this.selectedBranchId = null;
            this.loadWarehouseLowest();
        },

        loadWarehouseLowest() {
            try {
                const prodsRaw = localStorage.getItem('pos_ilp_inventory_products_v1');
                if (prodsRaw) {
                    const prods = JSON.parse(prodsRaw);
                    if (Array.isArray(prods) && prods.length > 0) {
                        this.warehouseLowestItems = prods.slice()
                            .sort((a,b) => (Number(a.stock)||0) - (Number(b.stock)||0))
                            .slice(0, 10)
                            .map((p, idx) => ({
                                rank: idx + 1,
                                sku: p.sku || ('SKU-' + (idx+1)),
                                name: p.name,
                                stock: Number(p.stock) || 0,
                                minStock: Number(p.minStock) || 10,
                                unit: p.unit || 'Pcs',
                                status: (Number(p.stock)||0) <= 0 ? 'Habis' : ((Number(p.stock)||0) <= (Number(p.minStock)||10) ? 'Kritis' : 'Menipis')
                            }));
                        return;
                    }
                }
            } catch(e) {}
            this.warehouseLowestItems = [];
        },

        saveBranches() {
            try {
                localStorage.setItem('pos_ilp_branches_v1', JSON.stringify(this.branches));
            } catch(e) {}
        },

        selectBranch(id) {
            this.selectedBranchId = id;
        },

        get storeCount() {
            return this.branches.filter(b => b.facilityType === 'store').length;
        },

        get storeActiveCount() {
            return this.branches.filter(b => b.facilityType === 'store' && b.status === 'AKTIF').length;
        },

        get warehouseCount() {
            return this.branches.filter(b => b.facilityType === 'warehouse').length;
        },

        get totalStokMaster() {
            try {
                const raw = localStorage.getItem('pos_ilp_inventory_products_v1');
                if (raw) {
                    const list = JSON.parse(raw);
                    if (Array.isArray(list)) {
                        return list.reduce((sum, p) => sum + (Number(p.stock) || 0), 0);
                    }
                }
            } catch(e) {}
            return 0;
        },

        get avgProfitability() {
            return '0%';
        },
            return this.branches.filter(b => b.facilityType === 'store' && b.status === 'AKTIF').length;
        },

        get selectedBranch() {
            return this.branches.find(b => b.id === this.selectedBranchId) || this.branches[0] || {};
        },

        get currentBranchTopProducts() {
            // Read valid branch specific top products
            if (!this.selectedBranch || this.selectedBranch.facilityType === 'warehouse') return [];
            return this.selectedBranch.topProducts || [];
        },

        get filteredBranches() {
            return this.branches.filter(b => {
                const matchSearch = b.name.toLowerCase().includes(this.searchQuery.toLowerCase()) || 
                                    b.manager.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                                    (b.address && b.address.toLowerCase().includes(this.searchQuery.toLowerCase()));
                const matchStatus = this.statusFilter === 'all' || b.status.toLowerCase() === this.statusFilter.toLowerCase();
                const matchType = this.typeFilter === 'all' || b.facilityType === this.typeFilter;
                return matchSearch && matchStatus && matchType;
            });
        },

        saveNewBranch() {
            if (!this.newBranch.name || !this.newBranch.manager) {
                if (typeof triggerToast === 'function') triggerToast('Harap lengkapi nama lokasi dan penanggung jawab!', 'error');
                return;
            }
            const isStore = this.newBranch.facilityType === 'store';
            const newId = (isStore ? 'CAB-00' : 'GDG-00') + (this.branches.length + 1);
            const item = {
                id: newId,
                name: this.newBranch.name,
                facilityType: this.newBranch.facilityType,
                typeName: isStore ? 'Gerai Ritel Toko' : 'Gudang Logistik',
                address: this.newBranch.address || 'Alamat Lokasi Baru',
                manager: this.newBranch.manager,
                managerRole: isStore ? 'Manager Cabang' : 'Supervisor Gudang',
                managerEmail: this.newBranch.managerEmail || 'manager@ilp-pos.co.id',
                status: this.newBranch.status,
                totalSku: '500',
                stockOut: 0,
                topProducts: []
            };
            this.branches.push(item);
            this.saveBranches();
            this.selectedBranchId = newId;
            this.showAddBranchModal = false;
            if (typeof triggerToast === 'function') triggerToast('Lokasi operasional ' + item.name + ' berhasil didaftarkan!', 'success');
            this.newBranch = { name: '', facilityType: 'store', address: '', manager: '', managerEmail: '', status: 'AKTIF' };
        },

        openEditModal(branch) {
            this.editingBranch = JSON.parse(JSON.stringify(branch));
            this.showEditBranchModal = true;
        },

        saveEditBranch() {
            if (!this.editingBranch.name || !this.editingBranch.manager) {
                if (typeof triggerToast === 'function') triggerToast('Nama lokasi dan PJ tidak boleh kosong!', 'error');
                return;
            }
            const idx = this.branches.findIndex(b => b.id === this.editingBranch.id);
            if (idx !== -1) {
                this.branches[idx] = { ...this.branches[idx], ...this.editingBranch };
                this.saveBranches();
                this.showEditBranchModal = false;
                if (typeof triggerToast === 'function') triggerToast('Perubahan lokasi ' + this.editingBranch.name + ' berhasil disimpan!', 'success');
            }
        },

        exportData() {
            try {
                const headers = ['ID Lokasi', 'Nama Lokasi', 'Tipe', 'Alamat', 'Manager', 'Email Manager', 'Status', 'Total SKU'];
                const rows = this.branches.map(b => [
                    '"' + b.id + '"',
                    '"' + b.name + '"',
                    '"' + b.typeName + '"',
                    '"' + (b.address || '').replace(/"/g, '""') + '"',
                    '"' + b.manager + '"',
                    '"' + b.managerEmail + '"',
                    '"' + b.status + '"',
                    '"' + b.totalSku + '"'
                ]);
                const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
                const encodedUri = encodeURI(csvContent);
                const link = document.createElement('a');
                link.setAttribute('href', encodedUri);
                link.setAttribute('download', 'rekap_cabang_ilp_' + new Date().toISOString().slice(0, 10) + '.csv');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                if (typeof triggerToast === 'function') triggerToast('File rekap_cabang_ilp.csv berhasil diunduh.', 'success');
            } catch(e) {
                if (typeof triggerToast === 'function') triggerToast('Gagal mengekspor data: ' + e.message, 'error');
            }
        }
    };
}
</script>
@endsection
