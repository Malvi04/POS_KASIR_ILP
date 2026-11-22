@extends('layouts.app')

@section('title', 'Barang Masuk Retur (Cabang ke Gudang) - Manager ILP POS')

@section('content')
<div x-data="{
    searchQuery: '',
    typeFilter: 'all',
    statusFilter: 'all',

    // Modals
    showVerifyModal: false,
    showBastModal: false,
    showReturnBackModal: false,
    showRejectInboundModal: false,

    // Active Selection
    selectedDoc: null,
    selectedDocForReject: null,
    rejectReason: '',
    verifyNotes: '',
    isSubmitting: false,

    // Return Back Modal Form State
    returnBackDoc: null,
    returnBackDriver: '',
    returnBackPlate: '',
    returnBackNotes: '',
    returnBackItems: [],
    isSubmittingReturn: false,

    // Toast
    toastMessage: '',
    toastType: 'success',
    showToast: false,

    returnInDocuments: [],

    async initReturnIn() {
        try {
            const res = await fetch('/api/returns?direction=toko_ke_gudang', { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const json = await res.json();
                if (json.success && Array.isArray(json.data)) {
                    this.returnInDocuments = json.data.map(d => this.normalizeDoc({
                        id: d.id,
                        docNo: d.docNo,
                        date: d.date,
                        originRef: d.ref || '-',
                        warehouseOrigin: d.branchOrigin || 'Cabang',
                        driverName: d.driver || '-',
                        plateNumber: d.plate || '-',
                        type: d.conditionType || 'rusak',
                        status: d.statusKey === 'selesai' ? 'SELESAI' : (d.statusKey === 'disetujui' ? 'DITERIMA_LENGKAP' : (d.statusKey === 'ditolak' ? 'DITOLAK' : 'MENUNGGU_VERIFIKASI')),
                        generalNotes: d.conditionDesc || '',
                        items: (d.products || []).map(p => ({
                            sku: p.sku || '-',
                            name: p.name,
                            qtySent: parseInt(p.qty, 10) || 1,
                            condition: d.conditionLabel || '-',
                            unit: 'Pcs'
                        }))
                    }));
                    return;
                }
            }
        } catch(e) {
            console.error('Failed to load return-in docs from API:', e);
        }
        this.returnInDocuments = [];
    },

    normalizeDoc(doc) {
        if (!doc) return doc;
        if (Array.isArray(doc.items)) {
            doc.items.forEach(it => {
                it.qtySent = parseInt(it.qtySent) || 0;
                it.qtyActual = parseInt(it.qtyActual !== undefined ? it.qtyActual : it.qtySent) || 0;
                it.qtyAccepted = parseInt(it.qtyAccepted !== undefined ? it.qtyAccepted : (doc.status === 'DITERIMA_LENGKAP' ? it.qtyActual : 0)) || 0;
                it.qtyRejected = parseInt(it.qtyRejected !== undefined ? it.qtyRejected : (doc.status === 'MENUNGGU_PENGEMBALIAN' ? it.qtyActual : 0)) || 0;
                it.qtyReturned = parseInt(it.qtyReturned) || 0;
                it.sisaDiGudang = Math.max(0, it.qtyRejected - it.qtyReturned);
                it.rejectReason = it.rejectReason || '';
                it.unit = 'Pcs';
            });
        }
        doc.sisaBarangDitolakDiGudang = Array.isArray(doc.items)
            ? doc.items.reduce((acc, it) => acc + (it.sisaDiGudang || 0), 0)
            : 0;
        if (!doc.returnHistory) doc.returnHistory = [];
        return doc;
    },

    saveDocs() {
        // Dokumen retur masuk dikelola melalui API backend PostgreSQL
    },

    getDefaultDocs() {
        return [];
    },

    get filteredDocuments() {
        return this.returnInDocuments.filter(doc => {
            if (this.typeFilter !== 'all' && doc.type !== this.typeFilter) return false;
            if (this.statusFilter !== 'all' && doc.status !== this.statusFilter) return false;
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                const matchNo = (doc.docNo || '').toLowerCase().includes(q);
                const matchRef = (doc.originRef || '').toLowerCase().includes(q);
                const matchWh = (doc.warehouseOrigin || '').toLowerCase().includes(q);
                const matchDriver = (doc.driverName || '').toLowerCase().includes(q) || (doc.plateNumber || '').toLowerCase().includes(q);
                const matchProd = Array.isArray(doc.items) && doc.items.some(it => (it.name || '').toLowerCase().includes(q) || (it.sku || '').includes(q));
                return matchNo || matchRef || matchWh || matchDriver || matchProd;
            }
            return true;
        });
    },

    get totalCount() {
        return this.returnInDocuments.length;
    },

    get pendingCount() {
        return this.returnInDocuments.filter(d => d.status === 'MENUNGGU_VERIFIKASI').length;
    },

    get acceptedDocCount() {
        return this.returnInDocuments.filter(d => d.status === 'DITERIMA_LENGKAP' || d.status === 'DITERIMA_SEBAGIAN' || d.status === 'SELESAI').length;
    },

    get pendingReturnCount() {
        return this.returnInDocuments.filter(d => (d.sisaBarangDitolakDiGudang || 0) > 0 || d.status === 'MENUNGGU_PENGEMBALIAN' || d.status === 'PENGEMBALIAN_SEBAGIAN').length;
    },

    openVerification(doc) {
        // Deep clone doc
        this.selectedDoc = JSON.parse(JSON.stringify(doc));
        // Prepare items verification form
        if (Array.isArray(this.selectedDoc.items)) {
            this.selectedDoc.items.forEach(it => {
                it.qtyActual = it.qtyActual !== undefined ? it.qtyActual : it.qtySent;
                // Default: jika belum diverifikasi, set diterima = aktual, ditolak = 0
                if (it.qtyAccepted === 0 && it.qtyRejected === 0) {
                    it.qtyAccepted = it.qtyActual;
                    it.qtyRejected = 0;
                }
                it.rejectReason = it.rejectReason || '';
                it.unit = 'Pcs';
            });
        }
        this.verifyNotes = doc.generalNotes || '';
        this.isSubmitting = false;
        this.showVerifyModal = true;
    },

    openBastView(doc) {
        this.selectedDoc = doc;
        this.showBastModal = true;
    },

    openReturnBackModal(doc) {
        this.returnBackDoc = doc;
        this.returnBackDriver = doc.driverName || '';
        this.returnBackPlate = doc.plateNumber || '';
        this.returnBackNotes = '';
        this.isSubmittingReturn = false;

        // Siapkan item yang memiliki sisa barang ditolak di gudang
        this.returnBackItems = (doc.items || [])
            .filter(it => (it.sisaDiGudang || 0) > 0)
            .map(it => ({
                sku: it.sku,
                name: it.name,
                unit: it.unit || 'Pcs',
                rejectReason: it.rejectReason || 'Ditolak QC DC',
                qtyRejectedTotal: it.qtyRejected,
                qtyAlreadyReturned: it.qtyReturned || 0,
                sisaDiGudang: it.sisaDiGudang,
                qtyReturnNow: it.sisaDiGudang // Default: kembalikan semua sisa
            }));

        this.showReturnBackModal = true;
    },

    // Validasi form verifikasi fisik
    isVerificationValid() {
        if (!this.selectedDoc || !Array.isArray(this.selectedDoc.items)) return false;
        for (let it of this.selectedDoc.items) {
            const actual = parseInt(it.qtyActual) || 0;
            const accepted = parseInt(it.qtyAccepted) || 0;
            const rejected = parseInt(it.qtyRejected) || 0;
            if (actual < 0 || accepted < 0 || rejected < 0) return false;
            if ((accepted + rejected) !== actual) return false;
            if (rejected > 0 && !String(it.rejectReason || '').trim()) return false;
        }
        return true;
    },

    // Total diterima pada modal verifikasi
    totalVerifyAccepted() {
        if (!this.selectedDoc || !Array.isArray(this.selectedDoc.items)) return 0;
        return this.selectedDoc.items.reduce((acc, it) => acc + (parseInt(it.qtyAccepted) || 0), 0);
    },

    // Total ditolak pada modal verifikasi
    totalVerifyRejected() {
        if (!this.selectedDoc || !Array.isArray(this.selectedDoc.items)) return 0;
        return this.selectedDoc.items.reduce((acc, it) => acc + (parseInt(it.qtyRejected) || 0), 0);
    },

    // Simpan hasil pemeriksaan fisik & update Stok Barang Retur (BUKAN STOK MASTER)
    confirmReturnReceipt() {
        if (this.isSubmitting) return;
        if (!this.isVerificationValid()) {
            this.showToastNotice('Mohon periksa kembali: Total Diterima + Ditolak harus sama dengan Kuantitas Aktual, dan barang ditolak wajib memiliki alasan.', 'error');
            return;
        }

        this.isSubmitting = true;
        const nowStr = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) + ' • ' +
                       new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') + ' WIB';

        const original = this.returnInDocuments.find(d => d.id === this.selectedDoc.id);
        if (original) {
            let totalAccepted = 0;
            let totalRejected = 0;
            let hasVarianceWithSent = false;

            original.items = this.selectedDoc.items.map(it => {
                const actual = Math.max(0, parseInt(it.qtyActual, 10) || 0);
                const accepted = Math.max(0, parseInt(it.qtyAccepted, 10) || 0);
                const rejected = Math.max(0, parseInt(it.qtyRejected, 10) || 0);
                const sent = parseInt(it.qtySent, 10) || 0;

                if (actual !== sent) hasVarianceWithSent = true;
                totalAccepted += accepted;
                totalRejected += rejected;

                return {
                    sku: it.sku,
                    name: it.name,
                    unit: 'Pcs',
                    qtySent: sent,
                    qtyActual: actual,
                    qtyAccepted: accepted,
                    qtyRejected: rejected,
                    qtyReturned: 0,
                    sisaDiGudang: rejected,
                    condition: it.condition,
                    note: it.note || '',
                    rejectReason: rejected > 0 ? (it.rejectReason || 'Ditolak QC DC') : ''
                };
            });

            // Tentukan status dokumen
            if (totalAccepted === 0 && totalRejected > 0) {
                original.status = 'DITOLAK';
            } else if (totalRejected === 0 && !hasVarianceWithSent) {
                original.status = 'DITERIMA_LENGKAP';
            } else if (totalAccepted > 0 && totalRejected > 0) {
                original.status = 'DITERIMA_SEBAGIAN';
            } else if (totalAccepted > 0 && totalRejected === 0 && hasVarianceWithSent) {
                original.status = 'DITERIMA_SEBAGIAN';
            } else {
                original.status = 'DITERIMA_LENGKAP';
            }

            original.receivedAt = nowStr;
            original.receiverName = @js(auth()->user()?->name ?? 'Manager');
            original.generalNotes = this.verifyNotes.trim() || 'Verifikasi fisik barang masuk retur dari cabang selesai diperiksa.';
            original.sisaBarangDitolakDiGudang = totalRejected;
            if (!original.returnHistory) original.returnHistory = [];

            if (original.id) {
                const targetApiStatus = totalAccepted === 0 && totalRejected > 0 ? 'ditolak' : 'selesai';
                const rejectReasons = (this.selectedDoc.items || [])
                    .filter(it => (parseInt(it.qtyRejected, 10) || 0) > 0 && it.rejectReason)
                    .map(it => it.rejectReason)
                    .join(', ');
                const reasonPayload = rejectReasons || this.verifyNotes.trim() || 'Verifikasi fisik barang masuk retur selesai diperiksa.';

                fetch(`/api/returns/${original.id}/status`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        status: targetApiStatus,
                        reason: reasonPayload,
                        notes: this.verifyNotes.trim()
                    })
                }).then(() => {
                    if (window.PosRealtime?.broadcast) {
                        window.PosRealtime.broadcast('returns_updated', { id: original.id, status: targetApiStatus });
                    }
                }).catch(() => {});
            }

            this.showVerifyModal = false;
            this.isSubmitting = false;

            let notif = totalAccepted === 0 && totalRejected > 0
                ? `Dokumen ${original.docNo} telah Ditolak! Kuantitas ditolak: ${totalRejected} Pcs.`
                : `Dokumen ${original.docNo} selesai diverifikasi! Diterima ke Stok Retur: ${totalAccepted} Pcs`;
            if (totalRejected > 0 && totalAccepted > 0) {
                notif += `, Ditolak di Gudang: ${totalRejected} Pcs.`;
            }
            this.showToastNotice(notif, 'success');
        }
    },

    openRejectInboundModal(doc) {
        this.selectedDocForReject = doc;
        this.rejectReason = '';
        this.showRejectInboundModal = true;
    },

    async confirmRejectInbound() {
        if (!this.selectedDocForReject || this.isSubmitting) return;
        const reason = (this.rejectReason || '').trim() || 'Ditolak saat pemeriksaan fisik gudang';
        this.isSubmitting = true;
        try {
            const res = await fetch(`/api/returns/${this.selectedDocForReject.id}/status`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    status: 'ditolak',
                    reason: reason,
                    notes: reason
                })
            });
            if (res.ok) {
                this.selectedDocForReject.status = 'DITOLAK';
                if (window.PosRealtime?.broadcast) {
                    window.PosRealtime.broadcast('returns_updated', { id: this.selectedDocForReject.id, status: 'ditolak' });
                }
                this.showRejectInboundModal = false;
                this.showToastNotice(`Pengajuan ${this.selectedDocForReject.docNo} berhasil ditolak.`, 'success');
            } else {
                const err = await res.json().catch(() => ({}));
                this.showToastNotice(err.message || 'Gagal menolak dokumen di server.', 'error');
            }
        } catch (e) {
            console.error('Failed to reject inbound return doc:', e);
            this.showToastNotice('Gagal menghubungi server.', 'error');
        } finally {
            this.isSubmitting = false;
        }
    },

    async rejectFromVerifyModal() {
        if (!this.selectedDoc || this.isSubmitting) return;
        const reason = (this.verifyNotes || '').trim() || 'Ditolak seluruhnya saat verifikasi fisik gudang';
        this.isSubmitting = true;
        try {
            const res = await fetch(`/api/returns/${this.selectedDoc.id}/status`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    status: 'ditolak',
                    reason: reason,
                    notes: reason
                })
            });
            if (res.ok) {
                const original = this.returnInDocuments.find(d => d.id === this.selectedDoc.id);
                if (original) {
                    original.status = 'DITOLAK';
                }
                if (window.PosRealtime?.broadcast) {
                    window.PosRealtime.broadcast('returns_updated', { id: this.selectedDoc.id, status: 'ditolak' });
                }
                this.showVerifyModal = false;
                this.showToastNotice(`Dokumen ${this.selectedDoc.docNo} berhasil ditolak.`, 'success');
            } else {
                const err = await res.json().catch(() => ({}));
                this.showToastNotice(err.message || 'Gagal menolak dokumen di server.', 'error');
            }
        } catch (e) {
            console.error('Failed to reject from verify modal:', e);
            this.showToastNotice('Gagal menghubungi server.', 'error');
        } finally {
            this.isSubmitting = false;
        }
    },

    // Konfirmasi Pengeluaran & Pengembalian Barang Ditolak ke Toko
    confirmReturnBackToStore() {
        if (this.isSubmittingReturn || !this.returnBackDoc) return;

        // Validasi kuantitas yang dikembalikan
        let totalReturnNow = 0;
        for (let item of this.returnBackItems) {
            const qty = parseInt(item.qtyReturnNow) || 0;
            if (qty < 0 || qty > item.sisaDiGudang) {
                this.showToastNotice('Jumlah barang yang dikembalikan tidak valid (melebihi sisa fisik di gudang).', 'error');
                return;
            }
            totalReturnNow += qty;
        }

        if (totalReturnNow <= 0) {
            this.showToastNotice('Kuantitas barang yang dikembalikan minimal 1 Pcs.', 'error');
            return;
        }

        this.isSubmittingReturn = true;
        const nowStr = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) + ' • ' +
                       new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') + ' WIB';

        const original = this.returnInDocuments.find(d => d.id === this.returnBackDoc.id);
        if (original) {
            // Update items
            this.returnBackItems.forEach(retItem => {
                const target = original.items.find(i => i.sku === retItem.sku);
                if (target) {
                    const returnQty = parseInt(retItem.qtyReturnNow) || 0;
                    target.qtyReturned = (target.qtyReturned || 0) + returnQty;
                    target.sisaDiGudang = Math.max(0, (target.qtyRejected || 0) - target.qtyReturned);
                }
            });

            // Hitung sisa total barang ditolak yang masih berada di gudang
            const remainingInDC = original.items.reduce((acc, it) => acc + (it.sisaDiGudang || 0), 0);
            original.sisaBarangDitolakDiGudang = remainingInDC;

            // Catat ke riwayat pengembalian dokumen
            if (!original.returnHistory) original.returnHistory = [];
            original.returnHistory.unshift({
                id: 'RET-OUT-' + Date.now(),
                timestamp: nowStr,
                driver: this.returnBackDriver.trim() || 'Driver Ekspedisi Cabang',
                plateNumber: this.returnBackPlate.trim() || '-',
                notes: this.returnBackNotes.trim() || 'Pengembalian barang ditolak ke cabang pengaju.',
                totalReturned: totalReturnNow,
                items: this.returnBackItems.filter(it => (parseInt(it.qtyReturnNow) || 0) > 0).map(it => ({
                    sku: it.sku,
                    name: it.name,
                    qtyReturned: parseInt(it.qtyReturnNow) || 0,
                    unit: 'Pcs',
                    reason: it.rejectReason
                }))
            });

            // Update status dokumen secara bertahap
            const totalAccepted = original.items.reduce((acc, it) => acc + (it.qtyAccepted || 0), 0);
            if (remainingInDC === 0) {
                // Seluruh barang ditolak telah selesai dikembalikan ke cabang
                original.status = 'SELESAI';
            } else {
                // Masih ada sisa barang ditolak di gudang
                original.status = 'PENGEMBALIAN_SEBAGIAN';
            }

            this.saveDocs();
            this.showReturnBackModal = false;
            this.isSubmittingReturn = false;

            this.showToastNotice(`Berhasil mencatat pengembalian ${totalReturnNow} Pcs barang ditolak ke ${original.warehouseOrigin}! Sisa fisik di gudang: ${remainingInDC} Pcs.`, 'success');
        }
    },

    showToastNotice(msg, type = 'success') {
        this.toastMessage = msg;
        this.toastType = type;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3800);
    }
}"
x-init="initReturnIn()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">

    <!-- Sidebar Manager -->
    <x-manager-sidebar active="retur_masuk" />

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden relative">
        <!-- Top Header Manager -->
        <header class="h-20 bg-[#f8f9fa] border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-3">
                <h1 class="text-[20px] font-bold text-gray-900">Barang Masuk Retur</h1>
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200 flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[14px]">assignment_return</span>
                    <span>Verifikasi Fisik Gudang</span>
                </span>
            </div>

            <div class="flex items-center gap-4 text-gray-500">
                <x-notification-bell role="MANAGER" />
                <button type="button" @click="window.PosRealtime?.syncNow?.(); initReturnIn();" class="hover:text-gray-900 transition-colors cursor-pointer" title="Sinkronkan Data">
                    <span class="material-symbols-rounded text-[24px]" :class="$store.network?.isChecking ? 'animate-spin text-[#0052cc]' : ''">autorenew</span>
                </button>
                <div class="h-6 w-px bg-gray-300"></div>
                <x-realtime-network-badge />
            </div>
        </header>

        <!-- Main Scrollable Area -->
        <div class="flex-1 overflow-y-auto p-6 md:p-8 bg-[#f8f9fa]">
            <div class="max-w-[1400px] mx-auto space-y-6">

                <!-- Breadcrumb & Header Title -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-black text-gray-900 tracking-tight">Verifikasi Barang Masuk Retur</h2>
                        <p class="text-xs text-gray-500 mt-1 font-medium">Verifikasi fisik dan penerimaan barang retur dari Toko Pusat/Cabang ke Gudang Pusat.</p>
                    </div>

                    <div class="flex items-center gap-2.5 flex-wrap">
                        <a href="{{ route('manager.retur') }}" class="px-3.5 py-2 rounded-xl border border-gray-200 hover:bg-slate-50 text-slate-700 text-xs font-bold flex items-center gap-1.5 transition-colors bg-white shadow-2xs">
                            <span class="material-symbols-rounded text-[16px] text-[#0052cc]">verified</span>
                            <span>Persetujuan Retur Cabang</span>
                        </a>
                        <a href="{{ route('manager.inventory') }}?tab=retur" class="px-3.5 py-2 rounded-xl border border-gray-200 hover:bg-slate-50 text-slate-700 text-xs font-bold flex items-center gap-1.5 transition-colors bg-white shadow-2xs">
                            <span class="material-symbols-rounded text-[16px] text-purple-600">inventory_2</span>
                            <span>Stok Karantina Retur</span>
                        </a>
                        <!-- Portal Retur Masuk Badge -->
                        <div class="bg-white border border-gray-200 rounded-xl px-4 py-2 flex items-center gap-3 shadow-2xs">
                            <div class="w-2.5 h-2.5 rounded-full bg-purple-600 animate-pulse"></div>
                            <div class="flex flex-col">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">PORTAL RETUR MASUK</span>
                                <span class="text-xs font-extrabold text-[#0052cc]">Toko/Cabang &rarr; Gudang Pusat</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4 Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Stat 1: Total Dokumen Retur Masuk -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 tracking-wider uppercase">TOTAL DOKUMEN RETUR MASUK</span>
                            <h3 class="text-2xl font-black text-gray-900 mt-1" x-text="totalCount">0</h3>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Pengajuan Toko &amp; Cabang</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">assignment_return</span>
                        </div>
                    </div>

                    <!-- Stat 2: Menunggu Verifikasi -->
                    <div class="bg-white rounded-2xl border border-amber-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-amber-500">
                        <div>
                            <span class="text-[10px] font-bold text-amber-700 tracking-wider uppercase">MENUNGGU VERIFIKASI</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <h3 class="text-2xl font-black text-amber-600" x-text="pendingCount">0</h3>
                                <span class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-md">Tiba di Gudang</span>
                            </div>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Perlu verifikasi fisik aktual</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">pending_actions</span>
                        </div>
                    </div>

                    <!-- Stat 3: Diterima ke Stok Retur -->
                    <div class="bg-white rounded-2xl border border-emerald-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-emerald-500">
                        <div>
                            <span class="text-[10px] font-bold text-emerald-700 tracking-wider uppercase">DITERIMA KE STOK RETUR</span>
                            <h3 class="text-2xl font-black text-emerald-600 mt-1" x-text="acceptedDocCount">0</h3>
                            <span class="text-[11px] text-emerald-600 font-medium mt-0.5 block">Tercatat di Stok Barang Retur DC</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">verified</span>
                        </div>
                    </div>

                    <!-- Stat 4: Menunggu Pengembalian -->
                    <div class="bg-white rounded-2xl border border-rose-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-rose-500">
                        <div>
                            <span class="text-[10px] font-bold text-rose-700 tracking-wider uppercase">MENUNGGU PENGEMBALIAN</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <h3 class="text-2xl font-black text-rose-600" x-text="pendingReturnCount">0</h3>
                                <span class="text-[10px] font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded-md">Fisik di Gudang</span>
                            </div>
                            <span class="text-[11px] text-rose-600 font-medium mt-0.5 block">Barang ditolak perlu dikirim ke toko</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">undo</span>
                        </div>
                    </div>
                </div>

                <!-- Filters & Search Toolbar -->
                <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="relative flex-1 w-full max-w-xl">
                        <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="Cari nomor dokumen, referensi klaim, cabang asal, driver, atau nama SKU..."
                               class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 text-[13px] shadow-xs font-medium bg-slate-50/50">
                    </div>

                    <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                        <!-- Type Filter -->
                        <div class="relative">
                            <select x-model="typeFilter"
                                    class="appearance-none bg-white border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-[13px] text-gray-700 focus:outline-none focus:border-[#0052cc] shadow-xs font-semibold cursor-pointer">
                                <option value="all">Semua Jenis Retur Masuk</option>
                                <option value="RETUR_RUSAK">Retur Rusak / Cacat</option>
                                <option value="RETUR_KEDALUWARSA">Retur Kedaluwarsa (ED)</option>
                                <option value="RETUR_OVERSTOCK">Retur Overstock</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                        </div>

                        <!-- Status Filter -->
                        <div class="relative">
                            <select x-model="statusFilter"
                                    class="appearance-none bg-white border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-[13px] text-gray-700 focus:outline-none focus:border-[#0052cc] shadow-xs font-semibold cursor-pointer">
                                <option value="all">Semua Status</option>
                                <option value="MENUNGGU_VERIFIKASI">Menunggu Verifikasi</option>
                                <option value="DITERIMA_LENGKAP">Diterima Lengkap</option>
                                <option value="DITERIMA_SEBAGIAN">Diterima Sebagian</option>
                                <option value="MENUNGGU_PENGEMBALIAN">Menunggu Pengembalian</option>
                                <option value="PENGEMBALIAN_SEBAGIAN">Pengembalian Sebagian</option>
                                <option value="DITOLAK">Ditolak</option>
                                <option value="SELESAI">Selesai</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                        </div>
                    </div>
                </div>

                <!-- Documents Table (Struktur Kolom Sesuai Ketentuan & Referensi) -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-rounded text-[20px] text-purple-700">move_to_inbox</span>
                            <h3 class="text-sm font-bold text-gray-900">Daftar Dokumen Barang Masuk Retur</h3>
                        </div>
                        <span class="text-xs text-gray-500 font-medium" x-text="'Menampilkan ' + filteredDocuments.length + ' dokumen retur cabang'"></span>
                    </div>

                    <div class="overflow-x-auto min-w-0 w-full">
                        <table class="w-full text-left border-collapse text-xs min-w-[1100px]">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold uppercase text-[11px] tracking-wider whitespace-nowrap">
                                    <th class="px-4 py-3.5 text-left">No. Dokumen</th>
                                    <th class="px-4 py-3.5 text-left">Jenis Retur</th>
                                    <th class="px-4 py-3.5 text-left">Ref. Klaim</th>
                                    <th class="px-4 py-3.5 text-left">Cabang Asal</th>
                                    <th class="px-4 py-3.5 text-left">Tanggal</th>
                                    <th class="px-4 py-3.5 text-left">SKU</th>
                                    <th class="px-4 py-3.5 text-left">Nama Produk</th>
                                    <th class="px-4 py-3.5 text-center">Qty Dikirim</th>
                                    <th class="px-4 py-3.5 text-left">Kondisi Fisik</th>
                                    <th class="px-4 py-3.5 text-left">Pengirim</th>
                                    <th class="px-4 py-3.5 text-left">Plat Nomor</th>
                                    <th class="px-4 py-3.5 text-center">Status</th>
                                    <th class="px-4 py-3.5 text-center">Aksi Verifikasi</th>
                                </tr>
                            </thead>
                            <tbody class="text-slate-700">
                                <template x-for="doc in filteredDocuments" :key="doc.id">
                                    <template x-for="(it, iIdx) in ((doc.items && doc.items.length > 0) ? doc.items : [{ sku: '-', name: '-', qtySent: 0, unit: 'Pcs', condition: '-' }])" :key="iIdx">
                                        <tr class="hover:bg-blue-50/30 transition-colors" :class="iIdx === 0 ? 'border-t border-gray-200' : 'border-t border-gray-100'">
                                            <!-- Parent Col 1: No. Dokumen -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white font-mono font-bold text-gray-900 text-[13px]">
                                                    <span x-text="doc.docNo"></span>
                                                </td>
                                            </template>

                                            <!-- Parent Col 2: Jenis Retur -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white">
                                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold whitespace-nowrap"
                                                          :class="doc.type === 'RETUR_RUSAK' ? 'bg-rose-50 text-rose-700 border border-rose-200' : (doc.type === 'RETUR_KEDALUWARSA' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-blue-50 text-blue-700 border border-blue-200')"
                                                          x-text="doc.typeLabel"></span>
                                                </td>
                                            </template>

                                            <!-- Parent Col 3: Ref. Klaim -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white font-mono text-[#0052cc] font-bold text-xs" x-text="doc.originRef || '-'">
                                                </td>
                                            </template>

                                            <!-- Parent Col 4: Cabang Asal -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white font-bold text-gray-900 text-xs" x-text="doc.warehouseOrigin">
                                                </td>
                                            </template>

                                            <!-- Parent Col 5: Tanggal -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-xs text-gray-500 font-medium" x-text="doc.date">
                                                </td>
                                            </template>

                                            <!-- Child Col 6: SKU -->
                                            <td class="px-4 py-3 align-middle whitespace-nowrap font-mono font-bold text-gray-700 text-xs" x-text="it.sku || '-'"></td>

                                            <!-- Child Col 7: Nama Produk -->
                                            <td class="px-4 py-3 align-middle whitespace-nowrap font-bold text-gray-900 text-xs max-w-[200px] truncate" :title="it.name" x-text="it.name"></td>

                                            <!-- Child Col 8: Qty Dikirim -->
                                            <td class="px-4 py-3 align-middle whitespace-nowrap text-center font-mono font-bold text-xs bg-slate-50/50">
                                                <span class="inline-block bg-blue-50 text-[#0047b3] border border-blue-200 px-2.5 py-0.5 rounded font-black font-mono text-xs" x-text="it.qtySent + ' ' + (it.unit || 'Pcs')"></span>
                                            </td>

                                            <!-- Child Col 9: Kondisi Fisik -->
                                            <td class="px-4 py-3 align-middle whitespace-nowrap text-xs text-slate-600 font-medium max-w-[180px] truncate" :title="it.condition" x-text="it.condition || '-'"></td>

                                            <!-- Parent Col 10: Pengirim -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-xs font-bold text-gray-800" x-text="doc.driverName || '-'">
                                                </td>
                                            </template>

                                            <!-- Parent Col 11: Plat Nomor -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white font-mono text-xs text-gray-500" x-text="doc.plateNumber || '-'">
                                                </td>
                                            </template>

                                            <!-- Parent Col 12: Status -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-center">
                                                    <template x-if="doc.status === 'MENUNGGU_VERIFIKASI'">
                                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200 whitespace-nowrap">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>
                                                            <span>Menunggu Verifikasi</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="doc.status === 'DITERIMA_LENGKAP'">
                                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 whitespace-nowrap">
                                                            <span class="material-symbols-rounded text-[14px]">check_circle</span>
                                                            <span>Diterima Lengkap</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="doc.status === 'DITERIMA_SEBAGIAN'">
                                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-800 border border-blue-200 whitespace-nowrap">
                                                            <span class="material-symbols-rounded text-[14px]">pie_chart</span>
                                                            <span>Diterima Sebagian</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="doc.status === 'MENUNGGU_PENGEMBALIAN'">
                                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200 whitespace-nowrap">
                                                            <span class="material-symbols-rounded text-[14px]">undo</span>
                                                            <span>Menunggu Pengembalian</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="doc.status === 'PENGEMBALIAN_SEBAGIAN'">
                                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-orange-50 text-orange-700 border border-orange-200 whitespace-nowrap">
                                                            <span class="material-symbols-rounded text-[14px]">schedule</span>
                                                            <span>Pengembalian Sebagian</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="doc.status === 'DITOLAK'">
                                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200 whitespace-nowrap">
                                                            <span class="material-symbols-rounded text-[14px]">cancel</span>
                                                            <span>Ditolak</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="doc.status === 'SELESAI'">
                                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 whitespace-nowrap">
                                                            <span class="material-symbols-rounded text-[14px]">done_all</span>
                                                            <span>Selesai</span>
                                                        </span>
                                                    </template>
                                                </td>
                                            </template>

                                            <!-- Parent Col 13: Aksi Verifikasi -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap text-center bg-white">
                                                    <div class="inline-flex items-center justify-center gap-1.5">
                                                        <template x-if="doc.status === 'MENUNGGU_VERIFIKASI'">
                                                            <div class="inline-flex items-center gap-1.5">
                                                                <button type="button"
                                                                        @click="openVerification(doc)"
                                                                        class="px-3 py-1.5 bg-[#0052cc] hover:bg-[#003994] text-white rounded-xl text-xs font-bold transition-all shadow-xs inline-flex items-center gap-1.5 cursor-pointer">
                                                                    <span class="material-symbols-rounded text-[16px]">fact_check</span>
                                                                    <span>Verifikasi Fisik</span>
                                                                </button>
                                                                <button type="button"
                                                                        @click="openRejectInboundModal(doc)"
                                                                        class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl text-xs font-bold transition-all shadow-xs inline-flex items-center gap-1 cursor-pointer"
                                                                        title="Tolak Pengajuan Retur dari Toko">
                                                                    <span class="material-symbols-rounded text-[15px]">cancel</span>
                                                                    <span>Tolak</span>
                                                                </button>
                                                            </div>
                                                        </template>
                                                        <template x-if="doc.status !== 'MENUNGGU_VERIFIKASI'">
                                                            <button type="button"
                                                                    @click="openBastView(doc)"
                                                                    class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-xl text-xs font-bold transition-colors inline-flex items-center gap-1.5 cursor-pointer">
                                                                <span class="material-symbols-rounded text-[16px]">receipt_long</span>
                                                                <span>Lihat BAST</span>
                                                            </button>
                                                        </template>

                                                        <!-- Tombol Tindak Lanjut Pengembalian Barang Ditolak jika masih ada sisa di DC -->
                                                        <template x-if="(doc.sisaBarangDitolakDiGudang || 0) > 0">
                                                            <button type="button"
                                                                    @click="openReturnBackModal(doc)"
                                                                    title="Proses pengeluaran dan pengembalian barang ditolak ke cabang"
                                                                    class="px-2.5 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 rounded-xl text-xs font-bold transition-colors inline-flex items-center gap-1 cursor-pointer">
                                                                <span class="material-symbols-rounded text-[15px]">local_shipping</span>
                                                                <span>Kembalikan</span>
                                                            </button>
                                                        </template>
                                                    </div>
                                                </td>
                                            </template>
                                        </tr>
                                    </template>
                                </template>
                                <tr x-show="filteredDocuments.length === 0">
                                    <td colspan="13" class="py-10 text-center text-slate-400">
                                        <span class="material-symbols-rounded text-3xl block text-slate-300 mb-1">archive</span>
                                        <span class="font-bold text-xs text-slate-600">Tidak ada dokumen retur yang cocok.</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- MODAL 1: VERIFIKASI FISIK BARANG MASUK RETUR (PEMISAHAN KEPUTUSAN DITERIMA VS DITOLAK) -->
    <template x-teleport="body">
        <div x-show="showVerifyModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showVerifyModal"
                 @click.away="showVerifyModal = false"
                 x-transition.scale.origin.bottom
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[92vh] flex flex-col overflow-hidden border border-gray-200">

                <div class="px-6 py-4.5 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]">assignment_return</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-gray-900">Verifikasi Fisik Barang Masuk Retur</h3>
                            <span class="font-mono text-xs text-gray-500" x-text="selectedDoc?.docNo + ' • ' + selectedDoc?.originRef"></span>
                        </div>
                    </div>
                    <button @click="showVerifyModal = false" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[24px]">close</span>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-4 text-xs">
                    <!-- Delivery Header Info (Konteks Toko/Cabang -> Gudang Pusat) -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-4 bg-slate-50 rounded-xl border border-gray-200">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">JENIS RETUR</span>
                            <span class="font-bold text-purple-700" x-text="selectedDoc?.typeLabel"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">TOKO / CABANG ASAL</span>
                            <span class="font-bold text-gray-800" x-text="selectedDoc?.warehouseOrigin"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">PENGIRIM &amp; PLAT NOMOR</span>
                            <span class="font-bold text-gray-800" x-text="selectedDoc?.driverName + ' (' + selectedDoc?.plateNumber + ')'"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">PETUGAS MANAGER</span>
                            <span class="font-bold text-[#0052cc]">{{ auth()->user()?->name ?? 'Manager' }} (Gudang Pusat)</span>
                        </div>
                    </div>

                    <!-- Panduan Aturan Operasional -->
                    <div class="p-3 bg-blue-50 border border-blue-200 rounded-xl flex items-start gap-2.5 text-blue-900">
                        <span class="material-symbols-rounded text-[18px] text-[#0052cc] shrink-0 mt-0.5">rule</span>
                        <div class="text-[11.5px] leading-relaxed">
                            <strong>Aturan Pemeriksaan Fisik:</strong> Tentukan keputusan per item antara <strong>Terima ke Stok Barang Retur</strong> (saldo karantina retur) dan <strong>Tolak / Kembalikan ke Toko</strong>. Barang yang ditolak secara fisik tetap berada di gudang dan tercatat untuk ditindaklanjuti pengembaliannya secara terpisah. <em>Stok Master normal toko tidak bertambah.</em>
                        </div>
                    </div>

                    <!-- Items Verification Table -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden bg-white">
                        <div class="overflow-x-auto min-w-0 w-full">
                            <table class="w-full text-left border-collapse text-xs min-w-[850px]">
                                <thead class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold uppercase tracking-wider text-[11px] whitespace-nowrap">
                                    <tr>
                                        <th class="px-4 py-3 text-left w-[200px]">PRODUK / SKU</th>
                                        <th class="px-3 py-3 text-center w-[90px]">DIAJUKAN</th>
                                        <th class="px-3 py-3 text-center w-[130px]">FISIK AKTUAL</th>
                                        <th class="px-3 py-3 text-center w-[200px]">KEPUTUSAN PENERIMAAN</th>
                                        <th class="px-3 py-3 text-left w-[140px]">KONDISI FISIK</th>
                                        <th class="px-4 py-3 text-left w-[180px]">CATATAN &amp; ALASAN TOLAK</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="(item, idx) in selectedDoc?.items" :key="idx">
                                        <tr class="hover:bg-slate-50/50">
                                            <!-- Produk / SKU -->
                                            <td class="px-4 py-3">
                                                <span class="font-bold text-gray-900 block" x-text="item.name"></span>
                                                <span class="font-mono text-[11px] text-gray-400" x-text="'SKU: ' + item.sku"></span>
                                            </td>

                                            <!-- Qty Diajukan Cabang -->
                                            <td class="px-3 py-3 text-center font-bold text-gray-700 whitespace-nowrap" x-text="item.qtySent + ' ' + (item.unit || 'Pcs')"></td>

                                            <!-- Qty Aktual Fisik Diperiksa -->
                                            <td class="px-3 py-3 text-center">
                                                <div class="inline-flex items-center gap-1">
                                                    <button type="button"
                                                            @click="
                                                                item.qtyActual = Math.max(0, (parseInt(item.qtyActual) || 0) - 1);
                                                                if (item.qtyAccepted > item.qtyActual) item.qtyAccepted = item.qtyActual;
                                                                item.qtyRejected = item.qtyActual - item.qtyAccepted;
                                                            "
                                                            class="w-6 h-6 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs flex items-center justify-center cursor-pointer">-</button>
                                                    <input type="number"
                                                           min="0"
                                                           x-model.number="item.qtyActual"
                                                           @input="
                                                               item.qtyActual = Math.max(0, parseInt(item.qtyActual) || 0);
                                                               if (item.qtyAccepted > item.qtyActual) item.qtyAccepted = item.qtyActual;
                                                               item.qtyRejected = item.qtyActual - item.qtyAccepted;
                                                           "
                                                           class="w-14 px-1.5 py-1 border border-gray-300 rounded-md text-center font-bold text-gray-900 focus:outline-none focus:border-[#0052cc]">
                                                    <button type="button"
                                                            @click="
                                                                item.qtyActual = (parseInt(item.qtyActual) || 0) + 1;
                                                                item.qtyAccepted = item.qtyAccepted + 1;
                                                            "
                                                            class="w-6 h-6 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs flex items-center justify-center cursor-pointer">+</button>
                                                </div>
                                                <!-- Indikator Selisih Fisik vs Diajukan -->
                                                <template x-if="item.qtyActual !== item.qtySent">
                                                    <span class="block text-[10px] font-bold mt-1"
                                                          :class="item.qtyActual < item.qtySent ? 'text-rose-600' : 'text-emerald-600'"
                                                          x-text="(item.qtyActual - item.qtySent > 0 ? '+' : '') + (item.qtyActual - item.qtySent) + ' Pcs dr diajukan'"></span>
                                                </template>
                                            </td>

                                            <!-- Keputusan Per Item: Terima ke Stok Retur vs Tolak / Kembalikan -->
                                            <td class="px-3 py-3">
                                                <div class="space-y-1.5 bg-slate-50 p-2 rounded-lg border border-slate-200">
                                                    <!-- Terima ke Stok Retur -->
                                                    <div class="flex items-center justify-between gap-1.5">
                                                        <span class="text-[10.5px] font-bold text-emerald-800 flex items-center gap-1">
                                                            <span class="material-symbols-rounded text-[13px] text-emerald-600">verified</span>
                                                            <span>Terima ke Stok Retur:</span>
                                                        </span>
                                                        <input type="number"
                                                               min="0"
                                                               :max="item.qtyActual"
                                                               x-model.number="item.qtyAccepted"
                                                               @input="
                                                                   item.qtyAccepted = Math.max(0, Math.min(item.qtyActual, parseInt(item.qtyAccepted) || 0));
                                                                   item.qtyRejected = item.qtyActual - item.qtyAccepted;
                                                               "
                                                               class="w-14 px-1.5 py-0.5 border border-emerald-300 bg-emerald-50/50 rounded text-center font-bold text-emerald-900 focus:outline-none focus:border-emerald-600">
                                                    </div>

                                                    <!-- Tolak / Kembalikan ke Toko -->
                                                    <div class="flex items-center justify-between gap-1.5">
                                                        <span class="text-[10.5px] font-bold text-rose-800 flex items-center gap-1">
                                                            <span class="material-symbols-rounded text-[13px] text-rose-600">cancel</span>
                                                            <span>Tolak / Retur Toko:</span>
                                                        </span>
                                                        <input type="number"
                                                               min="0"
                                                               :max="item.qtyActual"
                                                               x-model.number="item.qtyRejected"
                                                               @input="
                                                                   item.qtyRejected = Math.max(0, Math.min(item.qtyActual, parseInt(item.qtyRejected) || 0));
                                                                   item.qtyAccepted = item.qtyActual - item.qtyRejected;
                                                               "
                                                               class="w-14 px-1.5 py-0.5 border border-rose-300 bg-rose-50/50 rounded text-center font-bold text-rose-900 focus:outline-none focus:border-rose-600">
                                                    </div>

                                                    <!-- Quick Buttons: Terima Semua / Tolak Semua -->
                                                    <div class="flex items-center justify-end gap-1.5 pt-1 border-t border-slate-200/60">
                                                        <button type="button"
                                                                @click="item.qtyAccepted = item.qtyActual; item.qtyRejected = 0;"
                                                                class="px-1.5 py-0.5 text-[9.5px] font-bold bg-emerald-100 hover:bg-emerald-200 text-emerald-800 rounded cursor-pointer">Semua Terima</button>
                                                        <button type="button"
                                                                @click="item.qtyAccepted = 0; item.qtyRejected = item.qtyActual;"
                                                                class="px-1.5 py-0.5 text-[9.5px] font-bold bg-rose-100 hover:bg-rose-200 text-rose-800 rounded cursor-pointer">Semua Tolak</button>
                                                    </div>

                                                    <!-- Peringatan jika total != aktual -->
                                                    <template x-if="((parseInt(item.qtyAccepted) || 0) + (parseInt(item.qtyRejected) || 0)) !== (parseInt(item.qtyActual) || 0)">
                                                        <span class="text-rose-600 font-bold text-[10px] block text-center">⚠ Total keputusan != Aktual</span>
                                                    </template>
                                                </div>
                                            </td>

                                            <!-- Kondisi Fisik -->
                                            <td class="px-3 py-3">
                                                <select x-model="item.condition"
                                                        class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 bg-white focus:outline-none focus:border-[#0052cc]">
                                                    <option value="BAIK / LAYAK RETUR">Baik / Layak Retur</option>
                                                    <option value="KEMASAN RUSAK / PENYOK">Kemasan Rusak / Penyok</option>
                                                    <option value="BOCOR / REMBES">Bocor / Rembes</option>
                                                    <option value="KEDALUWARSA / ED">Kedaluwarsa / ED</option>
                                                    <option value="CACAT KELALAIAN TOKO">Cacat Kelalaian Toko (Wajib Tolak)</option>
                                                </select>
                                            </td>

                                            <!-- Catatan & Alasan Penolakan -->
                                            <td class="px-4 py-3">
                                                <div class="space-y-1">
                                                    <input type="text"
                                                           x-model="item.note"
                                                           placeholder="Catatan kondisi fisik..."
                                                           class="w-full px-2 py-1 border border-gray-200 rounded-md text-[11px] text-gray-800 focus:outline-none focus:border-[#0052cc]">
                                                    <template x-if="(parseInt(item.qtyRejected) || 0) > 0">
                                                        <input type="text"
                                                               x-model="item.rejectReason"
                                                               placeholder="Wajib: Alasan penolakan..."
                                                               class="w-full px-2 py-1 border rounded-md text-[11px] focus:outline-none"
                                                               :class="!item.rejectReason ? 'border-rose-400 bg-rose-50/50 text-rose-900 placeholder-rose-400' : 'border-gray-300 text-gray-800'">
                                                    </template>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Summary Perhitungan Keputusan -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3 bg-slate-100 rounded-xl text-center">
                        <div>
                            <span class="text-[10px] font-bold text-gray-500 uppercase block">Total Fisik Diperiksa</span>
                            <span class="text-sm font-black text-gray-900" x-text="(selectedDoc?.items || []).reduce((a, b) => a + (parseInt(b.qtyActual) || 0), 0) + ' Pcs'"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-emerald-700 uppercase block">Diterima ke Stok Retur</span>
                            <span class="text-sm font-black text-emerald-600" x-text="totalVerifyAccepted() + ' Pcs'"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-rose-700 uppercase block">Ditolak (Masih di DC)</span>
                            <span class="text-sm font-black text-rose-600" x-text="totalVerifyRejected() + ' Pcs'"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-gray-500 uppercase block">Status Validasi</span>
                            <span class="text-xs font-bold" :class="isVerificationValid() ? 'text-emerald-700' : 'text-rose-600'" x-text="isVerificationValid() ? '✓ Siap Konfirmasi' : '⚠ Periksa Input'"></span>
                        </div>
                    </div>

                    <!-- General Notes -->
                    <div class="space-y-1">
                        <label class="font-bold text-gray-700 text-xs uppercase tracking-wider">CATATAN PEMERIKSAAN &amp; BAST MANAGER:</label>
                        <textarea x-model="verifyNotes"
                                  rows="2"
                                  placeholder="Catatan berita acara serah terima gudang, keterangan segel, kondisi ekspedisi, dll..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-xl text-xs text-gray-800 focus:outline-none focus:border-[#0052cc]"></textarea>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between bg-slate-50">
                    <span class="text-[11px] text-gray-500 font-medium">Stok Master toko tidak akan bertambah. Hanya barang diterima yang masuk ke Stok Retur.</span>
                    <div class="flex items-center gap-2.5">
                        <button type="button"
                                @click="showVerifyModal = false"
                                class="px-4 py-2 rounded-xl text-gray-700 font-bold hover:bg-slate-200 transition-colors cursor-pointer">
                            Batal
                        </button>
                        <button type="button"
                                @click="rejectFromVerifyModal()"
                                :disabled="isSubmitting"
                                class="px-4 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                            <span class="material-symbols-rounded text-[18px]">cancel</span>
                            <span>Tolak Pengajuan</span>
                        </button>
                        <button type="button"
                                @click="confirmReturnReceipt()"
                                :disabled="!isVerificationValid() || isSubmitting"
                                class="px-5 py-2 rounded-xl bg-[#0052cc] hover:bg-[#003994] text-white font-bold transition-all shadow-xs flex items-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                            <span class="material-symbols-rounded text-[18px]">verified</span>
                            <span x-text="isSubmitting ? 'Memproses...' : (totalVerifyAccepted() === 0 && totalVerifyRejected() > 0 ? 'Konfirmasi Tolak Retur' : 'Konfirmasi Penerimaan Fisik')"></span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </template>

    <!-- MODAL 2: KONFIRMASI PENGELUARAN & PENGEMBALIAN BARANG DITOLAK KE TOKO/CABANG -->
    <template x-teleport="body">
        <div x-show="showReturnBackModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showReturnBackModal"
                 @click.away="showReturnBackModal = false"
                 x-transition.scale.origin.bottom
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden border border-gray-200">

                <div class="px-6 py-4.5 border-b border-gray-100 flex items-center justify-between bg-amber-50/50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]">local_shipping</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-gray-900">Pengembalian Barang Ditolak ke Toko/Cabang</h3>
                            <span class="font-mono text-xs text-gray-500" x-text="returnBackDoc?.docNo + ' • Tujuan: ' + returnBackDoc?.warehouseOrigin"></span>
                        </div>
                    </div>
                    <button @click="showReturnBackModal = false" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[24px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs overflow-y-auto max-h-[75vh]">
                    <!-- Informasi & Petunjuk -->
                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl flex items-start gap-2.5 text-amber-900">
                        <span class="material-symbols-rounded text-[18px] text-amber-700 shrink-0 mt-0.5">info</span>
                        <div class="text-[11.5px] leading-relaxed">
                            Barang berikut ditolak saat pemeriksaan QC gudang dan <strong>secara fisik saat ini masih berada di area karantina Gudang Pusat</strong>. Konfirmasikan jumlah barang yang dikeluarkan dan diangkut kembali oleh armada toko.
                        </div>
                    </div>

                    <!-- Tabel Item Barang yang Perlu Dikembalikan -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden bg-white">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold uppercase tracking-wider text-[11px]">
                                <tr>
                                    <th class="px-4 py-2.5">PRODUK / ALASAN PENOLAKAN</th>
                                    <th class="px-3 py-2.5 text-center">TOTAL DITOLAK</th>
                                    <th class="px-3 py-2.5 text-center">SISA DI DC</th>
                                    <th class="px-4 py-2.5 text-center w-[160px]">DIKEMBALIKAN TRIP INI</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(it, idx) in returnBackItems" :key="idx">
                                    <tr>
                                        <td class="px-4 py-2.5">
                                            <span class="font-bold text-gray-900 block" x-text="it.name"></span>
                                            <span class="text-[10.5px] text-rose-700 block mt-0.5 italic" x-text="'Alasan: ' + it.rejectReason"></span>
                                        </td>
                                        <td class="px-3 py-2.5 text-center font-bold text-gray-700" x-text="it.qtyRejectedTotal + ' Pcs'"></td>
                                        <td class="px-3 py-2.5 text-center">
                                            <span class="px-2 py-0.5 rounded font-bold text-rose-700 bg-rose-50" x-text="it.sisaDiGudang + ' Pcs'"></span>
                                        </td>
                                        <td class="px-4 py-2.5 text-center">
                                            <div class="inline-flex items-center gap-1">
                                                <input type="number"
                                                       min="1"
                                                       :max="it.sisaDiGudang"
                                                       x-model.number="it.qtyReturnNow"
                                                       class="w-16 px-2 py-1 border border-gray-300 rounded-md text-center font-bold text-gray-900 focus:outline-none focus:border-[#0052cc]">
                                                <button type="button"
                                                        @click="it.qtyReturnNow = it.sisaDiGudang"
                                                        class="px-2 py-1 text-[10px] font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 rounded cursor-pointer">Semua</button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Detail Informasi Pengiriman -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3.5 bg-slate-50 rounded-xl border border-gray-200">
                        <div>
                            <label class="font-bold text-gray-700 text-[10.5px] uppercase block mb-1">NAMA PENGIRIM / PENGAMBIL:</label>
                            <input type="text"
                                   x-model="returnBackDriver"
                                   placeholder="Nama pengirim penjemput toko..."
                                   class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs text-gray-900 focus:outline-none focus:border-[#0052cc]">
                        </div>
                        <div>
                            <label class="font-bold text-gray-700 text-[10.5px] uppercase block mb-1">PLAT NOMOR:</label>
                            <input type="text"
                                   x-model="returnBackPlate"
                                   placeholder="Contoh: B 9481 UQ"
                                   class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs text-gray-900 focus:outline-none focus:border-[#0052cc]">
                        </div>
                    </div>

                    <!-- Catatan Pengembalian -->
                    <div class="space-y-1">
                        <label class="font-bold text-gray-700 text-[10.5px] uppercase block">CATATAN SERAH TERIMA PENGEMBALIAN:</label>
                        <input type="text"
                               x-model="returnBackNotes"
                               placeholder="Contoh: Diangkut kembali menggunakan armada logistik sore untuk susut toko cabang..."
                               class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs text-gray-900 focus:outline-none focus:border-[#0052cc]">
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between bg-slate-50">
                    <span class="text-[11px] text-gray-500 font-medium">Pengembalian akan memperbarui saldo sisa fisik di gudang.</span>
                    <div class="flex items-center gap-2.5">
                        <button type="button"
                                @click="showReturnBackModal = false"
                                class="px-4 py-2 rounded-xl text-gray-700 font-bold hover:bg-slate-200 transition-colors cursor-pointer">
                            Batal
                        </button>
                        <button type="button"
                                @click="confirmReturnBackToStore()"
                                :disabled="isSubmittingReturn"
                                class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold transition-all shadow-xs flex items-center gap-2 cursor-pointer disabled:opacity-50">
                            <span class="material-symbols-rounded text-[18px]">local_shipping</span>
                            <span x-text="isSubmittingReturn ? 'Menyimpan...' : 'Konfirmasi Pengembalian ke Toko'"></span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </template>

    <!-- MODAL 3: BAST DIGITAL LENGKAP & HISTORI PENERIMAAN / PENGEMBALIAN -->
    <template x-teleport="body">
        <div x-show="showBastModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showBastModal"
                 @click.away="showBastModal = false"
                 x-transition.scale.origin.bottom
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden border border-gray-200">

                <div class="px-6 py-4.5 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]">receipt_long</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-gray-900">BAST Penerimaan Barang Masuk Retur</h3>
                            <span class="font-mono text-xs text-gray-500" x-text="selectedDoc?.docNo + ' • Waktu: ' + (selectedDoc?.receivedAt || selectedDoc?.date)"></span>
                        </div>
                    </div>
                    <button @click="showBastModal = false" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[24px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs overflow-y-auto max-h-[75vh]">
                    <!-- Identitas Serah Terima -->
                    <div class="grid grid-cols-2 gap-3 p-4 bg-slate-50 rounded-xl border border-gray-200">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">JENIS DOKUMEN &amp; ASAL</span>
                            <span class="font-bold text-purple-700" x-text="selectedDoc?.typeLabel"></span>
                            <span class="text-gray-700 text-[11px] block mt-0.5" x-text="'Dari: ' + selectedDoc?.warehouseOrigin"></span>
                            <span class="text-gray-500 text-[10.5px] block" x-text="'Ref Awal: ' + selectedDoc?.originRef"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">STATUS &amp; PENERIMA GUDANG</span>
                            <span class="font-bold text-emerald-700" x-text="selectedDoc?.status"></span>
                            <span class="text-gray-700 text-[11px] block mt-0.5" x-text="'Pemeriksa: ' + (selectedDoc?.receiverName || @js(auth()->user()?->name ?? 'Manager'))"></span>
                            <span class="text-gray-500 text-[10.5px] block" x-text="'Tujuan: Gudang Pusat (DC Logistik)'"></span>
                        </div>
                    </div>

                    <!-- Tabel Item Hasil Pemeriksaan -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden bg-white">
                        <table class="w-full text-left border-collapse text-xs min-w-[550px]">
                            <thead class="bg-gray-50/80 border-b border-gray-200 font-bold text-gray-600 uppercase tracking-wider text-[11px]">
                                <tr>
                                    <th class="px-4 py-2.5">PRODUK</th>
                                    <th class="px-3 py-2.5 text-center">DIAJUKAN</th>
                                    <th class="px-3 py-2.5 text-center">MASUK STOK RETUR</th>
                                    <th class="px-3 py-2.5 text-center">DITOLAK TOKO</th>
                                    <th class="px-3 py-2.5 text-center">SISA DI DC</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(it, idx) in selectedDoc?.items" :key="idx">
                                    <tr>
                                        <td class="px-4 py-2.5">
                                            <span class="font-bold text-gray-800 block" x-text="it.name"></span>
                                            <span class="text-[10px] text-gray-500 font-mono" x-text="it.condition"></span>
                                            <template x-if="it.rejectReason">
                                                <span class="text-[10px] text-rose-700 block italic" x-text="'Alasan Tolak: ' + it.rejectReason"></span>
                                            </template>
                                        </td>
                                        <td class="px-3 py-2.5 text-center text-gray-600" x-text="it.qtySent + ' Pcs'"></td>
                                        <td class="px-3 py-2.5 text-center font-bold text-emerald-700" x-text="(it.qtyAccepted || 0) + ' Pcs'"></td>
                                        <td class="px-3 py-2.5 text-center font-bold text-rose-700" x-text="(it.qtyRejected || 0) + ' Pcs'"></td>
                                        <td class="px-3 py-2.5 text-center">
                                            <span class="px-2 py-0.5 rounded font-bold"
                                                  :class="(it.sisaDiGudang || 0) > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-500'"
                                                  x-text="(it.sisaDiGudang || 0) + ' Pcs'"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Riwayat Pengembalian Barang Ditolak jika ada -->
                    <template x-if="selectedDoc?.returnHistory && selectedDoc.returnHistory.length > 0">
                        <div class="border border-amber-200 rounded-xl overflow-hidden bg-amber-50/30 p-3 space-y-2">
                            <span class="font-bold text-amber-900 text-[11px] uppercase flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[16px] text-amber-700">local_shipping</span>
                                <span>Riwayat Pengeluaran &amp; Pengembalian Barang Ditolak ke Cabang</span>
                            </span>
                            <div class="space-y-1.5">
                                <template x-for="(hist, hIdx) in selectedDoc.returnHistory" :key="hIdx">
                                    <div class="bg-white p-2.5 rounded-lg border border-amber-200 text-xs flex items-center justify-between">
                                        <div>
                                            <span class="font-bold text-gray-900" x-text="hist.timestamp"></span>
                                            <span class="text-gray-500 block text-[11px]" x-text="'Driver: ' + hist.driver + ' (' + hist.plateNumber + ') • ' + hist.notes"></span>
                                        </div>
                                        <span class="px-2.5 py-1 rounded-md bg-amber-100 text-amber-900 font-black" x-text="hist.totalReturned + ' Pcs Dikembalikan'"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Catatan BAST -->
                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-200">
                        <span class="font-bold text-gray-500 text-[10px] uppercase block mb-1">CATATAN BAST &amp; KELENGKAPAN:</span>
                        <p class="text-gray-800 italic" x-text="selectedDoc?.generalNotes"></p>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between bg-slate-50">
                    <div>
                        <!-- Tombol cepat jika masih ada sisa di DC -->
                        <template x-if="(selectedDoc?.sisaBarangDitolakDiGudang || 0) > 0">
                            <button type="button"
                                    @click="showBastModal = false; openReturnBackModal(selectedDoc);"
                                    class="px-3.5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs flex items-center gap-1.5 cursor-pointer">
                                <span class="material-symbols-rounded text-[16px]">local_shipping</span>
                                <span>Proses Pengembalian Barang Ditolak</span>
                            </button>
                        </template>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="window.print()"
                                class="px-4 py-2 rounded-xl border border-gray-300 hover:bg-gray-100 text-gray-700 font-bold text-xs transition-colors cursor-pointer flex items-center gap-1.5">
                            <span class="material-symbols-rounded text-[16px]">print</span>
                            <span>Cetak BAST</span>
                        </button>
                        <button type="button"
                                @click="showBastModal = false"
                                class="px-5 py-2 rounded-xl bg-gray-800 hover:bg-gray-900 text-white font-bold text-xs transition-colors cursor-pointer">
                            Tutup
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </template>

    <!-- MODAL 4: TOLAK RETUR INBOUND -->
    <template x-teleport="body">
        <div x-show="showRejectInboundModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showRejectInboundModal"
                 @click.away="showRejectInboundModal = false"
                 x-transition.scale.origin.bottom
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-gray-200 flex flex-col">

                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-rose-50/60">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]">cancel</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-gray-900">Tolak Pengajuan Retur</h3>
                            <span class="font-mono text-xs text-rose-700 font-bold" x-text="selectedDocForReject?.docNo"></span>
                        </div>
                    </div>
                    <button @click="showRejectInboundModal = false" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[24px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs">
                    <p class="text-gray-600 text-[13px] leading-relaxed">
                        Anda akan menolak pengajuan retur barang dari <strong class="text-gray-900" x-text="selectedDocForReject?.warehouseOrigin"></strong>. Status pengajuan di kasir akan langsung diubah menjadi <strong>Ditolak</strong> dan stok toko akan dikembalikan.
                    </p>

                    <div class="space-y-1.5">
                        <label class="font-bold text-gray-700 text-xs uppercase tracking-wider">Alasan Penolakan (Wajib Diisi):</label>
                        <textarea x-model="rejectReason"
                                  rows="3"
                                  placeholder="Contoh: Kondisi barang tidak sesuai SOP, masa garansi kedaluwarsa, atau dokumen fisik tidak lengkap..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-xl text-xs text-gray-800 focus:outline-none focus:border-rose-500"></textarea>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2.5 bg-slate-50">
                    <button type="button"
                            @click="showRejectInboundModal = false"
                            class="px-4 py-2 rounded-xl text-gray-700 font-bold hover:bg-slate-200 transition-colors cursor-pointer">
                        Batal
                    </button>
                    <button type="button"
                            @click="confirmRejectInbound()"
                            :disabled="isSubmitting"
                            class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                        <span class="material-symbols-rounded text-[16px]">cancel</span>
                        <span x-text="isSubmitting ? 'Memproses...' : 'Tolak Pengajuan'"></span>
                    </button>
                </div>

            </div>
        </div>
    </template>

    <!-- TOAST NOTIFICATION -->
    <div x-show="showToast"
         x-transition.opacity
         style="display: none;"
         class="fixed bottom-6 right-6 z-50 px-5 py-3.5 rounded-2xl shadow-xl flex items-center gap-3 border text-xs font-semibold text-white"
         :class="toastType === 'error' ? 'bg-rose-900 border-rose-800' : 'bg-gray-900 border-gray-800'">
        <span class="material-symbols-rounded text-[20px]" :class="toastType === 'error' ? 'text-rose-400' : 'text-emerald-400'">
            <span x-text="toastType === 'error' ? 'error' : 'check_circle'"></span>
        </span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
