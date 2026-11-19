@props([
    'labelOnline' => 'Terhubung',
    'labelOffline' => 'Terputus',
    'labelConnecting' => 'Menghubungkan...',
    'showLatency' => true
])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2 px-3 py-1.5 rounded-full border text-[11px] font-bold tracking-wide transition-all shadow-2xs select-none cursor-pointer hover:shadow-xs']) }}
     @click="$store.offlineSync?.openModal()"
     :class="{
        'text-emerald-700 bg-emerald-50 border-emerald-200': ($store.network && ($store.network.wsConnected || $store.network.isOnline)),
        'text-amber-700 bg-amber-50 border-amber-200': ($store.network && !$store.network.wsConnected && $store.network.wsStatus === 'connecting' && $store.network.isOnline),
        'text-rose-700 bg-rose-50 border-rose-200': (!$store.network || (!$store.network.isOnline && !$store.network.wsConnected))
     }"
     :title="($store.network && $store.network.wsConnected)
        ? 'Status Jaringan & Reverb: Terhubung Real-time (' + ($store.network.latency || 0) + 'ms)' 
        : (($store.network && $store.network.isOnline)
            ? 'Status Jaringan: Terhubung ke Server'
            : (($store.network && $store.network.wsStatus === 'connecting')
                ? 'Status Jaringan & Reverb: Menghubungkan ke WebSocket...' 
                : 'Status Jaringan & Reverb: Terputus'))">
    <!-- Pulsing Indicator Dot -->
    <span class="w-2 h-2 rounded-full shrink-0"
          :class="{
            'bg-emerald-500 animate-pulse': ($store.network && ($store.network.wsConnected || $store.network.isOnline)),
            'bg-amber-500 animate-ping': ($store.network && !$store.network.wsConnected && $store.network.wsStatus === 'connecting' && $store.network.isOnline),
            'bg-rose-500': (!$store.network || (!$store.network.isOnline && !$store.network.wsConnected))
          }"></span>

    <!-- Wifi Icon (Statis, tidak rotate saat sinkronisasi) -->
    <span class="material-symbols-rounded text-[18px] shrink-0"
          :class="{
            'text-emerald-600': ($store.network && ($store.network.wsConnected || $store.network.isOnline)),
            'text-amber-600': ($store.network && !$store.network.wsConnected && $store.network.wsStatus === 'connecting' && $store.network.isOnline),
            'text-rose-600': (!$store.network || (!$store.network.isOnline && !$store.network.wsConnected))
          }"
          x-text="($store.network && ($store.network.wsConnected || $store.network.isOnline)) ? 'wifi' : 'wifi_off'">wifi</span>

    <!-- Text Status -->
    <span x-text="($store.network && $store.network.wsConnected) ? '{{ $labelOnline }}' : (($store.network && $store.network.isOnline) ? '{{ $labelOnline }}' : (($store.network && $store.network.wsStatus === 'connecting') ? '{{ $labelConnecting }}' : '{{ $labelOffline }}'))">{{ $labelOnline }}</span>

    @if($showLatency)
        <!-- Real-Time Network Speed / Latency Indicator -->
        <template x-if="$store.network && $store.network.latency !== undefined && $store.network.latency !== null">
            <span class="text-[10px] font-mono hidden sm:inline ml-0.5 font-bold transition-colors"
                  :class="{
                      'text-emerald-600': ($store.network.latency < 50),
                      'text-teal-600': ($store.network.latency >= 50 && $store.network.latency < 150),
                      'text-amber-600': ($store.network.latency >= 150 && $store.network.latency <= 300),
                      'text-rose-600': ($store.network.latency > 300)
                  }"
                  :title="'Kecepatan Jaringan: ' + $store.network.latency + 'ms (Update tiap 1 detik)'"
                  x-text="$store.network.latency + 'ms'"></span>
        </template>
    @endif

    <!-- WebSocket Reverb Status Badge -->
    <template x-if="$store.network && $store.network.wsConnected">
        <span class="inline-flex items-center gap-1.5 text-[10px] text-blue-700 bg-blue-100/90 border border-blue-200/90 px-2 py-0.5 rounded-full font-bold ml-1 tracking-tight shadow-2xs select-none shrink-0"
              title="WebSocket Reverb Realtime Service Aktif">
            <span class="relative flex h-1.5 w-1.5 shrink-0">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-blue-600"></span>
            </span>
            <span>Reverb Active</span>
        </span>
    </template>
</div>
