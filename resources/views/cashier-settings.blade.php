@extends('layouts.app')

@section('title', 'Settings - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    autoPrintStruk: true,
    modeCepat: true,
    selectedPrinter: 'EPSON TM-T82II Receipt (Default)',
    toastMessage: '',
    showToast: false,

    initSettings() {
        const stored = localStorage.getItem('pos_ilp_terminal_prefs_v1');
        if (stored) {
            try {
                const prefs = JSON.parse(stored);
                if (prefs.autoPrintStruk !== undefined) this.autoPrintStruk = prefs.autoPrintStruk;
                if (prefs.modeCepat !== undefined) this.modeCepat = prefs.modeCepat;
                if (prefs.selectedPrinter) this.selectedPrinter = prefs.selectedPrinter;
            } catch(e) {}
        }
    },

    savePrefs() {
        localStorage.setItem('pos_ilp_terminal_prefs_v1', JSON.stringify({
            autoPrintStruk: this.autoPrintStruk,
            modeCepat: this.modeCepat,
            selectedPrinter: this.selectedPrinter
        }));
    },

    triggerToast(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3200);
    }
}"
x-init="initOpening(); initSettings();"
@pos-session-sync.window="initOpening()"
class="flex h-screen w-full overflow-hidden">
<!-- Sidebar -->
<x-cashier-sidebar active="settings" />

<!-- Main Content -->
<main class="flex-1 flex flex-col bg-white relative h-full">
    <!-- Header -->
    <x-cashier-header title="Pengaturan" />

    <!-- Scrollable Content -->
    <div class="flex-1 overflow-auto p-8 bg-slate-50">
        <div class="max-w-[1200px] mx-auto space-y-6">

            <!-- Top Section: Profile & System Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- User & Active Session Card -->
                <div class="bg-white rounded-xl border border-gray-200 p-6 flex flex-col justify-between relative overflow-hidden">
                    <div class="flex items-start justify-between gap-5">
                        <div>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-blue-50 text-[#0052cc] border border-blue-200 uppercase">PETUGAS AKTIF</span>
                            <h3 class="text-xl font-black text-gray-900 mt-1.5" x-text="activeSession.cashierName">Budi Kurniawan</h3>
                            <p class="text-gray-500 text-xs font-mono mt-0.5" x-text="'NIK: ' + activeSession.cashierNik">NIK: 202409821002</p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-black bg-emerald-50 text-emerald-700 border border-emerald-200" x-text="activeSession.terminalLabel">Kasir 01</span>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mt-5">
                        <div class="bg-blue-50 rounded-lg p-3 border border-blue-100">
                            <p class="text-[10px] font-bold text-gray-500 uppercase mb-0.5">ID SESI</p>
                            <p class="text-blue-700 font-mono font-bold text-xs" x-text="activeSession.sessionId">CS-20261019-K01-003</p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-3 border border-green-100">
                            <p class="text-[10px] font-bold text-gray-500 uppercase mb-0.5">UANG AWAL</p>
                            <p class="text-green-700 font-mono font-bold text-sm" x-text="formatRupiah(activeSession.openingCash)">Rp 1.740.000</p>
                        </div>
                    </div>

                    <div class="mt-5 flex gap-3">
                        <a href="{{ url('/kasir/sesi') }}" class="flex-1 flex items-center justify-center gap-2 bg-[#0052cc] text-white font-bold text-xs py-2.5 rounded-lg hover:bg-blue-800 transition-colors">
                            <span class="material-symbols-rounded text-[18px]">swap_horiz</span>
                            Sesi Kasir
                        </a>
                        <button type="button" @click="$dispatch('open-closing-wizard')" class="flex-1 flex items-center justify-center gap-2 border border-blue-600 text-blue-600 font-bold text-xs py-2.5 rounded-lg hover:bg-blue-50 transition-colors cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">logout</span>
                            Tutup Sesi
                        </button>
                    </div>
                </div>

                <!-- System Info Card -->
                <div class="bg-blue-600 rounded-xl p-6 text-white relative overflow-hidden flex flex-col justify-between">
                    <div class="relative z-10">
                        <span class="inline-block bg-blue-500 text-blue-50 text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-wider mb-3">
                            INFORMASI TERMINAL
                        </span>
                        <h3 class="text-xl font-bold">Kopkar ILP POS v2.5</h3>

                        <div class="mt-5 space-y-3 text-sm">
                            <div class="flex justify-between items-center border-b border-blue-500/50 pb-2">
                                <span class="text-blue-100">Lokasi</span>
                                <span class="font-bold" x-text="activeSession.storeLocation">Minimarket - Sektor A</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-blue-500/50 pb-2">
                                <span class="text-blue-100">Terminal</span>
                                <span class="font-bold font-mono" x-text="activeSession.terminalLabel">Kasir 01</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-blue-100">Tanggal</span>
                                <span class="font-bold" x-text="activeSession.businessDayDate">19 Oktober 2026</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Middle Section: Hardware & Prefs -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Printer Config -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex justify-between items-center mb-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center">
                                <span class="material-symbols-rounded">print</span>
                            </div>
                            <h3 class="font-bold text-gray-800 text-sm">Konfigurasi Printer</h3>
                        </div>
                        <div class="flex items-center gap-1.5 px-2.5 py-1 bg-green-50 text-green-700 rounded-full border border-green-200 text-[11px] font-bold">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                            Terhubung
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-[11px] font-bold text-gray-700 mb-2">Printer Struk</label>
                        <div class="relative">
                            <select class="w-full pl-4 pr-10 py-2.5 appearance-none rounded-lg bg-slate-50 border border-gray-200 text-gray-700 text-sm font-medium outline-none cursor-pointer">
                                <option>EPSON TM-T82II Receipt (Default)</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">expand_more</span>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <button type="button" @click="window.print()" class="flex-1 flex items-center justify-center gap-2 border border-gray-200 bg-white font-bold text-gray-700 text-xs py-2.5 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">print</span>
                            <span>Test Print</span>
                        </button>
                        <button type="button" @click="triggerToast('Koneksi printer dan service QZ Tray berhasil direset!')" class="flex-1 flex items-center justify-center gap-2 border border-gray-200 bg-white font-bold text-gray-700 text-xs py-2.5 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">refresh</span>
                            <span>Reset QZ</span>
                        </button>
                    </div>
                </div>

                <!-- Terminal Prefs -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-500 flex items-center justify-center">
                            <span class="material-symbols-rounded">tune</span>
                        </div>
                        <h3 class="font-bold text-gray-800 text-sm">Preferensi Terminal</h3>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3.5 bg-slate-50/50 rounded-xl border border-gray-100">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-rounded text-gray-400">receipt_long</span>
                                <p class="font-bold text-gray-800 text-sm">Auto-print Struk</p>
                            </div>
                            <div @click="autoPrintStruk = !autoPrintStruk; savePrefs(); triggerToast('Auto-print struk: ' + (autoPrintStruk ? 'Aktif' : 'Nonaktif'))"
                                 :class="autoPrintStruk ? 'bg-[#0052cc]' : 'bg-gray-300'"
                                 class="w-11 h-6 rounded-full relative cursor-pointer flex items-center px-0.5 transition-colors">
                                <div :class="autoPrintStruk ? 'right-0.5' : 'left-0.5'" class="w-5 h-5 bg-white rounded-full absolute shadow transition-all"></div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-3.5 bg-slate-50/50 rounded-xl border border-gray-100">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-rounded text-gray-400">bolt</span>
                                <p class="font-bold text-gray-800 text-sm">Mode Cepat (F2 Focus)</p>
                            </div>
                            <div @click="modeCepat = !modeCepat; savePrefs(); triggerToast('Mode Cepat: ' + (modeCepat ? 'Aktif' : 'Nonaktif'))"
                                 :class="modeCepat ? 'bg-[#0052cc]' : 'bg-gray-300'"
                                 class="w-11 h-6 rounded-full relative cursor-pointer flex items-center px-0.5 transition-colors">
                                <div :class="modeCepat ? 'right-0.5' : 'left-0.5'" class="w-5 h-5 bg-white rounded-full absolute shadow transition-all"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Section: Data & Sync -->
            <div class="bg-white rounded-xl border border-gray-200 p-6 mb-8">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                            <span class="material-symbols-rounded text-[22px]">sync_alt</span>
                        </div>
                        <h3 class="font-bold text-gray-800 text-sm">Sinkronisasi Data</h3>
                    </div>

                    <div class="flex items-center gap-6">
                        <div class="text-right">
                            <p class="text-[10px] font-bold text-gray-500 uppercase">DATABASE</p>
                            <p class="font-mono text-gray-800 font-bold text-xs">v14.2-local (OK)</p>
                        </div>
                        <button type="button"
                                @click="initOpening(); initSettings(); triggerToast('Semua data lokal berhasil disinkronkan!')"
                                class="bg-blue-700 hover:bg-blue-800 text-white font-bold text-xs py-2.5 px-5 rounded-lg shadow-sm flex items-center gap-2 transition-colors cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">sync</span>
                            <span>Sinkronisasi</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Toast Notification -->
            <div x-show="showToast"
                 x-transition.opacity
                 style="display: none;"
                 class="fixed bottom-6 right-6 z-50 bg-gray-900 text-white px-5 py-3.5 rounded-2xl shadow-xl flex items-center gap-3 border border-gray-800 text-xs font-semibold">
                <span class="material-symbols-rounded text-emerald-400 text-[20px]">check_circle</span>
                <span x-text="toastMessage"></span>
            </div>

        </div>
    </div>
</main>
</div>
@endsection
