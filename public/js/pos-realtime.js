/**
 * POS ILP Real-time & Network Synchronization Engine
 * - Sub-millisecond cross-tab & cross-page data synchronization via BroadcastChannel
 * - Automated storage mutation broadcasts
 * - Real-time network health, ping latency, and offline detection
 * - Reactive Alpine.js stores ($store.network & $store.posRealtime)
 * - Multi-device background server state synchronization
 */
(function () {
    'use strict';

    // 1. Core State
    const state = {
        isOnline: typeof navigator !== 'undefined' ? navigator.onLine : true,
        status: (typeof navigator !== 'undefined' && navigator.onLine) ? 'online' : 'offline',
        latency: null,
        lastChecked: new Date(),
        isChecking: false,
        pendingSyncCount: 0,
        subscribers: new Map()
    };

    // 2. BroadcastChannel for Instant Cross-Tab Communication
    const channelName = 'pos_ilp_realtime_bus';
    let broadcastChannel = null;
    try {
        if (typeof BroadcastChannel !== 'undefined') {
            broadcastChannel = new BroadcastChannel(channelName);
            broadcastChannel.onmessage = function (event) {
                if (event && event.data) {
                    handleIncomingMessage(event.data, false);
                }
            };
        }
    } catch (e) {
        console.warn('BroadcastChannel not supported or restricted, falling back to storage events:', e);
    }

    function handleIncomingMessage(msg, isLocal = false) {
        if (!msg || !msg.type) return;

        // Dispatch general and typed CustomEvents for Alpine and Vanilla listeners
        const eventDetail = {
            type: msg.type,
            entity: msg.entity || msg.type,
            data: msg.data || null,
            isLocal: isLocal,
            timestamp: msg.timestamp || Date.now()
        };

        window.dispatchEvent(new CustomEvent('pos:realtime', { detail: eventDetail }));
        window.dispatchEvent(new CustomEvent('pos:' + msg.type, { detail: eventDetail }));
        if (msg.entity) {
            window.dispatchEvent(new CustomEvent('pos:entity:' + msg.entity, { detail: eventDetail }));
        }

        // Notify direct subscriber callbacks
        const subs = state.subscribers.get(msg.type) || [];
        subs.forEach(cb => {
            try { cb(eventDetail); } catch (err) { console.error('Realtime subscriber error:', err); }
        });
    }

    // 3. Storage Mutation Interceptor
    // Automatically broadcasts changes when localStorage.setItem is called
    const monitoredKeys = {
        'pos_ilp_goods_in_docs_v1': 'goods_in',
        'pos_ilp_inventory_products_v1': 'inventory',
        'pos_ilp_transactions_v1': 'transactions',
        'pos_ilp_held_bills_v1': 'held_bills',
        'pos_ilp_active_session_v1': 'session',
        'pos_ilp_members_v1': 'members',
        'pos_ilp_coupons_v1': 'coupons',
        'pos_ilp_manager_returns_v1': 'returns',
        'pos_ilp_return_in_docs_v1': 'return_in',
        'pos_ilp_retur_history_docs_v1': 'retur_history',
        'pos_ilp_stock_opname_history_v1': 'stock_opname',
        'pos_ilp_cash_movements_v1': 'cash_movements',
        'pos_ilp_offline_transactions_v1': 'offline_transactions',
        'pos_ilp_offline_movements_v1': 'offline_movements',
        'pos_ilp_terminals_v1': 'terminals'
    };

    const originalSetItem = localStorage.setItem.bind(localStorage);
    localStorage.setItem = function (key, value) {
        originalSetItem(key, value);
        if (monitoredKeys[key]) {
            const entity = monitoredKeys[key];
            // Debounce broadcast slightly to avoid flooding during batch updates
            PosRealtime.broadcast(entity + '_updated', { key, entity });
        }
    };

    // Native storage event listener (for cross-window storage fallback)
    window.addEventListener('storage', function (e) {
        if (e.key && monitoredKeys[e.key]) {
            const entity = monitoredKeys[e.key];
            handleIncomingMessage({
                type: entity + '_updated',
                entity: entity,
                data: { key: e.key, newValue: e.newValue },
                timestamp: Date.now()
            }, false);
        }
    });

    // 4. Real-time Network Monitoring & Heartbeat Ping
    async function checkNetworkHealth() {
        if (!navigator.onLine) {
            updateNetworkState(false, null, 'offline');
            return;
        }

        const startTime = performance.now();
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 2500);

        try {
            const res = await fetch('/api/health?_t=' + Date.now(), {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'Cache-Control': 'no-cache' },
                signal: controller.signal
            });
            clearTimeout(timeoutId);

            if (res.ok) {
                const latency = Math.max(1, Math.round(performance.now() - startTime));
                updateNetworkState(true, latency, 'online');
            } else {
                updateNetworkState(false, null, 'offline');
            }
        } catch (err) {
            clearTimeout(timeoutId);
            // If offline or network error
            updateNetworkState(false, null, 'offline');
        }
    }

    function updateNetworkState(isOnline, latency, status) {
        const changed = state.isOnline !== isOnline || state.status !== status;
        state.isOnline = isOnline;
        state.latency = latency;
        state.status = status;
        state.lastChecked = new Date();

        // Update Alpine Store if available
        if (window.Alpine && window.Alpine.store && window.Alpine.store('network')) {
            const store = window.Alpine.store('network');
            store.isOnline = isOnline;
            store.latency = latency;
            store.status = status;
            store.lastChecked = state.lastChecked;
        }

        if (changed) {
            window.dispatchEvent(new CustomEvent('pos:network-status', {
                detail: { isOnline, latency, status }
            }));

            // If connection restored, auto-sync pending offline queue
            if (isOnline && window.PosOfflineSync && typeof window.PosOfflineSync.syncAll === 'function') {
                window.PosOfflineSync.syncAll({ isManual: false });
            }
        }
    }

    // Browser Online / Offline Event Handlers
    window.addEventListener('online', () => {
        checkNetworkHealth();
    });

    window.addEventListener('offline', () => {
        updateNetworkState(false, null, 'offline');
    });

    // When user returns to tab, check network & server updates (throttled to min 15s)
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            const now = Date.now();
            if (!state.lastChecked || (now - new Date(state.lastChecked).getTime() > 15000)) {
                checkNetworkHealth();
                pollServerUpdates();
            }
        }
    });

    // Throttled Heartbeat Ping (Gentle 30s interval instead of 4.5s)
    setInterval(() => {
        // Skip frequent HTTP pings if WebSocket is already active and healthy
        const isWsActive = window.Alpine && window.Alpine.store && window.Alpine.store('network') && window.Alpine.store('network').wsConnected;
        if (!isWsActive) {
            checkNetworkHealth();
        }
    }, 30000);

    // Initial check after short delay
    setTimeout(() => {
        checkNetworkHealth();
    }, 500);

    // 5. Multi-Device Background Server Sync Poller
    // Silently checks server APIs for latest changes so different devices stay realtime
    async function pollServerUpdates() {
        if (!state.isOnline) return;

        const path = window.location.pathname;

        // A. If on Barang Masuk (Kasir) or Kirim Barang (Manager)
        if (path.includes('goods-in') || path.includes('kirim-barang') || path.includes('/kasir/produk/masuk')) {
            try {
                const res = await fetch('/api/goods-receipts', { headers: { 'Accept': 'application/json' } });
                if (res.ok) {
                    const json = await res.json();
                    if (json.success && Array.isArray(json.data)) {
                        const localRaw = localStorage.getItem('pos_ilp_goods_in_docs_v1');
                        const localDocs = localRaw ? JSON.parse(localRaw) : [];
                        
                        // Compare count or latest ID
                        const localLatestId = localDocs.length > 0 ? localDocs[0].id : null;
                        const serverLatestId = json.data.length > 0 ? json.data[0].id : null;
                        const localCount = localDocs.length;
                        const serverCount = json.data.length;

                        if (localCount !== serverCount || localLatestId !== serverLatestId) {
                            // Merge without overriding local drafts
                            const merged = [...json.data];
                            for (const loc of localDocs) {
                                const exists = merged.some(m => (m.id && m.id === loc.id) || (m.docNo && m.docNo === loc.docNo));
                                if (!exists) merged.push(loc);
                            }
                            originalSetItem('pos_ilp_goods_in_docs_v1', JSON.stringify(merged));
                            PosRealtime.broadcast('goods_in_updated', { source: 'server_poll', documents: merged });
                        }
                    }
                }
            } catch (e) {}
        }

        // B. If on POS or Inventory, check product updates
        if (path.includes('/pos') || path.includes('/inventory')) {
            try {
                const res = await fetch('/api/pos/products/search?limit=10', { headers: { 'Accept': 'application/json' } });
                if (res.ok) {
                    // Just verify connection, individual actions push updates directly
                }
            } catch (e) {}
        }
    }

    // Periodic Server Poller (Gentle 35s fallback, skip when WebSocket is streaming)
    setInterval(() => {
        const isWsActive = window.Alpine && window.Alpine.store && window.Alpine.store('network') && window.Alpine.store('network').wsConnected;
        if (!isWsActive) {
            pollServerUpdates();
        }
    }, 35000);

    // 6. Public PosRealtime API
    const PosRealtime = {
        state: state,

        /**
         * Broadcast an event to current tab and all other open tabs/windows
         */
        broadcast(type, data = {}) {
            const payload = {
                type: type,
                entity: data.entity || type.replace(/_updated$/, ''),
                data: data,
                timestamp: Date.now()
            };

            // 1. Send to other tabs
            if (broadcastChannel) {
                try {
                    broadcastChannel.postMessage(payload);
                } catch (e) {
                    console.warn('BroadcastChannel post error:', e);
                }
            }

            // 2. Deliver in current tab
            handleIncomingMessage(payload, true);
        },

        /**
         * Convenience helper to notify entity change
         */
        notifyChange(entity, extra = {}) {
            this.broadcast(entity + '_updated', { entity, ...extra });
        },

        /**
         * Subscribe to specific event type
         */
        subscribe(type, callback) {
            if (!state.subscribers.has(type)) {
                state.subscribers.set(type, []);
            }
            state.subscribers.get(type).push(callback);
            return () => {
                const list = state.subscribers.get(type) || [];
                state.subscribers.set(type, list.filter(cb => cb !== callback));
            };
        },

        /**
         * Trigger manual instantaneous synchronization
         */
        async syncNow() {
            state.isChecking = true;
            if (window.Alpine && window.Alpine.store && window.Alpine.store('network')) {
                window.Alpine.store('network').isChecking = true;
            }

            try {
                await checkNetworkHealth();
                await pollServerUpdates();
                
                // Dispatch global sync events for components
                window.dispatchEvent(new CustomEvent('ilp-sync-data'));
                window.dispatchEvent(new CustomEvent('pos:sync-all'));
                window.dispatchEvent(new CustomEvent('pos-inventory-synced'));

                // Also sync pending offline transactions if any
                if (window.PosOfflineSync && typeof window.PosOfflineSync.syncAll === 'function') {
                    await window.PosOfflineSync.syncAll({ isManual: true });
                } else if (typeof window.triggerToast === 'function') {
                    window.triggerToast('Data konsolidasi & status koneksi real-time disinkronkan!', 'success');
                }
            } catch (err) {
                console.warn('Manual sync warning:', err);
            } finally {
                setTimeout(() => {
                    state.isChecking = false;
                    if (window.Alpine && window.Alpine.store && window.Alpine.store('network')) {
                        window.Alpine.store('network').isChecking = false;
                    }
                }, 400);
            }
        }
    };

    window.PosRealtime = PosRealtime;
    window.posNotifyChange = (entity, data) => PosRealtime.notifyChange(entity, data);

    // 7. Register Alpine Stores
    function initAlpineStores() {
        if (!window.Alpine) return;

        // Network Store
        if (!window.Alpine.store('network')) {
            window.Alpine.store('network', {
                isOnline: state.isOnline,
                latency: state.latency,
                status: state.status,
                lastChecked: state.lastChecked,
                isChecking: false,
                wsConnected: false,
                wsStatus: 'disconnected',
                syncNow() {
                    return PosRealtime.syncNow();
                }
            });
        }

        // PosRealtime Store
        if (!window.Alpine.store('posRealtime')) {
            window.Alpine.store('posRealtime', {
                broadcast(type, data) {
                    PosRealtime.broadcast(type, data);
                },
                notify(entity, data) {
                    PosRealtime.notifyChange(entity, data);
                }
            });
        }
    }

    if (window.Alpine) {
        initAlpineStores();
    } else {
        document.addEventListener('alpine:init', initAlpineStores);
    }
})();
