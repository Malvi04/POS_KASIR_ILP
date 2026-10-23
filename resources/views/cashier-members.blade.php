@extends('layouts.app')

@section('title', 'Pelanggan / Anggota - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    searchQuery: '',
    statusFilter: 'all',
    showDetailModal: false,
    selectedMember: null,

    members: [
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
            notes: 'Anggota aktif koperasi, pembayaran payroll tertib.',
            history: [
                { id: '#TRX-20261024-0019', date: '24 Okt 2026 • 07:15', total: 195000, cashier: 'Budi Kurniawan' },
                { id: '#TRX-20261018-0044', date: '18 Okt 2026 • 16:30', total: 305000, cashier: 'Citra Dewi' }
            ]
        },
        {
            id: 'MBR-002',
            nik: '327501440230012',
            name: 'Siti Lestari',
            dept: 'Administrasi & Keuangan',
            joinDate: '05 Mei 2022',
            phone: '0813-8821-4412',
            creditLimit: 5000000,
            currentDebt: 3800000,
            status: 'AKTIF',
            notes: 'Mendekati ambang 80% plafon belanja.',
            history: [
                { id: '#TRX-20261020-0081', date: '20 Okt 2026 • 12:45', total: 650000, cashier: 'Andi Pratama' },
                { id: '#TRX-20261014-0012', date: '14 Okt 2026 • 10:20', total: 1200000, cashier: 'Budi Kurniawan' }
            ]
        },
        {
            id: 'MBR-003',
            nik: '327501002230099',
            name: 'Andri Setiawan',
            dept: 'Produksi A',
            joinDate: '10 Agustus 2020',
            phone: '0857-1120-9981',
            creditLimit: 5000000,
            currentDebt: 5000000,
            status: 'DIBLOKIR',
            notes: 'Plafon telah habis (100%). Transaksi kredit otomatis ditolak oleh sistem kasir sampai pemotongan gaji/pelunasan.',
            history: [
                { id: '#TRX-20261005-0102', date: '05 Okt 2026 • 17:10', total: 1500000, cashier: 'Dedi Santoso' }
            ]
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
            notes: 'Tidak memiliki riwayat tunggakan, plafon utuh 100%.',
            history: []
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
            notes: 'Sisa plafon Rp 2.150.000, eligible transaksi kredit.',
            history: [
                { id: '#TRX-20261017-0033', date: '17 Okt 2026 • 14:15', total: 420000, cashier: 'Citra Dewi' }
            ]
        }
    ],

    get filteredMembers() {
        return this.members.filter(m => {
            if (this.statusFilter !== 'all' && m.status !== this.statusFilter) return false;
            if (this.searchQuery) {
                const q = this.searchQuery.toLowerCase();
                return m.name.toLowerCase().includes(q) ||
                       m.nik.toLowerCase().includes(q) ||
                       m.dept.toLowerCase().includes(q) ||
                       m.id.toLowerCase().includes(q);
            }
            return true;
        });
    },

    openDetail(m) {
        this.selectedMember = m;
        this.showDetailModal = true;
    }
}"
x-init="initOpening()"
@pos-session-sync.window="initOpening()"
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
                            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Data Pelanggan & Anggota Koperasi</h1>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-blue-100 text-[#0052cc]">
                                Validasi Plafon Kasir
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">Profil anggota, kelayakan kredit belanja, dan sisa plafon piutang.</p>
                    </div>
                </div>

                <!-- Security & Permission Notice -->
                <div class="bg-amber-50/80 border border-amber-200 rounded-2xl p-4 mb-6 flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[20px]">shield</span>
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-amber-900 uppercase tracking-wider">Batasan Wewenang Kasir (Permission Policy)</h3>
                            <p class="text-xs text-amber-800 mt-0.5 leading-relaxed">
                                Kasir hanya memiliki akses baca. Perubahan limit kredit/piutang dikelola oleh Pengurus Koperasi.
                            </p>
                        </div>
                    </div>
                    <span class="hidden sm:inline-flex px-2.5 py-1 rounded-full text-[10px] font-black bg-amber-200 text-amber-900 whitespace-nowrap uppercase tracking-wider">
                        Read-Only Mode
                    </span>
                </div>

                <!-- 3 Summary KPI Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                    <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-xs">
                        <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-wider">Total Anggota Terdaftar</span>
                        <div class="flex items-baseline gap-2 mt-1.5">
                            <h2 class="text-2xl font-black text-[#0052cc] font-mono">1.284</h2>
                            <span class="text-xs font-bold text-emerald-600">+12 bln ini</span>
                        </div>
                        <span class="text-xs font-medium text-gray-500 mt-1 block">Karyawan & Anggota Aktif</span>
                    </div>

                    <div class="bg-white rounded-xl p-5 border border-emerald-200 shadow-xs">
                        <span class="text-[11px] font-extrabold text-emerald-800 uppercase tracking-wider">Anggota Eligible Kredit</span>
                        <div class="flex items-baseline gap-2 mt-1.5">
                            <h2 class="text-2xl font-black text-emerald-700 font-mono">1.246</h2>
                            <span class="text-xs font-bold text-emerald-600">97.0%</span>
                        </div>
                        <span class="text-xs font-medium text-emerald-600 mt-1 block">Sisa plafon mencukupi</span>
                    </div>

                    <div class="bg-white rounded-xl p-5 border border-rose-200 shadow-xs">
                        <span class="text-[11px] font-extrabold text-rose-800 uppercase tracking-wider">Anggota Diblokir (Overlimit)</span>
                        <div class="flex items-baseline gap-2 mt-1.5">
                            <h2 class="text-2xl font-black text-rose-600 font-mono">38</h2>
                            <span class="text-xs font-bold text-rose-500">Plafon Habis</span>
                        </div>
                        <span class="text-xs font-medium text-rose-600 mt-1 block">Hanya boleh transaksi tunai/QRIS</span>
                    </div>
                </div>

                <!-- Search & Filters -->
                <div class="bg-white rounded-t-xl p-5 border border-gray-200 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-4 border-b-0">
                    <div class="relative w-full sm:w-80">
                        <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="Cari NIK, nama anggota, departemen..."
                               class="w-full pl-9 pr-3.5 py-2 bg-slate-50 border border-gray-200 rounded-lg text-xs font-medium text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#0052cc] focus:bg-white transition-colors" />
                    </div>

                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Status:</span>
                        <div class="flex bg-gray-100 p-0.5 rounded-lg text-xs">
                            <button type="button" @click="statusFilter = 'all'" :class="statusFilter === 'all' ? 'bg-white font-bold text-gray-900 shadow-2xs' : 'text-gray-500'" class="px-3 py-1 rounded cursor-pointer">Semua</button>
                            <button type="button" @click="statusFilter = 'AKTIF'" :class="statusFilter === 'AKTIF' ? 'bg-white font-bold text-emerald-700 shadow-2xs' : 'text-gray-500'" class="px-3 py-1 rounded cursor-pointer">Aktif</button>
                            <button type="button" @click="statusFilter = 'DIBLOKIR'" :class="statusFilter === 'DIBLOKIR' ? 'bg-white font-bold text-rose-700 shadow-2xs' : 'text-gray-500'" class="px-3 py-1 rounded cursor-pointer">Diblokir</button>
                        </div>
                    </div>
                </div>

                <!-- Table of Members -->
                <div class="bg-white rounded-b-xl border border-gray-200 shadow-xs overflow-hidden">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-500 text-[10px] font-black uppercase tracking-widest">
                                <th class="px-5 py-3.5">Nama Anggota</th>
                                <th class="px-5 py-3.5">NIK / ID</th>
                                <th class="px-5 py-3.5">Departemen</th>
                                <th class="px-5 py-3.5 text-right">Plafon Kredit</th>
                                <th class="px-5 py-3.5 text-right">Total Piutang</th>
                                <th class="px-5 py-3.5 text-right">Sisa Plafon</th>
                                <th class="px-5 py-3.5 text-center">Status</th>
                                <th class="px-5 py-3.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="m in filteredMembers" :key="m.id">
                                <tr class="hover:bg-blue-50/20 transition-colors" :class="m.status === 'DIBLOKIR' ? 'bg-rose-50/30' : ''">
                                    <td class="px-5 py-3.5 font-bold text-gray-900" x-text="m.name"></td>
                                    <td class="px-5 py-3.5 font-mono text-gray-600" x-text="m.nik"></td>
                                    <td class="px-5 py-3.5 text-gray-600" x-text="m.dept"></td>
                                    <td class="px-5 py-3.5 text-right font-mono font-bold text-gray-800" x-text="formatRupiah(m.creditLimit)"></td>
                                    <td class="px-5 py-3.5 text-right font-mono font-bold"
                                        :class="m.currentDebt > 0 ? (m.status === 'DIBLOKIR' ? 'text-rose-600' : 'text-orange-600') : 'text-gray-400'"
                                        x-text="formatRupiah(m.currentDebt)"></td>
                                    <td class="px-5 py-3.5 text-right font-mono font-black"
                                        :class="(m.creditLimit - m.currentDebt) > 0 ? 'text-emerald-700' : 'text-gray-400'"
                                        x-text="formatRupiah(m.creditLimit - m.currentDebt)"></td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider"
                                              :class="m.status === 'AKTIF' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-100 text-rose-700 border border-rose-300'"
                                              x-text="m.status"></span>
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <button type="button"
                                                @click="openDetail(m)"
                                                class="px-3 py-1 bg-blue-50 hover:bg-blue-100 text-[#0052cc] rounded-lg font-bold text-[11px] transition-colors cursor-pointer inline-flex items-center gap-1">
                                            <span class="material-symbols-rounded text-[14px]">visibility</span>
                                            <span>Detail Piutang</span>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredMembers.length === 0">
                                <td colspan="8" class="text-center py-12 text-gray-400">
                                    <span class="material-symbols-rounded text-4xl mb-2 text-gray-300">person_search</span>
                                    <p class="font-bold text-gray-600">Tidak ada anggota yang cocok</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Silakan periksa kata kunci pencarian Anda.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </main>
        </div>
    </div>

    <!-- MODAL DETAIL ANGGOTA & PIUTANG (READ-ONLY FOR CASHIER) -->
    <template x-teleport="body">
        <div x-show="showDetailModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div @click.away="showDetailModal = false"
                 class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-xl overflow-hidden animate-scale-up">
                
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-rounded text-[#0052cc] text-[22px]">account_circle</span>
                        <div>
                            <h3 class="font-bold text-gray-900 text-sm">Informasi Pelanggan / Anggota</h3>
                            <span class="font-mono text-xs text-gray-500" x-text="selectedMember?.id + ' • ' + selectedMember?.nik"></span>
                        </div>
                    </div>
                    <button @click="showDetailModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs font-sans">

                    <!-- Profile Info -->
                    <div class="p-4 bg-gray-50 rounded-xl border border-gray-200 space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Nama Lengkap:</span>
                            <span class="font-bold text-gray-900 text-sm" x-text="selectedMember?.name"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Departemen / Unit:</span>
                            <span class="font-bold text-gray-800" x-text="selectedMember?.dept"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">No. Telepon / Kontak:</span>
                            <span class="font-mono text-gray-700" x-text="selectedMember?.phone"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Tanggal Bergabung:</span>
                            <span class="text-gray-700" x-text="selectedMember?.joinDate"></span>
                        </div>
                    </div>

                    <!-- Plafon & Sisa Kredit (Read-Only) -->
                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div class="p-3 bg-slate-50 border border-gray-200 rounded-xl">
                            <span class="text-[10px] font-bold text-gray-500 uppercase block">Plafon Disetujui</span>
                            <span class="text-sm font-black font-mono text-gray-800 mt-1 block" x-text="formatRupiah(selectedMember?.creditLimit)"></span>
                            <span class="text-[9px] text-gray-400 mt-0.5 block flex items-center justify-center gap-0.5">
                                <span class="material-symbols-rounded text-[11px]">lock</span>
                                Terkunci
                            </span>
                        </div>

                        <div class="p-3 bg-orange-50/60 border border-orange-200 rounded-xl">
                            <span class="text-[10px] font-bold text-orange-800 uppercase block">Total Piutang</span>
                            <span class="text-sm font-black font-mono text-orange-700 mt-1 block" x-text="formatRupiah(selectedMember?.currentDebt)"></span>
                            <span class="text-[9px] text-orange-600 mt-0.5 block">Berjalan saat ini</span>
                        </div>

                        <div class="p-3 bg-emerald-50/60 border border-emerald-200 rounded-xl">
                            <span class="text-[10px] font-bold text-emerald-800 uppercase block">Sisa Plafon</span>
                            <span class="text-sm font-black font-mono text-emerald-700 mt-1 block" x-text="formatRupiah((selectedMember?.creditLimit || 0) - (selectedMember?.currentDebt || 0))"></span>
                            <span class="text-[9px] text-emerald-600 mt-0.5 block">Limit Tersedia</span>
                        </div>
                    </div>

                    <!-- Lock Notice Banner -->
                    <div class="p-3 bg-gray-100 border border-gray-200 rounded-xl flex items-center gap-2.5 text-gray-600">
                        <span class="material-symbols-rounded text-gray-500 text-[18px] shrink-0">lock</span>
                        <span class="text-[11px]">
                            <strong>Akses Terkunci:</strong> Kasir hanya memiliki hak izin baca. Penyesuaian limit kredit atau pelunasan piutang wajib diajukan melalui Pengurus Koperasi.
                        </span>
                    </div>

                    <!-- History of Credit Purchases -->
                    <div>
                        <h4 class="text-[11px] font-bold text-gray-800 uppercase tracking-wider mb-2">Riwayat Transaksi Kredit Terakhir:</h4>
                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50 text-gray-500 text-[10px] font-bold uppercase">
                                    <tr>
                                        <th class="px-3 py-2">Faktur</th>
                                        <th class="px-3 py-2">Waktu</th>
                                        <th class="px-3 py-2">Kasir</th>
                                        <th class="px-3 py-2 text-right">Nominal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="(h, idx) in selectedMember?.history" :key="idx">
                                        <tr>
                                            <td class="px-3 py-2 font-mono font-bold text-[#0052cc]" x-text="h.id"></td>
                                            <td class="px-3 py-2 text-gray-600 font-mono text-[11px]" x-text="h.date"></td>
                                            <td class="px-3 py-2 text-gray-800" x-text="h.cashier"></td>
                                            <td class="px-3 py-2 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(h.total)"></td>
                                        </tr>
                                    </template>
                                    <tr x-show="!selectedMember?.history || selectedMember?.history.length === 0">
                                        <td colspan="4" class="px-3 py-4 text-center text-gray-400 italic">
                                            Belum ada riwayat transaksi kredit bulan ini.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2 bg-slate-50">
                    <button type="button"
                            @click="showDetailModal = false"
                            class="px-5 py-2 bg-gray-800 hover:bg-gray-900 text-white rounded-xl font-bold text-xs transition-colors cursor-pointer">
                        Tutup
                    </button>
                </div>

            </div>
        </div>
    </template>

</div>
@endsection
