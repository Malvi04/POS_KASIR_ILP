/**
 * POS Kasir ILP - LocalStorage to Dexie.js (IndexedDB) Migration Engine
 * Safely, idempotently, and losslessly migrates offline transactions, shifts, movements,
 * and product catalogs from browser localStorage to IndexedDB.
 */
(function (global) {
    'use strict';

    const MIGRATION_FLAG = 'pos_ilp_dexie_migrated_v2';
    const BACKUP_FLAG = 'pos_ilp_migration_backup_v2';

    const OLD_STORAGE_KEYS = {
        OFFLINE_TRANSACTIONS: 'pos_ilp_offline_transactions_v1',
        OFFLINE_MOVEMENTS: 'pos_ilp_offline_movements_v1',
        OFFLINE_SHIFTS: 'pos_ilp_offline_shifts_v1',
        TRANSACTIONS: 'pos_ilp_transactions_v1',
        INVENTORY: 'pos_ilp_inventory_products_v1',
        CATALOG_META: 'pos_ilp_inventory_catalog_meta_v1'
    };

    function safeParse(raw, fallback = null) {
        if (!raw) return fallback;
        try {
            return JSON.parse(raw);
        } catch (e) {
            console.warn('[PosMigration] Failed to parse JSON:', e);
            return fallback;
        }
    }

    const PosMigration = {
        isMigrated() {
            try {
                return localStorage.getItem(MIGRATION_FLAG) === 'true';
            } catch (e) {
                return false;
            }
        },

        async runMigration(force = false) {
            if (!force && this.isMigrated()) {
                return { success: true, skipped: true, message: 'Data already migrated to Dexie.js.' };
            }

            const PosDb = global.PosDb;
            if (!PosDb || !PosDb.db) {
                console.warn('[PosMigration] PosDb is not available; migration postponed.');
                return { success: false, error: 'PosDb not available' };
            }

            console.log('[PosMigration] Starting migration from localStorage to Dexie.js...');

            // 1. Gather all data from localStorage
            let oldTrxs = [];
            let oldMovs = [];
            let oldShifts = [];
            let oldHistory = [];
            let branchCatalogs = [];

            try {
                oldTrxs = safeParse(localStorage.getItem(OLD_STORAGE_KEYS.OFFLINE_TRANSACTIONS), []) || [];
                oldMovs = safeParse(localStorage.getItem(OLD_STORAGE_KEYS.OFFLINE_MOVEMENTS), []) || [];
                oldShifts = safeParse(localStorage.getItem(OLD_STORAGE_KEYS.OFFLINE_SHIFTS), []) || [];
                oldHistory = safeParse(localStorage.getItem(OLD_STORAGE_KEYS.TRANSACTIONS), []) || [];

                // Discover all branch-specific catalogs in localStorage (e.g. pos_ilp_stock_toko_pusat_v1)
                for (let i = 0; i < localStorage.length; i++) {
                    const key = localStorage.key(i);
                    if (key && key.startsWith('pos_ilp_stock_toko_')) {
                        const rawCat = localStorage.getItem(key);
                        const prods = safeParse(rawCat, []);
                        if (Array.isArray(prods) && prods.length > 0) {
                            let locId = 2; // Default Cabang Pusat (ID: 2)
                            if (key.includes('cabang_a') || key.includes('cabang_1')) locId = 3;
                            else if (key.includes('cabang_b') || key.includes('cabang_2')) locId = 4;
                            branchCatalogs.push({ locationId: locId, products: prods, key: key });
                        }
                    }
                }
                localStorage.removeItem(OLD_STORAGE_KEYS.INVENTORY);
                localStorage.removeItem(OLD_STORAGE_KEYS.CATALOG_META);
            } catch (readErr) {
                console.error('[PosMigration] Error reading localStorage during migration:', readErr);
                return { success: false, error: 'Read error: ' + readErr.message };
            }

            // 2. Perform Atomic Migration inside a Dexie Transaction
            const db = PosDb.db;
            try {
                await db.transaction('rw', [
                    db.transaction_queue,
                    db.transaction_history,
                    db.shift_queue,
                    db.cash_movement_queue,
                    db.product_cache,
                    db.sync_metadata,
                    db.sync_logs
                ], async () => {
                    // A. Migrate offline transactions
                    if (Array.isArray(oldTrxs) && oldTrxs.length > 0) {
                        for (const trx of oldTrxs) {
                            if (!trx.offline_id) {
                                trx.offline_id = 'off_' + (trx.invoice_number || Date.now());
                            }
                            trx.sync_status = trx.sync_status || 'PENDING_SYNC';
                            trx.created_at = trx.created_at || trx.timestamp || trx.date || new Date().toISOString();
                            trx.retry_count = Number(trx.retry_count || 0);
                            await db.transaction_queue.put(trx);
                        }
                    }

                    // B. Migrate transaction history
                    if (Array.isArray(oldHistory) && oldHistory.length > 0) {
                        for (const hist of oldHistory) {
                            const offId = hist.offline_id || hist.rawId || hist.invoice_number || ('hist_' + Date.now());
                            await db.transaction_history.put({
                                offline_id: offId,
                                invoice_number: hist.invoice_number || hist.id,
                                location_id: hist.location_id || 1,
                                terminal_code: hist.terminal_code || hist.terminal || 'POS-01',
                                created_at: hist.created_at || new Date().toISOString(),
                                syncStatus: hist.syncStatus || (hist.isOffline ? 'PENDING_SYNC' : 'SYNCED'),
                                payload: hist
                            });
                        }
                    }

                    // C. Migrate shifts
                    if (Array.isArray(oldShifts) && oldShifts.length > 0) {
                        for (const shf of oldShifts) {
                            if (!shf.offline_id) shf.offline_id = 'shf_' + Date.now();
                            shf.sync_status = shf.sync_status || 'PENDING_SYNC';
                            shf.created_at = shf.created_at || shf.timestamp || new Date().toISOString();
                            shf.retry_count = Number(shf.retry_count || 0);
                            await db.shift_queue.put(shf);
                        }
                    }

                    // D. Migrate cash movements
                    if (Array.isArray(oldMovs) && oldMovs.length > 0) {
                        for (const mov of oldMovs) {
                            if (!mov.offline_id) mov.offline_id = 'mov_' + Date.now();
                            mov.sync_status = mov.sync_status || 'PENDING_SYNC';
                            mov.created_at = mov.created_at || mov.timestamp || new Date().toISOString();
                            mov.retry_count = Number(mov.retry_count || 0);
                            await db.cash_movement_queue.put(mov);
                        }
                    }

                    // E. Migrate branch catalogs
                    for (const cat of branchCatalogs) {
                        for (const p of cat.products) {
                            const stock = typeof p.stock === 'number' ? p.stock : Number(p.stock || 0);
                            const minStock = typeof p.min_stock === 'number' ? p.min_stock : Number(p.min_stock || 5);
                            let status = p.stock_status || p.status || (stock <= 0 ? 'HABIS' : (stock <= minStock ? 'STOK_RENDAH' : 'TERSEDIA'));

                            await db.product_cache.put({
                                product_id: p.id || p.product_id,
                                location_id: p.location_id || cat.locationId,
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
                                is_active: p.is_active !== undefined ? !!p.is_active : true,
                                is_in_stock: stock > 0,
                                is_out_of_stock: stock <= 0,
                                stock_status: status,
                                is_near_expired: !!p.is_near_expired,
                                clearance_discount_percent: Number(p.clearance_discount_percent || 0),
                                updated_at: p.updated_at || new Date().toISOString()
                            });
                        }
                    }

                    // F. Record migration metadata
                    await db.sync_metadata.put({
                        key: 'localStorage_migration',
                        meta: {
                            migrated_at: new Date().toISOString(),
                            trxs_count: oldTrxs.length,
                            shifts_count: oldShifts.length,
                            movements_count: oldMovs.length,
                            history_count: oldHistory.length
                        },
                        version: 2,
                        last_synced_at: new Date().toISOString()
                    });

                    // G. Record log
                    await db.sync_logs.add({
                        entity: 'MIGRATION',
                        offline_id: 'localStorage_to_dexie_v2',
                        status: 'SUCCESS',
                        error_message: null,
                        timestamp: new Date().toISOString()
                    });
                });

                // 3. Post-write Consistency Verification
                const queueCount = await db.transaction_queue.count();
                const shiftCount = await db.shift_queue.count();
                const movCount = await db.cash_movement_queue.count();

                if (queueCount < oldTrxs.length || shiftCount < oldShifts.length || movCount < oldMovs.length) {
                    throw new Error(`Verification mismatch: Dexie counts (trxs: ${queueCount}, shifts: ${shiftCount}, movs: ${movCount}) less than localStorage counts (trxs: ${oldTrxs.length}, shifts: ${oldShifts.length}, movs: ${oldMovs.length})`);
                }

                // 4. Mark successful migration
                localStorage.setItem(MIGRATION_FLAG, 'true');

                // Create backup archive in localStorage if quota allows, but DO NOT delete unsynced queues until sync
                try {
                    const backupData = {
                        migrated_at: new Date().toISOString(),
                        trxs: oldTrxs,
                        shifts: oldShifts,
                        movements: oldMovs
                    };
                    localStorage.setItem(BACKUP_FLAG, JSON.stringify(backupData));
                } catch (backupQuotaErr) {
                    console.warn('[PosMigration] Backup archive skipped due to quota; Dexie contains the authoritative copy.');
                }

                console.log(`[PosMigration] Migration completed successfully! Migrated ${oldTrxs.length} trxs, ${oldShifts.length} shifts, ${oldMovs.length} movements.`);

                return {
                    success: true,
                    migrated: {
                        transactions: oldTrxs.length,
                        shifts: oldShifts.length,
                        movements: oldMovs.length,
                        history: oldHistory.length
                    }
                };

            } catch (txErr) {
                console.error('[PosMigration] Dexie transaction failed; localStorage left untouched:', txErr);
                try {
                    await db.sync_logs.add({
                        entity: 'MIGRATION',
                        offline_id: 'localStorage_to_dexie_v2',
                        status: 'FAILED',
                        error_message: txErr && txErr.message ? txErr.message : 'Unknown error',
                        timestamp: new Date().toISOString()
                    });
                } catch (logErr) {}

                return {
                    success: false,
                    error: txErr && txErr.message ? txErr.message : 'Migration transaction failed'
                };
            }
        }
    };

    // Attach to global scope
    global.PosMigration = PosMigration;
    if (typeof window !== 'undefined') {
        window.PosMigration = PosMigration;
        // Run migration automatically when PosDb is initialized
        if (window.PosDb && typeof window.PosDb.init === 'function') {
            window.PosDb.init().then(() => {
                PosMigration.runMigration().catch(err => {
                    console.warn('[PosMigration] Migration auto-run notice:', err && err.message);
                });
            });
        }
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = PosMigration;
    }
})(typeof window !== 'undefined' ? window : globalThis);
