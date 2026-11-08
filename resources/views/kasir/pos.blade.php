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
    async initProducts() {
        try {
            const res = await fetch('/api/pos/products/search?limit=100', {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success && Array.isArray(json.data)) {
                    this.products = json.data;
                    localStorage.setItem('pos_ilp_inventory_products_v1', JSON.stringify(this.products));
                    return;
                }
            }
        } catch (e) {
            console.warn('API products search failed:', e);
        }

        try {
            const raw = localStorage.getItem('pos_ilp_inventory_products_v1');
            if (raw) {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) {
                    this.products = parsed;
                    return;
                }
            }
        } catch (e) {}

        this.products = [];
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
                    // Bersihkan jika masih berisi data demo bawaan (Indomie & Ultra Milk)
                    const isLegacyDemo = parsed.length === 2 && parsed.some(it => it.sku === 'PRD-006') && parsed.some(it => it.sku === 'PRD-003');
                    if (!isLegacyDemo) {
                        this.cart = parsed;
                        return;
                    } else {
                        localStorage.removeItem('pos_ilp_active_cart_v1');
                    }
                }
            }
        } catch (e) {}
        // Keranjang kasir bersih/kosong (0) sebelum kasir memilih produk
        this.cart = [];
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
                originalPrice: Number(p.original_price || p.price) || 0,
                price: Number(p.price) || 0,
                memberPrice: Number(p.member_price || p.price) || 0,
                qty: 1,
                unit: p.unit || 'Pcs',
                isTaxable: p.is_taxable !== false,
                isNearExpired: Boolean(p.is_near_expired),
                daysUntilExpiration: p.days_until_expiration,
                clearanceDiscountPercent: Number(p.clearance_discount_percent) || 0,
                clearanceWarningBadge: p.clearance_warning_badge || null
            });
        }
        this.saveCart();
        const discountNotice = p.is_near_expired ? ` (+Diskon Clearance ${p.clearance_discount_percent}%)` : '';
        this.triggerPosToast(`+1 ${p.name}${discountNotice} ditambahkan`);
    },

    updateCartQty(index, delta) {
        if (this.cart[index]) {
            if (delta < 0 && this.cart[index].qty === 1) {
                // If reducing 1 to 0, require Supervisor Void authorization
                this.requestVoidItem(index);
                return;
            }
            this.cart[index].qty += delta;
            this.saveCart();
        }
    },

    // Otorisasi Void / Anti-Fraud Control Modal
    voidModal: {
        open: false,
        actionType: 'VOID_ITEM', // VOID_ITEM or VOID_ALL
        itemIndex: null,
        item: null,
        pin: '',
        reason: 'Pelanggan membatalkan barang',
        error: '',
        isSubmitting: false
    },

    requestVoidItem(index) {
        if (!this.cart[index]) return;
        this.voidModal.actionType = 'VOID_ITEM';
        this.voidModal.itemIndex = index;
        this.voidModal.item = this.cart[index];
        this.voidModal.pin = '';
        this.voidModal.reason = 'Pelanggan membatalkan barang';
        this.voidModal.error = '';
        this.voidModal.open = true;
    },

    requestVoidCart() {
        if (this.cart.length === 0) return;
        this.voidModal.actionType = 'VOID_ALL';
        this.voidModal.itemIndex = null;
        this.voidModal.item = null;
        this.voidModal.pin = '';
        this.voidModal.reason = 'Batal transaksi seluruh keranjang';
        this.voidModal.error = '';
        this.voidModal.open = true;
    },

    async submitVoid() {
        if (!this.voidModal.pin.trim()) {
            this.voidModal.error = 'Masukkan PIN / Password Otorisasi Supervisor!';
            return;
        }

        this.voidModal.isSubmitting = true;
        this.voidModal.error = '';

        try {
            if (this.voidModal.actionType === 'VOID_ITEM' && this.voidModal.item) {
                const res = await fetch('/api/pos/void/item', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        pin_or_password: this.voidModal.pin,
                        item_sku: this.voidModal.item.sku || 'ITEM',
                        item_name: this.voidModal.item.name,
                        item_qty: this.voidModal.item.qty,
                        amount_voided: (this.isMember ? this.voidModal.item.memberPrice : this.voidModal.item.price) * this.voidModal.item.qty,
                        cashier_name: (this.activeOperator && this.activeOperator.name) ? this.activeOperator.name : (this.activeSession.cashierName || 'Kasir'),
                        terminal_code: this.activeSession.terminalLabel || 'POS-01',
                        reason: this.voidModal.reason
                    })
                });

                const json = await res.json();
                if (!res.ok || !json.success) {
                    this.voidModal.error = json.message || 'Otorisasi ditolak!';
                    this.voidModal.isSubmitting = false;
                    return;
                }

                const removedName = this.voidModal.item.name;
                this.cart.splice(this.voidModal.itemIndex, 1);
                this.saveCart();
                this.voidModal.open = false;
                this.triggerPosToast(`Item [${removedName}] dibatalkan (Otorisasi: ${json.data.supervisor_name})`);
            } else {
                // Void All
                const res = await fetch('/api/pos/void/authorize', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        pin_or_password: this.voidModal.pin,
                        action_type: 'VOID_TRANSACTION'
                    })
                });

                const json = await res.json();
                if (!res.ok || !json.authorized) {
                    this.voidModal.error = json.message || 'Otorisasi PIN Supervisor ditolak!';
                    this.voidModal.isSubmitting = false;
                    return;
                }

                this.cart = [];
                this.saveCart();
                this.voidModal.open = false;
                this.triggerPosToast(`Seluruh keranjang belanja berhasil dibatalkan oleh ${json.supervisor.name}`);
            }
        } catch (e) {
            this.voidModal.error = 'Gagal menghubungi server otorisasi: ' + e.message;
        } finally {
            this.voidModal.isSubmitting = false;
        }
    },

    async handleSearchEnter() {
        if (!this.searchQuery.trim()) return;
        const q = this.searchQuery.trim();

        // 1. Try local exact barcode match
        const exactBarcode = this.products.find(p => p.barcode === q);
        if (exactBarcode) {
            this.addToCart(exactBarcode);
            this.searchQuery = '';
            return;
        }

        // 2. Instant Barcode Scanner API Lookup
        try {
            const res = await fetch(`/api/pos/products/barcode/${encodeURIComponent(q)}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success && json.data) {
                    this.addToCart(json.data);
                    if (!this.products.find(p => p.sku === json.data.sku)) {
                        this.products.unshift(json.data);
                    }
                    this.searchQuery = '';
                    return;
                }
            }
        } catch (e) {}

        // 3. Fallback matches in filtered products
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
        return this.cart.reduce((sum, it) => {
            let itemSavings = 0;
            // Member savings
            if (this.isMember && it.memberPrice && it.memberPrice < it.price) {
                itemSavings += (it.price - it.memberPrice) * it.qty;
            }
            // Expired clearance savings
            if (it.isNearExpired && it.originalPrice && it.originalPrice > it.price) {
                itemSavings += (it.originalPrice - it.price) * it.qty;
            }
            return sum + itemSavings;
        }, 0);
    },

    get taxAmount() {
        return this.cart.reduce((sum, it) => {
            if (it.isTaxable !== false) {
                const price = this.isMember ? (it.memberPrice || it.price) : it.price;
                return sum + Math.round((price * it.qty) * 0.11);
            }
            return sum;
        }, 0);
    },

    get grandTotal() {
        return this.subtotal + this.taxAmount;
    },

    get totalItemsCount() {
        return this.cart.reduce((sum, it) => sum + it.qty, 0);
    },

    // Held Bills State & Methods
    heldBills: [],
    async initHeldBills() {
        try {
            const term = this.activeSession.terminalLabel || 'POS-01';
            const res = await fetch(`/api/pos/held-bills?terminal_code=${encodeURIComponent(term)}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success && Array.isArray(json.data)) {
                    this.heldBills = json.data.map(b => ({
                        id: b.hold_code,
                        dbId: b.id,
                        customerName: b.customer_name,
                        holdTime: b.hold_time_label,
                        totalAmount: b.grand_total,
                        itemCount: b.total_items,
                        items: b.items
                    }));
                    this.activeSession.heldBillsCount = this.heldBills.length;
                    return;
                }
            }
        } catch (e) {
            console.warn('API held bills failed, using cache:', e);
        }

        try {
            const raw = localStorage.getItem('pos_ilp_held_bills_v1');
            if (raw) {
                this.heldBills = JSON.parse(raw);
                this.activeSession.heldBillsCount = this.heldBills.length;
            }
        } catch (e) {}
    },

    saveHeldBills() {
        localStorage.setItem('pos_ilp_held_bills_v1', JSON.stringify(this.heldBills));
        this.activeSession.heldBillsCount = this.heldBills.length;
        this.persistAllState();
    },

    async holdCurrentBill() {
        if (this.cart.length === 0) {
            this.triggerPosToast('Keranjang belanja kosong! Tidak ada transaksi yang ditahan.', 'error');
            return;
        }

        const custName = this.isMember && this.selectedMember ? this.selectedMember.name + ' (Anggota)' : 'Pelanggan Walk-in';
        const payload = {
            terminal_code: this.activeSession.terminalLabel || 'POS-01',
            customer_name: custName,
            member_nik: this.isMember && this.selectedMember ? this.selectedMember.nik : null,
            items: this.cart
        };

        try {
            const res = await fetch('/api/pos/hold', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            });
            const json = await res.json();
            if (json.success) {
                this.triggerPosToast(json.message);
                this.cart = [];
                this.saveCart();
                this.showHoldModal = false;
                await this.initHeldBills();
                return;
            }
        } catch (e) {
            console.warn('API hold failed, fallback local:', e);
        }

        // Fallback local hold
        const now = new Date();
        const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') + ' WIB';
        const holdId = '#HOLD-' + String(now.getTime()).slice(-4);
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

    async recallBill(bill) {
        if (!bill) return;

        if (bill.dbId) {
            try {
                const res = await fetch(`/api/pos/recall/${bill.dbId}`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();
                if (json.success && json.data) {
                    this.cart = json.data.items || [];
                    this.saveCart();
                    this.showHoldModal = false;
                    this.triggerPosToast(json.message);
                    await this.initHeldBills();
                    return;
                }
            } catch (e) {
                console.warn('API recall failed, fallback local:', e);
            }
        }

        // Fallback local recall
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
        splitNonCash: 0,
        qrisRef: ''
    },

    // Member Live Validation for Kredit Anggota
    memberValidation: {
        nik: '',
        loading: false,
        isEligible: false,
        message: '',
        data: null
    },

    async validateMemberNik() {
        if (!this.memberValidation.nik.trim()) return;
        this.memberValidation.loading = true;
        this.memberValidation.data = null;
        try {
            const res = await fetch(`/api/pos/members/validate/${encodeURIComponent(this.memberValidation.nik.trim())}?amount=${this.grandTotal}`, {
                headers: { 'Accept': 'application/json' }
            });
            const json = await res.json();
            this.memberValidation.data = json.data;
            this.memberValidation.isEligible = json.is_eligible;
            this.memberValidation.message = json.message;
            if (json.is_eligible && json.data) {
                this.isMember = true;
                this.selectedMember = json.data;
            }
        } catch (e) {
            this.memberValidation.message = 'Gagal memverifikasi database anggota: ' + e.message;
            this.memberValidation.isEligible = false;
        } finally {
            this.memberValidation.loading = false;
        }
    },

    openPaymentModal() {
        if (this.cart.length === 0) {
            this.triggerPosToast('Keranjang kosong! Silakan tambahkan produk terlebih dahulu.', 'error');
            return;
        }
        this.paymentModal.open = true;
        this.paymentModal.method = 'tunai';
        this.paymentModal.uangDiterima = String(this.grandTotal);
        this.paymentModal.qrisRef = 'QRIS-' + Date.now().toString().slice(-6);
        this.calcKembalian();
        this.calcSplitPayment();
        if (this.isMember && this.selectedMember) {
            this.memberValidation.nik = this.selectedMember.nik;
            this.validateMemberNik();
        }
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

    async processPayment(method) {
        if (this.cart.length === 0) return;
        if (method === 'tunai' && this.paymentModal.kembalian < 0) {
            this.triggerPosToast('Uang diterima masih kurang dari total tagihan!', 'error');
            return;
        }

        const payload = {
            terminal_code: this.activeSession.terminalLabel || 'POS-01',
            cashier_nik: (this.activeOperator && this.activeOperator.nik) ? this.activeOperator.nik : (this.activeSession.cashierNik || '202409821002'),
            cashier_name: (this.activeOperator && this.activeOperator.name) ? this.activeOperator.name : (this.activeSession.cashierName || 'Budi Kurniawan'),
            payment_method: method.toUpperCase(),
            cash_received: method === 'tunai' ? (parseInt(this.paymentModal.uangDiterima.replace(/\D/g, ''), 10) || this.grandTotal) : 0,
            payment_reference: method === 'qris' ? (this.paymentModal.qrisRef || ('QRIS-' + Date.now())) : null,
            split_cash: method === 'campuran' ? this.paymentModal.splitCash : 0,
            split_non_cash: method === 'campuran' ? this.paymentModal.splitNonCash : 0,
            member_nik: method === 'kredit' ? (this.memberValidation.data ? this.memberValidation.data.nik : (this.selectedMember ? this.selectedMember.nik : null)) : (this.isMember && this.selectedMember ? this.selectedMember.nik : null),
            items: this.cart.map(it => ({
                sku: it.sku,
                barcode: it.barcode,
                name: it.name,
                qty: it.qty,
                unit: it.unit || 'Pcs',
                price: it.price,
                member_price: it.memberPrice || it.price,
                is_member: this.isMember
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
                this.triggerPosToast(json.message || 'Transaksi gagal diproses sistem!', 'error');
                return;
            }

            // Save printed receipt record
            const trxRecord = {
                id: json.data.invoice_number,
                rawId: json.data.id,
                time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') + ' WIB',
                date: new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }),
                terminal: json.data.terminal_code,
                cashier: json.data.cashier_name,
                method: json.data.payment_method,
                methodLabel: json.data.payment_method === 'TUNAI' ? 'Uang Tunai' : (json.data.payment_method === 'QRIS' ? 'QRIS Dinamis' : (json.data.payment_method === 'KREDIT' ? 'Kredit Anggota' : 'Campuran')),
                subtotal: json.data.subtotal,
                discount: json.data.discount_total,
                tax: json.data.tax_total,
                total: json.data.grand_total,
                cashGiven: json.data.cash_received,
                change: json.data.change_returned,
                paymentReference: json.data.payment_reference,
                member: json.data.member,
                items: this.cart.map(it => ({
                    name: it.name,
                    qty: it.qty,
                    unit: it.unit || 'Pcs',
                    price: this.isMember ? (it.memberPrice || it.price) : it.price,
                    total: (this.isMember ? (it.memberPrice || it.price) : it.price) * it.qty,
                    isNearExpired: it.isNearExpired,
                    clearanceDiscountPercent: it.clearanceDiscountPercent
                }))
            };

            localStorage.setItem('pos_ilp_latest_printed_receipt_v1', JSON.stringify(trxRecord));

            // Refresh products catalog stock from backend
            this.initProducts();

            // Clear Cart
            this.cart = [];
            this.saveCart();

            // Close modal & navigate to receipt print
            this.paymentModal.open = false;
            window.location.href = '/cetak-struk';
        } catch (e) {
            console.error('API checkout failed:', e);
            this.triggerPosToast('Gagal menghubung ke server kasir: ' + e.message, 'error');
        }
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
        this.posToastTimer = setTimeout(() => { this.showPosToast = false; }, 3500);
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
                <!-- Terminal & Active Operator Pill -->
                <a href="{{ url('/kasir/sesi') }}"
                   class="flex items-center gap-2 bg-blue-50 hover:bg-blue-100 text-[#0052cc] border border-blue-200 px-3 py-1.5 rounded-xl text-xs font-bold transition-all"
                   :title="'ID Sesi: ' + (activeSession.sessionId || '-')">
                    <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                    <span class="font-extrabold text-[#0052cc]" x-text="activeSession.terminalLabel || 'Kasir 01'">Kasir 01</span>
                    <span class="text-gray-300">•</span>
                    <span class="material-symbols-rounded text-[15px] text-[#0052cc]">badge</span>
                    <span class="text-gray-800 font-black" x-text="activeOperator ? activeOperator.name : activeSession.cashierName">Budi Kurniawan</span>
                </a>

                <!-- Ganti Operator Kasir -->
                <button type="button"
                        @click="openOperatorSwitchModal()"
                        class="flex items-center gap-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer shadow-2xs"
                        title="Pergantian operator kasir">
                    <span class="material-symbols-rounded text-[16px]">switch_account</span>
                    <span>Ganti Operator</span>
                </button>

                <!-- Kunci Layar Terminal -->
                <button type="button"
                        @click="lockTerminal()"
                        class="flex items-center gap-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-300 px-2.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer shadow-2xs"
                        title="Kunci Layar Terminal Sementara">
                    <span class="material-symbols-rounded text-[16px]">lock</span>
                    <span class="hidden sm:inline">Kunci</span>
                </button>

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
                    <span>Tutup Sesi (Audit)</span>
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
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-gray-800 text-base">Input Barcode Scanner / Pencarian Cepat</h3>
                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Fast API PostgreSQL
                            </span>
                        </div>
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
                               placeholder="Scan barcode EAN-13 atau ketik nama/SKU produk..."
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

                <!-- Product Grid (Dynamic from PostgreSQL API) -->
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

                                    <!-- Auto-Discount Expired Badge (Warning H-14) -->
                                    <template x-if="p.is_near_expired">
                                        <div class="mt-1.5">
                                            <span class="inline-flex items-center gap-1 text-[10px] font-extrabold text-rose-700 bg-rose-50 border border-rose-200 px-2 py-0.5 rounded-md">
                                                <span class="material-symbols-rounded text-[13px] text-rose-600">alarm</span>
                                                <span x-text="'H-' + p.days_until_expiration + ' • Diskon ' + p.clearance_discount_percent + '%'"></span>
                                            </span>
                                        </div>
                                    </template>
                                </div>

                                <div class="mt-3 pt-2 border-t border-gray-100 flex flex-col gap-1">
                                    <div class="flex items-baseline justify-between">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-black text-gray-900 font-mono" x-text="formatRupiah(isMember ? (p.member_price || p.price) : p.price)"></span>
                                            <!-- Strike-through original price if near expired discount is active -->
                                            <template x-if="p.is_near_expired && p.original_price > p.price">
                                                <span class="text-[10px] text-rose-500 line-through font-mono" x-text="formatRupiah(p.original_price)"></span>
                                            </template>
                                        </div>
                                        <template x-if="isMember && (p.member_price || p.price) < p.price">
                                            <span class="text-[10px] text-gray-400 line-through font-mono" x-text="formatRupiah(p.price)"></span>
                                        </template>
                                    </div>
                                    <div class="flex items-center justify-between text-[10.5px]">
                                        <span class="text-gray-500 font-medium" x-text="'Stok: ' + p.stock + ' ' + (p.unit || 'Pcs')"></span>
                                        <span class="text-[10px] font-bold"
                                              :class="p.is_taxable !== false ? 'text-blue-600' : 'text-gray-400'"
                                              x-text="p.is_taxable !== false ? 'PPN' : 'Non-PPN'"></span>
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
                                @click="requestVoidCart()"
                                title="Void Seluruh Keranjang (Perlu PIN Supervisor)"
                                class="text-rose-600 hover:bg-rose-50 p-2 rounded-lg transition-colors cursor-pointer flex items-center gap-1 text-xs font-bold">
                            <span class="material-symbols-rounded text-[18px]">delete_sweep</span>
                            <span>Void Cart</span>
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
                                    <div>
                                        <h4 class="font-bold text-gray-900 text-xs leading-snug" x-text="item.name"></h4>
                                        <template x-if="item.isNearExpired">
                                            <span class="text-[10px] text-rose-600 font-extrabold flex items-center gap-0.5 mt-0.5">
                                                <span class="material-symbols-rounded text-[12px]">alarm</span>
                                                <span x-text="'Auto-Diskon H-' + (item.daysUntilExpiration || '14') + ' (' + item.clearanceDiscountPercent + '%)'"></span>
                                            </span>
                                        </template>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <span class="font-mono font-bold text-gray-900 text-xs" x-text="formatRupiah((isMember ? (item.memberPrice || item.price) : item.price) * item.qty)"></span>
                                        <!-- Void Item Button (Perlu PIN SPV) -->
                                        <button type="button"
                                                @click="requestVoidItem(idx)"
                                                title="Void Item (Otorisasi Supervisor)"
                                                class="text-gray-400 hover:text-rose-600 p-1 rounded-md hover:bg-rose-50 transition-colors cursor-pointer">
                                            <span class="material-symbols-rounded text-[16px]">close</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="flex justify-between items-end mt-2 pt-1 border-t border-gray-100">
                                    <div class="flex flex-col">
                                        <span class="text-[11px] text-gray-500 font-mono" x-text="item.qty + ' x ' + formatRupiah(isMember ? (item.memberPrice || item.price) : item.price)"></span>
                                        <template x-if="item.isTaxable !== false">
                                            <span class="text-[9px] text-gray-400 font-bold">Termasuk PPN 11%</span>
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
                                <span>Hemat (Member / Expired Clearance)</span>
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
                    <span class="text-xs text-gray-500 font-mono">Database: PostgreSQL API</span>
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

    <!-- MODAL 4: OTORISASI VOID / ANTI-FRAUD CONTROL -->
    <template x-teleport="body">
        <div x-show="voidModal.open"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-70 flex items-center justify-center p-4">
            <div @click.away="voidModal.open = false"
                 class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-red-200">
                <div class="p-5 border-b border-gray-100 bg-red-50/80 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-red-100 text-red-600 flex items-center justify-center">
                            <span class="material-symbols-rounded text-2xl">shield_person</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-gray-900">Otorisasi Void / Anti-Fraud</h3>
                            <p class="text-xs text-gray-500 font-medium" x-text="voidModal.actionType === 'VOID_ITEM' ? 'Pembatalan Item Barang' : 'Pembatalan Seluruh Keranjang'"></p>
                        </div>
                    </div>
                    <button type="button" @click="voidModal.open = false" class="text-gray-400 hover:text-gray-700">
                        <span class="material-symbols-rounded">close</span>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <!-- Info target item -->
                    <template x-if="voidModal.actionType === 'VOID_ITEM' && voidModal.item">
                        <div class="p-3.5 bg-slate-50 border border-gray-200 rounded-xl text-xs space-y-1">
                            <div class="flex justify-between font-bold text-gray-800">
                                <span x-text="voidModal.item.name"></span>
                                <span x-text="formatRupiah((isMember ? voidModal.item.memberPrice : voidModal.item.price) * voidModal.item.qty)"></span>
                            </div>
                            <div class="text-[11px] text-gray-500 font-mono" x-text="'Qty: ' + voidModal.item.qty + ' ' + (voidModal.item.unit || 'Pcs')"></div>
                        </div>
                    </template>

                    <!-- Error Message -->
                    <template x-if="voidModal.error">
                        <div class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-bold flex items-center gap-2">
                            <span class="material-symbols-rounded text-[18px]">error</span>
                            <span x-text="voidModal.error"></span>
                        </div>
                    </template>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Alasan Pembatalan (Void)</label>
                        <select x-model="voidModal.reason" class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs font-medium text-gray-800 outline-none focus:border-red-500 bg-gray-50">
                            <option value="Pelanggan salah ambil barang">Pelanggan salah ambil barang</option>
                            <option value="Pelanggan tidak jadi beli">Pelanggan tidak jadi beli</option>
                            <option value="Kemasan / fisik produk cacat atau bocor">Kemasan / fisik produk cacat atau bocor</option>
                            <option value="Salah input jumlah (qty) oleh kasir">Salah input jumlah (qty) oleh kasir</option>
                            <option value="Harga sistem berbeda dengan label rak">Harga sistem berbeda dengan label rak</option>
                            <option value="Uang tunai pelanggan tidak cukup">Uang tunai pelanggan tidak cukup</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">PIN Otorisasi Supervisor / Admin</label>
                        <input type="password"
                               x-model="voidModal.pin"
                               @keydown.enter.prevent="submitVoid()"
                               placeholder="Masukkan 6-digit PIN Supervisor..."
                               autofocus
                               class="w-full px-4 py-3 text-center tracking-widest font-mono text-lg rounded-xl border border-gray-300 text-gray-900 outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100">
                        <p class="text-[10px] text-gray-400 mt-1">Masukkan PIN 6-digit Supervisor / Admin resmi.</p>
                    </div>
                </div>
                <div class="p-4 bg-slate-50 border-t border-gray-100 flex justify-between items-center">
                    <button type="button" @click="voidModal.open = false" class="px-4 py-2 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-100">Batal</button>
                    <button type="button"
                            @click="submitVoid()"
                            :disabled="voidModal.isSubmitting || !voidModal.pin"
                            class="px-5 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-40 text-white rounded-xl text-xs font-bold shadow-xs flex items-center gap-2 cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">verified_user</span>
                        <span x-text="voidModal.isSubmitting ? 'Memverifikasi...' : 'Konfirmasi Otorisasi Void'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- MODAL 5: PERGANTIAN OPERATOR TERMINAL -->
    <template x-if="operatorSwitchModal.open">
        <div class="fixed inset-0 z-[90] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-xs">
            <div @click.away="operatorSwitchModal.open = false"
                 class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-gray-100 overflow-hidden flex flex-col">
                <div class="px-6 py-4.5 border-b border-gray-100 flex items-center justify-between bg-slate-50/70">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]">switch_account</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-gray-900">Ganti Operator Kasir</h3>
                            <p class="text-xs text-gray-500 font-medium">Sesi keuangan terminal tetap berjalan aktif.</p>
                        </div>
                    </div>
                    <button type="button" @click="operatorSwitchModal.open = false" class="text-gray-400 hover:text-gray-700 cursor-pointer p-1">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-gray-200/90 text-xs space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 font-medium">Terminal &amp; Sesi:</span>
                            <span class="font-bold text-gray-900" x-text="(activeSession.terminalLabel || 'Kasir 01') + ' (' + (activeSession.sessionId || '-') + ')'"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 font-medium">Operator Aktif:</span>
                            <span class="font-bold text-[#0052cc]" x-text="activeOperator ? activeOperator.name : activeSession.cashierName"></span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">Kasir Pengganti</label>
                        <select x-model="operatorSwitchModal.targetStaffId"
                                class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:bg-white focus:border-[#0052cc] outline-none cursor-pointer">
                            <template x-for="st in getEligibleCashiersForSwitch()" :key="st.id">
                                <option :value="st.id" x-text="st.name + (st.nik ? ' (NIK: ' + st.nik + ')' : '')"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">Alasan Pergantian</label>
                        <select x-model="operatorSwitchModal.reason"
                                class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-medium text-gray-800 focus:bg-white focus:border-[#0052cc] outline-none cursor-pointer">
                            <option value="Istirahat / Sholat">Istirahat / Sholat</option>
                            <option value="Pergantian Shift Kerja">Pergantian Shift Kerja</option>
                            <option value="Bantuan Antrean Kasir">Bantuan Antrean Kasir</option>
                            <option value="Keperluan Operasional">Keperluan Operasional Lainnya</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">PIN Kasir Pengganti</label>
                        <input type="password"
                               x-model="operatorSwitchModal.targetPin"
                               @keydown.enter.prevent="submitOperatorSwitch()"
                               placeholder="Masukkan PIN kasir pengganti..."
                               class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl font-mono text-xs font-bold text-gray-900 focus:bg-white focus:border-[#0052cc] outline-none">
                    </div>
                </div>

                <div class="px-6 py-4 bg-slate-50 border-t border-gray-100 flex items-center justify-between">
                    <button type="button" @click="operatorSwitchModal.open = false" class="px-4 py-2 bg-white border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-100 cursor-pointer">Batal</button>
                    <button type="button" @click="submitOperatorSwitch()" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition-colors flex items-center gap-2 cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">verified_user</span>
                        <span>Konfirmasi Ganti Kasir</span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- MODAL 6: LAYAR KUNCI TERMINAL -->
    <template x-if="isTerminalLocked">
        <div class="fixed inset-0 z-[100] flex items-center justify-center p-6 bg-slate-900/95 backdrop-blur-md">
            <div class="bg-white rounded-3xl max-w-sm w-full p-8 shadow-2xl text-center space-y-5 border border-slate-700/30">
                <div class="w-16 h-16 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center mx-auto ring-8 ring-amber-50/50">
                    <span class="material-symbols-rounded text-3xl">lock</span>
                </div>

                <div>
                    <h3 class="text-lg font-black text-gray-900">Terminal Kasir Dikunci</h3>
                    <p class="text-xs text-gray-500 mt-1">Terminal diamankan sementara dari akses yang tidak berwenang.</p>
                </div>

                <div class="space-y-3">
                    <input type="password"
                           x-model="lockPinInput"
                           @keydown.enter.prevent="unlockTerminal()"
                           placeholder="Masukkan PIN Operator..."
                           autofocus
                           class="w-full text-center tracking-widest font-mono text-xl py-3 px-4 bg-gray-50 border border-gray-300 rounded-2xl focus:bg-white focus:border-[#0052cc] outline-none">
                    
                    <button type="button"
                            @click="unlockTerminal()"
                            class="w-full py-3 bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs rounded-2xl shadow-sm transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">lock_open</span>
                        <span>Buka Kunci Terminal</span>
                    </button>
                </div>
            </div>
        </div>
    </template>

</div>
@endsection
