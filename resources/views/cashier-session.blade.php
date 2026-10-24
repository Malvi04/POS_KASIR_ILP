@extends('layouts.app')

@section('title', 'Sesi Kasir - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    activeTab: 'movements'
}"
x-init="initOpening()"
@pos-session-sync.window="initOpening()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">

    <!-- Sidebar -->
    <x-cashier-sidebar active="session" />

    <!-- Main Area -->
    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
        <!-- Top Header -->
        <x-cashier-header title="Sesi Kasir">
            <div class="flex items-center gap-2 ml-2">
                <button type="button"
                        @click="toggleOfflineMode()"
                        :class="activeSession.isOffline ? 'bg-amber-100 text-amber-800 border-amber-300' : 'bg-slate-100 text-slate-700 border-slate-200 hover:bg-slate-200'"
                        class="px-3 py-1.5 rounded-xl border text-xs font-bold flex items-center gap-1.5 transition-all cursor-pointer">
                    <span class="material-symbols-rounded text-[16px]" x-text="activeSession.isOffline ? 'cloud_off' : 'cloud_done'"></span>
                    <span x-text="activeSession.isOffline ? 'Offline (' + activeSession.pendingSyncCount + ')' : 'Online'"></span>
                </button>
            </div>
        </x-cashier-header>

        <!-- Scrollable Content -->
        <main class="flex-1 overflow-y-auto p-6 lg:p-8 space-y-6">
            <div class="max-w-[1360px] mx-auto space-y-6">

                <!-- 1. INFO SESI AKTIF -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-xs p-6">
                    <!-- Banner jika sesi telah ditutup -->
                    <template x-if="activeSession.status !== 'OPEN'">
                        <div class="mb-5 p-4 rounded-xl bg-amber-50/90 border border-amber-200 text-amber-900 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                            <div class="flex items-center gap-2.5">
                                <span class="material-symbols-rounded text-amber-600 text-[22px] shrink-0">info</span>
                                <div>
                                    <span>Sesi kasir <strong class="font-mono text-gray-900" x-text="activeSession.sessionId"></strong> telah ditutup pada pukul <strong x-text="activeSession.endedAt || '-'"></strong> (Status: <strong x-text="activeSession.status"></strong>).</span>
                                    <span class="block text-amber-700 mt-0.5">Laci telah siap/diserahterimakan. Silakan buka sesi baru untuk transaksi berikutnya.</span>
                                </div>
                            </div>
                            <a href="/select-shift" class="px-4 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs flex items-center justify-center gap-1.5 shrink-0 shadow-xs transition-colors">
                                <span class="material-symbols-rounded text-[16px]">point_of_sale</span>
                                <span>Pilih Terminal / Buka Sesi</span>
                            </a>
                        </div>
                    </template>

                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-gray-100">
                        <h1 class="text-xl font-black text-gray-900" x-text="activeSession.status === 'OPEN' ? 'Informasi Sesi Aktif' : 'Informasi Sesi Kasir (Ditutup)'">Informasi Sesi Aktif</h1>

                        <div class="flex flex-wrap items-center gap-2.5">
                            <template x-if="activeSession.status === 'OPEN'">
                                <div class="flex items-center gap-2.5">
                                    <button type="button"
                                            @click="showCashMovementModal = true"
                                            class="px-4 py-2.5 rounded-xl bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 font-bold text-xs flex items-center gap-2 cursor-pointer transition-colors">
                                        <span class="material-symbols-rounded text-[18px] text-[#0052cc]">account_balance_wallet</span>
                                        <span>Catat Kas Laci</span>
                                    </button>
                                    <button type="button"
                                            @click="openClosingWizard()"
                                            class="px-5 py-2.5 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs flex items-center gap-2 shadow-sm cursor-pointer transition-colors">
                                        <span class="material-symbols-rounded text-[18px]">lock</span>
                                        <span>Tutup Sesi Kasir</span>
                                    </button>
                                </div>
                            </template>
                            <template x-if="activeSession.status !== 'OPEN'">
                                <a href="/select-shift"
                                   class="px-5 py-2.5 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs flex items-center gap-2 shadow-sm cursor-pointer transition-colors">
                                    <span class="material-symbols-rounded text-[18px]">add_circle</span>
                                    <span>Buka Sesi Baru</span>
                                </a>
                            </template>
                        </div>
                    </div>

                    <!-- Grid 4 Info -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-5">
                        <div class="p-4 rounded-xl bg-slate-50 border border-gray-200/80">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-extrabold text-gray-500 uppercase tracking-wider">Terminal</span>
                                <span class="material-symbols-rounded text-[#0052cc] text-[20px]">point_of_sale</span>
                            </div>
                            <div class="text-lg font-black text-gray-900 mt-1.5" x-text="activeSession.terminalLabel">Kasir 01</div>
                            <div class="text-xs text-gray-500 mt-0.5" x-text="activeSession.storeLocation">Minimarket - Sektor A</div>
                        </div>

                        <div class="p-4 rounded-xl bg-slate-50 border border-gray-200/80">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-extrabold text-gray-500 uppercase tracking-wider">Petugas</span>
                                <span class="material-symbols-rounded text-emerald-600 text-[20px]">badge</span>
                            </div>
                            <div class="text-lg font-black text-gray-900 mt-1.5" x-text="activeSession.cashierName">Budi Kurniawan</div>
                            <div class="text-xs text-gray-500 font-mono mt-0.5" x-text="'NIK: ' + activeSession.cashierNik">NIK: 202409821002</div>
                        </div>

                        <div class="p-4 rounded-xl bg-blue-50/60 border border-blue-200">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-extrabold text-[#0052cc] uppercase tracking-wider">ID Sesi</span>
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-black"
                                      :class="{
                                          'bg-emerald-100 text-emerald-800': activeSession.status === 'OPEN',
                                          'bg-slate-200 text-slate-700': activeSession.status === 'CLOSED',
                                          'bg-amber-100 text-amber-800': activeSession.status === 'PENDING_REVIEW'
                                      }"
                                      x-text="activeSession.status">OPEN</span>
                            </div>
                            <div class="text-base font-black font-mono text-[#0052cc] mt-1.5" x-text="activeSession.sessionId">CS-20261019-K01-003</div>
                            <div class="text-xs text-gray-600 mt-0.5">
                                <span x-text="activeSession.status === 'OPEN' ? 'Mulai ' + activeSession.startedAt : 'Selesai ' + (activeSession.endedAt || '-')"></span> • <strong x-text="activeSession.trxCount">28</strong> Transaksi
                            </div>
                        </div>

                        <div class="p-4 rounded-xl bg-slate-50 border border-gray-200/80">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-extrabold text-gray-500 uppercase tracking-wider">Tanggal Operasional</span>
                                <span class="material-symbols-rounded text-amber-600 text-[20px]">calendar_month</span>
                            </div>
                            <div class="text-lg font-black text-gray-900 mt-1.5" x-text="activeSession.businessDayDate">19 Oktober 2026</div>
                            <div class="text-xs text-gray-500 font-mono mt-0.5" x-text="activeSession.businessDayId">BD-20261019-01</div>
                        </div>
                    </div>
                </div>

                <!-- 2. RINGKASAN KAS LACI & NON-TUNAI -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left 2 Cols: Komponen Kas Laci -->
                    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-xs p-6">
                        <h2 class="text-base font-black text-gray-900 pb-4 border-b border-gray-100 flex items-center gap-2">
                            <span class="material-symbols-rounded text-[#0052cc]">payments</span>
                            Ringkasan Kas Laci
                        </h2>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3.5 mt-4">
                            <div class="p-4 rounded-xl bg-slate-50 border border-gray-200/80">
                                <span class="text-[11px] font-bold text-gray-500 uppercase block">Uang Awal</span>
                                <span class="text-lg font-black font-mono text-gray-900 mt-1 block" x-text="formatRupiah(activeSession.openingCash)"></span>
                            </div>

                            <div class="p-4 rounded-xl bg-emerald-50/60 border border-emerald-200/80">
                                <span class="text-[11px] font-bold text-emerald-700 uppercase block">(+) Penjualan Tunai</span>
                                <span class="text-lg font-black font-mono text-emerald-800 mt-1 block"
                                      x-text="'+ ' + formatRupiah((Number(activeSession.cashSalesNet)||0) + (Number(activeSession.splitCashPortion)||0))"></span>
                            </div>

                            <div class="p-4 rounded-xl bg-emerald-50/60 border border-emerald-200/80">
                                <span class="text-[11px] font-bold text-emerald-700 uppercase block">(+) Kas Masuk</span>
                                <span class="text-lg font-black font-mono text-emerald-800 mt-1 block" x-text="'+ ' + formatRupiah(activeSession.cashIn)"></span>
                            </div>

                            <div class="p-4 rounded-xl bg-red-50/60 border border-red-200/80">
                                <span class="text-[11px] font-bold text-red-700 uppercase block">(-) Retur Tunai</span>
                                <span class="text-lg font-black font-mono text-red-700 mt-1 block" x-text="'- ' + formatRupiah(activeSession.cashReturn)"></span>
                            </div>

                            <div class="p-4 rounded-xl bg-red-50/60 border border-red-200/80">
                                <span class="text-[11px] font-bold text-red-700 uppercase block">(-) Kas Keluar</span>
                                <span class="text-lg font-black font-mono text-red-700 mt-1 block" x-text="'- ' + formatRupiah(activeSession.cashOut)"></span>
                            </div>

                            <div class="p-4 rounded-xl bg-amber-50/70 border border-amber-200/80">
                                <span class="text-[11px] font-bold text-amber-800 uppercase block">(-) Setor Brankas</span>
                                <span class="text-lg font-black font-mono text-amber-900 mt-1 block" x-text="'- ' + formatRupiah(activeSession.cashDrop)"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Right 1 Col: Non-Tunai & Kredit -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-xs p-6 flex flex-col justify-between">
                        <div>
                            <h3 class="text-base font-black text-gray-900 pb-4 border-b border-gray-100">Non-Tunai &amp; Kredit</h3>

                            <div class="space-y-3 mt-4">
                                <div class="p-3.5 rounded-xl bg-blue-50/60 border border-blue-200/80 flex items-center justify-between">
                                    <div>
                                        <span class="text-[10px] font-extrabold text-[#0052cc] uppercase">QRIS / Debit</span>
                                        <div class="text-lg font-black font-mono text-gray-900 mt-0.5" x-text="formatRupiah(totalNonCashSales)"></div>
                                    </div>
                                    <span class="material-symbols-rounded text-2xl text-[#0052cc]">qr_code_2</span>
                                </div>

                                <div class="p-3.5 rounded-xl bg-orange-50/70 border border-orange-200/80 flex items-center justify-between">
                                    <div>
                                        <span class="text-[10px] font-extrabold text-orange-700 uppercase">Kredit Anggota</span>
                                        <div class="text-lg font-black font-mono text-gray-900 mt-0.5" x-text="formatRupiah(activeSession.creditSales)"></div>
                                    </div>
                                    <span class="material-symbols-rounded text-2xl text-orange-600">credit_score</span>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 mt-4 border-t border-gray-100 flex items-center justify-between text-xs">
                            <span class="font-bold text-gray-500">Total Omzet Sesi:</span>
                            <span class="font-mono font-black text-base text-gray-900" x-text="formatRupiah(totalSessionOmzet)"></span>
                        </div>
                    </div>
                </div>

                <!-- 3. TABS: ARUS KAS LACI & RIWAYAT SESI SAYA -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden">
                    <!-- Tab Navigation -->
                    <div class="px-6 pt-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-4 bg-slate-50/60">
                        <div class="flex gap-6">
                            <button type="button"
                                    @click="activeTab = 'movements'"
                                    :class="activeTab === 'movements' ? 'border-[#0052cc] text-[#0052cc] font-black' : 'border-transparent text-gray-500 hover:text-gray-800 font-bold'"
                                    class="pb-3.5 border-b-2 text-xs uppercase tracking-wider flex items-center gap-2 cursor-pointer transition-all">
                                <span class="material-symbols-rounded text-[18px]">receipt_long</span>
                                <span>Arus Kas Laci (<span x-text="cashMovements.length"></span>)</span>
                            </button>
                            <button type="button"
                                    @click="activeTab = 'my_sessions'"
                                    :class="activeTab === 'my_sessions' ? 'border-[#0052cc] text-[#0052cc] font-black' : 'border-transparent text-gray-500 hover:text-gray-800 font-bold'"
                                    class="pb-3.5 border-b-2 text-xs uppercase tracking-wider flex items-center gap-2 cursor-pointer transition-all">
                                <span class="material-symbols-rounded text-[18px]">person_check</span>
                                <span>Riwayat Sesi Saya</span>
                            </button>
                        </div>

                        <div class="pb-3">
                            <a href="{{ url('/select-shift') }}" class="text-xs font-bold text-[#0052cc] hover:underline flex items-center gap-1">
                                <span class="material-symbols-rounded text-[16px]">open_in_new</span>
                                <span>Pilihan Terminal Kasir</span>
                            </a>
                        </div>
                    </div>

                    <!-- TAB 1: ARUS KAS LACI -->
                    <div x-show="activeTab === 'movements'" class="p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-black text-gray-900">Jurnal Kas Laci</h3>
                            <button type="button"
                                    @click="showCashMovementModal = true"
                                    class="px-4 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-xs font-bold flex items-center gap-1.5 cursor-pointer">
                                <span class="material-symbols-rounded text-[16px]">add</span>
                                Tambah Kas Masuk / Keluar
                            </button>
                        </div>

                        <div class="overflow-x-auto border border-gray-200 rounded-xl">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-gray-200 text-[10px] font-black text-gray-500 uppercase tracking-wider">
                                        <th class="py-3 px-4">ID &amp; Jam</th>
                                        <th class="py-3 px-4">Kategori</th>
                                        <th class="py-3 px-4">Keterangan</th>
                                        <th class="py-3 px-4">Petugas</th>
                                        <th class="py-3 px-4 text-right">Nominal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="mv in cashMovements" :key="mv.id">
                                        <tr class="hover:bg-slate-50">
                                            <td class="py-3 px-4 font-mono text-gray-600" x-text="mv.id + ' • ' + mv.time"></td>
                                            <td class="py-3 px-4">
                                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold"
                                                      :class="mv.direction === 'in' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-800 border border-amber-200'"
                                                      x-text="mv.type"></span>
                                            </td>
                                            <td class="py-3 px-4 font-medium text-gray-800" x-text="mv.label"></td>
                                            <td class="py-3 px-4 text-gray-600 font-semibold" x-text="mv.actor"></td>
                                            <td class="py-3 px-4 text-right font-mono font-bold"
                                                :class="mv.direction === 'in' ? 'text-emerald-700' : 'text-red-600'"
                                                x-text="(mv.direction === 'in' ? '+ ' : '- ') + formatRupiah(mv.amount)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 2: RIWAYAT SESI SAYA -->
                    <div x-show="activeTab === 'my_sessions'" style="display: none;" class="p-6">
                        <div class="overflow-x-auto border border-gray-200 rounded-xl">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-gray-200 text-[10px] font-black text-gray-500 uppercase tracking-wider">
                                        <th class="py-3.5 px-4">ID Sesi</th>
                                        <th class="py-3.5 px-4">Terminal</th>
                                        <th class="py-3.5 px-4">Waktu</th>
                                        <th class="py-3.5 px-4 text-right">Uang Awal</th>
                                        <th class="py-3.5 px-4 text-right">Kas Sistem</th>
                                        <th class="py-3.5 px-4 text-right">Kas Fisik</th>
                                        <th class="py-3.5 px-4 text-right">Selisih</th>
                                        <th class="py-3.5 px-4 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-if="activeSession.status === 'OPEN'">
                                        <tr class="bg-blue-50/50 font-medium">
                                            <td class="py-3.5 px-4 font-mono font-black text-[#0052cc]" x-text="activeSession.sessionId"></td>
                                            <td class="py-3.5 px-4 font-bold text-gray-900" x-text="activeSession.terminalLabel"></td>
                                            <td class="py-3.5 px-4 font-mono text-blue-700 font-bold" x-text="activeSession.startedAt + ' – Sekarang'"></td>
                                            <td class="py-3.5 px-4 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(activeSession.openingCash)"></td>
                                            <td class="py-3.5 px-4 text-right font-mono font-bold text-[#0052cc]" x-text="formatRupiah(expectedClosingCash)"></td>
                                            <td class="py-3.5 px-4 text-right font-mono text-gray-400">-</td>
                                            <td class="py-3.5 px-4 text-right font-mono text-gray-400">-</td>
                                            <td class="py-3.5 px-4 text-center">
                                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-blue-600 text-white uppercase">OPEN</span>
                                            </td>
                                        </tr>
                                    </template>

                                    <template x-for="sess in sessionHistory.filter(s => s.cashierId === activeSession.cashierId)" :key="sess.sessionId">
                                        <tr class="hover:bg-slate-50">
                                            <td class="py-3.5 px-4 font-mono font-bold text-[#0052cc]" x-text="sess.sessionId"></td>
                                            <td class="py-3.5 px-4 font-bold text-gray-800" x-text="sess.terminalLabel"></td>
                                            <td class="py-3.5 px-4 font-mono text-gray-600" x-text="sess.startedAt + ' – ' + sess.endedAt"></td>
                                            <td class="py-3.5 px-4 text-right font-mono text-gray-700" x-text="formatRupiah(sess.openingCash)"></td>
                                            <td class="py-3.5 px-4 text-right font-mono text-gray-800" x-text="formatRupiah(sess.expectedClosingCash)"></td>
                                            <td class="py-3.5 px-4 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(sess.actualClosingCash)"></td>
                                            <td class="py-3.5 px-4 text-right font-mono font-black"
                                                :class="sess.cashVariance === 0 ? 'text-emerald-600' : 'text-red-600'"
                                                x-text="formatRupiah(sess.cashVariance)"></td>
                                            <td class="py-3.5 px-4 text-center">
                                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200 uppercase"
                                                      x-text="sess.status"></span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Include Opening & Denom Calculator Modal -->
    @include('kasir.partials._opening-modal')

    <!-- Toast Notification -->
    <div x-show="showSuccessToast"
         style="display: none;"
         x-transition
         class="fixed top-5 right-5 z-[99] bg-gray-900 text-white px-5 py-3.5 rounded-xl shadow-2xl flex items-center gap-3 font-bold text-xs border border-gray-700">
        <span class="material-symbols-rounded text-emerald-400 text-[20px]">check_circle</span>
        <span x-text="toastMessage"></span>
    </div>
</div>
@endsection
