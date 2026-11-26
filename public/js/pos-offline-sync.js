/**
 * POS Kasir ILP - Offline Resiliency & Data Synchronization Engine
 * Powered by Dexie.js (IndexedDB) with LocalStorage Fallback & In-Memory Snapshot
 * Version 3.0
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

    function generateUuid() {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    function isQuotaError(err) {
        if (!err) return false;
        return err.name === 'QuotaExceededError'
            || err.name === 'NS_ERROR_DOM_QUOTA_REACHED'
            || err.code === 22
            || err.code === 1014
            || /quota/i.test(String(err.message || ''));
    }

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
            return { ok: true, isQuota: false, error: null };
        } catch (e) {
            const quota = isQuotaError(e);
            return { ok: false, isQuota: quota, error: e && e.message ? e.message : 'Storage write error' };
        }
    }

    // In-Memory cache for lightning-fast synchronous UI queries
    const state = {
        isSyncing: false,
        pendingCount: 0,
        pendingMovementsCount: 0,
        pendingShiftsCount: 0,
        lastSyncTime: localStorage.getItem(STORAGE_KEYS.LAST_SYNC) || null,
        lastError: null,
        lastStorageError: null,
        modalOpen: false,

        // In-memory queues synced with Dexie
        cachedTransactions: [],
        cachedMovements: [],
        cachedShifts: []
    };

    async function reloadFromDexie() {
        if (window.PosDb && typeof window.PosDb.init === 'function') {
            try {
                await window.PosDb.init();
                const [trxs, movs, shifts] = await Promise.all([
                    window.PosDb.transactions.getPending(),
                    window.PosDb.movements.getPending(),
                    window.PosDb.shifts.getPending()
                ]);

                state.cachedTransactions = Array.isArray(trxs) ? trxs : [];
                state.cachedMovements = Array.isArray(movs) ? movs : [];
                state.cachedShifts = Array.isArray(shifts) ? shifts : [];

                refreshCounts();
                return true;
            } catch (e) {
                console.warn('[PosOfflineSync] Failed to reload queues from Dexie:', e);
            }
        }

        // Fallback to localStorage if PosDb is unavailable
        state.cachedTransactions = safeGetJson(STORAGE_KEYS.OFFLINE_TRANSACTIONS, []);
        state.cachedMovements = safeGetJson(STORAGE_KEYS.OFFLINE_MOVEMENTS, []);
        state.cachedShifts = safeGetJson(STORAGE_KEYS.OFFLINE_SHIFTS, []);
        refreshCounts();
        return false;
    }

    function refreshCounts() {
        state.pendingCount = state.cachedTransactions.length;
        state.pendingMovementsCount = state.cachedMovements.length;
        state.pendingShiftsCount = state.cachedShifts.length;

        if (window.Alpine && window.Alpine.store && window.Alpine.store('offlineSync')) {
            const store = window.Alpine.store('offlineSync');
            store.pendingCount = state.pendingCount;
            store.pendingMovementsCount = state.pendingMovementsCount;
            store.pendingShiftsCount = state.pendingShiftsCount;
            store.totalPending = state.pendingCount + state.pendingMovementsCount + state.pendingShiftsCount;
            store.pendingTransactions = state.cachedTransactions;
        }
    }

    const PosOfflineSync = {
        state: state,

        generateUuid: generateUuid,
        safeSetJson: safeSetJson,
        safeGetJson: safeGetJson,
        isQuotaError: isQuotaError,
        reloadFromDexie: reloadFromDexie,

        isOnline() {
            const isLocalHost = typeof window !== 'undefined' && ['127.0.0.1', 'localhost'].includes(window.location.hostname);
            if (window.Alpine && window.Alpine.store && window.Alpine.store('network')) {
                const net = window.Alpine.store('network');
                if (isLocalHost) {
                    return net.isServerConnected !== false;
                }
                // Fully online ONLY when browser has network AND server is reachable
                return !!(net.isOnline && (net.isServerConnected !== false));
            }
            if (window.posNetworkHealth && typeof window.posNetworkHealth.isServerConnected === 'boolean') {
                if (isLocalHost) {
                    return window.posNetworkHealth.isServerConnected !== false;
                }
                return !!(window.posNetworkHealth.isBrowserOnline && window.posNetworkHealth.isServerConnected);
            }
            return isLocalHost ? true : (typeof navigator !== 'undefined' ? navigator.onLine : true);
        },

        getPendingTransactions() {
            return state.cachedTransactions || [];
        },

        getPendingMovements() {
            return state.cachedMovements || [];
        },

        getPendingShifts() {
            return state.cachedShifts || [];
        },

        getPendingCount() {
            return (state.cachedTransactions?.length || 0)
                + (state.cachedMovements?.length || 0)
                + (state.cachedShifts?.length || 0);
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
         * Process and persist transaction offline in Dexie.js (IndexedDB)
         */
        processOfflineTransaction({ payload, cart, activeSession, grandTotal, paymentModal, isMember, selectedMember }) {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') + ' WIB';
            const dateStr = now.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
            const terminal = payload.terminal_code || activeSession?.terminalLabel || 'POS-01';
            const cashier = payload.cashier_name || activeSession?.cashierName || 'Kasir';
            const activeBranch = payload.branch || activeSession?.storeLocation || localStorage.getItem('pos_kasir_branch') || 'Pusat';
            const activeLocationId = payload.location_id || activeSession?.locationId || null;
            const method = payload.payment_method || 'TUNAI';

            const invoiceNumber = payload.invoice_number || this.generateOfflineInvoice(terminal);
            const offlineId = payload.offline_id || ('off_' + generateUuid());

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
                location_id: activeLocationId,
                branch: activeBranch,
                cashier: cashier,
                cashier_nik: payload.cashier_nik || activeSession?.cashierNik || null,
                cashier_name: cashier,
                method: method,
                payment_method: method,
                methodLabel: methodLabelMap[method] || method,
                subtotal: grandTotal,
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
                sync_status: 'PENDING_SYNC',
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
                    total: (isMember ? (it.memberPrice || it.price) : it.price) * it.qty - (Number(it.promoDiscount) || 0),
                    total_price: (isMember ? (it.memberPrice || it.price) : it.price) * it.qty - (Number(it.promoDiscount) || 0),
                    subtotal: (isMember ? (it.memberPrice || it.price) : it.price) * it.qty - (Number(it.promoDiscount) || 0),
                    promoName: it.promoName || null,
                    promo_name: it.promoName || null,
                    promoDiscount: Number(it.promoDiscount) || 0,
                    promo_discount: Number(it.promoDiscount) || 0,
                    isNearExpired: !!it.isNearExpired,
                    is_expired_clearance: !!it.isNearExpired,
                    clearanceDiscountPercent: it.clearanceDiscountPercent || 0,
                    clearance_discount_percent: it.clearanceDiscountPercent || 0
                }))
            };

            const queueItem = {
                offline_id: offlineId,
                invoice_number: invoiceNumber,
                terminal_code: terminal,
                location_id: activeLocationId,
                branch: activeBranch,
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
                sync_status: 'PENDING_SYNC',
                retry_count: 0
            };

            // 1. Immediately update in-memory queues for responsive UI
            state.cachedTransactions.push(queueItem);
            refreshCounts();

            // 2. Persist to Dexie.js (IndexedDB)
            if (window.PosDb && typeof window.PosDb.transactions?.enqueue === 'function') {
                window.PosDb.transactions.enqueue(queueItem).then(() => {
                    // Deduct stock in IndexedDB product_cache
                    if (activeLocationId) {
                        window.PosDb.products.deductStock(activeLocationId, trxRecord.items).catch(err => {
                            console.warn('[PosOfflineSync] Stock deduction in Dexie warning:', err);
                        });
                    }
                }).catch(err => {
                    console.error('[PosOfflineSync] Dexie persistence error:', err);
                });
            }

            // 3. Keep fallback mirror in localStorage if quota permits
            try {
                const pendingList = safeGetJson(STORAGE_KEYS.OFFLINE_TRANSACTIONS, []);
                pendingList.push(queueItem);
                safeSetJson(STORAGE_KEYS.OFFLINE_TRANSACTIONS, pendingList);

                const historyList = safeGetJson(STORAGE_KEYS.TRANSACTIONS, []);
                historyList.unshift(trxRecord);
                safeSetJson(STORAGE_KEYS.TRANSACTIONS, historyList.slice(0, 100));

                safeSetJson(STORAGE_KEYS.RECEIPT, trxRecord);
            } catch (mirrorErr) {
                // Secondary mirror failure is non-fatal because Dexie is the source of truth
            }

            // 4. Update Active Session Figures Locally
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
                    session.pendingSyncCount = this.getPendingCount();
                    session.isOffline = true;
                    safeSetJson(STORAGE_KEYS.ACTIVE_SESSION, session);
                }
            } catch (e) {
                console.warn('[PosOfflineSync] Active session update warning:', e);
            }

            // 5. Broadcast events
            if (window.PosRealtime && typeof window.PosRealtime.broadcast === 'function') {
                window.PosRealtime.broadcast('transactions_updated', { invoiceNumber, offline: true });
                window.PosRealtime.broadcast('offline_queue_updated', { count: state.cachedTransactions.length });
            }

            return {
                success: true,
                trxRecord: trxRecord,
                ...trxRecord
            };
        },

        /**
         * Queue offline shift opening
         */
        processOfflineShiftOpen(shiftData) {
            const offlineId = shiftData.offline_id || ('shf_open_' + generateUuid());
            const item = {
                offline_id: offlineId,
                action: 'OPEN',
                shift_code: shiftData.shift_code || null,
                terminal_code: shiftData.terminal_code || 'POS-01',
                location_id: shiftData.location_id || null,
                branch: shiftData.branch || null,
                cashier_nik: shiftData.cashier_nik || null,
                cashier_name: shiftData.cashier_name || null,
                opening_cash: Number(shiftData.opening_cash || 0),
                opened_at: shiftData.opened_at || new Date().toISOString(),
                sync_status: 'PENDING_SYNC',
                retry_count: 0
            };

            state.cachedShifts.push(item);
            refreshCounts();

            if (window.PosDb && typeof window.PosDb.shifts?.enqueue === 'function') {
                window.PosDb.shifts.enqueue(item).catch(err => {
                    console.error('[PosOfflineSync] Error enqueuing shift in Dexie:', err);
                });
            }

            // Fallback mirror
            try {
                const list = safeGetJson(STORAGE_KEYS.OFFLINE_SHIFTS, []);
                list.push(item);
                safeSetJson(STORAGE_KEYS.OFFLINE_SHIFTS, list);
            } catch (e) {}

            return { success: true, offline_id: offlineId };
        },

        /**
         * Queue offline shift closing & handover
         */
        processOfflineShiftClose(shiftData) {
            const offlineId = shiftData.offline_id || ('shf_close_' + generateUuid());
            const item = {
                offline_id: offlineId,
                action: 'CLOSE',
                shift_code: shiftData.shift_code || null,
                terminal_code: shiftData.terminal_code || 'POS-01',
                location_id: shiftData.location_id || null,
                branch: shiftData.branch || null,
                cashier_nik: shiftData.cashier_nik || null,
                cashier_name: shiftData.cashier_name || null,
                actual_cash: Number(shiftData.actual_cash || 0),
                closing_notes: shiftData.closing_notes || null,
                closed_at: shiftData.closed_at || new Date().toISOString(),
                sync_status: 'PENDING_SYNC',
                retry_count: 0
            };

            state.cachedShifts.push(item);
            refreshCounts();

            if (window.PosDb && typeof window.PosDb.shifts?.enqueue === 'function') {
                window.PosDb.shifts.enqueue(item).catch(err => {
                    console.error('[PosOfflineSync] Error enqueuing shift close in Dexie:', err);
                });
            }

            // Fallback mirror
            try {
                const list = safeGetJson(STORAGE_KEYS.OFFLINE_SHIFTS, []);
                list.push(item);
                safeSetJson(STORAGE_KEYS.OFFLINE_SHIFTS, list);
            } catch (e) {}

            return { success: true, offline_id: offlineId };
        },

        /**
         * Record drawer cash movement offline
         */
        processOfflineCashMovement(movementData) {
            const offlineId = movementData.offline_id || ('mov_' + generateUuid());
            const item = {
                offline_id: offlineId,
                shift_code: movementData.shift_code || null,
                terminal_code: movementData.terminal_code || 'POS-01',
                location_id: movementData.location_id || null,
                branch: movementData.branch || null,
                cashier_nik: movementData.cashier_nik || null,
                type: movementData.type || 'OUT',
                amount: Number(movementData.amount || 0),
                reason: movementData.reason || 'Biaya Operasional Laci',
                created_at: new Date().toISOString(),
                sync_status: 'PENDING_SYNC',
                retry_count: 0
            };

            state.cachedMovements.push(item);
            refreshCounts();

            if (window.PosDb && typeof window.PosDb.movements?.enqueue === 'function') {
                window.PosDb.movements.enqueue(item).catch(err => {
                    console.error('[PosOfflineSync] Error enqueuing movement in Dexie:', err);
                });
            }

            // Fallback mirror
            try {
                const list = safeGetJson(STORAGE_KEYS.OFFLINE_MOVEMENTS, []);
                list.push(item);
                safeSetJson(STORAGE_KEYS.OFFLINE_MOVEMENTS, list);
            } catch (e) {}

            if (window.PosRealtime && typeof window.PosRealtime.broadcast === 'function') {
                window.PosRealtime.broadcast('cash_movements_updated', { offline: true });
            }

            return { success: true, offline_id: offlineId };
        },

        /**
         * Synchronize all offline transactions, shifts, & cash movements to the main server
         */
        async syncAll(options = {}) {
            const isManual = !!options.isManual;

            if (state.isSyncing) {
                if (isManual) {
                    this.showToast('Sinkronisasi data sedang berjalan, harap tunggu sebentar...', 'info');
                }
                return { success: false, reason: 'ALREADY_SYNCING' };
            }

            // Refresh from Dexie first before checking pending counts
            await reloadFromDexie();

            // 1. Check Connectivity
            const isLocalHost = typeof window !== 'undefined' && ['127.0.0.1', 'localhost'].includes(window.location.hostname);
            if (!this.isOnline()) {
                if (isLocalHost || (typeof navigator !== 'undefined' && navigator.onLine)) {
                    const probeCtrl = new AbortController();
                    const probeTimeout = setTimeout(() => probeCtrl.abort(), 3000);
                    try {
                        const probe = await fetch('/api/health?_t=' + Date.now(), {
                            method: 'GET',
                            headers: { 'Accept': 'application/json', 'Cache-Control': 'no-cache' },
                            signal: probeCtrl.signal
                        });
                        clearTimeout(probeTimeout);
                        if (probe.ok) {
                            if (window.PosRealtime && typeof window.PosRealtime.markOnline === 'function') {
                                window.PosRealtime.markOnline('sync_probe_success');
                            }
                        } else {
                            if (isManual) {
                                this.showToast('Server utama belum merespons (Wi-Fi aktif). Data kasir tetap aman di laci lokal.', 'warning');
                            }
                            return { success: false, reason: 'SERVER_UNAVAILABLE' };
                        }
                    } catch (e) {
                        clearTimeout(probeTimeout);
                        if (isManual) {
                            this.showToast('Server utama belum merespons (Wi-Fi aktif). Data kasir tetap aman di laci lokal.', 'warning');
                        }
                        return { success: false, reason: 'SERVER_UNAVAILABLE' };
                    }
                } else {
                    if (isManual) {
                        this.showToast('Perangkat tidak terhubung ke jaringan (Wi-Fi / LAN mati). Data transaksi aman di laci lokal.', 'warning');
                    }
                    return { success: false, reason: 'OFFLINE' };
                }
            }

            const activeNik = (localStorage.getItem('pos_portal_logged_in_nik') || '').trim();
            const activeUsername = (localStorage.getItem('pos_portal_logged_in_username') || '').trim();
            const matchesActiveCashier = (item) => {
                if (!item || !item.cashier_nik) return true;
                if (!activeNik && !activeUsername) return true;
                const itemNik = String(item.cashier_nik).trim();
                return itemNik === activeNik || itemNik === activeUsername;
            };

            const pendingTrxs = state.cachedTransactions.filter(matchesActiveCashier);
            const deferredTrxs = state.cachedTransactions.filter(it => !matchesActiveCashier(it));
            const pendingMovs = state.cachedMovements.filter(matchesActiveCashier);
            const deferredMovs = state.cachedMovements.filter(it => !matchesActiveCashier(it));
            const pendingShifts = state.cachedShifts.filter(matchesActiveCashier);
            const deferredShifts = state.cachedShifts.filter(it => !matchesActiveCashier(it));

            if (pendingTrxs.length === 0 && pendingMovs.length === 0 && pendingShifts.length === 0) {
                if (isManual) {
                    const deferredTotal = deferredTrxs.length + deferredMovs.length + deferredShifts.length;
                    if (deferredTotal > 0) {
                        this.showToast(`Terdapat ${deferredTotal} data offline milik sesi akun kasir lain. Login dengan akun kasir terkait untuk menyinkronkannya.`, 'info');
                    } else {
                        this.showToast('Semua transaksi dan aktivitas kasir sudah tersinkronisasi 100% dengan server.', 'success');
                    }
                }
                return { success: true, syncedCount: 0, remaining: deferredTrxs.length + deferredMovs.length + deferredShifts.length };
            }

            state.isSyncing = true;
            if (window.Alpine && window.Alpine.store && window.Alpine.store('offlineSync')) {
                window.Alpine.store('offlineSync').isSyncing = true;
            }

            const syncCtrl = new AbortController();
            const syncTimeout = setTimeout(() => syncCtrl.abort(), 10000);

            try {
                // 2. Perform Batch Sync Request (strict order: shifts -> transactions -> movements)
                const res = await fetch('/api/pos/sync-offline', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    signal: syncCtrl.signal,
                    body: JSON.stringify({
                        offline_shifts: pendingShifts,
                        transactions: pendingTrxs,
                        cash_movements: pendingMovs
                    })
                });
                clearTimeout(syncTimeout);

                const json = await res.json();

                if (!res.ok || !json.success) {
                    throw new Error(json.message || 'Gagal sinkronisasi data ke server');
                }

                // 3. Process Per-Item Results: Remove ONLY confirmed SYNCED or ALREADY_SYNCED
                const confirmedStatus = (s) => s === 'SYNCED' || s === 'ALREADY_SYNCED';

                const trxResults = json.results && Array.isArray(json.results.transactions) ? json.results.transactions : [];
                const trxResMap = new Map(trxResults.map(r => [r.offline_id, r]));

                const confirmedTrxIds = [];
                const failedTrxList = [];

                pendingTrxs.forEach(t => {
                    const match = trxResMap.get(t.offline_id);
                    if (match && confirmedStatus(match.status)) {
                        confirmedTrxIds.push(t.offline_id);
                    } else {
                        failedTrxList.push({
                            ...t,
                            sync_status: 'FAILED',
                            retry_count: (t.retry_count || 0) + 1,
                            last_error: match && match.message ? match.message : 'Belum terkonfirmasi server'
                        });
                    }
                });

                const movResults = json.results && Array.isArray(json.results.cash_movements) ? json.results.cash_movements : [];
                const movResMap = new Map(movResults.map(r => [r.offline_id, r]));

                const confirmedMovIds = [];
                const failedMovList = [];

                pendingMovs.forEach(m => {
                    const match = movResMap.get(m.offline_id);
                    if (match && confirmedStatus(match.status)) {
                        confirmedMovIds.push(m.offline_id);
                    } else {
                        failedMovList.push({
                            ...m,
                            sync_status: 'FAILED',
                            retry_count: (m.retry_count || 0) + 1,
                            last_error: match && match.message ? match.message : 'Belum terkonfirmasi server'
                        });
                    }
                });

                const shiftResults = json.results && Array.isArray(json.results.shifts) ? json.results.shifts : [];
                const shiftResMap = new Map(shiftResults.map(r => [r.offline_id, r]));

                const confirmedShiftIds = [];
                const failedShiftList = [];

                pendingShifts.forEach(s => {
                    const match = shiftResMap.get(s.offline_id);
                    if (match && confirmedStatus(match.status)) {
                        confirmedShiftIds.push(s.offline_id);
                    } else {
                        failedShiftList.push({
                            ...s,
                            sync_status: 'FAILED',
                            retry_count: (s.retry_count || 0) + 1,
                            last_error: match && match.message ? match.message : 'Belum terkonfirmasi server'
                        });
                    }
                });

                // 4. Update Dexie.js Tables
                if (window.PosDb && typeof window.PosDb.init === 'function') {
                    if (confirmedTrxIds.length > 0) {
                        await window.PosDb.transactions.removeSynced(confirmedTrxIds);
                    }
                    if (confirmedMovIds.length > 0) {
                        await window.PosDb.movements.removeSynced(confirmedMovIds);
                    }
                    if (confirmedShiftIds.length > 0) {
                        await window.PosDb.shifts.removeSynced(confirmedShiftIds);
                    }

                    // Update failed items in Dexie
                    for (const ft of failedTrxList) {
                        await window.PosDb.transactions.markFailed(ft.offline_id, ft.last_error);
                    }
                    for (const fm of failedMovList) {
                        await window.PosDb.movements.markFailed(fm.offline_id, fm.last_error);
                    }
                    for (const fs of failedShiftList) {
                        await window.PosDb.shifts.markFailed(fs.offline_id, fs.last_error);
                    }

                    // Log audit entry
                    await window.PosDb.logs.addLog('SYNC_BATCH', 'batch_' + Date.now(), 'SUCCESS', `Synced: ${json.synced_count}, Failed: ${json.failed_count || 0}`);
                }

                // 5. Update In-Memory Queues (preserve deferred items from any other cashier session)
                const remainingTrxs = [...failedTrxList, ...deferredTrxs];
                const remainingMovs = [...failedMovList, ...deferredMovs];
                const remainingShifts = [...failedShiftList, ...deferredShifts];

                state.cachedTransactions = remainingTrxs;
                state.cachedMovements = remainingMovs;
                state.cachedShifts = remainingShifts;

                // Sync fallback mirror in localStorage
                safeSetJson(STORAGE_KEYS.OFFLINE_TRANSACTIONS, remainingTrxs);
                safeSetJson(STORAGE_KEYS.OFFLINE_MOVEMENTS, remainingMovs);
                safeSetJson(STORAGE_KEYS.OFFLINE_SHIFTS, remainingShifts);

                // 6. Update local history status
                const historyList = safeGetJson(STORAGE_KEYS.TRANSACTIONS, []);
                let histChanged = false;
                const confirmedSet = new Set(confirmedTrxIds);
                historyList.forEach(item => {
                    if (item.offline_id && confirmedSet.has(item.offline_id)) {
                        item.syncStatus = 'SYNCED';
                        item.isOffline = false;
                        item.is_offline = false;
                        const match = trxResMap.get(item.offline_id);
                        if (match && match.server_id) item.rawId = match.server_id;
                        histChanged = true;
                    }
                });
                if (histChanged) {
                    safeSetJson(STORAGE_KEYS.TRANSACTIONS, historyList);
                }

                // 7. Reset active session counters
                const totalRemaining = remainingTrxs.length + remainingMovs.length + remainingShifts.length;
                try {
                    const sessionRaw = localStorage.getItem(STORAGE_KEYS.ACTIVE_SESSION);
                    if (sessionRaw) {
                        const session = JSON.parse(sessionRaw);
                        session.pendingSyncCount = totalRemaining;
                        if (totalRemaining === 0) session.isOffline = false;
                        safeSetJson(STORAGE_KEYS.ACTIVE_SESSION, session);
                    }
                } catch (e) {}

                // 8. Record last sync time
                const nowTime = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                state.lastSyncTime = nowTime;
                localStorage.setItem(STORAGE_KEYS.LAST_SYNC, nowTime);

                refreshCounts();

                // 9. Dispatch events
                window.dispatchEvent(new CustomEvent('pos:sync-completed', {
                    detail: { syncedCount: json.synced_count, totalPending: totalRemaining, failedCount: json.failed_count || 0 }
                }));

                if (window.PosRealtime && typeof window.PosRealtime.broadcast === 'function') {
                    window.PosRealtime.broadcast('transactions_updated', { synced: true });
                    window.PosRealtime.broadcast('inventory_updated', { synced: true });
                }

                if (isManual || json.synced_count > 0 || json.failed_count > 0) {
                    const toastMsg = (json.failed_count > 0)
                        ? `Sinkronisasi parsial: ${json.synced_count} transaksi berhasil, ${json.failed_count} item tertunda di antrean.`
                        : (json.synced_count > 0
                            ? `Sinkronisasi Berhasil: ${json.synced_count} transaksi offline berhasil disinkronkan ke server utama!`
                            : 'Semua aktivitas offline telah diverifikasi & tersinkronkan!');
                    this.showToast(toastMsg, json.failed_count > 0 ? 'warning' : 'success');
                }

                return {
                    success: true,
                    partial: totalRemaining > 0,
                    syncedCount: json.synced_count,
                    duplicateCount: json.duplicate_count || 0,
                    failedCount: json.failed_count || 0,
                    remaining: totalRemaining
                };

            } catch (err) {
                console.error('[PosOfflineSync] Sync failed:', err);
                state.lastError = err.message;
                if (window.PosDb && typeof window.PosDb.logs?.addLog === 'function') {
                    window.PosDb.logs.addLog('SYNC_BATCH', 'batch_err', 'FAILED', err.message);
                }
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
            const singleCtrl = new AbortController();
            const singleTimeout = setTimeout(() => singleCtrl.abort(), 5000);
            try {
                const res = await fetch('/api/pos/sync-offline', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    signal: singleCtrl.signal,
                    body: JSON.stringify({ transactions: [target], cash_movements: [], offline_shifts: [] })
                });
                clearTimeout(singleTimeout);
                const json = await res.json();
                const trxItemRes = json.results && Array.isArray(json.results.transactions)
                    ? json.results.transactions.find(r => r.offline_id === offlineId)
                    : null;

                if (res.ok && json.success && trxItemRes && (trxItemRes.status === 'SYNCED' || trxItemRes.status === 'ALREADY_SYNCED')) {
                    if (window.PosDb && typeof window.PosDb.transactions?.removeSynced === 'function') {
                        await window.PosDb.transactions.removeSynced([offlineId]);
                    }
                    state.cachedTransactions = state.cachedTransactions.filter(t => t.offline_id !== offlineId);
                    safeSetJson(STORAGE_KEYS.OFFLINE_TRANSACTIONS, state.cachedTransactions);

                    const historyList = safeGetJson(STORAGE_KEYS.TRANSACTIONS, []);
                    const histItem = historyList.find(t => t.offline_id === offlineId);
                    if (histItem) {
                        histItem.syncStatus = 'SYNCED';
                        histItem.isOffline = false;
                        if (trxItemRes.server_id) histItem.rawId = trxItemRes.server_id;
                        safeSetJson(STORAGE_KEYS.TRANSACTIONS, historyList);
                    }

                    refreshCounts();
                    if (window.PosRealtime) window.PosRealtime.broadcast('transactions_updated');
                    this.showToast(`Transaksi ${target.invoice_number} berhasil disinkronkan!`, 'success');
                } else {
                    const errMsg = (trxItemRes && trxItemRes.message) || json.message || 'Gagal sinkronisasi item.';
                    this.showToast(`Gagal: ${errMsg}`, 'error');
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

    // Auto-Sync Event Listeners
    window.addEventListener('online', () => {
        setTimeout(() => {
            if (PosOfflineSync.getPendingCount() > 0) {
                PosOfflineSync.syncAll({ isManual: false });
            }
        }, 1200);
    });

    window.addEventListener('pos:network-status', (e) => {
        if (e.detail && e.detail.isOnline) {
            if (PosOfflineSync.getPendingCount() > 0 && !state.isSyncing) {
                PosOfflineSync.syncAll({ isManual: false });
            }
        }
    });

    setInterval(() => {
        if (PosOfflineSync.isOnline() && PosOfflineSync.getPendingCount() > 0 && !state.isSyncing) {
            PosOfflineSync.syncAll({ isManual: false });
        }
    }, 18000);

    // Initial load from Dexie / localStorage
    reloadFromDexie().then(() => {
        setTimeout(() => {
            if (PosOfflineSync.getPendingCount() > 0 && !state.isSyncing && PosOfflineSync.isOnline()) {
                PosOfflineSync.syncAll({ isManual: false });
            }
        }, 2000);
    });

    // Register Alpine Store
    function initOfflineAlpineStore() {
        if (!window.Alpine) return;

        if (!window.Alpine.store('offlineSync')) {
            window.Alpine.store('offlineSync', {
                pendingCount: state.pendingCount,
                pendingMovementsCount: state.pendingMovementsCount,
                pendingShiftsCount: state.pendingShiftsCount,
                totalPending: state.pendingCount + state.pendingMovementsCount + state.pendingShiftsCount,
                isSyncing: false,
                lastSyncTime: state.lastSyncTime,
                modalOpen: false,
                pendingTransactions: state.cachedTransactions,

                refresh() {
                    reloadFromDexie();
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
