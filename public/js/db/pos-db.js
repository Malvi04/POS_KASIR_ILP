/**
 * POS Kasir ILP - Dexie.js (IndexedDB) Offline Storage Engine
 * Version 1.0 (Database: PosIlpOfflineDB)
 *
 * Provides structured, versioned, high-capacity offline storage
 * for products, categories, transactions, shifts, movements, and sync logs.
 */
(function (global) {
    'use strict';

    const DexieClass = global.Dexie || (typeof require !== 'undefined' ? require('dexie') : null);
    if (!DexieClass) {
        console.error('[PosDb] Dexie library is not loaded! Offline IndexedDB is unavailable.');
        return;
    }

    const DB_NAME = 'PosIlpOfflineDB';
    const db = new DexieClass(DB_NAME);

    // Schema definition - Version 1
    db.version(1).stores({
        // Product cache isolated per location
        product_cache: '++id, product_id, location_id, [location_id+barcode], [location_id+sku], [location_id+name], [location_id+category], barcode, sku, name, is_active, stock_status',

        // Category cache per location
        category_cache: '++id, [location_id+name], location_id, name',

        // Offline transaction queue (waiting to sync)
        transaction_queue: 'offline_id, invoice_number, location_id, terminal_code, cashier_nik, sync_status, created_at, retry_count',

        // Local completed transaction history (for receipt printing & offline listing)
        transaction_history: 'offline_id, invoice_number, location_id, terminal_code, created_at, syncStatus',

        // Offline shift queue (OPEN & CLOSE actions)
        shift_queue: 'offline_id, shift_code, terminal_code, location_id, action, sync_status, created_at',

        // Offline drawer cash movement queue (IN / OUT)
        cash_movement_queue: 'offline_id, shift_code, terminal_code, location_id, type, sync_status, created_at',

        // Audit sync logs
        sync_logs: '++id, entity, offline_id, status, error_message, timestamp',

        // Sync metadata (timestamps, cursors, versions)
        sync_metadata: 'key, last_synced_at, version, meta',

        // Location metadata
        location_metadata: 'location_id, code, name, type, is_active, cached_at'
    });

    // Helper functions
    function normalizeStr(str) {
        return (str || '').toString().trim().toLowerCase();
    }

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

    const PosDb = {
        db: db,
        version: 1,

        isReady: false,
        initPromise: null,

        async init() {
            if (this.initPromise) return this.initPromise;
            this.initPromise = (async () => {
                try {
                    await db.open();
                    this.isReady = true;
                    console.log(`[PosDb] Connected to IndexedDB database "${DB_NAME}" (v${db.verno})`);
                    return true;
                } catch (err) {
                    console.error('[PosDb] Failed to open IndexedDB:', err);
                    this.isReady = false;
                    throw err;
                }
            })();
            return this.initPromise;
        },

        // --- PRODUCT CACHE REPOSITORY ---
        products: {
            async getByLocation(locationId, options = {}) {
                await PosDb.init();
                const locId = Number(locationId);
                let collection = db.product_cache.where('location_id').equals(locId);

                if (options.category && normalizeStr(options.category) !== 'semua') {
                    const normCat = normalizeStr(options.category);
                    collection = collection.filter(p => normalizeStr(p.category) === normCat);
                }

                if (options.limit && options.limit > 0) {
                    return await collection.limit(options.limit).toArray();
                }
                return await collection.toArray();
            },

            async findByBarcode(locationId, barcode) {
                if (!barcode) return null;
                await PosDb.init();
                const locId = Number(locationId);
                const bc = String(barcode).trim();

                // 1. Exact compound index lookup [location_id+barcode] (< 1ms)
                let prod = await db.product_cache.where('[location_id+barcode]').equals([locId, bc]).first();
                if (prod) return prod;

                // 2. Fast compound index lookup without leading zeros (e.g. '08990001' -> '8990001')
                const cleanBc = bc.replace(/^0+/, '');
                if (cleanBc && cleanBc !== bc) {
                    prod = await db.product_cache.where('[location_id+barcode]').equals([locId, cleanBc]).first();
                    if (prod) return prod;
                }

                // 3. Fast compound index lookup with leading zero padded
                const paddedBc = '0' + bc;
                prod = await db.product_cache.where('[location_id+barcode]').equals([locId, paddedBc]).first();
                if (prod) return prod;

                return null;
            },

            async findBySku(locationId, sku) {
                if (!sku) return null;
                await PosDb.init();
                const locId = Number(locationId);
                const s = String(sku).trim().toUpperCase();

                let prod = await db.product_cache.where('[location_id+sku]').equals([locId, s]).first();
                return prod || null;
            },

            async search(locationId, query, category = 'SEMUA', limit = 100) {
                await PosDb.init();
                const locId = Number(locationId);
                const q = normalizeStr(query);
                const hasCategory = category && normalizeStr(category) !== 'semua';
                const normCat = hasCategory ? normalizeStr(category) : null;

                if (!q && !hasCategory) {
                    return await db.product_cache
                        .where('location_id').equals(locId)
                        .limit(limit)
                        .toArray();
                }

                // If query is digits and looks like a barcode scan (>= 4 digits)
                if (q && /^\d{4,}$/.test(q)) {
                    const exact = await PosDb.products.findByBarcode(locId, q);
                    if (exact) return [exact];
                }

                let items;
                if (hasCategory) {
                    items = await db.product_cache.where('[location_id+category]').equals([locId, normCat]).toArray();
                } else {
                    items = await db.product_cache.where('location_id').equals(locId).toArray();
                }

                if (!q) {
                    return items.slice(0, limit);
                }

                const isNumeric = /^\d+$/.test(q);
                const results = [];
                for (let i = 0; i < items.length; i++) {
                    const p = items[i];
                    const name = normalizeStr(p.name);
                    const barcode = String(p.barcode || '').trim();
                    const sku = normalizeStr(p.sku);

                    if ((isNumeric && barcode.includes(q)) || name.includes(q) || sku.includes(q)) {
                        results.push(p);
                        if (results.length >= limit) break;
                    }
                }

                return results;
            },

            async bulkUpsert(locationId, products) {
                if (!Array.isArray(products) || products.length === 0) return 0;
                await PosDb.init();
                const locId = Number(locationId);

                const records = products.map(p => {
                    const stock = typeof p.stock === 'number' ? p.stock : Number(p.stock || 0);
                    const minStock = typeof p.min_stock === 'number' ? p.min_stock : Number(p.min_stock || 5);
                    const isActive = p.is_active !== undefined ? !!p.is_active : true;

                    let status = p.stock_status || p.status || 'TERSEDIA';
                    if (stock <= 0) {
                        status = 'HABIS';
                    } else if (stock <= minStock) {
                        status = 'STOK_RENDAH';
                    }

                    return {
                        product_id: p.id || p.product_id,
                        location_id: locId,
                        barcode: String(p.barcode || '').trim(),
                        sku: String(p.sku || '').trim().toUpperCase(),
                        name: p.name || 'Produk',
                        category: p.category || 'Umum',
                        unit: p.unit || 'Pcs',
                        price: Number(p.price || 0),
                        member_price: Number(p.member_price || p.memberPrice || p.price || 0),
                        cost_price: Number(p.cost_price || p.costPrice || 0),
                        stock: stock,
                        min_stock: minStock,
                        is_active: isActive,
                        is_in_stock: stock > 0,
                        is_out_of_stock: stock <= 0,
                        stock_status: status,
                        is_near_expired: !!p.is_near_expired,
                        clearance_discount_percent: Number(p.clearance_discount_percent || 0),
                        updated_at: p.updated_at || new Date().toISOString()
                    };
                });

                return await db.transaction('rw', db.product_cache, async () => {
                    // Build map of existing products by product_id in this location
                    const existing = await db.product_cache.where('location_id').equals(locId).toArray();
                    const existingMap = new Map();
                    existing.forEach(item => {
                        if (item.product_id) existingMap.set(String(item.product_id), item.id);
                        if (item.barcode) existingMap.set('bc:' + item.barcode, item.id);
                        if (item.sku) existingMap.set('sku:' + item.sku, item.id);
                    });

                    const toPut = [];
                    for (const rec of records) {
                        let existingAutoId = null;
                        if (rec.product_id && existingMap.has(String(rec.product_id))) {
                            existingAutoId = existingMap.get(String(rec.product_id));
                        } else if (rec.barcode && existingMap.has('bc:' + rec.barcode)) {
                            existingAutoId = existingMap.get('bc:' + rec.barcode);
                        } else if (rec.sku && existingMap.has('sku:' + rec.sku)) {
                            existingAutoId = existingMap.get('sku:' + rec.sku);
                        }

                        if (existingAutoId) {
                            rec.id = existingAutoId;
                        }
                        toPut.push(rec);
                    }

                    await db.product_cache.bulkPut(toPut);
                    return toPut.length;
                });
            },

            async deductStock(locationId, cartItems) {
                if (!Array.isArray(cartItems) || cartItems.length === 0) return;
                await PosDb.init();
                const locId = Number(locationId);

                await db.transaction('rw', db.product_cache, async () => {
                    for (const item of cartItems) {
                        let target = null;
                        const bc = String(item.barcode || '').trim();
                        const sku = String(item.sku || '').trim().toUpperCase();

                        if (bc && bc !== '-') {
                            target = await db.product_cache.where('[location_id+barcode]').equals([locId, bc]).first();
                        }
                        if (!target && sku && sku !== 'SKU-GEN') {
                            target = await db.product_cache.where('[location_id+sku]').equals([locId, sku]).first();
                        }
                        if (!target && item.id) {
                            target = await db.product_cache.where('location_id').equals(locId).filter(p => p.product_id == item.id).first();
                        }

                        if (target && typeof target.stock === 'number') {
                            const newStock = Math.max(0, target.stock - Number(item.qty || 1));
                            target.stock = newStock;
                            target.is_in_stock = newStock > 0;
                            target.is_out_of_stock = newStock <= 0;
                            if (newStock <= 0) {
                                target.stock_status = 'HABIS';
                            } else if (newStock <= (target.min_stock || 5)) {
                                target.stock_status = 'STOK_RENDAH';
                            } else {
                                target.stock_status = 'TERSEDIA';
                            }
                            await db.product_cache.put(target);
                        }
                    }
                });
            },

            async clearLocation(locationId) {
                await PosDb.init();
                const locId = Number(locationId);
                return await db.product_cache.where('location_id').equals(locId).delete();
            },

            async count(locationId) {
                await PosDb.init();
                if (locationId !== undefined && locationId !== null) {
                    return await db.product_cache.where('location_id').equals(Number(locationId)).count();
                }
                return await db.product_cache.count();
            }
        },

        // --- CATEGORY REPOSITORY ---
        categories: {
            async getByLocation(locationId) {
                await PosDb.init();
                const locId = Number(locationId);
                const list = await db.category_cache.where('location_id').equals(locId).toArray();
                return list.map(c => c.name);
            },

            async bulkUpsert(locationId, categories) {
                if (!Array.isArray(categories) || categories.length === 0) return 0;
                await PosDb.init();
                const locId = Number(locationId);

                return await db.transaction('rw', db.category_cache, async () => {
                    const existing = await db.category_cache.where('location_id').equals(locId).toArray();
                    const existingNames = new Set(existing.map(c => normalizeStr(c.name)));

                    const toAdd = [];
                    for (const catName of categories) {
                        const trimmed = String(catName || '').trim();
                        if (trimmed && !existingNames.has(normalizeStr(trimmed))) {
                            toAdd.push({ location_id: locId, name: trimmed });
                            existingNames.add(normalizeStr(trimmed));
                        }
                    }

                    if (toAdd.length > 0) {
                        await db.category_cache.bulkAdd(toAdd);
                    }
                    return toAdd.length;
                });
            }
        },

        // --- TRANSACTION QUEUE REPOSITORY ---
        transactions: {
            async enqueue(trxRecord) {
                await PosDb.init();
                if (!trxRecord.offline_id) {
                    trxRecord.offline_id = 'off_' + generateUuid();
                }
                trxRecord.sync_status = trxRecord.sync_status || 'PENDING_SYNC';
                trxRecord.created_at = trxRecord.created_at || new Date().toISOString();
                trxRecord.retry_count = Number(trxRecord.retry_count || 0);

                await db.transaction('rw', [db.transaction_queue, db.transaction_history], async () => {
                    // Put in sync queue
                    await db.transaction_queue.put(trxRecord);

                    // Also mirror in local history
                    await db.transaction_history.put({
                        offline_id: trxRecord.offline_id,
                        invoice_number: trxRecord.invoice_number,
                        location_id: trxRecord.location_id,
                        terminal_code: trxRecord.terminal_code,
                        created_at: trxRecord.created_at,
                        syncStatus: trxRecord.sync_status,
                        payload: trxRecord
                    });
                });

                return trxRecord;
            },

            async getPending() {
                await PosDb.init();
                const list = await db.transaction_queue.toArray();
                return list.sort((a, b) => {
                    const tA = a && a.created_at ? new Date(a.created_at).getTime() : 0;
                    const tB = b && b.created_at ? new Date(b.created_at).getTime() : 0;
                    return tA - tB;
                });
            },

            async getPendingCount() {
                await PosDb.init();
                return await db.transaction_queue.count();
            },

            async removeSynced(offlineIds) {
                if (!Array.isArray(offlineIds) || offlineIds.length === 0) return 0;
                await PosDb.init();

                return await db.transaction('rw', [db.transaction_queue, db.transaction_history], async () => {
                    await db.transaction_queue.bulkDelete(offlineIds);
                    for (const id of offlineIds) {
                        const hist = await db.transaction_history.get(id);
                        if (hist) {
                            hist.syncStatus = 'SYNCED';
                            if (hist.payload) {
                                hist.payload.syncStatus = 'SYNCED';
                                hist.payload.isOffline = false;
                                hist.payload.is_offline = false;
                            }
                            await db.transaction_history.put(hist);
                        }
                    }
                    return offlineIds.length;
                });
            },

            async markFailed(offlineId, errorMessage) {
                await PosDb.init();
                const item = await db.transaction_queue.get(offlineId);
                if (item) {
                    item.sync_status = 'FAILED';
                    item.retry_count = (item.retry_count || 0) + 1;
                    item.last_error = errorMessage || 'Sync error';
                    await db.transaction_queue.put(item);
                }
            },

            async getHistory(limit = 100) {
                await PosDb.init();
                const list = await db.transaction_history.orderBy('created_at').reverse().limit(limit).toArray();
                return list.map(item => item.payload || item);
            },

            async saveHistory(trxRecord) {
                await PosDb.init();
                const offlineId = trxRecord.offline_id || trxRecord.rawId || trxRecord.invoice_number;
                return await db.transaction_history.put({
                    offline_id: offlineId,
                    invoice_number: trxRecord.invoice_number,
                    location_id: trxRecord.location_id,
                    terminal_code: trxRecord.terminal_code,
                    created_at: trxRecord.created_at || new Date().toISOString(),
                    syncStatus: trxRecord.syncStatus || 'PENDING_SYNC',
                    payload: trxRecord
                });
            },

            async updateHistorySyncStatus(offlineId, serverId = null) {
                await PosDb.init();
                const hist = await db.transaction_history.get(offlineId);
                if (hist) {
                    hist.syncStatus = 'SYNCED';
                    if (hist.payload) {
                        hist.payload.syncStatus = 'SYNCED';
                        hist.payload.isOffline = false;
                        hist.payload.is_offline = false;
                        if (serverId) hist.payload.rawId = serverId;
                    }
                    await db.transaction_history.put(hist);
                }
            }
        },

        // --- SHIFT QUEUE REPOSITORY ---
        shifts: {
            async enqueue(shiftRecord) {
                await PosDb.init();
                if (!shiftRecord.offline_id) {
                    shiftRecord.offline_id = 'shf_' + generateUuid();
                }
                shiftRecord.sync_status = shiftRecord.sync_status || 'PENDING_SYNC';
                shiftRecord.created_at = shiftRecord.created_at || new Date().toISOString();
                shiftRecord.retry_count = Number(shiftRecord.retry_count || 0);

                await db.shift_queue.put(shiftRecord);
                return shiftRecord;
            },

            async getPending() {
                await PosDb.init();
                const list = await db.shift_queue.toArray();
                return list.sort((a, b) => {
                    const tA = a && a.created_at ? new Date(a.created_at).getTime() : 0;
                    const tB = b && b.created_at ? new Date(b.created_at).getTime() : 0;
                    return tA - tB;
                });
            },

            async getPendingCount() {
                await PosDb.init();
                return await db.shift_queue.count();
            },

            async removeSynced(offlineIds) {
                if (!Array.isArray(offlineIds) || offlineIds.length === 0) return 0;
                await PosDb.init();
                await db.shift_queue.bulkDelete(offlineIds);
                return offlineIds.length;
            },

            async markFailed(offlineId, errorMessage) {
                await PosDb.init();
                const item = await db.shift_queue.get(offlineId);
                if (item) {
                    item.sync_status = 'FAILED';
                    item.retry_count = (item.retry_count || 0) + 1;
                    item.last_error = errorMessage || 'Sync error';
                    await db.shift_queue.put(item);
                }
            }
        },

        // --- CASH MOVEMENT QUEUE REPOSITORY ---
        movements: {
            async enqueue(movementRecord) {
                await PosDb.init();
                if (!movementRecord.offline_id) {
                    movementRecord.offline_id = 'mov_' + generateUuid();
                }
                movementRecord.sync_status = movementRecord.sync_status || 'PENDING_SYNC';
                movementRecord.created_at = movementRecord.created_at || new Date().toISOString();
                movementRecord.retry_count = Number(movementRecord.retry_count || 0);

                await db.cash_movement_queue.put(movementRecord);
                return movementRecord;
            },

            async getPending() {
                await PosDb.init();
                const list = await db.cash_movement_queue.toArray();
                return list.sort((a, b) => {
                    const tA = a && a.created_at ? new Date(a.created_at).getTime() : 0;
                    const tB = b && b.created_at ? new Date(b.created_at).getTime() : 0;
                    return tA - tB;
                });
            },

            async getPendingCount() {
                await PosDb.init();
                return await db.cash_movement_queue.count();
            },

            async removeSynced(offlineIds) {
                if (!Array.isArray(offlineIds) || offlineIds.length === 0) return 0;
                await PosDb.init();
                await db.cash_movement_queue.bulkDelete(offlineIds);
                return offlineIds.length;
            },

            async markFailed(offlineId, errorMessage) {
                await PosDb.init();
                const item = await db.cash_movement_queue.get(offlineId);
                if (item) {
                    item.sync_status = 'FAILED';
                    item.retry_count = (item.retry_count || 0) + 1;
                    item.last_error = errorMessage || 'Sync error';
                    await db.cash_movement_queue.put(item);
                }
            }
        },

        // --- SYNC AUDIT LOGS REPOSITORY ---
        logs: {
            async addLog(entity, offlineId, status, errorMessage = null) {
                try {
                    await PosDb.init();
                    await db.sync_logs.add({
                        entity: entity,
                        offline_id: offlineId,
                        status: status,
                        error_message: errorMessage,
                        timestamp: new Date().toISOString()
                    });
                } catch (e) {
                    console.warn('[PosDb] Failed to append sync log:', e);
                }
            },

            async getRecentLogs(limit = 50) {
                await PosDb.init();
                return await db.sync_logs.orderBy('id').reverse().limit(limit).toArray();
            }
        },

        // --- METADATA REPOSITORY ---
        meta: {
            async get(key, defaultVal = null) {
                await PosDb.init();
                const record = await db.sync_metadata.get(key);
                return record ? record.meta : defaultVal;
            },

            async set(key, metaVal, version = 1) {
                await PosDb.init();
                await db.sync_metadata.put({
                    key: key,
                    meta: metaVal,
                    version: version,
                    last_synced_at: new Date().toISOString()
                });
            }
        },

        // --- LOCATION METADATA REPOSITORY ---
        locations: {
            async getAll() {
                await PosDb.init();
                return await db.location_metadata.toArray();
            },

            async getById(locationId) {
                await PosDb.init();
                return await db.location_metadata.get(Number(locationId));
            },

            async save(loc) {
                await PosDb.init();
                return await db.location_metadata.put({
                    location_id: Number(loc.id || loc.location_id),
                    code: loc.code || '',
                    name: loc.name || '',
                    type: loc.type || 'STORE',
                    is_active: loc.is_active !== undefined ? !!loc.is_active : true,
                    cached_at: new Date().toISOString()
                });
            }
        }
    };

    // Attach to global scope (window in browser, globalThis in Node/Workers)
    global.PosDb = PosDb;
    if (typeof window !== 'undefined') {
        window.PosDb = PosDb;
        // Warm up IndexedDB connection non-blockingly
        PosDb.init().catch(err => {
            console.warn('[PosDb] Non-fatal warm up notice:', err && err.message);
        });
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = PosDb;
    }
})(typeof window !== 'undefined' ? window : globalThis);
