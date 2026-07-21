@extends('layouts.app')

@section('title', 'Settings - Vigilant POS')

@section('content')
<!-- Sidebar -->
<aside class="w-64 bg-slate-50 border-r border-gray-200 flex flex-col justify-between shrink-0">
    <div>
        <div class="p-6">
            <h1 class="text-2xl font-bold text-blue-600">Vigilant POS</h1>
            <p class="text-sm text-gray-500 mt-1">Worker Coop Admin</p>
        </div>
        
        <nav class="mt-4 flex flex-col gap-1">
            <a href="/dashboard" class="flex items-center gap-3 px-6 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors">
                <span class="material-symbols-rounded text-gray-400">dashboard</span>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="/inventory" class="flex items-center gap-3 px-6 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors">
                <span class="material-symbols-rounded text-gray-400">inventory_2</span>
                <span class="font-medium">Products</span>
            </a>
            <a href="/members" class="flex items-center gap-3 px-6 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors">
                <span class="material-symbols-rounded text-gray-400">group</span>
                <span class="font-medium">Members</span>
            </a>
            <a href="/reports" class="flex items-center gap-3 px-6 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors">
                <span class="material-symbols-rounded text-gray-400">bar_chart</span>
                <span class="font-medium">Reports</span>
            </a>
            <a href="/settings" class="flex items-center gap-3 px-6 py-3 bg-blue-50 text-blue-600 border-l-4 border-blue-600 relative">
                <span class="material-symbols-rounded filled text-blue-600">settings</span>
                <span class="font-medium">Settings</span>
            </a>
        </nav>
    </div>

    <!-- User Profile (Bottom) -->
    <div class="p-6 border-t border-gray-200 flex flex-col gap-4">
        <div class="flex items-center gap-3">
            <img src="https://ui-avatars.com/api/?name=AH&background=f1f5f9&color=0f172a&rounded=true" alt="Ahmad Hidayat" class="w-10 h-10 rounded-full">
            <div>
                <p class="font-bold text-sm text-gray-800">Ahmad Hidayat</p>
                <p class="text-xs text-gray-500">Kasir</p>
            </div>
        </div>
        <button class="flex items-center gap-2 text-red-500 font-medium text-sm hover:bg-red-50 py-2 px-3 rounded-lg transition-colors -ml-3 w-fit">
            <span class="material-symbols-rounded">logout</span>
            Logout
        </button>
    </div>
</aside>

<!-- Main Content -->
<main class="flex-1 flex flex-col bg-white relative h-full">
    <!-- Header -->
    <header class="h-20 border-b border-gray-200 flex items-center justify-between px-8 bg-white shrink-0">
        <h2 class="text-xl font-bold text-gray-800">Pengaturan Terminal</h2>
        <div class="flex items-center gap-6">
            <button class="text-gray-500 hover:text-gray-700 relative">
                <span class="material-symbols-rounded">notifications</span>
            </button>
            <button class="text-green-500 hover:text-green-600">
                <span class="material-symbols-rounded filled">wifi</span>
            </button>
            <button class="text-gray-500 hover:text-gray-700">
                <span class="material-symbols-rounded">sync</span>
            </button>
            <div class="h-6 w-px bg-gray-300"></div>
            <div class="flex items-center text-sm font-medium text-gray-500">
                ID TERMINAL: #01
            </div>
        </div>
    </header>

    <!-- Scrollable Content -->
    <div class="flex-1 overflow-auto p-8 bg-slate-50">
        <div class="max-w-[1200px] mx-auto space-y-6">
            
            <!-- Top Section: Profile & System Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- User Card -->
                <div class="bg-white rounded-xl border border-gray-200 p-6 flex flex-col justify-between relative overflow-hidden">
                    <!-- Faint ID card icon in background -->
                    <span class="material-symbols-rounded absolute -right-6 top-6 text-9xl text-slate-50 opacity-50 z-0">badge</span>
                    
                    <div class="flex items-start gap-5 relative z-10">
                        <div class="relative">
                            <img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?q=80&w=200&auto=format&fit=crop" alt="Profile" class="w-20 h-20 rounded-xl object-cover border border-gray-200 bg-gray-100">
                            <div class="absolute -bottom-2 -right-2 w-6 h-6 bg-green-500 rounded-full border-2 border-white flex items-center justify-center">
                                <span class="material-symbols-rounded text-white text-[14px] font-bold">check</span>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Ahmad Hidayat</h3>
                            <div class="flex items-center text-gray-500 text-sm mt-1">
                                <span class="material-symbols-rounded text-[18px] mr-1">badge</span>
                                NIK: 202409821003
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mt-6 relative z-10">
                        <div class="bg-blue-50 rounded-lg p-3 border border-blue-100">
                            <p class="text-[10px] font-bold text-gray-500 tracking-wider mb-1 uppercase">SHIFT AKTIF</p>
                            <p class="text-blue-700 font-bold text-sm">Pagi (07:00 - 15:00)</p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-3 border border-green-100">
                            <p class="text-[10px] font-bold text-gray-500 tracking-wider mb-1 uppercase">STATUS LOGIN</p>
                            <p class="text-green-600 font-bold text-sm">Tervalidasi</p>
                        </div>
                    </div>
                    
                    <button class="mt-6 w-1/2 flex items-center justify-center gap-2 border border-blue-600 text-blue-600 font-bold py-2.5 rounded-lg hover:bg-blue-50 transition-colors relative z-10">
                        <span class="material-symbols-rounded text-[20px]">history</span>
                        Ganti PIN Keamanan
                    </button>
                </div>
                
                <!-- System Info Card -->
                <div class="bg-blue-600 rounded-xl p-6 text-white relative overflow-hidden flex flex-col justify-between">
                    <!-- Faint info icon background -->
                    <span class="material-symbols-rounded absolute -right-8 -bottom-8 text-[180px] text-blue-500 opacity-50 z-0 select-none pointer-events-none">info</span>
                    
                    <div class="relative z-10">
                        <span class="inline-block bg-blue-500 text-blue-50 text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-wider mb-4">
                            INFORMASI SISTEM
                        </span>
                        <h3 class="text-xl font-bold">Vigilant POS v2.4.1</h3>
                        
                        <div class="mt-6 space-y-4 text-sm">
                            <div class="flex justify-between items-center border-b border-blue-500/50 pb-3">
                                <span class="text-blue-100">Cabang</span>
                                <span class="font-bold">Minimarket Pusat</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-blue-500/50 pb-3">
                                <span class="text-blue-100">ID Terminal</span>
                                <span class="font-bold">TERM-01-JKT</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-blue-100">OS Environment</span>
                                <span class="font-bold">Windows 11</span>
                            </div>
                        </div>
                    </div>
                    
                    <p class="text-[11px] text-blue-200 italic mt-6 relative z-10">Update Terakhir: 12 Jan 2024 04:30</p>
                </div>
            </div>
            
            <!-- Middle Section: Hardware & Prefs -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Printer Config -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center">
                                <span class="material-symbols-rounded">print</span>
                            </div>
                            <h3 class="font-bold text-gray-800 text-sm">Konfigurasi Printer</h3>
                        </div>
                        <div class="flex items-center gap-1.5 px-2.5 py-1 bg-green-50 text-green-700 rounded-full border border-green-200 text-[11px] font-bold">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                            QZ Tray Terhubung
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-[11px] font-bold text-gray-700 mb-2">Pilih Printer Struk</label>
                        <div class="relative">
                            <select class="w-full pl-4 pr-10 py-2.5 appearance-none rounded-lg bg-slate-50 border border-gray-200 text-gray-700 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 cursor-pointer">
                                <option>EPSON TM-T82II Receipt (Default)</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">expand_more</span>
                        </div>
                    </div>
                    
                    <div class="flex gap-4 mb-6">
                        <button class="flex-1 flex items-center justify-center gap-2 border border-gray-200 bg-white font-bold text-gray-700 text-sm py-2 rounded-lg hover:bg-gray-50 transition-colors">
                            <span class="material-symbols-rounded text-[20px]">print</span>
                            Test Print
                        </button>
                        <button class="flex-1 flex items-center justify-center gap-2 border border-gray-200 bg-white font-bold text-gray-700 text-sm py-2 rounded-lg hover:bg-gray-50 transition-colors">
                            <span class="material-symbols-rounded text-[20px]">refresh</span>
                            Reset QZ
                        </button>
                    </div>
                    
                    <div class="bg-slate-100/80 rounded-lg p-4 flex gap-3 text-xs text-gray-600">
                        <span class="material-symbols-rounded text-blue-500 text-[20px]">info</span>
                        <p class="leading-relaxed">Pastikan aplikasi <strong>QZ Tray v2.2.0</strong> atau lebih tinggi sudah berjalan di taskbar sebelum melakukan pencetakan struk.</p>
                    </div>
                </div>
                
                <!-- Terminal Prefs -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-500 flex items-center justify-center">
                            <span class="material-symbols-rounded">tune</span>
                        </div>
                        <h3 class="font-bold text-gray-800 text-sm">Preferensi Terminal</h3>
                    </div>
                    
                    <div class="space-y-4">
                        <!-- Toggle 1 -->
                        <div class="flex items-center justify-between p-4 bg-slate-50/50 rounded-xl border border-gray-100">
                            <div class="flex items-center gap-4">
                                <span class="material-symbols-rounded text-gray-400">receipt_long</span>
                                <div>
                                    <p class="font-bold text-gray-800 text-sm">Auto-print Struk</p>
                                    <p class="text-[11px] text-gray-500 mt-0.5">Cetak otomatis setelah pembayaran sukses</p>
                                </div>
                            </div>
                            <!-- Switch ON -->
                            <div class="w-11 h-6 bg-blue-600 rounded-full relative cursor-pointer flex items-center px-0.5 transition-colors">
                                <div class="w-5 h-5 bg-white rounded-full absolute right-0.5 shadow transition-all"></div>
                            </div>
                        </div>
                        
                        <!-- Toggle 2 -->
                        <div class="flex items-center justify-between p-4 bg-slate-50/50 rounded-xl border border-gray-100">
                            <div class="flex items-center gap-4">
                                <span class="material-symbols-rounded text-gray-400">bolt</span>
                                <div>
                                    <p class="font-bold text-gray-800 text-sm">Mode Cepat (F2 Focus)</p>
                                    <p class="text-[11px] text-gray-500 mt-0.5">Fokus otomatis ke kolom pencarian</p>
                                </div>
                            </div>
                            <!-- Switch OFF -->
                            <div class="w-11 h-6 bg-gray-300 rounded-full relative cursor-pointer flex items-center px-0.5 transition-colors">
                                <div class="w-5 h-5 bg-white rounded-full absolute left-0.5 shadow transition-all"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bottom Section: Data & Sync -->
            <div class="bg-white rounded-xl border border-gray-200 p-6 mb-8">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                            <span class="material-symbols-rounded text-[24px]">sync_alt</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800 text-sm">Data & Sinkronisasi</h3>
                            <p class="text-gray-500 text-xs mt-0.5">Kelola pengiriman data transaksi ke pusat</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-6 md:divide-x md:divide-gray-200">
                        <div class="text-right">
                            <p class="text-[10px] font-bold text-gray-500 tracking-wider mb-1 uppercase">STATUS DATABASE</p>
                            <p class="font-mono text-gray-800 font-bold text-sm">v14.2-local (OK)</p>
                        </div>
                        <div class="md:pl-6 text-right">
                            <p class="text-[10px] font-bold text-gray-500 tracking-wider mb-1 uppercase">PENDING TRANSAKSI</p>
                            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-orange-50 text-orange-600 rounded text-xs font-bold border border-orange-200">
                                <span class="text-orange-500">12 Antrean</span>
                                <span class="material-symbols-rounded text-[14px]">warning</span>
                            </div>
                        </div>
                        <div class="md:pl-6">
                            <button class="bg-blue-700 hover:bg-blue-800 text-white font-bold text-sm py-2.5 px-5 rounded-lg shadow-sm flex items-center gap-2 transition-colors">
                                <span class="material-symbols-rounded text-[20px]">sync</span>
                                Sinkronisasi Manual
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>
@endsection
