@extends('layouts.app')

@section('title', 'Stock Opname Kasir - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    searchQuery: '',
    statusFilter: 'all', // all | selisih | sesuai | pending
    scanBarcode: '',
    items: [],
    showFinishModal: false,
    toastMessage: '',
    showToast: false,

    initOpname() {
        // Load master inventory products
        const raw = localStorage.getItem('pos_ilp_inventory_products_v1');
        let prods = [];
        if (raw) {
            try {
                prods = JSON.parse(raw);
            } catch(e) {
                prods = [];
            }
        }

        // Check if there is an in-progress opname draft
        const draft = localStorage.getItem('pos_ilp_active_opname_v1');
        let draftMap = {};
        if (draft) {
            try {
                const parsedDraft = JSON.parse(draft);
                parsedDraft.forEach(d => { draftMap[d.sku] = d; });
            } catch(e) {}
        }

        this.items = prods.map(p => {
            const existing = draftMap[p.sku];
            if (existing) {
                return existing;
            }
            return {
                sku: p.sku || p.barcode,
                nama: p.nama,
                kategori: p.kategori || 'Umum',
                harga: Number(p.hargaJual) || 0,
                stokSistem: Number(p.stok) || 0,
                stokFisik: Number(p.stok) || 0,
                selisih: 0,
                keterangan: 'Sesuai',
                isAudited: false
            };
        });
    },

    saveDraft() {
        localStorage.setItem('pos_ilp_active_opname_v1', JSON.stringify(this.items));
    },

    onPhysicalChange(item) {
        const val = parseInt(item.stokFisik, 10);
        item.stokFisik = isNaN(val) ? 0 : Math.max(0, val);
        item.selisih = item.stokFisik - item.stokSistem;
        if (item.selisih < 0) {
            if (item.keterangan === 'Sesuai' || !item.keterangan) item.keterangan = 'Hilang';
        } else if (item.selisih > 0) {
            if (item.keterangan === 'Sesuai' || !item.keterangan) item.keterangan = 'Lebih / Bonus';
        } else {
            item.keterangan = 'Sesuai';
        }
        this.saveDraft();
    },

    confirmRow(item) {
        item.isAudited = true;
        this.onPhysicalChange(item);
        this.saveDraft();
        this.triggerToast('Produk ' + item.nama + ' diverifikasi (' + item.stokFisik + ' fisik)');
    },

    handleScan() {
        if (!this.scanBarcode.trim()) return;
        const code = this.scanBarcode.trim().toLowerCase();
        const found = this.items.find(i => (i.sku || '').toLowerCase() === code || (i.nama || '').toLowerCase().includes(code));
        if (found) {
            found.stokFisik = (found.stokFisik || 0) + 1;
            this.confirmRow(found);
            this.searchQuery = found.nama;
            this.triggerToast('Scan berhasil: ' + found.nama + ' (+1)');
        } else {
            alert('Produk dengan barcode/kode ' + this.scanBarcode + ' tidak ditemukan di katalog!');
        }
        this.scanBarcode = '';
    },

    get filteredItems() {
        return this.items.filter(i => {
            if (this.statusFilter === 'selisih' && i.selisih === 0) return false;
            if (this.statusFilter === 'sesuai' && i.selisih !== 0) return false;
            if (this.statusFilter === 'pending' && i.isAudited) return false;

            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                return (i.nama || '').toLowerCase().includes(q) || (i.sku || '').toLowerCase().includes(q);
            }
            return true;
        });
    },

    get totalCount() {
        return this.items.length;
    },

    get auditedCount() {
        return this.items.filter(i => i.isAudited).length;
    },

    get auditPercentage() {
        if (!this.totalCount) return 0;
        return Math.round((this.auditedCount / this.totalCount) * 100);
    },

    get totalVarianceUnits() {
        return this.items.reduce((sum, i) => sum + (i.isAudited ? i.selisih : 0), 0);
    },

    get totalVarianceValue() {
        return this.items.reduce((sum, i) => sum + (i.isAudited ? (i.selisih * i.harga) : 0), 0);
    },

    finishOpname() {
        if (this.auditedCount === 0) {
            alert('Belum ada produk yang diaudit atau diverifikasi!');
            return;
        }
        this.showFinishModal = true;
    },

    executeFinishOpname() {
        // 1. Update master inventory products in pos_ilp_inventory_products_v1
        const raw = localStorage.getItem('pos_ilp_inventory_products_v1');
        if (raw) {
            try {
                let prods = JSON.parse(raw);
                this.items.forEach(it => {
                    if (it.isAudited) {
                        const target = prods.find(p => p.sku === it.sku || p.barcode === it.sku);
                        if (target) {
                            target.stok = it.stokFisik;
                        }
                    }
                });
                localStorage.setItem('pos_ilp_inventory_products_v1', JSON.stringify(prods));
            } catch(e) {
                console.error(e);
            }
        }

        // 2. Save opname record in history
        const historyRecord = {
            id: 'OPN-' + Date.now(),
            date: new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) + ' • ' +
                  new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' WIB',
            auditor: (this.activeSession?.cashierName || 'Kasir') + ' (Kasir)',
            terminal: this.activeSession?.terminalLabel || 'Kasir 01',
            totalAudited: this.auditedCount,
            totalVariance: this.totalVarianceUnits,
            totalValueVariance: this.totalVarianceValue,
            items: this.items.filter(i => i.isAudited)
        };

        const existingHist = localStorage.getItem('pos_ilp_stock_opname_history_v1');
        let hist = [];
        if (existingHist) {
            try { hist = JSON.parse(existingHist); } catch(e){}
        }
        hist.unshift(historyRecord);
        localStorage.setItem('pos_ilp_stock_opname_history_v1', JSON.stringify(hist));

        // 3. Clear draft
        localStorage.removeItem('pos_ilp_active_opname_v1');

        this.showFinishModal = false;
        this.triggerToast('Stock Opname berhasil diselesaikan! Stok master produk telah disesuaikan.');

        // Re-init with new system stock
        this.initOpname();
    },

    exportData() {
        window.print();
    },

    triggerToast(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3500);
    }
}"
x-init="initOpening(); initOpname();"
@pos-session-sync.window="initOpening()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">

    <!-- Sidebar -->
    <x-cashier-sidebar active="stock_opname" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">

        <!-- Top Header -->
        <header class="h-[68px] bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-3">
                <h1 class="text-[16px] font-black text-gray-900 tracking-tight">Stock Opname Kasir</h1>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200" x-text="activeSession.terminalLabel || 'Kasir 01'"></span>
            </div>

            <div class="flex items-center gap-3 text-gray-600">
                <button type="button"
                        @click="finishOpname()"
                        class="bg-[#0052cc] hover:bg-[#0043a8] text-white px-4 py-2 rounded-xl font-bold text-xs flex items-center gap-2 shadow-xs transition-colors cursor-pointer">
                    <span class="material-symbols-rounded text-[18px]">check_circle</span>
                    <span>Selesaikan Stock Opname</span>
                </button>
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-8 flex flex-col">
            <div class="max-w-[1400px] w-full mx-auto flex flex-col flex-1">

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <!-- Card 1: TOTAL PRODUK -->
                    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-xs flex items-center gap-4">
                        <div class="w-12 h-12 rounded-lg bg-[#dbeafe]/60 text-[#0052cc] flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">assignment_turned_in</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-500 tracking-wider mb-1 uppercase">TOTAL PRODUK</p>
                            <h3 class="text-[22px] font-black text-gray-900 leading-none font-mono tracking-tight" x-text="totalCount"></h3>
                        </div>
                    </div>

                    <!-- Card 2: SUDAH AUDIT -->
                    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-xs flex items-center gap-4">
                        <div class="w-12 h-12 rounded-lg bg-green-50 text-green-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">done_all</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-500 tracking-wider mb-1 uppercase">SUDAH AUDIT</p>
                            <div class="flex items-baseline gap-1.5">
                                <h3 class="text-[22px] font-black text-gray-900 leading-none font-mono tracking-tight" x-text="auditedCount"></h3>
                                <span class="text-[12px] font-semibold text-gray-400 font-mono" x-text="'(' + auditPercentage + '%)'"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Card 3: SELISIH STOK -->
                    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-xs flex items-center gap-4">
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center shrink-0"
                             :class="totalVarianceUnits === 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500'">
                            <span class="material-symbols-rounded text-[24px]">warning</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-500 tracking-wider mb-1 uppercase">SELISIH STOK</p>
                            <h3 class="text-[22px] font-black leading-none font-mono tracking-tight"
                                :class="totalVarianceUnits === 0 ? 'text-emerald-600' : 'text-red-500'"
                                x-text="(totalVarianceUnits > 0 ? '+' : '') + totalVarianceUnits"></h3>
                        </div>
                    </div>

                    <!-- Card 4: NILAI SELISIH -->
                    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-xs flex items-center gap-4">
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center shrink-0"
                             :class="totalVarianceValue === 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-orange-50 text-orange-500'">
                            <span class="material-symbols-rounded text-[24px]">payments</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-500 tracking-wider mb-1 uppercase">NILAI SELISIH (RP)</p>
                            <h3 class="text-[22px] font-black leading-none font-mono tracking-tight"
                                :class="totalVarianceValue === 0 ? 'text-emerald-600' : 'text-orange-500'"
                                x-text="'Rp ' + totalVarianceValue.toLocaleString('id-ID')"></h3>
                        </div>
                    </div>
                </div>

                <!-- Main Table Container -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-xs overflow-hidden flex flex-col mb-6">

                    <!-- Search and Filter Bar -->
                    <div class="p-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4">
                        <div class="relative w-full max-w-[360px]">
                            <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                            <input type="text"
                                   x-model="searchQuery"
                                   placeholder="Cari produk berdasarkan nama atau SKU..."
                                   class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-[#0052cc]/20 focus:border-[#0052cc] text-xs font-medium text-gray-700 placeholder-gray-400">
                        </div>

                        <!-- Status Filter Tabs -->
                        <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-lg text-xs">
                            <button type="button" @click="statusFilter = 'all'" :class="statusFilter === 'all' ? 'bg-white font-bold text-gray-900 shadow-2xs' : 'text-gray-500'" class="px-3 py-1 rounded cursor-pointer">Semua</button>
                            <button type="button" @click="statusFilter = 'selisih'" :class="statusFilter === 'selisih' ? 'bg-white font-bold text-red-600 shadow-2xs' : 'text-gray-500'" class="px-3 py-1 rounded cursor-pointer">Ada Selisih</button>
                            <button type="button" @click="statusFilter = 'sesuai'" :class="statusFilter === 'sesuai' ? 'bg-white font-bold text-green-700 shadow-2xs' : 'text-gray-500'" class="px-3 py-1 rounded cursor-pointer">Sesuai (0)</button>
                            <button type="button" @click="statusFilter = 'pending'" :class="statusFilter === 'pending' ? 'bg-white font-bold text-amber-700 shadow-2xs' : 'text-gray-500'" class="px-3 py-1 rounded cursor-pointer">Belum Diaudit</button>
                        </div>

                        <div class="flex items-center gap-2.5 shrink-0">
                            <button type="button"
                                    @click="exportData()"
                                    class="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors cursor-pointer">
                                <span class="material-symbols-rounded text-[16px] text-gray-500">print</span>
                                <span>Cetak / Export</span>
                            </button>
                        </div>
                    </div>

                    <!-- Data Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs whitespace-nowrap">
                            <thead class="bg-[#f8f9fa] border-b border-gray-200 text-gray-500 text-[10px] font-black tracking-wider uppercase">
                                <tr>
                                    <th class="px-6 py-3.5 w-[38%]">PRODUK</th>
                                    <th class="px-6 py-3.5 text-center">STOK SISTEM</th>
                                    <th class="px-6 py-3.5 text-center">STOK FISIK</th>
                                    <th class="px-6 py-3.5 text-center">SELISIH</th>
                                    <th class="px-6 py-3.5 text-left w-[16%]">KETERANGAN</th>
                                    <th class="px-6 py-3.5 text-center">AKSI</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 divide-y divide-gray-100">
                                <template x-for="item in filteredItems" :key="item.sku">
                                    <tr class="hover:bg-gray-50/80 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-900 text-xs" x-text="item.nama"></span>
                                                <span class="text-[11px] text-gray-400 font-mono mt-0.5" x-text="item.sku"></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center font-bold text-gray-800 font-mono text-sm" x-text="item.stokSistem"></td>
                                        <td class="px-6 py-4">
                                            <div class="flex justify-center">
                                                <input type="number"
                                                       x-model="item.stokFisik"
                                                       @input="onPhysicalChange(item)"
                                                       class="w-[72px] text-center px-2 py-1 border rounded-md focus:outline-none text-xs font-bold font-mono text-gray-800"
                                                       :class="item.isAudited ? (item.selisih === 0 ? 'border-green-500 bg-green-50/30' : 'border-red-400 bg-red-50/30') : 'border-gray-300'">
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="font-bold font-mono text-xs"
                                                  :class="item.selisih === 0 ? 'text-green-600' : 'text-red-500'"
                                                  x-text="(item.selisih > 0 ? '+' : '') + item.selisih"></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <select x-model="item.keterangan"
                                                    @change="saveDraft()"
                                                    class="bg-white border border-gray-200 rounded-md px-2.5 py-1 text-xs font-medium text-gray-700 focus:outline-none focus:border-[#0052cc]">
                                                <option value="Sesuai">Sesuai</option>
                                                <option value="Hilang">Hilang</option>
                                                <option value="Rusak">Rusak</option>
                                                <option value="Kadaluwarsa">Kadaluwarsa</option>
                                                <option value="Salah Input">Salah Input</option>
                                                <option value="Lebih / Bonus">Lebih / Bonus</option>
                                            </select>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <template x-if="!item.isAudited">
                                                <button type="button"
                                                        @click="confirmRow(item)"
                                                        class="bg-[#0052cc] hover:bg-blue-700 text-white px-3 py-1 rounded-md font-bold text-[11px] shadow-2xs transition-colors cursor-pointer">
                                                    Verifikasi
                                                </button>
                                            </template>
                                            <template x-if="item.isAudited">
                                                <div class="inline-flex items-center gap-1 bg-green-100 text-green-700 px-2.5 py-1 rounded-md font-bold text-[11px]">
                                                    <span class="material-symbols-rounded text-[14px]">check</span>
                                                    <span>Verified</span>
                                                </div>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="filteredItems.length === 0">
                                    <td colspan="6" class="text-center py-12 text-gray-400">
                                        <span class="material-symbols-rounded text-4xl mb-2 text-gray-300">inventory_2</span>
                                        <p class="font-bold text-gray-600">Tidak ada produk yang cocok</p>
                                        <p class="text-xs text-gray-400 mt-0.5">Silakan ganti kata kunci atau filter.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Table Footer -->
                    <div class="bg-[#f8f9fa] border-t border-gray-100 px-6 py-3 flex items-center justify-between text-xs">
                        <p class="text-gray-500 font-medium" x-text="'Menampilkan ' + filteredItems.length + ' dari ' + totalCount + ' produk'"></p>
                    </div>
                </div>

                <!-- Bottom Section: Quick Scan & Audit Progress -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-5">
                    <!-- Quick Scan Mode -->
                    <form @submit.prevent="handleScan()" class="lg:col-span-8 bg-[#eef2ff] border border-blue-200/80 rounded-xl p-5 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-xl bg-[#0052cc] text-white flex items-center justify-center shrink-0 shadow-2xs">
                                <span class="material-symbols-rounded text-[24px]">qr_code_scanner</span>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-[#0052cc]">Quick Scan Barcode Scanner</h4>
                                <p class="text-xs text-gray-600 mt-0.5">Arahkan scanner ke barcode atau ketik SKU produk di bawah.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <input type="text"
                                   x-model="scanBarcode"
                                   placeholder="Scan barcode di sini..."
                                   class="px-3.5 py-2 bg-white border border-blue-300 rounded-xl text-xs font-mono focus:outline-none focus:border-[#0052cc] w-full sm:w-60" />
                            <button type="submit"
                                    class="bg-[#0052cc] hover:bg-blue-700 text-white px-4 py-2 rounded-xl font-bold text-xs shadow-2xs transition-colors shrink-0 cursor-pointer">
                                Hitung (+1)
                            </button>
                        </div>
                    </form>

                    <!-- Audit Progress -->
                    <div class="lg:col-span-4 bg-white border border-gray-200 rounded-xl p-5 flex flex-col justify-center shadow-xs">
                        <div class="flex justify-between items-center mb-2">
                            <h4 class="text-xs font-bold text-gray-900">Audit Progress Toko</h4>
                            <span class="text-xs font-bold text-[#0052cc]" x-text="auditPercentage + '%'"></span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2.5 mb-2 overflow-hidden">
                            <div class="bg-[#0052cc] h-2.5 rounded-full transition-all duration-300" :style="'width: ' + auditPercentage + '%'"></div>
                        </div>
                        <div class="text-right">
                            <span class="text-[11px] font-medium text-gray-500" x-text="auditedCount + ' dari ' + totalCount + ' produk selesai diperiksa'"></span>
                        </div>
                    </div>
                </div>

                <!-- Bottom Action Button -->
                <div>
                    <button type="button"
                            @click="finishOpname()"
                            class="bg-[#0052cc] hover:bg-[#0043a8] text-white px-5 py-2.5 rounded-xl font-bold text-xs inline-flex items-center gap-2 shadow-xs transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">check_circle</span>
                        <span>Selesaikan Stock Opname</span>
                    </button>
                </div>

            </div>
        </div>
    </main>

    <!-- MODAL SELESAIKAN STOCK OPNAME -->
    <template x-teleport="body">
        <div x-show="showFinishModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div @click.away="showFinishModal = false"
                 class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-md overflow-hidden animate-scale-up">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-rounded text-[#0052cc] text-[22px]">verified</span>
                        <h3 class="font-bold text-gray-900 text-sm">Konfirmasi Penyelesaian Opname</h3>
                    </div>
                    <button @click="showFinishModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs font-sans">
                    <p class="text-gray-600">
                        Anda akan menyelesaikan sesi Stock Opname ini. Jumlah stok fisik yang diverifikasi akan otomatis menggantikan angka stok sistem di katalog master.
                    </p>

                    <div class="bg-slate-50 p-4 rounded-xl border border-gray-200 space-y-2 font-mono">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Produk Diverifikasi:</span>
                            <span class="font-bold text-gray-900" x-text="auditedCount + ' item'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Total Selisih Unit:</span>
                            <span class="font-bold" :class="totalVarianceUnits === 0 ? 'text-green-600' : 'text-red-500'" x-text="(totalVarianceUnits > 0 ? '+' : '') + totalVarianceUnits + ' unit'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Estimasi Nilai Selisih:</span>
                            <span class="font-bold" :class="totalVarianceValue === 0 ? 'text-green-600' : 'text-orange-600'" x-text="'Rp ' + totalVarianceValue.toLocaleString('id-ID')"></span>
                        </div>
                    </div>

                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-900 text-[11px] flex items-start gap-2">
                        <span class="material-symbols-rounded text-amber-600 text-[18px] shrink-0">info</span>
                        <span>Riwayat stock opname ini akan dicatat ke dalam log audit berkala sistem.</span>
                    </div>

                    <div class="pt-2 flex justify-end gap-2">
                        <button type="button"
                                @click="showFinishModal = false"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold cursor-pointer">
                            Periksa Lagi
                        </button>
                        <button type="button"
                                @click="executeFinishOpname()"
                                class="px-5 py-2 bg-[#0052cc] hover:bg-blue-700 text-white rounded-xl font-bold cursor-pointer">
                            Ya, Selesaikan & Terapkan
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </template>

    <!-- TOAST NOTIFICATION -->
    <div x-show="showToast"
         x-transition.opacity
         style="display: none;"
         class="fixed bottom-6 right-6 z-50 bg-gray-900 text-white px-5 py-3.5 rounded-2xl shadow-xl flex items-center gap-3 border border-gray-800 text-xs font-semibold">
        <span class="material-symbols-rounded text-emerald-400 text-[20px]">check_circle</span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
