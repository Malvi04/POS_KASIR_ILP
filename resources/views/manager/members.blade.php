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
        'Cabang Pusat',
        'Cabang A',
        'Cabang B'
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
    nextId: null,
    nextCode: '',
    
    // Plafon Form
    newLimitAmount: 5000000,
    limitNotes: '',
    limitProcessedBy: @js(auth()->user()?->name ?? 'Manager'),

    // Settlement Form
    settlementAmount: 0,
    settlementType: 'PAYMENT_SETTLEMENT',
    settlementNotes: '',

    // Form Member
    formData: {
        id: null,
        customer_id: '',
        member_code: '',
        kode_out: '',
        nik: '',
        name: '',
        department: 'Logistik & Distribusi',
        division: '',
        address: '',
        branch: 'Cabang Pusat',
        phone: '',
        fax: '',
        contact: '',
        tempo_bayar: '',
        idtk: '',
        credit_limit: 1000000,
        no_rekening: '',
        status: 'AKTIF',
        notes: ''
    },
    formErrors: {},

    // Import & Export Excel Feature State
    showImportModal: false,
    importStep: 'upload', // 'upload', 'invalid_structure', 'preview', 'processing', 'result'
    importFile: null,
    importFileName: '',
    isUploading: false,
    isDragging: false,
    validationData: null,
    validationErrorDetails: null,
    duplicateAction: 'update',
    importResult: null,

    openImportModal() {
        this.showImportModal = true;
        this.importStep = 'upload';
        this.importFile = null;
        this.importFileName = '';
        this.isUploading = false;
        this.isDragging = false;
        this.validationData = null;
        this.validationErrorDetails = null;
        this.duplicateAction = 'update';
        this.importResult = null;
        this.$nextTick(() => {
            const f = document.getElementById('memberExcelFileInput');
            if (f) f.value = '';
        });
    },

    triggerFileInput() {
        const f = document.getElementById('memberExcelFileInput');
        if (f) f.click();
    },

    resetFileInput() {
        this.importFile = null;
        this.importFileName = '';
        this.isDragging = false;
        const f = document.getElementById('memberExcelFileInput');
        if (f) f.value = '';
    },

    onFileSelected(event) {
        const file = event.target.files && event.target.files[0];
        if (file) {
            this.importFile = file;
            this.importFileName = file.name;
        }
    },

    handleFileDrop(event) {
        this.isDragging = false;
        const files = event.dataTransfer ? event.dataTransfer.files : null;
        if (files && files.length > 0) {
            this.importFile = files[0];
            this.importFileName = files[0].name;
        }
    },

    async uploadAndValidateFile() {
        if (!this.importFile) {
            this.triggerToast('Silakan pilih file Excel terlebih dahulu.', 'error');
            return;
        }

        this.isUploading = true;
        const fd = new FormData();
        fd.append('file', this.importFile);

        try {
            const res = await fetch('/api/members/import/validate', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: fd
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.validationData = json.data;
                this.importStep = 'preview';
                this.triggerToast('Struktur Excel anggota valid! Menampilkan pratinjau data...');
            } else {
                this.validationErrorDetails = json.details || { message: json.message || 'Format file tidak sesuai standar ILP.' };
                this.importStep = 'invalid_structure';
                this.triggerToast(json.message || 'Struktur file Excel tidak sesuai standar Master Anggota ILP.', 'error');
            }
        } catch (e) {
            console.error('Validation error:', e);
            this.triggerToast('Gagal memvalidasi file: ' + e.message, 'error');
        } finally {
            this.isUploading = false;
        }
    },

    async confirmExecuteImport() {
        if (!this.validationData || !this.validationData.token) {
            this.triggerToast('Token data import tidak ditemukan.', 'error');
            return;
        }

        this.importStep = 'processing';

        try {
            const res = await fetch('/api/members/import/process', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    token: this.validationData.token,
                    duplicate_action: this.duplicateAction
                })
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.importResult = json;
                this.importStep = 'result';
                await this.fetchMembers(1);
                this.triggerToast('Import master anggota berhasil diselesaikan!');
            } else {
                this.triggerToast('Gagal memproses import: ' + (json.message || 'Terjadi kesalahan sistem.'), 'error');
                this.importStep = 'preview';
            }
        } catch (e) {
            console.error('Import execution error:', e);
            this.triggerToast('Terjadi kesalahan jaringan saat import: ' + e.message, 'error');
            this.importStep = 'preview';
        }
    },

    exportExcelData() {
        const params = new URLSearchParams();
        if (this.searchQuery && this.searchQuery.trim()) {
            params.append('search', this.searchQuery.trim());
        }
        if (this.selectedBranch && this.selectedBranch !== 'all') {
            params.append('branch', this.selectedBranch);
        }
        if (this.selectedDept && this.selectedDept !== 'all') {
            params.append('department', this.selectedDept);
        }
        window.location.href = '/manager/members/export?' + params.toString();
        this.triggerToast('Mengunduh Master Anggota Excel (16 Kolom)...');
    },

    downloadILPTemplate() {
        window.location.href = '/manager/members/template';
    },

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
                    if (json.next_id || (json.meta && json.meta.next_id)) {
                        this.nextId = json.next_id || json.meta.next_id;
                        this.nextCode = json.next_code || json.meta.next_code;
                    }
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

    // Fetch Next ID and Code from backend
    async fetchNextCode() {
        try {
            const res = await fetch('/api/members/next-code', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success && json.data) {
                    this.nextId = json.data.next_id;
                    this.nextCode = json.data.next_code;
                    if (this.modalMode === 'create' && this.showModal) {
                        this.formData.customer_id = json.data.next_id;
                        this.formData.member_code = json.data.next_code;
                    }
                }
            }
        } catch (e) {
            console.error('Failed to fetch next member code:', e);
        }
    },

    // Open Create Modal
    async openCreateModal() {
        this.modalMode = 'create';
        this.formErrors = {};
        this.formData = {
            id: null,
            customer_id: this.nextId || '',
            member_code: this.nextCode || '',
            kode_out: '',
            nik: '',
            name: '',
            department: '',
            division: '',
            address: '',
            branch: this.selectedBranch !== 'all' ? this.selectedBranch : 'Cabang Pusat',
            phone: '',
            fax: '',
            contact: '',
            tempo_bayar: '',
            idtk: '',
            credit_limit: 1000000,
            no_rekening: '',
            status: 'AKTIF',
            notes: 'Pendaftaran anggota baru'
        };
        this.showModal = true;
        await this.fetchNextCode();
    },

    // Open Edit Modal
    openEditModal(m) {
        this.modalMode = 'edit';
        this.formErrors = {};
        this.formData = {
            id: m.id,
            customer_id: m.customer_id || '',
            member_code: m.member_code || '',
            kode_out: m.kode_out || '',
            nik: m.nik || '',
            name: m.name || '',
            department: m.department || '',
            division: m.division || m.department || '',
            address: m.address || '',
            branch: m.branch || '',
            phone: m.phone || '',
            fax: m.fax || '',
            contact: m.contact || '',
            tempo_bayar: m.tempo_bayar || '',
            idtk: m.idtk || '',
            credit_limit: m.credit_limit,
            no_rekening: m.no_rekening || '',
            status: m.status,
            notes: m.notes || ''
        };
        this.showModal = true;
    },

    // Save Member (POST or PUT)
    async saveMember() {
        if (this.isLoading) return;
        this.formErrors = {};

        // Only Nip, Nama, Dept, Divisi, Fax, Plafon are required manual fields
        if (!this.formData.nik || !String(this.formData.nik).trim()) {
            this.formErrors.nik = 'NIP / NIK wajib diisi.';
        }
        if (!this.formData.name || !String(this.formData.name).trim()) {
            this.formErrors.name = 'Nama lengkap wajib diisi.';
        }
        if (!this.formData.department || !String(this.formData.department).trim()) {
            this.formErrors.department = 'Departemen wajib diisi.';
        }
        if (!this.formData.division || !String(this.formData.division).trim()) {
            this.formErrors.division = 'Divisi wajib diisi.';
        }
        if (!this.formData.fax || !String(this.formData.fax).trim()) {
            this.formErrors.fax = 'Fax wajib diisi.';
        }
        if (this.formData.credit_limit === '' || this.formData.credit_limit === null || this.formData.credit_limit === undefined || isNaN(this.formData.credit_limit) || Number(this.formData.credit_limit) < 0) {
            this.formErrors.credit_limit = 'Plafon kasbon wajib diisi.';
        }

        if (Object.keys(this.formErrors).length > 0) return;

        // Auto-assign ID & Kode if empty
        if (!this.formData.customer_id && this.nextId) {
            this.formData.customer_id = this.nextId;
        }
        if (!this.formData.member_code && this.nextCode) {
            this.formData.member_code = this.nextCode;
        }

        this.isLoading = true;
        const idemKey = window.createIdempotencyKey ? window.createIdempotencyKey() : ('mbr-' + Date.now());
        try {
            const url = this.modalMode === 'create' ? '/api/members' : `/api/members/${this.formData.id}`;
            const method = this.modalMode === 'create' ? 'POST' : 'PUT';

            const res = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Idempotency-Key': idemKey
                },
                body: JSON.stringify(this.formData)
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.showModal = false;
                this.triggerToast(json.message);
                await this.fetchMembers(this.currentPage);
                await this.fetchNextCode();
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
                    processed_by: this.limitProcessedBy
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
                    processed_by: @js(auth()->user()?->name ?? 'Manager')
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
        if (b.includes('Pusat')) return 'bg-blue-50 text-[#0052cc] border-blue-200';
        if (b.includes('Cabang A') || b.includes('Cabang 1') || b.includes('Barat')) return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        if (b.includes('Cabang B') || b.includes('Cabang 2') || b.includes('Timur')) return 'bg-purple-50 text-purple-700 border-purple-200';
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
    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden relative">
        
        <!-- Top Header -->
        <header class="h-20 bg-[#f8f9fa] border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-3">
                <h1 class="text-[20px] font-bold text-gray-900">Database Anggota</h1>
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200 flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[14px]">groups</span>
                    <span>Multi-Cabang &amp; Kasbon</span>
                </span>
            </div>

            <div class="flex items-center gap-4 text-gray-500">
                <x-notification-bell role="MANAGER" />
                <button @click="window.PosRealtime?.syncNow?.(); fetchMembers(currentPage)" class="hover:text-gray-900 transition-colors cursor-pointer" title="Sinkronkan Data">
                    <span class="material-symbols-rounded text-[24px]" :class="(isLoading || $store.network?.isChecking) ? 'animate-spin text-[#0052cc]' : ''">autorenew</span>
                </button>
                <div class="h-6 w-px bg-gray-300"></div>
                <x-realtime-network-badge />
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-8 flex flex-col">

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

                <!-- Action Buttons: Laporan Piutang, Import Excel, Export Excel, Tambah Karyawan -->
                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <a href="{{ url('/reports') }}"
                       class="px-4 py-2.5 bg-white hover:bg-slate-50 text-slate-700 border border-gray-300 rounded-xl font-bold text-[13px] flex items-center justify-center gap-2 shadow-xs transition-colors cursor-pointer"
                       title="Buka Laporan Piutang Kasbon">
                        <span class="material-symbols-rounded text-[18px] text-[#0052cc]">assessment</span>
                        <span>Laporan Piutang</span>
                    </a>

                    <button type="button"
                            @click.stop="openImportModal()"
                            class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-[13px] flex items-center justify-center gap-2 shadow-xs transition-colors cursor-pointer"
                            title="Import data master anggota massal dari file Excel ILP (16 Kolom)">
                        <span class="material-symbols-rounded text-[18px]">upload_file</span>
                        <span>Import Excel</span>
                    </button>

                    <button type="button"
                            @click="exportExcelData()"
                            class="px-4 py-2.5 bg-white hover:bg-slate-50 text-slate-700 border border-gray-300 rounded-xl font-bold text-[13px] flex items-center justify-center gap-2 shadow-xs transition-colors cursor-pointer"
                            title="Export seluruh master anggota database ke file Excel 16 kolom ILP">
                        <span class="material-symbols-rounded text-[18px]">download</span>
                        <span>Export Excel</span>
                    </button>

                    <button type="button"
                            @click="openCreateModal()"
                            class="flex items-center justify-center gap-2 px-5 py-2.5 bg-[#0052cc] rounded-xl text-[13px] font-bold text-white hover:bg-[#0043a8] transition-colors shadow-xs active:scale-95 cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">person_add</span>
                        <span>Tambah Karyawan</span>
                    </button>
                </div>
            </div>

            <!-- Data Table Card -->
            <div class="bg-white border border-gray-200/90 rounded-2xl shadow-xs overflow-hidden flex flex-col mb-6">
                <div class="overflow-x-auto min-w-0 w-full">
                    <table class="w-full text-left text-[12.5px] min-w-[1800px] border-collapse">
                        <thead class="bg-gray-50/90 border-b border-gray-200 text-gray-600 text-[11px] font-bold tracking-wider uppercase whitespace-nowrap">
                            <tr>
                                <th class="px-4 py-3.5 text-left">ID</th>
                                <th class="px-4 py-3.5 text-left">Kode</th>
                                <th class="px-4 py-3.5 text-left">KodeOut</th>
                                <th class="px-4 py-3.5 text-left">Nip</th>
                                <th class="px-4 py-3.5 text-left">Nama</th>
                                <th class="px-4 py-3.5 text-left">Dept</th>
                                <th class="px-4 py-3.5 text-left">Divisi</th>
                                <th class="px-4 py-3.5 text-left">Alamat</th>
                                <th class="px-4 py-3.5 text-left">Tlp</th>
                                <th class="px-4 py-3.5 text-left">Fax</th>
                                <th class="px-4 py-3.5 text-left">Contact</th>
                                <th class="px-4 py-3.5 text-center">TempoBayar</th>
                                <th class="px-4 py-3.5 text-left">IDTK</th>
                                <th class="px-4 py-3.5 text-right">Plafon</th>
                                <th class="px-4 py-3.5 text-left">NoRekening</th>
                                <th class="px-4 py-3.5 text-left">Cabang Penugasan</th>
                                <th class="px-4 py-3.5 text-center">Status</th>
                                <th class="px-4 py-3.5 text-center sticky right-0 bg-gray-50 z-10 shadow-l">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-100">
                            <!-- Loading Skeleton -->
                            <template x-if="isLoading && members.length === 0">
                                <tr>
                                    <td colspan="18" class="px-6 py-12 text-center text-gray-400">
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
                                    <td colspan="18" class="px-6 py-14 text-center text-gray-400">
                                        <span class="material-symbols-rounded text-[48px] text-gray-300 block mb-2">person_search</span>
                                        <p class="font-bold text-gray-700 text-sm">Tidak ada data anggota ditemukan</p>
                                        <p class="text-xs text-gray-400 mt-1">Coba sesuaikan kata kunci pencarian, departemen, status, atau import data Excel.</p>
                                    </td>
                                </tr>
                            </template>

                            <!-- Member Rows -->
                            <template x-for="m in members" :key="m.id">
                                <tr class="hover:bg-blue-50/20 transition-colors">
                                    <!-- 1. ID -->
                                    <td class="px-4 py-3 font-mono text-gray-500 whitespace-nowrap"
                                        x-text="(m.customer_id !== null && m.customer_id !== undefined && String(m.customer_id) !== '') ? m.customer_id : m.id"></td>

                                    <!-- 2. Kode -->
                                    <td class="px-4 py-3 font-mono font-bold text-[#0052cc] whitespace-nowrap"
                                        x-text="(m.member_code !== null && m.member_code !== undefined && String(m.member_code) !== '') ? m.member_code : '-'"></td>

                                    <!-- 3. KodeOut -->
                                    <td class="px-4 py-3 font-mono text-gray-600 whitespace-nowrap"
                                        x-text="m.kode_out || '-'"></td>

                                    <!-- 4. Nip -->
                                    <td class="px-4 py-3 font-mono font-bold text-gray-800 whitespace-nowrap"
                                        x-text="(m.nik !== null && m.nik !== undefined && String(m.nik) !== '') ? m.nik : '-'"></td>

                                    <!-- 5. Nama -->
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <a :href="'/member-detail?id=' + m.id"
                                           @click="try { localStorage.setItem('pos_last_selected_member_id', String(m.id)); } catch(e) {}"
                                           class="font-bold text-gray-900 hover:text-[#0052cc] leading-tight block" x-text="m.name"></a>
                                    </td>

                                    <!-- 6. Dept -->
                                    <td class="px-4 py-3 text-gray-700 font-semibold whitespace-nowrap"
                                        x-text="m.department || '-'"></td>

                                    <!-- 7. Divisi -->
                                    <td class="px-4 py-3 text-gray-700 whitespace-nowrap"
                                        x-text="m.division || m.department || '-'"></td>

                                    <!-- 8. Alamat -->
                                    <td class="px-4 py-3 text-gray-600 max-w-[200px] truncate"
                                        :title="m.address || ''"
                                        x-text="m.address || '-'"></td>

                                    <!-- 9. Tlp -->
                                    <td class="px-4 py-3 font-mono text-gray-600 whitespace-nowrap"
                                        x-text="m.phone || '-'"></td>

                                    <!-- 10. Fax -->
                                    <td class="px-4 py-3 font-mono text-gray-700 whitespace-nowrap"
                                        x-text="m.fax || '-'"></td>

                                    <!-- 11. Contact -->
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap"
                                        x-text="m.contact || '-'"></td>

                                    <!-- 12. TempoBayar -->
                                    <td class="px-4 py-3 text-center font-mono text-gray-600 whitespace-nowrap"
                                        x-text="m.tempo_bayar || '-'"></td>

                                    <!-- 13. IDTK -->
                                    <td class="px-4 py-3 font-mono text-gray-600 whitespace-nowrap"
                                        x-text="m.idtk || '-'"></td>

                                    <!-- 14. Plafon -->
                                    <td class="px-4 py-3 text-right font-mono font-bold text-gray-900 whitespace-nowrap"
                                        x-text="formatRupiah(m.credit_limit)"></td>

                                    <!-- 15. NoRekening -->
                                    <td class="px-4 py-3 font-mono text-gray-600 whitespace-nowrap"
                                        x-text="m.no_rekening || '-'"></td>

                                    <!-- 16. Cabang Penugasan -->
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <template x-if="m.branch">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold border"
                                                  :class="getBranchBadgeClass(m.branch)">
                                                <span class="material-symbols-rounded text-[14px]">storefront</span>
                                                <span x-text="m.branch"></span>
                                            </span>
                                        </template>
                                        <template x-if="!m.branch">
                                            <span class="text-gray-400">-</span>
                                        </template>
                                    </td>

                                    <!-- Status Toggle -->
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
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
                                    <td class="px-4 py-3 text-center whitespace-nowrap sticky right-0 bg-white shadow-l">
                                        <div class="inline-flex items-center gap-1">
                                            <!-- Detail Profil & Riwayat -->
                                            <a :href="'/member-detail?id=' + m.id"
                                               @click="try { localStorage.setItem('pos_last_selected_member_id', String(m.id)); } catch(e) {}"
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
        
        <div class="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-gray-200 max-h-[92vh] overflow-y-auto"
             @click.outside="showModal = false">
            <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center font-bold">
                        <span class="material-symbols-rounded text-[22px]" x-text="modalMode === 'create' ? 'person_add' : 'edit'"></span>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-gray-900" x-text="modalMode === 'create' ? 'Daftarkan Karyawan Baru' : 'Edit Data Karyawan'"></h3>
                        <p class="text-xs text-gray-400">Master Data Anggota Koperasi Multi-Cabang (16 Kolom ILP)</p>
                    </div>
                </div>
                <button type="button" @click="showModal = false" class="text-gray-400 hover:text-gray-600 p-1">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>

            <form @submit.prevent="saveMember()" class="space-y-3.5 mt-4">
                <!-- Row 1: ID, Kode, KodeOut, Nip -->
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">
                            ID <span class="text-[10px] font-semibold text-blue-600">(Otomatis)</span>
                        </label>
                        <input type="text"
                               x-model="formData.customer_id"
                               placeholder="Auto / ID"
                               :readonly="modalMode === 'create'"
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs font-mono focus:outline-none focus:border-[#0052cc]"
                               :class="modalMode === 'create' ? 'bg-gray-50 text-gray-700 font-bold' : ''">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">
                            Kode <span class="text-[10px] font-semibold text-blue-600">(Otomatis)</span>
                        </label>
                        <input type="text"
                               x-model="formData.member_code"
                               placeholder="01-0001"
                               :readonly="modalMode === 'create'"
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs font-mono focus:outline-none focus:border-[#0052cc]"
                               :class="modalMode === 'create' ? 'bg-gray-50 text-gray-700 font-bold' : ''">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">KodeOut</label>
                        <input type="text"
                               x-model="formData.kode_out"
                               placeholder="-"
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs font-mono focus:outline-none focus:border-[#0052cc]">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Nip / NIK <span class="text-red-500">*</span></label>
                        <input type="text"
                               x-model="formData.nik"
                               placeholder="199701002"
                               class="w-full px-3 py-2 rounded-xl border text-xs font-mono focus:ring-2 focus:ring-[#0052cc]/20 focus:border-[#0052cc] focus:outline-none"
                               :class="formErrors.nik ? 'border-red-400 bg-red-50/20' : 'border-gray-300'">
                        <p x-show="formErrors.nik" x-text="formErrors.nik" class="text-[11px] text-red-500 mt-1"></p>
                    </div>
                </div>

                <!-- Row 2: Nama, Dept, Divisi -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Nama <span class="text-red-500">*</span></label>
                        <input type="text"
                               x-model="formData.name"
                               placeholder="Nama karyawan"
                               class="w-full px-3 py-2 rounded-xl border text-xs focus:ring-2 focus:ring-[#0052cc]/20 focus:border-[#0052cc] focus:outline-none"
                               :class="formErrors.name ? 'border-red-400 bg-red-50/20' : 'border-gray-300'">
                        <p x-show="formErrors.name" x-text="formErrors.name" class="text-[11px] text-red-500 mt-1"></p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Dept <span class="text-red-500">*</span></label>
                        <input type="text"
                               x-model="formData.department"
                               placeholder="General Affairs"
                               class="w-full px-3 py-2 rounded-xl border text-xs focus:ring-2 focus:ring-[#0052cc]/20 focus:border-[#0052cc] focus:outline-none"
                               :class="formErrors.department ? 'border-red-400 bg-red-50/20' : 'border-gray-300'">
                        <p x-show="formErrors.department" x-text="formErrors.department" class="text-[11px] text-red-500 mt-1"></p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Divisi <span class="text-red-500">*</span></label>
                        <input type="text"
                               x-model="formData.division"
                               placeholder="General Affairs"
                               class="w-full px-3 py-2 rounded-xl border text-xs focus:ring-2 focus:ring-[#0052cc]/20 focus:border-[#0052cc] focus:outline-none"
                               :class="formErrors.division ? 'border-red-400 bg-red-50/20' : 'border-gray-300'">
                        <p x-show="formErrors.division" x-text="formErrors.division" class="text-[11px] text-red-500 mt-1"></p>
                    </div>
                </div>

                <!-- Row 3: Alamat, Tlp, Fax, Contact -->
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Alamat</label>
                        <input type="text"
                               x-model="formData.address"
                               placeholder="Alamat..."
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs focus:outline-none focus:border-[#0052cc]">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Tlp</label>
                        <input type="text"
                               x-model="formData.phone"
                               placeholder="0812-xxxx"
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs font-mono focus:outline-none focus:border-[#0052cc]">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Fax <span class="text-red-500">*</span></label>
                        <input type="text"
                               x-model="formData.fax"
                               placeholder="I/22"
                               class="w-full px-3 py-2 rounded-xl border text-xs font-mono focus:ring-2 focus:ring-[#0052cc]/20 focus:border-[#0052cc] focus:outline-none"
                               :class="formErrors.fax ? 'border-red-400 bg-red-50/20' : 'border-gray-300'">
                        <p x-show="formErrors.fax" x-text="formErrors.fax" class="text-[11px] text-red-500 mt-1"></p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Contact</label>
                        <input type="text"
                               x-model="formData.contact"
                               placeholder="-"
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs focus:outline-none focus:border-[#0052cc]">
                    </div>
                </div>

                <!-- Row 4: TempoBayar, IDTK, Plafon, NoRekening, Cabang Penugasan -->
                <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">TempoBayar</label>
                        <input type="text"
                               x-model="formData.tempo_bayar"
                               placeholder="-"
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs font-mono focus:outline-none focus:border-[#0052cc]">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">IDTK</label>
                        <input type="text"
                               x-model="formData.idtk"
                               placeholder="-"
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs font-mono focus:outline-none focus:border-[#0052cc]">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Plafon (Rp) <span class="text-red-500">*</span></label>
                        <input type="number"
                               x-model="formData.credit_limit"
                               placeholder="1000000"
                               class="w-full px-3 py-2 rounded-xl border text-xs font-mono font-bold focus:ring-2 focus:ring-[#0052cc]/20 focus:border-[#0052cc] focus:outline-none"
                               :class="formErrors.credit_limit ? 'border-red-400 bg-red-50/20' : 'border-gray-300'">
                        <p x-show="formErrors.credit_limit" x-text="formErrors.credit_limit" class="text-[11px] text-red-500 mt-1"></p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">NoRekening</label>
                        <input type="text"
                               x-model="formData.no_rekening"
                               placeholder="-"
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs font-mono focus:outline-none focus:border-[#0052cc]">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Cabang Penugasan</label>
                        <input type="text"
                               x-model="formData.branch"
                               list="branchOptionsList"
                               placeholder="Cabang Pusat"
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs font-semibold focus:outline-none focus:border-[#0052cc]">
                        <datalist id="branchOptionsList">
                            <template x-for="b in branches" :key="b">
                                <option :value="b"></option>
                            </template>
                        </datalist>
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

    <!-- ============================================================== -->
    <!-- MODAL IMPORT EXCEL MASTER ANGGOTA (STANDAR 16 KOLOM ILP)       -->
    <!-- ============================================================== -->
    <template x-teleport="body">
        <div x-show="showImportModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs"
             style="display: none;">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-4xl max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150"
                 @click.away="if (importStep !== 'processing') showImportModal = false">

                <!-- Modal Header -->
                <div class="px-6 py-4.5 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center text-emerald-400">
                            <span class="material-symbols-rounded text-[22px]">upload_file</span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-white">Import Master Anggota Excel</h3>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-500/20 border border-emerald-400/30 text-emerald-300">Format ILP 16 Kolom</span>
                            </div>
                            <p class="text-xs text-slate-300 mt-0.5">Unggah dan sinkronisasi cepat puluhan ribu data anggota koperasi / customer</p>
                        </div>
                    </div>
                    <button type="button"
                            x-show="importStep !== 'processing'"
                            @click="showImportModal = false"
                            class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white flex items-center justify-center transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">close</span>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 overflow-y-auto p-6 space-y-5">

                    <!-- STEP 1: UPLOAD FILE -->
                    <div x-show="importStep === 'upload'" class="space-y-5">
                        <div class="p-4 bg-emerald-50/80 border border-emerald-200 rounded-xl text-emerald-950 flex items-start gap-3">
                            <span class="material-symbols-rounded text-emerald-600 text-[22px] shrink-0 mt-0.5">verified</span>
                            <div class="text-xs space-y-1">
                                <span class="font-bold text-emerald-900 block text-sm">Standar Format Master Anggota / Customer ILP (16 Kolom)</span>
                                <p class="text-emerald-800 leading-relaxed">
                                    File Excel memuat kolom: <strong>ID, Kode, KodeOut, Nip, Nama, Dept, Divisi, Alamat, Tlp, Fax, Contact, TempoBayar, IDTK, Plafon, NoRekening, Cabang Penugasan</strong>. Mendukung pembacaan berkecepatan tinggi hingga puluhan ribu baris data.
                                </p>
                            </div>
                        </div>

                        <!-- Drag and drop / File Picker Area -->
                        <div class="border-2 border-dashed rounded-2xl p-8 text-center transition-all cursor-pointer relative select-none"
                             :class="importFile ? 'border-emerald-400 bg-emerald-50/30' : (isDragging ? 'border-[#0052cc] bg-blue-50/50' : 'border-slate-300 hover:border-[#0052cc] hover:bg-slate-50/50')"
                             @click="triggerFileInput()"
                             @dragenter.prevent="isDragging = true"
                             @dragover.prevent="isDragging = true"
                             @dragleave.prevent="isDragging = false"
                             @drop.prevent="handleFileDrop($event)">
                            <input type="file"
                                   id="memberExcelFileInput"
                                   x-ref="memberExcelFileInput"
                                   @click.stop=""
                                   @change="onFileSelected($event)"
                                   accept=".xlsx,.xls,.csv"
                                   class="hidden">

                            <template x-if="!importFile">
                                <div class="space-y-3 pointer-events-none">
                                    <div class="w-14 h-14 mx-auto rounded-2xl bg-blue-50 text-[#0052cc] flex items-center justify-center">
                                        <span class="material-symbols-rounded text-[32px]">cloud_upload</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-800">Pilih file Excel Master Anggota atau tarik file ke sini</p>
                                        <p class="text-xs text-slate-400 mt-1">Mendukung format .XLSX, .XLS, atau .CSV (Maksimal 50MB • Puluhan Ribu Baris)</p>
                                    </div>
                                    <button type="button" @click.stop="triggerFileInput()" class="pointer-events-auto px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors inline-flex items-center gap-1.5 cursor-pointer">
                                        <span class="material-symbols-rounded text-[16px]">folder_open</span>
                                        <span>Telusuri File di Komputer</span>
                                    </button>
                                </div>
                            </template>

                            <template x-if="importFile">
                                <div class="flex items-center justify-between p-4 bg-white rounded-xl border border-emerald-200 shadow-2xs text-left" @click.stop="">
                                    <div class="flex items-center gap-3.5">
                                        <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                            <span class="material-symbols-rounded text-[28px]">description</span>
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-800 text-sm" x-text="importFileName"></p>
                                            <p class="text-xs text-slate-400 font-mono mt-0.5" x-text="(importFile.size / 1024).toFixed(1) + ' KB'"></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button"
                                                @click.stop="resetFileInput()"
                                                class="px-3 py-1.5 rounded-lg text-xs font-bold text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer">
                                            Ganti File
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Template Download Row -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2 border-t border-slate-100">
                            <div class="flex items-center gap-2 text-xs text-slate-500">
                                <span class="material-symbols-rounded text-blue-600 text-[18px]">info</span>
                                <span>Perlu contoh format struktur 16 kolom anggota?</span>
                            </div>
                            <button type="button"
                                    @click="downloadILPTemplate()"
                                    class="px-3.5 py-2 rounded-xl border border-slate-200 hover:border-[#0052cc] hover:bg-blue-50/40 text-xs font-bold text-[#0052cc] inline-flex items-center gap-1.5 transition-colors cursor-pointer">
                                <span class="material-symbols-rounded text-[16px]">download</span>
                                <span>Unduh Template Excel (16 Kolom ILP)</span>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 2: INVALID STRUCTURE -->
                    <div x-show="importStep === 'invalid_structure'" style="display: none;" class="space-y-4">
                        <div class="p-5 bg-rose-50 border-2 border-rose-200 rounded-2xl text-rose-950 space-y-3">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-xl bg-rose-600 text-white flex items-center justify-center shrink-0">
                                    <span class="material-symbols-rounded text-[24px]">gpp_bad</span>
                                </div>
                                <div>
                                    <h4 class="text-sm font-black text-rose-900">Import Ditolak: Struktur Kolom Tidak Sesuai!</h4>
                                    <p class="text-xs text-rose-700 mt-0.5 leading-relaxed" x-text="validationErrorDetails?.message || 'File yang diunggah tidak sesuai dengan struktur kolom Master Anggota ILP.'"></p>
                                </div>
                            </div>

                            <template x-if="validationErrorDetails?.missing_columns && validationErrorDetails.missing_columns.length > 0">
                                <div class="p-3 bg-white rounded-xl border border-rose-200">
                                    <span class="text-[11px] font-extrabold text-rose-700 uppercase block mb-1.5">Kolom yang Tidak Ditemukan:</span>
                                    <div class="flex flex-wrap gap-1.5">
                                        <template x-for="col in validationErrorDetails.missing_columns" :key="col">
                                            <span class="px-2 py-0.5 bg-rose-100 text-rose-800 rounded text-[11px] font-mono font-bold" x-text="col"></span>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- STEP 3: PREVIEW DATA SEBELUM IMPORT -->
                    <div x-show="importStep === 'preview'" style="display: none;" class="space-y-5">
                        <!-- Summary Banner -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                                <span class="text-[10px] font-bold uppercase text-slate-400 block">Total Baris Dibaca</span>
                                <span class="text-xl font-black text-slate-800 font-mono mt-0.5 block" x-text="validationData?.summary?.total_rows || 0">0</span>
                            </div>
                            <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200">
                                <span class="text-[10px] font-bold uppercase text-emerald-700 block">Baris Valid Siap Import</span>
                                <span class="text-xl font-black text-emerald-700 font-mono mt-0.5 block" x-text="validationData?.summary?.valid_rows || 0">0</span>
                            </div>
                            <div class="p-3.5 rounded-xl bg-blue-50 border border-blue-200">
                                <span class="text-[10px] font-bold uppercase text-blue-700 block">Kode Sudah Ada di DB</span>
                                <span class="text-xl font-black text-[#0052cc] font-mono mt-0.5 block" x-text="validationData?.summary?.existing_in_db_count || 0">0</span>
                            </div>
                            <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200">
                                <span class="text-[10px] font-bold uppercase text-rose-700 block">Baris Error / Dilewati</span>
                                <span class="text-xl font-black text-rose-600 font-mono mt-0.5 block" x-text="validationData?.summary?.error_rows || 0">0</span>
                            </div>
                        </div>

                        <!-- Duplicate Action Option -->
                        <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <span class="text-xs font-bold text-slate-800 block">Perlakuan untuk Kode Anggota yang Sudah Ada:</span>
                                <span class="text-[11px] text-slate-500">Saldo kasbon berjalan anggota lama tetap aman dan tidak akan ter-reset.</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <label class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-700 cursor-pointer">
                                    <input type="radio" value="update" x-model="duplicateAction" class="text-[#0052cc]">
                                    <span>Perbarui Data (Update)</span>
                                </label>
                                <label class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-700 cursor-pointer">
                                    <input type="radio" value="skip" x-model="duplicateAction" class="text-[#0052cc]">
                                    <span>Lewati (Skip)</span>
                                </label>
                            </div>
                        </div>

                        <!-- Preview Table (First 25 Rows) -->
                        <div class="border border-slate-200 rounded-xl overflow-hidden">
                            <div class="px-4 py-2.5 bg-slate-100 border-b border-slate-200 flex items-center justify-between text-xs font-bold text-slate-700">
                                <span>Pratinjau 25 Baris Pertama (16 Kolom Standar ILP)</span>
                                <span class="text-[11px] text-slate-500 font-normal">Geser tabel ke kanan untuk melihat seluruh kolom &rarr;</span>
                            </div>
                            <div class="overflow-x-auto max-h-64">
                                <table class="w-full text-left text-[11px] whitespace-nowrap border-collapse">
                                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase sticky top-0">
                                        <tr>
                                            <th class="px-3 py-2">Baris</th>
                                            <th class="px-3 py-2">Status</th>
                                            <th class="px-3 py-2">ID</th>
                                            <th class="px-3 py-2">Kode</th>
                                            <th class="px-3 py-2">KodeOut</th>
                                            <th class="px-3 py-2">Nip</th>
                                            <th class="px-3 py-2">Nama</th>
                                            <th class="px-3 py-2">Dept</th>
                                            <th class="px-3 py-2">Divisi</th>
                                            <th class="px-3 py-2">Alamat</th>
                                            <th class="px-3 py-2">Tlp</th>
                                            <th class="px-3 py-2">Fax</th>
                                            <th class="px-3 py-2">Contact</th>
                                            <th class="px-3 py-2">TempoBayar</th>
                                            <th class="px-3 py-2">IDTK</th>
                                            <th class="px-3 py-2 text-right">Plafon</th>
                                            <th class="px-3 py-2">NoRekening</th>
                                            <th class="px-3 py-2">Cabang Penugasan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="r in (validationData?.preview_rows || [])" :key="r.row">
                                            <tr :class="r.has_error ? 'bg-rose-50/60' : 'hover:bg-slate-50'">
                                                <td class="px-3 py-2 font-mono text-slate-400" x-text="r.row"></td>
                                                <td class="px-3 py-2">
                                                    <template x-if="r.has_error">
                                                        <span class="px-2 py-0.5 rounded bg-rose-100 text-rose-700 font-bold text-[10px]" x-text="r.error_message || 'Error'"></span>
                                                    </template>
                                                    <template x-if="!r.has_error && r.is_existing">
                                                        <span class="px-2 py-0.5 rounded bg-blue-100 text-[#0052cc] font-bold text-[10px]">Update DB</span>
                                                    </template>
                                                    <template x-if="!r.has_error && !r.is_existing">
                                                        <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 font-bold text-[10px]">Baru</span>
                                                    </template>
                                                </td>
                                                <td class="px-3 py-2 font-mono" x-text="r.customer_id || '-'"></td>
                                                <td class="px-3 py-2 font-mono font-bold text-[#0052cc]" x-text="r.member_code"></td>
                                                <td class="px-3 py-2 font-mono" x-text="r.kode_out || '-'"></td>
                                                <td class="px-3 py-2 font-mono font-bold" x-text="r.nik"></td>
                                                <td class="px-3 py-2 font-bold text-slate-800" x-text="r.name"></td>
                                                <td class="px-3 py-2" x-text="r.department || '-'"></td>
                                                <td class="px-3 py-2" x-text="r.division || '-'"></td>
                                                <td class="px-3 py-2" x-text="r.address || '-'"></td>
                                                <td class="px-3 py-2 font-mono" x-text="r.phone || '-'"></td>
                                                <td class="px-3 py-2 font-mono" x-text="r.fax || '-'"></td>
                                                <td class="px-3 py-2" x-text="r.contact || '-'"></td>
                                                <td class="px-3 py-2 font-mono text-center" x-text="r.tempo_bayar || '-'"></td>
                                                <td class="px-3 py-2 font-mono" x-text="r.idtk || '-'"></td>
                                                <td class="px-3 py-2 text-right font-mono font-bold" x-text="formatRupiah(r.credit_limit)"></td>
                                                <td class="px-3 py-2 font-mono" x-text="r.no_rekening || '-'"></td>
                                                <td class="px-3 py-2" x-text="r.branch || '-'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 4: PROCESSING -->
                    <div x-show="importStep === 'processing'" style="display: none;" class="py-12 flex flex-col items-center justify-center text-center space-y-4">
                        <div class="w-16 h-16 rounded-2xl bg-blue-50 text-[#0052cc] flex items-center justify-center">
                            <span class="material-symbols-rounded text-[36px] animate-spin">progress_activity</span>
                        </div>
                        <div>
                            <h4 class="text-base font-black text-slate-800">Memproses Bulk Import Master Anggota...</h4>
                            <p class="text-xs text-slate-500 mt-1">Mohon tunggu, sedang menyimpan data anggota secara batch ke database.</p>
                        </div>
                    </div>

                    <!-- STEP 5: RESULT -->
                    <div x-show="importStep === 'result'" style="display: none;" class="py-6 space-y-6 text-center">
                        <div class="w-16 h-16 mx-auto rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center">
                            <span class="material-symbols-rounded text-[38px]">task_alt</span>
                        </div>
                        <div>
                            <h4 class="text-lg font-black text-slate-800">Import Master Anggota Berhasil Selesai!</h4>
                            <p class="text-xs text-slate-500 mt-1" x-text="importResult?.message || 'Seluruh data anggota valid telah tersimpan.'"></p>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 max-w-2xl mx-auto text-left">
                            <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200">
                                <span class="text-[10px] font-bold uppercase text-emerald-700 block">Anggota Baru</span>
                                <span class="text-xl font-black text-emerald-700 font-mono mt-0.5 block" x-text="importResult?.summary?.created || 0">0</span>
                            </div>
                            <div class="p-3.5 rounded-xl bg-blue-50 border border-blue-200">
                                <span class="text-[10px] font-bold uppercase text-blue-700 block">Diperbarui</span>
                                <span class="text-xl font-black text-[#0052cc] font-mono mt-0.5 block" x-text="importResult?.summary?.updated || 0">0</span>
                            </div>
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                                <span class="text-[10px] font-bold uppercase text-slate-500 block">Dilewati</span>
                                <span class="text-xl font-black text-slate-700 font-mono mt-0.5 block" x-text="importResult?.summary?.skipped || 0">0</span>
                            </div>
                            <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200">
                                <span class="text-[10px] font-bold uppercase text-rose-700 block">Gagal</span>
                                <span class="text-xl font-black text-rose-600 font-mono mt-0.5 block" x-text="importResult?.summary?.failed || 0">0</span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200/80 flex items-center justify-between shrink-0">
                    <!-- Footer Step 1: Upload -->
                    <template x-if="importStep === 'upload'">
                        <div class="w-full flex items-center justify-end gap-2.5">
                            <button type="button"
                                    @click="showImportModal = false"
                                    class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-200 transition-colors cursor-pointer">
                                Batal
                            </button>
                            <button type="button"
                                    @click="uploadAndValidateFile()"
                                    :disabled="!importFile || isUploading"
                                    class="h-10 px-5 bg-[#0052cc] hover:bg-[#0043a8] disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl text-xs font-bold flex items-center gap-2 shadow-xs transition-colors cursor-pointer">
                                <span class="material-symbols-rounded text-[18px]" :class="isUploading ? 'animate-spin' : ''" x-text="isUploading ? 'progress_activity' : 'fact_check'"></span>
                                <span x-text="isUploading ? 'Memvalidasi File...' : 'Validasi & Pratinjau Data'"></span>
                            </button>
                        </div>
                    </template>

                    <!-- Footer Step 2: Invalid Structure -->
                    <template x-if="importStep === 'invalid_structure'">
                        <div class="w-full flex items-center justify-between">
                            <button type="button"
                                    @click="downloadILPTemplate()"
                                    class="px-4 py-2 rounded-xl text-xs font-bold text-[#0052cc] hover:bg-blue-50 border border-blue-200 transition-colors inline-flex items-center gap-1.5 cursor-pointer">
                                <span class="material-symbols-rounded text-[16px]">download</span>
                                <span>Unduh Format Template Resmi ILP</span>
                            </button>
                            <button type="button"
                                    @click="importStep = 'upload'; resetFileInput();"
                                    class="px-5 py-2.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold transition-colors cursor-pointer inline-flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[16px]">replay</span>
                                <span>Unggah Ulang File Lain</span>
                            </button>
                        </div>
                    </template>

                    <!-- Footer Step 3: Preview -->
                    <template x-if="importStep === 'preview'">
                        <div class="w-full flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <button type="button"
                                        @click="showImportModal = false"
                                        class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-200 transition-colors cursor-pointer">
                                    Batal
                                </button>
                                <button type="button"
                                        @click="importStep = 'upload'; resetFileInput();"
                                        class="px-4 py-2 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors cursor-pointer">
                                    Ganti File
                                </button>
                            </div>
                            <button type="button"
                                    @click="confirmExecuteImport()"
                                    :disabled="!validationData || (validationData?.summary?.valid_rows || 0) === 0"
                                    class="h-10 px-5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl text-xs font-bold flex items-center gap-2 shadow-xs transition-colors cursor-pointer">
                                <span class="material-symbols-rounded text-[18px]">cloud_done</span>
                                <span>Konfirmasi &amp; Mulai Import (<span x-text="validationData?.summary?.valid_rows || 0"></span> Anggota)</span>
                            </button>
                        </div>
                    </template>

                    <!-- Footer Step 5: Result -->
                    <template x-if="importStep === 'result'">
                        <div class="w-full flex items-center justify-end">
                            <button type="button"
                                    @click="showImportModal = false"
                                    class="h-10 px-6 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold transition-colors cursor-pointer">
                                Selesai &amp; Tutup
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

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
