/**
 * Comprehensive Dexie.js (IndexedDB) Test Suite for POS ILP
 * Tests Schema, Capacity (10,000 items), Location Isolation,
 * Queue Idempotency, and LocalStorage Migration with Rollback.
 */

const path = require('path');
const { indexedDB, IDBKeyRange } = require('fake-indexeddb');
globalThis.indexedDB = indexedDB;
globalThis.IDBKeyRange = IDBKeyRange;

const Dexie = require('dexie');
globalThis.Dexie = Dexie;

// Mock localStorage
const mockStorage = new Map();
globalThis.localStorage = {
    getItem: (k) => mockStorage.has(k) ? mockStorage.get(k) : null,
    setItem: (k, v) => mockStorage.set(k, String(v)),
    removeItem: (k) => mockStorage.delete(k),
    clear: () => mockStorage.clear(),
    get length() { return mockStorage.size; },
    key: (i) => Array.from(mockStorage.keys())[i] || null
};

// Load PosDb & PosMigration from public/js/db
require(path.resolve(__dirname, '../../public/js/db/pos-db.js'));
require(path.resolve(__dirname, '../../public/js/db/pos-migration.js'));
const PosDb = globalThis.PosDb;
const PosMigration = globalThis.PosMigration;

let passedTests = 0;
let failedTests = 0;

function assert(condition, testName) {
    if (condition) {
        console.log(`  [PASS] ${testName}`);
        passedTests++;
    } else {
        console.error(`  [FAIL] ${testName}`);
        failedTests++;
    }
}

async function runAllTests() {
    console.log('====================================================');
    console.log('STARTING DEXIE.JS & INDEXEDDB OFFLINE STORAGE TESTS');
    console.log('====================================================\n');

    // TEST 1: Database Initialization & Tables
    console.log('TEST 1: Schema & Store Verification');
    await PosDb.init();
    assert(PosDb.isReady === true, 'PosDb initialized and connected to PosIlpOfflineDB');
    assert(PosDb.db.tables.some(t => t.name === 'product_cache'), 'Table product_cache exists');
    assert(PosDb.db.tables.some(t => t.name === 'category_cache'), 'Table category_cache exists');
    assert(PosDb.db.tables.some(t => t.name === 'transaction_queue'), 'Table transaction_queue exists');
    assert(PosDb.db.tables.some(t => t.name === 'transaction_history'), 'Table transaction_history exists');
    assert(PosDb.db.tables.some(t => t.name === 'shift_queue'), 'Table shift_queue exists');
    assert(PosDb.db.tables.some(t => t.name === 'cash_movement_queue'), 'Table cash_movement_queue exists');
    assert(PosDb.db.tables.some(t => t.name === 'sync_logs'), 'Table sync_logs exists');
    assert(PosDb.db.tables.some(t => t.name === 'sync_metadata'), 'Table sync_metadata exists');
    assert(PosDb.db.tables.some(t => t.name === 'location_metadata'), 'Table location_metadata exists');

    // TEST 2: High-Capacity Catalog Storage (10,000 Products)
    console.log('\nTEST 2: High-Capacity Catalog Benchmark (10,000 items)');
    const sampleCategories = ['Makanan', 'Minuman', 'Sembako', 'Kebersihan', 'Kesehatan'];
    const prodsLoc1 = [];
    const prodsLoc2 = [];

    for (let i = 1; i <= 5000; i++) {
        prodsLoc1.push({
            id: i,
            product_id: i,
            barcode: `89912345${String(i).padStart(5, '0')}`,
            sku: `SKU-PST-${String(i).padStart(5, '0')}`,
            name: `Barang Pusat No. ${i}`,
            category: sampleCategories[i % sampleCategories.length],
            unit: 'Pcs',
            price: 5000 + (i * 10),
            member_price: 4500 + (i * 10),
            stock: 20 + (i % 100),
            min_stock: 5,
            is_active: true
        });
    }

    for (let i = 1; i <= 5000; i++) {
        const prodId = 5000 + i;
        prodsLoc2.push({
            id: prodId,
            product_id: prodId,
            barcode: `89998765${String(i).padStart(5, '0')}`,
            sku: `SKU-CBG-${String(i).padStart(5, '0')}`,
            name: `Barang Cabang No. ${i}`,
            category: sampleCategories[i % sampleCategories.length],
            unit: 'Pcs',
            price: 6000 + (i * 10),
            member_price: 5500 + (i * 10),
            stock: 15 + (i % 50),
            min_stock: 5,
            is_active: true
        });
    }

    const startInsert = Date.now();
    await PosDb.products.bulkUpsert(1, prodsLoc1);
    await PosDb.products.bulkUpsert(2, prodsLoc2);
    const insertDuration = Date.now() - startInsert;
    console.log(`  -> Inserted 10,000 items across 2 locations in ${insertDuration}ms`);

    const totalCount = await PosDb.products.count();
    const loc1Count = await PosDb.products.count(1);
    const loc2Count = await PosDb.products.count(2);
    assert(totalCount === 10000, `Total items in Dexie is 10,000 (actual: ${totalCount})`);
    assert(loc1Count === 5000, `Location 1 has exactly 5,000 items`);
    assert(loc2Count === 5000, `Location 2 has exactly 5,000 items`);

    // TEST 3: Compound Index Lookup & Location Isolation
    console.log('\nTEST 3: Compound Index Performance & Isolation');
    const startLookup = Date.now();
    const foundProd = await PosDb.products.findByBarcode(1, '8991234500123');
    const lookupDuration = Date.now() - startLookup;
    console.log(`  -> Exact compound barcode lookup took ${lookupDuration}ms`);

    assert(foundProd !== null, 'Product found by compound [location_id+barcode]');
    assert(foundProd && foundProd.sku === 'SKU-PST-00123', 'SKU matches SKU-PST-00123');
    assert(foundProd && foundProd.location_id === 1, 'Location ID strictly isolated to 1');

    // Check isolation: barcode of Location 2 must return null when queried with Location 1
    const crossCheck = await PosDb.products.findByBarcode(1, '8999876500123');
    assert(crossCheck === null, 'Barcode from Store 2 returns NULL when queried from Store 1');

    // SKU compound lookup
    const foundSku = await PosDb.products.findBySku(2, 'SKU-CBG-00050');
    assert(foundSku !== null && foundSku.location_id === 2, 'SKU found in Store 2 correctly');

    // Fast search with category
    const searchRes = await PosDb.products.search(1, 'No. 100', 'Semua', 10);
    assert(searchRes.length > 0, `Search found ${searchRes.length} items matching "No. 100"`);
    assert(searchRes.every(p => p.location_id === 1), 'All search results belong to location 1');

    // TEST 4: Stock Deduction & Status Updates
    console.log('\nTEST 4: Offline Stock Deduction');
    const targetItem = await PosDb.products.findByBarcode(1, '8991234500001');
    const initialStock = targetItem.stock;
    await PosDb.products.deductStock(1, [
        { barcode: '8991234500001', qty: initialStock } // Buy all remaining stock
    ]);
    const updatedTarget = await PosDb.products.findByBarcode(1, '8991234500001');
    assert(updatedTarget.stock === 0, 'Stock reduced to 0');
    assert(updatedTarget.stock_status === 'HABIS', 'Stock status updated to HABIS');
    assert(updatedTarget.is_out_of_stock === true, 'is_out_of_stock is true');

    // TEST 5: Offline Transaction Queue & Idempotency
    console.log('\nTEST 5: Offline Transaction Queue & Idempotency');
    const txId1 = 'off_test_uuid_0001';
    const txId2 = 'off_test_uuid_0002';

    await PosDb.transactions.enqueue({
        offline_id: txId1,
        invoice_number: 'TRX-OFF-20261118-POS01-1001',
        terminal_code: 'POS-01',
        location_id: 1,
        cashier_nik: 'KASIR01',
        payment_method: 'TUNAI',
        grand_total: 75000,
        items: [{ barcode: '8991234500002', qty: 1, price: 75000 }]
    });

    await PosDb.transactions.enqueue({
        offline_id: txId2,
        invoice_number: 'TRX-OFF-20261118-POS01-1002',
        terminal_code: 'POS-01',
        location_id: 1,
        cashier_nik: 'KASIR01',
        payment_method: 'QRIS',
        grand_total: 50000,
        items: [{ barcode: '8991234500003', qty: 1, price: 50000 }]
    });

    let pendingTxs = await PosDb.transactions.getPending();
    assert(pendingTxs.length === 2, `2 transactions queued in Dexie (actual: ${pendingTxs.length})`);

    // Test duplicate enqueue (idempotency by offline_id primary key)
    await PosDb.transactions.enqueue({
        offline_id: txId1, // Same ID
        invoice_number: 'TRX-OFF-20261118-POS01-1001',
        terminal_code: 'POS-01',
        location_id: 1,
        grand_total: 75000
    });
    pendingTxs = await PosDb.transactions.getPending();
    assert(pendingTxs.length === 2, 'Duplicate enqueue with identical offline_id does not create duplicate record');

    // Partial sync confirmation
    await PosDb.transactions.removeSynced([txId1]);
    pendingTxs = await PosDb.transactions.getPending();
    assert(pendingTxs.length === 1, `After syncing txId1, exactly 1 transaction remains in queue`);
    assert(pendingTxs[0].offline_id === txId2, 'Remaining transaction is txId2');

    // Mark failed with error
    await PosDb.transactions.markFailed(txId2, 'Koneksi timeout ke server');
    const failedTx = (await PosDb.transactions.getPending())[0];
    assert(failedTx.sync_status === 'FAILED', 'Transaction status marked as FAILED');
    assert(failedTx.retry_count === 1, 'retry_count incremented to 1');
    assert(failedTx.last_error === 'Koneksi timeout ke server', 'Error message recorded accurately');

    // TEST 6: Shifts & Movements Queue
    console.log('\nTEST 6: Shifts & Cash Movements Queue');
    const shfOpenId = 'shf_open_test_001';
    await PosDb.shifts.enqueue({
        offline_id: shfOpenId,
        action: 'OPEN',
        shift_code: 'CS-20261118-01',
        terminal_code: 'POS-01',
        location_id: 1,
        opening_cash: 500000
    });

    const movId = 'mov_test_001';
    await PosDb.movements.enqueue({
        offline_id: movId,
        shift_code: 'CS-20261118-01',
        terminal_code: 'POS-01',
        location_id: 1,
        type: 'OUT',
        amount: 25000,
        reason: 'Beli Lakban POS'
    });

    const shfCloseId = 'shf_close_test_001';
    await PosDb.shifts.enqueue({
        offline_id: shfCloseId,
        action: 'CLOSE',
        shift_code: 'CS-20261118-01',
        terminal_code: 'POS-01',
        location_id: 1,
        actual_cash: 550000
    });

    const pendingShifts = await PosDb.shifts.getPending();
    const pendingMovs = await PosDb.movements.getPending();
    assert(pendingShifts.length === 2, `2 shift actions queued (OPEN & CLOSE)`);
    assert(pendingMovs.length === 1, `1 cash movement queued`);

    // Clean up queues
    await PosDb.shifts.removeSynced([shfOpenId, shfCloseId]);
    await PosDb.movements.removeSynced([movId]);
    await PosDb.transactions.removeSynced([txId2]);

    // TEST 7: Safe LocalStorage to Dexie Migration
    console.log('\nTEST 7: Safe LocalStorage to Dexie Migration');
    // Prepare mock localStorage with legacy items
    const legacyTrxs = [
        { offline_id: 'off_mig_01', invoice_number: 'TRX-OFF-MIG-1', grand_total: 10000 },
        { offline_id: 'off_mig_02', invoice_number: 'TRX-OFF-MIG-2', grand_total: 20000 }
    ];
    const legacyShifts = [
        { offline_id: 'shf_mig_01', action: 'OPEN', opening_cash: 200000 }
    ];
    const legacyMovs = [
        { offline_id: 'mov_mig_01', type: 'IN', amount: 50000 }
    ];

    mockStorage.set('pos_ilp_offline_transactions_v1', JSON.stringify(legacyTrxs));
    mockStorage.set('pos_ilp_offline_shifts_v1', JSON.stringify(legacyShifts));
    mockStorage.set('pos_ilp_offline_movements_v1', JSON.stringify(legacyMovs));

    const migResult = await PosMigration.runMigration(true);
    assert(migResult.success === true, 'Migration returned success: true');
    assert(mockStorage.get('pos_ilp_dexie_migrated_v2') === 'true', 'Migration flag set in localStorage');

    const migQueueCount = await PosDb.transactions.getPendingCount();
    const migShiftCount = await PosDb.shifts.getPendingCount();
    const migMovCount = await PosDb.movements.getPendingCount();
    assert(migQueueCount === 2, `Dexie queue received 2 migrated transactions (actual: ${migQueueCount})`);
    assert(migShiftCount === 1, `Dexie shift received 1 migrated shift (actual: ${migShiftCount})`);
    assert(migMovCount === 1, `Dexie movement received 1 migrated movement (actual: ${migMovCount})`);

    // Verify Idempotency: Running migration again skips safely
    const reMigResult = await PosMigration.runMigration(false);
    assert(reMigResult.skipped === true, 'Subsequent migration run skipped without re-inserting or duplicating');
    const finalQueueCount = await PosDb.transactions.getPendingCount();
    assert(finalQueueCount === 2, 'Queue count remains exactly 2 after idempotent re-run');

    console.log('\n====================================================');
    console.log(`DEXIE TEST SUITE FINISHED: ${passedTests} PASSED, ${failedTests} FAILED`);
    console.log('====================================================');

    if (failedTests > 0) {
        process.exit(1);
    }
}

runAllTests().catch(err => {
    console.error('Fatal test error:', err);
    process.exit(1);
});
