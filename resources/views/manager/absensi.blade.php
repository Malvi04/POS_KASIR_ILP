@extends('layouts.app')

@section('title', 'Manajemen Absensi - ILP POS')

@section('content')
<div x-data="{
    activeTab: 'rekap', // rekap | izin
    searchQuery: '',
    kasirFilter: 'all',
    branchFilter: 'all',
    selectedDate: new Date().toISOString().split('T')[0],
    showCheckInModal: false,
    showIzinModal: false,
    isSubmittingAttendance: false,
    isSubmittingLeave: false,
    toastMessage: '',
    showToast: false,

    // Form Manual Absen
    formAbsen: {
        staffName: '',
        terminal: 'POS-01',
        branch: 'Pusat',
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
    leavePeriod: 'hari', // 'hari', 'minggu', 'bulan', 'tahun', 'all'
    leaveFilterDate: new Date().toISOString().split('T')[0],
    leaveFilterMonth: new Date().toISOString().slice(0, 7),
    leaveFilterYear: new Date().getFullYear().toString(),
    leaveStatusFilter: 'all',
    leaveTypeFilter: 'all',
    leaveSearchQuery: '',
    isLoadingLeaves: false,
    leaveSummary: {
        total: 0,
        disetujui: 0,
        menunggu: 0,
        ditolak: 0,
        izin: 0,
        cuti: 0,
        sakit: 0
    },

    async initAttendance() {
        await Promise.all([
            this.loadAttendance(),
            this.loadLeaves()
        ]);

        // 1. Event listener real-time Reverb untuk absensi kasir
        window.addEventListener('pos:attendance-recorded', (e) => {
            const item = e.detail;
            if (!item) return;
            const idx = this.attendanceList.findIndex(a => 
                a.id === item.id || (a.staffName === item.staffName && a.terminal === item.terminal)
            );
            if (idx >= 0) {
                this.attendanceList[idx] = { ...this.attendanceList[idx], ...item };
            } else {
                this.attendanceList.unshift(item);
            }
            if (item.type === 'PULANG') {
                this.triggerToast(`Real-time: Sesi selesai untuk ${item.staffName} (${item.terminal || '-'}) - Check-out: ${item.checkOut}`);
            } else {
                this.triggerToast(`Real-time: Kasir ${item.staffName} mulai bertugas di ${item.terminal || '-'} (${item.branch || 'Pusat'})`);
            }
        });

        // 2. Event listener shift terbuka / tertutup dari Reverb
        window.addEventListener('pos:shift-opened', (e) => {
            this.loadAttendance();
        });

        window.addEventListener('pos:shift-closed', (e) => {
            this.loadAttendance();
        });
    },

    async loadAttendance() {
        try {
            const res = await fetch(`/api/attendance?date=${this.selectedDate}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success && json.data) {
                    this.attendanceList = json.data;
                }
            }
        } catch(e) {
            console.error('Failed to load attendance from PostgreSQL API', e);
        }
    },

    async loadLeaves() {
        this.isLoadingLeaves = true;
        try {
            const params = new URLSearchParams({
                period: this.leavePeriod,
                date: this.leaveFilterDate,
                month: this.leaveFilterMonth,
                year: this.leaveFilterYear,
                status: this.leaveStatusFilter,
                type: this.leaveTypeFilter,
                q: this.leaveSearchQuery
            });
            const leaveRes = await fetch(`/api/attendance/leaves?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (leaveRes.ok) {
                const leaveJson = await leaveRes.json();
                if (leaveJson.success && leaveJson.data) {
                    this.leaveRequests = leaveJson.data;
                    if (leaveJson.summary) {
                        this.leaveSummary = leaveJson.summary;
                    }
                }
            }
        } catch(e) {
            console.error('Failed to load leave requests', e);
        } finally {
            this.isLoadingLeaves = false;
        }
    },

    setLeavePeriod(period) {
        this.leavePeriod = period;
        this.loadLeaves();
    },

    exportLeaveExcel() {
        const params = new URLSearchParams({
            period: this.leavePeriod,
            date: this.leaveFilterDate,
            month: this.leaveFilterMonth,
            year: this.leaveFilterYear,
            status: this.leaveStatusFilter,
            type: this.leaveTypeFilter,
            q: this.leaveSearchQuery
        });
        window.open(`/api/attendance/leaves/export-excel?${params.toString()}`, '_blank');
    },

    saveAttendance() {
        // Data absensi permanen tersimpan di PostgreSQL
    },

    saveLeaves() {
        // Data cuti/izin tersimpan di PostgreSQL
    },

    getDefaultAttendance() {
        return [];
    },

    getDefaultLeaves() {
        return [];
    },

    get filteredAttendance() {
        return this.attendanceList.filter(a => {
            if (this.branchFilter !== 'all') {
                const b = (a.branch || '').toLowerCase();
                const target = this.branchFilter.toLowerCase();
                if (target === 'pusat' && !b.includes('pusat')) return false;
                if (target !== 'pusat' && !b.includes(target)) return false;
            }
            if (this.kasirFilter !== 'all') {
                const term = (a.terminal || '').toLowerCase();
                const target = this.kasirFilter.toLowerCase();
                if (!term.includes(target)) return false;
            }
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                return (a.staffName || '').toLowerCase().includes(q) || (a.terminal || '').toLowerCase().includes(q) || (a.branch || '').toLowerCase().includes(q);
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

    async recordAttendance() {
        if (this.isSubmittingAttendance) return;
        if (!this.formAbsen.staffName.trim()) {
            alert('Silakan masukkan nama staf!');
            return;
        }

        const now = new Date();
        const timeStr = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
        const isLate = now.getHours() > 7 || (now.getHours() === 7 && now.getMinutes() > 10);

        this.isSubmittingAttendance = true;
        const idemKey = window.createIdempotencyKey ? window.createIdempotencyKey() : ('att-' + Date.now());
        try {
            const res = await fetch('/api/attendance', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Idempotency-Key': idemKey
                },
                body: JSON.stringify({
                    staffName: this.formAbsen.staffName.trim(),
                    terminal: this.formAbsen.terminal,
                    branch: this.formAbsen.branch,
                    type: this.formAbsen.type,
                    notes: this.formAbsen.notes || null,
                    date: this.selectedDate
                })
            });

            const json = await res.json().catch(() => ({}));
            if (res.ok && json.success && json.data) {
                if (this.formAbsen.type === 'MASUK') {
                    this.attendanceList.unshift(json.data);
                } else {
                    const existing = this.attendanceList.find(a => a.staffName.toLowerCase() === this.formAbsen.staffName.trim().toLowerCase());
                    if (existing) {
                        existing.checkOut = json.data.checkOut;
                    }
                }
                this.triggerToast(`${this.formAbsen.type === 'MASUK' ? 'Check-In' : 'Check-Out'} berhasil dicatat ke Database untuk ${this.formAbsen.staffName}!`);
                this.showCheckInModal = false;
                this.formAbsen.staffName = '';
            } else {
                alert(json.message || 'Gagal mencatat absensi ke server.');
            }
        } catch(e) {
            console.error('Failed to record attendance to API', e);
            alert('Terjadi kesalahan koneksi saat menyimpan absensi.');
        } finally {
            this.isSubmittingAttendance = false;
        }
    },

    async submitIzin() {
        if (this.isSubmittingLeave) return;
        if (!this.formIzin.staffName.trim() || !this.formIzin.reason.trim()) {
            alert('Mohon lengkapi nama dan alasan izin!');
            return;
        }

        this.isSubmittingLeave = true;
        const idemKey = window.createIdempotencyKey ? window.createIdempotencyKey() : ('lv-' + Date.now());
        try {
            const res = await fetch('/api/attendance/leaves', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Idempotency-Key': idemKey
                },
                body: JSON.stringify({
                    staffName: this.formIzin.staffName.trim(),
                    type: this.formIzin.type,
                    date: this.formIzin.date,
                    reason: this.formIzin.reason.trim()
                })
            });

            const json = await res.json().catch(() => ({}));
            if (res.ok && json.success && json.data) {
                this.showIzinModal = false;
                this.triggerToast(`Pengajuan ${this.formIzin.type} untuk ${this.formIzin.staffName} berhasil disimpan ke Database!`);
                this.formIzin.staffName = '';
                this.formIzin.reason = '';
                await this.loadLeaves();
            } else {
                alert(json.message || 'Gagal mengirim pengajuan izin ke server.');
            }
        } catch(e) {
            console.error('Failed to submit leave to API', e);
            alert('Terjadi kesalahan koneksi saat mengirim pengajuan izin.');
        } finally {
            this.isSubmittingLeave = false;
        }
    },

    async approveLeave(req) {
        try {
            const res = await fetch(`/api/attendance/leaves/${req.id}/status`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ status: 'DISETUJUI', approved_by: @js(auth()->user()?->name ?? 'Manager') })
            });
            if (res.ok) {
                req.status = 'DISETUJUI';
                this.triggerToast(`Izin ${req.staffName} telah disetujui di Database.`);
                await this.loadLeaves();
            } else {
                this.triggerToast('Gagal menyetujui izin di server.');
            }
        } catch(e) {
            console.error(e);
            this.triggerToast('Gagal terhubung ke server.');
        }
    },

    async rejectLeave(req) {
        try {
            const res = await fetch(`/api/attendance/leaves/${req.id}/status`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ status: 'DITOLAK', approved_by: @js(auth()->user()?->name ?? 'Manager') })
            });
            if (res.ok) {
                req.status = 'DITOLAK';
                this.triggerToast(`Izin ${req.staffName} telah ditolak.`);
                await this.loadLeaves();
            } else {
                this.triggerToast('Gagal menolak izin di server.');
            }
        } catch(e) {
            console.error(e);
            this.triggerToast('Gagal terhubung ke server.');
        }
    },

    exportData() {
        if (!this.filteredAttendance || this.filteredAttendance.length === 0) {
            this.triggerToast('Tidak ada data absensi untuk diekspor.');
            return;
        }

        const headers = ['No', 'Terminal Kasir', 'Nama Staf', 'Jam Masuk', 'Jam Pulang', 'Lokasi Cabang'];
        let no = 1;
        const rows = this.filteredAttendance.map(row => [
            no++,
            row.terminal || '-',
            row.staffName || '-',
            row.checkIn || '-',
            row.checkOut || '-',
            row.branch || '-'
        ]);

        window.exportTableToExcel({
            filename: `Rekap_Absensi_Karyawan_${this.selectedDate}.xlsx`,
            sheetName: 'Absensi Karyawan',
            title: 'PT INDO LAMPUNG PERKASA - MINIMARKET KOPERASI',
            subtitle: 'REKAPITULASI ABSENSI & KEHADIRAN KARYAWAN',
            metadata: [
                ['Tanggal Absensi', this.selectedDate],
                ['Total Staf Hadir', this.filteredAttendance.length + ' Orang'],
                ['Tanggal Cetak', new Date().toLocaleString('id-ID')]
            ],
            headers: headers,
            rows: rows
        });

        this.triggerToast('File Excel rekap absensi berhasil diunduh.');
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
    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden relative">

        <!-- Top Header -->
        <header class="h-20 bg-[#f8f9fa] border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-3">
                <h1 class="text-[20px] font-bold text-gray-900">Absensi &amp; Shift</h1>
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200 flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[14px]">badge</span>
                    <span>Presensi &amp; Jadwal Kerja</span>
                </span>
            </div>

            <div class="flex items-center gap-4 text-gray-500">
                <x-notification-bell role="MANAGER" />
                <button type="button" @click="window.PosRealtime?.syncNow?.(); initAttendance();" class="hover:text-gray-900 transition-colors cursor-pointer" title="Sinkronkan Data">
                    <span class="material-symbols-rounded text-[24px]" :class="$store.network?.isChecking ? 'animate-spin text-[#0052cc]' : ''">autorenew</span>
                </button>
                <div class="h-6 w-px bg-gray-300"></div>
                <x-realtime-network-badge />
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-8">

            <!-- Tab Switcher & Action Toolbar -->
            <div class="bg-white p-2.5 rounded-2xl border border-gray-200/80 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3 mb-6">
                <div class="inline-flex p-1 bg-gray-100 rounded-xl gap-1">
                    <button type="button"
                            @click="activeTab = 'rekap'"
                            :class="activeTab === 'rekap' ? 'bg-white text-[#0052cc] shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                            class="px-4 py-2 rounded-lg text-xs transition-all flex items-center gap-2 cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">calendar_today</span>
                        <span>Rekap Harian</span>
                    </button>
                    <button type="button"
                            @click="activeTab = 'izin'"
                            :class="activeTab === 'izin' ? 'bg-white text-[#0052cc] shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                            class="px-4 py-2 rounded-lg text-xs transition-all flex items-center gap-2 cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">event_busy</span>
                        <span>Pengajuan Izin</span>
                    </button>
                </div>

                <button type="button"
                        @click="showCheckInModal = true"
                        class="bg-[#0052cc] hover:bg-[#0043a8] text-white px-4 py-2 rounded-xl font-bold text-xs flex items-center gap-1.5 shadow-xs transition-colors cursor-pointer">
                    <span class="material-symbols-rounded text-[18px]">fingerprint</span>
                    <span>Check-In / Out Manual</span>
                </button>
            </div>

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
                                   @change="loadAttendance()"
                                   class="px-3 py-1.5 rounded-lg border border-gray-200 text-gray-700 bg-white focus:outline-none focus:border-[#0052cc]">
                        </div>

                        <div class="flex items-center gap-2">
                            <label class="font-bold text-gray-600">Terminal:</label>
                            <select x-model="kasirFilter" class="bg-white border border-gray-200 rounded-lg px-3 py-1.5 text-gray-700 focus:outline-none focus:border-[#0052cc]">
                                <option value="all">Semua Terminal</option>
                                <option value="POS-01">POS-01 (Pusat)</option>
                                <option value="POS-02">POS-02 (Pusat)</option>
                                <option value="POS-03">POS-03 (Pusat)</option>
                                <option value="POS-04">POS-04 (Pusat)</option>
                                <option value="POS-05">POS-05 (Pusat)</option>
                                <option value="POS-06">POS-06 (Cabang A)</option>
                                <option value="POS-07">POS-07 (Cabang A)</option>
                                <option value="POS-11">POS-11 (Cabang B)</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <label class="font-bold text-gray-600">Cabang:</label>
                            <select x-model="branchFilter" class="bg-white border border-gray-200 rounded-lg px-3 py-1.5 text-gray-700 focus:outline-none focus:border-[#0052cc]">
                                <option value="all">Semua Cabang</option>
                                <option value="Pusat">Pusat</option>
                                <option value="Cabang A">Cabang A</option>
                                <option value="Cabang B">Cabang B</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button"
                                @click="exportData()"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-2 rounded-xl font-bold text-xs flex items-center gap-1.5 shadow-xs transition-colors cursor-pointer">
                            <span class="material-symbols-rounded text-[16px]">table_chart</span>
                            <span>Ekspor Excel</span>
                        </button>
                        <button type="button"
                                @click="window.print()"
                                class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 px-3 py-2 rounded-xl font-bold text-xs flex items-center gap-1.5 shadow-xs transition-colors cursor-pointer">
                            <span class="material-symbols-rounded text-[16px] text-gray-500">print</span>
                            <span>Cetak</span>
                        </button>
                    </div>
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
                                        <th class="py-3.5 px-6 text-left w-[180px]">Nama Karyawan</th>
                                        <th class="py-3.5 px-6 text-left w-[150px]">Cabang</th>
                                        <th class="py-3.5 px-6 text-left w-[150px]">Terminal Kasir</th>
                                        <th class="py-3.5 px-6 text-center w-[110px]">Jam Masuk</th>
                                        <th class="py-3.5 px-6 text-center w-[110px]">Jam Pulang</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="row in filteredAttendance" :key="row.id">
                                        <tr class="hover:bg-blue-50/30 transition-colors">
                                            <td class="py-3.5 px-6">
                                                <div class="font-bold text-gray-900" x-text="row.staffName"></div>
                                            </td>
                                            <td class="py-3.5 px-6 text-gray-600 whitespace-nowrap" x-text="row.branch"></td>
                                            <td class="py-3.5 px-6 font-bold text-[#0052cc] whitespace-nowrap" x-text="row.terminal"></td>
                                            <td class="py-3.5 px-6 text-center font-mono font-bold whitespace-nowrap"
                                                :class="row.status === 'TERLAMBAT' ? 'text-red-600' : 'text-gray-800'"
                                                x-text="row.checkIn"></td>
                                            <td class="py-3.5 px-6 text-center font-mono font-bold text-gray-800 whitespace-nowrap" x-text="row.checkOut"></td>
                                        </tr>
                                    </template>
                                    <tr x-show="!filteredAttendance || filteredAttendance.length === 0">
                                        <td colspan="5" class="text-center py-12 text-gray-400 font-medium">
                                            <p class="text-sm">Belum ada data absensi hari ini.</p>
                                            <div class="mt-3">
                                                <button type="button" @click="showCheckInModal = true" class="px-4 py-2 bg-[#0052cc] text-white rounded-xl text-xs font-bold inline-flex items-center gap-1.5 shadow-sm hover:bg-[#0043a8] transition-colors cursor-pointer">
                                                    <span class="material-symbols-rounded text-[16px]">fingerprint</span>
                                                    <span>Catat Absensi</span>
                                                </button>
                                            </div>
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
                                <span class="font-bold text-blue-900 block">Shift Aktif Hari Ini</span>
                                <span class="text-blue-700 text-[11px] mt-0.5 block" x-text="attendanceList.filter(a => a.checkIn && a.checkIn !== '-' && (!a.checkOut || a.checkOut === '-')).length + ' Personel Aktif Bertugas'">0 Personel Aktif Bertugas</span>
                            </div>
                            <div class="p-3 bg-slate-50 border border-gray-200 rounded-xl">
                                <span class="font-bold text-gray-800 block">Selesai Shift / Check-Out</span>
                                <span class="text-gray-500 text-[11px] mt-0.5 block" x-text="attendanceList.filter(a => a.checkOut && a.checkOut !== '-').length + ' Personel Telah Check-Out'">0 Personel Telah Check-Out</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- TAB 2: PENGAJUAN IZIN -->
            <div x-show="activeTab === 'izin'" style="display: none;" class="space-y-4">
                <!-- TOP HEADER & ACTION BUTTONS -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                            <span>Daftar Pengajuan Izin & Cuti</span>
                            <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold bg-blue-50 text-[#0052cc]" x-text="leaveRequests.length + ' data'"></span>
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">Rekapitulasi berkala dan verifikasi izin, cuti tahunan, dan surat sakit staf.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- EXPORT EXCEL BUTTON -->
                        <button type="button"
                                @click="exportLeaveExcel()"
                                :disabled="isLoadingLeaves"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl font-bold text-xs flex items-center gap-1.5 shadow-xs cursor-pointer transition-colors disabled:opacity-50">
                            <span class="material-symbols-rounded text-[18px]">table_view</span>
                            <span>Export Excel</span>
                        </button>

                        <!-- AJUKAN IZIN BARU BUTTON -->
                        <button type="button"
                                @click="showIzinModal = true"
                                class="bg-[#0052cc] hover:bg-[#0043a8] text-white px-4 py-2 rounded-xl font-bold text-xs flex items-center gap-1.5 shadow-xs cursor-pointer transition-colors">
                            <span class="material-symbols-rounded text-[18px]">add</span>
                            <span>Ajukan Izin Baru</span>
                        </button>
                    </div>
                </div>

                <!-- SUMMARY COUNTER CARDS -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="bg-white border border-gray-200 rounded-2xl p-3.5 shadow-xs flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[20px]">description</span>
                        </div>
                        <div>
                            <div class="text-[11px] font-semibold text-gray-500">Total Pengajuan</div>
                            <div class="text-lg font-bold text-gray-900" x-text="leaveSummary.total || leaveRequests.length"></div>
                        </div>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-2xl p-3.5 shadow-xs flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[20px]">pending_actions</span>
                        </div>
                        <div>
                            <div class="text-[11px] font-semibold text-gray-500">Menunggu Review</div>
                            <div class="text-lg font-bold text-amber-600" x-text="leaveSummary.menunggu || 0"></div>
                        </div>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-2xl p-3.5 shadow-xs flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[20px]">check_circle</span>
                        </div>
                        <div>
                            <div class="text-[11px] font-semibold text-gray-500">Disetujui</div>
                            <div class="text-lg font-bold text-emerald-600" x-text="leaveSummary.disetujui || 0"></div>
                        </div>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-2xl p-3.5 shadow-xs flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[20px]">cancel</span>
                        </div>
                        <div>
                            <div class="text-[11px] font-semibold text-gray-500">Ditolak</div>
                            <div class="text-lg font-bold text-rose-600" x-text="leaveSummary.ditolak || 0"></div>
                        </div>
                    </div>
                </div>

                <!-- FILTER CONTROLS & PERIOD SELECTION -->
                <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-xs space-y-3">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                        <!-- PERIOD PILLS -->
                        <div class="flex items-center gap-1.5 p-1 bg-gray-100 rounded-xl text-xs font-semibold overflow-x-auto">
                            <button type="button"
                                    @click="setLeavePeriod('hari')"
                                    class="px-3 py-1.5 rounded-lg transition-all cursor-pointer whitespace-nowrap"
                                    :class="leavePeriod === 'hari' ? 'bg-white text-[#0052cc] shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900'">
                                Hari Ini
                            </button>
                            <button type="button"
                                    @click="setLeavePeriod('minggu')"
                                    class="px-3 py-1.5 rounded-lg transition-all cursor-pointer whitespace-nowrap"
                                    :class="leavePeriod === 'minggu' ? 'bg-white text-[#0052cc] shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900'">
                                Minggu Ini
                            </button>
                            <button type="button"
                                    @click="setLeavePeriod('bulan')"
                                    class="px-3 py-1.5 rounded-lg transition-all cursor-pointer whitespace-nowrap"
                                    :class="leavePeriod === 'bulan' ? 'bg-white text-[#0052cc] shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900'">
                                Bulan Ini
                            </button>
                            <button type="button"
                                    @click="setLeavePeriod('tahun')"
                                    class="px-3 py-1.5 rounded-lg transition-all cursor-pointer whitespace-nowrap"
                                    :class="leavePeriod === 'tahun' ? 'bg-white text-[#0052cc] shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900'">
                                Tahun Ini
                            </button>
                            <button type="button"
                                    @click="setLeavePeriod('all')"
                                    class="px-3 py-1.5 rounded-lg transition-all cursor-pointer whitespace-nowrap"
                                    :class="leavePeriod === 'all' ? 'bg-white text-[#0052cc] shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900'">
                                Semua Periode
                            </button>
                        </div>

                        <!-- DYNAMIC PERIOD PICKERS -->
                        <div class="flex items-center gap-2">
                            <!-- Daily Picker -->
                            <template x-if="leavePeriod === 'hari'">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-semibold text-gray-500 whitespace-nowrap">Pilih Tanggal:</span>
                                    <input type="date"
                                           x-model="leaveFilterDate"
                                           @change="loadLeaves()"
                                           class="px-3 py-1.5 text-xs font-medium border border-gray-200 rounded-xl focus:border-[#0052cc] focus:outline-none" />
                                </div>
                            </template>

                            <!-- Weekly Reference Date Picker -->
                            <template x-if="leavePeriod === 'minggu'">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-semibold text-gray-500 whitespace-nowrap">Acuan Minggu:</span>
                                    <input type="date"
                                           x-model="leaveFilterDate"
                                           @change="loadLeaves()"
                                           class="px-3 py-1.5 text-xs font-medium border border-gray-200 rounded-xl focus:border-[#0052cc] focus:outline-none" />
                                </div>
                            </template>

                            <!-- Monthly Picker -->
                            <template x-if="leavePeriod === 'bulan'">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-semibold text-gray-500 whitespace-nowrap">Pilih Bulan:</span>
                                    <input type="month"
                                           x-model="leaveFilterMonth"
                                           @change="loadLeaves()"
                                           class="px-3 py-1.5 text-xs font-medium border border-gray-200 rounded-xl focus:border-[#0052cc] focus:outline-none" />
                                </div>
                            </template>

                            <!-- Yearly Picker -->
                            <template x-if="leavePeriod === 'tahun'">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-semibold text-gray-500 whitespace-nowrap">Pilih Tahun:</span>
                                    <select x-model="leaveFilterYear"
                                            @change="loadLeaves()"
                                            class="px-3 py-1.5 text-xs font-medium border border-gray-200 rounded-xl focus:border-[#0052cc] focus:outline-none">
                                        <option value="2024">2024</option>
                                        <option value="2025">2025</option>
                                        <option value="2026">2026</option>
                                        <option value="2027">2027</option>
                                        <option value="2028">2028</option>
                                    </select>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- SEARCH & SECONDARY FILTERS -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 md:grid-cols-4 gap-2.5 pt-2 border-t border-gray-100">
                        <!-- Search Box -->
                        <div class="sm:col-span-2 relative">
                            <span class="material-symbols-rounded absolute left-3 top-2.5 text-gray-400 text-[18px]">search</span>
                            <input type="text"
                                   x-model="leaveSearchQuery"
                                   @input.debounce.400ms="loadLeaves()"
                                   placeholder="Cari nama personel, keterangan, atau approver..."
                                   class="w-full pl-9 pr-3 py-2 text-xs border border-gray-200 rounded-xl focus:border-[#0052cc] focus:outline-none" />
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <select x-model="leaveStatusFilter"
                                    @change="loadLeaves()"
                                    class="w-full px-3 py-2 text-xs border border-gray-200 rounded-xl focus:border-[#0052cc] focus:outline-none font-medium">
                                <option value="all">Semua Status</option>
                                <option value="MENUNGGU">Menunggu Review</option>
                                <option value="DISETUJUI">Disetujui</option>
                                <option value="DITOLAK">Ditolak</option>
                            </select>
                        </div>

                        <!-- Type Filter -->
                        <div>
                            <select x-model="leaveTypeFilter"
                                    @change="loadLeaves()"
                                    class="w-full px-3 py-2 text-xs border border-gray-200 rounded-xl focus:border-[#0052cc] focus:outline-none font-medium">
                                <option value="all">Semua Tipe</option>
                                <option value="IZIN">Izin Pribadi</option>
                                <option value="CUTI">Cuti Tahunan</option>
                                <option value="SAKIT">Surat Sakit</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- TABLE CARD -->
                <div class="bg-white border border-gray-200 rounded-2xl shadow-xs overflow-hidden">
                    <div class="overflow-x-auto min-w-0 w-full">
                        <table class="w-full text-left text-xs min-w-[950px] border-collapse">
                            <thead class="bg-gray-50/80 border-b border-gray-200 text-gray-600 text-[11px] font-bold uppercase tracking-wider whitespace-nowrap">
                                <tr>
                                    <th class="py-3.5 px-4 text-center w-[50px]">No</th>
                                    <th class="py-3.5 px-5 text-left w-[180px]">Nama Personel</th>
                                    <th class="py-3.5 px-4 text-left w-[140px]">Cabang</th>
                                    <th class="py-3.5 px-4 text-center w-[110px]">Tipe</th>
                                    <th class="py-3.5 px-5 text-left w-[140px]">Periode Izin</th>
                                    <th class="py-3.5 px-5 text-left min-w-[200px]">Alasan</th>
                                    <th class="py-3.5 px-4 text-center w-[120px]">Status</th>
                                    <th class="py-3.5 px-4 text-center w-[130px]">Reviewer</th>
                                    <th class="py-3.5 px-4 text-center w-[130px]">Diajukan</th>
                                    <th class="py-3.5 px-4 text-center w-[130px]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <!-- Loading State -->
                                <tr x-show="isLoadingLeaves">
                                    <td colspan="10" class="text-center py-10 text-gray-400">
                                        <div class="inline-flex items-center gap-2">
                                            <span class="material-symbols-rounded animate-spin text-[20px] text-[#0052cc]">progress_activity</span>
                                            <span class="font-medium text-xs">Memuat data pengajuan izin...</span>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Data Rows -->
                                <template x-for="(req, index) in leaveRequests" :key="req.id">
                                    <tr class="hover:bg-blue-50/30 transition-colors">
                                        <td class="py-3.5 px-4 text-center font-mono text-gray-500 text-[11px]" x-text="index + 1"></td>
                                        <td class="py-3.5 px-5 font-bold text-gray-900" x-text="req.staffName"></td>
                                        <td class="py-3.5 px-4 text-gray-600 font-medium" x-text="req.branch || '-'"></td>
                                        <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wide"
                                                  :class="{
                                                      'bg-red-50 text-red-600 border border-red-200': req.type === 'SAKIT',
                                                      'bg-blue-50 text-[#0052cc] border border-blue-200': req.type === 'CUTI',
                                                      'bg-amber-50 text-amber-700 border border-amber-200': req.type === 'IZIN'
                                                  }"
                                                  x-text="req.type"></span>
                                        </td>
                                        <td class="py-3.5 px-5 font-mono text-gray-700 whitespace-nowrap text-[11px]" x-text="req.date"></td>
                                        <td class="py-3.5 px-5 text-gray-600 text-[11px] leading-relaxed" x-text="req.reason"></td>
                                        <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                                                  :class="{
                                                      'bg-emerald-50 text-emerald-700 border border-emerald-200': req.status === 'DISETUJUI',
                                                      'bg-amber-50 text-amber-700 border border-amber-200': req.status === 'MENUNGGU',
                                                      'bg-rose-50 text-rose-600 border border-rose-200': req.status === 'DITOLAK'
                                                  }"
                                                  x-text="req.status"></span>
                                        </td>
                                        <td class="py-3.5 px-4 text-center text-gray-600 text-[11px]" x-text="req.approved_by || '-'"></td>
                                        <td class="py-3.5 px-4 text-center text-gray-500 font-mono text-[11px] whitespace-nowrap" x-text="req.created_at || '-'"></td>
                                        <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                            <template x-if="req.status === 'MENUNGGU'">
                                                <div class="inline-flex items-center justify-center gap-1.5">
                                                    <button type="button"
                                                            @click="approveLeave(req)"
                                                            title="Setujui Pengajuan"
                                                            class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[11px] font-bold cursor-pointer transition-colors shadow-xs">
                                                        Setujui
                                                    </button>
                                                    <button type="button"
                                                            @click="rejectLeave(req)"
                                                            title="Tolak Pengajuan"
                                                            class="px-2.5 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-[11px] font-bold cursor-pointer transition-colors shadow-xs">
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

                                <!-- Empty State -->
                                <tr x-show="!isLoadingLeaves && (!leaveRequests || leaveRequests.length === 0)">
                                    <td colspan="10" class="text-center py-12 text-gray-400 font-medium">
                                        <span class="material-symbols-rounded text-gray-300 text-[36px] block mb-1">event_busy</span>
                                        <p class="text-xs">Tidak ada data pengajuan izin atau cuti untuk filter ini.</p>
                                        <div class="mt-3">
                                            <button type="button" @click="showIzinModal = true" class="px-4 py-2 bg-[#0052cc] text-white rounded-xl text-xs font-bold inline-flex items-center gap-1.5 shadow-xs hover:bg-[#0043a8] transition-colors cursor-pointer">
                                                <span class="material-symbols-rounded text-[16px]">add</span>
                                                <span>Ajukan Izin / Cuti</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
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
                               placeholder="Nama lengkap personel"
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
                                <option value="POS-01">POS-01</option>
                                <option value="POS-02">POS-02</option>
                                <option value="POS-03">POS-03</option>
                                <option value="POS-05">POS-05</option>
                                <option value="POS-06">POS-06</option>
                                <option value="POS-11">POS-11</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Cabang Penugasan</label>
                        <select x-model="formAbsen.branch" class="w-full px-3 py-2 border border-gray-300 rounded-xl">
                            <option value="Pusat">Pusat</option>
                            <option value="Cabang A">Cabang A</option>
                            <option value="Cabang B">Cabang B</option>
                        </select>
                    </div>

                    <div class="pt-2 flex justify-end gap-2">
                        <button type="button"
                                :disabled="isSubmittingAttendance"
                                @click="showCheckInModal = false"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                            Batal
                        </button>
                        <button type="submit"
                                :disabled="isSubmittingAttendance"
                                class="px-5 py-2 bg-[#0052cc] hover:bg-blue-700 text-white rounded-xl font-bold cursor-pointer flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-show="isSubmittingAttendance" class="material-symbols-rounded text-[15px] animate-spin">progress_activity</span>
                            <span x-text="isSubmittingAttendance ? 'Menyimpan...' : 'Simpan Absensi'"></span>
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
                                :disabled="isSubmittingLeave"
                                @click="showIzinModal = false"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                            Batal
                        </button>
                        <button type="submit"
                                :disabled="isSubmittingLeave"
                                class="px-5 py-2 bg-[#0052cc] hover:bg-blue-700 text-white rounded-xl font-bold cursor-pointer flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-show="isSubmittingLeave" class="material-symbols-rounded text-[15px] animate-spin">progress_activity</span>
                            <span x-text="isSubmittingLeave ? 'Mengirim...' : 'Kirim Pengajuan'"></span>
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
