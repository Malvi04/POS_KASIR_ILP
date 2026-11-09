@props(['active' => ''])

<aside class="w-64 bg-white border-r border-slate-200/80 flex flex-col justify-between shrink-0 h-screen select-none z-20">
    <div class="flex flex-col">
        <!-- Brand & App Identity (Clickable to Owner Dashboard) -->
        <a href="{{ url('/owner') }}" 
           class="px-6 py-5 border-b border-slate-100 flex items-center gap-3 hover:bg-slate-50/70 transition-colors group"
           title="Kembali ke Dashboard Utama Owner">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#0052cc] to-[#1e40af] flex items-center justify-center text-white shadow-md shadow-blue-500/20 shrink-0 group-hover:scale-105 transition-transform">
                <span class="material-symbols-rounded text-[22px]">shield</span>
            </div>
            <div class="flex flex-col overflow-hidden">
                <h1 class="text-[17px] font-black text-slate-900 tracking-tight leading-none group-hover:text-[#0052cc] transition-colors">ILP POS</h1>
                <p class="text-[11px] font-semibold text-slate-500 mt-1 tracking-tight">Worker Coop Admin</p>
            </div>
        </a>

        <!-- Main Navigation Links (All 8 Core Modules Connected) -->
        <nav class="mt-4 px-3 space-y-1">
            <!-- 1. Dashboard -->
            <a href="{{ url('/owner') }}" 
               class="flex items-center gap-3.5 px-3.5 py-2.5 transition-all text-[13px] {{ $active === 'dashboard' ? 'bg-[#eef4ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc] rounded-l-xl rounded-r-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium rounded-xl' }}">
                <span class="material-symbols-rounded text-[21px] {{ $active === 'dashboard' ? 'filled' : '' }}">grid_view</span>
                <span>Dashboard</span>
            </a>

            <!-- 2. Cabang & Gudang -->
            <a href="{{ url('/owner/cabang') }}" 
               class="flex items-center gap-3.5 px-3.5 py-2.5 transition-all text-[13px] {{ $active === 'cabang' ? 'bg-[#eef4ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc] rounded-l-xl rounded-r-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium rounded-xl' }}">
                <span class="material-symbols-rounded text-[21px] {{ $active === 'cabang' ? 'filled' : '' }}">storefront</span>
                <span>Cabang &amp; Gudang</span>
            </a>

            <!-- 3. Products / Inventory -->
            <a href="{{ url('/owner/products') }}" 
               class="flex items-center gap-3.5 px-3.5 py-2.5 transition-all text-[13px] {{ $active === 'products' || $active === 'inventory' ? 'bg-[#eef4ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc] rounded-l-xl rounded-r-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium rounded-xl' }}">
                <span class="material-symbols-rounded text-[21px] {{ $active === 'products' || $active === 'inventory' ? 'filled' : '' }}">inventory_2</span>
                <span>Products &amp; Stok</span>
            </a>

            <!-- 4. Members / Anggota -->
            <a href="{{ url('/owner/members') }}" 
               class="flex items-center gap-3.5 px-3.5 py-2.5 transition-all text-[13px] {{ $active === 'members' ? 'bg-[#eef4ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc] rounded-l-xl rounded-r-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium rounded-xl' }}">
                <span class="material-symbols-rounded text-[21px] {{ $active === 'members' ? 'filled' : '' }}">group</span>
                <span>Members / Anggota</span>
            </a>

            <!-- 5. UMKM Partners -->
            <a href="{{ url('/owner/umkm-partners') }}" 
               class="flex items-center gap-3.5 px-3.5 py-2.5 transition-all text-[13px] {{ $active === 'umkm' || $active === 'umkm-partners' ? 'bg-[#eef4ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc] rounded-l-xl rounded-r-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium rounded-xl' }}">
                <span class="material-symbols-rounded text-[21px] {{ $active === 'umkm' || $active === 'umkm-partners' ? 'filled' : '' }}">handshake</span>
                <span>Mitra UMKM</span>
            </a>

            <!-- 6. Reports -->
            <a href="{{ url('/owner/reports') }}" 
               class="flex items-center gap-3.5 px-3.5 py-2.5 transition-all text-[13px] {{ $active === 'reports' ? 'bg-[#eef4ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc] rounded-l-xl rounded-r-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium rounded-xl' }}">
                <span class="material-symbols-rounded text-[21px] {{ $active === 'reports' ? 'filled' : '' }}">bar_chart</span>
                <span>Laporan Laba Rugi</span>
            </a>

            <!-- 7. Kelola Akun & Hak Akses -->
            <a href="{{ url('/owner/kelola-akun') }}" 
               class="flex items-center gap-3.5 px-3.5 py-2.5 transition-all text-[13px] {{ in_array($active, ['users', 'kelola-akun']) ? 'bg-[#eef4ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc] rounded-l-xl rounded-r-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium rounded-xl' }}">
                <span class="material-symbols-rounded text-[21px] {{ in_array($active, ['users', 'kelola-akun']) ? 'filled' : '' }}">admin_panel_settings</span>
                <span>Kelola Akun &amp; Akses</span>
            </a>

            <!-- 8. Audit Log / Riwayat Aktivitas -->
            <a href="{{ url('/owner/audit-log') }}" 
               class="flex items-center gap-3.5 px-3.5 py-2.5 transition-all text-[13px] {{ in_array($active, ['audit-log', 'audit', 'activity-log']) ? 'bg-[#eef4ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc] rounded-l-xl rounded-r-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium rounded-xl' }}">
                <span class="material-symbols-rounded text-[21px] {{ in_array($active, ['audit-log', 'audit', 'activity-log']) ? 'filled' : '' }}">history</span>
                <span>Audit Log &amp; Riwayat</span>
            </a>

            <!-- 9. Pengaturan Toko -->
            <a href="{{ url('/owner/settings') }}" 
               class="flex items-center gap-3.5 px-3.5 py-2.5 transition-all text-[13px] {{ $active === 'settings' ? 'bg-[#eef4ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc] rounded-l-xl rounded-r-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium rounded-xl' }}">
                <span class="material-symbols-rounded text-[21px] {{ $active === 'settings' ? 'filled' : '' }}">tune</span>
                <span>Pengaturan Toko</span>
            </a>

            <!-- 10. Profil Owner -->
            <a href="{{ url('/owner/profile') }}" 
               class="flex items-center gap-3.5 px-3.5 py-2.5 transition-all text-[13px] {{ $active === 'profile' ? 'bg-[#eef4ff] text-[#0052cc] font-bold border-r-4 border-[#0052cc] rounded-l-xl rounded-r-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-medium rounded-xl' }}">
                <span class="material-symbols-rounded text-[21px] {{ $active === 'profile' ? 'filled' : '' }}">account_circle</span>
                <span>Profil Owner</span>
            </a>
        </nav>
    </div>

    <!-- Bottom: Owner Profile & Quick Portal Navigation -->
    <div class="p-3 mb-2 flex flex-col gap-2 border-t border-slate-100" 
         x-data="{ 
             showProfileModal: false, 
             ownerName: 'Dewi Sartika', 
             ownerEmail: 'OWNER@COOP.ID', 
             ownerAvatar: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150&auto=format&fit=crop&q=80',
             initProfile() {
                 try {
                     const p = JSON.parse(localStorage.getItem('pos_ilp_owner_profile_v1'));
                     if (p) {
                         if (p.name) this.ownerName = p.name;
                         if (p.email) this.ownerEmail = p.email.toUpperCase();
                         if (p.avatar) this.ownerAvatar = p.avatar;
                     }
                 } catch(e){}
             }
         }"
         x-init="initProfile()">
        <!-- Owner Profile Card (Clickable to view Profile Modal) -->
        <div @click="showProfileModal = true"
             class="p-2.5 rounded-2xl bg-slate-50 border border-slate-200/70 flex items-center gap-3 hover:bg-slate-100/70 transition-colors cursor-pointer group"
             title="Klik untuk melihat profil pemilik">
            <div class="relative w-9 h-9 shrink-0">
                <img :src="ownerAvatar" 
                     alt="Owner Avatar" 
                     class="w-9 h-9 rounded-full object-cover border-2 border-white shadow-2xs group-hover:scale-105 transition-transform"
                     onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=Dewi+Sartika&background=0052cc&color=fff&bold=true'">
                <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 rounded-full ring-2 ring-white"></span>
            </div>
            <div class="flex flex-col min-w-0 flex-1">
                <span class="text-[12.5px] font-bold text-slate-800 leading-tight truncate" x-text="ownerName">Dewi Sartika</span>
                <span class="text-[10px] font-semibold text-slate-500 tracking-wider uppercase truncate" x-text="ownerEmail">OWNER@COOP.ID</span>
            </div>
            <span class="material-symbols-rounded text-[18px] text-slate-400 group-hover:text-slate-600">expand_less</span>
        </div>

        <!-- Exit to Portal / Switch Role Button -->
        <a href="{{ url('/') }}" data-no-spa class="flex items-center justify-center gap-2 px-3 py-1.5 rounded-xl text-slate-500 hover:text-red-600 hover:bg-red-50 text-[11.5px] font-medium transition-colors">
            <span class="material-symbols-rounded text-[16px]">logout</span>
            <span>Keluar ke Portal</span>
        </a>

        <!-- Interactive Profile Modal -->
        <template x-teleport="body">
            <div x-show="showProfileModal"
                 x-transition.opacity
                 class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4"
                 style="display: none;">
                <div @click.away="showProfileModal = false"
                     class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-sm w-full p-6 space-y-4">
                    <div class="text-center">
                        <img :src="ownerAvatar"
                             :alt="ownerName"
                             class="w-20 h-20 rounded-full mx-auto object-cover border-4 border-blue-50 shadow-md">
                        <h3 class="text-lg font-black text-slate-900 mt-3" x-text="ownerName">Dewi Sartika</h3>
                        <span class="inline-block px-3 py-0.5 rounded-full text-[11px] font-extrabold bg-blue-100 text-[#0052cc] mt-1">
                            Pemilik Usaha (Owner)
                        </span>
                        <p class="text-xs text-slate-500 mt-1" x-text="ownerEmail + ' • NIK: 327501550230056'"></p>
                    </div>

                    <div class="bg-slate-50 rounded-2xl p-3.5 space-y-2 text-xs border border-slate-100">
                        <div class="flex justify-between">
                            <span class="text-slate-500 font-medium">Hak Otorisasi</span>
                            <span class="font-bold text-slate-800">Akses Penuh Multi-Cabang</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500 font-medium">Sesi Login Aktif</span>
                            <span class="font-bold text-emerald-600">Online (Perangkat Ini)</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500 font-medium">Koperasi</span>
                            <span class="font-bold text-slate-800">Kopkar ILP Maju Bersama</span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 pt-2">
                        <a href="{{ url('/owner/profile') }}" 
                           @click="showProfileModal = false"
                           class="w-full py-2.5 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-xs font-bold text-center transition-colors">
                            Buka Pengaturan Profil Owner
                        </a>
                        <a href="{{ url('/owner/kelola-akun') }}" 
                           @click="showProfileModal = false"
                           class="w-full py-2 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-bold text-center transition-colors">
                            Buka Manajemen User &amp; Akses
                        </a>
                        <button type="button" 
                                @click="showProfileModal = false"
                                class="w-full py-2 rounded-xl text-slate-500 hover:bg-slate-50 text-xs font-medium transition-colors">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</aside>
