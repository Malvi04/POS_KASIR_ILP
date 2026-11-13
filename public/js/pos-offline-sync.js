/**
 * POS ILP - Offline Resiliency & Data Synchronization Engine
 * Handles offline transaction processing, local persistence, auto-recovery sync & manual sync.
 */
(function () {
    'use strict';

    const STORAGE_KEYS = {
        OFFLINE_TRANSACTIONS: 'pos_ilp_offline_transactions_v1',
        OFFLINE_MOVEMENTS: 'pos_ilp_offline_movements_v1',
        OFFLINE_SHIFTS: 'pos_ilp_offline_shifts_v1',
        INVENTORY: 'pos_ilp_inventory_products_v1',
        TRANSACTIONS: 'pos_ilp_transactions_v1',
        RECEIPT: 'pos_ilp_latest_printed_receipt_v1',
        ACTIVE_SESSION: 'pos_ilp_active_session_v1',
        LAST_SYNC: 'pos_ilp_last_sync_timestamp_v1'
    };

    function safeGetJson(key, defaultVal = []) {
        try {
            const raw = localStorage.getItem(key);
            return raw ? JSON.parse(raw) : defaultVal;
        } catch (e) {
            console.warn(`[PosOfflineSync] JSON parse error for key "${key}":`, e);
            return defaultVal;
        }
    }

    function safeSetJson(key, val) {
        try {
            localStorage.setItem(key, JSON.stringify(val));
        } catch (e) {
            console.error(`[PosOfflineSync] Storage set error for key "${key}":`, e);
        }
    }

    const state = {
        isSyncing: false,
        pendingCount: 0,
        pendingMovementsCount: 0,
        lastSyncTime: localStorage.getItem(STORAGE_KEYS.LAST_SYNC) || null,
        lastError: null,
        modalOpen: false
    };

    function refreshCounts() {
        const trxs = safeGetJson(STORAGE_KEYS.OFFLINE_TRANSACTIONS, []);
        const movs = safeGetJson(STORAGE_KEYS.OFFLINE_MOVEMENTS, []);
        state.pendingCount = trxs.length;
        state.pendingMovementsCount = movs.length;

        if (window.Alpine && window.Alpine.store && window.Alpine.store('offlineSync')) {
            const store = window.Alpine.store('offlineSync');
            store.pendingCount = trxs.length;
            store.pendingMovementsCount = movs.length;
            store.totalPending = trxs.length + movs.length;
            store.pendingTransactions = trxs;
        }
    }

    const PosOfflineSync = {
        state: state,

        isOnline() {
            if (window.Alpine && window.Alpine.store && window.Alpine.store('network')) {
                return !!window.Alpine.store('network').isOnline;
            }
            return typeof navigator !== 'undefined' ? navigator.onLine : true;
        },

        getPendingTransactions() {
            return safeGetJson(STORAGE_KEYS.OFFLINE_TRANSACTIONS, []);
        },

        getPendingMovements() {
            return safeGetJson(STORAGE_KEYS.OFFLINE_MOVEMENTS, []);
        },

        getPendingCount() {
            const trxs = this.getPendingTransactions();
            const movs = this.getPendingMovements();
            return trxs.length + movs.length;
        },

        /**
         * Generate a unique offline invoice number matching POS standard formatting
         */
        generateOfflineInvoice(terminalCode = 'POS-01') {
            const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
            const randSuffix = Math.floor(1000 + Math.random() * 9000);
            const termSlug = String(terminalCode).replace(/\s+/g, '').toUpperCase();
            return `TRX-OFF-${dateStr}-${termSlug}-${randSuffix}`;
        },

        /**
         * Process and persist transaction 100% offline without server connection
         */
        processOfflineTransaction({ payload, cart, activeSession, grandTotal, paymentModal, isMember, selectedMember }) {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') + ' WIB';
            const dateStr = now.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
            const terminal = payload.terminal_code || activeSession.terminalLabel || 'POS-01';
            const cashier = payload.cashier_name || activeSession.cashierName || 'Kasir';
            const method = payload.payment_method || 'TUNAI';

            const invoiceNumber = this.generateOfflineInvoice(terminal);
            const offlineId = 'off_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);

            // 1. Build Transaction Record
            const methodLabelMap = {
                'TUNAI': 'Uang Tunai',
                'QRIS': 'QRIS Dinamis',
                'TRANSFER': 'Transfer Bank',
                'KREDIT': 'Kredit Anggota',
                'CAMPURAN': 'Tunai + Non-Tunai'
            };

            const cashReceived = method === 'TUNAI'
                ? (payload.cash_received || grandTotal)
                : (method === 'CAMPURAN' ? (payload.split_cash || 0) : 0);

            const changeReturned = method === 'TUNAI'
                ? Math.max(0, cashReceived - grandTotal)
                : 0;

            const trxRecord = {
                id: invoiceNumber,
                rawId: offlineId,
                offline_id: offlineId,
                invoice_number: invoiceNumber,
                time: timeStr,
                date: dateStr,
                created_at: now.toISOString(),
                terminal: terminal,
                terminal_code: terminal,
                cashier: cashier,
                cashier_nik: payload.cashier_nik || activeSession.cashierNik || null,
                cashier_name: cashier,
                method: method,
                payment_method: method,
                methodLabel: methodLabelMap[method] || method,
                subtotal: grandTotal, // subtotal without extra fee
                discount: 0,
                discount_total: 0,
                tax: 0,
                tax_total: 0,
                total: grandTotal,
                grand_total: grandTotal,
                cashGiven: cashReceived,
                cash_received: cashReceived,
                change: changeReturned,
                change_returned: changeReturned,
                paymentReference: payload.payment_reference || ('OFFLINE-' + Date.now()),
                payment_reference: payload.payment_reference || ('OFFLINE-' + Date.now()),
                split_cash: payload.split_cash || 0,
                split_non_cash: payload.split_non_cash || 0,
                cashPortion: method === 'TUNAI' ? grandTotal : (method === 'CAMPURAN' ? (payload.split_cash || 0) : 0),
                nonCashPortion: method === 'TUNAI' ? 0 : (method === 'CAMPURAN' ? (payload.split_non_cash || 0) : grandTotal),
                status: 'SELESAI',
                syncStatus: 'PENDING_SYNC',
                isOffline: true,
                is_offline: true,
                member: (isMember && selectedMember) ? {
                    nik: selectedMember.nik,
                    name: selectedMember.name,
                    remaining_credit: selectedMember.remaining_credit || 0
                } : null,
                member_nik: payload.member_nik || null,
                items: cart.map(it => ({
                    id: it.id || it.sku,
                    sku: it.sku || 'SKU-GEN',
                    barcode: it.barcode || '-',
                    name: it.name,
                    product_name: it.name,
                    qty: it.qty,
                    unit: it.unit || 'Pcs',
                    price: isMember ? (it.memberPrice || it.price) : it.price,
                    unit_price: isMember ? (it.memberPrice || it.price) : it.price,
                    total: (isMember ? (it.memberPrice || it.price) : it.price) * it.qty,
                    total_price: (isMember ? (it.memberPrice || it.price) : it.price) * it.qty,
                    subtotal: (isMember ? (it.memberPrice || it.price) : it.price) * it.qty,
                    isNearExpired: !!it.isNearExpired,
                    is_expired_clearance: !!it.isNearExpired,
                    clearanceDiscountPercent: it.clearanceDiscountPercent || 0,
                    clearance_discount_percent: it.clearanceDiscountPercent || 0
                }))
            };

            // 2. Queue into Offline Transactions
            const pendingList = safeGetJson(STORAGE_KEYS.OFFLINE_TRANSACTIONS, []);
            pendingList.push({
                offline_id: offlineId,
                invoice_number: invoiceNumber,
                terminal_code: terminal,
                cashier_nik: trxRecord.cashier_nik,
                cashier_name: cashier,
                payment_method: method,
                cash_received: cashReceived,
                change_returned: changeReturned,
                payment_reference: trxRecord.payment_reference,
                split_cash: trxRecord.split_cash,
                split_non_cash: trxRecord.split_non_cash,
                member_nik: trxRecord.member_nik,
                created_at: trxRecord.created_at,
                subtotal: trxRecord.subtotal,
                discount_total: trxRecord.discount_total,
                tax_total: trxRecord.tax_total,
                grand_total: trxRecord.grand_total,
                items: trxRecord.items,
                retry_count: 0
            });
            safeSetJson(STORAGE_KEYS.OFFLINE_TRANSACTIONS, pendingList);

            // 3. Save into Local Transaction History
            const historyList = safeGetJson(STORAGE_KEYS.TRANSACTIONS, []);
            // Insert at beginning of list
            historyList.unshift(trxRecord);
            safeSetJson(STORAGE_KEYS.TRANSACTIONS, historyList);

            // 4. Save into Latest Printed Receipt (for cetak-struk page)
            safeSetJson(STORAGE_KEYS.RECEIPT, trxRecord);

            // 5. Deduct Local Inventory Stock (so cashier doesn't see phantom stock)
            try {
                const localProducts = safeGetJson(STORAGE_KEYS.INVENTORY, []);
                if (Array.isArray(localProducts) && localProducts.length > 0) {
                    cart.forEach(cartItem => {
                        const targetProd = localProducts.find(p =>
                            (cartItem.barcode && p.barcode === cartItem.barcode) ||
                            (cartItem.sku && p.sku === cartItem.sku)
                        );
                        if (targetProd && typeof targetProd.stock === 'number') {
                            targetProd.stock = Math.max(0, targetProd.stock - cartItem.qty);
                            if (targetProd.stock <= 0) targetProd.status = 'HABIS';
                            else if (targetProd.stock <= (targetProd.min_stock || 5)) targetProd.status = 'STOK_RENDAH';
                        }
                    });
                    safeSetJson(STORAGE_KEYS.INVENTORY, localProducts);
                }
            } catch (e) {
                console.warn('[PosOfflineSync] Local stock deduction warning:', e);
            }

            // 6. Update Active Session Figures Locally
            try {
                const sessionRaw = localStorage.getItem(STORAGE_KEYS.ACTIVE_SESSION);
                if (sessionRaw) {
                    const session = JSON.parse(sessionRaw);
                    session.trxCount = (session.trxCount || 0) + 1;
                    if (method === 'TUNAI') {
                        session.cashSalesNet = (session.cashSalesNet || 0) + grandTotal;
                    } else if (method === 'QRIS' || method === 'TRANSFER') {
                        session.qrisSales = (session.qrisSales || 0) + grandTotal;
                    } else if (method === 'KREDIT') {
                        session.creditSales = (session.creditSales || 0) + grandTotal;
                    } else if (method === 'CAMPURAN') {
                        session.cashSalesNet = (session.cashSalesNet || 0) + (payload.split_cash || 0);
                        session.qrisSales = (session.qrisSales || 0) + (payload.split_non_cash || 0);
                    }
                    session.pendingSyncCount = (session.pendingSyncCount || 0) + 1;
                    session.isOffline = true;
                    safeSetJson(STORAGE_KEYS.ACTIVE_SESSION, session);
                }
            } catch (e) {
                console.warn('[PosOfflineSync] Active session update warning:', e);
            }

            // 7. Refresh Counts & Broadcast
            refreshCounts();
            if (window.PosRealtime && typeof window.PosRealtime.broadcast === 'function') {
                window.PosRealtime.broadcast('transactions_updated', { invoiceNumber, offline: true });
                window.PosRealtime.broadcast('offline_queue_updated', { count: pendingList.length });
            }

            return trxRecord;
        },

        /**
         * Record drawer cash movement offline
         */
        processOfflineCashMovement(movementData) {
            const list = safeGetJson(STORAGE_KEYS.OFFLINE_MOVEMENTS, []);
            const offlineId = 'mov_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);
            list.push({
                offline_id: offlineId,
                terminal_code: movementData.terminal_code || 'POS-01',
                type: movementData.type || 'OUT',
                amount: movementData.amount || 0,
                reason: movementData.reason || 'Biaya Operasional Laci',
                created_at: new Date().toISOString()
            });
            safeSetJson(STORAGE_KEYS.OFFLINE_MOVEMENTS, list);
            refreshCounts();

            if (window.PosRealtime) {
                window.PosRealtime.broadcast('cash_movements_updated', { offline: true });
            }
        },

        /**
         * Synchronize all offline transactions & movements to the main server
         */
        async syncAll(options = {}) {
            const isManual = !!options.isManual;

            if (state.isSyncing) {
                if (isManual) {
                    this.showToast('Sinkronisasi data sedang berjalan, harap tunggu sebentar...', 'info');
                }
                return { success: false, reason: 'ALREADY_SYNCING' };
            }

            // 1. Check Connectivity
            if (!this.isOnline()) {
                if (isManual) {
                    this.showToast('Koneksi server utama / LAN masih terputus. Data Anda aman tersimpan di penyimpanan lokal kasir.', 'error');
                }
                return { success: false, reason: 'OFFLINE' };
            }

            const pendingTrxs = safeGetJson(STORAGE_KEYS.OFFLINE_TRANSACTIONS, []);
            const pendingMovs = safeGetJson(STORAGE_KEYS.OFFLINE_MOVEMENTS, []);

            if (pendingTrxs.length === 0 && pendingMovs.length === 0) {
                if (isManual) {
                    this.showToast('Semua transaksi dan aktivitas kasir sudah tersinkronisasi 100% dengan server.', 'success');
                }
                return { success: true, syncedCount: 0 };
            }

            state.isSyncing = true;
            if (window.Alpine && window.Alpine.store && window.Alpine.store('offlineSync')) {
                window.Alpine.store('offlineSync').isSyncing = true;
            }

            try {
                // 2. Perform Batch Sync Request
                const res = await fetch('/api/pos/sync-offline', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        transactions: pendingTrxs,
                        cash_movements: pendingMovs
                    })
                });

                const json = await res.json();

                if (!res.ok || !json.success) {
                    throw new Error(json.message || 'Gagal sinkronisasi data ke server');
                }

                // 3. Mark transactions as synced and clean offline queue
                const syncedResults = json.results && json.results.transactions ? json.results.transactions : [];
                const syncedIds = new Set(syncedResults.map(r => r.offline_id));

                // Retain only unsynced transactions in queue (if any failed)
                const remainingTrxs = pendingTrxs.filter(t => !syncedIds.has(t.offline_id));
                safeSetJson(STORAGE_KEYS.OFFLINE_TRANSACTIONS, remainingTrxs);

                // Clean movements
                safeSetJson(STORAGE_KEYS.OFFLINE_MOVEMENTS, []);

                // 4. Update status in local transaction history list
                const historyList = safeGetJson(STORAGE_KEYS.TRANSACTIONS, []);
                let historyChanged = false;
                historyList.forEach(item => {
                    if (item.offline_id && syncedIds.has(item.offline_id)) {
                        item.syncStatus = 'SYNCED';
                        item.isOffline = false;
                        item.is_offline = false;
                        const match = syncedResults.find(r => r.offline_id === item.offline_id);
                        if (match && match.server_id) {
                            item.rawId = match.server_id;
                        }
                        historyChanged = true;
                    }
                });
                if (historyChanged) {
                    safeSetJson(STORAGE_KEYS.TRANSACTIONS, historyList);
                }

                // 5. Reset pendingSyncCount on active session
                try {
                    const sessionRaw = localStorage.getItem(STORAGE_KEYS.ACTIVE_SESSION);
                    if (sessionRaw) {
                        const session = JSON.parse(sessionRaw);
                        session.pendingSyncCount = remainingTrxs.length;
                        if (remainingTrxs.length === 0) session.isOffline = false;
                        safeSetJson(STORAGE_KEYS.ACTIVE_SESSION, session);
                    }
                } catch (e) {}

                // 6. Record last sync time
                const nowTime = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                state.lastSyncTime = nowTime;
                localStorage.setItem(STORAGE_KEYS.LAST_SYNC, nowTime);

                refreshCounts();

                // 7. Dispatch events
                window.dispatchEvent(new CustomEvent('pos:sync-completed', {
                    detail: { syncedCount: json.synced_count, totalPending: remainingTrxs.length }
                }));

                if (window.PosRealtime) {
                    window.PosRealtime.broadcast('transactions_updated', { synced: true });
                    window.PosRealtime.broadcast('inventory_updated', { synced: true });
                }

                const toastMsg = json.synced_count > 0
                    ? `Sinkronisasi Berhasil: ${json.synced_count} transaksi offline berhasil disinkronkan ke server utama!`
                    : 'Semua transaksi offline telah diverifikasi & tersinkronkan!';
                this.showToast(toastMsg, 'success');

                return { success: true, syncedCount: json.synced_count, remaining: remainingTrxs.length };

            } catch (err) {
                console.error('[PosOfflineSync] Sync failed:', err);
                state.lastError = err.message;
                if (isManual) {
                    this.showToast('Gagal sinkronisasi: ' + err.message, 'error');
                }
                return { success: false, error: err.message };
            } finally {
                state.isSyncing = false;
                if (window.Alpine && window.Alpine.store && window.Alpine.store('offlineSync')) {
                    window.Alpine.store('offlineSync').isSyncing = false;
                }
            }
        },

        /**
         * Sync a single specific transaction from queue
         */
        async syncSingle(offlineId) {
            const pendingTrxs = this.getPendingTransactions();
            const target = pendingTrxs.find(t => t.offline_id === offlineId);
            if (!target) return;

            state.isSyncing = true;
            try {
                const res = await fetch('/api/pos/sync-offline', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ transactions: [target], cash_movements: [] })
                });
                const json = await res.json();
                if (json.success) {
                    const remaining = pendingTrxs.filter(t => t.offline_id !== offlineId);
                    safeSetJson(STORAGE_KEYS.OFFLINE_TRANSACTIONS, remaining);

                    // Update history
                    const historyList = safeGetJson(STORAGE_KEYS.TRANSACTIONS, []);
                    const histItem = historyList.find(t => t.offline_id === offlineId);
                    if (histItem) {
                        histItem.syncStatus = 'SYNCED';
                        histItem.isOffline = false;
                        safeSetJson(STORAGE_KEYS.TRANSACTIONS, historyList);
                    }

                    refreshCounts();
                    if (window.PosRealtime) window.PosRealtime.broadcast('transactions_updated');
                    this.showToast(`Transaksi ${target.invoice_number} berhasil disinkronkan!`, 'success');
                } else {
                    this.showToast(`Gagal: ${json.message}`, 'error');
                }
            } catch (e) {
                this.showToast('Gagal terhubung ke server: ' + e.message, 'error');
            } finally {
                state.isSyncing = false;
            }
        },

        /**
         * Export unsynced offline transactions to a downloadable JSON backup
         */
        exportBackup() {
            const pending = this.getPendingTransactions();
            if (pending.length === 0) {
                this.showToast('Tidak ada antrean transaksi offline untuk dicadangkan.', 'info');
                return;
            }

            const dataStr = 'data:text/json;charset=utf-8,' + encodeURIComponent(JSON.stringify(pending, null, 2));
            const dlAnchor = document.createElement('a');
            dlAnchor.setAttribute('href', dataStr);
            dlAnchor.setAttribute('download', `BACKUP_OFFLINE_TRX_${new Date().toISOString().slice(0, 10)}.json`);
            dlAnchor.click();
            this.showToast(`Backup ${pending.length} transaksi offline berhasil diunduh.`, 'success');
        },

        /**
         * Global Toast notification dispatcher
         */
        showToast(msg, type = 'info') {
            if (typeof window.triggerToast === 'function') {
                window.triggerToast(msg, type);
            } else if (typeof window.posToast === 'function') {
                window.posToast(msg, type);
            } else {
                console.log(`[Toast ${type}]: ${msg}`);
            }
        }
    };

    // 4. Auto-Sync Event Listeners
    // A. Online event (LAN restored / Wi-Fi reconnected)
    window.addEventListener('online', () => {
        setTimeout(() => {
            if (PosOfflineSync.getPendingCount() > 0) {
                PosOfflineSync.syncAll({ isManual: false });
            }
        }, 1200);
    });

    // B. Network health status transition event from pos-realtime.js
    window.addEventListener('pos:network-status', (e) => {
        if (e.detail && e.detail.isOnline) {
            if (PosOfflineSync.getPendingCount() > 0 && !state.isSyncing) {
                PosOfflineSync.syncAll({ isManual: false });
            }
        }
    });

    // C. Periodic background auto-sync check (every 18 seconds)
    setInterval(() => {
        if (PosOfflineSync.isOnline() && PosOfflineSync.getPendingCount() > 0 && !state.isSyncing) {
            PosOfflineSync.syncAll({ isManual: false });
        }
    }, 18000);

    // Initial count calculation
    refreshCounts();

    // 5. Register Alpine Store
    function initOfflineAlpineStore() {
        if (!window.Alpine) return;

        if (!window.Alpine.store('offlineSync')) {
            window.Alpine.store('offlineSync', {
                pendingCount: state.pendingCount,
                pendingMovementsCount: state.pendingMovementsCount,
                totalPending: state.pendingCount + state.pendingMovementsCount,
                isSyncing: false,
                lastSyncTime: state.lastSyncTime,
                modalOpen: false,
                pendingTransactions: PosOfflineSync.getPendingTransactions(),

                refresh() {
                    refreshCounts();
                },

                syncNow() {
                    return PosOfflineSync.syncAll({ isManual: true });
                },

                syncSingle(offlineId) {
                    return PosOfflineSync.syncSingle(offlineId);
                },

                openModal() {
                    this.pendingTransactions = PosOfflineSync.getPendingTransactions();
                    this.modalOpen = true;
                },

                closeModal() {
                    this.modalOpen = false;
                },

                exportBackup() {
                    PosOfflineSync.exportBackup();
                }
            });
        }
    }

    if (window.Alpine) {
        initOfflineAlpineStore();
    } else {
        document.addEventListener('alpine:init', initOfflineAlpineStore);
    }

    window.PosOfflineSync = PosOfflineSync;
})();
