@extends('layouts.app')

@section('title', 'Inventory - ILP POS')

@section('content')
<div class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800">
    
    <!-- Sidebar -->
    <x-cashier-sidebar active="inventory" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        <!-- Top Header -->
        <x-cashier-header title="Inventory Management" />

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-8">
            
            <!-- Filter & Search -->
            <div class="flex items-center gap-4 mb-6">
                <div class="relative flex-1 max-w-2xl">
                    <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                    <input type="text" placeholder="Cari nama produk atau scan barcode..." class="w-full pl-12 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 text-[13px] shadow-sm transition-all font-medium">
                </div>
                <div class="relative">
                    <select class="appearance-none bg-white border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-[13px] text-gray-700 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 shadow-sm transition-all font-semibold min-w-[200px]">
                        <option>Semua Kategori</option>
                        <option>Minuman</option>
                        <option>Snack</option>
                        <option>Kebutuhan Pokok</option>
                        <option>Pembersih</option>
                    </select>
                    <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- Card 1 -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm flex flex-col justify-between">
                    <span class="text-[11px] font-bold text-gray-500 tracking-wider">TOTAL ITEM</span>
                    <div class="flex items-center justify-between mt-3">
                        <span class="text-3xl font-bold text-gray-800 tracking-tight">1,248</span>
                        <span class="bg-blue-50 text-blue-600 text-[11px] font-bold px-2.5 py-1 rounded-md">+12 Baru</span>
                    </div>
                </div>
                <!-- Card 2 -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm flex flex-col justify-between border-l-4 border-l-amber-500 relative overflow-hidden">
                    <span class="text-[11px] font-bold text-gray-500 tracking-wider">STOK RENDAH</span>
                    <div class="flex items-center justify-between mt-3 relative z-10">
                        <span class="text-3xl font-bold text-amber-500 tracking-tight">24</span>
                        <span class="material-symbols-rounded text-amber-500 text-[26px]">warning</span>
                    </div>
                </div>
                <!-- Card 3 -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm flex flex-col justify-between border-l-4 border-l-red-500 relative overflow-hidden">
                    <span class="text-[11px] font-bold text-gray-500 tracking-wider">MENDEKATI EXPIRED</span>
                    <div class="flex items-center justify-between mt-3 relative z-10">
                        <span class="text-3xl font-bold text-red-500 tracking-tight">7</span>
                        <span class="material-symbols-rounded text-red-500 text-[26px]">event_busy</span>
                    </div>
                </div>
            </div>

            <!-- Data Table -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="px-6 py-4 text-[10px] font-bold text-gray-500 tracking-widest w-1/4">PRODUK</th>
                                <th class="px-4 py-4 text-[10px] font-bold text-gray-500 tracking-widest">BARCODE</th>
                                <th class="px-4 py-4 text-[10px] font-bold text-gray-500 tracking-widest">KATEGORI</th>
                                <th class="px-4 py-4 text-[10px] font-bold text-gray-500 tracking-widest">STOK</th>
                                <th class="px-4 py-4 text-[10px] font-bold text-gray-500 tracking-widest text-right">HARGA JUAL</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-gray-500 tracking-widest">STATUS</th>
                                <th class="px-4 py-4 text-[10px] font-bold text-gray-500 tracking-widest text-center">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <!-- Row 1 -->
                            <tr class="border-b border-gray-100 hover:bg-gray-50/80 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-800 text-[13px]">Aqua Mineral 600ml</span>
                                        <span class="text-[11px] text-gray-500 mt-0.5 font-medium">Danone Indonesia</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 font-mono font-medium text-gray-600 text-[12px]">8991234567890</td>
                                <td class="px-4 py-4">
                                    <span class="bg-[#eef2ff] text-[#0052cc] text-[11px] font-bold px-3 py-1 rounded-full">Minuman</span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-800 text-[13px]">450</span>
                                        <span class="text-[11px] text-gray-500 font-medium">Botol</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="flex flex-col items-end">
                                        <span class="text-[9px] text-gray-500 font-bold tracking-wider">Rp</span>
                                        <span class="font-mono font-bold text-gray-800 text-[13px]">5.500</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-1.5 text-green-600 font-bold text-[11px]">
                                        <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div>
                                        Tersedia
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <button class="text-gray-400 hover:text-gray-700 transition-colors">
                                        <span class="material-symbols-rounded text-[20px]">more_vert</span>
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Row 2 -->
                            <tr class="border-b border-amber-100 bg-amber-50/20 hover:bg-amber-50/40 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-800 text-[13px]">Chitato Sapi Panggang 68g</span>
                                        <span class="text-[11px] text-gray-500 mt-0.5 font-medium">Indofood</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 font-mono font-medium text-gray-600 text-[12px]">8992345678901</td>
                                <td class="px-4 py-4">
                                    <span class="bg-[#eef2ff] text-[#0052cc] text-[11px] font-bold px-3 py-1 rounded-full">Snack</span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-amber-600 text-[13px]">8</span>
                                        <span class="text-[11px] text-amber-500 font-medium">Pcs</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="flex flex-col items-end">
                                        <span class="text-[9px] text-gray-500 font-bold tracking-wider">Rp</span>
                                        <span class="font-mono font-bold text-gray-800 text-[13px]">12.800</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="inline-flex items-center gap-1 bg-amber-100 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-md font-bold text-[11px]">
                                        <span class="material-symbols-rounded text-[14px]">warning</span>
                                        Stok Rendah
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <button class="text-gray-400 hover:text-gray-700 transition-colors">
                                        <span class="material-symbols-rounded text-[20px]">more_vert</span>
                                    </button>
                                </td>
                            </tr>

                            <!-- Row 3 -->
                            <tr class="border-b border-red-100 bg-red-50/20 hover:bg-red-50/40 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-800 text-[13px]">Ultra Milk Full Cream 1L</span>
                                        <span class="text-[11px] text-gray-500 mt-0.5 font-medium">Ultrajaya</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 font-mono font-medium text-gray-600 text-[12px]">8993456789012</td>
                                <td class="px-4 py-4">
                                    <span class="bg-[#eef2ff] text-[#0052cc] text-[11px] font-bold px-3 py-1 rounded-full">Minuman</span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-800 text-[13px]">32</span>
                                        <span class="text-[11px] text-gray-500 font-medium">Karton</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="flex flex-col items-end">
                                        <span class="text-[9px] text-gray-500 font-bold tracking-wider">Rp</span>
                                        <span class="font-mono font-bold text-gray-800 text-[13px]">19.200</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="inline-flex items-center gap-1 bg-[#ffe4e6] text-red-600 border border-red-200 px-2 py-0.5 rounded-md font-bold text-[11px]">
                                        <span class="material-symbols-rounded text-[14px]">event_busy</span>
                                        Near Expired
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <button class="text-gray-400 hover:text-gray-700 transition-colors">
                                        <span class="material-symbols-rounded text-[20px]">more_vert</span>
                                    </button>
                                </td>
                            </tr>

                            <!-- Row 4 -->
                            <tr class="border-b border-gray-100 hover:bg-gray-50/80 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-800 text-[13px]">Beras Pandan Wangi 5kg</span>
                                        <span class="text-[11px] text-gray-500 mt-0.5 font-medium">Lokal Berdikari</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 font-mono font-medium text-gray-600 text-[12px]">8994567890123</td>
                                <td class="px-4 py-4">
                                    <span class="bg-[#eef2ff] text-[#0052cc] text-[11px] font-bold px-3 py-1 rounded-full whitespace-nowrap">Kebutuhan Pokok</span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-800 text-[13px]">120</span>
                                        <span class="text-[11px] text-gray-500 font-medium">Pack</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="flex flex-col items-end">
                                        <span class="text-[9px] text-gray-500 font-bold tracking-wider">Rp</span>
                                        <span class="font-mono font-bold text-gray-800 text-[13px]">82.000</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-1.5 text-green-600 font-bold text-[11px]">
                                        <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div>
                                        Tersedia
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <button class="text-gray-400 hover:text-gray-700 transition-colors">
                                        <span class="material-symbols-rounded text-[20px]">more_vert</span>
                                    </button>
                                </td>
                            </tr>

                            <!-- Row 5 -->
                            <tr class="hover:bg-gray-50/80 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-800 text-[13px]">Sunlight Jeruk Nipis 755ml</span>
                                        <span class="text-[11px] text-gray-500 mt-0.5 font-medium">Unilever</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 font-mono font-medium text-gray-600 text-[12px]">8995678901234</td>
                                <td class="px-4 py-4">
                                    <span class="bg-[#eef2ff] text-[#0052cc] text-[11px] font-bold px-3 py-1 rounded-full">Pembersih</span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-800 text-[13px]">54</span>
                                        <span class="text-[11px] text-gray-500 font-medium">Botol</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="flex flex-col items-end">
                                        <span class="text-[9px] text-gray-500 font-bold tracking-wider">Rp</span>
                                        <span class="font-mono font-bold text-gray-800 text-[13px]">15.500</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-1.5 text-green-600 font-bold text-[11px]">
                                        <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div>
                                        Tersedia
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <button class="text-gray-400 hover:text-gray-700 transition-colors">
                                        <span class="material-symbols-rounded text-[20px]">more_vert</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="border-t border-gray-200 px-6 py-4 flex items-center justify-between bg-white">
                    <span class="text-[13px] text-gray-500 font-medium">Menampilkan 5 dari 1,248 produk</span>
                    <div class="flex items-center gap-1">
                        <button class="w-8 h-8 flex items-center justify-center rounded border border-gray-200 text-gray-400 hover:bg-gray-50">
                            <span class="material-symbols-rounded text-[18px]">chevron_left</span>
                        </button>
                        <button class="w-8 h-8 flex items-center justify-center rounded bg-[#0052cc] text-white font-bold text-[13px] shadow-sm">1</button>
                        <button class="w-8 h-8 flex items-center justify-center rounded border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium text-[13px]">2</button>
                        <button class="w-8 h-8 flex items-center justify-center rounded border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium text-[13px]">3</button>
                        <span class="w-8 h-8 flex items-center justify-center text-gray-400 text-[13px]">...</span>
                        <button class="w-8 h-8 flex items-center justify-center rounded border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium text-[13px]">25</button>
                        <button class="w-8 h-8 flex items-center justify-center rounded border border-gray-200 text-gray-600 hover:bg-gray-50">
                            <span class="material-symbols-rounded text-[18px]">chevron_right</span>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="h-10"></div> <!-- Bottom spacing for FAB -->
        </div>

        <!-- Floating Action Button -->
        <button class="absolute bottom-10 right-10 w-[60px] h-[60px] bg-[#2563eb] hover:bg-blue-600 text-white rounded-full shadow-[0_8px_16px_rgba(37,99,235,0.4)] flex items-center justify-center transition-transform hover:scale-105 active:scale-95 focus:outline-none focus:ring-4 focus:ring-blue-500/30">
            <span class="material-symbols-rounded text-[28px]">qr_code_scanner</span>
        </button>
    </main>

</div>
@endsection
