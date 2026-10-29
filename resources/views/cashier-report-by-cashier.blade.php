@extends('layouts.app')

@section('title', 'Penjualan per Kasir - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    activeCashierProfile: {
        id: 'USR-02',
        name: 'Budi Kurniawan',
        username: 'budi_kasir01',
        nik: '202409821002',
        role: 'Kasir Utama',
        terminal: 'Kasir 01 (TERM-01)',
        shift: 'Pagi (07:00 - 15:00 WIB)',
        activeSince: '07:00 WIB'
    },

    myTransactions: [],

    initMyReport() {
        if (this.activeOperator) {
            this.activeCashierProfile.id = this.activeOperator.id;
            this.activeCashierProfile.name = this.activeOperator.name;
            this.activeCashierProfile.username = this.activeOperator.username;
            this.activeCashierProfile.nik = this.activeOperator.nik;
            this.activeCashierProfile.role = this.activeOperator.role || 'Kasir Operasional';
        }
        const raw = localStorage.getItem('pos_ilp_transactions_v1');
        if (raw) {
            try {
                const trxs = JSON.parse(raw);
                if (trxs && trxs.length > 0) {
                    const myName = (this.activeOperator ? this.activeOperator.name : (this.activeSession.cashierName || 'Budi Kurniawan')).toLowerCase();
                    const filtered = trxs.filter(t => !t.cashier || t.cashier.toLowerCase() === myName);
                    this.myTransactions = (filtered.length > 0 ? filtered : trxs).map(t => ({
                        id: t.id || t.trxId || '#TRX-001',
                        time: t.time || '11:42 WIB',
                        method: t.method || (t.paymentMethod || 'TUNAI'),
                        total: Number(t.total || 0),
                        discount: Number(t.discount || 0),
                        itemCount: t.items ? t.items.length : (t.itemCount || 1),
                        status: t.status || 'SELESAI'
                    }));
                    return;
                }
            } catch(e) {}
        }
        this.myTransactions = [
            { id: '#TRX-20261024-0028', time: '11:42 WIB', method: 'CAMPURAN', total: 145000, discount: 1500, itemCount: 5, status: 'SELESAI' },
            { id: '#TRX-20261024-0027', time: '11:20 WIB', method: 'TUNAI', total: 84000, discount: 0, itemCount: 4, status: 'SELESAI' },
            { id: '#TRX-20261024-0026', time: '10:55 WIB', method: 'QRIS', total: 245500, discount: 5000, itemCount: 8, status: 'SELESAI' },
            { id: '#TRX-20261024-0022', time: '07:30 WIB', method: 'TUNAI', total: 310000, discount: 0, itemCount: 10, status: 'SELESAI' },
            { id: '#TRX-20261024-0019', time: '07:15 WIB', method: 'KREDIT ANGGOTA', total: 195000, discount: 2500, itemCount: 6, status: 'SELESAI' }
        ];
    },

    productCategoryShare: [
        { category: 'Sembako & Kebutuhan Pokok', sales: 485000, percentage: 49.5, color: 'bg-blue-600' },
        { category: 'Minuman Kemasan', sales: 215000, percentage: 22.0, color: 'bg-emerald-500' },
        { category: 'Makanan Ringan & Biskuit', sales: 164500, percentage: 16.8, color: 'bg-amber-500' },
        { category: 'Perawatan & Toiletries', sales: 85000, percentage: 8.7, color: 'bg-purple-500' },
        { category: 'Kupon Layanan', sales: 30000, percentage: 3.0, color: 'bg-rose-500' }
    ],

    refundActivities: [
        { refundNo: 'REF-20261024-001', trxId: '#TRX-20261024-0012', amount: 35000, reason: 'Produk Cacat Segel', time: '08:50 WIB', status: 'DISETUJUI_SPV' }
    ],

    get totalMySales() {
        return this.myTransactions.filter(t => t.status === 'SELESAI').reduce((sum, t) => sum + t.total, 0);
    },

    get totalMyTrxCount() {
        return this.myTransactions.filter(t => t.status === 'SELESAI').length;
    },

    get avgBasketSize() {
        return this.totalMyTrxCount > 0 ? Math.round(this.totalMySales / this.totalMyTrxCount) : 0;
    },

    get totalDiscountGiven() {
        return this.myTransactions.reduce((sum, t) => sum + (t.discount || 0), 0);
    },

    get totalRefundAmount() {
        return this.refundActivities.reduce((sum, r) => sum + r.amount, 0);
    }
}"
x-init="initOpening(); initMyReport();"
@pos-session-sync.window="initOpening()"
class="flex flex-col h-screen w-full font-sans">
    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar -->
        <x-cashier-sidebar active="laporan_kasir" />

        <!-- Main Area -->
        <div class="flex-1 flex flex-col min-w-0 bg-[#fafafa]">
            <!-- Header -->
            <x-cashier-header title="Penjualan per Kasir" />

            <!-- Content -->
            <main class="flex-1 overflow-y-auto p-6 md:p-8 bg-[#f8f9fb]">

                <!-- Page Top Action Bar -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Kinerja Penjualan Pribadi Kasir</h1>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-emerald-100 text-emerald-800 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                                Kasir Login
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">Ringkasan performa penjualan khusus kasir yang sedang bertugas.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="window.print()"
                                class="px-4 py-2 bg-[#0052cc] hover:bg-blue-700 text-white rounded-xl text-xs font-bold flex items-center gap-2 transition-colors shadow-2xs cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">print</span>
                            <span>Cetak Laporan Kasir</span>
                        </button>
                    </div>
                </div>

                <!-- Info Banner: Permission Kasir Login Only -->
                <div class="bg-blue-50/70 border border-blue-200/80 rounded-2xl p-4 mb-6 flex items-start sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5">
                        <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center font-black text-sm shrink-0 shadow-xs">
                            BK
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-bold text-gray-900" x-text="activeCashierProfile.name"></h3>
                                <span class="text-[10px] font-mono bg-blue-100 text-blue-800 px-2 py-0.5 rounded font-bold" x-text="activeCashierProfile.nik"></span>
                                <span class="text-[11px] text-gray-500 font-semibold">• <span x-text="activeCashierProfile.terminal"></span></span>
                            </div>
                            <p class="text-xs text-gray-600 mt-0.5">
                                Shift: <strong class="text-gray-800" x-text="activeCashierProfile.shift"></strong> | Bertugas sejak: <span class="font-mono text-gray-700" x-text="activeCashierProfile.activeSince"></span>
                            </p>
                        </div>
                    </div>
                    <div class="hidden lg:flex items-center gap-2 text-[11px] text-gray-500 bg-white/80 px-3 py-1.5 rounded-xl border border-blue-100 font-medium">
                        <span class="material-symbols-rounded text-blue-600 text-[16px]">lock</span>
                        <span>Khusus akun kasir login</span>
                    </div>
                </div>

                <!-- KPI Cards for Current Cashier -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                    <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-xs">
                        <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-wider">Omzet Pribadi</span>
                        <h2 class="text-2xl font-black text-[#0052cc] mt-1.5 font-mono" x-text="formatRupiah(totalMySales)">Rp 0</h2>
                        <span class="text-xs font-bold text-emerald-600 mt-1 block">Hari Ini</span>
                    </div>

                    <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-xs">
                        <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-wider">Transaksi Selesai</span>
                        <h2 class="text-2xl font-black text-gray-900 mt-1.5 font-mono" x-text="totalMyTrxCount">0</h2>
                        <span class="text-xs font-bold text-gray-500 mt-1 block">Struk Tercetak</span>
                    </div>

                    <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-xs">
                        <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-wider">Rata-rata Keranjang</span>
                        <h2 class="text-2xl font-black text-gray-800 mt-1.5 font-mono" x-text="formatRupiah(avgBasketSize)">Rp 0</h2>
                        <span class="text-xs font-bold text-gray-400 mt-1 block">Per Transaksi</span>
                    </div>

                    <div class="bg-white rounded-xl p-5 border border-amber-200 shadow-xs">
                        <span class="text-[11px] font-extrabold text-amber-700 uppercase tracking-wider">Diskon Diberikan</span>
                        <h2 class="text-2xl font-black text-amber-700 mt-1.5 font-mono" x-text="formatRupiah(totalDiscountGiven)">Rp 0</h2>
                        <span class="text-xs font-bold text-amber-600 mt-1 block">Potongan Kupon & Promo</span>
                    </div>

                    <div class="bg-white rounded-xl p-5 border border-rose-200 shadow-xs">
                        <span class="text-[11px] font-extrabold text-rose-700 uppercase tracking-wider">Refund Diproses</span>
                        <h2 class="text-2xl font-black text-rose-600 mt-1.5 font-mono" x-text="formatRupiah(totalRefundAmount)">Rp 0</h2>
                        <span class="text-xs font-bold text-rose-500 mt-1 block" x-text="refundActivities.length + ' Dokumen Refund'"></span>
                    </div>
                </div>

                <!-- 2-Columns: Category Breakdown & Payment Breakdown -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Category Share -->
                    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-xs">
                        <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-4 flex items-center justify-between">
                            <span>Kontribusi Kategori Produk</span>
                            <span class="material-symbols-rounded text-gray-400 text-[18px]">pie_chart</span>
                        </h3>
                        <div class="space-y-3.5">
                            <template x-for="(cat, idx) in productCategoryShare" :key="idx">
                                <div>
                                    <div class="flex justify-between items-center text-xs mb-1">
                                        <span class="font-bold text-gray-700" x-text="cat.category"></span>
                                        <span class="font-mono font-bold text-gray-900" x-text="formatRupiah(cat.sales)"></span>
                                    </div>
                                    <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden flex">
                                        <div :class="cat.color" :style="'width: ' + cat.percentage + '%'" class="h-full rounded-full transition-all duration-500"></div>
                                    </div>
                                    <div class="text-[10px] text-gray-400 text-right mt-0.5" x-text="cat.percentage + '% dari total omzet'"></div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Payment Breakdown & Refund History -->
                    <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                        <div>
                            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-4 flex items-center justify-between">
                                <span>Distribusi Metode Pembayaran</span>
                                <span class="material-symbols-rounded text-gray-400 text-[18px]">payments</span>
                            </h3>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                                <div class="p-3 bg-emerald-50/60 border border-emerald-200 rounded-xl">
                                    <span class="text-[10px] font-bold text-emerald-800 uppercase block">Tunai</span>
                                    <span class="text-base font-black font-mono text-emerald-700 mt-1 block">Rp 394.000</span>
                                    <span class="text-[10px] text-emerald-600 mt-0.5 block">2 Transaksi</span>
                                </div>
                                <div class="p-3 bg-blue-50/60 border border-blue-200 rounded-xl">
                                    <span class="text-[10px] font-bold text-blue-800 uppercase block">QRIS / EDC</span>
                                    <span class="text-base font-black font-mono text-[#0052cc] mt-1 block">Rp 245.500</span>
                                    <span class="text-[10px] text-blue-600 mt-0.5 block">1 Transaksi</span>
                                </div>
                                <div class="p-3 bg-purple-50/60 border border-purple-200 rounded-xl">
                                    <span class="text-[10px] font-bold text-purple-800 uppercase block">Campuran</span>
                                    <span class="text-base font-black font-mono text-purple-700 mt-1 block">Rp 145.000</span>
                                    <span class="text-[10px] text-purple-600 mt-0.5 block">1 Transaksi</span>
                                </div>
                                <div class="p-3 bg-orange-50/60 border border-orange-200 rounded-xl">
                                    <span class="text-[10px] font-bold text-orange-800 uppercase block">Kredit Anggota</span>
                                    <span class="text-base font-black font-mono text-orange-700 mt-1 block">Rp 195.000</span>
                                    <span class="text-[10px] text-orange-600 mt-0.5 block">1 Transaksi</span>
                                </div>
                            </div>

                            <!-- Refund log by this cashier -->
                            <h4 class="text-[11px] font-bold text-gray-700 uppercase tracking-wider mb-2">Riwayat Refund Ditangani Kasir Ini:</h4>
                            <div class="border border-gray-100 rounded-xl overflow-hidden bg-white text-xs">
                                <div class="overflow-x-auto min-w-0 w-full">
                                    <table class="w-full text-left text-xs min-w-[650px] border-collapse">
                                        <thead class="bg-gray-50/80 border-b border-gray-200 text-gray-600 text-[10px] font-bold uppercase tracking-wider whitespace-nowrap">
                                            <tr>
                                                <th class="px-3.5 py-2.5 text-left w-[140px]">No. Dokumen</th>
                                                <th class="px-3.5 py-2.5 text-left w-[140px]">Faktur Asal</th>
                                                <th class="px-3.5 py-2.5 text-left w-[180px]">Alasan</th>
                                                <th class="px-3.5 py-2.5 text-right w-[110px]">Nilai Refund</th>
                                                <th class="px-3.5 py-2.5 text-center w-[110px]">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <template x-for="(rf, idx) in refundActivities" :key="idx">
                                                <tr class="hover:bg-blue-50/30 transition-colors">
                                                    <td class="px-3.5 py-2.5 font-mono font-bold text-gray-800 whitespace-nowrap" x-text="rf.refundNo"></td>
                                                    <td class="px-3.5 py-2.5 font-mono text-blue-600 whitespace-nowrap" x-text="rf.trxId"></td>
                                                    <td class="px-3.5 py-2.5 text-gray-600" x-text="rf.reason"></td>
                                                    <td class="px-3.5 py-2.5 font-mono font-bold text-rose-600 text-right whitespace-nowrap" x-text="formatRupiah(rf.amount)"></td>
                                                    <td class="px-3.5 py-2.5 text-center whitespace-nowrap">
                                                        <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded text-[10px] font-bold">Disetujui SPV</span>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                            <span>Total porsi kas fisik yang harus dipertanggungjawabkan pada saat tutup sesi:</span>
                            <span class="font-mono font-black text-gray-900 text-sm">Rp 494.000</span>
                        </div>
                    </div>
                </div>

                <!-- My Transactions List -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-xs overflow-hidden">
                    <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <h3 class="text-xs font-black text-gray-900 uppercase tracking-wider">Log Transaksi Kasir Budi Kurniawan (Hari Ini)</h3>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-[#0052cc]" x-text="myTransactions.length + ' Transaksi'"></span>
                        </div>
                        <a href="{{ url('/kasir/transaksi/riwayat') }}" class="text-xs font-bold text-[#0052cc] hover:underline flex items-center gap-1">
                            <span>Lihat di Riwayat Transaksi</span>
                            <span class="material-symbols-rounded text-[16px]">arrow_forward</span>
                        </a>
                    </div>
                    <div class="overflow-x-auto min-w-0 w-full">
                        <table class="w-full text-left border-collapse text-xs min-w-[1000px]">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 text-[11px] font-bold uppercase tracking-wider whitespace-nowrap">
                                    <th class="px-5 py-3 text-left w-[140px]">No. Faktur</th>
                                    <th class="px-5 py-3 text-left w-[130px]">Waktu</th>
                                    <th class="px-5 py-3 text-left w-[140px]">Metode Bayar</th>
                                    <th class="px-5 py-3 text-center w-[120px]">Jumlah Item</th>
                                    <th class="px-5 py-3 text-right w-[110px]">Diskon</th>
                                    <th class="px-5 py-3 text-right w-[140px]">Total Bayar</th>
                                    <th class="px-5 py-3 text-center w-[110px]">Status</th>
                                    <th class="px-5 py-3 text-center w-[100px]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="trx in myTransactions" :key="trx.id">
                                    <tr class="hover:bg-blue-50/30 transition-colors">
                                        <td class="px-5 py-3 font-mono font-bold text-[#0052cc] whitespace-nowrap" x-text="trx.id"></td>
                                        <td class="px-5 py-3 text-gray-600 font-mono whitespace-nowrap" x-text="trx.time"></td>
                                        <td class="px-5 py-3 whitespace-nowrap">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold"
                                                  :class="{
                                                      'bg-emerald-50 text-emerald-700 border border-emerald-200': trx.method === 'TUNAI',
                                                      'bg-blue-50 text-[#0052cc] border border-blue-200': trx.method === 'QRIS',
                                                      'bg-orange-50 text-orange-700 border border-orange-200': trx.method === 'KREDIT ANGGOTA',
                                                      'bg-purple-50 text-purple-700 border border-purple-200': trx.method === 'CAMPURAN'
                                                  }"
                                                  x-text="trx.method"></span>
                                        </td>
                                        <td class="px-5 py-3 text-center font-bold text-gray-700 whitespace-nowrap" x-text="trx.itemCount + ' item'"></td>
                                        <td class="px-5 py-3 text-right font-mono text-amber-600 font-bold whitespace-nowrap" x-text="trx.discount > 0 ? formatRupiah(trx.discount) : '-'"></td>
                                        <td class="px-5 py-3 text-right font-mono font-black text-gray-900 whitespace-nowrap" x-text="formatRupiah(trx.total)"></td>
                                        <td class="px-5 py-3 text-center whitespace-nowrap">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-green-50 text-green-700 border border-green-200" x-text="trx.status"></span>
                                        </td>
                                        <td class="px-5 py-3 text-center whitespace-nowrap">
                                            <a href="{{ url('/cetak-struk') }}"
                                               title="Cetak Struk"
                                               class="p-1 rounded-lg text-[#0052cc] hover:text-blue-800 hover:bg-blue-50 transition-colors inline-block">
                                                <span class="material-symbols-rounded text-[18px]">print</span>
                                            </a>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

            </main>
        </div>
    </div>
</div>
@endsection
