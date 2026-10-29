@extends('layouts.app')

@section('title', 'Kelola Akun & Penugasan Kasir - ILP POS')

@section('content')
<div x-data="{
    users: [],
    membersList: [],
    securityLogs: [],
    searchQuery: '',
    roleFilter: 'all',
    branchFilter: 'all',
    statusFilter: 'all',
    showEditModal: false,
    showDeleteModal: false,
    showManageAssignmentModal: false,
    deleteInputText: '',
    selectedUser: null,
    editingUser: null,
    managingUser: null,
    toastMessage: '',
    showToast: false,
    showFormKasirPassword: false,
    showFormKasirPin: false,
    showEditingPassword: false,
    showEditingPin: false,
    showPasswordList: {},
    showPinList: {},

    // Form Tambah / Penugasan Kasir dari Members
    formKasir: {
        branch: 'Pusat',
        username: '',
        password: '',
        pin: '123456'
    },
    selectedMember: null,
    searchMemberQuery: '',
    isMemberDropdownOpen: false,

    // Form Registrasi Manager Cabang (Akses Owner)
    formManager: {
        name: '',
        email: '',
        accessLevel: 'full', // full | reporting
        branch: 'Cabang Pusat',
        pin: '123456'
    },

    // Form Tambah Cabang pada Modal Kelola Penugasan
    modalNewBranch: 'Cabang A',

    defaultMembers: [
        { id: 'MBR-001', nik: '327501002230004', name: 'Bambang Pamungkas', dept: 'Logistik & Gudang', status: 'AKTIF' },
        { id: 'MBR-002', nik: '327501440230012', name: 'Siti Lestari', dept: 'Administrasi', status: 'AKTIF' },
        { id: 'MBR-003', nik: '327501082230899', name: 'Andri Setiawan', dept: 'Produksi A', status: 'DIBLOKIR' },
        { id: 'MBR-004', nik: '327501550230056', name: 'Dewi Indah', dept: 'Maintenance & Utility', status: 'AKTIF' },
        { id: 'MBR-005', nik: '327501009930011', name: 'Raka Hendra', dept: 'Logistik & Gudang', status: 'AKTIF' }
    ],

    initUsers() {
        window.accountsManagerScope = this;
        // 1. Inisialisasi Data Members untuk pencarian karyawan
        this.loadMembers();

        // 2. Inisialisasi Data Users
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

        // 3. Normalisasi Struktur Penugasan Cabang (Migrasi Idempotent Backwards-Compatible)
        let needsSave = false;
        this.users.forEach(u => {
            if (!Array.isArray(u.assignedBranches) || u.assignedBranches.length === 0) {
                const legacyBranch = u.branch || 'Cabang A';
                u.assignedBranches = [
                    { branch: legacyBranch, status: u.status || 'AKTIF', assignedAt: '19 Okt 2026' }
                ];
                needsSave = true;
            }
            if (!u.nik) {
                const foundMember = this.membersList.find(m => m.name && m.name.toLowerCase() === (u.name || '').toLowerCase());
                if (foundMember) {
                    u.memberId = foundMember.id;
                    u.nik = foundMember.nik;
                    u.dept = foundMember.dept;
                    needsSave = true;
                } else if (u.role === 'KASIR') {
                    u.nik = '3275' + Math.floor(100000000000 + Math.random() * 900000000000);
                    needsSave = true;
                }
            }
            if (!u.pin || u.pin === '1234') {
                u.pin = '123456';
                needsSave = true;
            }
            if (!u.password) {
                u.password = (u.username ? u.username.replace('cashier_', '') : 'kasir') + '123';
                needsSave = true;
            }
        });
        if (needsSave) {
            this.saveUsers();
        }

        // 4. Inisialisasi Security Audit Logs
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

    loadMembers() {
        try {
            const raw = localStorage.getItem('pos_ilp_members_v1');
            if (raw) {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed) && parsed.length > 0) {
                    this.membersList = parsed;
                    return;
                }
            }
        } catch(e){}
        this.membersList = JSON.parse(JSON.stringify(this.defaultMembers));
    },

    saveUsers() {
        localStorage.setItem('pos_ilp_users_v1', JSON.stringify(this.users));
    },

    saveLogs() {
        localStorage.setItem('pos_ilp_security_logs_v1', JSON.stringify(this.securityLogs));
    },

    getDefaultUsers() {
        return [
            {
                id: 'usr_01',
                name: 'Budi Kurniawan',
                username: 'cashier_budi',
                email: 'budi@pos.com',
                role: 'KASIR',
                branch: 'Cabang A',
                status: 'AKTIF',
                password: 'budi123',
                pin: '123456',
                nik: '327501009920001',
                dept: 'Operasional Kasir',
                assignedBranches: [
                    { branch: 'Cabang A', status: 'AKTIF', assignedAt: '19 Okt 2026' }
                ]
            },
            {
                id: 'usr_02',
                name: 'Citra Dewi',
                username: 'cashier_citra',
                email: 'citra@pos.com',
                role: 'KASIR',
                branch: 'Cabang A',
                status: 'AKTIF',
                password: 'citra123',
                pin: '123456',
                nik: '327501009920002',
                dept: 'Operasional Kasir',
                assignedBranches: [
                    { branch: 'Cabang A', status: 'AKTIF', assignedAt: '19 Okt 2026' }
                ]
            },
            {
                id: 'usr_03',
                name: 'Ahmad Fauzi',
                username: 'cashier_fauzi',
                email: 'fauzi@pos.com',
                role: 'KASIR',
                branch: 'Cabang B',
                status: 'AKTIF',
                password: 'fauzi123',
                pin: '123456',
                nik: '327501009920003',
                dept: 'Operasional Kasir',
                assignedBranches: [
                    { branch: 'Cabang B', status: 'AKTIF', assignedAt: '19 Okt 2026' }
                ]
            },
            {
                id: 'usr_04',
                name: 'Diana Putri',
                username: 'manager_diana',
                email: 'diana@pos.com',
                role: 'MANAGER',
                branch: 'Cabang A',
                status: 'AKTIF',
                password: 'password',
                pin: '123456',
                assignedBranches: [
                    { branch: 'Cabang A', status: 'AKTIF', assignedAt: '19 Okt 2026' }
                ]
            },
            {
                id: 'usr_05',
                name: 'Hendra Gunawan',
                username: 'owner_hendra',
                email: 'owner@pos.com',
                role: 'OWNER',
                branch: 'Cabang Pusat',
                status: 'AKTIF',
                password: 'owner123',
                pin: '999999',
                assignedBranches: [
                    { branch: 'Cabang Pusat', status: 'AKTIF', assignedAt: '19 Okt 2026' }
                ]
            }
        ];
    },

    // Masking NIK: 3275 •••• •••• 0004
    maskNik(nik) {
        if (!nik || String(nik).length < 8) return nik || '-';
        const clean = String(nik).replace(/\s+/g, '');
        if (clean.length >= 12) {
            return clean.substring(0, 4) + ' •••• •••• ' + clean.substring(clean.length - 4);
        }
        return clean.substring(0, 4) + ' •••• ' + clean.substring(clean.length - 4);
    },

    // Cari User Kasir yang Terhubung dengan Data Member
    findUserByMember(member) {
        if (!member) return null;
        return this.users.find(u => 
            (u.memberId && u.memberId === member.id) ||
            (u.nik && u.nik === member.nik) ||
            (u.role === 'KASIR' && (u.name || '').toLowerCase() === (member.name || '').toLowerCase())
        ) || null;
    },

    // Cek apakah member sudah ditugaskan aktif di cabang tertentu
    isMemberAssignedToBranch(member, branch) {
        const user = this.findUserByMember(member);
        if (!user || !Array.isArray(user.assignedBranches)) return false;
        return user.assignedBranches.some(ab => ab.branch.toLowerCase() === branch.toLowerCase() && ab.status === 'AKTIF');
    },

    // Dapatkan ringkasan penugasan cabang member
    getMemberAssignmentSummary(member) {
        const user = this.findUserByMember(member);
        if (!user || !Array.isArray(user.assignedBranches) || user.assignedBranches.length === 0) {
            return { hasAccount: false, branches: [] };
        }
        return {
            hasAccount: true,
            username: user.username,
            status: user.status,
            branches: user.assignedBranches
        };
    },

    // Autocomplete pencarian member
    get filteredMembersForSearch() {
        if (!this.searchMemberQuery || !this.searchMemberQuery.trim()) return [];
        const q = this.searchMemberQuery.toLowerCase().trim();
        return this.membersList.filter(m => {
            const matchName = (m.name || '').toLowerCase().includes(q);
            const matchNik = (m.nik || '').replace(/\s+/g, '').includes(q);
            const matchDept = (m.dept || '').toLowerCase().includes(q);
            return matchName || matchNik || matchDept;
        }).slice(0, 6);
    },

    // Pemilihan Karyawan dari Data Members
    selectMember(m) {
        if (m.status === 'DIBLOKIR') {
            alert('Perhatian: Status anggota ' + m.name + ' sedang DIBLOKIR di koperasi. Karyawan tidak dapat didaftarkan sebagai Kasir.');
            return;
        }

        this.selectedMember = m;
        this.searchMemberQuery = '';
        this.isMemberDropdownOpen = false;

        const existingUser = this.findUserByMember(m);
        if (existingUser) {
            this.formKasir.username = existingUser.username;
            this.formKasir.password = existingUser.password || 'kasir123';
            this.formKasir.pin = existingUser.pin || '123456';
        } else {
            // Auto generate username & default password unik dari nama depan karyawan
            const firstName = (m.name || '').toLowerCase().trim().split(/\s+/)[0].replace(/[^a-z0-9]/g, '');
            let candidate = 'cashier_' + firstName;
            let counter = 1;
            while (this.users.some(u => u.username === candidate)) {
                candidate = 'cashier_' + firstName + counter;
                counter++;
            }
            this.formKasir.username = candidate;
            this.formKasir.password = firstName + '123';
            this.formKasir.pin = '123456';
        }
    },

    clearSelectedMember() {
        this.selectedMember = null;
        this.formKasir.username = '';
        this.formKasir.password = '';
        this.formKasir.pin = '123456';
        this.showFormKasirPassword = false;
        this.showFormKasirPin = false;
        this.searchMemberQuery = '';
    },

    togglePasswordVisibility(id) {
        this.showPasswordList[id] = !this.showPasswordList[id];
    },

    togglePinVisibility(id) {
        this.showPinList[id] = !this.showPinList[id];
    },

    copyCredential(val, label, name) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(val).then(() => {
                this.triggerToast(`${label} kasir [${name}] (${val}) berhasil disalin!`);
            }).catch(() => {
                prompt(`Salin ${label} Kasir:`, val);
            });
        } else {
            prompt(`Salin ${label} Kasir:`, val);
        }
    },

    generateRandomPassword() {
        const chars = 'abcdefghjkmnpqrstuvwxyz23456789';
        let res = '';
        for (let i = 0; i < 8; i++) {
            res += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        this.formKasir.password = res;
        this.showFormKasirPassword = true;
    },

    generateRandomPin() {
        this.formKasir.pin = String(Math.floor(100000 + Math.random() * 900000));
        this.showFormKasirPin = true;
    },

    // Tambah / Daftarkan Penugasan Kasir
    addKasir() {
        if (!this.selectedMember) {
            alert('Silakan cari dan pilih karyawan dari data Members terlebih dahulu!');
            return;
        }

        if (this.selectedMember.status === 'DIBLOKIR') {
            alert('Karyawan dengan status DIBLOKIR tidak dapat ditugaskan sebagai Kasir!');
            return;
        }

        if (!this.formKasir.branch) {
            alert('Silakan tentukan cabang penugasan kasir!');
            return;
        }

        const targetBranch = this.formKasir.branch;
        let existingUser = this.findUserByMember(this.selectedMember);

        // Kasus 1: Karyawan sudah memiliki akun Kasir
        if (existingUser) {
            const existingAssignment = existingUser.assignedBranches.find(ab => ab.branch.toLowerCase() === targetBranch.toLowerCase());
            if (existingAssignment) {
                if (existingAssignment.status === 'AKTIF') {
                    alert('Karyawan [' + this.selectedMember.name + '] sudah aktif bertugas di ' + targetBranch + '!\nTidak dapat membuat penugasan duplikat pada cabang yang sama.');
                    return;
                } else {
                    // Reaktivasi penugasan yang sebelumnya nonaktif
                    existingAssignment.status = 'AKTIF';
                    this.saveUsers();
                    const timeStr = new Date().toLocaleTimeString('id-ID');
                    this.securityLogs.unshift({
                        time: timeStr,
                        text: `Penugasan Kasir [${this.selectedMember.name}] di ${targetBranch} diaktifkan kembali oleh Manager`
                    });
                    this.saveLogs();
                    this.triggerToast(`Penugasan Kasir ${this.selectedMember.name} di ${targetBranch} berhasil diaktifkan kembali!`);
                    this.clearSelectedMember();
                    return;
                }
            }

            // Tambah penugasan cabang baru ke akun yang sama (tanpa menduplikasi akun atau mengubah PIN)
            const dateStr = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
            existingUser.assignedBranches.push({
                branch: targetBranch,
                status: 'AKTIF',
                assignedAt: dateStr
            });
            existingUser.branch = existingUser.assignedBranches.map(b => b.branch).join(', ');
            this.saveUsers();

            const timeStr = new Date().toLocaleTimeString('id-ID');
            this.securityLogs.unshift({
                time: timeStr,
                text: `Penugasan Kasir [${this.selectedMember.name}] (${existingUser.username}) ditambahkan ke ${targetBranch}`
            });
            this.saveLogs();

            this.triggerToast(`Penugasan ${targetBranch} berhasil ditambahkan ke akun Kasir ${this.selectedMember.name}!`);
            this.clearSelectedMember();
            return;
        }

        // Kasus 2: Karyawan belum memiliki akun Kasir (buat akun baru yang terhubung ke member)
        if (!this.formKasir.username || !this.formKasir.username.trim()) {
            alert('Silakan tentukan username login untuk kasir baru!');
            return;
        }

        const cleanUsername = this.formKasir.username.toLowerCase().replace(/\s+/g, '_').trim();
        if (this.users.some(u => u.username === cleanUsername)) {
            alert(`Username '@${cleanUsername}' sudah terdaftar pada sistem! Silakan gunakan username lain.`);
            return;
        }

        const dateStr = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        const cleanPassword = this.formKasir.password && this.formKasir.password.trim() ? this.formKasir.password.trim() : (cleanUsername.replace('cashier_', '') + '123');
        const newUser = {
            id: 'usr_' + Date.now(),
            memberId: this.selectedMember.id,
            nik: this.selectedMember.nik,
            name: this.selectedMember.name,
            dept: this.selectedMember.dept,
            username: cleanUsername,
            email: cleanUsername + '@pos.com',
            role: 'KASIR',
            status: 'AKTIF',
            password: cleanPassword,
            pin: this.formKasir.pin || '123456',
            branch: targetBranch,
            assignedBranches: [
                { branch: targetBranch, status: 'AKTIF', assignedAt: dateStr }
            ]
        };

        this.users.unshift(newUser);
        this.saveUsers();

        const timeStr = new Date().toLocaleTimeString('id-ID');
        this.securityLogs.unshift({
            time: timeStr,
            text: `Kasir [${newUser.name}] (${newUser.username}) didaftarkan dari Anggota ke ${targetBranch}`
        });
        this.saveLogs();

        this.triggerToast(`Kasir ${newUser.name} berhasil didaftarkan di ${targetBranch}!`);
        this.clearSelectedMember();
    },

    // Registrasi Manager Cabang (Akses Owner)
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
            pin: this.formManager.pin || '123456',
            assignedBranches: [
                { branch: this.formManager.branch, status: 'AKTIF', assignedAt: '28 Okt 2026' }
            ]
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

    // Filter Daftar Akun Kasir
    get filteredCashiers() {
        return this.users.filter(u => {
            if (u.role !== 'KASIR') return false;
            
            // Filter Cabang
            if (this.branchFilter !== 'all') {
                const hasBranch = Array.isArray(u.assignedBranches) && u.assignedBranches.some(b => b.branch === this.branchFilter);
                if (!hasBranch && u.branch !== this.branchFilter) return false;
            }

            // Filter Status Akun
            if (this.statusFilter !== 'all' && u.status !== this.statusFilter) return false;

            // Pencarian
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                const matchName = (u.name || '').toLowerCase().includes(q);
                const matchUsername = (u.username || '').toLowerCase().includes(q);
                const matchNik = (u.nik || '').includes(q);
                const matchBranch = (u.branch || '').toLowerCase().includes(q) ||
                                    (Array.isArray(u.assignedBranches) && u.assignedBranches.some(b => b.branch.toLowerCase().includes(q)));
                return matchName || matchUsername || matchNik || matchBranch;
            }
            return true;
        });
    },

    // Filter Semua Pengguna (Sistem)
    get filteredUsers() {
        return this.users.filter(u => {
            if (this.roleFilter !== 'all' && u.role !== this.roleFilter) return false;
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                return (u.name || '').toLowerCase().includes(q) ||
                       (u.username || '').toLowerCase().includes(q) ||
                       (u.branch || '').toLowerCase().includes(q) ||
                       (u.role || '').toLowerCase().includes(q);
            }
            return true;
        });
    },

    // Modal Kelola Penugasan Cabang Kasir
    openManageAssignments(user) {
        this.managingUser = JSON.parse(JSON.stringify(user));
        // Cari opsi cabang yang belum dimiliki oleh kasir ini
        const currentBranches = this.managingUser.assignedBranches.map(b => b.branch);
        const allBranches = ['Pusat', 'Cabang A', 'Cabang B'];
        const remaining = allBranches.filter(b => !currentBranches.includes(b));
        this.modalNewBranch = remaining.length > 0 ? remaining[0] : 'Cabang A';
        this.showManageAssignmentModal = true;
    },

    // Toggle status penugasan cabang (Aktif / Nonaktif)
    toggleBranchAssignment(branchName) {
        if (!this.managingUser) return;
        const assignment = this.managingUser.assignedBranches.find(b => b.branch === branchName);
        if (assignment) {
            assignment.status = assignment.status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
            this.syncManagingUserToState();
            this.triggerToast(`Penugasan ${branchName} diubah menjadi ${assignment.status}!`);
        }
    },

    // Tambah penugasan cabang baru ke kasir yang sedang dikelola
    addBranchToManagingUser() {
        if (!this.managingUser) return;
        const exists = this.managingUser.assignedBranches.some(b => b.branch === this.modalNewBranch);
        if (exists) {
            alert(`Kasir sudah memiliki penugasan di ${this.modalNewBranch}!`);
            return;
        }

        const dateStr = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        this.managingUser.assignedBranches.push({
            branch: this.modalNewBranch,
            status: 'AKTIF',
            assignedAt: dateStr
        });
        this.managingUser.branch = this.managingUser.assignedBranches.map(b => b.branch).join(', ');
        this.syncManagingUserToState();

        const timeStr = new Date().toLocaleTimeString('id-ID');
        this.securityLogs.unshift({
            time: timeStr,
            text: `Penugasan [${this.managingUser.name}] ditambahkan ke ${this.modalNewBranch}`
        });
        this.saveLogs();

        this.triggerToast(`Penugasan ke ${this.modalNewBranch} berhasil ditambahkan!`);
    },

    // Hapus penugasan dari suatu cabang
    removeBranchFromManagingUser(branchName) {
        if (!this.managingUser) return;
        if (this.managingUser.assignedBranches.length <= 1) {
            alert('Tidak dapat menghapus penugasan cabang terakhir.\nJika kasir dinonaktifkan sepenuhnya, ubah status akun menjadi NONAKTIF.');
            return;
        }

        this.managingUser.assignedBranches = this.managingUser.assignedBranches.filter(b => b.branch !== branchName);
        this.managingUser.branch = this.managingUser.assignedBranches.map(b => b.branch).join(', ');
        this.syncManagingUserToState();

        const timeStr = new Date().toLocaleTimeString('id-ID');
        this.securityLogs.unshift({
            time: timeStr,
            text: `Penugasan [${this.managingUser.name}] di ${branchName} dicabut oleh Manager`
        });
        this.saveLogs();

        this.triggerToast(`Penugasan ${branchName} berhasil dicabut!`);
    },

    syncManagingUserToState() {
        const idx = this.users.findIndex(u => u.id === this.managingUser.id);
        if (idx !== -1) {
            this.users[idx] = JSON.parse(JSON.stringify(this.managingUser));
            this.saveUsers();
        }
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
            alert('Ketik \'hapus\' untuk konfirmasi penghapusan user!');
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
            <div class="flex items-center gap-3">
                <span class="material-symbols-rounded text-[#0052cc] text-[26px]">manage_accounts</span>
                <div>
                    <h1 class="text-[18px] font-bold text-gray-900 tracking-tight">Kelola Akun & Penugasan Kasir</h1>
                    <p class="text-[12px] text-gray-500 font-medium">Otoritas Manager: Manajemen staf kasir cabang berbasis data Anggota (Members)</p>
                </div>
            </div>

            <div class="flex items-center gap-5 text-gray-600">
                <button type="button" @click="initUsers(); triggerToast('Data pengguna & penugasan disinkronkan!')" class="hover:text-gray-900 transition-colors p-1.5 rounded-lg hover:bg-gray-100 cursor-pointer" title="Sinkronisasi Data">
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
        <div class="flex-1 overflow-y-auto p-6 lg:p-8 space-y-7">
            <div class="max-w-[1400px] mx-auto space-y-7">

                <!-- 1. FORM PENUGASAN KASIR BARU DARI MEMBERS (AKSES MANAGER) -->
                <section class="bg-white border border-gray-200/90 rounded-2xl p-6 lg:p-7 shadow-xs">
                    <div class="flex flex-wrap items-start justify-between gap-3 mb-6 pb-4 border-b border-gray-100">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-rounded text-[#0052cc] text-[22px]">assignment_ind</span>
                                <h2 class="text-[17px] font-bold text-[#0052cc] tracking-tight">Tambah Kasir Baru</h2>
                            </div>
                            <p class="text-[13px] text-gray-500 mt-0.5">
                                Pilih karyawan dari database <strong>Members</strong>, tentukan cabang penugasan, dan daftarkan akses Kasir tanpa membuat ulang identitas.
                            </p>
                        </div>
                        <span class="bg-[#dbeafe] text-[#0052cc] text-[10.5px] font-extrabold px-3.5 py-1 rounded-full tracking-wider uppercase shrink-0">
                            AKSES MANAGER
                        </span>
                    </div>

                    <form @submit.prevent="addKasir()" class="space-y-6">
                        
                        <!-- ALUR 1: PILIH CABANG PENUGASAN -->
                        <div>
                            <label class="block font-bold text-gray-700 text-xs tracking-wider uppercase mb-2.5">
                                1. Pilih Cabang Penugasan <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <!-- Pusat -->
                                <button type="button"
                                        @click="formKasir.branch = 'Pusat'"
                                        :class="formKasir.branch === 'Pusat' ? 'border-[#0052cc] bg-blue-50/70 text-[#0052cc] font-bold shadow-xs' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                                        class="p-3.5 rounded-xl border flex items-center gap-3 transition-all cursor-pointer text-left">
                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
                                         :class="formKasir.branch === 'Pusat' ? 'bg-[#0052cc] text-white' : 'bg-gray-100 text-gray-500'">
                                        <span class="material-symbols-rounded text-[20px]">corporate_fare</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[13px] leading-tight">Pusat</div>
                                        <div class="text-[11px] text-gray-500 truncate mt-0.5">Minimarket Pusat</div>
                                    </div>
                                </button>

                                <!-- Cabang A -->
                                <button type="button"
                                        @click="formKasir.branch = 'Cabang A'"
                                        :class="formKasir.branch === 'Cabang A' ? 'border-[#0052cc] bg-blue-50/70 text-[#0052cc] font-bold shadow-xs' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                                        class="p-3.5 rounded-xl border flex items-center gap-3 transition-all cursor-pointer text-left">
                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
                                         :class="formKasir.branch === 'Cabang A' ? 'bg-[#0052cc] text-white' : 'bg-gray-100 text-gray-500'">
                                        <span class="material-symbols-rounded text-[20px]">store</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[13px] leading-tight">Cabang A</div>
                                        <div class="text-[11px] text-gray-500 truncate mt-0.5">Gerai Utama</div>
                                    </div>
                                </button>

                                <!-- Cabang B -->
                                <button type="button"
                                        @click="formKasir.branch = 'Cabang B'"
                                        :class="formKasir.branch === 'Cabang B' ? 'border-[#0052cc] bg-blue-50/70 text-[#0052cc] font-bold shadow-xs' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                                        class="p-3.5 rounded-xl border flex items-center gap-3 transition-all cursor-pointer text-left">
                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
                                         :class="formKasir.branch === 'Cabang B' ? 'bg-[#0052cc] text-white' : 'bg-gray-100 text-gray-500'">
                                        <span class="material-symbols-rounded text-[20px]">storefront</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[13px] leading-tight">Cabang B</div>
                                        <div class="text-[11px] text-gray-500 truncate mt-0.5">Gerai Sekunder</div>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- ALUR 2 & 3: PENCARIAN & IDENTITAS KARYAWAN DARI MEMBERS -->
                        <div>
                            <label class="block font-bold text-gray-700 text-xs tracking-wider uppercase mb-2">
                                2. Cari &amp; Pilih Karyawan (Sumber: Database Members) <span class="text-red-500">*</span>
                            </label>

                            <!-- Jika Belum Ada Karyawan Terpilih: Tampilkan Kotak Pencarian -->
                            <div x-show="!selectedMember" class="relative">
                                <div class="relative">
                                    <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                                    <input type="text"
                                           x-model="searchMemberQuery"
                                           @focus="isMemberDropdownOpen = true"
                                           placeholder="Ketik nama karyawan, NIK (misal: 3275...), atau departemen..."
                                           class="w-full pl-11 pr-4 py-3 rounded-xl border border-gray-200 bg-white text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 transition-all">
                                    <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-[11px] font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded">
                                        Data Members
                                    </span>
                                </div>

                                <!-- Dropdown Hasil Pencarian Karyawan -->
                                <div x-show="isMemberDropdownOpen && filteredMembersForSearch.length > 0"
                                     @click.away="isMemberDropdownOpen = false"
                                     class="absolute left-0 right-0 top-full mt-1.5 bg-white border border-gray-200 rounded-xl shadow-lg z-30 divide-y divide-gray-100 max-h-72 overflow-y-auto">
                                    <template x-for="m in filteredMembersForSearch" :key="m.id">
                                        <div @click="selectMember(m)"
                                             class="p-3.5 hover:bg-blue-50/60 flex items-center justify-between gap-3 cursor-pointer transition-colors"
                                             :class="m.status === 'DIBLOKIR' ? 'opacity-60 bg-red-50/30 cursor-not-allowed' : ''">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <div class="w-9 h-9 rounded-full bg-blue-100 text-[#0052cc] flex items-center justify-center font-bold text-xs shrink-0">
                                                    <span x-text="m.name.charAt(0)"></span>
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-bold text-gray-900 text-xs truncate" x-text="m.name"></span>
                                                        <span class="font-mono text-[10.5px] text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded" x-text="maskNik(m.nik)"></span>
                                                    </div>
                                                    <p class="text-[11px] text-gray-500 truncate" x-text="m.dept + ' • ID: ' + m.id"></p>
                                                </div>
                                            </div>

                                            <!-- Indikator Penugasan pada Cabang Terpilih -->
                                            <div class="shrink-0 text-right">
                                                <template x-if="m.status === 'DIBLOKIR'">
                                                    <span class="text-[10px] font-bold bg-red-100 text-red-700 px-2 py-0.5 rounded-full">Anggota Diblokir</span>
                                                </template>
                                                <template x-if="m.status !== 'DIBLOKIR'">
                                                    <div>
                                                        <template x-if="isMemberAssignedToBranch(m, formKasir.branch)">
                                                            <span class="text-[10.5px] font-bold bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full">
                                                                Sudah di <span x-text="formKasir.branch"></span>
                                                            </span>
                                                        </template>
                                                        <template x-if="!isMemberAssignedToBranch(m, formKasir.branch)">
                                                            <span class="text-[10.5px] font-bold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full">
                                                                Tersedia
                                                            </span>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Identitas Karyawan Terpilih (Read-Only) -->
                            <div x-show="selectedMember" class="p-4 rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50/80 via-white to-blue-50/40 relative">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div class="flex items-center gap-3.5 min-w-0">
                                        <div class="w-11 h-11 rounded-full bg-[#0052cc] text-white flex items-center justify-center font-bold text-sm shadow-xs shrink-0">
                                            <span x-text="selectedMember?.name ? selectedMember.name.charAt(0) : ''"></span>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <h3 class="text-sm font-bold text-gray-900" x-text="selectedMember?.name"></h3>
                                                <span class="inline-flex items-center gap-1 text-[10px] font-extrabold bg-blue-100 text-[#0052cc] px-2 py-0.5 rounded-full">
                                                    <span class="material-symbols-rounded text-[12px]">lock</span>
                                                    Identitas Members
                                                </span>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-gray-600 mt-0.5">
                                                <span>NIK: <strong class="font-mono text-gray-800" x-text="maskNik(selectedMember?.nik)"></strong></span>
                                                <span>•</span>
                                                <span x-text="selectedMember?.dept"></span>
                                                <span>•</span>
                                                <span class="font-semibold text-emerald-700">Status: <span x-text="selectedMember?.status"></span></span>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="button"
                                            @click="clearSelectedMember()"
                                            class="text-xs font-bold text-gray-500 hover:text-red-600 hover:bg-white px-3 py-1.5 rounded-lg border border-gray-200 transition-colors inline-flex items-center gap-1.5 cursor-pointer">
                                        <span class="material-symbols-rounded text-[16px]">close</span>
                                        Ganti Karyawan
                                    </button>
                                </div>

                                <!-- Banner Deteksi Akun Existing -->
                                <template x-if="selectedMember && findUserByMember(selectedMember)">
                                    <div class="mt-3 pt-3 border-t border-blue-200/70 flex items-center gap-2.5 text-xs text-[#0052cc] bg-white/70 p-2.5 rounded-lg">
                                        <span class="material-symbols-rounded text-[18px]">info</span>
                                        <span>
                                            Karyawan ini telah memiliki akun Kasir aktif (<strong>@<span x-text="findUserByMember(selectedMember)?.username"></span></strong>). 
                                            Penugasan ke <strong x-text="formKasir.branch"></strong> akan ditambahkan ke akun yang sama tanpa mengubah PIN.
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- ALUR 4: KREDENSIAL SISTEM & VALIDASI (PASSWORD LOGIN & PIN KASIR DIPISAHKAN) -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 text-xs pt-1">
                            <!-- 1. Username Sistem -->
                            <div>
                                <label class="block font-bold text-gray-700 tracking-wider uppercase mb-1.5 text-[11px]">
                                    Username Sistem <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 font-mono">@</span>
                                    <input type="text"
                                           x-model="formKasir.username"
                                           :readonly="selectedMember && findUserByMember(selectedMember)"
                                           :class="selectedMember && findUserByMember(selectedMember) ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white text-gray-800 focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15'"
                                           placeholder="cashier_nama"
                                           required
                                           class="w-full pl-8 pr-4 py-2.5 rounded-xl border border-gray-200 font-mono transition-all">
                                </div>
                                <p class="text-[11px] text-gray-400 mt-1">Dibuat unik dari identitas karyawan.</p>
                            </div>

                            <!-- 2. Password Akun Login Portal Kasir -->
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block font-bold text-gray-700 tracking-wider uppercase text-[11px]">
                                        Password Portal Kasir <span class="text-red-500">*</span>
                                    </label>
                                    <button type="button" 
                                            @click="generateRandomPassword()" 
                                            x-show="!selectedMember || !findUserByMember(selectedMember)"
                                            class="text-[11px] text-[#0052cc] hover:underline font-semibold cursor-pointer inline-flex items-center gap-1">
                                        <span class="material-symbols-rounded text-[14px]">shuffle</span>
                                        <span>Acak Sandi</span>
                                    </button>
                                </div>
                                <div class="relative flex items-center">
                                    <span class="material-symbols-rounded absolute left-3 text-gray-400 text-[18px]">key</span>
                                    <input :type="showFormKasirPassword ? 'text' : 'password'"
                                           x-model="formKasir.password"
                                           :readonly="selectedMember && findUserByMember(selectedMember)"
                                           :class="selectedMember && findUserByMember(selectedMember) ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white text-gray-800 focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15'"
                                           placeholder="password_kasir"
                                           required
                                           class="w-full pl-9 pr-10 py-2.5 rounded-xl border border-gray-200 font-mono font-bold text-sm transition-all">
                                    <button type="button" 
                                            @click="showFormKasirPassword = !showFormKasirPassword" 
                                            class="absolute right-3 text-gray-400 hover:text-gray-600 cursor-pointer p-1"
                                            :title="showFormKasirPassword ? 'Sembunyikan' : 'Lihat'">
                                        <span class="material-symbols-rounded text-[18px]" x-text="showFormKasirPassword ? 'visibility_off' : 'visibility'"></span>
                                    </button>
                                </div>
                                <p class="text-[11px] text-gray-400 mt-1" x-text="selectedMember && findUserByMember(selectedMember) ? 'Menggunakan Password portal akun yang sudah aktif.' : 'Untuk autentikasi masuk ke Portal Web Koperasi.'"></p>
                            </div>

                            <!-- 3. PIN Kasir Terminal POS (6 Digit Angka) -->
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block font-bold text-gray-700 tracking-wider uppercase text-[11px]">
                                        PIN Kasir POS (6 Digit) <span class="text-red-500">*</span>
                                    </label>
                                    <button type="button" 
                                            @click="generateRandomPin()" 
                                            x-show="!selectedMember || !findUserByMember(selectedMember)"
                                            class="text-[11px] text-[#0052cc] hover:underline font-semibold cursor-pointer inline-flex items-center gap-1">
                                        <span class="material-symbols-rounded text-[14px]">dialpad</span>
                                        <span>Acak PIN</span>
                                    </button>
                                </div>
                                <div class="relative flex items-center">
                                    <span class="material-symbols-rounded absolute left-3 text-gray-400 text-[18px]">lock</span>
                                    <input :type="showFormKasirPin ? 'text' : 'password'"
                                           x-model="formKasir.pin"
                                           maxlength="8"
                                           :readonly="selectedMember && findUserByMember(selectedMember)"
                                           :class="selectedMember && findUserByMember(selectedMember) ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white text-gray-800 focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15'"
                                           placeholder="123456"
                                           required
                                           class="w-full pl-9 pr-10 py-2.5 rounded-xl border border-gray-200 font-mono tracking-widest font-bold text-sm transition-all">
                                    <button type="button" 
                                            @click="showFormKasirPin = !showFormKasirPin" 
                                            class="absolute right-3 text-gray-400 hover:text-gray-600 cursor-pointer p-1"
                                            :title="showFormKasirPin ? 'Sembunyikan' : 'Lihat'">
                                        <span class="material-symbols-rounded text-[18px]" x-text="showFormKasirPin ? 'visibility_off' : 'visibility'"></span>
                                    </button>
                                </div>
                                <p class="text-[11px] text-gray-400 mt-1" x-text="selectedMember && findUserByMember(selectedMember) ? 'PIN keamanan akun kasir yang sudah ada.' : 'PIN untuk buka sesi shift, switch operator & terminal POS.'"></p>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-2 flex justify-end">
                            <button type="submit"
                                    class="bg-[#0052cc] hover:bg-[#0043a8] active:scale-[0.99] text-white px-6 py-2.5 rounded-xl font-bold text-xs inline-flex items-center gap-2 shadow-xs transition-all cursor-pointer">
                                <span class="material-symbols-rounded text-[18px]">how_to_reg</span>
                                <span>Daftarkan Penugasan Kasir</span>
                            </button>
                        </div>
                    </form>
                </section>

                <!-- 2. TABEL AKUN KASIR & PENUGASAN MULTI-CABANG (TAHAP 5) -->
                <section class="bg-white border border-gray-200/90 rounded-2xl p-6 lg:p-7 shadow-xs space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-4 pb-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-rounded text-[#0052cc] text-[22px]">badge</span>
                                <h2 class="text-[17px] font-bold text-gray-900 tracking-tight">Daftar Akun Kasir &amp; Penugasan Cabang</h2>
                            </div>
                            <p class="text-[13px] text-gray-500 mt-0.5">
                                Karyawan dengan hak akses Kasir, penugasan per-cabang (Pusat, Cabang A, Cabang B), dan status penugasan.
                            </p>
                        </div>

                        <!-- Toolbar: Filter Cabang & Search -->
                        <div class="flex flex-wrap items-center gap-2.5">
                            <!-- Filter Cabang Pills -->
                            <div class="inline-flex bg-gray-100 p-1 rounded-xl text-xs font-semibold text-gray-600">
                                <button type="button" @click="branchFilter = 'all'" :class="branchFilter === 'all' ? 'bg-white text-gray-900 shadow-2xs font-bold' : 'text-gray-500'" class="px-3 py-1 rounded-lg cursor-pointer">Semua Cabang</button>
                                <button type="button" @click="branchFilter = 'Pusat'" :class="branchFilter === 'Pusat' ? 'bg-white text-[#0052cc] shadow-2xs font-bold' : 'text-gray-500'" class="px-3 py-1 rounded-lg cursor-pointer">Pusat</button>
                                <button type="button" @click="branchFilter = 'Cabang A'" :class="branchFilter === 'Cabang A' ? 'bg-white text-[#0052cc] shadow-2xs font-bold' : 'text-gray-500'" class="px-3 py-1 rounded-lg cursor-pointer">Cabang A</button>
                                <button type="button" @click="branchFilter = 'Cabang B'" :class="branchFilter === 'Cabang B' ? 'bg-white text-[#0052cc] shadow-2xs font-bold' : 'text-gray-500'" class="px-3 py-1 rounded-lg cursor-pointer">Cabang B</button>
                            </div>

                            <!-- Search Input -->
                            <div class="relative w-64">
                                <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                                <input type="text"
                                       x-model="searchQuery"
                                       placeholder="Cari nama, NIK, username..."
                                       class="w-full pl-9 pr-3 py-1.5 rounded-xl border border-gray-200 text-xs focus:outline-none focus:border-[#0052cc]">
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Data Kasir -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden bg-white shadow-2xs">
                        <div class="overflow-x-auto min-w-0 w-full">
                            <table class="w-full text-left text-xs font-sans min-w-[1100px] border-collapse">
                                <thead class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold tracking-wider uppercase text-[11px] whitespace-nowrap">
                                    <tr>
                                        <th class="py-3 px-4">Karyawan (Members)</th>
                                        <th class="py-3 px-4">NIK (Masked)</th>
                                        <th class="py-3 px-4">Username Sistem</th>
                                        <th class="py-3 px-4">Password Portal</th>
                                        <th class="py-3 px-4">PIN Kasir (POS)</th>
                                        <th class="py-3 px-4">Cabang Penugasan</th>
                                        <th class="py-3 px-4 text-center">Status Akun</th>
                                        <th class="py-3 px-4 text-center">Aksi Penugasan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="u in filteredCashiers" :key="u.id">
                                        <tr class="hover:bg-blue-50/30 transition-colors">
                                            <!-- Nama & Departemen -->
                                            <td class="py-3.5 px-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 rounded-full bg-blue-100 text-[#0052cc] font-bold text-xs flex items-center justify-center shrink-0">
                                                        <span x-text="u.name ? u.name.charAt(0) : 'K'"></span>
                                                    </div>
                                                    <div>
                                                        <div class="font-bold text-gray-900 text-xs" x-text="u.name"></div>
                                                        <div class="text-[11px] text-gray-500 truncate" x-text="u.dept || 'Staf Operasional'"></div>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- NIK Dimasking -->
                                            <td class="py-3.5 px-4 whitespace-nowrap">
                                                <span class="font-mono text-[11px] bg-gray-100 text-gray-700 px-2.5 py-1 rounded-lg font-medium" x-text="maskNik(u.nik)"></span>
                                            </td>

                                            <!-- Username -->
                                            <td class="py-3.5 px-4 font-mono text-gray-700 font-semibold whitespace-nowrap" x-text="'@' + u.username"></td>

                                            <!-- Password Portal (Login Web) -->
                                            <td class="py-3.5 px-4 font-mono whitespace-nowrap">
                                                <div class="inline-flex items-center gap-1.5 bg-slate-50 hover:bg-slate-100/90 px-2.5 py-1 rounded-lg border border-slate-200 text-slate-700 transition-colors">
                                                    <span class="material-symbols-rounded text-slate-400 text-[14px]">key</span>
                                                    <span class="font-bold text-xs tracking-wide text-slate-800 select-all"
                                                          x-text="showPasswordList[u.id] ? (u.password || 'kasir123') : '••••••••'"></span>
                                                    <button type="button"
                                                            @click="togglePasswordVisibility(u.id)"
                                                            class="text-slate-400 hover:text-slate-700 p-0.5 cursor-pointer ml-0.5"
                                                            :title="showPasswordList[u.id] ? 'Sembunyikan Password' : 'Lihat Password'">
                                                        <span class="material-symbols-rounded text-[15px]" x-text="showPasswordList[u.id] ? 'visibility_off' : 'visibility'"></span>
                                                    </button>
                                                    <button type="button"
                                                            @click="copyCredential(u.password || 'kasir123', 'Password Portal', u.name)"
                                                            class="text-slate-400 hover:text-[#0052cc] p-0.5 cursor-pointer"
                                                            title="Salin Password Portal">
                                                        <span class="material-symbols-rounded text-[15px]">content_copy</span>
                                                    </button>
                                                </div>
                                            </td>

                                            <!-- PIN Kasir (Terminal POS) -->
                                            <td class="py-3.5 px-4 font-mono whitespace-nowrap">
                                                <div class="inline-flex items-center gap-1.5 bg-blue-50/70 hover:bg-blue-100/80 px-2.5 py-1 rounded-lg border border-blue-200 text-blue-900 transition-colors">
                                                    <span class="material-symbols-rounded text-blue-500 text-[14px]">lock</span>
                                                    <span class="font-bold text-xs tracking-wider text-blue-950 select-all"
                                                          x-text="showPinList[u.id] ? (u.pin || '123456') : '••••••'"></span>
                                                    <button type="button"
                                                            @click="togglePinVisibility(u.id)"
                                                            class="text-blue-400 hover:text-blue-800 p-0.5 cursor-pointer ml-0.5"
                                                            :title="showPinList[u.id] ? 'Sembunyikan PIN' : 'Lihat PIN'">
                                                        <span class="material-symbols-rounded text-[15px]" x-text="showPinList[u.id] ? 'visibility_off' : 'visibility'"></span>
                                                    </button>
                                                    <button type="button"
                                                            @click="copyCredential(u.pin || '123456', 'PIN Kasir POS', u.name)"
                                                            class="text-blue-400 hover:text-blue-800 p-0.5 cursor-pointer"
                                                            title="Salin PIN Kasir POS">
                                                        <span class="material-symbols-rounded text-[15px]">content_copy</span>
                                                    </button>
                                                </div>
                                            </td>

                                            <!-- Cabang Penugasan (Multiple Tags dalam 1 Baris) -->
                                            <td class="py-3.5 px-4 whitespace-nowrap">
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <template x-for="ab in (u.assignedBranches || [{ branch: u.branch, status: u.status }])" :key="ab.branch">
                                                        <span class="inline-flex items-center gap-1.5 text-[10.5px] font-bold px-2.5 py-0.5 rounded-full"
                                                              :class="{
                                                                  'bg-blue-100 text-blue-800': ab.status === 'AKTIF' && ab.branch.includes('Pusat'),
                                                                  'bg-emerald-100 text-emerald-800': ab.status === 'AKTIF' && ab.branch.includes('Cabang A'),
                                                                  'bg-amber-100 text-amber-800': ab.status === 'AKTIF' && ab.branch.includes('Cabang B'),
                                                                  'bg-gray-100 text-gray-400 line-through': ab.status !== 'AKTIF'
                                                              }">
                                                            <span class="w-1.5 h-1.5 rounded-full"
                                                                  :class="ab.status === 'AKTIF' ? 'bg-current' : 'bg-gray-400'"></span>
                                                            <span x-text="ab.branch"></span>
                                                        </span>
                                                    </template>
                                                </div>
                                            </td>

                                            <!-- Status Akun Global -->
                                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                                <span class="text-[10px] font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-wider"
                                                      :class="u.status === 'AKTIF' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                                                      x-text="u.status"></span>
                                            </td>

                                            <!-- Aksi -->
                                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                                <div class="inline-flex items-center justify-center gap-1">
                                                    <!-- Kelola Penugasan Cabang -->
                                                    <button type="button"
                                                            @click="openManageAssignments(u)"
                                                            title="Kelola Penugasan Cabang"
                                                            class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors cursor-pointer font-bold inline-flex items-center gap-1 text-[11px]">
                                                        <span class="material-symbols-rounded text-[18px]">edit_location_alt</span>
                                                        <span>Kelola Cabang</span>
                                                    </button>

                                                    <!-- Edit Akun & PIN -->
                                                    <button type="button"
                                                            @click="openEdit(u)"
                                                            title="Edit Data & PIN Akun"
                                                            class="p-1.5 rounded-lg text-gray-500 hover:text-[#0052cc] hover:bg-blue-50 transition-colors cursor-pointer">
                                                        <span class="material-symbols-rounded text-[18px]">edit</span>
                                                    </button>

                                                    <!-- Toggle Status Akun -->
                                                    <button type="button"
                                                            @click="toggleStatus(u)"
                                                            :title="u.status === 'AKTIF' ? 'Nonaktifkan Akun' : 'Aktifkan Akun'"
                                                            class="p-1.5 rounded-lg text-gray-500 hover:text-amber-600 hover:bg-amber-50 transition-colors cursor-pointer">
                                                        <span class="material-symbols-rounded text-[18px]" x-text="u.status === 'AKTIF' ? 'block' : 'check_circle'"></span>
                                                    </button>

                                                    <!-- Hapus Akun -->
                                                    <button type="button"
                                                            @click="openDelete(u)"
                                                            title="Hapus Akun"
                                                            class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors cursor-pointer">
                                                        <span class="material-symbols-rounded text-[18px]">delete</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="filteredCashiers.length === 0">
                                        <td colspan="8" class="py-10 text-center text-gray-400 font-medium">
                                            <div class="flex flex-col items-center justify-center gap-2">
                                                <span class="material-symbols-rounded text-3xl text-gray-300">person_off</span>
                                                <span>Tidak ada data kasir yang sesuai dengan filter</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- 3. SECTION BAWAH: REGISTRASI MANAGER CABANG & AUDIT KEAMANAN -->
                <div class="grid grid-cols-12 gap-6 items-start">
                    
                    <!-- Card: Registrasi Manager Cabang (Akses Owner) -->
                    <div class="col-span-12 lg:col-span-7">
                        <section class="bg-white border border-gray-200/90 border-l-[3.5px] border-l-[#9a3412] rounded-2xl p-6 shadow-xs">
                            <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
                                <div>
                                    <h2 class="text-[16px] font-bold text-[#9a3412] tracking-tight">Registrasi Manager Cabang</h2>
                                    <p class="text-[12px] text-gray-500 mt-0.5">Otoritas Owner: Penugasan level manajemen tinggi gerai.</p>
                                </div>
                                <span class="bg-[#ffedd5] text-[#9a3412] text-[10px] font-extrabold px-3 py-0.5 rounded-full tracking-wider uppercase shrink-0">
                                    AKSES OWNER
                                </span>
                            </div>

                            <form @submit.prevent="addManager()" class="space-y-4 text-xs">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block font-bold text-gray-600 uppercase mb-1">NAMA LENGKAP MANAGER <span class="text-red-500">*</span></label>
                                        <input type="text"
                                               x-model="formManager.name"
                                               placeholder="Contoh: Siti Aminah"
                                               required
                                               class="w-full px-3.5 py-2 rounded-xl border border-gray-200 focus:outline-none focus:border-[#9a3412]">
                                    </div>
                                    <div>
                                        <label class="block font-bold text-gray-600 uppercase mb-1">EMAIL PROFESIONAL <span class="text-red-500">*</span></label>
                                        <input type="email"
                                               x-model="formManager.email"
                                               placeholder="manager.siti@pos.com"
                                               required
                                               class="w-full px-3.5 py-2 rounded-xl border border-gray-200 focus:outline-none focus:border-[#9a3412]">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block font-bold text-gray-600 uppercase mb-1">LEVEL AKSES</label>
                                        <div class="grid grid-cols-2 gap-2">
                                            <button type="button" @click="formManager.accessLevel = 'full'"
                                                    :class="formManager.accessLevel === 'full' ? 'bg-[#fff7ed] border-[#9a3412] text-[#9a3412] font-bold' : 'bg-white border-gray-200 text-gray-500'"
                                                    class="py-2 rounded-xl border text-center cursor-pointer">Full Access</button>
                                            <button type="button" @click="formManager.accessLevel = 'reporting'"
                                                    :class="formManager.accessLevel === 'reporting' ? 'bg-[#fff7ed] border-[#9a3412] text-[#9a3412] font-bold' : 'bg-white border-gray-200 text-gray-500'"
                                                    class="py-2 rounded-xl border text-center cursor-pointer">Reporting Only</button>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block font-bold text-gray-600 uppercase mb-1">CABANG PENUGASAN</label>
                                        <select x-model="formManager.branch"
                                                class="w-full px-3.5 py-2 rounded-xl border border-gray-200 bg-white">
                                            <option value="Cabang Pusat">Cabang Pusat</option>
                                            <option value="Cabang A">Cabang A (Gerai Utama)</option>
                                            <option value="Cabang B">Cabang B</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="pt-2 flex justify-end">
                                    <button type="submit" class="bg-[#9a3412] hover:bg-[#7c2d12] text-white px-5 py-2 rounded-xl font-bold text-xs inline-flex items-center gap-1.5 cursor-pointer">
                                        <span class="material-symbols-rounded text-[18px]">admin_panel_settings</span>
                                        <span>Registrasikan Manager</span>
                                    </button>
                                </div>
                            </form>
                        </section>
                    </div>

                    <!-- Card: Catatan Keamanan Sistem -->
                    <div class="col-span-12 lg:col-span-5">
                        <section class="bg-gradient-to-r from-[#1e242b] via-[#1b2535] to-[#112340] text-white rounded-2xl p-6 shadow-md h-full flex flex-col justify-between">
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="material-symbols-rounded text-[20px] text-gray-200">security</span>
                                    <h3 class="text-sm font-bold tracking-tight">Audit Log Keamanan Sistem</h3>
                                </div>
                                <p class="text-xs text-gray-300 leading-relaxed mb-4">
                                    Aktivitas penugasan kasir, perubahan cabang, dan penambahan akun terdata secara otomatis.
                                </p>
                                <div class="space-y-2 text-xs">
                                    <template x-for="(log, idx) in securityLogs.slice(0, 4)" :key="idx">
                                        <div class="flex items-baseline gap-2.5">
                                            <span class="font-mono text-gray-400 text-[11px] shrink-0" x-text="log.time"></span>
                                            <span class="text-gray-300 text-[11.5px]" x-text="log.text"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div class="pt-4 border-t border-gray-700/50 mt-4 flex items-center justify-between text-[11px] text-gray-400">
                                <span>Status POS: Online</span>
                                <span>Enkripsi Koperasi ILP v2.4</span>
                            </div>
                        </section>
                    </div>

                </div>

            </div>
        </div>

    </main>

    <!-- MODAL KELOLA PENUGASAN CABANG KASIR (MULTI-CABANG) -->
    <template x-teleport="body">
        <div x-show="showManageAssignmentModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div @click.away="showManageAssignmentModal = false"
                 class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-lg overflow-hidden animate-scale-up">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-blue-50/60">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-rounded text-[#0052cc] text-[22px]">domain_add</span>
                        <div>
                            <h3 class="font-bold text-gray-900 text-sm">Kelola Penugasan Cabang Kasir</h3>
                            <p class="text-[11px] text-gray-500 font-medium" x-text="managingUser?.name + ' (@' + managingUser?.username + ')'"></p>
                        </div>
                    </div>
                    <button @click="showManageAssignmentModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <template x-if="managingUser">
                    <div class="p-6 space-y-5 text-xs font-sans">
                        
                        <!-- Informasi Karyawan & NIK Masked & PIN -->
                        <div class="p-3.5 bg-gray-50 rounded-xl border border-gray-200/80 flex items-center justify-between gap-3">
                            <div>
                                <span class="text-[11px] text-gray-500 uppercase font-bold tracking-wider block">Identitas Karyawan (Members)</span>
                                <span class="font-bold text-gray-800 text-xs" x-text="managingUser?.name"></span>
                                <span class="text-gray-400 mx-1">•</span>
                                <span class="font-mono text-gray-600 text-[11px]" x-text="maskNik(managingUser?.nik)"></span>
                                <div class="mt-1 flex flex-wrap items-center gap-2 font-mono text-[11px]">
                                    <span class="text-gray-500">Password Portal:</span>
                                    <span class="font-bold text-slate-800 bg-white px-2 py-0.5 rounded border border-gray-200" x-text="managingUser?.password || 'kasir123'"></span>
                                    <span class="text-gray-300">•</span>
                                    <span class="text-gray-500">PIN POS:</span>
                                    <span class="font-bold text-blue-900 bg-blue-50 px-2 py-0.5 rounded border border-blue-200" x-text="managingUser?.pin || '123456'"></span>
                                </div>
                            </div>
                            <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full uppercase"
                                  :class="managingUser?.status === 'AKTIF' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                                  x-text="'Akun: ' + managingUser?.status"></span>
                        </div>

                        <!-- Daftar Cabang yang Saat Ini Ditugaskan -->
                        <div>
                            <label class="block font-bold text-gray-700 uppercase tracking-wider text-[11px] mb-2.5">
                                Cabang Penugasan Saat Ini
                            </label>
                            <div class="space-y-2">
                                <template x-for="ab in managingUser?.assignedBranches" :key="ab.branch">
                                    <div class="p-3 rounded-xl border border-gray-200 flex items-center justify-between gap-3 bg-white hover:bg-gray-50/50">
                                        <div class="flex items-center gap-2.5">
                                            <span class="material-symbols-rounded text-[18px]"
                                                  :class="ab.status === 'AKTIF' ? 'text-emerald-600' : 'text-gray-400'">store</span>
                                            <div>
                                                <div class="font-bold text-gray-900 text-xs" x-text="ab.branch"></div>
                                                <div class="text-[10.5px] text-gray-400" x-text="'Ditugaskan: ' + (ab.assignedAt || 'Aktif')"></div>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <!-- Toggle Status Penugasan di Cabang Ini -->
                                            <button type="button"
                                                    @click="toggleBranchAssignment(ab.branch)"
                                                    :class="ab.status === 'AKTIF' ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border-emerald-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200 border-gray-200'"
                                                    class="px-2.5 py-1 rounded-lg border font-bold text-[10.5px] cursor-pointer transition-colors">
                                                <span x-text="ab.status === 'AKTIF' ? 'Aktif' : 'Nonaktif'"></span>
                                            </button>

                                            <!-- Hapus Penugasan Cabang -->
                                            <button type="button"
                                                    @click="removeBranchFromManagingUser(ab.branch)"
                                                    title="Cabut penugasan dari cabang ini"
                                                    class="p-1 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors cursor-pointer">
                                                <span class="material-symbols-rounded text-[18px]">delete_sweep</span>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Tambah Penugasan ke Cabang Lain -->
                        <div class="pt-3 border-t border-gray-100">
                            <label class="block font-bold text-gray-700 uppercase tracking-wider text-[11px] mb-2">
                                Tambah Penugasan ke Cabang Lain
                            </label>
                            <div class="flex items-center gap-2">
                                <select x-model="modalNewBranch"
                                        class="flex-1 px-3.5 py-2 border border-gray-200 rounded-xl bg-white text-xs font-semibold focus:outline-none focus:border-[#0052cc]">
                                    <option value="Pusat">Pusat (Minimarket Pusat)</option>
                                    <option value="Cabang A">Cabang A</option>
                                    <option value="Cabang B">Cabang B</option>
                                </select>
                                <button type="button"
                                        @click="addBranchToManagingUser()"
                                        class="px-4 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl font-bold text-xs inline-flex items-center gap-1.5 cursor-pointer shadow-xs">
                                    <span class="material-symbols-rounded text-[16px]">add_location</span>
                                    <span>Tugaskan</span>
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-end pt-2 border-t border-gray-100">
                            <button type="button"
                                    @click="showManageAssignmentModal = false"
                                    class="px-5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold text-xs cursor-pointer">
                                Selesai &amp; Tutup
                            </button>
                        </div>
                    </div>
                </template>

            </div>
        </div>
    </template>

    <!-- MODAL EDIT DATA USER (GENERIC) -->
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

                <template x-if="editingUser">
                    <form @submit.prevent="saveEdit()" class="p-6 space-y-4 text-xs font-sans">
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

                        <!-- Kredensial: Password Portal & PIN POS Dipisahkan -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="font-bold text-gray-700 block">Password Portal</label>
                                    <span class="text-[10px] text-gray-400">Login web</span>
                                </div>
                                <div class="relative flex items-center">
                                    <input :type="showEditingPassword ? 'text' : 'password'"
                                           x-model="editingUser.password"
                                           class="w-full px-3.5 pr-8 py-2 border border-gray-300 rounded-xl font-mono font-bold text-xs focus:outline-none focus:border-[#0052cc]" />
                                    <button type="button"
                                            @click="showEditingPassword = !showEditingPassword"
                                            class="absolute right-2 text-gray-400 hover:text-gray-600 p-1 cursor-pointer">
                                        <span class="material-symbols-rounded text-[16px]" x-text="showEditingPassword ? 'visibility_off' : 'visibility'"></span>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="font-bold text-gray-700 block">PIN POS (6 Digit)</label>
                                    <span class="text-[10px] text-gray-400">Terminal POS</span>
                                </div>
                                <div class="relative flex items-center">
                                    <input :type="showEditingPin ? 'text' : 'password'"
                                           x-model="editingUser.pin"
                                           class="w-full px-3.5 pr-8 py-2 border border-gray-300 rounded-xl font-mono tracking-widest font-bold text-xs focus:outline-none focus:border-[#0052cc]" />
                                    <button type="button"
                                            @click="showEditingPin = !showEditingPin"
                                            class="absolute right-2 text-gray-400 hover:text-gray-600 p-1 cursor-pointer">
                                        <span class="material-symbols-rounded text-[16px]" x-text="showEditingPin ? 'visibility_off' : 'visibility'"></span>
                                    </button>
                                </div>
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
                </template>

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
