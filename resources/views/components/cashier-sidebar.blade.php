@props(['active' => ''])

<aside x-data="{ showLogoutModal: false, logoutText: '' }" class="w-64 bg-[#f1f5f9] border-r border-gray-200 flex flex-col justify-between shrink-0 h-screen overflow-y-auto">
    <div>
        <div class="px-6 py-6 border-b border-gray-200/50 mb-2">
            <h1 class="text-2xl font-black text-[#0052cc] tracking-tight">ILP POS</h1>
            <p class="text-[13px] text-gray-500 mt-1 font-medium">Worker Coop Cashier</p>
        </div>
        <nav class="mt-2 flex flex-col gap-1.5 px-4">
            <a href="{{ url('/pos') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[13px] {{ $active === 'pos' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'pos' ? 'filled' : '' }}">grid_view</span>
                Dashboard
            </a>
            <a href="{{ url('/kasir/inventory') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[13px] {{ $active === 'inventory' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'inventory' ? 'filled' : '' }}">inventory_2</span>
                Products
            </a>
            <a href="{{ url('/kasir/members') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[13px] {{ $active === 'members' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'members' ? 'filled' : '' }}">groups</span>
                Members
            </a>
            <a href="{{ url('/kasir/reports') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[13px] {{ $active === 'reports' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'reports' ? 'filled' : '' }}">bar_chart</span>
                Reports
            </a>
            <a href="{{ url('/kupon') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[13px] {{ $active === 'kupon' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'kupon' ? 'filled' : '' }}">local_activity</span>
                Kupon
            </a>
            <a href="{{ url('/kasir/retur') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[13px] {{ $active === 'retur' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'retur' ? 'filled' : '' }}">local_shipping</span>
                Retur Barang
            </a>
            <a href="{{ url('/kasir/stock-opname') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[13px] {{ $active === 'stock_opname' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'stock_opname' ? 'filled' : '' }}">inventory_2</span>
                Stock Opname
            </a>
        </nav>
    </div>
    <div class="p-4 mb-2 flex flex-col gap-1.5 border-t border-gray-200/50 pt-4">
        <a href="{{ url('/kasir/settings') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[13px] {{ $active === 'settings' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
            <span class="material-symbols-rounded text-[20px] {{ $active === 'settings' ? 'filled' : '' }}">settings</span>
            Settings
        </a>
        <button @click="showLogoutModal = true; setTimeout(() => $refs.logoutInput.focus(), 100)" class="flex w-full items-center gap-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-xl transition-colors font-bold text-[13px] mt-1">
            <span class="material-symbols-rounded text-[20px]">logout</span>
            Logout
        </button>
    </div>

    <!-- Modal Tutup Sesi Kasir -->
    <template x-teleport="body">
        <div x-show="showLogoutModal" class="fixed inset-0 z-[99] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm" style="display: none;">
            <div @click.away="showLogoutModal = false" class="bg-white rounded-[16px] p-8 w-[440px] shadow-2xl"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 scale-95"
                 @keydown.window.escape="showLogoutModal = false"
                 @keydown.window="(e) => {
                     if (!showLogoutModal) return;
                     if (document.activeElement === $refs.logoutInput && e.key !== 'Enter') return;
                     if (e.key.toLowerCase() === 'n') showLogoutModal = false;
                     if (e.key.toLowerCase() === 'y' && logoutText === 'TUTUP' || (e.key === 'Enter' && logoutText === 'TUTUP')) window.location.href='{{ url('/login-kasir') }}';
                 }">
                 
                <!-- Icon -->
                <div class="w-14 h-14 bg-[#fcecd7] rounded-full flex items-center justify-center mb-5">
                    <span class="material-symbols-rounded text-[#e88b23] text-[28px]">fax</span>
                </div>

                <h2 class="text-[22px] font-bold text-[#0a1629] mb-4">Tutup Sesi Kasir 1?</h2>

                <div class="bg-[#f1f4f9] rounded-xl p-4 mb-6">
                    <input type="text" x-model="logoutText" x-ref="logoutInput" placeholder="Ketik “TUTUP” untuk menutup sesi kasir" 
                           class="w-full bg-transparent border-none p-0 focus:ring-0 text-[14px] text-gray-800 placeholder-gray-500 outline-none font-medium">
                </div>

                <div class="flex gap-4">
                    <button @click="showLogoutModal = false" class="flex-1 flex items-center justify-center gap-2 bg-[#f1f4f9] hover:bg-gray-200 text-[#475569] font-bold py-3.5 rounded-xl transition-colors text-[14px]">
                        <span class="bg-gray-200 text-gray-500 rounded px-1.5 py-0.5 text-[12px] font-bold border border-gray-300/50">N</span>
                        Tidak, Kembali
                    </button>
                    <button @click="if(logoutText === 'TUTUP') window.location.href='{{ url('/login-kasir') }}'"
                            :disabled="logoutText !== 'TUTUP'"
                            :class="logoutText === 'TUTUP' ? 'bg-[#0052cc] hover:bg-[#0043a8] text-white' : 'bg-[#0052cc] text-white cursor-not-allowed opacity-90'"
                            class="flex-1 flex items-center justify-center gap-2 font-bold py-3.5 rounded-xl transition-colors text-[14px]">
                        <span :class="logoutText === 'TUTUP' ? 'bg-white text-[#0052cc]' : 'bg-white/80 text-[#0052cc]/80'" class="rounded px-1.5 py-0.5 text-[12px] font-extrabold shadow-sm">Y</span>
                        Ya, Tutup
                    </button>
                </div>
            </div>
        </div>
    </template>
</aside>
