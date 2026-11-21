@extends('layouts.app')

@section('title', 'Cetak Struk Transaksi - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    receipt: null,

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
        return this.isPiutang ? 'BELUM LUNAS' : 'LUNAS';
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
                            member: d.member || null,
                            items: (d.items || []).map(it => ({
                                name: it.name,
                                qty: it.qty,
                                unit: it.unit || 'Pcs',
                                price: it.unit_price,
                                total: it.total_price
                            })),
                            subtotal: d.subtotal,
                            discount: d.discount_total,
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
                        method: d.method,
                        methodLabel: d.methodLabel || d.method,
                        member: d.member || null,
                        items: (d.items || []).map(it => ({
                            name: it.name,
                            qty: it.qty,
                            unit: it.unit || 'Pcs',
                            price: it.price,
                            total: it.total || (it.price * it.qty)
                        })),
                        subtotal: d.subtotal,
                        discount: d.discount || 0,
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
x-init="initOpening(); initReceipt();"
class="flex flex-col min-h-screen w-full bg-[#f8f9fa] font-sans">

    <!-- Top Header (Hidden on print) -->
    <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0 print:hidden">
        <div class="flex items-center gap-3">
            <span class="text-base font-black text-gray-900">Preview Cetak Struk Belanja</span>
            <span class="text-xs font-mono font-bold text-black bg-gray-100 border border-gray-300 px-2.5 py-0.5 rounded-full" x-text="receipt?.id"></span>
        </div>

        <div class="flex items-center gap-2">
            <button type="button"
                    @click="printStruk()"
                    class="px-4 py-2 rounded-xl bg-black hover:bg-neutral-800 text-white text-xs font-bold flex items-center gap-1.5 shadow-xs cursor-pointer active:scale-95">
                <span class="material-symbols-rounded text-[18px]">print</span>
                <span>Cetak Struk (Ctrl+P)</span>
            </button>

            <a href="{{ url('/pos') }}" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-gray-700 text-xs font-bold flex items-center gap-1.5 transition-colors">
                <span class="material-symbols-rounded text-[18px]">point_of_sale</span>
                <span>Kembali ke Kasir POS</span>
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto bg-slate-100 relative py-8 px-4 flex justify-center items-start print:p-0 print:bg-white">

        <!-- If no receipt -->
        <template x-if="!receipt">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 text-center max-w-md w-full">
                <span class="material-symbols-rounded text-4xl text-black mb-2">receipt_long</span>
                <h3 class="font-bold text-black text-sm">Belum Ada Struk Dipilih</h3>
                <p class="text-xs text-black mt-1">Silakan lakukan transaksi di terminal kasir atau pilih dari riwayat transaksi.</p>
                <div class="flex items-center justify-center gap-3 mt-4">
                    <a href="/pos" class="px-4 py-2 bg-black text-white font-bold text-xs rounded-xl">Ke Kasir POS</a>
                    <a href="/kasir/transaksi/riwayat" class="px-4 py-2 bg-gray-100 text-black font-bold text-xs rounded-xl">Riwayat Transaksi</a>
                </div>
            </div>
        </template>

        <!-- Receipt Thermal Card (80mm / 380px) - Pure Black & White Only (Alfamart Reference Style) -->
        <template x-if="receipt">
            <div class="bg-white text-black w-full max-w-[380px] shadow-md border border-black/20 p-6 flex flex-col shrink-0 relative font-mono text-[12px] leading-snug print:shadow-none print:border-none print:p-2 print:max-w-none">

                <!-- Brand Logo Header (Alfamart Style Underline Bars in Pure Black) -->
                <div class="flex flex-col items-center justify-center mb-4">
                    <div class="inline-block text-center">
                        <h2 class="text-[22px] font-black italic tracking-tight text-black leading-none px-2">MINIMARKET ILP</h2>
                        <div class="mt-1.5 border-t-[3px] border-black"></div>
                        <div class="mt-0.5 border-t-[1.5px] border-black"></div>
                    </div>
                </div>

                <!-- Offline Mode Tag on Receipt -->
                <template x-if="receipt?.isOffline || receipt?.syncStatus === 'PENDING_SYNC'">
                    <div class="mb-3 py-1 px-2 border border-dashed border-black text-center text-[10px] font-bold text-black">
                        *** STRUK PENJUALAN OFFLINE (TERSIMPAN LOKAL) ***
                    </div>
                </template>

                <!-- Top Info Rows -->
                <div class="text-[11px] text-black">
                    <div class="flex justify-between items-start py-1.5 border-b border-dashed border-black">
                        <span>Sesi Kasir :</span>
                        <span class="text-right font-semibold" x-text="activeSession?.sessionId || '-'"></span>
                    </div>
                    <div class="flex justify-between items-start py-1.5 border-b border-dashed border-black">
                        <span>Kasir / Terminal :</span>
                        <div class="text-right">
                            <div class="font-semibold" x-text="(receipt?.cashier || activeSession?.cashierName || '-') + ' / ' + (receipt?.terminal || activeSession?.terminalLabel || '-')"></div>
                            <div x-text="(receipt?.date || '') + ' ' + (receipt?.time || '')"></div>
                        </div>
                    </div>
                    <div class="flex justify-between items-center py-1.5">
                        <span>Metode Bayar :</span>
                        <span class="font-bold text-right" x-text="receipt?.methodLabel || receipt?.method || '-'"></span>
                    </div>
                    <template x-if="receipt?.member && receipt?.member?.name">
                        <div class="flex justify-between items-center py-1 border-t border-dashed border-black">
                            <span>Anggota :</span>
                            <span class="font-bold text-right" x-text="receipt.member.name + (receipt.member.nik ? ' (' + receipt.member.nik + ')' : '')"></span>
                        </div>
                    </template>
                </div>

                <!-- Centered Store Identity Block (Like Alfamart Branch Block) -->
                <div class="text-center text-[11px] text-black my-3 space-y-0.5 leading-tight uppercase">
                    <div class="font-bold" x-text="receipt?.branch || activeSession?.storeLocation || 'CABANG UTAMA'"></div>
                    <div>KOPERASI KARYAWAN PT INDO LAMPUNG PERKASA</div>
                    <div>GEDUNG UTAMA • MINIMARKET KOPKAR ILP</div>
                    <div>NPWP: 01.345.678.9-412.000</div>
                </div>

                <!-- Ref. Transaction Number -->
                <div class="text-[11.5px] font-bold text-black pb-2 border-b border-dashed border-black">
                    <span x-text="'Ref. ' + (receipt?.id || '-')"></span>
                </div>

                <!-- Purchased Items (Alfamart 4-Column Layout: Name+Unit | Qty | Unit Price | Line Total) -->
                <div class="py-3 flex flex-col gap-2.5 text-[11.5px] text-black border-b border-dashed border-black">
                    <template x-for="(item, idx) in (receipt?.items || [])" :key="idx">
                        <div class="grid grid-cols-12 gap-1 items-center">
                            <div class="col-span-6 pr-1 leading-tight">
                                <div class="font-semibold text-black break-words" x-text="item.name"></div>
                                <div class="text-[10.5px] text-black" x-text="item.unit || 'Pcs'"></div>
                            </div>
                            <div class="col-span-1 text-right font-semibold text-black" x-text="item.qty"></div>
                            <div class="col-span-2 text-right text-black" x-text="formatNominal(item.price)"></div>
                            <div class="col-span-3 text-right font-semibold text-black" x-text="formatNominal(item.total)"></div>
                        </div>
                    </template>
                </div>

                <!-- Summary Totals Section -->
                <div class="py-2.5 flex flex-col gap-1 text-[11.5px] text-black">
                    <div class="grid grid-cols-12 gap-1 items-center">
                        <span class="col-span-6">Subtotal</span>
                        <span class="col-span-1 text-right font-semibold" x-text="totalItemsQty"></span>
                        <span class="col-span-2"></span>
                        <span class="col-span-3 text-right font-semibold" x-text="formatNominal(receipt?.subtotal)"></span>
                    </div>

                    <template x-if="Number(receipt?.discount) > 0">
                        <div class="flex justify-between items-center">
                            <span>Diskon / Potongan</span>
                            <span x-text="'-' + formatNominal(receipt?.discount)"></span>
                        </div>
                    </template>

                    <div class="flex justify-between items-center">
                        <span>PPN (11%)</span>
                        <span x-text="formatNominal(receipt?.tax || 0)"></span>
                    </div>

                    <div class="flex justify-between items-center font-bold text-[12.5px] pt-0.5">
                        <span>Total</span>
                        <span x-text="formatNominal(receipt?.total)"></span>
                    </div>

                    <template x-if="!isPiutang && (receipt?.method === 'TUNAI' || Number(receipt?.cashGiven) > 0)">
                        <div class="pt-1 space-y-0.5">
                            <div class="flex justify-between items-center">
                                <span>Tunai</span>
                                <span x-text="formatNominal(receipt?.cashGiven)"></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span>Kembali</span>
                                <span x-text="formatNominal(receipt?.change)"></span>
                            </div>
                        </div>
                    </template>

                    <div class="pt-1.5 text-[10.5px] text-black">
                        *Harga yang tertera sudah sesuai ketentuan PPN
                    </div>
                </div>

                <!-- Payment Status Banner: LUNAS or BELUM LUNAS (Alfamart Double-Dashed Style) -->
                <div class="my-2">
                    <div class="flex items-center justify-center gap-3">
                        <div class="flex-1 border-t border-dashed border-black"></div>
                        <span class="text-[12px] font-bold tracking-[0.25em] text-black uppercase whitespace-nowrap"
                              x-text="paymentStatusText"></span>
                        <div class="flex-1 border-t border-dashed border-black"></div>
                    </div>
                    <div class="border-t border-dashed border-black mt-1.5"></div>
                </div>

                <!-- Transaction Timestamp Block -->
                <div class="py-2 text-center text-[11px] text-black border-b border-dashed border-black">
                    <span x-text="'Tgl. ' + formattedDateTime"></span>
                </div>

                <!-- Footer Contact & Customer Care -->
                <div class="pt-3 text-center text-[11px] text-black space-y-0.5 leading-snug">
                    <div>Terima Kasih Telah Berbelanja</div>
                    <div>Kritik &amp; Saran: 0800-1-ILPPOS</div>
                    <div>Email: info@kopkarilp.co.id</div>
                </div>

            </div>
        </template>

    </main>
</div>
@endsection
