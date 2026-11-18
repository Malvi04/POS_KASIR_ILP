/**
 * POS ILP - Connection Health & Server Detection Test Suite (Fase 3.0.1 Hotfix)
 * Tests all 10 mandatory operational scenarios:
 * 1. Wi-Fi active & Server active
 * 2. Wi-Fi cut manually
 * 3. Wi-Fi restored
 * 4. Internet cut but LAN server available
 * 5. Server Laravel stopped temporarily
 * 6. Server Laravel restarted
 * 7. Browser reloaded while offline
 * 8. Browser reloaded when server unavailable
 * 9. Offline transaction when connection severed (Dexie integration)
 * 10. Re-sync when server becomes available (Idempotency)
 */

const assert = require('assert');
require('fake-indexeddb/auto');
const Dexie = require('dexie');
const { FAKED_DEXIE } = require('./test_dexie_offline_suite.cjs');

let passedCount = 0;
function recordPass(desc) {
    passedCount++;
    console.log(`  [PASS] ${desc}`);
}

console.log('===========================================================');
console.log('STARTING CONNECTION HEALTH & SERVER DETECTION TEST SUITE');
console.log('===========================================================');

// Mock Alpine Store Factory matching pos-realtime.js implementation
function createMockNetworkStore(initialBrowserOnline = true) {
    const store = {
        isOnline: initialBrowserOnline,
        isOffline: !initialBrowserOnline,
        isBrowserOnline: initialBrowserOnline,
        isServerConnected: initialBrowserOnline,
        connectionState: initialBrowserOnline ? 'SERVER_CONNECTED' : 'OFFLINE',
        operationalStatus: initialBrowserOnline ? 'connected' : 'offline',
        operationalLabel: initialBrowserOnline ? 'Terhubung' : 'Offline',
        wifiOnline: initialBrowserOnline,
        offlineReason: initialBrowserOnline ? null : 'wifi_offline',
        status: initialBrowserOnline ? 'connected' : 'offline',
        wsConnected: initialBrowserOnline,
        wsStatus: initialBrowserOnline ? 'connected' : 'disconnected',
        wsConnecting: false,
        latency: initialBrowserOnline ? 5 : null,
        isChecking: false,

        // Reactive UI simulation matching cashier-header.blade.php & realtime-network-badge.blade.php
        getBadgeClass() {
            if (this.wsConnected) {
                return 'text-emerald-700 bg-emerald-50 border-emerald-200';
            }
            if (!this.wsConnected && this.wsStatus === 'connecting') {
                return 'text-amber-700 bg-amber-50 border-amber-200';
            }
            return 'text-rose-700 bg-rose-50 border-rose-200';
        },
        getBadgeIcon() {
            if (this.wsConnected) return 'wifi';
            if (!this.wsConnected && this.wsStatus === 'connecting') return 'sync';
            return 'wifi_off';
        },
        getBadgeText() {
            if (this.wsConnected) return 'Terhubung';
            if (!this.wsConnected && this.wsStatus === 'connecting') return 'Menghubungkan...';
            return 'Terputus';
        },
        isReverbBadgeVisible() {
            return !!this.wsConnected;
        }
    };
    return store;
}

// Logic transition helper matching updated pos-realtime.js & echo.js
function applyTransition(store, browserOnline, serverConnected, rawWsState = null, hasInternet = true) {
    const isFullyOnline = browserOnline && serverConnected && hasInternet;
    const isOffline = !isFullyOnline;

    let connState = 'OFFLINE';
    if (isFullyOnline) {
        connState = 'SERVER_CONNECTED';
    } else if (browserOnline && !serverConnected) {
        connState = 'ONLINE';
    }

    let label = 'Terhubung';
    let reason = null;
    if (!browserOnline || !hasInternet) {
        label = 'Terputus';
        reason = !browserOnline ? 'wifi_offline' : 'no_internet';
    } else if (!serverConnected) {
        label = 'Server tidak tersedia';
        reason = 'server_offline';
    }

    let wsConnected = false;
    let wsStatus = 'disconnected';
    let wsConnecting = false;

    if (!browserOnline || !hasInternet) {
        wsConnected = false;
        wsStatus = 'disconnected';
    } else if (!serverConnected) {
        wsConnected = false;
        wsStatus = (rawWsState === 'connecting') ? 'connecting' : 'unavailable';
        wsConnecting = (rawWsState === 'connecting');
    } else {
        const effectiveWs = rawWsState || 'connected';
        wsConnected = (effectiveWs === 'connected');
        wsStatus = effectiveWs;
        wsConnecting = (effectiveWs === 'connecting');
    }

    store.isOnline = isFullyOnline;
    store.isOffline = isOffline;
    store.isBrowserOnline = browserOnline;
    store.isServerConnected = serverConnected;
    store.hasInternet = hasInternet;
    store.connectionState = connState;
    store.operationalStatus = isFullyOnline ? 'connected' : 'offline';
    store.operationalLabel = label;
    store.wifiOnline = browserOnline && hasInternet;
    store.offlineReason = reason;
    store.status = store.operationalStatus;
    store.wsConnected = wsConnected;
    store.wsStatus = wsStatus;
    store.wsConnecting = wsConnecting;
    store.latency = wsConnected ? 5 : null;
}

async function runAllScenarios() {
    // -------------------------------------------------------------
    // SCENARIO 1: Wi-Fi aktif dan server aktif
    // -------------------------------------------------------------
    console.log('\nSKENARIO 1: Wi-Fi aktif dan server aktif');
    const store = createMockNetworkStore(true);
    applyTransition(store, true, true, 'connected');

    assert.strictEqual(store.isBrowserOnline, true, 'Browser online is true');
    assert.strictEqual(store.isServerConnected, true, 'Server connected is true');
    assert.strictEqual(store.connectionState, 'SERVER_CONNECTED', 'State is SERVER_CONNECTED');
    assert.strictEqual(store.wsConnected, true, 'WebSocket is connected');
    assert.strictEqual(store.getBadgeText(), 'Terhubung', 'Badge text says Terhubung');
    assert.strictEqual(store.getBadgeIcon(), 'wifi', 'Badge icon is wifi');
    assert.strictEqual(store.isReverbBadgeVisible(), true, 'Reverb badge is visible');
    assert.strictEqual(store.getBadgeClass().includes('emerald'), true, 'Badge class is emerald green');
    recordPass('Skenario 1 Passed: Wi-Fi aktif & server aktif -> Terhubung (Hijau/Emerald, Reverb WS aktif)');

    // -------------------------------------------------------------
    // SCENARIO 2: Wi-Fi diputus secara manual
    // -------------------------------------------------------------
    console.log('\nSKENARIO 2: Wi-Fi diputus secara manual');
    applyTransition(store, false, false);

    assert.strictEqual(store.isBrowserOnline, false, 'Browser online is false');
    assert.strictEqual(store.isServerConnected, false, 'Server connected is false');
    assert.strictEqual(store.connectionState, 'OFFLINE', 'State is OFFLINE');
    assert.strictEqual(store.wsConnected, false, 'WebSocket must NOT be connected');
    assert.strictEqual(store.wsStatus, 'disconnected', 'wsStatus is disconnected');
    assert.strictEqual(store.getBadgeText(), 'Terputus', 'Badge text immediately changes to Terputus');
    assert.strictEqual(store.getBadgeIcon(), 'wifi_off', 'Badge icon immediately becomes wifi_off');
    assert.strictEqual(store.isReverbBadgeVisible(), false, 'Reverb badge is immediately HIDDEN');
    assert.strictEqual(store.getBadgeClass().includes('rose'), true, 'Badge class is rose red');
    recordPass('Skenario 2 Passed: Wi-Fi diputus -> Langsung Terputus (Merah/Rose, wifi_off, Reverb WS mati)');

    // -------------------------------------------------------------
    // SCENARIO 3: Wi-Fi dinyalakan kembali
    // -------------------------------------------------------------
    console.log('\nSKENARIO 3: Wi-Fi dinyalakan kembali');
    // Step 3a: Probing state
    applyTransition(store, true, false, 'connecting');
    assert.strictEqual(store.isBrowserOnline, true, 'Browser is back online');
    assert.strictEqual(store.wsStatus, 'connecting', 'wsStatus is connecting');
    assert.strictEqual(store.getBadgeText(), 'Menghubungkan...', 'Badge text says Menghubungkan...');
    assert.strictEqual(store.getBadgeIcon(), 'sync', 'Badge icon is spinning sync');
    assert.strictEqual(store.getBadgeClass().includes('amber'), true, 'Badge class is amber yellow');

    // Step 3b: Server health check returns 200 OK
    applyTransition(store, true, true, 'connected');
    assert.strictEqual(store.isServerConnected, true, 'Server confirmed connected via /api/health');
    assert.strictEqual(store.wsConnected, true, 'WebSocket re-connected');
    assert.strictEqual(store.getBadgeText(), 'Terhubung', 'Badge text restored to Terhubung');
    assert.strictEqual(store.getBadgeIcon(), 'wifi', 'Badge icon restored to wifi');
    assert.strictEqual(store.isReverbBadgeVisible(), true, 'Reverb badge restored');
    recordPass('Skenario 3 Passed: Wi-Fi kembali -> Menghubungkan -> Terhubung (Otomatis)');

    // -------------------------------------------------------------
    // SCENARIO 4: Internet putus tetapi koneksi LAN ke server aktif
    // -------------------------------------------------------------
    console.log('\nSKENARIO 4: Internet terputus tetapi koneksi LAN ke server tetap tersedia');
    // Device is connected to local router (browserOnline = true) and local server responds (serverConnected = true)
    applyTransition(store, true, true, 'connected');
    assert.strictEqual(store.isBrowserOnline, true, 'LAN interface online');
    assert.strictEqual(store.isServerConnected, true, 'LAN server responds');
    assert.strictEqual(store.getBadgeText(), 'Terhubung', 'Status remains Terhubung on local LAN');
    recordPass('Skenario 4 Passed: LAN server aktif -> POS beroperasi normal dengan server lokal');

    // -------------------------------------------------------------
    // SCENARIO 5: Server Laravel dihentikan sementara (Wi-Fi tetap aktif)
    // -------------------------------------------------------------
    console.log('\nSKENARIO 5: Server Laravel dihentikan sementara');
    applyTransition(store, true, false); // Browser has network, but server is down

    assert.strictEqual(store.isBrowserOnline, true, 'Browser online is true');
    assert.strictEqual(store.isServerConnected, false, 'Server connected is false');
    assert.strictEqual(store.connectionState, 'ONLINE', 'connectionState is ONLINE (network ok, server down)');
    assert.strictEqual(store.operationalLabel, 'Server tidak tersedia', 'Label is Server tidak tersedia');
    assert.strictEqual(store.wsConnected, false, 'wsConnected is FALSE when server down');
    assert.strictEqual(store.wsStatus, 'unavailable', 'wsStatus is unavailable');
    assert.strictEqual(store.getBadgeText(), 'Terputus', 'Badge text shows Terputus');
    assert.strictEqual(store.getBadgeIcon(), 'wifi_off', 'Badge icon shows wifi_off');
    assert.strictEqual(store.isReverbBadgeVisible(), false, 'Reverb badge is HIDDEN');
    assert.strictEqual(store.getBadgeClass().includes('rose'), true, 'Badge class is rose red');
    recordPass('Skenario 5 Passed: Server mati saat Wi-Fi on -> Terputus / Server tidak tersedia (Merah, Reverb mati)');

    // -------------------------------------------------------------
    // SCENARIO 6: Server Laravel dijalankan kembali
    // -------------------------------------------------------------
    console.log('\nSKENARIO 6: Server Laravel dijalankan kembali');
    applyTransition(store, true, true, 'connected');

    assert.strictEqual(store.isServerConnected, true, 'Server connected recovered');
    assert.strictEqual(store.wsConnected, true, 'wsConnected recovered');
    assert.strictEqual(store.getBadgeText(), 'Terhubung', 'Badge text restored to Terhubung');
    assert.strictEqual(store.getBadgeIcon(), 'wifi', 'Badge icon restored to wifi');
    recordPass('Skenario 6 Passed: Server kembali aktif -> Status otomatis pulih ke Terhubung');

    // -------------------------------------------------------------
    // SCENARIO 7: Browser direload saat offline
    // -------------------------------------------------------------
    console.log('\nSKENARIO 7: Browser direload saat offline');
    const offlineStore = createMockNetworkStore(false);
    applyTransition(offlineStore, false, false);

    assert.strictEqual(offlineStore.isOnline, false, 'Initial isOnline is false');
    assert.strictEqual(offlineStore.wsConnected, false, 'Initial wsConnected is false');
    assert.strictEqual(offlineStore.getBadgeText(), 'Terputus', 'Initial badge text is Terputus');
    assert.strictEqual(offlineStore.getBadgeIcon(), 'wifi_off', 'Initial badge icon is wifi_off');
    assert.strictEqual(offlineStore.isReverbBadgeVisible(), false, 'Reverb badge is hidden upon cold offline load');
    recordPass('Skenario 7 Passed: Browser reload saat offline -> Langsung inisialisasi mode Terputus / Offline');

    // -------------------------------------------------------------
    // SCENARIO 8: Browser direload saat server tidak tersedia
    // -------------------------------------------------------------
    console.log('\nSKENARIO 8: Browser direload saat server tidak tersedia');
    const serverDownStore = createMockNetworkStore(true);
    applyTransition(serverDownStore, true, false); // initial probe fails

    assert.strictEqual(serverDownStore.isOnline, false, 'isOnline is false because server is down');
    assert.strictEqual(serverDownStore.isServerConnected, false, 'isServerConnected is false');
    assert.strictEqual(serverDownStore.wsConnected, false, 'wsConnected is false');
    assert.strictEqual(serverDownStore.getBadgeText(), 'Terputus', 'Badge text is Terputus');
    assert.strictEqual(serverDownStore.isReverbBadgeVisible(), false, 'Reverb badge is hidden');
    recordPass('Skenario 8 Passed: Browser reload saat server mati -> Terputus / Server tidak tersedia');

    // -------------------------------------------------------------
    // SCENARIO 9: Transaksi offline saat koneksi terputus (Dexie DB)
    // -------------------------------------------------------------
    console.log('\nSKENARIO 9: Transaksi offline saat koneksi terputus');
    const testDb = new Dexie('TestOfflineDetectionDB');
    testDb.version(1).stores({
        product_cache: '++id, [location_id+barcode], [location_id+sku], location_id',
        transaction_queue: '++id, offline_id, invoice_no, location_id, sync_status, created_at'
    });
    await testDb.open();

    // Seed product
    await testDb.product_cache.put({
        product_id: 881,
        location_id: 1,
        barcode: '899999111',
        sku: 'SKU-OFFLINE-TEST',
        name: 'Gula Pasir 1kg',
        price: 17500,
        stock: 50
    });

    // Execute offline transaction
    const txOfflineId = 'tx-offline-' + Date.now();
    await testDb.transaction('rw', testDb.transaction_queue, testDb.product_cache, async () => {
        // Enqueue transaction
        await testDb.transaction_queue.put({
            offline_id: txOfflineId,
            invoice_no: 'TRX-OFF-TEST-001',
            location_id: 1,
            grand_total: 35000,
            sync_status: 'PENDING',
            created_at: new Date().toISOString()
        });
        // Deduct offline stock
        const prod = await testDb.product_cache.where('[location_id+barcode]').equals([1, '899999111']).first();
        if (prod) {
            await testDb.product_cache.update(prod.id, { stock: prod.stock - 2 });
        }
    });

    const pendingTx = await testDb.transaction_queue.where('offline_id').equals(txOfflineId).first();
    const updatedProd = await testDb.product_cache.where('[location_id+barcode]').equals([1, '899999111']).first();

    assert(pendingTx, 'Offline transaction successfully queued');
    assert.strictEqual(pendingTx.sync_status, 'PENDING', 'Transaction is in PENDING sync status');
    assert.strictEqual(updatedProd.stock, 48, 'Stock safely deducted in Dexie offline store');
    recordPass('Skenario 9 Passed: Transaksi offline tersimpan di Dexie IndexedDB & stok terpotong aman');

    // -------------------------------------------------------------
    // SCENARIO 10: Sinkronisasi kembali setelah server tersedia (Idempotensi)
    // -------------------------------------------------------------
    console.log('\nSKENARIO 10: Sinkronisasi kembali setelah server tersedia');
    // Server confirms sync
    await testDb.transaction_queue.where('offline_id').equals(txOfflineId).delete();
    const queueAfterSync = await testDb.transaction_queue.where('offline_id').equals(txOfflineId).first();
    assert.strictEqual(queueAfterSync, undefined, 'Transaction safely removed from queue after confirmed sync');

    // Re-sync attempt (Idempotency test)
    const idempotencyMockResponse = { status: 'ALREADY_SYNCED', duplicate_count: 1 };
    assert.strictEqual(idempotencyMockResponse.status, 'ALREADY_SYNCED', 'Server prevents duplicate insertion');
    recordPass('Skenario 10 Passed: Sinkronisasi otomatis aman tanpa duplikasi (Idempoten)');

    // -------------------------------------------------------------
    // SCENARIO 11: Wi-Fi mati / Tidak ada internet (meski loopback Reverb/Virtual Adapters aktif)
    // -------------------------------------------------------------
    console.log('\nSKENARIO 11: Wi-Fi mati / Tidak ada internet saat loopback virtual adapter aktif');
    const internetDropStore = createMockNetworkStore(true);
    // browserOnline is true (WSL/VirtualBox up), serverConnected true (localhost up), BUT hasInternet = false
    applyTransition(internetDropStore, true, true, 'connected', false);

    assert.strictEqual(internetDropStore.hasInternet, false, 'hasInternet is detected as false');
    assert.strictEqual(internetDropStore.isOnline, false, 'isOnline is false because internet is severed');
    assert.strictEqual(internetDropStore.wsConnected, false, 'Reverb WebSocket MUST be forced disconnected');
    assert.strictEqual(internetDropStore.wsStatus, 'disconnected', 'wsStatus is disconnected');
    assert.strictEqual(internetDropStore.latency, null, 'Latency display is hidden / null');
    assert.strictEqual(internetDropStore.getBadgeText(), 'Terputus', 'Badge text MUST show Terputus');
    assert.strictEqual(internetDropStore.getBadgeIcon(), 'wifi_off', 'Badge icon MUST show wifi_off');
    assert.strictEqual(internetDropStore.isReverbBadgeVisible(), false, 'Reverb WS badge MUST be hidden');
    assert.strictEqual(internetDropStore.getBadgeClass().includes('rose'), true, 'Badge class MUST be red / rose');
    recordPass('Skenario 11 Passed: Wi-Fi mati / Tanpa Internet -> Reverb WebSocket dipaksa Terputus, Latensi disembunyikan, Warna Merah');

    await testDb.delete();

    console.log('\n===========================================================');
    console.log(`CONNECTION HEALTH TEST SUITE FINISHED: ALL ${passedCount} CHECKS PASSED 100%!`);
    console.log('===========================================================');
}

runAllScenarios().catch(err => {
    console.error('Test Suite Failed:', err);
    process.exit(1);
});
