@extends('layouts.app')

@section('title', 'Pilih Cabang & Terminal Kasir - ILP POS')

@section('content')
<div class="bg-[#f8f9fa] min-h-screen w-full flex flex-col text-gray-800 font-sans overflow-y-auto"
     x-data="cashierOpeningData()"
     x-init="initOpening()">

    <!-- Topbar -->
    <header class="h-14 bg-white border-b border-gray-200 flex items-center justify-between px-6 shrink-0 sticky top-0 z-20">
        <div class="flex items-center gap-4">
            <a href="{{ url('/') }}" data-no-spa class="flex items-center gap-2 text-[#0052cc] hover:opacity-80 transition-opacity">
                <span class="material-symbols-rounded text-[22px]">store</span>
                <span class="font-black tracking-wider text-sm">ILP POS</span>
            </a>
            <div class="h-4 w-px bg-gray-300"></div>
            <span class="font-mono text-gray-500 text-xs sm:text-sm tracking-wider" x-text="(selectedBranch || 'Pusat').toUpperCase() + ' • ' + formatShiftLabel(selectedShift).toUpperCase()">PUSAT • POS-01</span>
        </div>
        <div class="flex items-center gap-5 text-sm">
            <div class="flex flex-col text-right leading-tight">
                <span class="text-gray-500 font-medium text-xs" x-text="businessDayDate">19 Oktober 2026</span>
                <span class="text-[#0052cc] font-mono font-bold text-xs">OPERASIONAL OPEN</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-rounded text-green-500 text-[20px]">wifi</span>
                <span class="text-xs font-bold text-green-700 bg-green-50 border border-green-200 px-2.5 py-1 rounded-full">Online</span>
            </div>
        </div>
    </header>

    <!-- Main Content Grid Layout (4 cols left : 8 cols right on desktop) -->
    <main class="flex-1 w-full max-w-[1280px] mx-auto p-6 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- Left Column: Identifikasi Petugas & Riwayat (4 Cols) -->
        <div class="lg:col-span-4 flex flex-col gap-6 w-full">
            <!-- Card 1: Identifikasi Kasir Terautentikasi (Berasal dari Akun Portal) -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 flex flex-col gap-5">
                <div class="flex items-center gap-3.5 pb-2 border-b border-gray-100">
                    <div class="w-12 h-12 bg-blue-50 text-[#0052cc] rounded-full flex items-center justify-center shrink-0">
                        <span class="material-symbols-rounded text-2xl">verified_user</span>
                    </div>
                    <div>
                        <h2 class="text-gray-900 font-black text-base">Identitas Petugas</h2>
                        <p class="text-gray-500 text-xs mt-0.5">Akun portal terautentikasi resmi</p>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <!-- Petugas Terautentikasi (Terkunci dari Portal, Bukan Dropdown Bebas) -->
                    <div class="bg-blue-50/50 border border-blue-200/80 rounded-xl p-3.5 flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-extrabold text-[#0052cc] uppercase tracking-wider flex items-center gap-1">
                                <span class="material-symbols-rounded text-[14px]">lock</span>
                                KASIR RESMI
                            </span>
                            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full bg-blue-100 text-[#0052cc]" x-text="'NIK: ' + (selectedStaffObj.nik || '-')"></span>
                        </div>
                        <div class="text-base font-black text-gray-900" x-text="selectedStaffObj.name"></div>
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span class="font-mono text-[11px]" x-text="'@' + (selectedStaffObj.username || selectedStaffObj.id)"></span>
                            <span class="font-bold text-gray-600" x-text="selectedStaffObj.role || 'Kasir Operasional'"></span>
                        </div>
                    </div>

                    <!-- Penugasan Cabang Terdaftar -->
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-3.5 flex flex-col gap-2">
                        <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-wider">DAFTAR PENUGASAN AKTIF</span>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="ab in (selectedStaffObj.assignedBranches || [{ branch: selectedStaffObj.branch, status: 'AKTIF' }])" :key="ab.branch">
                                <span class="text-[10px] font-bold px-2.5 py-1 rounded-md flex items-center gap-1"
                                      :class="(ab.status || 'AKTIF') === 'AKTIF' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-gray-200 text-gray-600'">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="(ab.status || 'AKTIF') === 'AKTIF' ? 'bg-emerald-600' : 'bg-gray-400'"></span>
                                    <span x-text="ab.branch"></span>
                                </span>
                            </template>
                        </div>
                    </div>

                    <!-- Status Cabang Terpilih Saat Ini -->
                    <div class="rounded-xl p-3.5 border flex items-center justify-between"
                         :class="isStaffAssignedToBranch(selectedStaffObj, selectedBranch) ? 'bg-emerald-50/70 border-emerald-200' : 'bg-red-50/70 border-red-200'">
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider block"
                                  :class="isStaffAssignedToBranch(selectedStaffObj, selectedBranch) ? 'text-emerald-800' : 'text-red-800'">
                                STATUS CABANG (<span x-text="selectedBranch"></span>)
                            </span>
                            <span class="text-xs font-bold"
                                  :class="isStaffAssignedToBranch(selectedStaffObj, selectedBranch) ? 'text-emerald-900' : 'text-red-700'"
                                  x-text="isStaffAssignedToBranch(selectedStaffObj, selectedBranch) ? 'Aktif & Diizinkan Bertugas' : 'Belum Memiliki Penugasan'">
                            </span>
                        </div>
                        <span class="material-symbols-rounded text-xl"
                              :class="isStaffAssignedToBranch(selectedStaffObj, selectedBranch) ? 'text-emerald-600' : 'text-red-500'"
                              x-text="isStaffAssignedToBranch(selectedStaffObj, selectedBranch) ? 'check_circle' : 'block'">
                        </span>
                    </div>

                    <!-- Logout / Kembali ke Portal -->
                    <a href="{{ url('/login-kasir') }}" data-no-spa
                       class="mt-1 text-center py-2 px-3 text-xs font-bold text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-xl border border-gray-200 transition-colors flex items-center justify-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-rounded text-[16px]">logout</span>
                        <span>Keluar / Ganti Akun Portal</span>
                    </a>
                </div>
            </div>

            <!-- Card 2: Riwayat Sesi Terminal Hari Ini -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 flex flex-col gap-4">
                <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                    <h3 class="text-xs font-black text-gray-800 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="material-symbols-rounded text-[18px] text-[#0052cc]">history</span>
                        <span>Riwayat <span x-text="formatShiftLabel(selectedShift)"></span></span>
                    </h3>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-50 text-[#0052cc]" x-text="selectedTerminalSessionsToday.length + ' Sesi'"></span>
                </div>

                <div class="space-y-2.5 max-h-[300px] overflow-y-auto pr-1">
                    <template x-if="selectedTerminalSessionsToday.length === 0">
                        <div class="p-4 rounded-xl bg-gray-50 border border-dashed border-gray-200 text-center text-xs text-gray-400">
                            Belum ada riwayat sesi pada terminal ini hari ini.
                        </div>
                    </template>
                    <template x-for="sess in selectedTerminalSessionsToday" :key="sess.sessionId">
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-gray-200/80 text-xs space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-gray-900" x-text="sess.cashierName"></span>
                                <span class="font-mono text-[10px] font-bold text-gray-500" x-text="sess.startedAt + ' – ' + (sess.endedAt || 'Sekarang')"></span>
                            </div>
                            <div class="flex items-center justify-between text-[11px] pt-1.5 border-t border-gray-200/60">
                                <span class="text-gray-500">Fisik: <strong class="text-gray-800 font-mono" x-text="formatRupiah(sess.actualClosingCash)"></strong></span>
                                <span class="font-mono font-bold"
                                      :class="sess.cashVariance === 0 ? 'text-emerald-600' : 'text-red-600'"
                                      x-text="sess.cashVariance === 0 ? 'Sesuai' : formatRupiah(sess.cashVariance)"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Right Column: Banner, Pemilihan Cabang, & Terminal Kasir (8 Cols) -->
        <div class="lg:col-span-8 flex flex-col gap-6 w-full">
            <!-- Welcome Banner -->
            <div class="bg-[#0052cc] rounded-2xl py-6 px-8 relative overflow-hidden flex items-center justify-between gap-4 shadow-sm">
                <div class="absolute inset-0 opacity-20 mix-blend-overlay" style="background-image: url('https://images.unsplash.com/photo-1578916171728-46686eac8d58?q=80&w=1000&auto=format&fit=crop'); background-size: cover; background-position: center;"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-[#0052cc] via-[#0052cc]/85 to-transparent"></div>
                
                <div class="relative z-10 text-white">
                    <h2 class="text-xl font-black mb-1">Selamat Datang, <span x-text="selectedStaffObj.name"></span></h2>
                    <p class="text-blue-100 text-xs sm:text-sm">Silakan pilih cabang penugasan &amp; terminal nomor kasir yang akan digunakan</p>
                </div>

                <div class="relative z-10 hidden sm:block bg-white/15 backdrop-blur-md border border-white/25 rounded-xl px-4 py-2.5 text-white text-right shrink-0">
                    <div class="text-[10px] font-bold text-blue-100 uppercase" x-text="selectedBranch">Pusat</div>
                    <div class="text-base font-black" x-text="formatShiftLabel(selectedShift)">POS-01</div>
                </div>
            </div>

            <!-- TAHAP 1: PILIH CABANG PENUGASAN (Pusat | Cabang A | Cabang B) -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 flex flex-col gap-4">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                    <div>
                        <h3 class="text-gray-900 font-black text-base flex items-center gap-2">
                            <span class="material-symbols-rounded text-[#0052cc]">storefront</span>
                            <span>Pilih Cabang Operasional</span>
                        </h3>
                        <p class="text-gray-500 text-xs mt-0.5">Daftar cabang mengikuti penugasan aktif Anda dari Manager</p>
                    </div>
                    <span class="text-xs font-mono font-bold text-[#0052cc] bg-blue-50 px-3 py-1 rounded-full border border-blue-200" x-text="'Cabang Aktif: ' + selectedBranch"></span>
                </div>

                <!-- 3 Branch Selection Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <!-- 1. Pusat -->
                    <button type="button" @click="selectBranch('Pusat')"
                            :class="{
                                'border-[#0052cc] bg-blue-50/70 ring-2 ring-[#0052cc]/20 shadow-xs': selectedBranch === 'Pusat',
                                'border-gray-200 bg-white hover:border-gray-300': selectedBranch !== 'Pusat'
                            }"
                            class="p-4 rounded-xl border-[1.5px] text-left transition-all cursor-pointer flex flex-col justify-between h-28 relative">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black text-gray-900">Minimarket Pusat</span>
                            <template x-if="isStaffAssignedToBranch(selectedStaffObj, 'Pusat')">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500" title="Penugasan Aktif"></span>
                            </template>
                            <template x-if="!isStaffAssignedToBranch(selectedStaffObj, 'Pusat')">
                                <span class="material-symbols-rounded text-gray-300 text-[18px]" title="Tidak Ada Penugasan">lock</span>
                            </template>
                        </div>
                        <div class="text-[11px] text-gray-500 font-medium">Terminal POS-01 s/d POS-05</div>
                        <div class="text-[10.5px] font-bold"
                             :class="isStaffAssignedToBranch(selectedStaffObj, 'Pusat') ? 'text-emerald-700' : 'text-gray-400'"
                             x-text="isStaffAssignedToBranch(selectedStaffObj, 'Pusat') ? '✓ Penugasan Aktif' : '✗ Tidak Terdaftar'">
                        </div>
                    </button>

                    <!-- 2. Cabang A -->
                    <button type="button" @click="selectBranch('Cabang A')"
                            :class="{
                                'border-[#0052cc] bg-blue-50/70 ring-2 ring-[#0052cc]/20 shadow-xs': selectedBranch === 'Cabang A',
                                'border-gray-200 bg-white hover:border-gray-300': selectedBranch !== 'Cabang A'
                            }"
                            class="p-4 rounded-xl border-[1.5px] text-left transition-all cursor-pointer flex flex-col justify-between h-28 relative">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black text-gray-900">Cabang A</span>
                            <template x-if="isStaffAssignedToBranch(selectedStaffObj, 'Cabang A')">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500" title="Penugasan Aktif"></span>
                            </template>
                            <template x-if="!isStaffAssignedToBranch(selectedStaffObj, 'Cabang A')">
                                <span class="material-symbols-rounded text-gray-300 text-[18px]" title="Tidak Ada Penugasan">lock</span>
                            </template>
                        </div>
                        <div class="text-[11px] text-gray-500 font-medium">Terminal POS-06 s/d POS-10</div>
                        <div class="text-[10.5px] font-bold"
                             :class="isStaffAssignedToBranch(selectedStaffObj, 'Cabang A') ? 'text-emerald-700' : 'text-gray-400'"
                             x-text="isStaffAssignedToBranch(selectedStaffObj, 'Cabang A') ? '✓ Penugasan Aktif' : '✗ Tidak Terdaftar'">
                        </div>
                    </button>

                    <!-- 3. Cabang B -->
                    <button type="button" @click="selectBranch('Cabang B')"
                            :class="{
                                'border-[#0052cc] bg-blue-50/70 ring-2 ring-[#0052cc]/20 shadow-xs': selectedBranch === 'Cabang B',
                                'border-gray-200 bg-white hover:border-gray-300': selectedBranch !== 'Cabang B'
                            }"
                            class="p-4 rounded-xl border-[1.5px] text-left transition-all cursor-pointer flex flex-col justify-between h-28 relative">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black text-gray-900">Cabang B</span>
                            <template x-if="isStaffAssignedToBranch(selectedStaffObj, 'Cabang B')">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500" title="Penugasan Aktif"></span>
                            </template>
                            <template x-if="!isStaffAssignedToBranch(selectedStaffObj, 'Cabang B')">
                                <span class="material-symbols-rounded text-gray-300 text-[18px]" title="Tidak Ada Penugasan">lock</span>
                            </template>
                        </div>
                        <div class="text-[11px] text-gray-500 font-medium">Terminal POS-11 s/d POS-15</div>
                        <div class="text-[10.5px] font-bold"
                             :class="isStaffAssignedToBranch(selectedStaffObj, 'Cabang B') ? 'text-emerald-700' : 'text-gray-400'"
                             x-text="isStaffAssignedToBranch(selectedStaffObj, 'Cabang B') ? '✓ Penugasan Aktif' : '✗ Tidak Terdaftar'">
                        </div>
                    </button>
                </div>

                <!-- Alert Blokir Jika Kasir Tidak Memiliki Penugasan pada Cabang Terpilih -->
                <div x-show="!isStaffAssignedToBranch(selectedStaffObj, selectedBranch)"
                     class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs flex items-start gap-3">
                    <span class="material-symbols-rounded text-red-600 text-[24px] shrink-0">block</span>
                    <div class="flex-1">
                        <h4 class="font-bold text-red-900 text-sm">Akses Cabang Belum Ditugaskan</h4>
                        <p class="mt-1 leading-relaxed">
                            Akun kasir Anda (<strong x-text="selectedStaffObj.name"></strong>) belum memiliki penugasan resmi aktif pada cabang <strong x-text="selectedBranch"></strong>.
                            Sistem tidak mengizinkan pembukaan sesi atau penggunaan terminal di cabang ini.
                        </p>
                        <p class="mt-1.5 text-[11px] text-red-700 font-bold flex items-center gap-1">
                            <span class="material-symbols-rounded text-[15px]">info</span>
                            <span>Silakan hubungi Manager Koperasi untuk mendaftarkan atau mengaktifkan penugasan cabang pada akun Anda.</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- TAHAP 2: PILIH NOMOR TERMINAL SESUAI CABANG -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 flex flex-col gap-5">
                <!-- Header + Filter Tabs -->
                <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-gray-100">
                    <div>
                        <h3 class="text-gray-900 font-black text-base flex items-center gap-2">
                            <span class="material-symbols-rounded text-[#0052cc]">point_of_sale</span>
                            <span>Pilih Terminal Kasir (<span x-text="selectedBranch"></span>)</span>
                        </h3>
                        <p class="text-gray-500 text-xs mt-0.5" x-text="'Daftar terminal resmi yang berlokasi di ' + selectedBranch"></p>
                    </div>

                    <div class="flex flex-wrap bg-gray-100 p-1 rounded-xl text-xs font-bold">
                        <button type="button" @click="terminalFilter = 'all'"
                                :class="terminalFilter === 'all' ? 'bg-white text-[#0052cc] shadow-xs' : 'text-gray-600 hover:text-gray-900'"
                                class="px-3 py-1.5 rounded-lg transition-all cursor-pointer">Semua</button>
                        <button type="button" @click="terminalFilter = 'handover'"
                                :class="terminalFilter === 'handover' ? 'bg-white text-amber-700 shadow-xs' : 'text-gray-600 hover:text-gray-900'"
                                class="px-3 py-1.5 rounded-lg transition-all cursor-pointer">Serah Terima</button>
                        <button type="button" @click="terminalFilter = 'available'"
                                :class="terminalFilter === 'available' ? 'bg-white text-emerald-700 shadow-xs' : 'text-gray-600 hover:text-gray-900'"
                                class="px-3 py-1.5 rounded-lg transition-all cursor-pointer">Kosong</button>
                        <button type="button" @click="terminalFilter = 'active'"
                                :class="terminalFilter === 'active' ? 'bg-white text-red-700 shadow-xs' : 'text-gray-600 hover:text-gray-900'"
                                class="px-3 py-1.5 rounded-lg transition-all cursor-pointer">Aktif</button>
                    </div>
                </div>

                <!-- Alert Notifikasi Terminal Nonaktif -->
                <div x-show="terminalInactiveNotice" class="p-3.5 rounded-xl bg-gray-100 border border-gray-300 text-gray-800 text-xs font-bold flex items-center gap-2">
                    <span class="material-symbols-rounded text-[20px] text-gray-500">build</span>
                    <span x-text="terminalInactiveNotice"></span>
                </div>

                <!-- Grid of Terminals for Selected Branch -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5"
                     :class="!isStaffAssignedToBranch(selectedStaffObj, selectedBranch) ? 'opacity-40 pointer-events-none' : ''">
                    <!-- Fallback / Empty State -->
                    <template x-if="filteredTerminals.length === 0">
                        <div class="col-span-full p-8 rounded-xl bg-gray-50 border border-dashed border-gray-300 text-center flex flex-col items-center justify-center gap-2">
                            <span class="material-symbols-rounded text-gray-400 text-3xl">point_of_sale</span>
                            <div class="text-sm font-bold text-gray-700">
                                <span x-show="terminalFilter !== 'all'">Tidak ada terminal dengan filter <strong x-text="terminalFilter"></strong> di <span x-text="selectedBranch"></span></span>
                                <span x-show="terminalFilter === 'all'">Memuat daftar terminal resmi di <span x-text="selectedBranch"></span>...</span>
                            </div>
                            <div class="flex items-center gap-2 mt-1">
                                <button x-show="terminalFilter !== 'all'" type="button" @click="terminalFilter = 'all'" class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-xs font-bold transition-colors cursor-pointer">
                                    <span class="material-symbols-rounded text-sm">filter_alt_off</span>
                                    <span>Reset Filter Tab</span>
                                </button>
                                <button type="button" @click="terminals = getUnifiedTerminals(); localStorage.setItem('pos_ilp_terminals_v1', JSON.stringify(terminals)); selectBranch(selectedBranch);" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#0052cc] text-white rounded-lg text-xs font-bold shadow-xs hover:bg-[#0040a8] cursor-pointer">
                                    <span class="material-symbols-rounded text-sm">refresh</span>
                                    <span>Sinkronkan Terminal Resmi</span>
                                </button>
                            </div>
                        </div>
                    </template>

                    <template x-for="term in filteredTerminals" :key="term.id">
                        <div @click="selectTerminalCard(term)"
                             @dblclick="if (term.status === 'ACTIVE_MINE') resumeActiveSession()"
                             :class="{
                                 'border-gray-300 bg-gray-100 opacity-70': term.isActive === false,
                                 'border-red-400 bg-red-50/50 ring-2 ring-red-300/60': term.isActive !== false && selectedShift === term.id && term.status === 'ACTIVE_OTHER',
                                 'border-[#0052cc] bg-[#eef2ff] ring-2 ring-[#0052cc]/20': term.isActive !== false && selectedShift === term.id && term.status !== 'ACTIVE_OTHER',
                                 'border-gray-200 bg-gray-50/70 hover:border-gray-300 opacity-90': term.isActive !== false && selectedShift !== term.id && term.status === 'ACTIVE_OTHER',
                                 'border-gray-200 hover:border-blue-300 bg-white': term.isActive !== false && selectedShift !== term.id && term.status !== 'ACTIVE_OTHER'
                             }"
                             class="p-4 rounded-xl border-[1.5px] cursor-pointer transition-all flex items-center justify-between gap-3 shadow-2xs">
                            
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-black text-gray-900" x-text="term.label"></span>
                                    <span class="text-[11px] text-gray-400 font-medium" x-text="'(' + (term.sessionsTodayCount || 0) + ' sesi)'"></span>
                                </div>
                                <div class="text-xs text-gray-500 truncate mt-1">
                                    <template x-if="term.isActive === false">
                                        <span class="text-gray-500 font-semibold italic flex items-center gap-1">
                                            <span class="material-symbols-rounded text-[14px]">warning</span>
                                            <span x-text="term.inactiveReason || 'Terminal Nonaktif'"></span>
                                        </span>
                                    </template>
                                    <template x-if="term.isActive !== false && term.status === 'HANDOVER_PENDING' && term.handover">
                                        <span>Operan <strong class="text-gray-700" x-text="term.handover.fromCashierName"></strong>: <strong class="font-mono text-emerald-700 font-bold" x-text="formatRupiah(term.handover.actualCash)"></strong></span>
                                    </template>
                                    <template x-if="term.isActive !== false && term.status === 'AVAILABLE'">
                                        <span>Siap dibuka • Modal Rp 500.000</span>
                                    </template>
                                    <template x-if="term.isActive !== false && term.status === 'ACTIVE_OTHER'">
                                        <span class="text-red-600 font-semibold flex items-center gap-1">
                                            <span>Aktif: <strong x-text="term.activeCashierName || 'Petugas Lain'"></strong></span>
                                        </span>
                                    </template>
                                    <template x-if="term.isActive !== false && term.status === 'ACTIVE_MINE'">
                                        <span class="text-[#0052cc] font-semibold flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-[#0052cc] animate-pulse"></span>
                                            <span>Sesi Anda sedang aktif</span>
                                        </span>
                                    </template>
                                </div>
                            </div>

                            <!-- Status Badge -->
                            <div class="shrink-0">
                                <span x-show="term.isActive === false" class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-gray-200 text-gray-700 border border-gray-300 flex items-center gap-1">
                                    <span class="material-symbols-rounded text-[13px]">block</span>
                                    NONAKTIF
                                </span>
                                <span x-show="term.isActive !== false && term.status === 'HANDOVER_PENDING'" class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-800 border border-amber-300">
                                    SERAH TERIMA
                                </span>
                                <span x-show="term.isActive !== false && term.status === 'AVAILABLE'" class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    KOSONG
                                </span>
                                <span x-show="term.isActive !== false && term.status === 'ACTIVE_OTHER'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-red-100 text-red-700 border border-red-200">
                                    <span class="material-symbols-rounded text-[13px]">lock</span>
                                    DIPAKAI
                                </span>
                                <span x-show="term.isActive !== false && term.status === 'ACTIVE_MINE'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-blue-100 text-[#0052cc] border border-blue-200">
                                    <span class="material-symbols-rounded text-[13px]">play_circle</span>
                                    AKTIF
                                </span>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Action Button Bottom Container -->
                <div class="pt-4 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <!-- Warning Kasir DIPAKAI Orang Lain -->
                        <div x-show="isStaffAssignedToBranch(selectedStaffObj, selectedBranch) && selectedTerminalObj && selectedTerminalObj.isActive !== false && selectedTerminalObj.status === 'ACTIVE_OTHER'" 
                             class="text-xs text-red-700 font-medium flex items-center gap-1.5 bg-red-50 px-3 py-2 rounded-lg border border-red-200 w-full sm:w-auto">
                            <span class="material-symbols-rounded text-[18px] text-red-500 shrink-0">lock</span>
                            <span>Terminal sedang aktif digunakan oleh <strong x-text="selectedTerminalObj.activeCashierName || 'Petugas Lain'"></strong>. Silakan pilih terminal lain.</span>
                        </div>

                        <!-- Warning Terminal Nonaktif -->
                        <div x-show="isStaffAssignedToBranch(selectedStaffObj, selectedBranch) && selectedTerminalObj && selectedTerminalObj.isActive === false" 
                             class="text-xs text-gray-700 font-medium flex items-center gap-1.5 bg-gray-100 px-3 py-2 rounded-lg border border-gray-300 w-full sm:w-auto">
                            <span class="material-symbols-rounded text-[18px] text-gray-500 shrink-0">build</span>
                            <span>Terminal ini dinonaktifkan: <strong x-text="selectedTerminalObj.inactiveReason || 'Maintenance'"></strong>. Akses ditolak.</span>
                        </div>

                        <!-- Warning Kasir Lain Dibuka Saat Masih Punya Sesi Aktif -->
                        <div x-show="isStaffAssignedToBranch(selectedStaffObj, selectedBranch) && hasActiveSessionElsewhere"
                             class="text-xs text-amber-800 font-medium flex items-center gap-1.5 bg-amber-50 px-3.5 py-2.5 rounded-lg border border-amber-200 w-full sm:w-auto">
                            <span class="material-symbols-rounded text-[18px] text-amber-600 shrink-0">warning</span>
                            <span>Anda masih memiliki sesi aktif di <strong x-text="userActiveSession ? userActiveSession.terminalLabel : 'Terminal Lain'"></strong>. Tutup sesi tersebut terlebih dahulu sebelum membuka terminal lain.</span>
                        </div>

                        <!-- Info Kasir ACTIVE_MINE (Sesi Anda Masih Aktif) -->
                        <div x-show="isStaffAssignedToBranch(selectedStaffObj, selectedBranch) && !hasActiveSessionElsewhere && selectedTerminalObj && selectedTerminalObj.status === 'ACTIVE_MINE'"
                             class="text-xs text-[#0052cc] font-medium flex items-center gap-1.5 bg-blue-50 px-3 py-2 rounded-lg border border-blue-200 w-full sm:w-auto">
                            <span class="material-symbols-rounded text-[18px] text-[#0052cc] shrink-0">info</span>
                            <span>Sesi Anda di terminal ini masih aktif. Klik <strong>Lanjutkan Sesi Kasir</strong> untuk langsung masuk tanpa input modal awal ulang.</span>
                        </div>
                    </div>

                    <button type="button"
                            @click="if (!isStaffAssignedToBranch(selectedStaffObj, selectedBranch)) { return; } if (hasActiveSessionElsewhere) { selectedShift = userActiveSession.terminalId; resumeActiveSession(); } else { openOpeningModal(); }"
                            :disabled="!isStaffAssignedToBranch(selectedStaffObj, selectedBranch) || (!hasActiveSessionElsewhere && selectedTerminalObj && (selectedTerminalObj.isActive === false || selectedTerminalObj.status === 'ACTIVE_OTHER'))"
                            :class="{
                                'bg-gray-200 text-gray-400 border border-gray-300 cursor-not-allowed shadow-none': !isStaffAssignedToBranch(selectedStaffObj, selectedBranch) || (!hasActiveSessionElsewhere && selectedTerminalObj && (selectedTerminalObj.isActive === false || selectedTerminalObj.status === 'ACTIVE_OTHER')),
                                'bg-amber-600 hover:bg-amber-700 text-white shadow-sm cursor-pointer': isStaffAssignedToBranch(selectedStaffObj, selectedBranch) && hasActiveSessionElsewhere,
                                'bg-[#0052cc] hover:bg-[#0040a8] text-white shadow-sm cursor-pointer': isStaffAssignedToBranch(selectedStaffObj, selectedBranch) && !hasActiveSessionElsewhere && selectedTerminalObj && selectedTerminalObj.status === 'ACTIVE_MINE',
                                'bg-[#16a34a] hover:bg-[#15803d] text-white shadow-sm cursor-pointer': isStaffAssignedToBranch(selectedStaffObj, selectedBranch) && !hasActiveSessionElsewhere && selectedTerminalObj && selectedTerminalObj.isActive !== false && selectedTerminalObj.status !== 'ACTIVE_OTHER' && selectedTerminalObj.status !== 'ACTIVE_MINE'
                            }"
                            class="w-full sm:w-auto font-bold py-3 px-8 rounded-xl transition-all flex items-center justify-center gap-2 text-sm shrink-0">
                        <span class="material-symbols-rounded text-[20px]"
                              x-text="!isStaffAssignedToBranch(selectedStaffObj, selectedBranch) ? 'block' : (hasActiveSessionElsewhere ? 'arrow_forward' : (selectedTerminalObj && selectedTerminalObj.isActive === false ? 'block' : (selectedTerminalObj && selectedTerminalObj.status === 'ACTIVE_OTHER' ? 'lock' : (selectedTerminalObj && selectedTerminalObj.status === 'ACTIVE_MINE' ? 'play_arrow' : 'login'))))"></span>
                        <span x-text="!isStaffAssignedToBranch(selectedStaffObj, selectedBranch)
                              ? 'Akses Cabang Belum Ditugaskan'
                              : (hasActiveSessionElsewhere
                                  ? ('Kembali ke ' + (userActiveSession ? userActiveSession.terminalLabel : 'Sesi Aktif'))
                                  : (selectedTerminalObj && selectedTerminalObj.isActive === false
                                      ? 'Terminal Nonaktif'
                                      : (selectedTerminalObj && selectedTerminalObj.status === 'ACTIVE_OTHER'
                                          ? 'Kasir Sedang Dipakai'
                                          : (selectedTerminalObj && selectedTerminalObj.status === 'ACTIVE_MINE'
                                              ? 'Lanjutkan Sesi Kasir'
                                              : (selectedTerminalObj && selectedTerminalObj.status === 'HANDOVER_PENDING' ? 'Terima Laci & Masuk' : 'Buka Kasir & Masuk')))))">
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </main>

    @include('kasir.partials._opening-modal')
</div>
@endsection
