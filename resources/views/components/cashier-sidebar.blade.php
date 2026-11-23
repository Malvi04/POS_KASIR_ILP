@props(['active' => ''])
@php
    $authUser = auth()->user();
    $rawServerBranch = $authUser?->branch ? trim(explode(',', $authUser->branch)[0]) : 'Pusat';
    $serverBranchLabel = match(strtolower($rawServerBranch)) {
        'pusat', 'minimarket pusat' => 'Cabang Pusat',
        'cabang a', 'sektor a', 'cabang 1' => 'Cabang A',
        'cabang b', 'sektor b', 'cabang 2' => 'Cabang B',
        default => $rawServerBranch ?: 'Cabang Pusat',
    };
@endphp

<div x-data="{
         isSidebarCollapsed: (function() {
             try { return localStorage.getItem('pos_sidebar_collapsed') === 'true'; } catch(e) { return false; }
         })(),
         toggleSidebar() {
             this.isSidebarCollapsed = !this.isSidebarCollapsed;
             try { localStorage.setItem('pos_sidebar_collapsed', this.isSidebarCollapsed ? 'true' : 'false'); } catch(e) {}
             window.dispatchEvent(new CustomEvent('sidebar-toggled', { detail: { collapsed: this.isSidebarCollapsed } }));
             setTimeout(() => { window.dispatchEvent(new Event('resize')); }, 60);
         },
         ...cashierOpeningData(),
         serverBranch: @js($serverBranchLabel),
         getSidebarBranch() {
             let raw = this.activeSession?.storeLocation || localStorage.getItem('pos_kasir_branch') || localStorage.getItem('pos_kasir_location') || this.serverBranch || this.selectedBranch;
             if (!raw && this.activeSession?.terminalLabel) {
                 const m = /^POS-(\d+)$/i.exec(this.activeSession.terminalLabel || '');
                 const num = m ? parseInt(m[1], 10) : 0;
                 if (num >= 6 && num <= 10) raw = 'Cabang A';
                 else if (num >= 11 && num <= 15) raw = 'Cabang B';
                 else if (num >= 1 && num <= 5) raw = 'Cabang Pusat';
             }
             if (!raw) raw = this.serverBranch || 'Cabang Pusat';
             return this.normalizeBranchName ? this.normalizeBranchName(raw) : raw;
         },
         openMenus: {
             produk: {{ in_array($active, ['produk_masuk', 'inventory', 'produk_umkm', 'produk']) ? 'true' : 'false' }},
             retur: {{ in_array($active, ['retur', 'retur_masuk']) ? 'true' : 'false' }},
             transaksi: {{ in_array($active, ['transaksi_riwayat', 'transaksi_ditahan', 'transaksi_refund', 'transaksi']) ? 'true' : 'false' }},
             laporan: {{ in_array($active, ['reports', 'laporan_kasir', 'laporan_sesi', 'laporan_operasional', 'laporan']) ? 'true' : 'false' }}
         }
     }"
     x-init="initOpening()"
     @pos-session-sync.window="initOpening()"
     @open-closing-wizard.window="openClosingWizard()"
     @open-cash-movement.window="showCashMovementModal = true"
     @lock-terminal.window="lockTerminal()"
     @pos:session_updated.window="initOpening()"
     @cashier_assignment_updated.window="initOpening()"
     @toggle-sidebar.window="toggleSidebar()"
     @storage.window="if ($event.key === 'pos_sidebar_collapsed') { isSidebarCollapsed = ($event.newValue === 'true'); }"
     @keydown.window="if (($event.ctrlKey || $event.metaKey) && $event.key.toLowerCase() === 'b' && !['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName)) { $event.preventDefault(); toggleSidebar(); }"
     class="shrink-0 flex transition-all duration-300 ease-in-out select-none"
     :class="isSidebarCollapsed ? 'w-0' : 'w-64'">

    <!-- Sidebar Aside Panel -->
    <aside x-show="!isSidebarCollapsed"
           x-transition:enter="transition-all ease-out duration-200"
           x-transition:enter-start="-translate-x-full opacity-0"
           x-transition:enter-end="translate-x-0 opacity-100"
           x-transition:leave="transition-all ease-in duration-150"
           x-transition:leave-start="translate-x-0 opacity-100"
           x-transition:leave-end="-translate-x-full opacity-0"
           class="w-64 bg-[#f1f5f9] border-r border-gray-200 h-screen relative select-none z-30">

    <!-- Inner Scrollable Sidebar Content -->
    <div class="h-full flex flex-col justify-between overflow-y-auto overflow-x-hidden">
        <div>
            <!-- Terminal & Cashier Header -->
            <div class="px-5 py-4 border-b border-gray-200/50 bg-white/40">
                <div class="flex items-center justify-between">
                    <h1 class="text-xl font-black text-[#0052cc] tracking-tight">ILP POS</h1>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-blue-100 text-[#0052cc]" x-text="activeSession ? activeSession.terminalLabel : (localStorage.getItem('pos_kasir_shift') || 'POS-01')">POS-01</span>
                </div>

            <!-- Informasi Lokasi Cabang Kasir -->
            <div class="mt-2.5 flex items-center gap-2 px-2.5 py-1.5 bg-white border border-gray-200/80 rounded-xl shadow-2xs">
                <div class="w-6 h-6 rounded-lg bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                    <span class="material-symbols-rounded text-[15px]">storefront</span>
                </div>
                <div class="flex flex-col min-w-0">
                    <span class="text-[9.5px] font-extrabold text-[#0052cc] uppercase tracking-wider leading-none">Cabang Aktif</span>
                    <span class="text-xs font-bold text-gray-800 truncate mt-0.5" x-text="getSidebarBranch()">{{ $serverBranchLabel }}</span>
                </div>
            </div>

            <!-- Petugas Kasir Aktif -->
            <div class="mt-2 flex items-center gap-1.5 text-[11px] text-gray-500 font-medium truncate">
                <span class="material-symbols-rounded text-[14px] text-gray-400 shrink-0">badge</span>
                <span class="truncate" x-text="activeOperator ? activeOperator.name : (activeSession ? activeSession.cashierName : (localStorage.getItem('pos_user_name') || 'Kasir'))">-</span>
            </div>
        </div>

        <!-- 11 Final Sidebar Navigation Items -->
        <nav class="mt-2 flex flex-col gap-1 px-3">
            <!-- 1. POS -->
            <a href="{{ url('/pos') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'pos' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'pos' ? 'filled' : '' }}">point_of_sale</span>
                <span>POS</span>
            </a>

            <!-- 2. Sesi Kasir -->
            <a href="{{ url('/kasir/sesi') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'session' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'session' ? 'filled' : '' }}">swap_horiz</span>
                <span>Sesi Kasir</span>
            </a>

            <!-- 3. Produk (Dropdown) -->
            <div class="flex flex-col">
                <button type="button"
                        @click="openMenus.produk = !openMenus.produk"
                        :aria-expanded="openMenus.produk ? 'true' : 'false'"
                        aria-controls="submenu-produk"
                        class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ in_array($active, ['produk_masuk', 'inventory', 'produk_umkm', 'produk']) ? 'bg-[#eef2ff] text-[#0052cc] font-bold' : 'text-gray-600 hover:bg-gray-200 font-semibold' }} cursor-pointer">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-rounded text-[20px] {{ in_array($active, ['produk_masuk', 'inventory', 'produk_umkm', 'produk']) ? 'filled' : '' }}">inventory_2</span>
                        <span>Produk</span>
                    </div>
                    <span class="material-symbols-rounded text-[18px] transition-transform duration-200 text-gray-400"
                          :class="openMenus.produk ? 'rotate-180 text-[#0052cc]' : ''">expand_more</span>
                </button>
                <div id="submenu-produk" @if(!in_array($active, ['produk_masuk', 'inventory', 'produk_umkm', 'produk'])) x-cloak @endif x-show="openMenus.produk" x-transition.origin.top class="flex flex-col gap-1 pl-8 pr-1 py-1">
                    <a href="{{ url('/kasir/produk/masuk') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'produk_masuk' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'produk_masuk' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Barang Masuk</span>
                    </a>
                    <a href="{{ url('/kasir/inventory') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'inventory' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'inventory' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Informasi Stok</span>
                    </a>
                    <a href="{{ url('/kasir/produk/umkm') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'produk_umkm' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'produk_umkm' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Produk UMKM</span>
                    </a>
                </div>
            </div>

            <!-- 4. Retur Barang (Dropdown) -->
            <div class="flex flex-col">
                <button type="button"
                        @click="openMenus.retur = !openMenus.retur"
                        :aria-expanded="openMenus.retur ? 'true' : 'false'"
                        aria-controls="submenu-retur"
                        class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ in_array($active, ['retur', 'retur_masuk']) ? 'bg-[#eef2ff] text-[#0052cc] font-bold' : 'text-gray-600 hover:bg-gray-200 font-semibold' }} cursor-pointer">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-rounded text-[20px] {{ in_array($active, ['retur', 'retur_masuk']) ? 'filled' : '' }}">local_shipping</span>
                        <span>Retur Barang</span>
                    </div>
                    <span class="material-symbols-rounded text-[18px] transition-transform duration-200 text-gray-400"
                          :class="openMenus.retur ? 'rotate-180 text-[#0052cc]' : ''">expand_more</span>
                </button>
                <div id="submenu-retur" @if(!in_array($active, ['retur', 'retur_masuk'])) x-cloak @endif x-show="openMenus.retur" x-transition.origin.top class="flex flex-col gap-1 pl-8 pr-1 py-1">
                    <a href="{{ url('/kasir/retur') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'retur' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'retur' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Ajukan Retur Barang</span>
                    </a>
                    <a href="{{ url('/kasir/retur/masuk') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'retur_masuk' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'retur_masuk' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Barang Masuk Retur</span>
                    </a>
                </div>
            </div>

            <!-- 5. Transaksi (Dropdown) -->
            <div class="flex flex-col">
                <button type="button"
                        @click="openMenus.transaksi = !openMenus.transaksi"
                        :aria-expanded="openMenus.transaksi ? 'true' : 'false'"
                        aria-controls="submenu-transaksi"
                        class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ in_array($active, ['transaksi_riwayat', 'transaksi_ditahan', 'transaksi_refund', 'transaksi']) ? 'bg-[#eef2ff] text-[#0052cc] font-bold' : 'text-gray-600 hover:bg-gray-200 font-semibold' }} cursor-pointer">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-rounded text-[20px] {{ in_array($active, ['transaksi_riwayat', 'transaksi_ditahan', 'transaksi_refund', 'transaksi']) ? 'filled' : '' }}">receipt_long</span>
                        <span>Transaksi</span>
                    </div>
                    <span class="material-symbols-rounded text-[18px] transition-transform duration-200 text-gray-400"
                          :class="openMenus.transaksi ? 'rotate-180 text-[#0052cc]' : ''">expand_more</span>
                </button>
                <div id="submenu-transaksi" @if(!in_array($active, ['transaksi_riwayat', 'transaksi_ditahan', 'transaksi_refund', 'transaksi'])) x-cloak @endif x-show="openMenus.transaksi" x-transition.origin.top class="flex flex-col gap-1 pl-8 pr-1 py-1">
                    <a href="{{ url('/kasir/transaksi/riwayat') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'transaksi_riwayat' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'transaksi_riwayat' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Riwayat Transaksi</span>
                    </a>
                    <a href="{{ url('/kasir/transaksi/ditahan') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'transaksi_ditahan' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'transaksi_ditahan' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Transaksi Ditahan</span>
                    </a>
                    <a href="{{ url('/kasir/transaksi/refund') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'transaksi_refund' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'transaksi_refund' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Refund</span>
                    </a>
                </div>
            </div>

            <!-- 6. Pelanggan / Anggota (Rename dari Members) -->
            <a href="{{ url('/kasir/members') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'members' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'members' ? 'filled' : '' }}">groups</span>
                <span>Pelanggan / Anggota</span>
            </a>

            <!-- 7. Laporan (Dropdown) -->
            <div class="flex flex-col">
                <button type="button"
                        @click="openMenus.laporan = !openMenus.laporan"
                        :aria-expanded="openMenus.laporan ? 'true' : 'false'"
                        aria-controls="submenu-laporan"
                        class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ in_array($active, ['reports', 'laporan_kasir', 'laporan_sesi', 'laporan_operasional', 'laporan']) ? 'bg-[#eef2ff] text-[#0052cc] font-bold' : 'text-gray-600 hover:bg-gray-200 font-semibold' }} cursor-pointer">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-rounded text-[20px] {{ in_array($active, ['reports', 'laporan_kasir', 'laporan_sesi', 'laporan_operasional']) ? 'filled' : '' }}">bar_chart</span>
                        <span>Laporan</span>
                    </div>
                    <span class="material-symbols-rounded text-[18px] transition-transform duration-200 text-gray-400"
                          :class="openMenus.laporan ? 'rotate-180 text-[#0052cc]' : ''">expand_more</span>
                </button>
                <div id="submenu-laporan" @if(!in_array($active, ['reports', 'laporan_kasir', 'laporan_sesi', 'laporan_operasional', 'laporan'])) x-cloak @endif x-show="openMenus.laporan" x-transition.origin.top class="flex flex-col gap-1 pl-8 pr-1 py-1">
                    <a href="{{ url('/kasir/reports') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'reports' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'reports' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Laporan Penjualan</span>
                    </a>
                    <a href="{{ url('/kasir/laporan/kasir') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'laporan_kasir' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'laporan_kasir' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Penjualan per Kasir</span>
                    </a>
                    <a href="{{ url('/kasir/laporan/sesi') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'laporan_sesi' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'laporan_sesi' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Laporan Sesi Kasir</span>
                    </a>
                    <a href="{{ url('/kasir/laporan/operasional') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'laporan_operasional' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'laporan_operasional' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Laporan Operasional</span>
                    </a>
                </div>
            </div>

            <!-- 8. Kupon -->
            <a href="{{ url('/kupon') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'kupon' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'kupon' ? 'filled' : '' }}">local_activity</span>
                <span>Kupon</span>
            </a>

            <!-- 9. Stock Opname -->
            <a href="{{ url('/kasir/stock-opname') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'stock_opname' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'stock_opname' ? 'filled' : '' }}">fact_check</span>
                <span>Stock Opname</span>
            </a>
        </nav>
    </div>

    <!-- Bottom Actions: Pengaturan, Profil Kasir & Tutup Sesi -->
    <div class="p-3 mb-1 flex flex-col gap-1.5 border-t border-gray-200/50 pt-3">
        <!-- 10. Pengaturan -->
        <a href="{{ url('/kasir/settings') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'settings' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
            <span class="material-symbols-rounded text-[20px] {{ $active === 'settings' ? 'filled' : '' }}">settings</span>
            <span>Pengaturan</span>
        </a>

        <!-- Profil Kasir & Cabang Aktif -->
        <div class="px-3 py-2 flex items-center gap-2.5 rounded-xl bg-white/70 border border-gray-200/70 shadow-2xs">
            <div class="w-8 h-8 rounded-full bg-[#0052cc] text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-2xs"
                 x-text="((activeOperator ? activeOperator.name : (activeSession ? activeSession.cashierName : (localStorage.getItem('pos_user_name') || 'Kasir'))).split(' ').filter(Boolean).map(w => w[0]).join('').substring(0, 2).toUpperCase()) || 'KS'">
                KS
            </div>
            <div class="flex flex-col overflow-hidden flex-1 min-w-0">
                <span class="text-[12px] font-bold text-gray-900 truncate leading-tight"
                      x-text="activeOperator ? activeOperator.name : (activeSession ? activeSession.cashierName : (localStorage.getItem('pos_user_name') || 'Kasir'))">Kasir</span>
                <span class="text-[10.5px] text-gray-500 truncate flex items-center gap-1 font-medium mt-0.5"
                      x-text="getSidebarBranch()">{{ $serverBranchLabel }}</span>
            </div>
        </div>

        <!-- 11. Tutup Sesi (Aksi Khusus) -->
        <button type="button"
                @click="openClosingWizard()"
                class="flex w-full items-center gap-2.5 px-3.5 py-2 text-red-600 hover:bg-red-50 rounded-xl transition-colors font-bold text-[13px] cursor-pointer">
            <span class="material-symbols-rounded text-[20px]">logout</span>
            <span>Tutup Sesi</span>
        </button>
    </div>
    </div>

    <!-- Attached Toggle Button (Melekat di Tepi Kanan Sidebar) -->
    <button type="button"
            @click="toggleSidebar()"
            title="Sembunyikan Sidebar (Ctrl+B)"
            aria-label="Sembunyikan Sidebar"
            class="sidebar-toggle-btn">
        <span class="material-symbols-rounded text-[18px]">chevron_left</span>
    </button>
    </aside>

    <!-- Floating Show-Sidebar Tab (Hanya muncul saat sidebar di-hide) -->
    <button type="button"
            x-cloak
            x-show="isSidebarCollapsed"
            @click="toggleSidebar()"
            title="Tampilkan Sidebar (Ctrl+B)"
            aria-label="Tampilkan Sidebar"
            class="sidebar-collapsed-btn">
        <span class="material-symbols-rounded text-[20px]">menu</span>
        <span class="text-xs font-bold tracking-wide">Sidebar</span>
    </button>

    <!-- Teleport Closing & Cash Movement Modal to Body -->
    <template x-teleport="body">
        <div>
            @include('kasir.partials._closing-handover-modal')
        </div>
    </template>
</div>
