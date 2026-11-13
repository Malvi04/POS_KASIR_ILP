<header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0 select-none z-20">
    <!-- Title & Left Slot -->
    <div class="flex items-center gap-4 min-w-0">
        <h2 class="text-lg font-bold text-gray-800 truncate">{{ $title }}</h2>
        {{ $slot ?? '' }}
    </div>

    <!-- Right Actions & Realtime Status -->
    <div class="flex items-center gap-3 sm:gap-4 shrink-0">
        <!-- Role-based Realtime Notification Bell Popover -->
        <x-notification-bell role="KASIR" />

        <!-- Pending Offline Queue Badge (Clickable to open sync modal) -->
        <template x-if="($store.offlineSync && $store.offlineSync.totalPending > 0)">
            <button type="button"
                    @click="$store.offlineSync?.openModal()"
                    class="flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 transition-all shadow-2xs cursor-pointer animate-pulse"
                    title="Klik untuk membuka Pusat Sinkronisasi Transaksi">
                <span class="material-symbols-rounded text-[15px] text-amber-600">cloud_off</span>
                <span x-text="($store.offlineSync.totalPending) + ' Tertunda'"></span>
            </button>
        </template>

        <!-- Realtime Data & Offline Sync Button (Manual Sync) -->
        <button type="button"
                @click="$store.offlineSync ? $store.offlineSync.syncNow() : window.PosRealtime?.syncNow?.()"
                class="p-2 rounded-xl text-gray-500 hover:text-[#0052cc] hover:bg-blue-50 transition-colors cursor-pointer relative"
                title="Sinkronisasi Data Manual / Sync Sekarang">
            <span class="material-symbols-rounded text-[22px] transition-transform"
                  :class="($store.network?.isChecking || ($store.offlineSync && $store.offlineSync.isSyncing)) ? 'animate-spin text-[#0052cc]' : ''">sync</span>
            <template x-if="($store.offlineSync && $store.offlineSync.totalPending > 0)">
                <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-amber-500 ring-2 ring-white"></span>
            </template>
        </button>

        <div class="h-6 w-px bg-gray-200 mx-0.5"></div>

        <!-- Real-time Network Status Pill Badge -->
        <div @click="$store.offlineSync?.openModal()"
             class="flex items-center gap-2 px-3 py-1.5 rounded-full border text-[11px] font-bold tracking-wide transition-all shadow-2xs select-none cursor-pointer hover:shadow-xs"
             :class="($store.network && $store.network.isOnline)
                ? 'text-emerald-700 bg-emerald-50 border-emerald-200 hover:bg-emerald-100/50'
                : 'text-rose-700 bg-rose-50 border-rose-200 hover:bg-rose-100/50'"
             :title="($store.network && $store.network.isOnline) 
                ? 'Koneksi POS: Terhubung Realtime (' + ($store.network.latency || 0) + 'ms). Klik untuk info sync.' 
                : 'Koneksi POS: Terputus (Mode Offline Aktif). Transaksi tetap berjalan normal.'">
            <!-- Pulsing Indicator Dot -->
            <span class="w-2 h-2 rounded-full shrink-0"
                  :class="($store.network && $store.network.isOnline) ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'"></span>

            <!-- Wifi / Wifi-Off Icon -->
            <span class="material-symbols-rounded text-[18px] shrink-0"
                  :class="($store.network && $store.network.isOnline) ? 'text-emerald-600' : 'text-rose-600'"
                  x-text="($store.network && $store.network.isOnline) ? 'wifi' : 'wifi_off'">wifi</span>

            <!-- Status Text -->
            <span x-text="($store.network && $store.network.isOnline) ? 'Terhubung' : 'Mode Offline'">Terhubung</span>

            <!-- Real-time Ping Latency (ms) -->
            <template x-if="$store.network && $store.network.isOnline && $store.network.latency">
                <span class="text-[10px] text-emerald-600/80 font-mono hidden sm:inline ml-0.5"
                      x-text="$store.network.latency + 'ms'"></span>
            </template>
        </div>
    </div>
</header>
