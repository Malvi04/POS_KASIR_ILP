@extends('layouts.app')

@section('title', 'Kirim Barang - Manager ILP POS')

@section('content')
<div x-data="{
    searchQuery: '',
    statusFilter: 'all',
    destinationFilter: 'all',

    // Modals
    showCreateModal: false,
    showDetailModal: false,
    selectedDoc: null,

    // Data Storage
    documents: [],
    availableProducts: [],
    isSubmitting: false,

    // Toast
    toastMessage: '',
    toastType: 'success',
    showToast: false,

    // Form Pengiriman Baru
    form: {
        destinationStore: 'Cabang A',
        requestRef: '',
        driverName: '',
        plateNumber: '',
        date: '',
        generalNotes: '',
        items: []
    },

    triggerToast(msg, type = 'success') {
        this.toastMessage = msg;
        this.toastType = type;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3500);
    },

    async initKirimBarang() {
        // Fetch live data dari API backend goods-receipts
        try {
            const res = await fetch('/api/goods-receipts', { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const json = await res.json();
                if (json.success && Array.isArray(json.data)) {
                    this.documents = json.data;
                }
            }
        } catch(e) {
            console.warn('API goods-receipts fetch error:', e);
            this.documents = [];
        }

        // Load referensi produk katalog master dari API
        await this.loadAvailableProducts();

        window.addEventListener('pos:goods_in_updated', () => {
            this.initKirimBarang();
        });

        window.addEventListener('pos:inventory_updated', () => {
            this.loadAvailableProducts();
        });

        window.addEventListener('pos:sync-all', () => {
            this.initKirimBarang();
        });
    },

    resolveProductType(p) {
        if (!p) return { is_consignment: false, is_coupon: false, product_type: 'UMUM', product_type_label: 'Produk Umum' };
        const cat = String(p.category || p.kategori || '').toLowerCase();
        const isCoupon = Boolean(p.is_coupon) || cat.includes('kupon') || cat.includes('voucher');
        const isConsignment = Boolean(p.is_consignment);
        if (isCoupon) {
            return { is_consignment: false, is_coupon: true, product_type: 'KUPON', product_type_label: 'Kupon / Voucher' };
        }
        if (isConsignment) {
            return { is_consignment: true, is_coupon: false, product_type: 'UMKM', product_type_label: 'Produk UMKM' };
        }
        return { is_consignment: false, is_coupon: false, product_type: 'UMUM', product_type_label: 'Produk Umum' };
    },

    async loadAvailableProducts() {
        try {
            const [resWh, resKpn] = await Promise.all([
                fetch('/api/products?location_code=GDG-001&per_page=500', { headers: { 'Accept': 'application/json' } }),
                fetch('/api/products?location_code=GDG-001&category=Kupon&per_page=100', { headers: { 'Accept': 'application/json' } }).catch(() => null)
            ]);
            let prods = [];
            if (resWh && resWh.ok) {
                const jsonWh = await resWh.json();
                prods = jsonWh.data || jsonWh || [];
            }
            if (resKpn && resKpn.ok) {
                const jsonKpn = await resKpn.json();
                const kpnProds = jsonKpn.data || jsonKpn || [];
                const existingIds = new Set(prods.map(p => p.id));
                const uniqueKpn = kpnProds.filter(p => !existingIds.has(p.id));
                prods = [...uniqueKpn, ...prods];
            }
            if (Array.isArray(prods)) {
                this.availableProducts = prods.map(p => {
                    const meta = this.resolveProductType(p);
                    return {
                        id: p.id,
                        sku: p.sku || p.barcode || '-',
                        barcode: p.barcode || p.sku || '-',
                        name: p.name || p.nama || '-',
                        category: p.category || p.kategori || 'Umum',
                        unit: p.unit || 'Pcs',
                        stock: Math.max(0, Number(p.stock !== undefined ? p.stock : (p.stok || 0))),
                        is_consignment: meta.is_consignment,
                        is_coupon: meta.is_coupon,
                        product_type: meta.product_type,
                        product_type_label: meta.product_type_label,
                        supplier_name: p.supplier_name || (p.supplier ? p.supplier.name : '') || ''
                    };
                });
                return;
            }
        } catch(e) {
            console.warn('Failed to load products from API:', e);
        }

        this.availableProducts = [];
    },

    saveDocuments(targetBranch = null) {
        if (window.PosRealtime) {
            window.PosRealtime.broadcast('goods_in_updated', { 
                branch: targetBranch, 
                documents: this.documents 
            });
        }
        try {
            // Sinkronkan dokumen lokal ke localStorage cabang tujuan (jika di browser yang sama)
            let branchesToSync = targetBranch ? [targetBranch] : ['Cabang A', 'Cabang B', 'Toko Pusat', 'Cabang Pusat', 'Pusat'];
            if (targetBranch && targetBranch.toLowerCase().includes('pusat')) {
                branchesToSync = ['Toko Pusat', 'Cabang Pusat', 'Pusat'];
            }
            branchesToSync.forEach(b => {
                const bNorm = b.toLowerCase();
                const branchDocs = this.documents.filter(d => {
                    const dest = (d.destinationStore || '').toLowerCase();
                    if (bNorm.includes('pusat')) return dest.includes('pusat') || Number(d.to_location_id) === 2;
                    if (bNorm.includes('cabang a')) return dest.includes('cabang a') || Number(d.to_location_id) === 3;
                    if (bNorm.includes('cabang b')) return dest.includes('cabang b') || Number(d.to_location_id) === 4;
                    return dest === bNorm;
                });
                localStorage.setItem('pos_ilp_goods_in_docs_v1_' + b, JSON.stringify(branchDocs));
            });
        } catch (e) {}
    },

    formatStoreName(name) {
        if (!name || name === '-') return '-';
        const lower = String(name).toLowerCase();
        if (lower.includes('pusat')) return 'Toko Pusat';
        if (lower.includes('cabang a') || lower.includes('sektor a') || lower === 'cab-002') return 'Cabang A';
        if (lower.includes('cabang b') || lower.includes('sektor b') || lower === 'cab-003') return 'Cabang B';
        return name;
    },

    matchesDestination(doc, filterVal) {
        if (!filterVal || filterVal === 'all') return true;
        const f = String(filterVal).toLowerCase().trim();
        const dest = String(doc.destinationStore || '').toLowerCase().trim();
        const locId = Number(doc.to_location_id);

        if (f.includes('pusat')) {
            return dest.includes('pusat') || dest.includes('minimarket') || locId === 2;
        }
        if (f.includes('cabang a') || f.includes('sektor a') || f === 'cab-002') {
            return dest.includes('cabang a') || dest.includes('sektor a') || dest.includes('cabang 1') || locId === 3;
        }
        if (f.includes('cabang b') || f.includes('sektor b') || f === 'cab-003') {
            return dest.includes('cabang b') || dest.includes('sektor b') || dest.includes('cabang 2') || locId === 4;
        }
        return dest === f || dest.includes(f);
    },

    matchesStatus(docStatus, filterVal) {
        if (!filterVal || filterVal === 'all') return true;
        const s = String(docStatus || '').toUpperCase();
        const f = String(filterVal).toUpperCase();
        if (f === 'MENUNGGU_VERIFIKASI' || f === 'BELUM_SAMPAI') {
            return s === 'MENUNGGU_VERIFIKASI' || s === 'BELUM_SAMPAI';
        }
        if (f === 'DITERIMA_LENGKAP' || f === 'APPROVED') {
            return s === 'DITERIMA_LENGKAP' || s === 'APPROVED';
        }
        if (f === 'DIBATALKAN' || f === 'TIDAK_SAMPAI') {
            return s === 'DIBATALKAN' || s === 'TIDAK_SAMPAI';
        }
        return s === f;
    },

    matchesSearch(doc, query) {
        if (!query || query.trim() === '') return true;
        const q = query.toLowerCase().trim();
        const matchNo = (doc.docNo || '').toLowerCase().includes(q);
        const matchRef = (doc.requestRef || '').toLowerCase().includes(q);
        const matchWh = (doc.warehouseOrigin || '').toLowerCase().includes(q);
        const rawDest = (doc.destinationStore || '').toLowerCase();
        const fmtDest = this.formatStoreName(doc.destinationStore).toLowerCase();
        const matchDest = rawDest.includes(q) || fmtDest.includes(q) || (q.includes('pusat') && (rawDest.includes('pusat') || Number(doc.to_location_id) === 2));
        const matchDriver = (doc.driverName || '').toLowerCase().includes(q) || (doc.plateNumber || '').toLowerCase().includes(q);
        const matchProd = Array.isArray(doc.items) && doc.items.some(it => (it.name || '').toLowerCase().includes(q) || (it.sku || '').toLowerCase().includes(q));
        return matchNo || matchRef || matchWh || matchDest || matchDriver || matchProd;
    },

    get filteredDocuments() {
        return this.documents.filter(doc => {
            if (!this.matchesStatus(doc.status, this.statusFilter)) return false;
            if (!this.matchesDestination(doc, this.destinationFilter)) return false;
            if (!this.matchesSearch(doc, this.searchQuery)) return false;
            return true;
        });
    },

    async openCreateModal() {
        await this.loadAvailableProducts();
        const today = new Date().toISOString().split('T')[0];

        // Temukan produk pertama yang memiliki stok > 0
        const firstWithStock = Array.isArray(this.availableProducts)
            ? this.availableProducts.find(p => p && Number(p.stock) > 0)
            : null;
        const firstMeta = this.resolveProductType(firstWithStock);

        this.form = {
            destinationStore: 'Cabang A',
            requestRef: '',
            driverName: '',
            plateNumber: '',
            date: today,
            generalNotes: '',
            items: [
                {
                    product_id: firstWithStock ? firstWithStock.id : null,
                    sku: firstWithStock ? firstWithStock.sku : '',
                    name: firstWithStock ? firstWithStock.name : '',
                    category: firstWithStock ? firstWithStock.category : '',
                    unit: firstWithStock ? firstWithStock.unit : 'Pcs',
                    availableStock: firstWithStock ? firstWithStock.stock : 0,
                    qtySent: firstWithStock && Number(firstWithStock.stock) > 0 ? 1 : 0,
                    is_consignment: firstMeta.is_consignment,
                    is_coupon: firstMeta.is_coupon,
                    product_type: firstMeta.product_type,
                    product_type_label: firstMeta.product_type_label,
                    supplier_name: firstWithStock ? (firstWithStock.supplier_name || '') : ''
                }
            ]
        };
        this.showCreateModal = true;
    },

    addItemRow() {
        // Cari produk yang belum dipilih
        const selectedSkus = (this.form.items || []).map(i => i.sku);
        const nextProd = (this.availableProducts || []).find(p => p && !selectedSkus.includes(p.sku) && Number(p.stock) > 0) || (this.availableProducts || [])[0];
        const nextMeta = this.resolveProductType(nextProd);

        this.form.items.push({
            product_id: nextProd ? nextProd.id : null,
            sku: nextProd ? nextProd.sku : '',
            name: nextProd ? nextProd.name : '',
            category: nextProd ? nextProd.category : '',
            unit: nextProd ? nextProd.unit : 'Pcs',
            availableStock: nextProd ? nextProd.stock : 0,
            qtySent: nextProd && Number(nextProd.stock) > 0 ? 1 : 0,
            is_consignment: nextMeta.is_consignment,
            is_coupon: nextMeta.is_coupon,
            product_type: nextMeta.product_type,
            product_type_label: nextMeta.product_type_label,
            supplier_name: nextProd ? (nextProd.supplier_name || '') : ''
        });
    },

    removeItemRow(index) {
        if (this.form.items.length <= 1) {
            this.triggerToast('Minimal harus ada 1 produk dalam dokumen pengiriman.', 'warning');
            return;
        }
        this.form.items.splice(index, 1);
    },

    onProductChange(item) {
        const prod = (this.availableProducts || []).find(p => p && p.sku === item.sku);
        if (prod) {
            const meta = this.resolveProductType(prod);
            item.product_id = prod.id;
            item.name = prod.name;
            item.category = prod.category;
            item.unit = prod.unit;
            item.availableStock = prod.stock;
            item.is_consignment = meta.is_consignment;
            item.is_coupon = meta.is_coupon;
            item.product_type = meta.product_type;
            item.product_type_label = meta.product_type_label;
            item.supplier_name = prod.supplier_name || '';
            if (item.qtySent > prod.stock || item.qtySent <= 0) {
                item.qtySent = prod.stock > 0 ? 1 : 0;
            }
        } else {
            item.product_id = null;
            item.name = '';
            item.category = '';
            item.unit = 'Pcs';
            item.availableStock = 0;
            item.qtySent = 0;
            item.is_consignment = false;
            item.is_coupon = false;
            item.product_type = 'UMUM';
            item.product_type_label = 'Produk Umum';
            item.supplier_name = '';
        }
    },

    hasValidationErrors() {
        if (!this.form.destinationStore) return 'Toko tujuan pengiriman wajib dipilih.';
        if (!this.form.driverName.trim()) return 'Nama pengirim / driver wajib diisi.';
        if (!this.form.plateNumber.trim()) return 'Plat nomor kendaraan wajib diisi.';
        if (!this.form.items || this.form.items.length === 0) return 'Daftar produk pengiriman tidak boleh kosong.';

        const skus = [];
        for (let idx = 0; idx < this.form.items.length; idx++) {
            const it = this.form.items[idx];
            if (!it.sku) return `Baris ke-${idx + 1}: Produk belum dipilih.`;
            if (skus.includes(it.sku)) return `Produk ${it.name} (${it.sku}) dipilih lebih dari satu kali. Gabungkan kuantitasnya.`;
            skus.push(it.sku);

            const qty = Number(it.qtySent);
            if (isNaN(qty) || qty <= 0) return `Baris ke-${idx + 1}: Kuantitas pengiriman harus lebih dari 0.`;
            if (qty > it.availableStock) return `Baris ke-${idx + 1}: Kuantitas ${it.name} (${qty} ${it.unit}) melebihi stok gudang yang tersedia (${it.availableStock} ${it.unit}).`;
        }
        return null;
    },

    async submitShipment(mode = 'kirim') {
        const err = this.hasValidationErrors();
        if (err) {
            this.triggerToast(err, 'error');
            return;
        }

        if (this.isSubmitting) return;
        this.isSubmitting = true;

        try {
            const now = new Date();
            const dateStr = this.form.date
                ? new Date(this.form.date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
                : now.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });

            const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') + ' WIB';

            // Generate nomor surat jalan resmi
            const yearStr = now.getFullYear();
            const seqNumber = String(this.documents.length + 1).padStart(4, '0');
            const docNo = `SJ-TRF-${yearStr}-${seqNumber}`;

            const isDraft = (mode === 'draft');
            const status = isDraft ? 'DRAFT' : 'MENUNGGU_VERIFIKASI';

            const preparedItems = this.form.items.map(it => {
                const isCoupon = Boolean(it.is_coupon) || (it.category && (it.category.toLowerCase().includes('kupon') || it.category.toLowerCase().includes('voucher')));
                const isConsignment = Boolean(it.is_consignment);
                const prodType = isCoupon ? 'KUPON' : (isConsignment ? 'UMKM' : 'UMUM');
                const prodTypeLabel = isCoupon ? 'Kupon / Voucher' : (isConsignment ? 'Produk UMKM' : 'Produk Umum');

                return {
                    product_id: it.product_id,
                    sku: it.sku,
                    name: it.name,
                    category: it.category || (isCoupon ? 'Kupon BBM' : 'Umum'),
                    qtySent: Number(it.qtySent),
                    qtyReceived: 0,
                    unit: it.unit || 'Pcs',
                    condition: 'BAIK',
                    is_consignment: isConsignment,
                    is_coupon: isCoupon,
                    product_type: prodType,
                    product_type_label: prodTypeLabel,
                    supplier_name: it.supplier_name || ''
                };
            });

            let newDoc = {
                id: 'trf_' + Date.now(),
                docNo: docNo,
                date: dateStr,
                time: timeStr,
                warehouseOrigin: 'Gudang Pusat (DC)',
                destinationStore: this.form.destinationStore,
                requestRef: this.form.requestRef.trim() || '-',
                driverName: this.form.driverName.trim(),
                plateNumber: this.form.plateNumber.trim().toUpperCase(),
                status: status,
                generalNotes: this.form.generalNotes.trim() || (isDraft ? 'Draft dokumen pengiriman barang.' : 'Pengiriman fisik barang transfer ke gerai cabang.'),
                createdAt: now.toISOString(),
                sentAt: isDraft ? null : `${dateStr} • ${timeStr}`,
                receivedAt: null,
                receiverName: null,
                items: preparedItems
            };

            // Simpan ke database backend
            const idemKey = window.createIdempotencyKey ? window.createIdempotencyKey() : ('gr-' + Date.now());
            const apiRes = await fetch('/api/goods-receipts', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Idempotency-Key': idemKey
                },
                body: JSON.stringify({
                    docNo: docNo,
                    origin_source: newDoc.warehouseOrigin,
                    destination_store: newDoc.destinationStore,
                    driver_name: newDoc.driverName,
                    plate_number: newDoc.plateNumber,
                    status: newDoc.status,
                    notes: newDoc.generalNotes,
                    items: preparedItems.map(it => ({
                        product_id: it.product_id,
                        sku: it.sku,
                        name: it.name,
                        category: it.category,
                        unit: it.unit,
                        qty_sent: it.qtySent,
                        condition: it.condition
                    }))
                })
            });

            const apiJson = await apiRes.json().catch(() => ({}));
            if (!apiRes.ok || !apiJson.success) {
                this.triggerToast(apiJson.message || 'Gagal menyimpan dokumen pengiriman ke server.', 'error');
                return;
            }
            if (apiJson.data) {
                newDoc = { ...newDoc, ...apiJson.data };
            }

            // Simpan dokumen pengiriman
            this.documents.unshift(newDoc);
            this.saveDocuments(newDoc.destinationStore);
            await this.loadAvailableProducts();

            this.showCreateModal = false;
            if (isDraft) {
                this.triggerToast(`Dokumen draft pengiriman ${newDoc.docNo} berhasil disimpan. Stok gudang belum berkurang.`);
            } else {
                this.triggerToast(`Dokumen pengiriman ${newDoc.docNo} berhasil dibuat & dikirim ke ${newDoc.destinationStore}. Stok gudang telah diperbarui!`);
            }
        } catch(e) {
            console.error('Error submitting shipment:', e);
            this.triggerToast('Terjadi kesalahan saat menyimpan pengiriman.', 'error');
        } finally {
            this.isSubmitting = false;
        }
    },

    deductMasterStock(items) {
        window.dispatchEvent(new CustomEvent('pos-inventory-synced'));
        this.loadAvailableProducts();
    },

    async dispatchDraft(doc) {
        if (!confirm(`Konfirmasi pengiriman fisik untuk dokumen ${doc.docNo}? Stok gudang akan dipotong sesuai kuantitas pengiriman.`)) {
            return;
        }

        // Validasi ketersediaan stok aktual
        await this.loadAvailableProducts();
        for (let it of doc.items) {
            const p = this.availableProducts.find(pr => pr.sku === it.sku);
            const currentStock = p ? p.stock : 0;
            if (it.qtySent > currentStock) {
                this.triggerToast(`Gagal kirim: Stok ${it.name} tidak mencukupi (dibutuhkan ${it.qtySent}, stok saat ini: ${currentStock}).`, 'error');
                return;
            }
        }

        // Sinkronisasi ke backend DB
        try {
            if (typeof doc.id === 'number') {
                const res = await fetch('/api/goods-receipts/' + doc.id + '/status', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ status: 'MENUNGGU_VERIFIKASI' })
                });
                if (!res.ok) {
                    this.triggerToast('Gagal memperbarui status pengiriman di server.', 'error');
                    return;
                }
            } else {
                const apiRes = await fetch('/api/goods-receipts', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        docNo: doc.docNo,
                        origin_source: doc.warehouseOrigin,
                        destination_store: doc.destinationStore,
                        driver_name: doc.driverName,
                        plate_number: doc.plateNumber,
                        status: 'MENUNGGU_VERIFIKASI',
                        notes: doc.generalNotes,
                        items: (doc.items || []).map(it => ({
                            sku: it.sku,
                            name: it.name,
                            category: it.category,
                            unit: it.unit,
                            qty_sent: it.qtySent,
                            condition: it.condition
                        }))
                    })
                });
                if (!apiRes.ok) {
                    this.triggerToast('Gagal menyimpan pengiriman ke server.', 'error');
                    return;
                }
                const apiJson = await apiRes.json();
                if (apiJson.success && apiJson.data) {
                    doc.id = apiJson.data.id;
                }
            }
        } catch(e) {
            this.triggerToast('Gagal menghubungi server.', 'error');
            return;
        }

        const now = new Date();
        const dateStr = now.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') + ' WIB';
        doc.status = 'MENUNGGU_VERIFIKASI';
        doc.sentAt = `${dateStr} • ${timeStr}`;

        await this.loadAvailableProducts();
        this.saveDocuments(doc.destinationStore);

        if (this.selectedDoc && this.selectedDoc.id === doc.id) {
            this.selectedDoc = JSON.parse(JSON.stringify(doc));
        }

        this.triggerToast(`Dokumen ${doc.docNo} berhasil dikirim! Status kini menunggu konfirmasi penerimaan toko.`);
    },

    async cancelDraft(doc) {
        if (!confirm(`Batalkan dokumen draft pengiriman ${doc.docNo}?`)) {
            return;
        }

        if (typeof doc.id === 'number') {
            try {
                const res = await fetch('/api/goods-receipts/' + doc.id + '/status', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ status: 'DIBATALKAN' })
                });
                if (!res.ok) {
                    this.triggerToast('Gagal membatalkan draft di server.', 'error');
                    return;
                }
            } catch(e) {
                console.warn('API update status error:', e);
                this.triggerToast('Gagal menghubungi server.', 'error');
                return;
            }
        }

        doc.status = 'DIBATALKAN';
        this.saveDocuments(doc.destinationStore);

        if (this.selectedDoc && this.selectedDoc.id === doc.id) {
            this.selectedDoc = JSON.parse(JSON.stringify(doc));
        }

        this.triggerToast(`Dokumen draft ${doc.docNo} telah dibatalkan.`, 'info');
    },

    openDetail(doc) {
        this.selectedDoc = JSON.parse(JSON.stringify(doc));
        this.showDetailModal = true;
    },

    getStatusBadge(status) {
        switch(status) {
            case 'DRAFT':
                return { label: 'Draft Pengiriman', class: 'bg-slate-100 text-slate-700 border-slate-300' };
            case 'BELUM_SAMPAI':
            case 'MENUNGGU_VERIFIKASI':
                return { label: 'Belum Sampai (Di Perjalanan)', class: 'bg-amber-50 text-amber-700 border-amber-300' };
            case 'SUDAH_SAMPAI':
                return { label: 'Sudah Sampai di Toko', class: 'bg-blue-50 text-blue-700 border-blue-300' };
            case 'APPROVED':
            case 'DITERIMA_LENGKAP':
                return { label: 'Approved (Selesai)', class: 'bg-emerald-50 text-emerald-700 border-emerald-300' };
            case 'DITERIMA_SELISIH':
                return { label: 'Diterima Selisih', class: 'bg-rose-50 text-rose-700 border-rose-300' };
            case 'TIDAK_SAMPAI':
            case 'DIBATALKAN':
                return { label: 'Tidak Sampai / Batal', class: 'bg-red-50 text-red-600 border-red-200' };
            default:
                return { label: status, class: 'bg-gray-100 text-gray-700 border-gray-300' };
        }
    }
}"
x-init="initKirimBarang()"
@pos-inventory-synced.window="loadAvailableProducts()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">

    <!-- Sidebar Manager -->
    <x-manager-sidebar :active="'kirim_barang'" />

    <!-- Main Workspace -->
    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden relative">

        <!-- Top Header Navigation -->
        <header class="h-20 bg-[#f8f9fa] border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-3">
                <h1 class="text-[20px] font-bold text-gray-900">Kirim Barang</h1>
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200 flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[14px]">local_shipping</span>
                    <span>Distribusi Antar Cabang</span>
                </span>
            </div>

            <!-- Header Right Actions & Realtime Status -->
            <div class="flex items-center gap-4 text-gray-500">
                <x-notification-bell role="MANAGER" />
                <button type="button" 
                        @click="window.PosRealtime?.syncNow?.(); initKirimBarang();" 
                        class="hover:text-gray-900 transition-colors cursor-pointer" 
                        title="Sinkronkan Data">
                    <span class="material-symbols-rounded text-[24px]" :class="$store.network?.isChecking ? 'animate-spin text-[#0052cc]' : ''">autorenew</span>
                </button>
                <div class="h-6 w-px bg-gray-300"></div>
                <x-realtime-network-badge />
            </div>
        </header>

        <!-- Main Scrollable Area -->
        <div class="flex-1 overflow-y-auto p-6 md:p-8 bg-[#f8f9fa]">
            <div class="max-w-[1400px] mx-auto space-y-6">

                <!-- Search & Filters Toolbar -->
                <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="relative flex-1 w-full max-w-xl">
                        <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="Cari nomor surat jalan, toko tujuan, driver, atau produk..."
                               class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 text-[13px] shadow-xs transition-all font-medium bg-slate-50/50">
                    </div>

                    <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                        <!-- Filter Tujuan -->
                        <div class="relative">
                            <select x-model="destinationFilter"
                                    class="appearance-none bg-white border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-[13px] text-gray-700 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 shadow-xs font-semibold cursor-pointer">
                                <option value="all">Semua Toko Tujuan</option>
                                <option value="Cabang A">Cabang A</option>
                                <option value="Cabang B">Cabang B</option>
                                <option value="Toko Pusat">Toko Pusat</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                        </div>

                        <!-- Filter Status -->
                        <div class="relative">
                            <select x-model="statusFilter"
                                    class="appearance-none bg-white border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-[13px] text-gray-700 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 shadow-xs font-semibold cursor-pointer">
                                <option value="all">Semua Status Pengiriman</option>
                                <option value="DRAFT">Draft Pengiriman</option>
                                <option value="MENUNGGU_VERIFIKASI">Dalam Pengiriman</option>
                                <option value="DITERIMA_LENGKAP">Diterima Lengkap</option>
                                <option value="DITERIMA_SELISIH">Diterima Selisih</option>
                                <option value="DIBATALKAN">Dibatalkan</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                        </div>

                        <!-- Button Buat Pengiriman -->
                        <button type="button"
                                @click.stop="openCreateModal()"
                                class="px-4 py-2.5 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold shadow-xs flex items-center gap-1.5 cursor-pointer transition-colors shrink-0">
                            <span class="material-symbols-rounded text-[18px]">add</span>
                            <span>Buat Pengiriman</span>
                        </button>
                    </div>
                </div>

                <!-- Tabel Riwayat Pengiriman (Single-Line Cell & Grouped Rows) -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-rounded text-[20px] text-[#0052cc]">local_shipping</span>
                            <h2 class="text-sm font-bold text-gray-900">Riwayat Pengiriman Barang Keluar</h2>
                        </div>
                        <span class="text-xs text-gray-500 font-medium" x-text="'Menampilkan ' + filteredDocuments.length + ' dokumen'"></span>
                    </div>

                    <div class="overflow-x-auto min-w-0 w-full">
                        <table class="w-full text-left border-collapse text-xs min-w-[1250px]">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold tracking-wider uppercase text-[11px] whitespace-nowrap">
                                    <th class="px-4 py-3.5 text-left">No. Surat Jalan</th>
                                    <th class="px-4 py-3.5 text-left">Tanggal</th>
                                    <th class="px-4 py-3.5 text-left">Gudang Asal</th>
                                    <th class="px-4 py-3.5 text-left">Toko Tujuan</th>
                                    <th class="px-4 py-3.5 text-left">No. Referensi</th>
                                    <th class="px-4 py-3.5 text-left">SKU</th>
                                    <th class="px-4 py-3.5 text-left">Nama Produk</th>
                                    <th class="px-4 py-3.5 text-left">Kategori</th>
                                    <th class="px-4 py-3.5 text-center">Qty Dikirim</th>
                                    <th class="px-4 py-3.5 text-left">Pengirim</th>
                                    <th class="px-4 py-3.5 text-left">Plat Nomor</th>
                                    <th class="px-4 py-3.5 text-center">Status</th>
                                    <th class="px-4 py-3.5 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="text-slate-700 divide-y divide-gray-100">
                                <!-- Kondisi Kosong: Header tetap tampil, isi tabel menampilkan tombol Buat Pengiriman -->
                                <template x-if="filteredDocuments.length === 0">
                                    <tr>
                                        <td colspan="13" class="px-6 py-12 text-center text-gray-500 bg-white">
                                            <div class="flex flex-col items-center justify-center">
                                                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mb-3">
                                                    <span class="material-symbols-rounded text-2xl">local_shipping</span>
                                                </div>
                                                <p class="font-bold text-gray-800 text-sm">Belum ada data pengiriman.</p>
                                                <p class="text-xs text-gray-400 mt-0.5">Mulai buat dokumen pengiriman barang keluar untuk distribusi ke toko cabang.</p>
                                                <button type="button"
                                                        @click.stop="openCreateModal()"
                                                        class="mt-4 px-4 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold shadow-xs flex items-center gap-1.5 cursor-pointer transition-colors">
                                                    <span class="material-symbols-rounded text-[16px]">add</span>
                                                    <span>Buat Pengiriman</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>

                                <!-- Baris Dokumen dengan Grouped Rows (rowspan) -->
                                <template x-for="doc in filteredDocuments" :key="doc.id">
                                    <template x-for="(it, iIdx) in ((doc.items && doc.items.length > 0) ? doc.items : [{ sku: '-', name: '-', category: '-', qtySent: 0, unit: 'Unit' }])" :key="iIdx">
                                        <tr class="hover:bg-blue-50/30 transition-colors" :class="iIdx === 0 ? 'border-t border-gray-200' : 'border-t border-gray-100'">
                                            <!-- Parent Col 1: No. Surat Jalan -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white font-mono font-bold text-gray-900 text-xs">
                                                    <button type="button"
                                                            @click="openDetail(doc)"
                                                            class="text-[#0052cc] hover:underline font-bold inline-flex items-center gap-1 cursor-pointer"
                                                            :title="'Buka detail ' + doc.docNo">
                                                        <span x-text="doc.docNo"></span>
                                                    </button>
                                                </td>
                                            </template>

                                            <!-- Parent Col 2: Tanggal -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-xs text-gray-500 font-medium" x-text="doc.date">
                                                </td>
                                            </template>

                                            <!-- Parent Col 3: Gudang Asal -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-xs font-semibold text-gray-800" x-text="doc.warehouseOrigin">
                                                </td>
                                            </template>

                                            <!-- Parent Col 4: Toko Tujuan -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-xs font-bold text-gray-900">
                                                    <span class="inline-flex items-center gap-1 text-slate-800">
                                                        <span class="material-symbols-rounded text-[14px] text-[#0052cc]">store</span>
                                                        <span x-text="formatStoreName(doc.destinationStore)"></span>
                                                    </span>
                                                </td>
                                            </template>

                                            <!-- Parent Col 5: No. Referensi -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-xs font-mono text-gray-600" x-text="doc.requestRef || '-'">
                                                </td>
                                            </template>

                                            <!-- Child Col 6: SKU -->
                                            <td class="px-4 py-3 align-middle whitespace-nowrap font-mono font-bold text-gray-700 text-xs" x-text="it.sku || '-'"></td>

                                            <!-- Child Col 7: Nama Produk & Jenis -->
                                            <td class="px-4 py-3 align-middle whitespace-nowrap text-xs">
                                                <div class="flex flex-col">
                                                    <span class="font-bold text-gray-900 max-w-[220px] truncate" :title="it.name" x-text="it.name || '-'"></span>
                                                    <div class="flex items-center gap-1 mt-0.5">
                                                        <template x-if="it.is_coupon || it.product_type === 'KUPON' || (it.category && (it.category.toLowerCase().includes('kupon') || it.category.toLowerCase().includes('voucher')))">
                                                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-200">
                                                                <span class="material-symbols-rounded text-[11px]">local_activity</span>
                                                                <span>Kupon / Voucher</span>
                                                            </span>
                                                        </template>
                                                        <template x-if="(it.is_consignment || it.product_type === 'UMKM') && !(it.is_coupon || it.product_type === 'KUPON') && !(it.category && (it.category.toLowerCase().includes('kupon') || it.category.toLowerCase().includes('voucher')))">
                                                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                                                <span class="material-symbols-rounded text-[11px]">storefront</span>
                                                                <span>Produk UMKM</span>
                                                                <span x-show="it.supplier_name" x-text="'• ' + it.supplier_name" class="truncate max-w-[120px]"></span>
                                                            </span>
                                                        </template>
                                                        <template x-if="!it.is_consignment && it.product_type !== 'UMKM' && !it.is_coupon && it.product_type !== 'KUPON' && !(it.category && (it.category.toLowerCase().includes('kupon') || it.category.toLowerCase().includes('voucher')))">
                                                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                                                <span class="material-symbols-rounded text-[11px]">inventory_2</span>
                                                                <span>Produk Umum</span>
                                                            </span>
                                                        </template>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Child Col 8: Kategori -->
                                            <td class="px-4 py-3 align-middle whitespace-nowrap text-xs text-slate-500" x-text="it.category || '-'"></td>

                                            <!-- Child Col 9: Qty Dikirim -->
                                            <td class="px-4 py-3 align-middle whitespace-nowrap text-center font-mono font-bold text-xs bg-slate-50/50">
                                                <span class="inline-block bg-blue-50 text-[#0047b3] border border-blue-200 px-2 py-0.5 rounded font-bold font-mono text-xs" x-text="it.qtySent + ' ' + (it.unit || 'Pcs')"></span>
                                            </td>

                                            <!-- Parent Col 10: Pengirim -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-xs font-medium text-gray-800" x-text="doc.driverName || '-'">
                                                </td>
                                            </template>

                                            <!-- Parent Col 11: Plat Nomor -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white font-mono text-xs text-gray-600" x-text="doc.plateNumber || '-'">
                                                </td>
                                            </template>

                                            <!-- Parent Col 12: Status -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-center">
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border"
                                                          :class="getStatusBadge(doc.status).class"
                                                          x-text="getStatusBadge(doc.status).label"></span>
                                                </td>
                                            </template>

                                            <!-- Parent Col 13: Aksi -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-center">
                                                    <div class="inline-flex items-center gap-1.5">
                                                        <button type="button"
                                                                @click="openDetail(doc)"
                                                                class="px-3 py-1.5 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-100 font-bold text-xs transition-colors cursor-pointer">
                                                            Detail
                                                        </button>
                                                        <template x-if="doc.status === 'DRAFT'">
                                                            <div class="inline-flex items-center gap-1">
                                                                <button type="button"
                                                                        @click="dispatchDraft(doc)"
                                                                        class="px-3 py-1.5 rounded-lg bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs transition-colors cursor-pointer shadow-2xs"
                                                                        title="Kirim Sekarang">
                                                                    Kirim
                                                                </button>
                                                                <button type="button"
                                                                        @click="cancelDraft(doc)"
                                                                        class="p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                                                                        title="Batalkan Draft">
                                                                    <span class="material-symbols-rounded text-[18px]">close</span>
                                                                </button>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </td>
                                            </template>
                                        </tr>
                                    </template>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

    </main>

    <!-- MODAL: BUAT PENGIRIMAN (FORMULIR MULTI-ITEM) -->
    <div x-show="showCreateModal"
         x-cloak
         x-transition.opacity
         @click.self="if (!isSubmitting) showCreateModal = false"
         class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.stop
             class="bg-white rounded-2xl border border-gray-200 shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[90vh]">

            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center">
                        <span class="material-symbols-rounded text-[20px]">local_shipping</span>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-gray-900">Buat Pengiriman Barang Keluar</h2>
                        <p class="text-[11px] text-gray-500">Pencatatan surat jalan transfer barang dari Gudang Pusat ke toko cabang.</p>
                    </div>
                </div>
                <button type="button"
                        @click="showCreateModal = false"
                        :disabled="isSubmitting"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 cursor-pointer">
                    <span class="material-symbols-rounded text-[20px]">close</span>
                </button>
            </div>

            <!-- Modal Body (Scrollable Form) -->
            <div class="p-6 overflow-y-auto space-y-5 text-xs">

                <!-- Alert jika katalog produk kosong -->
                <template x-if="availableProducts.length === 0">
                    <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 flex items-start gap-3">
                        <span class="material-symbols-rounded text-amber-600 text-[20px] shrink-0 mt-0.5">warning</span>
                        <div class="space-y-1">
                            <p class="font-bold">Katalog Produk Kosong atau Belum Terdaftar</p>
                            <p class="text-[11.5px] leading-relaxed">Belum ada data produk di master stok. Silakan tambahkan produk terlebih dahulu di menu <strong>Master Produk &amp; Stok</strong> sebelum membuat pengiriman.</p>
                        </div>
                    </div>
                </template>

                <!-- Grid Data Pengiriman Induk -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Toko Tujuan -->
                    <div>
                        <label class="block font-bold text-gray-700 mb-1.5 uppercase tracking-wider text-[10.5px]">Toko Tujuan <span class="text-rose-500">*</span></label>
                        <select x-model="form.destinationStore"
                                class="w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-xs text-gray-800 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-medium">
                            <option value="Cabang A">Cabang A</option>
                            <option value="Cabang B">Cabang B</option>
                            <option value="Toko Pusat">Toko Pusat</option>
                        </select>
                    </div>

                    <!-- Tanggal Pengiriman -->
                    <div>
                        <label class="block font-bold text-gray-700 mb-1.5 uppercase tracking-wider text-[10.5px]">Tanggal Pengiriman <span class="text-rose-500">*</span></label>
                        <input type="date"
                               x-model="form.date"
                               class="w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-xs text-gray-800 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-medium">
                    </div>

                    <!-- No. Referensi Permintaan (Opsional) -->
                    <div>
                        <label class="block font-bold text-gray-700 mb-1.5 uppercase tracking-wider text-[10.5px]">No. Referensi Permintaan Toko (Opsional)</label>
                        <input type="text"
                               x-model="form.requestRef"
                               placeholder="Contoh: REQ-CAB-2024-001"
                               class="w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-xs text-gray-800 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-medium">
                    </div>

                    <!-- Driver / Petugas Pengirim -->
                    <div>
                        <label class="block font-bold text-gray-700 mb-1.5 uppercase tracking-wider text-[10.5px]">Nama Pengirim / Driver <span class="text-rose-500">*</span></label>
                        <input type="text"
                               x-model="form.driverName"
                               placeholder="Nama pengemudi armada"
                               class="w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-xs text-gray-800 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-medium">
                    </div>

                    <!-- Plat Nomor Kendaraan -->
                    <div class="sm:col-span-2">
                        <label class="block font-bold text-gray-700 mb-1.5 uppercase tracking-wider text-[10.5px]">Plat Nomor Kendaraan <span class="text-rose-500">*</span></label>
                        <input type="text"
                               x-model="form.plateNumber"
                               placeholder="Contoh: B 9182 UQ"
                               class="w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-xs text-gray-800 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono uppercase font-bold">
                    </div>
                </div>

                <!-- Bagian Daftar Produk yang Dikirim (Multi-Item Support) -->
                <div class="border-t border-gray-200 pt-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="block font-bold text-gray-900 text-[12px] uppercase tracking-wider">Daftar Produk yang Dikirim</span>
                            <span class="text-[11px] text-gray-500">Pilih produk dari master katalog dan tentukan kuantitas yang dikeluarkan.</span>
                        </div>
                        <button type="button"
                                @click="addItemRow()"
                                :disabled="availableProducts.length === 0"
                                class="px-3 py-1.5 rounded-lg border border-[#0052cc] text-[#0052cc] hover:bg-blue-50 font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer">
                            <span class="material-symbols-rounded text-[16px]">add</span>
                            <span>Tambah Produk</span>
                        </button>
                    </div>

                    <!-- Tabel Item Form -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden bg-white">
                        <div class="overflow-x-auto min-w-0 w-full">
                        <table class="w-full text-left border-collapse text-xs min-w-[580px]">
                            <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 font-bold uppercase text-[10.5px] whitespace-nowrap">
                                <tr>
                                    <th class="px-3.5 py-2.5">Pilih Produk</th>
                                    <th class="px-3.5 py-2.5 text-center">Stok Gudang</th>
                                    <th class="px-3.5 py-2.5 text-center w-32">Qty Kirim</th>
                                    <th class="px-3.5 py-2.5 text-center w-16">Satuan</th>
                                    <th class="px-2 py-2.5 text-center w-12">Hapus</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <template x-for="(item, idx) in form.items" :key="idx">
                                    <tr class="hover:bg-slate-50/50">
                                        <!-- Dropdown Produk -->
                                        <td class="px-3.5 py-2.5">
                                            <select x-model="item.sku"
                                                    @change="onProductChange(item)"
                                                    class="w-full bg-white border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs text-gray-800 font-medium focus:outline-none focus:border-[#0052cc]">
                                                <option value="">-- Pilih Produk --</option>
                                                <template x-for="p in availableProducts" :key="p.sku">
                                                    <option :value="p.sku"
                                                            :disabled="p.stock <= 0"
                                                            x-text="(p.is_consignment ? '[UMKM' + (p.supplier_name ? ' • ' + p.supplier_name : '') + '] ' : ((p.is_coupon || p.product_type === 'KUPON' || (p.category && (p.category.toLowerCase().includes('kupon') || p.category.toLowerCase().includes('voucher')))) ? '[Kupon] ' : '[Umum] ')) + p.name + ' (' + p.sku + ') - Stok: ' + p.stock + ' ' + p.unit + (p.stock <= 0 ? ' [HABIS]' : '')">
                                                    </option>
                                                </template>
                                            </select>
                                            <div class="mt-1 flex items-center gap-1.5" x-show="item.sku">
                                                <template x-if="item.is_coupon || item.product_type === 'KUPON' || (item.category && (item.category.toLowerCase().includes('kupon') || item.category.toLowerCase().includes('voucher')))">
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-100 text-indigo-900 border border-indigo-300">
                                                        <span class="material-symbols-rounded text-[12px]">local_activity</span>
                                                        <span>Kupon / Voucher</span>
                                                    </span>
                                                </template>
                                                <template x-if="(item.is_consignment || item.product_type === 'UMKM') && !(item.is_coupon || item.product_type === 'KUPON') && !(item.category && (item.category.toLowerCase().includes('kupon') || item.category.toLowerCase().includes('voucher')))">
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                                                        <span class="material-symbols-rounded text-[12px]">storefront</span>
                                                        <span>Produk UMKM</span>
                                                        <span x-show="item.supplier_name" x-text="'• Mitra: ' + item.supplier_name"></span>
                                                    </span>
                                                </template>
                                                <template x-if="!item.is_consignment && item.product_type !== 'UMKM' && !item.is_coupon && item.product_type !== 'KUPON' && !(item.category && (item.category.toLowerCase().includes('kupon') || item.category.toLowerCase().includes('voucher')))">
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-100 text-blue-900 border border-blue-300">
                                                        <span class="material-symbols-rounded text-[12px]">inventory_2</span>
                                                        <span>Produk Umum</span>
                                                    </span>
                                                </template>
                                            </div>
                                        </td>

                                        <!-- Info Stok Tersedia -->
                                        <td class="px-3.5 py-2.5 text-center whitespace-nowrap">
                                            <span class="font-mono font-bold text-xs"
                                                  :class="item.availableStock > 0 ? 'text-emerald-700' : 'text-rose-600'"
                                                  x-text="item.availableStock + ' ' + item.unit"></span>
                                        </td>

                                        <!-- Input Qty Dikirim -->
                                        <td class="px-3.5 py-2.5 text-center">
                                            <input type="number"
                                                   min="1"
                                                   :max="item.availableStock"
                                                   x-model.number="item.qtySent"
                                                   class="w-24 text-center font-mono font-bold bg-white border rounded-lg px-2 py-1 text-xs focus:outline-none"
                                                   :class="item.qtySent > item.availableStock || item.qtySent <= 0 ? 'border-rose-400 text-rose-700 bg-rose-50/40 focus:border-rose-500' : 'border-gray-200 focus:border-[#0052cc]'">
                                        </td>

                                        <!-- Satuan -->
                                        <td class="px-3.5 py-2.5 text-center font-medium text-gray-500 whitespace-nowrap" x-text="item.unit || 'Pcs'"></td>

                                        <!-- Tombol Hapus Baris -->
                                        <td class="px-2 py-2.5 text-center">
                                            <button type="button"
                                                    @click="removeItemRow(idx)"
                                                    :disabled="form.items.length <= 1"
                                                    class="p-1 rounded text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-colors disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer">
                                                <span class="material-symbols-rounded text-[18px]">delete</span>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>

                <!-- Catatan Pengiriman -->
                <div>
                    <label class="block font-bold text-gray-700 mb-1.5 uppercase tracking-wider text-[10.5px]">Catatan Pengiriman (Opsional)</label>
                    <textarea x-model="form.generalNotes"
                              rows="2"
                              placeholder="Catatan tambahan untuk petugas penerima di toko cabang..."
                              class="w-full bg-white border border-gray-200 rounded-xl p-3 text-xs text-gray-800 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20"></textarea>
                </div>

            </div>

            <!-- Modal Footer Actions -->
            <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between bg-gray-50/50">
                <button type="button"
                        @click="showCreateModal = false"
                        :disabled="isSubmitting"
                        class="px-4 py-2 rounded-xl border border-gray-200 text-gray-700 hover:bg-gray-100 font-bold text-xs cursor-pointer">
                    Batal
                </button>

                <div class="flex items-center gap-2">
                    <!-- Tombol Simpan Draft -->
                    <button type="button"
                            @click="submitShipment('draft')"
                            :disabled="isSubmitting || availableProducts.length === 0"
                            class="px-4 py-2 rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-100 font-bold text-xs cursor-pointer disabled:opacity-50">
                        Simpan sebagai Draft
                    </button>

                    <!-- Tombol Kirim Sekarang -->
                    <button type="button"
                            @click="submitShipment('kirim')"
                            :disabled="isSubmitting || availableProducts.length === 0"
                            class="px-5 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl font-bold text-xs shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                        <span class="material-symbols-rounded text-[16px]">send</span>
                        <span>Kirim Sekarang</span>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- MODAL: DETAIL DOKUMEN PENGIRIMAN (SURAT JALAN TRANSFER) -->
    <div x-show="showDetailModal"
         x-cloak
         x-transition.opacity
         @click.self="showDetailModal = false"
         class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.stop
             class="bg-white rounded-2xl border border-gray-200 shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[90vh]">

            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center">
                        <span class="material-symbols-rounded text-[20px]">description</span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-bold text-gray-900" x-text="selectedDoc ? selectedDoc.docNo : 'Detail Pengiriman'"></h2>
                            <template x-if="selectedDoc">
                                <span class="px-2 py-0.5 rounded-full text-[10.5px] font-bold border"
                                      :class="getStatusBadge(selectedDoc.status).class"
                                      x-text="getStatusBadge(selectedDoc.status).label"></span>
                            </template>
                        </div>
                        <p class="text-[11px] text-gray-500">Surat Jalan Transfer Barang Antar Cabang</p>
                    </div>
                </div>
                <button type="button"
                        @click="showDetailModal = false"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 cursor-pointer">
                    <span class="material-symbols-rounded text-[20px]">close</span>
                </button>
            </div>

            <!-- Modal Body -->
            <template x-if="selectedDoc">
                <div class="p-6 overflow-y-auto space-y-5 text-xs">

                    <!-- Info Ringkasan Header -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-4 bg-slate-50/80 rounded-xl border border-gray-200/80">
                        <div>
                            <span class="text-[10.5px] font-bold text-gray-400 uppercase tracking-wider block">Tanggal</span>
                            <span class="text-xs font-bold text-gray-800 mt-0.5 block" x-text="selectedDoc.date"></span>
                        </div>
                        <div>
                            <span class="text-[10.5px] font-bold text-gray-400 uppercase tracking-wider block">Gudang Asal</span>
                            <span class="text-xs font-bold text-gray-800 mt-0.5 block" x-text="selectedDoc.warehouseOrigin"></span>
                        </div>
                        <div>
                            <span class="text-[10.5px] font-bold text-gray-400 uppercase tracking-wider block">Toko Tujuan</span>
                            <span class="text-xs font-bold text-[#0052cc] mt-0.5 block" x-text="formatStoreName(selectedDoc.destinationStore)"></span>
                        </div>
                        <div>
                            <span class="text-[10.5px] font-bold text-gray-400 uppercase tracking-wider block">No. Referensi</span>
                            <span class="text-xs font-mono font-bold text-gray-700 mt-0.5 block" x-text="selectedDoc.requestRef || '-'"></span>
                        </div>
                        <div class="sm:col-span-2">
                            <span class="text-[10.5px] font-bold text-gray-400 uppercase tracking-wider block">Pengemudi / Driver</span>
                            <span class="text-xs font-bold text-gray-800 mt-0.5 block" x-text="selectedDoc.driverName || '-'"></span>
                        </div>
                        <div class="sm:col-span-2">
                            <span class="text-[10.5px] font-bold text-gray-400 uppercase tracking-wider block">Plat Nomor Kendaraan</span>
                            <span class="text-xs font-mono font-bold text-gray-800 mt-0.5 block" x-text="selectedDoc.plateNumber || '-'"></span>
                        </div>
                    </div>

                    <!-- Tabel Rincian Barang Dikirim -->
                    <div>
                        <h3 class="font-bold text-gray-900 text-xs uppercase tracking-wider mb-2">Rincian Barang Dikirim</h3>
                        <div class="border border-gray-200 rounded-xl overflow-hidden bg-white">
                            <div class="overflow-x-auto min-w-0 w-full">
                            <table class="w-full text-left border-collapse text-xs min-w-[660px]">
                                <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 font-bold uppercase text-[10.5px] whitespace-nowrap">
                                    <tr>
                                        <th class="px-3.5 py-2.5">No</th>
                                        <th class="px-3.5 py-2.5">Jenis Produk</th>
                                        <th class="px-3.5 py-2.5">SKU</th>
                                        <th class="px-3.5 py-2.5">Nama Produk</th>
                                        <th class="px-3.5 py-2.5">Kategori</th>
                                        <th class="px-3.5 py-2.5 text-center">Qty Dikirim</th>
                                        <th class="px-3.5 py-2.5 text-center">Qty Diterima</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <template x-for="(it, idx) in selectedDoc.items" :key="idx">
                                        <tr class="hover:bg-slate-50/50">
                                            <td class="px-3.5 py-2.5 text-gray-400 font-bold" x-text="idx + 1"></td>
                                            <td class="px-3.5 py-2.5 whitespace-nowrap">
                                                <template x-if="it.is_coupon || it.product_type === 'KUPON' || (it.category && (it.category.toLowerCase().includes('kupon') || it.category.toLowerCase().includes('voucher')))">
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10.5px] font-bold bg-indigo-100 text-indigo-900 border border-indigo-300">
                                                        <span class="material-symbols-rounded text-[13px]">local_activity</span>
                                                        <span>Kupon / Voucher</span>
                                                    </span>
                                                </template>
                                                <template x-if="(it.is_consignment || it.product_type === 'UMKM') && !(it.is_coupon || it.product_type === 'KUPON') && !(it.category && (it.category.toLowerCase().includes('kupon') || it.category.toLowerCase().includes('voucher')))">
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10.5px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                                                        <span class="material-symbols-rounded text-[13px]">storefront</span>
                                                        <span>Produk UMKM</span>
                                                    </span>
                                                </template>
                                                <template x-if="!it.is_consignment && it.product_type !== 'UMKM' && !it.is_coupon && it.product_type !== 'KUPON' && !(it.category && (it.category.toLowerCase().includes('kupon') || it.category.toLowerCase().includes('voucher')))">
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10.5px] font-bold bg-blue-100 text-blue-900 border border-blue-300">
                                                        <span class="material-symbols-rounded text-[13px]">inventory_2</span>
                                                        <span>Produk Umum</span>
                                                    </span>
                                                </template>
                                            </td>
                                            <td class="px-3.5 py-2.5 font-mono font-bold text-gray-800" x-text="it.sku"></td>
                                            <td class="px-3.5 py-2.5 font-bold text-gray-900">
                                                <div class="flex flex-col">
                                                    <span x-text="it.name"></span>
                                                    <span x-show="it.supplier_name" class="text-[10px] text-amber-700 font-medium" x-text="'Mitra: ' + it.supplier_name"></span>
                                                </div>
                                            </td>
                                            <td class="px-3.5 py-2.5 text-gray-500 whitespace-nowrap" x-text="it.category || '-'"></td>
                                            <td class="px-3.5 py-2.5 text-center font-mono font-bold text-blue-700 bg-blue-50/30 whitespace-nowrap" x-text="it.qtySent + ' ' + (it.unit || 'Pcs')"></td>
                                            <td class="px-3.5 py-2.5 text-center font-mono font-bold whitespace-nowrap"
                                                :class="selectedDoc.status.includes('DITERIMA') || selectedDoc.status === 'APPROVED' ? (it.qtyReceived === it.qtySent ? 'text-emerald-700 bg-emerald-50/30' : 'text-rose-700 bg-rose-50/30') : 'text-gray-400'"
                                                x-text="(selectedDoc.status.includes('DITERIMA') || selectedDoc.status === 'APPROVED') ? (it.qtyReceived + ' ' + (it.unit || 'Pcs')) : '-'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>

                    <!-- Catatan Pengiriman & Penerimaan -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="p-3 rounded-xl border border-gray-200 bg-gray-50/40">
                            <span class="text-[10.5px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Catatan Pengiriman (Manager)</span>
                            <p class="text-xs text-gray-700 italic font-medium" x-text="selectedDoc.generalNotes || 'Tidak ada catatan.'"></p>
                        </div>
                        <div class="p-3 rounded-xl border border-gray-200 bg-gray-50/40">
                            <span class="text-[10.5px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Status Verifikasi Toko</span>
                            <template x-if="selectedDoc.status.includes('DITERIMA')">
                                <p class="text-xs text-emerald-800 font-semibold" x-text="'Diverifikasi oleh: ' + (selectedDoc.receiverName || 'Kasir Cabang') + ' pada ' + (selectedDoc.receivedAt || '-')"></p>
                            </template>
                            <template x-if="!selectedDoc.status.includes('DITERIMA')">
                                <p class="text-xs text-amber-700 font-medium" x-text="selectedDoc.status === 'DRAFT' ? 'Belum dikirim (Draft)' : 'Menunggu perhitungan fisik kasir cabang saat tiba'"></p>
                            </template>
                        </div>
                    </div>

                </div>
            </template>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between bg-gray-50/50">
                <button type="button"
                        @click="showDetailModal = false"
                        class="px-4 py-2 rounded-xl border border-gray-200 text-gray-700 hover:bg-gray-100 font-bold text-xs cursor-pointer">
                    Tutup
                </button>

                <template x-if="selectedDoc && selectedDoc.status === 'DRAFT'">
                    <button type="button"
                            @click="dispatchDraft(selectedDoc); showDetailModal = false;"
                            class="px-5 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl font-bold text-xs shadow-xs flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-rounded text-[16px]">send</span>
                        <span>Kirim Sekarang</span>
                    </button>
                </template>
            </div>

        </div>
    </div>

    <!-- Notification Toast -->
    <div x-show="showToast"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg border text-xs font-bold"
         :class="{
             'bg-emerald-600 text-white border-emerald-500': toastType === 'success',
             'bg-blue-600 text-white border-blue-500': toastType === 'info',
             'bg-amber-500 text-white border-amber-400': toastType === 'warning',
             'bg-rose-600 text-white border-rose-500': toastType === 'error'
         }">
        <span class="material-symbols-rounded text-[18px]">info</span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
