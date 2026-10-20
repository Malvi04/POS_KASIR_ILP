@props(['active' => ''])

<aside x-data="cashierOpeningData()"
       x-init="initOpening()"
       @pos-session-sync.window="initOpening()"
       @open-closing-wizard.window="openClosingWizard()"
       @open-cash-movement.window="showCashMovementModal = true"
       class="w-64 bg-[#f1f5f9] border-r border-gray-200 flex flex-col justify-between shrink-0 h-screen overflow-y-auto">
    <div>
        <div class="px-6 py-5 border-b border-gray-200/50">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-black text-[#0052cc] tracking-tight">ILP POS</h1>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-blue-100 text-[#0052cc]" x-text="activeSession.terminalLabel">Kasir 01</span>
            </div>
            <p class="text-[11px] text-gray-500 mt-0.5 font-medium" x-text="activeSession.cashierName">Budi Kurniawan</p>
        </div>

        <nav class="mt-2 flex flex-col gap-1 px-4">
            <a href="{{ url('/pos') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'pos' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'pos' ? 'filled' : '' }}">point_of_sale</span>
                POS
            </a>
            <a href="{{ url('/kasir/sesi') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'session' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'session' ? 'filled' : '' }}">swap_horiz</span>
                Sesi Kasir
            </a>
            <a href="{{ url('/kasir/inventory') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'inventory' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'inventory' ? 'filled' : '' }}">inventory_2</span>
                Products
            </a>
            <a href="{{ url('/kasir/members') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'members' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'members' ? 'filled' : '' }}">groups</span>
                Members
            </a>
            <a href="{{ url('/kasir/reports') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'reports' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'reports' ? 'filled' : '' }}">bar_chart</span>
                Reports
            </a>
            <a href="{{ url('/kupon') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'kupon' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'kupon' ? 'filled' : '' }}">local_activity</span>
                Kupon
            </a>
            <a href="{{ url('/kasir/retur') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'retur' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'retur' ? 'filled' : '' }}">local_shipping</span>
                Retur Barang
            </a>
            <a href="{{ url('/kasir/stock-opname') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'stock_opname' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'stock_opname' ? 'filled' : '' }}">fact_check</span>
                Stock Opname
            </a>
        </nav>
    </div>

    <div class="p-4 mb-1 flex flex-col gap-1 border-t border-gray-200/50 pt-3">
        <a href="{{ url('/kasir/settings') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'settings' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
            <span class="material-symbols-rounded text-[20px] {{ $active === 'settings' ? 'filled' : '' }}">settings</span>
            Settings
        </a>
        <button type="button"
                @click="openClosingWizard()"
                class="flex w-full items-center gap-2.5 px-4 py-2.5 text-red-600 hover:bg-red-50 rounded-xl transition-colors font-bold text-[13px] mt-1 cursor-pointer">
            <span class="material-symbols-rounded text-[20px]">logout</span>
            <span>Tutup Sesi</span>
        </button>
    </div>

    <!-- Teleport Closing & Cash Movement Modal to Body -->
    <template x-teleport="body">
        <div>
            @include('kasir.partials._closing-handover-modal')
        </div>
    </template>
</aside>
