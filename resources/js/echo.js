import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

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

    // Connection event listeners
    if (window.Echo.connector && window.Echo.connector.pusher) {
        window.Echo.connector.pusher.connection.bind('connected', () => {
            console.log('[Reverb/WebSocket] Connected successfully to Reverb server.');
            if (window.Alpine && window.Alpine.store && window.Alpine.store('network')) {
                window.Alpine.store('network').wsConnected = true;
                window.Alpine.store('network').wsStatus = 'connected';
            }
            window.dispatchEvent(new CustomEvent('pos:ws-connected'));
        });

        window.Echo.connector.pusher.connection.bind('disconnected', () => {
            console.warn('[Reverb/WebSocket] Disconnected from Reverb server.');
            if (window.Alpine && window.Alpine.store && window.Alpine.store('network')) {
                window.Alpine.store('network').wsConnected = false;
                window.Alpine.store('network').wsStatus = 'disconnected';
            }
            window.dispatchEvent(new CustomEvent('pos:ws-disconnected'));
        });

        window.Echo.connector.pusher.connection.bind('unavailable', () => {
            console.warn('[Reverb/WebSocket] Reverb server is unavailable. Will fallback to HTTP polling.');
            if (window.Alpine && window.Alpine.store && window.Alpine.store('network')) {
                window.Alpine.store('network').wsConnected = false;
                window.Alpine.store('network').wsStatus = 'unavailable';
            }
        });
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

} catch (err) {
    console.warn('[Reverb/WebSocket] Could not initialize Echo listener:', err);
}
