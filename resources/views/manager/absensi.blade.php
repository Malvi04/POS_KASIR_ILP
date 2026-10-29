@extends('layouts.app')

@section('title', 'Manajemen Absensi - ILP POS')

@section('content')
<div x-data="{
    activeTab: 'rekap', // rekap | jadwal | izin
    searchQuery: '',
    kasirFilter: 'all',
    branchFilter: 'all',
    selectedDate: new Date().toISOString().split('T')[0],
    showCheckInModal: false,
    showIzinModal: false,
    toastMessage: '',
    showToast: false,

    // Form Manual Absen
    formAbsen: {
        staffName: '',
        terminal: 'Kasir 01',
        branch: 'Cabang Pusat',
        type: 'MASUK', // MASUK | PULANG
        notes: ''
    },

    // Form Pengajuan Izin
    formIzin: {
        staffName: '',
        type: 'IZIN', // SAKIT | CUTI | IZIN
        date: new Date().toISOString().split('T')[0],
        reason: ''
    },

    attendanceList: [],
    leaveRequests: [],
    shifts: [],

    initAttendance() {
        const stored = localStorage.getItem('pos_ilp_attendance_v1');
        if (stored) {
            try { this.attendanceList = JSON.parse(stored); } catch(e) { this.attendanceList = this.getDefaultAttendance(); }
        } else {
            this.attendanceList = this.getDefaultAttendance();
            this.saveAttendance();
        }

        const storedLeaves = localStorage.getItem('pos_ilp_leaves_v1');
        if (storedLeaves) {
            try { this.leaveRequests = JSON.parse(storedLeaves); } catch(e) { this.leaveRequests = this.getDefaultLeaves(); }
        } else {
            this.leaveRequests = this.getDefaultLeaves();
            this.saveLeaves();
        }

        this.shifts = [
            { id: 1, name: 'Shift Pagi (07:00 - 15:00)', staff: ['Andi Setiawan', 'Budi Kurniawan', 'Citra Dewi'], branch: 'Cabang Pusat' },
            { id: 2, name: 'Shift Siang (14:30 - 22:30)', staff: ['Bambang Pamungkas', 'Dedi Nurcahyo', 'Eka Ramadhan'], branch: 'Cabang Pusat' },
            { id: 3, name: 'Shift Pagi Cabang A (07:30 - 15:30)', staff: ['Ahmad Fauzi', 'Siti Aminah'], branch: 'Cabang A' }
        ];
    },

    saveAttendance() {
        localStorage.setItem('pos_ilp_attendance_v1', JSON.stringify(this.attendanceList));
    },

    saveLeaves() {
        localStorage.setItem('pos_ilp_leaves_v1', JSON.stringify(this.leaveRequests));
    },

    getDefaultAttendance() {
        return [
            { id: 1, terminal: 'Kasir 1', staffName: 'Andi Setiawan', checkIn: '06:55', checkOut: '15:05', branch: 'Cabang Pusat', status: 'TEPAT_WAKTU' },
            { id: 2, terminal: 'Kasir 2', staffName: 'Bambang Pamungkas', checkIn: '07:15', checkOut: '-', branch: 'Cabang A', status: 'TERLAMBAT' },
            { id: 3, terminal: 'Kasir 3', staffName: 'Citra Dewi', checkIn: '06:50', checkOut: '-', branch: 'Cabang Pusat', status: 'TEPAT_WAKTU' },
            { id: 4, terminal: 'Kasir 4', staffName: 'Dedi Nurcahyo', checkIn: '06:45', checkOut: '-', branch: 'Cabang Pusat', status: 'TEPAT_WAKTU' },
            { id: 5, terminal: 'Kasir 5', staffName: 'Eka Ramadhan', checkIn: '07:02', checkOut: '-', branch: 'Cabang A', status: 'TEPAT_WAKTU' }
        ];
    },

    getDefaultLeaves() {
        return [
            { id: 1, staffName: 'Rico Wijaya', type: 'SAKIT', date: '24 Okt 2026', reason: 'Demam flu butuh istirahat', status: 'DISETUJUI' },
            { id: 2, staffName: 'Santi Larasati', type: 'CUTI', date: '25-27 Okt 2026', reason: 'Keperluan keluarga di luar kota', status: 'MENUNGGU' }
        ];
    },

    get filteredAttendance() {
        return this.attendanceList.filter(a => {
            if (this.branchFilter !== 'all' && a.branch !== this.branchFilter) return false;
            if (this.kasirFilter !== 'all' && a.terminal !== this.kasirFilter) return false;
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                return a.staffName.toLowerCase().includes(q) || a.terminal.toLowerCase().includes(q) || a.branch.toLowerCase().includes(q);
            }
            return true;
        });
    },

    get totalStaff() {
        return this.attendanceList.length + this.leaveRequests.length;
    },

    get presentToday() {
        return this.attendanceList.filter(a => a.checkIn && a.checkIn !== '-').length;
    },

    get lateToday() {
        return this.attendanceList.filter(a => a.status === 'TERLAMBAT').length;
    },

    get onLeaveCount() {
        return this.leaveRequests.filter(l => l.status === 'DISETUJUI').length;
    },

    recordAttendance() {
        if (!this.formAbsen.staffName.trim()) {
            alert('Silakan masukkan nama staf!');
            return;
        }

        const now = new Date();
        const timeStr = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
        const isLate = now.getHours() > 7 || (now.getHours() === 7 && now.getMinutes() > 10);

        if (this.formAbsen.type === 'MASUK') {
            const existing = this.attendanceList.find(a => a.staffName.toLowerCase() === this.formAbsen.staffName.trim().toLowerCase());
            if (existing) {
                existing.checkIn = timeStr;
                existing.status = isLate ? 'TERLAMBAT' : 'TEPAT_WAKTU';
            } else {
                this.attendanceList.unshift({
                    id: Date.now(),
                    terminal: this.formAbsen.terminal,
                    staffName: this.formAbsen.staffName.trim(),
                    checkIn: timeStr,
                    checkOut: '-',
                    branch: this.formAbsen.branch,
                    status: isLate ? 'TERLAMBAT' : 'TEPAT_WAKTU'
                });
            }
            this.triggerToast(`Check-In berhasil untuk ${this.formAbsen.staffName} jam ${timeStr}`);
        } else {
            const existing = this.attendanceList.find(a => a.staffName.toLowerCase() === this.formAbsen.staffName.trim().toLowerCase());
            if (existing) {
                existing.checkOut = timeStr;
                this.triggerToast(`Check-Out berhasil untuk ${this.formAbsen.staffName} jam ${timeStr}`);
            } else {
                alert('Staf belum tercatat check-in hari ini!');
                return;
            }
        }

        this.saveAttendance();
        this.showCheckInModal = false;
        this.formAbsen.staffName = '';
    },

    submitIzin() {
        if (!this.formIzin.staffName.trim() || !this.formIzin.reason.trim()) {
            alert('Mohon lengkapi nama dan alasan izin!');
            return;
        }

        this.leaveRequests.unshift({
            id: Date.now(),
            staffName: this.formIzin.staffName.trim(),
            type: this.formIzin.type,
            date: this.formIzin.date,
            reason: this.formIzin.reason.trim(),
            status: 'MENUNGGU'
        });
        this.saveLeaves();
        this.showIzinModal = false;
        this.triggerToast(`Pengajuan ${this.formIzin.type} untuk ${this.formIzin.staffName} berhasil diajukan!`);
        this.formIzin.staffName = '';
        this.formIzin.reason = '';
    },

    approveLeave(req) {
        req.status = 'DISETUJUI';
        this.saveLeaves();
        this.triggerToast(`Izin ${req.staffName} telah disetujui.`);
    },

    rejectLeave(req) {
        req.status = 'DITOLAK';
        this.saveLeaves();
        this.triggerToast(`Izin ${req.staffName} telah ditolak.`);
    },

    exportData() {
        window.print();
    },

    triggerToast(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3500);
    }
}"
x-init="initAttendance()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">

    <!-- Sidebar -->
    <x-manager-sidebar active="absensi" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">

        <!-- Top Header -->
        <header class="h-[72px] bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center h-full">
                <h1 class="text-[18px] font-black text-gray-900 mr-8 tracking-tight">Manajemen Absensi</h1>

                <nav class="flex h-full gap-8">
                    <button type="button"
                            @click="activeTab = 'rekap'"
                            :class="activeTab === 'rekap' ? 'text-[#0052cc] font-bold border-b-[3px] border-[#0052cc]' : 'text-gray-500 font-medium hover:text-gray-800 border-b-[3px] border-transparent'"
                            class="h-full flex items-center text-[14px] pt-[3px] transition-colors cursor-pointer">
                        Rekap Harian
                    </button>
                    <button type="button"
                            @click="activeTab = 'jadwal'"
                            :class="activeTab === 'jadwal' ? 'text-[#0052cc] font-bold border-b-[3px] border-[#0052cc]' : 'text-gray-500 font-medium hover:text-gray-800 border-b-[3px] border-transparent'"
                            class="h-full flex items-center text-[14px] pt-[3px] transition-colors cursor-pointer">
                        Jadwal Shift
                    </button>
                    <button type="button"
                            @click="activeTab = 'izin'"
                            :class="activeTab === 'izin' ? 'text-[#0052cc] font-bold border-b-[3px] border-[#0052cc]' : 'text-gray-500 font-medium hover:text-gray-800 border-b-[3px] border-transparent'"
                            class="h-full flex items-center text-[14px] pt-[3px] transition-colors cursor-pointer">
                        Pengajuan Izin
                    </button>
                </nav>
            </div>

            <div class="flex items-center gap-3">
                <button type="button"
                        @click="showCheckInModal = true"
                        class="bg-[#0052cc] hover:bg-[#0043a8] text-white px-4 py-2 rounded-xl font-bold text-xs flex items-center gap-1.5 shadow-xs transition-colors cursor-pointer">
                    <span class="material-symbols-rounded text-[18px]">fingerprint</span>
                    <span>Check-In / Out Manual</span>
                </button>
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-8">

            <!-- Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <!-- Card 1: Total Staf -->
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-xs flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-[#eef2ff] text-[#0052cc] flex items-center justify-center shrink-0">
                        <span class="material-symbols-rounded text-[24px]">groups</span>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-gray-500 tracking-wider uppercase">TOTAL PERSONEL</span>
                        <h3 class="text-2xl font-black text-gray-900 mt-0.5" x-text="totalStaff"></h3>
                    </div>
                </div>

                <!-- Card 2: Hadir Hari Ini -->
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-xs flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-green-50 text-green-600 flex items-center justify-center shrink-0">
                        <span class="material-symbols-rounded text-[24px]">check_circle</span>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-gray-500 tracking-wider uppercase">HADIR HARI INI</span>
                        <h3 class="text-2xl font-black text-green-600 mt-0.5" x-text="presentToday"></h3>
                    </div>
                </div>

                <!-- Card 3: Terlambat -->
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-xs flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-red-50 text-red-500 flex items-center justify-center shrink-0">
                        <span class="material-symbols-rounded text-[24px]">schedule</span>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-gray-500 tracking-wider uppercase">TERLAMBAT</span>
                        <h3 class="text-2xl font-black text-red-500 mt-0.5" x-text="lateToday"></h3>
                    </div>
                </div>

                <!-- Card 4: Izin / Cuti -->
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-xs flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-orange-50 text-orange-500 flex items-center justify-center shrink-0">
                        <span class="material-symbols-rounded text-[24px]">event_busy</span>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-gray-500 tracking-wider uppercase">IZIN / CUTI</span>
                        <h3 class="text-2xl font-black text-orange-500 mt-0.5" x-text="onLeaveCount"></h3>
                    </div>
                </div>
            </div>

            <!-- TAB 1: REKAP HARIAN -->
            <div x-show="activeTab === 'rekap'">
                <!-- Filter Section -->
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-xs mb-6 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-4 text-xs">
                        <div class="flex items-center gap-2">
                            <label class="font-bold text-gray-600">Tanggal:</label>
                            <input type="date"
                                   x-model="selectedDate"
                                   class="px-3 py-1.5 rounded-lg border border-gray-200 text-gray-700 bg-white focus:outline-none focus:border-[#0052cc]">
                        </div>

                        <div class="flex items-center gap-2">
                            <label class="font-bold text-gray-600">Terminal:</label>
                            <select x-model="kasirFilter" class="bg-white border border-gray-200 rounded-lg px-3 py-1.5 text-gray-700 focus:outline-none focus:border-[#0052cc]">
                                <option value="all">Semua Kasir</option>
                                <option value="Kasir 1">Kasir 1</option>
                                <option value="Kasir 2">Kasir 2</option>
                                <option value="Kasir 3">Kasir 3</option>
                                <option value="Kasir 4">Kasir 4</option>
                                <option value="Kasir 5">Kasir 5</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <label class="font-bold text-gray-600">Cabang:</label>
                            <select x-model="branchFilter" class="bg-white border border-gray-200 rounded-lg px-3 py-1.5 text-gray-700 focus:outline-none focus:border-[#0052cc]">
                                <option value="all">Semua Cabang</option>
                                <option value="Cabang Pusat">Cabang Pusat</option>
                                <option value="Cabang A">Cabang A</option>
                            </select>
                        </div>
                    </div>

                    <button type="button"
                            @click="exportData()"
                            class="bg-[#0052cc] hover:bg-[#0043a8] text-white px-4 py-2 rounded-xl font-bold text-xs flex items-center gap-1.5 shadow-xs transition-colors shrink-0 cursor-pointer">
                        <span class="material-symbols-rounded text-[16px]">print</span>
                        <span>Cetak / Export Absensi</span>
                    </button>
                </div>

                <!-- Main Content Area (Table & Summary) -->
                <div class="flex flex-col xl:flex-row gap-6 items-start">

                    <!-- Table Card -->
                    <div class="flex-[3] w-full bg-white rounded-xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                        <div class="p-4 flex items-center justify-between border-b border-gray-100">
                            <h2 class="text-sm font-bold text-gray-900">Log Absensi Hari Ini</h2>
                            <div class="relative w-[220px]">
                                <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                                <input type="text"
                                       x-model="searchQuery"
                                       placeholder="Cari nama staf..."
                                       class="w-full pl-9 pr-3 py-1.5 rounded-lg border border-gray-200 focus:outline-none focus:border-[#0052cc] text-xs text-gray-700 bg-white">
                            </div>
                        </div>

                        <div class="overflow-x-auto min-w-0 w-full">
                            <table class="w-full text-left border-collapse text-xs min-w-[850px]">
                                <thead>
                                    <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 text-[11px] font-bold uppercase tracking-wider whitespace-nowrap">
                                        <th class="py-3.5 px-6 text-left w-[150px]">Terminal Kasir</th>
                                        <th class="py-3.5 px-6 text-left w-[180px]">Nama Staf</th>
                                        <th class="py-3.5 px-6 text-center w-[110px]">Masuk</th>
                                        <th class="py-3.5 px-6 text-center w-[110px]">Pulang</th>
                                        <th class="py-3.5 px-6 text-left w-[150px]">Status / Keterangan</th>
                                        <th class="py-3.5 px-6 text-left w-[150px]">Lokasi Cabang</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="row in filteredAttendance" :key="row.id">
                                        <tr class="hover:bg-blue-50/30 transition-colors">
                                            <td class="py-3.5 px-6 font-bold text-gray-800 whitespace-nowrap" x-text="row.terminal"></td>
                                            <td class="py-3.5 px-6">
                                                <div class="font-bold text-gray-900" x-text="row.staffName"></div>
                                            </td>
                                            <td class="py-3.5 px-6 text-center font-mono font-bold whitespace-nowrap"
                                                :class="row.status === 'TERLAMBAT' ? 'text-red-600' : 'text-gray-800'"
                                                x-text="row.checkIn"></td>
                                            <td class="py-3.5 px-6 text-center font-mono font-bold text-gray-800 whitespace-nowrap" x-text="row.checkOut"></td>
                                            <td class="py-3.5 px-6 whitespace-nowrap">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                                      :class="row.status === 'TERLAMBAT' ? 'bg-red-50 text-red-600 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200'"
                                                      x-text="row.status === 'TERLAMBAT' ? 'Terlambat' : 'Tepat Waktu'"></span>
                                            </td>
                                            <td class="py-3.5 px-6 text-gray-600 whitespace-nowrap" x-text="row.branch"></td>
                                        </tr>
                                    </template>
                                    <tr x-show="filteredAttendance.length === 0">
                                        <td colspan="6" class="text-center py-8 text-gray-400">
                                            Tidak ada data absensi yang sesuai filter.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="bg-gray-50 border-t border-gray-100 p-3 text-xs text-gray-500 font-medium">
                            <span x-text="'Menampilkan ' + filteredAttendance.length + ' entri absensi'"></span>
                        </div>
                    </div>

                    <!-- Right Card: Quick Shift Summary -->
                    <div class="flex-1 w-full xl:max-w-[320px] bg-white rounded-xl border border-gray-200 shadow-xs p-5 shrink-0">
                        <h3 class="text-[11px] font-black text-gray-500 uppercase tracking-wider mb-4">RINGKASAN SHIFT AKTIF</h3>

                        <div class="space-y-3 text-xs">
                            <div class="p-3 bg-blue-50/60 border border-blue-100 rounded-xl">
                                <span class="font-bold text-blue-900 block">Shift Pagi (07:00 - 15:00)</span>
                                <span class="text-blue-700 text-[11px] mt-0.5 block">3 Kasir Aktif di Terminal</span>
                            </div>
                            <div class="p-3 bg-slate-50 border border-gray-200 rounded-xl">
                                <span class="font-bold text-gray-800 block">Shift Siang (14:30 - 22:30)</span>
                                <span class="text-gray-500 text-[11px] mt-0.5 block">Persiapan serah terima sesi</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- TAB 2: JADWAL SHIFT -->
            <div x-show="activeTab === 'jadwal'" style="display: none;">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <template x-for="shift in shifts" :key="shift.id">
                        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="material-symbols-rounded text-[#0052cc]">schedule</span>
                                <h3 class="font-bold text-gray-900 text-sm" x-text="shift.name"></h3>
                            </div>
                            <p class="text-xs text-gray-500 mb-3" x-text="shift.branch"></p>
                            <div class="border-t border-gray-100 pt-3">
                                <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block mb-2">Personel Bertugas:</span>
                                <ul class="space-y-1.5 text-xs text-gray-700">
                                    <template x-for="(st, idx) in shift.staff" :key="idx">
                                        <li class="flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#0052cc]"></span>
                                            <span x-text="st"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- TAB 3: PENGAJUAN IZIN -->
            <div x-show="activeTab === 'izin'" style="display: none;">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-base font-bold text-gray-900">Daftar Pengajuan Izin & Cuti</h2>
                    <button type="button"
                            @click="showIzinModal = true"
                            class="bg-[#0052cc] hover:bg-[#0043a8] text-white px-4 py-2 rounded-xl font-bold text-xs flex items-center gap-1.5 shadow-xs cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">add</span>
                        <span>Ajukan Izin Baru</span>
                    </button>
                </div>

                <div class="bg-white border border-gray-200 rounded-2xl shadow-xs overflow-hidden">
                    <div class="overflow-x-auto min-w-0 w-full">
                        <table class="w-full text-left text-xs min-w-[850px] border-collapse">
                            <thead class="bg-gray-50/80 border-b border-gray-200 text-gray-600 text-[11px] font-bold uppercase tracking-wider whitespace-nowrap">
                                <tr>
                                    <th class="py-3.5 px-6 text-left w-[200px]">Nama Personel</th>
                                    <th class="py-3.5 px-6 text-left w-[120px]">Tipe</th>
                                    <th class="py-3.5 px-6 text-left w-[140px]">Tanggal</th>
                                    <th class="py-3.5 px-6 text-left w-[220px]">Alasan</th>
                                    <th class="py-3.5 px-6 text-center w-[120px]">Status</th>
                                    <th class="py-3.5 px-6 text-center w-[140px]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="req in leaveRequests" :key="req.id">
                                    <tr class="hover:bg-blue-50/30 transition-colors">
                                        <td class="py-3.5 px-6 font-bold text-gray-900" x-text="req.staffName"></td>
                                        <td class="py-3.5 px-6 whitespace-nowrap">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                                  :class="{
                                                      'bg-red-50 text-red-600': req.type === 'SAKIT',
                                                      'bg-blue-50 text-blue-700': req.type === 'CUTI',
                                                      'bg-amber-50 text-amber-700': req.type === 'IZIN'
                                                  }"
                                                  x-text="req.type"></span>
                                        </td>
                                        <td class="py-3.5 px-6 font-mono whitespace-nowrap" x-text="req.date"></td>
                                        <td class="py-3.5 px-6 text-gray-600" x-text="req.reason"></td>
                                        <td class="py-3.5 px-6 text-center whitespace-nowrap">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                                  :class="{
                                                      'bg-green-50 text-green-700': req.status === 'DISETUJUI',
                                                      'bg-amber-50 text-amber-700': req.status === 'MENUNGGU',
                                                      'bg-red-50 text-red-600': req.status === 'DITOLAK'
                                                  }"
                                                  x-text="req.status"></span>
                                        </td>
                                        <td class="py-3.5 px-6 text-center whitespace-nowrap">
                                            <template x-if="req.status === 'MENUNGGU'">
                                                <div class="inline-flex items-center justify-center gap-2">
                                                    <button type="button"
                                                            @click="approveLeave(req)"
                                                            class="px-2.5 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-[11px] font-bold cursor-pointer">
                                                        Setujui
                                                    </button>
                                                    <button type="button"
                                                            @click="rejectLeave(req)"
                                                            class="px-2.5 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-[11px] font-bold cursor-pointer">
                                                        Tolak
                                                    </button>
                                                </div>
                                            </template>
                                            <template x-if="req.status !== 'MENUNGGU'">
                                                <span class="text-gray-400 font-medium text-[11px]">Selesai</span>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- MODAL CHECK-IN / CHECK-OUT MANUAL -->
    <template x-teleport="body">
        <div x-show="showCheckInModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div @click.away="showCheckInModal = false"
                 class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-md overflow-hidden animate-scale-up">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-rounded text-[#0052cc] text-[22px]">fingerprint</span>
                        <h3 class="font-bold text-gray-900 text-sm">Catat Absensi Personel</h3>
                    </div>
                    <button @click="showCheckInModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <form @submit.prevent="recordAttendance()" class="p-6 space-y-4 text-xs font-sans">
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Nama Personel / Kasir <span class="text-red-500">*</span></label>
                        <input type="text"
                               x-model="formAbsen.staffName"
                               placeholder="Contoh: Budi Kurniawan"
                               required
                               class="w-full px-3.5 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-[#0052cc]" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">Tipe Absen</label>
                            <select x-model="formAbsen.type" class="w-full px-3 py-2 border border-gray-300 rounded-xl">
                                <option value="MASUK">Check-In (Masuk)</option>
                                <option value="PULANG">Check-Out (Pulang)</option>
                            </select>
                        </div>
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">Terminal</label>
                            <select x-model="formAbsen.terminal" class="w-full px-3 py-2 border border-gray-300 rounded-xl">
                                <option value="Kasir 1">Kasir 1</option>
                                <option value="Kasir 2">Kasir 2</option>
                                <option value="Kasir 3">Kasir 3</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Cabang Penugasan</label>
                        <select x-model="formAbsen.branch" class="w-full px-3 py-2 border border-gray-300 rounded-xl">
                            <option value="Cabang Pusat">Cabang Pusat</option>
                            <option value="Cabang A">Cabang A</option>
                        </select>
                    </div>

                    <div class="pt-2 flex justify-end gap-2">
                        <button type="button"
                                @click="showCheckInModal = false"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold cursor-pointer">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-5 py-2 bg-[#0052cc] hover:bg-blue-700 text-white rounded-xl font-bold cursor-pointer">
                            Simpan Absensi
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </template>

    <!-- MODAL AJUKAN IZIN -->
    <template x-teleport="body">
        <div x-show="showIzinModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div @click.away="showIzinModal = false"
                 class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-md overflow-hidden animate-scale-up">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-rounded text-[#0052cc] text-[22px]">event_note</span>
                        <h3 class="font-bold text-gray-900 text-sm">Form Pengajuan Izin / Cuti</h3>
                    </div>
                    <button @click="showIzinModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <form @submit.prevent="submitIzin()" class="p-6 space-y-4 text-xs font-sans">
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Nama Personel <span class="text-red-500">*</span></label>
                        <input type="text"
                               x-model="formIzin.staffName"
                               placeholder="Nama staf"
                               required
                               class="w-full px-3.5 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-[#0052cc]" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">Jenis Pengajuan</label>
                            <select x-model="formIzin.type" class="w-full px-3 py-2 border border-gray-300 rounded-xl">
                                <option value="IZIN">Izin Pribadi</option>
                                <option value="SAKIT">Surat Sakit</option>
                                <option value="CUTI">Cuti Tahunan</option>
                            </select>
                        </div>
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">Tanggal</label>
                            <input type="date"
                                   x-model="formIzin.date"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-xl" />
                        </div>
                    </div>

                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Alasan / Keterangan <span class="text-red-500">*</span></label>
                        <textarea x-model="formIzin.reason"
                                  rows="3"
                                  placeholder="Jelaskan alasan izin secara singkat..."
                                  required
                                  class="w-full px-3.5 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-[#0052cc]"></textarea>
                    </div>

                    <div class="pt-2 flex justify-end gap-2">
                        <button type="button"
                                @click="showIzinModal = false"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold cursor-pointer">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-5 py-2 bg-[#0052cc] hover:bg-blue-700 text-white rounded-xl font-bold cursor-pointer">
                            Kirim Pengajuan
                        </button>
                    </div>
                </form>

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
