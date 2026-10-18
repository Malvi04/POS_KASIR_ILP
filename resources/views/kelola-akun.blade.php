@extends('layouts.app')

@section('title', 'Kelola Akun - ILP POS')

@section('content')
<div class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">
    
    <!-- Sidebar -->
    <x-manager-sidebar active="akun" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden bg-[#f8f9fa]">
        
        <!-- Top Header -->
        <header class="h-[68px] bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <h1 class="text-[18px] font-bold text-gray-900 tracking-tight">Kelola akun</h1>

            <div class="flex items-center gap-5 text-gray-600">
                <button type="button" class="hover:text-gray-900 transition-colors p-1 rounded-lg hover:bg-gray-100" title="Notifikasi">
                    <span class="material-symbols-rounded text-[22px]">notifications_active</span>
                </button>
                <button type="button" class="hover:text-gray-900 transition-colors p-1 rounded-lg hover:bg-gray-100" title="Sinkronisasi">
                    <span class="material-symbols-rounded text-[22px]">sync</span>
                </button>
                <div class="h-6 w-px bg-gray-200"></div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-rounded text-green-600 text-[20px]">wifi</span>
                    <span class="text-[13px] font-semibold text-gray-700">Terhubung</span>
                </div>
            </div>
        </header>

        <!-- Scrollable Content Workspace -->
        <div class="flex-1 overflow-y-auto p-6 lg:p-8">
            <div class="max-w-[1400px] mx-auto grid grid-cols-12 gap-6 items-start">
                
                <!-- Left Column: Forms & System Security Log (8 cols) -->
                <div class="col-span-12 xl:col-span-8 space-y-6 min-w-0">
                    
                    <!-- Card 1: Tambah Kasir Baru -->
                    <section class="bg-white border border-gray-200/90 rounded-2xl p-6 lg:p-7 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
                            <div>
                                <h2 class="text-[17px] font-bold text-[#0052cc] tracking-tight">Tambah Kasir Baru</h2>
                                <p class="text-[13px] text-gray-500 mt-0.5">Otoritas Manager: Tambahkan personel operasional.</p>
                            </div>
                            <span class="bg-[#dbeafe] text-[#0052cc] text-[10.5px] font-extrabold px-3.5 py-1 rounded-full tracking-wider uppercase shrink-0">
                                AKSES MANAGER
                            </span>
                        </div>

                        <form onsubmit="return false;" class="space-y-5">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                                <!-- Nama Lengkap -->
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-600 tracking-wider uppercase mb-2">
                                        NAMA LENGKAP
                                    </label>
                                    <input type="text" placeholder="Contoh: Budi Santoso" 
                                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-[13.5px] text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 transition-all">
                                </div>

                                <!-- Username -->
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-600 tracking-wider uppercase mb-2">
                                        USERNAME
                                    </label>
                                    <input type="text" placeholder="cashier_budi" 
                                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-[13.5px] text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 transition-all">
                                </div>

                                <!-- Spacer on desktop to align Cabang Penugasan in right column -->
                                <div class="hidden md:block"></div>

                                <!-- Cabang Penugasan -->
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-600 tracking-wider uppercase mb-2">
                                        CABANG PENUGASAN
                                    </label>
                                    <div class="relative">
                                        <select class="w-full appearance-none px-4 py-2.5 pr-10 rounded-xl border border-gray-200 bg-white text-[13.5px] font-medium text-gray-800 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 transition-all cursor-pointer">
                                            <option value="Cabang A">Cabang A</option>
                                            <option value="Cabang B">Cabang B</option>
                                            <option value="Cabang Pusat">Cabang Pusat</option>
                                        </select>
                                        <span class="material-symbols-rounded absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[20px]">expand_more</span>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-1 flex justify-end">
                                <button type="submit" class="bg-[#0052cc] hover:bg-[#0043a8] active:scale-[0.99] text-white px-5 py-2.5 rounded-xl font-bold text-[13px] inline-flex items-center gap-2 shadow-sm transition-all cursor-pointer">
                                    <span class="material-symbols-rounded text-[18px]">person_add</span>
                                    Daftarkan Kasir
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- Card 2: Registrasi Manager Cabang -->
                    <section x-data="{ accessLevel: 'full' }" class="bg-white border border-gray-200/90 border-l-[3.5px] border-l-[#9a3412] rounded-2xl p-6 lg:p-7 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
                            <div>
                                <h2 class="text-[17px] font-bold text-[#9a3412] tracking-tight">Registrasi Manager Cabang</h2>
                                <p class="text-[13px] text-gray-500 mt-0.5">Otoritas Owner: Penugasan level manajemen tinggi.</p>
                            </div>
                            <span class="bg-[#ffedd5] text-[#9a3412] text-[10.5px] font-extrabold px-3.5 py-1 rounded-full tracking-wider uppercase shrink-0">
                                AKSES OWNER
                            </span>
                        </div>

                        <form onsubmit="return false;" class="space-y-5">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                                <!-- Nama Lengkap Manager -->
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-600 tracking-wider uppercase mb-2">
                                        NAMA LENGKAP MANAGER
                                    </label>
                                    <input type="text" placeholder="Contoh: Siti Aminah" 
                                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-[13.5px] text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#9a3412] focus:ring-2 focus:ring-[#9a3412]/15 transition-all">
                                </div>

                                <!-- Email Profesional -->
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-600 tracking-wider uppercase mb-2">
                                        EMAIL PROFESIONAL
                                    </label>
                                    <input type="email" placeholder="manager.siti@pos.com" 
                                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-[13.5px] text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#9a3412] focus:ring-2 focus:ring-[#9a3412]/15 transition-all">
                                </div>

                                <!-- Level Akses -->
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-600 tracking-wider uppercase mb-2">
                                        LEVEL AKSES
                                    </label>
                                    <div class="grid grid-cols-2 gap-3">
                                        <button type="button" @click="accessLevel = 'full'"
                                                :class="accessLevel === 'full' 
                                                    ? 'bg-[#fff7ed] border-[#9a3412] text-[#9a3412] font-bold' 
                                                    : 'bg-white border-gray-200 text-gray-500 font-semibold hover:bg-gray-50'"
                                                class="px-3.5 py-2.5 rounded-xl border text-[12.5px] inline-flex items-center justify-center gap-1.5 transition-all cursor-pointer">
                                            <span class="material-symbols-rounded text-[16px]">verified_user</span>
                                            <span class="truncate">Full Access</span>
                                        </button>

                                        <button type="button" @click="accessLevel = 'reporting'"
                                                :class="accessLevel === 'reporting' 
                                                    ? 'bg-[#fff7ed] border-[#9a3412] text-[#9a3412] font-bold' 
                                                    : 'bg-white border-gray-200 text-gray-500 font-semibold hover:bg-gray-50'"
                                                class="px-3.5 py-2.5 rounded-xl border text-[12.5px] inline-flex items-center justify-center gap-1.5 transition-all cursor-pointer">
                                            <span class="material-symbols-rounded text-[16px]">visibility</span>
                                            <span class="truncate">Reporting Only</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-1 flex justify-end">
                                <button type="submit" class="bg-[#9a3412] hover:bg-[#7c2d12] active:scale-[0.99] text-white px-5 py-2.5 rounded-xl font-bold text-[13px] inline-flex items-center gap-2 shadow-sm transition-all cursor-pointer">
                                    <span class="material-symbols-rounded text-[18px]">admin_panel_settings</span>
                                    Registrasikan Manager
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- Card 3: Catatan Keamanan Sistem -->
                    <section class="bg-gradient-to-r from-[#1e242b] via-[#1b2535] to-[#112340] text-white rounded-2xl p-6 shadow-md">
                        <div class="flex items-center gap-2.5 mb-2">
                            <span class="material-symbols-rounded filled text-[20px] text-gray-200">security</span>
                            <h3 class="text-[15.5px] font-bold tracking-tight">Catatan Keamanan Sistem</h3>
                        </div>
                        <p class="text-[12.5px] text-gray-300 leading-relaxed max-w-2xl mb-4">
                            Aktivitas registrasi dan perubahan hak akses diaudit secara otomatis untuk keamanan transaksi.
                        </p>
                        <div class="space-y-2 text-[12px]">
                            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <span class="font-mono text-gray-400 shrink-0">14:20:05</span>
                                <span class="text-gray-300">User [Ahmad Fauzi] berhasil didaftarkan oleh Manager [Diana Putri]</span>
                            </div>
                            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <span class="font-mono text-gray-400 shrink-0">09:12:33</span>
                                <span class="text-gray-300">Perubahan Level Akses [Diana Putri] oleh Owner</span>
                            </div>
                        </div>
                    </section>

                </div>

                <!-- Right Column: Active Users & Help Card (4 cols) -->
                <div class="col-span-12 xl:col-span-4 space-y-6 min-w-0">
                    
                    <!-- Card 1: Daftar Pengguna Aktif -->
                    <section class="bg-white border border-gray-200/90 rounded-2xl p-5 lg:p-6 shadow-sm flex flex-col">
                        <h2 class="text-[15.5px] font-bold text-[#0052cc] tracking-tight mb-3.5">Daftar Pengguna Aktif</h2>

                        <!-- Search Box -->
                        <div class="relative mb-2">
                            <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                            <input type="text" placeholder="Cari nama atau peran..." 
                                   class="w-full pl-10 pr-4 py-2 rounded-xl border border-gray-200 bg-white text-[12.5px] text-gray-700 placeholder-gray-400 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 transition-all">
                        </div>

                        <!-- User List -->
                        <div class="divide-y divide-gray-100">
                            <!-- User 1 -->
                            <div class="py-3.5 flex items-start gap-3.5">
                                <div class="w-10 h-10 rounded-full bg-[#dbeafe] text-[#1e40af] flex items-center justify-center shrink-0 mt-0.5">
                                    <span class="material-symbols-rounded text-[20px]">account_circle</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-[13.5px] font-bold text-gray-900 truncate">Ahmad Fauzi</h3>
                                    <p class="text-[11.5px] text-gray-500 truncate mt-0.5">Cabang A • Kasir 1</p>
                                    <span class="inline-block mt-1.5 bg-green-100 text-green-700 text-[9.5px] font-extrabold px-2 py-0.5 rounded uppercase tracking-wider">
                                        KASIR
                                    </span>
                                </div>
                            </div>

                            <!-- User 2 -->
                            <div class="py-3.5 flex items-start gap-3.5">
                                <div class="w-10 h-10 rounded-full bg-[#dbeafe] text-[#1e40af] flex items-center justify-center shrink-0 mt-0.5">
                                    <span class="material-symbols-rounded text-[20px]">account_circle</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-[13.5px] font-bold text-gray-900 truncate">Rico Wijaya</h3>
                                    <p class="text-[11.5px] text-gray-500 truncate mt-0.5">Cabang B • Kasir 2</p>
                                    <span class="inline-block mt-1.5 bg-green-100 text-green-700 text-[9.5px] font-extrabold px-2 py-0.5 rounded uppercase tracking-wider">
                                        KASIR
                                    </span>
                                </div>
                            </div>

                            <!-- User 3 -->
                            <div class="py-3.5 flex items-start gap-3.5">
                                <div class="w-10 h-10 rounded-full bg-[#dbeafe] text-[#1e40af] flex items-center justify-center shrink-0 mt-0.5">
                                    <span class="material-symbols-rounded text-[20px]">account_circle</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-[13.5px] font-bold text-gray-900 truncate">Santi Larasati</h3>
                                    <p class="text-[11.5px] text-gray-500 truncate mt-0.5">Cabang Pusat • Kasie 4</p>
                                    <span class="inline-block mt-1.5 bg-green-100 text-green-700 text-[9.5px] font-extrabold px-2 py-0.5 rounded uppercase tracking-wider">
                                        KASIR
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Action -->
                        <button type="button" class="mt-4 w-full py-2.5 rounded-xl border border-[#0052cc] text-[#0052cc] hover:bg-blue-50 font-bold text-[12.5px] transition-colors cursor-pointer">
                            Lihat Semua Pengguna
                        </button>
                    </section>

                    <!-- Card 2: Butuh Bantuan? -->
                    <section class="bg-[#f4f6fa] border border-gray-200/80 rounded-2xl p-6 text-center flex flex-col items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-[#0052cc] mb-2">
                            <span class="material-symbols-rounded text-[26px]">info</span>
                        </div>
                        <h3 class="text-[14.5px] font-bold text-gray-900">Butuh Bantuan?</h3>
                        <p class="text-[12px] text-gray-500 mt-1 max-w-[230px] leading-relaxed">
                            Lihat panduan penugasan peran dan shift kerja.
                        </p>
                        <a href="#" class="mt-3 inline-flex items-center gap-0.5 text-[12.5px] font-bold text-[#0052cc] hover:underline">
                            <span>Buka Tutorial</span>
                            <span class="material-symbols-rounded text-[16px]">chevron_right</span>
                        </a>
                    </section>

                </div>

            </div>
        </div>

    </main>
</div>
@endsection
