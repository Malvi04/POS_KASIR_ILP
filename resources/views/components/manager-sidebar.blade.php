@props(['active' => ''])

<aside class="w-[260px] bg-[#f1f5f9] border-r border-gray-200 flex flex-col justify-between shrink-0 h-screen overflow-y-auto">
    <div>
        <div class="px-6 py-6 mb-2">
            <h1 class="text-[28px] font-black text-[#0052cc] tracking-tight">ILP POS</h1>
            <p class="text-[13px] text-gray-500 mt-1 font-medium">Manager</p>
        </div>
        <nav class="mt-2 flex flex-col px-4 gap-1">
            <a href="{{ url('/dashboard') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[14px] {{ $active === 'dashboard' ? 'bg-[#dbeafe] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[22px] {{ $active === 'dashboard' ? 'filled' : '' }}">grid_view</span>
                Dashboard
            </a>
            <a href="{{ url('/inventory') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[14px] {{ $active === 'inventory' ? 'bg-[#dbeafe] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[22px] {{ $active === 'inventory' ? 'filled' : '' }}">inventory_2</span>
                Inventory
            </a>
            <a href="{{ url('/absensi') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[14px] {{ $active === 'absensi' ? 'bg-[#dbeafe] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[22px] {{ $active === 'absensi' ? 'filled' : '' }}">badge</span>
                Absensi
            </a>
            <a href="{{ url('/members') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[14px] {{ $active === 'members' ? 'bg-[#dbeafe] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[22px] {{ $active === 'members' ? 'filled' : '' }}">groups</span>
                Members
            </a>
            <a href="{{ url('/stock-opname') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[14px] {{ $active === 'stock_opname' ? 'bg-[#dbeafe] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[22px] {{ $active === 'stock_opname' ? 'filled' : '' }}">inventory</span>
                Stock Opname
            </a>
            <a href="{{ url('/promosi') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[14px] {{ $active === 'promosi' ? 'bg-[#dbeafe] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[22px] {{ $active === 'promosi' ? 'filled' : '' }}">sell</span>
                Promosi
            </a>
            <a href="{{ url('/reports') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[14px] {{ $active === 'reports' ? 'bg-[#dbeafe] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[22px] {{ $active === 'reports' ? 'filled' : '' }}">bar_chart</span>
                Sales Reports
            </a>
            <a href="{{ url('/kelola-akun') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[14px] {{ $active === 'akun' ? 'bg-[#dbeafe] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[22px] {{ $active === 'akun' ? 'filled' : '' }}">manage_accounts</span>
                Kelola Akun
            </a>
            <a href="{{ url('/retur') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[14px] {{ $active === 'retur' ? 'bg-[#dbeafe] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[22px] {{ $active === 'retur' ? 'filled' : '' }}">local_shipping</span>
                Retur barang
            </a>
            <a href="{{ url('/umkm-partners') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[14px] {{ $active === 'umkm' ? 'bg-[#dbeafe] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[22px] {{ $active === 'umkm' ? 'filled' : '' }}">handshake</span>
                UMKM Partners
            </a>
            <a href="{{ url('/settings') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-[14px] {{ $active === 'settings' ? 'bg-[#dbeafe] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[22px] {{ $active === 'settings' ? 'filled' : '' }}">settings</span>
                Settings
            </a>
        </nav>
    </div>
    
    <!-- User Profile Area -->
    <div class="p-6 border-t border-gray-200/50 mt-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-[#0052cc] flex items-center justify-center text-white font-bold text-sm shrink-0">
            AD
        </div>
        <div class="flex flex-col overflow-hidden">
            <span class="text-[14px] font-bold text-gray-900 truncate">Manager</span>
            <span class="text-[12px] text-gray-500 truncate">Minimarket Pusat</span>
        </div>
    </div>
</aside>
