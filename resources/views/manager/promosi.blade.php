@extends('layouts.app')

@section('title', 'Manajemen Promo & Diskon - ILP POS')

@section('content')
<div x-data="{
    promotions: [],
    searchQuery: '',
    statusFilter: 'all',
    showAddModal: false,
    showEditModal: false,
    showDeleteModal: false,
    showRuleModal: false,
    deleteInputText: '',
    selectedPromo: null,
    editingPromo: null,
    toastMessage: '',
    showToast: false,

    formPromo: {
        name: '',
        condition: '',
        type: 'PERCENTAGE', // PERCENTAGE | FIXED | BOGO
        value: 10,
        periodStart: '2026-10-25',
        periodEnd: '2026-11-05',
        status: 'AKTIF'
    },

    expirySettings: {
        h7Percent: 40,
        h3Percent: 70,
        autoPrint: true
    },

    activities: [
        { text: 'Promo Sembako Murah diaktifkan oleh Admin.', time: '10 menit yang lalu', icon: 'check', color: 'green' },
        { text: 'Aturan H-7 Expired diubah menjadi 40%.', time: '2 jam yang lalu', icon: 'history', color: 'amber' },
        { text: 'Promo Ramadan Kareem telah berakhir.', time: 'Kemarin, 23:59', icon: 'close', color: 'red' }
    ],

    initPromotions() {
        const stored = localStorage.getItem('pos_ilp_promotions_v1');
        if (stored) {
            try {
                this.promotions = JSON.parse(stored);
            } catch(e) {
                this.promotions = this.getDefaultPromotions();
            }
        } else {
            this.promotions = this.getDefaultPromotions();
            this.savePromotions();
        }

        const storedSettings = localStorage.getItem('pos_ilp_promo_settings_v1');
        if (storedSettings) {
            try {
                this.expirySettings = JSON.parse(storedSettings);
            } catch(e){}
        }
    },

    savePromotions() {
        localStorage.setItem('pos_ilp_promotions_v1', JSON.stringify(this.promotions));
    },

    saveSettings() {
        localStorage.setItem('pos_ilp_promo_settings_v1', JSON.stringify(this.expirySettings));
    },

    getDefaultPromotions() {
        return [
            {
                id: 'PRM-001',
                name: 'Promo Gajian Mantap',
                condition: 'Min. belanja Rp 250k',
                type: 'PERCENTAGE',
                typeLabel: 'Persentase (10%)',
                badgeClass: 'bg-[#dbeafe] text-[#1e40af]',
                period: '25 Okt - 02 Nov',
                periodStart: '2026-10-25',
                periodEnd: '2026-11-02',
                status: 'AKTIF',
                statusColor: 'green'
            },
            {
                id: 'PRM-002',
                name: 'BOGO: Susu Cair Ultra',
                condition: 'Beli 2 Gratis 1',
                type: 'BOGO',
                typeLabel: 'Bundle / BOGO',
                badgeClass: 'bg-[#ffedd5] text-[#9a3412]',
                period: '01 Nov - 15 Nov',
                periodStart: '2026-11-01',
                periodEnd: '2026-11-15',
                status: 'MENDATANG',
                statusColor: 'blue'
            },
            {
                id: 'PRM-003',
                name: 'Potongan Flat Deterjen',
                condition: 'Diskon Rp 5.000',
                type: 'FIXED',
                typeLabel: 'Fixed Amount',
                badgeClass: 'bg-gray-200 text-gray-700',
                period: '01 Okt - 15 Okt',
                periodStart: '2026-10-01',
                periodEnd: '2026-10-15',
                status: 'BERAKHIR',
                statusColor: 'gray'
            }
        ];
    },

    get filteredPromotions() {
        return this.promotions.filter(p => {
            if (this.statusFilter !== 'all' && p.status !== this.statusFilter) return false;
            if (this.searchQuery) {
                const q = this.searchQuery.toLowerCase();
                return p.name.toLowerCase().includes(q) ||
                       (p.condition && p.condition.toLowerCase().includes(q)) ||
                       (p.typeLabel && p.typeLabel.toLowerCase().includes(q));
            }
            return true;
        });
    },

    get activeCount() {
        return this.promotions.filter(p => p.status === 'AKTIF').length;
    },
    get upcomingCount() {
        return this.promotions.filter(p => p.status === 'MENDATANG').length;
    },
    get expiredCount() {
        return this.promotions.filter(p => p.status === 'BERAKHIR').length;
    },

    saveNewPromo() {
        if (!this.formPromo.name) {
            alert('Silakan masukkan nama promo!');
            return;
        }

        let typeLabel = 'Persentase (' + this.formPromo.value + '%)';
        let badgeClass = 'bg-[#dbeafe] text-[#1e40af]';
        if (this.formPromo.type === 'FIXED') {
            typeLabel = 'Diskon Rp ' + Number(this.formPromo.value).toLocaleString('id-ID');
            badgeClass = 'bg-gray-200 text-gray-700';
        } else if (this.formPromo.type === 'BOGO') {
            typeLabel = 'Bundle / BOGO';
            badgeClass = 'bg-[#ffedd5] text-[#9a3412]';
        }

        const newPromo = {
            id: 'PRM-' + String(this.promotions.length + 1).padStart(3, '0'),
            name: this.formPromo.name,
            condition: this.formPromo.condition || '-',
            type: this.formPromo.type,
            typeLabel: typeLabel,
            badgeClass: badgeClass,
            period: this.formPromo.periodStart + ' s/d ' + this.formPromo.periodEnd,
            periodStart: this.formPromo.periodStart,
            periodEnd: this.formPromo.periodEnd,
            status: this.formPromo.status,
            statusColor: this.formPromo.status === 'AKTIF' ? 'green' : (this.formPromo.status === 'MENDATANG' ? 'blue' : 'gray')
        };

        this.promotions.unshift(newPromo);
        this.savePromotions();
        this.activities.unshift({
            text: 'Promo ' + newPromo.name + ' ditambahkan oleh Manager.',
            time: 'Baru saja',
            icon: 'check',
            color: 'green'
        });

        this.showAddModal = false;
        this.triggerToast('Promo baru berhasil ditambahkan!');

        // Reset
        this.formPromo.name = '';
        this.formPromo.condition = '';
        this.formPromo.value = 10;
    },

    openEditPromo(p) {
        this.editingPromo = JSON.parse(JSON.stringify(p));
        this.showEditModal = true;
    },

    saveEditPromo() {
        if (!this.editingPromo) return;
        const idx = this.promotions.findIndex(p => p.id === this.editingPromo.id);
        if (idx !== -1) {
            this.editingPromo.statusColor = this.editingPromo.status === 'AKTIF' ? 'green' : (this.editingPromo.status === 'MENDATANG' ? 'blue' : 'gray');
            this.promotions[idx] = this.editingPromo;
            this.savePromotions();
            this.activities.unshift({
                text: 'Promo ' + this.editingPromo.name + ' diperbarui.',
                time: 'Baru saja',
                icon: 'history',
                color: 'amber'
            });
            this.triggerToast('Promo ' + this.editingPromo.name + ' berhasil diperbarui!');
        }
        this.showEditModal = false;
        this.editingPromo = null;
    },

    openDeletePromo(p) {
        this.selectedPromo = p;
        this.deleteInputText = '';
        this.showDeleteModal = true;
    },

    confirmDeletePromo() {
        if (!this.selectedPromo) return;
        if (this.deleteInputText.trim().toLowerCase() !== 'hapus') {
            alert('Ketik \'hapus\' untuk konfirmasi penghapusan!');
            return;
        }
        const name = this.selectedPromo.name;
        this.promotions = this.promotions.filter(p => p.id !== this.selectedPromo.id);
        this.savePromotions();
        this.activities.unshift({
            text: 'Promo ' + name + ' telah dihapus permanen.',
            time: 'Baru saja',
            icon: 'close',
            color: 'red'
        });
        this.showDeleteModal = false;
        this.selectedPromo = null;
        this.deleteInputText = '';
        this.triggerToast('Promo berhasil dihapus!');
    },

    toggleStatus(p) {
        if (p.status === 'AKTIF') {
            p.status = 'BERAKHIR';
            p.statusColor = 'gray';
        } else {
            p.status = 'AKTIF';
            p.statusColor = 'green';
        }
        this.savePromotions();
        this.triggerToast('Status promo diubah menjadi ' + p.status);
    },

    createRecommended() {
        this.formPromo.name = 'Bundle Sabun & Deterjen';
        this.formPromo.condition = 'Beli 2 Gratis 1 Produk Kebersihan';
        this.formPromo.type = 'BOGO';
        this.formPromo.status = 'AKTIF';
        this.showAddModal = true;
    },

    triggerToast(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3500);
    }
}"
x-init="initPromotions()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">
    
    <!-- Sidebar -->
    <x-manager-sidebar active="promosi" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        
        <!-- Top Header -->
        <header class="h-[68px] bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-8">
                <span class="text-[18px] font-black text-[#0052cc] tracking-tight">ILP POS</span>
                <nav class="flex items-center gap-6 text-[13.5px] font-medium text-gray-600">
                    <span class="text-gray-900 font-bold">Modul Promo</span>
                    <a href="/inventory" class="hover:text-gray-900 transition-colors">Master Produk</a>
                    <a href="/reports" class="hover:text-gray-900 transition-colors">Laporan Penjualan</a>
                </nav>
            </div>

            <div class="flex items-center gap-4">
                <button type="button"
                        @click="showAddModal = true"
                        class="bg-[#0052cc] hover:bg-[#0043a8] text-white px-4 py-2 rounded-xl font-bold text-[12.5px] shadow-sm transition-colors flex items-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-rounded text-[18px]">add_circle</span>
                    <span>Tambah Promo Baru</span>
                </button>
                <div class="flex items-center gap-2 bg-gray-100 border border-gray-200 rounded-full pl-3.5 pr-1.5 py-1">
                    <span class="text-[12.5px] font-bold text-gray-800">Manager Toko</span>
                    <div class="w-6 h-6 rounded-full bg-gray-800 text-white flex items-center justify-center">
                        <span class="material-symbols-rounded text-[16px]">person</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-8">
            <div class="max-w-[1400px] mx-auto">
                
                <!-- Page Title -->
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h1 class="text-[26px] font-black text-gray-900 tracking-tight leading-tight">Manajemen Promo & Diskon</h1>
                        <p class="text-[14px] text-gray-500 mt-1">Optimalkan penjualan toko koperasi dengan penawaran dan diskon cerdas.</p>
                    </div>
                    <!-- Quick status filter badges -->
                    <div class="flex items-center gap-2">
                        <button type="button" @click="statusFilter = 'all'" :class="statusFilter === 'all' ? 'bg-gray-900 text-white font-bold' : 'bg-white text-gray-600 border border-gray-200'" class="px-3 py-1.5 rounded-lg text-xs cursor-pointer transition-colors">Semua</button>
                        <button type="button" @click="statusFilter = 'AKTIF'" :class="statusFilter === 'AKTIF' ? 'bg-green-600 text-white font-bold' : 'bg-white text-gray-600 border border-gray-200'" class="px-3 py-1.5 rounded-lg text-xs cursor-pointer transition-colors">Aktif</button>
                        <button type="button" @click="statusFilter = 'MENDATANG'" :class="statusFilter === 'MENDATANG' ? 'bg-[#0052cc] text-white font-bold' : 'bg-white text-gray-600 border border-gray-200'" class="px-3 py-1.5 rounded-lg text-xs cursor-pointer transition-colors">Mendatang</button>
                        <button type="button" @click="statusFilter = 'BERAKHIR'" :class="statusFilter === 'BERAKHIR' ? 'bg-gray-600 text-white font-bold' : 'bg-white text-gray-600 border border-gray-200'" class="px-3 py-1.5 rounded-lg text-xs cursor-pointer transition-colors">Berakhir</button>
                    </div>
                </div>

                <!-- 4 Summary Cards -->
                <div class="grid grid-cols-4 gap-5 mb-7">
                    <!-- Card 1: PROMO AKTIF -->
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm flex flex-col justify-between min-h-[132px] cursor-pointer hover:border-green-400 transition-colors"
                         @click="statusFilter = 'AKTIF'">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">PROMO AKTIF</span>
                            <span class="material-symbols-rounded text-green-600 text-[20px]">bolt</span>
                        </div>
                        <div class="text-[30px] font-black text-gray-900 font-mono leading-none my-2" x-text="activeCount"></div>
                        <div class="flex items-center gap-1 text-[12px] font-semibold text-green-600">
                            <span class="material-symbols-rounded text-[15px]">trending_up</span>
                            <span>Sedang Berjalan</span>
                        </div>
                    </div>

                    <!-- Card 2: MENDATANG -->
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm flex flex-col justify-between min-h-[132px] cursor-pointer hover:border-blue-400 transition-colors"
                         @click="statusFilter = 'MENDATANG'">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">MENDATANG</span>
                            <span class="material-symbols-rounded text-[#0052cc] text-[19px]">calendar_today</span>
                        </div>
                        <div class="text-[30px] font-black text-gray-900 font-mono leading-none my-2" x-text="upcomingCount"></div>
                        <div class="text-[12px] font-medium text-gray-500">
                            Terjadwal periode depan
                        </div>
                    </div>

                    <!-- Card 3: KEDALUWARSA -->
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm flex flex-col justify-between min-h-[132px] cursor-pointer hover:border-gray-400 transition-colors"
                         @click="statusFilter = 'BERAKHIR'">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">KEDALUWARSA</span>
                            <span class="material-symbols-rounded text-red-600 text-[20px]">history</span>
                        </div>
                        <div class="text-[30px] font-black text-gray-900 font-mono leading-none my-2" x-text="expiredCount"></div>
                        <div class="text-[12px] font-medium text-gray-500">
                            Promo non-aktif / selesai
                        </div>
                    </div>

                    <!-- Card 4: AUTO-DISCOUNT -->
                    <div class="bg-[#e0eaff] rounded-2xl border border-blue-200 p-5 shadow-sm flex flex-col justify-between min-h-[132px] relative overflow-hidden cursor-pointer"
                         @click="showRuleModal = true">
                        <div class="flex items-center justify-between relative z-10">
                            <span class="text-[11px] font-bold text-gray-700 tracking-wider uppercase">AUTO-DISCOUNT</span>
                            <span class="material-symbols-rounded text-[#0052cc] text-[20px]">verified</span>
                        </div>
                        <div class="text-[30px] font-black text-[#002b6b] font-mono leading-none my-2 relative z-10" x-text="expirySettings.h7Percent + '% & ' + expirySettings.h3Percent + '%'"></div>
                        <div class="text-[12px] font-semibold text-[#0052cc] relative z-10 flex items-center gap-1">
                            <span>Aturan Near-Expiry</span>
                            <span class="material-symbols-rounded text-[14px]">tune</span>
                        </div>
                        <span class="material-symbols-rounded text-[#0052cc]/15 text-[110px] absolute -right-4 -bottom-6 pointer-events-none select-none">timer</span>
                    </div>
                </div>

                <!-- Main Grid: Left Table (8 cols) & Right Widgets (4 cols) -->
                <div class="grid grid-cols-12 gap-6 items-start">
                    
                    <!-- Left Column: Daftar Promosi Berjalan -->
                    <div class="col-span-8 bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex flex-col">
                        
                        <!-- Card Header -->
                        <div class="px-6 py-5 flex items-center justify-between gap-4">
                            <h2 class="text-[16px] font-bold text-gray-900">Daftar Promosi Berjalan</h2>
                            <div class="flex items-center gap-2.5">
                                <div class="relative w-[240px]">
                                    <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                                    <input type="text"
                                           x-model="searchQuery"
                                           placeholder="Cari promo..."
                                           class="w-full pl-9 pr-3 py-1.5 rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-[#0052cc]/20 focus:border-[#0052cc] text-[12.5px] text-gray-700 placeholder-gray-400">
                                </div>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto min-w-0 w-full">
                            <table class="w-full text-left border-collapse min-w-[850px]">
                                <thead>
                                    <tr class="bg-gray-50/80 border-y border-gray-200 text-gray-600 text-[11px] font-bold tracking-wider uppercase whitespace-nowrap">
                                        <th class="pl-8 pr-4 py-3.5 text-left w-[320px]">Nama Promo</th>
                                        <th class="px-4 py-3.5 text-left w-[150px]">Tipe</th>
                                        <th class="px-4 py-3.5 text-left w-[180px]">Periode</th>
                                        <th class="px-4 py-3.5 text-left w-[120px]">Status</th>
                                        <th class="pl-4 pr-8 py-3.5 text-center w-[100px]">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-[13px]">
                                    <template x-for="p in filteredPromotions" :key="p.id">
                                        <tr class="hover:bg-blue-50/30 transition-colors">
                                            <td class="pl-8 pr-4 py-3.5 align-middle">
                                                <div class="font-bold text-gray-900 text-[13.5px]" x-text="p.name"></div>
                                                <div class="text-[11.5px] text-gray-500 mt-0.5" x-text="p.condition"></div>
                                            </td>
                                            <td class="px-4 py-3.5 align-middle whitespace-nowrap">
                                                <span class="inline-block px-3 py-1 rounded-full text-[11px] font-bold"
                                                      :class="p.badgeClass"
                                                      x-text="p.typeLabel"></span>
                                            </td>
                                            <td class="px-4 py-3.5 align-middle font-mono text-[12px] text-gray-700 whitespace-nowrap" x-text="p.period"></td>
                                            <td class="px-4 py-3.5 align-middle whitespace-nowrap">
                                                <button type="button"
                                                        @click="toggleStatus(p)"
                                                        title="Klik untuk ubah status"
                                                        class="inline-flex items-center gap-1.5 font-semibold text-[12px] cursor-pointer"
                                                        :class="{
                                                            'text-green-600': p.status === 'AKTIF',
                                                            'text-[#0052cc]': p.status === 'MENDATANG',
                                                            'text-gray-400': p.status === 'BERAKHIR'
                                                        }">
                                                    <span class="w-2 h-2 rounded-full"
                                                          :class="{
                                                              'bg-green-600': p.status === 'AKTIF',
                                                              'bg-[#0052cc]': p.status === 'MENDATANG',
                                                              'bg-gray-400': p.status === 'BERAKHIR'
                                                          }"></span>
                                                    <span x-text="p.status"></span>
                                                </button>
                                            </td>
                                            <td class="pl-4 pr-8 py-3.5 align-middle text-center whitespace-nowrap">
                                                <div class="inline-flex items-center justify-center gap-2 text-gray-500">
                                                    <button type="button"
                                                            @click="openEditPromo(p)"
                                                            title="Edit Promo"
                                                            class="hover:text-[#0052cc] transition-colors p-1 rounded-md hover:bg-blue-50 cursor-pointer">
                                                        <span class="material-symbols-rounded text-[18px]">edit</span>
                                                    </button>
                                                    <button type="button"
                                                            @click="openDeletePromo(p)"
                                                            title="Hapus Promo"
                                                            class="hover:text-red-600 transition-colors p-1 rounded-md hover:bg-red-50 cursor-pointer">
                                                        <span class="material-symbols-rounded text-[18px]">delete</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="filteredPromotions.length === 0">
                                        <td colspan="5" class="text-center py-12 text-gray-400">
                                            <span class="material-symbols-rounded text-4xl mb-2 text-gray-300">local_offer</span>
                                            <p class="font-bold text-gray-600">Tidak ada promo yang ditemukan</p>
                                            <p class="text-xs text-gray-400 mt-0.5">Coba ubah status filter atau kata kunci pencarian.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Table Footer -->
                        <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
                            <span class="text-[12px] text-gray-500 font-medium" x-text="'Total ' + filteredPromotions.length + ' promosi'"></span>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="showAddModal = true" class="px-3.5 py-1.5 rounded-lg bg-[#0052cc] hover:bg-[#0043a8] text-[12px] font-semibold text-white shadow-sm transition-colors cursor-pointer">
                                    + Promo Baru
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- Right Column: Widgets -->
                    <div class="col-span-4 space-y-5">
                        
                        <!-- Widget 1: Diskon Otomatis Expired -->
                        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                            <!-- Header with warm tint -->
                            <div class="bg-[#fff9f2] p-5 border-b border-orange-100/60">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-rounded text-amber-800 text-[20px]">timer</span>
                                    <h3 class="text-[15px] font-bold text-gray-900">Diskon Otomatis Expired</h3>
                                </div>
                                <p class="text-[12px] text-gray-500 mt-1.5 leading-relaxed">
                                    Kurangi kerugian stok dengan diskon berjenjang otomatis.
                                </p>
                            </div>

                            <!-- Rules Body -->
                            <div class="p-5 space-y-3">
                                <!-- Rule 1 -->
                                <div class="bg-[#f3f5f8] rounded-xl px-4 py-3 flex items-center justify-between">
                                    <div>
                                        <h4 class="text-[12.5px] font-bold text-gray-900">Near Expiry (H-7)</h4>
                                        <p class="text-[11px] text-gray-500 mt-0.5">Diskon untuk produk sisa 7 hari</p>
                                    </div>
                                    <span class="text-[16px] font-black text-amber-800 font-mono" x-text="expirySettings.h7Percent + '%'"></span>
                                </div>

                                <!-- Rule 2 -->
                                <div class="bg-[#f3f5f8] rounded-xl px-4 py-3 flex items-center justify-between">
                                    <div>
                                        <h4 class="text-[12.5px] font-bold text-gray-900">Critical (H-3)</h4>
                                        <p class="text-[11px] text-gray-500 mt-0.5">Diskon pembersihan stok</p>
                                    </div>
                                    <span class="text-[16px] font-black text-red-600 font-mono" x-text="expirySettings.h3Percent + '%'"></span>
                                </div>

                                <!-- Add / Configure Rule Button -->
                                <button type="button"
                                        @click="showRuleModal = true"
                                        class="w-full py-2.5 border border-dashed border-gray-300 rounded-xl flex items-center justify-center gap-2 text-[12px] font-bold text-gray-700 hover:bg-gray-50 transition-colors cursor-pointer">
                                    <span class="material-symbols-rounded text-[18px] text-gray-500">tune</span>
                                    <span>Konfigurasi Aturan Expired</span>
                                </button>

                                <!-- Auto-Print Checkbox -->
                                <label class="flex items-center gap-2.5 pt-2 cursor-pointer select-none">
                                    <input type="checkbox"
                                           x-model="expirySettings.autoPrint"
                                           @change="saveSettings(); triggerToast('Preferensi auto-print label disimpan!')"
                                           class="w-4 h-4 rounded text-[#0052cc] border-gray-300 focus:ring-[#0052cc]">
                                    <span class="text-[12px] font-medium text-gray-700">Aktifkan Auto-Print Label Diskon</span>
                                </label>
                            </div>
                        </div>

                        <!-- Widget 2: Rekomendasi Promo -->
                        <div class="bg-[#0052cc] rounded-2xl p-6 text-white shadow-md relative overflow-hidden">
                            <span class="material-symbols-rounded text-white/15 text-[84px] absolute -right-2 -top-2 pointer-events-none select-none">auto_awesome</span>
                            
                            <h3 class="text-[15px] font-bold relative z-10">Rekomendasi Promo AI</h3>
                            <p class="text-[12px] text-blue-100 mt-2 leading-relaxed relative z-10">
                                Analisis riwayat transaksi menunjukkan peningkatan penjualan 22% jika Anda menerapkan 'Bundle Sabun & Deterjen' pada akhir pekan ini.
                            </p>
                            <button type="button"
                                    @click="createRecommended()"
                                    class="mt-4 bg-white hover:bg-blue-50 text-[#0052cc] font-bold text-[12px] px-4 py-2 rounded-xl shadow-sm transition-colors relative z-10 cursor-pointer">
                                Buat Promo Sekarang
                            </button>
                        </div>

                        <!-- Widget 3: Aktivitas Terakhir -->
                        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                            <h3 class="text-[14px] font-bold text-gray-900 mb-4">Aktivitas Terakhir</h3>
                            
                            <div class="space-y-4">
                                <template x-for="(act, idx) in activities" :key="idx">
                                    <div class="flex items-start gap-3">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 mt-0.5"
                                             :class="{
                                                 'bg-green-50 text-green-600': act.color === 'green',
                                                 'bg-amber-50 text-amber-600': act.color === 'amber',
                                                 'bg-red-50 text-red-600': act.color === 'red'
                                             }">
                                            <span class="material-symbols-rounded text-[15px]" x-text="act.icon"></span>
                                        </div>
                                        <div>
                                            <p class="text-[12px] font-medium text-gray-800 leading-snug" x-text="act.text"></p>
                                            <span class="text-[11px] text-gray-400 block mt-0.5" x-text="act.time"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                    </div>

                </div>

            </div>
        </div>
    </main>

    <!-- MODAL TAMBAH PROMO -->
    <template x-teleport="body">
        <div x-show="showAddModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div @click.away="showAddModal = false"
                 class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-lg overflow-hidden animate-scale-up">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-rounded text-[#0052cc] text-[22px]">local_offer</span>
                        <h3 class="font-bold text-gray-900 text-sm">Buat Promosi / Diskon Baru</h3>
                    </div>
                    <button @click="showAddModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <form @submit.prevent="saveNewPromo()" class="p-6 space-y-4 text-xs font-sans">
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Nama Promo <span class="text-red-500">*</span></label>
                        <input type="text"
                               x-model="formPromo.name"
                               placeholder="Contoh: Diskon Gajian Seru"
                               required
                               class="w-full px-3.5 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-[#0052cc]" />
                    </div>

                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Keterangan / Syarat Ketentuan</label>
                        <input type="text"
                               x-model="formPromo.condition"
                               placeholder="Contoh: Min. belanja Rp 200.000"
                               class="w-full px-3.5 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-[#0052cc]" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">Tipe Promosi</label>
                            <select x-model="formPromo.type" class="w-full px-3 py-2 border border-gray-300 rounded-xl">
                                <option value="PERCENTAGE">Persentase (%)</option>
                                <option value="FIXED">Potongan Tetap (Rp)</option>
                                <option value="BOGO">Bundle / BOGO</option>
                            </select>
                        </div>
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">Besaran Nilai (% / Rp)</label>
                            <input type="number"
                                   x-model="formPromo.value"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-xl font-mono focus:outline-none focus:border-[#0052cc]" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">Tanggal Mulai</label>
                            <input type="date"
                                   x-model="formPromo.periodStart"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-xl" />
                        </div>
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">Tanggal Selesai</label>
                            <input type="date"
                                   x-model="formPromo.periodEnd"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-xl" />
                        </div>
                    </div>

                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Status Awal</label>
                        <select x-model="formPromo.status" class="w-full px-3 py-2 border border-gray-300 rounded-xl">
                            <option value="AKTIF">AKTIF (Langsung Berjalan)</option>
                            <option value="MENDATANG">MENDATANG (Terjadwal)</option>
                            <option value="BERAKHIR">BERAKHIR (Draft / Arsip)</option>
                        </select>
                    </div>

                    <div class="pt-2 flex justify-end gap-2">
                        <button type="button"
                                @click="showAddModal = false"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold cursor-pointer">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-5 py-2 bg-[#0052cc] hover:bg-blue-700 text-white rounded-xl font-bold cursor-pointer">
                            Simpan Promo
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </template>

    <!-- MODAL EDIT PROMO -->
    <template x-teleport="body">
        <div x-show="showEditModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div @click.away="showEditModal = false"
                 class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-lg overflow-hidden animate-scale-up">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-rounded text-amber-600 text-[22px]">edit</span>
                        <h3 class="font-bold text-gray-900 text-sm">Edit Data Promosi</h3>
                    </div>
                    <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <template x-if="editingPromo">
                    <form @submit.prevent="saveEditPromo()" class="p-6 space-y-4 text-xs font-sans">
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Nama Promo</label>
                        <input type="text"
                               x-model="editingPromo.name"
                               required
                               class="w-full px-3.5 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-[#0052cc]" />
                    </div>

                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Keterangan / Syarat Ketentuan</label>
                        <input type="text"
                               x-model="editingPromo.condition"
                               class="w-full px-3.5 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-[#0052cc]" />
                    </div>

                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Label Diskon Tampil</label>
                        <input type="text"
                               x-model="editingPromo.typeLabel"
                               class="w-full px-3 py-2 border border-gray-300 rounded-xl font-mono focus:outline-none focus:border-[#0052cc]" />
                    </div>

                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Teks Periode</label>
                        <input type="text"
                               x-model="editingPromo.period"
                               class="w-full px-3 py-2 border border-gray-300 rounded-xl" />
                    </div>

                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Status Promo</label>
                        <select x-model="editingPromo.status" class="w-full px-3 py-2 border border-gray-300 rounded-xl">
                            <option value="AKTIF">AKTIF</option>
                            <option value="MENDATANG">MENDATANG</option>
                            <option value="BERAKHIR">BERAKHIR</option>
                        </select>
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

    <!-- MODAL HAPUS PROMO DENGAN KETIK 'hapus' -->
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
                        <h3 class="font-bold text-gray-900 text-sm">Hapus Promosi</h3>
                    </div>
                    <button @click="showDeleteModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs font-sans">
                    <p class="text-gray-600">
                        Anda akan menghapus promo <strong class="text-gray-900" x-text="selectedPromo?.name"></strong> secara permanen.
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
                                @click="confirmDeletePromo()"
                                :disabled="deleteInputText.trim().toLowerCase() !== 'hapus'"
                                :class="deleteInputText.trim().toLowerCase() === 'hapus' ? 'bg-red-600 hover:bg-red-700 text-white cursor-pointer' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                                class="px-5 py-2 rounded-xl font-bold transition-colors">
                            Hapus Promo
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </template>

    <!-- MODAL ATURAN NEAR-EXPIRY -->
    <template x-teleport="body">
        <div x-show="showRuleModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div @click.away="showRuleModal = false"
                 class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-md overflow-hidden animate-scale-up">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-rounded text-amber-600 text-[22px]">tune</span>
                        <h3 class="font-bold text-gray-900 text-sm">Konfigurasi Aturan Diskon Expired</h3>
                    </div>
                    <button @click="showRuleModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs font-sans">
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Diskon Near-Expiry (H-7 sebelum kadaluarsa)</label>
                        <div class="flex items-center gap-2">
                            <input type="number"
                                   x-model="expirySettings.h7Percent"
                                   class="w-24 px-3 py-2 border border-gray-300 rounded-xl font-mono text-center font-bold" />
                            <span class="font-bold text-gray-700">%</span>
                        </div>
                    </div>

                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Diskon Critical (H-3 sebelum kadaluarsa)</label>
                        <div class="flex items-center gap-2">
                            <input type="number"
                                   x-model="expirySettings.h3Percent"
                                   class="w-24 px-3 py-2 border border-gray-300 rounded-xl font-mono text-center font-bold text-red-600" />
                            <span class="font-bold text-gray-700">%</span>
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end gap-2">
                        <button type="button"
                                @click="showRuleModal = false"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold cursor-pointer">
                            Tutup
                        </button>
                        <button type="button"
                                @click="saveSettings(); showRuleModal = false; triggerToast('Aturan diskon expired berhasil disimpan!')"
                                class="px-5 py-2 bg-[#0052cc] hover:bg-blue-700 text-white rounded-xl font-bold cursor-pointer">
                            Simpan Aturan
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
