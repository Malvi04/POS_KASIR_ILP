@props(['active' => ''])

<aside x-data="{
           ...cashierOpeningData(),
           openMenus: {
               produk: {{ in_array($active, ['produk_masuk', 'inventory', 'produk']) ? 'true' : 'false' }},
               retur: {{ in_array($active, ['retur', 'retur_masuk']) ? 'true' : 'false' }},
               transaksi: {{ in_array($active, ['transaksi_riwayat', 'transaksi_ditahan', 'transaksi_refund', 'transaksi']) ? 'true' : 'false' }},
               laporan: {{ in_array($active, ['reports', 'laporan_kasir', 'laporan_sesi', 'laporan_operasional', 'laporan']) ? 'true' : 'false' }}
           }
       }"
       x-init="initOpening()"
       @pos-session-sync.window="initOpening()"
       @open-closing-wizard.window="openClosingWizard()"
       @open-cash-movement.window="showCashMovementModal = true"
       class="w-64 bg-[#f1f5f9] border-r border-gray-200 flex flex-col justify-between shrink-0 h-screen overflow-y-auto">
    <div>
        <!-- Terminal & Cashier Header -->
        <div class="px-6 py-5 border-b border-gray-200/50">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-black text-[#0052cc] tracking-tight">ILP POS</h1>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-blue-100 text-[#0052cc]" x-text="activeSession.terminalLabel">Kasir 01</span>
            </div>
            <p class="text-[11px] text-gray-500 mt-0.5 font-medium" x-text="activeSession.cashierName">Budi Kurniawan</p>
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
                        class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ in_array($active, ['produk_masuk', 'inventory', 'produk']) ? 'bg-[#eef2ff] text-[#0052cc] font-bold' : 'text-gray-600 hover:bg-gray-200 font-semibold' }} cursor-pointer">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-rounded text-[20px] {{ in_array($active, ['produk_masuk', 'inventory', 'produk']) ? 'filled' : '' }}">inventory_2</span>
                        <span>Produk</span>
                    </div>
                    <span class="material-symbols-rounded text-[18px] transition-transform duration-200 text-gray-400"
                          :class="openMenus.produk ? 'rotate-180 text-[#0052cc]' : ''">expand_more</span>
                </button>
                <div id="submenu-produk" x-show="openMenus.produk" x-transition.origin.top class="flex flex-col gap-1 pl-8 pr-1 py-1">
                    <a href="{{ url('/kasir/produk/masuk') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'produk_masuk' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'produk_masuk' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Barang Masuk</span>
                    </a>
                    <a href="{{ url('/kasir/inventory') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'inventory' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'inventory' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Informasi Stok</span>
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
                <div id="submenu-retur" x-show="openMenus.retur" x-transition.origin.top class="flex flex-col gap-1 pl-8 pr-1 py-1">
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
                <div id="submenu-transaksi" x-show="openMenus.transaksi" x-transition.origin.top class="flex flex-col gap-1 pl-8 pr-1 py-1">
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
                <div id="submenu-laporan" x-show="openMenus.laporan" x-transition.origin.top class="flex flex-col gap-1 pl-8 pr-1 py-1">
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

    <!-- Bottom Actions: Pengaturan & Tutup Sesi -->
    <div class="p-3 mb-1 flex flex-col gap-1 border-t border-gray-200/50 pt-3">
        <!-- 10. Pengaturan -->
        <a href="{{ url('/kasir/settings') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'settings' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
            <span class="material-symbols-rounded text-[20px] {{ $active === 'settings' ? 'filled' : '' }}">settings</span>
            <span>Pengaturan</span>
        </a>

        <!-- 11. Tutup Sesi (Aksi Khusus) -->
        <button type="button"
                @click="openClosingWizard()"
                class="flex w-full items-center gap-2.5 px-3.5 py-2.5 text-red-600 hover:bg-red-50 rounded-xl transition-colors font-bold text-[13px] mt-1 cursor-pointer">
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
