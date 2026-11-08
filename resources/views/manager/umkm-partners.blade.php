@extends('layouts.app')

@section('title', 'Manajemen Konsinyasi & Mitra UMKM - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    activeTab: 'partners', // 'partners' | 'report'
    
    // Partners State
    partners: [],
    partnerSummary: {
        total_partners: 0,
        active_partners: 0,
        total_consignment_skus: 0,
        average_commission: 15
    },
    searchPartner: '',
    statusPartner: 'all',
    isLoadingPartners: false,

    // Report State
    month: '{{ date('Y-m') }}',
    selectedSupplierId: 'all',
    reportSummary: {
        total_gross_sales: 0,
        total_coop_commission: 0,
        total_umkm_net_share: 0,
        total_sold_units: 0,
        active_consignment_skus: 0
    },
    reportRows: [],
    isLoadingReport: false,

    // Modals
    showPartnerModal: false,
    partnerModalMode: 'create', // 'create' | 'edit'
    partnerFormData: {
        id: null,
        name: '',
        owner_name: '',
        phone: '',
        address: '',
        category: 'Makanan Ringan',
        commission_percent: 15.00,
        settlement_period: 'BULANAN',
        bank_name: 'BRI',
        bank_account: '',
        status: 'AKTIF',
        notes: ''
    },
    partnerFormErrors: {},

    showSettlementModal: false,
    settlementSupplier: null,
    settlementNotes: '',
    isSettling: false,

    // Toast
    toastMessage: '',
    toastType: 'success',
    showToast: false,

    async initUmkm() {
        await this.loadPartners();
        await this.loadReport();
    },

    async loadPartners() {
        this.isLoadingPartners = true;
        try {
            const params = new URLSearchParams({
                search: this.searchPartner.trim(),
                status: this.statusPartner
            });
            const res = await fetch(`/api/consignment/partners?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.partners = json.data;
                    this.partnerSummary = json.summary;
                }
            }
        } catch (e) {
            console.error('Error fetching partners:', e);
            this.triggerToast('Gagal memuat mitra UMKM.', 'error');
        } finally {
            this.isLoadingPartners = false;
        }
    },

    async loadReport() {
        this.isLoadingReport = true;
        try {
            const params = new URLSearchParams({
                month: this.month,
                supplier_id: this.selectedSupplierId
            });
            const res = await fetch(`/api/consignment/report?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.reportSummary = json.summary;
                    this.reportRows = json.data;
                }
            }
        } catch (e) {
            console.error('Error fetching consignment report:', e);
            this.triggerToast('Gagal memuat laporan bagi hasil konsinyasi.', 'error');
        } finally {
            this.isLoadingReport = false;
        }
    },

    openCreatePartner() {
        this.partnerModalMode = 'create';
        this.partnerFormErrors = {};
        this.partnerFormData = {
            id: null,
            name: '',
            owner_name: '',
            phone: '',
            address: '',
            category: 'Makanan Ringan',
            commission_percent: 15.00,
            settlement_period: 'MINGGUAN',
            bank_name: 'BRI',
            bank_account: '',
            status: 'AKTIF',
            notes: 'Mitra UMKM Titipan Baru'
        };
        this.showPartnerModal = true;
    },

    openEditPartner(p) {
        this.partnerModalMode = 'edit';
        this.partnerFormErrors = {};
        this.partnerFormData = {
            id: p.id,
            name: p.name,
            owner_name: p.owner_name || '',
            phone: p.phone || '',
            address: p.address || '',
            category: p.category || 'Makanan Ringan',
            commission_percent: p.commission_percent,
            settlement_period: p.settlement_period || 'BULANAN',
            bank_name: p.bank_name || '',
            bank_account: p.bank_account || '',
            status: p.status,
            notes: p.notes || ''
        };
        this.showPartnerModal = true;
    },

    async savePartner() {
        this.partnerFormErrors = {};
        if (!this.partnerFormData.name || !this.partnerFormData.name.trim()) this.partnerFormErrors.name = 'Nama UMKM / Usaha wajib diisi.';
        if (Object.keys(this.partnerFormErrors).length > 0) return;

        this.isLoadingPartners = true;
        try {
            const url = this.partnerModalMode === 'create' ? '/api/consignment/partners' : `/api/consignment/partners/${this.partnerFormData.id}`;
            const method = this.partnerModalMode === 'create' ? 'POST' : 'PUT';

            const res = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(this.partnerFormData)
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.showPartnerModal = false;
                this.triggerToast(json.message);
                await this.loadPartners();
            } else {
                this.triggerToast(json.message || 'Gagal menyimpan mitra UMKM.', 'error');
            }
        } catch (e) {
            this.triggerToast('Koneksi terputus.', 'error');
        } finally {
            this.isLoadingPartners = false;
        }
    },

    async deletePartner(p) {
        if (!confirm(`Hapus mitra UMKM [${p.name}]?`)) return;
        try {
            const res = await fetch(`/api/consignment/partners/${p.id}`, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            const json = await res.json();
            if (res.ok && json.success) {
                this.triggerToast(json.message);
                await this.loadPartners();
            }
        } catch (e) {
            this.triggerToast('Gagal menghapus.', 'error');
        }
    },

    openSettlement(supplierId, supplierName) {
        this.settlementSupplier = { id: supplierId, name: supplierName };
        this.settlementNotes = `Pembayaran bagi hasil konsinyasi periode ${this.month}`;
        this.showSettlementModal = true;
    },

    async processSettlement() {
        if (!this.settlementSupplier) return;
        this.isSettling = true;

        try {
            const res = await fetch('/api/consignment/settlement', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    supplier_id: this.settlementSupplier.id,
                    period_month: this.month,
                    notes: this.settlementNotes
                })
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.showSettlementModal = false;
                this.triggerToast(json.message);
                await this.loadReport();
            } else {
                this.triggerToast(json.message || 'Gagal membukukan settlement.', 'error');
            }
        } catch (e) {
            this.triggerToast('Terjadi kesalahan jaringan.', 'error');
        } finally {
            this.isSettling = false;
        }
    },

    triggerToast(msg, type = 'success') {
        this.toastMessage = msg;
        this.toastType = type;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3500);
    }
}"
x-init="initOpening(); initUmkm();"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden relative">

    <!-- Sidebar -->
    <x-manager-sidebar active="umkm" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        
        <!-- Header -->
        <header class="h-[72px] bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-3">
                <div class="inline-flex p-1 bg-gray-100 rounded-xl">
                    <button type="button"
                            @click="activeTab = 'partners'"
                            :class="activeTab === 'partners' ? 'bg-white text-[#0052cc] shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                            class="px-4 py-2 rounded-lg text-xs transition-all flex items-center gap-2">
                        <span class="material-symbols-rounded text-[18px]">handshake</span>
                        <span>Daftar Mitra UMKM Lokal</span>
                    </button>
                    <button type="button"
                            @click="activeTab = 'report'; loadReport();"
                            :class="activeTab === 'report' ? 'bg-white text-[#0052cc] shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                            class="px-4 py-2 rounded-lg text-xs transition-all flex items-center gap-2">
                        <span class="material-symbols-rounded text-[18px]">account_balance_wallet</span>
                        <span>Laporan Bagi Hasil Konsinyasi</span>
                    </button>
                </div>
            </div>

            <div class="flex items-center gap-4 text-gray-500">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                    Konsinyasi UMKM Lokal
                </span>
            </div>
        </header>

        <!-- Workspace -->
        <div class="flex-1 overflow-y-auto p-8 flex flex-col">
            
            <!-- ============================================== -->
            <!-- TAB 1: DAFTAR MITRA UMKM                       -->
            <!-- ============================================== -->
            <div x-show="activeTab === 'partners'" class="space-y-6">
                <!-- 4 KPI Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-center">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">TOTAL MITRA UMKM</span>
                            <span class="material-symbols-rounded text-[20px] text-amber-600">store</span>
                        </div>
                        <div class="mt-2">
                            <h3 class="text-[26px] font-black text-gray-900" x-text="partnerSummary.total_partners">0</h3>
                            <p class="text-[11px] text-gray-400 mt-1" x-text="partnerSummary.active_partners + ' Mitra Aktif'"></p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-center">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">PRODUK KONSINYASI</span>
                            <span class="material-symbols-rounded text-[20px] text-[#0052cc]">inventory_2</span>
                        </div>
                        <div class="mt-2">
                            <h3 class="text-[26px] font-black text-[#0052cc]" x-text="partnerSummary.total_consignment_skus">0</h3>
                            <p class="text-[11px] text-gray-400 mt-1">SKU titipan di rak toko</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-center">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">RATA-RATA KOMISI</span>
                            <span class="material-symbols-rounded text-[20px] text-purple-600">percent</span>
                        </div>
                        <div class="mt-2">
                            <h3 class="text-[26px] font-black text-purple-600" x-text="partnerSummary.average_commission + '%'">15%</h3>
                            <p class="text-[11px] text-gray-400 mt-1">Bagian milik Koperasi</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-center">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">BINAAN KOPERASI</span>
                            <span class="material-symbols-rounded text-[20px] text-emerald-600">verified</span>
                        </div>
                        <div class="mt-2">
                            <h3 class="text-[20px] font-black text-emerald-700">PT Indo Lampung Perkasa</h3>
                            <p class="text-[11px] text-gray-400 mt-1">Pemberdayaan warga sekitar kebun</p>
                        </div>
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="flex flex-col sm:flex-row items-center gap-3">
                    <div class="relative flex-1 w-full">
                        <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                        <input type="text"
                               x-model="searchPartner"
                               @input.debounce.300ms="loadPartners()"
                               placeholder="Cari kode UMKM, nama usaha, pemilik..."
                               class="w-full pl-9 pr-3 py-2 bg-white rounded-xl border border-gray-200 text-xs focus:outline-none focus:border-[#0052cc]">
                    </div>

                    <button type="button"
                            @click="openCreatePartner()"
                            class="px-4 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 shrink-0">
                        <span class="material-symbols-rounded text-[18px]">add</span>
                        <span>Tambah Mitra UMKM</span>
                    </button>
                </div>

                <!-- Partners Table -->
                <div class="bg-white rounded-2xl border border-gray-200/90 shadow-xs overflow-hidden">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 font-bold uppercase tracking-wider text-[11px]">
                            <tr>
                                <th class="px-5 py-3.5">KODE &amp; NAMA USAHA UMKM</th>
                                <th class="px-5 py-3.5">PEMILIK &amp; KONTAK</th>
                                <th class="px-5 py-3.5">KATEGORI PRODUK</th>
                                <th class="px-5 py-3.5 text-center">KOMISI KOPERASI</th>
                                <th class="px-5 py-3.5 text-center">TOTAL SKU TITIPAN</th>
                                <th class="px-5 py-3.5">REKENING BANK PENCAIRAN</th>
                                <th class="px-5 py-3.5 text-center">STATUS</th>
                                <th class="px-5 py-3.5 text-center">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <template x-for="p in partners" :key="p.id">
                                <tr class="hover:bg-amber-50/20 transition-colors">
                                    <td class="px-5 py-3.5">
                                        <div class="font-bold text-gray-900" x-text="p.name"></div>
                                        <span class="font-mono text-[10.5px] text-gray-400" x-text="p.code"></span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="font-semibold text-gray-800" x-text="p.owner_name || '-'"></div>
                                        <div class="text-[11px] text-gray-400" x-text="p.phone || '-'"></div>
                                    </td>
                                    <td class="px-5 py-3.5 font-semibold text-gray-700" x-text="p.category"></td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="inline-block px-2.5 py-0.5 rounded-full font-mono font-bold text-xs bg-purple-50 text-purple-700 border border-purple-200"
                                              x-text="p.commission_percent + '%'">
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-center font-mono font-bold" x-text="(p.products_count || 0) + ' SKU'"></td>
                                    <td class="px-5 py-3.5 font-mono text-[11px]">
                                        <span class="font-bold text-gray-800" x-text="p.bank_name"></span>: <span x-text="p.bank_account || '-'"></span>
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border"
                                              :class="p.status === 'AKTIF' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200'"
                                              x-text="p.status">
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                        <button type="button" @click="openEditPartner(p)" class="p-1.5 text-gray-400 hover:text-gray-900 rounded-lg">
                                            <span class="material-symbols-rounded text-[18px]">edit</span>
                                        </button>
                                        <button type="button" @click="deletePartner(p)" class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg">
                                            <span class="material-symbols-rounded text-[18px]">delete</span>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- TAB 2: LAPORAN BAGI HASIL KONSINYASI           -->
            <!-- ============================================== -->
            <div x-show="activeTab === 'report'" class="space-y-6" style="display: none;">
                <!-- 3 Summary KPI Cards: Gross Sales vs Koperasi Commission vs UMKM Share -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <!-- Total Gross -->
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs flex flex-col justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">TOTAL OMZET PENJUALAN KOTOR</span>
                        <div class="mt-2">
                            <h3 class="text-[22px] font-mono font-black text-gray-900" x-text="formatRupiah(reportSummary.total_gross_sales)">Rp 0</h3>
                            <p class="text-[11px] text-gray-400 mt-1" x-text="reportSummary.total_sold_units + ' Unit Terjual di Kasir'"></p>
                        </div>
                    </div>

                    <!-- Koperasi Commission -->
                    <div class="bg-white rounded-2xl border border-purple-200 p-5 shadow-xs flex flex-col justify-between bg-purple-50/20">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-purple-700">BAGIAN KOMISI KOPERASI</span>
                        <div class="mt-2">
                            <h3 class="text-[22px] font-mono font-black text-purple-700" x-text="formatRupiah(reportSummary.total_coop_commission)">Rp 0</h3>
                            <p class="text-[11px] text-purple-600 mt-1">Pendapatan bagi hasil Koperasi</p>
                        </div>
                    </div>

                    <!-- UMKM Net Share -->
                    <div class="bg-white rounded-2xl border border-emerald-200 p-5 shadow-xs flex flex-col justify-between bg-emerald-50/20">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">HAK BERSIH MILIK UMKM</span>
                        <div class="mt-2">
                            <h3 class="text-[22px] font-mono font-black text-emerald-700" x-text="formatRupiah(reportSummary.total_umkm_net_share)">Rp 0</h3>
                            <p class="text-[11px] text-emerald-600 mt-1">Wajib dicairkan / ditransfer ke mitra</p>
                        </div>
                    </div>
                </div>

                <!-- Filter Period -->
                <div class="bg-white rounded-2xl border border-gray-200/90 p-4 shadow-xs flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-gray-500">Periode Bulan:</span>
                        <input type="month"
                               x-model="month"
                               @change="loadReport()"
                               class="px-3 py-1.5 bg-gray-50 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 focus:outline-none">
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-gray-500">Filter Mitra:</span>
                        <select x-model="selectedSupplierId"
                                @change="loadReport()"
                                class="px-3 py-1.5 bg-gray-50 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 focus:outline-none">
                            <option value="all">Semua Mitra UMKM</option>
                            <template x-for="p in partners" :key="p.id">
                                <option :value="p.id" x-text="p.name"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <!-- Report Table -->
                <div class="bg-white rounded-2xl border border-gray-200/90 shadow-xs overflow-hidden">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 font-bold uppercase tracking-wider text-[11px]">
                            <tr>
                                <th class="px-5 py-3.5">PRODUK TITIPAN &amp; SKU</th>
                                <th class="px-5 py-3.5">MITRA UMKM</th>
                                <th class="px-5 py-3.5 text-center">SISA STOK</th>
                                <th class="px-5 py-3.5 text-center">TERJUAL</th>
                                <th class="px-5 py-3.5 text-right">TOTAL OMZET KOTOR</th>
                                <th class="px-5 py-3.5 text-right text-purple-700">BAGIAN KOMISI KOPKAR</th>
                                <th class="px-5 py-3.5 text-right text-emerald-700">BAGIAN BERSIH UMKM</th>
                                <th class="px-5 py-3.5 text-center">AKSI PENCAIRAN</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <template x-if="reportRows.length === 0">
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                                        Tidak ada data penjualan konsinyasi pada periode ini.
                                    </td>
                                </tr>
                            </template>
                            <template x-for="r in reportRows" :key="r.product_id">
                                <tr class="hover:bg-gray-50/50">
                                    <td class="px-5 py-3.5">
                                        <div class="font-bold text-gray-900" x-text="r.name"></div>
                                        <span class="font-mono text-[10px] text-gray-400" x-text="r.sku"></span>
                                    </td>
                                    <td class="px-5 py-3.5 font-semibold text-gray-800" x-text="r.supplier_name"></td>
                                    <td class="px-5 py-3.5 text-center font-mono font-bold" x-text="r.stock"></td>
                                    <td class="px-5 py-3.5 text-center font-mono font-bold" x-text="r.units_sold"></td>
                                    <td class="px-5 py-3.5 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(r.gross_sales)"></td>
                                    <td class="px-5 py-3.5 text-right font-mono font-bold text-purple-700">
                                        <div x-text="formatRupiah(r.coop_commission)"></div>
                                        <span class="text-[9.5px] text-purple-500" x-text="r.commission_percent + '%'"></span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono font-black text-emerald-700" x-text="formatRupiah(r.umkm_net_share)"></td>
                                    <td class="px-5 py-3.5 text-center">
                                        <button type="button"
                                                @click="openSettlement(r.supplier_id, r.supplier_name)"
                                                :disabled="r.umkm_net_share <= 0"
                                                class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg font-bold text-[11px] transition-colors disabled:opacity-30">
                                            Cairkan Bagi Hasil
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Modal Tambah / Edit Mitra -->
    <div x-show="showPartnerModal"
         class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-gray-200"
             @click.outside="showPartnerModal = false">
            <h3 class="text-base font-black text-gray-900 mb-1" x-text="partnerModalMode === 'create' ? 'Daftarkan Mitra UMKM Lokal' : 'Edit Mitra UMKM'"></h3>
            <p class="text-xs text-gray-400 mb-4">Pengaturan bagi hasil omzet titipan konsinyasi.</p>

            <form @submit.prevent="savePartner()" class="space-y-4 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Nama Usaha / Merk UMKM <span class="text-red-500">*</span></label>
                    <input type="text" x-model="partnerFormData.name" placeholder="Contoh: Dapur Bu Eni" class="w-full px-3 py-2 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc]">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Nama Pemilik</label>
                        <input type="text" x-model="partnerFormData.owner_name" placeholder="Nama pemilik" class="w-full px-3 py-2 rounded-xl border border-gray-300 focus:outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">No. WhatsApp / HP</label>
                        <input type="text" x-model="partnerFormData.phone" placeholder="0812-xxxx-xxxx" class="w-full px-3 py-2 rounded-xl border border-gray-300 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-purple-700 mb-1">Bagian Komisi Koperasi (%)</label>
                        <input type="number" step="0.5" x-model="partnerFormData.commission_percent" class="w-full px-3 py-2 rounded-xl border border-gray-300 font-mono font-bold text-purple-700 focus:outline-none">
                        <p class="text-[10px] text-gray-400 mt-1">Hak bersih UMKM = <span x-text="(100 - partnerFormData.commission_percent) + '%'"></span></p>
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Periode Settlement</label>
                        <select x-model="partnerFormData.settlement_period" class="w-full px-3 py-2 rounded-xl border border-gray-300 font-semibold focus:outline-none">
                            <option value="MINGGUAN">Mingguan</option>
                            <option value="BULANAN">Bulanan</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Bank Penampung</label>
                        <input type="text" x-model="partnerFormData.bank_name" placeholder="BRI, Mandiri, BSI" class="w-full px-3 py-2 rounded-xl border border-gray-300 focus:outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">No. Rekening</label>
                        <input type="text" x-model="partnerFormData.bank_account" placeholder="Nomor rekening" class="w-full px-3 py-2 rounded-xl border border-gray-300 font-mono focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Alamat / Lokasi Usaha</label>
                    <input type="text" x-model="partnerFormData.address" placeholder="Alamat mitra" class="w-full px-3 py-2 rounded-xl border border-gray-300 focus:outline-none">
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="showPartnerModal = false" class="px-4 py-2 border rounded-xl font-bold text-gray-600">Batal</button>
                    <button type="submit" :disabled="isLoadingPartners" class="px-5 py-2 bg-[#0052cc] text-white rounded-xl font-bold">Simpan Mitra</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Settlement -->
    <div x-show="showSettlementModal"
         class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-gray-200"
             @click.outside="showSettlementModal = false">
            <h3 class="text-base font-black text-gray-900 mb-1">Bukukan Bagi Hasil Konsinyasi</h3>
            <p class="text-xs text-gray-400 mb-4" x-text="settlementSupplier ? settlementSupplier.name : ''"></p>

            <div class="space-y-4 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Catatan Pencairan</label>
                    <input type="text" x-model="settlementNotes" class="w-full px-3 py-2 rounded-xl border border-gray-300">
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="showSettlementModal = false" class="px-4 py-2 border rounded-xl font-bold text-gray-600">Batal</button>
                    <button type="button" @click="processSettlement()" :disabled="isSettling" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold">Konfirmasi Pembayaran</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div x-show="showToast"
         class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-2xl shadow-xl border text-xs font-bold"
         :class="toastType === 'error' ? 'bg-red-900 text-white border-red-800' : 'bg-gray-900 text-white border-gray-800'"
         style="display: none;">
        <span class="material-symbols-rounded text-emerald-400" x-show="toastType === 'success'">check_circle</span>
        <span class="material-symbols-rounded text-red-400" x-show="toastType === 'error'">error</span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
