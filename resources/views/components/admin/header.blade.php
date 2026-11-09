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
            <!-- Notification Bell with Interactive Dropdown Popover -->
            <div class="relative" @click.outside="showNotifications = false">
                <button type="button" 
                        class="p-2 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-colors relative cursor-pointer"
                        title="Notifikasi Sistem"
                        @click="showNotifications = !showNotifications">
                    <span class="material-symbols-rounded text-[20px]">notifications</span>
                    <template x-if="notifications.length > 0">
                        <span class="absolute top-2 right-2 w-2 h-2 bg-blue-600 rounded-full ring-2 ring-white"></span>
                    </template>
                </button>

                <!-- Notification Dropdown Menu -->
                <div x-show="showNotifications"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                     x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                     class="absolute right-0 mt-2 w-80 bg-white rounded-2xl shadow-xl border border-slate-200 py-3 z-50 overflow-hidden"
                     style="display: none;">
                    <div class="px-4 pb-2 border-b border-slate-100 flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="font-bold text-xs text-slate-900">Notifikasi</span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-blue-100 text-[#0052cc]" x-text="notifications.length"></span>
                        </div>
                        <template x-if="notifications.length > 0">
                            <button type="button" @click="markAsRead()" class="text-[11px] font-bold text-[#0052cc] hover:underline">
                                Bersihkan
                            </button>
                        </template>
                    </div>

                    <div class="max-h-72 overflow-y-auto divide-y divide-slate-50">
                        <template x-for="n in notifications" :key="n.id">
                            <a :href="n.link" 
                               @click="showNotifications = false"
                               class="p-3 flex items-start gap-3 hover:bg-slate-50 transition-colors block">
                                <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0" :class="n.color">
                                    <span class="material-symbols-rounded text-[16px]" x-text="n.icon"></span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-xs text-slate-800 truncate" x-text="n.title"></div>
                                    <div class="text-[11px] text-slate-500 leading-tight mt-0.5" x-text="n.desc"></div>
                                    <span class="text-[10px] text-slate-400 block mt-1" x-text="n.time"></span>
                                </div>
                            </a>
                        </template>

                        <template x-if="notifications.length === 0">
                            <div class="py-8 text-center text-slate-400 text-xs">
                                <span class="material-symbols-rounded text-2xl block mb-1 text-slate-300">notifications_off</span>
                                <span>Tidak ada notifikasi aktif</span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Network / Status Real-time Indicator (Non-clickable badge) -->
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-bold border border-emerald-200" title="Koneksi Sistem POS: Online & Real-time">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>POS Online</span>
            </div>

            <!-- Sync Button -->
            <button type="button" 
                    class="p-2 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-colors cursor-pointer"
                    title="Sinkronisasi Data Realtime"
                    @click="triggerSync()">
                <span class="material-symbols-rounded text-[20px] active:rotate-180 transition-transform">sync</span>
            </button>
        </div>

        <div class="h-6 w-px bg-slate-200 mx-1 hidden sm:block"></div>

        <!-- Custom Action Buttons (Slot) -->
        <div class="flex items-center gap-2">
            {{ $slot ?? '' }}
        </div>
    </div>
</header>
