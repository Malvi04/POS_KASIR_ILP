@props([
    'labelOnline' => 'Terhubung',
    'labelOffline' => 'Terputus',
    'showLatency' => true
])

<div class="flex items-center gap-2 px-3 py-1.5 rounded-full border text-[11px] font-bold tracking-wide transition-all shadow-2xs select-none"
     :class="($store.network && $store.network.isOnline)
        ? 'text-emerald-700 bg-emerald-50 border-emerald-200'
        : 'text-rose-700 bg-rose-50 border-rose-200'"
     :title="($store.network && $store.network.isOnline) 
        ? 'Status Jaringan: Terhubung Real-time (' + ($store.network.latency || 0) + 'ms)' 
        : 'Status Jaringan: Terputus / Mode Offline Aktif'">
    <!-- Pulsing Indicator Dot -->
    <span class="w-2 h-2 rounded-full shrink-0"
          :class="($store.network && $store.network.isOnline) ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'"></span>

    <!-- Wifi Icon -->
    <span class="material-symbols-rounded text-[18px] shrink-0"
          :class="($store.network && $store.network.isOnline) ? 'text-emerald-600' : 'text-rose-600'"
          x-text="($store.network && $store.network.isOnline) ? 'wifi' : 'wifi_off'">wifi</span>

    <!-- Text Status -->
    <span x-text="($store.network && $store.network.isOnline) ? '{{ $labelOnline }}' : '{{ $labelOffline }}'">{{ $labelOnline }}</span>

    @if($showLatency)
        <!-- Latency Display -->
        <template x-if="$store.network && $store.network.isOnline && $store.network.latency">
            <span class="text-[10px] text-emerald-600/80 font-mono hidden sm:inline ml-0.5"
                  x-text="$store.network.latency + 'ms'"></span>
        </template>
    @endif

    <!-- WebSocket Reverb Status Indicator -->
    <template x-if="$store.network && $store.network.wsConnected">
        <span class="inline-flex items-center gap-1 text-[10px] text-blue-600 bg-blue-100/80 px-1.5 py-0.5 rounded-full font-bold ml-1 tracking-tight"
              title="WebSocket Reverb Realtime Service Aktif">
            <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-ping"></span>
            Reverb WS
        </span>
    </template>
</div>
