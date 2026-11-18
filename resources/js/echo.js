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
    const hasInternet = (window.posNetworkHealth && typeof window.posNetworkHealth.hasInternet === 'boolean')
        ? window.posNetworkHealth.hasInternet
        : true;
    const isBrowserOnline = typeof navigator !== 'undefined' ? navigator.onLine : true;

    if (!hasInternet || !isBrowserOnline || !window.posReverbState.wsConnected) {
        window.posReverbState.latency = null;
        if (window.Alpine && window.Alpine.store && window.Alpine.store('network')) {
            const net = window.Alpine.store('network');
            net.latency = null;
            net.wsLatency = null;
        }
        return;
    }

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

function sendReverbPing() {
    const hasInternet = (window.posNetworkHealth && typeof window.posNetworkHealth.hasInternet === 'boolean')
        ? window.posNetworkHealth.hasInternet
        : true;
    const isBrowserOnline = typeof navigator !== 'undefined' ? navigator.onLine : true;

    if (!hasInternet || !isBrowserOnline || !window.posReverbState.wsConnected) {
        stopReverbPingMonitor();
        return;
    }

    if (!window.Echo || !window.Echo.connector || !window.Echo.connector.pusher) return;
    const pusher = window.Echo.connector.pusher;
    if (!pusher.connection || pusher.connection.state !== 'connected') return;

    // Reset hung ping if previous pong exceeded 2.5s
    if (isPinging && pingStartTime && performance.now() - pingStartTime > 2500) {
        isPinging = false;
    }

    if (isPinging) return;

    isPinging = true;
    pingStartTime = performance.now();

    try {
        pusher.send_event('pusher:ping', {});
    } catch (err) {
        isPinging = false;
    }
}

function startReverbPingMonitor() {
    stopReverbPingMonitor();
    sendReverbPing();
    pingTimer = setInterval(() => {
        sendReverbPing();
    }, 1000);
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
    const isBrowserOnline = typeof navigator !== 'undefined' ? navigator.onLine : true;
    const isServerReachable = (window.posNetworkHealth && typeof window.posNetworkHealth.isServerConnected === 'boolean')
        ? window.posNetworkHealth.isServerConnected
        : true;
    const hasInternet = (window.posNetworkHealth && typeof window.posNetworkHealth.hasInternet === 'boolean')
        ? window.posNetworkHealth.hasInternet
        : true;

    // Force disconnected/unavailable if physical network is down, server is unreachable, OR internet is absent
    let effectiveState = state;
    if (!isBrowserOnline || !hasInternet) {
        effectiveState = 'disconnected';
    } else if (!isServerReachable && state === 'connected') {
        effectiveState = 'unavailable';
    }

    const isConnected = effectiveState === 'connected' && isBrowserOnline && isServerReachable && hasInternet;
    const isConnecting = effectiveState === 'connecting' && isBrowserOnline && isServerReachable && hasInternet;
    const isDisconnected = !isConnected && !isConnecting;

    window.posReverbState.status = effectiveState;
    window.posReverbState.wsConnected = isConnected;
    window.posReverbState.wsConnecting = isConnecting;
    window.posReverbState.wsDisconnected = isDisconnected;

    if (window.Alpine && window.Alpine.store && window.Alpine.store('network')) {
        const net = window.Alpine.store('network');
        net.wsConnected = isConnected;
        net.wsStatus = effectiveState;
        net.wsConnecting = isConnecting;
        if (isDisconnected) {
            net.latency = null;
            net.wsLatency = null;
        }
    }

    window.dispatchEvent(new CustomEvent('pos:ws-status-changed', {
        detail: { state: effectiveState, isConnected, isConnecting, isDisconnected }
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

// Instant physical network offline listener
window.addEventListener('offline', () => {
    console.warn('[Reverb/WebSocket] Physical network offline detected. Halting WebSocket.');
    stopReverbPingMonitor();
    if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
        try {
            window.Echo.connector.pusher.disconnect();
        } catch (e) {}
    }
    updateReverbStatus('disconnected');
});

// Physical network online listener
window.addEventListener('online', () => {
    console.log('[Reverb/WebSocket] Physical network online detected.');
    updateReverbStatus('connecting');
});

// Server availability listener from pos-realtime health check probe
window.addEventListener('pos:server-health-changed', (e) => {
    const isServerOk = !!e.detail?.isServerConnected;
    const isBrowserOnline = typeof navigator !== 'undefined' ? navigator.onLine : true;
    const hasInternet = (typeof e.detail?.hasInternet === 'boolean') ? e.detail.hasInternet : true;

    if (!isBrowserOnline || !isServerOk || !hasInternet) {
        stopReverbPingMonitor();
        if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
            try {
                window.Echo.connector.pusher.disconnect();
            } catch (e) {}
        }
        updateReverbStatus((!isBrowserOnline || !hasInternet) ? 'disconnected' : 'unavailable');
    } else {
        if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
            const curState = window.Echo.connector.pusher.connection.state;
            if (curState !== 'connected' && curState !== 'connecting') {
                try {
                    window.Echo.connector.pusher.connect();
                } catch (e) {}
            }
        }
    }
});

try {
    const reverbKey = import.meta.env.VITE_REVERB_APP_KEY || 'poskasirilpkey123';
    const rawHost = import.meta.env.VITE_REVERB_HOST || (typeof window !== 'undefined' ? window.location.hostname : '127.0.0.1');
    const reverbHost = rawHost === 'localhost' ? '127.0.0.1' : rawHost;
    const reverbPort = import.meta.env.VITE_REVERB_PORT ? parseInt(import.meta.env.VITE_REVERB_PORT, 10) : 8080;
    const reverbScheme = import.meta.env.VITE_REVERB_SCHEME || 'http';
    const forceTLS = reverbScheme === 'https';

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
        scheme: reverbScheme,
    });

    // Real-time connection lifecycle event listeners
    if (window.Echo.connector && window.Echo.connector.pusher) {
        const connection = window.Echo.connector.pusher.connection;

        // 1. Precise state transition handling
        connection.bind('state_change', (states) => {
            console.log('[Reverb/WebSocket] State change:', states.previous, '->', states.current);
            const hasInternet = (window.posNetworkHealth && typeof window.posNetworkHealth.hasInternet === 'boolean')
                ? window.posNetworkHealth.hasInternet
                : true;
            const isBrowserOnline = typeof navigator !== 'undefined' ? navigator.onLine : true;

            if ((!hasInternet || !isBrowserOnline) && states.current === 'connected') {
                try { window.Echo.connector.pusher.disconnect(); } catch (e) {}
                updateReverbStatus('disconnected');
            } else {
                updateReverbStatus(states.current);
            }
        });

        connection.bind('connected', () => {
            const hasInternet = (window.posNetworkHealth && typeof window.posNetworkHealth.hasInternet === 'boolean')
                ? window.posNetworkHealth.hasInternet
                : true;
            const isBrowserOnline = typeof navigator !== 'undefined' ? navigator.onLine : true;

            if (!hasInternet || !isBrowserOnline) {
                console.warn('[Reverb/WebSocket] Pusher reported connected on loopback, but internet/physical network is down. Forcing disconnected.');
                try {
                    window.Echo.connector.pusher.disconnect();
                } catch (e) {}
                updateReverbStatus('disconnected');
                return;
            }

            console.log('[Reverb/WebSocket] Connected successfully to Reverb server.');
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

} catch (err) {
    console.warn('[Reverb/WebSocket] Could not initialize Echo listener:', err);
}
