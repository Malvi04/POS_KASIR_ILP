@extends('layouts.app')

@section('title', 'Manajemen Anggota - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    searchQuery: '',
    statusFilter: 'all',
    showModal: false,
    modalMode: 'create', // 'create' | 'edit'
    selectedMember: null,
    showDeleteModal: false,
    memberToDelete: null,
    deleteConfirmInput: '',
    toastMessage: '',
    showToast: false,

    members: [],
    defaultMembers: [
        {
            id: 'MBR-001',
            nik: '327501002230004',
            name: 'Bambang Pamungkas',
            dept: 'Logistik & Gudang',
            joinDate: '12 Januari 2021',
            phone: '0812-9841-2201',
            creditLimit: 5000000,
            currentDebt: 500000,
            status: 'AKTIF',
            notes: 'Anggota aktif koperasi, pembayaran payroll tertib.'
        },
        {
            id: 'MBR-002',
            nik: '327501440230012',
            name: 'Siti Lestari',
            dept: 'Administrasi',
            joinDate: '05 Mei 2022',
            phone: '0813-8821-4412',
            creditLimit: 5000000,
            currentDebt: 3800000,
            status: 'AKTIF',
            notes: 'Mendekati ambang 80% plafon belanja.'
        },
        {
            id: 'MBR-003',
            nik: '327501082230899',
            name: 'Andri Setiawan',
            dept: 'Produksi A',
            joinDate: '10 Agustus 2020',
            phone: '0857-1120-9981',
            creditLimit: 5000000,
            currentDebt: 5000000,
            status: 'DIBLOKIR',
            notes: 'Plafon telah habis (100%). Transaksi kredit ditolak sementara.'
        },
        {
            id: 'MBR-004',
            nik: '327501550230056',
            name: 'Dewi Indah',
            dept: 'Maintenance & Utility',
            joinDate: '19 November 2023',
            phone: '0819-2234-8871',
            creditLimit: 5000000,
            currentDebt: 0,
            status: 'AKTIF',
            notes: 'Plafon utuh 100%.'
        },
        {
            id: 'MBR-005',
            nik: '327501009930011',
            name: 'Raka Hendra',
            dept: 'Logistik & Gudang',
            joinDate: '02 Februari 2022',
            phone: '0812-4411-9902',
            creditLimit: 5000000,
            currentDebt: 2850000,
            status: 'AKTIF',
            notes: 'Eligible transaksi kredit tempo.'
        }
    ],

    initMembers() {
        try {
            const raw = localStorage.getItem('pos_ilp_members_v1');
            if (raw) {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed) && parsed.length > 0) {
                    this.members = parsed;
                    return;
                }
            }
        } catch (e) {}
        this.members = JSON.parse(JSON.stringify(this.defaultMembers));
        this.saveMembers();
    },

    saveMembers() {
        localStorage.setItem('pos_ilp_members_v1', JSON.stringify(this.members));
    },

    get filteredMembers() {
        return this.members.filter(m => {
            if (this.statusFilter !== 'all' && m.status !== this.statusFilter) return false;
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                return (m.name || '').toLowerCase().includes(q) ||
                       (m.nik || '').includes(q) ||
                       (m.dept || '').toLowerCase().includes(q) ||
                       (m.id || '').toLowerCase().includes(q);
            }
            return true;
        });
    },

    get totalAnggota() { return this.members.length; },
    get totalPiutang() { return this.members.reduce((acc, m) => acc + (Number(m.currentDebt) || 0), 0); },
    get totalPlafonTersedia() { return this.members.reduce((acc, m) => acc + Math.max(0, (Number(m.creditLimit) || 0) - (Number(m.currentDebt) || 0)), 0); },
    get persentaseSehat() {
        if (this.members.length === 0) return 100;
        const aktif = this.members.filter(m => m.status === 'AKTIF').length;
        return ((aktif / this.members.length) * 100).toFixed(1);
    },

    // Form State
    formData: {
        id: '',
        nik: '',
        name: '',
        dept: 'Produksi Utama',
        phone: '',
        creditLimit: 5000000,
        currentDebt: 0,
        status: 'AKTIF',
        notes: ''
    },
    formErrors: {},

    openCreateModal() {
        this.modalMode = 'create';
        this.formErrors = {};
        const nextId = 'MBR-' + String(this.members.length + 1).padStart(3, '0');
        this.formData = {
            id: nextId,
            nik: '3275' + Math.floor(100000000000 + Math.random() * 900000000000),
            name: '',
            dept: 'Produksi Utama',
            phone: '0812-' + Math.floor(1000 + Math.random() * 9000) + '-' + Math.floor(1000 + Math.random() * 9000),
            creditLimit: 5000000,
            currentDebt: 0,
            status: 'AKTIF',
            notes: 'Anggota terdaftar baru'
        };
        this.showModal = true;
    },

    openEditModal(m) {
        this.modalMode = 'edit';
        this.formErrors = {};
        this.formData = JSON.parse(JSON.stringify(m));
        this.showModal = true;
    },

    saveMember() {
        this.formErrors = {};
        if (!this.formData.name || !this.formData.name.trim()) this.formErrors.name = 'Nama lengkap wajib diisi.';
        if (!this.formData.nik || !this.formData.nik.trim()) this.formErrors.nik = 'NIK anggota wajib diisi.';
        if (Object.keys(this.formErrors).length > 0) return;

        if (this.modalMode === 'create') {
            this.members.unshift(JSON.parse(JSON.stringify(this.formData)));
            this.saveMembers();
            this.showModal = false;
            this.triggerToast(`Anggota '${this.formData.name}' berhasil ditambahkan.`);
        } else {
            const idx = this.members.findIndex(m => m.id === this.formData.id);
            if (idx !== -1) {
                this.members[idx] = JSON.parse(JSON.stringify(this.formData));
                this.saveMembers();
                this.showModal = false;
                this.triggerToast(`Data anggota '${this.formData.name}' berhasil diperbarui.`);
            }
        }
    },

    toggleStatus(m) {
        m.status = m.status === 'AKTIF' ? 'DIBLOKIR' : 'AKTIF';
        this.saveMembers();
        this.triggerToast(`Status anggota '${m.name}' diubah menjadi ${m.status}.`);
    },

    openDeleteModal(m) {
        this.memberToDelete = m;
        this.deleteConfirmInput = '';
        this.showDeleteModal = true;
    },

    confirmDelete() {
        if (!this.memberToDelete) return;
        if (this.deleteConfirmInput.trim().toLowerCase() !== 'hapus') return;
        const name = this.memberToDelete.name;
        this.members = this.members.filter(m => m.id !== this.memberToDelete.id);
        this.saveMembers();
        this.showDeleteModal = false;
        this.memberToDelete = null;
        this.triggerToast(`Anggota '${name}' berhasil dihapus.`, 'info');
    },

    triggerToast(msg, type = 'success') {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3200);
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
                <h1 class="text-[18px] font-black text-gray-900 tracking-tight">Manajemen Anggota Kopkar</h1>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200">Koperasi ILP</span>
            </div>

            <div class="flex items-center gap-6 text-gray-500">
                <button class="hover:text-gray-900 transition-colors relative" title="Notifikasi">
                    <span class="material-symbols-rounded text-[24px]">notifications</span>
                </button>
                <button @click="initMembers()" class="hover:text-gray-900 transition-colors" title="Sinkron Data">
                    <span class="material-symbols-rounded text-[24px]">autorenew</span>
                </button>
                <div class="h-6 w-px bg-gray-300"></div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-rounded text-green-500 text-[20px]">wifi</span>
                    <span class="text-[14px] font-semibold text-gray-600">Terhubung</span>
                </div>
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-8 flex flex-col">
            
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <!-- Card 1 -->
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-xs flex flex-col">
                    <p class="text-[11px] font-bold text-gray-500 tracking-wider mb-2 uppercase">TOTAL ANGGOTA</p>
                    <div class="flex items-baseline gap-2 mt-auto">
                        <h3 class="text-[28px] font-black text-[#0052cc] leading-none" x-text="totalAnggota">0</h3>
                        <span class="text-green-600 text-[11px] font-bold">Terdaftar</span>
                    </div>
                </div>
                
                <!-- Card 2 -->
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-xs flex flex-col">
                    <p class="text-[11px] font-bold text-gray-500 tracking-wider mb-2 uppercase">TOTAL PIUTANG AKTIF</p>
                    <div class="flex items-baseline gap-2 mt-auto">
                        <h3 class="text-[22px] font-mono font-black text-[#b45309] leading-none tracking-tight" x-text="formatRupiah(totalPiutang)">Rp 0</h3>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-xs flex flex-col">
                    <p class="text-[11px] font-bold text-gray-500 tracking-wider mb-2 uppercase">PLAFON TERSEDIA</p>
                    <div class="flex items-baseline gap-2 mt-auto">
                        <h3 class="text-[22px] font-mono font-black text-gray-800 leading-none tracking-tight" x-text="formatRupiah(totalPlafonTersedia)">Rp 0</h3>
                    </div>
                </div>

                <!-- Card 4 -->
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-xs flex flex-col">
                    <p class="text-[11px] font-bold text-gray-500 tracking-wider mb-2 uppercase">STATUS KREDIT SEHAT</p>
                    <div class="flex items-baseline gap-2 mt-auto text-green-600">
                        <h3 class="text-[28px] font-black leading-none" x-text="persentaseSehat + '%'">100%</h3>
                        <span class="material-symbols-rounded text-[20px] font-bold">trending_up</span>
                    </div>
                </div>
            </div>

            <!-- Search, Filter & Add Button -->
            <div class="flex flex-col sm:flex-row gap-4 mb-6">
                <div class="relative flex-1">
                    <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                    <input type="text"
                           x-model="searchQuery"
                           placeholder="Cari NIK, ID, nama anggota, atau departemen..."
                           class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-[#0052cc]/20 focus:border-[#0052cc] text-[13px] shadow-xs">
                </div>
                <div class="flex gap-3 shrink-0">
                    <select x-model="statusFilter"
                            class="px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-[13px] font-bold text-gray-700 shadow-xs cursor-pointer">
                        <option value="all">Semua Status</option>
                        <option value="AKTIF">Aktif</option>
                        <option value="DIBLOKIR">Diblokir</option>
                    </select>

                    <button type="button"
                            @click="openCreateModal()"
                            class="flex items-center gap-2 px-5 py-2.5 bg-[#0052cc] border border-[#0052cc] rounded-xl text-[13px] font-bold text-white hover:bg-[#0043a8] transition-colors shadow-xs cursor-pointer active:scale-95">
                        <span class="material-symbols-rounded text-[18px]">person_add</span>
                        Tambah Anggota
                    </button>
                </div>
            </div>

            <!-- Data Table -->
            <div class="bg-white border border-gray-200 rounded-2xl shadow-xs overflow-hidden flex flex-col mb-6">
                <div class="overflow-x-auto min-w-0 w-full">
                    <table class="w-full text-left text-[13px] min-w-[950px] border-collapse">
                        <thead class="bg-gray-50/80 border-b border-gray-200 text-gray-600 text-[11px] font-bold tracking-wider uppercase whitespace-nowrap">
                            <tr>
                                <th class="px-6 py-3.5 text-left w-[240px]">NAMA ANGGOTA</th>
                                <th class="px-6 py-3.5 text-left w-[150px]">NIK / ID</th>
                                <th class="px-6 py-3.5 text-left w-[160px]">DEPARTEMEN</th>
                                <th class="px-6 py-3.5 text-right w-[150px]">SISA PLAFON</th>
                                <th class="px-6 py-3.5 text-right w-[150px]">TOTAL PIUTANG</th>
                                <th class="px-6 py-3.5 text-center w-[110px]">STATUS</th>
                                <th class="px-6 py-3.5 text-center w-[120px]">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-100">
                            <template x-for="m in filteredMembers" :key="m.id">
                                <tr class="hover:bg-blue-50/30 transition-colors">
                                    <td class="px-6 py-3.5">
                                        <div class="font-bold text-gray-900 leading-tight" x-text="m.name"></div>
                                        <span class="text-[11px] text-gray-500" x-text="m.phone"></span>
                                    </td>
                                    <td class="px-6 py-3.5 font-mono font-medium text-gray-600 whitespace-nowrap">
                                        <span class="block font-bold text-xs" x-text="m.nik"></span>
                                        <span class="text-[10px] text-gray-400" x-text="m.id"></span>
                                    </td>
                                    <td class="px-6 py-3.5 text-gray-600 font-medium whitespace-nowrap" x-text="m.dept"></td>
                                    <td class="px-6 py-3.5 text-right font-mono font-bold text-gray-800 whitespace-nowrap" x-text="formatRupiah(Math.max(0, m.creditLimit - m.currentDebt))"></td>
                                    <td class="px-6 py-3.5 text-right font-mono font-bold whitespace-nowrap"
                                        :class="m.currentDebt > 0 ? 'text-[#b45309]' : 'text-gray-400'"
                                        x-text="formatRupiah(m.currentDebt)"></td>
                                    <td class="px-6 py-3.5 text-center whitespace-nowrap">
                                        <button type="button"
                                                @click="toggleStatus(m)"
                                                title="Klik untuk ubah status aktif/blokir"
                                                class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold tracking-wider uppercase cursor-pointer"
                                                :class="m.status === 'AKTIF' ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-red-100 text-red-600 hover:bg-red-200'"
                                                x-text="m.status"></button>
                                    </td>
                                    <td class="px-6 py-3.5 text-center whitespace-nowrap">
                                        <div class="inline-flex items-center justify-center gap-1.5">
                                            <a :href="'{{ url('/member-detail') }}?nik=' + encodeURIComponent(m.nik)"
                                               title="Detail Profil & Mutasi"
                                               class="p-1.5 text-gray-500 hover:text-[#0052cc] hover:bg-blue-50 rounded-lg transition-colors">
                                                <span class="material-symbols-rounded text-[18px]">visibility</span>
                                            </a>
                                            <button type="button"
                                                    @click="openEditModal(m)"
                                                    title="Edit Data Anggota"
                                                    class="p-1.5 text-gray-500 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors cursor-pointer">
                                                <span class="material-symbols-rounded text-[18px]">edit</span>
                                            </button>
                                            <button type="button"
                                                    @click="openDeleteModal(m)"
                                                    title="Hapus Anggota"
                                                    class="p-1.5 text-gray-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer">
                                                <span class="material-symbols-rounded text-[18px]">delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <template x-if="filteredMembers.length === 0">
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-400 text-xs">
                                        <span class="material-symbols-rounded text-3xl mb-1 text-gray-300">group_off</span>
                                        <p class="font-bold text-gray-600">Tidak ada anggota yang cocok dengan filter pencarian.</p>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- TOAST -->
    <div x-show="showToast"
         x-transition.opacity
         style="display: none;"
         class="fixed bottom-8 left-1/2 -translate-x-1/2 z-50 px-5 py-3 rounded-2xl bg-gray-900 text-white font-bold text-xs shadow-xl flex items-center gap-2">
        <span class="material-symbols-rounded text-[18px] text-green-400">check_circle</span>
        <span x-text="toastMessage"></span>
    </div>

    <!-- MODAL FORM TAMBAH / EDIT ANGGOTA -->
    <template x-teleport="body">
        <div x-show="showModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showModal"
                 @click.away="showModal = false"
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-gray-200 flex flex-col">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <h3 class="text-base font-black text-gray-900" x-text="modalMode === 'create' ? 'Tambah Anggota Baru' : 'Edit Profil Anggota'"></h3>
                    <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                        <input type="text" x-model="formData.name" placeholder="Contoh: Bambang Pamungkas" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 font-medium text-xs">
                        <p x-show="formErrors.name" class="text-rose-500 text-[11px] mt-1" x-text="formErrors.name"></p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">NIK Karyawan <span class="text-rose-500">*</span></label>
                            <input type="text" x-model="formData.nik" placeholder="3275..." class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 font-mono font-bold text-xs">
                            <p x-show="formErrors.nik" class="text-rose-500 text-[11px] mt-1" x-text="formErrors.nik"></p>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Departemen</label>
                            <select x-model="formData.dept" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 bg-white font-semibold text-xs">
                                <option value="Logistik & Gudang">Logistik &amp; Gudang</option>
                                <option value="Produksi Utama">Produksi Utama</option>
                                <option value="Administrasi">Administrasi</option>
                                <option value="Maintenance & Utility">Maintenance &amp; Utility</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Nomor Telepon</label>
                            <input type="text" x-model="formData.phone" placeholder="0812-..." class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 font-medium text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Plafon Kredit (Rp)</label>
                            <input type="number" step="100000" x-model.number="formData.creditLimit" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 font-mono font-bold text-xs">
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between bg-slate-50">
                    <button type="button" @click="showModal = false" class="px-4 py-2 rounded-xl border border-gray-300 text-gray-700 font-bold text-xs">Batal</button>
                    <button type="button" @click="saveMember()" class="px-5 py-2 rounded-xl bg-[#0052cc] text-white font-bold text-xs">Simpan Data</button>
                </div>
            </div>
        </div>
    </template>

    <!-- STRICT DELETE CONFIRMATION -->
    <template x-teleport="body">
        <div x-show="showDeleteModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showDeleteModal"
                 @click.away="showDeleteModal = false"
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-rose-200 p-6 flex flex-col space-y-4">

                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center font-bold">
                        <span class="material-symbols-rounded">delete_forever</span>
                    </div>
                    <div>
                        <h4 class="font-black text-rose-900 text-sm">Hapus Data Anggota</h4>
                        <p class="text-xs text-rose-700">Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                </div>

                <p class="text-xs text-gray-700">
                    Anggota <strong x-text="memberToDelete?.name"></strong> akan dihapus dari sistem koperasi. Ketik kata <span class="font-mono text-rose-600 font-bold underline">hapus</span> untuk mengonfirmasi:
                </p>

                <input type="text" x-model="deleteConfirmInput" placeholder="Ketik 'hapus' di sini..." class="w-full px-4 py-2 rounded-xl border border-gray-300 text-xs font-bold font-mono">

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showDeleteModal = false" class="px-4 py-2 border border-gray-300 rounded-xl text-xs font-bold text-gray-700">Batal</button>
                    <button type="button"
                            @click="confirmDelete()"
                            :disabled="deleteConfirmInput.trim().toLowerCase() !== 'hapus'"
                            class="px-5 py-2 rounded-xl bg-rose-600 disabled:opacity-40 text-white font-bold text-xs">
                        Hapus Permanen
                    </button>
                </div>
            </div>
        </div>
    </template>

</div>
@endsection
