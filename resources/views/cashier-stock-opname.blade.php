@extends('layouts.app')

@section('title', 'Stock Opname Kasir - ILP POS')

@section('content')
<div class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">
    
    <!-- Sidebar -->
    <x-cashier-sidebar active="stock_opname" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        
        <!-- Top Header -->
        <header class="h-[68px] bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <h1 class="text-[16px] font-black text-gray-900 tracking-tight">Stock Opname</h1>

            <div class="flex items-center gap-4 text-gray-600">
                <button class="hover:text-gray-900 transition-colors flex items-center justify-center">
                    <span class="material-symbols-rounded text-[22px]">notifications_active</span>
                </button>
                <div class="text-green-600 flex items-center justify-center">
                    <span class="material-symbols-rounded text-[22px]">wifi</span>
                </div>
                <button class="hover:text-gray-900 transition-colors flex items-center justify-center">
                    <span class="material-symbols-rounded text-[22px]">sync</span>
                </button>
                
                <div class="h-5 w-px bg-gray-200 mx-1"></div>
                
                <span class="text-[13px] font-bold text-gray-600 tracking-wide">ID TERMINAL: #01</span>
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-8 flex flex-col">
            <div class="max-w-[1400px] w-full mx-auto flex flex-col flex-1">
                
                <!-- Summary Cards -->
                <div class="grid grid-cols-4 gap-6 mb-6">
                    <!-- Card 1 -->
                    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm flex items-center gap-4">
                        <div class="w-12 h-12 rounded-lg bg-[#dbeafe]/60 text-[#0052cc] flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">assignment_turned_in</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-500 tracking-wider mb-1 uppercase">TOTAL PRODUK</p>
                            <h3 class="text-[22px] font-black text-gray-900 leading-none font-mono tracking-tight">1,248</h3>
                        </div>
                    </div>
                    
                    <!-- Card 2 -->
                    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm flex items-center gap-4">
                        <div class="w-12 h-12 rounded-lg bg-green-50 text-green-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">done_all</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-500 tracking-wider mb-1 uppercase">SUDAH AUDIT</p>
                            <div class="flex items-baseline gap-1.5">
                                <h3 class="text-[22px] font-black text-gray-900 leading-none font-mono tracking-tight">842</h3>
                                <span class="text-[12px] font-semibold text-gray-400 font-mono">(67%)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Card 3 -->
                    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm flex items-center gap-4">
                        <div class="w-12 h-12 rounded-lg bg-red-50 text-red-500 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">warning</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-500 tracking-wider mb-1 uppercase">SELISIH STOK</p>
                            <h3 class="text-[22px] font-black text-red-500 leading-none font-mono tracking-tight">-24</h3>
                        </div>
                    </div>

                    <!-- Card 4 -->
                    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm flex items-center gap-4">
                        <div class="w-12 h-12 rounded-lg bg-orange-50 text-orange-500 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">payments</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-500 tracking-wider mb-1 uppercase">NILAI SELISIH (RP)</p>
                            <h3 class="text-[22px] font-black text-orange-500 leading-none font-mono tracking-tight">-842.500</h3>
                        </div>
                    </div>
                </div>

                <!-- Main Table Container (Search + Table + Pagination) -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden flex flex-col mb-6">
                    
                    <!-- Search and Filter Bar inside Card -->
                    <div class="p-4 border-b border-gray-100 flex items-center justify-between gap-4">
                        <div class="relative w-full max-w-[420px]">
                            <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                            <input type="text" placeholder="Cari produk berdasarkan nama atau barcode..." class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-[#0052cc]/20 focus:border-[#0052cc] text-[13px] text-gray-700 placeholder-gray-400">
                        </div>
                        <div class="flex items-center gap-2.5 shrink-0">
                            <button class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-[13px] font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                                <span class="material-symbols-rounded text-[18px] text-gray-500">filter_list</span>
                                Filter
                            </button>
                            <button class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-[13px] font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                                <span class="material-symbols-rounded text-[18px] text-gray-500">download</span>
                                Export
                            </button>
                        </div>
                    </div>

                    <!-- Data Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-[13px] whitespace-nowrap">
                            <thead class="bg-[#f8f9fa] border-b border-gray-200 text-gray-500 text-[11px] font-bold tracking-wider uppercase">
                                <tr>
                                    <th class="px-6 py-3.5 w-[38%]">PRODUK</th>
                                    <th class="px-6 py-3.5 text-center">STOK SISTEM</th>
                                    <th class="px-6 py-3.5 text-center">STOK FISIK</th>
                                    <th class="px-6 py-3.5 text-center">SELISIH</th>
                                    <th class="px-6 py-3.5 text-left w-[16%]">KETERANGAN</th>
                                    <th class="px-6 py-3.5 text-center">AKSI</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 divide-y divide-gray-100">
                                <!-- Row 1 -->
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-gray-900 text-[13.5px]">Indomie Goreng Original</span>
                                            <span class="text-[11.5px] text-gray-400 font-mono mt-0.5">8991234567890</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center font-bold text-gray-800 font-mono text-[14px]">40</td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-center">
                                            <input type="text" value="38" class="w-[72px] text-center px-2 py-1.5 border border-gray-200 rounded-md focus:outline-none focus:border-[#0052cc] text-[14px] font-bold font-mono text-gray-800">
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="font-bold text-red-500 font-mono text-[14px]">-2</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="relative inline-block w-[110px]">
                                            <select class="appearance-none w-full bg-white border border-gray-200 rounded-md px-3 py-1.5 pr-7 text-[12.5px] font-medium text-gray-700 focus:outline-none focus:border-[#0052cc]">
                                                <option>Hilang</option>
                                                <option>Rusak</option>
                                            </select>
                                            <span class="material-symbols-rounded absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[16px]">expand_more</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button class="bg-[#0052cc] hover:bg-[#0043a8] text-white px-4 py-1.5 rounded-md font-bold text-[12px] shadow-sm transition-colors">Confirm</button>
                                    </td>
                                </tr>
                                
                                <!-- Row 2 -->
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-gray-900 text-[13.5px]">Aqua Botol 600ml</span>
                                            <span class="text-[11.5px] text-gray-400 font-mono mt-0.5">8999876543210</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center font-bold text-gray-800 font-mono text-[14px]">120</td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-center">
                                            <input type="text" value="120" class="w-[72px] text-center px-2 py-1.5 border border-green-500 bg-white rounded-md focus:outline-none text-[14px] font-bold font-mono text-gray-800">
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="font-bold text-green-600 font-mono text-[14px]">0</span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-400 font-medium pl-8">-</td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="inline-flex items-center gap-1 bg-green-100/80 text-green-700 px-3 py-1.5 rounded-md font-bold text-[12px]">
                                            <span class="material-symbols-rounded text-[14px]">check</span>
                                            Verified
                                        </div>
                                    </td>
                                </tr>

                                <!-- Row 3 -->
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-gray-900 text-[13.5px]">Sunlight Lime 400ml</span>
                                            <span class="text-[11.5px] text-gray-400 font-mono mt-0.5">8995544332211</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center font-bold text-gray-800 font-mono text-[14px]">15</td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-center">
                                            <input type="text" value="14" class="w-[72px] text-center px-2 py-1.5 border border-gray-200 rounded-md focus:outline-none focus:border-[#0052cc] text-[14px] font-bold font-mono text-gray-800">
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="font-bold text-red-500 font-mono text-[14px]">-1</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="relative inline-block w-[110px]">
                                            <select class="appearance-none w-full bg-white border border-gray-200 rounded-md px-3 py-1.5 pr-7 text-[12.5px] font-medium text-gray-700 focus:outline-none focus:border-[#0052cc]">
                                                <option>Rusak</option>
                                                <option>Hilang</option>
                                            </select>
                                            <span class="material-symbols-rounded absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[16px]">expand_more</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button class="bg-[#0052cc] hover:bg-[#0043a8] text-white px-4 py-1.5 rounded-md font-bold text-[12px] shadow-sm transition-colors">Confirm</button>
                                    </td>
                                </tr>

                                <!-- Row 4 -->
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-gray-900 text-[13.5px]">Tango Wafer Cokelat</span>
                                            <span class="text-[11.5px] text-gray-400 font-mono mt-0.5">8991200120012</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center font-bold text-gray-800 font-mono text-[14px]">55</td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-center">
                                            <input type="text" value="--" class="w-[72px] text-center px-2 py-1.5 border border-gray-200 text-gray-400 bg-white rounded-md focus:outline-none text-[14px] font-bold font-mono" readonly>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="font-bold text-gray-400 font-mono text-[14px]">?</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="relative inline-block w-[110px]">
                                            <select class="appearance-none w-full bg-white border border-gray-200 rounded-md px-3 py-1.5 pr-7 text-[12.5px] font-medium text-gray-700 focus:outline-none focus:border-[#0052cc]">
                                                <option>-</option>
                                            </select>
                                            <span class="material-symbols-rounded absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[16px]">expand_more</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button class="bg-[#0052cc] hover:bg-[#0043a8] text-white px-4 py-1.5 rounded-md font-bold text-[12px] shadow-sm transition-colors">Confirm</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="bg-[#f8f9fa] border-t border-gray-100 px-6 py-3.5 flex items-center justify-between">
                        <p class="text-[12.5px] text-gray-500 font-medium">Showing 1 to 4 of 1,248 products</p>
                        <div class="flex items-center gap-1.5 text-[13px]">
                            <button class="w-8 h-8 flex items-center justify-center rounded-md bg-white border border-gray-200 text-gray-400 hover:bg-gray-50 transition-colors">
                                <span class="material-symbols-rounded text-[16px]">chevron_left</span>
                            </button>
                            <button class="w-8 h-8 flex items-center justify-center rounded-md bg-[#0052cc] text-white font-bold shadow-sm">1</button>
                            <button class="w-8 h-8 flex items-center justify-center rounded-md bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 font-semibold transition-colors">2</button>
                            <button class="w-8 h-8 flex items-center justify-center rounded-md bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 font-semibold transition-colors">3</button>
                            <button class="w-8 h-8 flex items-center justify-center rounded-md bg-white border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                                <span class="material-symbols-rounded text-[16px]">chevron_right</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Bottom Section: Quick Scan & Audit Progress -->
                <div class="grid grid-cols-12 gap-6 mb-5">
                    <!-- Quick Scan Mode -->
                    <div class="col-span-8 bg-[#eef2ff] border border-blue-200/80 rounded-xl p-5 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-[#1d4ed8] text-white flex items-center justify-center shrink-0 shadow-sm">
                                <span class="material-symbols-rounded text-[22px]">qr_code_scanner</span>
                            </div>
                            <div>
                                <h4 class="text-[15px] font-bold text-[#0052cc]">Quick Scan Mode</h4>
                                <p class="text-[13px] text-gray-600 mt-0.5 leading-snug max-w-[320px]">Gunakan scanner bluetooth untuk input stok lebih cepat.</p>
                            </div>
                        </div>
                        <button class="bg-white text-[#0052cc] border border-[#0052cc] px-7 py-2.5 rounded-lg font-bold text-[13px] shadow-sm hover:bg-blue-50 transition-colors shrink-0 leading-tight text-center">
                            Connect<br>Scanner
                        </button>
                    </div>

                    <!-- Audit Progress -->
                    <div class="col-span-4 bg-white border border-gray-200 rounded-xl p-5 flex flex-col justify-center shadow-sm">
                        <h4 class="text-[13.5px] font-bold text-gray-900 mb-3">Audit Progress</h4>
                        <div class="w-full bg-gray-100 rounded-full h-2.5 mb-2.5 overflow-hidden">
                            <div class="bg-[#0052cc] h-2.5 rounded-full" style="width: 67%"></div>
                        </div>
                        <div class="text-right">
                            <span class="text-[11.5px] font-medium text-gray-500">842 of 1,248 products audited</span>
                        </div>
                    </div>
                </div>

                <!-- Bottom Action Button -->
                <div>
                    <button class="bg-[#0052cc] hover:bg-[#0043a8] text-white px-5 py-2.5 rounded-lg font-bold text-[13px] inline-flex items-center gap-2 shadow-sm transition-colors">
                        <span class="material-symbols-rounded text-[18px]">check_circle</span>
                        Selesaikan Stock Opname
                    </button>
                </div>

            </div>
        </div>
    </main>
</div>
@endsection
