@extends('layouts.app')

@section('title', 'Manajemen Retur Barang - ILP POS')

@section('content')
<div x-data="{
    // Tab Navigation: 'approval' | 'masuk' | 'riwayat'
    mainTab: (new URLSearchParams(window.location.search).get('tab') === 'masuk' ? 'masuk' : (new URLSearchParams(window.location.search).get('tab') === 'riwayat' ? 'riwayat' : ('{{ $initialTab ?? 'approval' }}' === 'masuk' ? 'masuk' : 'approval'))),
    
    // Filters & Search
    searchQuery: '',
    statusFilterApproval: 'all',
    filterPhysicalStatus: 'all', // 'all', 'pending', 'received'
    
    // Modal: Detail Barang Retur dari Kasir Cabang
    showDetailDocModal: false,
    selectedDocForDetail: null,

    // Modal: Approval
    showApproveModal: false,
    selectedDocForApproval: null,

    // Modal: Reject
    showRejectModal: false,
    selectedDocForReject: null,
    rejectReason: '',

    // Modal: Request Revision
    showRevisionModal: false,
    selectedDocForRevision: null,
    revisionNotes: '',

    // Modal: Konfirmasi / Atur Penerimaan Fisik Gudang (Anti-Stok Tertinggal)
    showPhysicalModal: false,
    receivingDoc: null,
    highlightedSku: null,
    physicalInspector: 'Hendra W. (DC Head / Gudang Pusat)',
    physicalReceivedDate: '',
    physicalGlobalNotes: '',
    physicalItemsForm: [],

    // Modal: Preview BAST Digital
    showBastModal: false,
    selectedBastDoc: null,

    // Modal: Buat Pengajuan / Kirim Barang Baru
    showModal: false,
    modalMode: 'toko_ke_gudang',
    newDocBranch: 'Cabang A',
    newDocCashier: 'Siti Aminah (Kasir Cabang A)',
    newDocSku: '',
    newDocQty: 12,
    newDocUnit: 'Pcs',
    newDocCondition: 'RUSAK / BOCOR',
    newDocReason: '',
    newDocDriver: 'Andi Pratama (B 9481 UQ)',

    // Toast Notification
    toastMessage: '',
    toastType: 'success',
    showToast: false,
    triggerToast(msg, type = 'success') {
        this.toastMessage = msg;
        this.toastType = type;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3500);
    },

    items: [],

    async initReturns() {
        try {
            const res = await fetch('/api/returns', {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success && json.data) {
                    this.items = json.data;
                    return;
                }
            }
        } catch(e) {
            console.error('Failed to load returns from API', e);
        }

        const stored = localStorage.getItem('pos_ilp_manager_returns_v1');
        if (stored) {
            try {
                const parsed = JSON.parse(stored);
                if (Array.isArray(parsed) && parsed.length > 0) {
                    this.items = parsed.map(d => this.normalizeReturnDoc(d));
                    this.saveReturns();
                    return;
                }
            } catch(e) {
                console.error('Failed to parse returns data:', e);
            }
        }
        this.items = this.getDefaultReturns().map(d => this.normalizeReturnDoc(d));
        this.saveReturns();
    },

    saveReturns() {
        try {
            localStorage.setItem('pos_ilp_manager_returns_v1', JSON.stringify(this.items));
        } catch(e) {
            console.error('Failed to save returns:', e);
        }
    },

    // Normalizer to ensure complete cashier info, clean units, and detailed item structures
    normalizeReturnDoc(doc) {
        if (!doc.branchOrigin) {
            doc.branchOrigin = doc.docNo && doc.docNo.includes('TK') ? 'Cabang A' : 'Gudang Pusat';
        }
        if (!doc.cashierName) {
            doc.cashierName = doc.direction === 'toko_ke_gudang' 
                ? (doc.branchOrigin === 'Cabang B' ? 'Bambang S. (Kasir Cabang B)' : 'Siti Aminah (Kasir Cabang A)')
                : 'Hendra W. (DC Head)';
        }
        if (!doc.terminalId) {
            doc.terminalId = 'POS-01 (Kasir Toko)';
        }
        if (!doc.invoiceRef) {
            doc.invoiceRef = doc.ref || ('INV-2024/' + (doc.branchOrigin || 'CAB-A'));
        }

        if (Array.isArray(doc.products)) {
            doc.products.forEach((p, idx) => {
                // Bersihkan kuantitas dan pastikan satuan Pcs murni (tanpa pouch/tetra/unit ganda)
                let rawQty = String(p.submittedQty || p.qty || '1').trim();
                let parsedNum = parseInt(rawQty) || 1;
                p.submittedQty = parsedNum;
                p.qty = parsedNum;
                p.unit = 'Pcs';

                if (!p.sku) {
                    if (p.name.includes('Minyak')) p.sku = 'PRD-007';
                    else if (p.name.includes('Ultra') || p.name.includes('Susu')) p.sku = 'PRD-003';
                    else if (p.name.includes('Indomie')) p.sku = 'PRD-006';
                    else if (p.name.includes('Kopi')) p.sku = 'PRD-001';
                    else if (p.name.includes('Kecap')) p.sku = 'PRD-002';
                    else if (p.name.includes('Yogurt')) p.sku = 'PRD-008';
                    else if (p.name.includes('Roti')) p.sku = 'PRD-009';
                    else if (p.name.includes('Deterjen')) p.sku = 'PRD-010';
                    else if (p.name.includes('Pembersih')) p.sku = 'PRD-005';
                    else p.sku = 'PRD-RET-0' + (idx + 1);
                }
                if (!p.barcode) {
                    p.barcode = '899' + Math.floor(1000000000 + Math.random() * 9000000000);
                }
                if (!p.category) {
                    if (p.name.includes('Minyak') || p.name.includes('Beras')) p.category = 'Kebutuhan Pokok';
                    else if (p.name.includes('Susu') || p.name.includes('Yogurt') || p.name.includes('Aqua') || p.name.includes('Kopi')) p.category = 'Minuman';
                    else if (p.name.includes('Indomie') || p.name.includes('Roti') || p.name.includes('Chitato')) p.category = 'Makanan';
                    else if (p.name.includes('Deterjen') || p.name.includes('Pembersih') || p.name.includes('Sunlight')) p.category = 'Pembersih';
                    else p.category = 'Barang Konsumsi';
                }
                if (!p.price) {
                    if (p.name.includes('Minyak')) p.price = 34000;
                    else if (p.name.includes('Susu')) p.price = 19200;
                    else if (p.name.includes('Indomie')) p.price = 3500;
                    else if (p.name.includes('Kopi')) p.price = 12500;
                    else if (p.name.includes('Yogurt')) p.price = 9500;
                    else if (p.name.includes('Roti')) p.price = 16000;
                    else if (p.name.includes('Deterjen')) p.price = 22000;
                    else if (p.name.includes('Pembersih')) p.price = 15500;
                    else p.price = 15000;
                }
                p.subtotal = p.price * p.submittedQty;

                // Sinkronkan data kuantitas fisik diterima dan kalkulasi stok tertinggal
                if (p.receivedQty === undefined) {
                    if (doc.isPhysicalReceived && doc.physicalReceiptData && Array.isArray(doc.physicalReceiptData.items)) {
                        const recMatch = doc.physicalReceiptData.items.find(i => i.sku === p.sku || i.name === p.name);
                        p.receivedQty = recMatch ? Number(recMatch.receivedQty) : p.submittedQty;
                    } else if (doc.isPhysicalReceived) {
                        p.receivedQty = p.submittedQty;
                    } else {
                        p.receivedQty = 0;
                    }
                }
                p.receivedQty = Number(p.receivedQty) || 0;
                p.selisih = Math.max(0, p.submittedQty - p.receivedQty);
                p.tertinggalQty = p.selisih;
            });
        }

        if (doc.physicalReceiptData && Array.isArray(doc.physicalReceiptData.items)) {
            doc.physicalReceiptData.items.forEach(i => {
                i.submittedQty = parseInt(i.submittedQty) || 1;
                i.receivedQty = parseInt(i.receivedQty) || 0;
                i.unit = 'Pcs';
                i.selisih = Math.max(0, i.submittedQty - i.receivedQty);
            });
        }
        return doc;
    },

    formatQtyClean(prod) {
        if (!prod) return '-';
        const raw = String(prod.submittedQty || prod.qty || '1');
        const num = parseInt(raw) || 1;
        return num + ' Pcs';
    },

    calculateDocTotalQty(doc) {
        if (!doc || !Array.isArray(doc.products)) return '0 Unit';
        const total = doc.products.reduce((acc, p) => acc + (Number(p.submittedQty) || parseInt(p.qty) || 0), 0);
        return total + ' Unit';
    },

    calculateDocTotalReceivedQty(doc) {
        if (!doc || !Array.isArray(doc.products)) return '0 Unit';
        if (!doc.isPhysicalReceived) return '0 Unit';
        const total = doc.products.reduce((acc, p) => acc + (Number(p.receivedQty !== undefined ? p.receivedQty : (p.submittedQty || 0))), 0);
        return total + ' Unit';
    },

    calculateDocTotalTertinggalQty(doc) {
        if (!doc || !Array.isArray(doc.products)) return 0;
        if (!doc.isPhysicalReceived) {
            return doc.products.reduce((acc, p) => acc + (Number(p.submittedQty || 0)), 0);
        }
        return doc.products.reduce((acc, p) => acc + (Number(p.selisih || 0)), 0);
    },

    openDetailDoc(doc) {
        this.selectedDocForDetail = doc;
        this.showDetailDocModal = true;
    },

    getDefaultReturns() {
        return [
            {
                id: 1,
                docNo: '#RTR-TK-2024-0895',
                date: 'Hari ini • 08:30 WIB',
                ref: 'Ref: NOTA-CAB-A/2405',
                direction: 'toko_ke_gudang',
                directionLabel: 'TOKO → GUDANG',
                branchOrigin: 'Cabang A',
                cashierName: 'Siti Aminah (Kasir Cabang A)',
                terminalId: 'POS-01 (Kasir Toko Depan)',
                conditionType: 'rusak',
                conditionLabel: 'RUSAK / PECAH',
                conditionDesc: 'Karton rembes tersenggol rak dorong saat bongkar stok.',
                products: [
                    { sku: 'PRD-006', barcode: '899275321004', name: 'Indomie Goreng Spesial 85g', category: 'Makanan', submittedQty: 48, unit: 'Pcs', price: 3500, color: 'text-[#dc2626]' },
                    { sku: 'PRD-001', barcode: '8991234567890', name: 'Kopi Kapal Api Spesial 165g', category: 'Minuman', submittedQty: 20, unit: 'Pcs', price: 12500, color: 'text-[#dc2626]' }
                ],
                subtotalLabel: 'Subtotal:',
                subtotal: 'Rp 418.000',
                truckFee: '-Rp 250.000',
                truckFeeFree: false,
                netLabel: 'Net Klaim:',
                netAmount: 'Rp 168.000',
                plate: 'B 9481 UQ',
                driver: 'Andi Pratama',
                statusKey: 'menunggu_approval',
                statusLabel: 'MENUNGGU APPROVAL',
                statusSub: 'Menunggu telaah Manager Toko',
                needsApproval: true,
                isPhysicalReceived: false,
                physicalReceiptData: null
            },
            {
                id: 2,
                docNo: '#RTR-TK-2024-0892',
                date: '24 Mei 2024 • 09:42 WIB',
                ref: 'Ref: INV-2024/CAB-A',
                direction: 'toko_ke_gudang',
                directionLabel: 'TOKO → GUDANG',
                branchOrigin: 'Cabang A',
                cashierName: 'Siti Aminah (Kasir Cabang A)',
                terminalId: 'POS-01 (Kasir Toko Depan)',
                conditionType: 'rusak',
                conditionLabel: 'RUSAK / BOCOR',
                conditionDesc: 'Karton rembes saat transit delivery ke rak pajang.',
                products: [
                    { sku: 'PRD-007', barcode: '8996789012345', name: 'Minyak Goreng Sawit 2L', category: 'Kebutuhan Pokok', submittedQty: 15, unit: 'Pcs', price: 34000, color: 'text-[#dc2626]' },
                    { sku: 'PRD-003', barcode: '8993456789012', name: 'Susu UHT Cokelat 1L', category: 'Minuman', submittedQty: 24, unit: 'Pcs', price: 19200, color: 'text-[#dc2626]' }
                ],
                subtotalLabel: 'Subtotal:',
                subtotal: 'Rp 970.800',
                truckFee: '-Rp 500.000',
                truckFeeFree: false,
                netLabel: 'Net:',
                netAmount: 'Rp 470.800',
                plate: 'B 9481 UQ',
                driver: 'Andi Pratama',
                statusKey: 'ditolak',
                statusLabel: 'DITOLAK MANAGER',
                statusSub: 'Ditolak: Kerusakan akibat kelalaian display cabang',
                needsApproval: false,
                isPhysicalReceived: false,
                physicalReceiptData: null
            },
            {
                id: 3,
                docNo: '#RTR-TK-2024-0891',
                date: '23 Mei 2024 • 16:15 WIB',
                ref: 'Ref: STK-ED-2405',
                direction: 'toko_ke_gudang',
                directionLabel: 'TOKO → GUDANG',
                branchOrigin: 'Cabang A',
                cashierName: 'Bambang S. (Kasir Shift Siang)',
                terminalId: 'POS-02 (Kasir Belakang)',
                conditionType: 'ed',
                conditionLabel: 'KEDALUWARSA / H-14',
                conditionDesc: 'ED Bulan Juni 2024 batch A12 diverifikasi QC toko.',
                products: [
                    { sku: 'PRD-008', barcode: '8998899001122', name: 'Yogurt Drink Strawberry 250ml', category: 'Minuman', submittedQty: 48, unit: 'Pcs', price: 9500, color: 'text-[#d97706]' },
                    { sku: 'PRD-009', barcode: '8997788990011', name: 'Roti Tawar Gandum Spesial', category: 'Makanan', submittedQty: 16, unit: 'Pcs', price: 16000, color: 'text-[#d97706]' }
                ],
                subtotalLabel: 'Subtotal:',
                subtotal: 'Rp 712.000',
                truckFee: '-Rp 500.000',
                truckFeeFree: false,
                netLabel: 'Net:',
                netAmount: 'Rp 212.000',
                plate: 'B 9102 UQ',
                driver: 'Rahmat Hidayat',
                statusKey: 'selesai',
                statusLabel: 'SELESAI (BAST)',
                statusSub: 'Diterima: Joko (WH-Staff)',
                needsApproval: false,
                isPhysicalReceived: true,
                physicalReceiptData: {
                    receivedDate: '23 Mei 2024 16:45 WIB',
                    receivedBy: 'Joko (WH-Staff)',
                    hasDiscrepancy: false,
                    discrepancyReason: '',
                    bastNo: 'BAST-2024/05/TK-0888',
                    items: [
                        { sku: 'PRD-008', name: 'Yogurt Drink Strawberry 250ml', submittedQty: 48, receivedQty: 48, unit: 'Pcs', selisih: 0, discrepancyReason: '' },
                        { sku: 'PRD-009', name: 'Roti Tawar Gandum Spesial', submittedQty: 16, receivedQty: 16, unit: 'Pcs', selisih: 0, discrepancyReason: '' }
                    ]
                }
            },
            {
                id: 4,
                docNo: '#RTR-TK-2024-0890',
                date: '22 Mei 2024 • 14:05 WIB',
                ref: 'Ref: OVER-STOCK-09',
                direction: 'toko_ke_gudang',
                directionLabel: 'TOKO → GUDANG',
                branchOrigin: 'Cabang A',
                cashierName: 'Siti Aminah (Kasir Cabang A)',
                terminalId: 'POS-01 (Kasir Toko Depan)',
                conditionType: 'overstock',
                conditionLabel: 'MUTASI OVERSTOCK',
                conditionDesc: 'Penyesuaian buffer ruang simpan rak barang cepat saji.',
                products: [
                    { sku: 'PRD-010', barcode: '8995544332211', name: 'Deterjen Bubuk Premium 800g', category: 'Pembersih', submittedQty: 60, unit: 'Pcs', price: 22000, color: 'text-[#0052cc]' },
                    { sku: 'PRD-005', barcode: '8995678901234', name: 'Pembersih Lantai Lavender 750ml', category: 'Pembersih', submittedQty: 36, unit: 'Pcs', price: 15500, color: 'text-[#0052cc]' }
                ],
                subtotalLabel: 'Subtotal:',
                subtotal: 'Rp 1.878.000',
                truckFee: 'GRATIS (SOP)',
                truckFeeFree: true,
                netLabel: 'Net:',
                netAmount: 'Rp 1.878.000',
                plate: 'B 9481 UQ',
                driver: 'Andi Pratama',
                statusKey: 'ditolak',
                statusLabel: 'DITOLAK MANAGER',
                statusSub: 'Ditolak: Gudang Pusat sedang renovasi area deterjen',
                needsApproval: false,
                isPhysicalReceived: false,
                physicalReceiptData: null
            },
            {
                id: 5,
                docNo: '#REP-GD-2024-0310',
                date: '24 Mei 2024 • 11:20 WIB',
                ref: 'Klaim: #RTR-TK-2024-0880',
                direction: 'gudang_ke_toko',
                directionLabel: 'GUDANG → TOKO',
                branchOrigin: 'Gudang Pusat',
                cashierName: 'Hendra W. (DC Head)',
                terminalId: 'DC-DISPATCH-01',
                conditionType: 'garansi',
                conditionLabel: 'PENGGANTIAN GARANSI',
                conditionDesc: 'Barang Baru Kompensasi Distributor Supplier',
                products: [
                    { sku: 'PRD-002', barcode: '8992345678901', name: 'Kecap Manis Refill 550ml', category: 'Kebutuhan Pokok', submittedQty: 30, unit: 'Pcs', price: 14000, color: 'text-[#16a34a]' }
                ],
                subtotalLabel: 'Nilai Barang:',
                subtotal: 'Rp 420.000',
                truckFee: 'GRATIS (SOP)',
                truckFeeFree: true,
                netLabel: 'Total:',
                netAmount: 'Rp 420.000',
                plate: 'B 9844 XZ',
                driver: 'Dedi Kurnia',
                statusKey: 'perjalanan',
                statusLabel: 'DALAM PERJALANAN',
                statusSub: 'Menuju Toko Cabang A (Tol KM 14)',
                needsApproval: false,
                isPhysicalReceived: false,
                physicalReceiptData: null
            }
        ];
    },

    // KPI Dynamic Counts
    get countPendingApproval() {
        return this.items.filter(i => i.direction === 'toko_ke_gudang' && (i.needsApproval || i.statusKey === 'menunggu_approval')).length;
    },

    get countApprovedPendingPhysical() {
        return this.items.filter(i => i.direction === 'toko_ke_gudang' && !i.isPhysicalReceived && (i.statusKey === 'disetujui' || i.statusKey === 'cek_fisik')).length;
    },

    get countCompletedReceipt() {
        return this.items.filter(i => i.isPhysicalReceived).length;
    },

    get totalQuarantineUnitsInGudang() {
        try {
            const raw = localStorage.getItem('pos_ilp_stok_retur_gudang_v1');
            if (raw) {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) {
                    return parsed.reduce((sum, item) => sum + (Number(item.qtyReceived) || 0), 0);
                }
            }
        } catch(e) {}
        return 77;
    },

    // Filtered lists for each tab
    get filteredApprovalList() {
        return this.items.filter(item => {
            if (item.direction !== 'toko_ke_gudang') return false;
            if (this.statusFilterApproval !== 'all') {
                if (this.statusFilterApproval === 'menunggu' && item.statusKey !== 'menunggu_approval' && !item.needsApproval) return false;
                if (this.statusFilterApproval === 'disetujui' && item.statusKey !== 'disetujui') return false;
                if (this.statusFilterApproval === 'revisi' && item.statusKey !== 'revisi') return false;
                if (this.statusFilterApproval === 'ditolak' && item.statusKey !== 'ditolak') return false;
            }
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                const matchDoc = item.docNo.toLowerCase().includes(q) || item.ref.toLowerCase().includes(q);
                const matchBranch = (item.branchOrigin || '').toLowerCase().includes(q);
                const matchCashier = (item.cashierName || '').toLowerCase().includes(q);
                const matchDriver = (item.driver || '').toLowerCase().includes(q);
                const matchProduct = item.products.some(p => p.name.toLowerCase().includes(q) || (p.sku && p.sku.toLowerCase().includes(q)));
                return matchDoc || matchBranch || matchCashier || matchDriver || matchProduct;
            }
            return true;
        });
    },
    async approveItem(item) {
        item.statusKey = 'disetujui';
        item.statusLabel = 'DISETUJUI MANAGER';
        item.statusSub = 'Siap Muat ke Gudang DC';
        item.needsApproval = false;

        try {
            if (item.id && typeof item.id === 'number' && item.id < 1000000000000) {
                await fetch(`/api/returns/${item.id}/status`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ status: 'disetujui' })
                });
            }
        } catch(e) {
            console.error('Failed to sync return approval to API', e);
        }

        this.saveReturns();
        this.triggerToast('Pengajuan ' + item.docNo + ' berhasil di-Approve oleh Manager.');
    },
    async rejectItem(item) {
        item.statusKey = 'ditolak';
        item.statusLabel = 'DITOLAK MANAGER';
        item.statusSub = 'Dikembalikan ke Staf Toko';
        item.needsApproval = false;

        try {
            if (item.id && typeof item.id === 'number' && item.id < 1000000000000) {
                await fetch(`/api/returns/${item.id}/status`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ status: 'ditolak' })
                });
            }
        } catch(e) {
            console.error('Failed to sync return rejection to API', e);
        }

        this.saveReturns();
        this.triggerToast('Pengajuan ' + item.docNo + ' telah ditolak.');
    },
    async confirmReceive(item) {
        item.statusKey = 'selesai';
        item.statusLabel = 'SELESAI (BAST)';
        item.statusSub = 'Diterima Manager Toko';

        try {
            if (item.id && typeof item.id === 'number' && item.id < 1000000000000) {
                await fetch(`/api/returns/${item.id}/status`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ status: 'selesai' })
                });
            }
        } catch(e) {
            console.error('Failed to sync return receive to API', e);
        }

        this.saveReturns();
        this.triggerToast('Pengiriman Gudang ' + item.docNo + ' dikonfirmasi masuk stok toko.');
    },

    get filteredMasukList() {
        return this.items.filter(item => {
            if (item.direction !== 'toko_ke_gudang') return false;
            if (this.filterPhysicalStatus === 'pending') {
                if (item.isPhysicalReceived || (item.statusKey !== 'disetujui' && item.statusKey !== 'cek_fisik' && item.statusKey !== 'menunggu_approval')) return false;
            } else if (this.filterPhysicalStatus === 'received') {
                if (!item.isPhysicalReceived) return false;
            }
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                const matchDoc = item.docNo.toLowerCase().includes(q) || item.ref.toLowerCase().includes(q);
                const matchBranch = (item.branchOrigin || '').toLowerCase().includes(q);
                const matchCashier = (item.cashierName || '').toLowerCase().includes(q);
                const matchDriver = (item.driver || '').toLowerCase().includes(q);
                const matchProduct = item.products.some(p => p.name.toLowerCase().includes(q) || (p.sku && p.sku.toLowerCase().includes(q)));
                return matchDoc || matchBranch || matchCashier || matchDriver || matchProduct;
            }
            return true;
        });
    },

    get filteredRiwayatList() {
        return this.items.filter(item => {
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                const matchDoc = item.docNo.toLowerCase().includes(q) || item.ref.toLowerCase().includes(q);
                const matchBranch = (item.branchOrigin || '').toLowerCase().includes(q);
                const matchCashier = (item.cashierName || '').toLowerCase().includes(q);
                const matchDriver = (item.driver || '').toLowerCase().includes(q);
                const matchProduct = item.products.some(p => p.name.toLowerCase().includes(q));
                return matchDoc || matchBranch || matchCashier || matchDriver || matchProduct;
            }
            return true;
        });
    },

    // ========================================================
    // APPROVAL / REJECT / REVISION ACTIONS
    // ========================================================
    openApproveModal(doc) {
        this.selectedDocForApproval = doc;
        this.showApproveModal = true;
    },

    confirmApprove() {
        if (!this.selectedDocForApproval) return;
        const doc = this.selectedDocForApproval;
        doc.statusKey = 'disetujui';
        doc.statusLabel = 'DISETUJUI MANAGER';
        doc.statusSub = 'Siap Kirim & Cek Fisik Gudang';
        doc.needsApproval = false;
        this.saveReturns();

        // Audit Trail
        this.logAudit(
            'APPROVE_RETURN',
            doc.docNo,
            `Manager menyetujui pengajuan retur ${doc.docNo} dari ${doc.branchOrigin} (Kasir: ${doc.cashierName}). Barang diizinkan dikirim ke Gudang Pusat (Belum masuk Stok Retur).`
        );

        this.showApproveModal = false;
        this.triggerToast(`Pengajuan ${doc.docNo} berhasil disetujui! Barang siap diterima fisik di Gudang Pusat.`);
    },

    openRejectModal(doc) {
        this.selectedDocForReject = doc;
        this.rejectReason = '';
        this.showRejectModal = true;
    },

    confirmReject() {
        if (!this.selectedDocForReject || !this.rejectReason.trim()) {
            alert('Wajib mengisi alasan penolakan retur.');
            return;
        }
        const doc = this.selectedDocForReject;
        doc.statusKey = 'ditolak';
        doc.statusLabel = 'DITOLAK MANAGER';
        doc.statusSub = 'Ditolak: ' + this.rejectReason.trim();
        doc.needsApproval = false;
        this.saveReturns();

        // Audit Trail
        this.logAudit(
            'REJECT_RETURN',
            doc.docNo,
            `Manager menolak pengajuan retur ${doc.docNo} dari ${doc.branchOrigin}. Alasan: ${this.rejectReason.trim()}`
        );

        this.showRejectModal = false;
        this.triggerToast(`Pengajuan ${doc.docNo} telah ditolak.`);
    },

    openRevisionModal(doc) {
        this.selectedDocForRevision = doc;
        this.revisionNotes = '';
        this.showRevisionModal = true;
    },

    confirmRevision() {
        if (!this.selectedDocForRevision || !this.revisionNotes.trim()) {
            alert('Wajib mengisi catatan instruksi revisi dokumen.');
            return;
        }
        const doc = this.selectedDocForRevision;
        doc.statusKey = 'revisi';
        doc.statusLabel = 'PERLU REVISI';
        doc.statusSub = 'Catatan: ' + this.revisionNotes.trim();
        doc.needsApproval = false;
        this.saveReturns();

        // Audit Trail
        this.logAudit(
            'REVISE_RETURN',
            doc.docNo,
            `Manager meminta revisi dokumen retur ${doc.docNo}. Instruksi: ${this.revisionNotes.trim()}`
        );

        this.showRevisionModal = false;
        this.triggerToast(`Permintaan revisi ${doc.docNo} berhasil dikirim ke toko.`);
    },

    // ========================================================
    // ATUR & KONFIRMASI PENERIMAAN FISIK GUDANG (ANTI-STOK TERTINGGAL)
    // ========================================================
    openPhysicalReceiveModal(doc, focusSku = null) {
        if (!doc) return;
        this.receivingDoc = doc;
        this.highlightedSku = focusSku;
        const now = new Date();
        this.physicalReceivedDate = now.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) + ' • ' + now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' WIB';
        this.physicalInspector = doc.physicalReceiptData?.receivedBy || 'Hendra W. (DC Head / Gudang Pusat)';
        this.physicalGlobalNotes = doc.physicalReceiptData?.discrepancyReason || '';

        this.physicalItemsForm = doc.products.map(p => {
            const subQty = Number(p.submittedQty || parseInt(p.qty) || 1);
            let initialRec = subQty;
            if (p.receivedQty !== undefined && doc.isPhysicalReceived) {
                initialRec = Number(p.receivedQty);
            } else if (doc.physicalReceiptData && Array.isArray(doc.physicalReceiptData.items)) {
                const fItem = doc.physicalReceiptData.items.find(i => i.sku === p.sku || i.name === p.name);
                if (fItem && fItem.receivedQty !== undefined) {
                    initialRec = Number(fItem.receivedQty);
                }
            }
            return {
                sku: p.sku || 'PRD-007',
                name: p.name,
                category: p.category || 'Barang Toko',
                submittedQty: subQty,
                receivedQty: initialRec,
                unit: p.unit || 'Pcs',
                selisih: Math.max(0, subQty - initialRec),
                discrepancyReason: p.discrepancyReason || '',
                followUpAction: p.followUpAction || 'kirim_susulan'
            };
        });

        this.showPhysicalModal = true;
    },

    updateItemDiscrepancy(item) {
        const sub = Number(item.submittedQty) || 0;
        let rec = Number(item.receivedQty);
        if (isNaN(rec) || rec < 0) rec = 0;
        if (rec > sub) rec = sub;
        item.receivedQty = rec;
        item.selisih = Math.max(0, sub - rec);
    },

    setFormItemFull(itm) {
        itm.receivedQty = itm.submittedQty;
        this.updateItemDiscrepancy(itm);
    },

    setFormItemZero(itm) {
        itm.receivedQty = 0;
        this.updateItemDiscrepancy(itm);
    },

    incrementFormItem(itm) {
        itm.receivedQty = Math.min(itm.submittedQty, (Number(itm.receivedQty) || 0) + 1);
        this.updateItemDiscrepancy(itm);
    },

    decrementFormItem(itm) {
        itm.receivedQty = Math.max(0, (Number(itm.receivedQty) || 0) - 1);
        this.updateItemDiscrepancy(itm);
    },

    setAllFormItemsFull() {
        this.physicalItemsForm.forEach(i => {
            i.receivedQty = i.submittedQty;
            this.updateItemDiscrepancy(i);
        });
    },

    calculateTotalReceivedForm() {
        return this.physicalItemsForm.reduce((acc, i) => acc + (Number(i.receivedQty) || 0), 0);
    },

    calculateTotalSubmittedForm() {
        return this.physicalItemsForm.reduce((acc, i) => acc + (Number(i.submittedQty) || 0), 0);
    },

    calculateTotalTertinggalForm() {
        return Math.max(0, this.calculateTotalSubmittedForm() - this.calculateTotalReceivedForm());
    },

    confirmPhysicalReceipt() {
        if (!this.receivingDoc) return;

        // Validasi tiap item
        for (let itm of this.physicalItemsForm) {
            if (itm.receivedQty < 0) {
                alert(`Kuantitas fisik untuk '${itm.name}' tidak boleh negatif.`);
                return;
            }
            if (itm.selisih > 0 && !itm.discrepancyReason.trim()) {
                itm.discrepancyReason = itm.followUpAction === 'kirim_susulan'
                    ? `Stok tertinggal di ${this.receivingDoc.branchOrigin || 'cabang'}, dijadwalkan pengiriman susulan.`
                    : 'Stok tertinggal / belum diterima fisik di gudang.';
            }
        }

        let totalReceived = 0;
        let totalSubmitted = 0;
        let hasAnyDiscrepancy = false;

        this.physicalItemsForm.forEach(i => {
            const rec = Number(i.receivedQty) || 0;
            const sub = Number(i.submittedQty) || 0;
            totalReceived += rec;
            totalSubmitted += sub;
            if (i.selisih > 0) hasAnyDiscrepancy = true;
        });

        const totalTertinggal = Math.max(0, totalSubmitted - totalReceived);

        // 1. Simpan HANYA kuantitas fisik yang benar-benar diterima ke pos_ilp_stok_retur_gudang_v1 (ISOLASI)
        try {
            const rawReturnStock = localStorage.getItem('pos_ilp_stok_retur_gudang_v1');
            let returnStock = rawReturnStock ? JSON.parse(rawReturnStock) : [];

            this.physicalItemsForm.forEach(formItem => {
                const recQty = Number(formItem.receivedQty) || 0;
                const existingIdx = returnStock.findIndex(r => r.docRef === this.receivingDoc.docNo && (r.sku === formItem.sku || r.name === formItem.name));

                if (recQty > 0) {
                    if (existingIdx !== -1) {
                        returnStock[existingIdx].qtyReceived = recQty;
                        returnStock[existingIdx].updatedAt = this.physicalReceivedDate;
                        returnStock[existingIdx].inspectorNotes = formItem.selisih > 0
                            ? `Diatur fisik: ${recQty}/${formItem.submittedQty} ${formItem.unit}. Tertinggal: ${formItem.selisih} ${formItem.unit} (${formItem.discrepancyReason})`
                            : 'Penerimaan fisik lengkap 100% di Gudang Pusat.';
                    } else {
                        returnStock.unshift({
                            id: 'RET-GD-' + Date.now() + '-' + Math.floor(Math.random() * 900),
                            sku: formItem.sku,
                            barcode: '899' + Math.floor(1000000000 + Math.random() * 9000000000),
                            name: formItem.name,
                            branchOrigin: this.receivingDoc.branchOrigin || 'Cabang A',
                            cashierName: this.receivingDoc.cashierName || 'Siti Aminah (Kasir Cabang A)',
                            docRef: this.receivingDoc.docNo,
                            receivedDate: this.physicalReceivedDate,
                            qtyReceived: recQty,
                            unit: formItem.unit,
                            initialCondition: this.receivingDoc.conditionLabel || 'Retur Toko',
                            inspectionStatus: 'Menunggu Pemeriksaan',
                            followUp: 'Klaim Retur Supplier / QC',
                            inspectorNotes: formItem.selisih > 0
                                ? `Diterima sebagian (${recQty}/${formItem.submittedQty}). Tertinggal: ${formItem.selisih} ${formItem.unit} (${formItem.discrepancyReason})`
                                : 'Penerimaan fisik lengkap di Gudang Pusat.',
                            inspectedBy: this.physicalInspector,
                            updatedAt: this.physicalReceivedDate
                        });
                    }
                } else if (existingIdx !== -1) {
                    returnStock.splice(existingIdx, 1);
                }
            });

            localStorage.setItem('pos_ilp_stok_retur_gudang_v1', JSON.stringify(returnStock));
            window.dispatchEvent(new CustomEvent('pos-return-stock-synced', { detail: { count: returnStock.length } }));
        } catch(e) {
            console.error('Failed to update warehouse return stock:', e);
        }

        // 2. Sinkronkan data ke receivingDoc.products
        if (Array.isArray(this.receivingDoc.products)) {
            this.receivingDoc.products.forEach(p => {
                const fItem = this.physicalItemsForm.find(i => i.sku === p.sku || i.name === p.name);
                if (fItem) {
                    p.receivedQty = Number(fItem.receivedQty) || 0;
                    p.selisih = Math.max(0, (Number(p.submittedQty) || 0) - p.receivedQty);
                    p.discrepancyReason = fItem.discrepancyReason;
                    p.followUpAction = fItem.followUpAction;
                }
            });
        }

        // 3. Update status dokumen
        const bastCode = this.receivingDoc.physicalReceiptData?.bastNo || ('BAST-' + new Date().getFullYear() + '/05/TK-' + Math.floor(1000 + Math.random() * 9000));
        this.receivingDoc.isPhysicalReceived = totalReceived > 0;
        this.receivingDoc.statusKey = totalTertinggal === 0 ? 'selesai' : (totalReceived > 0 ? 'selesai_parsial' : 'tertinggal');
        this.receivingDoc.statusLabel = totalTertinggal === 0 ? 'SELESAI (BAST LENGKAP)' : (totalReceived > 0 ? 'DITERIMA PARSIAL GUDANG' : 'STOK TERTINGGAL');
        this.receivingDoc.statusSub = totalTertinggal === 0 
            ? `BAST: ${bastCode} • Diterima Lengkap 100% (${totalReceived} unit)`
            : `BAST: ${bastCode} • Diterima: ${totalReceived} unit (${totalTertinggal} unit tertinggal di cabang)`;

        this.receivingDoc.physicalReceiptData = {
            receivedDate: this.physicalReceivedDate,
            receivedBy: this.physicalInspector,
            hasDiscrepancy: hasAnyDiscrepancy,
            totalReceived: totalReceived,
            totalSubmitted: totalSubmitted,
            totalTertinggal: totalTertinggal,
            discrepancyReason: this.physicalGlobalNotes || (hasAnyDiscrepancy ? `${totalTertinggal} unit stok tertinggal/susulan` : 'Fisik 100% cocok dengan dokumen'),
            bastNo: bastCode,
            items: JSON.parse(JSON.stringify(this.physicalItemsForm))
        };

        // Jika dokumen ini juga yang sedang dibuka di selectedDocForDetail, sinkronkan
        if (this.selectedDocForDetail && this.selectedDocForDetail.docNo === this.receivingDoc.docNo) {
            this.selectedDocForDetail = JSON.parse(JSON.stringify(this.receivingDoc));
        }

        this.saveReturns();

        // 4. Catat Audit Log
        this.logAudit(
            'UPDATE_RECEIVED_RETURN_STOCK',
            this.receivingDoc.docNo,
            `Atur stok retur diterima ${this.receivingDoc.docNo}: ${totalReceived} unit diterima fisik di Gudang Pusat. ${totalTertinggal > 0 ? `PERINGATAN: ${totalTertinggal} unit stok tertinggal di cabang/armada dan tetap dipantau.` : 'Semua stok lengkap diterima, tidak ada yang tertinggal.'}`
        );

        this.showPhysicalModal = false;
        this.triggerToast(`Pengaturan stok retur ${this.receivingDoc.docNo} tersimpan! Diterima: ${totalReceived} unit (${totalTertinggal > 0 ? `${totalTertinggal} unit tertinggal dipantau` : 'Lengkap tanpa stok tertinggal'}).`);
    },

    viewBast(doc) {
        this.selectedBastDoc = doc;
        this.showBastModal = true;
    },

    // Audit Logging Helper
    logAudit(actionType, refNo, desc) {
        try {
            const rawLogs = localStorage.getItem('pos_ilp_audit_logs_v1');
            const logs = rawLogs ? JSON.parse(rawLogs) : [];
            logs.unshift({
                id: 'AUD-' + Date.now(),
                timestamp: new Date().toISOString().replace('T', ' ').substring(0, 19),
                actor_id: 'USR-MGR-001',
                actor_name: 'Budi Santoso',
                actor_role: 'Manager',
                action_category: 'LOGISTICS_RETURN',
                action_type: actionType,
                reference_number: refNo,
                location: 'Gudang Pusat',
                description: desc,
                status: 'SUCCESS'
            });
            localStorage.setItem('pos_ilp_audit_logs_v1', JSON.stringify(logs.slice(0, 300)));
        } catch(e) {
            console.error('Audit log error:', e);
        }
    },

    // Quick Draft Submission
    submitQuickDraft() {
        if (!this.newDocSku.trim() || !this.newDocQty) return;
        const isTokoToGudang = this.modalMode === 'toko_ke_gudang';
        const codeNum = Math.floor(896 + Math.random() * 90);
        let docId = isTokoToGudang ? '#RTR-TK-2024-0' + codeNum : '#REP-GD-2024-0' + codeNum;
        const prodName = this.newDocSku.includes('-') ? this.newDocSku.split('-')[1].trim() : this.newDocSku.trim();

        let newDbId = Date.now();

        try {
            const res = await fetch('/api/returns', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    direction: this.modalMode || 'toko_ke_gudang',
                    condition_type: this.newDocCondition === 'ED' ? 'kadaluarsa' : 'rusak',
                    sku: this.newDocSku,
                    qty: Number(this.newDocQty) || 1,
                    unit: this.newDocUnit || 'Pcs',
                    reason: isTokoToGudang ? 'Input cepat draft retur terverifikasi' : 'Pengiriman barang pengganti dari Gudang DC',
                    driver_name: this.newDocDriver || (isTokoToGudang ? 'Andi Pratama' : 'Dedi Kurnia'),
                    vehicle_plate: 'B 9481 UQ',
                    use_fleet: true
                })
            });

            if (res.ok) {
                const json = await res.json();
                if (json.success && json.data) {
                    newDbId = json.data.id;
                    if (json.data.return_number) {
                        docId = json.data.return_number;
                    }
                }
            }
        } catch(e) {
            console.error('API create return error', e);
        }

        const newDoc = {
            id: newDbId,
            docNo: docId,
            date: 'Baru saja • Hari Ini',
            ref: isTokoToGudang ? 'Ref: DRAFT-' + this.newDocBranch.toUpperCase() : 'Klaim: #RTR-TK-2024-0892',
            direction: this.modalMode,
            directionLabel: isTokoToGudang ? 'TOKO → GUDANG' : 'GUDANG → TOKO',
            branchOrigin: isTokoToGudang ? this.newDocBranch : 'Gudang Pusat',
            cashierName: this.newDocCashier || 'Siti Aminah (Kasir Cabang A)',
            terminalId: 'POS-01 (Kasir Toko)',
            conditionType: 'rusak',
            conditionLabel: this.newDocCondition,
            conditionDesc: this.newDocReason || 'Pengajuan retur barang rusak/ED dari kasir toko',
            products: [
                { sku: 'PRD-007', barcode: '8996789012345', name: prodName, category: 'Barang Konsumsi', submittedQty: Number(this.newDocQty), unit: this.newDocUnit, price: 25000, subtotal: 25000 * Number(this.newDocQty), color: 'text-[#dc2626]' }
            ],
            subtotalLabel: 'Subtotal:',
            subtotal: 'Rp ' + (25000 * Number(this.newDocQty)).toLocaleString('id-ID'),
            truckFee: isTokoToGudang ? '-Rp 500.000' : 'GRATIS (SOP)',
            truckFeeFree: !isTokoToGudang,
            netLabel: isTokoToGudang ? 'Net Klaim:' : 'Total:',
            netAmount: 'Rp ' + Math.max(0, (25000 * Number(this.newDocQty)) - (isTokoToGudang ? 500000 : 0)).toLocaleString('id-ID'),
            plate: 'B 9481 UQ',
            driver: this.newDocDriver,
            statusKey: isTokoToGudang ? 'menunggu_approval' : 'perjalanan',
            statusLabel: isTokoToGudang ? 'MENUNGGU APPROVAL' : 'DALAM PERJALANAN',
            statusSub: isTokoToGudang ? 'Menunggu telaah Manager Toko' : 'Dikirim dari Gudang DC',
            needsApproval: isTokoToGudang,
            isPhysicalReceived: false,
            physicalReceiptData: null
        };

        this.items.unshift(this.normalizeReturnDoc(newDoc));
        this.saveReturns();
        this.logAudit('CREATE_RETURN_DRAFT', docId, `Dibuat pengajuan retur ${docId} dari ${this.newDocBranch} oleh ${this.newDocCashier}.`);
        this.showModal = false;
        this.newDocSku = '';
        this.newDocReason = '';
        this.triggerToast(`Dokumen ${docId} berhasil dibuat.`);
    }
}"
x-init="initReturns()"
class="flex h-screen w-full bg-[#f8fafc] font-sans text-slate-800 overflow-hidden">

    <!-- Manager Sidebar -->
    <x-manager-sidebar :active="$activeNav ?? (request()->is('*retur-masuk*') ? 'retur_masuk' : 'retur')" />

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden min-w-0">

        <!-- Top Header Bar -->
        <header class="h-[64px] bg-white border-b border-slate-200/80 px-8 flex items-center justify-between shrink-0 z-10">
            <div class="relative w-full max-w-[380px]">
                <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                <input type="text" x-model="searchQuery" placeholder="Cari dokumen, kasir, SKU, produk, driver..." class="w-full bg-[#f1f5f9] border border-transparent focus:border-blue-500 focus:bg-white rounded-xl pl-10 pr-4 py-2 text-[13px] font-medium text-slate-700 placeholder-slate-400 focus:outline-none transition-all">
            </div>
            <div class="flex items-center gap-4">
                <a href="{{ url('/manager/inventory?tab=retur') }}" class="px-3 py-1.5 rounded-lg text-xs font-bold text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-300 flex items-center gap-1.5 transition-colors">
                    <span class="material-symbols-rounded text-[16px]">warehouse</span>
                    <span>Monitoring Stok Retur Gudang</span>
                </a>
                <button class="relative w-9 h-9 rounded-full hover:bg-slate-100 flex items-center justify-center text-slate-600 transition-colors cursor-pointer" title="Notifikasi">
                    <span class="material-symbols-rounded text-[21px]">notifications</span>
                    <span class="absolute top-1.5 right-2 w-2 h-2 rounded-full bg-orange-500 ring-2 ring-white"></span>
                </button>
            </div>
        </header>

        <!-- Scrollable Page Body -->
        <main class="flex-1 overflow-y-auto px-6 lg:px-8 py-6">
            <div class="max-w-[1440px] mx-auto space-y-5">

                <!-- Title Header & Breadcrumb -->
                <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-1.5 text-[10px] font-extrabold text-slate-400 tracking-wider uppercase mb-1.5">
                            <span>OPERASIONAL CABANG</span>
                            <span class="material-symbols-rounded text-[13px]">chevron_right</span>
                            <span>INVENTARIS &amp; LOGISTIK</span>
                            <span class="material-symbols-rounded text-[13px]">chevron_right</span>
                            <span class="text-[#0052cc]">MANAJEMEN RETUR BARANG</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-[24px] lg:text-[26px] font-black text-[#0f172a] tracking-tight leading-tight">Manajemen Retur Barang Toko ke Gudang</h1>
                            <span class="inline-flex items-center gap-1.5 bg-[#dbeafe] text-[#1e40af] text-[12px] font-extrabold px-3 py-1 rounded-full border border-blue-200/80 shadow-2xs">
                                <span>Pusat &amp; Cabang</span>
                                <span class="material-symbols-rounded text-[15px]">arrow_forward</span>
                                <span>Gudang Pusat (DC)</span>
                            </span>
                        </div>
                        <p class="text-[13px] text-slate-500 font-medium mt-1.5 max-w-2xl leading-relaxed">
                            Persetujuan manajerial pengajuan retur dari kasir cabang serta konfirmasi penerimaan fisik barang masuk di Gudang Pusat dengan isolasi penuh terhadap stok normal toko.
                        </p>
                    </div>

                    <!-- SOP Rule Alert Badge -->
                    <div class="bg-amber-50 border border-amber-200/90 rounded-xl px-4 py-2.5 flex items-center gap-3 shrink-0 self-start shadow-2xs">
                        <div class="w-9 h-9 rounded-lg bg-amber-500 text-white flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[20px]">shield</span>
                        </div>
                        <div class="flex flex-col leading-tight">
                            <span class="text-[10px] font-extrabold text-amber-900 uppercase tracking-wider">SOP ISOLASI RETUR</span>
                            <span class="text-[11.5px] font-bold text-amber-800 mt-0.5">
                                Fisik Diterima &rarr; Stok Retur (Bukan Normal)
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 5 KPI Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
                    <!-- Card 1 -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-2xs flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider leading-snug">TOTAL DOKUMEN RETUR</span>
                            <div class="text-[24px] font-black text-[#0f172a] font-mono tracking-tight mt-2" x-text="items.length">0</div>
                        </div>
                        <div class="text-[11px] text-slate-500 font-semibold mt-3">Semua riwayat pengajuan</div>
                    </div>

                    <!-- Card 2: Menunggu Persetujuan Manager -->
                    <div class="bg-white border border-amber-200 rounded-2xl p-4 shadow-2xs flex flex-col justify-between border-l-4 border-l-amber-500">
                        <div>
                            <div class="flex items-start justify-between gap-1">
                                <span class="text-[10px] font-extrabold text-amber-700 uppercase tracking-wider leading-snug">MENUNGGU APPROVAL</span>
                                <span class="material-symbols-rounded text-[18px] text-amber-500">pending_actions</span>
                            </div>
                            <div class="text-[24px] font-black text-amber-600 font-mono tracking-tight mt-2" x-text="countPendingApproval">0</div>
                        </div>
                        <div class="text-[11px] text-amber-800 font-semibold mt-3">Perlu telaah Manager Toko</div>
                    </div>

                    <!-- Card 3: Siap Diterima Fisik Gudang -->
                    <div class="bg-white border border-blue-200 rounded-2xl p-4 shadow-2xs flex flex-col justify-between border-l-4 border-l-[#0052cc]">
                        <div>
                            <div class="flex items-start justify-between gap-1">
                                <span class="text-[10px] font-extrabold text-[#0052cc] uppercase tracking-wider leading-snug">SIAP CEK FISIK GUDANG</span>
                                <span class="material-symbols-rounded text-[18px] text-[#0052cc]">archive</span>
                            </div>
                            <div class="text-[24px] font-black text-[#0052cc] font-mono tracking-tight mt-2" x-text="countApprovedPendingPhysical">0</div>
                        </div>
                        <div class="text-[11px] text-blue-800 font-semibold mt-3">Disetujui &amp; Menuju Gudang</div>
                    </div>

                    <!-- Card 4: Selesai / Diterima Gudang -->
                    <div class="bg-white border border-emerald-200 rounded-2xl p-4 shadow-2xs flex flex-col justify-between border-l-4 border-l-emerald-500">
                        <div>
                            <div class="flex items-start justify-between gap-1">
                                <span class="text-[10px] font-extrabold text-emerald-700 uppercase tracking-wider leading-snug">SELESAI TERIMA FISIK</span>
                                <span class="material-symbols-rounded text-[18px] text-emerald-500">task_alt</span>
                            </div>
                            <div class="text-[24px] font-black text-emerald-600 font-mono tracking-tight mt-2" x-text="countCompletedReceipt">0</div>
                        </div>
                        <div class="text-[11px] text-emerald-800 font-semibold mt-3">Memiliki BAST resmi</div>
                    </div>

                    <!-- Card 5: Kuantitas di Karantina Gudang -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-2xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between gap-1">
                                <span class="text-[10px] font-extrabold text-slate-600 uppercase tracking-wider leading-snug">STOK RETUR KARANTINA</span>
                                <span class="material-symbols-rounded text-[18px] text-amber-600">warehouse</span>
                            </div>
                            <div class="text-[22px] font-black text-amber-700 font-mono tracking-tight mt-2" x-text="totalQuarantineUnitsInGudang + ' Unit'">0 Unit</div>
                        </div>
                        <div class="text-[11px] text-slate-500 font-semibold mt-3">Gudang Pusat (Terisolasi)</div>
                    </div>
                </div>

                <!-- Navigation Tabs Bar (3 Main Sub-Features) -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-2.5 flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-3 shadow-2xs">
                    <!-- Left Tab Buttons -->
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Tab 1: Persetujuan Retur -->
                        <button type="button"
                                @click="mainTab = 'approval'"
                                :class="mainTab === 'approval' ? 'bg-[#0047b3] text-white shadow-xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600'"
                                class="px-4 py-2.5 rounded-xl text-[12.5px] font-extrabold flex items-center gap-2.5 transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[17px]">verified</span>
                            <span>Persetujuan Retur (Toko &rarr; Gudang)</span>
                            <span :class="mainTab === 'approval' ? 'bg-white text-[#0047b3]' : 'bg-slate-200 text-slate-700'"
                                  class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full"
                                  x-text="countPendingApproval + ' Menunggu'"></span>
                        </button>

                        <!-- Tab 2: Barang Masuk Retur (Cabang ke Gudang) -->
                        <a href="{{ url('/manager/retur-masuk') }}"
                           class="px-4 py-2.5 rounded-xl text-[12.5px] font-extrabold flex items-center gap-2.5 transition-all cursor-pointer bg-slate-50 hover:bg-slate-100 text-slate-600">
                            <span class="material-symbols-rounded text-[17px]">archive</span>
                            <span>Barang Masuk Retur (Cabang ke Gudang)</span>
                            <span class="bg-purple-100 text-purple-800 text-[10.5px] font-extrabold px-2 py-0.5 rounded-full">Portal Terpadu &rarr;</span>
                        </a>

                        <!-- Tab 3: Riwayat BAST & Serah Terima -->
                        <button type="button"
                                @click="mainTab = 'riwayat'"
                                :class="mainTab === 'riwayat' ? 'bg-[#0047b3] text-white shadow-xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600'"
                                class="px-4 py-2.5 rounded-xl text-[12.5px] font-extrabold flex items-center gap-2.5 transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[17px]">assignment_turned_in</span>
                            <span>Riwayat Dokumen &amp; BAST</span>
                            <span :class="mainTab === 'riwayat' ? 'bg-white text-[#0047b3]' : 'bg-emerald-100 text-emerald-800'"
                                  class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full"
                                  x-text="countCompletedReceipt + ' Selesai'"></span>
                        </button>
                    </div>

                    <!-- Right Action Button -->
                    <div class="flex items-center gap-2.5">
                        <button type="button"
                                @click="modalMode = 'toko_ke_gudang'; showModal = true;"
                                class="bg-[#0047b3] hover:bg-[#003994] text-white px-4 py-2.5 rounded-xl text-[12px] font-extrabold flex items-center gap-2 shadow-xs transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[17px]">add_circle</span>
                            <span>+ Buat Pengajuan Retur Baru</span>
                        </button>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- TAB 1: PERSETUJUAN RETUR MANAGER (TOKO -> GUDANG) -->
                <!-- ======================================================== -->
                <div x-show="mainTab === 'approval'" class="space-y-4">

                    <!-- Filter Toolbar -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 flex flex-col md:flex-row items-center justify-between gap-3 shadow-2xs">
                        <div class="flex items-center gap-2 w-full md:w-auto">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Status:</span>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <button type="button" @click="statusFilterApproval = 'all'" :class="statusFilterApproval === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer">Semua</button>
                                <button type="button" @click="statusFilterApproval = 'menunggu'" :class="statusFilterApproval === 'menunggu' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer">Menunggu Approval</button>
                                <button type="button" @click="statusFilterApproval = 'disetujui'" :class="statusFilterApproval === 'disetujui' ? 'bg-[#0047b3] text-white' : 'bg-blue-50 text-blue-800 hover:bg-blue-100 border border-blue-200'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer">Disetujui</button>
                                <button type="button" @click="statusFilterApproval = 'revisi'" :class="statusFilterApproval === 'revisi' ? 'bg-purple-600 text-white' : 'bg-purple-50 text-purple-800 hover:bg-purple-100 border border-purple-200'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer">Perlu Revisi</button>
                                <button type="button" @click="statusFilterApproval = 'ditolak'" :class="statusFilterApproval === 'ditolak' ? 'bg-rose-600 text-white' : 'bg-rose-50 text-rose-800 hover:bg-rose-100 border border-rose-200'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer">Ditolak</button>
                            </div>
                        </div>

                        <div class="text-xs text-slate-500 font-medium">
                            Menampilkan <span class="font-bold text-slate-800" x-text="filteredApprovalList.length">0</span> pengajuan retur cabang
                        </div>
                    </div>

                    <!-- Table Dokumen Persetujuan Retur (Standardized Grouped Rows & Single-Line Cells) -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-2xs overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead class="bg-slate-50 border-b border-slate-200/80 text-slate-500 font-extrabold uppercase tracking-wider text-[11px] whitespace-nowrap">
                                    <tr>
                                        <th class="py-3 px-4">No. Dokumen</th>
                                        <th class="py-3 px-4">Asal Toko</th>
                                        <th class="py-3 px-4">Tanggal</th>
                                        <th class="py-3 px-4">Alasan Retur</th>
                                        <th class="py-3 px-4">SKU</th>
                                        <th class="py-3 px-4">Nama Produk</th>
                                        <th class="py-3 px-4 text-center">Qty Diajukan</th>
                                        <th class="py-3 px-4">Pengirim</th>
                                        <th class="py-3 px-4">Plat Nomor</th>
                                        <th class="py-3 px-4">Status Pengajuan</th>
                                        <th class="py-3 px-4 text-center">Tindakan Manager</th>
                                    </tr>
                                </thead>
                                <tbody class="text-slate-700">
                                    <template x-for="item in filteredApprovalList" :key="item.id">
                                        <template x-for="(prod, pIdx) in ((item.products && item.products.length > 0) ? item.products : [{ name: '-', sku: '-', submittedQty: '-' }])" :key="pIdx">
                                            <tr class="hover:bg-slate-50/70 transition-colors" :class="pIdx === 0 ? 'border-t border-slate-200' : 'border-t border-slate-100'">
                                                <!-- Parent Col 1: No Dokumen -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="item.products && item.products.length > 0 ? item.products.length : 1" class="py-3 px-4 align-middle whitespace-nowrap bg-white font-mono font-black text-slate-900 text-[13px]">
                                                        <button type="button"
                                                                @click="openDetailDoc(item)"
                                                                class="hover:text-[#0052cc] hover:underline inline-flex items-center gap-1 cursor-pointer"
                                                                :title="'Buka detail ' + item.docNo">
                                                            <span x-text="item.docNo"></span>
                                                            <span class="material-symbols-rounded text-[14px] text-slate-400">open_in_new</span>
                                                        </button>
                                                    </td>
                                                </template>

                                                <!-- Parent Col 2: Asal Toko -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="item.products && item.products.length > 0 ? item.products.length : 1" class="py-3 px-4 align-middle whitespace-nowrap bg-white">
                                                        <div class="inline-flex items-center gap-1.5 font-bold text-slate-800 text-xs">
                                                            <span class="material-symbols-rounded text-[15px] text-[#0052cc]">store</span>
                                                            <span x-text="item.branchOrigin || 'Cabang A'"></span>
                                                        </div>
                                                    </td>
                                                </template>

                                                <!-- Parent Col 3: Tanggal -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="item.products && item.products.length > 0 ? item.products.length : 1" class="py-3 px-4 align-middle whitespace-nowrap bg-white text-xs text-slate-600 font-medium" x-text="item.date">
                                                    </td>
                                                </template>

                                                <!-- Parent Col 4: Alasan Retur -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="item.products && item.products.length > 0 ? item.products.length : 1" class="py-3 px-4 align-middle whitespace-nowrap bg-white">
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[10px] font-extrabold uppercase border"
                                                              :class="{
                                                                  'bg-red-50 text-red-700 border-red-200': item.conditionType === 'rusak',
                                                                  'bg-amber-50 text-amber-700 border-amber-200': item.conditionType === 'ed',
                                                                  'bg-blue-50 text-blue-700 border-blue-200': item.conditionType === 'overstock',
                                                                  'bg-emerald-50 text-emerald-700 border-emerald-200': item.conditionType === 'garansi'
                                                              }" x-text="item.conditionLabel"></span>
                                                    </td>
                                                </template>

                                                <!-- Child Col 5: SKU -->
                                                <td class="py-3 px-4 align-middle whitespace-nowrap font-mono text-xs text-slate-600 font-bold" x-text="prod.sku || '-'"></td>

                                                <!-- Child Col 6: Nama Produk -->
                                                <td class="py-3 px-4 align-middle whitespace-nowrap font-bold text-slate-800 text-xs max-w-[220px] truncate" :title="prod.name" x-text="prod.name"></td>

                                                <!-- Child Col 7: Qty Diajukan -->
                                                <td class="py-3 px-4 align-middle whitespace-nowrap text-center">
                                                    <span class="font-mono font-bold text-xs bg-blue-50 text-[#0047b3] border border-blue-200 px-2 py-0.5 rounded" x-text="formatQtyClean(prod)"></span>
                                                </td>

                                                <!-- Parent Col 8: Pengirim -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="item.products && item.products.length > 0 ? item.products.length : 1" class="py-3 px-4 align-middle whitespace-nowrap bg-white text-xs font-bold text-slate-800" x-text="item.driver || '-'">
                                                    </td>
                                                </template>

                                                <!-- Parent Col 9: Plat Nomor -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="item.products && item.products.length > 0 ? item.products.length : 1" class="py-3 px-4 align-middle whitespace-nowrap bg-white font-mono text-xs text-slate-600" x-text="item.plate || '-'">
                                                    </td>
                                                </template>

                                                <!-- Parent Col 10: Status Pengajuan -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="item.products && item.products.length > 0 ? item.products.length : 1" class="py-3 px-4 align-middle whitespace-nowrap bg-white">
                                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10.5px] font-extrabold uppercase border whitespace-nowrap"
                                                              :class="{
                                                                  'bg-amber-50 text-amber-800 border-amber-200': item.statusKey === 'menunggu_approval' || item.statusKey === 'cek_fisik',
                                                                  'bg-blue-50 text-blue-800 border-blue-200': item.statusKey === 'disetujui',
                                                                  'bg-purple-50 text-purple-800 border-purple-200': item.statusKey === 'revisi',
                                                                  'bg-rose-50 text-rose-800 border-rose-200': item.statusKey === 'ditolak',
                                                                  'bg-emerald-50 text-emerald-800 border-emerald-200': item.statusKey === 'selesai'
                                                              }">
                                                            <span class="w-1.5 h-1.5 rounded-full"
                                                                  :class="{
                                                                      'bg-amber-500': item.statusKey === 'menunggu_approval' || item.statusKey === 'cek_fisik',
                                                                      'bg-blue-600': item.statusKey === 'disetujui',
                                                                      'bg-purple-600': item.statusKey === 'revisi',
                                                                      'bg-rose-600': item.statusKey === 'ditolak',
                                                                      'bg-emerald-600': item.statusKey === 'selesai'
                                                                  }"></span>
                                                            <span x-text="item.statusLabel"></span>
                                                        </span>
                                                    </td>
                                                </template>

                                                <!-- Parent Col 11: Tindakan Manager -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="item.products && item.products.length > 0 ? item.products.length : 1" class="py-3 px-4 align-middle whitespace-nowrap text-center bg-white">
                                                        <template x-if="item.needsApproval || item.statusKey === 'menunggu_approval'">
                                                            <div class="inline-flex items-center justify-center gap-1.5">
                                                                <button type="button"
                                                                        @click="openDetailDoc(item)"
                                                                        class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-bold px-2.5 py-1.5 rounded-lg inline-flex items-center gap-1 transition-colors cursor-pointer"
                                                                        title="Lihat Detail Barang">
                                                                    <span class="material-symbols-rounded text-[14px]">visibility</span>
                                                                    <span>Detail</span>
                                                                </button>
                                                                <button type="button"
                                                                        @click="openApproveModal(item)"
                                                                        class="bg-[#16a34a] hover:bg-[#15803d] text-white text-[11px] font-bold px-3 py-1.5 rounded-lg inline-flex items-center gap-1 shadow-2xs transition-colors cursor-pointer">
                                                                    <span class="material-symbols-rounded text-[14px]">check_circle</span>
                                                                    <span>Approve</span>
                                                                </button>
                                                                <button type="button"
                                                                        @click="openRevisionModal(item)"
                                                                        class="bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 text-[11px] font-bold px-2.5 py-1.5 rounded-lg inline-flex items-center gap-1 transition-colors cursor-pointer"
                                                                        title="Minta Revisi">
                                                                    <span class="material-symbols-rounded text-[14px]">edit_note</span>
                                                                    <span>Revisi</span>
                                                                </button>
                                                                <button type="button"
                                                                        @click="openRejectModal(item)"
                                                                        class="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-[11px] font-bold px-2.5 py-1.5 rounded-lg inline-flex items-center gap-1 transition-colors cursor-pointer"
                                                                        title="Tolak Retur">
                                                                    <span class="material-symbols-rounded text-[14px]">cancel</span>
                                                                    <span>Tolak</span>
                                                                </button>
                                                            </div>
                                                        </template>

                                                        <template x-if="!item.needsApproval && item.statusKey !== 'menunggu_approval'">
                                                            <div class="inline-flex items-center justify-center gap-1.5">
                                                                <button type="button"
                                                                        @click="openDetailDoc(item)"
                                                                        class="bg-blue-50 hover:bg-blue-100 text-[#0052cc] border border-blue-200 text-[11px] font-bold px-2.5 py-1.5 rounded-lg inline-flex items-center gap-1 shadow-2xs transition-colors cursor-pointer">
                                                                    <span class="material-symbols-rounded text-[14px]">visibility</span>
                                                                    <span>Detail</span>
                                                                </button>
                                                                <template x-if="item.direction === 'toko_ke_gudang' && item.statusKey !== 'ditolak'">
                                                                    <button type="button"
                                                                            @click="openPhysicalReceiveModal(item)"
                                                                            class="bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 text-[11px] font-extrabold px-2.5 py-1.5 rounded-lg inline-flex items-center gap-1 shadow-2xs transition-colors cursor-pointer"
                                                                            title="Atur Kuantitas Retur yang Diterima (Anti-Stok Tertinggal)">
                                                                        <span class="material-symbols-rounded text-[14px]">tune</span>
                                                                        <span>Atur Qty</span>
                                                                    </button>
                                                                </template>
                                                            </div>
                                                        </template>
                                                    </td>
                                                </template>
                                            </tr>
                                        </template>
                                    </template>
                                    <tr x-show="filteredApprovalList.length === 0">
                                        <td colspan="11" class="py-10 text-center text-slate-400">
                                            <span class="material-symbols-rounded text-3xl block text-slate-300 mb-1">inbox</span>
                                            <span class="font-bold text-xs text-slate-600">Tidak ada pengajuan retur yang cocok.</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- SOP Reminder Box -->
                    <div class="p-3.5 bg-blue-50/70 border border-blue-200 rounded-xl text-xs text-blue-900 flex items-start gap-2.5">
                        <span class="material-symbols-rounded text-[#0052cc] text-[18px] shrink-0 mt-0.5">info</span>
                        <div>
                            <strong>Catatan Alur SOP:</strong> Persetujuan Manager (Approve) hanya mengubah status pengajuan menjadi 'Disetujui' dan mengizinkan cabang melakukan pengiriman fisik. <strong>Stok Barang Retur belum bertambah</strong> sebelum staf Gudang Pusat mengonfirmasi penerimaan fisik di tab <em>Barang Masuk Retur</em>.
                        </div>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- TAB 2: BARANG MASUK RETUR (KONFIRMASI FISIK GUDANG) -->
                <!-- ======================================================== -->
                <div x-show="mainTab === 'masuk'" class="space-y-4">

                    <!-- Filter Toolbar -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 flex flex-col md:flex-row items-center justify-between gap-3 shadow-2xs">
                        <div class="flex items-center gap-2 w-full md:w-auto">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Status Fisik:</span>
                            <div class="flex items-center gap-1.5">
                                <button type="button" @click="filterPhysicalStatus = 'all'" :class="filterPhysicalStatus === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer">Semua</button>
                                <button type="button" @click="filterPhysicalStatus = 'pending'" :class="filterPhysicalStatus === 'pending' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer">Menunggu Konfirmasi Fisik</button>
                                <button type="button" @click="filterPhysicalStatus = 'received'" :class="filterPhysicalStatus === 'received' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100 border border-emerald-200'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer">Sudah Diterima Fisik</button>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-500 font-medium" x-text="'Menampilkan ' + filteredMasukList.length + ' pengiriman masuk'"></span>
                        </div>
                    </div>

                    <!-- Table Barang Masuk Retur (Standardized Grouped Rows & Single-Line Cells) -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-2xs overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead class="bg-slate-50 border-b border-slate-200/80 text-slate-500 font-extrabold uppercase tracking-wider text-[11px] whitespace-nowrap">
                                    <tr>
                                        <th class="py-3 px-4">No. Dokumen</th>
                                        <th class="py-3 px-4">Asal Toko</th>
                                        <th class="py-3 px-4">Tanggal</th>
                                        <th class="py-3 px-4">Pengirim</th>
                                        <th class="py-3 px-4">Plat Nomor</th>
                                        <th class="py-3 px-4">SKU</th>
                                        <th class="py-3 px-4">Nama Produk</th>
                                        <th class="py-3 px-4 text-center">Qty Diajukan</th>
                                        <th class="py-3 px-4 text-center">Fisik Diterima</th>
                                        <th class="py-3 px-4 text-center">Selisih</th>
                                        <th class="py-3 px-4">Status Fisik</th>
                                        <th class="py-3 px-4">No. BAST</th>
                                        <th class="py-3 px-4 text-center">Aksi Konfirmasi &amp; Detail</th>
                                    </tr>
                                </thead>
                                <tbody class="text-slate-700">
                                    <template x-for="item in filteredMasukList" :key="item.id">
                                        <template x-for="(prod, pIdx) in ((item.products && item.products.length > 0) ? item.products : [{ name: '-', sku: '-', submittedQty: '-' }])" :key="pIdx">
                                            <tr class="hover:bg-slate-50/70 transition-colors" :class="pIdx === 0 ? 'border-t border-slate-200' : 'border-t border-slate-100'">
                                                <!-- Parent Col 1: No Dokumen -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="item.products && item.products.length > 0 ? item.products.length : 1" class="py-3 px-4 align-middle whitespace-nowrap bg-white font-mono font-black text-slate-900 text-[13px]">
                                                        <button type="button"
                                                                @click="openDetailDoc(item)"
                                                                class="hover:text-[#0052cc] hover:underline inline-flex items-center gap-1 cursor-pointer"
                                                                :title="'Buka detail ' + item.docNo">
                                                            <span x-text="item.docNo"></span>
                                                            <span class="material-symbols-rounded text-[14px] text-slate-400">open_in_new</span>
                                                        </button>
                                                    </td>
                                                </template>

                                                <!-- Parent Col 2: Asal Toko -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="item.products && item.products.length > 0 ? item.products.length : 1" class="py-3 px-4 align-middle whitespace-nowrap bg-white">
                                                        <div class="inline-flex items-center gap-1.5 font-bold text-slate-800 text-xs">
                                                            <span class="material-symbols-rounded text-[15px] text-[#0052cc]">store</span>
                                                            <span x-text="item.branchOrigin || 'Cabang A'"></span>
                                                        </div>
                                                    </td>
                                                </template>

                                                <!-- Parent Col 3: Tanggal -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="item.products && item.products.length > 0 ? item.products.length : 1" class="py-3 px-4 align-middle whitespace-nowrap bg-white text-xs text-slate-600 font-medium" x-text="item.date">
                                                    </td>
                                                </template>

                                                <!-- Parent Col 4: Pengirim -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="item.products && item.products.length > 0 ? item.products.length : 1" class="py-3 px-4 align-middle whitespace-nowrap bg-white text-xs font-bold text-slate-800" x-text="item.driver || '-'">
                                                    </td>
                                                </template>

                                                <!-- Parent Col 5: Plat Nomor -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="item.products && item.products.length > 0 ? item.products.length : 1" class="py-3 px-4 align-middle whitespace-nowrap bg-white font-mono text-xs text-slate-600" x-text="item.plate || '-'">
                                                    </td>
                                                </template>

                                                <!-- Child Col 6: SKU -->
                                                <td class="py-3 px-4 align-middle whitespace-nowrap font-mono text-xs text-slate-600 font-bold" x-text="prod.sku || (item.physicalReceiptData?.items?.[pIdx]?.sku || '-')"></td>

                                                <!-- Child Col 7: Nama Produk -->
                                                <td class="py-3 px-4 align-middle whitespace-nowrap font-bold text-slate-800 text-xs max-w-[200px] truncate" :title="prod.name" x-text="prod.name"></td>

                                                <!-- Child Col 8: Qty Diajukan -->
                                                <td class="py-3 px-4 align-middle whitespace-nowrap text-center">
                                                    <span class="font-mono font-bold text-xs bg-blue-50 text-[#0047b3] border border-blue-200 px-2 py-0.5 rounded" x-text="formatQtyClean(prod)"></span>
                                                </td>

                                                <!-- Child Col 9: Fisik Diterima -->
                                                <td class="py-3 px-4 align-middle whitespace-nowrap text-center">
                                                    <template x-if="item.isPhysicalReceived && item.physicalReceiptData?.items?.[pIdx]">
                                                        <span class="font-mono font-bold text-xs bg-amber-100 text-amber-900 px-2 py-0.5 rounded" x-text="item.physicalReceiptData.items[pIdx].receivedQty + ' ' + (item.physicalReceiptData.items[pIdx].unit || 'Pcs')"></span>
                                                    </template>
                                                    <template x-if="!item.isPhysicalReceived">
                                                        <span class="text-slate-400 text-xs italic">Belum Cek</span>
                                                    </template>
                                                </td>

                                                <!-- Child Col 10: Selisih -->
                                                <td class="py-3 px-4 align-middle whitespace-nowrap text-center">
                                                    <template x-if="item.isPhysicalReceived && (item.physicalReceiptData?.items?.[pIdx]?.selisih > 0)">
                                                        <span class="font-mono font-bold text-xs text-rose-600 bg-rose-50 border border-rose-200 px-2 py-0.5 rounded" x-text="'-' + item.physicalReceiptData.items[pIdx].selisih + ' ' + (item.physicalReceiptData.items[pIdx].unit || 'Pcs')"></span>
                                                    </template>
                                                    <template x-if="!item.isPhysicalReceived || !(item.physicalReceiptData?.items?.[pIdx]?.selisih > 0)">
                                                        <span class="text-slate-400 font-mono text-xs">-</span>
                                                    </template>
                                                </td>

                                                <!-- Parent Col 11: Status Fisik -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="item.products && item.products.length > 0 ? item.products.length : 1" class="py-3 px-4 align-middle whitespace-nowrap bg-white">
                                                        <template x-if="item.isPhysicalReceived">
                                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10.5px] font-extrabold border"
                                                                  :class="item.physicalReceiptData?.hasDiscrepancy ? 'bg-amber-50 text-amber-900 border-amber-300' : 'bg-emerald-50 text-emerald-800 border-emerald-300'">
                                                                <span class="material-symbols-rounded text-[14px]" x-text="item.physicalReceiptData?.hasDiscrepancy ? 'warning' : 'check_circle'"></span>
                                                                <span x-text="item.physicalReceiptData?.hasDiscrepancy ? 'DITERIMA PARSIAL GUDANG' : 'DITERIMA LENGKAP GUDANG'"></span>
                                                            </span>
                                                        </template>
                                                        <template x-if="!item.isPhysicalReceived">
                                                            <template x-if="item.statusKey === 'disetujui' || item.statusKey === 'cek_fisik'">
                                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10.5px] font-extrabold bg-blue-50 text-[#0052cc] border border-blue-200">
                                                                    <span class="material-symbols-rounded text-[14px]">local_shipping</span>
                                                                    <span>Siap Konfirmasi Fisik</span>
                                                                </span>
                                                            </template>
                                                            <template x-if="item.statusKey === 'menunggu_approval'">
                                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10.5px] font-extrabold bg-amber-50 text-amber-800 border border-amber-200">
                                                                    <span class="material-symbols-rounded text-[14px]">hourglass_top</span>
                                                                    <span>Menunggu Approval Manager</span>
                                                                </span>
                                                            </template>
                                                        </template>
                                                    </td>
                                                </template>

                                                <!-- Parent Col 12: No. BAST -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="item.products && item.products.length > 0 ? item.products.length : 1" class="py-3 px-4 align-middle whitespace-nowrap bg-white font-mono text-xs font-bold text-slate-700" x-text="item.physicalReceiptData?.bastNo || '-'">
                                                    </td>
                                                </template>

                                                <!-- Parent Col 13: Aksi Konfirmasi & Detail -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="item.products && item.products.length > 0 ? item.products.length : 1" class="py-3 px-4 align-middle whitespace-nowrap text-center bg-white">
                                                        <div class="inline-flex items-center justify-center gap-1.5">
                                                            <button type="button"
                                                                    @click="openDetailDoc(item)"
                                                                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-bold px-2.5 py-1.5 rounded-lg inline-flex items-center gap-1 transition-colors cursor-pointer"
                                                                    title="Lihat Detail Barang">
                                                                <span class="material-symbols-rounded text-[14px]">visibility</span>
                                                                <span>Detail</span>
                                                            </button>

                                                            <template x-if="!item.isPhysicalReceived && (item.statusKey === 'disetujui' || item.statusKey === 'cek_fisik')">
                                                                <button type="button"
                                                                        @click="openPhysicalReceiveModal(item)"
                                                                        class="bg-amber-600 hover:bg-amber-700 text-white text-[11px] font-extrabold px-3 py-1.5 rounded-lg inline-flex items-center gap-1 shadow-xs transition-colors cursor-pointer">
                                                                    <span class="material-symbols-rounded text-[15px]">done_all</span>
                                                                    <span>Konfirmasi Fisik</span>
                                                                </button>
                                                            </template>

                                                            <template x-if="item.isPhysicalReceived">
                                                                <div class="inline-flex items-center gap-1">
                                                                    <button type="button"
                                                                            @click="openPhysicalReceiveModal(item)"
                                                                            class="bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 text-[11px] font-extrabold px-2.5 py-1.5 rounded-lg inline-flex items-center gap-1 transition-colors cursor-pointer"
                                                                            title="Atur Ulang / Tambah Stok Susulan">
                                                                        <span class="material-symbols-rounded text-[14px]">tune</span>
                                                                        <span>Atur Qty</span>
                                                                    </button>
                                                                    <button type="button"
                                                                            @click="viewBast(item)"
                                                                            class="bg-slate-800 hover:bg-slate-900 text-white text-[11px] font-bold px-2.5 py-1.5 rounded-lg inline-flex items-center gap-1 transition-colors cursor-pointer">
                                                                        <span class="material-symbols-rounded text-[14px]">description</span>
                                                                        <span>BAST</span>
                                                                    </button>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </td>
                                                </template>
                                            </tr>
                                        </template>
                                    </template>
                                    <tr x-show="filteredMasukList.length === 0">
                                        <td colspan="13" class="py-10 text-center text-slate-400">
                                            <span class="material-symbols-rounded text-3xl block text-slate-300 mb-1">archive</span>
                                            <span class="font-bold text-xs text-slate-600">Tidak ada pengiriman masuk retur.</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- SOP Quarantine Banner -->
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl flex items-start gap-3 shadow-2xs">
                        <span class="material-symbols-rounded text-amber-600 text-[22px] shrink-0 mt-0.5">warehouse</span>
                        <div class="text-xs text-amber-900 leading-relaxed">
                            <strong>Konsekuensi Pencatatan Fisik:</strong> Ketika staf gudang mengonfirmasi penerimaan fisik, kuantitas yang benar-benar diterima akan <strong>langsung dicatat ke database Stok Barang Retur (pos_ilp_stok_retur_gudang_v1)</strong>. Stok Normal toko tidak akan tersentuh dan barang tersebut tidak dapat dijual melalui Kasir POS.
                        </div>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- TAB 3: RIWAYAT DOKUMEN & BAST -->
                <!-- ======================================================== -->
                <div x-show="mainTab === 'riwayat'" class="space-y-4">

                    <!-- Riwayat Table -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-2xs overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Arsip Berita Acara Serah Terima (BAST) &amp; Status Retur</h3>
                            <span class="text-xs text-slate-500 font-medium" x-text="filteredRiwayatList.length + ' Arsip Dokumen'"></span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead class="bg-slate-50 border-b border-slate-200/80 text-slate-500 font-extrabold uppercase tracking-wider text-[11px] whitespace-nowrap">
                                    <tr>
                                        <th class="py-3 px-4">No. BAST</th>
                                        <th class="py-3 px-4">No. Dokumen</th>
                                        <th class="py-3 px-4">Tanggal</th>
                                        <th class="py-3 px-4">Arah Retur</th>
                                        <th class="py-3 px-4">Cabang Asal</th>
                                        <th class="py-3 px-4">SKU</th>
                                        <th class="py-3 px-4">Nama Produk</th>
                                        <th class="py-3 px-4 text-center">Qty Diajukan</th>
                                        <th class="py-3 px-4 text-center">Fisik Diterima</th>
                                        <th class="py-3 px-4">Kesesuaian &amp; Catatan</th>
                                        <th class="py-3 px-4">Petugas Gudang</th>
                                        <th class="py-3 px-4 text-center">Aksi Dokumen</th>
                                    </tr>
                                </thead>
                                <tbody class="text-slate-700">
                                    <template x-for="item in filteredRiwayatList" :key="item.id">
                                        <template x-for="(pItem, pIdx) in (((item.physicalReceiptData && item.physicalReceiptData.items && item.physicalReceiptData.items.length > 0) ? item.physicalReceiptData.items : (item.products && item.products.length > 0 ? item.products : [{ name: '-', sku: '-', submittedQty: '-' }])))" :key="pIdx">
                                            <tr class="hover:bg-slate-50/70 transition-colors" :class="pIdx === 0 ? 'border-t border-slate-200' : 'border-t border-slate-100'">
                                                <!-- Parent Col 1: No BAST -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="(item.physicalReceiptData?.items?.length || item.products?.length || 1)" class="py-3 px-4 align-middle whitespace-nowrap bg-white font-mono font-black text-slate-900 text-[13px]">
                                                        <button type="button"
                                                                @click="openDetailDoc(item)"
                                                                class="hover:text-[#0052cc] hover:underline inline-flex items-center gap-1 cursor-pointer"
                                                                :title="'Buka BAST ' + (item.physicalReceiptData?.bastNo || item.docNo)">
                                                            <span x-text="item.physicalReceiptData?.bastNo || item.docNo"></span>
                                                            <span class="material-symbols-rounded text-[14px] text-slate-400">open_in_new</span>
                                                        </button>
                                                    </td>
                                                </template>

                                                <!-- Parent Col 2: No Dokumen -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="(item.physicalReceiptData?.items?.length || item.products?.length || 1)" class="py-3 px-4 align-middle whitespace-nowrap bg-white font-mono text-xs font-bold text-slate-700" x-text="item.docNo">
                                                    </td>
                                                </template>

                                                <!-- Parent Col 3: Tanggal -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="(item.physicalReceiptData?.items?.length || item.products?.length || 1)" class="py-3 px-4 align-middle whitespace-nowrap bg-white text-xs text-slate-600 font-medium" x-text="item.date">
                                                    </td>
                                                </template>

                                                <!-- Parent Col 4: Arah Retur -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="(item.physicalReceiptData?.items?.length || item.products?.length || 1)" class="py-3 px-4 align-middle whitespace-nowrap bg-white">
                                                        <span class="inline-block px-2.5 py-0.5 rounded text-[10px] font-extrabold uppercase"
                                                              :class="item.direction === 'toko_ke_gudang' ? 'bg-blue-50 text-[#0052cc]' : 'bg-emerald-50 text-emerald-800'"
                                                              x-text="item.directionLabel"></span>
                                                    </td>
                                                </template>

                                                <!-- Parent Col 5: Cabang Asal -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="(item.physicalReceiptData?.items?.length || item.products?.length || 1)" class="py-3 px-4 align-middle whitespace-nowrap bg-white font-bold text-slate-800 text-xs" x-text="item.branchOrigin || 'Cabang A'">
                                                    </td>
                                                </template>

                                                <!-- Child Col 6: SKU -->
                                                <td class="py-3 px-4 align-middle whitespace-nowrap font-mono text-xs text-slate-600 font-bold" x-text="pItem.sku || '-'"></td>

                                                <!-- Child Col 7: Nama Produk -->
                                                <td class="py-3 px-4 align-middle whitespace-nowrap font-bold text-slate-800 text-xs max-w-[200px] truncate" :title="pItem.name" x-text="pItem.name"></td>

                                                <!-- Child Col 8: Qty Diajukan -->
                                                <td class="py-3 px-4 align-middle whitespace-nowrap text-center">
                                                    <span class="font-mono font-bold text-xs bg-slate-100 text-slate-700 px-2 py-0.5 rounded" x-text="pItem.submittedQty ? (pItem.submittedQty + ' ' + (pItem.unit || 'Pcs')) : formatQtyClean(pItem)"></span>
                                                </td>

                                                <!-- Child Col 9: Fisik Diterima -->
                                                <td class="py-3 px-4 align-middle whitespace-nowrap text-center">
                                                    <template x-if="item.isPhysicalReceived">
                                                        <span class="font-mono font-bold text-xs bg-amber-100 text-amber-900 px-2 py-0.5 rounded" x-text="(pItem.receivedQty !== undefined ? pItem.receivedQty : pItem.qty) + ' ' + (pItem.unit || 'Pcs')"></span>
                                                    </template>
                                                    <template x-if="!item.isPhysicalReceived">
                                                        <span class="text-slate-400 text-xs italic">-</span>
                                                    </template>
                                                </td>

                                                <!-- Parent Col 10: Kesesuaian & Catatan -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="(item.physicalReceiptData?.items?.length || item.products?.length || 1)" class="py-3 px-4 align-middle whitespace-nowrap bg-white">
                                                        <template x-if="item.isPhysicalReceived">
                                                            <div>
                                                                <template x-if="item.physicalReceiptData?.hasDiscrepancy">
                                                                    <span class="text-amber-800 font-bold text-xs inline-flex items-center gap-1">
                                                                        <span class="material-symbols-rounded text-[14px]">warning</span>
                                                                        <span>Parsial: <span class="font-normal" x-text="item.physicalReceiptData?.discrepancyReason || 'Ada selisih'"></span></span>
                                                                    </span>
                                                                </template>
                                                                <template x-if="!item.physicalReceiptData?.hasDiscrepancy">
                                                                    <span class="text-emerald-700 font-bold text-xs inline-flex items-center gap-1">
                                                                        <span class="material-symbols-rounded text-[14px]">check</span>
                                                                        <span>100% Cocok (Fisik = Dokumen)</span>
                                                                    </span>
                                                                </template>
                                                            </div>
                                                        </template>
                                                        <template x-if="!item.isPhysicalReceived">
                                                            <span class="text-slate-400 italic text-xs" x-text="item.statusLabel"></span>
                                                        </template>
                                                    </td>
                                                </template>

                                                <!-- Parent Col 11: Petugas Gudang -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="(item.physicalReceiptData?.items?.length || item.products?.length || 1)" class="py-3 px-4 align-middle whitespace-nowrap bg-white text-xs text-slate-700 font-semibold" x-text="item.physicalReceiptData?.receivedBy || item.driver || '-'">
                                                    </td>
                                                </template>

                                                <!-- Parent Col 12: Aksi Dokumen -->
                                                <template x-if="pIdx === 0">
                                                    <td :rowspan="(item.physicalReceiptData?.items?.length || item.products?.length || 1)" class="py-3 px-4 align-middle whitespace-nowrap text-center bg-white">
                                                        <div class="inline-flex items-center justify-center gap-1.5">
                                                            <button type="button"
                                                                    @click="openDetailDoc(item)"
                                                                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-bold px-2.5 py-1.5 rounded-lg inline-flex items-center gap-1 transition-colors cursor-pointer"
                                                                    title="Lihat Detail Barang">
                                                                <span class="material-symbols-rounded text-[14px]">visibility</span>
                                                                <span>Detail</span>
                                                            </button>

                                                            <template x-if="item.isPhysicalReceived">
                                                                <button type="button"
                                                                        @click="viewBast(item)"
                                                                        class="bg-[#0047b3] hover:bg-[#003994] text-white text-[11px] font-bold px-2.5 py-1.5 rounded-lg inline-flex items-center gap-1 shadow-2xs transition-colors cursor-pointer">
                                                                    <span class="material-symbols-rounded text-[14px]">print</span>
                                                                    <span>BAST</span>
                                                                </button>
                                                            </template>
                                                        </div>
                                                    </td>
                                                </template>
                                            </tr>
                                        </template>
                                    </template>
                                    <tr x-show="filteredRiwayatList.length === 0">
                                        <td colspan="12" class="py-10 text-center text-slate-400">
                                            <span class="material-symbols-rounded text-3xl block text-slate-300 mb-1">history</span>
                                            <span class="font-bold text-xs text-slate-600">Tidak ada arsip BAST.</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL DETAIL LENGKAP PENGAJUAN & BARANG RETUR DARI KASIR -->
    <!-- ======================================================== -->
    <div x-show="showDetailDocModal" style="display: none;"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4"
         x-transition.opacity>
        <div @click.away="showDetailDocModal = false"
             class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[92vh]">
            
            <!-- Modal Header -->
            <div class="px-6 py-4.5 bg-slate-900 text-white flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-600/30 text-blue-400 border border-blue-500/40 flex items-center justify-center">
                        <span class="material-symbols-rounded text-[22px]">inventory_2</span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-black tracking-tight">Detail Pengajuan Retur Barang Cabang ke Gudang</h3>
                            <span class="px-2.5 py-0.5 rounded-full text-[10.5px] font-extrabold border"
                                  :class="{
                                      'bg-amber-500/20 text-amber-300 border-amber-500/40': selectedDocForDetail?.statusKey === 'menunggu_approval' || selectedDocForDetail?.statusKey === 'cek_fisik',
                                      'bg-blue-500/20 text-blue-300 border-blue-500/40': selectedDocForDetail?.statusKey === 'disetujui',
                                      'bg-purple-500/20 text-purple-300 border-purple-500/40': selectedDocForDetail?.statusKey === 'revisi',
                                      'bg-rose-500/20 text-rose-300 border-rose-500/40': selectedDocForDetail?.statusKey === 'ditolak',
                                      'bg-emerald-500/20 text-emerald-300 border-emerald-500/40': selectedDocForDetail?.statusKey === 'selesai'
                                  }" x-text="selectedDocForDetail?.statusLabel"></span>
                        </div>
                        <p class="text-xs text-slate-400 font-mono mt-0.5">
                            <span x-text="selectedDocForDetail?.docNo"></span> &bull; 
                            <span x-text="'Asal: ' + (selectedDocForDetail?.branchOrigin || 'Cabang A')"></span> &bull; 
                            <span x-text="selectedDocForDetail?.date"></span>
                        </p>
                    </div>
                </div>
                <button @click="showDetailDocModal = false" class="text-slate-400 hover:text-white cursor-pointer transition-colors p-1 rounded-lg hover:bg-slate-800">
                    <span class="material-symbols-rounded text-[22px]">close</span>
                </button>
            </div>

            <!-- Modal Content (Scrollable) -->
            <div class="p-6 space-y-5 text-xs overflow-y-auto flex-1 bg-[#f8fafc]" x-if="selectedDocForDetail">
                
                <!-- 3 Metadata Cards Row -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3.5">
                    <!-- Card 1: Info Toko & Kasir Pengirim -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200/90 shadow-2xs space-y-2">
                        <div class="flex items-center gap-1.5 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">
                            <span class="material-symbols-rounded text-[15px] text-[#0052cc]">store</span>
                            <span>KASIR &amp; TOKO PENGIRIM</span>
                        </div>
                        <div class="space-y-1 text-slate-700">
                            <div class="font-bold text-[13px] text-slate-900" x-text="selectedDocForDetail?.branchOrigin || 'Cabang A'"></div>
                            <div class="flex items-center justify-between text-slate-500">
                                <span>Petugas Kasir:</span>
                                <strong class="text-slate-800" x-text="selectedDocForDetail?.cashierName || 'Siti Aminah (Kasir 1)'"></strong>
                            </div>
                            <div class="flex items-center justify-between text-slate-500">
                                <span>Terminal POS:</span>
                                <span class="font-mono font-bold text-slate-700" x-text="selectedDocForDetail?.terminalId || 'POS-01'"></span>
                            </div>
                            <div class="flex items-center justify-between text-slate-500">
                                <span>Ref Nota Asal:</span>
                                <span class="font-mono text-slate-700" x-text="selectedDocForDetail?.ref || '-'"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Alasan & Kondisi Retur -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200/90 shadow-2xs space-y-2">
                        <div class="flex items-center gap-1.5 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">
                            <span class="material-symbols-rounded text-[15px] text-amber-600">warning</span>
                            <span>KONDISI &amp; ALASAN KASIR</span>
                        </div>
                        <div class="space-y-1.5">
                            <span class="inline-block px-2.5 py-0.5 rounded text-[10.5px] font-extrabold uppercase border"
                                  :class="{
                                      'bg-red-50 text-red-700 border-red-200': selectedDocForDetail?.conditionType === 'rusak',
                                      'bg-amber-50 text-amber-700 border-amber-200': selectedDocForDetail?.conditionType === 'ed',
                                      'bg-blue-50 text-blue-700 border-blue-200': selectedDocForDetail?.conditionType === 'overstock',
                                      'bg-emerald-50 text-emerald-700 border-emerald-200': selectedDocForDetail?.conditionType === 'garansi'
                                  }" x-text="selectedDocForDetail?.conditionLabel"></span>
                            <p class="text-[11.5px] text-slate-600 leading-snug font-medium" x-text="selectedDocForDetail?.conditionDesc || 'Pengajuan retur barang rusak dari toko cabang'"></p>
                        </div>
                    </div>

                    <!-- Card 3: Informasi Pengiriman -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200/90 shadow-2xs space-y-2">
                        <div class="flex items-center gap-1.5 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">
                            <span class="material-symbols-rounded text-[15px] text-slate-600">local_shipping</span>
                            <span>INFORMASI PENGIRIMAN</span>
                        </div>
                        <div class="space-y-1.5 text-slate-700">
                            <div class="flex items-center justify-between text-slate-500">
                                <span>Nama Pengirim:</span>
                                <strong class="text-slate-900" x-text="selectedDocForDetail?.driver || 'Andi Pratama'"></strong>
                            </div>
                            <div class="flex items-center justify-between text-slate-500">
                                <span>Plat Nomor:</span>
                                <span class="font-mono font-bold text-slate-900" x-text="selectedDocForDetail?.plate || 'B 9481 UQ'"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABEL RINCIAN LENGKAP BARANG RETUR -->
                <div class="bg-white rounded-xl border border-slate-200/90 shadow-2xs overflow-hidden">
                    <div class="px-5 py-3.5 bg-slate-50/80 border-b border-slate-200/80 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-rounded text-[18px] text-[#0052cc]">format_list_bulleted</span>
                            <div>
                                <h4 class="text-xs font-black text-slate-800 uppercase tracking-wider">Rincian Barang &amp; Pengaturan Kuantitas Retur Diterima</h4>
                                <p class="text-[11px] text-slate-500 font-medium">Atur kuantitas fisik yang benar-benar diterima per produk agar tidak ada stok tertinggal.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    @click="openPhysicalReceiveModal(selectedDocForDetail)"
                                    class="px-3.5 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-extrabold text-xs inline-flex items-center gap-1.5 shadow-2xs transition-colors cursor-pointer"
                                    title="Atur Kuantitas Fisik Diterima Seluruh Produk">
                                <span class="material-symbols-rounded text-[15px]">tune</span>
                                <span>Atur Jumlah Diterima (Anti-Stok Tertinggal)</span>
                            </button>
                            <span class="text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded-md" x-text="(selectedDocForDetail?.products?.length || 0) + ' Jenis SKU'"></span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50/50 border-b border-slate-200 text-slate-500 font-extrabold uppercase tracking-wider text-[10.5px]">
                                <tr>
                                    <th class="py-3 px-4">No &amp; SKU</th>
                                    <th class="py-3 px-4">Nama Produk &amp; Kategori</th>
                                    <th class="py-3 px-4 text-center">Qty Diajukan Kasir</th>
                                    <th class="py-3 px-4 text-center">Qty Diterima Gudang</th>
                                    <th class="py-3 px-4 text-center">Status Rekonsiliasi Stok</th>
                                    <th class="py-3 px-4 text-right">Subtotal Nilai</th>
                                    <th class="py-3 px-4 text-center">Aksi Stok</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                <template x-for="(prod, idx) in selectedDocForDetail?.products" :key="idx">
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="py-3 px-4 font-mono font-bold text-slate-900">
                                            <span class="text-blue-700" x-text="prod.sku || ('PRD-00' + (idx + 1))"></span>
                                            <span class="block text-[10px] text-slate-400 font-mono" x-text="prod.barcode || '899275321004'"></span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="font-bold text-slate-900 text-[12.5px]" x-text="prod.name"></div>
                                            <span class="inline-block bg-slate-100 text-slate-600 text-[10px] font-bold px-1.5 py-0.5 rounded mt-0.5" x-text="prod.category || 'Barang Konsumsi'"></span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="inline-block bg-blue-50 text-[#0047b3] font-black font-mono px-3 py-1 rounded-lg text-xs border border-blue-200"
                                                  x-text="formatQtyClean(prod)"></span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <template x-if="selectedDocForDetail?.isPhysicalReceived">
                                                <span class="inline-block px-3 py-1 rounded-lg font-mono font-black text-xs border"
                                                      :class="(prod.receivedQty >= prod.submittedQty) ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : (prod.receivedQty > 0 ? 'bg-amber-50 text-amber-800 border-amber-300' : 'bg-rose-50 text-rose-800 border-rose-200')"
                                                      x-text="(parseInt(prod.receivedQty !== undefined ? prod.receivedQty : prod.submittedQty) || 0) + ' Pcs'"></span>
                                            </template>
                                            <template x-if="!selectedDocForDetail?.isPhysicalReceived">
                                                <span class="inline-block px-2.5 py-1 rounded-md text-[11px] font-bold bg-slate-100 text-slate-500">
                                                    0 Pcs (Belum Diterima)
                                                </span>
                                            </template>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <template x-if="selectedDocForDetail?.isPhysicalReceived">
                                                <div>
                                                    <template x-if="(prod.selisih || 0) === 0">
                                                        <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-800 border border-emerald-200 font-extrabold px-2 py-0.5 rounded text-[10.5px]">
                                                            <span class="material-symbols-rounded text-[13px]">check_circle</span>
                                                            <span>Lengkap (0 Tertinggal)</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="(prod.selisih || 0) > 0">
                                                        <div class="space-y-0.5">
                                                            <span class="inline-flex items-center gap-1 bg-rose-50 text-rose-800 border border-rose-300 font-black px-2 py-0.5 rounded text-[10.5px]">
                                                                <span class="material-symbols-rounded text-[13px]">warning</span>
                                                                <span x-text="'Tertinggal: ' + prod.selisih + ' ' + prod.unit"></span>
                                                            </span>
                                                            <span class="block text-[9.5px] font-bold text-amber-700" x-text="prod.followUpAction === 'kirim_susulan' ? '• Pantau Pengiriman Susulan' : '• Dicatat Selisih'"></span>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                            <template x-if="!selectedDocForDetail?.isPhysicalReceived">
                                                <span class="text-slate-400 italic text-[11px]">Menunggu Cek Fisik</span>
                                            </template>
                                        </td>
                                        <td class="py-3 px-4 text-right font-mono font-black text-slate-900" x-text="'Rp ' + (prod.subtotal ? Number(prod.subtotal).toLocaleString('id-ID') : '150.000')"></td>
                                        <td class="py-3 px-4 text-center">
                                            <button type="button"
                                                    @click="openPhysicalReceiveModal(selectedDocForDetail, prod.sku)"
                                                    class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 rounded-lg font-extrabold text-[11px] inline-flex items-center gap-1 transition-colors cursor-pointer shadow-2xs"
                                                    title="Atur Jumlah Diterima untuk Produk Ini">
                                                <span class="material-symbols-rounded text-[14px]">tune</span>
                                                <span>Atur Qty</span>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="bg-slate-50/80 border-t border-slate-200 font-bold text-slate-800">
                                <tr>
                                    <td colspan="2" class="py-3 px-4 text-slate-600 uppercase tracking-wider text-[11px]">Rekapitulasi Fisik:</td>
                                    <td class="py-3 px-4 text-center font-black font-mono text-[#0052cc] text-xs" x-text="calculateDocTotalQty(selectedDocForDetail)"></td>
                                    <td class="py-3 px-4 text-center font-black font-mono text-emerald-700 text-xs" x-text="calculateDocTotalReceivedQty(selectedDocForDetail)"></td>
                                    <td class="py-3 px-4 text-center font-black font-mono text-xs" :class="calculateDocTotalTertinggalQty(selectedDocForDetail) > 0 ? 'text-rose-600' : 'text-emerald-600'" x-text="calculateDocTotalTertinggalQty(selectedDocForDetail) > 0 ? (calculateDocTotalTertinggalQty(selectedDocForDetail) + ' Unit Tertinggal') : 'Semua Lengkap (0 Tertinggal)'"></td>
                                    <td class="py-3 px-4 text-right font-black font-mono text-slate-900 text-[13px]" x-text="selectedDocForDetail?.subtotal || 'Rp 0'"></td>
                                    <td class="py-3 px-4 text-center">
                                        <button type="button"
                                                @click="openPhysicalReceiveModal(selectedDocForDetail)"
                                                class="text-amber-700 hover:text-amber-900 font-extrabold text-[11px] underline cursor-pointer">
                                            Atur Semua
                                        </button>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- TIMELINE TRACKING ALUR RETUR -->
                <div class="bg-white p-4 rounded-xl border border-slate-200/90 shadow-2xs space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-rounded text-[18px] text-slate-700">timeline</span>
                        <h4 class="text-xs font-black text-slate-800 uppercase tracking-wider">Riwayat Status &amp; Alur Penanganan Retur</h4>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 pt-1 text-center">
                        <!-- Step 1 -->
                        <div class="p-2.5 rounded-xl border bg-emerald-50 border-emerald-200 text-emerald-900 space-y-0.5">
                            <span class="material-symbols-rounded text-[18px] text-emerald-600 block">check_circle</span>
                            <div class="font-black text-[11px]">1. Diajukan Kasir</div>
                            <div class="text-[10px] text-slate-500" x-text="selectedDocForDetail?.date"></div>
                        </div>

                        <!-- Step 2 -->
                        <div class="p-2.5 rounded-xl border space-y-0.5"
                             :class="{
                                 'bg-emerald-50 border-emerald-200 text-emerald-900': selectedDocForDetail?.statusKey === 'disetujui' || selectedDocForDetail?.statusKey === 'selesai',
                                 'bg-amber-50 border-amber-200 text-amber-900': selectedDocForDetail?.statusKey === 'menunggu_approval',
                                 'bg-rose-50 border-rose-200 text-rose-900': selectedDocForDetail?.statusKey === 'ditolak',
                                 'bg-purple-50 border-purple-200 text-purple-900': selectedDocForDetail?.statusKey === 'revisi'
                             }">
                            <span class="material-symbols-rounded text-[18px] block"
                                  :class="{
                                      'text-emerald-600': selectedDocForDetail?.statusKey === 'disetujui' || selectedDocForDetail?.statusKey === 'selesai',
                                      'text-amber-600': selectedDocForDetail?.statusKey === 'menunggu_approval',
                                      'text-rose-600': selectedDocForDetail?.statusKey === 'ditolak',
                                      'text-purple-600': selectedDocForDetail?.statusKey === 'revisi'
                                  }"
                                  x-text="selectedDocForDetail?.statusKey === 'ditolak' ? 'cancel' : (selectedDocForDetail?.statusKey === 'revisi' ? 'edit_note' : (selectedDocForDetail?.statusKey === 'menunggu_approval' ? 'hourglass_top' : 'verified'))"></span>
                            <div class="font-black text-[11px]">2. Review Manager</div>
                            <div class="text-[10px] text-slate-500" x-text="selectedDocForDetail?.statusLabel"></div>
                        </div>

                        <!-- Step 3 -->
                        <div class="p-2.5 rounded-xl border space-y-0.5"
                             :class="(selectedDocForDetail?.statusKey === 'disetujui' || selectedDocForDetail?.statusKey === 'selesai') ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-slate-50 border-slate-200 text-slate-400'">
                            <span class="material-symbols-rounded text-[18px] block" :class="(selectedDocForDetail?.statusKey === 'disetujui' || selectedDocForDetail?.statusKey === 'selesai') ? 'text-emerald-600' : 'text-slate-400'">local_shipping</span>
                            <div class="font-black text-[11px]">3. Pengiriman DC</div>
                            <div class="text-[10px] text-slate-500" x-text="selectedDocForDetail?.driver || 'Andi Pratama'"></div>
                        </div>

                        <!-- Step 4 -->
                        <div class="p-2.5 rounded-xl border space-y-0.5"
                             :class="selectedDocForDetail?.isPhysicalReceived ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-slate-50 border-slate-200 text-slate-400'">
                            <span class="material-symbols-rounded text-[18px] block" :class="selectedDocForDetail?.isPhysicalReceived ? 'text-emerald-600' : 'text-slate-400'">warehouse</span>
                            <div class="font-black text-[11px]">4. Masuk Stok Retur</div>
                            <div class="text-[10px] text-slate-500" x-text="selectedDocForDetail?.isPhysicalReceived ? 'Terkarantina DC' : 'Menunggu Fisik'"></div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Modal Footer with Contextual Actions -->
            <div class="px-6 py-4 bg-white border-t border-slate-200 flex flex-wrap items-center justify-between gap-3 shrink-0">
                <button type="button" @click="showDetailDocModal = false" class="px-4 py-2 rounded-xl text-slate-700 font-bold hover:bg-slate-100 cursor-pointer">
                    Tutup
                </button>

                <div class="flex items-center gap-2">
                    <!-- If pending approval: Manager action buttons right in modal! -->
                    <template x-if="selectedDocForDetail?.needsApproval || selectedDocForDetail?.statusKey === 'menunggu_approval'">
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    @click="showDetailDocModal = false; openRejectModal(selectedDocForDetail);"
                                    class="px-4 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold border border-rose-200 cursor-pointer">
                                Tolak Retur
                            </button>
                            <button type="button"
                                    @click="showDetailDocModal = false; openRevisionModal(selectedDocForDetail);"
                                    class="px-4 py-2 rounded-xl bg-purple-50 hover:bg-purple-100 text-purple-700 font-bold border border-purple-200 cursor-pointer">
                                Minta Revisi
                            </button>
                            <button type="button"
                                    @click="showDetailDocModal = false; openApproveModal(selectedDocForDetail);"
                                    class="px-5 py-2 rounded-xl bg-[#16a34a] hover:bg-[#15803d] text-white font-bold shadow-xs cursor-pointer flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[17px]">check_circle</span>
                                <span>Setujui Retur</span>
                            </button>
                        </div>
                    </template>

                    <!-- Button to adjust return stock received per product (Anti-stok tertinggal) -->
                    <template x-if="selectedDocForDetail && selectedDocForDetail.direction === 'toko_ke_gudang'">
                        <button type="button"
                                @click="openPhysicalReceiveModal(selectedDocForDetail)"
                                class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-extrabold shadow-xs cursor-pointer flex items-center gap-1.5 transition-colors">
                            <span class="material-symbols-rounded text-[17px]">tune</span>
                            <span x-text="selectedDocForDetail.isPhysicalReceived ? 'Atur Ulang / Tambah Qty Diterima' : 'Atur Qty Diterima Fisik'"></span>
                        </button>
                    </template>

                    <!-- If physically received: also offer BAST digital archive -->
                    <template x-if="selectedDocForDetail?.isPhysicalReceived">
                        <button type="button"
                                @click="showDetailDocModal = false; viewBast(selectedDocForDetail);"
                                class="px-5 py-2 rounded-xl bg-[#0047b3] hover:bg-[#003994] text-white font-bold shadow-xs cursor-pointer flex items-center gap-1.5">
                            <span class="material-symbols-rounded text-[17px]">print</span>
                            <span>Lihat Arsip BAST</span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 1: PERSETUJUAN RETUR (APPROVE) -->
    <!-- ======================================================== -->
    <div x-show="showApproveModal" style="display: none;"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4"
         x-transition.opacity>
        <div @click.away="showApproveModal = false"
             class="bg-white rounded-2xl w-full max-w-md shadow-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-5 bg-emerald-50 border-b border-emerald-100 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center">
                        <span class="material-symbols-rounded text-[22px]">verified</span>
                    </div>
                    <div>
                        <h3 class="text-[15px] font-black text-slate-900">Setujui Pengajuan Retur</h3>
                        <p class="text-[11px] text-slate-500 font-medium">Otorisasi Manager Operasional</p>
                    </div>
                </div>
                <button @click="showApproveModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                    <span class="material-symbols-rounded text-[20px]">close</span>
                </button>
            </div>

            <div class="p-6 space-y-4 text-xs" x-if="selectedDocForApproval">
                <p class="text-slate-700 leading-relaxed">
                    Apakah Anda yakin ingin menyetujui dokumen pengajuan retur <strong class="font-mono font-bold text-slate-900" x-text="selectedDocForApproval?.docNo"></strong> dari cabang <strong x-text="selectedDocForApproval?.branchOrigin"></strong>?
                </p>

                <div class="bg-slate-50 rounded-xl p-3 border border-slate-200 space-y-1">
                    <div class="font-bold text-slate-800">Daftar Barang Retur:</div>
                    <template x-for="p in selectedDocForApproval?.products" :key="p.name">
                        <div class="flex items-center justify-between text-slate-600">
                            <span x-text="p.name"></span>
                            <span class="font-mono font-bold" x-text="formatQtyClean(p)"></span>
                        </div>
                    </template>
                </div>

                <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-[11px] text-amber-900 leading-relaxed">
                    <strong>Pemberitahuan SOP:</strong> Persetujuan ini TIDAK menambahkan barang ke Stok Retur Gudang. Barang baru resmi masuk stok retur saat staf Gudang mengonfirmasi penerimaan fisik.
                </div>
            </div>

            <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2.5">
                <button @click="showApproveModal = false" class="px-4 py-2 rounded-xl text-slate-700 font-bold hover:bg-slate-200 cursor-pointer">Batal</button>
                <button @click="confirmApprove()" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-xs cursor-pointer">Ya, Setujui Pengajuan</button>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 2: TOLAK RETUR (REJECT) -->
    <!-- ======================================================== -->
    <div x-show="showRejectModal" style="display: none;"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4"
         x-transition.opacity>
        <div @click.away="showRejectModal = false"
             class="bg-white rounded-2xl w-full max-w-md shadow-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-5 bg-rose-50 border-b border-rose-100 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-rose-600 text-white flex items-center justify-center">
                        <span class="material-symbols-rounded text-[22px]">cancel</span>
                    </div>
                    <div>
                        <h3 class="text-[15px] font-black text-slate-900">Tolak Pengajuan Retur</h3>
                        <p class="text-[11px] text-slate-500 font-medium">Pembatalan Alur Retur Cabang</p>
                    </div>
                </div>
                <button @click="showRejectModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                    <span class="material-symbols-rounded text-[20px]">close</span>
                </button>
            </div>

            <div class="p-6 space-y-4 text-xs" x-if="selectedDocForReject">
                <p class="text-slate-700">
                    Anda akan menolak dokumen <strong class="font-mono font-bold text-slate-900" x-text="selectedDocForReject?.docNo"></strong>. Silakan masukkan alasan penolakan secara jelas:
                </p>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Alasan Penolakan <span class="text-rose-500">*</span></label>
                    <textarea x-model="rejectReason" rows="3"
                              placeholder="Contoh: Kerusakan akibat kelalaian operasional toko / Tidak sesuai kriteria klaim produsen..."
                              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs font-medium focus:border-rose-500 focus:outline-none"></textarea>
                </div>
            </div>

            <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2.5">
                <button @click="showRejectModal = false" class="px-4 py-2 rounded-xl text-slate-700 font-bold hover:bg-slate-200 cursor-pointer">Batal</button>
                <button @click="confirmReject()" class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold shadow-xs cursor-pointer">Tolak Pengajuan</button>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 3: MINTA REVISI DOKUMEN -->
    <!-- ======================================================== -->
    <div x-show="showRevisionModal" style="display: none;"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4"
         x-transition.opacity>
        <div @click.away="showRevisionModal = false"
             class="bg-white rounded-2xl w-full max-w-md shadow-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-5 bg-purple-50 border-b border-purple-100 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-purple-600 text-white flex items-center justify-center">
                        <span class="material-symbols-rounded text-[22px]">edit_note</span>
                    </div>
                    <div>
                        <h3 class="text-[15px] font-black text-slate-900">Minta Revisi Dokumen Retur</h3>
                        <p class="text-[11px] text-slate-500 font-medium">Instruksi Perbaikan untuk Toko</p>
                    </div>
                </div>
                <button @click="showRevisionModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                    <span class="material-symbols-rounded text-[20px]">close</span>
                </button>
            </div>

            <div class="p-6 space-y-4 text-xs" x-if="selectedDocForRevision">
                <p class="text-slate-700">
                    Masukkan instruksi perbaikan data untuk dokumen <strong class="font-mono font-bold text-slate-900" x-text="selectedDocForRevision?.docNo"></strong>:
                </p>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Catatan Permintaan Revisi <span class="text-rose-500">*</span></label>
                    <textarea x-model="revisionNotes" rows="3"
                              placeholder="Contoh: Kuantitas melebihi sisa stok sistem, mohon sesuaikan kuantitas fisik riil barang..."
                              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs font-medium focus:border-purple-500 focus:outline-none"></textarea>
                </div>
            </div>

            <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2.5">
                <button @click="showRevisionModal = false" class="px-4 py-2 rounded-xl text-slate-700 font-bold hover:bg-slate-200 cursor-pointer">Batal</button>
                <button @click="confirmRevision()" class="px-5 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold shadow-xs cursor-pointer">Kirim Permintaan Revisi</button>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 4: ATUR & KONFIRMASI PENERIMAAN FISIK GUDANG (ANTI-STOK TERTINGGAL) -->
    <!-- ======================================================== -->
    <div x-show="showPhysicalModal" style="display: none;"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
         x-transition.opacity>
        <div @click.away="showPhysicalModal = false"
             class="bg-white rounded-2xl w-full max-w-3xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[92vh]">
            
            <!-- Modal Header -->
            <div class="px-6 py-4.5 bg-gradient-to-r from-amber-600 to-amber-700 text-white flex items-center justify-between shrink-0 shadow-xs">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shadow-2xs">
                        <span class="material-symbols-rounded text-2xl">tune</span>
                    </div>
                    <div>
                        <h3 class="text-base font-black leading-tight tracking-tight">Atur Kuantitas Stok Retur Diterima (Gudang Pusat)</h3>
                        <p class="text-xs text-amber-100 font-medium">Sesuaikan kuantitas fisik riil per produk agar tidak ada stok yang tertinggal atau tercecer.</p>
                    </div>
                </div>
                <button @click="showPhysicalModal = false" class="text-white/80 hover:text-white cursor-pointer p-1 rounded-lg hover:bg-white/10 transition-colors">
                    <span class="material-symbols-rounded text-[22px]">close</span>
                </button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="p-6 overflow-y-auto space-y-5 text-xs flex-1" x-if="receivingDoc">
                
                <!-- Document Info Strip -->
                <div class="p-3.5 bg-amber-50/60 rounded-xl border border-amber-200 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <span class="text-[10px] font-extrabold text-amber-900/60 uppercase tracking-wider">NO. DOKUMEN RETUR</span>
                        <div class="font-mono font-black text-slate-900 text-[14px]" x-text="receivingDoc?.docNo"></div>
                    </div>
                    <div>
                        <span class="text-[10px] font-extrabold text-amber-900/60 uppercase tracking-wider">ASAL TOKO CABANG</span>
                        <div class="font-bold text-slate-800" x-text="receivingDoc?.branchOrigin"></div>
                    </div>
                    <div>
                        <span class="text-[10px] font-extrabold text-amber-900/60 uppercase tracking-wider">KASIR PENGAJU</span>
                        <div class="text-slate-700 font-bold" x-text="receivingDoc?.cashierName || 'Kasir Cabang'"></div>
                    </div>
                </div>

                <!-- Rekonsiliasi Realtime Counters & Global Quick Button -->
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs space-y-3">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-rounded text-[18px] text-amber-600">calculate</span>
                            <span class="text-xs font-black text-slate-800 uppercase tracking-wider">Rekonsiliasi Real-Time Kuantitas:</span>
                        </div>
                        <button type="button"
                                @click="setAllFormItemsFull()"
                                class="px-3 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-lg text-xs font-extrabold inline-flex items-center gap-1 transition-colors cursor-pointer shadow-2xs">
                            <span class="material-symbols-rounded text-[14px]">done_all</span>
                            <span>Setel Terima Penuh 100% (Tanpa Ada yang Tertinggal)</span>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                        <div class="p-3 rounded-xl border border-blue-200 bg-blue-50/60 flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-extrabold text-blue-700 uppercase">Diajukan Kasir</span>
                                <div class="text-lg font-black font-mono text-blue-900" x-text="calculateTotalSubmittedForm() + ' Unit'"></div>
                            </div>
                            <span class="material-symbols-rounded text-2xl text-blue-400">upload</span>
                        </div>

                        <div class="p-3 rounded-xl border border-emerald-200 bg-emerald-50/60 flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-extrabold text-emerald-700 uppercase">Diterima Fisik Gudang</span>
                                <div class="text-lg font-black font-mono text-emerald-900" x-text="calculateTotalReceivedForm() + ' Unit'"></div>
                            </div>
                            <span class="material-symbols-rounded text-2xl text-emerald-400">warehouse</span>
                        </div>

                        <div class="p-3 rounded-xl border flex items-center justify-between"
                             :class="calculateTotalTertinggalForm() > 0 ? 'border-rose-300 bg-rose-50/80' : 'border-slate-200 bg-slate-50'">
                            <div>
                                <span class="text-[10px] font-extrabold uppercase" :class="calculateTotalTertinggalForm() > 0 ? 'text-rose-700' : 'text-slate-500'">Stok Tertinggal</span>
                                <div class="text-lg font-black font-mono" :class="calculateTotalTertinggalForm() > 0 ? 'text-rose-700' : 'text-slate-700'" x-text="calculateTotalTertinggalForm() + ' Unit'"></div>
                            </div>
                            <span class="material-symbols-rounded text-2xl" :class="calculateTotalTertinggalForm() > 0 ? 'text-rose-500' : 'text-slate-300'" x-text="calculateTotalTertinggalForm() > 0 ? 'warning' : 'check_circle'"></span>
                        </div>
                    </div>
                </div>

                <!-- Form Items Checklist with Stepper Controls -->
                <div class="space-y-3">
                    <label class="block font-black text-slate-800 uppercase tracking-wider text-[11px]">
                        Atur Kuantitas Diterima per Produk (Diajukan vs Diterima):
                    </label>

                    <div class="space-y-3">
                        <template x-for="(itm, idx) in physicalItemsForm" :key="idx">
                            <div class="p-4 bg-white border rounded-xl shadow-2xs space-y-3 transition-all"
                                 :class="(highlightedSku && itm.sku === highlightedSku) ? 'border-amber-400 ring-2 ring-amber-200' : 'border-slate-200/90'">
                                
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-black text-slate-900 text-[13px]" x-text="itm.name"></span>
                                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600" x-text="itm.category"></span>
                                        </div>
                                        <span class="text-[10.5px] font-mono text-slate-400 block" x-text="'SKU: ' + itm.sku"></span>
                                    </div>
                                    <span class="bg-blue-100 text-[#0052cc] text-xs font-bold px-3 py-1 rounded-lg"
                                          x-text="'Diajukan: ' + itm.submittedQty + ' ' + itm.unit"></span>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                                    <!-- Input & Quick Steppers -->
                                    <div>
                                        <label class="block font-bold text-slate-700 mb-1">
                                            Jumlah Diterima Gudang <span class="text-rose-500">*</span>
                                        </label>
                                        <div class="flex items-center gap-1.5">
                                            <!-- Decrement -->
                                            <button type="button"
                                                    @click="decrementFormItem(itm)"
                                                    class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-black text-base flex items-center justify-center shrink-0 cursor-pointer">
                                                -
                                            </button>
                                            
                                            <!-- Direct Input -->
                                            <input type="number"
                                                   x-model="itm.receivedQty"
                                                   @input="updateItemDiscrepancy(itm)"
                                                   min="0"
                                                   :max="itm.submittedQty"
                                                   class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-lg font-mono font-black text-sm text-center focus:border-amber-500 focus:outline-none">
                                            
                                            <!-- Increment -->
                                            <button type="button"
                                                    @click="incrementFormItem(itm)"
                                                    class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-black text-base flex items-center justify-center shrink-0 cursor-pointer">
                                                +
                                            </button>

                                            <!-- Unit text -->
                                            <span class="font-bold text-slate-600 text-xs shrink-0 px-1" x-text="itm.unit"></span>

                                            <!-- Quick Full Button -->
                                            <button type="button"
                                                    @click="setFormItemFull(itm)"
                                                    class="px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 rounded-lg text-[10.5px] font-bold shrink-0 cursor-pointer"
                                                    title="Terima kuantitas penuh">
                                                Max
                                            </button>
                                            <!-- Quick 0 Button -->
                                            <button type="button"
                                                    @click="setFormItemZero(itm)"
                                                    class="px-2 py-1 bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200 rounded-lg text-[10.5px] font-bold shrink-0 cursor-pointer"
                                                    title="Set 0 (semua tertinggal)">
                                                0
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Status Kalkulasi Selisih / Tertinggal -->
                                    <div>
                                        <label class="block font-bold text-slate-700 mb-1">Status Kuantitas</label>
                                        <div class="px-3 py-1.5 rounded-lg font-mono font-bold text-xs flex items-center justify-between border"
                                             :class="itm.selisih > 0 ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'">
                                            <span x-text="itm.selisih > 0 ? '⚠ Tertinggal: ' + itm.selisih + ' ' + itm.unit : '✓ Lengkap 100% (Tidak Tertinggal)'"></span>
                                            <span class="material-symbols-rounded text-[16px]" x-text="itm.selisih > 0 ? 'warning' : 'check_circle'"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Follow-up Actions for Left-behind stock -->
                                <template x-if="itm.selisih > 0">
                                    <div class="p-3 bg-amber-50/70 border border-amber-200 rounded-xl space-y-2 mt-2">
                                        <div class="flex items-center gap-1.5 text-amber-900 font-extrabold text-[11px]">
                                            <span class="material-symbols-rounded text-[15px] text-amber-600">inventory</span>
                                            <span x-text="'TINDAK LANJUT ' + itm.selisih + ' ' + itm.unit + ' STOK TERTINGGAL:'"></span>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
                                            <label class="flex items-center gap-2 p-2 rounded-lg bg-white border border-amber-200 cursor-pointer hover:bg-amber-50">
                                                <input type="radio" x-model="itm.followUpAction" value="kirim_susulan" class="text-amber-600">
                                                <span class="font-bold text-slate-800">Jadwalkan Pengiriman Susulan (Tetap Dipantau)</span>
                                            </label>
                                            <label class="flex items-center gap-2 p-2 rounded-lg bg-white border border-amber-200 cursor-pointer hover:bg-amber-50">
                                                <input type="radio" x-model="itm.followUpAction" value="batal_sisa" class="text-amber-600">
                                                <span class="font-bold text-slate-800">Batalkan Sisa &amp; Kembalikan ke Cabang</span>
                                            </label>
                                        </div>

                                        <div>
                                            <label class="block font-bold text-slate-700 mb-1 text-[10.5px]">
                                                Catatan Alasan Selisih / Stok Tertinggal:
                                            </label>
                                            <input type="text"
                                                   x-model="itm.discrepancyReason"
                                                   placeholder="Contoh: Barang tertinggal di gudang cabang A, akan diangkut trip delivery berikutnya..."
                                                   class="w-full px-3 py-1.5 bg-white border border-amber-300 rounded-lg text-xs font-medium text-slate-800 focus:outline-none focus:border-amber-500">
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Inspector and Timestamp Form -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Petugas Penerima Gudang (QC)</label>
                        <input type="text" x-model="physicalInspector" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-bold text-slate-800">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Waktu Konfirmasi Fisik</label>
                        <input type="text" x-model="physicalReceivedDate" readonly class="w-full px-3 py-2 bg-slate-100 border border-slate-200 rounded-lg text-xs font-mono text-slate-600">
                    </div>
                </div>

                <!-- Isolation and Anti-Loss Policy -->
                <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-[11px] text-slate-700 leading-relaxed space-y-1">
                    <div class="font-extrabold flex items-center gap-1.5 text-slate-900">
                        <span class="material-symbols-rounded text-[16px] text-[#0052cc]">shield</span>
                        <span>JAMINAN SISTEM BEBAS STOK TERTINGGAL:</span>
                    </div>
                    <p>
                        Setiap kuantitas yang Anda masukkan di atas akan langsung dialokasikan ke <strong>Stok Retur Gudang Pusat</strong>. Bila terdapat sisa stok tertinggal, sistem tetap menyimpannya dalam pantauan penerimaan susulan sehingga Manager dapat menambahkan sisa stok tersebut kapan saja ketika fisik tiba.
                    </p>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2.5 shrink-0">
                <button type="button" @click="showPhysicalModal = false" class="px-4 py-2 rounded-xl text-slate-700 font-bold hover:bg-slate-200 cursor-pointer">Batal</button>
                <button type="button" @click="confirmPhysicalReceipt()" class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-extrabold shadow-xs cursor-pointer flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[18px]">done_all</span>
                    <span>Simpan &amp; Perbarui Stok Retur Gudang</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 5: DETAIL ARSIP BAST RESMI -->
    <!-- ======================================================== -->
    <div x-show="showBastModal" style="display: none;"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4"
         x-transition.opacity>
        <div @click.away="showBastModal = false"
             class="bg-white rounded-2xl w-full max-w-lg shadow-2xl border border-slate-200 overflow-hidden flex flex-col">
            <div class="px-6 py-4 bg-slate-800 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-rounded text-[20px] text-emerald-400">verified</span>
                    <h3 class="text-sm font-black">Berita Acara Serah Terima (BAST) Digital</h3>
                </div>
                <button @click="showBastModal = false" class="text-slate-400 hover:text-white cursor-pointer">
                    <span class="material-symbols-rounded text-[20px]">close</span>
                </button>
            </div>

            <div class="p-6 space-y-4 text-xs" x-if="selectedBastDoc">
                <div class="text-center pb-3 border-b border-slate-200">
                    <h4 class="text-base font-black text-slate-900 tracking-tight">KOPERASI KARYAWAN INDAH KIAT (KOPKAR ILP)</h4>
                    <p class="text-[11px] text-slate-500 font-medium">BUKTI RESMI SERAH TERIMA BARANG RETUR LOGISTIK GUDANG</p>
                    <div class="inline-block mt-2 font-mono font-black text-slate-800 bg-slate-100 px-3 py-1 rounded-md text-xs border border-slate-200"
                         x-text="selectedBastDoc?.physicalReceiptData?.bastNo || 'BAST-2024/05/TK-0888'"></div>
                </div>

                <div class="space-y-2 text-[11.5px]">
                    <div class="flex justify-between py-1 border-b border-slate-100">
                        <span class="text-slate-500">Ref. Dokumen Retur:</span>
                        <span class="font-mono font-bold text-slate-800" x-text="selectedBastDoc?.docNo"></span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100">
                        <span class="text-slate-500">Pihak Toko (Pelepas):</span>
                        <span class="font-bold text-slate-800" x-text="selectedBastDoc?.branchOrigin + ' (' + (selectedBastDoc?.cashierName || 'Kasir') + ')'"></span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100">
                        <span class="text-slate-500">Pihak Gudang (Penerima QC):</span>
                        <span class="font-bold text-slate-800" x-text="selectedBastDoc?.physicalReceiptData?.receivedBy || 'Hendra W. (DC Head)'"></span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100">
                        <span class="text-slate-500">Waktu Serah Terima Fisik:</span>
                        <span class="font-mono text-slate-800" x-text="selectedBastDoc?.physicalReceiptData?.receivedDate || selectedBastDoc?.date"></span>
                    </div>
                </div>

                <!-- Table BAST Items -->
                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <table class="w-full text-left text-[11px]">
                        <thead class="bg-slate-50 font-bold text-slate-600 border-b border-slate-200">
                            <tr>
                                <th class="p-2">Item Barang</th>
                                <th class="p-2 text-center">Diajukan</th>
                                <th class="p-2 text-center">Fisik Diterima</th>
                                <th class="p-2 text-center">Selisih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="bItem in (selectedBastDoc?.physicalReceiptData?.items || selectedBastDoc?.products)" :key="bItem.name">
                                <tr>
                                    <td class="p-2 font-medium" x-text="bItem.name"></td>
                                    <td class="p-2 text-center font-mono" x-text="formatQtyClean(bItem)"></td>
                                    <td class="p-2 text-center font-mono font-bold text-amber-800" x-text="(parseInt(bItem.receivedQty !== undefined ? bItem.receivedQty : (bItem.submittedQty || bItem.qty)) || 0) + ' Pcs'"></td>
                                    <td class="p-2 text-center font-mono" :class="(bItem.selisih > 0) ? 'text-rose-600 font-bold' : 'text-emerald-600'" x-text="(bItem.selisih || 0)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <template x-if="selectedBastDoc?.physicalReceiptData?.hasDiscrepancy">
                    <div class="p-2.5 bg-rose-50 border border-rose-200 rounded-lg text-[11px] text-rose-800">
                        <strong>Catatan Selisih:</strong> <span x-text="selectedBastDoc?.physicalReceiptData?.discrepancyReason"></span>
                    </div>
                </template>
            </div>

            <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2.5">
                <button type="button" @click="showBastModal = false" class="px-4 py-2 rounded-xl text-slate-700 font-bold hover:bg-slate-200 cursor-pointer">Tutup</button>
                <button type="button" @click="triggerToast('Mengunduh arsip BAST digital (PDF)...'); showBastModal = false;" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold inline-flex items-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-rounded text-[16px]">download</span>
                    <span>Unduh PDF BAST</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 6: BUAT PENGAJUAN RETUR BARU -->
    <!-- ======================================================== -->
    <div x-show="showModal" style="display: none;"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4"
         x-transition.opacity>
        <div @click.away="showModal = false"
             class="bg-white rounded-2xl w-full max-w-lg shadow-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-[#0047b3] text-white flex items-center justify-center">
                        <span class="material-symbols-rounded text-[20px]">outbox</span>
                    </div>
                    <div>
                        <h3 class="text-[15px] font-black text-slate-900">Buat Pengajuan Retur Toko &rarr; Gudang</h3>
                        <p class="text-[11px] font-medium text-slate-500">Ajukan pengembalian produk rusak/ED/overstock ke Gudang Pusat</p>
                    </div>
                </div>
                <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                    <span class="material-symbols-rounded text-[18px]">close</span>
                </button>
            </div>

            <div class="p-6 space-y-4 text-xs">
                <div>
                    <label class="block font-extrabold text-slate-600 uppercase tracking-wider mb-1">ASAL CABANG / TOKO</label>
                    <select x-model="newDocBranch" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-800 focus:outline-none focus:border-blue-500">
                        <option value="Cabang A">Cabang A</option>
                        <option value="Cabang B">Cabang B</option>
                        <option value="Toko Pusat">Toko Pusat</option>
                    </select>
                </div>
                <div>
                    <label class="block font-extrabold text-slate-600 uppercase tracking-wider mb-1">NAMA PETUGAS KASIR PENGAJU</label>
                    <input type="text" x-model="newDocCashier" placeholder="Nama Kasir Cabang"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-800 focus:bg-white focus:border-blue-500 focus:outline-none">
                </div>
                <div>
                    <label class="block font-extrabold text-slate-600 uppercase tracking-wider mb-1">NAMA PRODUK / SKU</label>
                    <input type="text" x-model="newDocSku" placeholder="Contoh: PRD-007 - Minyak Goreng Sawit 2L"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-800 focus:bg-white focus:border-blue-500 focus:outline-none">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-extrabold text-slate-600 uppercase tracking-wider mb-1">KUANTITAS DIAJUKAN</label>
                        <input type="number" x-model="newDocQty" min="1"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-800 focus:bg-white focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block font-extrabold text-slate-600 uppercase tracking-wider mb-1">SATUAN KEMASAN</label>
                        <select x-model="newDocUnit" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-800 focus:outline-none focus:border-blue-500">
                            <option>Pcs</option>
                            <option>Karton</option>
                            <option>Pack</option>
                            <option>Botol</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block font-extrabold text-slate-600 uppercase tracking-wider mb-1">KONDISI FISIK</label>
                    <select x-model="newDocCondition" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-800 focus:outline-none focus:border-blue-500">
                        <option value="RUSAK / BOCOR">RUSAK / BOCOR</option>
                        <option value="KEDALUWARSA / H-14">KEDALUWARSA / H-14</option>
                        <option value="MUTASI OVERSTOCK">MUTASI OVERSTOCK</option>
                    </select>
                </div>
                <div>
                    <label class="block font-extrabold text-slate-600 uppercase tracking-wider mb-1">CATATAN &amp; ALASAN DETAIL DARI KASIR</label>
                    <input type="text" x-model="newDocReason" placeholder="Contoh: Kemasan penyok rembes saat dibongkar kasir toko"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-medium text-slate-800 focus:bg-white focus:border-blue-500 focus:outline-none">
                </div>
            </div>

            <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2.5">
                <button type="button" @click="showModal = false" class="px-4 py-2 rounded-xl text-slate-700 font-bold hover:bg-slate-200 cursor-pointer">Batal</button>
                <button type="button" @click="submitQuickDraft()" class="px-5 py-2 rounded-xl bg-[#0047b3] hover:bg-[#003994] text-white font-bold shadow-xs cursor-pointer">
                    Simpan &amp; Ajukan Retur
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div x-show="showToast" style="display: none;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-3"
         class="fixed bottom-6 right-6 z-50 px-4 py-3 rounded-xl shadow-xl border flex items-center gap-3 text-[12.5px] font-bold text-white"
         :class="toastType === 'error' ? 'bg-rose-900 border-rose-700' : 'bg-slate-900 border-slate-700'">
        <span class="material-symbols-rounded text-[18px]" :class="toastType === 'error' ? 'text-rose-400' : 'text-emerald-400'">
            <span x-text="toastType === 'error' ? 'error' : 'check_circle'"></span>
        </span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
