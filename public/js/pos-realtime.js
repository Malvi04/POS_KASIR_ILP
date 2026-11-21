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

    // 1. Core State & 3-Tier Connection Model:
    // A. ONLINE: Browser detects network via navigator.onLine
    // B. SERVER_CONNECTED: Laravel server reachable via /api/health probe
    // C. OFFLINE: Physical network cut OR server probe failed
    let consecutiveFailures = 0;
    const MAX_CONSECUTIVE_FAILURES = 3;

    const initialBrowserOnline = typeof navigator !== 'undefined' ? navigator.onLine : true;

    // Global container for immediate cross-script access (e.g. echo.js, cashier-core.js)
    window.posNetworkHealth = {
        isBrowserOnline: initialBrowserOnline,
        isServerConnected: initialBrowserOnline,
        hasInternet: initialBrowserOnline,
        connectionState: initialBrowserOnline ? 'SERVER_CONNECTED' : 'OFFLINE',
        operationalStatus: initialBrowserOnline ? 'connected' : 'offline',
        operationalLabel: initialBrowserOnline ? 'Terhubung' : 'Terputus',
        lastChecked: new Date()
    };

    const state = {
        isOnline: initialBrowserOnline,
        isBrowserOnline: initialBrowserOnline,
        isServerConnected: initialBrowserOnline,
        hasInternet: initialBrowserOnline,
        connectionState: initialBrowserOnline ? 'SERVER_CONNECTED' : 'OFFLINE',
        status: initialBrowserOnline ? 'online' : 'offline',
        operationalStatus: initialBrowserOnline ? 'connected' : 'offline',
        operationalLabel: initialBrowserOnline ? 'Terhubung' : 'Terputus',
        latency: 2,
        lastChecked: new Date(),
        isChecking: false,
        isReconnecting: false,
        offlineReason: initialBrowserOnline ? null : 'wifi_offline',
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

    // 4. Real-time Network Monitoring & Snappy Direct Server Health Check
    let healthPollTimer = null;
    let activeCheckController = null;

    
    // Active internet probe (detects Wi-Fi / WAN drop even when virtual adapters are Up)
    async function probeInternet(timeoutMs = 1500) {
        if (typeof navigator !== 'undefined' && !navigator.onLine) {
            return false;
        }
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeoutMs);
        try {
            await fetch('https://connectivitycheck.gstatic.com/generate_204?_t=' + Date.now(), {
                method: 'GET',
                mode: 'no-cors',
                cache: 'no-store',
                signal: controller.signal
            });
            clearTimeout(timeoutId);
            return true;
        } catch (e) {
            clearTimeout(timeoutId);
            try {
                const c2 = new AbortController();
                const t2 = setTimeout(() => c2.abort(), 1000);
                await fetch('https://1.1.1.1/cdn-cgi/trace?_t=' + Date.now(), {
                    method: 'GET',
                    mode: 'no-cors',
                    cache: 'no-store',
                    signal: c2.signal
                });
                clearTimeout(t2);
                return true;
            } catch (e2) {
                return false;
            }
        }
    }

    function applyConnectionState(browserOnline, serverConnected, opStatus, reason, httpLatency, hasInternet = true) {
        const isFullyOnline = browserOnline && serverConnected && hasInternet;
        const isOffline = !isFullyOnline;

        let connState = 'OFFLINE';
        if (isFullyOnline) {
            connState = 'SERVER_CONNECTED';
        } else if (browserOnline && !serverConnected) {
            connState = 'ONLINE';
        } else {
            connState = 'OFFLINE';
        }

        let label = 'Terhubung';
        if (!browserOnline || !hasInternet) {
            label = 'Terputus';
        } else if (!serverConnected) {
            label = 'Server tidak tersedia';
        }

        let wsConnected = false;
        let wsStatus = 'disconnected';
        let wsConnecting = false;

        if (!browserOnline || !hasInternet) {
            wsConnected = false;
            wsStatus = 'disconnected';
            wsConnecting = false;
            if (window.posReverbState) {
                window.posReverbState.wsConnected = false;
                window.posReverbState.status = 'disconnected';
                window.posReverbState.latency = null;
            }
            if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
                try { window.Echo.connector.pusher.disconnect(); } catch (e) {}
            }
        } else if (!serverConnected) {
            wsConnected = false;
            const isTryingConnect = (state.isChecking || window.posReverbState?.status === 'connecting');
            wsStatus = isTryingConnect ? 'connecting' : 'unavailable';
            wsConnecting = isTryingConnect;
            if (window.posReverbState) {
                window.posReverbState.wsConnected = false;
                window.posReverbState.status = wsStatus;
                window.posReverbState.latency = null;
            }
        } else {
            const rawWsState = (window.posReverbState && window.posReverbState.status) || 'connecting';
            const rawWsConnected = !!(window.posReverbState && window.posReverbState.wsConnected);
            wsConnected = rawWsConnected;
            wsStatus = rawWsState;
            wsConnecting = (rawWsState === 'connecting');
        }

        const resolvedLatency = (wsConnected && typeof window.posReverbState?.latency === 'number')
            ? window.posReverbState.latency
            : (isFullyOnline ? httpLatency : null);

        const prevOnline = state.isOnline;
        const prevServer = state.isServerConnected;
        const prevBrowser = state.isBrowserOnline;
        const prevInternet = state.hasInternet;

        state.isOnline = isFullyOnline;
        state.isBrowserOnline = browserOnline;
        state.isServerConnected = serverConnected;
        state.hasInternet = hasInternet;
        state.connectionState = connState;
        state.status = isFullyOnline ? 'connected' : 'offline';
        state.operationalStatus = isFullyOnline ? 'connected' : 'offline';
        state.operationalLabel = label;
        state.offlineReason = reason;
        state.latency = isFullyOnline ? resolvedLatency : null;
        state.lastChecked = new Date();

        window.posNetworkHealth.isBrowserOnline = browserOnline;
        window.posNetworkHealth.isServerConnected = serverConnected;
        window.posNetworkHealth.hasInternet = hasInternet;
        window.posNetworkHealth.connectionState = connState;
        window.posNetworkHealth.operationalStatus = isFullyOnline ? 'connected' : 'offline';
        window.posNetworkHealth.operationalLabel = label;
        window.posNetworkHealth.lastChecked = state.lastChecked;

        if (window.Alpine && window.Alpine.store && window.Alpine.store('network')) {
            const store = window.Alpine.store('network');
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
            store.status = isFullyOnline ? 'connected' : 'offline';
            store.wsConnected = wsConnected;
            store.wsStatus = wsStatus;
            store.wsConnecting = wsConnecting;
            store.latency = isFullyOnline ? resolvedLatency : null;
            store.wsLatency = isFullyOnline ? resolvedLatency : null;
            store.lastChecked = state.lastChecked;
        }

        window.dispatchEvent(new CustomEvent('pos:server-health-changed', {
            detail: { browserOnline, isServerConnected: serverConnected, hasInternet, connState, opStatus: (isFullyOnline ? 'connected' : 'offline'), reason }
        }));

        if (prevOnline !== isFullyOnline || prevServer !== serverConnected || prevBrowser !== browserOnline || prevInternet !== hasInternet) {
            window.dispatchEvent(new CustomEvent('pos:network-status', {
                detail: { isOnline: isFullyOnline, browserOnline, isServerConnected: serverConnected, hasInternet, status: (isFullyOnline ? 'connected' : 'offline'), reason }
            }));

            if (isFullyOnline && (!prevOnline || !prevServer || !prevInternet)) {
                if (window.PosOfflineSync && typeof window.PosOfflineSync.syncAll === 'function') {
                    setTimeout(() => {
                        window.PosOfflineSync.syncAll({ isManual: false });
                    }, 800);
                }
            }
        }
    }

    async function checkNetworkHealth(trigger = 'timer') {
        const browserOnline = typeof navigator !== 'undefined' ? navigator.onLine : true;

        if (!browserOnline) {
            if (activeCheckController) {
                try { activeCheckController.abort(); } catch (e) {}
                activeCheckController = null;
            }
            state.isChecking = false;
            applyConnectionState(false, false, 'offline', 'wifi_offline', null, false);
            scheduleNextHealthCheck(3000);
            return false;
        }

        if (state.isChecking) return;
        state.isChecking = true;

        if (window.Alpine && window.Alpine.store && window.Alpine.store('network')) {
            window.Alpine.store('network').isChecking = true;
        }

        const startTime = performance.now();
        activeCheckController = new AbortController();
        const timeoutMs = 5000;
        const timeoutId = setTimeout(() => {
            if (activeCheckController) activeCheckController.abort();
        }, timeoutMs);

        let serverOk = false;
        let serverHasInternet = false;
        let clientHasInternet = false;
        let httpLatency = null;

        try {
            let serverRtt = null;
            const serverProbe = (async () => {
                const sStart = performance.now();
                try {
                    const res = await fetch('/api/health?_t=' + Date.now(), {
                        method: 'GET',
                        cache: 'no-store',
                        headers: {
                            'Accept': 'application/json',
                            'Cache-Control': 'no-cache, no-store, must-revalidate',
                            'Pragma': 'no-cache'
                        },
                        signal: activeCheckController.signal
                    });
                    serverRtt = Math.max(1, Math.round(performance.now() - sStart));
                    if (!res.ok) return { ok: false, data: null, latency: serverRtt };
                    const data = await res.json().catch(() => null);
                    return { ok: true, data, latency: serverRtt };
                } catch (e) {
                    return { ok: false, data: null, latency: null };
                }
            })();

            const [clientInternetRes, serverRes] = await Promise.allSettled([
                probeInternet(1500),
                serverProbe
            ]);

            clearTimeout(timeoutId);

            clientHasInternet = (clientInternetRes.status === 'fulfilled') ? !!clientInternetRes.value : false;

            if (serverRes.status === 'fulfilled' && serverRes.value && serverRes.value.ok) {
                const data = serverRes.value.data;
                if (data && data.status === 'ok') {
                    serverOk = true;
                    serverHasInternet = !!data.has_internet;
                    httpLatency = serverRes.value.latency || serverRtt || 5;
                }
            }
        } catch (err) {
            clearTimeout(timeoutId);
            serverOk = false;
        } finally {
            activeCheckController = null;
            state.isChecking = false;
            if (window.Alpine && window.Alpine.store && window.Alpine.store('network')) {
                window.Alpine.store('network').isChecking = false;
            }
        }

        const currentBrowserOnline = typeof navigator !== 'undefined' ? navigator.onLine : true;
        const overallInternet = currentBrowserOnline && (clientHasInternet || serverHasInternet);

        if (serverOk && currentBrowserOnline && overallInternet) {
            consecutiveFailures = 0;
            applyConnectionState(true, true, 'connected', null, httpLatency, true);
            scheduleNextHealthCheck(8000);
            return true;
        } else {
            consecutiveFailures++;
            if (!currentBrowserOnline) {
                applyConnectionState(false, false, 'offline', 'wifi_offline', null, false);
                scheduleNextHealthCheck(3000);
                return false;
            }

            if (consecutiveFailures >= MAX_CONSECUTIVE_FAILURES) {
                const reason = !overallInternet ? 'no_internet' : 'server_offline';
                const opStatus = !overallInternet ? 'offline' : 'server_unavailable';
                applyConnectionState(currentBrowserOnline, false, opStatus, reason, null, overallInternet);
                scheduleNextHealthCheck(4000);
            } else {
                scheduleNextHealthCheck(1500);
            }
            return false;
        }
    }

    function scheduleNextHealthCheck(delayMs) {
        if (healthPollTimer) clearTimeout(healthPollTimer);
        healthPollTimer = setTimeout(() => {
            checkNetworkHealth('timer');
        }, delayMs);
    }

    // Real-time WebSocket Reverb Ping Synchronizer
    window.addEventListener('pos:ws-ping', (e) => {
        if (e.detail && typeof e.detail.latency === 'number' && state.isOnline) {
            state.latency = e.detail.latency;
            if (window.Alpine && window.Alpine.store && window.Alpine.store('network')) {
                const store = window.Alpine.store('network');
                store.latency = e.detail.latency;
                store.wsLatency = e.detail.latency;
                if (e.detail.quality) store.wsSpeedQuality = e.detail.quality;
                if (e.detail.timestamp) store.lastPingAt = e.detail.timestamp;
            }
        }
    });

    // Browser Physical Online / Offline Event Handlers
    window.addEventListener('offline', () => {
        console.warn('[PosRealtime] Physical network disconnected (offline event).');
        if (activeCheckController) {
            try { activeCheckController.abort(); } catch (e) {}
            activeCheckController = null;
        }
        state.isChecking = false;
        applyConnectionState(false, false, 'offline', 'wifi_offline', null, false);
        scheduleNextHealthCheck(3000);
    });

    window.addEventListener('online', () => {
        console.log('[PosRealtime] Physical network connected (online event). Probing server health...');
        if (window.Alpine && window.Alpine.store && window.Alpine.store('network')) {
            const store = window.Alpine.store('network');
            store.isBrowserOnline = true;
            store.wifiOnline = true;
            store.wsStatus = 'connecting';
            store.wsConnecting = true;
        }
        checkNetworkHealth('online_event');
    });

    // When user returns to tab, check network & server updates (throttled to avoid dialog close burst)
    let lastVisibilityCheckTime = 0;
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            const now = Date.now();
            if (now - lastVisibilityCheckTime > 5000) {
                lastVisibilityCheckTime = now;
                checkNetworkHealth('visibility');
                if (state.isOnline) {
                    pollServerUpdates();
                }
            }
        }
    });

    // Initial check deferred slightly so it doesn't compete with initial page APIs on single-worker dev servers
    setTimeout(() => {
        checkNetworkHealth('init');
    }, 400);

    // 5. Multi-Device Background Server Sync Poller
    // Silently checks server APIs for latest changes so different devices stay realtime
    async function pollServerUpdates() {
        if (!state.isOnline) return;

        const path = window.location.pathname;

        // A. If on Barang Masuk (Kasir) or Kirim Barang (Manager)
        if (path.includes('goods-in') || path.includes('kirim-barang') || path.includes('/kasir/produk/masuk')) {
            try {
                const c = new AbortController();
                const t = setTimeout(() => c.abort(), 2000);
                const isKasir = path.includes('goods-in') || path.includes('/kasir/produk/masuk');
                const branch = localStorage.getItem('pos_kasir_branch') || 'Pusat';
                const q = isKasir ? ('?branch=' + encodeURIComponent(branch)) : '';
                const storageKey = isKasir ? ('pos_ilp_goods_in_docs_v1_' + branch) : 'pos_ilp_goods_in_docs_v1';

                const res = await fetch('/api/goods-receipts' + q, { headers: { 'Accept': 'application/json' }, signal: c.signal });
                clearTimeout(t);
                if (res.ok) {
                    const json = await res.json();
                    if (json.success && Array.isArray(json.data)) {
                        const localRaw = localStorage.getItem(storageKey);
                        const localDocs = localRaw ? JSON.parse(localRaw) : [];
                        
                        const localLatestId = localDocs.length > 0 ? localDocs[0].id : null;
                        const serverLatestId = json.data.length > 0 ? json.data[0].id : null;
                        const localCount = localDocs.length;
                        const serverCount = json.data.length;

                        if (localCount !== serverCount || localLatestId !== serverLatestId) {
                            originalSetItem(storageKey, JSON.stringify(json.data));
                            PosRealtime.broadcast('goods_in_updated', { source: 'server_poll', branch: (isKasir ? branch : null), documents: json.data });
                        }
                    }
                }
            } catch (e) {}
        }

        // B. If on POS or Inventory, check product updates
        if (path.includes('/pos') || path.includes('/inventory')) {
            try {
                const c = new AbortController();
                const t = setTimeout(() => c.abort(), 2000);
                const res = await fetch('/api/pos/products/search?limit=10', { headers: { 'Accept': 'application/json' }, signal: c.signal });
                clearTimeout(t);
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

        broadcast(type, data = {}) {
            const payload = {
                type: type,
                entity: data.entity || type.replace(/_updated$/, ''),
                data: data,
                timestamp: Date.now()
            };

            if (broadcastChannel) {
                try {
                    broadcastChannel.postMessage(payload);
                } catch (e) {
                    console.warn('BroadcastChannel post error:', e);
                }
            }

            handleIncomingMessage(payload, true);
        },

        notifyChange(entity, extra = {}) {
            this.broadcast(entity + '_updated', { entity, ...extra });
        },

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

        markOnline(reason = 'external_signal') {
            consecutiveFailures = 0;
            applyConnectionState(true, true, 'connected', null, state.latency || 2);
        },

        async syncNow() {
            state.isChecking = true;
            if (window.Alpine && window.Alpine.store && window.Alpine.store('network')) {
                window.Alpine.store('network').isChecking = true;
            }

            try {
                await checkNetworkHealth('sync_now');
                await pollServerUpdates();
                
                window.dispatchEvent(new CustomEvent('ilp-sync-data'));
                window.dispatchEvent(new CustomEvent('pos:sync-all'));
                window.dispatchEvent(new CustomEvent('pos-inventory-synced'));

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

        const curWsState = (window.Echo && window.Echo.connector && window.Echo.connector.pusher && window.Echo.connector.pusher.connection && window.Echo.connector.pusher.connection.state) 
            || (window.posReverbState ? window.posReverbState.status : 'connecting');
        const isWsConnected = (curWsState === 'connected') && state.isOnline && state.hasInternet;
        const isWsConnecting = (curWsState === 'connecting') && state.isOnline && state.hasInternet;
        const initialLatency = (isWsConnected && typeof window.posReverbState?.latency === 'number')
            ? window.posReverbState.latency
            : state.latency;

        // Network Store
        if (!window.Alpine.store('network')) {
            window.Alpine.store('network', {
                isOnline: state.isOnline,
                isOffline: !state.isOnline,
                isBrowserOnline: state.isBrowserOnline,
                isServerConnected: state.isServerConnected,
                connectionState: state.connectionState,
                operationalStatus: state.operationalStatus,
                operationalLabel: state.operationalLabel,
                isReconnecting: state.isReconnecting,
                offlineReason: state.offlineReason,
                wifiOnline: state.isBrowserOnline,
                latency: initialLatency,
                wsLatency: window.posReverbState?.latency ?? null,
                wsSpeedQuality: window.posReverbState?.wsSpeedQuality ?? null,
                lastPingAt: window.posReverbState?.lastPingAt ?? null,
                status: state.status,
                lastChecked: state.lastChecked,
                isChecking: false,
                wsConnected: isWsConnected,
                wsStatus: isWsConnected ? 'connected' : (state.isOnline ? curWsState : (state.isBrowserOnline ? 'unavailable' : 'disconnected')),
                wsConnecting: isWsConnecting,
                syncNow() {
                    return PosRealtime.syncNow();
                }
            });
        } else {
            const net = window.Alpine.store('network');
            net.isOnline = state.isOnline;
            net.isOffline = !state.isOnline;
            net.isBrowserOnline = state.isBrowserOnline;
            net.isServerConnected = state.isServerConnected;
            net.connectionState = state.connectionState;
            net.operationalStatus = state.operationalStatus;
            net.operationalLabel = state.operationalLabel;
            net.wsConnected = isWsConnected;
            net.wsStatus = isWsConnected ? 'connected' : (state.isOnline ? curWsState : (state.isBrowserOnline ? 'unavailable' : 'disconnected'));
            net.wsConnecting = isWsConnecting;
            if (isWsConnected && typeof window.posReverbState?.latency === 'number') {
                net.latency = window.posReverbState.latency;
                net.wsLatency = window.posReverbState.latency;
                net.wsSpeedQuality = window.posReverbState.wsSpeedQuality;
            }
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

    window.addEventListener('pos:ws-status-changed', function(e) {
        const browserOnline = typeof navigator !== 'undefined' ? navigator.onLine : true;
        const serverConnected = state.isServerConnected;

        if (window.Alpine && window.Alpine.store && window.Alpine.store('network')) {
            const net = window.Alpine.store('network');
            if (!browserOnline || !serverConnected || !state.hasInternet) {
                net.wsConnected = false;
                net.wsStatus = (!browserOnline || !state.hasInternet) ? 'disconnected' : 'unavailable';
                net.wsConnecting = false;
                net.latency = null;
                net.wsLatency = null;
            } else {
                net.wsConnected = !!e.detail.isConnected;
                net.wsStatus = e.detail.state;
                net.wsConnecting = !!e.detail.isConnecting;
            }
        }
    });

    if (window.Alpine) {
        initAlpineStores();
    } else {
        document.addEventListener('alpine:init', initAlpineStores);
    }
})();
