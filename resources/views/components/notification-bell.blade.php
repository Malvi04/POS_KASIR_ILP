@props([
    'role' => null
])

@php
    $currentPath = request()->path();
    $inferredRole = 'ALL';
    if ($role) {
        $inferredRole = strtoupper($role);
    } elseif (str_starts_with($currentPath, 'kasir') || str_starts_with($currentPath, 'cashier') || in_array($currentPath, ['pos', 'kupon', 'proses-kredit', 'select-shift', 'cetak-struk'])) {
        $inferredRole = 'KASIR';
    } elseif (str_starts_with($currentPath, 'manager')) {
        $inferredRole = 'MANAGER';
    } elseif (str_starts_with($currentPath, 'owner') || str_starts_with($currentPath, 'admin')) {
        $inferredRole = 'OWNER';
    }
@endphp

<div class="relative"
     x-data="ilpNotificationBell({ role: '{{ $inferredRole }}' })"
     x-init="init()"
     @click.outside="isOpen = false"
     @keydown.escape.window="isOpen = false">

    <!-- Bell Trigger Button -->
    <button type="button"
            @click="toggleDropdown()"
            class="p-2 rounded-xl text-gray-500 hover:text-gray-900 hover:bg-gray-100 transition-colors relative flex items-center justify-center cursor-pointer focus:outline-none"
            :class="isOpen ? 'bg-gray-100 text-gray-900' : ''"
            title="Notifikasi Aktivitas Realtime">
        <span class="material-symbols-rounded text-[22px]">notifications</span>

        <!-- Unread Badge Ping & Counter -->
        <template x-if="unreadCount > 0">
            <span class="absolute top-1 right-1 flex h-4 min-w-4 px-1 items-center justify-center">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3.5 min-w-3.5 px-1 bg-red-600 text-white text-[9px] font-black items-center justify-center leading-none"
                      x-text="unreadCount > 9 ? '9+' : unreadCount"></span>
            </span>
        </template>
    </button>

    <!-- Notification Dropdown Menu Popover -->
    <div x-show="isOpen"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-2 scale-95"
         class="absolute right-0 mt-2.5 w-[360px] sm:w-[420px] bg-white rounded-2xl shadow-2xl border border-gray-200/90 py-0 z-50 overflow-hidden text-gray-800"
         style="display: none;">

        <!-- Header -->
        <div class="px-5 py-3.5 bg-gray-50/80 border-b border-gray-200/80 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-[#0052cc] text-white flex items-center justify-center shadow-2xs">
                    <span class="material-symbols-rounded text-[18px]">notifications</span>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-gray-900 tracking-tight leading-none">
                        Notifikasi Realtime
                        <span class="text-[11px] font-semibold text-gray-500" x-text="'(' + role + ')'"></span>
                    </h3>
                    <p class="text-[10.5px] text-gray-400 mt-0.5" x-text="unreadCount + ' belum dibaca'"></p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <template x-if="unreadCount > 0">
                    <button type="button"
                            @click="markAllAsRead()"
                            class="text-[11px] font-bold text-[#0052cc] hover:text-[#0043a8] hover:underline cursor-pointer">
                        Tandai Dibaca
                    </button>
                </template>
                <button type="button"
                        @click="isOpen = false"
                        class="p-1 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-200/50 cursor-pointer">
                    <span class="material-symbols-rounded text-[18px]">close</span>
                </button>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="px-4 py-2 border-b border-gray-100 flex items-center gap-1.5 bg-white text-xs">
            <button type="button"
                    @click="filterMode = 'all'"
                    :class="filterMode === 'all' ? 'bg-[#0052cc] text-white font-bold' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    class="px-3 py-1 rounded-full text-[11px] transition-colors cursor-pointer">
                Semua (<span x-text="notifications.length"></span>)
            </button>
            <button type="button"
                    @click="filterMode = 'unread'"
                    :class="filterMode === 'unread' ? 'bg-[#0052cc] text-white font-bold' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    class="px-3 py-1 rounded-full text-[11px] transition-colors cursor-pointer">
                Belum Dibaca (<span x-text="unreadCount"></span>)
            </button>
            <div class="flex-1"></div>
            <button type="button"
                    @click="fetchNotifications(true)"
                    :disabled="isLoading"
                    class="p-1 rounded text-gray-400 hover:text-gray-600 hover:bg-gray-100 cursor-pointer transition-transform"
                    :class="isLoading ? 'animate-spin' : ''"
                    title="Segarkan Notifikasi">
                <span class="material-symbols-rounded text-[16px]">sync</span>
            </button>
        </div>

        <!-- Notifications List -->
        <div class="max-h-[380px] overflow-y-auto divide-y divide-gray-100/80 bg-white">
            <template x-if="filteredNotifications.length === 0">
                <div class="py-12 px-6 text-center text-gray-400">
                    <div class="w-12 h-12 rounded-2xl bg-gray-50 border border-gray-100 text-gray-400 mx-auto flex items-center justify-center mb-3">
                        <span class="material-symbols-rounded text-[26px]">notifications_paused</span>
                    </div>
                    <p class="text-xs font-bold text-gray-700">Tidak ada notifikasi baru</p>
                    <p class="text-[11px] text-gray-400 mt-1" x-text="filterMode === 'unread' ? 'Semua notifikasi untuk role ' + role + ' telah dibaca.' : 'Belum ada aktivitas yang dicatat.'"></p>
                </div>
            </template>

            <template x-for="item in filteredNotifications" :key="item.id">
                <div @click="handleItemClick(item)"
                     class="p-3.5 flex items-start gap-3 transition-colors cursor-pointer group hover:bg-blue-50/40 relative"
                     :class="item.is_read ? 'opacity-70 bg-white' : 'bg-blue-50/20 font-medium'">

                    <!-- Icon with colored badge -->
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 shadow-2xs mt-0.5"
                         :class="{
                             'bg-amber-100 text-amber-700': item.color === 'amber',
                             'bg-emerald-100 text-emerald-700': item.color === 'emerald',
                             'bg-rose-100 text-rose-700': item.color === 'rose',
                             'bg-blue-100 text-blue-700': item.color === 'blue',
                             'bg-purple-100 text-purple-700': item.color === 'purple'
                         }">
                        <span class="material-symbols-rounded text-[18px]" x-text="item.icon || 'notifications'"></span>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0 pr-1">
                        <div class="flex items-center justify-between gap-2 mb-0.5">
                            <h4 class="text-xs font-bold text-gray-900 truncate flex items-center gap-1.5">
                                <template x-if="!item.is_read">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#0052cc] shrink-0"></span>
                                </template>
                                <span x-text="item.title"></span>
                            </h4>
                            <span class="text-[10px] text-gray-400 shrink-0 font-normal" x-text="item.time_ago"></span>
                        </div>

                        <p class="text-[11px] text-gray-600 leading-snug line-clamp-2" x-text="item.message"></p>

                        <!-- Footer tags -->
                        <div class="flex items-center gap-2 mt-1.5">
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-gray-100 text-gray-600 border border-gray-200"
                                  x-text="item.role"></span>
                            <template x-if="item.reference_id">
                                <span class="text-[10px] font-mono text-gray-400" x-text="'#' + item.reference_id"></span>
                            </template>
                            <template x-if="item.link">
                                <span class="text-[10px] font-bold text-[#0052cc] group-hover:underline flex items-center gap-0.5 ml-auto">
                                    <span>Buka</span>
                                    <span class="material-symbols-rounded text-[12px]">chevron_right</span>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Footer -->
        <div class="px-5 py-2.5 bg-gray-50/90 border-t border-gray-100 flex items-center justify-between text-[11px]">
            <span class="text-gray-400">Sinkronisasi otomatis realtime aktif</span>
            <template x-if="notifications.length > 0">
                <button type="button"
                        @click="clearAllNotifications()"
                        class="text-red-600 hover:text-red-700 hover:underline font-bold cursor-pointer">
                    Bersihkan Riwayat
                </button>
            </template>
        </div>
    </div>
</div>

<script>
if (typeof window.ilpNotificationBell === 'undefined') {
    window.ilpNotificationBell = function(config) {
        return {
            role: config.role || 'ALL',
            isOpen: false,
            filterMode: 'all', // 'all' | 'unread'
            isLoading: false,
            notifications: [],
            unreadCount: 0,
            pollingTimer: null,

            get filteredNotifications() {
                if (this.filterMode === 'unread') {
                    return this.notifications.filter(n => !n.is_read);
                }
                return this.notifications;
            },

            init() {
                // Initial load
                this.fetchNotifications();

                // Gentle fallback polling if WebSocket is not active (35s interval instead of 8s flood)
                if (!this.pollingTimer) {
                    this.pollingTimer = setInterval(() => {
                        const isWsActive = window.Alpine && window.Alpine.store && window.Alpine.store('network') && window.Alpine.store('network').wsConnected;
                        if (!isWsActive) {
                            this.fetchNotifications(false);
                        }
                    }, 35000);
                }

                // Listen for custom event triggered anywhere in application
                window.addEventListener('ilp-notify', (e) => {
                    const detail = e.detail;
                    if (detail) {
                        this.handleIncomingRealtime(detail);
                    }
                });

                // Cross-tab sync listener
                window.addEventListener('storage', (e) => {
                    if (e.key === 'pos_ilp_notify_sync_v2') {
                        this.fetchNotifications(false);
                    }
                });
            },

            toggleDropdown() {
                this.isOpen = !this.isOpen;
                if (this.isOpen) {
                    this.fetchNotifications(true);
                }
            },

            async fetchNotifications(showLoading = false) {
                if (showLoading) this.isLoading = true;
                try {
                    const res = await fetch(`/api/notifications?role=${encodeURIComponent(this.role)}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (res.ok) {
                        const json = await res.json();
                        if (json.success && Array.isArray(json.data)) {
                            // Detect if there is a newly added unread notification
                            const prevIds = new Set(this.notifications.map(n => n.id));
                            const newlyAdded = json.data.filter(n => !prevIds.has(n.id) && !n.is_read);

                            this.notifications = json.data;
                            this.unreadCount = json.unread_count || 0;

                            // If new unread notification arrived and this is not initial load, trigger notification sound & toast
                            if (prevIds.size > 0 && newlyAdded.length > 0) {
                                newlyAdded.forEach(n => {
                                    this.playChimeSound();
                                    if (window.triggerToast) {
                                        window.triggerToast(`🔔 [${n.role}] ${n.title}: ${n.message}`, 'info');
                                    }
                                });
                            }
                        }
                    }
                } catch(e) {
                    console.warn('Gagal memuat notifikasi:', e);
                } finally {
                    if (showLoading) this.isLoading = false;
                }
            },

            async handleItemClick(item) {
                // Mark item as read
                if (!item.is_read) {
                    item.is_read = true;
                    this.unreadCount = Math.max(0, this.unreadCount - 1);
                    try {
                        await fetch(`/api/notifications/mark-read/${item.id}`, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                            }
                        });
                        localStorage.setItem('pos_ilp_notify_sync_v2', Date.now().toString());
                    } catch(e) {}
                }

                this.isOpen = false;

                // Navigate if link exists
                if (item.link) {
                    window.location.href = item.link;
                }
            },

            async markAllAsRead() {
                this.notifications.forEach(n => n.is_read = true);
                this.unreadCount = 0;
                try {
                    await fetch('/api/notifications/mark-read', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({ role: this.role })
                    });
                    localStorage.setItem('pos_ilp_notify_sync_v2', Date.now().toString());
                    if (window.triggerToast) window.triggerToast('Semua notifikasi telah ditandai dibaca.', 'success');
                } catch(e) {}
            },

            async clearAllNotifications() {
                if (!confirm(`Bersihkan semua riwayat notifikasi untuk role ${this.role}?`)) return;
                this.notifications = [];
                this.unreadCount = 0;
                try {
                    await fetch('/api/notifications/clear-all', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({ role: this.role })
                    });
                    localStorage.setItem('pos_ilp_notify_sync_v2', Date.now().toString());
                    if (window.triggerToast) window.triggerToast('Riwayat notifikasi berhasil dibersihkan.', 'info');
                } catch(e) {}
            },

            handleIncomingRealtime(detail) {
                // If detail matches our role or is ALL
                const targetRole = (detail.role || 'ALL').toUpperCase();
                if (targetRole === 'ALL' || targetRole === this.role.toUpperCase()) {
                    this.playChimeSound();
                    this.fetchNotifications(false);
                    if (window.triggerToast && detail.title) {
                        window.triggerToast(`🔔 ${detail.title}: ${detail.message || ''}`, 'info');
                    }
                }
            },

            playChimeSound() {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
                    osc.frequency.exponentialRampToValueAtTime(880.00, ctx.currentTime + 0.15); // A5

                    gain.gain.setValueAtTime(0.08, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);

                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.4);
                } catch(e) {
                    // Audio context may require user gesture on some browsers
                }
            }
        };
    };
}
</script>
