@extends('layouts.app')

@section('title', 'Laporan Operasional - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    activeTab: 'all', // all | barang_masuk | retur_keluar | retur_masuk | stock_opname
    searchQuery: '',

    operationalLogs: [
        {
            id: 'LOG-OP-001',
            type: 'barang_masuk',
            typeLabel: 'Penerimaan Barang',
            badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200',
            icon: 'inventory_2',
            refNo: 'SJ-DC-20261024-001',
            bastNo: 'BAST-IN-20261024-001',
            date: '24 Oktober 2026 • 09:30 WIB',
            operator: 'Budi Kurniawan (Kasir)',
            details: 'Penerimaan transfer 4 item dari Gudang DC Pusat (Beras, Minyak, Gula, Terigu). Fisik 100% lengkap tanpa selisih.',
            status: 'DITERIMA_LENGKAP'
        },
        {
            id: 'LOG-OP-002',
            type: 'retur_keluar',
            typeLabel: 'Pengajuan Retur Toko',
            badgeClass: 'bg-amber-50 text-amber-700 border-amber-200',
            icon: 'assignment_return',
            refNo: 'RET-20261024-001',
            bastNo: '-',
            date: '24 Oktober 2026 • 10:15 WIB',
            operator: 'Budi Kurniawan (Kasir)',
            details: 'Pengajuan retur ke DC Pusat: 2 botol Sirup Marjan pecah kemasan saat handling di lorong. Menunggu serah terima fisik kurir DC.',
            status: 'DISETUJUI_SPV'
        },
        {
            id: 'LOG-OP-003',
            type: 'retur_masuk',
            typeLabel: 'Retur Masuk DC',
            badgeClass: 'bg-purple-50 text-purple-700 border-purple-200',
            icon: 'assignment_returned',
            refNo: 'DO-RET-20261024-001',
            bastNo: 'BAST-RET-20261024-001',
            date: '24 Oktober 2026 • 11:00 WIB',
            operator: 'Budi Kurniawan (Kasir)',
            details: 'Penerimaan barang pengganti garansi dari DC: 3 kaleng Biskuit Khong Guan kondisi prima, lot baru.',
            status: 'SELESAI_DIVERIFIKASI'
        },
        {
            id: 'LOG-OP-004',
            type: 'stock_opname',
            typeLabel: 'Stock Opname',
            badgeClass: 'bg-blue-50 text-blue-700 border-blue-200',
            icon: 'fact_check',
            refNo: 'SO-20261023-01',
            bastNo: 'DOC-SO-01',
            date: '23 Oktober 2026 • 21:30 WIB',
            operator: 'Budi Kurniawan (Kasir) & Supervisor',
            details: 'Stock counting Lorong A (Sembako & Kebutuhan Pokok): 15 SKU dihitung fisik, akurasi stok 99.4%, selisih 1 botol saus tiram.',
            status: 'SELESAI_REKONSILIASI'
        }
    ],

    get filteredLogs() {
        return this.operationalLogs.filter(log => {
            if (this.activeTab !== 'all' && log.type !== this.activeTab) return false;
            if (this.searchQuery) {
                const q = this.searchQuery.toLowerCase();
                return log.refNo.toLowerCase().includes(q) ||
                       log.details.toLowerCase().includes(q) ||
                       log.operator.toLowerCase().includes(q) ||
                       (log.bastNo && log.bastNo.toLowerCase().includes(q));
            }
            return true;
        });
    }
}"
x-init="initOpening()"
@pos-session-sync.window="initOpening()"
class="flex flex-col h-screen w-full font-sans">
    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar -->
        <x-cashier-sidebar active="laporan_operasional" />

        <!-- Main Area -->
        <div class="flex-1 flex flex-col min-w-0 bg-[#fafafa]">
            <!-- Header -->
            <x-cashier-header title="Laporan Operasional" />

            <!-- Content -->
            <main class="flex-1 overflow-y-auto p-6 md:p-8 bg-[#f8f9fb]">

                <!-- Page Top Action Bar -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Laporan Aktivitas Operasional</h1>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-blue-100 text-[#0052cc]">
                                Toko & Logistik
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">Log penerimaan barang masuk, retur logistik, dan audit stock opname.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="window.print()"
                                class="px-4 py-2 bg-[#0052cc] hover:bg-blue-700 text-white rounded-xl text-xs font-bold flex items-center gap-2 transition-colors shadow-2xs cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">print</span>
                            <span>Cetak Log Operasional</span>
                        </button>
                    </div>
                </div>

                <!-- 4 KPI Stat Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-xl p-5 border border-emerald-200 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-extrabold text-emerald-800 uppercase tracking-wider">Barang Masuk DC</span>
                            <span class="material-symbols-rounded text-emerald-600 text-[20px]">inventory_2</span>
                        </div>
                        <h2 class="text-2xl font-black text-emerald-700 mt-1.5 font-mono">1 BAST</h2>
                        <span class="text-xs font-bold text-emerald-600 mt-1 block">100% Terverifikasi Lengkap</span>
                    </div>

                    <div class="bg-white rounded-xl p-5 border border-amber-200 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-extrabold text-amber-800 uppercase tracking-wider">Retur Toko Diajukan</span>
                            <span class="material-symbols-rounded text-amber-600 text-[20px]">assignment_return</span>
                        </div>
                        <h2 class="text-2xl font-black text-amber-700 mt-1.5 font-mono">1 Pengajuan</h2>
                        <span class="text-xs font-bold text-amber-600 mt-1 block">Menunggu Serah Terima Fisik</span>
                    </div>

                    <div class="bg-white rounded-xl p-5 border border-purple-200 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-extrabold text-purple-800 uppercase tracking-wider">Klaim Garansi Diterima</span>
                            <span class="material-symbols-rounded text-purple-600 text-[20px]">assignment_returned</span>
                        </div>
                        <h2 class="text-2xl font-black text-purple-700 mt-1.5 font-mono">1 BAST</h2>
                        <span class="text-xs font-bold text-purple-600 mt-1 block">Barang Pengganti Layak</span>
                    </div>

                    <div class="bg-white rounded-xl p-5 border border-blue-200 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-extrabold text-blue-800 uppercase tracking-wider">Stock Opname</span>
                            <span class="material-symbols-rounded text-blue-600 text-[20px]">fact_check</span>
                        </div>
                        <h2 class="text-2xl font-black text-[#0052cc] mt-1.5 font-mono">99.4%</h2>
                        <span class="text-xs font-bold text-blue-600 mt-1 block">Akurasi Fisik vs Buku</span>
                    </div>
                </div>

                <!-- Tabs & Search -->
                <div class="bg-white rounded-t-xl p-5 border border-gray-200 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-4 border-b-0">
                    <div class="flex items-center gap-1.5 bg-gray-100 p-1 rounded-xl w-full sm:w-auto overflow-x-auto">
                        <button type="button"
                                @click="activeTab = 'all'"
                                :class="activeTab === 'all' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                class="px-3 py-1.5 rounded-lg text-xs transition-all whitespace-nowrap cursor-pointer">
                            Semua Aktivitas
                        </button>
                        <button type="button"
                                @click="activeTab = 'barang_masuk'"
                                :class="activeTab === 'barang_masuk' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                class="px-3 py-1.5 rounded-lg text-xs transition-all whitespace-nowrap cursor-pointer">
                            Barang Masuk
                        </button>
                        <button type="button"
                                @click="activeTab = 'retur_keluar'"
                                :class="activeTab === 'retur_keluar' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                class="px-3 py-1.5 rounded-lg text-xs transition-all whitespace-nowrap cursor-pointer">
                            Retur ke DC
                        </button>
                        <button type="button"
                                @click="activeTab = 'retur_masuk'"
                                :class="activeTab === 'retur_masuk' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                class="px-3 py-1.5 rounded-lg text-xs transition-all whitespace-nowrap cursor-pointer">
                            Retur Masuk
                        </button>
                        <button type="button"
                                @click="activeTab = 'stock_opname'"
                                :class="activeTab === 'stock_opname' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                class="px-3 py-1.5 rounded-lg text-xs transition-all whitespace-nowrap cursor-pointer">
                            Stock Opname
                        </button>
                    </div>

                    <div class="relative w-full sm:w-64">
                        <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="Cari no referensi, petugas..."
                               class="w-full pl-9 pr-3.5 py-1.5 bg-slate-50 border border-gray-200 rounded-lg text-xs font-medium text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#0052cc] focus:bg-white transition-colors" />
                    </div>
                </div>

                <!-- Activity Log List -->
                <div class="bg-white rounded-b-xl border border-gray-200 shadow-xs divide-y divide-gray-100 overflow-hidden">
                    <template x-for="log in filteredLogs" :key="log.id">
                        <div class="p-5 hover:bg-blue-50/20 transition-colors flex flex-col md:flex-row md:items-start justify-between gap-4">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border"
                                     :class="log.badgeClass">
                                    <span class="material-symbols-rounded text-[20px]" x-text="log.icon"></span>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider border"
                                              :class="log.badgeClass"
                                              x-text="log.typeLabel"></span>
                                        <span class="font-mono font-bold text-gray-900 text-xs" x-text="log.refNo"></span>
                                        <template x-if="log.bastNo && log.bastNo !== '-'">
                                            <span class="text-[11px] text-gray-500 font-mono">(BAST: <span x-text="log.bastNo"></span>)</span>
                                        </template>
                                    </div>
                                    <p class="text-xs text-gray-700 leading-relaxed font-medium" x-text="log.details"></p>
                                    <div class="flex items-center gap-3 text-[11px] text-gray-400 pt-1">
                                        <span class="flex items-center gap-1">
                                            <span class="material-symbols-rounded text-[14px]">schedule</span>
                                            <span x-text="log.date"></span>
                                        </span>
                                        <span>•</span>
                                        <span class="flex items-center gap-1">
                                            <span class="material-symbols-rounded text-[14px]">person</span>
                                            <span x-text="log.operator"></span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex md:flex-col items-center md:items-end justify-between shrink-0 gap-2">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-black tracking-wider uppercase bg-emerald-50 text-emerald-800 border border-emerald-200"
                                      x-text="log.status.replace('_', ' ')"></span>
                                <span class="text-[10px] font-mono text-gray-400" x-text="log.id"></span>
                            </div>
                        </div>
                    </template>
                    <div x-show="filteredLogs.length === 0" class="p-12 text-center text-gray-400">
                        <span class="material-symbols-rounded text-4xl mb-2 text-gray-300">history_edu</span>
                        <p class="font-bold text-gray-600">Tidak ada catatan operasional</p>
                        <p class="text-xs text-gray-400 mt-0.5">Silakan pilih kategori tab lain atau ubah kata kunci pencarian.</p>
                    </div>
                </div>

            </main>
        </div>
    </div>
</div>
@endsection
