@props(['active' => ''])

<aside x-data="{
           currentRole: (new URLSearchParams(window.location.search).get('role') || localStorage.getItem('pos_user_role') || 'Manager'),
           userName: (localStorage.getItem('pos_user_name') || 'Admin Manager'),
           userBranch: (localStorage.getItem('pos_user_branch') || 'Minimarket Pusat'),
           openMenus: {
               persediaan: {{ in_array($active, ['inventory', 'stock_opname', 'kirim_barang']) ? 'true' : 'false' }},
               retur: {{ in_array($active, ['retur', 'retur_masuk']) ? 'true' : 'false' }},
               anggota: {{ in_array($active, ['members', 'member_detail']) ? 'true' : 'false' }}
           },
           isOwner() {
               return (this.currentRole || '').toLowerCase() === 'owner' || (this.currentRole || '').toLowerCase() === 'superadmin';
           },
           getNavUrl(path) {
               if (this.isOwner()) {
                   return path + (path.includes('?') ? '&' : '?') + 'role=owner';
               }
               return path;
           }
       }"
       class="w-64 bg-[#f1f5f9] border-r border-gray-200 flex flex-col justify-between shrink-0 h-screen overflow-y-auto">
    <div>
        <!-- Brand & Role Header -->
        <a :href="getNavUrl('{{ url('/dashboard') }}')" class="block px-6 py-5 border-b border-gray-200/50 hover:bg-slate-200/40 transition-colors group">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-black tracking-tight group-hover:scale-105 transition-transform" :class="isOwner() ? 'text-purple-900' : 'text-[#0052cc]'">ILP POS</h1>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase"
                      :class="isOwner() ? 'bg-purple-100 text-purple-900 border border-purple-300' : 'bg-blue-100 text-[#0052cc]'"
                      x-text="isOwner() ? 'Owner' : 'Manager'">Manager</span>
            </div>
            <p class="text-[11px] text-gray-500 mt-0.5 font-medium"
               x-text="isOwner() ? 'Konsolidasi 3 Cabang' : userBranch">Minimarket Pusat</p>
        </a>

        <!-- Navigation Items -->
        <nav class="mt-2 flex flex-col gap-1 px-3">
            <!-- 1. Dashboard -->
            <a :href="getNavUrl('{{ url('/dashboard') }}')" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'dashboard' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'dashboard' ? 'filled' : '' }}">grid_view</span>
                <span>Dashboard</span>
            </a>

            <!-- 2. Persediaan (Dropdown Accordion) -->
            <div class="flex flex-col">
                <button type="button"
                        @click="openMenus.persediaan = !openMenus.persediaan"
                        :aria-expanded="openMenus.persediaan ? 'true' : 'false'"
                        aria-controls="submenu-persediaan"
                        class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ in_array($active, ['inventory', 'stock_opname', 'kirim_barang']) ? 'bg-[#eef2ff] text-[#0052cc] font-bold' : 'text-gray-600 hover:bg-gray-200 font-semibold' }} cursor-pointer">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-rounded text-[20px] {{ in_array($active, ['inventory', 'stock_opname', 'kirim_barang']) ? 'filled' : '' }}">inventory_2</span>
                        <span>Persediaan</span>
                    </div>
                    <span class="material-symbols-rounded text-[18px] transition-transform duration-200 text-gray-400"
                          :class="openMenus.persediaan ? 'rotate-180 text-[#0052cc]' : ''">expand_more</span>
                </button>
                <div id="submenu-persediaan" x-show="openMenus.persediaan" x-transition.origin.top class="flex flex-col gap-1 pl-8 pr-1 py-1">
                    <a :href="getNavUrl('{{ url('/inventory') }}')" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'inventory' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'inventory' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Master Produk &amp; Stok</span>
                    </a>
                    <a :href="getNavUrl('{{ url('/kirim-barang') }}')" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'kirim_barang' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'kirim_barang' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Kirim Barang</span>
                    </a>
                    <a :href="getNavUrl('{{ url('/stock-opname') }}')" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'stock_opname' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'stock_opname' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Stock Opname</span>
                    </a>
                </div>
            </div>

            <!-- 3. Retur Barang (Dropdown Accordion) -->
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
                    <a :href="getNavUrl('{{ url('/retur') }}')" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'retur' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'retur' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Persetujuan Retur</span>
                    </a>
                    <a :href="getNavUrl('{{ url('/retur-masuk') }}')" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'retur_masuk' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'retur_masuk' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Barang Masuk Retur</span>
                    </a>
                </div>
            </div>

            <!-- 4. Pelanggan & Anggota (Dropdown Accordion) -->
            <div class="flex flex-col">
                <button type="button"
                        @click="openMenus.anggota = !openMenus.anggota"
                        :aria-expanded="openMenus.anggota ? 'true' : 'false'"
                        aria-controls="submenu-anggota"
                        class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ in_array($active, ['members', 'member_detail']) ? 'bg-[#eef2ff] text-[#0052cc] font-bold' : 'text-gray-600 hover:bg-gray-200 font-semibold' }} cursor-pointer">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-rounded text-[20px] {{ in_array($active, ['members', 'member_detail']) ? 'filled' : '' }}">groups</span>
                        <span>Pelanggan &amp; Anggota</span>
                    </div>
                    <span class="material-symbols-rounded text-[18px] transition-transform duration-200 text-gray-400"
                          :class="openMenus.anggota ? 'rotate-180 text-[#0052cc]' : ''">expand_more</span>
                </button>
                <div id="submenu-anggota" x-show="openMenus.anggota" x-transition.origin.top class="flex flex-col gap-1 pl-8 pr-1 py-1">
                    <a :href="getNavUrl('{{ url('/members') }}')" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'members' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'members' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Daftar Anggota</span>
                    </a>
                    <a :href="getNavUrl('{{ url('/member-detail') }}')" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12.5px] transition-colors {{ $active === 'member_detail' ? 'bg-[#0052cc] text-white font-bold' : 'text-gray-600 hover:bg-gray-200 font-medium' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active === 'member_detail' ? 'bg-white' : 'bg-gray-400' }}"></span>
                        <span>Detail &amp; Riwayat</span>
                    </a>
                </div>
            </div>

            <!-- 5. Mitra UMKM Konsinyasi -->
            <a :href="getNavUrl('{{ url('/umkm-partners') }}')" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'umkm' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'umkm' ? 'filled' : '' }}">handshake</span>
                <span>Mitra UMKM Konsinyasi</span>
            </a>

            <!-- 6. Promosi & Diskon -->
            <a :href="getNavUrl('{{ url('/promosi') }}')" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'promosi' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'promosi' ? 'filled' : '' }}">sell</span>
                <span>Promosi &amp; Diskon</span>
            </a>

            <!-- 7. Absensi Karyawan -->
            <a :href="getNavUrl('{{ url('/absensi') }}')" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'absensi' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'absensi' ? 'filled' : '' }}">badge</span>
                <span>Absensi Karyawan</span>
            </a>

            <!-- 8. Laporan & Analitik -->
            <a :href="getNavUrl('{{ url('/reports') }}')" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'reports' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'reports' ? 'filled' : '' }}">bar_chart</span>
                <span>Laporan &amp; Analitik</span>
            </a>

            <!-- 8b. Shortcut Owner: Konsolidasi 3 Cabang & P&L -->
            <a x-show="isOwner()" :href="getNavUrl('{{ url('/reports') }}?tab=consolidated_3branches')" class="flex items-center justify-between px-3.5 py-2 rounded-xl transition-all text-[12.5px] bg-purple-50 hover:bg-purple-100 text-purple-900 font-bold border border-purple-200 shadow-2xs">
                <div class="flex items-center gap-2.5">
                    <span class="material-symbols-rounded text-[18px] text-purple-700">account_balance</span>
                    <span>Konsolidasi 3 Cabang</span>
                </div>
                <span class="px-1.5 py-0.5 rounded text-[8.5px] font-black bg-purple-700 text-white uppercase tracking-wider">Owner</span>
            </a>

            <!-- 9. Kelola Akun -->
            <a :href="getNavUrl('{{ url('/kelola-akun') }}')" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'akun' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
                <span class="material-symbols-rounded text-[20px] {{ $active === 'akun' ? 'filled' : '' }}">manage_accounts</span>
                <span>Kelola Akun</span>
            </a>
        </nav>
    </div>

    <!-- Bottom Actions: Pengaturan, Profile & Logout -->
    <div class="p-3 mb-1 flex flex-col gap-1 border-t border-gray-200/50 pt-3">
        <!-- Pengaturan -->
        <a :href="getNavUrl('{{ url('/settings') }}')" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors text-[13px] {{ $active === 'settings' ? 'bg-[#eef2ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc]' : 'text-gray-600 hover:bg-gray-200 font-semibold' }}">
            <span class="material-symbols-rounded text-[20px] {{ $active === 'settings' ? 'filled' : '' }}">settings</span>
            <span>Pengaturan</span>
        </a>

        <!-- Profile Card (Dynamic Owner / Manager) -->
        <div class="px-3.5 py-2 flex items-center gap-3 rounded-xl bg-white/70 border border-gray-200/70 mt-1 shadow-2xs">
            <div class="w-9 h-9 rounded-full flex items-center justify-center text-white font-bold text-xs shrink-0 shadow-2xs"
                 :class="isOwner() ? 'bg-purple-900 ring-2 ring-purple-300' : 'bg-[#0052cc]'"
                 x-text="isOwner() ? 'HW' : 'AD'">
                AD
            </div>
            <div class="flex flex-col overflow-hidden flex-1">
                <span class="text-[13px] font-bold text-gray-900 truncate leading-tight"
                      x-text="isOwner() ? 'Ir. Hartono Wibowo' : userName">Admin Manager</span>
                <span class="text-[11px] text-gray-500 truncate"
                      x-text="isOwner() ? 'Pemilik / Superadmin' : userBranch">Minimarket Pusat</span>
            </div>
        </div>

        <!-- Keluar ke Portal -->
        <a href="{{ url('/') }}" data-no-spa @click="localStorage.removeItem('pos_user_role'); localStorage.removeItem('pos_user_name');" class="flex w-full items-center justify-center gap-2 px-3.5 py-2 text-red-600 hover:bg-red-50 rounded-xl transition-colors font-bold text-[12.5px] cursor-pointer mt-1">
            <span class="material-symbols-rounded text-[18px]">logout</span>
            <span>Keluar ke Portal</span>
        </a>
    </div>
</aside>
