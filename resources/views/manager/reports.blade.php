@extends('layouts.app')

@section('title', 'Pelaporan, Akuntansi Dasar & Konsolidasi - ILP POS')

@section('content')
<div x-data="{
    activeTab: (new URLSearchParams(window.location.search).get('tab') || 'sales_accounting'), // 'sales_accounting' | 'profit_loss' | 'fast_moving' | 'cash_reconciliation' | 'product_taxes' | 'payroll_debt' | 'consolidated_3branches'
    
    // ==========================================
    // 1. Modul E: Laporan Penjualan State
    // ==========================================
    salesPeriod: 'monthly',
    salesBranch: 'all',
    salesDate: '{{ date('Y-m-d') }}',
    salesMonth: '{{ date('Y-m') }}',
    salesCustomStart: '{{ date('Y-m-01') }}',
    salesCustomEnd: '{{ date('Y-m-d') }}',
    isLoadingSales: false,
    salesData: {
        summary: {
            gross_sales: 0,
            total_discounts: 0,
            total_tax: 0,
            grand_total: 0,
            transaction_count: 0,
            average_basket: 0,
            payment_breakdown: { tunai: 0, qris: 0, kredit_kasbon: 0, campuran: 0 }
        },
        shifts_breakdown: [],
        branch_breakdown: [],
        daily_trends: [],
        recent_transactions: []
    },

    // ==========================================
    // 2. Modul E: Laporan Laba/Rugi (P&L) State
    // ==========================================
    plStartDate: '{{ date('Y-m-01') }}',
    plEndDate: '{{ date('Y-m-d') }}',
    plBranch: 'all',
    isLoadingPL: false,
    plData: {
        statement: {
            gross_sales: 0,
            regular_product_sales: 0,
            consignment_product_sales: 0,
            total_discounts: 0,
            net_sales: 0,
            regular_cogs: 0,
            umkm_partner_share: 0,
            cooperative_commission_earned: 0,
            regular_gross_profit: 0,
            total_gross_profit: 0,
            collected_tax_ppn: 0,
            net_profit: 0,
            profit_margin_percent: 0
        }
    },

    // ==========================================
    // 3. Modul E: Barang Terlaris (Fast Moving)
    // ==========================================
    fastMovingLimit: 10,
    fastMovingSort: 'qty',
    isLoadingFastMoving: false,
    fastMovingData: [],
    fastMovingTotalRevenue: 0,

    // ==========================================
    // 4. Modul E: Rekonsiliasi Kas (Cash Count)
    // ==========================================
    reconTerminal: 'all',
    reconStatus: 'all',
    isLoadingRecon: false,
    reconData: {
        summary: {
            total_audited_shifts: 0,
            exact_matches: 0,
            shortage_count: 0,
            overage_count: 0,
            accuracy_rate_percent: 100,
            total_expected_cash: 0,
            total_actual_cash: 0,
            net_variance: 0
        },
        data: []
    },

    // ==========================================
    // 5. Modul E: Pengaturan Pajak Produk (Tax)
    // ==========================================
    taxSearch: '',
    taxableFilter: 'all',
    taxCategory: 'Semua',
    isLoadingTax: false,
    taxProductList: [],
    taxSummary: {
        taxable_products: 0,
        non_taxable_products: 0,
        global_tax_enabled: true,
        global_tax_rate: 11
    },

    // ==========================================
    // Modul B: Laporan Piutang Kasbon State
    // ==========================================
    month: '{{ date('Y-m') }}',
    selectedBranch: 'all',
    selectedDept: 'all',
    searchDebt: '',
    isLoadingDebt: false,
    isProcessingCutoff: false,
    showCutoffModal: false,
    cutoffNotes: '',
    debtSummary: {
        period_month: '{{ date('Y-m') }}',
        total_registered_members: 0,
        members_with_active_debt: 0,
        total_credit_limit: 0,
        total_active_debt: 0,
        total_remaining_credit: 0,
        overall_utilization_percent: 0
    },
    debtRows: [],
    branches: [
        'Cabang 1 - Mill / Pabrik',
        'Cabang 2 - Kebun Barat',
        'Cabang 3 - Kebun Timur'
    ],

    // Modul D: Konsolidasi 3 Cabang
    isLoadingConsolidated: false,
    consolidatedStartDate: '{{ date('Y-m-01') }}',
    consolidatedEndDate: '{{ date('Y-m-d') }}',
    consolidatedBranch: 'all',
    consolidatedData: {
        financial_summary: {},
        branch_breakdown: [],
        recent_stock_corrections: []
    },

    // Toast
    toastMessage: '',
    toastType: 'success',
    showToast: false,

    async initReports() {
        await Promise.all([
            this.loadSalesReport(),
            this.loadProfitLossReport(),
            this.loadFastMoving(),
            this.loadCashReconciliation(),
            this.loadConsolidatedReport()
        ]);
    },

    // 1. Load Sales Report API
    async loadSalesReport() {
        this.isLoadingSales = true;
        try {
            const params = new URLSearchParams({
                period: this.salesPeriod,
                branch: this.salesBranch,
                date: this.salesDate,
                month: this.salesMonth,
                start_date: this.salesCustomStart,
                end_date: this.salesCustomEnd
            });
            const res = await fetch(`/api/reports/sales?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.salesData = json;
                }
            }
        } catch (e) {
            console.error('Error fetching sales report:', e);
            this.triggerToast('Gagal memuat laporan penjualan.', 'error');
        } finally {
            this.isLoadingSales = false;
        }
    },

    // 2. Load Profit & Loss API
    async loadProfitLossReport() {
        this.isLoadingPL = true;
        try {
            const params = new URLSearchParams({
                start_date: this.plStartDate,
                end_date: this.plEndDate,
                branch: this.plBranch
            });
            const res = await fetch(`/api/reports/profit-loss?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.plData = json;
                }
            }
        } catch (e) {
            console.error('Error fetching P&L report:', e);
        } finally {
            this.isLoadingPL = false;
        }
    },

    // 3. Load Fast Moving Items API
    async loadFastMoving() {
        this.isLoadingFastMoving = true;
        try {
            const params = new URLSearchParams({
                limit: this.fastMovingLimit,
                sort_by: this.fastMovingSort
            });
            const res = await fetch(`/api/reports/fast-moving?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.fastMovingData = json.data;
                    this.fastMovingTotalRevenue = json.total_period_revenue;
                }
            }
        } catch (e) {
            console.error('Error fetching fast moving items:', e);
        } finally {
            this.isLoadingFastMoving = false;
        }
    },

    // 4. Load Cash Reconciliation API
    async loadCashReconciliation() {
        this.isLoadingRecon = true;
        try {
            const params = new URLSearchParams({
                terminal_code: this.reconTerminal,
                status: this.reconStatus
            });
            const res = await fetch(`/api/reports/cash-reconciliation?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.reconData = json;
                }
            }
        } catch (e) {
            console.error('Error fetching cash reconciliation:', e);
        } finally {
            this.isLoadingRecon = false;
        }
    },

    // 5. Load Tax Products API
    async loadTaxProducts() {
        this.isLoadingTax = true;
        try {
            const params = new URLSearchParams({
                search: this.taxSearch,
                is_taxable: this.taxableFilter,
                category: this.taxCategory
            });
            const res = await fetch(`/api/products/tax/list?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.taxProductList = json.data;
                    this.taxSummary = json.summary;
                }
            }
        } catch (e) {
            console.error('Error fetching tax products:', e);
        } finally {
            this.isLoadingTax = false;
        }
    },

    async toggleProductTax(p) {
        const newStatus = !p.is_taxable;
        try {
            const res = await fetch(`/api/products/${p.id}/tax-status`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    is_taxable: newStatus,
                    tax_rate: newStatus ? 11.0 : 0.0
                })
            });
            const json = await res.json();
            if (res.ok && json.success) {
                p.is_taxable = newStatus;
                p.tax_rate = newStatus ? 11.0 : 0.0;
                this.triggerToast(json.message);
                await this.loadTaxProducts();
            }
        } catch (e) {
            this.triggerToast('Gagal mengubah status pajak produk.', 'error');
        }
    },

    // Load Debt Report
    async loadDebtReport() {
        this.isLoadingDebt = true;
        try {
            const params = new URLSearchParams({
                month: this.month,
                branch: this.selectedBranch,
                department: this.selectedDept,
                search: this.searchDebt.trim(),
                per_page: 50
            });
            const res = await fetch(`/api/reports/member-debts?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.debtSummary = json.summary;
                    this.debtRows = json.data;
                }
            }
        } catch (e) {
            console.error('Error fetching debt report:', e);
        } finally {
            this.isLoadingDebt = false;
        }
    },

    // 7. Modul D: Konsolidasi 3 Cabang & P&L (Akses Owner)
    async loadConsolidatedReport() {
        this.isLoadingConsolidated = true;
        try {
            const params = new URLSearchParams({
                start_date: this.consolidatedStartDate,
                end_date: this.consolidatedEndDate,
                branch: this.consolidatedBranch
            });
            const res = await fetch(`/api/reports/consolidated-branches?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success && json.data) {
                    this.consolidatedData = json.data;
                }
            }
        } catch (e) {
            console.error('Error fetching consolidated 3-branches report:', e);
        } finally {
            this.isLoadingConsolidated = false;
        }
    },

    formatRupiah(val) {
        if (!val && val !== 0) return 'Rp 0';
        return 'Rp ' + Math.round(val).toLocaleString('id-ID');
    },

    triggerToast(msg, type = 'success') {
        this.toastMessage = msg;
        this.toastType = type;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3500);
    }
}"
x-init="initReports()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">
    
    <!-- Sidebar -->
    <x-manager-sidebar active="reports" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        
        <!-- Top Header Navigation Tabs for Modul E & Accounting -->
        <header class="h-[68px] bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-2 overflow-x-auto py-2">
                <div class="inline-flex p-1 bg-gray-100 rounded-xl gap-1 shrink-0">
                    <!-- Tab 1: Laporan Penjualan (Modul E) -->
                    <button type="button"
                            @click="activeTab = 'sales_accounting'; loadSalesReport()"
                            :class="activeTab === 'sales_accounting' ? 'bg-white text-[#0052cc] shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                            class="px-3.5 py-2 rounded-lg text-xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-rounded text-[17px]">point_of_sale</span>
                        <span>1. Penjualan Cabang &amp; Shift</span>
                    </button>

                    <!-- Tab 2: Laporan Laba/Rugi (Modul E) -->
                    <button type="button"
                            @click="activeTab = 'profit_loss'; loadProfitLossReport()"
                            :class="activeTab === 'profit_loss' ? 'bg-white text-emerald-700 shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                            class="px-3.5 py-2 rounded-lg text-xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-rounded text-[17px]">account_balance</span>
                        <span>2. Laba / Rugi Bersih (P&amp;L)</span>
                    </button>

                    <!-- Tab 3: Barang Terlaris (Modul E) -->
                    <button type="button"
                            @click="activeTab = 'fast_moving'; loadFastMoving()"
                            :class="activeTab === 'fast_moving' ? 'bg-white text-amber-700 shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                            class="px-3.5 py-2 rounded-lg text-xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-rounded text-[17px]">local_fire_department</span>
                        <span>3. Barang Terlaris &amp; Restock</span>
                    </button>

                    <!-- Tab 4: Rekonsiliasi Kas (Modul E) -->
                    <button type="button"
                            @click="activeTab = 'cash_reconciliation'; loadCashReconciliation()"
                            :class="activeTab === 'cash_reconciliation' ? 'bg-white text-indigo-700 shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                            class="px-3.5 py-2 rounded-lg text-xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-rounded text-[17px]">price_check</span>
                        <span>4. Rekonsiliasi Kas Shift</span>
                    </button>

                    <!-- Tab 5: Pajak Produk (Modul E) -->
                    <button type="button"
                            @click="activeTab = 'product_taxes'; loadTaxProducts()"
                            :class="activeTab === 'product_taxes' ? 'bg-white text-teal-700 shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                            class="px-3.5 py-2 rounded-lg text-xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-rounded text-[17px]">receipt</span>
                        <span>5. Produk Kena Pajak (PPN)</span>
                    </button>

                    <!-- Tab 6: Piutang Anggota (Modul B) -->
                    <button type="button"
                            @click="activeTab = 'payroll_debt'; loadDebtReport()"
                            :class="activeTab === 'payroll_debt' ? 'bg-white text-purple-700 shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                            class="px-3.5 py-2 rounded-lg text-xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-rounded text-[17px]">badge</span>
                        <span>Piutang Anggota (Payroll)</span>
                    </button>

                    <!-- Tab 7: Konsolidasi 3 Cabang & P&L (Akses Owner) -->
                    <button type="button"
                            @click="activeTab = 'consolidated_3branches'; loadConsolidatedReport()"
                            :class="activeTab === 'consolidated_3branches' ? 'bg-purple-900 text-white shadow-xs font-black' : 'text-purple-800 hover:text-purple-950 font-bold bg-purple-50/70'"
                            class="px-3.5 py-2 rounded-lg text-xs transition-all flex items-center gap-1.5 cursor-pointer border border-purple-200">
                        <span class="material-symbols-rounded text-[17px]">hub</span>
                        <span>Konsolidasi 3 Cabang (Owner)</span>
                    </button>
                </div>
            </div>

            <div class="flex items-center gap-4 text-gray-500 shrink-0">
                <x-notification-bell role="MANAGER" />
                <button @click="window.PosRealtime?.syncNow?.(); initReports()"
                        class="hover:text-gray-900 transition-colors p-1" title="Muat Ulang Semua Data">
                    <span class="material-symbols-rounded text-[22px]" :class="((isLoadingSales || isLoadingPL || isLoadingFastMoving || isLoadingRecon) || $store.network?.isChecking) ? 'animate-spin text-[#0052cc]' : ''">autorenew</span>
                </button>
                <div class="h-6 w-px bg-gray-200"></div>
                <x-realtime-network-badge labelOnline="Modul E Terhubung" />
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-6 lg:p-8 pb-10">
            <div class="max-w-[1400px] mx-auto space-y-6">

                <!-- ========================================================= -->
                <!-- 1. TAB: LAPORAN PENJUALAN (MODUL E)                       -->
                <!-- ========================================================= -->
                <div x-show="activeTab === 'sales_accounting'" class="space-y-6">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-3 border-b border-gray-200">
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-[19px] font-black text-gray-900 tracking-tight">Laporan Penjualan Cabang &amp; Shift Kasir</h1>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200">Modul E: Penjualan</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Laporan omzet penjualan per-shift kasir, per-cabang, dan konsolidasi 3 cabang periode harian, mingguan, bulanan.</p>
                        </div>

                        <!-- Filter Toolbar -->
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Periode Selector -->
                            <div class="inline-flex p-1 bg-white border border-gray-300 rounded-xl text-xs font-bold shadow-2xs">
                                <button type="button" @click="salesPeriod = 'daily'; loadSalesReport()"
                                        :class="salesPeriod === 'daily' ? 'bg-[#0052cc] text-white' : 'text-gray-600'"
                                        class="px-3 py-1 rounded-lg">Harian</button>
                                <button type="button" @click="salesPeriod = 'weekly'; loadSalesReport()"
                                        :class="salesPeriod === 'weekly' ? 'bg-[#0052cc] text-white' : 'text-gray-600'"
                                        class="px-3 py-1 rounded-lg">Mingguan</button>
                                <button type="button" @click="salesPeriod = 'monthly'; loadSalesReport()"
                                        :class="salesPeriod === 'monthly' ? 'bg-[#0052cc] text-white' : 'text-gray-600'"
                                        class="px-3 py-1 rounded-lg">Bulanan</button>
                            </div>

                            <!-- Cabang Selector -->
                            <select x-model="salesBranch" @change="loadSalesReport()"
                                    class="bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold text-gray-700 shadow-2xs">
                                <option value="all">Konsolidasi 3 Cabang</option>
                                <option value="Pusat">Minimarket Pusat</option>
                                <option value="Cabang 1">Cabang 1 - Mill / Pabrik</option>
                                <option value="Cabang 2">Cabang 2 - Kebun Barat</option>
                            </select>

                            <button type="button" @click="loadSalesReport()"
                                    class="p-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl shadow-2xs">
                                <span class="material-symbols-rounded text-[18px]">refresh</span>
                            </button>
                        </div>
                    </div>

                    <!-- 4 Sales KPI Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">TOTAL OMZET BERSIH</span>
                            <div class="mt-2">
                                <h3 class="text-[22px] font-mono font-black text-gray-900" x-text="formatRupiah(salesData?.summary?.grand_total || 0)">Rp 0</h3>
                                <p class="text-[11px] text-gray-400 font-mono mt-0.5" x-text="(salesData?.summary?.transaction_count || 0) + ' Transaksi Berhasil'"></p>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">RATA-RATA KERANJANG (BASKET)</span>
                            <div class="mt-2">
                                <h3 class="text-[22px] font-mono font-black text-[#0052cc]" x-text="formatRupiah(salesData?.summary?.average_basket || 0)">Rp 0</h3>
                                <p class="text-[11px] text-gray-400 font-mono mt-0.5">Nilai rata-rata belanja per struk</p>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">PEMBAYARAN TUNAI</span>
                            <div class="mt-2">
                                <h3 class="text-[22px] font-mono font-black text-emerald-600" x-text="formatRupiah(salesData?.summary?.payment_breakdown?.tunai || 0)">Rp 0</h3>
                                <p class="text-[11px] text-emerald-700 font-bold mt-0.5">Arus Fisik Laci Kasir</p>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">NON-TUNAI &amp; KASBON</span>
                            <div class="mt-2">
                                <h3 class="text-[22px] font-mono font-black text-purple-700" x-text="formatRupiah((salesData?.summary?.payment_breakdown?.qris || 0) + (salesData?.summary?.payment_breakdown?.kredit_kasbon || 0))">Rp 0</h3>
                                <p class="text-[11px] text-gray-400 font-mono mt-0.5">QRIS &amp; Potong Gaji Anggota</p>
                            </div>
                        </div>
                    </div>

                    <!-- Breakdown Per-Cabang Table -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-xs space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-gray-900">Performa Penjualan 3 Cabang PT ILP</h3>
                            <span class="text-xs text-gray-500 font-mono">Konsolidasi Multi-Outlet</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs min-w-[700px] border-collapse">
                                <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 text-[11px] font-bold uppercase">
                                    <tr>
                                        <th class="px-5 py-3">KODE CABANG</th>
                                        <th class="px-5 py-3">CABANG</th>
                                        <th class="px-5 py-3 text-center">TRANSAKSI</th>
                                        <th class="px-5 py-3 text-right">TOTAL PENJUALAN</th>
                                        <th class="px-5 py-3 text-center">KONTRIBUSI OMZET</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-gray-700">
                                    <template x-for="b in (salesData?.branch_breakdown || [])" :key="b.branch_code">
                                        <tr class="hover:bg-blue-50/20">
                                            <td class="px-5 py-3.5 font-mono font-bold text-gray-500" x-text="b.branch_code"></td>
                                            <td class="px-5 py-3.5 font-bold text-gray-900" x-text="b.branch_name"></td>
                                            <td class="px-5 py-3.5 text-center font-mono font-bold" x-text="b.transaction_count"></td>
                                            <td class="px-5 py-3.5 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(b.total_sales)"></td>
                                            <td class="px-5 py-3.5 text-center">
                                                <div class="inline-flex items-center gap-2">
                                                    <div class="w-24 bg-gray-200 rounded-full h-2 overflow-hidden">
                                                        <div class="bg-[#0052cc] h-2 rounded-full" :style="'width: ' + b.contribution_percent + '%'"></div>
                                                    </div>
                                                    <span class="font-bold text-xs" x-text="b.contribution_percent + '%'"></span>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Breakdown Per-Shift Table -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-xs space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-gray-900">Rincian Penjualan Berdasarkan Shift Kasir</h3>
                            <span class="text-xs text-gray-500 font-mono" x-text="(salesData?.shifts_breakdown?.length || 0) + ' Sesi Shift Terdata'"></span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs min-w-[800px] border-collapse">
                                <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 text-[11px] font-bold uppercase">
                                    <tr>
                                        <th class="px-4 py-3">KODE SHIFT</th>
                                        <th class="px-4 py-3">TERMINAL</th>
                                        <th class="px-4 py-3">NAMA KASIR</th>
                                        <th class="px-4 py-3 text-center">TRANSAKSI</th>
                                        <th class="px-4 py-3 text-right">MODAL AWAL</th>
                                        <th class="px-4 py-3 text-right">PENJUALAN TUNAI</th>
                                        <th class="px-4 py-3 text-right font-bold text-gray-900">TOTAL OMZET</th>
                                        <th class="px-4 py-3 text-center">STATUS</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-gray-700">
                                    <template x-for="s in (salesData?.shifts_breakdown || [])" :key="s.shift_id">
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-4 py-3 font-mono text-gray-500" x-text="s.shift_code"></td>
                                            <td class="px-4 py-3 font-mono font-bold text-gray-700" x-text="s.terminal_code"></td>
                                            <td class="px-4 py-3 font-bold text-gray-900" x-text="s.cashier_name"></td>
                                            <td class="px-4 py-3 text-center font-mono" x-text="s.transaction_count"></td>
                                            <td class="px-4 py-3 text-right font-mono text-gray-600" x-text="formatRupiah(s.opening_cash)"></td>
                                            <td class="px-4 py-3 text-right font-mono text-emerald-600" x-text="formatRupiah(s.cash_sales)"></td>
                                            <td class="px-4 py-3 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(s.total_sales)"></td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                                      :class="s.status === 'OPEN' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-600'"
                                                      x-text="s.status"></span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- 2. TAB: LAPORAN LABA / RUGI BERSIH (MODUL E - P&L)        -->
                <!-- ========================================================= -->
                <div x-show="activeTab === 'profit_loss'" class="space-y-6" style="display: none;">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-3 border-b border-gray-200">
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-[19px] font-black text-gray-900 tracking-tight">Laporan Laba / Rugi Bersih (P&amp;L Statement)</h1>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Akuntansi Koperasi &amp; UMKM</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Dihitung otomatis: Omzet kotor dikurangi HPP produk reguler serta memperhitungkan bagi hasil komisi konsinyasi UMKM.</p>
                        </div>

                        <div class="flex items-center gap-2">
                            <input type="date" x-model="plStartDate" @change="loadProfitLossReport()" class="bg-white border rounded-xl px-3 py-1.5 text-xs font-semibold">
                            <span class="text-xs text-gray-400">s/d</span>
                            <input type="date" x-model="plEndDate" @change="loadProfitLossReport()" class="bg-white border rounded-xl px-3 py-1.5 text-xs font-semibold">
                            <button type="button" @click="loadProfitLossReport()" class="p-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl">
                                <span class="material-symbols-rounded text-[18px]">refresh</span>
                            </button>
                        </div>
                    </div>

                    <!-- P&L Statement Accounting Card -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-6 lg:p-8 shadow-xs max-w-4xl mx-auto space-y-6">
                        <div class="border-b pb-4 flex justify-between items-center">
                            <div>
                                <h2 class="text-base font-bold text-gray-900">IKHTISAR LABA / RUGI OPERASIONAL</h2>
                                <p class="text-xs text-gray-500" x-text="'Periode: ' + plStartDate + ' s/d ' + plEndDate"></p>
                            </div>
                            <span class="text-xs font-mono font-bold text-gray-400">Mata Uang: IDR (Rp)</span>
                        </div>

                        <div class="space-y-4 text-xs">
                            <!-- 1. Pendapatan Penjualan -->
                            <div class="space-y-2">
                                <div class="font-bold text-gray-900 uppercase tracking-wider text-[11px] bg-slate-50 p-2 rounded-lg">I. PENDAPATAN PENJUALAN (REVENUES)</div>
                                <div class="flex justify-between py-1 px-3">
                                    <span class="text-gray-600">Penjualan Produk Reguler Koperasi</span>
                                    <span class="font-mono font-bold text-gray-800" x-text="formatRupiah(plData?.statement?.regular_product_sales || 0)"></span>
                                </div>
                                <div class="flex justify-between py-1 px-3">
                                    <span class="text-gray-600">Penjualan Produk Titipan Konsinyasi UMKM</span>
                                    <span class="font-mono font-bold text-gray-800" x-text="formatRupiah(plData?.statement?.consignment_product_sales || 0)"></span>
                                </div>
                                <div class="flex justify-between py-1 px-3 border-t">
                                    <span class="font-bold text-gray-800">Total Penjualan Kotor (Gross Sales)</span>
                                    <span class="font-mono font-bold text-gray-900" x-text="formatRupiah(plData?.statement?.gross_sales || 0)"></span>
                                </div>
                                <div class="flex justify-between py-1 px-3 text-red-600">
                                    <span>(-) Potongan / Diskon Promosi &amp; Expired Clearance</span>
                                    <span class="font-mono font-bold" x-text="'- ' + formatRupiah(plData?.statement?.total_discounts || 0)"></span>
                                </div>
                                <div class="flex justify-between py-1.5 px-3 bg-blue-50/50 rounded-lg font-bold text-gray-900">
                                    <span>Total Penjualan Bersih (Net Sales)</span>
                                    <span class="font-mono text-[#0052cc]" x-text="formatRupiah(plData?.statement?.net_sales || 0)"></span>
                                </div>
                            </div>

                            <!-- 2. Harga Pokok Penjualan & Bagi Hasil UMKM -->
                            <div class="space-y-2 pt-2">
                                <div class="font-bold text-gray-900 uppercase tracking-wider text-[11px] bg-slate-50 p-2 rounded-lg">II. BIAYA POKOK PENJUALAN &amp; BAGI HASIL MITRA</div>
                                <div class="flex justify-between py-1 px-3 text-red-700">
                                    <span>(-) Harga Pokok Penjualan (HPP) Barang Reguler</span>
                                    <span class="font-mono font-bold" x-text="'- ' + formatRupiah(plData?.statement?.regular_cogs || 0)"></span>
                                </div>
                                <div class="flex justify-between py-1 px-3 text-amber-800">
                                    <span>(-) Hak Setoran Hasil Penjualan ke Mitra UMKM</span>
                                    <span class="font-mono font-bold" x-text="'- ' + formatRupiah(plData?.statement?.umkm_partner_share || 0)"></span>
                                </div>
                                <div class="flex justify-between py-1 px-3 text-emerald-700 bg-emerald-50/40 rounded">
                                    <span class="font-semibold">(+) Pendapatan Bagi Hasil / Komisi Koperasi dari UMKM</span>
                                    <span class="font-mono font-bold" x-text="'+ ' + formatRupiah(plData?.statement?.cooperative_commission_earned || 0)"></span>
                                </div>
                            </div>

                            <!-- 3. Laba Kotor -->
                            <div class="flex justify-between py-2 px-3 bg-emerald-50 rounded-xl font-black text-[13px] text-emerald-900 border border-emerald-200">
                                <span>TOTAL LABA KOTOR (GROSS PROFIT)</span>
                                <span class="font-mono" x-text="formatRupiah(plData?.statement?.total_gross_profit || 0)"></span>
                            </div>

                            <!-- 4. Pajak Terkumpul -->
                            <div class="flex justify-between py-1 px-3 text-gray-500">
                                <span>Pajak Pertambahan Nilai (PPN 11%) Terkumpul</span>
                                <span class="font-mono" x-text="formatRupiah(plData?.statement?.collected_tax_ppn || 0)"></span>
                            </div>

                            <!-- 5. Laba Bersih Akhir -->
                            <div class="flex justify-between py-3 px-4 bg-gray-900 text-white rounded-2xl font-black text-sm">
                                <div>
                                    <div>LABA BERSIH OPERASIONAL (NET PROFIT)</div>
                                    <div class="text-[11px] font-normal text-emerald-400" x-text="'Margin Keuntungan Bersih: ' + (plData?.statement?.profit_margin_percent || 0) + '%'"></div>
                                </div>
                                <span class="text-[18px] font-mono text-emerald-400 self-center" x-text="formatRupiah(plData?.statement?.net_profit || 0)"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- 3. TAB: BARANG TERLARIS & RESTOCK (MODUL E)               -->
                <!-- ========================================================= -->
                <div x-show="activeTab === 'fast_moving'" class="space-y-6" style="display: none;">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-3 border-b border-gray-200">
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-[19px] font-black text-gray-900 tracking-tight">Analisis Barang Terlaris (Fast Moving Items)</h1>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Smart Restock Advisor</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Peringkat produk terlaris berdasarkan kuantitas &amp; nilai penjualan untuk mempermudah keputusan restock barang.</p>
                        </div>

                        <div class="flex items-center gap-2">
                            <select x-model="fastMovingSort" @change="loadFastMoving()" class="bg-white border rounded-xl px-3 py-1.5 text-xs font-bold text-gray-700">
                                <option value="qty">Urutkan: Kuantitas Terbanyak (Pcs)</option>
                                <option value="revenue">Urutkan: Omzet Tertinggi (Rp)</option>
                            </select>

                            <select x-model="fastMovingLimit" @change="loadFastMoving()" class="bg-white border rounded-xl px-3 py-1.5 text-xs font-bold text-gray-700">
                                <option value="5">Top 5 Item</option>
                                <option value="10">Top 10 Item</option>
                                <option value="20">Top 20 Item</option>
                            </select>
                        </div>
                    </div>

                    <!-- Fast Moving Items Table with Restock Alert -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-xs space-y-4">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs min-w-[850px] border-collapse">
                                <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 text-[11px] font-bold uppercase">
                                    <tr>
                                        <th class="px-4 py-3 w-12 text-center">RANK</th>
                                        <th class="px-4 py-3">PRODUK &amp; SKU</th>
                                        <th class="px-4 py-3">KATEGORI</th>
                                        <th class="px-4 py-3 text-center">TERJUAL</th>
                                        <th class="px-4 py-3 text-right">TOTAL OMZET</th>
                                        <th class="px-4 py-3 text-center">KONTRIBUSI</th>
                                        <th class="px-4 py-3 text-center">SISA STOK</th>
                                        <th class="px-4 py-3 text-center text-amber-700 font-bold">REKOMENDASI RESTOCK</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-gray-700">
                                    <template x-for="item in fastMovingData" :key="item.sku">
                                        <tr class="hover:bg-amber-50/20">
                                            <td class="px-4 py-3.5 text-center font-bold">
                                                <span class="w-6 h-6 rounded-full inline-flex items-center justify-center text-xs font-bold"
                                                      :class="item.rank <= 3 ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600'"
                                                      x-text="item.rank"></span>
                                            </td>
                                            <td class="px-4 py-3.5">
                                                <div class="font-bold text-gray-900" x-text="item.name"></div>
                                                <div class="font-mono text-[11px] text-gray-400" x-text="item.sku"></div>
                                            </td>
                                            <td class="px-4 py-3.5 text-gray-600" x-text="item.category"></td>
                                            <td class="px-4 py-3.5 text-center font-mono font-bold text-gray-900" x-text="item.total_qty_sold + ' ' + item.unit"></td>
                                            <td class="px-4 py-3.5 text-right font-mono font-bold text-emerald-600" x-text="formatRupiah(item.total_revenue)"></td>
                                            <td class="px-4 py-3.5 text-center font-bold text-xs" x-text="item.revenue_share_percent + '%'"></td>
                                            <td class="px-4 py-3.5 text-center">
                                                <span class="px-2 py-0.5 rounded text-[11px] font-bold font-mono"
                                                      :class="item.is_low_stock ? 'bg-red-100 text-red-700' : 'bg-emerald-50 text-emerald-700'"
                                                      x-text="item.current_stock + ' ' + item.unit"></span>
                                            </td>
                                            <td class="px-4 py-3.5 text-center">
                                                <template x-if="item.recommended_restock_qty > 0">
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10.5px] font-bold bg-amber-100 text-amber-800">
                                                        <span class="material-symbols-rounded text-[14px]">add_shopping_cart</span>
                                                        <span x-text="'Pesan +' + item.recommended_restock_qty + ' ' + item.unit"></span>
                                                    </span>
                                                </template>
                                                <template x-if="item.recommended_restock_qty <= 0">
                                                    <span class="text-gray-400 text-[11px]">Stok Aman</span>
                                                </template>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- 4. TAB: REKONSILIASI KAS SHIFT (MODUL E - CASH COUNT)     -->
                <!-- ========================================================= -->
                <div x-show="activeTab === 'cash_reconciliation'" class="space-y-6" style="display: none;">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-3 border-b border-gray-200">
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-[19px] font-black text-gray-900 tracking-tight">Laporan Rekonsiliasi Kas (Cash Count / Shift Closing)</h1>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">Audit Serah Terima Kasir</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Pencocokan jumlah uang fisik di laci kasir (termasuk modal awal) dengan hitungan sistem sebelum penggantian shift.</p>
                        </div>

                        <div class="flex items-center gap-2">
                            <select x-model="reconStatus" @change="loadCashReconciliation()" class="bg-white border rounded-xl px-3 py-1.5 text-xs font-bold text-gray-700">
                                <option value="all">Semua Status Selisih</option>
                                <option value="SESUAI">Sesuai (0 Selisih)</option>
                                <option value="KURANG">Kas Kurang (Defisit)</option>
                                <option value="LEBIH">Kas Lebih (Surplus)</option>
                            </select>
                        </div>
                    </div>

                    <!-- 4 Reconciliation Summary Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">TOTAL SHIFT DIAUDIT</span>
                            <div class="mt-2">
                                <h3 class="text-[22px] font-mono font-black text-gray-900" x-text="reconData?.summary?.total_audited_shifts || 0">0</h3>
                                <p class="text-[11px] text-gray-400 font-mono mt-0.5">Sesi Kasir Selesai Ditutup</p>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">TINGKAT AKURASI KAS</span>
                            <div class="mt-2">
                                <h3 class="text-[22px] font-mono font-black text-emerald-600" x-text="(reconData?.summary?.accuracy_rate_percent || 100) + '%'">100%</h3>
                                <p class="text-[11px] text-emerald-700 font-bold mt-0.5" x-text="(reconData?.summary?.exact_matches || 0) + ' Shift Sesuai Presisi'"></p>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">TOTAL UANG FISIK LACI</span>
                            <div class="mt-2">
                                <h3 class="text-[22px] font-mono font-black text-indigo-700" x-text="formatRupiah(reconData?.summary?.total_actual_cash || 0)">Rp 0</h3>
                                <p class="text-[11px] text-gray-400 font-mono mt-0.5">Uang Tunai Hasil Hitungan</p>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">NET SELISIH KAS</span>
                            <div class="mt-2">
                                <h3 class="text-[22px] font-mono font-black"
                                    :class="(reconData?.summary?.net_variance || 0) < 0 ? 'text-red-600' : 'text-gray-900'"
                                    x-text="formatRupiah(reconData?.summary?.net_variance || 0)">Rp 0</h3>
                                <p class="text-[11px] text-gray-400 font-mono mt-0.5"
                                   x-text="(reconData?.summary?.shortage_count || 0) + ' Kasus Kurang, ' + (reconData?.summary?.overage_count || 0) + ' Kasus Lebih'"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Cash Reconciliation Table -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-xs space-y-4">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs min-w-[900px] border-collapse">
                                <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 text-[11px] font-bold uppercase">
                                    <tr>
                                        <th class="px-4 py-3">KODE SHIFT</th>
                                        <th class="px-4 py-3">TERMINAL</th>
                                        <th class="px-4 py-3">KASIR</th>
                                        <th class="px-4 py-3 text-right">MODAL AWAL</th>
                                        <th class="px-4 py-3 text-right">PENJUALAN TUNAI</th>
                                        <th class="px-4 py-3 text-right font-bold">ESTIMASI SISTEM</th>
                                        <th class="px-4 py-3 text-right font-bold text-indigo-700">FISIK LACI</th>
                                        <th class="px-4 py-3 text-right">SELISIH</th>
                                        <th class="px-4 py-3 text-center">STATUS</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-gray-700">
                                    <template x-for="r in (reconData?.data || [])" :key="r.id">
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-4 py-3.5 font-mono text-gray-500" x-text="r.shift_code"></td>
                                            <td class="px-4 py-3.5 font-mono font-bold" x-text="r.terminal_code"></td>
                                            <td class="px-4 py-3.5 font-bold text-gray-900" x-text="r.cashier_name"></td>
                                            <td class="px-4 py-3.5 text-right font-mono" x-text="formatRupiah(r.opening_cash)"></td>
                                            <td class="px-4 py-3.5 text-right font-mono text-emerald-600" x-text="formatRupiah(r.cash_sales_total)"></td>
                                            <td class="px-4 py-3.5 text-right font-mono font-bold text-gray-800" x-text="formatRupiah(r.expected_cash)"></td>
                                            <td class="px-4 py-3.5 text-right font-mono font-bold text-indigo-700" x-text="formatRupiah(r.actual_cash)"></td>
                                            <td class="px-4 py-3.5 text-right font-mono font-bold"
                                                :class="r.variance < 0 ? 'text-red-600' : (r.variance > 0 ? 'text-amber-600' : 'text-gray-400')"
                                                x-text="(r.variance > 0 ? '+' : '') + formatRupiah(r.variance)"></td>
                                            <td class="px-4 py-3.5 text-center">
                                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border"
                                                      :class="r.variance_status === 'SESUAI' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : (r.variance_status === 'LEBIH' ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-red-50 text-red-700 border-red-200')"
                                                      x-text="r.variance_status"></span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- 5. TAB: PRODUK KENA PAJAK / NON-TAX (MODUL E)             -->
                <!-- ========================================================= -->
                <div x-show="activeTab === 'product_taxes'" class="space-y-6" style="display: none;">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-3 border-b border-gray-200">
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-[19px] font-black text-gray-900 tracking-tight">Pengaturan Pajak Produk (Tax / Non-Tax Products)</h1>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-teal-700 border border-teal-200">Modul E: PPN Otomatis</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Pengelompokan barang Kena Pajak (PPN 11%) vs Tidak Kena Pajak (Sembako Bebas PPN) serta kalkulasi otomatis saat kasir checkout.</p>
                        </div>

                        <div class="flex items-center gap-2">
                            <input type="text" x-model="taxSearch" @input.debounce.300ms="loadTaxProducts()" placeholder="Cari nama produk / barcode..."
                                   class="bg-white border rounded-xl px-3 py-1.5 text-xs text-gray-700">

                            <select x-model="taxableFilter" @change="loadTaxProducts()" class="bg-white border rounded-xl px-3 py-1.5 text-xs font-bold text-gray-700">
                                <option value="all">Semua Status Pajak</option>
                                <option value="1">Kena Pajak PPN (Taxable)</option>
                                <option value="0">Bebas Pajak (Non-Tax)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Tax Status Table -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-xs space-y-4">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs min-w-[800px] border-collapse">
                                <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 text-[11px] font-bold uppercase">
                                    <tr>
                                        <th class="px-5 py-3">BARCODE &amp; SKU</th>
                                        <th class="px-5 py-3">NAMA PRODUK</th>
                                        <th class="px-5 py-3">KATEGORI</th>
                                        <th class="px-5 py-3 text-right">HARGA JUAL</th>
                                        <th class="px-5 py-3 text-center">STATUS PPN</th>
                                        <th class="px-5 py-3 text-center">TARIF PPN</th>
                                        <th class="px-5 py-3 text-center">AKSI TOGGLE</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-gray-700">
                                    <template x-for="p in taxProductList" :key="p.id">
                                        <tr class="hover:bg-teal-50/20">
                                            <td class="px-5 py-3.5 font-mono">
                                                <div class="font-bold text-gray-800" x-text="p.barcode"></div>
                                                <div class="text-[10.5px] text-gray-400" x-text="p.sku"></div>
                                            </td>
                                            <td class="px-5 py-3.5 font-bold text-gray-900" x-text="p.name"></td>
                                            <td class="px-5 py-3.5 text-gray-600" x-text="p.category"></td>
                                            <td class="px-5 py-3.5 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(p.price)"></td>
                                            <td class="px-5 py-3.5 text-center">
                                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border"
                                                      :class="p.is_taxable ? 'bg-teal-50 text-teal-800 border-teal-200' : 'bg-gray-100 text-gray-600 border-gray-200'"
                                                      x-text="p.is_taxable ? 'KENA PAJAK' : 'BEBAS PAJAK'"></span>
                                            </td>
                                            <td class="px-5 py-3.5 text-center font-mono font-bold" x-text="(p.is_taxable ? (p.tax_rate || 11) : 0) + '%'"></td>
                                            <td class="px-5 py-3.5 text-center">
                                                <button type="button" @click="toggleProductTax(p)"
                                                        class="px-3 py-1 rounded-lg text-xs font-bold transition-all border cursor-pointer"
                                                        :class="p.is_taxable ? 'bg-red-50 text-red-600 border-red-200 hover:bg-red-100' : 'bg-teal-50 text-teal-700 border-teal-200 hover:bg-teal-100'"
                                                        x-text="p.is_taxable ? 'Jadikan Bebas Pajak' : 'Set Kena Pajak'"></button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- 6. TAB: PIUTANG ANGGOTA KASBON (MODUL B)                  -->
                <!-- ========================================================= -->
                <div x-show="activeTab === 'payroll_debt'" class="space-y-6" style="display: none;">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-3 border-b border-gray-200">
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-[19px] font-black text-gray-900 tracking-tight">Laporan Piutang Kasbon &amp; Potong Gaji (Payroll Debt)</h1>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200">PT Indo Lampung Perkasa</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Rekapitulasi piutang kasbon bulanan karyawan untuk diserahkan ke Tim Finance &amp; HRD.</p>
                        </div>

                        <div class="flex items-center gap-2">
                            <input type="month" x-model="month" @change="loadDebtReport()" class="bg-white border rounded-xl px-3 py-1.5 text-xs font-bold text-gray-700">
                            <a :href="'/api/reports/member-debts/export-excel?month=' + month" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 shadow-xs transition-colors">
                                <span class="material-symbols-rounded text-[18px]">table_chart</span>
                                <span>Ekspor Excel (.xlsx)</span>
                            </a>
                            <a :href="'/api/reports/member-debts/export-csv?month=' + month" class="px-3 py-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 font-bold rounded-xl text-xs flex items-center gap-1 shadow-xs transition-colors">
                                <span>CSV</span>
                            </a>
                        </div>
                    </div>

                    <!-- 4 Debt KPI Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs">
                            <span class="text-[11px] font-bold text-gray-500 uppercase">TOTAL KASBON BERJALAN</span>
                            <h3 class="text-[22px] font-mono font-black text-amber-600 mt-2" x-text="formatRupiah(debtSummary.total_active_debt)">Rp 0</h3>
                        </div>
                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs">
                            <span class="text-[11px] font-bold text-gray-500 uppercase">TOTAL PLAFON KREDIT</span>
                            <h3 class="text-[22px] font-mono font-black text-gray-900 mt-2" x-text="formatRupiah(debtSummary.total_credit_limit)">Rp 0</h3>
                        </div>
                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs">
                            <span class="text-[11px] font-bold text-gray-500 uppercase">SISA PLAFON AMAN</span>
                            <h3 class="text-[22px] font-mono font-black text-emerald-600 mt-2" x-text="formatRupiah(debtSummary.total_remaining_credit)">Rp 0</h3>
                        </div>
                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs">
                            <span class="text-[11px] font-bold text-gray-500 uppercase">UTILISASI KREDIT</span>
                            <h3 class="text-[22px] font-mono font-black text-purple-700 mt-2" x-text="debtSummary.overall_utilization_percent + '%'">0%</h3>
                        </div>
                    </div>
                <!-- ============================================================== -->
                <!-- TAB 7: KONSOLIDASI 3 CABANG & P&L (AKSES OWNER & SUPERADMIN) -->
                <!-- ============================================================== -->
                <div x-show="activeTab === 'consolidated_3branches'" class="space-y-6" style="display: none;">
                    <!-- Filter & Executive Controls -->
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-3 border-b border-gray-200">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-purple-100 text-purple-900 border border-purple-300">
                                    AKSES KHUSUS OWNER &amp; SUPERADMIN
                                </span>
                                <span class="text-xs text-gray-500 font-medium">Real-Time P&amp;L Independen Kasir</span>
                            </div>
                            <h2 class="text-xl font-black text-gray-900 tracking-tight mt-1">Konsolidasi Kinerja Keuangan 3 Cabang</h2>
                        </div>

                        <!-- Date & Branch Filter Form -->
                        <div class="flex flex-wrap items-center gap-2.5">
                            <input type="date" x-model="consolidatedStartDate" @change="loadConsolidatedReport()"
                                   class="px-3 py-2 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 focus:outline-none">
                            <span class="text-xs text-gray-400 font-bold">s/d</span>
                            <input type="date" x-model="consolidatedEndDate" @change="loadConsolidatedReport()"
                                   class="px-3 py-2 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 focus:outline-none">
                            <select x-model="consolidatedBranch" @change="loadConsolidatedReport()"
                                    class="px-3 py-2 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 focus:outline-none">
                                <option value="all">Semua Cabang (Konsolidasi)</option>
                                <option value="Pusat">Minimarket Pusat</option>
                                <option value="Cabang 1 - Mill / Pabrik">Cabang 1 - Mill / Pabrik</option>
                                <option value="Cabang 2 - Kebun Barat">Cabang 2 - Kebun Barat</option>
                            </select>
                            <button type="button" @click="loadConsolidatedReport()"
                                    class="px-3.5 py-2 bg-purple-900 hover:bg-purple-950 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 transition-colors cursor-pointer">
                                <span class="material-symbols-rounded text-[16px]" :class="isLoadingConsolidated ? 'animate-spin' : ''">refresh</span>
                                <span>Sinkronisasi</span>
                            </button>
                        </div>
                    </div>

                    <!-- 4 Executive KPI Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                        <!-- KPI 1: Gross Revenue -->
                        <div class="bg-gradient-to-br from-white to-purple-50/40 rounded-2xl border border-purple-200/80 p-5 shadow-xs flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-extrabold text-purple-900 uppercase tracking-wider">OMZET KONSOLIDASI</span>
                                <span class="material-symbols-rounded text-purple-700 text-[20px]">payments</span>
                            </div>
                            <div class="mt-3">
                                <h3 class="text-[22px] font-mono font-black text-gray-900" x-text="formatRupiah(consolidatedData.financial_summary?.gross_revenue)">Rp 0</h3>
                                <p class="text-[11px] text-gray-500 mt-0.5" x-text="(consolidatedData.financial_summary?.transaction_count || 0) + ' Transaksi tervalidasi'"></p>
                            </div>
                        </div>

                        <!-- KPI 2: Total COGS (HPP) -->
                        <div class="bg-gradient-to-br from-white to-rose-50/40 rounded-2xl border border-rose-200/80 p-5 shadow-xs flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-extrabold text-rose-800 uppercase tracking-wider">TOTAL HPP (COGS)</span>
                                <span class="material-symbols-rounded text-rose-600 text-[20px]">inventory_2</span>
                            </div>
                            <div class="mt-3">
                                <h3 class="text-[22px] font-mono font-black text-rose-700" x-text="formatRupiah(consolidatedData.financial_summary?.total_cogs)">Rp 0</h3>
                                <p class="text-[11px] text-gray-500 mt-0.5">Biaya Pokok Penjualan Riil</p>
                            </div>
                        </div>

                        <!-- KPI 3: Net Profit -->
                        <div class="bg-gradient-to-br from-white to-emerald-50/40 rounded-2xl border border-emerald-200/80 p-5 shadow-xs flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-extrabold text-emerald-900 uppercase tracking-wider">LABA BERSIH (P&amp;L)</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800"
                                      x-text="(consolidatedData.financial_summary?.profit_margin_percent || 0) + '% Margin'"></span>
                            </div>
                            <div class="mt-3">
                                <h3 class="text-[22px] font-mono font-black text-emerald-600" x-text="formatRupiah(consolidatedData.financial_summary?.net_profit)">Rp 0</h3>
                                <p class="text-[11px] text-gray-500 mt-0.5" x-text="'Laba Kotor: ' + formatRupiah(consolidatedData.financial_summary?.gross_profit)"></p>
                            </div>
                        </div>

                        <!-- KPI 4: Stock Valuation -->
                        <div class="bg-gradient-to-br from-white to-blue-50/40 rounded-2xl border border-blue-200/80 p-5 shadow-xs flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-extrabold text-blue-900 uppercase tracking-wider">VALUASI ASET STOK</span>
                                <span class="material-symbols-rounded text-blue-600 text-[20px]">storefront</span>
                            </div>
                            <div class="mt-3">
                                <h3 class="text-[22px] font-mono font-black text-[#0052cc]" x-text="formatRupiah(consolidatedData.stock_valuation?.total_stock_asset_cost)">Rp 0</h3>
                                <p class="text-[11px] text-gray-500 mt-0.5" x-text="'Nilai Ritel: ' + formatRupiah(consolidatedData.stock_valuation?.total_stock_retail_value)"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Matriks Komparasi 3 Cabang -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-purple-50/30">
                            <div>
                                <h3 class="text-sm font-black text-gray-900">Performa Komparasi 3 Cabang (Pusat &amp; Unit Kebun)</h3>
                                <p class="text-[11px] text-gray-500">Omzet, Laba Kotor, dan Portofolio Piutang Kasbon Karyawan</p>
                            </div>
                            <span class="text-[11px] font-bold text-purple-900 bg-purple-100 px-3 py-1 rounded-full">
                                3 Unit Usaha Aktif
                            </span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-500 text-[11px] font-bold uppercase tracking-wider">
                                        <th class="py-3 px-6">Kode &amp; Cabang</th>
                                        <th class="py-3 px-6 text-right">Omzet Penjualan</th>
                                        <th class="py-3 px-6 text-center">Jumlah Struk</th>
                                        <th class="py-3 px-6 text-right">Laba Kotor</th>
                                        <th class="py-3 px-6 text-right">Piutang Anggota</th>
                                        <th class="py-3 px-6 text-right">Alokasi Stok Aset</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="(b, bidx) in (consolidatedData.branch_comparison || [])" :key="bidx">
                                        <tr class="hover:bg-purple-50/20 transition-colors">
                                            <td class="py-4 px-6 font-bold text-gray-900">
                                                <div class="flex items-center gap-2">
                                                    <span class="w-2 h-2 rounded-full bg-purple-600"></span>
                                                    <div>
                                                        <span x-text="b.branch_name"></span>
                                                        <span class="block text-[10px] font-mono text-gray-400" x-text="b.branch_code"></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-4 px-6 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(b.total_revenue)"></td>
                                            <td class="py-4 px-6 text-center font-mono font-bold text-gray-600" x-text="b.transaction_count + ' Struk'"></td>
                                            <td class="py-4 px-6 text-right font-mono font-bold text-emerald-600" x-text="formatRupiah(b.gross_profit)"></td>
                                            <td class="py-4 px-6 text-right font-mono font-bold text-amber-600" x-text="formatRupiah(b.member_debt)"></td>
                                            <td class="py-4 px-6 text-right font-mono font-bold text-blue-700" x-text="formatRupiah(b.stock_valuation_share)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Audit Koreksi Stok Harian oleh Supervisor -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-black text-gray-900">Audit Koreksi Stok Harian Supervisor (Loss Prevention)</h3>
                                <p class="text-[11px] text-gray-500">Pencatatan barang rusak, bocor, kadaluarsa, atau selisih rak oleh SPV / Manager</p>
                            </div>
                            <span class="text-[11px] font-bold text-gray-600 bg-gray-100 px-3 py-1 rounded-full">
                                Log Terenkripsi
                            </span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-500 text-[11px] font-bold uppercase tracking-wider">
                                        <th class="py-3 px-6">Waktu &amp; Tanggal</th>
                                        <th class="py-3 px-6">Cabang</th>
                                        <th class="py-3 px-6">Supervisor / Manager</th>
                                        <th class="py-3 px-6">Produk / SKU</th>
                                        <th class="py-3 px-6 text-center">Selisih Fisik</th>
                                        <th class="py-3 px-6">Alasan Koreksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="(c, cidx) in (consolidatedData.recent_stock_corrections || [])" :key="cidx">
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="py-3.5 px-6 font-mono text-[11px] text-gray-600" x-text="c.date"></td>
                                            <td class="py-3.5 px-6 font-bold text-gray-800" x-text="c.branch"></td>
                                            <td class="py-3.5 px-6 font-bold text-purple-900" x-text="c.supervisor_name"></td>
                                            <td class="py-3.5 px-6 font-semibold text-gray-900" x-text="c.product_name"></td>
                                            <td class="py-3.5 px-6 text-center font-mono font-bold"
                                                :class="c.variance_qty < 0 ? 'text-red-600' : 'text-emerald-600'"
                                                x-text="(c.variance_qty > 0 ? '+' : '') + c.variance_qty + ' Unit'"></td>
                                            <td class="py-3.5 px-6 text-gray-500" x-text="c.reason"></td>
                                        </tr>
                                    </template>
                                    <template x-if="!consolidatedData.recent_stock_corrections || consolidatedData.recent_stock_corrections.length === 0">
                                        <tr>
                                            <td colspan="6" class="py-8 text-center text-gray-400">
                                                Belum ada audit koreksi stok supervisor pada rentang tanggal ini.
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>


    <!-- Toast Notification -->
    <div x-show="showToast"
         class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-2xl shadow-xl border text-xs font-bold"
         :class="toastType === 'error' ? 'bg-red-900 text-white border-red-800' : 'bg-gray-900 text-white border-gray-800'"
         style="display: none;">
        <span class="material-symbols-rounded text-emerald-400" x-show="toastType === 'success'">check_circle</span>
        <span class="material-symbols-rounded text-red-400" x-show="toastType === 'error'">error</span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
