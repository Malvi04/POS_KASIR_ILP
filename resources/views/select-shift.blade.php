@extends('layouts.app')

@section('title', 'Pilih Kasir - ILP POS')

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
            <span class="font-mono text-gray-500 text-xs sm:text-sm tracking-wider" x-text="formatShiftLabel(selectedShift).toUpperCase()">KASIR 01</span>
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
        
        <!-- Left Column: Identifikasi & Riwayat (4 Cols) -->
        <div class="lg:col-span-4 flex flex-col gap-6 w-full">
            <!-- Card 1: Identifikasi Kasir -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 flex flex-col gap-5">
                <div class="flex items-center gap-3.5 pb-2 border-b border-gray-100">
                    <div class="w-12 h-12 bg-blue-50 text-[#0052cc] rounded-full flex items-center justify-center shrink-0">
                        <span class="material-symbols-rounded text-2xl">badge</span>
                    </div>
                    <div>
                        <h2 class="text-gray-900 font-black text-base">Identifikasi Kasir</h2>
                        <p class="text-gray-500 text-xs mt-0.5">Pilih petugas &amp; lokasi tugas</p>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <!-- Pilih Petugas -->
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-3.5 flex items-center justify-between gap-3">
                        <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-wider shrink-0">PETUGAS</span>
                        <div class="relative flex-1 text-right">
                            <select x-model="selectedStaffId"
                                    @change="handleStaffChange()"
                                    class="w-full bg-transparent font-bold text-gray-900 text-xs text-right outline-none cursor-pointer">
                                <template x-for="st in staffList" :key="st.id">
                                    <option :value="st.id" x-text="st.name"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <!-- Pilih Lokasi -->
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-3.5 flex items-center justify-between gap-3">
                        <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-wider shrink-0">LOKASI</span>
                        <div class="relative flex-1 text-right">
                            <select x-model="selectedLocation"
                                    class="w-full bg-transparent font-bold text-gray-800 text-xs text-right outline-none cursor-pointer">
                                <option value="Minimarket - Sektor A">Minimarket - Sektor A</option>
                                <option value="Minimarket - Sektor B">Minimarket - Sektor B</option>
                                <option value="Minimarket - Sektor Pusat">Minimarket - Sektor Pusat</option>
                            </select>
                        </div>
                    </div>

                    <!-- Level Akses -->
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-3.5 flex items-center justify-between">
                        <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-wider">AKSES</span>
                        <span class="bg-[#004aad] text-white text-[10px] font-black px-3 py-1 rounded-full tracking-wider">KASIR</span>
                    </div>
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
                                <span class="font-mono text-[10px] font-bold text-gray-500" x-text="sess.startedAt + ' – ' + sess.endedAt"></span>
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

        <!-- Right Column: Banner & Daftar Terminal Kasir (8 Cols) -->
        <div class="lg:col-span-8 flex flex-col gap-6 w-full">
            <!-- Welcome Banner -->
            <div class="bg-[#0052cc] rounded-2xl py-6 px-8 relative overflow-hidden flex items-center justify-between gap-4 shadow-sm">
                <div class="absolute inset-0 opacity-20 mix-blend-overlay" style="background-image: url('https://images.unsplash.com/photo-1578916171728-46686eac8d58?q=80&w=1000&auto=format&fit=crop'); background-size: cover; background-position: center;"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-[#0052cc] via-[#0052cc]/85 to-transparent"></div>
                
                <div class="relative z-10 text-white">
                    <h2 class="text-xl font-black mb-1">Selamat Datang, <span x-text="selectedStaffObj.name"></span></h2>
                    <p class="text-blue-100 text-xs sm:text-sm">Silakan pilih nomor kasir yang akan digunakan</p>
                </div>

                <div class="relative z-10 hidden sm:block bg-white/15 backdrop-blur-md border border-white/25 rounded-xl px-4 py-2.5 text-white text-right shrink-0">
                    <div class="text-[10px] font-bold text-blue-100 uppercase">Terminal Dipilih</div>
                    <div class="text-base font-black" x-text="formatShiftLabel(selectedShift)">Kasir 01</div>
                </div>
            </div>

            <!-- Pilih Kasir Container -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 flex flex-col gap-5">
                <!-- Header + Filter Tabs -->
                <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-gray-100">
                    <h3 class="text-gray-900 font-black text-base">Pilih Kasir</h3>
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

                <!-- Grid of Terminals (2 cols on desktop) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <template x-for="term in filteredTerminals" :key="term.id">
                        <div @click="selectTerminalCard(term)"
                             @dblclick="if (term.status === 'ACTIVE_MINE') resumeActiveSession()"
                             :class="{
                                 'border-red-400 bg-red-50/50 ring-2 ring-red-300/60': selectedShift === term.id && term.status === 'ACTIVE_OTHER',
                                 'border-[#0052cc] bg-[#eef2ff] ring-2 ring-[#0052cc]/20': selectedShift === term.id && term.status !== 'ACTIVE_OTHER',
                                 'border-gray-200 bg-gray-50/70 hover:border-gray-300 opacity-90': selectedShift !== term.id && term.status === 'ACTIVE_OTHER',
                                 'border-gray-200 hover:border-blue-300 bg-white': selectedShift !== term.id && term.status !== 'ACTIVE_OTHER'
                             }"
                             class="p-4 rounded-xl border-[1.5px] cursor-pointer transition-all flex items-center justify-between gap-3 shadow-2xs">
                            
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-black text-gray-900" x-text="term.label"></span>
                                    <span class="text-[11px] text-gray-400 font-medium" x-text="'(' + term.sessionsTodayCount + ' sesi)'"></span>
                                </div>
                                <div class="text-xs text-gray-500 truncate mt-1">
                                    <template x-if="term.status === 'HANDOVER_PENDING' && term.handover">
                                        <span>Operan <strong class="text-gray-700" x-text="term.handover.fromCashierName"></strong>: <strong class="font-mono text-emerald-700 font-bold" x-text="formatRupiah(term.handover.actualCash)"></strong></span>
                                    </template>
                                    <template x-if="term.status === 'AVAILABLE'">
                                        <span>Siap dibuka • Modal Rp 500.000</span>
                                    </template>
                                    <template x-if="term.status === 'ACTIVE_OTHER'">
                                        <span class="text-red-600 font-semibold flex items-center gap-1">
                                            <span>Aktif: <strong x-text="term.activeCashierName"></strong></span>
                                        </span>
                                    </template>
                                    <template x-if="term.status === 'ACTIVE_MINE'">
                                        <span class="text-[#0052cc] font-semibold flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-[#0052cc] animate-pulse"></span>
                                            <span>Sesi Anda sedang aktif</span>
                                        </span>
                                    </template>
                                </div>
                            </div>

                            <!-- Status Badge -->
                            <div class="shrink-0">
                                <span x-show="term.status === 'HANDOVER_PENDING'" class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-800 border border-amber-300">
                                    SERAH TERIMA
                                </span>
                                <span x-show="term.status === 'AVAILABLE'" class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    KOSONG
                                </span>
                                <span x-show="term.status === 'ACTIVE_OTHER'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-red-100 text-red-700 border border-red-200">
                                    <span class="material-symbols-rounded text-[13px]">lock</span>
                                    DIPAKAI
                                </span>
                                <span x-show="term.status === 'ACTIVE_MINE'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-blue-100 text-[#0052cc] border border-blue-200">
                                    <span class="material-symbols-rounded text-[13px]">play_circle</span>
                                    AKTIF
                                </span>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Action Button Bottom -->
                <div class="pt-4 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <!-- Warning Kasir DIPAKAI Orang Lain -->
                        <div x-show="selectedTerminalObj && selectedTerminalObj.status === 'ACTIVE_OTHER'" 
                             class="text-xs text-red-700 font-medium flex items-center gap-1.5 bg-red-50 px-3 py-2 rounded-lg border border-red-200 w-full sm:w-auto">
                            <span class="material-symbols-rounded text-[18px] text-red-500 shrink-0">lock</span>
                            <span>Kasir sedang aktif digunakan oleh <strong x-text="selectedTerminalObj.activeCashierName"></strong>. Silakan pilih kasir lain.</span>
                        </div>

                        <!-- Info Kasir ACTIVE_MINE (Sesi Anda Masih Aktif) -->
                        <div x-show="selectedTerminalObj && selectedTerminalObj.status === 'ACTIVE_MINE'" 
                             class="text-xs text-[#0052cc] font-medium flex items-center gap-1.5 bg-blue-50 px-3 py-2 rounded-lg border border-blue-200 w-full sm:w-auto">
                            <span class="material-symbols-rounded text-[18px] text-[#0052cc] shrink-0">info</span>
                            <span>Sesi Anda di kasir ini masih aktif. Klik <strong>Lanjutkan Sesi Kasir</strong> untuk langsung masuk tanpa input modal awal ulang.</span>
                        </div>
                    </div>

                    <button type="button"
                            @click="openOpeningModal()"
                            :disabled="selectedTerminalObj && selectedTerminalObj.status === 'ACTIVE_OTHER'"
                            :class="{
                                'bg-gray-200 text-gray-400 border border-gray-300 cursor-not-allowed shadow-none': selectedTerminalObj && selectedTerminalObj.status === 'ACTIVE_OTHER',
                                'bg-[#0052cc] hover:bg-[#0040a8] text-white shadow-sm cursor-pointer': selectedTerminalObj && selectedTerminalObj.status === 'ACTIVE_MINE',
                                'bg-[#16a34a] hover:bg-[#15803d] text-white shadow-sm cursor-pointer': selectedTerminalObj && selectedTerminalObj.status !== 'ACTIVE_OTHER' && selectedTerminalObj.status !== 'ACTIVE_MINE'
                            }"
                            class="w-full sm:w-auto font-bold py-3 px-8 rounded-xl transition-all flex items-center justify-center gap-2 text-sm shrink-0">
                        <span class="material-symbols-rounded text-[20px]"
                              x-text="selectedTerminalObj && selectedTerminalObj.status === 'ACTIVE_OTHER' ? 'lock' : (selectedTerminalObj && selectedTerminalObj.status === 'ACTIVE_MINE' ? 'play_arrow' : 'login')"></span>
                        <span x-text="selectedTerminalObj && selectedTerminalObj.status === 'ACTIVE_OTHER' 
                              ? 'Kasir Sedang Dipakai' 
                              : (selectedTerminalObj && selectedTerminalObj.status === 'ACTIVE_MINE' 
                                  ? 'Lanjutkan Sesi Kasir' 
                                  : (selectedTerminalObj && selectedTerminalObj.status === 'HANDOVER_PENDING' ? 'Terima Laci & Masuk' : 'Buka Kasir & Masuk'))">
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </main>

    @include('kasir.partials._opening-modal')
</div>
@endsection
