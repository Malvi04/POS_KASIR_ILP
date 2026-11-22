@extends('layouts.app')

@section('title', 'Daftar & Monitoring Piutang Anggota - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    members: [],
    summary: {
        total_registered_members: 0,
        total_credit_limit: 0,
        total_active_debt: 0,
        total_remaining_credit: 0,
        members_with_debt_count: 0,
        members_over_limit_count: 0,
        members_within_limit_count: 0,
        total_piutang_transactions_count: 0,
        total_over_limit_transactions_count: 0
    },
    branches: [],
    departments: [],
    isLoading: true,
    searchQuery: '',
    filterStatus: 'all', // 'all' | 'over_limit' | 'within_limit' | 'zero_debt'
    filterBranch: '',
    filterDepartment: '',
    currentPage: 1,
    lastPage: 1,
    perPage: 15,
    totalRecords: 0,

    // Real-time auto-refresh interval
    autoRefresh: true,
    refreshTimer: null,

    // Modal 1: Detail Transaksi Piutang Member
    showMemberTrxModal: false,
    selectedMember: null,
    memberTransactions: [],
    isLoadingTrx: false,
    expandedTrxId: null,

    // Modal 2: Struk Viewer Modal
    showReceiptModal: false,
    receiptData: null,
    isLoadingReceipt: false,
    receiptPaperSize: '80mm', // '80mm' | '58mm'

    // Toast Notification
    toastMessage: '',
    toastType: 'success',
    showToast: false,

    async initPiutang() {
        await this.loadDebts();
        this.setupRealtimeListeners();
    },

    async loadDebts(page = 1) {
        this.isLoading = true;
        this.currentPage = page;

        const params = new URLSearchParams({
            page: this.currentPage,
            per_page: this.perPage,
            search: this.searchQuery || '',
            filter_status: this.filterStatus || 'all',
            branch: this.filterBranch || '',
            department: this.filterDepartment || ''
        });

        try {
            const res = await fetch(`/api/manager/debts?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.members = json.data || [];
                    this.summary = json.summary || this.summary;
                    this.branches = json.branches || [];
                    this.departments = json.departments || [];
                    this.currentPage = json.meta?.current_page || 1;
                    this.lastPage = json.meta?.last_page || 1;
                    this.totalRecords = json.meta?.total || 0;
                }
            } else {
                this.triggerToast('Gagal memuat daftar piutang anggota.', 'error');
            }
        } catch (e) {
            console.error('Error loading debts:', e);
            this.triggerToast('Terjadi kesalahan jaringan saat memuat piutang.', 'error');
        } finally {
            this.isLoading = false;
        }
    },

    setupRealtimeListeners() {
        // Listen to live transaction events via Echo / Reverb
        if (window.PosRealtime) {
            window.PosRealtime.on?.('transaction.created', (e) => {
                if (e?.payment_method === 'KREDIT' || e?.transaction?.payment_method === 'KREDIT') {
                    this.loadDebts(this.currentPage);
                    if (this.showMemberTrxModal && this.selectedMember) {
                        this.loadMemberTransactions(this.selectedMember.id, false);
                    }
                }
            });
            window.PosRealtime.on?.('debt.updated', () => {
                this.loadDebts(this.currentPage);
            });
        }

        // Periodic light polling if autoRefresh is enabled (every 15s)
        if (this.refreshTimer) clearInterval(this.refreshTimer);
        this.refreshTimer = setInterval(() => {
            if (this.autoRefresh && !this.isLoading && !this.showReceiptModal) {
                this.loadDebts(this.currentPage);
            }
        }, 15000);
    },

    async openMemberTransactions(member) {
        this.selectedMember = member;
        this.showMemberTrxModal = true;
        this.expandedTrxId = null;
        await this.loadMemberTransactions(member.id);
    },

    async loadMemberTransactions(memberId, showLoading = true) {
        if (showLoading) this.isLoadingTrx = true;
        try {
            const res = await fetch(`/api/manager/debts/members/${memberId}/transactions`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.memberTransactions = json.data || [];
                    if (json.member) {
                        this.selectedMember = { ...this.selectedMember, ...json.member };
                    }
                }
            } else {
                this.triggerToast('Gagal memuat detail transaksi piutang.', 'error');
            }
        } catch (e) {
            console.error('Error fetching member transactions:', e);
            this.triggerToast('Terjadi gangguan jaringan saat memuat riwayat transaksi.', 'error');
        } finally {
            this.isLoadingTrx = false;
        }
    },

    toggleExpandTrx(trxId) {
        this.expandedTrxId = (this.expandedTrxId === trxId) ? null : trxId;
    },

    async openReceipt(idOrInvoice) {
        this.isLoadingReceipt = true;
        this.showReceiptModal = true;
        this.receiptData = null;

        try {
            const res = await fetch(`/api/manager/debts/transactions/${encodeURIComponent(idOrInvoice)}/receipt`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success && json.data) {
                    this.receiptData = json.data;
                } else {
                    this.triggerToast(json.message || 'Struk tidak ditemukan.', 'error');
                }
            } else {
                this.triggerToast('Gagal mengambil data struk.', 'error');
            }
        } catch (e) {
            console.error('Error loading receipt:', e);
            this.triggerToast('Gangguan koneksi saat memuat struk.', 'error');
        } finally {
            this.isLoadingReceipt = false;
        }
    },

    printReceipt() {
        const printWindow = window.open('', '_blank');
        const content = document.getElementById('thermal-receipt-printable');
        if (!content || !printWindow) {
            window.print();
            return;
        }
        printWindow.document.write(`
            <html>
                <head>
                    <title>Struk Transaksi Piutang - ${this.receiptData?.id || ''}</title>
                    <style>
                        body { font-family: 'Courier New', Courier, monospace; margin: 0; padding: 10px; font-size: 11px; }
                        .thermal-receipt { width: 100%; max-width: ${this.receiptPaperSize === '58mm' ? '280px' : '380px'}; margin: 0 auto; }
                        .text-center { text-align: center; }
                        .border-b { border-bottom: 1px dashed #000; }
                        .border-t { border-top: 1px dashed #000; }
                        .flex { display: flex; justify-content: space-between; }
                        .font-bold { font-weight: bold; }
                    </style>
                </head>
                <body>
                    ${content.innerHTML}
                    <script>window.onload = function() { window.print(); window.close(); }<\/script>
                </body>
            </html>
        `);
        printWindow.document.close();
    },

    formatNominal(val) {
        return new Intl.NumberFormat('id-ID').format(Math.round(Number(val) || 0));
    },

    triggerToast(msg, type = 'success') {
        if (!msg || typeof msg !== 'string' || !msg.trim()) return;
        this.toastMessage = msg;
        this.toastType = type;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3500);
    }
}"
x-init="initOpening(); initPiutang();"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden relative">

    <!-- Sidebar -->
    <x-manager-sidebar active="piutang" />

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden relative">

        <!-- Top Header -->
        <header class="h-20 bg-[#f8f9fa] border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-3">
                <a href="{{ url('/members') }}" class="p-2 rounded-xl hover:bg-gray-200 text-gray-500 transition-colors cursor-pointer" title="Kembali ke Daftar Anggota">
                    <span class="material-symbols-rounded text-[20px]">arrow_back</span>
                </a>
                <h1 class="text-[20px] font-bold text-gray-900">Daftar &amp; Monitoring Piutang</h1>
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200 flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[14px]">account_balance_wallet</span>
                    <span>Kasbon &amp; Limit Piutang</span>
                </span>
                <template x-if="summary.members_over_limit_count > 0">
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-red-50 text-red-700 border border-red-200 flex items-center gap-1.5 animate-pulse">
                        <span class="w-2 h-2 rounded-full bg-red-600"></span>
                        <span x-text="summary.members_over_limit_count + ' Melebihi Limit'"></span>
                    </span>
                </template>
            </div>

            <div class="flex items-center gap-4 text-gray-500">
                <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white border border-gray-200 text-xs font-semibold text-gray-600">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span>Monitoring Real-time</span>
                    <button type="button" @click="autoRefresh = !autoRefresh" class="ml-1 text-[11px] font-bold text-[#0052cc] hover:underline" x-text="autoRefresh ? 'ON' : 'PAUSED'"></button>
                </div>
                <x-notification-bell role="MANAGER" />
                <button type="button" @click="window.PosRealtime?.syncNow?.(); loadDebts(currentPage);" class="hover:text-gray-900 transition-colors cursor-pointer" title="Perbarui Data Piutang">
                    <span class="material-symbols-rounded text-[24px]" :class="isLoading ? 'animate-spin text-[#0052cc]' : ''">autorenew</span>
                </button>
                <div class="h-6 w-px bg-gray-300"></div>
                <x-realtime-network-badge />
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-8 space-y-6 pb-[100px]">

            <!-- 1. Real-Time KPI Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <!-- Card 1: Total Piutang Berjalan -->
                <div class="bg-white rounded-3xl border border-gray-200/90 p-5 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Total Piutang Berjalan</span>
                        <div class="w-9 h-9 rounded-2xl bg-blue-50 text-[#0052cc] flex items-center justify-center">
                            <span class="material-symbols-rounded text-[20px]">payments</span>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-2xl font-black font-mono text-gray-900" x-text="'Rp ' + formatNominal(summary.total_active_debt)"></h3>
                        <p class="text-xs text-gray-400 mt-1 font-medium" x-text="summary.members_with_debt_count + ' anggota memiliki saldo piutang'"></p>
                    </div>
                </div>

                <!-- Card 2: Melebihi Limit Piutang (ALERT) -->
                <div class="bg-white rounded-3xl border border-red-200/90 p-5 shadow-xs flex flex-col justify-between relative overflow-hidden"
                     :class="summary.members_over_limit_count > 0 ? 'bg-gradient-to-br from-red-50/40 to-white' : ''">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-red-700 uppercase tracking-wider">Melebihi Limit Piutang</span>
                        <div class="w-9 h-9 rounded-2xl bg-red-100 text-red-700 flex items-center justify-center">
                            <span class="material-symbols-rounded text-[20px]">warning</span>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-baseline gap-2">
                            <h3 class="text-2xl font-black font-mono text-red-600" x-text="summary.members_over_limit_count"></h3>
                            <span class="text-xs font-bold text-red-600">Anggota</span>
                        </div>
                        <div class="flex items-center justify-between mt-1">
                            <p class="text-[11px] text-gray-500 font-medium" x-text="summary.total_over_limit_transactions_count + ' transaksi di atas plafon'"></p>
                            <button type="button" @click="filterStatus = 'over_limit'; loadDebts(1);" class="text-[11px] font-bold text-red-600 hover:underline">Lihat Semua →</button>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Dalam Batas Limit -->
                <div class="bg-white rounded-3xl border border-gray-200/90 p-5 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-emerald-700 uppercase tracking-wider">Dalam Batas Limit</span>
                        <div class="w-9 h-9 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                            <span class="material-symbols-rounded text-[20px]">verified</span>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-baseline gap-2">
                            <h3 class="text-2xl font-black font-mono text-emerald-700" x-text="summary.members_within_limit_count"></h3>
                            <span class="text-xs font-bold text-emerald-700">Anggota</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1 font-medium">Piutang aman sesuai batas plafon</p>
                    </div>
                </div>

                <!-- Card 4: Total Plafon Terdaftar -->
                <div class="bg-white rounded-3xl border border-gray-200/90 p-5 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Total Plafon Kredit</span>
                        <div class="w-9 h-9 rounded-2xl bg-purple-50 text-purple-700 flex items-center justify-center">
                            <span class="material-symbols-rounded text-[20px]">account_balance</span>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-2xl font-black font-mono text-gray-900" x-text="'Rp ' + formatNominal(summary.total_credit_limit)"></h3>
                        <p class="text-xs text-gray-400 mt-1 font-medium" x-text="'Sisa kredit: Rp ' + formatNominal(summary.total_remaining_credit)"></p>
                    </div>
                </div>
            </div>

            <!-- 2. Search & Filters Bar -->
            <div class="bg-white rounded-3xl border border-gray-200/90 p-5 shadow-xs space-y-4">
                <div class="flex flex-col md:flex-row gap-4 items-stretch md:items-center justify-between">
                    
                    <!-- Search Input -->
                    <div class="relative flex-1 max-w-md">
                        <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                        <input type="text"
                               x-model="searchQuery"
                               @input.debounce.300ms="loadDebts(1)"
                               placeholder="Cari nama anggota, NIK, kode, atau divisi..."
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 text-xs font-semibold focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/10 bg-white">
                    </div>

                    <!-- Dropdowns (Branch & Department) -->
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Cabang Filter -->
                        <select x-model="filterBranch" @change="loadDebts(1)" class="px-3.5 py-2.5 rounded-xl border border-gray-300 text-xs font-bold bg-white text-gray-700 focus:outline-none focus:border-[#0052cc]">
                            <option value="">Semua Cabang</option>
                            <template x-for="b in branches" :key="b">
                                <option :value="b" x-text="b"></option>
                            </template>
                        </select>

                        <!-- Departemen Filter -->
                        <select x-model="filterDepartment" @change="loadDebts(1)" class="px-3.5 py-2.5 rounded-xl border border-gray-300 text-xs font-bold bg-white text-gray-700 focus:outline-none focus:border-[#0052cc] max-w-[200px]">
                            <option value="">Semua Departemen</option>
                            <template x-for="dept in departments" :key="dept">
                                <option :value="dept" x-text="dept"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <!-- Status Filter Pills -->
                <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-gray-100">
                    <button type="button"
                            @click="filterStatus = 'all'; loadDebts(1);"
                            :class="filterStatus === 'all' ? 'bg-[#0052cc] text-white font-bold' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 font-semibold'"
                            class="px-4 py-1.5 rounded-xl text-xs transition-colors cursor-pointer flex items-center gap-1.5">
                        <span>Semua Status</span>
                        <span class="text-[10px] px-1.5 py-0.2 rounded-full" :class="filterStatus === 'all' ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-700'" x-text="summary.total_registered_members"></span>
                    </button>

                    <button type="button"
                            @click="filterStatus = 'over_limit'; loadDebts(1);"
                            :class="filterStatus === 'over_limit' ? 'bg-red-600 text-white font-bold shadow-xs' : 'bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 font-semibold'"
                            class="px-4 py-1.5 rounded-xl text-xs transition-colors cursor-pointer flex items-center gap-1.5">
                        <span class="material-symbols-rounded text-[15px]">warning</span>
                        <span>Melebihi Limit Piutang</span>
                        <span class="text-[10px] px-1.5 py-0.2 rounded-full font-black" :class="filterStatus === 'over_limit' ? 'bg-white text-red-600' : 'bg-red-200 text-red-800'" x-text="summary.members_over_limit_count"></span>
                    </button>

                    <button type="button"
                            @click="filterStatus = 'within_limit'; loadDebts(1);"
                            :class="filterStatus === 'within_limit' ? 'bg-emerald-600 text-white font-bold' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 font-semibold'"
                            class="px-4 py-1.5 rounded-xl text-xs transition-colors cursor-pointer flex items-center gap-1.5">
                        <span class="material-symbols-rounded text-[15px]">check_circle</span>
                        <span>Dalam Batas Limit</span>
                        <span class="text-[10px] px-1.5 py-0.2 rounded-full" :class="filterStatus === 'within_limit' ? 'bg-white/20 text-white' : 'bg-emerald-200 text-emerald-800'" x-text="summary.members_within_limit_count"></span>
                    </button>

                    <button type="button"
                            @click="filterStatus = 'zero_debt'; loadDebts(1);"
                            :class="filterStatus === 'zero_debt' ? 'bg-gray-700 text-white font-bold' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 font-semibold'"
                            class="px-4 py-1.5 rounded-xl text-xs transition-colors cursor-pointer flex items-center gap-1.5">
                        <span>Lunas / Bebas Piutang (Rp 0)</span>
                    </button>
                </div>
            </div>

            <!-- 3. Members Debt Table -->
            <div class="bg-white rounded-3xl border border-gray-200/90 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs min-w-[950px]">
                        <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 font-bold uppercase tracking-wider text-[11px]">
                            <tr>
                                <th class="px-5 py-3.5">ANGGOTA KOPERASI</th>
                                <th class="px-5 py-3.5">DEPARTEMEN &amp; CABANG</th>
                                <th class="px-5 py-3.5 text-right">BATAS PLAFON</th>
                                <th class="px-5 py-3.5 text-right">TOTAL PIUTANG</th>
                                <th class="px-5 py-3.5 text-right">SISA / KELEBIHAN</th>
                                <th class="px-5 py-3.5 text-center">STATUS LIMIT PIUTANG</th>
                                <th class="px-5 py-3.5 text-center">TRANSAKSI</th>
                                <th class="px-5 py-3.5 text-center sticky right-0 bg-gray-50">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <!-- Loading Skeletons -->
                            <template x-if="isLoading">
                                <tr>
                                    <td colspan="8" class="px-5 py-12 text-center text-gray-400">
                                        <div class="flex items-center justify-center gap-3">
                                            <span class="material-symbols-rounded animate-spin text-[28px] text-[#0052cc]">progress_activity</span>
                                            <span class="font-bold text-sm">Memuat data monitoring piutang real-time...</span>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <!-- Empty State -->
                            <template x-if="!isLoading && members.length === 0">
                                <tr>
                                    <td colspan="8" class="px-5 py-12 text-center text-gray-400">
                                        <span class="material-symbols-rounded text-[40px] text-gray-300 block mb-2">person_search</span>
                                        <h4 class="font-bold text-gray-800 text-sm">Tidak Ada Data Anggota</h4>
                                        <p class="text-xs text-gray-500 mt-1">Tidak ditemukan data piutang anggota yang sesuai dengan kriteria pencarian.</p>
                                    </td>
                                </tr>
                            </template>

                            <!-- Member Rows -->
                            <template x-if="!isLoading && members.length > 0">
                                <template x-for="m in members" :key="m.id">
                                    <tr class="hover:bg-gray-50/70 transition-colors" :class="m.is_over_limit ? 'bg-red-50/20' : ''">
                                        
                                        <!-- 1. Nama Anggota & NIK -->
                                        <td class="px-5 py-3.5 whitespace-nowrap">
                                            <div class="flex items-center gap-3">
                                                <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-xs shrink-0"
                                                     :class="m.is_over_limit ? 'bg-red-100 text-red-700' : (m.current_debt > 0 ? 'bg-blue-100 text-[#0052cc]' : 'bg-gray-100 text-gray-500')">
                                                    <span class="material-symbols-rounded text-[18px]">badge</span>
                                                </div>
                                                <div>
                                                    <a :href="'/member-detail?id=' + m.id" class="font-bold text-gray-900 hover:text-[#0052cc] block truncate max-w-[220px]" x-text="m.name"></a>
                                                    <div class="text-[11px] font-mono text-gray-400">
                                                        <span>NIK: </span><span class="font-bold text-gray-600" x-text="m.nik || '-'"></span>
                                                        <span class="mx-1">•</span>
                                                        <span x-text="'Kode: ' + m.member_code"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- 2. Departemen & Cabang -->
                                        <td class="px-5 py-3.5 whitespace-nowrap">
                                            <span class="font-bold text-gray-800 block text-xs" x-text="m.department"></span>
                                            <span class="text-[11px] text-gray-500 font-medium" x-text="m.branch"></span>
                                        </td>

                                        <!-- 3. Batas Plafon Bulanan -->
                                        <td class="px-5 py-3.5 text-right font-mono font-bold whitespace-nowrap text-gray-800"
                                            x-text="'Rp ' + formatNominal(m.credit_limit)">
                                        </td>

                                        <!-- 4. Total Piutang Berjalan -->
                                        <td class="px-5 py-3.5 text-right font-mono font-black whitespace-nowrap text-sm"
                                            :class="m.is_over_limit ? 'text-red-600' : (m.current_debt > 0 ? 'text-[#0052cc]' : 'text-gray-400')"
                                            x-text="'Rp ' + formatNominal(m.current_debt)">
                                        </td>

                                        <!-- 5. Sisa / Kelebihan Plafon -->
                                        <td class="px-5 py-3.5 text-right font-mono whitespace-nowrap">
                                            <template x-if="m.is_over_limit">
                                                <div>
                                                    <span class="text-xs font-black text-red-600" x-text="'+Rp ' + formatNominal(m.excess_debt_amount)"></span>
                                                    <span class="block text-[10px] text-red-500 font-sans font-bold">Kelebihan Plafon</span>
                                                </div>
                                            </template>
                                            <template x-if="!m.is_over_limit">
                                                <div>
                                                    <span class="text-xs font-bold text-emerald-600" x-text="'Rp ' + formatNominal(m.remaining_credit)"></span>
                                                    <span class="block text-[10px] text-gray-400 font-sans font-medium" x-text="'Tersisa ' + (100 - m.utilization_percent) + '%'"></span>
                                                </div>
                                            </template>
                                        </td>

                                        <!-- 6. Status Limit Piutang -->
                                        <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                            <template x-if="m.is_over_limit">
                                                <div class="inline-flex flex-col items-center">
                                                    <span class="px-3 py-1 rounded-full text-[10.5px] font-black tracking-wide bg-red-100 text-red-800 border border-red-300 flex items-center gap-1 shadow-xs animate-pulse">
                                                        <span class="material-symbols-rounded text-[14px]">warning</span>
                                                        <span>MELEBIHI LIMIT PIUTANG</span>
                                                    </span>
                                                    <span class="text-[10px] font-mono font-bold text-red-600 mt-0.5" x-text="'Terpakai: ' + m.utilization_percent + '%'"></span>
                                                </div>
                                            </template>
                                            <template x-if="!m.is_over_limit && m.current_debt > 0">
                                                <div class="inline-flex flex-col items-center">
                                                    <span class="px-2.5 py-1 rounded-full text-[10.5px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1">
                                                        <span class="material-symbols-rounded text-[14px]">check_circle</span>
                                                        <span>DALAM LIMIT</span>
                                                    </span>
                                                    <span class="text-[10px] font-mono text-gray-500 mt-0.5" x-text="'Terpakai: ' + m.utilization_percent + '%'"></span>
                                                </div>
                                            </template>
                                            <template x-if="m.current_debt <= 0">
                                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-gray-100 text-gray-500 border border-gray-200">
                                                    LUNAS / RP 0
                                                </span>
                                            </template>
                                        </td>

                                        <!-- 7. Transaksi Piutang Count -->
                                        <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                            <template x-if="m.piutang_trx_count > 0">
                                                <span class="px-2.5 py-1 rounded-xl text-xs font-bold font-mono bg-blue-50 text-[#0052cc] border border-blue-200 inline-flex items-center gap-1"
                                                      x-text="m.piutang_trx_count + ' Trx'"></span>
                                            </template>
                                            <template x-if="m.piutang_trx_count === 0">
                                                <span class="text-gray-400 text-xs">-</span>
                                            </template>
                                        </td>

                                        <!-- 8. Aksi -->
                                        <td class="px-5 py-3.5 text-center whitespace-nowrap sticky right-0 bg-white shadow-l">
                                            <div class="inline-flex items-center gap-1.5">
                                                <!-- Button Buka Modal Transaksi Piutang -->
                                                <button type="button"
                                                        @click="openMemberTransactions(m)"
                                                        class="px-3 py-1.5 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl font-bold text-xs flex items-center gap-1.5 shadow-xs transition-colors cursor-pointer"
                                                        title="Lihat Faktur & Item Transaksi Piutang">
                                                    <span class="material-symbols-rounded text-[16px]">receipt_long</span>
                                                    <span>Lihat Transaksi</span>
                                                </button>

                                                <!-- Button Profil & Limit -->
                                                <a :href="'/member-detail?id=' + m.id"
                                                   class="p-1.5 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-xl transition-colors"
                                                   title="Detail Profil & Kasbon">
                                                    <span class="material-symbols-rounded text-[18px]">open_in_new</span>
                                                </a>
                                            </div>
                                        </td>

                                    </tr>
                                </template>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="p-4 bg-gray-50 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                    <span class="text-gray-500 font-semibold" x-text="'Menampilkan halaman ' + currentPage + ' dari ' + lastPage + ' (' + totalRecords + ' total anggota)'"></span>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="loadDebts(currentPage - 1)"
                                :disabled="currentPage <= 1"
                                class="px-3 py-1.5 border border-gray-300 rounded-xl font-bold hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed">
                            Sebelumnya
                        </button>
                        <span class="font-mono font-bold px-2" x-text="currentPage"></span>
                        <button type="button"
                                @click="loadDebts(currentPage + 1)"
                                :disabled="currentPage >= lastPage"
                                class="px-3 py-1.5 border border-gray-300 rounded-xl font-bold hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed">
                            Selanjutnya
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- ========================================================================= -->
    <!-- MODAL 1: Detail Riwayat Transaksi Piutang Member & Item Faktur           -->
    <!-- ========================================================================= -->
    <div x-show="showMemberTrxModal"
         class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div class="bg-white rounded-3xl max-w-4xl w-full max-h-[90vh] flex flex-col shadow-2xl border border-gray-200 overflow-hidden"
             @click.outside="showMemberTrxModal = false">
            
            <!-- Modal Header -->
            <div class="p-6 bg-gray-50 border-b border-gray-200 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center font-bold text-sm shadow-xs"
                         :class="selectedMember?.is_over_limit ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-[#0052cc]'">
                        <span class="material-symbols-rounded text-[24px]">receipt_long</span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-black text-gray-900" x-text="selectedMember?.name"></h3>
                            <template x-if="selectedMember?.is_over_limit">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-red-100 text-red-800 border border-red-300">
                                    MELEBIHI LIMIT PIUTANG
                                </span>
                            </template>
                            <template x-if="!selectedMember?.is_over_limit && selectedMember?.current_debt > 0">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    DALAM LIMIT
                                </span>
                            </template>
                        </div>
                        <p class="text-xs font-mono text-gray-500 mt-0.5">
                            <span x-text="'NIK: ' + (selectedMember?.nik || '-')"></span> •
                            <span x-text="'Dept: ' + (selectedMember?.department || '-')"></span> •
                            <span x-text="'Cabang: ' + (selectedMember?.branch || 'Cabang Pusat')"></span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Debt figures badge -->
                    <div class="text-right hidden sm:block">
                        <span class="text-[10px] text-gray-400 uppercase font-bold block">Total Piutang Berjalan</span>
                        <span class="font-mono font-black text-base"
                              :class="selectedMember?.is_over_limit ? 'text-red-600' : 'text-[#0052cc]'"
                              x-text="'Rp ' + formatNominal(selectedMember?.current_debt)"></span>
                    </div>
                    <button type="button" @click="showMemberTrxModal = false" class="p-2 rounded-xl text-gray-400 hover:text-gray-700 hover:bg-gray-200 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>
            </div>

            <!-- Modal Body (Transactions List & Purchased Items) -->
            <div class="flex-1 overflow-y-auto p-6 space-y-4">
                
                <!-- Loading State -->
                <template x-if="isLoadingTrx">
                    <div class="p-12 text-center text-gray-400">
                        <span class="material-symbols-rounded animate-spin text-[32px] text-[#0052cc] block mx-auto mb-2">progress_activity</span>
                        <p class="font-bold text-xs">Memuat daftar faktur dan item belanja piutang...</p>
                    </div>
                </template>

                <!-- Empty State -->
                <template x-if="!isLoadingTrx && memberTransactions.length === 0">
                    <div class="p-12 text-center text-gray-400 border border-dashed border-gray-200 rounded-2xl">
                        <span class="material-symbols-rounded text-[40px] text-gray-300 block mb-2">receipt</span>
                        <h4 class="font-bold text-gray-800 text-sm">Belum Ada Transaksi Piutang</h4>
                        <p class="text-xs text-gray-500 mt-1">Anggota ini belum memiliki transaksi penjualan dengan metode pembayaran kredit / kasbon.</p>
                    </div>
                </template>

                <!-- Transactions Accordion Cards -->
                <template x-if="!isLoadingTrx && memberTransactions.length > 0">
                    <div class="space-y-3">
                        <div class="text-xs font-bold text-gray-500 flex items-center justify-between pb-1">
                            <span x-text="memberTransactions.length + ' Faktur Transaksi Piutang Tercatat'"></span>
                            <span class="text-gray-400 text-[11px]">Klik faktur untuk melihat rincian barang belanja</span>
                        </div>

                        <template x-for="trx in memberTransactions" :key="trx.id">
                            <div class="border rounded-2xl overflow-hidden transition-all duration-200"
                                 :class="trx.is_over_limit ? 'border-red-200 bg-red-50/10' : 'border-gray-200 bg-white'">
                                
                                <!-- Card Header / Summary Row -->
                                <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 cursor-pointer hover:bg-gray-50/80 transition-colors"
                                     @click="toggleExpandTrx(trx.id)">
                                    
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0"
                                             :class="trx.is_over_limit ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-[#0052cc]'">
                                            <span class="material-symbols-rounded text-[18px]">receipt</span>
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono font-bold text-gray-900 text-xs" x-text="trx.invoice_number"></span>
                                                <template x-if="trx.is_over_limit">
                                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-black bg-red-100 text-red-800 border border-red-300">
                                                        MELEBIHI LIMIT PIUTANG
                                                    </span>
                                                </template>
                                                <template x-if="!trx.is_over_limit">
                                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                        DALAM LIMIT
                                                    </span>
                                                </template>
                                            </div>
                                            <p class="text-[11px] font-mono text-gray-500 mt-0.5"
                                               x-text="trx.formatted_date + ' • Kasir: ' + trx.cashier_name + ' • ' + trx.branch"></p>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0">
                                        <div class="text-right">
                                            <span class="text-[10px] text-gray-400 block">Total Tagihan</span>
                                            <span class="font-mono font-black text-sm"
                                                  :class="trx.is_over_limit ? 'text-red-600' : 'text-gray-900'"
                                                  x-text="'Rp ' + formatNominal(trx.grand_total)"></span>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <!-- Tombol Buka Struk -->
                                            <button type="button"
                                                    @click.stop="openReceipt(trx.invoice_number)"
                                                    class="px-3 py-1.5 rounded-xl bg-purple-50 hover:bg-purple-100 text-purple-700 font-bold text-xs flex items-center gap-1 transition-colors cursor-pointer shadow-xs"
                                                    title="Lihat Struk Thermal Belanja">
                                                <span class="material-symbols-rounded text-[15px]">print</span>
                                                <span>Lihat Struk</span>
                                            </button>

                                            <!-- Expand/Collapse Chevron -->
                                            <span class="material-symbols-rounded text-[20px] text-gray-400 transition-transform duration-200"
                                                  :class="expandedTrxId === trx.id ? 'rotate-180 text-[#0052cc]' : ''">expand_more</span>
                                        </div>
                                    </div>

                                </div>

                                <!-- Collapsible Items Table ("transaksinya apa saja yang mereka lakukan piutang") -->
                                <div x-show="expandedTrxId === trx.id"
                                     x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0"
                                     x-transition:enter-end="opacity-100"
                                     class="p-4 bg-gray-50/70 border-t border-gray-100 text-xs">
                                    <h5 class="font-bold text-gray-700 mb-2 flex items-center gap-1.5">
                                        <span class="material-symbols-rounded text-[16px] text-gray-500">shopping_bag</span>
                                        <span>Rincian Barang yang Dibeli Secara Piutang:</span>
                                    </h5>

                                    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                                        <table class="w-full text-left text-xs">
                                            <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 font-bold text-[10.5px]">
                                                <tr>
                                                    <th class="px-3 py-2">NO</th>
                                                    <th class="px-3 py-2">NAMA BARANG / SKU</th>
                                                    <th class="px-3 py-2 text-right">JUMLAH</th>
                                                    <th class="px-3 py-2 text-right">HARGA SATUAN</th>
                                                    <th class="px-3 py-2 text-right">DISKON PROMO</th>
                                                    <th class="px-3 py-2 text-right">SUBTOTAL</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                <template x-for="(it, idx) in trx.items" :key="it.id">
                                                    <tr class="hover:bg-gray-50/50">
                                                        <td class="px-3 py-2 font-mono text-gray-400" x-text="idx + 1"></td>
                                                        <td class="px-3 py-2">
                                                            <span class="font-bold text-gray-900 block" x-text="it.name"></span>
                                                            <span class="text-[10px] font-mono text-gray-400" x-text="'SKU: ' + it.sku + ' • ' + it.barcode"></span>
                                                        </td>
                                                        <td class="px-3 py-2 text-right font-mono font-bold whitespace-nowrap" x-text="it.qty + ' ' + (it.unit || 'Pcs')"></td>
                                                        <td class="px-3 py-2 text-right font-mono whitespace-nowrap" x-text="'Rp ' + formatNominal(it.unit_price)"></td>
                                                        <td class="px-3 py-2 text-right font-mono whitespace-nowrap text-emerald-600" x-text="it.promo_discount > 0 ? ('-Rp ' + formatNominal(it.promo_discount)) : '-'"></td>
                                                        <td class="px-3 py-2 text-right font-mono font-bold whitespace-nowrap text-gray-800" x-text="'Rp ' + formatNominal(it.total_price)"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            </div>
                        </template>
                    </div>
                </template>

            </div>

            <!-- Modal Footer -->
            <div class="p-4 bg-gray-50 border-t border-gray-200 flex justify-end shrink-0">
                <button type="button" @click="showMemberTrxModal = false" class="px-5 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold text-xs rounded-xl transition-colors cursor-pointer">
                    Tutup
                </button>
            </div>

        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 2: Authentic Thermal Struk Viewer & Printing ("bisa lihat struknya") -->
    <!-- ========================================================================= -->
    <div x-show="showReceiptModal"
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95">
        
        <div class="bg-white rounded-3xl max-w-lg w-full max-h-[95vh] flex flex-col shadow-2xl border border-gray-200 overflow-hidden"
             @click.outside="showReceiptModal = false">
            
            <!-- Struk Modal Header -->
            <div class="p-4 bg-gray-900 text-white flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-rounded text-purple-400 text-[20px]">receipt_long</span>
                    <span class="font-bold text-xs">Preview Struk Kasir (Metode Piutang)</span>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Paper Size Toggle -->
                    <div class="flex bg-gray-800 p-0.5 rounded-lg text-[10px] font-bold">
                        <button type="button" @click="receiptPaperSize = '80mm'"
                                :class="receiptPaperSize === '80mm' ? 'bg-[#0052cc] text-white' : 'text-gray-400 hover:text-white'"
                                class="px-2 py-0.5 rounded cursor-pointer">80mm</button>
                        <button type="button" @click="receiptPaperSize = '58mm'"
                                :class="receiptPaperSize === '58mm' ? 'bg-[#0052cc] text-white' : 'text-gray-400 hover:text-white'"
                                class="px-2 py-0.5 rounded cursor-pointer">58mm</button>
                    </div>

                    <button type="button" @click="showReceiptModal = false" class="p-1 rounded-lg text-gray-400 hover:text-white cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">close</span>
                    </button>
                </div>
            </div>

            <!-- Struk Modal Body (Thermal Paper View) -->
            <div class="flex-1 overflow-y-auto p-6 bg-gray-100 flex justify-center items-start">
                
                <!-- Loading -->
                <template x-if="isLoadingReceipt">
                    <div class="p-12 text-center text-gray-500">
                        <span class="material-symbols-rounded animate-spin text-[32px] text-[#0052cc] block mx-auto mb-2">progress_activity</span>
                        <p class="font-bold text-xs">Menyiapkan struk belanja...</p>
                    </div>
                </template>

                <!-- Thermal Paper Card -->
                <template x-if="!isLoadingReceipt && receiptData">
                    <div id="thermal-receipt-printable"
                         class="bg-white text-black border border-neutral-300 shadow-md p-4 sm:p-5 flex flex-col shrink-0 font-mono transition-all duration-300"
                         :class="receiptPaperSize === '58mm' ? 'w-full max-w-[280px] text-[10.5px]' : 'w-full max-w-[380px] text-[11px]'">
                        
                        <!-- 1. Header Toko -->
                        <div class="text-center pb-3 border-b border-dashed border-black">
                            <h2 class="text-base sm:text-lg font-black tracking-wider leading-none text-black uppercase">MINIMARKET ILP</h2>
                            <div class="text-[10px] font-bold text-black uppercase mt-1 leading-tight">
                                KOPERASI KARYAWAN PT INDO LAMPUNG PERKASA
                            </div>
                            <div class="text-[9.5px] text-black uppercase mt-0.5" x-text="receiptData.branch || 'Cabang Pusat'"></div>
                            <div class="text-[9px] text-black leading-tight mt-0.5">
                                Gedung Utama Kopkar ILP • NPWP: 01.345.678.9-412.000
                            </div>
                        </div>

                        <!-- 2. Metadata Faktur -->
                        <div class="py-2 border-b border-dashed border-black text-[10.5px] space-y-1">
                            <div class="flex justify-between items-baseline">
                                <span class="text-neutral-700">No. Faktur:</span>
                                <span class="font-bold text-right" x-text="receiptData.id"></span>
                            </div>
                            <div class="flex justify-between items-baseline">
                                <span class="text-neutral-700">Waktu:</span>
                                <span class="text-right" x-text="receiptData.date + ' ' + receiptData.time"></span>
                            </div>
                            <div class="flex justify-between items-baseline">
                                <span class="text-neutral-700">Kasir / Term:</span>
                                <span class="text-right" x-text="receiptData.cashier + ' / ' + receiptData.terminal"></span>
                            </div>
                            <div class="flex justify-between items-baseline">
                                <span class="text-neutral-700">Metode Bayar:</span>
                                <span class="font-bold text-right uppercase" x-text="receiptData.methodLabel"></span>
                            </div>
                            <template x-if="receiptData.member">
                                <div class="flex justify-between items-baseline pt-0.5 border-t border-dotted border-neutral-300">
                                    <span class="text-neutral-700">Anggota:</span>
                                    <span class="font-bold text-right truncate max-w-[200px]" x-text="receiptData.member.name + ' (' + receiptData.member.nik + ')'"></span>
                                </div>
                            </template>
                        </div>

                        <!-- 3. Daftar Barang Belanja -->
                        <div class="py-2 border-b border-dashed border-black space-y-1.5">
                            <div class="flex justify-between text-[10px] font-bold uppercase text-neutral-600 pb-1 border-b border-dotted border-neutral-300">
                                <span>ITEM / PRODUK</span>
                                <span class="text-right">TOTAL (RP)</span>
                            </div>

                            <template x-for="(item, idx) in receiptData.items" :key="idx">
                                <div class="space-y-0.5">
                                    <div class="font-bold text-[11px] uppercase tracking-tight leading-snug break-words" x-text="(idx + 1) + '. ' + item.name"></div>
                                    <div class="flex justify-between items-baseline text-[10.5px] pl-2 text-neutral-900">
                                        <span x-text="item.qty + ' ' + (item.unit || 'Pcs') + '  x  ' + formatNominal(item.price)"></span>
                                        <span class="font-bold shrink-0 ml-2" x-text="formatNominal(item.total)"></span>
                                    </div>
                                    <template x-if="item.promo_discount > 0">
                                        <div class="flex justify-between items-baseline text-[9.5px] pl-2 text-neutral-700 italic">
                                            <span x-text="'* ' + (item.promo_name || 'Diskon Promo')"></span>
                                            <span class="shrink-0" x-text="'-' + formatNominal(item.promo_discount)"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        <!-- 4. Total Finansial -->
                        <div class="py-2 border-b border-dashed border-black text-[11px] space-y-1">
                            <div class="flex justify-between items-baseline">
                                <span class="text-neutral-700">Subtotal:</span>
                                <span class="font-bold" x-text="formatNominal(receiptData.subtotal)"></span>
                            </div>
                            <template x-if="receiptData.discount > 0">
                                <div class="flex justify-between items-baseline text-neutral-900">
                                    <span>Potongan Diskon:</span>
                                    <span class="font-bold" x-text="'-' + formatNominal(receiptData.discount)"></span>
                                </div>
                            </template>
                            <div class="flex justify-between items-baseline">
                                <span class="text-neutral-700">PPN (11%):</span>
                                <span class="font-bold" x-text="formatNominal(receiptData.tax)"></span>
                            </div>
                            <div class="border-t border-dashed border-black my-1"></div>
                            <div class="flex justify-between items-baseline text-xs sm:text-sm font-black">
                                <span>TOTAL PIUTANG:</span>
                                <span x-text="'Rp ' + formatNominal(receiptData.total)"></span>
                            </div>
                        </div>

                        <!-- 5. STATUS PIUTANG BANNER -->
                        <div class="my-2 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <div class="flex-1 border-t border-dashed border-black"></div>
                                <span class="text-[11px] font-black tracking-widest uppercase">BELUM LUNAS (PIUTANG)</span>
                                <div class="flex-1 border-t border-dashed border-black"></div>
                            </div>

                            <!-- Over Limit Banner on Receipt -->
                            <template x-if="receiptData.is_over_limit || receiptData.debt_status === 'MELEBIHI_LIMIT'">
                                <div class="my-1.5 py-1 px-2 border-2 border-dashed border-black text-center font-black text-[10.5px] tracking-wider uppercase bg-neutral-100">
                                    *** STATUS: MELEBIHI LIMIT PIUTANG ***
                                </div>
                            </template>
                            <template x-if="!receiptData.is_over_limit && receiptData.debt_status !== 'MELEBIHI_LIMIT'">
                                <div class="my-1 py-0.5 text-center text-[10px] font-bold text-neutral-800">
                                    STATUS: DALAM BATAS LIMIT PLAFON
                                </div>
                            </template>
                            <div class="border-t border-dashed border-black mt-1"></div>
                        </div>

                        <!-- 6. Footer Struk -->
                        <div class="pt-2 text-center text-[9.5px] text-neutral-700 space-y-0.5 leading-tight">
                            <div>*Potong gaji bulanan otomatis melalui payroll PT ILP</div>
                            <div class="font-bold pt-1 text-black">TERIMA KASIH TELAH BERBELANJA</div>
                            <div>Layanan Konsumen &amp; Pengaduan: 0800-1-ILPPOS</div>
                            <div class="pt-1 text-[8.5px] text-neutral-500" x-text="'Dicetak: ' + receiptData.date + ' ' + receiptData.time"></div>
                        </div>

                    </div>
                </template>

            </div>

            <!-- Struk Modal Footer -->
            <div class="p-4 bg-white border-t border-gray-200 flex justify-between items-center shrink-0">
                <span class="text-xs text-gray-500 font-mono" x-text="receiptData?.id ? ('No. Faktur: ' + receiptData.id) : ''"></span>
                <div class="flex gap-2">
                    <button type="button" @click="showReceiptModal = false" class="px-4 py-2 border rounded-xl font-bold text-xs text-gray-600 hover:bg-gray-50 cursor-pointer">
                        Tutup
                    </button>
                    <button type="button" @click="printReceipt()" class="px-5 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs rounded-xl flex items-center gap-1.5 shadow-xs transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[16px]">print</span>
                        <span>Cetak Struk Sekarang</span>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- Toast Notification -->
    <div x-cloak
         x-show="showToast && !!toastMessage"
         class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-2xl shadow-xl border text-xs font-bold"
         :class="toastType === 'error' ? 'bg-red-900 text-white border-red-800' : 'bg-gray-900 text-white border-gray-800'"
         style="display: none;">
        <span class="material-symbols-rounded text-emerald-400" x-show="toastType === 'success'">check_circle</span>
        <span class="material-symbols-rounded text-red-400" x-show="toastType === 'error'">error</span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
