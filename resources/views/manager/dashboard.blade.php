@extends('layouts.app')

@section('title', 'Dashboard Manager - ILP POS')

@section('content')
<div x-data="{
    salesToday: 0,
    trxCountToday: 0,
    lowStockCount: 0,
    lowStockItems: [],
    cashierList: [],
    showQuickActionModal: false,
    toastMessage: '',
    showToast: false,

    initDashboard() {
        // 1. Compute transactions today
        const rawTrx = localStorage.getItem('pos_ilp_transactions_v1');
        if (rawTrx) {
            try {
                const trxs = JSON.parse(rawTrx);
                this.trxCountToday = trxs.length;
                this.salesToday = trxs.reduce((sum, t) => sum + (Number(t.total) || 0), 0);
            } catch(e){}
        } else {
            this.salesToday = 12450000;
            this.trxCountToday = 184;
        }

        // 2. Compute low stock items from pos_ilp_inventory_products_v1
        const rawProds = localStorage.getItem('pos_ilp_inventory_products_v1');
        if (rawProds) {
            try {
                const prods = JSON.parse(rawProds);
                const critical = prods.filter(p => Number(p.stok) <= 10);
                this.lowStockCount = critical.length;
                this.lowStockItems = critical.slice(0, 3);
            } catch(e){}
        } else {
            this.lowStockCount = 12;
            this.lowStockItems = [
                { nama: 'Susu UHT Full Cream 1L', stok: 3, statusText: 'STOK KRITIS', desc: 'Tersisa 3 unit di rak utama.' },
                { nama: 'Roti Gandum Spesial', stok: 6, statusText: 'KEDALUWARSA SEGERA', desc: '6 item berakhir dalam 24 jam.' },
                { nama: 'Minyak Goreng 2L', stok: 0, statusText: 'STOK KOSONG', desc: 'Segera lakukan re-order vendor.' }
            ];
        }

        // 3. Active Cashiers
        const rawSession = localStorage.getItem('pos_ilp_active_cashier_session_v1');
        let activeName = 'Budi Nugraha';
        let terminalNo = 'Kasir 1';
        if (rawSession) {
            try {
                const sess = JSON.parse(rawSession);
                if (sess.cashierName) activeName = sess.cashierName;
                if (sess.terminalLabel) terminalNo = sess.terminalLabel;
            } catch(e){}
        }

        this.cashierList = [
            { name: activeName, initial: activeName.substring(0, 2).toUpperCase(), terminal: terminalNo, total: 'Rp ' + this.salesToday.toLocaleString('id-ID'), status: 'Aktif', color: 'bg-green-500' },
            { name: 'Ani Safitri', initial: 'AS', terminal: 'Kasir 2', total: 'Rp 2.150.000', status: 'Aktif', color: 'bg-green-500' },
            { name: 'Dedi Permana', initial: 'DP', terminal: 'Kasir 3', total: 'Rp 0', status: 'Break', color: 'bg-gray-400' }
        ];
    },

    triggerToast(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3500);
    }
}"
x-init="initDashboard()"
class="flex h-screen w-full bg-[#f4f7fb] font-sans text-gray-800 overflow-hidden">
    <!-- Sidebar -->
    <x-manager-sidebar active="dashboard" />

    <!-- Main Workspace -->
    <main class="flex-1 flex flex-col h-screen overflow-y-auto relative">
        
        <!-- Top Navigation -->
        <header class="h-20 bg-[#f4f7fb] flex items-center justify-between px-8 shrink-0 sticky top-0 z-10">
            <!-- Title & Info -->
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-black text-gray-900 tracking-tight">Dashboard Ringkasan Operasional</h1>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200">
                    Gerai Utama
                </span>
            </div>

            <!-- Right Actions -->
            <div class="flex items-center gap-4">
                <button type="button" @click="initDashboard(); triggerToast('Data dashboard disinkronkan!')" class="p-2 rounded-xl bg-white hover:bg-gray-50 text-gray-600 transition-colors shadow-2xs border border-gray-200 cursor-pointer" title="Refresh Data">
                    <span class="material-symbols-rounded text-[20px]">sync</span>
                </button>
                <div class="h-6 w-px bg-gray-300"></div>
                <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-full pl-3.5 pr-1.5 py-1 shadow-2xs">
                    <span class="text-xs font-bold text-gray-800">Manager Gerai</span>
                    <div class="w-6 h-6 rounded-full bg-[#0052cc] text-white flex items-center justify-center text-xs font-bold">
                        M
                    </div>
                </div>
            </div>
        </header>

        <div class="px-8 pb-8 flex flex-col gap-6">
            
            <!-- 4 Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Card 1 -->
                <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-xs flex flex-col justify-between h-[130px]">
                    <div class="flex justify-between items-start">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                            <span class="material-symbols-rounded text-[#0052cc] text-[20px]">payments</span>
                        </div>
                        <div class="bg-green-100 text-green-700 px-2 py-0.5 rounded-md flex items-center gap-1">
                            <span class="material-symbols-rounded text-[14px]">trending_up</span>
                            <span class="text-[11px] font-bold">Aktif</span>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium mb-1">Total Penjualan Hari Ini</p>
                        <h3 class="text-lg font-mono font-bold text-gray-900" x-text="'Rp ' + salesToday.toLocaleString('id-ID')"></h3>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-xs flex flex-col justify-between h-[130px]">
                    <div class="flex justify-between items-start">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                            <span class="material-symbols-rounded text-[#0052cc] text-[20px]">receipt_long</span>
                        </div>
                        <div class="bg-green-100 text-green-700 px-2 py-0.5 rounded-md flex items-center gap-1">
                            <span class="material-symbols-rounded text-[14px]">done_all</span>
                            <span class="text-[11px] font-bold">Sukses</span>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium mb-1">Jumlah Transaksi</p>
                        <h3 class="text-xl font-mono font-bold text-gray-900" x-text="trxCountToday + ' Struk'"></h3>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-xs flex flex-col justify-between h-[130px]">
                    <div class="flex justify-between items-start">
                        <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center">
                            <span class="material-symbols-rounded text-green-600 text-[20px]">how_to_reg</span>
                        </div>
                        <span class="text-[10px] font-extrabold text-green-700 uppercase tracking-wider bg-green-50 px-2 py-0.5 rounded">ONLINE</span>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium mb-1">Kasir Bertugas</p>
                        <div class="flex items-baseline gap-2">
                            <h3 class="text-xl font-mono font-bold text-gray-900">2 Kasir</h3>
                            <span class="text-xs font-mono font-bold text-gray-400">/ 3 Terminal</span>
                        </div>
                    </div>
                </div>

                <!-- Card 4 -->
                <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-xs flex flex-col justify-between h-[130px]">
                    <div class="flex justify-between items-start">
                        <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center">
                            <span class="material-symbols-rounded text-red-500 text-[20px]">warning</span>
                        </div>
                        <span class="text-[11px] font-extrabold text-red-600 tracking-wider">RESTOCK</span>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium mb-1">Peringatan Stok Rendah</p>
                        <h3 class="text-xl font-mono font-bold text-gray-900" x-text="lowStockCount + ' Item'"></h3>
                    </div>
                </div>
            </div>

            <!-- Middle Section -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Left: Chart -->
                <div class="lg:col-span-8 bg-white rounded-2xl border border-gray-100 shadow-xs p-6 flex flex-col">
                    <div class="flex justify-between items-start mb-8">
                        <div>
                            <h2 class="text-base font-bold text-gray-900">Tren Penjualan Mingguan</h2>
                            <p class="text-xs text-gray-500 mt-0.5">Statistik performa gerai 7 hari terakhir</p>
                        </div>
                        <a href="/reports" class="flex items-center gap-1 text-xs font-bold text-[#0052cc] hover:underline">
                            <span>Buka Laporan Penuh</span>
                            <span class="material-symbols-rounded text-[16px]">arrow_forward</span>
                        </a>
                    </div>
                    
                    <!-- Simulated Bar Chart -->
                    <div class="flex-1 flex items-end justify-between px-2 gap-4 h-[180px] mt-auto">
                        <div class="w-full flex flex-col items-center gap-3">
                            <div class="w-full bg-[#dbeafe] rounded-t-md h-[80px]"></div>
                            <span class="text-[10px] font-bold text-gray-500">SEN</span>
                        </div>
                        <div class="w-full flex flex-col items-center gap-3">
                            <div class="w-full bg-[#dbeafe] rounded-t-md h-[60px]"></div>
                            <span class="text-[10px] font-bold text-gray-500">SEL</span>
                        </div>
                        <div class="w-full flex flex-col items-center gap-3">
                            <div class="w-full bg-[#dbeafe] rounded-t-md h-[100px]"></div>
                            <span class="text-[10px] font-bold text-gray-500">RAB</span>
                        </div>
                        <div class="w-full flex flex-col items-center gap-3">
                            <div class="w-full bg-[#dbeafe] rounded-t-md h-[150px]"></div>
                            <span class="text-[10px] font-bold text-gray-500">KAM</span>
                        </div>
                        <div class="w-full flex flex-col items-center gap-3">
                            <div class="w-full bg-[#dbeafe] rounded-t-md h-[120px]"></div>
                            <span class="text-[10px] font-bold text-gray-500">JUM</span>
                        </div>
                        <div class="w-full flex flex-col items-center gap-3">
                            <div class="w-full bg-[#dbeafe] rounded-t-md h-[90px]"></div>
                            <span class="text-[10px] font-bold text-gray-500">SAB</span>
                        </div>
                        <div class="w-full flex flex-col items-center gap-3">
                            <div class="w-full bg-[#0052cc] rounded-t-md h-[170px] shadow-sm"></div>
                            <span class="text-[10px] font-bold text-[#0052cc]">MIN</span>
                        </div>
                    </div>
                </div>

                <!-- Right: Peringatan Kritis -->
                <div class="lg:col-span-4 bg-white rounded-2xl border border-gray-100 shadow-xs p-6 flex flex-col">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="material-symbols-rounded text-orange-500 text-[20px]">error</span>
                        <h2 class="text-base font-bold text-gray-900">Peringatan Stok & Inventori</h2>
                    </div>

                    <div class="flex flex-col gap-3 flex-1">
                        <template x-for="(item, idx) in lowStockItems" :key="idx">
                            <div class="border-l-[3px] p-3 rounded-r-xl rounded-l-xs"
                                 :class="Number(item.stok) === 0 ? 'border-red-500 bg-[#fff5f5]' : 'border-amber-400 bg-[#fffbeb]'">
                                <span class="text-[10px] font-black uppercase tracking-wider"
                                      :class="Number(item.stok) === 0 ? 'text-red-600' : 'text-amber-700'"
                                      x-text="Number(item.stok) === 0 ? 'STOK KOSONG' : 'STOK TERSISA ' + item.stok + ' UNIT'"></span>
                                <h4 class="text-xs font-bold text-gray-900 mt-0.5" x-text="item.nama"></h4>
                                <p class="text-[11px] text-gray-500 mt-0.5" x-text="item.desc || ('Kategori: ' + (item.kategori || 'Umum'))"></p>
                            </div>
                        </template>
                    </div>

                    <a href="/inventory" class="w-full mt-4 bg-white border border-[#0052cc] text-[#0052cc] hover:bg-blue-50 font-bold py-2 rounded-xl transition-colors text-xs text-center block">
                        Buka Manajemen Inventaris
                    </a>
                </div>
            </div>

            <!-- Bottom Section: Aktivitas Kasir -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-xs p-6 mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-base font-bold text-gray-900">Aktivitas Kasir & Terminal</h2>
                    <div class="flex gap-2">
                        <div class="flex items-center gap-1.5 bg-gray-50 border border-gray-200 px-3 py-1 rounded-full text-xs">
                            <div class="w-2 h-2 rounded-full bg-green-500"></div>
                            <span class="font-bold text-gray-600">Online: 2</span>
                        </div>
                        <div class="flex items-center gap-1.5 bg-gray-50 border border-gray-200 px-3 py-1 rounded-full text-xs">
                            <div class="w-2 h-2 rounded-full bg-gray-300"></div>
                            <span class="font-bold text-gray-600">Break: 1</span>
                        </div>
                    </div>
                </div>

                <div class="w-full overflow-x-auto min-w-0">
                    <table class="w-full text-left border-collapse text-xs min-w-[750px]">
                        <thead>
                            <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 text-[11px] font-bold uppercase tracking-wider whitespace-nowrap">
                                <th class="py-3 px-4">Petugas Kasir</th>
                                <th class="py-3 px-4">Nomor Terminal</th>
                                <th class="py-3 px-4">Total Transaksi Hari Ini</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4 text-center">Navigasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="(cashier, idx) in cashierList" :key="idx">
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-blue-100 text-[#0052cc] flex items-center justify-center font-bold text-xs" x-text="cashier.initial"></div>
                                            <span class="font-bold text-gray-900" x-text="cashier.name"></span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-gray-600 font-semibold whitespace-nowrap" x-text="cashier.terminal"></td>
                                    <td class="py-3 px-4 font-mono font-bold text-gray-800 whitespace-nowrap" x-text="cashier.total"></td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-2 h-2 rounded-full" :class="cashier.color"></div>
                                            <span class="font-bold" :class="cashier.status === 'Aktif' ? 'text-green-600' : 'text-gray-500'" x-text="cashier.status"></span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        <a href="/kasir/transaksi" class="inline-flex items-center gap-1 text-[#0052cc] hover:underline font-bold text-xs">
                                            Lihat Sesi
                                            <span class="material-symbols-rounded text-sm">arrow_forward</span>
                                        </a>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        
        <!-- Floating Quick Action Button -->
        <button type="button"
                @click="showQuickActionModal = true"
                title="Aksi Cepat Manager"
                class="fixed bottom-8 right-8 w-14 h-14 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-105 active:scale-95 z-20 cursor-pointer">
            <span class="material-symbols-rounded text-[28px]">add</span>
        </button>

    </main>

    <!-- MODAL AKSI CEPAT -->
    <template x-teleport="body">
        <div x-show="showQuickActionModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div @click.away="showQuickActionModal = false"
                 class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-sm overflow-hidden animate-scale-up">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <h3 class="font-bold text-gray-900 text-sm">Aksi Cepat Gerai</h3>
                    <button @click="showQuickActionModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <div class="p-4 space-y-2 text-xs">
                    <a href="/inventory" class="p-3 rounded-xl border border-gray-200 hover:border-blue-500 hover:bg-blue-50/50 flex items-center gap-3 transition-colors block">
                        <span class="material-symbols-rounded text-[#0052cc] text-[22px]">add_box</span>
                        <div>
                            <span class="font-bold text-gray-900 block">Tambah Produk Master Baru</span>
                            <span class="text-gray-500 text-[11px]">Buka katalog produk dan input data</span>
                        </div>
                    </a>

                    <a href="/promosi" class="p-3 rounded-xl border border-gray-200 hover:border-blue-500 hover:bg-blue-50/50 flex items-center gap-3 transition-colors block">
                        <span class="material-symbols-rounded text-green-600 text-[22px]">local_offer</span>
                        <div>
                            <span class="font-bold text-gray-900 block">Buat Promo & Diskon</span>
                            <span class="text-gray-500 text-[11px]">Pasang diskon akhir pekan / gajian</span>
                        </div>
                    </a>

                    <a href="/stock-opname" class="p-3 rounded-xl border border-gray-200 hover:border-blue-500 hover:bg-blue-50/50 flex items-center gap-3 transition-colors block">
                        <span class="material-symbols-rounded text-amber-600 text-[22px]">assignment_turned_in</span>
                        <div>
                            <span class="font-bold text-gray-900 block">Mulai Stock Opname</span>
                            <span class="text-gray-500 text-[11px]">Verifikasi fisik stok rak toko</span>
                        </div>
                    </a>

                    <a href="/pos" class="p-3 rounded-xl border border-gray-200 hover:border-blue-500 hover:bg-blue-50/50 flex items-center gap-3 transition-colors block">
                        <span class="material-symbols-rounded text-[#0052cc] text-[22px]">point_of_sale</span>
                        <div>
                            <span class="font-bold text-gray-900 block">Buka Meja Kasir POS</span>
                            <span class="text-gray-500 text-[11px]">Masuk ke terminal penjualan kasir</span>
                        </div>
                    </a>
                </div>

            </div>
        </div>
    </template>

    <!-- TOAST NOTIFICATION -->
    <div x-show="showToast"
         x-transition.opacity
         style="display: none;"
         class="fixed bottom-6 right-6 z-50 bg-gray-900 text-white px-5 py-3.5 rounded-2xl shadow-xl flex items-center gap-3 border border-gray-800 text-xs font-semibold">
        <span class="material-symbols-rounded text-emerald-400 text-[20px]">check_circle</span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
