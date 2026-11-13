@extends('layouts.app')

@section('title', 'Dashboard Manager - ILP POS')

@section('content')
<div x-data="{
    currentRole: (new URLSearchParams(window.location.search).get('role') || localStorage.getItem('pos_user_role') || 'Manager'),
    userName: (localStorage.getItem('pos_user_name') || 'Admin Manager'),
    isOwner() {
        return (this.currentRole || '').toLowerCase() === 'owner' || (this.currentRole || '').toLowerCase() === 'superadmin';
    },
    salesToday: 0,
    trxCountToday: 0,
    lowStockCount: 0,
    lowStockItems: [],
    cashierList: [],
    weeklySales: [],
    isLoading: true,
    showQuickActionModal: false,
    toastMessage: '',
    showToast: false,

    async initDashboard() {
        this.isLoading = true;
        try {
            const res = await fetch('/api/manager/dashboard-summary', {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success && json.data) {
                    this.salesToday = json.data.sales_today || 0;
                    this.trxCountToday = json.data.trx_count_today || 0;
                    this.lowStockCount = json.data.low_stock_count || 0;
                    this.lowStockItems = json.data.low_stock_items || [];
                    this.cashierList = json.data.cashier_list || [];
                    this.weeklySales = json.data.weekly_sales || [];
                    this.isLoading = false;
                    return;
                }
            }
        } catch(e) {
            console.error('Failed to fetch dashboard summary from API', e);
        }

        // Fallback if network or offline
        const rawTrx = localStorage.getItem('pos_ilp_transactions_v1');
        this.salesToday = 0;
        this.trxCountToday = 0;
        if (rawTrx) {
            try {
                const trxs = JSON.parse(rawTrx);
                if (Array.isArray(trxs)) {
                    this.trxCountToday = trxs.length;
                    this.salesToday = trxs.reduce((sum, t) => sum + (Number(t.total) || Number(t.finalTotal) || 0), 0);
                }
            } catch(e){}
        }

        const rawProds = localStorage.getItem('pos_ilp_inventory_products_v1');
        this.lowStockCount = 0;
        this.lowStockItems = [];
        if (rawProds) {
            try {
                const prods = JSON.parse(rawProds);
                if (Array.isArray(prods)) {
                    const critical = prods.filter(p => Number(p.stock !== undefined ? p.stock : p.stok) <= Number(p.minStock || 10));
                    this.lowStockCount = critical.length;
                    this.lowStockItems = critical.slice(0, 5).map(p => ({
                        nama: p.name || p.nama,
                        stok: Number(p.stock !== undefined ? p.stock : p.stok) || 0,
                        kategori: p.category || p.kategori || 'Umum',
                        desc: 'Stok tersisa: ' + (p.stock !== undefined ? p.stock : p.stok)
                    }));
                }
            } catch(e){}
        }

        // 3. Active Cashiers from session storage
        this.cashierList = [];
        const rawSession = localStorage.getItem('pos_ilp_active_cashier_session_v1');
        if (rawSession) {
            try {
                const sess = JSON.parse(rawSession);
                if (sess && sess.status === 'OPEN' && sess.cashierName) {
                    this.cashierList.push({
                        name: sess.cashierName,
                        initial: sess.cashierName.substring(0, 2).toUpperCase(),
                        terminal: sess.terminalLabel || 'Terminal 01',
                        total: 'Rp ' + this.salesToday.toLocaleString('id-ID'),
                        status: 'Aktif',
                        color: 'bg-green-500'
                    });
                }
            } catch(e){}
        }
        this.isLoading = false;
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
                <h1 class="text-xl font-black text-gray-900 tracking-tight"
                    x-text="isOwner() ? 'Dashboard Eksekutif & Konsolidasi Pemilik' : 'Dashboard Ringkasan Operasional'">
                    Dashboard Ringkasan Operasional
                </h1>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold"
                      :class="isOwner() ? 'bg-purple-100 text-purple-900 border border-purple-300' : 'bg-blue-50 text-[#0052cc] border border-blue-200'"
                      x-text="isOwner() ? 'Akses Owner • Konsolidasi 3 Cabang' : 'Gerai Utama'">
                    Gerai Utama
                </span>
            </div>

            <!-- Right Actions -->
            <div class="flex items-center gap-3">
                <x-notification-bell role="MANAGER" />
                <button type="button" @click="window.PosRealtime?.syncNow?.(); initDashboard();" class="p-2 rounded-xl bg-white hover:bg-gray-50 text-gray-600 transition-colors shadow-2xs border border-gray-200 cursor-pointer" title="Refresh Data Realtime">
                    <span class="material-symbols-rounded text-[20px]" :class="$store.network?.isChecking ? 'animate-spin text-[#0052cc]' : ''">sync</span>
                </button>
                <x-realtime-network-badge />
                <div class="h-6 w-px bg-gray-300"></div>
                <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-full pl-3.5 pr-1.5 py-1 shadow-2xs">
                    <span class="text-xs font-bold text-gray-800" x-text="isOwner() ? 'Ir. Hartono Wibowo (Owner)' : 'Manager Gerai'">Manager Gerai</span>
                    <div class="w-6 h-6 rounded-full text-white flex items-center justify-center text-xs font-bold shadow-2xs"
                         :class="isOwner() ? 'bg-purple-900 ring-2 ring-purple-300' : 'bg-[#0052cc]'"
                         x-text="isOwner() ? 'HW' : 'M'">
                        M
                    </div>
                </div>
            </div>
        </header>

        <div class="px-8 pb-8 flex flex-col gap-6">

            <!-- Executive Owner Quick Banner -->
            <div x-show="isOwner()" class="bg-gradient-to-r from-[#2e1065] via-[#3b0764] to-[#1e1b4b] rounded-2xl p-6 text-white shadow-md border border-purple-800/40 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center border border-white/20 shrink-0">
                        <span class="material-symbols-rounded text-yellow-300 text-[32px]">shield_person</span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h2 class="text-lg font-black tracking-tight">Portal Eksekutif Pemilik Koperasi (Owner)</h2>
                            <span class="px-2.5 py-0.5 rounded-full bg-yellow-400 text-purple-950 text-[10px] font-black uppercase tracking-wider">Superadmin</span>
                        </div>
                        <p class="text-xs text-purple-200 mt-1 max-w-2xl leading-relaxed">
                            Selamat datang, <strong class="text-white">Ir. Hartono Wibowo</strong>. Anda memiliki otorisasi penuh atas data keuangan multi-cabang (Pusat, Utara, Selatan), audit P&amp;L konsolidasi, plafon kasbon anggota, dan tarif pajak PPN.
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2.5 shrink-0 w-full md:w-auto">
                    <a href="{{ url('/reports?tab=consolidated_3branches') }}" class="flex-1 md:flex-initial px-4 py-2.5 bg-yellow-400 hover:bg-yellow-300 text-purple-950 rounded-xl font-bold text-xs shadow-sm transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">account_balance</span>
                        <span>Konsolidasi 3 Cabang &amp; P&amp;L</span>
                    </a>
                    <a href="{{ url('/settings') }}" class="px-3.5 py-2.5 bg-white/15 hover:bg-white/25 border border-white/20 text-white rounded-xl font-bold text-xs transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">tune</span>
                        <span>Pengaturan Pajak</span>
                    </a>
                </div>
            </div>
            
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
                        <span class="text-[10px] font-extrabold uppercase tracking-wider px-2 py-0.5 rounded"
                              :class="cashierList.filter(c => c.status === 'Aktif').length > 0 ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'"
                              x-text="cashierList.filter(c => c.status === 'Aktif').length > 0 ? 'ONLINE' : 'OFFLINE'"></span>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium mb-1">Kasir Bertugas</p>
                        <div class="flex items-baseline gap-2">
                            <h3 class="text-xl font-mono font-bold text-gray-900" x-text="cashierList.filter(c => c.status === 'Aktif').length + ' Kasir'"></h3>
                            <span class="text-xs font-mono font-bold text-gray-400">Aktif</span>
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
                    
                    <!-- Dynamic Weekly Bar Chart -->
                    <div class="flex-1 flex items-end justify-between px-2 gap-4 h-[180px] mt-auto">
                        <template x-if="weeklySales && weeklySales.length > 0">
                            <template x-for="(bar, bidx) in weeklySales" :key="bidx">
                                <div class="w-full flex flex-col items-center gap-3">
                                    <div class="w-full rounded-t-md transition-all duration-300"
                                         :style="'height: ' + (bar.height_percent * 1.5) + 'px; max-height: 150px;'"
                                         :class="bidx === weeklySales.length - 1 ? 'bg-[#0052cc] shadow-sm' : 'bg-[#dbeafe]'"
                                         :title="bar.day + ': Rp ' + Number(bar.sales).toLocaleString('id-ID')"></div>
                                    <span class="text-[10px] font-bold"
                                          :class="bidx === weeklySales.length - 1 ? 'text-[#0052cc]' : 'text-gray-500'"
                                          x-text="bar.day"></span>
                                </div>
                            </template>
                        </template>
                        <template x-if="!weeklySales || weeklySales.length === 0">
                            <div class="w-full flex items-center justify-center text-xs text-gray-400 py-8">
                                Memuat statistik mingguan...
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Right: Peringatan Kritis -->
                <div class="lg:col-span-4 bg-white rounded-2xl border border-gray-100 shadow-xs p-6 flex flex-col">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="material-symbols-rounded text-orange-500 text-[20px]">error</span>
                        <h2 class="text-base font-bold text-gray-900">Peringatan Stok & Inventori</h2>
                    </div>

                    <div class="flex flex-col gap-3 flex-1">
                        <template x-if="lowStockItems.length === 0">
                            <div class="p-6 text-center text-xs text-gray-400">
                                <span class="material-symbols-rounded text-2xl text-emerald-500 block mb-1">check_circle</span>
                                Semua stok produk dalam batas aman.
                            </div>
                        </template>
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
                            <span class="font-bold text-gray-600" x-text="'Online: ' + cashierList.filter(c => c.status === 'Aktif').length"></span>
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
                            <template x-if="cashierList.length === 0">
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-slate-400 text-xs">
                                        <span class="material-symbols-rounded text-2xl text-slate-300 block mb-1">person_off</span>
                                        Belum ada sesi kasir aktif hari ini.
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
