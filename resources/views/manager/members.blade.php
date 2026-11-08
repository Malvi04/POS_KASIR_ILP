@extends('layouts.app')

@section('title', 'Database Anggota Multi-Cabang & Plafon Kasbon - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    // API State
    members: [],
    summary: {
        total_members: 0,
        active_members: 0,
        blocked_members: 0,
        total_credit_limit: 0,
        total_current_debt: 0,
        total_remaining_credit: 0
    },
    branches: [
        'Cabang 1 - Mill / Pabrik',
        'Cabang 2 - Kebun Barat',
        'Cabang 3 - Kebun Timur'
    ],
    departments: [],
    
    // Filters & Pagination
    searchQuery: '',
    selectedBranch: 'all',
    selectedDept: 'all',
    statusFilter: 'all',
    currentPage: 1,
    lastPage: 1,
    totalRecords: 0,
    perPage: 15,
    isLoading: false,
    debounceTimer: null,

    // Modals
    showModal: false,
    modalMode: 'create', // 'create' | 'edit'
    showLimitModal: false,
    showSettlementModal: false,
    showDeleteModal: false,
    selectedMember: null,
    deleteConfirmInput: '',
    
    // Plafon Form
    newLimitAmount: 5000000,
    limitNotes: '',
    limitProcessedBy: 'Manager Koperasi',

    // Settlement Form
    settlementAmount: 0,
    settlementType: 'PAYMENT_SETTLEMENT',
    settlementNotes: '',

    // Form Member
    formData: {
        id: null,
        nik: '',
        name: '',
        department: 'Logistik & Distribusi',
        branch: 'Cabang 1 - Mill / Pabrik',
        phone: '',
        credit_limit: 5000000,
        status: 'AKTIF',
        notes: ''
    },
    formErrors: {},

    // Toast
    toastMessage: '',
    toastType: 'success',
    showToast: false,

    // Initializer
    async initMembers() {
        await this.fetchMembers(1);
    },

    // Debounced Search (300ms) to ensure zero lag
    onSearchInput() {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            this.fetchMembers(1);
        }, 300);
    },

    // Main API Fetch Function
    async fetchMembers(page = 1) {
        this.isLoading = true;
        this.currentPage = page;

        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                per_page: this.perPage,
                search: this.searchQuery.trim(),
                branch: this.selectedBranch,
                department: this.selectedDept,
                status: this.statusFilter
            });

            const res = await fetch(`/api/members?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.members = json.data;
                    this.summary = json.summary || this.summary;
                    this.branches = json.branches || this.branches;
                    this.departments = json.departments || this.departments;
                    this.currentPage = json.meta.current_page;
                    this.lastPage = json.meta.last_page;
                    this.totalRecords = json.meta.total;
                }
            } else {
                this.triggerToast('Gagal memuat data anggota dari server API.', 'error');
            }
        } catch (err) {
            console.error('Fetch members error:', err);
            this.triggerToast('Terjadi kesalahan jaringan saat memanggil API.', 'error');
        } finally {
            this.isLoading = false;
        }
    },

    // Open Create Modal
    openCreateModal() {
        this.modalMode = 'create';
        this.formErrors = {};
        this.formData = {
            id: null,
            nik: '',
            name: '',
            department: this.departments[0] || 'Logistik & Gudang',
            branch: this.selectedBranch !== 'all' ? this.selectedBranch : 'Cabang 1 - Mill / Pabrik',
            phone: '',
            credit_limit: 5000000,
            status: 'AKTIF',
            notes: 'Pendaftaran anggota baru'
        };
        this.showModal = true;
    },

    // Open Edit Modal
    openEditModal(m) {
        this.modalMode = 'edit';
        this.formErrors = {};
        this.formData = {
            id: m.id,
            nik: m.nik,
            name: m.name,
            department: m.department,
            branch: m.branch || 'Cabang 1 - Mill / Pabrik',
            phone: m.phone || '',
            credit_limit: m.credit_limit,
            status: m.status,
            notes: m.notes || ''
        };
        this.showModal = true;
    },

    // Save Member (POST or PUT)
    async saveMember() {
        this.formErrors = {};
        if (!this.formData.name || !this.formData.name.trim()) this.formErrors.name = 'Nama lengkap wajib diisi.';
        if (!this.formData.nik || !this.formData.nik.trim()) this.formErrors.nik = 'NIK karyawan wajib diisi.';
        if (!this.formData.department) this.formErrors.department = 'Departemen wajib dipilih.';
        if (!this.formData.branch) this.formErrors.branch = 'Cabang wajib dipilih.';
        if (Object.keys(this.formErrors).length > 0) return;

        this.isLoading = true;
        try {
            const url = this.modalMode === 'create' ? '/api/members' : `/api/members/${this.formData.id}`;
            const method = this.modalMode === 'create' ? 'POST' : 'PUT';

            const res = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(this.formData)
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.showModal = false;
                this.triggerToast(json.message);
                await this.fetchMembers(this.currentPage);
            } else {
                this.triggerToast(json.message || 'Gagal menyimpan data anggota.', 'error');
                if (json.errors) {
                    this.formErrors = json.errors;
                }
            }
        } catch (err) {
            this.triggerToast('Terjadi kesalahan jaringan saat menyimpan data.', 'error');
        } finally {
            this.isLoading = false;
        }
    },

    // Quick Plafon Limit Adjustment Modal
    openLimitModal(m) {
        this.selectedMember = m;
        this.newLimitAmount = Number(m.credit_limit) || 0;
        this.limitNotes = 'Penyesuaian batas maksimal kasbon bulanan';
        this.showLimitModal = true;
    },

    async saveCreditLimit() {
        if (!this.selectedMember) return;
        const amt = Number(this.newLimitAmount);
        if (amt < Number(this.selectedMember.current_debt)) {
            this.triggerToast('Plafon baru tidak boleh lebih kecil dari saldo kasbon berjalan!', 'error');
            return;
        }

        this.isLoading = true;
        try {
            const res = await fetch(`/api/members/${this.selectedMember.id}/credit-limit`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    credit_limit: amt,
                    notes: this.limitNotes,
                    processed_by: 'Manager Koperasi'
                })
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.showLimitModal = false;
                this.triggerToast(json.message);
                await this.fetchMembers(this.currentPage);
            } else {
                this.triggerToast(json.message || 'Gagal mengubah plafon kredit.', 'error');
            }
        } catch (e) {
            this.triggerToast('Koneksi terputus saat memperbarui plafon.', 'error');
        } finally {
            this.isLoading = false;
        }
    },

    // Quick Settlement Modal
    openSettlementModal(m) {
        this.selectedMember = m;
        this.settlementAmount = Number(m.current_debt) || 0;
        this.settlementType = 'PAYMENT_SETTLEMENT';
        this.settlementNotes = 'Pelunasan kasbon tunai/transfer di kantor koperasi';
        this.showSettlementModal = true;
    },

    async saveSettlement() {
        if (!this.selectedMember) return;
        const amt = Number(this.settlementAmount);
        if (amt <= 0) {
            this.triggerToast('Nominal pelunasan harus lebih dari 0.', 'error');
            return;
        }
        if (amt > Number(this.selectedMember.current_debt)) {
            this.triggerToast('Nominal melebihi total kasbon berjalan!', 'error');
            return;
        }

        this.isLoading = true;
        try {
            const res = await fetch(`/api/members/${this.selectedMember.id}/settlement`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    amount: amt,
                    type: this.settlementType,
                    notes: this.settlementNotes,
                    processed_by: 'Finance Koperasi ILP'
                })
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.showSettlementModal = false;
                this.triggerToast(json.message);
                await this.fetchMembers(this.currentPage);
            } else {
                this.triggerToast(json.message || 'Gagal memproses pelunasan kasbon.', 'error');
            }
        } catch (e) {
            this.triggerToast('Koneksi terputus saat memproses pelunasan.', 'error');
        } finally {
            this.isLoading = false;
        }
    },

    // Toggle Active Status
    async toggleStatus(m) {
        const newStatus = m.status === 'AKTIF' ? 'DIBLOKIR' : 'AKTIF';
        this.isLoading = true;
        try {
            const res = await fetch(`/api/members/${m.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    name: m.name,
                    department: m.department,
                    branch: m.branch,
                    phone: m.phone,
                    status: newStatus,
                    notes: `Status diubah menjadi ${newStatus} oleh Manager.`
                })
            });

            const json = await res.json();
            if (res.ok && json.success) {
                m.status = newStatus;
                this.triggerToast(`Status anggota '${m.name}' berhasil diubah ke ${newStatus}.`);
                await this.fetchMembers(this.currentPage);
            } else {
                this.triggerToast(json.message || 'Gagal mengubah status.', 'error');
            }
        } catch (e) {
            this.triggerToast('Kesalahan koneksi saat mengubah status.', 'error');
        } finally {
            this.isLoading = false;
        }
    },

    // Delete Modal
    openDeleteModal(m) {
        this.selectedMember = m;
        this.deleteConfirmInput = '';
        this.showDeleteModal = true;
    },

    async confirmDelete() {
        if (!this.selectedMember) return;
        if (this.deleteConfirmInput.trim().toLowerCase() !== 'hapus') return;

        this.isLoading = true;
        try {
            const res = await fetch(`/api/members/${this.selectedMember.id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.showDeleteModal = false;
                this.triggerToast(json.message);
                await this.fetchMembers(1);
            } else {
                this.triggerToast(json.message || 'Gagal menghapus anggota.', 'error');
            }
        } catch (e) {
            this.triggerToast('Gagal terhubung ke API.', 'error');
        } finally {
            this.isLoading = false;
        }
    },

    getBranchBadgeClass(b) {
        if (!b) return 'bg-gray-100 text-gray-700 border-gray-200';
        if (b.includes('Mill') || b.includes('Pabrik')) return 'bg-blue-50 text-[#0052cc] border-blue-200';
        if (b.includes('Barat')) return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        if (b.includes('Timur')) return 'bg-purple-50 text-purple-700 border-purple-200';
        return 'bg-amber-50 text-amber-700 border-amber-200';
    },

    triggerToast(msg, type = 'success') {
        this.toastMessage = msg;
        this.toastType = type;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3500);
    }
}"
x-init="initMembers()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden relative">
    
    <!-- Sidebar -->
    <x-manager-sidebar active="members" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        
        <!-- Top Header -->
        <header class="h-[72px] bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-3">
                <h1 class="text-[18px] font-black text-gray-900 tracking-tight">Database Anggota Multi-Cabang & Kasbon</h1>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200">PT Indo Lampung Perkasa</span>
            </div>

            <div class="flex items-center gap-4 text-gray-500">
                <a href="{{ url('/reports') }}" class="flex items-center gap-2 px-3 py-1.5 text-xs font-bold text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 transition-colors">
                    <span class="material-symbols-rounded text-[18px] text-[#0052cc]">assessment</span>
                    Laporan Piutang
                </a>
                <button @click="fetchMembers(currentPage)" class="p-2 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors" title="Refresh Data API">
                    <span class="material-symbols-rounded text-[22px]" :class="isLoading ? 'animate-spin text-[#0052cc]' : ''">autorenew</span>
                </button>
                <div class="h-6 w-px bg-gray-200"></div>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-[13px] font-bold text-gray-700">API Terhubung</span>
                </div>
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-8 flex flex-col">
            
            <!-- Transparent 3-Branch Selector Bar -->
            <div class="bg-white rounded-2xl border border-gray-200/90 p-3 shadow-xs mb-6 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-rounded text-gray-400 text-[20px] ml-2">hub</span>
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider mr-2">Filter Cabang:</span>
                    <div class="inline-flex p-1 bg-gray-100 rounded-xl gap-1">
                        <button type="button"
                                @click="selectedBranch = 'all'; fetchMembers(1);"
                                :class="selectedBranch === 'all' ? 'bg-white text-[#0052cc] shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                                class="px-3.5 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1.5">
                            <span>Semua 3 Cabang</span>
                            <span class="text-[10px] px-1.5 py-0.2 bg-gray-200 rounded-full" x-text="summary.total_members"></span>
                        </button>
                        <template x-for="b in branches" :key="b">
                            <button type="button"
                                    @click="selectedBranch = b; fetchMembers(1);"
                                    :class="selectedBranch === b ? 'bg-white text-[#0052cc] shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                                    class="px-3 py-1.5 rounded-lg text-xs transition-all whitespace-nowrap"
                                    x-text="b">
                            </button>
                        </template>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-400">Akses terpusat transparan real-time antar 3 cabang</span>
                </div>
            </div>

            <!-- Summary KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                <!-- Card 1: TOTAL ANGGOTA -->
                <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">TOTAL ANGGOTA</span>
                        <span class="w-8 h-8 rounded-lg bg-blue-50 text-[#0052cc] flex items-center justify-center">
                            <span class="material-symbols-rounded text-[18px]">group</span>
                        </span>
                    </div>
                    <div class="flex items-baseline gap-2 mt-2">
                        <h3 class="text-[26px] font-black text-gray-900 leading-none" x-text="summary.total_members">0</h3>
                        <span class="text-emerald-600 text-xs font-bold" x-text="summary.active_members + ' Aktif'"></span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-2" x-text="(summary.blocked_members || 0) + ' dibekukan / plafon penuh'"></p>
                </div>
                
                <!-- Card 2: TOTAL PLAFON TERDAFTAR -->
                <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">TOTAL PLAFON KREDIT</span>
                        <span class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center">
                            <span class="material-symbols-rounded text-[18px]">account_balance_wallet</span>
                        </span>
                    </div>
                    <div class="mt-2">
                        <h3 class="text-[20px] font-mono font-black text-gray-900 leading-none" x-text="formatRupiah(summary.total_credit_limit)">Rp 0</h3>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-2">Plafon kasbon maksimal bulanan</p>
                </div>

                <!-- Card 3: TOTAL PIUTANG BERJALAN -->
                <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">PIUTANG KASBON BERJALAN</span>
                        <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                            <span class="material-symbols-rounded text-[18px]">receipt_long</span>
                        </span>
                    </div>
                    <div class="mt-2">
                        <h3 class="text-[20px] font-mono font-black text-amber-600 leading-none" x-text="formatRupiah(summary.total_current_debt)">Rp 0</h3>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-2">Potong gaji payroll PT ILP</p>
                </div>

                <!-- Card 4: SISA PLAFON TERSEDIA -->
                <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">SISA PLAFON TERSEDIA</span>
                        <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <span class="material-symbols-rounded text-[18px]">check_circle</span>
                        </span>
                    </div>
                    <div class="mt-2">
                        <h3 class="text-[20px] font-mono font-black text-emerald-600 leading-none" x-text="formatRupiah(summary.total_remaining_credit)">Rp 0</h3>
                    </div>
                    <div class="flex items-center gap-1.5 mt-2">
                        <div class="flex-1 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                            <div class="bg-emerald-500 h-full rounded-full"
                                 :style="'width: ' + (summary.total_credit_limit > 0 ? ((summary.total_remaining_credit / summary.total_credit_limit) * 100) : 0) + '%'"></div>
                        </div>
                        <span class="text-[10px] font-bold text-gray-500"
                              x-text="(summary.total_credit_limit > 0 ? Math.round((summary.total_remaining_credit / summary.total_credit_limit) * 100) : 0) + '%'"></span>
                    </div>
                </div>
            </div>

            <!-- Search, Department & Status Filters, and Add Button -->
            <div class="flex flex-col md:flex-row gap-3 mb-6">
                <!-- Search Input with Debounce -->
                <div class="relative flex-1">
                    <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                    <input type="text"
                           x-model="searchQuery"
                           @input="onSearchInput()"
                           placeholder="Cari NIK karyawan, nama, departemen, atau kode..."
                           class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-[#0052cc]/20 focus:border-[#0052cc] text-[13px] shadow-xs transition-all">
                </div>

                <!-- Department Filter -->
                <div class="shrink-0">
                    <select x-model="selectedDept"
                            @change="fetchMembers(1)"
                            class="w-full md:w-auto px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-[13px] font-semibold text-gray-700 shadow-xs cursor-pointer focus:outline-none focus:border-[#0052cc]">
                        <option value="all">Semua Departemen</option>
                        <template x-for="d in departments" :key="d">
                            <option :value="d" x-text="d"></option>
                        </template>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="shrink-0">
                    <select x-model="statusFilter"
                            @change="fetchMembers(1)"
                            class="w-full md:w-auto px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-[13px] font-semibold text-gray-700 shadow-xs cursor-pointer focus:outline-none focus:border-[#0052cc]">
                        <option value="all">Semua Status</option>
                        <option value="AKTIF">Aktif</option>
                        <option value="DIBLOKIR">Diblokir / Penuh</option>
                    </select>
                </div>

                <!-- Add Button -->
                <div class="shrink-0">
                    <button type="button"
                            @click="openCreateModal()"
                            class="w-full md:w-auto flex items-center justify-center gap-2 px-5 py-2.5 bg-[#0052cc] rounded-xl text-[13px] font-bold text-white hover:bg-[#0043a8] transition-colors shadow-xs active:scale-95">
                        <span class="material-symbols-rounded text-[18px]">person_add</span>
                        Tambah Karyawan
                    </button>
                </div>
            </div>

            <!-- Data Table Card -->
            <div class="bg-white border border-gray-200/90 rounded-2xl shadow-xs overflow-hidden flex flex-col mb-6">
                <div class="overflow-x-auto min-w-0 w-full">
                    <table class="w-full text-left text-[13px] min-w-[1050px] border-collapse">
                        <thead class="bg-gray-50/90 border-b border-gray-200 text-gray-600 text-[11px] font-bold tracking-wider uppercase whitespace-nowrap">
                            <tr>
                                <th class="px-6 py-4 text-left">NAMA KARYAWAN & NO. HP</th>
                                <th class="px-6 py-4 text-left">NIK & KODE ANGGOTA</th>
                                <th class="px-6 py-4 text-left">DEPARTEMEN</th>
                                <th class="px-6 py-4 text-left">CABANG PENUGASAN</th>
                                <th class="px-6 py-4 text-right">PLAFON BULANAN</th>
                                <th class="px-6 py-4 text-right">KASBON BERJALAN</th>
                                <th class="px-6 py-4 text-right">SISA PLAFON</th>
                                <th class="px-6 py-4 text-center">STATUS</th>
                                <th class="px-6 py-4 text-center">AKSI MANAJEMEN</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-100">
                            <!-- Loading Skeleton -->
                            <template x-if="isLoading && members.length === 0">
                                <tr>
                                    <td colspan="9" class="px-6 py-12 text-center text-gray-400">
                                        <div class="flex items-center justify-center gap-3">
                                            <span class="material-symbols-rounded animate-spin text-[26px] text-[#0052cc]">progress_activity</span>
                                            <span class="text-sm font-semibold">Mengambil data anggota dari backend API...</span>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <!-- Empty State -->
                            <template x-if="!isLoading && members.length === 0">
                                <tr>
                                    <td colspan="9" class="px-6 py-14 text-center text-gray-400">
                                        <span class="material-symbols-rounded text-[48px] text-gray-300 block mb-2">person_search</span>
                                        <p class="font-bold text-gray-700 text-sm">Tidak ada data anggota ditemukan</p>
                                        <p class="text-xs text-gray-400 mt-1">Coba sesuaikan kata kunci pencarian atau filter cabang.</p>
                                    </td>
                                </tr>
                            </template>

                            <!-- Member Rows -->
                            <template x-for="m in members" :key="m.id">
                                <tr class="hover:bg-blue-50/20 transition-colors">
                                    <!-- Nama & HP -->
                                    <td class="px-6 py-4">
                                        <a :href="'/member-detail?id=' + m.id" class="font-bold text-gray-900 hover:text-[#0052cc] leading-tight block" x-text="m.name"></a>
                                        <span class="text-[11px] text-gray-400 flex items-center gap-1 mt-0.5">
                                            <span class="material-symbols-rounded text-[13px]">phone</span>
                                            <span x-text="m.phone || '-'"></span>
                                        </span>
                                    </td>

                                    <!-- NIK & Member Code -->
                                    <td class="px-6 py-4 font-mono whitespace-nowrap">
                                        <span class="block font-bold text-xs text-gray-800" x-text="m.nik"></span>
                                        <span class="text-[10px] text-gray-400" x-text="m.member_code"></span>
                                    </td>

                                    <!-- Departemen -->
                                    <td class="px-6 py-4 text-gray-700 font-semibold whitespace-nowrap" x-text="m.department"></td>

                                    <!-- Cabang -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold border"
                                              :class="getBranchBadgeClass(m.branch)">
                                            <span class="material-symbols-rounded text-[14px]">storefront</span>
                                            <span x-text="m.branch"></span>
                                        </span>
                                    </td>

                                    <!-- Plafon Bulanan -->
                                    <td class="px-6 py-4 text-right font-mono font-bold text-gray-900 whitespace-nowrap">
                                        <span x-text="formatRupiah(m.credit_limit)"></span>
                                    </td>

                                    <!-- Kasbon Berjalan -->
                                    <td class="px-6 py-4 text-right font-mono font-bold whitespace-nowrap"
                                        :class="m.current_debt > 0 ? 'text-amber-600' : 'text-gray-400'"
                                        x-text="formatRupiah(m.current_debt)">
                                    </td>

                                    <!-- Sisa Plafon -->
                                    <td class="px-6 py-4 text-right whitespace-nowrap">
                                        <div class="font-mono font-bold text-emerald-600" x-text="formatRupiah(m.remaining_credit)"></div>
                                        <div class="text-[10px] text-gray-400" x-text="'Terpakai ' + (m.debt_utilization_percent || 0) + '%'"></div>
                                    </td>

                                    <!-- Status Toggle -->
                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        <button type="button"
                                                @click="toggleStatus(m)"
                                                :title="m.status === 'AKTIF' ? 'Klik untuk memblokir' : 'Klik untuk mengaktifkan'"
                                                :class="m.status === 'AKTIF' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200'"
                                                class="px-2.5 py-1 rounded-full text-[11px] font-bold border transition-transform active:scale-95 inline-flex items-center gap-1 cursor-pointer">
                                            <span class="w-1.5 h-1.5 rounded-full" :class="m.status === 'AKTIF' ? 'bg-emerald-500' : 'bg-red-500'"></span>
                                            <span x-text="m.status"></span>
                                        </button>
                                    </td>

                                    <!-- Aksi -->
                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1">
                                            <!-- Detail Profil & Riwayat -->
                                            <a :href="'/member-detail?id=' + m.id"
                                               class="p-1.5 text-gray-400 hover:text-[#0052cc] hover:bg-blue-50 rounded-lg transition-colors"
                                               title="Detail Profil & Riwayat Kasbon">
                                                <span class="material-symbols-rounded text-[18px]">visibility</span>
                                            </a>

                                            <!-- Atur Plafon Kasbon -->
                                            <button type="button"
                                                    @click="openLimitModal(m)"
                                                    class="p-1.5 text-gray-400 hover:text-purple-600 hover:bg-purple-50 rounded-lg transition-colors"
                                                    title="Atur Plafon Kasbon Bulanan">
                                                <span class="material-symbols-rounded text-[18px]">account_balance_wallet</span>
                                            </button>

                                            <!-- Catat Pelunasan -->
                                            <button type="button"
                                                    @click="openSettlementModal(m)"
                                                    :disabled="m.current_debt <= 0"
                                                    class="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                                    title="Catat Pelunasan / Potong Gaji">
                                                <span class="material-symbols-rounded text-[18px]">price_check</span>
                                            </button>

                                            <!-- Edit Profil -->
                                            <button type="button"
                                                    @click="openEditModal(m)"
                                                    class="p-1.5 text-gray-400 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
                                                    title="Edit Data Karyawan">
                                                <span class="material-symbols-rounded text-[18px]">edit</span>
                                            </button>

                                            <!-- Hapus -->
                                            <button type="button"
                                                    @click="openDeleteModal(m)"
                                                    class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                    title="Hapus Karyawan">
                                                <span class="material-symbols-rounded text-[18px]">delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="px-6 py-4 bg-gray-50/70 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-600">
                    <div>
                        Menampilkan <span class="font-bold text-gray-900" x-text="members.length"></span> dari
                        <span class="font-bold text-gray-900" x-text="totalRecords"></span> data karyawan
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="fetchMembers(currentPage - 1)"
                                :disabled="currentPage <= 1 || isLoading"
                                class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                            Sebelumnya
                        </button>
                        <span class="font-mono px-2 font-bold" x-text="currentPage + ' / ' + lastPage"></span>
                        <button type="button"
                                @click="fetchMembers(currentPage + 1)"
                                :disabled="currentPage >= lastPage || isLoading"
                                class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                            Selanjutnya
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Tambah / Edit Anggota -->
    <div x-show="showModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-gray-200"
             @click.outside="showModal = false">
            <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center font-bold">
                        <span class="material-symbols-rounded text-[22px]" x-text="modalMode === 'create' ? 'person_add' : 'edit'"></span>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-gray-900" x-text="modalMode === 'create' ? 'Daftarkan Karyawan Baru' : 'Edit Data Karyawan'"></h3>
                        <p class="text-xs text-gray-400">Master Data Anggota Koperasi Multi-Cabang</p>
                    </div>
                </div>
                <button type="button" @click="showModal = false" class="text-gray-400 hover:text-gray-600 p-1">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>

            <form @submit.prevent="saveMember()" class="space-y-4 mt-5">
                <!-- NIK & Nama -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">NIK Karyawan <span class="text-red-500">*</span></label>
                        <input type="text"
                               x-model="formData.nik"
                               :disabled="modalMode === 'edit'"
                               placeholder="Contoh: 32750100..."
                               class="w-full px-3.5 py-2 rounded-xl border text-xs font-mono focus:ring-2 focus:ring-[#0052cc]/20 focus:border-[#0052cc] focus:outline-none"
                               :class="formErrors.nik ? 'border-red-400 bg-red-50/20' : 'border-gray-300'">
                        <p x-show="formErrors.nik" x-text="formErrors.nik" class="text-[11px] text-red-500 mt-1"></p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input type="text"
                               x-model="formData.name"
                               placeholder="Nama karyawan"
                               class="w-full px-3.5 py-2 rounded-xl border text-xs focus:ring-2 focus:ring-[#0052cc]/20 focus:border-[#0052cc] focus:outline-none"
                               :class="formErrors.name ? 'border-red-400 bg-red-50/20' : 'border-gray-300'">
                        <p x-show="formErrors.name" x-text="formErrors.name" class="text-[11px] text-red-500 mt-1"></p>
                    </div>
                </div>

                <!-- Cabang Penugasan (Multi-Cabang) & Departemen -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Cabang Penugasan <span class="text-red-500">*</span></label>
                        <select x-model="formData.branch"
                                class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs font-semibold focus:outline-none focus:border-[#0052cc]">
                            <template x-for="b in branches" :key="b">
                                <option :value="b" x-text="b"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Departemen / Divisi <span class="text-red-500">*</span></label>
                        <input type="text"
                               x-model="formData.department"
                               placeholder="Misal: Operasional Kebun"
                               class="w-full px-3.5 py-2 rounded-xl border border-gray-300 text-xs focus:outline-none focus:border-[#0052cc]">
                    </div>
                </div>

                <!-- Plafon Kredit & No HP -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Plafon Kasbon Bulanan (Rp) <span class="text-red-500">*</span></label>
                        <input type="number"
                               x-model="formData.credit_limit"
                               :disabled="modalMode === 'edit'"
                               class="w-full px-3.5 py-2 rounded-xl border border-gray-300 text-xs font-mono font-bold focus:outline-none focus:border-[#0052cc]">
                        <p class="text-[10px] text-gray-400 mt-1" x-text="modalMode === 'edit' ? 'Untuk ubah limit gunakan tombol Atur Plafon' : 'Batas maksimal kasbon per bulan'"></p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">No. WhatsApp / HP</label>
                        <input type="text"
                               x-model="formData.phone"
                               placeholder="0812-xxxx-xxxx"
                               class="w-full px-3.5 py-2 rounded-xl border border-gray-300 text-xs focus:outline-none focus:border-[#0052cc]">
                    </div>
                </div>

                <!-- Status Keanggotaan -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Status Keanggotaan</label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" value="AKTIF" x-model="formData.status" class="text-[#0052cc]">
                            <span class="text-xs font-bold text-emerald-700">AKTIF (Eligible Kasbon)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" value="DIBLOKIR" x-model="formData.status" class="text-[#0052cc]">
                            <span class="text-xs font-bold text-red-700">DIBLOKIR (Transaksi Ditolak)</span>
                        </label>
                    </div>
                </div>

                <!-- Catatan -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Catatan Tambahan</label>
                    <textarea x-model="formData.notes"
                              rows="2"
                              placeholder="Keterangan penugasan atau riwayat"
                              class="w-full px-3.5 py-2 rounded-xl border border-gray-300 text-xs focus:outline-none focus:border-[#0052cc]"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100">
                    <button type="button" @click="showModal = false" class="px-4 py-2 border border-gray-300 rounded-xl text-xs font-bold text-gray-600 hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="submit"
                            :disabled="isLoading"
                            class="px-5 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5">
                        <span x-show="isLoading" class="material-symbols-rounded text-sm animate-spin">progress_activity</span>
                        <span x-text="modalMode === 'create' ? 'Daftarkan Anggota' : 'Simpan Perubahan'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Atur Plafon Kasbon Bulanan -->
    <div x-show="showLimitModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-gray-200"
             @click.outside="showLimitModal = false">
            <div class="flex items-center gap-3 pb-3 border-b border-gray-100">
                <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                    <span class="material-symbols-rounded text-[22px]">account_balance_wallet</span>
                </div>
                <div>
                    <h3 class="text-base font-black text-gray-900">Atur Batas Plafon Kasbon</h3>
                    <p class="text-xs text-gray-400" x-text="selectedMember ? selectedMember.name + ' (' + selectedMember.nik + ')' : ''"></p>
                </div>
            </div>

            <div class="space-y-4 mt-5">
                <div class="p-3.5 bg-gray-50 rounded-2xl border border-gray-200/80 space-y-1.5 text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Plafon Saat Ini:</span>
                        <span class="font-mono font-bold text-gray-800" x-text="selectedMember ? formatRupiah(selectedMember.credit_limit) : ''"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Kasbon Berjalan:</span>
                        <span class="font-mono font-bold text-amber-600" x-text="selectedMember ? formatRupiah(selectedMember.current_debt) : ''"></span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Plafon Bulanan Baru (Rp)</label>
                    <input type="number"
                           x-model="newLimitAmount"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-mono font-black text-purple-700 focus:outline-none focus:border-purple-600">
                    <p class="text-[11px] text-gray-400 mt-1">Plafon otomatis terpotong saat kasir memproses kasbon.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Alasan / Catatan Penyesuaian</label>
                    <input type="text"
                           x-model="limitNotes"
                           placeholder="Contoh: Kenaikan pangkat / persetujuan manajer"
                           class="w-full px-3.5 py-2 rounded-xl border border-gray-300 text-xs focus:outline-none focus:border-[#0052cc]">
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100">
                    <button type="button" @click="showLimitModal = false" class="px-4 py-2 border border-gray-300 rounded-xl text-xs font-bold text-gray-600 hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="button"
                            @click="saveCreditLimit()"
                            :disabled="isLoading"
                            class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5">
                        <span x-show="isLoading" class="material-symbols-rounded text-sm animate-spin">progress_activity</span>
                        <span>Perbarui Plafon</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Catat Pelunasan / Potong Gaji -->
    <div x-show="showSettlementModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-gray-200"
             @click.outside="showSettlementModal = false">
            <div class="flex items-center gap-3 pb-3 border-b border-gray-100">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <span class="material-symbols-rounded text-[22px]">price_check</span>
                </div>
                <div>
                    <h3 class="text-base font-black text-gray-900">Catat Pelunasan Kasbon</h3>
                    <p class="text-xs text-gray-400" x-text="selectedMember ? selectedMember.name : ''"></p>
                </div>
            </div>

            <div class="space-y-4 mt-5">
                <div class="p-3.5 bg-gray-50 rounded-2xl border border-gray-200/80 space-y-1.5 text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Total Kasbon Berjalan:</span>
                        <span class="font-mono font-bold text-amber-600 text-sm" x-text="selectedMember ? formatRupiah(selectedMember.current_debt) : ''"></span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Nominal Pembayaran (Rp)</label>
                    <input type="number"
                           x-model="settlementAmount"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-mono font-black text-emerald-700 focus:outline-none focus:border-emerald-600">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Tipe Pembayaran</label>
                    <select x-model="settlementType" class="w-full px-3.5 py-2 rounded-xl border border-gray-300 text-xs font-semibold focus:outline-none">
                        <option value="PAYMENT_SETTLEMENT">Pelunasan Tunai / Transfer</option>
                        <option value="PAYROLL_DEDUCTION">Potong Gaji Payroll PT ILP</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Keterangan</label>
                    <input type="text"
                           x-model="settlementNotes"
                           placeholder="Keterangan bukti pembayaran"
                           class="w-full px-3.5 py-2 rounded-xl border border-gray-300 text-xs focus:outline-none">
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100">
                    <button type="button" @click="showSettlementModal = false" class="px-4 py-2 border border-gray-300 rounded-xl text-xs font-bold text-gray-600 hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="button"
                            @click="saveSettlement()"
                            :disabled="isLoading"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5">
                        <span x-show="isLoading" class="material-symbols-rounded text-sm animate-spin">progress_activity</span>
                        <span>Konfirmasi Pembayaran</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div x-show="showDeleteModal"
         class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-gray-200"
             @click.outside="showDeleteModal = false">
            <div class="w-12 h-12 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-rounded text-[28px]">warning</span>
            </div>
            <h3 class="text-center font-black text-gray-900 text-base mb-1">Hapus Anggota Karyawan?</h3>
            <p class="text-center text-xs text-gray-500 mb-4" x-text="'Karyawan ' + (selectedMember ? selectedMember.name : '') + ' akan dihapus dari sistem.'"></p>

            <div class="mb-4">
                <label class="block text-[11px] font-bold text-gray-500 mb-1 text-center">Ketik <span class="text-red-600 font-mono">hapus</span> untuk konfirmasi</label>
                <input type="text"
                       x-model="deleteConfirmInput"
                       placeholder="hapus"
                       class="w-full text-center px-3 py-2 border rounded-xl font-mono text-xs focus:outline-none">
            </div>

            <div class="flex gap-2">
                <button type="button" @click="showDeleteModal = false" class="flex-1 py-2 rounded-xl border border-gray-200 text-xs font-bold text-gray-600">Batal</button>
                <button type="button"
                        @click="confirmDelete()"
                        :disabled="deleteConfirmInput.trim().toLowerCase() !== 'hapus' || isLoading"
                        class="flex-1 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-bold disabled:opacity-40">
                    Hapus
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div x-show="showToast"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-4"
         class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-2xl shadow-xl border text-xs font-bold"
         :class="toastType === 'error' ? 'bg-red-900 text-white border-red-800' : 'bg-gray-900 text-white border-gray-800'"
         style="display: none;">
        <span class="material-symbols-rounded text-emerald-400" x-show="toastType === 'success'">check_circle</span>
        <span class="material-symbols-rounded text-red-400" x-show="toastType === 'error'">error</span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
