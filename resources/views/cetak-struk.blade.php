@extends('layouts.app')

@section('title', 'Cetak Struk Transaksi - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    receipt: null,

    initReceipt() {
        const urlParams = new URLSearchParams(window.location.search);
        const fakturParam = urlParams.get('faktur');

        if (fakturParam) {
            try {
                const trxs = JSON.parse(localStorage.getItem('pos_ilp_transactions_v1') || '[]');
                const found = trxs.find(t => t.id === fakturParam || t.rawId === fakturParam);
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

        // Fallback default sample receipt
        this.receipt = {
            id: '#TRX-99201',
            rawId: 'TRX-99201',
            time: '11:42 WIB',
            date: '24 Oktober 2026',
            terminal: 'Kasir 01',
            cashier: 'Budi Kurniawan',
            method: 'TUNAI',
            methodLabel: 'Uang Tunai',
            items: [
                { name: 'Indomie Goreng Spcl', qty: 3, unit: 'Pcs', price: 3200, total: 9600 },
                { name: 'Oreo Vanilla 133g', qty: 1, unit: 'Pcs', price: 8200, total: 8200 },
                { name: 'Teh Pucuk 350ml', qty: 2, unit: 'Botol', price: 4000, total: 8000 }
            ],
            subtotal: 25800,
            discount: 1300,
            tax: 2838,
            total: 28638,
            cashGiven: 50000,
            change: 21362
        };
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
            <span class="text-xs font-mono font-bold text-[#0052cc] bg-blue-50 px-2.5 py-0.5 rounded-full" x-text="receipt?.id"></span>
        </div>

        <div class="flex items-center gap-2">
            <button type="button"
                    @click="printStruk()"
                    class="px-4 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-xs font-bold flex items-center gap-1.5 shadow-xs cursor-pointer active:scale-95">
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
    <main class="flex-1 overflow-y-auto bg-slate-50 relative py-8 px-4 flex justify-center items-start print:p-0 print:bg-white">

        <!-- Receipt Thermal Card (80mm / 400px) -->
        <div class="bg-white w-full max-w-[400px] rounded-2xl shadow-xl border border-gray-200 p-8 flex flex-col shrink-0 relative print:shadow-none print:border-none print:p-2 print:max-w-none">

            <!-- Receipt Header -->
            <div class="text-center mb-4">
                <h2 class="text-2xl font-black text-[#0052cc] tracking-tight mb-0.5">MINIMARKET ILP</h2>
                <p class="text-[13px] font-bold text-gray-700" x-text="activeSession.storeLocation || 'Minimarket Kopkar - Sektor A'"></p>
                <p class="text-[11px] font-medium text-gray-400">Koperasi Karyawan ILP • Gedung Utama</p>
                <p class="text-[10px] text-gray-400 font-mono">NPWP: 01.345.678.9-412.000</p>
            </div>

            <!-- Separator -->
            <div class="border-t border-dashed border-gray-300 my-3"></div>

            <!-- Transaction Meta -->
            <div class="flex flex-col gap-1 text-xs font-mono mb-2">
                <div class="flex justify-between">
                    <span class="text-gray-500">No. Transaksi:</span>
                    <span class="text-gray-900 font-bold" x-text="receipt?.id"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Sesi Kasir:</span>
                    <span class="text-gray-800 font-bold" x-text="activeSession.sessionId"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Terminal:</span>
                    <span class="text-gray-800 font-bold" x-text="receipt?.terminal || activeSession.terminalLabel"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Kasir:</span>
                    <span class="text-gray-800 font-bold" x-text="receipt?.cashier || activeSession.cashierName"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Waktu:</span>
                    <span class="text-gray-800 font-bold" x-text="(receipt?.date || 'Hari Ini') + ' • ' + (receipt?.time || '')"></span>
                </div>
            </div>

            <!-- Separator -->
            <div class="border-t border-dashed border-gray-300 my-3"></div>

            <!-- Purchased Items -->
            <div class="flex flex-col gap-2.5 text-xs font-mono">
                <template x-for="item in (receipt?.items || [])" :key="item.name">
                    <div class="flex flex-col">
                        <div class="flex justify-between items-start">
                            <span class="font-bold text-gray-900" x-text="item.name"></span>
                            <span class="font-bold text-gray-900" x-text="formatRupiah(item.total)"></span>
                        </div>
                        <span class="text-[11px] text-gray-500 font-medium" x-text="item.qty + ' ' + (item.unit || 'Pcs') + ' x ' + formatRupiah(item.price)"></span>
                    </div>
                </template>
            </div>

            <!-- Separator -->
            <div class="border-t border-dashed border-gray-300 my-3"></div>

            <!-- Totals & Taxes -->
            <div class="flex flex-col gap-1.5 text-xs font-mono mb-3">
                <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">Subtotal:</span>
                    <span class="text-gray-900 font-bold" x-text="formatRupiah(receipt?.subtotal)"></span>
                </div>
                <template x-if="receipt?.discount > 0">
                    <div class="flex justify-between text-emerald-600 font-bold">
                        <span>Diskon / Potongan:</span>
                        <span x-text="'-' + formatRupiah(receipt?.discount)"></span>
                    </div>
                </template>
                <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">Pajak (PPN 11%):</span>
                    <span class="text-gray-900 font-bold" x-text="formatRupiah(receipt?.tax)"></span>
                </div>
            </div>

            <!-- Grand Total -->
            <div class="flex justify-between items-center py-2.5 border-y-2 border-gray-900 my-1 font-mono">
                <span class="text-base font-black text-gray-900 tracking-wide uppercase">TOTAL AKHIR</span>
                <span class="text-lg font-black text-gray-900" x-text="formatRupiah(receipt?.total)"></span>
            </div>

            <!-- Payment Breakdown -->
            <div class="bg-slate-50 rounded-xl p-3 flex flex-col gap-1.5 text-xs font-mono border border-gray-200 mt-2">
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">Metode Bayar:</span>
                    <span class="font-bold text-gray-900" x-text="receipt?.methodLabel || receipt?.method"></span>
                </div>
                <template x-if="receipt?.method === 'TUNAI' || receipt?.cashGiven > 0">
                    <div class="space-y-1 pt-1 border-t border-gray-200 border-dashed">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tunai Diterima:</span>
                            <span class="font-bold text-gray-900" x-text="formatRupiah(receipt?.cashGiven)"></span>
                        </div>
                        <div class="flex justify-between text-emerald-700 font-bold">
                            <span>Kembalian:</span>
                            <span x-text="formatRupiah(receipt?.change)"></span>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Separator -->
            <div class="border-t border-dashed border-gray-300 my-4"></div>

            <!-- QR Verification & Barcode -->
            <div class="flex flex-col items-center mb-3">
                <div class="border border-gray-300 rounded-xl p-2 mb-1.5 bg-white">
                    <span class="material-symbols-rounded text-6xl text-gray-800">qr_code_2</span>
                </div>
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Validasi Struk Digital POS</span>
                <span class="text-[9px] font-mono text-gray-400" x-text="receipt?.rawId"></span>
            </div>

            <!-- Footer Message -->
            <div class="text-center pt-2 border-t border-gray-100 text-[11px] text-gray-600 space-y-0.5">
                <p class="font-bold">Terima Kasih Telah Berbelanja!</p>
                <p class="text-[10px] text-gray-500">Barang yang sudah dibeli dapat ditukar maksimal 1x24 jam dengan membawa struk ini.</p>
                <p class="text-[9px] text-gray-400 font-mono pt-1">Call Center: 0800-1-ILPPOS • info@kopkarilp.co.id</p>
            </div>

        </div>

    </main>
</div>
@endsection
