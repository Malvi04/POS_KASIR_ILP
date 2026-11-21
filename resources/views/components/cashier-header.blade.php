<header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 sm:px-6 lg:px-8 shrink-0 select-none z-20">
    <!-- Title & Consistent Cashier Control Bar -->
    <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1 mr-2 overflow-hidden">
        <h2 class="text-base sm:text-lg font-bold text-gray-800 truncate shrink-0">{{ $title }}</h2>

        <!-- Custom Slot Badges if any (e.g. from refund, held-bills, etc) -->
        @if(isset($slot) && trim($slot))
            <div class="hidden 2xl:flex items-center shrink-0">
                {{ $slot }}
            </div>
        @endif

        <!-- Consistent Cashier Control Bar (Identical across all cashier pages) -->
        <div class="flex items-center gap-2 overflow-x-auto py-1 no-scrollbar shrink-0">
            <!-- Terminal & Active Operator Pill -->
            <a href="{{ url('/kasir/sesi') }}"
               class="flex items-center gap-2 bg-blue-50 hover:bg-blue-100 text-[#0052cc] border border-blue-200 px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 shadow-2xs"
               :title="'ID Sesi: ' + (activeSession?.sessionId || '-')">
                <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                <span class="font-extrabold text-[#0052cc]" x-text="activeSession?.terminalLabel || localStorage.getItem('pos_kasir_shift') || 'POS-01'">POS-01</span>
                <span class="text-gray-300">•</span>
                <span class="material-symbols-rounded text-[15px] text-[#0052cc]">badge</span>
                <span class="text-gray-800 font-black" x-text="activeOperator ? activeOperator.name : (activeSession?.cashierName || localStorage.getItem('pos_user_name') || 'Kasir 1')">Kasir 1</span>
            </a>

            <!-- Kunci Layar Terminal -->
            <button type="button"
                    @click="typeof lockTerminal === 'function' ? lockTerminal() : $dispatch('lock-terminal')"
                    class="flex items-center gap-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-300 px-2.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer shadow-2xs shrink-0"
                    title="Kunci Layar Terminal Sementara">
                <span class="material-symbols-rounded text-[16px]">lock</span>
                <span class="hidden sm:inline">Kunci</span>
            </button>

            <!-- Fitur Kas Laci (Total uang laci ada di dalam popup fitur Kas Laci, tidak terlihat di tampilan depan) -->
            <button type="button"
                    @click="$dispatch('open-cash-movement')"
                    class="flex items-center gap-1.5 bg-white hover:bg-blue-50/50 text-gray-700 hover:text-[#0052cc] border border-gray-200 hover:border-blue-200 px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 shadow-2xs group"
                    title="Buka Fitur Kas Laci (Catat Kas Masuk / Keluar / Setor Brankas & Cek Saldo Laci)">
                <span class="material-symbols-rounded text-[16px] text-[#0052cc]">account_balance_wallet</span>
                <span>Kas Laci</span>
            </button>

            <!-- Tutup Sesi (Audit) -->
            <button type="button"
                    @click="$dispatch('open-closing-wizard')"
                    class="flex items-center gap-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 shadow-2xs"
                    title="Tutup Sesi & Audit Kasir">
                <span class="material-symbols-rounded text-[16px] text-amber-700">logout</span>
                <span class="hidden sm:inline">Tutup Sesi (Audit)</span>
            </button>
        </div>
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
             :class="{
                'text-emerald-700 bg-emerald-50 border-emerald-200 hover:bg-emerald-100/50': ($store.network && ($store.network.wsConnected || $store.network.isOnline)),
                'text-amber-700 bg-amber-50 border-amber-200 hover:bg-amber-100/50': ($store.network && !$store.network.wsConnected && $store.network.wsStatus === 'connecting' && $store.network.isOnline),
                'text-rose-700 bg-rose-50 border-rose-200 hover:bg-rose-100/50': (!$store.network || (!$store.network.isOnline && !$store.network.wsConnected))
             }"
             :title="($store.network && $store.network.wsConnected)
                ? 'Koneksi POS: Terhubung Real-time (' + ($store.network.latency || 0) + 'ms). Klik untuk info sync.' 
                : (($store.network && $store.network.isOnline)
                    ? 'Koneksi POS: Terhubung ke Server. Klik untuk info sync.'
                    : (($store.network && $store.network.wsStatus === 'connecting')
                        ? 'Koneksi POS: Menghubungkan ke WebSocket Server...'
                        : 'Koneksi POS: Terputus / Mode Offline Aktif. Transaksi tetap berjalan normal.'))">
            <!-- Pulsing Indicator Dot -->
            <span class="w-2 h-2 rounded-full shrink-0"
                  :class="{
                    'bg-emerald-500 animate-pulse': ($store.network && ($store.network.wsConnected || $store.network.isOnline)),
                    'bg-amber-500 animate-ping': ($store.network && !$store.network.wsConnected && $store.network.wsStatus === 'connecting' && $store.network.isOnline),
                    'bg-rose-500': (!$store.network || (!$store.network.isOnline && !$store.network.wsConnected))
                  }"></span>

            <!-- Wifi / Wifi-Off Icon (Statis, tidak rotate saat sinkron) -->
            <span class="material-symbols-rounded text-[18px] shrink-0"
                  :class="{
                    'text-emerald-600': ($store.network && ($store.network.wsConnected || $store.network.isOnline)),
                    'text-amber-600': ($store.network && !$store.network.wsConnected && $store.network.wsStatus === 'connecting' && $store.network.isOnline),
                    'text-rose-600': (!$store.network || (!$store.network.isOnline && !$store.network.wsConnected))
                  }"
                  x-text="($store.network && ($store.network.wsConnected || $store.network.isOnline)) ? 'wifi' : 'wifi_off'">wifi</span>

            <!-- Status Text -->
            <span x-text="($store.network && $store.network.wsConnected) ? 'Terhubung' : (($store.network && $store.network.isOnline) ? 'Terhubung' : (($store.network && $store.network.wsStatus === 'connecting') ? 'Menghubungkan...' : 'Terputus'))">Terhubung</span>

            <!-- Real-time Ping Latency (ms) with Speed Quality Indicator -->
            <template x-if="$store.network && $store.network.latency !== undefined && $store.network.latency !== null">
                <span class="text-[10px] font-mono hidden sm:inline ml-0.5 font-bold transition-colors"
                      :class="{
                          'text-emerald-600': ($store.network.latency < 50),
                          'text-teal-600': ($store.network.latency >= 50 && $store.network.latency < 150),
                          'text-amber-600': ($store.network.latency >= 150 && $store.network.latency <= 300),
                          'text-rose-600': ($store.network.latency > 300)
                      }"
                      :title="'Kecepatan WebSocket Reverb: ' + $store.network.latency + 'ms'"
                      x-text="$store.network.latency + 'ms'"></span>
            </template>
        </div>
    </div>
</header>
