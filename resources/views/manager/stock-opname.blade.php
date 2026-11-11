@extends('layouts.app')

@section('title', 'Stock Opname & Variance Report - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    activeSessionId: null,
    currentSession: null,
    sessions: [],
    items: [],
    
    // Filters & Scanner
    searchQuery: '',
    statusFilter: 'all', // all | selisih | sesuai | pending
    scanBarcode: '',
    isLoading: false,
    isSubmitting: false,
    
    // Modals
    showNewSessionModal: false,
    newSessionTitle: 'Stock Opname ' + new Date().toLocaleDateString('id-ID', { month: 'long', year: 'numeric' }),
    newSessionBranch: 'Pusat',
    newSessionAuditor: 'Manager Koperasi',
    
    showFinishModal: false,
    finishNotes: '',

    // Toast
    toastMessage: '',
    toastType: 'success',
    showToast: false,

    async initOpnamePage() {
        await this.loadSessions();
    },

    async loadSessions() {
        this.isLoading = true;
        try {
            const res = await fetch('/api/inventory/stock-opname', { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.sessions = json.data;
                    // If active draft exists, load it
                    const draft = this.sessions.find(s => s.status === 'DRAFT');
                    if (draft) {
                        await this.loadSessionDetail(draft.id);
                    } else if (this.sessions.length > 0) {
                        await this.loadSessionDetail(this.sessions[0].id);
                    }
                }
            }
        } catch (e) {
            console.error('Error fetching opname sessions:', e);
        } finally {
            this.isLoading = false;
        }
    },

    async loadSessionDetail(id) {
        this.activeSessionId = id;
        this.isLoading = true;
        try {
            const res = await fetch(`/api/inventory/stock-opname/${id}`, { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.currentSession = json.data;
                    this.items = json.data.items || [];
                }
            }
        } catch (e) {
            this.triggerToast('Gagal memuat detail sesi opname.', 'error');
        } finally {
            this.isLoading = false;
        }
    },

    async createNewSession() {
        if (!this.newSessionTitle.trim()) return;
        this.isSubmitting = true;

        try {
            const res = await fetch('/api/inventory/stock-opname', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    title: this.newSessionTitle,
                    branch: this.newSessionBranch,
                    conducted_by: this.newSessionAuditor,
                    notes: 'Snapshot stok sistem tanpa mengganggu operasional kasir'
                })
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.showNewSessionModal = false;
                this.triggerToast(json.message);
                await this.loadSessions();
            } else {
                this.triggerToast(json.message || 'Gagal membuat sesi.', 'error');
            }
        } catch (e) {
            this.triggerToast('Koneksi terputus.', 'error');
        } finally {
            this.isSubmitting = false;
        }
    },

    async verifyRow(item) {
        const val = parseInt(item.physical_stock, 10);
        const physical = isNaN(val) ? 0 : Math.max(0, val);

        try {
            const res = await fetch(`/api/inventory/stock-opname/${this.activeSessionId}/verify-item`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    item_id: item.id,
                    physical_stock: physical,
                    notes: item.variance_reason
                })
            });

            const json = await res.json();
            if (res.ok && json.success) {
                item.variance = json.data.variance;
                item.variance_value = json.data.variance_value;
                item.variance_reason = json.data.variance_reason;
                item.is_audited = true;
                this.triggerToast(`Verifikasi [${item.product_name}]: ${physical} fisik (selisih: ${item.variance})`);
            }
        } catch (e) {
            this.triggerToast('Gagal memverifikasi item.', 'error');
        }
    },

    async handleBarcodeScan() {
        if (!this.scanBarcode.trim() || !this.activeSessionId) return;
        const code = this.scanBarcode.trim();

        try {
            const res = await fetch(`/api/inventory/stock-opname/${this.activeSessionId}/verify-item`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    barcode: code,
                    increment_scan: true
                })
            });

            const json = await res.json();
            if (res.ok && json.success) {
                const updated = json.data;
                const idx = this.items.findIndex(i => i.id === updated.id);
                if (idx !== -1) {
                    this.items[idx] = updated;
                }
                this.triggerToast(`Scan +1 [${updated.product_name}]: ${updated.physical_stock} fisik`);
            } else {
                this.triggerToast(json.message || 'Produk tidak ditemukan.', 'error');
            }
        } catch (e) {
            this.triggerToast('Koneksi terputus saat scan.', 'error');
        } finally {
            this.scanBarcode = '';
        }
    },

    async completeOpname() {
        if (!this.activeSessionId) return;
        this.isSubmitting = true;

        try {
            const res = await fetch(`/api/inventory/stock-opname/${this.activeSessionId}/complete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    notes: this.finishNotes || 'Audit fisik selesai disetujui.'
                })
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.showFinishModal = false;
                this.triggerToast(json.message);
                await this.loadSessions();
            } else {
                this.triggerToast(json.message || 'Gagal menyelesaikan opname.', 'error');
            }
        } catch (e) {
            this.triggerToast('Koneksi terputus.', 'error');
        } finally {
            this.isSubmitting = false;
        }
    },

    get filteredItems() {
        return this.items.filter(i => {
            if (this.statusFilter === 'selisih' && i.variance === 0) return false;
            if (this.statusFilter === 'sesuai' && (i.variance !== 0 || !i.is_audited)) return false;
            if (this.statusFilter === 'pending' && i.is_audited) return false;

            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                return (i.product_name || '').toLowerCase().includes(q) || (i.product_sku || '').toLowerCase().includes(q);
            }
            return true;
        });
    },

    get totalItemsCount() { return this.items.length; },
    get auditedCount() { return this.items.filter(i => i.is_audited).length; },
    get auditPercent() { return this.totalItemsCount > 0 ? Math.round((this.auditedCount / this.totalItemsCount) * 100) : 0; },
    get totalVarianceUnits() { return this.items.reduce((sum, i) => sum + (i.is_audited ? i.variance : 0), 0); },
    get totalVarianceValue() { return this.items.reduce((sum, i) => sum + (i.is_audited ? (i.variance_value || 0) : 0), 0); },

    exportCsvUrl() {
        return this.activeSessionId ? `/api/inventory/stock-opname/${this.activeSessionId}/export-csv` : '#';
    },

    triggerToast(msg, type = 'success') {
        this.toastMessage = msg;
        this.toastType = type;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3500);
    }
}"
x-init="initOpening(); initOpnamePage();"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden relative">

    <!-- Sidebar -->
    <x-manager-sidebar active="stock_opname" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        
        <!-- Top Header -->
        <header class="h-[72px] bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-3">
                <span class="material-symbols-rounded text-[#0052cc] text-[26px]">fact_check</span>
                <div>
                    <h1 class="text-[18px] font-black text-gray-900 tracking-tight leading-none mb-0.5">Stock Opname &amp; Variance Report</h1>
                    <p class="text-xs text-gray-400">Pencatatan fisik tanpa mengganggu operasional penjualan toko</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <template x-if="currentSession && currentSession.status === 'DRAFT'">
                    <button type="button"
                            @click="finishNotes = ''; showFinishModal = true;"
                            :disabled="auditedCount === 0"
                            class="bg-[#0052cc] hover:bg-[#0043a8] text-white px-4 py-2 rounded-xl font-bold text-xs flex items-center gap-2 shadow-xs transition-colors disabled:opacity-40 active:scale-95">
                        <span class="material-symbols-rounded text-[18px]">verified</span>
                        <span>Selesaikan &amp; Terapkan Penyesuaian</span>
                    </button>
                </template>

                <button type="button"
                        @click="showNewSessionModal = true"
                        class="bg-gray-900 hover:bg-black text-white px-4 py-2 rounded-xl font-bold text-xs flex items-center gap-2 shadow-xs transition-colors active:scale-95">
                    <span class="material-symbols-rounded text-[18px]">add_circle</span>
                    <span>Sesi Opname Baru</span>
                </button>
            </div>
        </header>

        <!-- Workspace -->
        <div class="flex-1 overflow-y-auto p-8 flex flex-col">
            
            <!-- Session Selector Bar -->
            <div class="bg-white rounded-2xl border border-gray-200/90 p-3 shadow-xs mb-6 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-rounded text-gray-400 text-[20px]">history</span>
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Sesi Opname:</span>
                    <select :value="activeSessionId"
                            @change="loadSessionDetail($event.target.value)"
                            class="px-3 py-1.5 bg-gray-50 border border-gray-300 rounded-xl text-xs font-bold text-gray-800 focus:outline-none">
                        <template x-for="s in sessions" :key="s.id">
                            <option :value="s.id" x-text="s.opname_code + ' - ' + s.title + ' [' + s.status + ']'"></option>
                        </template>
                    </select>
                </div>

                <div class="flex items-center gap-3">
                    <template x-if="currentSession">
                        <span class="text-xs font-bold px-2.5 py-0.5 rounded-full border"
                              :class="currentSession.status === 'DRAFT' ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-emerald-50 text-emerald-800 border-emerald-200'"
                              x-text="'STATUS: ' + currentSession.status">
                        </span>
                    </template>
                    <a :href="exportCsvUrl()"
                       download
                       class="px-3 py-1.5 bg-white border border-gray-200 hover:bg-gray-50 rounded-xl text-xs font-bold text-gray-700 flex items-center gap-1.5 shadow-xs transition-colors">
                        <span class="material-symbols-rounded text-[16px] text-emerald-600">table_view</span>
                        <span>Ekspor Variance Report (CSV)</span>
                    </a>
                </div>
            </div>

            <!-- 4 KPI Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                <!-- Total Produk -->
                <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                        <span class="material-symbols-rounded text-[24px]">inventory_2</span>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-0.5">TOTAL ITEM AUDIT</p>
                        <h3 class="text-[22px] font-black text-gray-900 leading-none font-mono" x-text="totalItemsCount">0</h3>
                    </div>
                </div>

                <!-- Sudah Diaudit -->
                <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <span class="material-symbols-rounded text-[24px]">check_circle</span>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-0.5">SUDAH DIVERIFIKASI</p>
                        <div class="flex items-baseline gap-1.5">
                            <h3 class="text-[22px] font-black text-gray-900 leading-none font-mono" x-text="auditedCount">0</h3>
                            <span class="text-xs font-bold text-emerald-600 font-mono" x-text="'(' + auditPercent + '%)'"></span>
                        </div>
                    </div>
                </div>

                <!-- Selisih Stok (Units) -->
                <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                         :class="totalVarianceUnits === 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'">
                        <span class="material-symbols-rounded text-[24px]">difference</span>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-0.5">TOTAL SELISIH UNIT</p>
                        <h3 class="text-[22px] font-black leading-none font-mono"
                            :class="totalVarianceUnits === 0 ? 'text-emerald-600' : 'text-red-600'"
                            x-text="(totalVarianceUnits > 0 ? '+' : '') + totalVarianceUnits">0</h3>
                    </div>
                </div>

                <!-- Nominal Selisih (Rp) -->
                <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                         :class="totalVarianceValue === 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'">
                        <span class="material-symbols-rounded text-[24px]">receipt_long</span>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-0.5">NILAI SELISIH (RP)</p>
                        <h3 class="text-[18px] font-black leading-none font-mono"
                            :class="totalVarianceValue === 0 ? 'text-emerald-600' : 'text-amber-600'"
                            x-text="formatRupiah(totalVarianceValue)">Rp 0</h3>
                    </div>
                </div>
            </div>

            <!-- Barcode Scanner Quick Audit Input -->
            <template x-if="currentSession && currentSession.status === 'DRAFT'">
                <div class="bg-gradient-to-r from-blue-900 to-indigo-900 text-white rounded-2xl p-4 shadow-sm mb-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center">
                            <span class="material-symbols-rounded text-[24px]">barcode_scanner</span>
                        </div>
                        <div>
                            <h4 class="font-bold text-xs">Pindai Barcode Cepat (Audit Langsung +1)</h4>
                            <p class="text-[11px] text-blue-200">Arahkan scanner ke produk fisik di rak tanpa menghentikan penjualan kasir.</p>
                        </div>
                    </div>

                    <div class="w-full sm:w-80">
                        <input type="text"
                               x-model="scanBarcode"
                               @keydown.enter.prevent="handleBarcodeScan()"
                               placeholder="Scan barcode lalu tekan Enter..."
                               class="w-full px-4 py-2 bg-white text-gray-900 rounded-xl text-xs font-mono font-bold focus:outline-none focus:ring-2 focus:ring-blue-400">
                    </div>
                </div>
            </template>

            <!-- Table & Filters Card -->
            <div class="bg-white border border-gray-200/90 rounded-2xl shadow-xs overflow-hidden flex flex-col mb-6">
                <!-- Toolbar -->
                <div class="p-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div class="relative flex-1 min-w-[240px]">
                        <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="Cari nama barang atau SKU..."
                               class="w-full pl-9 pr-3 py-2 bg-gray-50 rounded-xl border border-gray-200 text-xs focus:bg-white focus:outline-none focus:border-[#0052cc]">
                    </div>

                    <div class="inline-flex p-1 bg-gray-100 rounded-xl gap-1">
                        <button type="button" @click="statusFilter = 'all'" :class="statusFilter === 'all' ? 'bg-white font-bold text-gray-900 shadow-2xs' : 'text-gray-500'" class="px-3 py-1 rounded-lg">Semua</button>
                        <button type="button" @click="statusFilter = 'selisih'" :class="statusFilter === 'selisih' ? 'bg-white font-bold text-red-600 shadow-2xs' : 'text-gray-500'" class="px-3 py-1 rounded-lg">Ada Selisih</button>
                        <button type="button" @click="statusFilter = 'sesuai'" :class="statusFilter === 'sesuai' ? 'bg-white font-bold text-emerald-700 shadow-2xs' : 'text-gray-500'" class="px-3 py-1 rounded-lg">Sesuai (0)</button>
                        <button type="button" @click="statusFilter = 'pending'" :class="statusFilter === 'pending' ? 'bg-white font-bold text-amber-700 shadow-2xs' : 'text-gray-500'" class="px-3 py-1 rounded-lg">Belum Diverifikasi</button>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto min-w-0 w-full">
                    <table class="w-full text-left text-xs border-collapse min-w-[950px]">
                        <thead class="bg-gray-50/90 border-b border-gray-200 text-gray-500 font-bold uppercase tracking-wider text-[10.5px]">
                            <tr>
                                <th class="px-5 py-3.5">PRODUK &amp; SKU</th>
                                <th class="px-5 py-3.5 text-center">STOK SISTEM (SNAPSHOT)</th>
                                <th class="px-5 py-3.5 text-center">STOK FISIK AKTUAL</th>
                                <th class="px-5 py-3.5 text-center">SELISIH (VARIANCE)</th>
                                <th class="px-5 py-3.5 text-right">NILAI SELISIH (RP)</th>
                                <th class="px-5 py-3.5">KETERANGAN SELISIH</th>
                                <th class="px-5 py-3.5 text-center">STATUS AUDIT</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <template x-for="item in filteredItems" :key="item.id">
                                <tr class="hover:bg-blue-50/20 transition-colors">
                                    <td class="px-5 py-3.5">
                                        <div class="font-bold text-gray-900" x-text="item.product_name"></div>
                                        <span class="font-mono text-[10.5px] text-gray-400" x-text="item.product_sku"></span>
                                    </td>

                                    <!-- Stok Sistem -->
                                    <td class="px-5 py-3.5 text-center font-mono font-bold text-gray-800" x-text="item.system_stock"></td>

                                    <!-- Stok Fisik Input -->
                                    <td class="px-5 py-3.5 text-center">
                                        <div class="inline-flex items-center gap-1">
                                            <input type="number"
                                                   x-model="item.physical_stock"
                                                   :disabled="currentSession && currentSession.status !== 'DRAFT'"
                                                   @change="verifyRow(item)"
                                                   class="w-20 text-center px-2 py-1 rounded-lg border font-mono font-bold text-xs"
                                                   :class="item.is_audited ? (item.variance === 0 ? 'border-emerald-500 bg-emerald-50/20 text-emerald-800' : 'border-red-400 bg-red-50/20 text-red-800') : 'border-gray-300'">
                                            <template x-if="currentSession && currentSession.status === 'DRAFT'">
                                                <button type="button"
                                                        @click="verifyRow(item)"
                                                        class="p-1 hover:bg-blue-50 text-[#0052cc] rounded"
                                                        title="Simpan Verifikasi">
                                                    <span class="material-symbols-rounded text-[18px]">done</span>
                                                </button>
                                            </template>
                                        </div>
                                    </td>

                                    <!-- Variance -->
                                    <td class="px-5 py-3.5 text-center font-mono font-black"
                                        :class="item.variance === 0 ? 'text-emerald-600' : 'text-red-600'"
                                        x-text="(item.variance > 0 ? '+' : '') + item.variance">
                                    </td>

                                    <!-- Nilai Selisih -->
                                    <td class="px-5 py-3.5 text-right font-mono font-bold whitespace-nowrap"
                                        :class="item.variance_value === 0 ? 'text-gray-400' : (item.variance_value < 0 ? 'text-red-600' : 'text-emerald-600')"
                                        x-text="formatRupiah(item.variance_value || 0)">
                                    </td>

                                    <!-- Reason -->
                                    <td class="px-5 py-3.5">
                                        <select x-model="item.variance_reason"
                                                :disabled="currentSession && currentSession.status !== 'DRAFT'"
                                                @change="verifyRow(item)"
                                                class="px-2 py-1 bg-white border border-gray-300 rounded-lg text-xs font-semibold">
                                            <option value="Sesuai">Sesuai</option>
                                            <option value="Hilang / Selisih Kurang">Hilang / Selisih Kurang</option>
                                            <option value="Rusak / Bocor">Rusak / Bocor</option>
                                            <option value="Lebih / Bonus Supplier">Lebih / Bonus Supplier</option>
                                            <option value="Salah Input Kasir">Salah Input Kasir</option>
                                        </select>
                                    </td>

                                    <!-- Audit Status -->
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border inline-flex items-center gap-1"
                                              :class="item.is_audited ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-100 text-gray-500 border-gray-200'">
                                            <span class="material-symbols-rounded text-[12px]" x-text="item.is_audited ? 'check' : 'pending'"></span>
                                            <span x-text="item.is_audited ? 'Verified' : 'Pending'"></span>
                                        </span>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="items.length === 0">
                                <td colspan="6" class="text-center py-12 text-slate-400">
                                    <span class="material-symbols-rounded text-4xl mb-2 text-slate-300">inventory_2</span>
                                    <p class="font-bold text-sm text-slate-700">Belum ada data produk untuk stock opname.</p>
                                    <a href="{{ url('/inventory') }}" class="mt-3 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#0052cc] text-white text-xs font-bold hover:bg-[#0043a8] transition-colors">
                                        <span class="material-symbols-rounded text-[16px]">add</span>
                                        <span>Kelola Master Produk</span>
                                    </a>
                                </td>
                            </tr>
                            <tr x-show="items.length > 0 && filteredItems.length === 0">
                                <td colspan="6" class="text-center py-12 text-gray-400">
                                    <span class="material-symbols-rounded text-4xl mb-2 text-gray-300">search_off</span>
                                    <p class="font-bold text-gray-600">Tidak ada produk yang cocok dengan filter pencarian.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Modal Buat Sesi Baru -->
    <div x-show="showNewSessionModal"
         class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-gray-200"
             @click.outside="showNewSessionModal = false">
            <h3 class="text-base font-black text-gray-900 mb-1">Mulai Sesi Stock Opname Baru</h3>
            <p class="text-xs text-gray-400 mb-4">Sistem akan mengambil snapshot stok buku saat ini tanpa menghentikan kasir.</p>

            <div class="space-y-4 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Judul / Periode Opname</label>
                    <input type="text" x-model="newSessionTitle" class="w-full px-3 py-2 rounded-xl border border-gray-300 focus:outline-none">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Auditor / Penanggung Jawab</label>
                    <input type="text" x-model="newSessionAuditor" class="w-full px-3 py-2 rounded-xl border border-gray-300 focus:outline-none">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Lokasi Toko / Gudang</label>
                    <input type="text" x-model="newSessionBranch" class="w-full px-3 py-2 rounded-xl border border-gray-300 focus:outline-none">
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="showNewSessionModal = false" class="px-4 py-2 border rounded-xl font-bold text-gray-600">Batal</button>
                    <button type="button" @click="createNewSession()" :disabled="isSubmitting" class="px-5 py-2 bg-[#0052cc] text-white rounded-xl font-bold">Mulai Opname</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Selesai Opname -->
    <div x-show="showFinishModal"
         class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-gray-200"
             @click.outside="showFinishModal = false">
            <h3 class="text-base font-black text-gray-900 mb-1">Selesaikan Stock Opname?</h3>
            <p class="text-xs text-gray-500 mb-4">Stok fisik yang telah diverifikasi akan otomatis disesuaikan ke stok master inventaris.</p>

            <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-800 space-y-1 mb-4">
                <div>Total Item Diaudit: <strong x-text="auditedCount"></strong></div>
                <div>Total Selisih Unit: <strong x-text="totalVarianceUnits"></strong></div>
                <div>Total Nilai Selisih: <strong x-text="formatRupiah(totalVarianceValue)"></strong></div>
            </div>

            <div class="space-y-4 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Catatan Berita Acara Opname</label>
                    <input type="text" x-model="finishNotes" placeholder="Nomor BA / persetujuan supervisor" class="w-full px-3 py-2 rounded-xl border border-gray-300">
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="showFinishModal = false" class="px-4 py-2 border rounded-xl font-bold text-gray-600">Batal</button>
                    <button type="button" @click="completeOpname()" :disabled="isSubmitting" class="px-5 py-2 bg-[#0052cc] text-white rounded-xl font-bold">Terapkan Penyesuaian</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div x-show="showToast"
         class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-2xl shadow-xl border text-xs font-bold"
         :class="toastType === 'error' ? 'bg-red-900 text-white border-red-800' : 'bg-gray-900 text-white border-gray-800'"
         style="display: none;">
        <span class="material-symbols-rounded text-emerald-400" x-show="toastType === 'success'">check_circle</span>
        <span class="material-symbols-rounded text-red-400" x-show="toastType === 'error'">error</span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
