@props([
    'title' => 'Admin Console',
    'searchPlaceholder' => 'Cari sesuatu...',
    'showSearch' => true,
    'searchModel' => 'searchQuery'
])

<header class="h-16 bg-white border-b border-slate-200/80 px-6 flex items-center justify-between shrink-0 z-30 gap-4"
        x-data="{
            searchQuery: '',
            showNotifications: false,
            notifications: [
                { id: 1, title: '12 Produk Mendekati Minimum', desc: 'SKU UMKM & Sembako menipis di gerai retail.', time: '10m lalu', link: '{{ url('/owner/umkm-partners') }}', icon: 'warning', color: 'text-amber-500 bg-amber-50' },
                { id: 2, title: '1 Akun Anggota Dibekukan', desc: 'Plafon habis 100%, butuh otorisasi Owner.', time: '45m lalu', link: '{{ url('/owner/members') }}', icon: 'lock', color: 'text-rose-500 bg-rose-50' },
                { id: 3, title: 'Sinkronisasi 3 Cabang Berhasil', desc: 'Pusat, Cabang A, dan B terhubung realtime.', time: '1j lalu', link: '{{ url('/owner/cabang') }}', icon: 'sync', color: 'text-blue-500 bg-blue-50' }
            ],
            markAsRead() {
                this.notifications = [];
                if (typeof triggerToast === 'function') triggerToast('Semua notifikasi ditandai telah dibaca.', 'success');
            },
            triggerSync() {
                window.dispatchEvent(new CustomEvent('ilp-sync-data'));
                if (typeof refreshData === 'function') {
                    refreshData();
                } else if (typeof triggerToast === 'function') {
                    triggerToast('Data konsolidasi berhasil disinkronkan!', 'success');
                }
            }
        }">
    <!-- Left: Title, Breadcrumb or Navigation Tabs -->
    <div class="flex items-center gap-4 min-w-0">
        {{ $leftSlot ?? '' }}
        
        @if(!isset($leftSlot))
            <h2 class="text-base font-bold text-slate-800 tracking-tight whitespace-nowrap">{{ $title }}</h2>
        @endif
    </div>

    <!-- Center/Right Search Bar (if present) -->
    @if($showSearch)
        <div class="flex-1 max-w-md hidden md:flex items-center justify-center">
            {{ $searchSlot ?? '' }}
            @if(!isset($searchSlot))
                <div class="relative w-full max-w-sm">
                    <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                    <input type="text" 
                           x-model="searchQuery"
                           @input="window.dispatchEvent(new CustomEvent('ilp-search', { detail: searchQuery }))"
                           placeholder="{{ $searchPlaceholder }}" 
                           class="w-full pl-10 pr-4 py-2 rounded-xl bg-slate-50 border border-slate-200 focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 focus:outline-none text-[13px] text-slate-700 placeholder-slate-400 transition-all">
                </div>
            @endif
        </div>
    @endif

    <!-- Right Side: System Status, Sync & Action Buttons -->
    <div class="flex items-center gap-3">
        <!-- System Quick Status Icons (Bell, Wifi, Sync) -->
        <div class="flex items-center gap-2 text-slate-500">
            <!-- Notification Bell with Interactive Realtime Popover for Owner -->
            <x-notification-bell role="OWNER" />

            <!-- Network / Status Real-time Indicator -->
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border transition-all select-none"
                 :class="($store.network && $store.network.isOnline)
                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                    : 'bg-rose-50 text-rose-700 border-rose-200'"
                 :title="($store.network && $store.network.isOnline) 
                    ? 'Koneksi Sistem POS: Terhubung Real-time (' + ($store.network.latency || 0) + 'ms)' 
                    : 'Koneksi Sistem POS: Terputus / Mode Offline'">
                <span class="w-1.5 h-1.5 rounded-full" 
                      :class="($store.network && $store.network.isOnline) ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'"></span>
                <span class="material-symbols-rounded text-[14px]"
                      :class="($store.network && $store.network.isOnline) ? 'text-emerald-600' : 'text-rose-600'"
                      x-text="($store.network && $store.network.isOnline) ? 'wifi' : 'wifi_off'">wifi</span>
                <span x-text="($store.network && $store.network.isOnline) ? 'POS Online' : 'POS Terputus'">POS Online</span>
                <template x-if="$store.network && $store.network.isOnline && $store.network.latency">
                    <span class="text-[10px] text-emerald-600/80 font-mono hidden sm:inline" x-text="$store.network.latency + 'ms'"></span>
                </template>
            </div>

            <!-- Sync Button -->
            <button type="button" 
                    class="p-2 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-colors cursor-pointer"
                    title="Sinkronisasi Data Realtime"
                    @click="window.PosRealtime?.syncNow?.(); triggerSync()">
                <span class="material-symbols-rounded text-[20px] transition-transform"
                      :class="$store.network?.isChecking ? 'animate-spin text-[#0052cc]' : ''">sync</span>
            </button>
        </div>

        <div class="h-6 w-px bg-slate-200 mx-1 hidden sm:block"></div>

        <!-- Custom Action Buttons (Slot) -->
        <div class="flex items-center gap-2">
            {{ $slot ?? '' }}
        </div>
    </div>
</header>
