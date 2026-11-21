@extends('layouts.app')

@section('title', 'Cetak Struk Transaksi - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    receipt: null,
    paperSize: '80mm', // '80mm' | '58mm' | 'fluid'

    formatNominal(val) {
        return new Intl.NumberFormat('id-ID').format(Math.round(Number(val) || 0));
    },

    get totalItemsQty() {
        if (!this.receipt || !Array.isArray(this.receipt.items)) return 0;
        return this.receipt.items.reduce((sum, it) => sum + (Number(it.qty) || 0), 0);
    },

    get isPiutang() {
        const m = String(this.receipt?.method || '').toUpperCase();
        const ml = String(this.receipt?.methodLabel || '').toUpperCase();
        return m.includes('KREDIT') || m.includes('PIUTANG') || ml.includes('KREDIT') || ml.includes('PIUTANG');
    },

    get paymentStatusText() {
        return this.isPiutang ? 'BELUM LUNAS (PIUTANG)' : 'LUNAS';
    },

    get formattedDateTime() {
        if (this.receipt?.createdAt) {
            const d = new Date(this.receipt.createdAt);
            if (!isNaN(d.getTime())) {
                const pad = n => String(n).padStart(2, '0');
                return `${pad(d.getDate())}-${pad(d.getMonth() + 1)}-${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
            }
        }
        const dateStr = this.receipt?.date || new Date().toLocaleDateString('id-ID');
        const timeStr = (this.receipt?.time || new Date().toLocaleTimeString('id-ID')).replace(/\s*WIB/i, '').replace(/\./g, ':');
        return `${dateStr} ${timeStr}`.trim();
    },

    async initReceipt() {
        const urlParams = new URLSearchParams(window.location.search);
        const fakturParam = urlParams.get('faktur');

        if (fakturParam) {
            // 1. Try fetching directly from PostgreSQL backend API
            try {
                const cleanId = fakturParam.replace('#', '').trim();
                const res = await fetch(`/api/pos/transactions/${encodeURIComponent(cleanId)}`);
                if (res.ok) {
                    const json = await res.json();
                    if (json.success && json.data) {
                        const d = json.data;
                        const dt = d.created_at ? new Date(d.created_at) : new Date();
                        this.receipt = {
                            id: d.invoice_number,
                            rawId: d.id,
                            createdAt: d.created_at,
                            time: dt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }).replace(/\./g, ':'),
                            date: dt.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' }).replace(/\//g, '-'),
                            terminal: d.terminal_code || '-',
                            cashier: d.cashier_name,
                            branch: d.branch || null,
                            method: d.payment_method,
                            methodLabel: d.payment_method === 'TUNAI' ? 'Uang Tunai' : (d.payment_method === 'QRIS' ? 'QRIS Dinamis' : (d.payment_method === 'KREDIT' ? 'Kredit Anggota (Piutang)' : (d.payment_method === 'CAMPURAN' ? 'Tunai + QRIS' : d.payment_method))),
                            paymentReference: d.payment_reference || null,
                            member: d.member || null,
                            items: (d.items || []).map(it => ({
                                name: it.name,
                                qty: it.qty,
                                unit: it.unit || 'Pcs',
                                price: it.unit_price,
                                total: it.total_price,
                                promo_name: it.promo_name || null,
                                promo_discount: it.promo_discount || 0
                            })),
                            subtotal: d.subtotal,
                            discount: (Number(d.discount_total) || 0) + (Number(d.promo_discount_total) || 0),
                            promoDiscountTotal: Number(d.promo_discount_total) || 0,
                            tax: d.tax_total,
                            total: d.grand_total,
                            cashGiven: d.cash_received,
                            change: d.change_returned
                        };
                        return;
                    }
                }
            } catch (e) {
                console.warn('Could not fetch receipt from API, falling back to localStorage', e);
            }

            if (window.PosDb && window.PosDb.db) {
                try {
                    const dexieTrx = await window.PosDb.db.transaction_history.get(fakturParam);
                    if (dexieTrx && dexieTrx.payload) {
                        this.receipt = dexieTrx.payload;
                        return;
                    }
                } catch (dexieErr) {}
            }

            try {
                const trxs = JSON.parse(localStorage.getItem('pos_ilp_transactions_v1') || '[]');
                const found = trxs.find(t => t.id === fakturParam || t.rawId === fakturParam || t.invoice_number === fakturParam);
                if (found) {
                    this.receipt = found;
                    return;
                }
            } catch (e) {}
        }

        // Try load latest receipt
        try {
            const rawLatest = localStorage.getItem('pos_ilp_latest_printed_receipt_v1');
            if (rawLatest) {
                this.receipt = JSON.parse(rawLatest);
                return;
            }
        } catch (e) {}

        // Try load latest transaction from database
        try {
            const res = await fetch('/api/pos/transactions?per_page=1');
            if (res.ok) {
                const json = await res.json();
                if (json.success && Array.isArray(json.data) && json.data.length > 0) {
                    const d = json.data[0];
                    this.receipt = {
                        id: d.id,
                        rawId: d.rawId || d.id,
                        time: d.time || new Date().toLocaleTimeString('id-ID'),
                        date: d.date || new Date().toLocaleDateString('id-ID'),
                        terminal: d.terminal || '-',
                        cashier: d.cashier || '-',
                        branch: d.branch || null,
                        method: d.method,
                        methodLabel: d.methodLabel || d.method,
                        paymentReference: d.paymentReference || null,
                        member: d.member || null,
                        items: (d.items || []).map(it => ({
                            name: it.name,
                            qty: it.qty,
                            unit: it.unit || 'Pcs',
                            price: it.price,
                            total: it.total || (it.price * it.qty),
                            promo_name: it.promo_name || it.promoName || null,
                            promo_discount: it.promo_discount || it.promoDiscount || 0
                        })),
                        subtotal: d.subtotal,
                        discount: d.discount || 0,
                        promoDiscountTotal: d.promoDiscountTotal || 0,
                        tax: d.tax || 0,
                        total: d.total,
                        cashGiven: d.cashGiven || d.total,
                        change: d.change || 0
                    };
                    return;
                }
            }
        } catch (e) {}

        this.receipt = null;
    },

    printStruk() {
        window.print();
    }
}"
x-init="
    initOpening();
    initReceipt();
    window.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'p') {
            e.preventDefault();
            printStruk();
        }
    });
"
class="flex flex-col min-h-screen w-full bg-[#f4f6f8] font-sans antialiased text-slate-800">

    <style>
        /* Authentic Monospaced Thermal Receipt Styling */
        @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Courier+Prime:ital,wght@0,400;0,700;1,400&display=swap');

        .thermal-receipt {
            font-family: 'JetBrains Mono', 'Courier Prime', 'Consolas', 'Courier New', monospace !important;
            font-variant-numeric: tabular-nums !important;
            letter-spacing: -0.015em;
            line-height: 1.35;
            color: #000 !important;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .thermal-receipt * {
            box-sizing: border-box;
        }

        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }
            body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            header,
            nav,
            .print-hidden {
                display: none !important;
            }
            main {
                padding: 0 !important;
                background: #ffffff !important;
                display: block !important;
            }
            .thermal-receipt {
                width: 100% !important;
                max-width: 80mm !important;
                margin: 0 auto !important;
                padding: 4mm 3mm !important;
                box-shadow: none !important;
                border: none !important;
                font-size: 11px !important;
            }
        }
    </style>

    <!-- Top Header (Hidden on print) -->
    <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 sm:px-8 shrink-0 print-hidden shadow-2xs z-20">
        <div class="flex items-center gap-3">
            <a href="{{ url('/pos') }}" class="p-2 rounded-xl text-gray-500 hover:text-black hover:bg-gray-100 transition-colors" title="Kembali ke POS">
                <span class="material-symbols-rounded text-[20px]">arrow_back</span>
            </a>
            <div>
                <span class="text-sm sm:text-base font-black text-gray-900 block leading-tight">Preview Cetak Struk Belanja</span>
                <span class="text-[11px] font-mono text-gray-500" x-text="receipt?.id ? ('No. Faktur: ' + receipt.id) : 'Memuat data struk...'"></span>
            </div>
        </div>

        <div class="flex items-center gap-2 sm:gap-3">
            <!-- Responsive Paper Size Selector -->
            <div class="hidden sm:inline-flex items-center bg-gray-100 p-1 rounded-xl border border-gray-200 text-xs font-semibold">
                <button type="button"
                        @click="paperSize = '80mm'"
                        :class="paperSize === '80mm' ? 'bg-white text-black shadow-xs font-bold' : 'text-gray-500 hover:text-black'"
                        class="px-2.5 py-1 rounded-lg transition-all cursor-pointer">
                    80mm (Standar)
                </button>
                <button type="button"
                        @click="paperSize = '58mm'"
                        :class="paperSize === '58mm' ? 'bg-white text-black shadow-xs font-bold' : 'text-gray-500 hover:text-black'"
                        class="px-2.5 py-1 rounded-lg transition-all cursor-pointer">
                    58mm (Kecil)
                </button>
                <button type="button"
                        @click="paperSize = 'fluid'"
                        :class="paperSize === 'fluid' ? 'bg-white text-black shadow-xs font-bold' : 'text-gray-500 hover:text-black'"
                        class="px-2.5 py-1 rounded-lg transition-all cursor-pointer">
                    Fluid (Responsif)
                </button>
            </div>

            <!-- Print Button -->
            <button type="button"
                    @click="printStruk()"
                    :disabled="!receipt"
                    class="px-4 py-2 rounded-xl bg-black hover:bg-neutral-800 disabled:bg-gray-300 text-white text-xs font-bold flex items-center gap-1.5 shadow-xs cursor-pointer active:scale-95 transition-all">
                <span class="material-symbols-rounded text-[18px]">print</span>
                <span>Cetak (Ctrl+P)</span>
            </button>

            <!-- Back to POS -->
            <a href="{{ url('/pos') }}" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-gray-700 text-xs font-bold flex items-center gap-1.5 transition-colors">
                <span class="material-symbols-rounded text-[18px]">point_of_sale</span>
                <span class="hidden md:inline">Kasir POS</span>
            </a>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 overflow-y-auto relative py-6 sm:py-8 px-3 sm:px-4 flex justify-center items-start print:p-0">

        <!-- If no receipt found -->
        <template x-if="!receipt">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 text-center max-w-md w-full my-8">
                <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto text-gray-400 mb-3">
                    <span class="material-symbols-rounded text-[32px]">receipt_long</span>
                </div>
                <h3 class="font-bold text-gray-900 text-sm">Belum Ada Struk Dipilih</h3>
                <p class="text-xs text-gray-500 mt-1">Silakan lakukan transaksi di kasir POS atau buka struk dari riwayat transaksi.</p>
                <div class="flex items-center justify-center gap-2.5 mt-5">
                    <a href="/pos" class="px-4 py-2 bg-black text-white font-bold text-xs rounded-xl hover:bg-neutral-800 transition-colors">
                        Buka Kasir POS
                    </a>
                    <a href="/kasir/transaksi/riwayat" class="px-4 py-2 bg-gray-100 text-gray-700 font-bold text-xs rounded-xl hover:bg-gray-200 transition-colors">
                        Riwayat Transaksi
                    </a>
                </div>
            </div>
        </template>

        <!-- Thermal Receipt Paper Card -->
        <template x-if="receipt">
            <div class="thermal-receipt bg-white text-black border border-neutral-300 shadow-md p-4 sm:p-5 flex flex-col shrink-0 relative transition-all duration-300 print:shadow-none print:border-none print:p-0"
                 :class="{
                     'w-full max-w-[380px]': paperSize === '80mm',
                     'w-full max-w-[290px] text-[11px]': paperSize === '58mm',
                     'w-full max-w-[420px]': paperSize === 'fluid'
                 }">

                <!-- ================= 1. STORE HEADER ================= -->
                <div class="text-center pb-3 border-b border-dashed border-black">
                    <h2 class="text-[18px] sm:text-[20px] font-black tracking-wider leading-none text-black uppercase">MINIMARKET ILP</h2>
                    <div class="text-[10.5px] font-bold text-black uppercase mt-1 leading-tight">
                        KOPERASI KARYAWAN PT INDO LAMPUNG PERKASA
                    </div>
                    <div class="text-[10px] text-black uppercase mt-0.5"
                         x-text="receipt?.branch || activeSession?.storeLocation || 'Toko Pusat (CAB-001)'"></div>
                    <div class="text-[9.5px] text-black leading-tight mt-0.5">
                        Gedung Utama Kopkar ILP • NPWP: 01.345.678.9-412.000
                    </div>
                </div>

                <!-- Offline Notice Tag -->
                <template x-if="receipt?.isOffline || receipt?.syncStatus === 'PENDING_SYNC'">
                    <div class="my-2 py-1 px-2 border border-dashed border-black text-center text-[10px] font-bold text-black">
                        *** STRUK TRANSAKSI OFFLINE (TERSIMPAN LOKAL) ***
                    </div>
                </template>

                <!-- ================= 2. TRANSACTION METADATA ================= -->
                <div class="py-2.5 border-b border-dashed border-black text-[11px] space-y-1">
                    <div class="flex justify-between items-baseline gap-2">
                        <span class="text-neutral-700 shrink-0">No. Faktur</span>
                        <span class="font-bold text-right truncate font-mono" x-text="receipt?.id || '-'"></span>
                    </div>
                    <div class="flex justify-between items-baseline gap-2">
                        <span class="text-neutral-700 shrink-0">Waktu</span>
                        <span class="text-right truncate font-mono" x-text="formattedDateTime"></span>
                    </div>
                    <div class="flex justify-between items-baseline gap-2">
                        <span class="text-neutral-700 shrink-0">Kasir / Term</span>
                        <span class="text-right truncate font-mono" x-text="(receipt?.cashier || activeSession?.cashierName || '-') + ' / ' + (receipt?.terminal || activeSession?.terminalLabel || '-')"></span>
                    </div>
                    <div class="flex justify-between items-baseline gap-2">
                        <span class="text-neutral-700 shrink-0">Metode Bayar</span>
                        <span class="font-bold text-right truncate uppercase font-mono" x-text="receipt?.methodLabel || receipt?.method || '-'"></span>
                    </div>
                    <template x-if="receipt?.member && (receipt?.member?.name || receipt?.member?.nik)">
                        <div class="flex justify-between items-baseline gap-2 pt-0.5 border-t border-dotted border-neutral-300">
                            <span class="text-neutral-700 shrink-0">Anggota</span>
                            <span class="font-bold text-right truncate font-mono" x-text="(receipt?.member?.name || '') + (receipt?.member?.nik ? ' (' + receipt.member.nik + ')' : '')"></span>
                        </div>
                    </template>
                </div>

                <!-- ================= 3. PURCHASED ITEMS LIST ================= -->
                <!-- 2-Line Retail Thermal Layout: 100% Responsive for any item name length -->
                <div class="py-2.5 border-b border-dashed border-black space-y-2">
                    <div class="flex justify-between text-[10px] font-bold uppercase text-neutral-600 pb-1 border-b border-dotted border-neutral-300">
                        <span>ITEM / PRODUK</span>
                        <span class="text-right">TOTAL (RP)</span>
                    </div>

                    <template x-for="(item, idx) in (receipt?.items || [])" :key="idx">
                        <div class="space-y-0.5">
                            <!-- Line 1: Item Number & Name (Full Width) -->
                            <div class="font-bold text-[11.5px] uppercase tracking-tight leading-snug break-words"
                                 x-text="(idx + 1) + '. ' + item.name"></div>

                            <!-- Line 2: Qty x Price on left, Line Total on far right -->
                            <div class="flex justify-between items-baseline text-[11px] pl-3 text-neutral-900">
                                <span class="font-mono text-neutral-800"
                                      x-text="item.qty + ' ' + (item.unit || 'Pcs') + '  x  ' + formatNominal(item.price)"></span>
                                <span class="font-bold font-mono shrink-0 ml-2"
                                      x-text="formatNominal(item.total)"></span>
                            </div>

                            <!-- Line 3: Promo / Discount Info (if any) -->
                            <template x-if="Number(item.promo_discount || item.promoDiscount) > 0">
                                <div class="flex justify-between items-baseline text-[10px] pl-3 text-neutral-800 italic">
                                    <span class="truncate pr-2" x-text="'* ' + (item.promo_name || item.promoName || 'Promo Toko')"></span>
                                    <span class="font-mono shrink-0" x-text="'-' + formatNominal(item.promo_discount || item.promoDiscount)"></span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- ================= 4. SUMMARY & FINANCIAL TOTALS ================= -->
                <div class="py-2.5 border-b border-dashed border-black text-[11.5px] space-y-1">
                    <div class="flex justify-between items-baseline">
                        <span class="text-neutral-700">Total Item</span>
                        <span class="font-mono font-bold" x-text="totalItemsQty + ' Pcs'"></span>
                    </div>

                    <div class="flex justify-between items-baseline">
                        <span class="text-neutral-700">Subtotal Belanja</span>
                        <span class="font-mono font-bold" x-text="formatNominal(receipt?.subtotal)"></span>
                    </div>

                    <!-- Discount Line -->
                    <template x-if="Number(receipt?.discount) > 0">
                        <div class="flex justify-between items-baseline text-neutral-900">
                            <span>Diskon Promo / Potongan</span>
                            <span class="font-mono font-bold" x-text="'-' + formatNominal(receipt?.discount)"></span>
                        </div>
                    </template>

                    <!-- PPN Line -->
                    <div class="flex justify-between items-baseline">
                        <span class="text-neutral-700">PPN (11%)</span>
                        <span class="font-mono font-bold" x-text="formatNominal(receipt?.tax || 0)"></span>
                    </div>

                    <!-- Divider -->
                    <div class="border-t border-dashed border-black my-1"></div>

                    <!-- Grand Total -->
                    <div class="flex justify-between items-baseline text-[13px] font-black">
                        <span>TOTAL TAGIHAN</span>
                        <span class="font-mono text-sm" x-text="'Rp ' + formatNominal(receipt?.total)"></span>
                    </div>

                    <!-- Payment Tender & Change Details -->
                    <template x-if="!isPiutang && (receipt?.method === 'TUNAI' || Number(receipt?.cashGiven) > 0)">
                        <div class="pt-1 space-y-0.5 border-t border-dotted border-neutral-300">
                            <div class="flex justify-between items-baseline">
                                <span class="text-neutral-700">Tunai</span>
                                <span class="font-mono font-bold" x-text="formatNominal(receipt?.cashGiven)"></span>
                            </div>
                            <div class="flex justify-between items-baseline">
                                <span class="text-neutral-700">Kembali</span>
                                <span class="font-mono font-bold" x-text="formatNominal(receipt?.change)"></span>
                            </div>
                        </div>
                    </template>

                    <!-- Non-Cash Payment Ref (QRIS / Transfer) -->
                    <template x-if="receipt?.paymentReference">
                        <div class="pt-1 flex justify-between items-baseline text-[10px] text-neutral-700 border-t border-dotted border-neutral-300">
                            <span>Ref Pembayaran</span>
                            <span class="font-mono font-bold truncate max-w-[180px]" x-text="receipt.paymentReference"></span>
                        </div>
                    </template>
                </div>

                <!-- ================= 5. CALLOUT BANNER: ANDA HEMAT ================= -->
                <template x-if="Number(receipt?.discount) > 0">
                    <div class="my-2 py-1.5 px-2 border-2 border-dashed border-black text-center font-black text-[11.5px] sm:text-[12px] tracking-wider uppercase font-mono">
                        *** ANDA HEMAT: Rp <span x-text="formatNominal(receipt?.discount)"></span> ***
                    </div>
                </template>

                <!-- ================= 6. PAYMENT STATUS ================= -->
                <div class="my-2 text-center">
                    <div class="flex items-center justify-center gap-2">
                        <div class="flex-1 border-t border-dashed border-black"></div>
                        <span class="text-[12px] font-black tracking-[0.25em] uppercase whitespace-nowrap"
                              x-text="paymentStatusText"></span>
                        <div class="flex-1 border-t border-dashed border-black"></div>
                    </div>
                    <div class="border-t border-dashed border-black mt-1"></div>
                </div>

                <!-- ================= 7. FOOTER NOTES & CONTACT ================= -->
                <div class="pt-2 text-center text-[10px] text-neutral-700 space-y-0.5 leading-tight">
                    <div>*Harga barang sudah sesuai ketentuan PPN (11%)</div>
                    <div class="font-bold pt-1 text-black">TERIMA KASIH TELAH BERBELANJA</div>
                    <div>Layanan Konsumen &amp; Pengaduan: 0800-1-ILPPOS</div>
                    <div>Email: info@kopkarilp.co.id</div>
                    <div class="pt-1 text-[9px] text-neutral-500 font-mono" x-text="'Dicetak: ' + formattedDateTime"></div>
                </div>

            </div>
        </template>

    </main>
</div>
@endsection
