@extends('layouts.app')

@section('title', 'Refund Transaksi - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    activeTab: 'form', // 'form' | 'history'
    searchFakturInput: '{{ request()->query('faktur', '') }}',
    foundFaktur: null,
    searchError: '',
    selectedItems: [],
    refundReason: 'CACAT_KEMASAN',
    itemCondition: 'RUSAK_KEMASAN',
    refundNotes: '',
    supervisorPin: '',
    pinError: '',
    showManagerPinModal: false,
    showPin: false,
    isSubmitting: false,
    showReceiptModal: false,
    selectedRefundDoc: null,
    toastMessage: '',
    showToast: false,
    refundHistory: [],

    fakturDatabase: {},

    async initRefundState() {
        try {
            const res = await fetch('/api/pos/void/logs', { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const json = await res.json();
                if (json.success && Array.isArray(json.data)) {
                    this.refundHistory = json.data.map((v, idx) => ({
                        refundNo: '#RFD-VOID-' + (v.id || (idx + 1)),
                        date: new Date(v.created_at).toLocaleString('id-ID'),
                        originFaktur: v.invoice_number,
                        totalRefund: Number(v.amount || v.amount_voided || v.grand_total || 0),
                        refundAmount: Number(v.amount || v.amount_voided || v.grand_total || 0),
                        reason: v.reason || 'Void Transaksi Kasir',
                        condition: 'VOID / REFUND',
                        cashier: v.cashier_name || '-',
                        cashierName: v.cashier_name || '-',
                        supervisorName: v.supervisor_name || v.authorized_by_name || '-',
                        items: []
                    }));
                }
            }
        } catch (e) {}

        if (this.searchFakturInput.trim()) {
            this.lookupFaktur();
        }
    },

    saveRefundHistory() {
        localStorage.setItem('pos_ilp_refund_history_v1', JSON.stringify(this.refundHistory));
    },

    async lookupFaktur() {
        this.searchError = '';
        const q = (this.searchFakturInput || '').trim();
        if (!q) {
            this.searchError = 'Masukkan nomor faktur nota kasir.';
            this.foundFaktur = null;
            return;
        }

        // Try API first
        try {
            const res = await fetch('/api/pos/transactions/' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success && json.data) {
                    const d = json.data;
                    this.foundFaktur = {
                        id: d.invoice_number,
                        date: new Date(d.created_at).toLocaleString('id-ID'),
                        cashier: d.cashier_name,
                        terminal: d.terminal_code,
                        method: d.payment_method,
                        totalPaid: d.grand_total,
                        items: (d.items || []).map((it, idx) => ({
                            id: idx + 1,
                            name: it.name,
                            buyQty: it.qty,
                            refundedQty: 0,
                            price: it.unit_price,
                            unit: it.unit || 'Pcs'
                        }))
                    };
                    this.selectedItems = this.foundFaktur.items.map(it => ({
                        id: it.id,
                        name: it.name,
                        buyQty: it.buyQty,
                        unit: it.unit,
                        price: it.price,
                        refundQty: 1,
                        checked: true
                    }));
                    this.searchError = '';
                    return;
                }
            }
        } catch (err) {
            console.warn('API lookup transaction error:', err);
        }

        const match = this.fakturDatabase[q] || Object.values(this.fakturDatabase).find(f => f.id.toLowerCase() === q.toLowerCase());
        if (match) {
            // Deep clone
            this.foundFaktur = JSON.parse(JSON.stringify(match));
            // Initialize selectable refund items
            this.selectedItems = this.foundFaktur.items.map(it => ({
                id: it.id,
                name: it.name,
                buyQty: it.buyQty,
                unit: it.unit,
                price: it.price,
                refundQty: 1,
                checked: false
            }));
            this.searchError = '';
        } else {
            this.foundFaktur = null;
            this.searchError = `Faktur ${q} tidak ditemukan di sistem. Pastikan nomor faktur nota kasir benar.`;
        }
    },

    get totalRefundCalculated() {
        if (!this.selectedItems) return 0;
        return this.selectedItems
            .filter(it => it.checked)
            .reduce((acc, it) => acc + (Number(it.refundQty || 0) * Number(it.price || 0)), 0);
    },

    get needsSupervisorAuth() {
        return true;
    },

    async processRefundSubmission() {
        this.pinError = '';
        const activeRefundItems = (this.selectedItems || []).filter(it => it.checked && it.refundQty > 0);
        if (activeRefundItems.length === 0) {
            this.pinError = 'Pilih minimal satu item belanja yang akan direfund.';
            return;
        }

        // Validate max allowed qty
        for (const it of activeRefundItems) {
            if (it.refundQty > it.buyQty) {
                this.pinError = `Kuantitas refund untuk ${it.name} melebihi jumlah pembelian asli (${it.buyQty} ${it.unit}).`;
                return;
            }
        }

        // If supervisor PIN is empty, trigger the Manager PIN modal
        if (!this.supervisorPin || !this.supervisorPin.trim()) {
            this.pinError = 'Silakan masukkan PIN Otorisasi Manager terlebih dahulu.';
            this.showManagerPinModal = true;
            this.$nextTick(() => {
                if (this.$refs.managerPinInput) this.$refs.managerPinInput.focus();
            });
            return;
        }

        this.isSubmitting = true;

        // Call API void transaction with manager/supervisor PIN
        try {
            const voidRes = await fetch('/api/pos/void/transaction', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    invoice_number: this.foundFaktur.id,
                    pin_or_password: this.supervisorPin.trim(),
                    reason: `[${this.refundReason}] ${this.refundNotes || 'Refund Barang'}`
                })
            });
            const voidJson = await voidRes.json();
            if (!voidRes.ok || !voidJson.success) {
                this.pinError = voidJson.message || 'Otorisasi atau proses refund ditolak sistem.';
                this.isSubmitting = false;
                return;
            }

            const approvedBy = voidJson.data?.supervisor_name || 'Manager';
            const approvedRole = voidJson.data?.supervisor_role || 'Manager';

            const todayCode = new Date().toISOString().slice(0, 10).replace(/-/g, '');
            const newRefundNo = '#RFD-' + todayCode + '-' + String(this.refundHistory.length + 1).padStart(3, '0');
            const nowStr = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) + ' • ' +
                           new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') + ' WIB';
            const activeCashier = this.activeSession?.cashierName || localStorage.getItem('pos_user_name') || 'Kasir';

            const refundDoc = {
                refundNo: newRefundNo,
                date: nowStr,
                originFaktur: this.foundFaktur.id,
                cashier: activeCashier,
                reason: this.refundReason,
                condition: this.itemCondition,
                itemCount: activeRefundItems.length,
                refundAmount: this.totalRefundCalculated,
                status: 'SELESAI_DIREFUND',
                spvApproval: `Disetujui ${approvedRole} (${approvedBy})`,
                supervisorName: approvedBy,
                supervisorRole: approvedRole,
                items: activeRefundItems.map(it => ({
                    name: it.name,
                    qty: it.refundQty,
                    price: it.price,
                    unit: it.unit
                }))
            };

            // Record to cash movements as RETUR_TUNAI if cash method
            if ((this.foundFaktur.method === 'TUNAI' || this.foundFaktur.method === 'CAMPURAN') && this.activeSession) {
                if (voidJson.data?.shift) {
                    this.activeSession.cashSalesNet = Number(voidJson.data.shift.cash_sales_total || 0);
                    this.activeSession.expectedClosingCash = Number(voidJson.data.shift.expected_cash || 0);
                } else {
                    this.activeSession.cashReturn = (Number(this.activeSession.cashReturn) || 0) + this.totalRefundCalculated;
                }
                this.cashMovements.unshift({
                    id: 'CM-' + Math.floor(100 + Math.random() * 900),
                    time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':'),
                    type: 'RETUR_TUNAI',
                    label: `Refund Pelanggan ${this.foundFaktur.id} (${newRefundNo})`,
                    amount: this.totalRefundCalculated,
                    direction: 'out',
                    actor: activeCashier
                });
                if (typeof this.recalcClosingFigures === 'function') this.recalcClosingFigures();
                this.persistAllState();
                window.dispatchEvent(new CustomEvent('pos-session-sync'));
                window.dispatchEvent(new CustomEvent('pos:session_updated'));
            }

            this.refundHistory.unshift(refundDoc);
            this.saveRefundHistory();
            this.selectedRefundDoc = refundDoc;
            this.showManagerPinModal = false;
            this.showReceiptModal = true;
            this.showToastNotice(`Refund ${newRefundNo} berhasil disetujui ${approvedBy} dan diproses!`);

            // Reset form
            this.foundFaktur = null;
            this.searchFakturInput = '';
            this.selectedItems = [];
            this.supervisorPin = '';
            this.refundNotes = '';
        } catch (e) {
            this.pinError = 'Gagal menghubungi server otorisasi refund: ' + e.message;
        } finally {
            this.isSubmitting = false;
        }
    },

    openHistoryDetail(doc) {
        this.selectedRefundDoc = doc;
        this.showReceiptModal = true;
    },

    showToastNotice(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3600);
    }
}"
x-init="initOpening(); initRefundState(); lookupFaktur();"
@pos-session-sync.window="initOpening(); initRefundState();"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">

    <!-- Sidebar Kasir -->
    <x-cashier-sidebar active="transaksi_refund" />

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden relative">
        <!-- Top Header -->
        <x-cashier-header title="Refund Transaksi Pelanggan">
            <div class="flex items-center gap-2 ml-2">
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200 flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[14px]">currency_exchange</span>
                    <span>Pencatatan Retur Pelanggan</span>
                </span>
            </div>
        </x-cashier-header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-6 lg:p-8 space-y-6">
            <div class="max-w-[1400px] mx-auto space-y-6">

                <!-- Breadcrumb & Header Title -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-[11px] font-bold text-gray-400 tracking-wider mb-1 uppercase">
                            <span>OPERASIONAL KASIR</span>
                            <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                            <span>TRANSAKSI</span>
                            <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                            <span class="text-[#0052cc]">REFUND PELANGGAN KE TOKO</span>
                        </div>
                        <h1 class="text-2xl font-black text-gray-900 tracking-tight">Pengembalian Dana & Barang Belanja (Refund)</h1>
                        <p class="text-xs text-gray-500 mt-1 font-medium">Klaim retur/refund pelanggan berdasarkan referensi faktur asli.</p>
                    </div>

                    <!-- Navigation Tabs -->
                    <div class="flex items-center bg-gray-200/70 p-1 rounded-xl gap-1 shrink-0">
                        <button type="button"
                                @click="activeTab = 'form'"
                                :class="activeTab === 'form' ? 'bg-white text-[#0052cc] font-bold shadow-xs' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                class="px-4 py-2 rounded-lg text-xs flex items-center gap-2 transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[16px]">edit_document</span>
                            <span>Form Pengajuan Refund</span>
                        </button>
                        <button type="button"
                                @click="activeTab = 'history'"
                                :class="activeTab === 'history' ? 'bg-white text-[#0052cc] font-bold shadow-xs' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                class="px-4 py-2 rounded-lg text-xs flex items-center gap-2 transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[16px]">history</span>
                            <span>Riwayat Refund</span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-rose-100 text-rose-700" x-text="refundHistory.length">0</span>
                        </button>
                    </div>
                </div>

                <!-- 4 KPI Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 tracking-wider uppercase">TOTAL KLAIM REFUND HARI INI</span>
                            <h3 class="text-2xl font-black text-gray-900 mt-1" x-text="refundHistory.length + ' Nota'">0 Nota</h3>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block" x-text="'Shift: ' + (activeSession?.terminalLabel || '-')">Shift: -</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">receipt</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-rose-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-rose-500">
                        <div>
                            <span class="text-[10px] font-bold text-rose-700 tracking-wider uppercase">TOTAL NILAI PENGEMBALIAN</span>
                            <h3 class="text-xl font-black text-rose-600 font-mono mt-1" x-text="formatRupiah(refundHistory.reduce((a, b) => a + Number(b.refundAmount || 0), 0))">Rp 0</h3>
                            <span class="text-[11px] text-rose-600 font-medium mt-0.5 block">Kas keluar laci</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">currency_exchange</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-blue-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-[#0052cc]">
                        <div>
                            <span class="text-[10px] font-bold text-[#0052cc] tracking-wider uppercase">OTORITAS APPROVAL REFUND</span>
                            <h3 class="text-xl font-black text-gray-900 font-mono mt-1">Wajib PIN Manager</h3>
                            <span class="text-[10px] text-gray-500 font-medium mt-0.5 block">Supervisor / Manager / Owner</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">verified_user</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-amber-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-amber-500">
                        <div>
                            <span class="text-[10px] font-bold text-amber-700 tracking-wider uppercase">STATUS MUTASI STOK REFUND</span>
                            <h3 class="text-sm font-black text-gray-900 mt-1">Area Karantina Toko</h3>
                            <span class="text-[10px] text-amber-700 font-bold mt-0.5 block">Tidak otomatis masuk rak aktif</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">policy</span>
                        </div>
                    </div>
                </div>

                <!-- VIEW 1: FORM PENGAJUAN REFUND -->
                <div x-show="activeTab === 'form'" class="grid grid-cols-12 gap-6">

                    <!-- Search Faktur & Detail Header (Col 5) -->
                    <div class="col-span-12 lg:col-span-5 bg-white border border-gray-200 rounded-2xl shadow-xs p-6 flex flex-col gap-5">
                        <div>
                            <h3 class="font-bold text-gray-900 text-sm">Pencarian Faktur Pembelian Asli</h3>
                            <p class="text-[11px] text-gray-500 mt-0.5">Ketik atau scan nomor struk nota kasir yang dibawa pelanggan</p>
                        </div>

                        <!-- Search Bar -->
                        <div>
                            <label class="text-[11px] font-bold text-gray-700 tracking-wider block mb-1.5">NOMOR STRUK / FAKTUR</label>
                            <div class="flex gap-2">
                                <input type="text"
                                       x-model="searchFakturInput"
                                       @keyup.enter="lookupFaktur()"
                                       placeholder="Contoh: #TRX-20261024-0024"
                                       class="flex-1 px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-xl font-mono text-xs font-bold text-gray-900 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20">
                                <button type="button"
                                        @click="lookupFaktur()"
                                        class="bg-[#0052cc] hover:bg-[#0043a8] text-white px-4 rounded-xl font-bold text-xs transition-colors shadow-2xs cursor-pointer">
                                    Cari
                                </button>
                            </div>
                            <span x-show="searchError" class="text-xs font-bold text-rose-600 mt-1.5 block" x-text="searchError"></span>
                        </div>

                        <!-- Faktur Info Card -->
                        <template x-if="foundFaktur">
                            <div class="bg-blue-50/50 border border-blue-200 rounded-xl p-4 space-y-2 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono font-bold text-[#0052cc]" x-text="foundFaktur.id"></span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-[#0052cc]" x-text="foundFaktur.method"></span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-gray-600">
                                    <div>
                                        <span class="text-[10px] text-gray-400 uppercase block">TANGGAL TRANSAKSI</span>
                                        <span class="font-bold text-gray-800" x-text="foundFaktur.date"></span>
                                    </div>
                                    <div>
                                        <span class="text-[10px] text-gray-400 uppercase block">KASIR / TERMINAL</span>
                                        <span class="font-bold text-gray-800" x-text="foundFaktur.cashier + ' (' + foundFaktur.terminal + ')'"></span>
                                    </div>
                                </div>
                                <div class="pt-2 border-t border-blue-100 flex justify-between items-baseline font-bold">
                                    <span class="text-gray-600">Total Pembayaran Asli:</span>
                                    <span class="font-mono text-sm text-gray-900" x-text="formatRupiah(foundFaktur.totalPaid)"></span>
                                </div>
                            </div>
                        </template>

                        <!-- Reason & Conditions Form -->
                        <div class="space-y-4 pt-2 border-t border-gray-100">
                            <div>
                                <label class="text-[11px] font-bold text-gray-700 tracking-wider block mb-1.5">ALASAN KLAIM REFUND</label>
                                <select x-model="refundReason"
                                        class="w-full px-3 py-2.5 bg-slate-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-[#0052cc]">
                                    <option value="CACAT_KEMASAN">Cacat Produk / Kemasan Rusak Tersembunyi</option>
                                    <option value="KEDALUWARSA">Produk Kedaluwarsa Belum Terdeteksi</option>
                                    <option value="SALAH_VARIAN">Salah Ambil Varian Produk oleh Pelanggan</option>
                                    <option value="BARANG_TIDAK_SESUAI">Barang Tidak Sesuai Spesifikasi</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-[11px] font-bold text-gray-700 tracking-wider block mb-1.5">KONDISI FISIK SAAT DIKEMBALIKAN</label>
                                <select x-model="itemCondition"
                                        class="w-full px-3 py-2.5 bg-slate-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-[#0052cc]">
                                    <option value="RUSAK_KEMASAN">Kemasan Rusak (Masuk Karantina Gerai)</option>
                                    <option value="SEMPURNA_SEGEL">Masih Segel Pabrik Utuh</option>
                                    <option value="KADALUWARSA">Kedaluwarsa (Siap Retur ke DC)</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-[11px] font-bold text-gray-700 tracking-wider block mb-1.5">CATATAN PEMERIKSAAN KASIR</label>
                                <textarea rows="2"
                                          x-model="refundNotes"
                                          placeholder="Keterangan fisik barang..."
                                          class="w-full bg-slate-50 border border-gray-200 rounded-xl p-3 text-xs text-gray-800 focus:outline-none focus:border-[#0052cc]"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Item Selection & Calculation (Col 7) -->
                    <div class="col-span-12 lg:col-span-7 flex flex-col gap-5">

                        <!-- Table Card -->
                        <div class="bg-white border border-gray-200 rounded-2xl shadow-xs overflow-hidden flex flex-col">
                            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-rounded text-[20px] text-[#0052cc]">checklist</span>
                                    <h3 class="font-bold text-gray-900 text-sm">Pilih Item Pembelian yang Direfund</h3>
                                </div>
                                <span class="text-xs text-gray-400 font-medium">Centang item dan tentukan kuantitas</span>
                            </div>

                            <div class="overflow-x-auto min-w-0 w-full">
                                <table class="w-full text-left text-xs border-collapse min-w-[650px]">
                                    <thead class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold uppercase text-[11px] tracking-wider whitespace-nowrap">
                                        <tr>
                                            <th class="px-4 py-3 text-center w-[70px]">PILIH</th>
                                            <th class="px-4 py-3 text-left w-[240px]">PRODUK</th>
                                            <th class="px-3 py-3 text-center w-[110px]">QTY BELI</th>
                                            <th class="px-4 py-3 text-center w-[130px]">QTY REFUND</th>
                                            <th class="px-5 py-3 text-right w-[140px]">NILAI REFUND</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-for="(it, idx) in selectedItems" :key="it.id">
                                            <tr :class="it.checked ? 'bg-blue-50/40' : 'hover:bg-slate-50/50'">
                                                <!-- Checkbox -->
                                                <td class="px-4 py-3.5 text-center">
                                                    <input type="checkbox"
                                                           x-model="it.checked"
                                                           class="w-4 h-4 rounded text-[#0052cc] focus:ring-[#0052cc] cursor-pointer">
                                                </td>

                                                <!-- Name -->
                                                <td class="px-4 py-3.5">
                                                    <span class="font-bold text-gray-900 block" x-text="it.name"></span>
                                                    <span class="font-mono text-[11px] text-gray-400" x-text="'@ ' + formatRupiah(it.price) + ' / ' + it.unit"></span>
                                                </td>

                                                <!-- Buy Qty -->
                                                <td class="px-3 py-3.5 text-center font-bold text-gray-600" x-text="it.buyQty + ' ' + it.unit"></td>

                                                <!-- Refund Qty (Input with strict validation) -->
                                                <td class="px-4 py-3.5 text-center">
                                                    <div class="inline-flex items-center gap-1">
                                                        <input type="number"
                                                               min="1"
                                                               :max="it.buyQty"
                                                               :disabled="!it.checked"
                                                               x-model.number="it.refundQty"
                                                               class="w-16 px-2 py-1 border border-gray-300 rounded-lg text-center font-bold text-gray-900 focus:outline-none focus:border-[#0052cc] disabled:bg-gray-100 disabled:opacity-50">
                                                        <span class="text-[10px] text-gray-400" x-text="it.unit"></span>
                                                    </div>
                                                </td>

                                                <!-- Subtotal -->
                                                <td class="px-5 py-3.5 text-right font-mono font-bold"
                                                    :class="it.checked ? 'text-gray-900' : 'text-gray-300'"
                                                    x-text="formatRupiah(it.checked ? (it.refundQty * it.price) : 0)">
                                                </td>
                                            </tr>
                                        </template>

                                        <template x-if="!foundFaktur">
                                            <tr>
                                                <td colspan="5" class="py-12 text-center text-gray-400">
                                                    Silakan masukkan dan cari nomor faktur struk di sebelah kiri terlebih dahulu.
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Calculation & Supervisor Approval Card -->
                        <div class="bg-white border border-gray-200 rounded-2xl shadow-xs p-6 flex flex-col gap-4">
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                <div>
                                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block">TOTAL NILAI PENGEMBALIAN DANA</span>
                                    <span class="text-[11px] text-gray-400">Uang tunai diserahkan kembali kepada pelanggan</span>
                                </div>
                                <span class="font-mono text-2xl font-black text-rose-600" x-text="formatRupiah(totalRefundCalculated)">Rp 0</span>
                            </div>

                            <!-- Otorisasi PIN Manager/Supervisor Card -->
                            <div class="p-4 bg-amber-50/80 border border-amber-300 rounded-xl space-y-2.5" x-show="foundFaktur && totalRefundCalculated > 0">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2 text-amber-900 text-xs font-bold">
                                        <span class="material-symbols-rounded text-[18px] text-amber-600">lock_person</span>
                                        <span>Otorisasi Manager / Supervisor (Wajib)</span>
                                    </div>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-200/70 text-amber-900">PIN Otoritas</span>
                                </div>
                                <p class="text-[11px] text-amber-800 leading-tight">
                                    Kasir wajib memasukkan PIN otorisasi Manager/Supervisor yang berwenang untuk menyetujui pengembalian dana ini.
                                </p>
                                <div class="flex items-center gap-2 pt-1">
                                    <div class="relative flex-1 max-w-xs">
                                        <input :type="showPin ? 'text' : 'password'"
                                               x-model="supervisorPin"
                                               maxlength="20"
                                               placeholder="Ketik PIN / Password Manager..."
                                               @keydown.enter.prevent="processRefundSubmission()"
                                               class="w-full pl-3.5 pr-10 py-2 bg-white border border-amber-300 rounded-xl text-xs font-mono font-bold text-gray-900 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20">
                                        <button type="button"
                                                @click="showPin = !showPin"
                                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none">
                                            <span class="material-symbols-rounded text-[18px]" x-text="showPin ? 'visibility_off' : 'visibility'"></span>
                                        </button>
                                    </div>
                                    <button type="button"
                                            @click="showManagerPinModal = true; $nextTick(() => { if ($refs.managerPinInput) $refs.managerPinInput.focus(); });"
                                            class="px-3 py-2 bg-amber-100 hover:bg-amber-200 text-amber-900 rounded-xl text-xs font-bold transition-colors flex items-center gap-1 cursor-pointer">
                                        <span class="material-symbols-rounded text-[16px]">key</span>
                                        <span>Buka Dialog PIN</span>
                                    </button>
                                </div>
                            </div>

                            <div x-show="pinError" class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs font-bold text-rose-700 flex items-center gap-2">
                                <span class="material-symbols-rounded text-[18px] text-rose-600 shrink-0">error</span>
                                <span x-text="pinError"></span>
                            </div>

                            <!-- Strict Stock Policy Alert -->
                            <div class="p-3.5 bg-slate-50 border border-gray-200 rounded-xl text-xs text-gray-700 flex items-start gap-3">
                                <span class="material-symbols-rounded text-gray-500 text-[18px] shrink-0 mt-0.5">inventory</span>
                                <div class="space-y-0.5">
                                    <p class="font-bold">Integritas Stok Fisik:</p>
                                    <p class="text-gray-500 text-[11px] leading-relaxed">
                                        Barang refund dialokasikan ke area karantina untuk inspeksi supervisor, tidak menambah stok aktif otomatis.
                                    </p>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-end pt-2">
                                <button type="button"
                                        @click="if (!supervisorPin || !supervisorPin.trim()) { showManagerPinModal = true; pinError = ''; $nextTick(() => { if ($refs.managerPinInput) $refs.managerPinInput.focus(); }); } else { processRefundSubmission(); }"
                                        :disabled="totalRefundCalculated === 0 || isSubmitting"
                                        :class="(totalRefundCalculated === 0 || isSubmitting) ? 'opacity-50 cursor-not-allowed bg-gray-400' : 'bg-rose-600 hover:bg-rose-700 cursor-pointer shadow-sm'"
                                        class="px-6 py-3 rounded-xl text-white font-bold text-xs flex items-center gap-2 transition-all">
                                    <template x-if="isSubmitting">
                                        <span class="material-symbols-rounded animate-spin text-[18px]">progress_activity</span>
                                    </template>
                                    <template x-if="!isSubmitting">
                                        <span class="material-symbols-rounded text-[18px]">payments</span>
                                    </template>
                                    <span x-text="isSubmitting ? 'Memproses Otorisasi...' : 'Proses Pengembalian Dana (Refund)'"></span>
                                </button>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- VIEW 2: RIWAYAT REFUND PELANGGAN -->
                <div x-cloak x-show="activeTab === 'history'" class="space-y-4">
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h2 class="text-sm font-bold text-gray-900">Riwayat Nota Refund Pelanggan</h2>
                            <span class="text-xs text-gray-500 font-medium" x-text="'Total ' + refundHistory.length + ' dokumen refund tercatat'"></span>
                        </div>

                        <div class="overflow-x-auto min-w-0 w-full">
                            <table class="w-full text-left border-collapse text-xs min-w-[850px]">
                                <thead>
                                    <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold uppercase text-[11px] tracking-wider whitespace-nowrap">
                                        <th class="px-6 py-3.5 text-left w-[180px]">NO. REFUND & WAKTU</th>
                                        <th class="px-4 py-3.5 text-left w-[160px]">FAKTUR ASLI</th>
                                        <th class="px-4 py-3.5 text-left w-[180px]">ALASAN KLAIM</th>
                                        <th class="px-4 py-3.5 text-right w-[140px]">NILAI REFUND</th>
                                        <th class="px-4 py-3.5 text-center w-[120px]">STATUS</th>
                                        <th class="px-6 py-3.5 text-right w-[140px]">BUKTI NOTA</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-sm">
                                    <template x-for="rf in refundHistory" :key="rf.refundNo">
                                        <tr class="hover:bg-slate-50/50 transition-colors">
                                            <td class="px-6 py-4">
                                                <span class="font-mono font-bold text-gray-900 text-xs block" x-text="rf.refundNo"></span>
                                                <span class="text-[11px] text-gray-400" x-text="rf.date"></span>
                                            </td>

                                            <td class="px-4 py-4">
                                                <span class="font-mono font-bold text-[#0052cc] text-xs" x-text="rf.originFaktur"></span>
                                                <span class="text-[10px] text-gray-400 block" x-text="'Kasir: ' + rf.cashier"></span>
                                            </td>

                                            <td class="px-4 py-4">
                                                <span class="font-bold text-gray-800 text-xs block" x-text="rf.reason"></span>
                                                <span class="text-[10px] text-gray-400" x-text="'Kondisi: ' + rf.condition"></span>
                                            </td>

                                            <td class="px-4 py-4 text-right">
                                                <span class="font-mono font-bold text-rose-600 text-sm" x-text="formatRupiah(rf.refundAmount)"></span>
                                            </td>

                                            <td class="px-4 py-4 text-center">
                                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    Selesai Refund
                                                </span>
                                            </td>

                                            <td class="px-6 py-4 text-right">
                                                <button type="button"
                                                        @click="openHistoryDetail(rf)"
                                                        class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-xl text-xs font-bold transition-colors flex items-center gap-1.5 ml-auto cursor-pointer">
                                                    <span class="material-symbols-rounded text-[16px]">receipt</span>
                                                    <span>Nota Refund</span>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- MODAL OTORISASI PIN MANAGER -->
    <template x-teleport="body">
        <div x-show="showManagerPinModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showManagerPinModal"
                 @click.away="if (!isSubmitting) showManagerPinModal = false"
                 x-transition.scale.origin.center
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-gray-200 flex flex-col">

                <!-- Header Modal -->
                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-amber-50/60">
                    <div class="flex items-center gap-2.5">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]">admin_panel_settings</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 text-sm">Otorisasi Manager / Supervisor</h3>
                            <p class="text-[11px] text-gray-500">Persetujuan pengembalian dana (refund) transaksi</p>
                        </div>
                    </div>
                    <button type="button"
                            @click="if (!isSubmitting) showManagerPinModal = false"
                            class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <!-- Body Modal -->
                <div class="p-6 space-y-4 text-xs">
                    <!-- Ringkasan Refund -->
                    <div class="p-4 bg-slate-50 rounded-xl border border-gray-200 space-y-2">
                        <div class="flex justify-between items-center text-gray-500 text-[11px]">
                            <span>Nomor Faktur:</span>
                            <span class="font-mono font-bold text-gray-900" x-text="foundFaktur?.id"></span>
                        </div>
                        <div class="flex justify-between items-center text-gray-500 text-[11px]">
                            <span>Metode Pembayaran:</span>
                            <span class="font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded" x-text="foundFaktur?.method"></span>
                        </div>
                        <div class="flex justify-between items-baseline pt-2 border-t border-gray-200">
                            <span class="font-bold text-gray-700">Total Refund:</span>
                            <span class="font-mono font-black text-rose-600 text-base" x-text="formatRupiah(totalRefundCalculated)"></span>
                        </div>
                    </div>

                    <!-- Input PIN Field -->
                    <div class="space-y-1.5">
                        <label class="text-[11px] font-bold text-gray-700 tracking-wider block">
                            PIN / PASSWORD OTORISASI MANAGER <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <input :type="showPin ? 'text' : 'password'"
                                   x-model="supervisorPin"
                                   x-ref="managerPinInput"
                                   maxlength="20"
                                   placeholder="Ketik PIN Otorisasi (cth: 654321)..."
                                   @keydown.enter.prevent="processRefundSubmission()"
                                   class="w-full pl-4 pr-11 py-3 bg-slate-50 border border-gray-300 rounded-xl text-sm font-mono font-bold text-gray-900 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20">
                            <button type="button"
                                    @click="showPin = !showPin"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none">
                                <span class="material-symbols-rounded text-[20px]" x-text="showPin ? 'visibility_off' : 'visibility'"></span>
                            </button>
                        </div>
                        <p class="text-[10px] text-gray-500">
                            Masukkan PIN Manager/Supervisor yang bertugas atau minta Manager mengisi PIN.
                        </p>
                    </div>

                    <!-- Error Alert in Modal -->
                    <div x-show="pinError" class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs font-bold text-rose-700 flex items-start gap-2">
                        <span class="material-symbols-rounded text-[18px] text-rose-600 shrink-0 mt-0.5">error</span>
                        <span x-text="pinError" class="leading-tight"></span>
                    </div>
                </div>

                <!-- Footer Modal -->
                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2 bg-slate-50">
                    <button type="button"
                            @click="showManagerPinModal = false"
                            :disabled="isSubmitting"
                            class="px-4 py-2.5 bg-white border border-gray-300 hover:bg-gray-100 text-gray-700 rounded-xl font-bold text-xs transition-colors cursor-pointer">
                        Batal
                    </button>
                    <button type="button"
                            @click="processRefundSubmission()"
                            :disabled="!supervisorPin || !supervisorPin.trim() || isSubmitting"
                            :class="(!supervisorPin || !supervisorPin.trim() || isSubmitting) ? 'opacity-50 cursor-not-allowed bg-gray-400' : 'bg-rose-600 hover:bg-rose-700 cursor-pointer shadow-sm'"
                            class="px-5 py-2.5 text-white rounded-xl font-bold text-xs flex items-center gap-2 transition-colors">
                        <template x-if="isSubmitting">
                            <span class="material-symbols-rounded animate-spin text-[16px]">progress_activity</span>
                        </template>
                        <template x-if="!isSubmitting">
                            <span class="material-symbols-rounded text-[16px]">check_circle</span>
                        </template>
                        <span x-text="isSubmitting ? 'Memverifikasi...' : 'Verifikasi & Setujui Refund'"></span>
                    </button>
                </div>

            </div>
        </div>
    </template>

    <!-- MODAL BUKTI NOTA REFUND -->
    <template x-teleport="body">
        <div x-show="showReceiptModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showReceiptModal"
                 @click.away="showReceiptModal = false"
                 x-transition.scale.origin.bottom
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-gray-200 flex flex-col">

                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[20px]">receipt</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 text-sm">Nota Pengembalian Dana Kasir</h3>
                            <span class="font-mono text-xs text-gray-400" x-text="selectedRefundDoc?.refundNo"></span>
                        </div>
                    </div>
                    <button @click="showReceiptModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs">
                    <div class="p-4 bg-slate-50 rounded-xl border border-gray-200 space-y-1.5 font-mono">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Ref Faktur Asli:</span>
                            <span class="font-bold text-gray-900" x-text="selectedRefundDoc?.originFaktur"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Tanggal Klaim:</span>
                            <span class="text-gray-800" x-text="selectedRefundDoc?.date"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Kasir Petugas:</span>
                            <span class="text-gray-800" x-text="selectedRefundDoc?.cashier"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Otorisasi Manager:</span>
                            <span class="font-bold text-emerald-700" x-text="selectedRefundDoc?.spvApproval || 'Disetujui Manager'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Alasan:</span>
                            <span class="text-gray-800" x-text="selectedRefundDoc?.reason"></span>
                        </div>
                    </div>

                    <div class="border border-gray-200 rounded-xl overflow-hidden bg-white">
                        <div class="overflow-x-auto min-w-0 w-full">
                            <table class="w-full text-left border-collapse text-xs min-w-[450px]">
                                <thead class="bg-gray-50/80 border-b border-gray-200 font-bold text-gray-600 text-[11px] uppercase tracking-wider whitespace-nowrap">
                                    <tr>
                                        <th class="px-3.5 py-2.5 text-left w-[240px]">PRODUK</th>
                                        <th class="px-2.5 py-2.5 text-center w-[90px]">QTY</th>
                                        <th class="px-3.5 py-2.5 text-right w-[120px]">TOTAL</th>
                                    </tr>
                                </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(it, idx) in selectedRefundDoc?.items" :key="idx">
                                    <tr>
                                        <td class="px-3 py-2 font-bold text-gray-800" x-text="it.name"></td>
                                        <td class="px-2 py-2 text-center text-gray-600" x-text="it.qty + ' ' + (it.unit || '')"></td>
                                        <td class="px-3 py-2 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(it.qty * it.price)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-3 bg-rose-50/60 border border-rose-200 rounded-xl flex justify-between items-baseline">
                        <span class="font-bold text-rose-900">DANA PENGEMBALIAN:</span>
                        <span class="font-mono font-black text-lg text-rose-600" x-text="formatRupiah(selectedRefundDoc?.refundAmount)"></span>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2 bg-slate-50">
                    <button type="button"
                            @click="window.print()"
                            class="px-4 py-2 bg-blue-50 hover:bg-blue-100 text-[#0052cc] rounded-xl font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[16px]">print</span>
                        <span>Cetak Nota</span>
                    </button>
                    <button type="button"
                            @click="showReceiptModal = false"
                            class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white rounded-xl font-bold text-xs transition-colors cursor-pointer">
                        Selesai
                    </button>
                </div>

            </div>
        </div>
    </template>

    <!-- TOAST -->
    <div x-show="showToast"
         x-transition.opacity
         style="display: none;"
         class="fixed bottom-6 right-6 z-50 bg-gray-900 text-white px-5 py-3.5 rounded-2xl shadow-xl flex items-center gap-3 border border-gray-800 text-xs font-semibold">
        <span class="material-symbols-rounded text-emerald-400 text-[20px]">check_circle</span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
