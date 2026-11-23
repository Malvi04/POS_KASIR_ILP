@extends('layouts.app')

@section('title', 'Barang Masuk (Transfer Gudang) - ILP POS')

@section('content')
@php
    $currentUser = auth()->user();
    $activeBranches = $currentUser ? $currentUser->getActiveAssignedBranches() : ['Pusat'];
    $defaultBranch = !empty($activeBranches) ? $activeBranches[0] : 'Pusat';
@endphp
<div x-data="{
    ...cashierOpeningData(),
    searchQuery: '',
    statusFilter: 'all',
    showVerifyModal: false,
    showBastModal: false,
    selectedDoc: null,
    verifyNotes: '',
    toastMessage: '',
    showToast: false,

    userAssignedBranches: @js($activeBranches),
    defaultCashierBranch: @js($defaultBranch),
    selectedBranch: (function() {
        try {
            const s = localStorage.getItem('pos_kasir_branch');
            if (s) return s;
        } catch(e) {}
        return @js($defaultBranch);
    })(),
    isLoadingDocs: false,

    documents: [],

    isAssignedToBranch(branchName) {
        if (!branchName) return false;
        const norm = this.normalizeBranchName(branchName);
        if (!this.userAssignedBranches || !this.userAssignedBranches.length) return true;
        return this.userAssignedBranches.some(b => this.normalizeBranchName(b) === norm);
    },

    getActiveCashierBranch() {
        if (this.activeSession && this.activeSession.storeLocation && this.activeSession.status === 'OPEN') {
            return this.normalizeBranchName(this.activeSession.storeLocation);
        }
        if (this.selectedBranch && this.isAssignedToBranch(this.selectedBranch)) {
            return this.normalizeBranchName(this.selectedBranch);
        }
        const stored = localStorage.getItem('pos_kasir_branch');
        if (stored && this.isAssignedToBranch(stored)) {
            return this.normalizeBranchName(stored);
        }
        if (this.defaultCashierBranch) {
            return this.normalizeBranchName(this.defaultCashierBranch);
        }
        const userBranch = '{{ auth()->user()?->branch ?? "" }}';
        return this.normalizeBranchName(userBranch || 'Pusat');
    },

    selectCashierBranch(branchName) {
        const norm = this.normalizeBranchName(branchName);
        this.selectedBranch = norm;
        this.selectedLocation = norm;
        try {
            localStorage.setItem('pos_kasir_branch', norm);
            localStorage.setItem('pos_kasir_location', norm);
        } catch(e) {}
        this.refreshDocsFromServer(true);
    },

    isDocForCurrentBranch(doc, targetBranch) {
        if (!doc) return false;
        const b = (targetBranch || this.getActiveCashierBranch() || 'Pusat').toLowerCase();
        const dest = (doc.destinationStore || '').toLowerCase();
        if (b.includes('pusat')) {
            return dest.includes('pusat') || dest.includes('minimarket') || doc.to_location_id === 2;
        }
        if (b.includes('cabang a') || b.includes('sektor a') || b.includes('cabang 1')) {
            return dest.includes('cabang a') || dest.includes('sektor a') || dest.includes('cabang 1') || doc.to_location_id === 3;
        }
        if (b.includes('cabang b') || b.includes('sektor b') || b.includes('cabang 2')) {
            return dest.includes('cabang b') || dest.includes('sektor b') || dest.includes('cabang 2') || doc.to_location_id === 4;
        }
        return dest === b || (doc.destinationStore && doc.destinationStore.toLowerCase() === b);
    },

    async initGoodsIn() {
        const branch = this.getActiveCashierBranch();
        this.selectedBranch = branch;
        try { localStorage.setItem('pos_kasir_branch', branch); } catch(e) {}

        const storageKey = 'pos_ilp_goods_in_docs_v1_' + branch;
        let localDocs = [];
        const stored = localStorage.getItem(storageKey);
        if (stored) {
            try {
                localDocs = JSON.parse(stored);
                if (!Array.isArray(localDocs)) localDocs = [];
            } catch(e) {
                localDocs = [];
            }
        }
        this.documents = localDocs.filter(d => this.isDocForCurrentBranch(d, branch));

        await this.refreshDocsFromServer();

        // Sinkronisasi real-time antar tab & perangkat (Manager Kirim Barang <-> Kasir Barang Masuk)
        window.addEventListener('storage', (e) => {
            const curBranch = this.getActiveCashierBranch();
            if (e.key === ('pos_ilp_goods_in_docs_v1_' + curBranch) && e.newValue) {
                try {
                    const parsed = JSON.parse(e.newValue);
                    if (Array.isArray(parsed)) {
                        this.documents = parsed.filter(d => this.isDocForCurrentBranch(d, curBranch));
                    }
                } catch(err) {}
            }
        });

        window.addEventListener('pos:goods_in_updated', () => {
            this.refreshDocsFromServer();
        });

        window.addEventListener('pos:branch-synced', () => {
            this.refreshDocsFromServer();
        });

        window.addEventListener('pos:sync-all', () => {
            this.refreshDocsFromServer();
        });
    },

    async refreshDocsFromServer(showFeedback = false) {
        this.isLoadingDocs = true;
        try {
            const branch = this.getActiveCashierBranch();
            this.selectedBranch = branch;
            try { localStorage.setItem('pos_kasir_branch', branch); } catch(e) {}

            const res = await fetch('/api/goods-receipts?branch=' + encodeURIComponent(branch), {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success && Array.isArray(json.data)) {
                    this.documents = json.data.filter(d => this.isDocForCurrentBranch(d, branch));
                    this.saveDocs();
                    if (showFeedback) {
                        this.showToastNotice(`Data barang masuk ${branch} berhasil dimuat (${this.documents.length} dokumen).`);
                    }
                }
            } else if (showFeedback) {
                this.showToastNotice('Gagal memuat data barang masuk dari server.');
            }
        } catch(e) {
            console.warn('API goods-receipts fetch error:', e);
            if (showFeedback) {
                this.showToastNotice('Terjadi kesalahan jaringan saat memuat data barang masuk.');
            }
        } finally {
            this.isLoadingDocs = false;
        }
    },

    saveDocs() {
        const branch = this.getActiveCashierBranch();
        const storageKey = 'pos_ilp_goods_in_docs_v1_' + branch;
        localStorage.setItem(storageKey, JSON.stringify(this.documents));
        if (window.PosRealtime) {
            window.PosRealtime.broadcast('goods_in_updated', { branch: branch, documents: this.documents });
        }
    },

    getDefaultDocs() {
        return [];
    },

    get filteredDocuments() {
        const branch = this.getActiveCashierBranch();
        let list = this.documents.filter(doc => {
            // Isolasi gerai: Hanya tampilkan surat jalan yang ditujukan ke cabang kasir saat ini
            if (!this.isDocForCurrentBranch(doc, branch)) return false;

            if (this.statusFilter !== 'all') {
                if (this.statusFilter === 'APPROVED' && doc.status !== 'APPROVED' && doc.status !== 'DITERIMA_LENGKAP') return false;
                else if (this.statusFilter === 'BELUM_SAMPAI' && doc.status !== 'BELUM_SAMPAI' && doc.status !== 'MENUNGGU_VERIFIKASI') return false;
                else if (this.statusFilter !== 'APPROVED' && this.statusFilter !== 'BELUM_SAMPAI' && doc.status !== this.statusFilter) return false;
            }
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                const matchNo = (doc.docNo || '').toLowerCase().includes(q);
                const matchWh = (doc.warehouseOrigin || '').toLowerCase().includes(q);
                const matchDest = (doc.destinationStore || '').toLowerCase().includes(q);
                const matchDriver = (doc.senderName || doc.driverName || '').toLowerCase().includes(q) || (doc.plateNumber || '').toLowerCase().includes(q);
                const matchProd = Array.isArray(doc.items) && doc.items.some(it => (it.name || '').toLowerCase().includes(q) || (it.sku || '').toLowerCase().includes(q) || (it.barcode || '').toLowerCase().includes(q) || (it.brand || '').toLowerCase().includes(q));
                return matchNo || matchWh || matchDest || matchDriver || matchProd;
            }
            return true;
        });

        // Urutkan: Dokumen yang belum selesai (Belum/Sudah Sampai/Menunggu) prioritas paling atas
        return list.sort((a, b) => {
            const isPendingA = (a.status === 'BELUM_SAMPAI' || a.status === 'SUDAH_SAMPAI' || a.status === 'MENUNGGU_VERIFIKASI') ? 1 : 0;
            const isPendingB = (b.status === 'BELUM_SAMPAI' || b.status === 'SUDAH_SAMPAI' || b.status === 'MENUNGGU_VERIFIKASI') ? 1 : 0;
            if (isPendingA !== isPendingB) {
                return isPendingB - isPendingA;
            }
            const idA = Number(a.id) || 0;
            const idB = Number(b.id) || 0;
            if (idA !== idB) return idB - idA;
            return (b.docNo || '').localeCompare(a.docNo || '');
        });
    },

    get totalItemsCount() {
        return this.documents.length;
    },

    get pendingCount() {
        return this.documents.filter(d => d.status === 'BELUM_SAMPAI' || d.status === 'MENUNGGU_VERIFIKASI').length;
    },

    get arrivedCount() {
        return this.documents.filter(d => d.status === 'SUDAH_SAMPAI').length;
    },

    get approvedCount() {
        return this.documents.filter(d => d.status === 'APPROVED' || d.status === 'DITERIMA_LENGKAP').length;
    },

    get completedFullCount() {
        return this.approvedCount;
    },

    get completedVarianceCount() {
        return this.documents.filter(d => d.status === 'DITERIMA_SELISIH' || d.status === 'TIDAK_SAMPAI').length;
    },

    formatRupiah(num) {
        return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
    },

    openVerification(doc) {
        const branch = this.getActiveCashierBranch();
        if (!this.isDocForCurrentBranch(doc, branch)) {
            this.showToastNotice(`Akses Ditolak: Dokumen ${doc.docNo} ditujukan untuk ${doc.destinationStore}. Anda bertugas di ${branch}.`);
            return;
        }
        // Clone doc to avoid direct mutation before confirmation
        this.selectedDoc = JSON.parse(JSON.stringify(doc));
        if (this.selectedDoc && Array.isArray(this.selectedDoc.items)) {
            // Default received quantity to sent quantity if not yet filled
            this.selectedDoc.items.forEach(it => {
                if (it.qtyReceived === undefined || it.qtyReceived === null || it.qtyReceived === 0) {
                    it.qtyReceived = it.qtySent;
                }
            });
        }
        this.verifyNotes = doc.generalNotes || '';
        this.showVerifyModal = true;
    },

    openBastView(doc) {
        this.selectedDoc = doc;
        this.showBastModal = true;
    },

    calculateDocVariance(doc) {
        if (!doc || !doc.items) return 0;
        return doc.items.reduce((acc, it) => acc + (Number(it.qtyReceived) - Number(it.qtySent)), 0);
    },

    updateInventoryStock(items) {
        const rawProd = localStorage.getItem('pos_ilp_inventory_products_v1');
        if (!rawProd) return;
        try {
            let prods = JSON.parse(rawProd);
            items.forEach(it => {
                const receivedQty = parseInt(it.qtyReceived, 10) || 0;
                if (receivedQty > 0) {
                    const p = prods.find(pr => (pr.sku && pr.sku === it.sku) || (pr.barcode && pr.barcode === it.sku) || (pr.name && pr.name.toLowerCase() === (it.name || '').toLowerCase()) || (pr.nama && pr.nama.toLowerCase() === (it.name || '').toLowerCase()));
                    if (p) {
                        const cur = parseInt(p.stock !== undefined ? p.stock : (p.stok || 0), 10) || 0;
                        p.stock = cur + receivedQty;
                        p.stok = cur + receivedQty;
                    }
                }
            });
            localStorage.setItem('pos_ilp_inventory_products_v1', JSON.stringify(prods));
            window.dispatchEvent(new CustomEvent('pos-inventory-synced'));
        } catch(e) {
            console.error('Error updating stock', e);
        }
    },

    async confirmGoodsReceipt() {
        if (!this.selectedDoc) return;
        const branch = this.getActiveCashierBranch();
        if (!this.isDocForCurrentBranch(this.selectedDoc, branch)) {
            this.showToastNotice(`Akses Ditolak: Dokumen ${this.selectedDoc.docNo} ditujukan untuk ${this.selectedDoc.destinationStore}. Anda bertugas di ${branch}.`);
            this.showVerifyModal = false;
            return;
        }

        const nowStr = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) + ' • ' +
                       new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') + ' WIB';

        const original = this.documents.find(d => (d.id && d.id === this.selectedDoc.id) || (d.docNo && d.docNo === this.selectedDoc.docNo));
        if (original) {
            // Apply items confirmation
            original.items = this.selectedDoc.items.map(it => ({
                ...it,
                qtyReceived: Math.max(0, parseInt(it.qtyReceived, 10) || 0)
            }));

            // Check if there is any quantity variance or damaged condition
            const hasVariance = original.items.some(it => it.qtyReceived !== it.qtySent || it.condition !== 'BAIK');

            original.status = hasVariance ? 'DITERIMA_SELISIH' : 'DITERIMA_LENGKAP';
            original.receivedAt = nowStr;
            original.receiverName = (this.activeSession?.cashierName || localStorage.getItem('pos_user_name') || 'Kasir') + ' (Kasir)';
            original.generalNotes = this.verifyNotes.trim() || (hasVariance ? 'Penerimaan diverifikasi dengan catatan selisih/kondisi.' : 'Penerimaan fisik barang transfer lengkap dan sesuai.');

            // Update Stock Toko via API (wajib sukses sebelum memperbarui state lokal)
            if (original.id && typeof original.id === 'number') {
                try {
                    const res = await fetch('/api/goods-receipts/' + original.id + '/verify', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({
                            branch: branch,
                            location_id: this.activeSession?.locationId || null,
                            receiver_name: original.receiverName,
                            notes: original.generalNotes,
                            items: (original.items || []).map(it => ({
                                id: it.id,
                                qty_received: it.qtyReceived,
                                condition: it.condition,
                                note: it.note
                            }))
                        })
                    });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok || !json.success) {
                        this.showToastNotice(json.message || 'Gagal memverifikasi dokumen penerimaan di server.');
                        return;
                    }
                    if (json.data) {
                        Object.assign(original, json.data);
                    }
                } catch (e) {
                    console.warn('API sync receipt error', e);
                    this.showToastNotice('Gagal menghubungi server untuk verifikasi penerimaan.');
                    return;
                }
            }

            this.updateInventoryStock(original.items);
            this.saveDocs();
            window.dispatchEvent(new CustomEvent('pos-inventory-synced'));
            if (window.PosRealtime) {
                window.PosRealtime.broadcast('inventory_updated', { source: 'goods_in_verified', branch: branch });
            }

            this.showVerifyModal = false;
            this.showToastNotice(`Dokumen ${original.docNo} berhasil dikonfirmasi (${original.status === 'DITERIMA_LENGKAP' ? 'Lengkap Sesuai' : 'Ada Selisih Fisik'}) dan stok gerai ${original.destinationStore} telah diperbarui!`);
        }
    },

    async updateReceiptStatus(doc, targetStatus) {
        if (!doc || !doc.id) return;
        const branch = this.getActiveCashierBranch();
        if (!this.isDocForCurrentBranch(doc, branch)) {
            this.showToastNotice(`Akses Ditolak: Dokumen ${doc.docNo} ditujukan untuk ${doc.destinationStore}. Anda bertugas di ${branch}.`);
            return;
        }

        try {
            const res = await fetch('/api/goods-receipts/' + doc.id + '/status', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    status: targetStatus,
                    branch: branch,
                    location_id: this.activeSession?.locationId || null
                })
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.success) {
                this.showToastNotice(json.message || 'Gagal memperbarui status dokumen di server.');
                return;
            }
            if (json.data) {
                Object.assign(doc, json.data);
            } else {
                doc.status = targetStatus;
            }
            this.saveDocs();
            this.showToastNotice(`Status surat jalan ${doc.docNo} berhasil diubah menjadi: ${doc.statusLabel || targetStatus}`);
            if (targetStatus === 'APPROVED' || targetStatus === 'Approved') {
                if (doc.items) {
                    this.updateInventoryStock(doc.items);
                }
                window.dispatchEvent(new CustomEvent('pos-inventory-synced'));
                if (window.PosRealtime) {
                    window.PosRealtime.broadcast('inventory_updated', { source: 'goods_in_approved', branch: branch });
                }
            }
        } catch (e) {
            this.showToastNotice('Gagal menghubungi server: ' + e.message);
        }
    },

    showToastNotice(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3600);
    }
}"
x-init="initOpening(); initGoodsIn();"
@pos-session-sync.window="initOpening()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">

    <!-- Sidebar Kasir -->
    <x-cashier-sidebar active="produk_masuk" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden relative">
        <!-- Top Header -->
        <x-cashier-header title="Barang Masuk (Transfer Gudang)">
            <div class="flex items-center gap-2 ml-2">
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-[#0052cc] animate-pulse"></span>
                    <span>Penerimaan Fisik Toko</span>
                </span>
            </div>
        </x-cashier-header>

        <!-- Scrollable Content Area -->
        <div class="flex-1 overflow-y-auto p-6 lg:p-8 space-y-6">
            <div class="max-w-[1400px] mx-auto space-y-6">

                <!-- Breadcrumb & Page Info -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-black text-gray-900 tracking-tight">Barang Masuk</h1>
                        <p class="text-xs text-gray-500 mt-1 font-medium">Verifikasi surat jalan transfer dari gudang pusat dan pencatatan selisih barang.</p>
                    </div>

                    <!-- Branch & Online Status Badge -->
                    <div class="flex items-center gap-3">
                        <div class="bg-white border border-gray-200 rounded-xl px-4 py-2 flex items-center gap-2.5 shadow-xs">
                            <div class="w-8 h-8 rounded-lg bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                                <span class="material-symbols-rounded text-[18px]">storefront</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">GERAI PENERIMA</span>
                                <template x-if="userAssignedBranches && userAssignedBranches.length > 1">
                                    <div class="relative mt-0.5">
                                        <select :value="getActiveCashierBranch()" 
                                                @change="selectCashierBranch($event.target.value)"
                                                class="text-xs font-black text-[#0052cc] bg-transparent border-none pr-5 py-0 focus:outline-none cursor-pointer appearance-none font-sans">
                                            <template x-for="b in userAssignedBranches" :key="b">
                                                <option :value="normalizeBranchName(b)" x-text="normalizeBranchName(b)" :selected="getActiveCashierBranch() === normalizeBranchName(b)"></option>
                                            </template>
                                        </select>
                                        <span class="material-symbols-rounded text-[14px] text-[#0052cc] absolute right-0 top-1/2 -translate-y-1/2 pointer-events-none">expand_more</span>
                                    </div>
                                </template>
                                <template x-if="!userAssignedBranches || userAssignedBranches.length <= 1">
                                    <span class="text-xs font-black text-[#0052cc]" x-text="getActiveCashierBranch()">Pusat</span>
                                </template>
                            </div>
                        </div>

                        <div class="bg-white border border-gray-200 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-xs">
                            <div class="w-2.5 h-2.5 rounded-full transition-colors"
                                 :class="($store.network && $store.network.isOnline) ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'"></div>
                            <div class="flex flex-col">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">TERMINAL PENERIMA</span>
                                    <span class="text-[9px] font-bold px-1.5 py-0.2 rounded-full"
                                          :class="($store.network && $store.network.isOnline) ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                                          x-text="($store.network && $store.network.isOnline) ? 'ONLINE' : 'OFFLINE'">ONLINE</span>
                                </div>
                                <span class="text-xs font-bold text-gray-800" x-text="(activeSession && activeSession.terminalLabel) ? (activeSession.terminalLabel + ' • ' + activeSession.cashierName) : (localStorage.getItem('pos_user_name') || '-')">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4 Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Stat 1 -->
                    <div @click="statusFilter = 'all'"
                         class="bg-white rounded-2xl border p-5 shadow-xs flex items-center justify-between cursor-pointer hover:shadow-md transition-all"
                         :class="statusFilter === 'all' ? 'border-[#0052cc] ring-2 ring-[#0052cc]/20' : 'border-gray-200'">
                        <div>
                            <span class="text-[11px] font-bold text-gray-400 tracking-wider uppercase">TOTAL DOKUMEN</span>
                            <h3 class="text-2xl font-black text-gray-900 mt-1" x-text="totalItemsCount">0</h3>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Transfer periode aktif</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">local_shipping</span>
                        </div>
                    </div>

                    <!-- Stat 2 -->
                    <div @click="statusFilter = (statusFilter === 'BELUM_SAMPAI' ? 'all' : 'BELUM_SAMPAI')"
                         class="bg-white rounded-2xl border p-5 shadow-xs flex items-center justify-between border-l-4 border-l-amber-500 cursor-pointer hover:shadow-md transition-all"
                         :class="statusFilter === 'BELUM_SAMPAI' ? 'border-amber-400 ring-2 ring-amber-400/30' : 'border-amber-200'">
                        <div>
                            <span class="text-[11px] font-bold text-amber-700 tracking-wider uppercase">BELUM SAMPAI</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <h3 class="text-2xl font-black text-amber-600" x-text="pendingCount">0</h3>
                                <span class="text-[11px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-md">Dalam Perjalanan</span>
                            </div>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Dari Gudang Pusat</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">pending_actions</span>
                        </div>
                    </div>

                    <!-- Stat 3 -->
                    <div @click="statusFilter = (statusFilter === 'SUDAH_SAMPAI' ? 'all' : 'SUDAH_SAMPAI')"
                         class="bg-white rounded-2xl border p-5 shadow-xs flex items-center justify-between border-l-4 border-l-blue-500 cursor-pointer hover:shadow-md transition-all"
                         :class="statusFilter === 'SUDAH_SAMPAI' ? 'border-blue-400 ring-2 ring-blue-400/30' : 'border-blue-200'">
                        <div>
                            <span class="text-[11px] font-bold text-blue-700 tracking-wider uppercase">SUDAH SAMPAI</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <h3 class="text-2xl font-black text-[#0052cc]" x-text="arrivedCount">0</h3>
                                <span class="text-[11px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md">Tiba di Toko</span>
                            </div>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Perlu verifikasi fisik</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">pin_drop</span>
                        </div>
                    </div>

                    <!-- Stat 4 -->
                    <div @click="statusFilter = (statusFilter === 'APPROVED' ? 'all' : 'APPROVED')"
                         class="bg-white rounded-2xl border p-5 shadow-xs flex items-center justify-between border-l-4 border-l-emerald-500 cursor-pointer hover:shadow-md transition-all"
                         :class="statusFilter === 'APPROVED' ? 'border-emerald-400 ring-2 ring-emerald-400/30' : 'border-emerald-200'">
                        <div>
                            <span class="text-[11px] font-bold text-emerald-700 tracking-wider uppercase">APPROVED</span>
                            <h3 class="text-2xl font-black text-emerald-600 mt-1" x-text="approvedCount">0</h3>
                            <span class="text-[11px] text-emerald-600 font-medium mt-0.5 block">Stok Masuk Toko</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">verified</span>
                        </div>
                    </div>
                </div>

                <!-- Filter & Search Toolbar -->
                <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="relative flex-1 w-full max-w-xl">
                        <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="Cari nomor surat jalan, DC pengirim, toko tujuan, driver, atau produk..."
                               class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 text-[13px] shadow-xs transition-all font-medium bg-slate-50/50">
                    </div>

                    <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                        <!-- Filter Status Dokumen -->
                        <div class="relative">
                            <select x-model="statusFilter"
                                    class="appearance-none bg-white border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-[13px] text-gray-700 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 shadow-xs font-semibold cursor-pointer">
                                <option value="all">Semua Status Dokumen</option>
                                <option value="BELUM_SAMPAI">Belum sampai</option>
                                <option value="SUDAH_SAMPAI">Sudah sampai</option>
                                <option value="APPROVED">Approved</option>
                                <option value="TIDAK_SAMPAI">Tidak sampai</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                        </div>

                        <!-- Tombol Muat Ulang / Segarkan Data -->
                        <button type="button" 
                                @click="refreshDocsFromServer(true)" 
                                :disabled="isLoadingDocs"
                                title="Segarkan dan muat ulang data pengiriman dari Gudang Pusat"
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-blue-50 hover:bg-blue-100 text-[#0052cc] rounded-xl text-[13px] font-bold border border-blue-200 transition-colors shadow-xs cursor-pointer disabled:opacity-50">
                            <span class="material-symbols-rounded text-[18px]" :class="{ 'animate-spin': isLoadingDocs }">sync</span>
                            <span x-text="isLoadingDocs ? 'Memuat...' : 'Muat Data'"></span>
                        </button>
                    </div>
                </div>

                <!-- Documents Table -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-rounded text-[20px] text-[#0052cc]">inbox</span>
                            <h2 class="text-sm font-bold text-gray-900">Daftar Dokumen Transfer Gudang</h2>
                        </div>
                        <span class="text-xs text-gray-500 font-medium" x-text="'Menampilkan ' + filteredDocuments.length + ' dokumen'"></span>
                    </div>

                    <div class="overflow-x-auto min-w-0 w-full">
                        <table class="w-full text-left border-collapse text-xs min-w-[1250px]">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold tracking-wider uppercase text-[11px] whitespace-nowrap">
                                    <th class="px-3.5 py-3.5 text-left">No. Surat Jalan</th>
                                    <th class="px-3.5 py-3.5 text-left">Tanggal Dikirim</th>
                                    <th class="px-3.5 py-3.5 text-left">SKU</th>
                                    <th class="px-3.5 py-3.5 text-left">Barcode</th>
                                    <th class="px-3.5 py-3.5 text-left">Nama Produk</th>
                                    <th class="px-3.5 py-3.5 text-left">Brand</th>
                                    <th class="px-3.5 py-3.5 text-left">Kategori</th>
                                    <th class="px-3.5 py-3.5 text-center">Qty Dikirim</th>
                                    <th class="px-3.5 py-3.5 text-right">Harga Umum</th>
                                    <th class="px-3.5 py-3.5 text-right">Harga Member</th>
                                    <th class="px-3.5 py-3.5 text-left">Pengirim</th>
                                    <th class="px-3.5 py-3.5 text-left">Plat Nomor</th>
                                    <th class="px-3.5 py-3.5 text-center">Status</th>
                                    <th class="px-3.5 py-3.5 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="text-slate-700">
                                <template x-for="doc in filteredDocuments" :key="doc.id">
                                    <template x-for="(it, iIdx) in ((doc.items && doc.items.length > 0) ? doc.items : [{ sku: '-', barcode: '-', name: '-', brand: 'ILP', category: '-', qtySent: 0, unit: 'Unit', price: 0, memberPrice: 0 }])" :key="iIdx">
                                        <tr class="hover:bg-blue-50/30 transition-colors" :class="iIdx === 0 ? 'border-t border-gray-200' : 'border-t border-gray-100'">
                                            <!-- 1. No. Surat Jalan -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-3.5 py-3 align-middle whitespace-nowrap bg-white font-mono font-bold text-gray-900 text-xs">
                                                    <span x-text="doc.docNo"></span>
                                                </td>
                                            </template>

                                            <!-- 2. Tanggal Dikirim -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-3.5 py-3 align-middle whitespace-nowrap bg-white text-xs text-gray-500 font-medium" x-text="doc.date">
                                                </td>
                                            </template>

                                            <!-- 3. SKU -->
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap font-mono font-bold text-gray-700 text-xs" x-text="it.sku || '-'"></td>

                                            <!-- 4. Barcode -->
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap font-mono text-gray-600 text-xs" x-text="it.barcode || it.sku || '-'"></td>

                                            <!-- 5. Nama Produk & Jenis -->
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap text-xs">
                                                <div class="flex flex-col">
                                                    <span class="font-bold text-gray-900 max-w-[200px] truncate" :title="it.name" x-text="it.name"></span>
                                                    <div class="flex items-center gap-1 mt-0.5">
                                                        <template x-if="it.is_coupon || it.product_type === 'KUPON' || (it.category && (it.category.toLowerCase().includes('kupon') || it.category.toLowerCase().includes('voucher')))">
                                                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-200">
                                                                <span class="material-symbols-rounded text-[11px]">local_activity</span>
                                                                <span>Kupon / Voucher</span>
                                                            </span>
                                                        </template>
                                                        <template x-if="(it.is_consignment || it.product_type === 'UMKM') && !(it.is_coupon || it.product_type === 'KUPON') && !(it.category && (it.category.toLowerCase().includes('kupon') || it.category.toLowerCase().includes('voucher')))">
                                                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                                                <span class="material-symbols-rounded text-[11px]">storefront</span>
                                                                <span>Produk UMKM</span>
                                                                <span x-show="it.supplier_name" x-text="'• ' + it.supplier_name" class="truncate max-w-[120px]"></span>
                                                            </span>
                                                        </template>
                                                        <template x-if="!it.is_consignment && it.product_type !== 'UMKM' && !it.is_coupon && it.product_type !== 'KUPON' && !(it.category && (it.category.toLowerCase().includes('kupon') || it.category.toLowerCase().includes('voucher')))">
                                                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                                                <span class="material-symbols-rounded text-[11px]">inventory_2</span>
                                                                <span>Produk Umum</span>
                                                            </span>
                                                        </template>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- 6. Brand -->
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap text-xs text-gray-600 font-medium" x-text="it.brand || 'ILP'"></td>

                                            <!-- 7. Kategori -->
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap text-xs text-slate-500" x-text="it.category || '-'"></td>

                                            <!-- 8. Qty Dikirim -->
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap text-center font-mono font-bold text-xs bg-slate-50/50">
                                                <span class="inline-block bg-blue-50 text-[#0047b3] border border-blue-200 px-2.5 py-0.5 rounded font-black font-mono text-xs" x-text="it.qtySent + ' ' + (it.unit || 'Unit')"></span>
                                            </td>

                                            <!-- 9. Harga Umum -->
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap text-right font-mono font-semibold text-gray-800 text-xs" x-text="formatRupiah(it.price)"></td>

                                            <!-- 10. Harga Member -->
                                            <td class="px-3.5 py-3 align-middle whitespace-nowrap text-right font-mono font-bold text-emerald-600 text-xs" x-text="formatRupiah(it.memberPrice || it.price)"></td>

                                            <!-- 11. Pengirim -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-3.5 py-3 align-middle whitespace-nowrap bg-white text-xs font-bold text-gray-800" x-text="doc.senderName || doc.driverName || '-'">
                                                </td>
                                            </template>

                                            <!-- 12. Plat Nomor -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-3.5 py-3 align-middle whitespace-nowrap bg-white font-mono text-xs text-gray-500" x-text="doc.plateNumber || '-'">
                                                </td>
                                            </template>

                                            <!-- 13. Status (Dapat diubah oleh kasir) -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-3.5 py-3 align-middle whitespace-nowrap bg-white text-center">
                                                    <select :value="doc.status" 
                                                            @change="updateReceiptStatus(doc, $event.target.value)"
                                                            class="text-[11px] font-bold rounded-lg border px-2 py-1 focus:outline-none cursor-pointer transition-colors shadow-xs"
                                                            :class="{
                                                                'bg-amber-50 text-amber-700 border-amber-300': doc.status === 'BELUM_SAMPAI' || doc.status === 'MENUNGGU_VERIFIKASI',
                                                                'bg-blue-50 text-blue-700 border-blue-300': doc.status === 'SUDAH_SAMPAI',
                                                                'bg-emerald-50 text-emerald-700 border-emerald-300': doc.status === 'APPROVED' || doc.status === 'DITERIMA_LENGKAP',
                                                                'bg-rose-50 text-rose-700 border-rose-300': doc.status === 'TIDAK_SAMPAI'
                                                            }">
                                                        <option value="BELUM_SAMPAI">Belum sampai</option>
                                                        <option value="SUDAH_SAMPAI">Sudah sampai</option>
                                                        <option value="APPROVED">Approved</option>
                                                        <option value="TIDAK_SAMPAI">Tidak sampai</option>
                                                    </select>
                                                </td>
                                            </template>

                                            <!-- 14. Aksi -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-3.5 py-3 align-middle whitespace-nowrap text-center bg-white">
                                                    <div class="inline-flex items-center justify-center gap-1.5">
                                                        <template x-if="doc.status !== 'APPROVED' && doc.status !== 'DITERIMA_LENGKAP'">
                                                            <button type="button"
                                                                    @click="openVerification(doc)"
                                                                    class="px-3 py-1.5 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold transition-all shadow-xs inline-flex items-center gap-1 cursor-pointer">
                                                                <span class="material-symbols-rounded text-[15px]">fact_check</span>
                                                                <span>Periksa Fisik</span>
                                                            </button>
                                                        </template>
                                                        <template x-if="doc.status === 'APPROVED' || doc.status === 'DITERIMA_LENGKAP'">
                                                            <button type="button"
                                                                    @click="openBastView(doc)"
                                                                    class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-xl text-xs font-bold transition-colors inline-flex items-center gap-1 cursor-pointer">
                                                                <span class="material-symbols-rounded text-[15px]">receipt_long</span>
                                                                <span>Lihat BAST</span>
                                                            </button>
                                                        </template>
                                                    </div>
                                                </td>
                                            </template>
                                        </tr>
                                    </template>
                                </template>
                                <tr x-show="!filteredDocuments || filteredDocuments.length === 0">
                                    <td colspan="14" class="py-12 text-center text-slate-400 font-medium">
                                        <div class="flex flex-col items-center justify-center gap-2 max-w-md mx-auto">
                                            <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mb-1">
                                                <span class="material-symbols-rounded text-2xl">inbox</span>
                                            </div>
                                            <p class="text-sm font-bold text-gray-700">Belum ada dokumen penerimaan barang untuk <span class="text-[#0052cc]" x-text="getActiveCashierBranch()"></span>.</p>
                                            <p class="text-xs text-gray-500">Pastikan Manager telah mengirimkan barang ke cabang ini dari menu <strong>Kirim Barang</strong>.</p>
                                            <button type="button" 
                                                    @click="refreshDocsFromServer(true)" 
                                                    :disabled="isLoadingDocs"
                                                    class="mt-2 inline-flex items-center gap-1.5 px-4 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer disabled:opacity-50">
                                                <span class="material-symbols-rounded text-[16px]" :class="{ 'animate-spin': isLoadingDocs }">sync</span>
                                                <span x-text="isLoadingDocs ? 'Memuat...' : 'Muat Ulang Data Barang Masuk'"></span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- MODAL 1: PERIKSA & KONFIRMASI PENERIMAAN FISIK -->
    <template x-teleport="body">
        <div x-show="showVerifyModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showVerifyModal"
                 @click.away="showVerifyModal = false"
                 x-transition.scale.origin.bottom
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden border border-gray-200">

                <!-- Modal Header -->
                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-slate-50/70">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]">fact_check</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-gray-900">Verifikasi Fisik Barang Masuk</h3>
                            <p class="text-xs text-gray-500 mt-0.5" x-text="selectedDoc ? selectedDoc.docNo + ' • Dari ' + selectedDoc.warehouseOrigin : ''"></p>
                        </div>
                    </div>
                    <button @click="showVerifyModal = false" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[24px]">close</span>
                    </button>
                </div>

                <!-- Modal Body: Scrollable Form -->
                <div class="p-6 overflow-y-auto space-y-5 flex-1">
                    <!-- Delivery Manifest Info -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-4 bg-slate-50 rounded-xl border border-gray-200 text-xs">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">PENGEMUDI / ARMADA</span>
                            <span class="font-bold text-gray-800" x-text="selectedDoc?.driverName + ' (' + selectedDoc?.plateNumber + ')'"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">GUDANG PENGIRIM</span>
                            <span class="font-bold text-gray-800" x-text="selectedDoc?.warehouseOrigin"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">TOTAL SKU</span>
                            <span class="font-bold text-gray-800" x-text="selectedDoc?.items?.length + ' Macam Produk'"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">PETUGAS PEMERIKSA</span>
                            <span class="font-bold text-[#0052cc]" x-text="(activeSession && activeSession.cashierName) ? activeSession.cashierName : (localStorage.getItem('pos_user_name') || '-')">-</span>
                        </div>
                    </div>

                    <!-- Items Verification Table -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden shadow-xs bg-white">
                        <div class="overflow-x-auto min-w-0 w-full">
                            <table class="w-full text-left border-collapse text-xs min-w-[750px]">
                                <thead class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold uppercase tracking-wider text-[11px] whitespace-nowrap">
                                    <tr>
                                        <th class="px-4 py-3 text-left w-[240px]">PRODUK / SKU</th>
                                        <th class="px-3 py-3 text-center w-[120px]">QTY SURAT JALAN</th>
                                        <th class="px-3 py-3 text-center w-[140px]">QTY AKTUAL DITERIMA</th>
                                        <th class="px-3 py-3 text-center w-[100px]">SELISIH</th>
                                        <th class="px-3 py-3 text-left w-[180px]">KONDISI FISIK</th>
                                        <th class="px-4 py-3 text-left w-[180px]">CATATAN KHUSUS</th>
                                    </tr>
                                </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(item, idx) in selectedDoc?.items" :key="idx">
                                    <tr class="hover:bg-slate-50/50">
                                        <!-- Product -->
                                        <td class="px-4 py-3">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-900" x-text="item.name"></span>
                                                <div class="flex items-center gap-1.5 mt-0.5">
                                                    <span class="font-mono text-[11px] text-gray-400" x-text="'SKU: ' + item.sku + ' (' + item.unit + ')'"></span>
                                                    <template x-if="item.is_coupon || item.product_type === 'KUPON' || (item.category && (item.category.toLowerCase().includes('kupon') || item.category.toLowerCase().includes('voucher')))">
                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-200">
                                                            <span class="material-symbols-rounded text-[11px]">local_activity</span>
                                                            <span>Kupon / Voucher</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="(item.is_consignment || item.product_type === 'UMKM') && !(item.is_coupon || item.product_type === 'KUPON') && !(item.category && (item.category.toLowerCase().includes('kupon') || item.category.toLowerCase().includes('voucher')))">
                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                                            <span class="material-symbols-rounded text-[11px]">storefront</span>
                                                            <span>Produk UMKM</span>
                                                            <span x-show="item.supplier_name" x-text="'• ' + item.supplier_name"></span>
                                                        </span>
                                                    </template>
                                                    <template x-if="!item.is_consignment && item.product_type !== 'UMKM' && !item.is_coupon && item.product_type !== 'KUPON' && !(item.category && (item.category.toLowerCase().includes('kupon') || item.category.toLowerCase().includes('voucher')))">
                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                                            <span class="material-symbols-rounded text-[11px]">inventory_2</span>
                                                            <span>Produk Umum</span>
                                                        </span>
                                                    </template>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Qty Sent -->
                                        <td class="px-3 py-3 text-center font-bold text-gray-700" x-text="item.qtySent + ' ' + item.unit"></td>

                                        <!-- Qty Received (Input) -->
                                        <td class="px-3 py-3 text-center">
                                            <input type="number"
                                                   min="0"
                                                   x-model.number="item.qtyReceived"
                                                   class="w-20 px-2 py-1.5 border border-gray-300 rounded-lg text-center font-bold text-gray-900 focus:outline-none focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc]">
                                        </td>

                                        <!-- Selisih -->
                                        <td class="px-3 py-3 text-center font-bold font-mono">
                                            <span :class="(item.qtyReceived - item.qtySent) === 0 ? 'text-emerald-600' : 'text-rose-600 bg-rose-50 px-2 py-0.5 rounded'"
                                                  x-text="(item.qtyReceived - item.qtySent) > 0 ? '+' + (item.qtyReceived - item.qtySent) : (item.qtyReceived - item.qtySent)">0</span>
                                        </td>

                                        <!-- Kondisi -->
                                        <td class="px-3 py-3">
                                            <select x-model="item.condition"
                                                    class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 bg-white focus:outline-none focus:border-[#0052cc]">
                                                <option value="BAIK">Baik / Sempurna</option>
                                                <option value="KEMASAN_RUSAK">Kemasan Rusak / Penyok</option>
                                                <option value="BOCOR_PECAH">Bocor / Pecah Rembes</option>
                                                <option value="NEAR_EXPIRED">Mendekati Expired (H-30)</option>
                                                <option value="KADALUWARSA">Kadaluarsa (ED Lewat)</option>
                                            </select>
                                        </td>

                                        <!-- Catatan -->
                                        <td class="px-4 py-3">
                                            <input type="text"
                                                   x-model="item.note"
                                                   placeholder="Keterangan..."
                                                   class="w-full px-2.5 py-1.5 border border-gray-200 rounded-lg text-xs text-gray-700 focus:outline-none focus:border-[#0052cc]">
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- General Reception Notes -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-gray-700 flex items-center justify-between">
                            <span>Catatan Serah Terima Fisik (BAST):</span>
                            <span class="text-[11px] text-gray-400 font-normal">Wajib diisi jika terdapat selisih atau kerusakan</span>
                        </label>
                        <textarea x-model="verifyNotes"
                                  rows="2"
                                  placeholder="Contoh: Seluruh kardus telah dibongkar, ditemukan 1 botol pecah di tumpukan bawah..."
                                  class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-xs text-gray-800 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20"></textarea>
                    </div>

                    <!-- Warning / Info Notice -->
                    <div class="p-3.5 bg-blue-50/70 border border-blue-200 rounded-xl flex items-start gap-3 text-xs text-blue-900">
                        <span class="material-symbols-rounded text-[20px] text-[#0052cc] shrink-0 mt-0.5">policy</span>
                        <div class="space-y-0.5">
                            <p class="font-bold">Ketentuan SOP Penerimaan Toko:</p>
                            <p class="text-blue-800 text-[11.5px] leading-relaxed">
                                Konfirmasi BAST fisik. Stok toko disesuaikan otomatis sesuai kuantitas aktual diterima.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between bg-slate-50/50">
                    <button type="button"
                            @click="showVerifyModal = false"
                            class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-700 font-bold text-xs hover:bg-gray-100 transition-colors cursor-pointer">
                        Batal
                    </button>

                    <button type="button"
                            @click="confirmGoodsReceipt()"
                            class="px-6 py-2.5 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs flex items-center gap-2 shadow-sm transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">verified</span>
                        <span>Konfirmasi Penerimaan (BAST)</span>
                    </button>
                </div>

            </div>
        </div>
    </template>

    <!-- MODAL 2: LIHAT BAST (DOKUMEN PENERIMAAN SELESAI) -->
    <template x-teleport="body">
        <div x-show="showBastModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showBastModal"
                 @click.away="showBastModal = false"
                 x-transition.scale.origin.bottom
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden border border-gray-200 flex flex-col">

                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]">receipt_long</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-gray-900">Berita Acara Penerimaan Barang (BAST)</h3>
                            <p class="text-xs text-gray-500 font-mono mt-0.5" x-text="selectedDoc?.docNo"></p>
                        </div>
                    </div>
                    <button @click="showBastModal = false" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[24px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 overflow-y-auto max-h-[70vh] text-xs">
                    <div class="grid grid-cols-2 gap-4 p-4 bg-slate-50 rounded-xl border border-gray-200">
                        <div>
                            <span class="text-gray-400 font-bold uppercase text-[10px] block">GUDANG ASAL</span>
                            <span class="font-bold text-gray-900 text-sm" x-text="selectedDoc?.warehouseOrigin"></span>
                            <span class="text-gray-500 text-[11px] block mt-0.5" x-text="'Driver: ' + selectedDoc?.driverName + ' (' + selectedDoc?.plateNumber + ')'"></span>
                        </div>
                        <div>
                            <span class="text-gray-400 font-bold uppercase text-[10px] block">STATUS & PENERIMA</span>
                            <span class="font-bold text-emerald-700 text-sm" x-text="selectedDoc?.status === 'DITERIMA_LENGKAP' ? 'Diterima Lengkap' : 'Diterima dengan Selisih'"></span>
                            <span class="text-gray-500 text-[11px] block mt-0.5" x-text="'Oleh: ' + selectedDoc?.receiverName + ' pada ' + selectedDoc?.receivedAt"></span>
                        </div>
                    </div>

                    <div class="border border-gray-200 rounded-xl overflow-hidden bg-white">
                        <div class="overflow-x-auto min-w-0 w-full">
                            <table class="w-full text-left border-collapse text-xs min-w-[650px]">
                                <thead class="bg-gray-50/80 border-b border-gray-200 font-bold text-gray-600 uppercase tracking-wider text-[11px] whitespace-nowrap">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left w-[240px]">PRODUK</th>
                                        <th class="px-3 py-2.5 text-center w-[120px]">QTY SURAT JALAN</th>
                                        <th class="px-3 py-2.5 text-center w-[120px]">QTY DITERIMA</th>
                                        <th class="px-3 py-2.5 text-center w-[100px]">SELISIH</th>
                                        <th class="px-4 py-2.5 text-left w-[180px]">KONDISI</th>
                                    </tr>
                                </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(it, idx) in selectedDoc?.items" :key="idx">
                                    <tr>
                                        <td class="px-4 py-2.5">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-800" x-text="it.name"></span>
                                                <div class="flex items-center gap-1 mt-0.5">
                                                    <template x-if="it.is_coupon || it.product_type === 'KUPON' || (it.category && (it.category.toLowerCase().includes('kupon') || it.category.toLowerCase().includes('voucher')))">
                                                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-200">
                                                            <span class="material-symbols-rounded text-[11px]">local_activity</span>
                                                            <span>Kupon / Voucher</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="(it.is_consignment || it.product_type === 'UMKM') && !(it.is_coupon || it.product_type === 'KUPON') && !(it.category && (it.category.toLowerCase().includes('kupon') || it.category.toLowerCase().includes('voucher')))">
                                                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                                            <span class="material-symbols-rounded text-[11px]">storefront</span>
                                                            <span>Produk UMKM</span>
                                                            <span x-show="it.supplier_name" x-text="'• ' + it.supplier_name"></span>
                                                        </span>
                                                    </template>
                                                    <template x-if="!it.is_consignment && it.product_type !== 'UMKM' && !it.is_coupon && it.product_type !== 'KUPON' && !(it.category && (it.category.toLowerCase().includes('kupon') || it.category.toLowerCase().includes('voucher')))">
                                                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                                            <span class="material-symbols-rounded text-[11px]">inventory_2</span>
                                                            <span>Produk Umum</span>
                                                        </span>
                                                    </template>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 text-center text-gray-600" x-text="it.qtySent + ' ' + it.unit"></td>
                                        <td class="px-3 py-2.5 text-center font-bold text-gray-900" x-text="it.qtyReceived + ' ' + it.unit"></td>
                                        <td class="px-3 py-2.5 text-center font-bold font-mono">
                                            <span :class="(it.qtyReceived - it.qtySent) === 0 ? 'text-emerald-600' : 'text-rose-600'"
                                                  x-text="(it.qtyReceived - it.qtySent) === 0 ? 'Sesuai' : (it.qtyReceived - it.qtySent)"></span>
                                        </td>
                                        <td class="px-4 py-2.5 text-gray-700" x-text="it.condition + (it.note ? ' (' + it.note + ')' : '')"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-200">
                        <span class="font-bold text-gray-500 text-[10px] uppercase block mb-1">CATATAN BAST DOKUMEN:</span>
                        <p class="text-gray-800 italic" x-text="selectedDoc?.generalNotes || 'Tidak ada catatan tambahan.'"></p>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-end bg-slate-50">
                    <button type="button"
                            @click="showBastModal = false"
                            class="px-5 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-900 text-white font-bold text-xs transition-colors cursor-pointer">
                        Tutup
                    </button>
                </div>

            </div>
        </div>
    </template>

    <!-- TOAST NOTIFICATION -->
    <div x-show="showToast"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-4"
         style="display: none;"
         class="fixed bottom-6 right-6 z-50 bg-gray-900 text-white px-5 py-3.5 rounded-2xl shadow-xl flex items-center gap-3 border border-gray-800">
        <span class="material-symbols-rounded text-emerald-400 text-[20px]">check_circle</span>
        <span class="text-xs font-semibold" x-text="toastMessage"></span>
    </div>

</div>
@endsection
