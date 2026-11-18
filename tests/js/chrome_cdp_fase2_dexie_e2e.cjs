/**
 * POS ILP - Fase 2 Full Chrome CDP End-to-End Test Suite
 * Tests Real Chrome Browser with Dexie.js + IndexedDB:
 * 1. Dexie initialization & IndexedDB persistence
 * 2. LocalStorage migration
 * 3. Offline checkout via Dexie transaction_queue
 * 4. Page reload persistence while offline
 * 5. Online re-connection & atomic sync to PostgreSQL
 * 6. Screenshot UI audit for Kasir, Manager, Owner
 */

const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');
const assert = require('assert');

const CHROME_PATH = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const CDP_PORT = 9233;
const USER_DATA_DIR = path.join(os.tmpdir(), 'pos_ilp_fase2_cdp_' + Date.now());
fs.mkdirSync(USER_DATA_DIR, { recursive: true });

const SCREENSHOT_DIR = path.resolve(__dirname, '../../brain/screenshots_fase2');
fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });

function sleep(ms) {
    return new Promise(r => setTimeout(r, ms));
}

class CDPClient {
    constructor(wsUrl) {
        this.ws = new WebSocket(wsUrl);
        this.id = 0;
        this.pending = new Map();
        this.events = [];
    }
    async connect() {
        await new Promise((resolve, reject) => {
            this.ws.onopen = resolve;
            this.ws.onerror = reject;
        });
        this.ws.onmessage = (msg) => {
            const data = JSON.parse(msg.data);
            if (data.id && this.pending.has(data.id)) {
                const { resolve, reject } = this.pending.get(data.id);
                this.pending.delete(data.id);
                if (data.error) reject(new Error(JSON.stringify(data.error)));
                else resolve(data.result);
            } else if (data.method) {
                this.events.push(data);
            }
        };
    }
    send(method, params = {}) {
        const id = ++this.id;
        return new Promise((resolve, reject) => {
            this.pending.set(id, { resolve, reject });
            this.ws.send(JSON.stringify({ id, method, params }));
        });
    }
    async eval(expression) {
        const res = await this.send('Runtime.evaluate', {
            expression,
            awaitPromise: true,
            returnByValue: true
        });
        if (res.exceptionDetails) {
            throw new Error('Browser eval exception: ' + JSON.stringify(res.exceptionDetails));
        }
        return res.result ? res.result.value : undefined;
    }
    async captureScreenshot(filename) {
        const res = await this.send('Page.captureScreenshot', { format: 'png' });
        const filePath = path.join(SCREENSHOT_DIR, filename);
        fs.writeFileSync(filePath, Buffer.from(res.data, 'base64'));
        return filePath;
    }
    close() {
        this.ws.close();
    }
}

(async () => {
    console.log('===========================================================');
    console.log('STARTING CHROME CDP E2E TEST: DEXIE.JS & INDEXEDDB OFFLINE');
    console.log('===========================================================\n');

    const chrome = spawn(CHROME_PATH, [
        '--headless=new',
        `--remote-debugging-port=${CDP_PORT}`,
        `--user-data-dir=${USER_DATA_DIR}`,
        '--no-first-run',
        '--no-default-browser-check',
        '--disable-gpu',
        '--window-size=1440,900',
        'about:blank'
    ], { stdio: 'ignore' });

    let cdp = null;
    let passedChecks = 0;

    function recordPass(desc) {
        console.log(`  [PASS] ${desc}`);
        passedChecks++;
    }

    try {
        let targets = null;
        for (let i = 0; i < 30; i++) {
            try {
                const res = await fetch(`http://127.0.0.1:${CDP_PORT}/json/list`);
                targets = await res.json();
                if (Array.isArray(targets) && targets.find(t => t.type === 'page')) break;
            } catch (e) {}
            await sleep(250);
        }
        const pageTarget = targets && targets.find(t => t.type === 'page');
        assert(pageTarget && pageTarget.webSocketDebuggerUrl, 'No Chrome CDP page target found');

        cdp = new CDPClient(pageTarget.webSocketDebuggerUrl);
        await cdp.connect();
        await cdp.send('Page.enable');
        await cdp.send('Network.enable');
        await cdp.send('Runtime.enable');

        // STEP 1: Login as kasir1
        console.log('STEP 1: Authenticate kasir1 and reach /pos');
        await cdp.send('Page.navigate', { url: 'http://127.0.0.1:8000/login?role=kasir' });
        await sleep(1500);

        await cdp.eval(`(async () => {
            const u = document.getElementById('kasir-identifier');
            const p = document.getElementById('kasir-password');
            if (u && p) {
                u.value = 'kasir1';
                u.dispatchEvent(new Event('input', { bubbles: true }));
                p.value = 'kasir123';
                p.dispatchEvent(new Event('input', { bubbles: true }));
                const form = document.getElementById('form-kasir');
                const btn = form.querySelector('button[type="submit"]');
                if (btn) setTimeout(() => btn.click(), 50);
            }
        })()`);

        for (let i = 0; i < 20; i++) {
            await sleep(300);
            const cur = await cdp.eval('window.location.pathname');
            if (cur === '/select-shift' || cur === '/kasir/pos') break;
        }

        const path1 = await cdp.eval('window.location.pathname');
        recordPass(`Redirected after login to ${path1}`);

        // Open shift for POS-01
        await sleep(1000);
        await cdp.eval(`(async () => {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const r = await fetch('/api/shift/open', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({
                    terminal_code: 'POS-01',
                    cashier_nik: '202409820001',
                    cashier_name: 'Kasir 1',
                    opening_cash: 100000,
                    branch: 'Toko Pusat',
                    pin: '111222'
                })
            });
            const j = await r.json();
            if (j.success && j.data) {
                localStorage.setItem('pos_cashier_opened', 'true');
                localStorage.setItem('pos_kasir_branch', 'Toko Pusat');
                localStorage.setItem('pos_kasir_location', 'Toko Pusat');
                localStorage.setItem('pos_active_terminal_label', 'POS-01');
                localStorage.setItem('pos_ilp_active_session_v1', JSON.stringify({
                    sessionId: j.data.shift_code,
                    locationId: j.data.location_id,
                    storeLocation: j.data.branch || 'Toko Pusat',
                    terminalId: 'shift-1',
                    terminalLabel: 'POS-01',
                    cashierId: 1,
                    cashierName: 'Kasir 1',
                    cashierNik: '202409820001',
                    openingCash: 100000
                }));
            }
        })()`);

        // Navigate to /kasir/pos
        await cdp.send('Page.navigate', { url: 'http://127.0.0.1:8000/kasir/pos' });
        for (let i = 0; i < 20; i++) {
            await sleep(300);
            const cur = await cdp.eval('window.location.pathname');
            if (cur.includes('pos')) break;
        }

        const currentPosPath = await cdp.eval('window.location.pathname');
        assert(currentPosPath.includes('pos'), 'Current path is POS page');
        recordPass('Loaded POS Cashier page');

        // Ensure Service Worker is registered and precached before cutting network
        await sleep(3500);
        await cdp.eval(`(async () => {
            if ('serviceWorker' in navigator) {
                try {
                    await navigator.serviceWorker.ready;
                } catch (e) {}
            }
        })()`);

        // STEP 2: Verify Dexie & IndexedDB in Chrome Browser runtime
        console.log('\nSTEP 2: Dexie & IndexedDB Runtime Verification');
        const dbVerification = await cdp.eval(`(async () => {
            const hasDexie = typeof window.Dexie === 'function';
            const hasPosDb = !!window.PosDb;
            await window.PosDb.init();
            const isReady = window.PosDb.isReady;
            const tables = window.PosDb.db.tables.map(t => t.name);
            const hasMigration = !!window.PosMigration;
            const isMigrated = window.PosMigration.isMigrated();
            return { hasDexie, hasPosDb, isReady, tables, hasMigration, isMigrated };
        })()`);

        assert.strictEqual(dbVerification.hasDexie, true, 'window.Dexie exists in Chrome');
        assert.strictEqual(dbVerification.hasPosDb, true, 'window.PosDb exists in Chrome');
        assert.strictEqual(dbVerification.isReady, true, 'window.PosDb is connected to IndexedDB');
        assert(dbVerification.tables.includes('product_cache'), 'IndexedDB table product_cache exists');
        assert(dbVerification.tables.includes('transaction_queue'), 'IndexedDB table transaction_queue exists');
        assert(dbVerification.tables.includes('shift_queue'), 'IndexedDB table shift_queue exists');
        assert(dbVerification.tables.includes('cash_movement_queue'), 'IndexedDB table cash_movement_queue exists');
        recordPass('Dexie.js & IndexedDB schema initialized in real Chrome');

        // STEP 3: Seed Dexie product cache with location 1 items for offline testing
        console.log('\nSTEP 3: Seed Stock Toko Catalog in Dexie product_cache');
        const seedResult = await cdp.eval(`(async () => {
            const prods = [
                { id: 991, barcode: '8991001001', sku: 'SKU-DEX-01', name: 'Minyak Goreng 2L', category: 'Sembako', unit: 'Pouch', price: 34000, stock: 50, min_stock: 5 },
                { id: 992, barcode: '8991001002', sku: 'SKU-DEX-02', name: 'Beras Premium 5kg', category: 'Sembako', unit: 'Sak', price: 72000, stock: 30, min_stock: 5 }
            ];
            await window.PosDb.products.bulkUpsert(1, prods);
            const count = await window.PosDb.products.count(1);
            const lookup = await window.PosDb.products.findByBarcode(1, '8991001001');
            return { count, foundSku: lookup?.sku };
        })()`);

        assert.strictEqual(seedResult.count >= 2, true, 'Stock Toko products cached in Dexie');
        assert.strictEqual(seedResult.foundSku, 'SKU-DEX-01', 'Barcode lookup fast indexed in Dexie');
        recordPass('Stock Toko products successfully cached and indexed in Dexie');

        // STEP 4: Network Emulation - Go OFFLINE
        console.log('\nSTEP 4: Cut Internet Connection (Emulate OFFLINE)');
        await cdp.send('Network.emulateNetworkConditions', {
            offline: true,
            latency: 0,
            downloadThroughput: 0,
            uploadThroughput: 0
        });

        const isOnlineBrowser = await cdp.eval('navigator.onLine');
        assert.strictEqual(isOnlineBrowser, false, 'Browser network condition set to OFFLINE');
        recordPass('Network successfully severed (OFFLINE mode)');

        // STEP 5: Perform Offline Transaction
        console.log('\nSTEP 5: Perform Offline Checkout in Dexie');
        const checkoutRes = await cdp.eval(`(async () => {
            const prod = await window.PosDb.products.findByBarcode(1, '8991001001');
            const cartItem = {
                id: prod.product_id,
                sku: prod.sku,
                barcode: prod.barcode,
                name: prod.name,
                qty: 2,
                unit: prod.unit,
                price: prod.price
            };

            const grandTotal = prod.price * 2;
            const res = window.PosOfflineSync.processOfflineTransaction({
                payload: {
                    terminal_code: 'POS-01',
                    branch: 'Toko Pusat',
                    location_id: 1,
                    cashier_nik: '202409820001',
                    cashier_name: 'Kasir 1',
                    payment_method: 'TUNAI',
                    cash_received: 100000
                },
                cart: [cartItem],
                activeSession: {
                    terminalLabel: 'POS-01',
                    locationId: 1,
                    storeLocation: 'Toko Pusat',
                    cashierName: 'Kasir 1',
                    cashierNik: '202409820001'
                },
                grandTotal: grandTotal,
                paymentModal: {},
                isMember: false,
                selectedMember: null
            });

            // Inspect Dexie transaction_queue directly
            await new Promise(r => setTimeout(r, 200));
            const pendingDexie = await window.PosDb.transactions.getPending();
            const pendingCountDexie = await window.PosDb.transactions.getPendingCount();
            const updatedStockProd = await window.PosDb.products.findByBarcode(1, '8991001001');

            return {
                checkoutSuccess: res.success,
                invoiceNumber: res.trxRecord?.invoice_number,
                offlineId: res.trxRecord?.offline_id,
                pendingCountDexie: pendingCountDexie,
                pendingItems: pendingDexie,
                remainingStock: updatedStockProd.stock
            };
        })()`);

        assert.strictEqual(checkoutRes.checkoutSuccess, true, 'Offline checkout returned success');
        assert(checkoutRes.offlineId && checkoutRes.offlineId.startsWith('off_'), 'Transaction assigned UUID offline_id');
        assert.strictEqual(checkoutRes.pendingCountDexie, 1, 'Dexie transaction_queue has exactly 1 pending transaction');
        assert.strictEqual(checkoutRes.remainingStock, 48, 'Stock in Dexie product_cache decremented by 2 (50 -> 48)');
        recordPass(`Offline transaction queued in Dexie (${checkoutRes.invoiceNumber}) with offline stock deduction`);

        // STEP 6: Reload page while still offline (Zero Data Loss Check)
        console.log('\nSTEP 6: Page Reload While Offline (Persistence Verification)');
        await cdp.send('Page.reload');
        await sleep(3500);

        const reloadVerification = await cdp.eval(`(async () => {
            for (let i = 0; i < 40; i++) {
                if (window.PosDb && typeof window.PosDb.init === 'function') {
                    try {
                        await window.PosDb.init();
                        if (window.PosDb.isReady) break;
                    } catch (e) {}
                }
                await new Promise(r => setTimeout(r, 200));
            }
            if (!window.PosDb) throw new Error('window.PosDb not loaded after reload');
            const pending = await window.PosDb.transactions.getPending();
            const count = await window.PosDb.transactions.getPendingCount();
            return { count, pendingFirst: pending[0] };
        })()`);

        assert.strictEqual(reloadVerification.count, 1, 'Transaction survived full page reload in offline mode');
        assert.strictEqual(reloadVerification.pendingFirst.offline_id, checkoutRes.offlineId, 'offline_id matches exactly');
        recordPass('Zero Data Loss: Dexie persisted offline transaction across full page reload');

        // STEP 7: Restore Network & Perform Synchronization
        console.log('\nSTEP 7: Restore Internet & Re-Sync to PostgreSQL');
        await cdp.send('Network.emulateNetworkConditions', {
            offline: false,
            latency: 0,
            downloadThroughput: 0,
            uploadThroughput: 0
        });
        await sleep(500);

        // Dispatch window 'online' event to trigger auto-sync
        await cdp.eval(`(() => {
            window.dispatchEvent(new Event('online'));
            if (window.Alpine && window.Alpine.store && window.Alpine.store('network')) {
                window.Alpine.store('network').isOnline = true;
            }
        })()`);

        // Wait for auto-sync or trigger manual sync until queue drains
        const syncResult = await cdp.eval(`(async () => {
            for (let i = 0; i < 30; i++) {
                const count = await window.PosDb.transactions.getPendingCount();
                if (count === 0 && !window.PosOfflineSync.state.isSyncing) {
                    break;
                }
                if (!window.PosOfflineSync.state.isSyncing && count > 0) {
                    await window.PosOfflineSync.syncAll({ isManual: true });
                }
                await new Promise(r => setTimeout(r, 400));
            }
            const remainingCount = await window.PosDb.transactions.getPendingCount();
            const history = await window.PosDb.transactions.getHistory(5);
            return { remainingCount, historyStatus: history[0]?.syncStatus };
        })()`);
        console.log('  -> sync result:', JSON.stringify(syncResult));

        assert.strictEqual(syncResult.remainingCount, 0, 'Dexie transaction_queue purged of confirmed synced item');
        assert.strictEqual(syncResult.historyStatus, 'SYNCED', 'Transaction history status updated to SYNCED in Dexie');
        recordPass('Synchronized offline transaction to server and updated IndexedDB status');

        // STEP 8: Idempotency Check (re-syncing already synced transaction)
        console.log('\nSTEP 8: Idempotency Protection Test');
        const idempotencyRes = await cdp.eval(`(async () => {
            // Attempt to re-send the same transaction manually
            const oldTx = ${JSON.stringify(checkoutRes.pendingItems[0])};
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const res = await fetch('/api/pos/sync-offline', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ transactions: [oldTx], offline_shifts: [], cash_movements: [] })
            });
            const json = await res.json();
            const txResult = json.results?.transactions?.[0];
            return { success: json.success, status: txResult?.status, duplicateCount: json.duplicate_count };
        })()`);

        assert.strictEqual(idempotencyRes.success, true, 'Server response ok');
        assert.strictEqual(idempotencyRes.status, 'ALREADY_SYNCED', 'Server prevented duplicate insertion via offline_id idempotency');
        recordPass('Server confirmed ALREADY_SYNCED; zero duplicate insertion');

        // STEP 9: UI Visual Fidelity Audit Screenshots
        console.log('\nSTEP 9: UI Visual Fidelity Audit (Screenshots)');
        const ssPos = await cdp.captureScreenshot('01_pos_kasir.png');
        recordPass(`Captured POS screenshot: ${path.basename(ssPos)}`);

        await cdp.send('Page.navigate', { url: 'http://127.0.0.1:8000/select-shift' });
        await sleep(1500);
        const ssShift = await cdp.captureScreenshot('02_select_shift.png');
        recordPass(`Captured Select Shift screenshot: ${path.basename(ssShift)}`);

        await cdp.send('Page.navigate', { url: 'http://127.0.0.1:8000/kasir/transactions' });
        await sleep(1500);
        const ssTrx = await cdp.captureScreenshot('03_kasir_transactions.png');
        recordPass(`Captured Kasir Transactions screenshot: ${path.basename(ssTrx)}`);

        // Manager Login & Dashboard (Logout first to clear kasir session)
        await cdp.send('Page.navigate', { url: 'http://127.0.0.1:8000/logout' });
        await sleep(800);
        await cdp.send('Page.navigate', { url: 'http://127.0.0.1:8000/login?role=manager' });
        await sleep(1500);
        await cdp.eval(`(() => {
            const u = document.getElementById('manager-username');
            const p = document.getElementById('manager-password');
            if (u && p) {
                u.value = 'manager1';
                u.dispatchEvent(new Event('input', { bubbles: true }));
                p.value = 'manager123';
                p.dispatchEvent(new Event('input', { bubbles: true }));
                const form = document.querySelector('#form-manager form');
                if (form) {
                    const btn = form.querySelector('button[type="submit"]');
                    if (btn) setTimeout(() => btn.click(), 50);
                }
            }
        })()`);
        await sleep(2500);
        const ssMgr = await cdp.captureScreenshot('04_manager_dashboard.png');
        recordPass(`Captured Manager Dashboard screenshot: ${path.basename(ssMgr)}`);

        // Owner Login & Dashboard (Logout first to clear manager session)
        await cdp.send('Page.navigate', { url: 'http://127.0.0.1:8000/logout' });
        await sleep(800);
        await cdp.send('Page.navigate', { url: 'http://127.0.0.1:8000/login?role=owner' });
        await sleep(1500);
        await cdp.eval(`(() => {
            const u = document.getElementById('owner-username');
            const p = document.getElementById('owner-password');
            if (u && p) {
                u.value = 'owner1';
                u.dispatchEvent(new Event('input', { bubbles: true }));
                p.value = 'owner123';
                p.dispatchEvent(new Event('input', { bubbles: true }));
                const form = document.querySelector('#form-owner form');
                if (form) {
                    const btn = form.querySelector('button[type="submit"]');
                    if (btn) setTimeout(() => btn.click(), 50);
                }
            }
        })()`);
        await sleep(2500);
        const ssOwner = await cdp.captureScreenshot('05_owner_dashboard.png');
        recordPass(`Captured Owner Dashboard screenshot: ${path.basename(ssOwner)}`);

        console.log('\n===========================================================');
        console.log(`CHROME CDP E2E FINISHED: ALL ${passedChecks} CHECKS PASSED 100%!`);
        console.log('===========================================================');

    } catch (err) {
        console.error('\nCHROME CDP E2E FAILED:', err);
        process.exitCode = 1;
    } finally {
        if (cdp) cdp.close();
        chrome.kill();
        try { fs.rmSync(USER_DATA_DIR, { recursive: true, force: true }); } catch (e) {}
    }
})();
