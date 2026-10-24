@extends('layouts.app')

@section('title', 'Kelola Akun - ILP POS')

@section('content')
<div x-data="{
    users: [],
    searchQuery: '',
    roleFilter: 'all',
    securityLogs: [],
    showEditModal: false,
    showDeleteModal: false,
    deleteInputText: '',
    selectedUser: null,
    editingUser: null,
    toastMessage: '',
    showToast: false,

    // Form Tambah Kasir
    formKasir: {
        name: '',
        username: '',
        branch: 'Cabang A',
        pin: '1234'
    },

    // Form Registrasi Manager
    formManager: {
        name: '',
        email: '',
        accessLevel: 'full', // full | reporting
        branch: 'Cabang Pusat',
        pin: '123456'
    },

    initUsers() {
        const stored = localStorage.getItem('pos_ilp_users_v1');
        if (stored) {
            try {
                this.users = JSON.parse(stored);
            } catch(e) {
                this.users = this.getDefaultUsers();
            }
        } else {
            this.users = this.getDefaultUsers();
            this.saveUsers();
        }

        const storedLogs = localStorage.getItem('pos_ilp_security_logs_v1');
        if (storedLogs) {
            try { this.securityLogs = JSON.parse(storedLogs); } catch(e){}
        } else {
            this.securityLogs = [
                { time: '14:20:05', text: 'User [Ahmad Fauzi] berhasil didaftarkan oleh Manager [Diana Putri]' },
                { time: '09:12:33', text: 'Perubahan Level Akses [Diana Putri] oleh Owner' }
            ];
            this.saveLogs();
        }
    },

    saveUsers() {
        localStorage.setItem('pos_ilp_users_v1', JSON.stringify(this.users));
    },

    saveLogs() {
        localStorage.setItem('pos_ilp_security_logs_v1', JSON.stringify(this.securityLogs));
    },

    getDefaultUsers() {
        return [
            { id: 'usr_01', name: 'Budi Kurniawan', username: 'cashier_budi', email: 'budi@pos.com', role: 'KASIR', branch: 'Cabang A', status: 'AKTIF', pin: '1234' },
            { id: 'usr_02', name: 'Citra Dewi', username: 'cashier_citra', email: 'citra@pos.com', role: 'KASIR', branch: 'Cabang A', status: 'AKTIF', pin: '1234' },
            { id: 'usr_03', name: 'Ahmad Fauzi', username: 'cashier_fauzi', email: 'fauzi@pos.com', role: 'KASIR', branch: 'Cabang B', status: 'AKTIF', pin: '1234' },
            { id: 'usr_04', name: 'Diana Putri', username: 'manager_diana', email: 'diana@pos.com', role: 'MANAGER', branch: 'Cabang A', status: 'AKTIF', pin: '123456' },
            { id: 'usr_05', name: 'Hendra Gunawan', username: 'owner_hendra', email: 'owner@pos.com', role: 'OWNER', branch: 'Cabang Pusat', status: 'AKTIF', pin: '999999' }
        ];
    },

    get filteredUsers() {
        return this.users.filter(u => {
            if (this.roleFilter !== 'all' && u.role !== this.roleFilter) return false;
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                return u.name.toLowerCase().includes(q) ||
                       u.username.toLowerCase().includes(q) ||
                       u.branch.toLowerCase().includes(q) ||
                       u.role.toLowerCase().includes(q);
            }
            return true;
        });
    },

    addKasir() {
        if (!this.formKasir.name || !this.formKasir.username) {
            alert('Silakan isi nama lengkap dan username kasir!');
            return;
        }

        const newUser = {
            id: 'usr_' + Date.now(),
            name: this.formKasir.name,
            username: this.formKasir.username.toLowerCase().replace(/\s+/g, '_'),
            email: this.formKasir.username.toLowerCase() + '@pos.com',
            role: 'KASIR',
            branch: this.formKasir.branch,
            status: 'AKTIF',
            pin: this.formKasir.pin || '1234'
        };

        this.users.unshift(newUser);
        this.saveUsers();

        const timeStr = new Date().toLocaleTimeString('id-ID');
        this.securityLogs.unshift({
            time: timeStr,
            text: `Kasir [${newUser.name}] (${newUser.username}) didaftarkan ke ${newUser.branch}`
        });
        this.saveLogs();

        this.triggerToast(`Kasir ${newUser.name} berhasil didaftarkan!`);
        this.formKasir.name = '';
        this.formKasir.username = '';
    },

    addManager() {
        if (!this.formManager.name || !this.formManager.email) {
            alert('Silakan isi nama lengkap dan email manager!');
            return;
        }

        const newUser = {
            id: 'usr_' + Date.now(),
            name: this.formManager.name,
            username: this.formManager.name.toLowerCase().replace(/\s+/g, '_'),
            email: this.formManager.email,
            role: 'MANAGER',
            accessLevel: this.formManager.accessLevel,
            branch: this.formManager.branch,
            status: 'AKTIF',
            pin: this.formManager.pin || '123456'
        };

        this.users.unshift(newUser);
        this.saveUsers();

        const timeStr = new Date().toLocaleTimeString('id-ID');
        this.securityLogs.unshift({
            time: timeStr,
            text: `Manager [${newUser.name}] (${newUser.accessLevel.toUpperCase()}) didaftarkan oleh Owner`
        });
        this.saveLogs();

        this.triggerToast(`Manager ${newUser.name} berhasil didaftarkan!`);
        this.formManager.name = '';
        this.formManager.email = '';
    },

    openEdit(u) {
        this.editingUser = JSON.parse(JSON.stringify(u));
        this.showEditModal = true;
    },

    saveEdit() {
        if (!this.editingUser) return;
        const idx = this.users.findIndex(u => u.id === this.editingUser.id);
        if (idx !== -1) {
            this.users[idx] = this.editingUser;
            this.saveUsers();

            const timeStr = new Date().toLocaleTimeString('id-ID');
            this.securityLogs.unshift({
                time: timeStr,
                text: `Data user [${this.editingUser.name}] diperbarui`
            });
            this.saveLogs();

            this.triggerToast(`Data user ${this.editingUser.name} berhasil diperbarui!`);
        }
        this.showEditModal = false;
        this.editingUser = null;
    },

    toggleStatus(u) {
        u.status = (u.status === 'AKTIF') ? 'NONAKTIF' : 'AKTIF';
        this.saveUsers();

        const timeStr = new Date().toLocaleTimeString('id-ID');
        this.securityLogs.unshift({
            time: timeStr,
            text: `Status user [${u.name}] diubah menjadi ${u.status}`
        });
        this.saveLogs();

        this.triggerToast(`Status ${u.name} sekarang: ${u.status}`);
    },

    openDelete(u) {
        this.selectedUser = u;
        this.deleteInputText = '';
        this.showDeleteModal = true;
    },

    confirmDelete() {
        if (!this.selectedUser) return;
        if (this.deleteInputText.trim().toLowerCase() !== 'hapus') {
            alert('Ketik "hapus" untuk konfirmasi penghapusan user!');
            return;
        }

        const name = this.selectedUser.name;
        this.users = this.users.filter(u => u.id !== this.selectedUser.id);
        this.saveUsers();

        const timeStr = new Date().toLocaleTimeString('id-ID');
        this.securityLogs.unshift({
            time: timeStr,
            text: `Akun user [${name}] dihapus dari sistem`
        });
        this.saveLogs();

        this.showDeleteModal = false;
        this.selectedUser = null;
        this.deleteInputText = '';
        this.triggerToast(`Akun ${name} telah berhasil dihapus!`);
    },

    triggerToast(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3500);
    }
}"
x-init="initUsers()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">

    <!-- Sidebar -->
    <x-manager-sidebar active="akun" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden bg-[#f8f9fa]">

        <!-- Top Header -->
        <header class="h-[68px] bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <h1 class="text-[18px] font-bold text-gray-900 tracking-tight">Kelola Akun & Hak Akses</h1>

            <div class="flex items-center gap-5 text-gray-600">
                <button type="button" @click="initUsers(); triggerToast('Data pengguna disinkronkan!')" class="hover:text-gray-900 transition-colors p-1 rounded-lg hover:bg-gray-100 cursor-pointer" title="Sinkronisasi">
                    <span class="material-symbols-rounded text-[22px]">sync</span>
                </button>
                <div class="h-6 w-px bg-gray-200"></div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-rounded text-green-600 text-[20px]">verified_user</span>
                    <span class="text-[13px] font-semibold text-gray-700">Aman Terenkripsi</span>
                </div>
            </div>
        </header>

        <!-- Scrollable Content Workspace -->
        <div class="flex-1 overflow-y-auto p-6 lg:p-8">
            <div class="max-w-[1400px] mx-auto grid grid-cols-12 gap-6 items-start">

                <!-- Left Column: Forms & System Security Log (8 cols) -->
                <div class="col-span-12 xl:col-span-8 space-y-6 min-w-0">

                    <!-- Card 1: Tambah Kasir Baru -->
                    <section class="bg-white border border-gray-200/90 rounded-2xl p-6 lg:p-7 shadow-xs">
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
                            <div>
                                <h2 class="text-[17px] font-bold text-[#0052cc] tracking-tight">Tambah Kasir Baru</h2>
                                <p class="text-[13px] text-gray-500 mt-0.5">Otoritas Manager: Tambahkan personel operasional meja kasir.</p>
                            </div>
                            <span class="bg-[#dbeafe] text-[#0052cc] text-[10.5px] font-extrabold px-3.5 py-1 rounded-full tracking-wider uppercase shrink-0">
                                AKSES MANAGER
                            </span>
                        </div>

                        <form @submit.prevent="addKasir()" class="space-y-5">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5 text-xs">
                                <!-- Nama Lengkap -->
                                <div>
                                    <label class="block font-bold text-gray-600 tracking-wider uppercase mb-2">
                                        NAMA LENGKAP KASIR <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text"
                                           x-model="formKasir.name"
                                           placeholder="Contoh: Budi Santoso"
                                           required
                                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 transition-all">
                                </div>

                                <!-- Username -->
                                <div>
                                    <label class="block font-bold text-gray-600 tracking-wider uppercase mb-2">
                                        USERNAME SISTEM <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text"
                                           x-model="formKasir.username"
                                           placeholder="cashier_budi"
                                           required
                                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white font-mono text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 transition-all">
                                </div>

                                <!-- PIN Default -->
                                <div>
                                    <label class="block font-bold text-gray-600 tracking-wider uppercase mb-2">
                                        PIN KASIR (4 DIGIT)
                                    </label>
                                    <input type="password"
                                           x-model="formKasir.pin"
                                           maxlength="6"
                                           placeholder="1234"
                                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white font-mono text-gray-800 focus:outline-none focus:border-[#0052cc]">
                                </div>

                                <!-- Cabang Penugasan -->
                                <div>
                                    <label class="block font-bold text-gray-600 tracking-wider uppercase mb-2">
                                        CABANG PENUGASAN
                                    </label>
                                    <div class="relative">
                                        <select x-model="formKasir.branch"
                                                class="w-full appearance-none px-4 py-2.5 pr-10 rounded-xl border border-gray-200 bg-white font-medium text-gray-800 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 transition-all cursor-pointer">
                                            <option value="Cabang A">Cabang A (Gerai Utama)</option>
                                            <option value="Cabang B">Cabang B</option>
                                            <option value="Cabang Pusat">Cabang Pusat</option>
                                        </select>
                                        <span class="material-symbols-rounded absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[20px]">expand_more</span>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-1 flex justify-end">
                                <button type="submit" class="bg-[#0052cc] hover:bg-[#0043a8] active:scale-[0.99] text-white px-5 py-2.5 rounded-xl font-bold text-xs inline-flex items-center gap-2 shadow-xs transition-all cursor-pointer">
                                    <span class="material-symbols-rounded text-[18px]">person_add</span>
                                    <span>Daftarkan Kasir</span>
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- Card 2: Registrasi Manager Cabang -->
                    <section class="bg-white border border-gray-200/90 border-l-[3.5px] border-l-[#9a3412] rounded-2xl p-6 lg:p-7 shadow-xs">
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
                            <div>
                                <h2 class="text-[17px] font-bold text-[#9a3412] tracking-tight">Registrasi Manager Cabang</h2>
                                <p class="text-[13px] text-gray-500 mt-0.5">Otoritas Owner: Penugasan level manajemen tinggi gerai.</p>
                            </div>
                            <span class="bg-[#ffedd5] text-[#9a3412] text-[10.5px] font-extrabold px-3.5 py-1 rounded-full tracking-wider uppercase shrink-0">
                                AKSES OWNER
                            </span>
                        </div>

                        <form @submit.prevent="addManager()" class="space-y-5">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5 text-xs">
                                <!-- Nama Lengkap Manager -->
                                <div>
                                    <label class="block font-bold text-gray-600 tracking-wider uppercase mb-2">
                                        NAMA LENGKAP MANAGER <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text"
                                           x-model="formManager.name"
                                           placeholder="Contoh: Siti Aminah"
                                           required
                                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#9a3412] focus:ring-2 focus:ring-[#9a3412]/15 transition-all">
                                </div>

                                <!-- Email Profesional -->
                                <div>
                                    <label class="block font-bold text-gray-600 tracking-wider uppercase mb-2">
                                        EMAIL PROFESIONAL <span class="text-red-500">*</span>
                                    </label>
                                    <input type="email"
                                           x-model="formManager.email"
                                           placeholder="manager.siti@pos.com"
                                           required
                                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#9a3412] focus:ring-2 focus:ring-[#9a3412]/15 transition-all">
                                </div>

                                <!-- Level Akses -->
                                <div>
                                    <label class="block font-bold text-gray-600 tracking-wider uppercase mb-2">
                                        LEVEL AKSES
                                    </label>
                                    <div class="grid grid-cols-2 gap-3">
                                        <button type="button" @click="formManager.accessLevel = 'full'"
                                                :class="formManager.accessLevel === 'full'
                                                    ? 'bg-[#fff7ed] border-[#9a3412] text-[#9a3412] font-bold'
                                                    : 'bg-white border-gray-200 text-gray-500 font-semibold hover:bg-gray-50'"
                                                class="px-3.5 py-2.5 rounded-xl border text-xs inline-flex items-center justify-center gap-1.5 transition-all cursor-pointer">
                                            <span class="material-symbols-rounded text-[16px]">verified_user</span>
                                            <span class="truncate">Full Access</span>
                                        </button>

                                        <button type="button" @click="formManager.accessLevel = 'reporting'"
                                                :class="formManager.accessLevel === 'reporting'
                                                    ? 'bg-[#fff7ed] border-[#9a3412] text-[#9a3412] font-bold'
                                                    : 'bg-white border-gray-200 text-gray-500 font-semibold hover:bg-gray-50'"
                                                class="px-3.5 py-2.5 rounded-xl border text-xs inline-flex items-center justify-center gap-1.5 transition-all cursor-pointer">
                                            <span class="material-symbols-rounded text-[16px]">visibility</span>
                                            <span class="truncate">Reporting Only</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Cabang -->
                                <div>
                                    <label class="block font-bold text-gray-600 tracking-wider uppercase mb-2">
                                        CABANG PENUGASAN
                                    </label>
                                    <select x-model="formManager.branch"
                                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white font-medium text-gray-800 focus:outline-none focus:border-[#9a3412]">
                                        <option value="Cabang Pusat">Cabang Pusat</option>
                                        <option value="Cabang A">Cabang A (Gerai Utama)</option>
                                        <option value="Cabang B">Cabang B</option>
                                    </select>
                                </div>
                            </div>

                            <div class="pt-1 flex justify-end">
                                <button type="submit" class="bg-[#9a3412] hover:bg-[#7c2d12] active:scale-[0.99] text-white px-5 py-2.5 rounded-xl font-bold text-xs inline-flex items-center gap-2 shadow-xs transition-all cursor-pointer">
                                    <span class="material-symbols-rounded text-[18px]">admin_panel_settings</span>
                                    <span>Registrasikan Manager</span>
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- Card 3: Catatan Keamanan Sistem -->
                    <section class="bg-gradient-to-r from-[#1e242b] via-[#1b2535] to-[#112340] text-white rounded-2xl p-6 shadow-md">
                        <div class="flex items-center gap-2.5 mb-2">
                            <span class="material-symbols-rounded text-[20px] text-gray-200">security</span>
                            <h3 class="text-sm font-bold tracking-tight">Catatan Keamanan Sistem</h3>
                        </div>
                        <p class="text-xs text-gray-300 leading-relaxed max-w-2xl mb-4">
                            Aktivitas registrasi dan perubahan hak akses diaudit secara otomatis untuk keamanan data operasional gerai.
                        </p>
                        <div class="space-y-2 text-xs">
                            <template x-for="(log, idx) in securityLogs.slice(0, 5)" :key="idx">
                                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                    <span class="font-mono text-gray-400 shrink-0" x-text="log.time"></span>
                                    <span class="text-gray-300" x-text="log.text"></span>
                                </div>
                            </template>
                        </div>
                    </section>

                </div>

                <!-- Right Column: Active Users (4 cols) -->
                <div class="col-span-12 xl:col-span-4 space-y-6 min-w-0">

                    <!-- Card 1: Daftar Pengguna Aktif -->
                    <section class="bg-white border border-gray-200/90 rounded-2xl p-5 lg:p-6 shadow-xs flex flex-col">
                        <div class="flex items-center justify-between mb-3.5">
                            <h2 class="text-sm font-bold text-[#0052cc] tracking-tight">Daftar Pengguna Sistem</h2>
                            <span class="text-xs font-mono text-gray-500 font-bold" x-text="filteredUsers.length + ' Akun'"></span>
                        </div>

                        <!-- Search Box -->
                        <div class="relative mb-3">
                            <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                            <input type="text"
                                   x-model="searchQuery"
                                   placeholder="Cari nama, role, cabang..."
                                   class="w-full pl-10 pr-4 py-2 rounded-xl border border-gray-200 bg-white text-xs text-gray-700 placeholder-gray-400 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 transition-all">
                        </div>

                        <!-- Role Filter Pills -->
                        <div class="flex gap-1 mb-3 bg-gray-100 p-1 rounded-xl text-[11px]">
                            <button type="button" @click="roleFilter = 'all'" :class="roleFilter === 'all' ? 'bg-white font-bold text-gray-900 shadow-2xs' : 'text-gray-500'" class="flex-1 py-1 rounded cursor-pointer">Semua</button>
                            <button type="button" @click="roleFilter = 'KASIR'" :class="roleFilter === 'KASIR' ? 'bg-white font-bold text-green-700 shadow-2xs' : 'text-gray-500'" class="flex-1 py-1 rounded cursor-pointer">Kasir</button>
                            <button type="button" @click="roleFilter = 'MANAGER'" :class="roleFilter === 'MANAGER' ? 'bg-white font-bold text-blue-700 shadow-2xs' : 'text-gray-500'" class="flex-1 py-1 rounded cursor-pointer">Manager</button>
                            <button type="button" @click="roleFilter = 'OWNER'" :class="roleFilter === 'OWNER' ? 'bg-white font-bold text-orange-700 shadow-2xs' : 'text-gray-500'" class="flex-1 py-1 rounded cursor-pointer">Owner</button>
                        </div>

                        <!-- User List -->
                        <div class="divide-y divide-gray-100 max-h-[460px] overflow-y-auto">
                            <template x-for="u in filteredUsers" :key="u.id">
                                <div class="py-3 flex items-start justify-between gap-3">
                                    <div class="flex items-start gap-3 min-w-0">
                                        <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 mt-0.5"
                                             :class="{
                                                 'bg-green-100 text-green-700': u.role === 'KASIR',
                                                 'bg-blue-100 text-blue-700': u.role === 'MANAGER',
                                                 'bg-orange-100 text-orange-700': u.role === 'OWNER'
                                             }">
                                            <span class="material-symbols-rounded text-[20px]">account_circle</span>
                                        </div>
                                        <div class="min-w-0">
                                            <h3 class="text-xs font-bold text-gray-900 truncate" x-text="u.name"></h3>
                                            <p class="text-[11px] text-gray-500 truncate" x-text="u.branch + ' • @' + u.username"></p>
                                            <div class="flex items-center gap-1.5 mt-1">
                                                <span class="inline-block text-[9.5px] font-extrabold px-2 py-0.5 rounded uppercase tracking-wider"
                                                      :class="{
                                                          'bg-green-100 text-green-700': u.role === 'KASIR',
                                                          'bg-blue-100 text-blue-700': u.role === 'MANAGER',
                                                          'bg-orange-100 text-orange-700': u.role === 'OWNER'
                                                      }"
                                                      x-text="u.role"></span>
                                                <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded uppercase"
                                                      :class="u.status === 'AKTIF' ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'"
                                                      x-text="u.status"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action buttons -->
                                    <div class="flex items-center gap-1 shrink-0">
                                        <button type="button"
                                                @click="openEdit(u)"
                                                title="Edit Data User"
                                                class="p-1 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors cursor-pointer">
                                            <span class="material-symbols-rounded text-[18px]">edit</span>
                                        </button>
                                        <button type="button"
                                                @click="toggleStatus(u)"
                                                :title="u.status === 'AKTIF' ? 'Nonaktifkan Akun' : 'Aktifkan Akun'"
                                                class="p-1 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors cursor-pointer">
                                            <span class="material-symbols-rounded text-[18px]" x-text="u.status === 'AKTIF' ? 'block' : 'check_circle'"></span>
                                        </button>
                                        <button type="button"
                                                @click="openDelete(u)"
                                                title="Hapus Akun"
                                                class="p-1 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors cursor-pointer">
                                            <span class="material-symbols-rounded text-[18px]">delete</span>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </section>

                </div>

            </div>
        </div>

    </main>

    <!-- MODAL EDIT USER -->
    <template x-teleport="body">
        <div x-show="showEditModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div @click.away="showEditModal = false"
                 class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-md overflow-hidden animate-scale-up">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-rounded text-[#0052cc] text-[22px]">manage_accounts</span>
                        <h3 class="font-bold text-gray-900 text-sm">Edit Data Pengguna</h3>
                    </div>
                    <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <form @submit.prevent="saveEdit()" class="p-6 space-y-4 text-xs font-sans" x-if="editingUser">
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Nama Lengkap</label>
                        <input type="text"
                               x-model="editingUser.name"
                               required
                               class="w-full px-3.5 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-[#0052cc]" />
                    </div>

                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Username</label>
                        <input type="text"
                               x-model="editingUser.username"
                               required
                               class="w-full px-3.5 py-2 border border-gray-300 rounded-xl font-mono focus:outline-none focus:border-[#0052cc]" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">Role / Peran</label>
                            <select x-model="editingUser.role" class="w-full px-3 py-2 border border-gray-300 rounded-xl">
                                <option value="KASIR">KASIR</option>
                                <option value="MANAGER">MANAGER</option>
                                <option value="OWNER">OWNER</option>
                            </select>
                        </div>
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">Status</label>
                            <select x-model="editingUser.status" class="w-full px-3 py-2 border border-gray-300 rounded-xl">
                                <option value="AKTIF">AKTIF</option>
                                <option value="NONAKTIF">NONAKTIF</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">Cabang Penugasan</label>
                            <select x-model="editingUser.branch" class="w-full px-3 py-2 border border-gray-300 rounded-xl">
                                <option value="Cabang A">Cabang A</option>
                                <option value="Cabang B">Cabang B</option>
                                <option value="Cabang Pusat">Cabang Pusat</option>
                            </select>
                        </div>
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">PIN Keamanan</label>
                            <input type="password"
                                   x-model="editingUser.pin"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-xl font-mono focus:outline-none focus:border-[#0052cc]" />
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end gap-2">
                        <button type="button"
                                @click="showEditModal = false"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold cursor-pointer">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-5 py-2 bg-[#0052cc] hover:bg-blue-700 text-white rounded-xl font-bold cursor-pointer">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </template>

    <!-- MODAL HAPUS USER DENGAN KETIK 'hapus' -->
    <template x-teleport="body">
        <div x-show="showDeleteModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div @click.away="showDeleteModal = false"
                 class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-md overflow-hidden animate-scale-up">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-red-50">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-rounded text-red-600 text-[22px]">warning</span>
                        <h3 class="font-bold text-gray-900 text-sm">Hapus Pengguna</h3>
                    </div>
                    <button @click="showDeleteModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs font-sans">
                    <p class="text-gray-600">
                        Anda akan menghapus akun <strong class="text-gray-900" x-text="selectedUser?.name"></strong> (@<span x-text="selectedUser?.username"></span>) secara permanen.
                    </p>

                    <div class="bg-gray-50 p-3 rounded-xl border border-gray-200">
                        <label class="block font-bold text-gray-700 mb-1">
                            Ketik kata <span class="text-red-600 font-mono font-black">hapus</span> untuk konfirmasi:
                        </label>
                        <input type="text"
                               x-model="deleteInputText"
                               placeholder="ketik hapus di sini"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs font-mono focus:outline-none focus:border-red-500" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button"
                                @click="showDeleteModal = false"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold cursor-pointer">
                            Batal
                        </button>
                        <button type="button"
                                @click="confirmDelete()"
                                :disabled="deleteInputText.trim().toLowerCase() !== 'hapus'"
                                :class="deleteInputText.trim().toLowerCase() === 'hapus' ? 'bg-red-600 hover:bg-red-700 text-white cursor-pointer' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                                class="px-5 py-2 rounded-xl font-bold transition-colors">
                            Hapus Pengguna
                        </button>
                    </div>
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
