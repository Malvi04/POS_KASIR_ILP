@extends('layouts.app')

@section('title', 'Laporan Sesi Kasir - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    showSessionDetailModal: false,
    selectedSessionDetail: null,

    pastSessions: [],

    initSessions() {
        const raw = localStorage.getItem('pos_ilp_session_history_v1');
        if (raw) {
            try {
                const hist = JSON.parse(raw);
                if (hist && hist.length > 0) {
                    this.pastSessions = hist.map(h => ({
                        id: h.sessionId || h.id || 'CS-SESS-01',
                        date: h.closedAt || h.date || '24 Oktober 2026',
                        shift: h.shift || 'Pagi (07:00 - 15:00 WIB)',
                        terminal: h.terminalLabel || 'Kasir 01',
                        cashier: h.cashierName || 'Budi Kurniawan',
                        openingFloat: Number(h.openingCash || h.openingFloat || 500000),
                        cashSales: Number(h.cashSalesNet || h.cashSales || 0),
                        nonCashSales: Number(h.nonCashSales || 0),
                        cashOut: Number(h.cashOut || 0),
                        expectedCash: Number(h.expectedCash || 0),
                        actualCash: Number(h.actualCash || h.expectedCash || 0),
                        variance: Number(h.variance || 0),
                        varianceStatus: (Number(h.variance || 0) === 0) ? 'SESUAI' : (Number(h.variance) < 0 ? 'SELISIH_KURANG' : 'SELISIH_LEBIH'),
                        notes: h.varianceReason || h.notes || 'Penutupan sesi kasir terverifikasi.',
                        status: h.status || 'CLOSED',
                        verifiedBy: h.verifiedBy || 'Supervisor Toko'
                    }));
                    return;
                }
            } catch(e) {}
        }
        this.pastSessions = this.getDefaultPastSessions();
    },

    getDefaultPastSessions() {
        return [
            {
                id: 'CS-20261024-K01-001',
                date: '24 Oktober 2026',
                shift: 'Pagi (07:00 - 10:00 WIB)',
                terminal: 'Kasir 01',
                cashier: 'Budi Kurniawan',
                openingFloat: 500000,
                cashSales: 310000,
                nonCashSales: 0,
                cashOut: 0,
                expectedCash: 810000,
                actualCash: 810000,
                variance: 0,
                varianceStatus: 'SESUAI',
                notes: 'Sesi berakhir pada pergantian shift awal, saldo fisik tepat sesuai sistem.',
                status: 'HANDOVER_DONE',
                verifiedBy: 'Siti Aminah (SPV)'
            },
            {
                id: 'CS-20261023-K01-002',
                date: '23 Oktober 2026',
                shift: 'Siang/Malam (14:00 - 22:00 WIB)',
                terminal: 'Kasir 01',
                cashier: 'Budi Kurniawan',
                openingFloat: 500000,
                cashSales: 1640000,
                nonCashSales: 890000,
                cashOut: 20000,
                expectedCash: 2120000,
                actualCash: 2115000,
                variance: -5000,
                varianceStatus: 'SELISIH_KURANG',
                notes: 'Selisih kurang Rp 5.000 akibat pembulatan koin kembalian saat jam ramai sore.',
                status: 'CLOSED',
                verifiedBy: 'Bambang Sudiro (SPV)'
            },
            {
                id: 'CS-20261023-K01-001',
                date: '23 Oktober 2026',
                shift: 'Pagi (07:00 - 14:00 WIB)',
                terminal: 'Kasir 01',
                cashier: 'Budi Kurniawan',
                openingFloat: 500000,
                cashSales: 1450000,
                nonCashSales: 620000,
                cashOut: 0,
                expectedCash: 1950000,
                actualCash: 1950000,
                variance: 0,
                varianceStatus: 'SESUAI',
                notes: 'Penutupan sesi berjalan lancar tanpa kendala fisik.',
                status: 'CLOSED',
                verifiedBy: 'Siti Aminah (SPV)'
            }
        ];
    },

    viewSession(session) {
        this.selectedSessionDetail = session;
        this.showSessionDetailModal = true;
    }
}"
x-init="initOpening(); initSessions();"
@pos-session-sync.window="initOpening()"
class="flex flex-col h-screen w-full font-sans">
    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar -->
        <x-cashier-sidebar active="laporan_sesi" />

        <!-- Main Area -->
        <div class="flex-1 flex flex-col min-w-0 bg-[#fafafa]">
            <!-- Header -->
            <x-cashier-header title="Laporan Sesi Kasir" />

            <!-- Content -->
            <main class="flex-1 overflow-y-auto p-6 md:p-8 bg-[#f8f9fb]">

                <!-- Page Top Action Bar -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Rekonsiliasi & Riwayat Sesi Kasir</h1>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-blue-100 text-[#0052cc]">
                                Kasir: Budi Kurniawan
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">Pemantauan kas laci sesi aktif dan arsip riwayat sesi kasir.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="openClosingWizard()"
                                class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold flex items-center gap-2 transition-colors shadow-2xs cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">logout</span>
                            <span>Tutup Sesi Ini</span>
                        </button>
                    </div>
                </div>

                <!-- Active Session Reconciliation Card -->
                <div class="bg-white rounded-2xl border-2 border-blue-500/30 p-6 mb-8 shadow-xs relative overflow-hidden">
                    <div class="absolute top-0 right-0 bg-blue-600 text-white font-black text-[10px] tracking-widest uppercase px-4 py-1 rounded-bl-xl shadow-xs flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>SESI AKTIF BERJALAN</span>
                    </div>

                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pb-6 border-b border-gray-100">
                        <div>
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-black text-gray-400 font-mono" x-text="activeSession.sessionId || 'CS-20261024-K01-003'"></span>
                                <span class="text-base font-bold text-gray-900">Kasir 01 (TERM-01)</span>
                            </div>
                            <div class="flex items-center gap-4 text-xs text-gray-500 mt-1">
                                <span>Dibuka: <strong class="text-gray-800">Hari Ini, 10:00 WIB</strong></span>
                                <span>•</span>
                                <span>Petugas: <strong class="text-gray-800">Budi Kurniawan</strong></span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 bg-slate-50 p-3 rounded-xl border border-gray-200">
                            <div class="text-right">
                                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">Estimasi Kas Fisik Laci:</span>
                                <span class="text-xl font-black text-emerald-700 font-mono" x-text="formatRupiah((Number(activeSession.openingFloat)||500000) + (Number(activeSession.cashSalesNet)||970000))">Rp 1.470.000</span>
                            </div>
                        </div>
                    </div>

                    <!-- 4 Financial Breakdowns for Active Session -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                        <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">Modal Awal (Float)</span>
                            <span class="text-lg font-black font-mono text-gray-900 mt-1 block" x-text="formatRupiah(activeSession.openingFloat || 500000)">Rp 500.000</span>
                            <span class="text-[11px] text-gray-500 mt-0.5 block">Diserahkan SPV saat buka</span>
                        </div>

                        <div class="p-4 bg-emerald-50/60 rounded-xl border border-emerald-100">
                            <span class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider block">Penjualan Tunai Bersih</span>
                            <span class="text-lg font-black font-mono text-emerald-700 mt-1 block" x-text="formatRupiah(activeSession.cashSalesNet || 970000)">Rp 970.000</span>
                            <span class="text-[11px] text-emerald-600 mt-0.5 block">Termasuk porsi split-bill</span>
                        </div>

                        <div class="p-4 bg-blue-50/60 rounded-xl border border-blue-100">
                            <span class="text-[10px] font-bold text-blue-700 uppercase tracking-wider block">Penjualan Non-Tunai</span>
                            <span class="text-lg font-black font-mono text-[#0052cc] mt-1 block" x-text="formatRupiah(totalNonCashSales || 700000)">Rp 700.000</span>
                            <span class="text-[11px] text-blue-600 mt-0.5 block">QRIS, Debit, Kredit Anggota</span>
                        </div>

                        <div class="p-4 bg-purple-50/60 rounded-xl border border-purple-100">
                            <span class="text-[10px] font-bold text-purple-700 uppercase tracking-wider block">Kas Keluar Laci (Paid Out)</span>
                            <span class="text-lg font-black font-mono text-purple-700 mt-1 block">Rp 0</span>
                            <span class="text-[11px] text-purple-600 mt-0.5 block">Beban operasional / refund tunai</span>
                        </div>
                    </div>
                </div>

                <!-- Past Sessions Table -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-xs overflow-hidden">
                    <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900 tracking-tight">Riwayat Sesi Terdahulu (Kasir Budi Kurniawan)</h2>
                            <p class="text-xs text-gray-500 mt-0.5">Daftar penutupan shift dan serah terima kasir.</p>
                        </div>
                        <span class="text-xs font-bold text-gray-500 bg-gray-100 px-3 py-1 rounded-lg" x-text="pastSessions.length + ' Sesi Tersimpan'"></span>
                    </div>

                    <div class="overflow-x-auto min-w-0 w-full">
                        <table class="w-full text-left border-collapse text-xs min-w-[1050px]">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 text-[11px] font-bold uppercase tracking-wider whitespace-nowrap">
                                    <th class="px-5 py-3.5 text-left w-[130px]">ID Sesi</th>
                                    <th class="px-5 py-3.5 text-left w-[180px]">Tanggal & Shift</th>
                                    <th class="px-5 py-3.5 text-right w-[130px]">Modal Awal</th>
                                    <th class="px-5 py-3.5 text-right w-[140px]">Penjualan Tunai</th>
                                    <th class="px-5 py-3.5 text-right w-[140px]">Uang Fisik Kasir</th>
                                    <th class="px-5 py-3.5 text-center w-[140px]">Selisih Kas</th>
                                    <th class="px-5 py-3.5 text-center w-[110px]">Status</th>
                                    <th class="px-5 py-3.5 text-center w-[140px]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="sesi in pastSessions" :key="sesi.id">
                                    <tr class="hover:bg-blue-50/30 transition-colors">
                                        <td class="px-5 py-3.5 font-mono font-bold text-[#0052cc] whitespace-nowrap" x-text="sesi.id"></td>
                                        <td class="px-5 py-3.5">
                                            <div class="font-bold text-gray-900" x-text="sesi.date"></div>
                                            <div class="text-[11px] text-gray-500 font-mono" x-text="sesi.shift"></div>
                                        </td>
                                        <td class="px-5 py-3.5 text-right font-mono text-gray-700 whitespace-nowrap" x-text="formatRupiah(sesi.openingFloat)"></td>
                                        <td class="px-5 py-3.5 text-right font-mono font-bold text-gray-900 whitespace-nowrap" x-text="formatRupiah(sesi.cashSales)"></td>
                                        <td class="px-5 py-3.5 text-right font-mono font-bold text-emerald-700 whitespace-nowrap" x-text="formatRupiah(sesi.actualCash)"></td>
                                        <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                            <template x-if="sesi.variance === 0">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                    SESUAI (Rp 0)
                                                </span>
                                            </template>
                                            <template x-if="sesi.variance < 0">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-800 border border-rose-200" x-text="'KURANG ' + formatRupiah(Math.abs(sesi.variance))">
                                                </span>
                                            </template>
                                        </td>
                                        <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                                  :class="sesi.status === 'CLOSED' ? 'bg-gray-100 text-gray-700' : 'bg-blue-50 text-[#0052cc]'"
                                                  x-text="sesi.status === 'CLOSED' ? 'Selesai Tutup' : 'Serah Terima'"></span>
                                        </td>
                                        <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                            <button type="button"
                                                    @click="viewSession(sesi)"
                                                    class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-[#0052cc] rounded-lg font-bold text-[11px] transition-colors cursor-pointer inline-flex items-center gap-1">
                                                <span class="material-symbols-rounded text-[14px]">receipt_long</span>
                                                <span>Detail Z-Report</span>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- MODAL DETAIL Z-REPORT SESI -->
    <template x-teleport="body">
        <div x-show="showSessionDetailModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div @click.away="showSessionDetailModal = false"
                 class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-lg overflow-hidden animate-scale-up">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-rounded text-[#0052cc] text-[22px]">account_balance_wallet</span>
                        <div>
                            <h3 class="font-bold text-gray-900 text-sm">Laporan Penutupan Sesi (Z-Report)</h3>
                            <span class="font-mono text-xs text-gray-500" x-text="selectedSessionDetail?.id"></span>
                        </div>
                    </div>
                    <button @click="showSessionDetailModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs font-sans">
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-1.5 font-mono">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Tanggal & Shift:</span>
                            <span class="font-bold text-gray-800" x-text="selectedSessionDetail?.date + ' • ' + selectedSessionDetail?.shift"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Kasir:</span>
                            <span class="text-gray-800" x-text="selectedSessionDetail?.cashier"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Diverifikasi Oleh:</span>
                            <span class="text-gray-800 font-bold" x-text="selectedSessionDetail?.verifiedBy"></span>
                        </div>
                    </div>

                    <div class="space-y-2 border-t border-b border-gray-100 py-3 font-mono">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Modal Awal Laci:</span>
                            <span class="font-bold text-gray-800" x-text="formatRupiah(selectedSessionDetail?.openingFloat)"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">(+) Total Penjualan Tunai:</span>
                            <span class="font-bold text-emerald-700" x-text="formatRupiah(selectedSessionDetail?.cashSales)"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">(-) Kas Keluar / Paid Out:</span>
                            <span class="text-purple-700" x-text="formatRupiah(selectedSessionDetail?.cashOut)"></span>
                        </div>
                        <div class="flex justify-between pt-2 border-t border-gray-200 font-bold">
                            <span class="text-gray-900">Ekspektasi Kas Sistem:</span>
                            <span class="text-gray-900" x-text="formatRupiah(selectedSessionDetail?.expectedCash)"></span>
                        </div>
                        <div class="flex justify-between font-bold">
                            <span class="text-blue-900">Hitungan Fisik Aktual Kasir:</span>
                            <span class="text-blue-900" x-text="formatRupiah(selectedSessionDetail?.actualCash)"></span>
                        </div>
                        <div class="flex justify-between font-black pt-1">
                            <span>Status Selisih (Variance):</span>
                            <span :class="selectedSessionDetail?.variance === 0 ? 'text-emerald-600' : 'text-rose-600'"
                                  x-text="selectedSessionDetail?.variance === 0 ? 'SESUAI (Rp 0)' : formatRupiah(selectedSessionDetail?.variance)"></span>
                        </div>
                    </div>

                    <div class="p-3 bg-slate-50 border border-gray-200 rounded-xl">
                        <span class="font-bold text-gray-700 block mb-1">Berita Acara / Catatan Shift:</span>
                        <p class="text-gray-600 italic" x-text="selectedSessionDetail?.notes || '-'"></p>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2 bg-slate-50">
                    <button type="button"
                            @click="window.print()"
                            class="px-4 py-2 bg-blue-50 hover:bg-blue-100 text-[#0052cc] rounded-xl font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[16px]">print</span>
                        <span>Cetak Struk Z-Report</span>
                    </button>
                    <button type="button"
                            @click="showSessionDetailModal = false"
                            class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white rounded-xl font-bold text-xs transition-colors cursor-pointer">
                        Tutup
                    </button>
                </div>

            </div>
        </div>
    </template>
</div>
@endsection
