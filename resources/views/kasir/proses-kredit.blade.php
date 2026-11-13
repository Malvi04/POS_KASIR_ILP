@extends('layouts.app')

@section('title', 'Proses Kredit Anggota - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    searchMemberQuery: '',
    members: [],
    selectedMember: null,
    cartItems: [],
    toastMessage: '',
    showToast: false,

    async initCreditPage() {
        // Load members from API
        try {
            const res = await fetch('/api/pos/members/search', { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const json = await res.json();
                if (json.success && Array.isArray(json.data) && json.data.length > 0) {
                    this.members = json.data.map(m => ({
                        id: m.member_code,
                        nik: m.nik,
                        name: m.name,
                        dept: m.department,
                        branch: m.branch,
                        creditLimit: m.credit_limit,
                        currentDebt: m.current_debt,
                        status: m.status
                    }));
                    const urlParams = new URLSearchParams(window.location.search);
                    const nikParam = urlParams.get('nik');
                    if (nikParam) {
                        const found = this.members.find(m => m.nik === nikParam);
                        this.selectedMember = found || this.members[0];
                    } else {
                        this.selectedMember = this.members[0];
                    }
                }
            }
        } catch (e) {
            console.warn('API members fetch failed, fallback local:', e);
        }

        if (!this.members) this.members = [];
        if (!this.selectedMember && this.members.length > 0) {
            this.selectedMember = this.members[0];
        }

        // Load active cart from POS
        try {
            const rawCart = localStorage.getItem('pos_ilp_active_cart_v1');
            if (rawCart) {
                this.cartItems = JSON.parse(rawCart);
            }
        } catch (e) {}

        if (!this.cartItems) this.cartItems = [];
    },

    get filteredMemberList() {
        if (!this.searchMemberQuery.trim()) return [];
        const q = this.searchMemberQuery.toLowerCase();
        return this.members.filter(m => (m.name || '').toLowerCase().includes(q) || (m.nik || '').includes(q));
    },

    selectMember(m) {
        this.selectedMember = m;
        this.searchMemberQuery = '';
    },

    get subtotal() {
        return this.cartItems.reduce((acc, it) => acc + ((it.memberPrice || it.price) * it.qty), 0);
    },

    get taxAmount() {
        return Math.round(this.subtotal * 0.11);
    },

    get grandTotal() {
        return this.subtotal + this.taxAmount;
    },

    get sisaPlafon() {
        if (!this.selectedMember) return 0;
        return Math.max(0, (Number(this.selectedMember.creditLimit) || 0) - (Number(this.selectedMember.currentDebt) || 0));
    },

    get isSaldoCukup() {
        if (!this.selectedMember || this.selectedMember.status === 'DIBLOKIR') return false;
        return this.sisaPlafon >= this.grandTotal;
    },

    get estimasiSisaAkhir() {
        return this.sisaPlafon - this.grandTotal;
    },

    async confirmCreditPayment() {
        if (!this.selectedMember) {
            alert('Pilih anggota terlebih dahulu.');
            return;
        }
        if (!this.isSaldoCukup) {
            alert('Sisa plafon kredit tidak mencukupi untuk transaksi ini.');
            return;
        }

        const payload = {
            terminal_code: this.activeSession?.terminalLabel || 'POS-01',
            cashier_nik: (this.activeOperator && this.activeOperator.nik) ? this.activeOperator.nik : (this.activeSession?.cashierNik || '202409821002'),
            cashier_name: (this.activeOperator && this.activeOperator.name) ? this.activeOperator.name : (this.activeSession?.cashierName || 'Budi Kurniawan'),
            payment_method: 'KREDIT',
            member_nik: this.selectedMember.nik,
            items: this.cartItems.map(it => ({
                sku: it.sku || 'GEN-ITEM',
                barcode: it.barcode || '-',
                name: it.name,
                qty: it.qty,
                unit: it.unit || 'Pcs',
                price: it.memberPrice || it.price,
                member_price: it.memberPrice || it.price,
                is_member: true
            }))
        };

        try {
            const res = await fetch('/api/pos/checkout', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            });

            const json = await res.json();
            if (!res.ok || !json.success) {
                alert(json.message || 'Transaksi kredit ditolak sistem!');
                return;
            }

            const trxRecord = {
                id: json.data.invoice_number,
                rawId: json.data.id,
                time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') + ' WIB',
                date: new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }),
                terminal: json.data.terminal_code,
                cashier: json.data.cashier_name,
                method: 'KREDIT',
                methodLabel: 'Kredit Anggota (' + this.selectedMember.name + ')',
                subtotal: json.data.subtotal,
                discount: json.data.discount_total,
                tax: json.data.tax_total,
                total: json.data.grand_total,
                member: json.data.member,
                items: this.cartItems
            };

            localStorage.setItem('pos_ilp_latest_printed_receipt_v1', JSON.stringify(trxRecord));
            localStorage.removeItem('pos_ilp_active_cart_v1');
            window.location.href = '/cetak-struk?faktur=' + encodeURIComponent(json.data.invoice_number);
        } catch (e) {
            alert('Gagal memproses pembayaran kredit ke server: ' + e.message);
        }
    }
}"
x-init="initOpening(); initCreditPage();"
class="flex flex-col h-screen w-full bg-white font-sans text-gray-800">

    <!-- Header -->
    <header class="h-16 border-b border-gray-200 px-6 flex items-center justify-between shrink-0 bg-white">
        <div class="flex items-center gap-4">
            <a href="{{ url('/pos') }}" class="text-gray-500 hover:text-gray-800 p-2 rounded-full hover:bg-gray-100 transition-colors">
                <span class="material-symbols-rounded">arrow_back</span>
            </a>
            <h1 class="text-[#0052cc] font-black text-[16px] tracking-tight">Otorisasi &amp; Proses Kredit Anggota</h1>
        </div>
        <div class="flex items-center gap-6">
            <div class="flex flex-col text-right">
                <span class="text-[10px] font-bold text-gray-500 tracking-wider">TOTAL TAGIHAN</span>
                <span class="text-[#0052cc] font-mono font-bold text-[15px]" x-text="formatRupiah(grandTotal)">Rp 0</span>
            </div>
            <x-realtime-network-badge />
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
        <!-- Left Panel: Member Search & Verification -->
        <div class="w-7/12 bg-[#f8f9fa] p-8 overflow-y-auto border-r border-gray-200">
            <label class="text-[11px] font-bold text-gray-500 tracking-wider mb-2 block uppercase">CARI ANGGOTA (NIK / NAMA)</label>
            <div class="relative mb-3">
                <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">person_search</span>
                <input type="text"
                       x-model="searchMemberQuery"
                       placeholder="Ketik NIK atau nama anggota untuk mencari..."
                       class="w-full pl-12 pr-4 py-3.5 rounded-xl border border-gray-300 bg-white focus:outline-none focus:border-[#0052cc] focus:ring-4 focus:ring-blue-500/10 text-sm font-medium shadow-2xs transition-all">
            </div>

            <!-- Search Results Dropdown -->
            <div x-show="filteredMemberList.length > 0"
                 class="mb-6 bg-white border border-gray-200 rounded-xl shadow-lg max-h-48 overflow-y-auto divide-y divide-gray-100">
                <template x-for="m in filteredMemberList" :key="m.id">
                    <div @click="selectMember(m)"
                         class="p-3 hover:bg-blue-50 cursor-pointer flex items-center justify-between text-xs">
                        <div>
                            <span class="font-bold text-gray-900" x-text="m.name"></span>
                            <span class="text-gray-500 font-mono ml-2" x-text="'NIK: ' + m.nik"></span>
                        </div>
                        <span class="font-bold font-mono text-emerald-600" x-text="'Sisa Plafon: ' + formatRupiah(m.creditLimit - m.currentDebt)"></span>
                    </div>
                </template>
            </div>

            <!-- Selected Member Card -->
            <template x-if="selectedMember">
                <div class="bg-white rounded-2xl border-2 border-[#0052cc] p-6 relative shadow-sm">
                    <!-- Profile Info -->
                    <div class="mb-5">
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-black text-gray-900" x-text="selectedMember.name"></h2>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                  :class="selectedMember.status === 'AKTIF' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'"
                                  x-text="selectedMember.status"></span>
                        </div>
                        <div class="flex items-center gap-2 text-gray-500 mt-1 text-xs">
                            <span class="material-symbols-rounded text-[16px]">badge</span>
                            <span class="font-mono font-bold" x-text="'NIK: ' + selectedMember.nik"></span>
                            <span class="text-gray-300">•</span>
                            <span x-text="selectedMember.dept"></span>
                        </div>
                    </div>

                    <!-- Plafon Info Grid -->
                    <div class="grid grid-cols-2 gap-4 mb-5">
                        <div class="bg-[#f8f9fa] rounded-xl p-4 border border-gray-200">
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">PLAFON BULANAN</span>
                            <p class="font-mono font-bold text-gray-700 mt-1 text-base" x-text="formatRupiah(selectedMember.creditLimit)"></p>
                        </div>
                        <div class="bg-[#f8f9fa] rounded-xl p-4 border border-gray-200">
                            <span class="text-[10px] font-bold text-[#0052cc] uppercase tracking-wider block">SISA PLAFON SAAT INI</span>
                            <p class="font-mono font-black text-[#0052cc] mt-1 text-base" x-text="formatRupiah(sisaPlafon)"></p>
                        </div>
                    </div>

                    <!-- Validation Box -->
                    <div class="rounded-xl p-5 border relative overflow-hidden"
                         :class="isSaldoCukup ? 'bg-[#f2fbf5] border-green-200' : 'bg-rose-50 border-rose-200'">
                        <div class="absolute left-0 top-0 bottom-0 w-1.5"
                             :class="isSaldoCukup ? 'bg-emerald-600' : 'bg-rose-600'"></div>

                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-gray-900 text-xs uppercase tracking-wider">Validasi Kredit Toko</h3>
                            <span class="text-[11px] font-bold px-3 py-1 rounded-full flex items-center gap-1"
                                  :class="isSaldoCukup ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'">
                                <span class="material-symbols-rounded text-[16px]" x-text="isSaldoCukup ? 'check_circle' : 'cancel'"></span>
                                <span x-text="isSaldoCukup ? 'SALDO CUKUP' : 'PLAFON TIDAK CUKUP'"></span>
                            </span>
                        </div>

                        <div class="space-y-2 text-xs font-mono">
                            <div class="flex justify-between text-gray-600">
                                <span>Sisa Plafon Saat Ini</span>
                                <span class="font-bold text-gray-800" x-text="formatRupiah(sisaPlafon)"></span>
                            </div>
                            <div class="flex justify-between text-gray-600 pb-2 border-b border-gray-200 border-dashed">
                                <span>Tagihan Belanja Baru</span>
                                <span class="font-bold text-rose-600" x-text="'- ' + formatRupiah(grandTotal)"></span>
                            </div>
                            <div class="flex justify-between items-center pt-1">
                                <span class="font-bold text-gray-900 font-sans">Estimasi Sisa Akhir:</span>
                                <span class="font-bold text-sm"
                                      :class="estimasiSisaAkhir >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                                      x-text="formatRupiah(estimasiSisaAkhir)"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Right Panel: Transaction Summary -->
        <div class="w-5/12 bg-white p-8 flex flex-col border-l border-gray-200">
            <h3 class="font-bold text-gray-800 mb-4 text-sm flex items-center justify-between">
                <span>Rincian Keranjang Belanja</span>
                <span class="text-xs text-gray-500 font-mono" x-text="cartItems.length + ' Item'"></span>
            </h3>

            <!-- Items List -->
            <div class="flex-1 overflow-y-auto space-y-3 pr-1">
                <template x-for="item in cartItems" :key="item.name">
                    <div class="flex justify-between items-start border-b border-gray-100 pb-3 text-xs">
                        <div>
                            <h4 class="font-bold text-gray-900" x-text="item.name"></h4>
                            <p class="text-[11px] font-bold text-[#0052cc] mt-0.5" x-text="item.qty + ' unit x ' + formatRupiah(item.memberPrice || item.price)"></p>
                        </div>
                        <span class="font-mono font-bold text-gray-800" x-text="formatRupiah((item.memberPrice || item.price) * item.qty)"></span>
                    </div>
                </template>
            </div>

            <!-- Footer Section -->
            <div class="border-t border-gray-200 pt-5 mt-4 space-y-4">
                <div class="space-y-1.5 text-xs font-mono">
                    <div class="flex justify-between text-gray-500">
                        <span>Subtotal Belanja:</span>
                        <span class="font-bold text-gray-800" x-text="formatRupiah(subtotal)"></span>
                    </div>
                    <div class="flex justify-between text-gray-500">
                        <span>Pajak (PPN 11%):</span>
                        <span class="font-bold text-gray-800" x-text="formatRupiah(taxAmount)"></span>
                    </div>
                    <div class="flex justify-between items-center text-sm font-bold pt-2 border-t border-gray-100">
                        <span class="text-gray-900">Total Potong Plafon:</span>
                        <span class="font-bold text-lg text-[#0052cc]" x-text="formatRupiah(grandTotal)"></span>
                    </div>
                </div>

                <div class="flex flex-col gap-2.5 pt-2">
                    <button type="button"
                            @click="confirmCreditPayment()"
                            :disabled="!isSaldoCukup"
                            class="w-full bg-[#0052cc] hover:bg-[#0043a8] disabled:opacity-40 text-white font-bold py-3.5 rounded-xl shadow-xs transition-all flex items-center justify-center gap-2 text-xs cursor-pointer active:scale-95">
                        <span class="material-symbols-rounded text-[18px]">credit_score</span>
                        <span>Konfirmasi Potong Plafon Anggota</span>
                    </button>

                    <a href="{{ url('/pos') }}" class="w-full bg-slate-100 hover:bg-slate-200 text-gray-700 font-bold py-3 rounded-xl transition-colors flex items-center justify-center text-xs text-center">
                        Batal &amp; Kembali ke Layar Kasir
                    </a>
                </div>

                <div class="flex items-center justify-center gap-1.5 text-[10px] text-gray-400 font-bold uppercase tracking-wider text-center">
                    <span class="material-symbols-rounded text-[14px] text-emerald-600">verified_user</span>
                    <span>Transaksi ini otomatis dicatat sebagai pemotongan gaji bulanan koperasi</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
