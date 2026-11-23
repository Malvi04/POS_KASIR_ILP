@extends('layouts.app')

@section('title', 'Pelaporan, Akuntansi Dasar & Konsolidasi - ILP POS')

@php
    $registeredCashiers = \App\Models\User::query()
        ->where(function ($q) {
            $q->whereRaw('LOWER(role) LIKE ?', ['%kasir%'])
              ->orWhereRaw('LOWER(role) LIKE ?', ['%cashier%']);
        })
        ->whereNotNull('username')
        ->where('username', '!=', '')
        ->orderBy('name')
        ->get(['id', 'name', 'username', 'nik', 'branch'])
        ->toArray();
@endphp

@section('content')
<div x-data="{
    activeTab: (new URLSearchParams(window.location.search).get('tab') || 'sales_accounting'), // 'sales_accounting' | 'profit_loss' | 'fast_moving' | 'cash_reconciliation' | 'product_taxes' | 'payroll_debt' | 'consolidated_3branches'

    // ==========================================
    // Unified Period & Reporting Controls (Per Hari, Per Bulan, Per Tahun)
    // ==========================================
    periodMode: 'monthly', // 'daily' | 'monthly' | 'yearly'
    selectedDate: '{{ date('Y-m-d') }}',
    selectedMonth: '{{ date('Y-m') }}',
    selectedYear: '{{ date('Y') }}',
    selectedBranch: 'all',
    showPrintModal: false,
    
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
        terminals_breakdown: [],
        cashiers_breakdown: [],
        branch_breakdown: [],
        daily_trends: [],
        recent_transactions: []
    },

    // ==========================================
    // 1B. Modul E: Laporan Penjualan Per Terminal State
    // ==========================================
    terminalPeriod: 'daily', // 'daily' | 'weekly' | 'monthly' | 'yearly'
    terminalDate: '{{ date('Y-m-d') }}',
    terminalMonth: '{{ date('Y-m') }}',
    terminalYear: '{{ date('Y') }}',
    terminalBranch: 'all',
    terminalSelected: 'all',
    isLoadingTerminal: false,
    terminalData: {
        summary: {},
        terminals_breakdown: []
    },

    get availableTerminalsForBranch() {
        const b = String(this.terminalBranch || 'all').toLowerCase();
        if (b.includes('pusat')) {
            return ['POS-01', 'POS-02', 'POS-03', 'POS-04', 'POS-05'];
        }
        if (b.includes('cabang a')) {
            return ['POS-06', 'POS-07', 'POS-08', 'POS-09', 'POS-10'];
        }
        if (b.includes('cabang b')) {
            return ['POS-11', 'POS-12', 'POS-13', 'POS-14', 'POS-15'];
        }
        return [
            'POS-01', 'POS-02', 'POS-03', 'POS-04', 'POS-05',
            'POS-06', 'POS-07', 'POS-08', 'POS-09', 'POS-10',
            'POS-11', 'POS-12', 'POS-13', 'POS-14', 'POS-15'
        ];
    },

    onTerminalBranchChange() {
        this.terminalSelected = 'all';
        this.loadTerminalReport();
    },

    // ==========================================
    // 1C. Modul E: Laporan Penjualan Per Akun Kasir State
    // ==========================================
    cashierPeriod: 'daily', // 'daily' | 'weekly' | 'monthly' | 'yearly'
    cashierDate: '{{ date('Y-m-d') }}',
    cashierMonth: '{{ date('Y-m') }}',
    cashierYear: '{{ date('Y') }}',
    cashierBranch: 'all',
    cashierSearch: '',
    cashierUsernameFilter: 'all',
    registeredCashiers: @js($registeredCashiers),
    isLoadingCashier: false,
    cashierData: {
        summary: {},
        cashiers_breakdown: []
    },

    get filteredRegisteredCashiers() {
        if (!this.cashierBranch || this.cashierBranch === 'all') {
            return this.registeredCashiers || [];
        }
        const b = String(this.cashierBranch).toLowerCase();
        return (this.registeredCashiers || []).filter(c => {
            if (!c.branch) return true;
            const cb = String(c.branch).toLowerCase();
            return cb.includes(b) || cb.includes('pusat');
        });
    },

    onCashierBranchChange() {
        // Reset cashier username if not available in current branch
        const availableUsernames = this.filteredRegisteredCashiers.map(c => c.username);
        if (this.cashierUsernameFilter !== 'all' && !availableUsernames.includes(this.cashierUsernameFilter)) {
            this.cashierUsernameFilter = 'all';
        }
        this.loadCashierReport();
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
            coupon_sales: 0,
            total_discounts: 0,
            net_sales: 0,
            regular_cogs: 0,
            coupon_cogs: 0,
            umkm_partner_share: 0,
            cooperative_commission_earned: 0,
            regular_gross_profit: 0,
            coupon_gross_profit: 0,
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
        'Cabang Pusat',
        'Cabang A',
        'Cabang B'
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
        const loaders = {
            sales_accounting: () => this.loadSalesReport(),
            sales_terminal: () => this.loadTerminalReport(),
            sales_cashier: () => this.loadCashierReport(),
            profit_loss: () => this.loadProfitLossReport(),
            fast_moving: () => this.loadFastMoving(),
            cash_reconciliation: () => this.loadCashReconciliation(),
            product_taxes: () => this.loadTaxProducts(),
            payroll_debt: () => this.loadDebtReport(),
            consolidated_3branches: () => this.loadConsolidatedReport()
        };
        const activeLoader = loaders[this.activeTab] || loaders.sales_accounting;
        await activeLoader();
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

    // 1B. Load Terminal Report API
    async loadTerminalReport() {
        this.isLoadingTerminal = true;
        try {
            const params = new URLSearchParams({
                period: this.terminalPeriod,
                branch: this.terminalBranch,
                date: this.terminalDate,
                month: this.terminalMonth,
                year: this.terminalYear,
                terminal_code: this.terminalSelected
            });
            const res = await fetch(`/api/reports/sales?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.terminalData = json;
                }
            }
        } catch (e) {
            console.error('Error fetching terminal report:', e);
            this.triggerToast('Gagal memuat laporan per terminal.', 'error');
        } finally {
            this.isLoadingTerminal = false;
        }
    },

    // 1C. Load Cashier Report API
    async loadCashierReport() {
        this.isLoadingCashier = true;
        try {
            const params = new URLSearchParams({
                period: this.cashierPeriod,
                branch: this.cashierBranch || 'all',
                date: this.cashierDate,
                month: this.cashierMonth,
                year: this.cashierYear,
                cashier_username: this.cashierUsernameFilter || 'all',
                search: this.cashierSearch
            });
            const res = await fetch(`/api/reports/sales?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.cashierData = json;
                    if (json.available_cashiers && Array.isArray(json.available_cashiers)) {
                        this.registeredCashiers = json.available_cashiers;
                    }
                }
            }
        } catch (e) {
            console.error('Error fetching cashier report:', e);
            this.triggerToast('Gagal memuat laporan per akun kasir.', 'error');
        } finally {
            this.isLoadingCashier = false;
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
    },

    syncPeriodDates() {
        if (this.periodMode === 'daily') {
            this.salesPeriod = 'daily';
            this.terminalPeriod = 'daily';
            this.cashierPeriod = 'daily';
            this.salesDate = this.selectedDate;
            this.terminalDate = this.selectedDate;
            this.cashierDate = this.selectedDate;
            this.plStartDate = this.selectedDate;
            this.plEndDate = this.selectedDate;
            this.consolidatedStartDate = this.selectedDate;
            this.consolidatedEndDate = this.selectedDate;
            this.month = this.selectedDate ? this.selectedDate.slice(0, 7) : '{{ date('Y-m') }}';
        } else if (this.periodMode === 'yearly') {
            this.salesPeriod = 'yearly';
            this.terminalPeriod = 'yearly';
            this.cashierPeriod = 'yearly';
            const y = this.selectedYear || '{{ date('Y') }}';
            this.terminalYear = y;
            this.cashierYear = y;
            this.plStartDate = y + '-01-01';
            this.plEndDate = y + '-12-31';
            this.consolidatedStartDate = this.plStartDate;
            this.consolidatedEndDate = this.plEndDate;
            this.month = y + '-01';
        } else { // monthly
            this.salesPeriod = 'monthly';
            this.terminalPeriod = 'monthly';
            this.cashierPeriod = 'monthly';
            this.salesMonth = this.selectedMonth || '{{ date('Y-m') }}';
            this.terminalMonth = this.selectedMonth || '{{ date('Y-m') }}';
            this.cashierMonth = this.selectedMonth || '{{ date('Y-m') }}';
            const parts = (this.selectedMonth || '{{ date('Y-m') }}').split('-');
            const yr = parseInt(parts[0], 10);
            const mo = parseInt(parts[1], 10);
            const lastDay = new Date(yr, mo, 0).getDate();
            this.plStartDate = this.selectedMonth + '-01';
            this.plEndDate = this.selectedMonth + '-' + String(lastDay).padStart(2, '0');
            this.consolidatedStartDate = this.plStartDate;
            this.consolidatedEndDate = this.plEndDate;
            this.month = this.selectedMonth;
        }
        this.salesBranch = this.selectedBranch;
        this.terminalBranch = this.selectedBranch;
        this.cashierBranch = this.selectedBranch;
        this.plBranch = this.selectedBranch;
        this.consolidatedBranch = this.selectedBranch;
    },

    async applyPeriodChange() {
        this.syncPeriodDates();
        await this.initReports();
        this.triggerToast('Data diperbarui untuk: ' + this.getPeriodDisplayLabel() + ' (' + (this.selectedBranch === 'all' ? 'Semua Cabang' : this.selectedBranch) + ')');
    },

    getPeriodDisplayLabel() {
        if (this.periodMode === 'daily') {
            try {
                const parts = this.selectedDate.split('-');
                const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
            } catch(e) { return this.selectedDate; }
        } else if (this.periodMode === 'yearly') {
            return 'Tahun ' + this.selectedYear;
        } else {
            try {
                const parts = this.selectedMonth.split('-');
                const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, 1);
                return d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
            } catch(e) { return this.selectedMonth; }
        }
    },

    getTabTitle() {
        const titles = {
            sales_accounting: 'Laporan Penjualan Cabang (Konsolidasi)',
            sales_terminal: 'Laporan Penjualan Berdasarkan Terminal Kasir',
            sales_cashier: 'Laporan Penjualan Berdasarkan Akun Kasir',
            profit_loss: 'Laporan Laba / Rugi Bersih (P&L)',
            fast_moving: 'Laporan Analisis Barang Terlaris & Restock',
            cash_reconciliation: 'Laporan Rekonsiliasi Kas Shift Kasir',
            product_taxes: 'Laporan Status Pajak PPN Produk',
            payroll_debt: 'Laporan Piutang Anggota & Kredit Potong Gaji',
            consolidated_3branches: 'Laporan Konsolidasi Finansial 3 Cabang'
        };
        return titles[this.activeTab] || 'Laporan POS ILP';
    },

    exportCurrentTabToExcel() {
        if (typeof window.exportTableToExcel !== 'function') {
            this.triggerToast('Library ekspor Excel sedang dimuat, coba sesaat lagi.', 'error');
            return;
        }
        const periodStr = this.getPeriodDisplayLabel();
        const branchStr = this.selectedBranch === 'all' ? 'Semua Cabang (Konsolidasi)' : this.selectedBranch;
        const baseMeta = [
            ['Organisasi', 'PT INDO LAMPUNG PERKASA - MINIMARKET KOPERASI'],
            ['Jenis Laporan', this.getTabTitle()],
            ['Periode', periodStr + ' (' + (this.periodMode === 'daily' ? 'Harian' : (this.periodMode === 'yearly' ? 'Tahunan' : 'Bulanan')) + ')'],
            ['Cabang / Unit', branchStr],
            ['Tanggal Cetak', new Date().toLocaleString('id-ID')],
            ['Otorisator / Dicetak Oleh', '{{ Auth::user()->name ?? "Manager" }} ({{ Auth::user()->role ?? "Manager" }})']
        ];

        const dateSuffix = this.periodMode === 'daily' ? this.selectedDate : (this.periodMode === 'yearly' ? this.selectedYear : this.selectedMonth);

        if (this.activeTab === 'sales_accounting') {
            const headers = ['No', 'Kode Cabang', 'Nama Cabang Outlet', 'Jumlah Transaksi', 'Total Penjualan (Rp)', 'Kontribusi (%)'];
            const rows = (this.salesData?.branch_breakdown || []).map((b, idx) => [
                idx + 1,
                b.branch_code || '-',
                b.branch_name || '-',
                Number(b.transaction_count || 0),
                Number(b.total_sales || 0),
                (b.contribution_percent || 0) + '%'
            ]);
            const totals = [
                'TOTAL OMZET',
                '',
                '',
                Number(this.salesData?.summary?.transaction_count || 0),
                Number(this.salesData?.summary?.grand_total || 0),
                '100%'
            ];

            window.exportTableToExcel({
                filename: `Laporan_Penjualan_Cabang_${dateSuffix}.xlsx`,
                sheetName: 'Penjualan Cabang',
                title: 'PT INDO LAMPUNG PERKASA - MINIMARKET KOPERASI',
                subtitle: 'LAPORAN REKAPITULASI PENJUALAN PER CABANG (' + periodStr + ')',
                metadata: baseMeta,
                headers: headers,
                rows: rows,
                totals: totals
            });
        } else if (this.activeTab === 'sales_terminal') {
            const headers = ['No', 'Terminal', 'Cabang Penugasan', 'Jumlah Transaksi', 'Penjualan Tunai (Rp)', 'Penjualan Non-Tunai (Rp)', 'Total Omzet (Rp)', 'Kontribusi (%)'];
            const rows = (this.terminalData?.terminals_breakdown || []).map((t, idx) => [
                idx + 1,
                t.terminal_code || '-',
                t.branch || '-',
                Number(t.transaction_count || 0),
                Number(t.cash_sales || 0),
                Number(t.non_cash_sales || 0),
                Number(t.total_sales || 0),
                (t.contribution_percent || 0) + '%'
            ]);
            const totals = [
                'TOTAL',
                '',
                '',
                Number(this.terminalData?.summary?.transaction_count || 0),
                Number(this.terminalData?.summary?.payment_breakdown?.tunai || 0),
                Number((this.terminalData?.summary?.grand_total || 0) - (this.terminalData?.summary?.payment_breakdown?.tunai || 0)),
                Number(this.terminalData?.summary?.grand_total || 0),
                '100%'
            ];

            window.exportTableToExcel({
                filename: `Laporan_Penjualan_Terminal_${dateSuffix}.xlsx`,
                sheetName: 'Terminal Kasir',
                title: 'PT INDO LAMPUNG PERKASA - MINIMARKET KOPERASI',
                subtitle: 'LAPORAN PENJUALAN BERDASARKAN TERMINAL KASIR (' + periodStr + ')',
                metadata: baseMeta,
                headers: headers,
                rows: rows,
                totals: totals
            });
        } else if (this.activeTab === 'sales_cashier') {
            const headers = ['No', 'Nama Kasir', 'Username', 'NIP / NIK', 'Cabang Penugasan', 'Jumlah Transaksi', 'Penjualan Tunai (Rp)', 'Penjualan Non-Tunai (Rp)', 'Rata-rata / Trx (Rp)', 'Total Omzet (Rp)', 'Kontribusi (%)'];
            const rows = (this.cashierData?.cashiers_breakdown || []).map((c, idx) => [
                idx + 1,
                c.cashier_name || '-',
                c.cashier_username || '-',
                c.cashier_nik || '-',
                c.branch || '-',
                Number(c.transaction_count || 0),
                Number(c.cash_sales || 0),
                Number(c.non_cash_sales || 0),
                Number(c.average_sales || 0),
                Number(c.total_sales || 0),
                (c.contribution_percent || 0) + '%'
            ]);
            const totals = [
                'TOTAL',
                '',
                '',
                '',
                '',
                Number(this.cashierData?.summary?.transaction_count || 0),
                Number(this.cashierData?.summary?.payment_breakdown?.tunai || 0),
                Number((this.cashierData?.summary?.grand_total || 0) - (this.cashierData?.summary?.payment_breakdown?.tunai || 0)),
                '',
                Number(this.cashierData?.summary?.grand_total || 0),
                '100%'
            ];

            window.exportTableToExcel({
                filename: `Laporan_Penjualan_Akun_Kasir_${dateSuffix}.xlsx`,
                sheetName: 'Akun Kasir',
                title: 'PT INDO LAMPUNG PERKASA - MINIMARKET KOPERASI',
                subtitle: 'LAPORAN PENJUALAN BERDASARKAN AKUN KASIR (' + periodStr + ')',
                metadata: baseMeta,
                headers: headers,
                rows: rows,
                totals: totals
            });
        } else if (this.activeTab === 'profit_loss') {
            const stmt = this.plData?.statement || {};
            const rev = Number(stmt.gross_sales || 0);
            const pct = v => (rev > 0 ? ((Number(v || 0) / rev) * 100).toFixed(1) + '%' : '0%');
            const headers = ['No', 'Komponen Akuntansi Laba Rugi', 'Nominal (Rp)', '% Terhadap Gross Sales', 'Kategori Pos'];
            const rows = [
                [1, 'Penjualan Produk Reguler Koperasi', Number(stmt.regular_product_sales || 0), pct(stmt.regular_product_sales), 'Pendapatan Pokok'],
                [2, 'Penjualan Produk Titipan Konsinyasi UMKM', Number(stmt.consignment_product_sales || 0), pct(stmt.consignment_product_sales), 'Titipan Mitra UMKM'],
                [3, 'Penjualan Kupon BBM & Voucher Operasional', Number(stmt.coupon_sales || 0), pct(stmt.coupon_sales), 'Kupon BBM / Voucher'],
                [4, 'TOTAL PENJUALAN KOTOR (GROSS SALES)', rev, '100%', 'Total Omzet Toko'],
                [5, '(-) Potongan / Diskon Promosi', Number(stmt.total_discounts || 0), pct(stmt.total_discounts), 'Beban Promosi'],
                [6, 'TOTAL PENJUALAN BERSIH (NET SALES)', Number(stmt.net_sales || 0), pct(stmt.net_sales), 'Penjualan Bersih Kasir'],
                [7, '(-) Harga Pokok Penjualan (HPP Reguler)', Number(stmt.regular_cogs || 0), pct(stmt.regular_cogs), 'Biaya Pokok Barang'],
                [8, '(-) Hak Setoran Hasil Penjualan ke Mitra UMKM', Number(stmt.umkm_partner_share || 0), pct(stmt.umkm_partner_share), 'Kewajiban Hutang UMKM'],
                [9, '(+) Komisi Koperasi dari Konsinyasi UMKM', Number(stmt.cooperative_commission_earned || 0), pct(stmt.cooperative_commission_earned), 'Pendapatan Komisi Koperasi'],
                [10, 'TOTAL LABA KOTOR (GROSS PROFIT)', Number(stmt.total_gross_profit || 0), pct(stmt.total_gross_profit), 'Laba Kotor Usaha'],
                [11, 'Pajak Pertambahan Nilai (PPN 11%) Terkumpul', Number(stmt.collected_tax_ppn || 0), pct(stmt.collected_tax_ppn), 'Titipan Kas Negara'],
                [12, 'LABA BERSIH OPERASIONAL (NET PROFIT)', Number(stmt.net_profit || 0), (stmt.profit_margin_percent || 0) + '%', 'Laba Bersih Akhir']
            ];

            window.exportTableToExcel({
                filename: `Laporan_Laba_Rugi_${this.periodMode}_${dateSuffix}.xlsx`,
                sheetName: 'Laba Rugi',
                title: 'PT INDO LAMPUNG PERKASA - MINIMARKET KOPERASI',
                subtitle: 'LAPORAN LABA RUGI KOMPREHENSIF (' + periodStr + ')',
                metadata: baseMeta,
                headers: headers,
                rows: rows
            });
        } else if (this.activeTab === 'fast_moving') {
            const headers = ['Rank', 'Kode SKU / Barcode', 'Nama Produk', 'Kategori', 'Qty Terjual', 'Satuan', 'Total Omzet (Rp)', 'Pangsa Omzet (%)', 'Sisa Stok Saat Ini', 'Batas Min. Stok', 'Status Kecepatan', 'Rekomendasi Restock'];
            const rows = (this.fastMovingData || []).map(item => [
                Number(item.rank || 0),
                item.sku || '-',
                item.name || '-',
                item.category || 'Umum',
                Number(item.total_qty_sold || 0),
                item.unit || 'Pcs',
                Number(item.total_revenue || 0),
                Number(item.revenue_share_percent || 0) + '%',
                Number(item.current_stock || 0),
                Number(item.min_stock || 0),
                item.velocity_status || 'FAST MOVING',
                item.recommended_restock_qty > 0 ? Number(item.recommended_restock_qty) : 0
            ]);
            const totals = [
                'TOTAL',
                '',
                '',
                '',
                rows.reduce((sum, r) => sum + (Number(r[4]) || 0), 0),
                '',
                Number(this.fastMovingTotalRevenue || 0),
                '100%',
                '',
                '',
                '',
                ''
            ];

            window.exportTableToExcel({
                filename: `Laporan_Barang_Terlaris_${this.periodMode}_${dateSuffix}.xlsx`,
                sheetName: 'Barang Terlaris',
                title: 'PT INDO LAMPUNG PERKASA - MINIMARKET KOPERASI',
                subtitle: 'LAPORAN ANALISIS BARANG TERLARIS & RESTOCK (' + periodStr + ')',
                metadata: baseMeta,
                headers: headers,
                rows: rows,
                totals: totals
            });
        } else if (this.activeTab === 'cash_reconciliation') {
            const headers = ['Kode Shift', 'Terminal', 'Nama Kasir', 'Modal Awal (Rp)', 'Penjualan Tunai (Rp)', 'Saldo Sistem (Rp)', 'Fisik Laci Kasir (Rp)', 'Selisih Kas (Rp)', 'Status Rekonsiliasi'];
            const rows = (this.reconData?.data || []).map(r => [
                r.shift_code || '-',
                r.terminal_code || '-',
                r.cashier_name || '-',
                Number(r.opening_cash || 0),
                Number(r.cash_sales_total || 0),
                Number(r.expected_cash || 0),
                Number(r.actual_cash || 0),
                Number(r.variance || 0),
                r.variance_status || '-'
            ]);
            const totals = [
                'TOTAL',
                '',
                '',
                '',
                '',
                Number(this.reconData?.summary?.total_expected_cash || 0),
                Number(this.reconData?.summary?.total_actual_cash || 0),
                Number(this.reconData?.summary?.net_variance || 0),
                'Akurasi: ' + (this.reconData?.summary?.accuracy_rate_percent || 100) + '%'
            ];

            window.exportTableToExcel({
                filename: `Laporan_Rekonsiliasi_Kas_${this.periodMode}_${dateSuffix}.xlsx`,
                sheetName: 'Rekonsiliasi Kas',
                title: 'PT INDO LAMPUNG PERKASA - MINIMARKET KOPERASI',
                subtitle: 'LAPORAN REKONSILIASI KAS SHIFT KASIR (' + periodStr + ')',
                metadata: baseMeta,
                headers: headers,
                rows: rows,
                totals: totals
            });
        } else if (this.activeTab === 'product_taxes') {
            const headers = ['No', 'Barcode', 'Kode SKU', 'Nama Produk', 'Kategori', 'Harga Jual (Rp)', 'Status Kena Pajak', 'Tarif PPN (%)'];
            let no = 1;
            const rows = (this.taxProductList || []).map(p => [
                no++,
                p.barcode || '-',
                p.sku || '-',
                p.name || '-',
                p.category || 'Umum',
                Number(p.price || 0),
                p.is_taxable ? 'KENA PAJAK' : 'BEBAS PAJAK',
                Number(p.is_taxable ? (p.tax_rate || 11) : 0) + '%'
            ]);

            window.exportTableToExcel({
                filename: `Laporan_Status_Pajak_Produk_${new Date().toISOString().slice(0, 10)}.xlsx`,
                sheetName: 'Status Pajak PPN',
                title: 'PT INDO LAMPUNG PERKASA - MINIMARKET KOPERASI',
                subtitle: 'LAPORAN STATUS PPN 11% MASTER PRODUK',
                metadata: baseMeta,
                headers: headers,
                rows: rows
            });
        } else if (this.activeTab === 'payroll_debt') {
            const headers = ['No', 'Nama Anggota', 'NIK / No Anggota', 'Departemen / Divisi', 'Cabang', 'Plafon Kredit (Rp)', 'Total Kasbon Berjalan (Rp)', 'Sisa Plafon (Rp)', 'Utilisasi (%)', 'Status'];
            let no = 1;
            const rows = (this.debtRows || []).map(d => [
                no++,
                d.name || '-',
                d.nik || d.member_code || '-',
                d.department || '-',
                d.branch || '-',
                Number(d.credit_limit || 0),
                Number(d.total_debt || 0),
                Number(d.remaining_credit || 0),
                Number(d.utilization_percent || 0) + '%',
                d.status || 'Aktif'
            ]);
            const totals = [
                'TOTAL',
                '',
                '',
                '',
                '',
                Number(this.debtSummary?.total_credit_limit || 0),
                Number(this.debtSummary?.total_active_debt || 0),
                Number(this.debtSummary?.total_remaining_credit || 0),
                (this.debtSummary?.overall_utilization_percent || 0) + '%',
                ''
            ];

            window.exportTableToExcel({
                filename: `Laporan_Piutang_Payroll_${this.periodMode}_${dateSuffix}.xlsx`,
                sheetName: 'Piutang Anggota',
                title: 'PT INDO LAMPUNG PERKASA - MINIMARKET KOPERASI',
                subtitle: 'LAPORAN PIUTANG ANGGOTA & KREDIT GAJI (' + periodStr + ')',
                metadata: baseMeta,
                headers: headers,
                rows: rows,
                totals: totals
            });
        } else if (this.activeTab === 'consolidated_3branches') {
            const headers = ['No', 'Kode Cabang', 'Nama Cabang', 'Jumlah Transaksi', 'Omzet Penjualan (Rp)', 'Laba Kotor Cabang (Rp)', 'Piutang Anggota (Rp)', 'Valuasi Stok Toko (Rp)'];
            let no = 1;
            const rows = (this.consolidatedData?.branch_comparison || []).map(b => [
                no++,
                b.branch_code || '-',
                b.branch_name || '-',
                Number(b.transaction_count || 0),
                Number(b.total_revenue || 0),
                Number(b.gross_profit || 0),
                Number(b.member_debt || 0),
                Number(b.stock_valuation_share || 0)
            ]);
            const totals = [
                'TOTAL KONSOLIDASI',
                '',
                '',
                rows.reduce((sum, r) => sum + (Number(r[3]) || 0), 0),
                rows.reduce((sum, r) => sum + (Number(r[4]) || 0), 0),
                rows.reduce((sum, r) => sum + (Number(r[5]) || 0), 0),
                rows.reduce((sum, r) => sum + (Number(r[6]) || 0), 0),
                rows.reduce((sum, r) => sum + (Number(r[7]) || 0), 0)
            ];

            window.exportTableToExcel({
                filename: `Laporan_Konsolidasi_3Cabang_${this.periodMode}_${dateSuffix}.xlsx`,
                sheetName: 'Konsolidasi 3 Cabang',
                title: 'PT INDO LAMPUNG PERKASA - MINIMARKET KOPERASI',
                subtitle: 'LAPORAN KONSOLIDASI FINANSIAL 3 CABANG (' + periodStr + ')',
                metadata: baseMeta,
                headers: headers,
                rows: rows,
                totals: totals
            });
        }
        this.triggerToast('File Excel ' + this.getTabTitle() + ' berhasil diunduh.');
    },

    async openPrintPdfModal() {
        if (this.activeTab === 'profit_loss' && (!this.plData || !this.plData.statement)) {
            await this.loadProfitLossReport();
        }
        this.showPrintModal = true;
    },

    getPdfPeriodLabel() {
        if (this.activeTab === 'profit_loss') {
            return (this.plStartDate || '-') + ' s/d ' + (this.plEndDate || '-');
        }
        if (this.activeTab === 'consolidated_3branches') {
            return (this.consolidatedStartDate || '-') + ' s/d ' + (this.consolidatedEndDate || '-');
        }
        return this.getPeriodDisplayLabel();
    },

    getPdfBranchLabel() {
        const b = this.activeTab === 'profit_loss'
            ? this.plBranch
            : (this.activeTab === 'sales_accounting'
                ? this.salesBranch
                : (this.activeTab === 'consolidated_3branches' ? this.consolidatedBranch : this.selectedBranch));
        return (!b || b === 'all') ? 'Semua Cabang (Konsolidasian)' : b;
    },

    getPlRatio(val) {
        const gross = Number(this.plData?.statement?.gross_sales || 0);
        if (gross <= 0) return '0.0%';
        return ((Number(val || 0) / gross) * 100).toFixed(1) + '%';
    },

    printOfficialDocument() {
        window.print();
    }
}"
x-init="initReports()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">
    
    <!-- Sidebar -->
    <x-manager-sidebar active="reports" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden relative">
        
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
                        <span>1. Penjualan Cabang</span>
                    </button>

                    <!-- Tab 2: Laporan Penjualan Per Terminal -->
                    <button type="button"
                            @click="activeTab = 'sales_terminal'; loadTerminalReport()"
                            :class="activeTab === 'sales_terminal' ? 'bg-white text-[#0052cc] shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                            class="px-3.5 py-2 rounded-lg text-xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-rounded text-[17px]">point_of_sale</span>
                        <span>2. Penjualan Terminal Kasir</span>
                    </button>

                    <!-- Tab 3: Laporan Penjualan Per Akun Kasir -->
                    <button type="button"
                            @click="activeTab = 'sales_cashier'; loadCashierReport()"
                            :class="activeTab === 'sales_cashier' ? 'bg-white text-emerald-700 shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                            class="px-3.5 py-2 rounded-lg text-xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-rounded text-[17px]">badge</span>
                        <span>3. Penjualan Akun Kasir</span>
                    </button>

                    <!-- Tab 4: Laporan Laba/Rugi (Modul E) -->
                    <button type="button"
                            @click="activeTab = 'profit_loss'; loadProfitLossReport()"
                            :class="activeTab === 'profit_loss' ? 'bg-white text-emerald-700 shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                            class="px-3.5 py-2 rounded-lg text-xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-rounded text-[17px]">account_balance</span>
                        <span>4. Laba / Rugi Bersih (P&amp;L)</span>
                    </button>

                    <!-- Tab 5: Barang Terlaris (Modul E) -->
                    <button type="button"
                            @click="activeTab = 'fast_moving'; loadFastMoving()"
                            :class="activeTab === 'fast_moving' ? 'bg-white text-amber-700 shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                            class="px-3.5 py-2 rounded-lg text-xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-rounded text-[17px]">local_fire_department</span>
                        <span>5. Barang Terlaris &amp; Restock</span>
                    </button>

                    <!-- Tab 6: Rekonsiliasi Kas (Modul E) -->
                    <button type="button"
                            @click="activeTab = 'cash_reconciliation'; loadCashReconciliation()"
                            :class="activeTab === 'cash_reconciliation' ? 'bg-white text-indigo-700 shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                            class="px-3.5 py-2 rounded-lg text-xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-rounded text-[17px]">price_check</span>
                        <span>6. Rekonsiliasi Kas Shift</span>
                    </button>

                    <!-- Tab 7: Pajak Produk (Modul E) -->
                    <button type="button"
                            @click="activeTab = 'product_taxes'; loadTaxProducts()"
                            :class="activeTab === 'product_taxes' ? 'bg-white text-teal-700 shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                            class="px-3.5 py-2 rounded-lg text-xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-rounded text-[17px]">receipt</span>
                        <span>7. Produk Kena Pajak (PPN)</span>
                    </button>

                    <!-- Tab 8: Piutang Anggota (Modul B) -->
                    <button type="button"
                            @click="activeTab = 'payroll_debt'; loadDebtReport()"
                            :class="activeTab === 'payroll_debt' ? 'bg-white text-purple-700 shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                            class="px-3.5 py-2 rounded-lg text-xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-rounded text-[17px]">credit_card</span>
                        <span>8. Piutang Anggota (Payroll)</span>
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
                <x-realtime-network-badge />
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-6 lg:p-8 pb-10">
            <div class="max-w-[1400px] mx-auto space-y-6">

                <!-- Global Period, Branch & Export Toolbar (Harian, Bulanan, Tahunan & Excel/PDF) -->
                <div class="bg-white border border-gray-200 rounded-2xl p-4 lg:p-5 shadow-xs flex flex-wrap items-center justify-between gap-4">
                    <!-- Left: Period Switcher & Date Input & Branch Filter -->
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-black text-gray-500 uppercase tracking-wider">Periode:</span>
                            <!-- Period Mode Toggle Pills: Per Hari | Per Bulan | Per Tahun -->
                            <div class="inline-flex p-1 bg-gray-100 rounded-xl text-xs font-bold shadow-2xs">
                                <button type="button"
                                        @click="periodMode = 'daily'; applyPeriodChange()"
                                        :class="periodMode === 'daily' ? 'bg-[#0052cc] text-white shadow-xs' : 'text-gray-600 hover:text-gray-900'"
                                        class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1 cursor-pointer">
                                    <span class="material-symbols-rounded text-[15px]">today</span>
                                    <span>Per Hari</span>
                                </button>
                                <button type="button"
                                        @click="periodMode = 'monthly'; applyPeriodChange()"
                                        :class="periodMode === 'monthly' ? 'bg-[#0052cc] text-white shadow-xs' : 'text-gray-600 hover:text-gray-900'"
                                        class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1 cursor-pointer">
                                    <span class="material-symbols-rounded text-[15px]">calendar_month</span>
                                    <span>Per Bulan</span>
                                </button>
                                <button type="button"
                                        @click="periodMode = 'yearly'; applyPeriodChange()"
                                        :class="periodMode === 'yearly' ? 'bg-[#0052cc] text-white shadow-xs' : 'text-gray-600 hover:text-gray-900'"
                                        class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1 cursor-pointer">
                                    <span class="material-symbols-rounded text-[15px]">date_range</span>
                                    <span>Per Tahun</span>
                                </button>
                            </div>
                        </div>

                        <!-- Dynamic Date/Month/Year Input -->
                        <div class="flex items-center gap-2">
                            <template x-if="periodMode === 'daily'">
                                <input type="date"
                                       x-model="selectedDate"
                                       @change="applyPeriodChange()"
                                       class="bg-gray-50 hover:bg-white border border-gray-200 focus:border-[#0052cc] rounded-xl px-3 py-1.5 text-xs font-bold text-gray-800 outline-none shadow-2xs transition-colors cursor-pointer" />
                            </template>
                            <template x-if="periodMode === 'monthly'">
                                <input type="month"
                                       x-model="selectedMonth"
                                       @change="applyPeriodChange()"
                                       class="bg-gray-50 hover:bg-white border border-gray-200 focus:border-[#0052cc] rounded-xl px-3 py-1.5 text-xs font-bold text-gray-800 outline-none shadow-2xs transition-colors cursor-pointer" />
                            </template>
                            <template x-if="periodMode === 'yearly'">
                                <select x-model="selectedYear"
                                        @change="applyPeriodChange()"
                                        class="bg-gray-50 hover:bg-white border border-gray-200 focus:border-[#0052cc] rounded-xl px-3.5 py-1.5 text-xs font-bold text-gray-800 outline-none shadow-2xs transition-colors cursor-pointer">
                                    <option value="2024">Tahun 2024</option>
                                    <option value="2025">Tahun 2025</option>
                                    <option value="2026">Tahun 2026</option>
                                    <option value="2027">Tahun 2027</option>
                                </select>
                            </template>
                        </div>

                        <!-- Branch Filter (Konsolidasi Multi-Outlet) -->
                        <div class="relative">
                            <select x-model="selectedBranch"
                                    @change="applyPeriodChange()"
                                    class="bg-gray-50 hover:bg-white border border-gray-200 focus:border-[#0052cc] rounded-xl pl-3 pr-8 py-1.5 text-xs font-bold text-gray-800 outline-none shadow-2xs transition-colors cursor-pointer">
                                <option value="all">Semua Cabang (Konsolidasi)</option>
                                <option value="Pusat">Cabang Pusat</option>
                                <option value="Cabang A">Cabang A</option>
                                <option value="Cabang B">Cabang B</option>
                            </select>
                        </div>
                    </div>

                    <!-- Right: Export Excel & PDF Buttons -->
                    <div class="flex items-center gap-2">
                        <!-- Button Ekspor Excel (.xlsx) -->
                        <button type="button"
                                @click="exportCurrentTabToExcel()"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer"
                                title="Unduh data tabel laporan saat ini dalam format spreadsheet Excel (.xlsx)">
                            <span class="material-symbols-rounded text-[17px]">table_chart</span>
                            <span>Ekspor Excel (.xlsx)</span>
                        </button>

                        <!-- Button Cetak / Unduh PDF -->
                        <button type="button"
                                @click="openPrintPdfModal()"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#0052cc] hover:bg-blue-700 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer"
                                title="Pratinjau & Cetak / Simpan sebagai dokumen PDF resmi lengkap dengan tanda tangan">
                            <span class="material-symbols-rounded text-[17px]">picture_as_pdf</span>
                            <span>Cetak / Unduh PDF</span>
                        </button>

                        <button type="button"
                                @click="applyPeriodChange()"
                                class="p-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors cursor-pointer"
                                title="Muat ulang data laporan">
                            <span class="material-symbols-rounded text-[18px]">refresh</span>
                        </button>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- 1. TAB: LAPORAN PENJUALAN (MODUL E)                       -->
                <!-- ========================================================= -->
                <div x-show="activeTab === 'sales_accounting'" class="space-y-6">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-3 border-b border-gray-200">
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-[19px] font-black text-gray-900 tracking-tight">Laporan Penjualan Cabang, Terminal &amp; Kasir</h1>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200">Penjualan</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Laporan omzet penjualan per-terminal kasir, per-akun kasir, per-cabang, dan konsolidasi 3 cabang periode harian, mingguan, bulanan.</p>
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
                                    class="bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold text-gray-700 shadow-2xs cursor-pointer">
                                <option value="all">Konsolidasi 3 Cabang</option>
                                <option value="Pusat">Cabang Pusat</option>
                                <option value="Cabang A">Cabang A</option>
                                <option value="Cabang B">Cabang B</option>
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

                </div>

                <!-- ========================================================= -->
                <!-- 2. TAB: LAPORAN PENJUALAN PER TERMINAL KASIR             -->
                <!-- ========================================================= -->
                <div x-show="activeTab === 'sales_terminal'" class="space-y-6" style="display: none;">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-3 border-b border-gray-200">
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-[19px] font-black text-gray-900 tracking-tight">Rincian Penjualan Berdasarkan Terminal Kasir</h1>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200">Terminal Kasir</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Laporan omzet penjualan, volume transaksi, dan arus kas per mesin terminal POS masing-masing cabang.</p>
                        </div>

                        <!-- Filter Toolbar Terminal -->
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Periode Selector: Tanggal (Harian), Minggu (Mingguan), Bulan (Bulanan), Tahun (Tahunan) -->
                            <div class="inline-flex p-1 bg-white border border-gray-300 rounded-xl text-xs font-bold shadow-2xs">
                                <button type="button" @click="terminalPeriod = 'daily'; loadTerminalReport()"
                                        :class="terminalPeriod === 'daily' ? 'bg-[#0052cc] text-white' : 'text-gray-600'"
                                        class="px-3 py-1 rounded-lg transition-all cursor-pointer">Tanggal</button>
                                <button type="button" @click="terminalPeriod = 'weekly'; loadTerminalReport()"
                                        :class="terminalPeriod === 'weekly' ? 'bg-[#0052cc] text-white' : 'text-gray-600'"
                                        class="px-3 py-1 rounded-lg transition-all cursor-pointer">Minggu</button>
                                <button type="button" @click="terminalPeriod = 'monthly'; loadTerminalReport()"
                                        :class="terminalPeriod === 'monthly' ? 'bg-[#0052cc] text-white' : 'text-gray-600'"
                                        class="px-3 py-1 rounded-lg transition-all cursor-pointer">Bulan</button>
                                <button type="button" @click="terminalPeriod = 'yearly'; loadTerminalReport()"
                                        :class="terminalPeriod === 'yearly' ? 'bg-[#0052cc] text-white' : 'text-gray-600'"
                                        class="px-3 py-1 rounded-lg transition-all cursor-pointer">Tahun</button>
                            </div>

                            <!-- Dynamic Period Input -->
                            <template x-if="terminalPeriod === 'daily' || terminalPeriod === 'weekly'">
                                <input type="date"
                                       x-model="terminalDate"
                                       @change="loadTerminalReport()"
                                       class="bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold text-gray-800 outline-none shadow-2xs cursor-pointer" />
                            </template>
                            <template x-if="terminalPeriod === 'monthly'">
                                <input type="month"
                                       x-model="terminalMonth"
                                       @change="loadTerminalReport()"
                                       class="bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold text-gray-800 outline-none shadow-2xs cursor-pointer" />
                            </template>
                            <template x-if="terminalPeriod === 'yearly'">
                                <select x-model="terminalYear"
                                        @change="loadTerminalReport()"
                                        class="bg-white border border-gray-300 rounded-xl px-3.5 py-2 text-xs font-bold text-gray-800 outline-none shadow-2xs cursor-pointer">
                                    <option value="2024">Tahun 2024</option>
                                    <option value="2025">Tahun 2025</option>
                                    <option value="2026">Tahun 2026</option>
                                    <option value="2027">Tahun 2027</option>
                                </select>
                            </template>

                            <!-- Filter Cabang -->
                            <select x-model="terminalBranch" @change="onTerminalBranchChange()"
                                    class="bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold text-gray-700 shadow-2xs cursor-pointer">
                                <option value="all">Semua Cabang (Konsolidasi)</option>
                                <option value="Pusat">Cabang Pusat</option>
                                <option value="Cabang A">Cabang A</option>
                                <option value="Cabang B">Cabang B</option>
                            </select>

                            <!-- Filter Terminal Kasir -->
                            <select x-model="terminalSelected" @change="loadTerminalReport()"
                                    class="bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold text-[#0052cc] shadow-2xs cursor-pointer">
                                <option value="all">Semua Terminal Kasir</option>
                                <template x-for="tCode in availableTerminalsForBranch" :key="tCode">
                                    <option :value="tCode" x-text="'Terminal ' + tCode"></option>
                                </template>
                            </select>

                            <button type="button" @click="loadTerminalReport()"
                                    class="p-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl shadow-2xs transition-colors cursor-pointer"
                                    title="Segarkan data terminal">
                                <span class="material-symbols-rounded text-[18px]">refresh</span>
                            </button>
                        </div>
                    </div>

                    <!-- 4 Terminal KPI Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">TOTAL OMZET TERMINAL</span>
                            <div class="mt-2">
                                <h3 class="text-[22px] font-mono font-black text-gray-900" x-text="formatRupiah(terminalData?.summary?.grand_total || 0)">Rp 0</h3>
                                <p class="text-[11px] text-gray-400 font-mono mt-0.5" x-text="(terminalData?.summary?.transaction_count || 0) + ' Transaksi Berhasil'"></p>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">TOTAL TRANSAKSI</span>
                            <div class="mt-2">
                                <h3 class="text-[22px] font-mono font-black text-[#0052cc]" x-text="(terminalData?.summary?.transaction_count || 0) + ' Struk'">0 Struk</h3>
                                <p class="text-[11px] text-gray-400 font-mono mt-0.5" x-text="(terminalData?.terminals_breakdown?.length || 0) + ' Terminal Aktif'"></p>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">PENJUALAN TUNAI (CASH)</span>
                            <div class="mt-2">
                                <h3 class="text-[22px] font-mono font-black text-emerald-600" x-text="formatRupiah(terminalData?.summary?.payment_breakdown?.tunai || 0)">Rp 0</h3>
                                <p class="text-[11px] text-emerald-700 font-bold mt-0.5">Fisik Kas Laci Terminal</p>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">PENJUALAN NON-TUNAI</span>
                            <div class="mt-2">
                                <h3 class="text-[22px] font-mono font-black text-blue-600" x-text="formatRupiah((terminalData?.summary?.payment_breakdown?.qris || 0) + (terminalData?.summary?.payment_breakdown?.campuran || 0) + (terminalData?.summary?.payment_breakdown?.kredit_kasbon || 0))">Rp 0</h3>
                                <p class="text-[11px] text-gray-400 font-mono mt-0.5">QRIS, Debit &amp; Kasbon Anggota</p>
                            </div>
                        </div>
                    </div>

                    <!-- Breakdown Per-Terminal Table -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-xs space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-rounded text-[#0052cc] text-[20px]">point_of_sale</span>
                                <h3 class="text-sm font-bold text-gray-900">Rincian Penjualan Berdasarkan Terminal Kasir</h3>
                            </div>
                            <span class="text-xs text-gray-500 font-mono" x-text="(terminalData?.terminals_breakdown?.length || 0) + ' Terminal Kasir Terdata'"></span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs min-w-[800px] border-collapse">
                                <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 text-[11px] font-bold uppercase">
                                    <tr>
                                        <th class="px-4 py-3">TERMINAL</th>
                                        <th class="px-4 py-3">CABANG PENUGASAN</th>
                                        <th class="px-4 py-3 text-center">JUMLAH TRANSAKSI</th>
                                        <th class="px-4 py-3 text-right">PENJUALAN TUNAI</th>
                                        <th class="px-4 py-3 text-right">PENJUALAN NON-TUNAI</th>
                                        <th class="px-4 py-3 text-right font-bold text-gray-900">TOTAL OMZET</th>
                                        <th class="px-4 py-3 text-center">KONTRIBUSI</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-gray-700">
                                    <template x-for="t in (terminalData?.terminals_breakdown || [])" :key="t.terminal_code">
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-4 py-3 font-mono font-bold text-[#0052cc]" x-text="t.terminal_code"></td>
                                            <td class="px-4 py-3 font-semibold text-gray-800" x-text="t.branch"></td>
                                            <td class="px-4 py-3 text-center font-mono font-bold" x-text="t.transaction_count"></td>
                                            <td class="px-4 py-3 text-right font-mono text-emerald-600" x-text="formatRupiah(t.cash_sales)"></td>
                                            <td class="px-4 py-3 text-right font-mono text-blue-600" x-text="formatRupiah(t.non_cash_sales)"></td>
                                            <td class="px-4 py-3 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(t.total_sales)"></td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200"
                                                      x-text="(t.contribution_percent || 0) + '%'"></span>
                                            </td>
                                        </tr>
                                    </template>
                                    <template x-if="!terminalData?.terminals_breakdown || terminalData.terminals_breakdown.length === 0">
                                        <tr>
                                            <td colspan="7" class="px-4 py-6 text-center text-gray-400">Tidak ada data transaksi per terminal pada periode ini.</td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- 3. TAB: LAPORAN PENJUALAN PER AKUN KASIR                 -->
                <!-- ========================================================= -->
                <div x-show="activeTab === 'sales_cashier'" class="space-y-6" style="display: none;">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-3 border-b border-gray-200">
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-[19px] font-black text-gray-900 tracking-tight">Rincian Penjualan Berdasarkan Akun Kasir</h1>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Akun Kasir</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Laporan produktivitas staf kasir, rekap transaksi tunai & non-tunai, serta kontribusi omzet per kasir.</p>
                        </div>

                        <!-- Filter Toolbar Akun Kasir -->
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Search Bar (Mencari Username dari Akun Kasir) -->
                            <div class="relative">
                                <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                                <input type="text"
                                       x-model="cashierSearch"
                                       @input.debounce.300ms="loadCashierReport()"
                                       placeholder="Cari username kasir..."
                                       class="bg-white border border-gray-300 rounded-xl pl-9 pr-4 py-2 text-xs font-semibold text-gray-800 outline-none shadow-2xs focus:border-emerald-600 transition-colors w-52 sm:w-64" />
                                <button type="button"
                                        x-show="cashierSearch.length > 0"
                                        @click="cashierSearch = ''; loadCashierReport()"
                                        class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-[16px] cursor-pointer">
                                    close
                                </button>
                            </div>

                            <!-- Periode Selector: Tanggal (Harian), Minggu (Mingguan), Bulan (Bulanan), Tahun (Tahunan) -->
                            <div class="inline-flex p-1 bg-white border border-gray-300 rounded-xl text-xs font-bold shadow-2xs">
                                <button type="button" @click="cashierPeriod = 'daily'; loadCashierReport()"
                                        :class="cashierPeriod === 'daily' ? 'bg-emerald-600 text-white' : 'text-gray-600'"
                                        class="px-3 py-1 rounded-lg transition-all cursor-pointer">Tanggal</button>
                                <button type="button" @click="cashierPeriod = 'weekly'; loadCashierReport()"
                                        :class="cashierPeriod === 'weekly' ? 'bg-emerald-600 text-white' : 'text-gray-600'"
                                        class="px-3 py-1 rounded-lg transition-all cursor-pointer">Minggu</button>
                                <button type="button" @click="cashierPeriod = 'monthly'; loadCashierReport()"
                                        :class="cashierPeriod === 'monthly' ? 'bg-emerald-600 text-white' : 'text-gray-600'"
                                        class="px-3 py-1 rounded-lg transition-all cursor-pointer">Bulan</button>
                                <button type="button" @click="cashierPeriod = 'yearly'; loadCashierReport()"
                                        :class="cashierPeriod === 'yearly' ? 'bg-emerald-600 text-white' : 'text-gray-600'"
                                        class="px-3 py-1 rounded-lg transition-all cursor-pointer">Tahun</button>
                            </div>

                            <!-- Dynamic Period Input -->
                            <template x-if="cashierPeriod === 'daily' || cashierPeriod === 'weekly'">
                                <input type="date"
                                       x-model="cashierDate"
                                       @change="loadCashierReport()"
                                       class="bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold text-gray-800 outline-none shadow-2xs cursor-pointer" />
                            </template>
                            <template x-if="cashierPeriod === 'monthly'">
                                <input type="month"
                                       x-model="cashierMonth"
                                       @change="loadCashierReport()"
                                       class="bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold text-gray-800 outline-none shadow-2xs cursor-pointer" />
                            </template>
                            <template x-if="cashierPeriod === 'yearly'">
                                <select x-model="cashierYear"
                                        @change="loadCashierReport()"
                                        class="bg-white border border-gray-300 rounded-xl px-3.5 py-2 text-xs font-bold text-gray-800 outline-none shadow-2xs cursor-pointer">
                                    <option value="2024">Tahun 2024</option>
                                    <option value="2025">Tahun 2025</option>
                                    <option value="2026">Tahun 2026</option>
                                    <option value="2027">Tahun 2027</option>
                                </select>
                            </template>

                            <!-- Filter Cabang -->
                            <select x-model="cashierBranch" @change="onCashierBranchChange()"
                                    class="bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold text-gray-700 shadow-2xs cursor-pointer focus:border-emerald-600 outline-none">
                                <option value="all">Semua Cabang (Konsolidasi)</option>
                                <option value="Pusat">Cabang Pusat</option>
                                <option value="Cabang A">Cabang A</option>
                                <option value="Cabang B">Cabang B</option>
                            </select>

                            <!-- Filter Akun Kasir Terdaftar (Berdasarkan Username) -->
                            <div class="relative">
                                <select x-model="cashierUsernameFilter"
                                        @change="loadCashierReport()"
                                        class="bg-white border border-emerald-300 text-emerald-800 rounded-xl px-3 py-2 text-xs font-bold shadow-2xs cursor-pointer focus:ring-1 focus:ring-emerald-500 focus:border-emerald-600 outline-none pr-8">
                                    <option value="all">Semua Username Kasir</option>
                                    <template x-for="c in filteredRegisteredCashiers" :key="c.id || c.username">
                                        <option :value="c.username" x-text="'@' + c.username + ' (' + c.name + ')'"></option>
                                    </template>
                                </select>
                            </div>

                            <button type="button" @click="loadCashierReport()"
                                    class="p-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl shadow-2xs transition-colors cursor-pointer"
                                    title="Segarkan data akun kasir">
                                <span class="material-symbols-rounded text-[18px]">refresh</span>
                            </button>
                        </div>
                    </div>

                    <!-- 4 Cashier KPI Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">TOTAL OMZET KASIR</span>
                            <div class="mt-2">
                                <h3 class="text-[22px] font-mono font-black text-gray-900" x-text="formatRupiah(cashierData?.summary?.grand_total || 0)">Rp 0</h3>
                                <p class="text-[11px] text-gray-400 font-mono mt-0.5" x-text="(cashierData?.summary?.transaction_count || 0) + ' Transaksi Berhasil'"></p>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">TOTAL TRANSAKSI</span>
                            <div class="mt-2">
                                <h3 class="text-[22px] font-mono font-black text-emerald-700" x-text="(cashierData?.summary?.transaction_count || 0) + ' Struk'">0 Struk</h3>
                                <p class="text-[11px] text-gray-400 font-mono mt-0.5" x-text="(cashierData?.cashiers_breakdown?.length || 0) + ' Akun Kasir Terdata'"></p>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">RATA-RATA / TRANSAKSI</span>
                            <div class="mt-2">
                                <h3 class="text-[22px] font-mono font-black text-blue-600" x-text="formatRupiah(cashierData?.summary?.average_basket || 0)">Rp 0</h3>
                                <p class="text-[11px] text-gray-400 font-mono mt-0.5">Nilai rata-rata keranjang kasir</p>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">AKUN KASIR AKTIF</span>
                            <div class="mt-2">
                                <h3 class="text-[22px] font-mono font-black text-purple-700" x-text="(cashierData?.cashiers_breakdown?.length || 0) + ' Kasir'">0 Kasir</h3>
                                <p class="text-[11px] text-gray-400 font-mono mt-0.5">Beroperasi pada periode ini</p>
                            </div>
                        </div>
                    </div>

                    <!-- Breakdown Per-Akun Kasir Table -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-xs space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-rounded text-emerald-600 text-[20px]">badge</span>
                                <h3 class="text-sm font-bold text-gray-900">Rincian Penjualan Berdasarkan Akun Kasir</h3>
                            </div>
                            <span class="text-xs text-gray-500 font-mono" x-text="(cashierData?.cashiers_breakdown?.length || 0) + ' Akun Kasir Terdata'"></span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs min-w-[850px] border-collapse">
                                <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 text-[11px] font-bold uppercase">
                                    <tr>
                                        <th class="px-4 py-3">NAMA KASIR</th>
                                        <th class="px-4 py-3">USERNAME / NIP</th>
                                        <th class="px-4 py-3">CABANG</th>
                                        <th class="px-4 py-3 text-center">JUMLAH TRANSAKSI</th>
                                        <th class="px-4 py-3 text-right">PENJUALAN TUNAI</th>
                                        <th class="px-4 py-3 text-right">PENJUALAN NON-TUNAI</th>
                                        <th class="px-4 py-3 text-right text-gray-600">RATA-RATA / TRX</th>
                                        <th class="px-4 py-3 text-right font-bold text-gray-900">TOTAL OMZET</th>
                                        <th class="px-4 py-3 text-center">KONTRIBUSI</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-gray-700">
                                    <template x-for="c in (cashierData?.cashiers_breakdown || [])" :key="c.cashier_name + (c.cashier_username || c.cashier_nik || '')">
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-4 py-3 font-bold text-gray-900" x-text="c.cashier_name"></td>
                                            <td class="px-4 py-3 font-mono text-gray-600">
                                                <span class="font-bold text-emerald-700" x-text="c.cashier_username ? '@' + c.cashier_username : (c.cashier_nik || '-')"></span>
                                            </td>
                                            <td class="px-4 py-3 font-semibold text-gray-800" x-text="c.branch"></td>
                                            <td class="px-4 py-3 text-center font-mono font-bold" x-text="c.transaction_count"></td>
                                            <td class="px-4 py-3 text-right font-mono text-emerald-600" x-text="formatRupiah(c.cash_sales)"></td>
                                            <td class="px-4 py-3 text-right font-mono text-blue-600" x-text="formatRupiah(c.non_cash_sales)"></td>
                                            <td class="px-4 py-3 text-right font-mono text-gray-600" x-text="formatRupiah(c.average_sales)"></td>
                                            <td class="px-4 py-3 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(c.total_sales)"></td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"
                                                      x-text="(c.contribution_percent || 0) + '%'"></span>
                                            </td>
                                        </tr>
                                    </template>
                                    <template x-if="!cashierData?.cashiers_breakdown || cashierData.cashiers_breakdown.length === 0">
                                        <tr>
                                            <td colspan="9" class="px-4 py-6 text-center text-gray-400">Tidak ada data transaksi per akun kasir pada periode ini.</td>
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

                        <div class="flex flex-wrap items-center gap-2">
                            <input type="date" x-model="plStartDate" @change="loadProfitLossReport()" class="bg-white border rounded-xl px-3 py-1.5 text-xs font-semibold">
                            <span class="text-xs text-gray-400">s/d</span>
                            <input type="date" x-model="plEndDate" @change="loadProfitLossReport()" class="bg-white border rounded-xl px-3 py-1.5 text-xs font-semibold">
                            <button type="button" @click="loadProfitLossReport()" class="p-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl cursor-pointer" title="Segarkan Data Laba Rugi">
                                <span class="material-symbols-rounded text-[18px]">refresh</span>
                            </button>
                            <button type="button"
                                    @click="openPrintPdfModal()"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#0052cc] hover:bg-blue-700 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                                <span class="material-symbols-rounded text-[17px]">picture_as_pdf</span>
                                <span>Ekspor PDF</span>
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
                                <div class="flex justify-between py-1 px-3">
                                    <span class="text-gray-600">Penjualan Kupon BBM &amp; Voucher</span>
                                    <span class="font-mono font-bold text-indigo-700" x-text="formatRupiah(plData?.statement?.coupon_sales || 0)"></span>
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
                                    class="px-3 py-2 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 focus:outline-none cursor-pointer">
                                <option value="all">Semua Cabang (Konsolidasi)</option>
                                <option value="Pusat">Cabang Pusat</option>
                                <option value="Cabang A">Cabang A</option>
                                <option value="Cabang B">Cabang B</option>
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
                                <h3 class="text-sm font-black text-gray-900">Performa Komparasi 3 Cabang (Pusat, Cabang A, Cabang B)</h3>
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
            </div>
        </div>
    </main>

    <!-- ========================================================= -->
    <!-- MODAL PRATINJAU DOKUMEN CETAK RESMI (PDF / PRINT READY)    -->
    <!-- ========================================================= -->
    <div x-show="showPrintModal"
         x-cloak
         class="fixed inset-0 z-[9999] overflow-y-auto bg-black/75 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6"
         style="display: none;">
            
            <div class="relative w-full max-w-5xl bg-white rounded-3xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden border border-gray-200">
                <!-- Modal Top Action Toolbar (No-Print) -->
                <div class="px-6 py-4 bg-gray-900 text-white flex items-center justify-between shrink-0 no-print">
                    <div class="flex items-center gap-3">
                        <span class="w-9 h-9 rounded-xl bg-blue-600/30 border border-blue-500/40 flex items-center justify-center text-blue-400">
                            <span class="material-symbols-rounded text-xl">picture_as_pdf</span>
                        </span>
                        <div>
                            <h3 class="text-sm font-black tracking-tight text-white flex items-center gap-2">
                                <span>Pratinjau Dokumen Laporan Resmi (PDF)</span>
                                <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-blue-500/20 text-blue-300 border border-blue-500/30">Siap Cetak / Unduh PDF</span>
                            </h3>
                            <p class="text-[11px] text-gray-400">Dokumen diformat khusus standar A4 dengan Kop Surat Resmi &amp; Pengesahan 3 Kolom.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="printOfficialDocument()"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#0052cc] hover:bg-blue-600 text-white text-xs font-bold shadow-md transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[17px]">print</span>
                            <span>Cetak / Simpan PDF</span>
                        </button>
                        <button type="button"
                                @click="showPrintModal = false"
                                class="px-3.5 py-2 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs font-bold transition-all cursor-pointer">
                            Tutup
                        </button>
                    </div>
                </div>

                <!-- Printable Document Preview Container -->
                <div class="flex-1 overflow-y-auto p-4 sm:p-8 bg-gray-200/80 flex justify-center">
                    <!-- The Actual Paper Document (Target of @media print) -->
                    <div id="official-printable-doc-root" class="bg-white text-gray-900 w-full max-w-4xl p-8 sm:p-10 shadow-lg border border-gray-300 rounded-sm font-sans">
                        
                        <!-- 1. KOP SURAT RESMI PERUSAHAAN & KOPERASI -->
                        <div class="flex items-center gap-5 pb-4 border-b-4 border-double border-gray-900">
                            <div class="w-16 h-16 rounded-xl bg-blue-900 flex items-center justify-center text-white font-black text-2xl tracking-tighter shrink-0 border border-blue-950">
                                ILP
                            </div>
                            <div class="flex-1 text-center pr-16">
                                <h2 class="text-base sm:text-lg font-black tracking-tight uppercase text-gray-900 leading-tight">
                                    KOPERASI PEKERJA PT INDO LAMPUNG PERKASA (ILP)
                                </h2>
                                <h3 class="text-xs sm:text-sm font-bold tracking-wide uppercase text-blue-900 mt-0.5">
                                    UNIT USAHA RETAIL MINIMARKET &amp; TOKO SERBA ADA
                                </h3>
                                <p class="text-[10.5px] text-gray-600 mt-1 leading-snug">
                                    Jl. Lintas Timur KM. 120, Kawasan Industri PT ILP, Gedung Koperasi Lantai 1, Menggala, Tulang Bawang, Lampung 34596<br>
                                    Telepon: (0726) 775123 | Layanan Anggota: ext. 402 | Email: koperasi.ilp@sugarindogroup.com
                                </p>
                            </div>
                        </div>

                        <!-- 2. JUDUL DOKUMEN & METADATA RESMI -->
                        <div class="mt-6 mb-5 text-center">
                            <h1 class="text-base font-black uppercase tracking-wider text-gray-900" x-text="'LAPORAN RESMI: ' + getTabTitle()"></h1>
                            <div class="inline-block mt-1 px-3 py-0.5 bg-gray-100 rounded text-[11px] font-mono font-bold text-gray-700">
                                DOKUMEN STANDAR AKUNTANSI KOPERASI (SAK EP)
                            </div>
                        </div>

                        <!-- Metadata Grid -->
                        <div class="grid grid-cols-2 gap-x-6 gap-y-2 p-3.5 bg-gray-50 border border-gray-200 rounded-lg text-xs mb-6">
                            <div class="flex justify-between">
                                <span class="text-gray-500 font-semibold">Periode Laporan:</span>
                                <span class="font-bold text-gray-900" x-text="getPdfPeriodLabel()"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500 font-semibold">Cakupan Cabang:</span>
                                <span class="font-bold text-gray-900" x-text="getPdfBranchLabel()"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500 font-semibold">Waktu Pencetakan:</span>
                                <span class="font-mono text-gray-700" x-text="new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500 font-semibold">Penanggung Jawab:</span>
                                <span class="font-bold text-gray-900">{{ Auth::user()->name ?? 'Manager Toko' }} (Manager Unit)</span>
                            </div>
                        </div>

                        <!-- 3. RINGKASAN EKSEKUTIF / KPI CARDS -->
                        <!-- If Sales -->
                        <template x-if="activeTab === 'sales_accounting'">
                            <div class="grid grid-cols-4 gap-3 mb-6 text-center">
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Total Omzet Bersih</div>
                                    <div class="text-xs font-mono font-black text-gray-900 mt-0.5" x-text="formatRupiah(salesData?.summary?.grand_total || 0)"></div>
                                </div>
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Jumlah Transaksi</div>
                                    <div class="text-xs font-mono font-black text-gray-900 mt-0.5" x-text="(salesData?.summary?.transaction_count || 0) + ' Struk'"></div>
                                </div>
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Pembayaran Tunai</div>
                                    <div class="text-xs font-mono font-black text-gray-900 mt-0.5" x-text="formatRupiah(salesData?.summary?.payment_breakdown?.tunai || 0)"></div>
                                </div>
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Non-Tunai &amp; Kasbon</div>
                                    <div class="text-xs font-mono font-black text-gray-900 mt-0.5" x-text="formatRupiah((salesData?.summary?.payment_breakdown?.qris || 0) + (salesData?.summary?.payment_breakdown?.kredit_kasbon || 0))"></div>
                                </div>
                            </div>
                        </template>

                        <!-- If Profit Loss -->
                        <template x-if="activeTab === 'profit_loss'">
                            <div class="grid grid-cols-4 gap-3 mb-6 text-center">
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Total Penjualan Kotor</div>
                                    <div class="text-xs font-mono font-black text-gray-900 mt-0.5" x-text="formatRupiah(plData?.statement?.gross_sales || 0)"></div>
                                </div>
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Total Penjualan Bersih</div>
                                    <div class="text-xs font-mono font-black text-[#0052cc] mt-0.5" x-text="formatRupiah(plData?.statement?.net_sales || 0)"></div>
                                </div>
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Total Laba Kotor</div>
                                    <div class="text-xs font-mono font-black text-emerald-800 mt-0.5" x-text="formatRupiah(plData?.statement?.total_gross_profit || 0)"></div>
                                </div>
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Laba Bersih Operasional</div>
                                    <div class="text-xs font-mono font-black text-emerald-700 mt-0.5" x-text="formatRupiah(plData?.statement?.net_profit || 0)"></div>
                                    <div class="text-[9px] font-bold text-gray-500" x-text="'Margin: ' + (plData?.statement?.profit_margin_percent || 0) + '%'"></div>
                                </div>
                            </div>
                        </template>

                        <!-- If Fast Moving -->
                        <template x-if="activeTab === 'fast_moving'">
                            <div class="grid grid-cols-3 gap-3 mb-6 text-center">
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Total Item Dianalisis</div>
                                    <div class="text-xs font-mono font-black text-gray-900 mt-0.5" x-text="(fastMovingData || []).length + ' SKU'"></div>
                                </div>
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Perlu Restock</div>
                                    <div class="text-xs font-mono font-black text-amber-700 mt-0.5" x-text="(fastMovingData || []).filter(i => (i.recommended_restock_qty || 0) > 0).length + ' SKU'"></div>
                                </div>
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Kondisi Stok Rendah</div>
                                    <div class="text-xs font-mono font-black text-red-600 mt-0.5" x-text="(fastMovingData || []).filter(i => i.is_low_stock).length + ' SKU'"></div>
                                </div>
                            </div>
                        </template>

                        <!-- If Cash Reconciliation -->
                        <template x-if="activeTab === 'cash_reconciliation'">
                            <div class="grid grid-cols-4 gap-3 mb-6 text-center">
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Shift Diaudit</div>
                                    <div class="text-xs font-mono font-black text-gray-900 mt-0.5" x-text="reconData?.summary?.total_audited_shifts || 0"></div>
                                </div>
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Estimasi Kas Sistem</div>
                                    <div class="text-xs font-mono font-black text-gray-900 mt-0.5" x-text="formatRupiah(reconData?.summary?.total_expected_cash || 0)"></div>
                                </div>
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Total Kas Fisik Laci</div>
                                    <div class="text-xs font-mono font-black text-gray-900 mt-0.5" x-text="formatRupiah(reconData?.summary?.total_actual_cash || 0)"></div>
                                </div>
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Net Selisih Kasir</div>
                                    <div class="text-xs font-mono font-black mt-0.5" :class="(reconData?.summary?.net_variance || 0) < 0 ? 'text-red-600' : 'text-gray-900'" x-text="formatRupiah(reconData?.summary?.net_variance || 0)"></div>
                                </div>
                            </div>
                        </template>

                        <!-- If Product Taxes -->
                        <template x-if="activeTab === 'product_taxes'">
                            <div class="grid grid-cols-3 gap-3 mb-6 text-center">
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Produk Kena Pajak (PPN)</div>
                                    <div class="text-xs font-mono font-black text-gray-900 mt-0.5" x-text="(taxSummary?.taxable_products || 0) + ' SKU'"></div>
                                </div>
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Produk Bebas Pajak</div>
                                    <div class="text-xs font-mono font-black text-gray-900 mt-0.5" x-text="(taxSummary?.non_taxable_products || 0) + ' SKU'"></div>
                                </div>
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Tarif PPN Global</div>
                                    <div class="text-xs font-mono font-black text-purple-900 mt-0.5" x-text="(taxSummary?.global_tax_rate || 11) + '%'"></div>
                                </div>
                            </div>
                        </template>

                        <!-- If Payroll Debt -->
                        <template x-if="activeTab === 'payroll_debt'">
                            <div class="grid grid-cols-4 gap-3 mb-6 text-center">
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Total Piutang Berjalan</div>
                                    <div class="text-xs font-mono font-black text-amber-800 mt-0.5" x-text="formatRupiah(debtSummary?.total_active_debt || 0)"></div>
                                </div>
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Anggota Berhutang</div>
                                    <div class="text-xs font-mono font-black text-gray-900 mt-0.5" x-text="(debtSummary?.members_with_active_debt || 0) + ' Karyawan'"></div>
                                </div>
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Total Plafon Kredit</div>
                                    <div class="text-xs font-mono font-black text-gray-900 mt-0.5" x-text="formatRupiah(debtSummary?.total_credit_limit || 0)"></div>
                                </div>
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Sisa Plafon Tersedia</div>
                                    <div class="text-xs font-mono font-black text-emerald-700 mt-0.5" x-text="formatRupiah(debtSummary?.total_remaining_credit || 0)"></div>
                                </div>
                            </div>
                        </template>

                        <!-- If Consolidated -->
                        <template x-if="activeTab === 'consolidated_3branches'">
                            <div class="grid grid-cols-4 gap-3 mb-6 text-center">
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Total Omzet Konsolidasi</div>
                                    <div class="text-xs font-mono font-black text-gray-900 mt-0.5" x-text="formatRupiah(consolidatedData?.financial_summary?.consolidated_gross_sales || 0)"></div>
                                </div>
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Total Laba Kotor</div>
                                    <div class="text-xs font-mono font-black text-gray-900 mt-0.5" x-text="formatRupiah(consolidatedData?.financial_summary?.consolidated_gross_profit || 0)"></div>
                                </div>
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Total Piutang Berjalan</div>
                                    <div class="text-xs font-mono font-black text-gray-900 mt-0.5" x-text="formatRupiah(consolidatedData?.financial_summary?.total_active_member_debt || 0)"></div>
                                </div>
                                <div class="p-2.5 bg-gray-50 border border-gray-200 rounded">
                                    <div class="text-[9.5px] uppercase font-bold text-gray-500">Valuasi Stok Gabungan</div>
                                    <div class="text-xs font-mono font-black text-gray-900 mt-0.5" x-text="formatRupiah(consolidatedData?.financial_summary?.total_inventory_valuation || 0)"></div>
                                </div>
                            </div>
                        </template>

                        <!-- 4. COMPLETE TABULAR DATA ACCORDING TO ACTIVE TAB -->
                        <!-- TAB 1: Penjualan Cabang -->
                        <template x-if="activeTab === 'sales_accounting'">
                            <div class="space-y-4">
                                <h4 class="text-xs font-black uppercase text-gray-700 mb-2">Rekapitulasi Performa Penjualan Antar Cabang</h4>
                                <table class="w-full text-left text-[11px] border-collapse border border-gray-300">
                                    <thead>
                                        <tr class="bg-gray-100 border-b border-gray-300 font-bold uppercase">
                                            <th class="p-2 border border-gray-300">Kode</th>
                                            <th class="p-2 border border-gray-300">Nama Cabang Outlet</th>
                                            <th class="p-2 border border-gray-300 text-center">Transaksi</th>
                                            <th class="p-2 border border-gray-300 text-right">Total Penjualan (Rp)</th>
                                            <th class="p-2 border border-gray-300 text-center">Kontribusi (%)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="b in (salesData?.branch_breakdown || [])" :key="b.branch_code">
                                            <tr class="border-b border-gray-200">
                                                <td class="p-2 border border-gray-300 font-mono" x-text="b.branch_code"></td>
                                                <td class="p-2 border border-gray-300 font-bold" x-text="b.branch_name"></td>
                                                <td class="p-2 border border-gray-300 text-center font-mono" x-text="b.transaction_count"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono" x-text="formatRupiah(b.total_sales)"></td>
                                                <td class="p-2 border border-gray-300 text-center font-bold" x-text="b.contribution_percent + '%'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <!-- TAB 2: Penjualan Berdasarkan Terminal Kasir -->
                        <template x-if="activeTab === 'sales_terminal'">
                            <div class="space-y-4">
                                <h4 class="text-xs font-black uppercase text-gray-700 mb-2">Rekapitulasi Penjualan Berdasarkan Mesin Terminal Kasir</h4>
                                <table class="w-full text-left text-[11px] border-collapse border border-gray-300">
                                    <thead>
                                        <tr class="bg-gray-100 border-b border-gray-300 font-bold uppercase">
                                            <th class="p-2 border border-gray-300">Terminal</th>
                                            <th class="p-2 border border-gray-300">Cabang Penugasan</th>
                                            <th class="p-2 border border-gray-300 text-center">Transaksi</th>
                                            <th class="p-2 border border-gray-300 text-right">Penjualan Tunai (Rp)</th>
                                            <th class="p-2 border border-gray-300 text-right">Penjualan Non-Tunai (Rp)</th>
                                            <th class="p-2 border border-gray-300 text-right">Total Omzet (Rp)</th>
                                            <th class="p-2 border border-gray-300 text-center">Kontribusi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="t in (terminalData?.terminals_breakdown || [])" :key="t.terminal_code">
                                            <tr class="border-b border-gray-200">
                                                <td class="p-2 border border-gray-300 font-mono font-bold text-blue-700" x-text="t.terminal_code"></td>
                                                <td class="p-2 border border-gray-300 font-semibold" x-text="t.branch"></td>
                                                <td class="p-2 border border-gray-300 text-center font-mono font-bold" x-text="t.transaction_count"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono" x-text="formatRupiah(t.cash_sales)"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono" x-text="formatRupiah(t.non_cash_sales)"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono font-bold" x-text="formatRupiah(t.total_sales)"></td>
                                                <td class="p-2 border border-gray-300 text-center font-bold" x-text="(t.contribution_percent || 0) + '%'"></td>
                                            </tr>
                                        </template>
                                        <template x-if="!terminalData?.terminals_breakdown || terminalData.terminals_breakdown.length === 0">
                                            <tr>
                                                <td colspan="7" class="p-4 text-center text-gray-400">Tidak ada data transaksi per terminal kasir pada periode ini.</td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <!-- TAB 3: Penjualan Berdasarkan Akun Kasir -->
                        <template x-if="activeTab === 'sales_cashier'">
                            <div class="space-y-4">
                                <h4 class="text-xs font-black uppercase text-gray-700 mb-2">Rekapitulasi Penjualan Berdasarkan Akun Pengguna Kasir</h4>
                                <table class="w-full text-left text-[11px] border-collapse border border-gray-300">
                                    <thead>
                                        <tr class="bg-gray-100 border-b border-gray-300 font-bold uppercase">
                                            <th class="p-2 border border-gray-300">Nama Kasir</th>
                                            <th class="p-2 border border-gray-300">Username / NIP</th>
                                            <th class="p-2 border border-gray-300">Cabang</th>
                                            <th class="p-2 border border-gray-300 text-center">Transaksi</th>
                                            <th class="p-2 border border-gray-300 text-right">Penjualan Tunai (Rp)</th>
                                            <th class="p-2 border border-gray-300 text-right">Penjualan Non-Tunai (Rp)</th>
                                            <th class="p-2 border border-gray-300 text-right">Rata-rata / Trx (Rp)</th>
                                            <th class="p-2 border border-gray-300 text-right">Total Omzet (Rp)</th>
                                            <th class="p-2 border border-gray-300 text-center">Kontribusi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="c in (cashierData?.cashiers_breakdown || [])" :key="c.cashier_name + (c.cashier_username || c.cashier_nik || '')">
                                            <tr class="border-b border-gray-200">
                                                <td class="p-2 border border-gray-300 font-bold" x-text="c.cashier_name"></td>
                                                <td class="p-2 border border-gray-300 font-mono text-gray-600" x-text="c.cashier_username ? '@' + c.cashier_username : (c.cashier_nik || '-')"></td>
                                                <td class="p-2 border border-gray-300 font-semibold" x-text="c.branch"></td>
                                                <td class="p-2 border border-gray-300 text-center font-mono font-bold" x-text="c.transaction_count"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono" x-text="formatRupiah(c.cash_sales)"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono" x-text="formatRupiah(c.non_cash_sales)"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono" x-text="formatRupiah(c.average_sales)"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono font-bold" x-text="formatRupiah(c.total_sales)"></td>
                                                <td class="p-2 border border-gray-300 text-center font-bold" x-text="(c.contribution_percent || 0) + '%'"></td>
                                            </tr>
                                        </template>
                                        <template x-if="!cashierData?.cashiers_breakdown || cashierData.cashiers_breakdown.length === 0">
                                            <tr>
                                                <td colspan="9" class="p-4 text-center text-gray-400">Tidak ada data transaksi per akun kasir pada periode ini.</td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <!-- TAB 2: Laporan Laba / Rugi Bersih (P&L Statement - Sinkron 100% dengan Aplikasi) -->
                        <template x-if="activeTab === 'profit_loss'">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between border-b border-gray-300 pb-2">
                                    <div>
                                        <h4 class="text-xs font-black uppercase text-gray-900">IKHTISAR LABA / RUGI OPERASIONAL</h4>
                                        <p class="text-[11px] text-gray-600" x-text="'Periode: ' + plStartDate + ' s/d ' + plEndDate"></p>
                                    </div>
                                    <span class="text-[11px] font-mono font-bold text-gray-500">Mata Uang: IDR (Rp)</span>
                                </div>

                                <table class="w-full text-left text-[11.5px] border-collapse border border-gray-300">
                                    <thead>
                                        <tr class="bg-gray-100 border-b border-gray-300 font-bold uppercase text-gray-800">
                                            <th class="p-2.5 border border-gray-300">Komponen Akuntansi Laba / Rugi Operasional</th>
                                            <th class="p-2.5 border border-gray-300 text-right w-48">Nominal (Rp)</th>
                                            <th class="p-2.5 border border-gray-300 text-center w-28">% Terhadap Gross</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Section I: Pendapatan Penjualan -->
                                        <tr class="bg-slate-100 font-bold text-gray-900">
                                            <td colspan="3" class="p-2 border border-gray-300 uppercase tracking-wider text-[10.5px]">I. PENDAPATAN PENJUALAN (REVENUES)</td>
                                        </tr>
                                        <tr>
                                            <td class="p-2 border border-gray-300 pl-4 text-gray-700">Penjualan Produk Reguler Koperasi</td>
                                            <td class="p-2 border border-gray-300 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(plData?.statement?.regular_product_sales || 0)"></td>
                                            <td class="p-2 border border-gray-300 text-center font-mono text-gray-600" x-text="getPlRatio(plData?.statement?.regular_product_sales)"></td>
                                        </tr>
                                        <tr>
                                            <td class="p-2 border border-gray-300 pl-4 text-gray-700">Penjualan Produk Titipan Konsinyasi UMKM</td>
                                            <td class="p-2 border border-gray-300 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(plData?.statement?.consignment_product_sales || 0)"></td>
                                            <td class="p-2 border border-gray-300 text-center font-mono text-gray-600" x-text="getPlRatio(plData?.statement?.consignment_product_sales)"></td>
                                        </tr>
                                        <tr>
                                            <td class="p-2 border border-gray-300 pl-4 text-gray-700">Penjualan Kupon BBM &amp; Voucher Operasional</td>
                                            <td class="p-2 border border-gray-300 text-right font-mono font-bold text-indigo-700" x-text="formatRupiah(plData?.statement?.coupon_sales || 0)"></td>
                                            <td class="p-2 border border-gray-300 text-center font-mono text-gray-600" x-text="getPlRatio(plData?.statement?.coupon_sales)"></td>
                                        </tr>
                                        <tr class="font-bold bg-gray-50">
                                            <td class="p-2 border border-gray-300 pl-4 text-gray-900">Total Penjualan Kotor (Gross Sales)</td>
                                            <td class="p-2 border border-gray-300 text-right font-mono text-gray-900" x-text="formatRupiah(plData?.statement?.gross_sales || 0)"></td>
                                            <td class="p-2 border border-gray-300 text-center font-mono" x-text="(Number(plData?.statement?.gross_sales || 0) > 0) ? '100.0%' : '0.0%'"></td>
                                        </tr>
                                        <tr class="text-red-700">
                                            <td class="p-2 border border-gray-300 pl-4">(-) Potongan / Diskon Promosi &amp; Expired Clearance</td>
                                            <td class="p-2 border border-gray-300 text-right font-mono font-bold" x-text="'- ' + formatRupiah(plData?.statement?.total_discounts || 0)"></td>
                                            <td class="p-2 border border-gray-300 text-center font-mono" x-text="getPlRatio(plData?.statement?.total_discounts)"></td>
                                        </tr>
                                        <tr class="bg-blue-50/70 font-bold text-gray-900">
                                            <td class="p-2.5 border border-gray-300 pl-4">Total Penjualan Bersih (Net Sales)</td>
                                            <td class="p-2.5 border border-gray-300 text-right font-mono text-[#0052cc]" x-text="formatRupiah(plData?.statement?.net_sales || 0)"></td>
                                            <td class="p-2.5 border border-gray-300 text-center font-mono text-[#0052cc]" x-text="getPlRatio(plData?.statement?.net_sales)"></td>
                                        </tr>

                                        <!-- Section II: Biaya Pokok Penjualan & Bagi Hasil Mitra -->
                                        <tr class="bg-slate-100 font-bold text-gray-900">
                                            <td colspan="3" class="p-2 border border-gray-300 uppercase tracking-wider text-[10.5px]">II. BIAYA POKOK PENJUALAN &amp; BAGI HASIL MITRA</td>
                                        </tr>
                                        <tr class="text-red-700">
                                            <td class="p-2 border border-gray-300 pl-4">(-) Harga Pokok Penjualan (HPP) Barang Reguler</td>
                                            <td class="p-2 border border-gray-300 text-right font-mono font-bold" x-text="'- ' + formatRupiah(plData?.statement?.regular_cogs || 0)"></td>
                                            <td class="p-2 border border-gray-300 text-center font-mono" x-text="getPlRatio(plData?.statement?.regular_cogs)"></td>
                                        </tr>
                                        <tr class="text-amber-800">
                                            <td class="p-2 border border-gray-300 pl-4">(-) Hak Setoran Hasil Penjualan ke Mitra UMKM</td>
                                            <td class="p-2 border border-gray-300 text-right font-mono font-bold" x-text="'- ' + formatRupiah(plData?.statement?.umkm_partner_share || 0)"></td>
                                            <td class="p-2 border border-gray-300 text-center font-mono" x-text="getPlRatio(plData?.statement?.umkm_partner_share)"></td>
                                        </tr>
                                        <tr class="text-emerald-800 bg-emerald-50/40">
                                            <td class="p-2 border border-gray-300 pl-4 font-semibold">(+) Pendapatan Bagi Hasil / Komisi Koperasi dari UMKM</td>
                                            <td class="p-2 border border-gray-300 text-right font-mono font-bold" x-text="'+ ' + formatRupiah(plData?.statement?.cooperative_commission_earned || 0)"></td>
                                            <td class="p-2 border border-gray-300 text-center font-mono" x-text="getPlRatio(plData?.statement?.cooperative_commission_earned)"></td>
                                        </tr>

                                        <!-- Section III: Laba Kotor, Pajak & Laba Bersih -->
                                        <tr class="bg-emerald-50 font-black text-emerald-900">
                                            <td class="p-2.5 border border-gray-300">TOTAL LABA KOTOR (GROSS PROFIT)</td>
                                            <td class="p-2.5 border border-gray-300 text-right font-mono" x-text="formatRupiah(plData?.statement?.total_gross_profit || 0)"></td>
                                            <td class="p-2.5 border border-gray-300 text-center font-mono" x-text="getPlRatio(plData?.statement?.total_gross_profit)"></td>
                                        </tr>
                                        <tr>
                                            <td class="p-2 border border-gray-300 pl-4 text-gray-600">Pajak Pertambahan Nilai (PPN 11%) Terkumpul</td>
                                            <td class="p-2 border border-gray-300 text-right font-mono text-gray-700" x-text="formatRupiah(plData?.statement?.collected_tax_ppn || 0)"></td>
                                            <td class="p-2 border border-gray-300 text-center font-mono text-gray-500" x-text="getPlRatio(plData?.statement?.collected_tax_ppn)"></td>
                                        </tr>
                                        <tr class="bg-emerald-100 font-black text-gray-900 border-t-2 border-emerald-700">
                                            <td class="p-3 border border-gray-400">
                                                <div>LABA BERSIH OPERASIONAL (NET PROFIT)</div>
                                                <div class="text-[10.5px] font-bold text-emerald-800 mt-0.5"
                                                     x-text="'Margin Keuntungan Bersih: ' + (plData?.statement?.profit_margin_percent || 0) + '%'"></div>
                                            </td>
                                            <td class="p-3 border border-gray-400 text-right font-mono text-sm text-emerald-900" x-text="formatRupiah(plData?.statement?.net_profit || 0)"></td>
                                            <td class="p-3 border border-gray-400 text-center font-mono text-emerald-900" x-text="(plData?.statement?.profit_margin_percent || 0) + '%'"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <!-- TAB 3: Fast Moving Items -->
                        <template x-if="activeTab === 'fast_moving'">
                            <div>
                                <table class="w-full text-left text-[10.5px] border-collapse border border-gray-300">
                                    <thead>
                                        <tr class="bg-gray-100 border-b border-gray-300 font-bold uppercase">
                                            <th class="p-2 border border-gray-300 text-center w-10">Rank</th>
                                            <th class="p-2 border border-gray-300">SKU</th>
                                            <th class="p-2 border border-gray-300">Nama Produk</th>
                                            <th class="p-2 border border-gray-300">Kategori</th>
                                            <th class="p-2 border border-gray-300 text-center">Terjual</th>
                                            <th class="p-2 border border-gray-300 text-right">Omzet Penjualan (Rp)</th>
                                            <th class="p-2 border border-gray-300 text-center">Share (%)</th>
                                            <th class="p-2 border border-gray-300 text-center">Sisa Stok</th>
                                            <th class="p-2 border border-gray-300 text-center">Saran Restock</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="item in (fastMovingData || [])" :key="item.sku">
                                            <tr class="border-b border-gray-200">
                                                <td class="p-2 border border-gray-300 text-center font-bold" x-text="item.rank"></td>
                                                <td class="p-2 border border-gray-300 font-mono text-[10px]" x-text="item.sku"></td>
                                                <td class="p-2 border border-gray-300 font-bold" x-text="item.name"></td>
                                                <td class="p-2 border border-gray-300" x-text="item.category"></td>
                                                <td class="p-2 border border-gray-300 text-center font-mono" x-text="item.total_qty_sold + ' ' + item.unit"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono" x-text="formatRupiah(item.total_revenue)"></td>
                                                <td class="p-2 border border-gray-300 text-center font-bold" x-text="item.revenue_share_percent + '%'"></td>
                                                <td class="p-2 border border-gray-300 text-center font-mono" x-text="item.current_stock + ' ' + item.unit"></td>
                                                <td class="p-2 border border-gray-300 text-center font-bold" :class="item.recommended_restock_qty > 0 ? 'text-amber-800' : 'text-gray-400'" x-text="item.recommended_restock_qty > 0 ? ('+' + item.recommended_restock_qty + ' ' + item.unit) : 'Aman'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <!-- TAB 4: Rekonsiliasi Kas Shift -->
                        <template x-if="activeTab === 'cash_reconciliation'">
                            <div>
                                <table class="w-full text-left text-[10.5px] border-collapse border border-gray-300">
                                    <thead>
                                        <tr class="bg-gray-100 border-b border-gray-300 font-bold uppercase">
                                            <th class="p-2 border border-gray-300">Kode Shift</th>
                                            <th class="p-2 border border-gray-300">Terminal</th>
                                            <th class="p-2 border border-gray-300">Kasir</th>
                                            <th class="p-2 border border-gray-300 text-right">Modal Awal</th>
                                            <th class="p-2 border border-gray-300 text-right">Penjualan Tunai</th>
                                            <th class="p-2 border border-gray-300 text-right">Estimasi Sistem</th>
                                            <th class="p-2 border border-gray-300 text-right">Fisik Laci</th>
                                            <th class="p-2 border border-gray-300 text-right">Selisih</th>
                                            <th class="p-2 border border-gray-300 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="s in (reconData?.data || [])" :key="s.id || s.shift_code">
                                            <tr class="border-b border-gray-200">
                                                <td class="p-2 border border-gray-300 font-mono text-[10px]" x-text="s.shift_code"></td>
                                                <td class="p-2 border border-gray-300 font-bold" x-text="s.terminal_code"></td>
                                                <td class="p-2 border border-gray-300" x-text="s.cashier_name"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono" x-text="formatRupiah(s.opening_cash)"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono" x-text="formatRupiah(s.cash_sales_total)"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono" x-text="formatRupiah(s.expected_cash)"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono font-bold" x-text="formatRupiah(s.actual_cash)"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono font-bold" :class="s.variance < 0 ? 'text-red-600' : (s.variance > 0 ? 'text-emerald-700' : 'text-gray-900')" x-text="formatRupiah(s.variance)"></td>
                                                <td class="p-2 border border-gray-300 text-center font-bold" x-text="s.variance_status"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <!-- TAB 5: Pajak PPN -->
                        <template x-if="activeTab === 'product_taxes'">
                            <div>
                                <table class="w-full text-left text-[10.5px] border-collapse border border-gray-300">
                                    <thead>
                                        <tr class="bg-gray-100 border-b border-gray-300 font-bold uppercase">
                                            <th class="p-2 border border-gray-300">SKU</th>
                                            <th class="p-2 border border-gray-300">Barcode</th>
                                            <th class="p-2 border border-gray-300">Nama Produk</th>
                                            <th class="p-2 border border-gray-300">Kategori</th>
                                            <th class="p-2 border border-gray-300 text-right">Harga Jual (Rp)</th>
                                            <th class="p-2 border border-gray-300 text-center">Status Pajak</th>
                                            <th class="p-2 border border-gray-300 text-center">Tarif PPN</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="item in (taxProductList || [])" :key="item.id || item.sku">
                                            <tr class="border-b border-gray-200">
                                                <td class="p-2 border border-gray-300 font-mono text-[10px]" x-text="item.sku"></td>
                                                <td class="p-2 border border-gray-300 font-mono text-[10px]" x-text="item.barcode || '-'"></td>
                                                <td class="p-2 border border-gray-300 font-bold" x-text="item.name"></td>
                                                <td class="p-2 border border-gray-300" x-text="item.category"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono" x-text="formatRupiah(item.price)"></td>
                                                <td class="p-2 border border-gray-300 text-center font-bold" x-text="item.is_taxable ? 'KENA PAJAK' : 'BEBAS PAJAK'"></td>
                                                <td class="p-2 border border-gray-300 text-center font-mono font-bold" x-text="(item.is_taxable ? (item.tax_rate || 11) : 0) + '%'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <!-- TAB 6: Piutang Payroll -->
                        <template x-if="activeTab === 'payroll_debt'">
                            <div>
                                <table class="w-full text-left text-[10.5px] border-collapse border border-gray-300">
                                    <thead>
                                        <tr class="bg-gray-100 border-b border-gray-300 font-bold uppercase">
                                            <th class="p-2 border border-gray-300">NIK / ID Anggota</th>
                                            <th class="p-2 border border-gray-300">Nama Anggota Karyawan</th>
                                            <th class="p-2 border border-gray-300">Departemen / Divisi</th>
                                            <th class="p-2 border border-gray-300">Cabang</th>
                                            <th class="p-2 border border-gray-300 text-right">Plafon Kredit</th>
                                            <th class="p-2 border border-gray-300 text-right">Kasbon Berjalan</th>
                                            <th class="p-2 border border-gray-300 text-right">Sisa Plafon</th>
                                            <th class="p-2 border border-gray-300 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="m in (debtRows || [])" :key="m.id || m.nik">
                                            <tr class="border-b border-gray-200">
                                                <td class="p-2 border border-gray-300 font-mono text-[10px]" x-text="m.nik || m.member_code"></td>
                                                <td class="p-2 border border-gray-300 font-bold" x-text="m.name"></td>
                                                <td class="p-2 border border-gray-300" x-text="m.department || '-'"></td>
                                                <td class="p-2 border border-gray-300" x-text="m.branch || '-'"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono" x-text="formatRupiah(m.credit_limit)"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono font-bold text-amber-800" x-text="formatRupiah(m.total_debt)"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono font-bold text-emerald-700" x-text="formatRupiah(m.remaining_credit)"></td>
                                                <td class="p-2 border border-gray-300 text-center font-bold" x-text="m.status || 'AKTIF'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <!-- TAB 7: Konsolidasi 3 Cabang -->
                        <template x-if="activeTab === 'consolidated_3branches'">
                            <div>
                                <table class="w-full text-left text-[11px] border-collapse border border-gray-300">
                                    <thead>
                                        <tr class="bg-gray-100 border-b border-gray-300 font-bold uppercase">
                                            <th class="p-2 border border-gray-300">Kode &amp; Cabang Outlet</th>
                                            <th class="p-2 border border-gray-300 text-right">Omzet Penjualan (Rp)</th>
                                            <th class="p-2 border border-gray-300 text-center">Jumlah Struk</th>
                                            <th class="p-2 border border-gray-300 text-right">Laba Kotor (Rp)</th>
                                            <th class="p-2 border border-gray-300 text-right">Piutang Anggota (Rp)</th>
                                            <th class="p-2 border border-gray-300 text-right">Alokasi Stok Aset (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="b in (consolidatedData?.branch_comparison || [])" :key="b.branch_code">
                                            <tr class="border-b border-gray-200">
                                                <td class="p-2 border border-gray-300 font-bold" x-text="b.branch_name + ' (' + b.branch_code + ')'"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono font-bold" x-text="formatRupiah(b.total_revenue)"></td>
                                                <td class="p-2 border border-gray-300 text-center font-mono" x-text="b.transaction_count + ' Struk'"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono" x-text="formatRupiah(b.gross_profit)"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono" x-text="formatRupiah(b.member_debt)"></td>
                                                <td class="p-2 border border-gray-300 text-right font-mono" x-text="formatRupiah(b.stock_valuation_share)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <!-- 5. TANDA TANGAN & PENGESAHAN RESMI (3 KOLOM STANDAR KOPERASI) -->
                        <div class="mt-10 pt-6 border-t border-gray-300 grid grid-cols-3 gap-6 text-center text-xs break-inside-avoid">
                            <div>
                                <p class="text-gray-500 font-medium">Dibuat Oleh,</p>
                                <p class="text-gray-400 text-[10px] mt-0.5">Staf Akuntansi / Kasir SPV</p>
                                <div class="h-16 flex items-end justify-center">
                                    <div class="w-32 border-b border-dashed border-gray-400"></div>
                                </div>
                                <p class="font-bold text-gray-900 mt-1">Petugas Administrasi</p>
                                <p class="text-[10px] text-gray-400">Unit Retail ILP</p>
                            </div>

                            <div>
                                <p class="text-gray-500 font-medium">Diperiksa Oleh,</p>
                                <p class="text-gray-400 text-[10px] mt-0.5">Manager Operasional Retail</p>
                                <div class="h-16 flex items-end justify-center">
                                    <div class="w-32 border-b border-dashed border-gray-400"></div>
                                </div>
                                <p class="font-bold text-gray-900 mt-1">{{ Auth::user()->name ?? 'Manager Toko' }}</p>
                                <p class="text-[10px] text-gray-400">Manager Operasional PT ILP</p>
                            </div>

                            <div>
                                <p class="text-gray-500 font-medium">Mengetahui &amp; Menyetujui,</p>
                                <p class="text-gray-400 text-[10px] mt-0.5">Pengurus / Direksi Koperasi</p>
                                <div class="h-16 flex items-end justify-center">
                                    <div class="w-32 border-b border-dashed border-gray-400"></div>
                                </div>
                                <p class="font-bold text-gray-900 mt-1">Ketua Koperasi / Owner</p>
                                <p class="text-[10px] text-gray-400">PT Indo Lampung Perkasa</p>
                            </div>
                        </div>

                        <!-- 6. DISCLAIMER RESMI -->
                        <div class="mt-8 pt-4 border-t border-gray-200 text-[10px] text-gray-400 text-center leading-relaxed">
                            Dokumen ini merupakan laporan resmi yang dikeluarkan dan diotorisasi secara elektronik oleh Sistem POS &amp; Akuntansi Koperasi Karyawan PT Indo Lampung Perkasa. Seluruh angka finansial dan stok yang tertera dicatat secara real-time dan mengikat secara hukum serta manajerial.
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PRINT STYLESHEET (A4 PDF EXPORT) -->
    <style>
    @media print {
        body * {
            visibility: hidden !important;
        }
        #official-printable-doc-root, #official-printable-doc-root * {
            visibility: visible !important;
        }
        #official-printable-doc-root {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 8mm 12mm !important;
            box-shadow: none !important;
            border: none !important;
            background: #ffffff !important;
            color: #000000 !important;
        }
        @page {
            size: A4 portrait;
            margin: 6mm;
        }
        .no-print {
            display: none !important;
        }
    }
    </style>
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
