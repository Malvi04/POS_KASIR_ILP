@extends('layouts.app')

@section('title', 'Pelanggan / Anggota - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    members: [],
    branches: [
        'Cabang 1 - Mill / Pabrik',
        'Cabang 2 - Kebun Barat',
        'Cabang 3 - Kebun Timur'
    ],
    searchQuery: '',
    statusFilter: 'all',
    selectedBranch: 'all',
    showDetailModal: false,
    selectedMember: null,
    isLoading: false,
    debounceTimer: null,
    showFormModal: false,
    formMode: 'create', // 'create' | 'edit'
    formSubmitting: false,
    formErrors: {},
    memberForm: {
        id: null,
        nik: '',
        name: '',
        department: 'Pabrik / Pengolahan',
        branch: 'Cabang 1 - Mill / Pabrik',
        phone: '',
        credit_limit: 2000000,
        status: 'AKTIF',
        notes: ''
    },
    toastMessage: '',
    showToast: false,

    triggerToast(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3500);
    },

    openCreateMemberModal() {
        this.formMode = 'create';
        this.formErrors = {};
        this.memberForm = {
            id: null,
            nik: '',
            name: '',
            department: 'Pabrik / Pengolahan',
            branch: this.branches[0] || 'Cabang 1 - Mill / Pabrik',
            phone: '',
            credit_limit: 2000000,
            status: 'AKTIF',
            notes: ''
        };
        this.showFormModal = true;
    },

    openEditMemberModal(m) {
        this.formMode = 'edit';
        this.formErrors = {};
        this.memberForm = {
            id: m.id,
            nik: m.nik,
            name: m.name,
            department: m.department,
            branch: m.branch,
            phone: m.phone || '',
            credit_limit: m.credit_limit || 0,
            status: m.status || 'AKTIF',
            notes: m.notes || ''
        };
        this.showFormModal = true;
    },

    async submitMemberForm() {
        this.formErrors = {};
        if (!this.memberForm.name.trim()) this.formErrors.name = 'Nama lengkap wajib diisi.';
        if (!this.memberForm.nik.trim()) this.formErrors.nik = 'NIK wajib diisi.';
        if (Object.keys(this.formErrors).length > 0) return;

        this.formSubmitting = true;
        try {
            if (this.formMode === 'create') {
                const res = await fetch('/api/members', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json', 
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(this.memberForm)
                });
                const json = await res.json();
                if (!res.ok || !json.success) {
                    this.formErrors.general = json.message || 'Gagal mendaftarkan anggota baru.';
                    return;
                }
                this.triggerToast(`Anggota '${this.memberForm.name}' berhasil didaftarkan.`);

                // Optimistically insert new member into members list immediately!
                if (json.data) {
                    const newM = {
                        ...json.data,
                        remaining_credit: Number(json.data.credit_limit) - Number(json.data.current_debt || 0),
                        is_active: json.data.status === 'AKTIF'
                    };
                    this.members.unshift(newM);
                }
            } else {
                const res = await fetch('/api/members/' + this.memberForm.id, {
                    method: 'PUT',
                    headers: { 
                        'Content-Type': 'application/json', 
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        name: this.memberForm.name,
                        department: this.memberForm.department,
                        branch: this.memberForm.branch,
                        phone: this.memberForm.phone,
                        status: this.memberForm.status,
                        notes: this.memberForm.notes
                    })
                });
                const json = await res.json();
                if (!res.ok || !json.success) {
                    this.formErrors.general = json.message || 'Gagal memperbarui data anggota.';
                    return;
                }
                this.triggerToast(`Data anggota '${this.memberForm.name}' berhasil diperbarui.`);

                // Update in active list
                const idx = this.members.findIndex(m => m.id === this.memberForm.id);
                if (idx !== -1 && json.data) {
                    this.members[idx] = { 
                        ...this.members[idx], 
                        ...json.data,
                        remaining_credit: Number(json.data.credit_limit) - Number(json.data.current_debt || 0)
                    };
                }
            }
            this.showFormModal = false;
            await this.fetchMembers();
        } catch (err) {
            this.formErrors.general = 'Terjadi kesalahan: ' + err.message;
        } finally {
            this.formSubmitting = false;
        }
    },

    async deleteMember(m) {
        if (!confirm(`Hapus anggota ${m.name} (${m.nik}) dari sistem? Transaksi kasbon yang ada harus lunas terlebih dahulu.`)) {
            return;
        }
        try {
            const res = await fetch('/api/members/' + m.id, {
                method: 'DELETE',
                headers: { 
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const json = await res.json();
            if (!res.ok || !json.success) {
                alert(json.message || 'Gagal menghapus anggota.');
                return;
            }
            this.triggerToast(`Anggota ${m.name} berhasil dihapus.`);
            this.members = this.members.filter(it => it.id !== m.id);
            await this.fetchMembers();
        } catch (err) {
            alert('Kesalahan jaringan: ' + err.message);
        }
    },

    onSearchInput() {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            this.fetchMembers();
        }, 300);
    },

    async fetchMembers() {
        this.isLoading = true;
        try {
            const params = new URLSearchParams({
                search: (this.searchQuery || '').trim(),
                status: this.statusFilter || 'all',
                branch: this.selectedBranch || 'all',
                per_page: 50
            });
            const res = await fetch(`/api/members?${params.toString()}`, {
                headers: { 
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success && Array.isArray(json.data)) {
                    this.members = json.data;
                    if (json.branches) this.branches = json.branches;
                }
            }
        } catch (e) {
            console.error('Error fetching members:', e);
        } finally {
            this.isLoading = false;
        }
    },

    openDetail(m) {
        this.selectedMember = m;
        this.showDetailModal = true;
    },

    getBranchBadgeClass(b) {
        if (!b) return 'bg-gray-100 text-gray-700';
        if (b.includes('Mill') || b.includes('Pabrik')) return 'bg-blue-50 text-[#0052cc] border-blue-200';
        if (b.includes('Barat')) return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        if (b.includes('Timur')) return 'bg-purple-50 text-purple-700 border-purple-200';
        return 'bg-amber-50 text-amber-700 border-amber-200';
    }
}"
x-init="initOpening(); fetchMembers();"
@pos-session-sync.window="initOpening(); fetchMembers();"
class="flex flex-col h-screen w-full font-sans">
    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar -->
        <x-cashier-sidebar active="members" />

        <!-- Main Area -->
        <div class="flex-1 flex flex-col min-w-0 bg-[#fafafa]">
            <!-- Header -->
            <x-cashier-header title="Pelanggan / Anggota" />

            <!-- Content -->
            <main class="flex-1 overflow-y-auto p-6 md:p-8 bg-[#f8f9fb]">

                <!-- Page Top Action Bar -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Database Anggota Multi-Cabang</h1>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-blue-100 text-[#0052cc]">
                                Akses 3 Cabang Transparan
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">Validasi NIK, kelayakan kasbon potong gaji, dan sisa plafon terpusat secara real-time.</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="button"
                                @click="openCreateMemberModal()"
                                class="px-4 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs flex items-center gap-2 shadow-xs transition-colors cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">person_add</span>
                            <span>Tambah Anggota Baru</span>
                        </button>
                        <button @click="fetchMembers()" class="p-2 hover:bg-white rounded-xl border border-gray-200 text-gray-600 transition-colors" title="Muat Ulang API">
                            <span class="material-symbols-rounded text-[20px]" :class="isLoading ? 'animate-spin text-[#0052cc]' : ''">autorenew</span>
                        </button>
                    </div>
                </div>

                <!-- Security & Notice -->
                <div class="bg-blue-50/80 border border-blue-200 rounded-2xl p-4 mb-6 flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-xl bg-blue-100 text-[#0052cc] flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[20px]">hub</span>
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-[#0052cc] uppercase tracking-wider">Akses Terpusat Transparan Multi-Cabang</h3>
                            <p class="text-xs text-blue-900 mt-0.5 leading-relaxed">
                                Kasir di terminal ini dapat memeriksa dan memproses transaksi kasbon untuk karyawan dari seluruh unit kerja: <strong>Cabang 1 (Mill/Pabrik)</strong>, <strong>Cabang 2 (Kebun Barat)</strong>, dan <strong>Cabang 3 (Kebun Timur)</strong>.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Filter & Search Toolbar -->
                <div class="flex flex-col sm:flex-row items-center gap-3 mb-6">
                    <!-- Search Input -->
                    <div class="relative flex-1 w-full">
                        <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                        <input type="text"
                               x-model="searchQuery"
                               @input="onSearchInput()"
                               placeholder="Cari NIK, nama karyawan, kode anggota..."
                               class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-[#0052cc]/20 focus:border-[#0052cc] text-xs shadow-xs transition-all">
                    </div>

                    <!-- Branch Filter -->
                    <div class="w-full sm:w-auto">
                        <select x-model="selectedBranch"
                                @change="fetchMembers()"
                                class="w-full sm:w-auto px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 shadow-xs cursor-pointer focus:outline-none">
                            <option value="all">Semua 3 Cabang</option>
                            <option value="Cabang 1 - Mill / Pabrik">Cabang 1 - Mill / Pabrik</option>
                            <option value="Cabang 2 - Kebun Barat">Cabang 2 - Kebun Barat</option>
                            <option value="Cabang 3 - Kebun Timur">Cabang 3 - Kebun Timur</option>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div class="w-full sm:w-auto">
                        <select x-model="statusFilter"
                                @change="fetchMembers()"
                                class="w-full sm:w-auto px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 shadow-xs cursor-pointer focus:outline-none">
                            <option value="all">Semua Status</option>
                            <option value="AKTIF">Aktif</option>
                            <option value="DIBLOKIR">Diblokir / Penuh</option>
                        </select>
                    </div>
                </div>

                <!-- Members Table -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs min-w-[900px] border-collapse">
                            <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 font-bold uppercase tracking-wider text-[11px]">
                                <tr>
                                    <th class="px-6 py-4">NAMA KARYAWAN</th>
                                    <th class="px-6 py-4">NIK & KODE</th>
                                    <th class="px-6 py-4">CABANG PENUGASAN</th>
                                    <th class="px-6 py-4">DEPARTEMEN</th>
                                    <th class="px-6 py-4 text-right">SISA PLAFON KASBON</th>
                                    <th class="px-6 py-4 text-center">STATUS KASBON</th>
                                    <th class="px-6 py-4 text-center">AKSI</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700">
                                <template x-if="isLoading">
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                                            <div class="flex items-center justify-center gap-2">
                                                <span class="material-symbols-rounded animate-spin text-[20px] text-[#0052cc]">progress_activity</span>
                                                <span>Memeriksa database anggota koperasi...</span>
                                            </div>
                                        </td>
                                    </tr>
                                </template>

                                <template x-if="!isLoading && members.length === 0">
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                                            Tidak ada data anggota ditemukan.
                                        </td>
                                    </tr>
                                </template>

                                <template x-for="m in members" :key="m.id">
                                    <tr class="hover:bg-blue-50/20 transition-colors">
                                        <td class="px-6 py-4 font-bold text-gray-900" x-text="m.name"></td>
                                        <td class="px-6 py-4 font-mono">
                                            <span class="font-bold text-gray-800 block" x-text="m.nik"></span>
                                            <span class="text-[10px] text-gray-400" x-text="m.member_code"></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold border"
                                                  :class="getBranchBadgeClass(m.branch)"
                                                  x-text="m.branch"></span>
                                        </td>
                                        <td class="px-6 py-4 font-semibold text-gray-600" x-text="m.department"></td>
                                        <td class="px-6 py-4 text-right font-mono font-black text-emerald-600" x-text="formatRupiah(m.remaining_credit)"></td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold border"
                                                  :class="m.status === 'AKTIF' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200'"
                                                  x-text="m.status === 'AKTIF' ? 'Eligible' : 'Dibekukan'">
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="flex items-center justify-center gap-1.5">
                                                <button type="button"
                                                        @click="openDetail(m)"
                                                        class="px-2.5 py-1 bg-gray-100 hover:bg-[#0052cc] hover:text-white rounded-lg text-xs font-bold transition-colors">
                                                    Cek Plafon
                                                </button>
                                                <button type="button"
                                                        @click="openEditMemberModal(m)"
                                                        class="p-1 text-gray-500 hover:text-[#0052cc] hover:bg-blue-50 rounded-lg transition-colors cursor-pointer"
                                                        title="Edit Data Anggota">
                                                    <span class="material-symbols-rounded text-[18px]">edit</span>
                                                </button>
                                                <button type="button"
                                                        @click="deleteMember(m)"
                                                        class="p-1 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                                                        title="Hapus Anggota">
                                                    <span class="material-symbols-rounded text-[18px]">delete</span>
                                                </button>
                                            </div>
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

    <!-- Modal Detail Plafon Kasir -->
    <div x-show="showDetailModal"
         class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-gray-200"
             @click.outside="showDetailModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-rounded text-[#0052cc] text-[24px]">verified_user</span>
                    <h3 class="font-black text-gray-900 text-base">Info Plafon Kredit Kasbon</h3>
                </div>
                <button type="button" @click="showDetailModal = false" class="text-gray-400 hover:text-gray-600">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>

            <div class="space-y-4 mt-4 text-xs" x-if="selectedMember">
                <div class="p-4 bg-gray-50 rounded-2xl border border-gray-200/80 space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Nama Karyawan:</span>
                        <strong class="text-gray-900" x-text="selectedMember?.name"></strong>
                    </div>
                    <div class="flex justify-between font-mono">
                        <span class="text-gray-500">NIK:</span>
                        <strong class="text-gray-900" x-text="selectedMember?.nik"></strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Cabang Penugasan:</span>
                        <span class="font-bold text-[#0052cc]" x-text="selectedMember?.branch"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Departemen:</span>
                        <strong class="text-gray-900" x-text="selectedMember?.department"></strong>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="p-3 bg-blue-50 rounded-xl text-center border border-blue-200">
                        <span class="text-[10px] uppercase font-bold text-blue-700 block mb-0.5">Plafon Bulanan</span>
                        <span class="font-mono font-black text-sm text-[#0052cc]" x-text="formatRupiah(selectedMember?.credit_limit || 0)"></span>
                    </div>
                    <div class="p-3 bg-amber-50 rounded-xl text-center border border-amber-200">
                        <span class="text-[10px] uppercase font-bold text-amber-700 block mb-0.5">Kasbon Berjalan</span>
                        <span class="font-mono font-black text-sm text-amber-700" x-text="formatRupiah(selectedMember?.current_debt || 0)"></span>
                    </div>
                </div>

                <div class="p-4 bg-emerald-50 rounded-2xl border border-emerald-200 text-center">
                    <span class="text-xs uppercase font-bold text-emerald-800 block mb-1">Sisa Plafon Siap Belanja</span>
                    <span class="font-mono font-black text-2xl text-emerald-700" x-text="formatRupiah(selectedMember?.remaining_credit || 0)"></span>
                    <p class="text-[11px] text-emerald-600 mt-1">Saldo plafon otomatis terpotong saat transaksi kasbon.</p>
                </div>

                <div class="pt-2">
                    <a :href="'/proses-kredit?nik=' + selectedMember?.nik" class="w-full py-2.5 bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold rounded-xl text-xs flex items-center justify-center gap-2 transition-colors">
                        <span class="material-symbols-rounded text-[18px]">shopping_cart_checkout</span>
                        <span>Lanjutkan Transaksi Kasbon POS</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Form Tambah / Edit Anggota -->
    <div x-show="showFormModal"
         class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-gray-200"
             @click.outside="showFormModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-rounded text-[#0052cc] text-[24px]" x-text="formMode === 'create' ? 'person_add' : 'edit'"></span>
                    <h3 class="font-black text-gray-900 text-base" x-text="formMode === 'create' ? 'Tambah Anggota Koperasi Baru' : 'Edit Profil Anggota'"></h3>
                </div>
                <button type="button" @click="showFormModal = false" class="text-gray-400 hover:text-gray-600">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>

            <!-- Error Banner -->
            <template x-if="formErrors.general">
                <div class="mt-3 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs" x-text="formErrors.general"></div>
            </template>

            <form @submit.prevent="submitMemberForm()" class="space-y-3.5 mt-4 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">NIK Karyawan <span class="text-rose-500">*</span></label>
                    <input type="text"
                           x-model="memberForm.nik"
                           :disabled="formMode === 'edit'"
                           placeholder="Contoh: 202609821099"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 font-mono text-xs focus:ring-2 focus:ring-[#0052cc]/20 focus:border-[#0052cc] outline-none disabled:bg-gray-100">
                    <span class="text-rose-500 text-[10px]" x-text="formErrors.nik"></span>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Nama Lengkap Karyawan <span class="text-rose-500">*</span></label>
                    <input type="text"
                           x-model="memberForm.name"
                           placeholder="Nama lengkap..."
                           class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-xs focus:ring-2 focus:ring-[#0052cc]/20 focus:border-[#0052cc] outline-none">
                    <span class="text-rose-500 text-[10px]" x-text="formErrors.name"></span>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Cabang Penugasan</label>
                        <select x-model="memberForm.branch" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 bg-white text-xs font-semibold focus:border-[#0052cc] outline-none cursor-pointer">
                            <option value="Cabang 1 - Mill / Pabrik">Cabang 1 - Mill / Pabrik</option>
                            <option value="Cabang 2 - Kebun Barat">Cabang 2 - Kebun Barat</option>
                            <option value="Cabang 3 - Kebun Timur">Cabang 3 - Kebun Timur</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Departemen / Divisi</label>
                        <input type="text"
                               x-model="memberForm.department"
                               placeholder="Departemen..."
                               class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-xs focus:border-[#0052cc] outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">No. WhatsApp / HP</label>
                        <input type="text"
                               x-model="memberForm.phone"
                               placeholder="0812xxxx"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-xs focus:border-[#0052cc] outline-none">
                    </div>
                    <template x-if="formMode === 'create'">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Plafon Kredit Awal (Rp)</label>
                            <input type="number"
                                   x-model="memberForm.credit_limit"
                                   min="0"
                                   step="50000"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 font-mono text-xs focus:border-[#0052cc] outline-none">
                        </div>
                    </template>
                    <template x-if="formMode === 'edit'">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Status Keanggotaan</label>
                            <select x-model="memberForm.status" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 bg-white text-xs font-semibold focus:border-[#0052cc] outline-none cursor-pointer">
                                <option value="AKTIF">AKTIF (Eligible Kasbon)</option>
                                <option value="DIBLOKIR">DIBLOKIR (Dibekukan)</option>
                            </select>
                        </div>
                    </template>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-gray-100">
                    <button type="button" @click="showFormModal = false" class="px-4 py-2 rounded-xl border border-gray-300 text-gray-700 font-bold hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                            :disabled="formSubmitting"
                            class="px-5 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold flex items-center gap-1.5 shadow-sm transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]" :class="formSubmitting ? 'animate-spin' : ''" x-text="formSubmitting ? 'progress_activity' : 'check'"></span>
                        <span x-text="formMode === 'create' ? 'Simpan Anggota' : 'Perbarui Anggota'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notification -->
    <div x-show="showToast"
         x-transition.opacity
         style="display: none;"
         class="fixed bottom-6 right-6 z-50 bg-gray-900 text-white text-xs px-4 py-3 rounded-2xl shadow-xl flex items-center gap-2.5 border border-gray-800">
        <span class="material-symbols-rounded text-emerald-400 text-[20px]">check_circle</span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
