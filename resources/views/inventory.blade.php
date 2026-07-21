@extends('layouts.app')

@section('title', 'Inventory Management - Vigilant POS')

@section('content')
<!-- Sidebar -->
<aside class="w-64 bg-slate-50 border-r border-gray-200 flex flex-col justify-between">
    <div>
        <div class="p-6">
            <h1 class="text-2xl font-bold text-blue-600">ILP POS</h1>
            <p class="text-sm text-gray-500 mt-1">Worker Coop Admin</p>
        </div>
        
        <nav class="mt-4 flex flex-col gap-1">
            <a href="#" class="flex items-center gap-3 px-6 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors">
                <span class="material-symbols-rounded text-gray-400">dashboard</span>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="#" class="flex items-center gap-3 px-6 py-3 bg-blue-50 text-blue-600 border-l-4 border-blue-600 relative">
                <span class="material-symbols-rounded filled text-blue-600">inventory_2</span>
                <span class="font-medium">Products</span>
            </a>
            <a href="#" class="flex items-center gap-3 px-6 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors">
                <span class="material-symbols-rounded text-gray-400">group</span>
                <span class="font-medium">Members</span>
            </a>
            <a href="#" class="flex items-center gap-3 px-6 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors">
                <span class="material-symbols-rounded text-gray-400">bar_chart</span>
                <span class="font-medium">Reports</span>
            </a>
            <a href="#" class="flex items-center gap-3 px-6 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors">
                <span class="material-symbols-rounded text-gray-400">settings</span>
                <span class="font-medium">Settings</span>
            </a>
        </nav>
    </div>

    <!-- User Profile -->
    <div class="p-6 border-t border-gray-200 flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-sm">
            AD
        </div>
        <div>
            <p class="font-semibold text-sm text-gray-800">Admin Utama</p>
            <p class="text-xs text-gray-500">Minimarket Pusat</p>
        </div>
    </div>
</aside>

<!-- Main Content -->
<main class="flex-1 flex flex-col bg-slate-50 relative h-full">
    <!-- Header -->
    <header class="h-20 border-b border-gray-200 flex items-center justify-between px-8 bg-white shrink-0">
        <h2 class="text-xl font-bold text-gray-800">Inventory Management</h2>
        <div class="flex items-center gap-6">
            <button class="text-gray-500 hover:text-gray-700 relative">
                <span class="material-symbols-rounded">notifications</span>
                <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full border border-white"></span>
            </button>
            <button class="text-gray-500 hover:text-gray-700">
                <span class="material-symbols-rounded">sync</span>
            </button>
            <div class="h-6 w-px bg-gray-300"></div>
            <div class="flex items-center gap-2 text-sm font-medium text-gray-600">
                <span class="material-symbols-rounded text-green-500 filled" style="font-size: 20px;">wifi</span>
                Terhubung
            </div>
        </div>
    </header>

    <!-- Scrollable content area -->
    <div class="flex-1 overflow-auto p-8 bg-slate-50">
        
        <!-- Search and Filter -->
        <div class="flex gap-4 mb-6">
            <div class="relative flex-1">
                <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                <input type="text" placeholder="Cari nama produk atau scan barcode..." class="w-full pl-11 pr-4 py-3 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 bg-white shadow-sm text-sm">
            </div>
            <div class="relative w-64">
                <select class="w-full pl-4 pr-10 py-3 appearance-none rounded-lg border border-gray-200 bg-white shadow-sm text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 cursor-pointer">
                    <option>Semua Kategori</option>
                    <option>Minuman</option>
                    <option>Snack</option>
                    <option>Kebutuhan Pokok</option>
                    <option>Pembersih</option>
                </select>
                <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">expand_more</span>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-3 gap-6 mb-6">
            <!-- Total Item -->
            <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm flex flex-col justify-center">
                <p class="text-xs font-bold text-gray-500 tracking-wider mb-2 uppercase">TOTAL ITEM</p>
                <div class="flex items-baseline gap-3">
                    <h3 class="text-4xl font-bold text-gray-900">1,248</h3>
                    <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-semibold">+12 Baru</span>
                </div>
            </div>
            
            <!-- Stok Rendah -->
            <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm flex flex-col justify-center">
                <p class="text-xs font-bold text-gray-500 tracking-wider mb-2 uppercase">STOK RENDAH</p>
                <div class="flex items-center justify-between">
                    <h3 class="text-4xl font-bold text-orange-500">24</h3>
                    <span class="material-symbols-rounded text-orange-500 text-3xl">warning</span>
                </div>
            </div>

            <!-- Mendekati Expired -->
            <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm flex flex-col justify-center relative overflow-hidden">
                <!-- Red accent line on the left -->
                <div class="absolute left-0 top-0 bottom-0 w-1 bg-red-600"></div>
                <p class="text-xs font-bold text-gray-500 tracking-wider mb-2 uppercase">MENDEKATI EXPIRED</p>
                <div class="flex items-center justify-between">
                    <h3 class="text-4xl font-bold text-red-600">7</h3>
                    <span class="material-symbols-rounded text-red-600 text-3xl">event_busy</span>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden flex flex-col">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 text-xs font-bold tracking-wider uppercase">
                    <tr>
                        <th class="px-6 py-4 rounded-tl-xl">PRODUK</th>
                        <th class="px-6 py-4">BARCODE</th>
                        <th class="px-6 py-4">KATEGORI</th>
                        <th class="px-6 py-4">STOK</th>
                        <th class="px-6 py-4 text-right">HARGA JUAL</th>
                        <th class="px-6 py-4">STATUS</th>
                        <th class="px-6 py-4 text-center rounded-tr-xl">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    <!-- Row 1 -->
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded border border-gray-200 flex items-center justify-center p-1 bg-white">
                                    <img src="https://placehold.co/100x100/e0f2fe/0284c7?text=Aqua" alt="Aqua" class="w-full h-full object-contain">
                                </div>
                                <div>
                                    <p class="font-bold text-gray-900">Aqua Mineral 600ml</p>
                                    <p class="text-xs text-gray-500">Danone Indonesia</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 font-mono text-gray-600">8991234567890</td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 bg-blue-100/50 text-blue-700 rounded-full text-xs font-semibold">Minuman</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col">
                                <span class="font-semibold text-gray-800">450</span>
                                <span class="text-[10px] text-gray-400">Botol</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right font-medium">Rp 5.500</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-1.5 font-bold text-green-600 text-sm">
                                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                Tersedia
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button class="text-gray-400 hover:text-gray-600"><span class="material-symbols-rounded">more_vert</span></button>
                        </td>
                    </tr>
                    
                    <!-- Row 2 -->
                    <tr class="hover:bg-gray-50 transition-colors bg-orange-50/20">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded border border-gray-200 flex items-center justify-center p-1 bg-white">
                                    <img src="https://placehold.co/100x100/ffedd5/ea580c?text=Chitato" alt="Chitato" class="w-full h-full object-contain">
                                </div>
                                <div>
                                    <p class="font-bold text-gray-900">Chitato Sapi Panggang 68g</p>
                                    <p class="text-xs text-gray-500">Indofood</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 font-mono text-gray-600">8992345678901</td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 bg-blue-100/50 text-blue-700 rounded-full text-xs font-semibold">Snack</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-baseline gap-1">
                                <span class="font-bold text-orange-500">8</span>
                                <span class="text-[10px] text-orange-400 font-medium">Pcs</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right font-medium">
                            <div class="flex flex-col items-end">
                                <span class="text-xs text-gray-400 line-through hidden"></span>
                                <span>Rp 12.800</span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-100 text-orange-700 rounded text-xs font-bold border border-orange-200">
                                <span class="material-symbols-rounded text-[16px]">priority_high</span>
                                Stok Rendah
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button class="text-gray-400 hover:text-gray-600"><span class="material-symbols-rounded">more_vert</span></button>
                        </td>
                    </tr>

                    <!-- Row 3 -->
                    <tr class="hover:bg-gray-50 transition-colors bg-red-50/10">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded border border-gray-200 flex items-center justify-center p-1 bg-white">
                                    <img src="https://placehold.co/100x100/eff6ff/3b82f6?text=Ultra" alt="Ultra Milk" class="w-full h-full object-contain">
                                </div>
                                <div>
                                    <p class="font-bold text-gray-900">Ultra Milk Full Cream 1L</p>
                                    <p class="text-xs text-gray-500">Ultrajaya</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 font-mono text-gray-600">8993456789012</td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 bg-blue-100/50 text-blue-700 rounded-full text-xs font-semibold">Minuman</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col">
                                <span class="font-semibold text-gray-800">32</span>
                                <span class="text-[10px] text-gray-400">Karton</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right font-medium">Rp 19.200</td>
                        <td class="px-6 py-4">
                            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-100 text-red-700 rounded text-xs font-bold border border-red-200">
                                <span class="material-symbols-rounded text-[16px]">event_busy</span>
                                Near Expired
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button class="text-gray-400 hover:text-gray-600"><span class="material-symbols-rounded">more_vert</span></button>
                        </td>
                    </tr>

                    <!-- Row 4 -->
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded border border-gray-200 flex items-center justify-center p-1 bg-white">
                                    <img src="https://placehold.co/100x100/fefce8/ca8a04?text=Beras" alt="Beras" class="w-full h-full object-contain">
                                </div>
                                <div>
                                    <p class="font-bold text-gray-900">Beras Pandan Wangi 5kg</p>
                                    <p class="text-xs text-gray-500">Lokal Berdikari</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 font-mono text-gray-600">8994567890123</td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 bg-blue-100/50 text-blue-700 rounded-full text-xs font-semibold">Kebutuhan Pokok</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-baseline gap-1">
                                <span class="font-semibold text-gray-800">120</span>
                                <span class="text-[10px] text-gray-400">Pack</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right font-medium">Rp 82.000</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-1.5 font-bold text-green-600 text-sm">
                                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                Tersedia
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button class="text-gray-400 hover:text-gray-600"><span class="material-symbols-rounded">more_vert</span></button>
                        </td>
                    </tr>

                    <!-- Row 5 -->
                    <tr class="hover:bg-gray-50 transition-colors border-b-0">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded border border-gray-200 flex items-center justify-center p-1 bg-white">
                                    <img src="https://placehold.co/100x100/f0fdf4/16a34a?text=Sunlight" alt="Sunlight" class="w-full h-full object-contain">
                                </div>
                                <div>
                                    <p class="font-bold text-gray-900">Sunlight Jeruk Nipis 755ml</p>
                                    <p class="text-xs text-gray-500">Unilever</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 font-mono text-gray-600">8995678901234</td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 bg-blue-100/50 text-blue-700 rounded-full text-xs font-semibold">Pembersih</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-baseline gap-1">
                                <span class="font-semibold text-gray-800">54</span>
                                <span class="text-[10px] text-gray-400">Botol</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right font-medium">Rp 15.500</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-1.5 font-bold text-green-600 text-sm">
                                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                Tersedia
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button class="text-gray-400 hover:text-gray-600"><span class="material-symbols-rounded">more_vert</span></button>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Pagination / Footer -->
            <div class="bg-white border-t border-gray-200 px-6 py-4 flex items-center justify-between">
                <p class="text-sm text-gray-500 font-medium">Menampilkan 5 dari 1,248 produk</p>
                <div class="flex items-center gap-2">
                    <button class="w-9 h-9 flex items-center justify-center rounded border border-gray-200 text-gray-400 hover:bg-gray-50 transition-colors disabled:opacity-50">
                        <span class="material-symbols-rounded text-[20px]">chevron_left</span>
                    </button>
                    <button class="w-9 h-9 flex items-center justify-center rounded bg-blue-700 text-white font-bold shadow-sm">1</button>
                    <button class="w-9 h-9 flex items-center justify-center rounded border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium transition-colors">2</button>
                    <button class="w-9 h-9 flex items-center justify-center rounded border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium transition-colors">3</button>
                    <span class="text-gray-400 px-1">...</span>
                    <button class="w-9 h-9 flex items-center justify-center rounded border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium transition-colors">25</button>
                    <button class="w-9 h-9 flex items-center justify-center rounded border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors">
                        <span class="material-symbols-rounded text-[20px]">chevron_right</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Action Button -->
    <button class="absolute bottom-8 right-8 w-14 h-14 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg shadow-blue-500/30 flex items-center justify-center transition-transform hover:scale-105 active:scale-95">
        <span class="material-symbols-rounded">qr_code_scanner</span>
    </button>
</main>
@endsection
