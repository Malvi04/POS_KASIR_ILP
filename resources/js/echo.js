import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Global state container for immediate zero-delay status lookup
window.posReverbState = {
    status: 'connecting',
    wsConnected: false,
    wsConnecting: true,
    latency: null,
    wsSpeedQuality: null,
    lastPingAt: null,
};

// -------------------------------------------------------------------------
// Real-Time Reverb WebSocket Latency / Ping Monitor (1-second interval)
// -------------------------------------------------------------------------
let pingTimer = null;
let pingStartTime = null;
let isPinging = false;

function getSpeedQuality(latencyMs) {
    if (latencyMs < 50) return 'optimal';
    if (latencyMs < 150) return 'good';
    if (latencyMs <= 300) return 'fair';
    return 'slow';
}

function recordLatency(latencyMs) {
    const quality = getSpeedQuality(latencyMs);
    window.posReverbState.latency = latencyMs;
    window.posReverbState.wsSpeedQuality = quality;
    window.posReverbState.lastPingAt = Date.now();

    if (window.Alpine && window.Alpine.store && window.Alpine.store('network')) {
        const net = window.Alpine.store('network');
        net.latency = latencyMs;
        net.wsLatency = latencyMs;
        net.wsSpeedQuality = quality;
        net.lastPingAt = window.posReverbState.lastPingAt;
    }

    window.dispatchEvent(new CustomEvent('pos:ws-ping', {
        detail: { latency: latencyMs, quality, timestamp: window.posReverbState.lastPingAt }
    }));
}

function hookRawWebSocket() {
    try {
        const socket = window.Echo?.connector?.pusher?.connection?.transport?.socket;
        if (socket && !socket.__reverbPingHooked) {
            socket.__reverbPingHooked = true;
            socket.addEventListener('message', (e) => {
                try {
                    const data = typeof e.data === 'string' ? JSON.parse(e.data) : null;
                    if (data && (data.event === 'pusher:pong' || data.event === 'pong')) {
                        if (isPinging && pingStartTime) {
                            const rtt = Math.max(1, Math.round(performance.now() - pingStartTime));
                            isPinging = false;
                            pingStartTime = null;
                            recordLatency(rtt);
                        }
                    }
                } catch (err) {}
            });
        }
    } catch (err) {}
}

async function measureFallbackHttpPing() {
    if (typeof navigator !== 'undefined' && !navigator.onLine) return;
    const t0 = performance.now();
    try {
        const res = await fetch('/api/ping?_t=' + Date.now(), {
            method: 'GET',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        });
        if (res.ok) {
            const rtt = Math.max(1, Math.round(performance.now() - t0));
            const lastPingAge = Date.now() - (window.posReverbState.lastPingAt || 0);
            if (lastPingAge > 2500 || window.posReverbState.latency === null) {
                recordLatency(rtt);
            }
        }
    } catch (err) {}
}

function sendReverbPing() {
    if (!window.Echo || !window.Echo.connector || !window.Echo.connector.pusher) return;
    const pusher = window.Echo.connector.pusher;
    if (!pusher.connection || pusher.connection.state !== 'connected') return;

    hookRawWebSocket();

    // Reset hung ping if previous pong exceeded 2s
    if (isPinging && pingStartTime && performance.now() - pingStartTime > 2000) {
        isPinging = false;
        // Fallback to instant server HTTP ping
        measureFallbackHttpPing();
    }

    if (isPinging) return;

    isPinging = true;
    pingStartTime = performance.now();

    try {
        const socket = pusher.connection?.transport?.socket;
        if (socket && socket.readyState === WebSocket.OPEN) {
            socket.send(JSON.stringify({ event: 'pusher:ping', data: {} }));
        } else {
            pusher.send_event('pusher:ping', {});
        }
    } catch (err) {
        isPinging = false;
        measureFallbackHttpPing();
    }
}

function startReverbPingMonitor() {
    stopReverbPingMonitor();
    hookRawWebSocket();
    sendReverbPing();
    measureFallbackHttpPing();
    pingTimer = setInterval(() => {
        sendReverbPing();
    }, 1500);
}

function stopReverbPingMonitor() {
    if (pingTimer) {
        clearInterval(pingTimer);
        pingTimer = null;
    }
    isPinging = false;
    pingStartTime = null;
}

function updateReverbStatus(state) {
    const isConnected = state === 'connected';
    const isConnecting = state === 'connecting';
    const isDisconnected = !isConnected && !isConnecting;

    window.posReverbState.status = state;
    window.posReverbState.wsConnected = isConnected;
    window.posReverbState.wsConnecting = isConnecting;
    window.posReverbState.wsDisconnected = isDisconnected;

    if (window.Alpine && window.Alpine.store && window.Alpine.store('network')) {
        const net = window.Alpine.store('network');
        net.wsConnected = isConnected;
        net.wsStatus = state;
        net.wsConnecting = isConnecting;
        if (isDisconnected) {
            net.latency = null;
            net.wsLatency = null;
        }
    }

    window.dispatchEvent(new CustomEvent('pos:ws-status-changed', {
        detail: { state, isConnected, isConnecting, isDisconnected }
    }));

    if (isConnected) {
        startReverbPingMonitor();
        window.dispatchEvent(new CustomEvent('pos:ws-connected'));
    } else if (isDisconnected) {
        stopReverbPingMonitor();
        window.posReverbState.latency = null;
        window.dispatchEvent(new CustomEvent('pos:ws-disconnected'));
    }
}

try {
    const reverbKey = import.meta.env.VITE_REVERB_APP_KEY || 'poskasirilpkey123';
    const isBrowser = typeof window !== 'undefined';
    const browserHost = isBrowser && window.location.hostname ? window.location.hostname : '127.0.0.1';
    const isLocalhost = browserHost === 'localhost' || browserHost === '127.0.0.1';

    // In production/VPS, dynamically connect to current host via standard port 80/443 (proxied via Nginx)
    // In local development, connect to 127.0.0.1:8080
    const reverbHost = isLocalhost ? (import.meta.env.VITE_REVERB_HOST || '127.0.0.1') : browserHost;
    const isHttps = isBrowser ? window.location.protocol === 'https:' : false;

    let reverbPort = 8080;
    if (isLocalhost) {
        reverbPort = import.meta.env.VITE_REVERB_PORT ? parseInt(import.meta.env.VITE_REVERB_PORT, 10) : 8080;
    } else {
        reverbPort = window.location.port ? parseInt(window.location.port, 10) : (isHttps ? 443 : 80);
    }
    const forceTLS = isHttps;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS: forceTLS,
        enabledTransports: ['ws', 'wss'],
        activityTimeout: 30000,
        pongTimeout: 10000,
    });

    console.log('[Reverb/WebSocket] Client initialized:', {
        key: reverbKey,
        host: reverbHost,
        port: reverbPort,
        scheme: isHttps ? 'https' : 'http',
    });

    // Real-time connection lifecycle event listeners
    if (window.Echo.connector && window.Echo.connector.pusher) {
        const connection = window.Echo.connector.pusher.connection;

        // 1. Precise state transition handling
        connection.bind('state_change', (states) => {
            console.log('[Reverb/WebSocket] State change:', states.previous, '->', states.current);
            updateReverbStatus(states.current);
        });

        connection.bind('connected', () => {
            console.log('[Reverb/WebSocket] Connected successfully to Reverb server.');
            hookRawWebSocket();
            updateReverbStatus('connected');
        });

        connection.bind('connecting', () => {
            console.log('[Reverb/WebSocket] Connecting to Reverb server...');
            updateReverbStatus('connecting');
        });

        connection.bind('disconnected', () => {
            console.warn('[Reverb/WebSocket] Disconnected from Reverb server.');
            updateReverbStatus('disconnected');
        });

        connection.bind('unavailable', () => {
            console.warn('[Reverb/WebSocket] Reverb server is unavailable.');
            updateReverbStatus('unavailable');
        });

        connection.bind('failed', () => {
            console.error('[Reverb/WebSocket] Connection to Reverb failed.');
            updateReverbStatus('failed');
        });

        // 2. Real-time WebSocket ping/pong latency listener
        connection.bind('message', (msg) => {
            if (msg && (msg.event === 'pusher:pong' || msg.event === 'pong')) {
                if (isPinging && pingStartTime) {
                    const rtt = Math.max(1, Math.round(performance.now() - pingStartTime));
                    isPinging = false;
                    pingStartTime = null;
                    recordLatency(rtt);
                }
            }
        });

        connection.bind('pong', () => {
            if (isPinging && pingStartTime) {
                const rtt = Math.max(1, Math.round(performance.now() - pingStartTime));
                isPinging = false;
                pingStartTime = null;
                recordLatency(rtt);
            }
        });

        // Initialize immediately with current state if already active
        if (connection.state) {
            updateReverbStatus(connection.state);
        }
    }

    // 1. Notifications Channel
    window.Echo.channel('notifications')
        .listen('.notification.created', (e) => {
            console.log('[Reverb/WebSocket] New notification received:', e);
            if (e && e.notification) {
                window.dispatchEvent(new CustomEvent('ilp-notify', { detail: e.notification }));
                window.dispatchEvent(new CustomEvent('pos:notification', { detail: e.notification }));
            }
        });

    // 2. POS Transactions Channel
    window.Echo.channel('pos-transactions')
        .listen('.transaction.created', (e) => {
            console.log('[Reverb/WebSocket] New transaction received:', e);
            if (e && e.transaction) {
                window.dispatchEvent(new CustomEvent('pos:transaction', { detail: e.transaction }));
                window.dispatchEvent(new CustomEvent('pos:realtime', {
                    detail: { type: 'transactions_updated', entity: 'transactions', data: e.transaction }
                }));
            }
        });

    // 3. Inventory Channel
    window.Echo.channel('inventory')
        .listen('.stock.updated', (e) => {
            console.log('[Reverb/WebSocket] Inventory stock update received:', e);
            window.dispatchEvent(new CustomEvent('pos:inventory-updated', { detail: e.stock }));
            window.dispatchEvent(new CustomEvent('pos:realtime', {
                detail: { type: 'inventory_updated', entity: 'inventory', data: e.stock }
            }));
        });

    // 4. Cashier Shifts Channel
    window.Echo.channel('cashier-shifts')
        .listen('.shift.opened', (e) => {
            console.log('[Reverb/WebSocket] Shift opened:', e);
            window.dispatchEvent(new CustomEvent('pos:shift-opened', { detail: e.shift }));
            window.dispatchEvent(new CustomEvent('pos:realtime', {
                detail: { type: 'session_updated', entity: 'session', data: e.shift }
            }));
        })
        .listen('.shift.closed', (e) => {
            console.log('[Reverb/WebSocket] Shift closed:', e);
            window.dispatchEvent(new CustomEvent('pos:shift-closed', { detail: e.shift }));
            window.dispatchEvent(new CustomEvent('pos:realtime', {
                detail: { type: 'session_updated', entity: 'session', data: e.shift }
            }));
        });

    // 5. Real-time Attendance Channel
    window.Echo.channel('attendance')
        .listen('.attendance.recorded', (e) => {
            console.log('[Reverb/WebSocket] Attendance recorded event:', e);
            if (e && e.attendance) {
                window.dispatchEvent(new CustomEvent('pos:attendance-recorded', { detail: e.attendance }));
                window.dispatchEvent(new CustomEvent('pos:realtime', {
                    detail: { type: 'attendance_updated', entity: 'attendance', data: e.attendance }
                }));
            }
        });

    // 6. Real-time Coupons Channel
    window.Echo.channel('coupons')
        .listen('.coupon.updated', (e) => {
            console.log('[Reverb/WebSocket] Coupon update event received:', e);
            window.dispatchEvent(new CustomEvent('pos:coupons_updated', { detail: e }));
            window.dispatchEvent(new CustomEvent('pos:entity:coupons', { detail: e }));
            window.dispatchEvent(new CustomEvent('pos:realtime', {
                detail: { type: 'coupons_updated', entity: 'coupons', data: e }
            }));
        });

} catch (err) {
    console.warn('[Reverb/WebSocket] Could not initialize Echo listener:', err);
}
