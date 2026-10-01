@props(['active' => ''])

<aside class="w-64 bg-[#f1f5f9] border-r border-gray-200 flex flex-col justify-between shrink-0 h-screen overflow-y-auto">
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
            <a href="{{ url('/inventory') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[13px] {{ $active === 'inventory' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'inventory' ? 'filled' : '' }}">inventory_2</span>
                Products
            </a>
            <a href="{{ url('/members') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[13px] {{ $active === 'members' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'members' ? 'filled' : '' }}">groups</span>
                Members
            </a>
            <a href="{{ url('/reports') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[13px] {{ $active === 'reports' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'reports' ? 'filled' : '' }}">bar_chart</span>
                Reports
            </a>
            <a href="{{ url('/kupon') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[13px] {{ $active === 'kupon' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'kupon' ? 'filled' : '' }}">local_activity</span>
                Kupon
            </a>
            <a href="{{ url('/retur') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[13px] {{ $active === 'retur' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'retur' ? 'filled' : '' }}">local_shipping</span>
                Retur Barang
            </a>
        </nav>
    </div>
    <div class="p-4 mb-2 flex flex-col gap-1.5 border-t border-gray-200/50 pt-4">
        <a href="{{ url('/settings') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[13px] {{ $active === 'settings' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
            <span class="material-symbols-rounded text-[20px] {{ $active === 'settings' ? 'filled' : '' }}">settings</span>
            Settings
        </a>
        <a href="{{ url('/login-kasir') }}" class="flex items-center gap-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-xl transition-colors font-bold text-[13px] mt-1">
            <span class="material-symbols-rounded text-[20px]">logout</span>
            Logout
        </a>
    </div>
</aside>
