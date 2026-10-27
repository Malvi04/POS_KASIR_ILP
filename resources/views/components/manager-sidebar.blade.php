@props(['active' => ''])

<aside x-data="{
           openMenus: {
               persediaan: {{ in_array($active, ['inventory', 'stock_opname', 'produk_masuk']) ? 'true' : 'false' }},
               penjualan: {{ in_array($active, ['pos', 'transaksi_riwayat', 'transaksi_refund']) ? 'true' : 'false' }},
               keuangan: {{ in_array($active, ['session', 'kasir_sesi', 'laporan_sesi', 'laporan_operasional']) ? 'true' : 'false' }},
               logistik: {{ in_array($active, ['retur', 'retur_masuk']) ? 'true' : 'false' }},
               anggota: {{ in_array($active, ['members', 'proses_kredit']) ? 'true' : 'false' }},
               promosi: {{ in_array($active, ['promosi', 'kupon']) ? 'true' : 'false' }},
               laporan: {{ in_array($active, ['reports', 'laporan_kasir', 'kasir_reports', 'laporan']) ? 'true' : 'false' }}
           }
       }"
       class="w-64 bg-[#f1f5f9] border-r border-gray-200 flex flex-col justify-between shrink-0 h-screen overflow-y-auto">
    <div>
        <!-- Brand & Manager Header -->
        <div class="px-6 py-5 border-b border-gray-200/50">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-black text-[#0052cc] tracking-tight">ILP POS</h1>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-blue-100 text-[#0052cc]">Manager</span>
            </div>
            <p class="text-[11px] text-gray-500 mt-0.5 font-medium">Minimarket Pusat</p>
        </div>

        <!-- Navigation Items -->
        <nav class="mt-2 flex flex-col gap-1 px-3">
            <!-- 1. Dashboard -->
            <a href="{{ url('/dashboard') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'dashboard' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'dashboard' ? 'filled' : '' }}">grid_view</span>
                <span>Dashboard</span>
            </a>

            <!-- 2. Persediaan (Dropdown) -->
            <div class="flex flex-col">
                <button type="button"
                        @click="openMenus.persediaan = !openMenus.persediaan"
                        :aria-expanded="openMenus.persediaan ? 'true' : 'false'"
                        aria-controls="submenu-persediaan"
                        class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ in_array($active, ['inventory', 'stock_opname', 'produk_masuk']) ? 'bg-[#eef2ff] text-[#0052cc] font-bold' : 'text-gray-600 hover:bg-gray-200 font-semibold' }} cursor-pointer">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-rounded text-[20px] {{ in_array($active, ['inventory', 'stock_opname', 'produk_masuk']) ? 'filled' : '' }}">inventory_2</span>
                        <span>Persediaan</span>
                    </div>
                    <span class="material-symbols-rounded text-[18px] transition-transform duration-200 text-gray-400"
                          :class="openMenus.persediaan ? 'rotate-180 text-[#0052cc]' : ''">expand_more</span>
                </button>
                <div id="submenu-persediaan" x-show="openMenus.persediaan" x-transition.origin.top class="flex flex-col gap-1 pl-8 pr-1 py-1">
                    <a href="{{ url('/inventory') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'inventory' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'inventory' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Master Produk &amp; Stok</span>
                    </a>
                    <a href="{{ url('/stock-opname') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'stock_opname' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'stock_opname' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Stock Opname</span>
                    </a>
                    <a href="{{ url('/kasir/produk/masuk') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'produk_masuk' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'produk_masuk' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Penerimaan Barang</span>
                    </a>
                </div>
            </div>

            <!-- 3. Penjualan (Dropdown) -->
            <div class="flex flex-col">
                <button type="button"
                        @click="openMenus.penjualan = !openMenus.penjualan"
                        :aria-expanded="openMenus.penjualan ? 'true' : 'false'"
                        aria-controls="submenu-penjualan"
                        class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ in_array($active, ['pos', 'transaksi_riwayat', 'transaksi_refund']) ? 'bg-[#eef2ff] text-[#0052cc] font-bold' : 'text-gray-600 hover:bg-gray-200 font-semibold' }} cursor-pointer">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-rounded text-[20px] {{ in_array($active, ['pos', 'transaksi_riwayat', 'transaksi_refund']) ? 'filled' : '' }}">point_of_sale</span>
                        <span>Penjualan</span>
                    </div>
                    <span class="material-symbols-rounded text-[18px] transition-transform duration-200 text-gray-400"
                          :class="openMenus.penjualan ? 'rotate-180 text-[#0052cc]' : ''">expand_more</span>
                </button>
                <div id="submenu-penjualan" x-show="openMenus.penjualan" x-transition.origin.top class="flex flex-col gap-1 pl-8 pr-1 py-1">
                    <a href="{{ url('/pos') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'pos' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'pos' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Monitoring POS</span>
                    </a>
                    <a href="{{ url('/kasir/transaksi/riwayat') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'transaksi_riwayat' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'transaksi_riwayat' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Riwayat Transaksi</span>
                    </a>
                    <a href="{{ url('/kasir/transaksi/refund') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'transaksi_refund' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'transaksi_refund' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Refund Transaksi</span>
                    </a>
                </div>
            </div>

            <!-- 4. Keuangan (Dropdown) -->
            <div class="flex flex-col">
                <button type="button"
                        @click="openMenus.keuangan = !openMenus.keuangan"
                        :aria-expanded="openMenus.keuangan ? 'true' : 'false'"
                        aria-controls="submenu-keuangan"
                        class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ in_array($active, ['session', 'kasir_sesi', 'laporan_sesi', 'laporan_operasional']) ? 'bg-[#eef2ff] text-[#0052cc] font-bold' : 'text-gray-600 hover:bg-gray-200 font-semibold' }} cursor-pointer">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-rounded text-[20px] {{ in_array($active, ['session', 'kasir_sesi', 'laporan_sesi', 'laporan_operasional']) ? 'filled' : '' }}">payments</span>
                        <span>Keuangan</span>
                    </div>
                    <span class="material-symbols-rounded text-[18px] transition-transform duration-200 text-gray-400"
                          :class="openMenus.keuangan ? 'rotate-180 text-[#0052cc]' : ''">expand_more</span>
                </button>
                <div id="submenu-keuangan" x-show="openMenus.keuangan" x-transition.origin.top class="flex flex-col gap-1 pl-8 pr-1 py-1">
                    <a href="{{ url('/kasir/sesi') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ in_array($active, ['session', 'kasir_sesi']) ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ in_array($active, ['session', 'kasir_sesi']) ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Sesi &amp; Rekonsiliasi Kas</span>
                    </a>
                    <a href="{{ url('/kasir/laporan/sesi') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'laporan_sesi' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'laporan_sesi' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Audit Sesi &amp; Selisih</span>
                    </a>
                    <a href="{{ url('/kasir/laporan/operasional') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'laporan_operasional' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'laporan_operasional' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Arus Kas Operasional</span>
                    </a>
                </div>
            </div>

            <!-- 5. Logistik & Retur (Dropdown) -->
            <div class="flex flex-col">
                <button type="button"
                        @click="openMenus.logistik = !openMenus.logistik"
                        :aria-expanded="openMenus.logistik ? 'true' : 'false'"
                        aria-controls="submenu-logistik"
                        class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ in_array($active, ['retur', 'retur_masuk']) ? 'bg-[#eef2ff] text-[#0052cc] font-bold' : 'text-gray-600 hover:bg-gray-200 font-semibold' }} cursor-pointer">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-rounded text-[20px] {{ in_array($active, ['retur', 'retur_masuk']) ? 'filled' : '' }}">local_shipping</span>
                        <span>Logistik &amp; Retur</span>
                    </div>
                    <span class="material-symbols-rounded text-[18px] transition-transform duration-200 text-gray-400"
                          :class="openMenus.logistik ? 'rotate-180 text-[#0052cc]' : ''">expand_more</span>
                </button>
                <div id="submenu-logistik" x-show="openMenus.logistik" x-transition.origin.top class="flex flex-col gap-1 pl-8 pr-1 py-1">
                    <a href="{{ url('/retur') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'retur' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'retur' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Retur Toko ke DC</span>
                    </a>
                    <a href="{{ url('/kasir/retur/masuk') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'retur_masuk' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'retur_masuk' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Barang Masuk Retur</span>
                    </a>
                </div>
            </div>

            <!-- 6. Pelanggan & Anggota (Dropdown) -->
            <div class="flex flex-col">
                <button type="button"
                        @click="openMenus.anggota = !openMenus.anggota"
                        :aria-expanded="openMenus.anggota ? 'true' : 'false'"
                        aria-controls="submenu-anggota"
                        class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ in_array($active, ['members', 'proses_kredit']) ? 'bg-[#eef2ff] text-[#0052cc] font-bold' : 'text-gray-600 hover:bg-gray-200 font-semibold' }} cursor-pointer">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-rounded text-[20px] {{ in_array($active, ['members', 'proses_kredit']) ? 'filled' : '' }}">groups</span>
                        <span>Pelanggan &amp; Anggota</span>
                    </div>
                    <span class="material-symbols-rounded text-[18px] transition-transform duration-200 text-gray-400"
                          :class="openMenus.anggota ? 'rotate-180 text-[#0052cc]' : ''">expand_more</span>
                </button>
                <div id="submenu-anggota" x-show="openMenus.anggota" x-transition.origin.top class="flex flex-col gap-1 pl-8 pr-1 py-1">
                    <a href="{{ url('/members') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'members' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'members' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Data Anggota Kopkar</span>
                    </a>
                    <a href="{{ url('/proses-kredit') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'proses_kredit' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'proses_kredit' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Verifikasi Belanja Kredit</span>
                    </a>
                </div>
            </div>

            <!-- 7. Mitra UMKM Konsinyasi -->
            <a href="{{ url('/umkm-partners') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'umkm' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'umkm' ? 'filled' : '' }}">handshake</span>
                <span>Mitra UMKM Konsinyasi</span>
            </a>

            <!-- 8. Promosi & Kupon (Dropdown) -->
            <div class="flex flex-col">
                <button type="button"
                        @click="openMenus.promosi = !openMenus.promosi"
                        :aria-expanded="openMenus.promosi ? 'true' : 'false'"
                        aria-controls="submenu-promosi"
                        class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ in_array($active, ['promosi', 'kupon']) ? 'bg-[#eef2ff] text-[#0052cc] font-bold' : 'text-gray-600 hover:bg-gray-200 font-semibold' }} cursor-pointer">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-rounded text-[20px] {{ in_array($active, ['promosi', 'kupon']) ? 'filled' : '' }}">sell</span>
                        <span>Promosi &amp; Kupon</span>
                    </div>
                    <span class="material-symbols-rounded text-[18px] transition-transform duration-200 text-gray-400"
                          :class="openMenus.promosi ? 'rotate-180 text-[#0052cc]' : ''">expand_more</span>
                </button>
                <div id="submenu-promosi" x-show="openMenus.promosi" x-transition.origin.top class="flex flex-col gap-1 pl-8 pr-1 py-1">
                    <a href="{{ url('/promosi') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'promosi' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'promosi' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Program Promosi &amp; Diskon</span>
                    </a>
                    <a href="{{ url('/kupon') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'kupon' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'kupon' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Kupon &amp; Tiket Belanja</span>
                    </a>
                </div>
            </div>

            <!-- 9. Operasional Karyawan -->
            <a href="{{ url('/absensi') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'absensi' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'absensi' ? 'filled' : '' }}">badge</span>
                <span>Absensi Karyawan</span>
            </a>

            <!-- 10. Laporan & Analitik (Dropdown) -->
            <div class="flex flex-col">
                <button type="button"
                        @click="openMenus.laporan = !openMenus.laporan"
                        :aria-expanded="openMenus.laporan ? 'true' : 'false'"
                        aria-controls="submenu-laporan"
                        class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ in_array($active, ['reports', 'laporan_kasir', 'kasir_reports', 'laporan']) ? 'bg-[#eef2ff] text-[#0052cc] font-bold' : 'text-gray-600 hover:bg-gray-200 font-semibold' }} cursor-pointer">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-rounded text-[20px] {{ in_array($active, ['reports', 'laporan_kasir', 'kasir_reports']) ? 'filled' : '' }}">bar_chart</span>
                        <span>Laporan &amp; Analitik</span>
                    </div>
                    <span class="material-symbols-rounded text-[18px] transition-transform duration-200 text-gray-400"
                          :class="openMenus.laporan ? 'rotate-180 text-[#0052cc]' : ''">expand_more</span>
                </button>
                <div id="submenu-laporan" x-show="openMenus.laporan" x-transition.origin.top class="flex flex-col gap-1 pl-8 pr-1 py-1">
                    <a href="{{ url('/reports') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'reports' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'reports' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Laporan Penjualan &amp; Margin</span>
                    </a>
                    <a href="{{ url('/kasir/laporan/kasir') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'laporan_kasir' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'laporan_kasir' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Penjualan per Kasir</span>
                    </a>
                    <a href="{{ url('/kasir/reports') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'kasir_reports' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'kasir_reports' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Rekap Transaksi Toko</span>
                    </a>
                </div>
            </div>

            <!-- 11. Kelola Akun -->
            <a href="{{ url('/kelola-akun') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'akun' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'akun' ? 'filled' : '' }}">manage_accounts</span>
                <span>Kelola Akun</span>
            </a>
        </nav>
    </div>

    <!-- Bottom Actions: Pengaturan, Manager Profile & Logout -->
    <div class="p-3 mb-1 flex flex-col gap-1 border-t border-gray-200/50 pt-3">
        <!-- Pengaturan -->
        <a href="{{ url('/settings') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'settings' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
            <span class="material-symbols-rounded text-[20px] {{ $active === 'settings' ? 'filled' : '' }}">settings</span>
            <span>Pengaturan</span>
        </a>

        <!-- Manager Profile Card -->
        <div class="px-3.5 py-2 flex items-center gap-3 rounded-xl bg-white/70 border border-gray-200/70 mt-1">
            <div class="w-9 h-9 rounded-full bg-[#0052cc] flex items-center justify-center text-white font-bold text-xs shrink-0 shadow-2xs">
                AD
            </div>
            <div class="flex flex-col overflow-hidden flex-1">
                <span class="text-[13px] font-bold text-gray-900 truncate leading-tight">Admin Manager</span>
                <span class="text-[11px] text-gray-500 truncate">Minimarket Pusat</span>
            </div>
        </div>

        <!-- Keluar ke Portal -->
        <a href="{{ url('/') }}" data-no-spa class="flex w-full items-center justify-center gap-2 px-3.5 py-2 text-red-600 hover:bg-red-50 rounded-xl transition-colors font-bold text-[12.5px] cursor-pointer mt-1">
            <span class="material-symbols-rounded text-[18px]">logout</span>
            <span>Keluar ke Portal</span>
        </a>
    </div>
</aside>
