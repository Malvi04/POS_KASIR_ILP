@extends('layouts.app')

@section('title', 'Layar Kasir POS - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    showHoldModal: false,
    showMemberModal: false,
    searchQuery: '',
    selectedCategory: 'Semua',
    isMember: false,
    selectedMember: null,

    // Products Catalog State
    products: [],
    initProducts() {
        try {
            const raw = localStorage.getItem('pos_ilp_inventory_products_v1');
            if (raw) {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed) && parsed.length > 0) {
                    this.products = parsed;
                    return;
                }
            }
        } catch (e) {
            console.error('Error loading inventory products in POS:', e);
        }
        // Fallback seed
        this.products = [
            { sku: 'PRD-001', barcode: '8991234567890', name: 'Aqua Mineral 600ml', category: 'Minuman', stock: 450, unit: 'Botol', price: 5500, memberPrice: 5000, status: 'TERSEDIA' },
            { sku: 'PRD-002', barcode: '8992345678901', name: 'Chitato Sapi Panggang 68g', category: 'Snack', stock: 8, unit: 'Pcs', price: 12800, memberPrice: 12000, status: 'STOK_RENDAH' },
            { sku: 'PRD-003', barcode: '8993456789012', name: 'Ultra Milk Full Cream 1L', category: 'Minuman', stock: 32, unit: 'Tetra', price: 19200, memberPrice: 18500, status: 'NEAR_EXPIRED' },
            { sku: 'PRD-004', barcode: '8994567890123', name: 'Beras Pandan Wangi 5kg', category: 'Kebutuhan Pokok', stock: 120, unit: 'Pack', price: 82000, memberPrice: 79000, status: 'TERSEDIA' },
            { sku: 'PRD-005', barcode: '8995678901234', name: 'Sunlight Jeruk Nipis 755ml', category: 'Pembersih', stock: 54, unit: 'Botol', price: 15500, memberPrice: 14500, status: 'TERSEDIA' },
            { sku: 'PRD-006', barcode: '899275321004', name: 'Indomie Goreng Spesial 85g', category: 'Makanan', stock: 320, unit: 'Pcs', price: 3500, memberPrice: 3200, status: 'TERSEDIA' }
        ];
    },

    get availableCategories() {
        const cats = ['Semua'];
        this.products.forEach(p => {
            if (p.category && !cats.includes(p.category)) {
                cats.push(p.category);
            }
        });
        return cats;
    },

    get filteredProducts() {
        return this.products.filter(p => {
            if (this.selectedCategory !== 'Semua' && p.category !== this.selectedCategory) return false;
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                const matchName = (p.name || '').toLowerCase().includes(q);
                const matchBarcode = (p.barcode || '').includes(q);
                const matchSku = (p.sku || '').toLowerCase().includes(q);
                return matchName || matchBarcode || matchSku;
            }
            return true;
        });
    },

    // Cart State & Persistence
    cart: [],
    initCart() {
        try {
            const raw = localStorage.getItem('pos_ilp_active_cart_v1');
            if (raw) {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed) && parsed.length > 0) {
                    this.cart = parsed;
                    return;
                }
            }
        } catch (e) {}
        // Default initial items
        this.cart = [
            { sku: 'PRD-006', barcode: '899275321004', name: 'Indomie Goreng Spesial 85g', price: 3500, memberPrice: 3200, qty: 3, unit: 'Pcs' },
            { sku: 'PRD-009', barcode: '8998901234567', name: 'Oreo Vanilla 133g', price: 9500, memberPrice: 8200, qty: 1, unit: 'Pcs' },
            { sku: 'PRD-008', barcode: '8997890123456', name: 'Teh Pucuk Harum 350ml', price: 4000, memberPrice: 3800, qty: 2, unit: 'Botol' }
        ];
        this.saveCart();
    },

    saveCart() {
        localStorage.setItem('pos_ilp_active_cart_v1', JSON.stringify(this.cart));
    },

    addToCart(p) {
        if (!p) return;
        const existing = this.cart.find(it => it.sku === p.sku || it.barcode === p.barcode);
        if (existing) {
            existing.qty += 1;
        } else {
            this.cart.push({
                sku: p.sku,
                barcode: p.barcode,
                name: p.name,
                price: Number(p.price) || 0,
                memberPrice: Number(p.memberPrice || p.price) || 0,
                qty: 1,
                unit: p.unit || 'Pcs'
            });
        }
        this.saveCart();
        this.triggerPosToast(`+1 ${p.name} ditambahkan`);
    },

    updateCartQty(index, delta) {
        if (this.cart[index]) {
            this.cart[index].qty += delta;
            if (this.cart[index].qty <= 0) {
                this.cart.splice(index, 1);
            }
            this.saveCart();
        }
    },

    removeFromCart(index) {
        if (this.cart[index]) {
            const name = this.cart[index].name;
            this.cart.splice(index, 1);
            this.saveCart();
            this.triggerPosToast(`${name} dihapus dari keranjang`);
        }
    },

    clearCart() {
        if (this.cart.length === 0) return;
        if (confirm('Kosongkan seluruh isi keranjang belanja?')) {
            this.cart = [];
            this.saveCart();
            this.triggerPosToast('Keranjang telah dikosongkan.');
        }
    },

    handleSearchEnter() {
        if (!this.searchQuery.trim()) return;
        const q = this.searchQuery.trim().toLowerCase();
        // Exact barcode match first
        const exactBarcode = this.products.find(p => p.barcode === this.searchQuery.trim());
        if (exactBarcode) {
            this.addToCart(exactBarcode);
            this.searchQuery = '';
            return;
        }
        // Match single result
        const matches = this.filteredProducts;
        if (matches.length === 1) {
            this.addToCart(matches[0]);
            this.searchQuery = '';
        }
    },

    // Cart Financial Calculations
    get subtotal() {
        return this.cart.reduce((sum, it) => {
            const price = this.isMember ? (it.memberPrice || it.price) : it.price;
            return sum + (price * it.qty);
        }, 0);
    },

    get discountSavings() {
        if (!this.isMember) return 0;
        return this.cart.reduce((sum, it) => {
            const diff = Math.max(0, it.price - (it.memberPrice || it.price));
            return sum + (diff * it.qty);
        }, 0);
    },

    get taxAmount() {
        return Math.round(this.subtotal * 0.11);
    },

    get grandTotal() {
        return this.subtotal + this.taxAmount;
    },

    get totalItemsCount() {
        return this.cart.reduce((sum, it) => sum + it.qty, 0);
    },

    // Held Bills State & Methods
    heldBills: [],
    initHeldBills() {
        try {
            const raw = localStorage.getItem('pos_ilp_held_bills_v1');
            if (raw) {
                this.heldBills = JSON.parse(raw);
                this.activeSession.heldBillsCount = this.heldBills.length;
                return;
            }
        } catch (e) {}
        this.heldBills = [
            {
                id: '#HOLD-001',
                customerName: 'Budi Santoso (Anggota)',
                holdTime: '10:45 WIB',
                totalAmount: 145000,
                itemCount: 12,
                items: [
                    { sku: 'PRD-006', barcode: '899275321004', name: 'Indomie Goreng Spesial 85g', price: 3500, memberPrice: 3200, qty: 5, unit: 'Pcs' },
                    { sku: 'PRD-003', barcode: '8993456789012', name: 'Ultra Milk Full Cream 1L', price: 19200, memberPrice: 18500, qty: 2, unit: 'Tetra' },
                    { sku: 'PRD-004', barcode: '8994567890123', name: 'Beras Pandan Wangi 5kg', price: 82000, memberPrice: 79000, qty: 1, unit: 'Pack' },
                    { sku: 'PRD-001', barcode: '8991234567890', name: 'Aqua Mineral 600ml', price: 5500, memberPrice: 5000, qty: 4, unit: 'Botol' }
                ]
            },
            {
                id: '#HOLD-002',
                customerName: 'Walk-in Customer',
                holdTime: '11:12 WIB',
                totalAmount: 42500,
                itemCount: 4,
                items: [
                    { sku: 'PRD-002', barcode: '8992345678901', name: 'Chitato Sapi Panggang 68g', price: 12800, memberPrice: 12000, qty: 2, unit: 'Pcs' },
                    { sku: 'PRD-008', barcode: '8997890123456', name: 'Teh Pucuk Harum 350ml', price: 4000, memberPrice: 3800, qty: 2, unit: 'Botol' }
                ]
            }
        ];
        this.saveHeldBills();
    },

    saveHeldBills() {
        localStorage.setItem('pos_ilp_held_bills_v1', JSON.stringify(this.heldBills));
        this.activeSession.heldBillsCount = this.heldBills.length;
        this.persistAllState();
    },

    holdCurrentBill() {
        if (this.cart.length === 0) {
            this.triggerPosToast('Keranjang belanja kosong! Tidak ada transaksi yang ditahan.', 'error');
            return;
        }

        const now = new Date();
        const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') + ' WIB';
        const holdId = '#HOLD-' + String(now.getTime()).slice(-4);
        const custName = this.isMember && this.selectedMember ? this.selectedMember.name + ' (Anggota)' : 'Pelanggan Walk-in';

        const newHold = {
            id: holdId,
            customerName: custName,
            holdTime: timeStr,
            totalAmount: this.grandTotal,
            itemCount: this.totalItemsCount,
            items: JSON.parse(JSON.stringify(this.cart))
        };

        this.heldBills.unshift(newHold);
        this.saveHeldBills();
        this.cart = [];
        this.saveCart();
        this.showHoldModal = false;
        this.triggerPosToast(`Transaksi ${holdId} berhasil ditahan.`);
    },

    recallBill(bill) {
        if (!bill || !bill.items) return;
        this.cart = JSON.parse(JSON.stringify(bill.items));
        this.saveCart();

        const idx = this.heldBills.findIndex(b => b.id === bill.id);
        if (idx !== -1) {
            this.heldBills.splice(idx, 1);
            this.saveHeldBills();
        }

        this.showHoldModal = false;
        this.triggerPosToast(`Bill ${bill.id} berhasil dipanggil ke keranjang.`);
    },

    // Payment Modal State
    paymentModal: {
        open: false,
        method: 'tunai',
        uangDiterima: '',
        kembalian: 0,
        splitCash: 0,
        splitNonCash: 0
    },

    openPaymentModal() {
        if (this.cart.length === 0) {
            this.triggerPosToast('Keranjang kosong! Silakan tambahkan produk terlebih dahulu.', 'error');
            return;
        }
        this.paymentModal.open = true;
        this.paymentModal.method = 'tunai';
        this.paymentModal.uangDiterima = String(this.grandTotal);
        this.calcKembalian();
        this.calcSplitPayment();
    },

    formatNumber(num) {
        return new Intl.NumberFormat('id-ID').format(num || 0);
    },

    calcKembalian() {
        const uang = parseInt(this.paymentModal.uangDiterima.toString().replace(/\D/g, ''), 10) || 0;
        this.paymentModal.kembalian = uang - this.grandTotal;
    },

    calcSplitPayment() {
        let cash = Math.min(Math.max(parseInt(this.paymentModal.splitCash, 10) || 0, 0), this.grandTotal);
        this.paymentModal.splitCash = cash;
        this.paymentModal.splitNonCash = Math.max(this.grandTotal - cash, 0);
    },

    setUang(amount) {
        this.paymentModal.uangDiterima = String(amount);
        this.calcKembalian();
    },

    processPayment(method) {
        if (this.cart.length === 0) return;
        if (method === 'tunai' && this.paymentModal.kembalian < 0) {
            this.triggerPosToast('Uang diterima masih kurang dari total tagihan!', 'error');
            return;
        }

        const now = new Date();
        const dateStr = now.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
        const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') + ' WIB';
        const trxId = '#TRX-' + now.toISOString().slice(0, 10).replace(/-/g, '') + '-' + Math.floor(1000 + Math.random() * 9000);

        const trxRecord = {
            id: trxId,
            rawId: 'TRX-' + Math.floor(1000 + Math.random() * 9000),
            time: timeStr,
            date: dateStr,
            terminal: this.activeSession.terminalLabel || 'Kasir 01',
            cashier: this.activeSession.cashierName || 'Budi Kurniawan',
            method: method.toUpperCase(),
            methodLabel: method === 'tunai' ? 'Uang Tunai' : (method === 'qris' ? 'QRIS Dinamis' : (method === 'campuran' ? 'Tunai + QRIS' : 'Kredit Anggota')),
            cashPortion: method === 'tunai' ? this.grandTotal : (method === 'campuran' ? this.paymentModal.splitCash : 0),
            nonCashPortion: method === 'qris' ? this.grandTotal : (method === 'campuran' ? this.paymentModal.splitNonCash : (method === 'kredit' ? this.grandTotal : 0)),
            subtotal: this.subtotal,
            discount: this.discountSavings,
            tax: this.taxAmount,
            total: this.grandTotal,
            cashGiven: method === 'tunai' ? (parseInt(this.paymentModal.uangDiterima.replace(/\D/g, ''), 10) || this.grandTotal) : 0,
            change: method === 'tunai' ? Math.max(0, this.paymentModal.kembalian) : 0,
            status: 'SELESAI',
            syncStatus: 'SYNCED',
            items: this.cart.map(it => ({
                name: it.name,
                qty: it.qty,
                unit: it.unit || 'Pcs',
                price: this.isMember ? (it.memberPrice || it.price) : it.price,
                total: (this.isMember ? (it.memberPrice || it.price) : it.price) * it.qty
            }))
        };

        // 1. Save to transactions history
        try {
            let trxs = [];
            const rawTrx = localStorage.getItem('pos_ilp_transactions_v1');
            if (rawTrx) trxs = JSON.parse(rawTrx);
            trxs.unshift(trxRecord);
            localStorage.setItem('pos_ilp_transactions_v1', JSON.stringify(trxs));
        } catch (e) {
            console.error('Error saving transaction:', e);
        }

        // 2. Save latest receipt
        localStorage.setItem('pos_ilp_latest_printed_receipt_v1', JSON.stringify(trxRecord));

        // 3. Deduct product inventory stock
        try {
            this.cart.forEach(cartItem => {
                const prod = this.products.find(p => p.sku === cartItem.sku || p.barcode === cartItem.barcode);
                if (prod) {
                    prod.stock = Math.max(0, prod.stock - cartItem.qty);
                    if (prod.stock === 0) prod.status = 'HABIS';
                    else if (prod.stock <= prod.minStock) prod.status = 'STOK_RENDAH';
                }
            });
            localStorage.setItem('pos_ilp_inventory_products_v1', JSON.stringify(this.products));
        } catch (e) {
            console.error('Error deducting inventory:', e);
        }

        // 4. Update Cashier Session Figures
        if (method === 'tunai') {
            this.activeSession.cashSalesNet = (Number(this.activeSession.cashSalesNet) || 0) + this.grandTotal;
        } else if (method === 'qris') {
            this.activeSession.qrisSales = (Number(this.activeSession.qrisSales) || 0) + this.grandTotal;
        } else if (method === 'campuran') {
            this.activeSession.splitCashPortion = (Number(this.activeSession.splitCashPortion) || 0) + this.paymentModal.splitCash;
            this.activeSession.splitNonCashPortion = (Number(this.activeSession.splitNonCashPortion) || 0) + this.paymentModal.splitNonCash;
        }
        this.activeSession.trxCount = (Number(this.activeSession.trxCount) || 0) + 1;
        this.persistAllState();

        // 5. Clear Cart
        this.cart = [];
        this.saveCart();

        // 6. Navigate to receipt print
        window.location.href = '/cetak-struk';
    },

    // Toast Notification
    posToastMsg: '',
    posToastType: 'success',
    showPosToast: false,
    posToastTimer: null,
    triggerPosToast(msg, type = 'success') {
        this.posToastMsg = msg;
        this.posToastType = type;
        this.showPosToast = true;
        if (this.posToastTimer) clearTimeout(this.posToastTimer);
        this.posToastTimer = setTimeout(() => { this.showPosToast = false; }, 3000);
    }
}"
x-init="initOpening(); initProducts(); initCart(); initHeldBills();"
@pos-session-sync.window="initOpening()"
@pos-inventory-synced.window="initProducts()"
@keydown.f2.window.prevent="$refs.searchInput.focus()"
@keydown.f3.window.prevent="showHoldModal = true"
@keydown.f4.window.prevent="openPaymentModal()"
class="flex w-full h-full relative text-gray-800">

    <!-- Sidebar -->
    <x-cashier-sidebar active="pos" />

    <!-- Main Section -->
    <main class="flex-1 flex flex-col bg-slate-50 min-w-0">
        <!-- Header -->
        <x-cashier-header title="Terminal POS">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ url('/kasir/sesi') }}"
                   class="flex items-center gap-2 bg-blue-50 hover:bg-blue-100 text-[#0052cc] border border-blue-200 px-3 py-1.5 rounded-xl text-xs font-bold transition-all">
                    <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                    <span class="font-extrabold text-[#0052cc]" x-text="activeSession.terminalLabel">Kasir 01</span>
                    <span class="text-gray-300">•</span>
                    <span class="text-gray-700 font-bold" x-text="activeSession.cashierName">Budi Kurniawan</span>
                </a>

                <button type="button"
                        @click="$dispatch('open-cash-movement')"
                        class="flex items-center gap-1.5 bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer">
                    <span class="material-symbols-rounded text-[16px] text-[#0052cc]">account_balance_wallet</span>
                    <span>Kas Laci</span>
                </button>

                <button type="button"
                        @click="$dispatch('open-closing-wizard')"
                        class="flex items-center gap-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer">
                    <span class="material-symbols-rounded text-[16px] text-amber-700">logout</span>
                    <span>Tutup Sesi</span>
                </button>
            </div>
        </x-cashier-header>

        <!-- Content Area -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Left Side: Products Catalog -->
            <div class="flex-1 flex flex-col p-6 overflow-hidden">

                <!-- Search Box -->
                <div class="bg-gray-200/50 rounded-xl p-4 mb-4">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-bold text-gray-800 text-base">Input Barcode / Scan Produk</h3>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-gray-500 tracking-wider bg-gray-200 px-2 py-0.5 rounded">F2: CARI</span>
                            <span class="text-[10px] font-bold text-gray-500 tracking-wider bg-gray-200 px-2 py-0.5 rounded">ENTER: TAMBAH</span>
                        </div>
                    </div>
                    <div class="relative">
                        <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">barcode_scanner</span>
                        <input type="text"
                               x-ref="searchInput"
                               x-model="searchQuery"
                               @keydown.enter.prevent="handleSearchEnter()"
                               placeholder="Scan barcode EAN-13 atau ketik nama produk..."
                               class="w-full pl-12 pr-4 py-3.5 rounded-xl border border-gray-300 bg-white shadow-inner focus:outline-none focus:ring-2 focus:ring-[#0052cc]/20 focus:border-[#0052cc] transition-colors text-sm font-medium placeholder-gray-400">
                    </div>
                </div>

                <!-- Category Tabs -->
                <div class="flex gap-2 overflow-x-auto pb-3 scrollbar-hide shrink-0">
                    <template x-for="cat in availableCategories" :key="cat">
                        <button type="button"
                                @click="selectedCategory = cat"
                                :class="selectedCategory === cat ? 'bg-[#0052cc] text-white shadow-xs' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50'"
                                class="px-4 py-2 font-bold rounded-full text-xs transition-colors whitespace-nowrap cursor-pointer"
                                x-text="cat">
                        </button>
                    </template>
                </div>

                <!-- Product Grid (Dynamic from localStorage) -->
                <div class="flex-1 overflow-y-auto mt-2 pb-4">
                    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3.5">
                        <template x-for="p in filteredProducts" :key="p.sku">
                            <div @click="addToCart(p)"
                                 class="bg-white border border-gray-200 rounded-xl p-3.5 shadow-2xs hover:shadow-md hover:border-[#0052cc] cursor-pointer transition-all flex flex-col justify-between group active:scale-[0.98]">
                                <div>
                                    <div class="flex items-start justify-between gap-1 mb-1">
                                        <span class="text-[10px] font-bold text-[#0052cc] bg-blue-50 px-2 py-0.5 rounded" x-text="p.category"></span>
                                        <span class="font-mono text-[10px] text-gray-400" x-text="p.sku"></span>
                                    </div>
                                    <h4 class="font-bold text-gray-900 text-xs leading-snug line-clamp-2 group-hover:text-[#0052cc] transition-colors" x-text="p.name"></h4>
                                </div>

                                <div class="mt-3 pt-2 border-t border-gray-100 flex flex-col gap-1">
                                    <div class="flex items-baseline justify-between">
                                        <span class="text-sm font-black text-gray-900 font-mono" x-text="formatRupiah(isMember ? (p.memberPrice || p.price) : p.price)"></span>
                                        <template x-if="isMember && p.memberPrice < p.price">
                                            <span class="text-[10px] text-gray-400 line-through font-mono" x-text="formatRupiah(p.price)"></span>
                                        </template>
                                    </div>
                                    <div class="flex items-center justify-between text-[10.5px]">
                                        <span class="text-gray-500 font-medium" x-text="'Stok: ' + p.stock + ' ' + (p.unit || 'Pcs')"></span>
                                        <template x-if="p.status === 'STOK_RENDAH'">
                                            <span class="text-amber-600 font-bold">Stok Rendah</span>
                                        </template>
                                        <template x-if="p.status === 'NEAR_EXPIRED'">
                                            <span class="text-rose-600 font-bold">Hampir ED</span>
                                        </template>
                                        <template x-if="p.status === 'HABIS'">
                                            <span class="text-gray-400 font-bold">Kosong</span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Empty state if no products -->
                        <template x-if="filteredProducts.length === 0">
                            <div class="col-span-full py-12 text-center text-gray-400">
                                <span class="material-symbols-rounded text-4xl mb-1 text-gray-300">inventory_2</span>
                                <p class="text-xs font-bold text-gray-600">Tidak ada produk yang cocok dengan pencarian.</p>
                                <button type="button" @click="searchQuery = ''; selectedCategory = 'Semua'" class="text-xs text-[#0052cc] font-bold mt-2 hover:underline">Reset Filter</button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Right Side: Active Cart -->
            <div class="w-[420px] bg-slate-200/50 border-l border-gray-300 flex flex-col shadow-inner z-10 shrink-0">
                <!-- Cart Header & Customer Type Toggle -->
                <div class="p-4 border-b border-gray-300/60 bg-white/60 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-black text-gray-800">Keranjang Belanja</h2>
                            <p class="text-xs text-gray-500 font-medium" x-text="cart.length + ' Baris Produk'"></p>
                        </div>
                        <button type="button"
                                @click="clearCart()"
                                title="Kosongkan Keranjang"
                                class="text-rose-600 hover:bg-rose-50 p-2 rounded-lg transition-colors cursor-pointer">
                            <span class="material-symbols-rounded text-[20px]">delete_sweep</span>
                        </button>
                    </div>

                    <!-- Member Pricing Benefit Toggle -->
                    <div class="flex items-center justify-between p-2.5 rounded-xl border transition-all"
                         :class="isMember ? 'bg-emerald-50 border-emerald-300 text-emerald-900' : 'bg-white border-gray-200 text-gray-700'">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-rounded text-[20px]" :class="isMember ? 'text-emerald-600' : 'text-gray-400'">badge</span>
                            <div class="text-xs">
                                <span class="font-bold block" x-text="isMember ? 'Anggota Kopkar Aktif' : 'Pelanggan Non-Member'"></span>
                                <span class="text-[10px] text-gray-500" x-text="isMember ? 'Mendapatkan benefit harga khusus anggota' : 'Harga jual umum toko berlaku'"></span>
                            </div>
                        </div>
                        <button type="button"
                                @click="isMember = !isMember"
                                :class="isMember ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
                                class="px-3 py-1 rounded-lg text-xs font-bold transition-colors cursor-pointer">
                            <span x-text="isMember ? 'Aktif' : 'Aktifkan'"></span>
                        </button>
                    </div>
                </div>

                <!-- Cart Items List -->
                <div class="flex-1 overflow-y-auto p-4 space-y-2.5">
                    <template x-for="(item, idx) in cart" :key="item.sku">
                        <div class="bg-white p-3 rounded-xl shadow-2xs border border-gray-200 flex gap-2.5 relative">
                            <div class="flex-1 flex flex-col justify-between">
                                <div class="flex justify-between items-start gap-2">
                                    <h4 class="font-bold text-gray-900 text-xs leading-snug" x-text="item.name"></h4>
                                    <span class="font-mono font-bold text-gray-900 text-xs shrink-0" x-text="formatRupiah((isMember ? item.memberPrice : item.price) * item.qty)"></span>
                                </div>

                                <div class="flex justify-between items-end mt-2 pt-1 border-t border-gray-100">
                                    <div class="flex flex-col">
                                        <span class="text-[11px] text-gray-500 font-mono" x-text="item.qty + ' x ' + formatRupiah(isMember ? item.memberPrice : item.price)"></span>
                                        <template x-if="isMember && item.memberPrice < item.price">
                                            <span class="text-[10px] text-emerald-600 font-bold" x-text="'Hemat: ' + formatRupiah((item.price - item.memberPrice) * item.qty)"></span>
                                        </template>
                                    </div>
                                    <div class="flex items-center bg-gray-100 rounded-lg p-0.5">
                                        <button type="button" @click="updateCartQty(idx, -1)" class="w-6 h-6 flex items-center justify-center text-gray-600 hover:bg-white rounded font-bold cursor-pointer transition-colors">-</button>
                                        <span class="w-7 text-center font-bold text-xs font-mono" x-text="item.qty"></span>
                                        <button type="button" @click="updateCartQty(idx, 1)" class="w-6 h-6 flex items-center justify-center text-gray-600 hover:bg-white rounded font-bold cursor-pointer transition-colors">+</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Empty Cart Message -->
                    <template x-if="cart.length === 0">
                        <div class="h-full flex flex-col items-center justify-center text-gray-400 py-12">
                            <span class="material-symbols-rounded text-4xl mb-1 text-gray-300">shopping_cart</span>
                            <p class="text-xs font-bold text-gray-600">Keranjang Masih Kosong</p>
                            <p class="text-[11px] text-gray-400 mt-0.5">Klik produk di katalog untuk menambahkan.</p>
                        </div>
                    </template>
                </div>

                <!-- Totals & Actions -->
                <div class="bg-gray-100 border-t border-gray-300 p-5 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
                    <div class="space-y-1.5 mb-3 text-xs">
                        <div class="flex justify-between text-gray-600 font-medium">
                            <span>Subtotal Belanja</span>
                            <span class="font-mono font-bold text-gray-800" x-text="formatRupiah(subtotal)"></span>
                        </div>
                        <template x-if="discountSavings > 0">
                            <div class="flex justify-between text-emerald-600 font-bold">
                                <span>Potongan Member</span>
                                <span class="font-mono" x-text="'-' + formatRupiah(discountSavings)"></span>
                            </div>
                        </template>
                        <div class="flex justify-between text-gray-600 font-medium">
                            <span>Pajak (PPN 11%)</span>
                            <span class="font-mono font-bold text-gray-800" x-text="formatRupiah(taxAmount)"></span>
                        </div>
                    </div>

                    <div class="border-t border-gray-300 pt-3 mb-4">
                        <div class="flex justify-between items-baseline">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">TOTAL TAGIHAN</span>
                            <span class="text-xs font-bold text-gray-500" x-text="totalItemsCount + ' Unit Barang'"></span>
                        </div>
                        <div class="text-3xl font-black text-[#0052cc] mt-1 font-mono flex items-baseline">
                            <span class="text-lg mr-1 font-bold text-[#0052cc]">Rp</span>
                            <span x-text="formatNumber(grandTotal)"></span>
                        </div>
                    </div>

                    <div class="flex gap-2.5">
                        <button type="button"
                                @click="showHoldModal = true"
                                class="flex-1 flex flex-col items-center justify-center gap-0.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 py-2.5 rounded-xl shadow-2xs font-bold transition-colors cursor-pointer">
                            <div class="flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[18px]">pause_circle</span>
                                <span class="text-xs">Tahan Bill</span>
                            </div>
                            <span class="text-[9px] text-gray-400 font-bold tracking-widest">F3</span>
                        </button>

                        <button type="button"
                                @click="openPaymentModal()"
                                class="flex-[1.5] flex flex-col items-center justify-center gap-0.5 bg-[#0052cc] hover:bg-[#0043a8] text-white py-2.5 rounded-xl shadow-xs font-bold transition-colors cursor-pointer active:scale-95">
                            <div class="flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[18px]">payments</span>
                                <span class="text-xs">Bayar Sekarang</span>
                            </div>
                            <span class="text-[9px] text-blue-200 font-bold tracking-widest">F4</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- TOAST NOTIFICATION -->
    <div x-show="showPosToast"
         x-transition.opacity
         style="display: none;"
         class="fixed top-5 right-5 z-70 px-4 py-2.5 rounded-xl shadow-lg text-xs font-bold text-white flex items-center gap-2 border"
         :class="posToastType === 'error' ? 'bg-rose-600 border-rose-500' : 'bg-gray-900 border-gray-700'">
        <span class="material-symbols-rounded text-[18px]" x-text="posToastType === 'error' ? 'error' : 'check_circle'"></span>
        <span x-text="posToastMsg"></span>
    </div>

    <!-- MODAL 1: DAFTAR BILL TERTAHAN & SIMPAN HOLD -->
    <template x-teleport="body">
        <div x-show="showHoldModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showHoldModal"
                 @click.away="showHoldModal = false"
                 x-transition.scale.origin.bottom
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-gray-200 flex flex-col">

                <div class="p-5 border-b border-gray-200 flex justify-between items-center bg-slate-50">
                    <div>
                        <h3 class="text-base font-black text-gray-900">Kelola Transaksi Ditahan (Hold Bill)</h3>
                        <p class="text-xs text-gray-500 font-medium">Tangguhkan keranjang aktif atau recall transaksi sebelumnya.</p>
                    </div>
                    <button @click="showHoldModal = false" class="text-gray-400 hover:text-gray-700 cursor-pointer">
                        <span class="material-symbols-rounded">close</span>
                    </button>
                </div>

                <!-- Action Button: Hold Current Cart -->
                <div class="p-4 bg-blue-50/60 border-b border-blue-100 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold text-[#0052cc] block">Tahan Keranjang Saat Ini?</span>
                        <span class="text-[11px] text-gray-500" x-text="'Total: ' + formatRupiah(grandTotal) + ' (' + totalItemsCount + ' items)'"></span>
                    </div>
                    <button type="button"
                            @click="holdCurrentBill()"
                            :disabled="cart.length === 0"
                            class="px-4 py-2 bg-[#0052cc] hover:bg-[#0043a8] disabled:opacity-40 text-white rounded-xl text-xs font-bold shadow-xs cursor-pointer">
                        Tahan Bill Sekarang
                    </button>
                </div>

                <!-- List of Existing Held Bills -->
                <div class="p-4 space-y-3 max-h-[350px] overflow-y-auto">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Daftar Antrean Bill Tertahan</span>

                    <template x-for="bill in heldBills" :key="bill.id">
                        <div class="bg-white border border-gray-200 rounded-xl p-3.5 flex items-center justify-between shadow-2xs hover:border-[#0052cc] transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold shrink-0">
                                    <span class="material-symbols-rounded text-[20px]">pause</span>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-gray-900 text-xs" x-text="bill.customerName"></h4>
                                        <span class="font-mono text-[10px] text-gray-400" x-text="bill.id"></span>
                                    </div>
                                    <div class="flex items-center gap-2 text-[11px] text-gray-500 mt-0.5 font-mono">
                                        <span x-text="bill.holdTime"></span>
                                        <span>•</span>
                                        <span x-text="bill.itemCount + ' Items'"></span>
                                        <span>•</span>
                                        <span class="font-bold text-gray-800" x-text="formatRupiah(bill.totalAmount)"></span>
                                    </div>
                                </div>
                            </div>

                            <button type="button"
                                    @click="recallBill(bill)"
                                    class="px-3.5 py-1.5 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-lg text-xs font-bold shadow-2xs cursor-pointer">
                                Recall
                            </button>
                        </div>
                    </template>

                    <template x-if="heldBills.length === 0">
                        <div class="text-center py-8 text-gray-400 text-xs">
                            <span class="material-symbols-rounded text-3xl mb-1 text-gray-300">task_alt</span>
                            <p class="font-bold">Tidak ada transaksi yang sedang ditahan.</p>
                        </div>
                    </template>
                </div>

                <div class="p-4 border-t border-gray-200 bg-slate-50 flex justify-between items-center">
                    <a href="{{ url('/kasir/transaksi/ditahan') }}" class="text-xs text-[#0052cc] font-bold hover:underline">Lihat Halaman Transaksi Ditahan &rarr;</a>
                    <button type="button" @click="showHoldModal = false" class="px-4 py-2 bg-white border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-100 cursor-pointer">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- MODAL 2: KONFIRMASI PEMBAYARAN -->
    @include('kasir.partials._payment-modal')

    <!-- MODAL 3: KONTROL PEMBUKAAN / OPENING KASIR -->
    @include('kasir.partials._opening-modal')

</div>
@endsection
