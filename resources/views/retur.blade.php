@extends('layouts.app')

@section('title', 'Manajemen Retur Barang - ILP POS')

@section('content')
<div x-data="{
    activeTab: 'all',
    searchQuery: '',
    directionFilter: 'all',
    conditionFilter: 'all',
    statusFilter: 'all',
    draftDirection: 'toko_ke_gudang',
    draftSku: '899275321004 - Indomie Goreng Spesial 85g',
    draftQty: 24,
    draftUnit: 'Pcs',
    draftCondition: 'Kemasan Rusak / Pecah',
    draftUseFleet: true,
    draftPhotoName: 'kemasan_robek.jpg',
    showModal: false,
    modalMode: 'toko_ke_gudang',
    newDocSku: '',
    newDocQty: 12,
    newDocUnit: 'Karton',
    newDocCondition: 'RUSAK / BOCOR',
    newDocReason: '',
    newDocDriver: 'Andi Pratama (B 9481 UQ)',
    toastMessage: '',
    showToast: false,
    triggerToast(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3200);
    },
    items: [],

    initReturns() {
        const stored = localStorage.getItem('pos_ilp_manager_returns_v1');
        if (stored) {
            try {
                this.items = JSON.parse(stored);
            } catch(e) {
                this.items = this.getDefaultReturns();
            }
        } else {
            this.items = this.getDefaultReturns();
            this.saveReturns();
        }
    },

    saveReturns() {
        localStorage.setItem('pos_ilp_manager_returns_v1', JSON.stringify(this.items));
    },

    getDefaultReturns() {
        return [
            {
                id: 1,
                docNo: '#RTR-TK-2024-0892',
                date: '24 Mei 2024 • 09:42 WIB',
                ref: 'Ref: INV-2024/CAB-A',
                direction: 'toko_ke_gudang',
                directionLabel: 'TOKO → GUDANG',
                conditionType: 'rusak',
                conditionLabel: 'RUSAK / BOCOR',
                conditionDesc: 'Karton rembes saat transit delivery rak',
                products: [
                    { name: 'Minyak Goreng Sawit 2L', qty: '15 Pouch', color: 'text-[#dc2626]' },
                    { name: 'Susu UHT Cokelat 1L', qty: '24 Tetra', color: 'text-[#dc2626]' }
                ],
                extraItems: '+ 2 Item Cacat lainnya',
                subtotalLabel: 'Subtotal:',
                subtotal: 'Rp 3.420.000',
                truckFee: '-Rp 500.000',
                truckFeeFree: false,
                netLabel: 'Net:',
                netAmount: 'Rp 2.920.000',
                plate: 'B 9481 UQ',
                driver: 'Andi Pratama',
                statusKey: 'cek_fisik',
                statusLabel: 'CEK FISIK GUDANG',
                statusSub: 'ETA: 13:30 WIB',
                needsApproval: true
            },
            {
                id: 2,
                docNo: '#RTR-TK-2024-0891',
                date: '23 Mei 2024 • 16:15 WIB',
                ref: 'Ref: STK-ED-2405',
                direction: 'toko_ke_gudang',
                directionLabel: 'TOKO → GUDANG',
                conditionType: 'ed',
                conditionLabel: 'KEDALUWARSA / H-14',
                conditionDesc: 'ED Bulan Juni 2024 batch A12',
                products: [
                    { name: 'Yogurt Drink Strawberry 250ml', qty: '48 Btl', color: 'text-[#d97706]' },
                    { name: 'Roti Tawar Gandum Spesial', qty: '16 Pack', color: 'text-[#d97706]' }
                ],
                extraItems: '',
                subtotalLabel: 'Subtotal:',
                subtotal: 'Rp 1.880.000',
                truckFee: '-Rp 500.000',
                truckFeeFree: false,
                netLabel: 'Net:',
                netAmount: 'Rp 1.380.000',
                plate: 'B 9102 UQ',
                driver: 'Rahmat Hidayat',
                statusKey: 'selesai',
                statusLabel: 'SELESAI (BAST)',
                statusSub: 'Diterima: Joko (WH-Staff)',
                needsApproval: false
            },
            {
                id: 3,
                docNo: '#REP-GD-2024-0310',
                date: '24 Mei 2024 • 11:20 WIB',
                ref: 'Klaim: #RTR-TK-2024-0880',
                direction: 'gudang_ke_toko',
                directionLabel: 'GUDANG → TOKO',
                conditionType: 'garansi',
                conditionLabel: 'PENGGANTIAN GARANSI',
                conditionDesc: 'Barang Baru Kompensasi Supplier',
                products: [
                    { name: 'Kecap Manis Refill 550ml', qty: '30 Pouch', color: 'text-[#16a34a]' },
                    { name: 'Biskuit Cokelat Kaleng 650g', qty: '12 Kaleng', color: 'text-[#16a34a]' }
                ],
                extraItems: '',
                subtotalLabel: 'Nilai Barang:',
                subtotal: 'Rp 2.150.000',
                truckFee: 'GRATIS (SOP)',
                truckFeeFree: true,
                netLabel: 'Total:',
                netAmount: 'Rp 2.150.000',
                plate: 'B 9844 XZ',
                driver: 'Dedi Kurnia',
                statusKey: 'perjalanan',
                statusLabel: 'DALAM PERJALANAN',
                statusSub: 'Tol Barat KM 14',
                needsApproval: false
            },
            {
                id: 4,
                docNo: '#RTR-TK-2024-0890',
                date: '22 Mei 2024 • 14:05 WIB',
                ref: 'Ref: OVER-STOCK-09',
                direction: 'toko_ke_gudang',
                directionLabel: 'TOKO → GUDANG',
                conditionType: 'overstock',
                conditionLabel: 'MUTASI OVERSTOCK',
                conditionDesc: 'Penyesuaian buffer ruang simpan rak',
                products: [
                    { name: 'Deterjen Bubuk Premium 800g', qty: '60 Pack', color: 'text-[#0052cc]' },
                    { name: 'Pembersih Lantai Lavender 750ml', qty: '36 Btl', color: 'text-[#0052cc]' }
                ],
                extraItems: '',
                subtotalLabel: 'Subtotal:',
                subtotal: 'Rp 4.120.000',
                truckFee: '-Rp 500.000',
                truckFeeFree: false,
                netLabel: 'Net:',
                netAmount: 'Rp 3.620.000',
                plate: 'B 9481 UQ',
                driver: 'Andi Pratama',
                statusKey: 'disetujui',
                statusLabel: 'DISETUJUI SPV',
                statusSub: 'Jadwal Besok Pagi',
                needsApproval: true
            }
        ];
    },
    get filteredItems() {
        return this.items.filter(item => {
            if (this.directionFilter !== 'all' && item.direction !== this.directionFilter) return false;
            if (this.conditionFilter !== 'all' && item.conditionType !== this.conditionFilter) return false;
            if (this.statusFilter !== 'all' && item.statusKey !== this.statusFilter) return false;
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                const matchDoc = item.docNo.toLowerCase().includes(q) || item.ref.toLowerCase().includes(q);
                const matchDriver = item.driver.toLowerCase().includes(q) || item.plate.toLowerCase().includes(q);
                const matchProd = item.products.some(p => p.name.toLowerCase().includes(q));
                return matchDoc || matchDriver || matchProd;
            }
            return true;
        });
    },
    approveItem(item) {
        item.statusKey = 'disetujui';
        item.statusLabel = 'DISETUJUI MANAGER';
        item.statusSub = 'Siap Muat ke Gudang DC';
        item.needsApproval = false;
        this.saveReturns();
        this.triggerToast('Pengajuan ' + item.docNo + ' berhasil di-Approve oleh Manager.');
    },
    rejectItem(item) {
        item.statusKey = 'ditolak';
        item.statusLabel = 'DITOLAK MANAGER';
        item.statusSub = 'Dikembalikan ke Staf Toko';
        item.needsApproval = false;
        this.saveReturns();
        this.triggerToast('Pengajuan ' + item.docNo + ' telah ditolak.');
    },
    confirmReceive(item) {
        item.statusKey = 'selesai';
        item.statusLabel = 'SELESAI (BAST)';
        item.statusSub = 'Diterima Manager Toko';
        this.saveReturns();
        this.triggerToast('Pengiriman Gudang ' + item.docNo + ' dikonfirmasi masuk stok toko.');
    },
    submitQuickDraft() {
        if (!this.draftSku.trim() || !this.draftQty) return;
        const isTokoToGudang = this.draftDirection === 'toko_ke_gudang';
        const codeNum = Math.floor(893 + Math.random() * 90);
        const docId = isTokoToGudang ? '#RTR-TK-2024-0' + codeNum : '#REP-GD-2024-0' + codeNum;
        const prodName = this.draftSku.includes('-') ? this.draftSku.split('-')[1].trim() : this.draftSku.trim();

        let condType = 'rusak';
        let condLabel = 'RUSAK / BOCOR';
        let prodColor = 'text-[#dc2626]';
        if (this.draftCondition.includes('Kedaluwarsa')) {
            condType = 'ed';
            condLabel = 'KEDALUWARSA / H-14';
            prodColor = 'text-[#d97706]';
        } else if (this.draftCondition.includes('Overstock')) {
            condType = 'overstock';
            condLabel = 'MUTASI OVERSTOCK';
            prodColor = 'text-[#0052cc]';
        } else if (!isTokoToGudang || this.draftCondition.includes('Penggantian')) {
            condType = 'garansi';
            condLabel = 'PENGGANTIAN GARANSI';
            prodColor = 'text-[#16a34a]';
        }

        this.items.unshift({
            id: Date.now(),
            docNo: docId,
            date: 'Baru saja • Hari Ini',
            ref: isTokoToGudang ? 'Ref: DRAFT-MGR-01' : 'Klaim: #RTR-TK-2024-0892',
            direction: this.draftDirection,
            directionLabel: isTokoToGudang ? 'TOKO → GUDANG' : 'GUDANG → TOKO',
            conditionType: condType,
            conditionLabel: condLabel,
            conditionDesc: isTokoToGudang ? 'Input cepat draft retur terverifikasi' : 'Pengiriman barang pengganti dari Gudang DC',
            products: [
                { name: prodName, qty: this.draftQty + ' ' + this.draftUnit, color: prodColor }
            ],
            extraItems: '',
            subtotalLabel: isTokoToGudang ? 'Subtotal:' : 'Nilai Barang:',
            subtotal: 'Rp 1.250.000',
            truckFee: (isTokoToGudang && this.draftUseFleet) ? '-Rp 500.000' : 'GRATIS (SOP)',
            truckFeeFree: !(isTokoToGudang && this.draftUseFleet),
            netLabel: isTokoToGudang ? 'Net:' : 'Total:',
            netAmount: (isTokoToGudang && this.draftUseFleet) ? 'Rp 750.000' : 'Rp 1.250.000',
            plate: isTokoToGudang ? 'B 9481 UQ' : 'B 9844 XZ',
            driver: isTokoToGudang ? 'Andi Pratama' : 'Dedi Kurnia',
            statusKey: isTokoToGudang ? 'cek_fisik' : 'perjalanan',
            statusLabel: isTokoToGudang ? 'MENUNGGU APPROVAL' : 'DALAM PERJALANAN',
            statusSub: isTokoToGudang ? 'Siap Di-approve Manager' : 'Dikirim dari Gudang DC',
            needsApproval: isTokoToGudang
        });
        this.saveReturns();

        this.triggerToast('Draft ' + docId + ' (' + (isTokoToGudang ? 'Toko → Gudang' : 'Gudang → Toko') + ') berhasil ditambahkan!');
    }
}"
x-init="initReturns()"
class="flex h-screen w-full bg-[#f8fafc] font-sans text-slate-800 overflow-hidden">

    <!-- Manager Sidebar -->
    <x-manager-sidebar active="retur" />

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden min-w-0">

        <!-- Top Navbar -->
        <header class="h-[64px] bg-white border-b border-slate-200/80 px-8 flex items-center justify-between shrink-0 z-10">
            <div class="relative w-full max-w-[380px]">
                <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                <input type="text" x-model="searchQuery" placeholder="Cari dokumen retur, SKU, atau armada..." class="w-full bg-[#f1f5f9] border border-transparent focus:border-blue-500 focus:bg-white rounded-xl pl-10 pr-4 py-2 text-[13px] font-medium text-slate-700 placeholder-slate-400 focus:outline-none transition-all">
            </div>
            <div class="flex items-center gap-4">
                <button class="relative w-9 h-9 rounded-full hover:bg-slate-100 flex items-center justify-center text-slate-600 transition-colors cursor-pointer">
                    <span class="material-symbols-rounded text-[21px]">notifications</span>
                    <span class="absolute top-1.5 right-2 w-2 h-2 rounded-full bg-orange-500 ring-2 ring-white"></span>
                </button>
                <button class="w-9 h-9 rounded-full hover:bg-slate-100 flex items-center justify-center text-slate-600 transition-colors cursor-pointer">
                    <span class="material-symbols-rounded text-[21px]">help_outline</span>
                </button>
            </div>
        </header>

        <!-- Scrollable Page Body -->
        <main class="flex-1 overflow-y-auto px-6 lg:px-8 py-6">
            <div class="max-w-[1440px] mx-auto space-y-5">

                <!-- Breadcrumb & Title Header -->
                <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-1.5 text-[10px] font-extrabold text-slate-400 tracking-wider uppercase mb-1.5">
                            <span>OPERASIONAL CABANG</span>
                            <span class="material-symbols-rounded text-[13px]">chevron_right</span>
                            <span>INVENTARIS & LOGISTIK</span>
                            <span class="material-symbols-rounded text-[13px]">chevron_right</span>
                            <span class="text-[#0052cc]">MANAJEMEN RETUR BARANG</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-[24px] lg:text-[26px] font-black text-[#0f172a] tracking-tight leading-tight">Manajemen Retur Barang Terpadu</h1>
                            <span class="inline-flex items-center gap-1.5 bg-[#dbeafe] text-[#1e40af] text-[12px] font-extrabold px-3 py-1 rounded-full border border-blue-200/80 shadow-2xs">
                                <span>Toko</span>
                                <span class="material-symbols-rounded text-[15px]">swap_horiz</span>
                                <span>Gudang</span>
                            </span>
                        </div>
                        <p class="text-[13px] text-slate-500 font-medium mt-1.5 max-w-2xl leading-relaxed">
                            Alur pengembalian dan relokasi produk dua arah antara Toko Cabang dan Gudang Pusat dengan pelacakan armada logistik internal serta audit kondisi fisik barang secara komprehensif.
                        </p>
                    </div>

                    <!-- ERP Sinkron Badge Card -->
                    <div class="bg-[#f4f7fe] border border-[#dce5fc] rounded-xl px-4 py-2.5 flex items-center gap-3 shrink-0 self-start shadow-2xs">
                        <div class="w-9 h-9 rounded-lg bg-[#e0eaff] text-[#0052cc] flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[20px]">cloud_done</span>
                        </div>
                        <div class="flex flex-col leading-tight">
                            <span class="text-[10px] font-extrabold text-slate-600 uppercase tracking-wider">ERP SINKRON</span>
                            <span class="text-[12px] font-extrabold text-[#16a34a] flex items-center gap-1 mt-0.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#16a34a]"></span>
                                Terhubung
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Blue Info Policy Banner -->
                <div class="bg-[#ebf2ff] border border-[#c7dcff] rounded-2xl p-4 lg:p-5 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 shadow-2xs">
                    <div class="flex items-start gap-3.5">
                        <div class="w-11 h-11 rounded-xl bg-[#0047b3] text-white flex items-center justify-center shrink-0 shadow-xs mt-0.5">
                            <span class="material-symbols-rounded text-[22px]">local_shipping</span>
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <h2 class="text-[14.5px] font-extrabold text-[#0f172a]">Kebijakan Logistik & Biaya Angkutan Retur</h2>
                                <span class="bg-[#0047b3] text-white text-[10px] font-extrabold px-2.5 py-0.5 rounded-md uppercase tracking-wider">SOP LOG-04</span>
                            </div>
                            <p class="text-[12.5px] text-slate-600 font-medium leading-relaxed mt-1 max-w-4xl">
                                Biaya ekspedisi flat <span class="font-extrabold text-[#0047b3] underline decoration-[#93c5fd] underline-offset-2">Rp 500.000</span> berlaku untuk setiap order retur <span class="font-extrabold text-slate-800">Toko &rarr; Gudang</span> per trip armada pick-up (dipotong otomatis dari nota retur cabang). Pengiriman <span class="font-extrabold text-slate-800">Gudang &rarr; Toko</span> (penggantian produk rusak/cacat pabrik) sepenuhnya <span class="font-extrabold text-[#15803d]">Bebas Biaya Logistik</span>.
                            </p>
                        </div>
                    </div>
                    <button @click="triggerToast('Membuka dokumen Panduan SOP LOG-04...')" class="bg-white hover:bg-slate-50 text-slate-800 border border-slate-200/90 px-4 py-2.5 rounded-xl text-[12px] font-extrabold flex items-center gap-2 shrink-0 shadow-2xs transition-all cursor-pointer">
                        <span class="material-symbols-rounded text-[17px] text-slate-600">menu_book</span>
                        <span>Panduan SOP</span>
                    </button>
                </div>

                <!-- 5 KPI Summary Cards (Clean, Non-Wrapping Layout) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
                    <!-- Card 1 -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-2xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider leading-snug">TOTAL NILAI RETUR<br>BLN INI</span>
                                <span class="material-symbols-rounded text-[18px] text-[#0052cc] shrink-0">inventory</span>
                            </div>
                            <div class="text-[20px] font-black text-[#0f172a] font-mono tracking-tight mt-2.5 whitespace-nowrap">
                                Rp 18.450.000
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="flex items-center justify-between text-[11px] mb-2">
                                <span class="text-slate-500 font-semibold">14 Dokumen Pengajuan</span>
                                <span class="font-extrabold text-[#16a34a] inline-flex items-center gap-0.5">▲ +4%</span>
                            </div>
                            <div class="w-full h-1 bg-slate-100 rounded-full overflow-hidden">
                                <div class="w-[72%] h-full bg-[#0047b3] rounded-full"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2 -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-2xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider leading-snug">KONDISI RUSAK /<br>CACAT</span>
                                <span class="w-2.5 h-2.5 rounded-full bg-[#dc2626] shrink-0 mt-1"></span>
                            </div>
                            <div class="text-[20px] font-black text-[#dc2626] font-mono tracking-tight mt-2.5 whitespace-nowrap">
                                Rp 4.250.000
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="flex items-center justify-between gap-1 text-[11px] mb-2">
                                <span class="text-slate-500 font-semibold truncate">32 SKU Teridentifikasi</span>
                                <span class="bg-[#fee2e2] text-[#b91c1c] text-[10px] font-extrabold px-2 py-0.5 rounded-md whitespace-nowrap shrink-0">Cacat Fisik</span>
                            </div>
                            <div class="w-full h-1 bg-slate-100 rounded-full overflow-hidden">
                                <div class="w-[38%] h-full bg-[#dc2626] rounded-full"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 3 -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-2xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider leading-snug">KEDALUWARSA /<br>DEKAT ED</span>
                                <span class="w-2.5 h-2.5 rounded-full bg-[#d97706] shrink-0 mt-1"></span>
                            </div>
                            <div class="text-[20px] font-black text-[#d97706] font-mono tracking-tight mt-2.5 whitespace-nowrap">
                                Rp 8.900.000
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="flex items-center justify-between gap-1 text-[11px] mb-2">
                                <span class="text-slate-500 font-semibold truncate">18 SKU Kritis</span>
                                <span class="bg-[#ffedd5] text-[#b45309] text-[10px] font-extrabold px-2 py-0.5 rounded-md whitespace-nowrap shrink-0">&lt; 30 Hari</span>
                            </div>
                            <div class="w-full h-1 bg-slate-100 rounded-full overflow-hidden">
                                <div class="w-[58%] h-full bg-[#d97706] rounded-full"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 4 -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-2xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider leading-snug">MUTASI<br>OVERSTOCK</span>
                                <span class="w-2.5 h-2.5 rounded-full bg-[#0052cc] shrink-0 mt-1"></span>
                            </div>
                            <div class="text-[20px] font-black text-[#0052cc] font-mono tracking-tight mt-2.5 whitespace-nowrap">
                                Rp 5.300.000
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="flex items-center justify-between gap-1 text-[11px] mb-2">
                                <span class="text-slate-500 font-semibold truncate">45 SKU Relokasi</span>
                                <span class="bg-[#dbeafe] text-[#1d4ed8] text-[10px] font-extrabold px-2 py-0.5 rounded-md whitespace-nowrap shrink-0">Rotasi DC</span>
                            </div>
                            <div class="w-full h-1 bg-slate-100 rounded-full overflow-hidden">
                                <div class="w-[45%] h-full bg-[#0052cc] rounded-full"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 5 -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-2xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider leading-snug">BIAYA ARMADA<br>TERPOTONG</span>
                                <span class="material-symbols-rounded text-[18px] text-slate-500 shrink-0">local_shipping</span>
                            </div>
                            <div class="text-[20px] font-black text-[#0f172a] font-mono tracking-tight mt-2.5 whitespace-nowrap">
                                Rp 1.500.000
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="flex items-center justify-between gap-1 text-[11px] mb-2">
                                <span class="text-slate-500 font-semibold truncate">3 Pengiriman Truk</span>
                                <span class="text-slate-700 font-extrabold font-mono whitespace-nowrap shrink-0">@ Rp 500k</span>
                            </div>
                            <div class="w-full h-1 bg-slate-100 rounded-full overflow-hidden">
                                <div class="w-[64%] h-full bg-slate-600 rounded-full"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Tabs & Action Buttons Bar (Fixed Truncated Tab!) -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-2.5 flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-3 shadow-2xs">
                    <!-- Left Tabs -->
                    <div class="flex flex-wrap items-center gap-2">
                        <button @click="activeTab = 'all'; directionFilter = 'all'"
                                :class="directionFilter === 'all' || directionFilter === 'toko_ke_gudang' ? 'bg-[#0047b3] text-white shadow-xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600'"
                                class="px-4 py-2.5 rounded-xl text-[12.5px] font-extrabold flex items-center gap-2.5 transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[17px]">outbox</span>
                            <span>Toko &rarr; Gudang (Return to DC)</span>
                            <span :class="directionFilter === 'all' || directionFilter === 'toko_ke_gudang' ? 'bg-white text-[#0047b3]' : 'bg-slate-200 text-slate-700'"
                                  class="text-[10.5px] font-extrabold px-2.5 py-0.5 rounded-full">4 Pengajuan</span>
                        </button>

                        <button @click="directionFilter = (directionFilter === 'gudang_ke_toko' ? 'all' : 'gudang_ke_toko')"
                                :class="directionFilter === 'gudang_ke_toko' ? 'bg-[#0047b3] text-white shadow-xs' : 'bg-white hover:bg-slate-50 text-slate-600 border border-slate-200/70'"
                                class="px-4 py-2.5 rounded-xl text-[12.5px] font-extrabold flex items-center gap-2.5 transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[17px]">move_to_inbox</span>
                            <span>Gudang &rarr; Toko (Penggantian / Kirim Barang)</span>
                            <span :class="directionFilter === 'gudang_ke_toko' ? 'bg-white text-[#0047b3]' : 'bg-emerald-50 text-[#15803d] border border-emerald-200'"
                                  class="text-[10.5px] font-extrabold px-2.5 py-0.5 rounded-full">1 Pengiriman</span>
                        </button>
                    </div>

                    <!-- Right Action Buttons -->
                    <div class="flex flex-wrap items-center gap-2.5">
                        <button @click="modalMode = 'gudang_ke_toko'; showModal = true"
                                class="bg-[#e2e8f0]/80 hover:bg-[#cbd5e1] text-[#0f172a] px-4 py-2.5 rounded-xl text-[12px] font-extrabold flex items-center gap-2 transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[17px]">archive</span>
                            <span>+ Penerimaan dari Gudang</span>
                        </button>
                        <button @click="modalMode = 'toko_ke_gudang'; showModal = true"
                                class="bg-[#0047b3] hover:bg-[#003994] text-white px-4 py-2.5 rounded-xl text-[12px] font-extrabold flex items-center gap-2 shadow-xs transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[17px]">add_circle</span>
                            <span>+ Buat Pengajuan Retur Baru</span>
                        </button>
                    </div>
                </div>

                <!-- Main Table Card -->
                <div class="bg-white border border-slate-200/80 rounded-2xl shadow-2xs overflow-hidden">

                    <!-- Filter Toolbar (Cleanly Aligned, Non-Cut Off) -->
                    <div class="p-4 lg:px-6 border-b border-slate-100 flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
                        <!-- Search Box -->
                        <div class="relative flex-1 max-w-md">
                            <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                            <input type="text" x-model="searchQuery" placeholder="Cari No. Dokumen, SKU, Produk, Driver..." class="w-full bg-[#f1f5f9] border border-transparent focus:border-blue-500 focus:bg-white rounded-xl pl-10 pr-4 py-2.5 text-[12.5px] font-medium text-slate-700 placeholder-slate-400 focus:outline-none transition-all">
                        </div>

                        <!-- Filter Controls -->
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Direction Filter -->
                            <div class="relative">
                                <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-[16px] pointer-events-none">swap_horiz</span>
                                <select x-model="directionFilter" class="bg-[#f1f5f9] hover:bg-slate-200/70 text-slate-700 text-[12px] font-bold pl-9 pr-7 py-2 rounded-xl appearance-none cursor-pointer focus:outline-none border border-transparent focus:border-blue-400">
                                    <option value="all">Toko ⇄ Gudang</option>
                                    <option value="toko_ke_gudang">Toko → Gudang</option>
                                    <option value="gudang_ke_toko">Gudang → Toko</option>
                                </select>
                                <span class="material-symbols-rounded absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 text-[16px] pointer-events-none">expand_more</span>
                            </div>

                            <!-- Condition Filter -->
                            <div class="relative">
                                <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-[16px] pointer-events-none">category</span>
                                <select x-model="conditionFilter" class="bg-[#f1f5f9] hover:bg-slate-200/70 text-slate-700 text-[12px] font-bold pl-9 pr-7 py-2 rounded-xl appearance-none cursor-pointer focus:outline-none border border-transparent focus:border-blue-400">
                                    <option value="all">Semua Kondisi</option>
                                    <option value="rusak">Rusak / Bocor</option>
                                    <option value="ed">Kedaluwarsa / H-14</option>
                                    <option value="garansi">Penggantian Garansi</option>
                                    <option value="overstock">Mutasi Overstock</option>
                                </select>
                                <span class="material-symbols-rounded absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 text-[16px] pointer-events-none">expand_more</span>
                            </div>

                            <!-- Status Filter -->
                            <div class="relative">
                                <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-[16px] pointer-events-none">verified</span>
                                <select x-model="statusFilter" class="bg-[#f1f5f9] hover:bg-slate-200/70 text-slate-700 text-[12px] font-bold pl-9 pr-7 py-2 rounded-xl appearance-none cursor-pointer focus:outline-none border border-transparent focus:border-blue-400">
                                    <option value="all">Semua Status</option>
                                    <option value="cek_fisik">Cek Fisik / Menunggu</option>
                                    <option value="disetujui">Disetujui SPV/Mgr</option>
                                    <option value="perjalanan">Dalam Perjalanan</option>
                                    <option value="selesai">Selesai (BAST)</option>
                                </select>
                                <span class="material-symbols-rounded absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 text-[16px] pointer-events-none">expand_more</span>
                            </div>

                            <!-- Date Badge -->
                            <div class="bg-[#f1f5f9] text-slate-700 text-[12px] font-bold px-3.5 py-2 rounded-xl flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[16px] text-slate-500">calendar_today</span>
                                <span>Mei 2024</span>
                            </div>

                            <!-- Reset Filter -->
                            <button @click="searchQuery = ''; directionFilter = 'all'; conditionFilter = 'all'; statusFilter = 'all'"
                                    title="Reset Filter"
                                    class="w-9 h-9 rounded-xl bg-[#f1f5f9] hover:bg-slate-200 text-slate-600 flex items-center justify-center transition-colors cursor-pointer">
                                <span class="material-symbols-rounded text-[18px]">filter_alt_off</span>
                            </button>
                        </div>
                    </div>

                    <!-- Responsive Table Container -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[1160px]">
                            <thead>
                                <tr class="bg-[#f8fafc] border-b border-slate-200/80 text-[10.5px] font-extrabold text-slate-500 uppercase tracking-wider">
                                    <th class="py-3.5 px-5 w-[165px]">NO. DOKUMEN & TANGGAL</th>
                                    <th class="py-3.5 px-4 w-[145px]">ARAH RETUR</th>
                                    <th class="py-3.5 px-4 w-[195px]">KONDISI / ALASAN</th>
                                    <th class="py-3.5 px-4 w-[240px]">BARANG & KUANTITAS</th>
                                    <th class="py-3.5 px-4 w-[185px]">KALKULASI FINANSIAL</th>
                                    <th class="py-3.5 px-4 w-[155px]">ARMADA & DRIVER</th>
                                    <th class="py-3.5 px-5 w-[195px]">STATUS & APPROVAL</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-[12.5px]">
                                <template x-for="item in filteredItems" :key="item.id">
                                    <tr class="hover:bg-slate-50/70 transition-colors align-top">
                                        <!-- 1. NO. DOKUMEN & TANGGAL -->
                                        <td class="py-4 px-5">
                                            <div class="font-mono font-extrabold text-[12.5px] whitespace-nowrap"
                                                 :class="item.direction === 'gudang_ke_toko' ? 'text-[#15803d]' : 'text-[#0052cc]'"
                                                 x-text="item.docNo"></div>
                                            <div class="text-[11px] font-medium text-slate-500 mt-1" x-text="item.date"></div>
                                            <div class="text-[10.5px] font-mono text-slate-400 mt-0.5" x-text="item.ref"></div>
                                        </td>

                                        <!-- 2. ARAH RETUR -->
                                        <td class="py-4 px-4">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10.5px] font-extrabold whitespace-nowrap border"
                                                  :class="item.direction === 'gudang_ke_toko' ? 'bg-[#dcfce7] text-[#15803d] border-emerald-200' : 'bg-[#dbeafe] text-[#1e40af] border-blue-200'">
                                                <span class="material-symbols-rounded text-[14px]" x-text="item.direction === 'gudang_ke_toko' ? 'warehouse' : 'storefront'"></span>
                                                <span x-text="item.directionLabel"></span>
                                            </span>
                                        </td>

                                        <!-- 3. KONDISI / ALASAN -->
                                        <td class="py-4 px-4">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[10px] font-extrabold uppercase tracking-wide whitespace-nowrap"
                                                  :class="{
                                                      'bg-[#fee2e2] text-[#b91c1c]': item.conditionType === 'rusak',
                                                      'bg-[#ffedd5] text-[#b45309]': item.conditionType === 'ed',
                                                      'bg-[#dbeafe] text-[#1e40af]': item.conditionType === 'garansi' || item.conditionType === 'overstock'
                                                  }">
                                                <span class="w-1.5 h-1.5 rounded-full"
                                                      :class="{
                                                          'bg-[#dc2626]': item.conditionType === 'rusak',
                                                          'bg-[#d97706]': item.conditionType === 'ed',
                                                          'bg-[#0052cc]': item.conditionType === 'garansi' || item.conditionType === 'overstock'
                                                      }"></span>
                                                <span x-text="item.conditionLabel"></span>
                                            </span>
                                            <p class="text-[11.5px] text-slate-500 font-medium mt-1.5 leading-snug" x-text="item.conditionDesc"></p>
                                        </td>

                                        <!-- 4. BARANG & KUANTITAS -->
                                        <td class="py-4 px-4">
                                            <div class="space-y-1.5">
                                                <template x-for="(prod, idx) in item.products" :key="idx">
                                                    <div class="flex items-center justify-between gap-3">
                                                        <span class="text-[12px] font-bold text-slate-800 leading-snug" x-text="prod.name"></span>
                                                        <span class="text-[11.5px] font-extrabold font-mono whitespace-nowrap shrink-0" :class="prod.color" x-text="prod.qty"></span>
                                                    </div>
                                                </template>
                                                <template x-if="item.extraItems">
                                                    <div class="text-[11px] font-semibold text-slate-400 pt-0.5" x-text="item.extraItems"></div>
                                                </template>
                                            </div>
                                        </td>

                                        <!-- 5. KALKULASI FINANSIAL -->
                                        <td class="py-4 px-4">
                                            <div class="space-y-1 text-[11.5px]">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-slate-400 font-medium" x-text="item.subtotalLabel"></span>
                                                    <span class="font-mono font-bold text-slate-700 whitespace-nowrap" x-text="item.subtotal"></span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="font-medium" :class="item.truckFeeFree ? 'text-[#16a34a]' : 'text-red-500'">Biaya Truk:</span>
                                                    <span class="font-mono font-bold whitespace-nowrap" :class="item.truckFeeFree ? 'text-[#16a34a]' : 'text-red-600'" x-text="item.truckFee"></span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2 pt-1 border-t border-slate-100">
                                                    <span class="font-extrabold text-slate-900" x-text="item.netLabel"></span>
                                                    <span class="font-mono font-black text-[12.5px] whitespace-nowrap"
                                                          :class="item.direction === 'gudang_ke_toko' ? 'text-[#16a34a]' : 'text-[#0052cc]'"
                                                          x-text="item.netAmount"></span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- 6. ARMADA & DRIVER -->
                                        <td class="py-4 px-4">
                                            <div class="flex items-start gap-2.5">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 mt-0.5"
                                                     :class="item.direction === 'gudang_ke_toko' ? 'bg-emerald-50 text-[#16a34a]' : 'bg-slate-100 text-slate-600'">
                                                    <span class="material-symbols-rounded text-[16px]">local_shipping</span>
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="font-mono font-extrabold text-[12px] text-slate-900 whitespace-nowrap" x-text="item.plate"></div>
                                                    <div class="text-[11px] font-medium text-slate-500 whitespace-nowrap mt-0.5" x-text="item.driver"></div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- 7. STATUS & APPROVAL -->
                                        <td class="py-4 px-5">
                                            <div class="flex flex-col items-start gap-1.5">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase tracking-wide whitespace-nowrap border"
                                                      :class="{
                                                          'bg-[#fef3c7] text-[#92400e] border-[#fde68a]': item.statusKey === 'cek_fisik',
                                                          'bg-[#dcfce7] text-[#15803d] border-[#bbf7d0]': item.statusKey === 'selesai',
                                                          'bg-[#dbeafe] text-[#1e40af] border-[#bfdbfe]': item.statusKey === 'perjalanan',
                                                          'bg-[#e0e7ff] text-[#3730a3] border-[#c7d2fe]': item.statusKey === 'disetujui',
                                                          'bg-[#fee2e2] text-[#b91c1c] border-[#fecaca]': item.statusKey === 'ditolak'
                                                      }">
                                                    <span class="material-symbols-rounded text-[13px]"
                                                          x-text="{
                                                              'cek_fisik': 'inventory',
                                                              'selesai': 'check_circle',
                                                              'perjalanan': 'local_shipping',
                                                              'disetujui': 'verified_user',
                                                              'ditolak': 'cancel'
                                                          }[item.statusKey]"></span>
                                                    <span x-text="item.statusLabel"></span>
                                                </span>
                                                <span class="text-[11px] text-slate-500 font-medium" x-text="item.statusSub"></span>

                                                <!-- Manager Approve / Reject Controls for Toko -> Gudang -->
                                                <template x-if="item.needsApproval">
                                                    <div class="flex items-center gap-1.5 mt-1">
                                                        <button @click="approveItem(item)"
                                                                class="bg-[#16a34a] hover:bg-[#15803d] text-white text-[10.5px] font-extrabold px-2.5 py-1 rounded-lg inline-flex items-center gap-1 shadow-2xs transition-colors cursor-pointer">
                                                            <span class="material-symbols-rounded text-[13px]">check</span>
                                                            <span>Approve</span>
                                                        </button>
                                                        <button @click="rejectItem(item)"
                                                                class="bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-[10.5px] font-extrabold px-2.5 py-1 rounded-lg inline-flex items-center gap-1 transition-colors cursor-pointer">
                                                            <span class="material-symbols-rounded text-[13px]">close</span>
                                                            <span>Tolak</span>
                                                        </button>
                                                    </div>
                                                </template>

                                                <!-- Manager Receive Confirmation for Gudang -> Toko -->
                                                <template x-if="item.direction === 'gudang_ke_toko' && item.statusKey === 'perjalanan'">
                                                    <button @click="confirmReceive(item)"
                                                            class="mt-1 bg-emerald-50 hover:bg-emerald-100 text-[#15803d] border border-emerald-200 text-[10.5px] font-extrabold px-2.5 py-1 rounded-lg inline-flex items-center gap-1 transition-colors cursor-pointer">
                                                        <span class="material-symbols-rounded text-[13px]">done_all</span>
                                                        <span>Terima Barang</span>
                                                    </button>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Table Footer Pagination -->
                    <div class="px-6 py-4 bg-[#f8fafc] border-t border-slate-200/80 flex flex-col sm:flex-row items-center justify-between gap-3 text-[12px]">
                        <div class="text-slate-500 font-medium">
                            Menampilkan <span class="font-extrabold text-slate-800" x-text="filteredItems.length">4</span> dari <span class="font-extrabold text-slate-800">14</span> pengajuan retur aktif •
                            <a href="#bast-section" class="font-extrabold text-[#0052cc] hover:underline">Semua data sinkron ke ERP DC</a>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button class="px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-slate-400 font-bold text-[11.5px] cursor-not-allowed">Sebelumnya</button>
                            <button class="w-8 h-8 rounded-lg bg-[#0047b3] text-white font-extrabold text-[12px] flex items-center justify-center shadow-2xs">1</button>
                            <button class="w-8 h-8 rounded-lg bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 font-bold text-[12px] flex items-center justify-center transition-colors cursor-pointer">2</button>
                            <button class="px-3 py-1.5 rounded-lg bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 font-bold text-[11.5px] transition-colors cursor-pointer">Selanjutnya</button>
                        </div>
                    </div>
                </div>

                <!-- Bottom Section: Quick Input Draft Retur (8 Cols) & Audit BAST Terbaru (4 Cols) -->
                <div id="bast-section" class="grid grid-cols-12 gap-5">

                    <!-- Left: Input Cepat Draft Retur (Toko <-> Gudang) -->
                    <div class="col-span-12 xl:col-span-8 bg-white border border-slate-200/80 rounded-2xl p-6 shadow-2xs flex flex-col justify-between">
                        <div>
                            <!-- Card Header -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-5 border-b border-slate-100">
                                <div class="flex items-start gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-[#e0eaff] text-[#0052cc] flex items-center justify-center shrink-0 mt-0.5">
                                        <span class="material-symbols-rounded text-[20px]">bolt</span>
                                    </div>
                                    <div>
                                        <h3 class="text-[16px] font-black text-[#0f172a] flex flex-wrap items-center gap-2">
                                            <span>Input Cepat Draft Retur</span>
                                            <span class="text-[#0052cc]" x-text="draftDirection === 'toko_ke_gudang' ? '(Toko → Gudang)' : '(Gudang → Toko)'">(Toko → Gudang)</span>
                                        </h3>
                                        <p class="text-[12px] text-slate-500 font-medium mt-0.5">
                                            Tambahkan item cacat, expired, atau instruksi pengiriman barang gudang ke toko pada antrean armada berikutnya
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 self-start sm:self-center shrink-0">
                                    <div class="bg-slate-100 p-1 rounded-xl flex items-center text-[11px] font-extrabold">
                                        <button type="button"
                                                @click="draftDirection = 'toko_ke_gudang'; draftUseFleet = true"
                                                :class="draftDirection === 'toko_ke_gudang' ? 'bg-white text-[#0052cc] shadow-2xs' : 'text-slate-500 hover:text-slate-700'"
                                                class="px-2.5 py-1 rounded-lg transition-all cursor-pointer">
                                            Toko &rarr; Gudang
                                        </button>
                                        <button type="button"
                                                @click="draftDirection = 'gudang_ke_toko'; draftCondition = 'Penggantian Garansi (Gudang → Toko)'; draftUseFleet = false"
                                                :class="draftDirection === 'gudang_ke_toko' ? 'bg-white text-[#15803d] shadow-2xs' : 'text-slate-500 hover:text-slate-700'"
                                                class="px-2.5 py-1 rounded-lg transition-all cursor-pointer">
                                            Gudang &rarr; Toko
                                        </button>
                                    </div>
                                    <span class="bg-[#dbeafe] text-[#1e40af] text-[10px] font-extrabold px-2.5 py-1 rounded-lg uppercase tracking-wider leading-tight text-center hidden sm:inline-block">
                                        MODE CEPAT<br>KASIR/INV
                                    </span>
                                </div>
                            </div>

                            <!-- Form Inputs Row 1 (Fixed Overlapping Barcode Icon!) -->
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-5">
                                <!-- Pilih / Scan Barcode SKU -->
                                <div class="md:col-span-5">
                                    <label class="block text-[10.5px] font-extrabold text-slate-600 uppercase tracking-wider mb-1.5">PILIH / SCAN BARCODE SKU</label>
                                    <div class="relative">
                                        <input type="text" x-model="draftSku"
                                               class="w-full bg-[#f1f5f9] border border-slate-200/80 focus:border-blue-500 focus:bg-white rounded-xl pl-3.5 pr-10 py-2.5 text-[12.5px] font-bold text-slate-800 focus:outline-none transition-all truncate">
                                        <button type="button" @click="triggerToast('Scanner Barcode Aktif: Siap memindai EAN-13')"
                                                class="absolute right-2.5 top-1/2 -translate-y-1/2 w-7 h-7 rounded-lg bg-blue-50 text-[#0052cc] hover:bg-blue-100 flex items-center justify-center transition-colors cursor-pointer">
                                            <span class="material-symbols-rounded text-[17px]">qr_code_scanner</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Jumlah (Qty Retur) -->
                                <div class="md:col-span-3">
                                    <label class="block text-[10.5px] font-extrabold text-slate-600 uppercase tracking-wider mb-1.5">JUMLAH (QTY RETUR)</label>
                                    <div class="flex items-center bg-[#f1f5f9] border border-slate-200/80 focus-within:border-blue-500 focus-within:bg-white rounded-xl overflow-hidden transition-all">
                                        <input type="number" x-model="draftQty" min="1"
                                               class="w-full bg-transparent px-3.5 py-2.5 text-[13px] font-extrabold text-slate-800 focus:outline-none">
                                        <span class="px-3 py-2.5 text-[11.5px] font-bold text-slate-500 bg-slate-200/60 border-l border-slate-200/80 shrink-0" x-text="draftUnit">Pcs</span>
                                    </div>
                                </div>

                                <!-- Kondisi Fisik -->
                                <div class="md:col-span-4">
                                    <label class="block text-[10.5px] font-extrabold text-slate-600 uppercase tracking-wider mb-1.5">KONDISI FISIK</label>
                                    <div class="relative">
                                        <span class="w-2.5 h-2.5 rounded-full absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"
                                              :class="{
                                                  'bg-[#dc2626]': draftCondition.includes('Rusak'),
                                                  'bg-[#d97706]': draftCondition.includes('Kedaluwarsa'),
                                                  'bg-[#0052cc]': draftCondition.includes('Overstock'),
                                                  'bg-[#16a34a]': draftCondition.includes('Penggantian')
                                              }"></span>
                                        <select x-model="draftCondition"
                                                class="w-full bg-[#f1f5f9] border border-slate-200/80 focus:border-blue-500 focus:bg-white rounded-xl pl-8 pr-8 py-2.5 text-[12.5px] font-bold text-slate-800 appearance-none cursor-pointer focus:outline-none transition-all">
                                            <option>Kemasan Rusak / Pecah</option>
                                            <option>Kedaluwarsa / Dekat ED</option>
                                            <option>Mutasi Overstock Rak</option>
                                            <option>Penggantian Garansi (Gudang → Toko)</option>
                                        </select>
                                        <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px] pointer-events-none">expand_more</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Row 2: Photo Upload & Fleet Option -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <!-- Foto Bukti Fisik Barang -->
                                <div class="bg-[#f8fafc] border border-slate-200/80 rounded-xl p-3.5 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-9 h-9 rounded-lg bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                                            <span class="material-symbols-rounded text-[19px]">photo_camera</span>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-[12px] font-extrabold text-slate-800">Foto Bukti Fisik Barang</div>
                                            <div class="text-[11px] text-slate-500 font-medium truncate">1 Foto terlampir (<span x-text="draftPhotoName"></span>)</div>
                                        </div>
                                    </div>
                                    <label class="bg-white hover:bg-slate-50 text-[#0052cc] border border-slate-200/90 px-3.5 py-1.5 rounded-lg text-[11.5px] font-extrabold shrink-0 shadow-2xs transition-colors cursor-pointer">
                                        <span>Unggah</span>
                                        <input type="file" class="hidden" @change="if($event.target.files[0]) { draftPhotoName = $event.target.files[0].name; triggerToast('Foto bukti fisik berhasil diperbarui'); }">
                                    </label>
                                </div>

                                <!-- Gunakan Armada Logistik Rutin -->
                                <label class="bg-[#f8fafc] border border-slate-200/80 rounded-xl p-3.5 flex items-center gap-3 cursor-pointer select-none">
                                    <input type="checkbox" x-model="draftUseFleet" class="w-4 h-4 rounded text-[#0052cc] focus:ring-[#0052cc] border-slate-300">
                                    <div>
                                        <div class="text-[12px] font-extrabold text-slate-800">Gunakan Armada Logistik Rutin</div>
                                        <div class="text-[11px] font-semibold"
                                             :class="draftDirection === 'toko_ke_gudang' ? 'text-red-500' : 'text-[#16a34a]'"
                                             x-text="draftDirection === 'toko_ke_gudang' ? 'Termasuk potongan flat Rp 500.000 / trip' : 'Bebas biaya logistik (SOP Gudang → Toko)'">
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div class="mt-6 pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-2 text-[12px] text-slate-600 font-medium">
                                <span class="material-symbols-rounded text-[18px] text-[#16a34a]">check_circle</span>
                                <span>Otorisasi Supervisor Shift Aktif: <strong class="font-extrabold text-slate-900">Budi Santoso</strong></span>
                            </div>
                            <div class="flex items-center gap-2.5 w-full sm:w-auto justify-end">
                                <button type="button"
                                        @click="draftSku = ''; draftQty = 1; triggerToast('Form input cepat telah di-reset')"
                                        class="bg-[#f1f5f9] hover:bg-slate-200 text-slate-700 font-extrabold text-[12px] px-4 py-2.5 rounded-xl transition-colors cursor-pointer">
                                    Reset Form
                                </button>
                                <button type="button"
                                        @click="submitQuickDraft()"
                                        class="bg-[#0047b3] hover:bg-[#003994] text-white font-extrabold text-[12px] px-5 py-2.5 rounded-xl flex items-center gap-2 shadow-xs transition-all cursor-pointer">
                                    <span class="material-symbols-rounded text-[17px]">send</span>
                                    <span x-text="draftDirection === 'toko_ke_gudang' ? 'Ajukan Draft Retur' : 'Kirim Barang ke Toko'">Ajukan Draft Retur</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Audit BAST Terbaru (4 Cols - Fixed Non-Cramped BAST Layout!) -->
                    <div class="col-span-12 xl:col-span-4 bg-white border border-slate-200/80 rounded-2xl p-6 shadow-2xs flex flex-col justify-between gap-4">
                        <div>
                            <!-- Header -->
                            <div class="flex items-center justify-between gap-2 mb-4">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-rounded text-[20px] text-[#0052cc]">assignment_turned_in</span>
                                    <h3 class="text-[15.5px] font-black text-[#0f172a]">Audit BAST Terbaru</h3>
                                </div>
                                <span class="bg-[#dcfce7] text-[#15803d] text-[10.5px] font-extrabold px-2.5 py-0.5 rounded-md border border-emerald-200">Resmi</span>
                            </div>

                            <!-- BAST Detail Box -->
                            <div class="bg-[#f8fafc] border border-slate-200/80 rounded-xl p-4 space-y-3.5">
                                <div class="flex items-start justify-between gap-3 pb-3 border-b border-slate-200/70">
                                    <div>
                                        <div class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">NOMOR BERITA ACARA</div>
                                        <div class="text-[13px] font-black text-[#0f172a] font-mono mt-0.5">BAST-2024/05/TK-0888</div>
                                    </div>
                                    <span class="bg-white border border-blue-200 text-[#0052cc] text-[10.5px] font-extrabold px-2.5 py-1 rounded-lg shadow-2xs whitespace-nowrap shrink-0">
                                        Selesai Ditandatangani
                                    </span>
                                </div>

                                <div class="space-y-2.5 text-[12px]">
                                    <div class="flex items-start justify-between gap-3">
                                        <span class="text-slate-500 font-medium shrink-0">Pihak Toko (Pelepas):</span>
                                        <span class="font-extrabold text-slate-800 text-right">Budi Santoso (Spv)</span>
                                    </div>
                                    <div class="flex items-start justify-between gap-3">
                                        <span class="text-slate-500 font-medium shrink-0">Pihak Gudang (Penerima):</span>
                                        <span class="font-extrabold text-slate-800 text-right">Hendra W. (DC Head)</span>
                                    </div>
                                    <div class="flex items-start justify-between gap-3">
                                        <span class="text-slate-500 font-medium shrink-0">Waktu Serah Terima:</span>
                                        <span class="font-extrabold text-slate-800 text-right">23 Mei, 18:20 WIB</span>
                                    </div>
                                    <div class="flex items-start justify-between gap-3">
                                        <span class="text-slate-500 font-medium shrink-0">Kesesuaian Item:</span>
                                        <span class="font-extrabold text-[#16a34a] text-right">100% Cocok (Fisik = Dokumen)</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Info Note -->
                            <div class="mt-3.5 bg-[#f8fafc] border border-slate-200/80 rounded-xl p-3 flex items-start gap-2.5">
                                <span class="material-symbols-rounded text-[16px] text-[#0052cc] mt-0.5 shrink-0">info</span>
                                <p class="text-[11px] text-slate-500 font-medium leading-relaxed">
                                    BAST wajib dicetak 3 rangkap: 1 Kasir Cabang, 1 Driver Armada, 1 Arsip Gudang Pusat.
                                </p>
                            </div>
                        </div>

                        <!-- Download PDF Button -->
                        <button type="button"
                                @click="triggerToast('Mengunduh BAST-2024/05/TK-0888.pdf...')"
                                class="w-full bg-[#e2e8f0]/70 hover:bg-[#cbd5e1]/80 text-[#0f172a] font-extrabold text-[12px] py-3 rounded-xl flex items-center justify-center gap-2 transition-colors cursor-pointer">
                            <span class="material-symbols-rounded text-[17px]">download</span>
                            <span>Unduh Arsip BAST Digital (PDF)</span>
                        </button>
                    </div>

                </div>

            </div>
        </main>
    </div>

    <!-- Modal: Buat Pengajuan Retur (Toko -> Gudang) / Kirim & Penerimaan Barang (Gudang -> Toko) -->
    <div x-show="showModal" style="display: none;"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4"
         x-transition.opacity>
        <div @click.away="showModal = false"
             class="bg-white rounded-2xl w-full max-w-lg shadow-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 bg-[#f8fafc] border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white"
                         :class="modalMode === 'toko_ke_gudang' ? 'bg-[#0047b3]' : 'bg-[#16a34a]'">
                        <span class="material-symbols-rounded text-[20px]" x-text="modalMode === 'toko_ke_gudang' ? 'outbox' : 'move_to_inbox'"></span>
                    </div>
                    <div>
                        <h3 class="text-[15px] font-black text-slate-900"
                            x-text="modalMode === 'toko_ke_gudang' ? 'Buat Pengajuan Retur (Toko → Gudang)' : 'Kirim / Terima Barang (Gudang → Toko)'"></h3>
                        <p class="text-[11px] font-medium text-slate-500"
                           x-text="modalMode === 'toko_ke_gudang' ? 'Ajukan pengembalian barang rusak/ED/overstock ke DC' : 'Catat pengiriman penggantian atau relokasi stok dari Gudang ke Toko'"></p>
                    </div>
                </div>
                <button @click="showModal = false" class="w-8 h-8 rounded-lg hover:bg-slate-200/60 flex items-center justify-center text-slate-500 cursor-pointer">
                    <span class="material-symbols-rounded text-[18px]">close</span>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-600 uppercase tracking-wider mb-1.5">NAMA PRODUK / SKU</label>
                    <input type="text" x-model="newDocSku" placeholder="Contoh: Susu UHT Full Cream 1L"
                           class="w-full bg-[#f1f5f9] border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] font-bold text-slate-800 focus:bg-white focus:border-blue-500 focus:outline-none">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-extrabold text-slate-600 uppercase tracking-wider mb-1.5">KUANTITAS</label>
                        <input type="number" x-model="newDocQty" min="1"
                               class="w-full bg-[#f1f5f9] border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] font-bold text-slate-800 focus:bg-white focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-extrabold text-slate-600 uppercase tracking-wider mb-1.5">SATUAN KEMASAN</label>
                        <select x-model="newDocUnit" class="w-full bg-[#f1f5f9] border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] font-bold text-slate-800 focus:bg-white focus:border-blue-500 focus:outline-none">
                            <option>Karton</option>
                            <option>Pouch</option>
                            <option>Pack</option>
                            <option>Btl</option>
                            <option>Kaleng</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-600 uppercase tracking-wider mb-1.5">KATEGORI ALASAN & ARMADA</label>
                    <input type="text" x-model="newDocReason" :placeholder="modalMode === 'toko_ke_gudang' ? 'Contoh: Kemasan penyok saat bongkar muat' : 'Contoh: Kompensasi klaim retur #RTR-TK-2024-0892'"
                           class="w-full bg-[#f1f5f9] border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] font-medium text-slate-800 focus:bg-white focus:border-blue-500 focus:outline-none">
                </div>
            </div>

            <div class="px-6 py-4 bg-[#f8fafc] border-t border-slate-200 flex items-center justify-end gap-2.5">
                <button @click="showModal = false" class="px-4 py-2 rounded-xl bg-slate-200/70 hover:bg-slate-200 text-slate-700 text-[12px] font-extrabold cursor-pointer">Batal</button>
                <button @click="
                    draftDirection = modalMode;
                    draftSku = newDocSku || 'Minyak Goreng Sawit 2L';
                    draftQty = newDocQty || 12;
                    draftUnit = newDocUnit;
                    submitQuickDraft();
                    showModal = false;
                    newDocSku = '';
                    newDocReason = '';
                " class="px-5 py-2 rounded-xl text-white text-[12px] font-extrabold shadow-xs cursor-pointer"
                   :class="modalMode === 'toko_ke_gudang' ? 'bg-[#0047b3] hover:bg-[#003994]' : 'bg-[#16a34a] hover:bg-[#15803d]'">
                    Simpan & Terbitkan Dokumen
                </button>
            </div>
        </div>
    </div>

    <!-- Interactive Toast Notification -->
    <div x-show="showToast" style="display: none;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-3"
         class="fixed bottom-6 right-6 z-50 bg-[#0f172a] text-white px-4 py-3 rounded-xl shadow-xl border border-slate-700 flex items-center gap-3 text-[12.5px] font-bold">
        <span class="material-symbols-rounded text-[18px] text-emerald-400">check_circle</span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
