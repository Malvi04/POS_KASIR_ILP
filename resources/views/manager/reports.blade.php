@extends('layouts.app')

@section('title', 'Sales Reports - ILP POS')

@section('content')
<div class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">
    
    <!-- Sidebar -->
    <x-manager-sidebar active="reports" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        
        <!-- Top Header -->
        <header class="h-[68px] bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="relative w-[360px]">
                <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                <input type="text" placeholder="Cari laporan..." class="w-full pl-11 pr-4 py-2 rounded-full bg-[#f1f5f9] border border-transparent focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 focus:outline-none text-[13px] text-gray-700 placeholder-gray-400 transition-all">
            </div>

            <div class="flex items-center gap-4 text-gray-500">
                <button class="hover:text-gray-900 transition-colors p-1">
                    <span class="material-symbols-rounded text-[22px]">notifications</span>
                </button>
                <button class="hover:text-gray-900 transition-colors p-1">
                    <span class="material-symbols-rounded text-[22px]">help_outline</span>
                </button>
                <div class="h-6 w-px bg-gray-200 ml-1"></div>
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-8 pb-6">
            <div class="max-w-[1400px] mx-auto space-y-6">
                
                <!-- Subheader: Title & Period Filter -->
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h1 class="text-[19px] font-bold text-gray-900 tracking-tight">Laporan Manajerial</h1>
                        <p class="text-[13px] text-gray-500 mt-0.5">Ikhtisar performa bisnis real-time untuk pengambilan keputusan strategis.</p>
                    </div>
                    <button class="bg-white hover:bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 flex items-center gap-2.5 shadow-sm transition-colors text-[13px] font-semibold text-gray-700 shrink-0">
                        <span class="material-symbols-rounded text-[18px] text-gray-500">calendar_today</span>
                        <span>Bulan Ini (Mei 2024)</span>
                        <span class="material-symbols-rounded text-[18px] text-gray-400">expand_more</span>
                    </button>
                </div>

                <!-- 4 Summary KPI Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <!-- Card 1: TOTAL PENDAPATAN -->
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm flex flex-col justify-between min-h-[130px]">
                        <div class="flex items-center justify-between">
                            <div class="w-9 h-9 rounded-lg bg-[#dbeafe] text-[#0052cc] flex items-center justify-center">
                                <span class="material-symbols-rounded text-[20px]">payments</span>
                            </div>
                            <span class="inline-flex items-center gap-1 bg-green-50 text-green-600 text-[11px] font-bold px-2.5 py-1 rounded-full">
                                <span class="material-symbols-rounded text-[14px]">trending_up</span>
                                +12.5%
                            </span>
                        </div>
                        <div class="mt-4">
                            <p class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">TOTAL PENDAPATAN</p>
                            <h3 class="text-[19px] font-bold text-gray-900 font-mono mt-1 tracking-tight">Rp 482.950.000</h3>
                        </div>
                    </div>

                    <!-- Card 2: LABA BERSIH -->
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm flex flex-col justify-between min-h-[130px]">
                        <div class="flex items-center justify-between">
                            <div class="w-9 h-9 rounded-lg bg-[#ffedd5] text-[#c2410c] flex items-center justify-center">
                                <span class="material-symbols-rounded text-[20px]">savings</span>
                            </div>
                            <span class="inline-flex items-center bg-gray-100 text-gray-600 text-[11px] font-bold px-2.5 py-1 rounded-full">
                                Margin 18.2%
                            </span>
                        </div>
                        <div class="mt-4">
                            <p class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">LABA BERSIH</p>
                            <h3 class="text-[19px] font-bold text-gray-900 font-mono mt-1 tracking-tight">Rp 87.896.900</h3>
                        </div>
                    </div>

                    <!-- Card 3: RATA-RATA TRANSAKSI -->
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm flex flex-col justify-between min-h-[130px]">
                        <div class="flex items-center justify-between">
                            <div class="w-9 h-9 rounded-lg bg-[#e0e7ff] text-[#3730a3] flex items-center justify-center">
                                <span class="material-symbols-rounded text-[20px]">shopping_basket</span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <p class="text-[11px] font-bold text-gray-500 tracking-wider uppercase leading-snug">RATA-RATA TRANSAKSI</p>
                            <h3 class="text-[19px] font-bold text-gray-900 font-mono mt-1 tracking-tight">Rp 154.300</h3>
                        </div>
                    </div>

                    <!-- Card 4: TOTAL PRODUK TERJUAL -->
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm flex flex-col justify-between min-h-[130px]">
                        <div class="flex items-center justify-between">
                            <div class="w-9 h-9 rounded-lg bg-[#fef3c7] text-[#d97706] flex items-center justify-center">
                                <span class="material-symbols-rounded text-[20px]">inventory</span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <p class="text-[11px] font-bold text-gray-500 tracking-wider uppercase leading-snug">TOTAL PRODUK TERJUAL</p>
                            <h3 class="text-[19px] font-bold text-gray-900 font-mono mt-1 tracking-tight">3.120 Unit</h3>
                        </div>
                    </div>
                </div>

                <!-- Middle Row: Tren Penjualan Harian (8 cols) & Analisis Pergerakan Stok (4 cols) -->
                <div class="grid grid-cols-12 gap-6 items-stretch">
                    
                    <!-- Left: Tren Penjualan Harian -->
                    <div class="col-span-12 lg:col-span-8 bg-white border border-gray-200 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-[15px] font-bold text-gray-900">Tren Penjualan Harian</h2>
                            <div class="flex items-center gap-4 text-[11.5px] font-medium text-gray-500">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-[#0052cc]"></span>
                                    <span>Penjualan</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-[#bfdbfe]"></span>
                                    <span>Target</span>
                                </div>
                            </div>
                        </div>

                        <!-- Bar Chart Container -->
                        <div class="relative flex-1 flex flex-col justify-end min-h-[220px] pt-4">
                            <!-- Horizontal Grid Lines -->
                            <div class="absolute inset-x-0 top-4 bottom-7 flex flex-col justify-between pointer-events-none">
                                <div class="border-b border-gray-100 w-full"></div>
                                <div class="border-b border-gray-100 w-full"></div>
                                <div class="border-b border-gray-100 w-full"></div>
                                <div class="border-b border-gray-100 w-full"></div>
                                <div class="border-b border-gray-200 w-full"></div>
                            </div>

                            <!-- 7 Day Bars -->
                            <div class="relative z-10 grid grid-cols-7 gap-1.5 items-end h-[190px] px-2">
                                <!-- Sen -->
                                <div class="flex flex-col items-center h-full justify-end group">
                                    <div class="w-full bg-[#0052cc] group-hover:bg-[#0043a8] transition-colors rounded-t-sm" style="height: 74%"></div>
                                </div>
                                <!-- Sel -->
                                <div class="flex flex-col items-center h-full justify-end group">
                                    <div class="w-full bg-[#0052cc] group-hover:bg-[#0043a8] transition-colors rounded-t-sm" style="height: 55%"></div>
                                </div>
                                <!-- Rab -->
                                <div class="flex flex-col items-center h-full justify-end group">
                                    <div class="w-full bg-[#0052cc] group-hover:bg-[#0043a8] transition-colors rounded-t-sm" style="height: 85%"></div>
                                </div>
                                <!-- Kam -->
                                <div class="flex flex-col items-center h-full justify-end group">
                                    <div class="w-full bg-[#0052cc] group-hover:bg-[#0043a8] transition-colors rounded-t-sm" style="height: 97%"></div>
                                </div>
                                <!-- Jum -->
                                <div class="flex flex-col items-center h-full justify-end group">
                                    <div class="w-full bg-[#0052cc] group-hover:bg-[#0043a8] transition-colors rounded-t-sm" style="height: 61%"></div>
                                </div>
                                <!-- Sab -->
                                <div class="flex flex-col items-center h-full justify-end group">
                                    <div class="w-full bg-[#0052cc] group-hover:bg-[#0043a8] transition-colors rounded-t-sm" style="height: 79%"></div>
                                </div>
                                <!-- Min -->
                                <div class="flex flex-col items-center h-full justify-end group">
                                    <div class="w-full bg-[#0052cc] group-hover:bg-[#0043a8] transition-colors rounded-t-sm" style="height: 49%"></div>
                                </div>
                            </div>

                            <!-- X-Axis Day Labels -->
                            <div class="grid grid-cols-7 gap-1.5 px-2 pt-2.5 text-center text-[11px] font-medium text-gray-500">
                                <span>Sen</span>
                                <span>Sel</span>
                                <span>Rab</span>
                                <span>Kam</span>
                                <span>Jum</span>
                                <span>Sab</span>
                                <span>Min</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Analisis Pergerakan Stok -->
                    <div class="col-span-12 lg:col-span-4 bg-white border border-gray-200 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                        <div>
                            <h2 class="text-[15px] font-bold text-gray-900 mb-4">Analisis Pergerakan Stok</h2>

                            <!-- Fast Moving -->
                            <div class="mb-4">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-1.5">
                                        <span class="material-symbols-rounded text-green-600 text-[17px]">bolt</span>
                                        <span class="text-[12.5px] font-bold text-gray-800">Produk Terlaris (Fast)</span>
                                    </div>
                                    <span class="text-[11px] font-bold text-green-600">8 Item</span>
                                </div>
                                <div class="bg-[#e9eef5] rounded-xl p-3.5 space-y-2">
                                    <div class="flex items-center justify-between text-[12px]">
                                        <span class="text-gray-600 font-medium">Minyak Goreng 2L</span>
                                        <span class="font-bold text-gray-900">Stok &lt; 20</span>
                                    </div>
                                    <div class="flex items-center justify-between text-[12px]">
                                        <span class="text-gray-600 font-medium">Beras Premium 5kg</span>
                                        <span class="font-bold text-gray-900">Stok &lt; 12</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Slow Moving -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-1.5">
                                        <span class="material-symbols-rounded text-gray-400 text-[17px]">cloud</span>
                                        <span class="text-[12.5px] font-bold text-gray-800">Produk Lambat (Slow)</span>
                                    </div>
                                    <span class="text-[11px] font-bold text-gray-500">14 Item</span>
                                </div>
                                <div class="bg-[#f1f5f9] rounded-xl p-3.5 space-y-2">
                                    <div class="flex items-center justify-between text-[12px]">
                                        <span class="text-gray-600 font-medium">Pembersih Lantai X</span>
                                        <span class="font-bold text-gray-900">&gt; 30 Hari</span>
                                    </div>
                                    <div class="flex items-center justify-between text-[12px]">
                                        <span class="text-gray-600 font-medium">Sikat Gigi Soft</span>
                                        <span class="font-bold text-gray-900">&gt; 45 Hari</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <a href="{{ url('/inventory') }}" class="mt-5 w-full py-2.5 bg-[#e2e8f0]/80 hover:bg-[#cbd5e1]/80 text-gray-800 font-bold text-[12.5px] rounded-xl transition-colors text-center block">
                            Lihat Inventaris Lengkap
                        </a>
                    </div>

                </div>

                <!-- Bottom Row: Produk Terlaris (6 cols) & Ringkasan Performa Kasir (6 cols) -->
                <div class="grid grid-cols-12 gap-6 items-stretch">
                    
                    <!-- Left: Produk Terlaris -->
                    <div class="col-span-12 lg:col-span-6 bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex flex-col">
                        <div class="px-6 py-4 flex items-center justify-between border-b border-gray-100">
                            <h2 class="text-[15px] font-bold text-gray-900">Produk Terlaris</h2>
                            <span class="bg-[#dbeafe] text-[#0052cc] text-[10px] font-extrabold px-2.5 py-1 rounded-md tracking-wider uppercase">
                                TOP 3 HIGHLIGHTED
                            </span>
                        </div>

                        <div class="overflow-x-auto min-w-0 w-full flex-1">
                            <table class="w-full text-left border-collapse min-w-[550px]">
                                <thead>
                                    <tr class="bg-[#f8f9fa] border-b border-gray-100 text-gray-500 text-[11px] font-bold uppercase tracking-wider whitespace-nowrap">
                                        <th class="pl-6 pr-3 py-3.5 text-left w-[220px]">Nama Produk</th>
                                        <th class="px-3 py-3.5 text-left w-[130px]">Kategori</th>
                                        <th class="px-3 py-3.5 text-center w-[90px]">Terjual</th>
                                        <th class="pl-3 pr-6 py-3.5 text-right w-[140px]">Pendapatan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-[13px]">
                                    <!-- Top 1 -->
                                    <tr class="bg-[#f4f7fb]/80 hover:bg-[#edf2f9] transition-colors">
                                        <td class="pl-6 pr-3 py-3.5">
                                            <div class="flex items-center gap-2.5">
                                                <span class="w-4 h-4 rounded-full bg-amber-500 text-white flex items-center justify-center shrink-0">
                                                    <span class="material-symbols-rounded filled text-[11px]">star</span>
                                                </span>
                                                <span class="font-bold text-gray-900 leading-snug">Susu UHT Full Cream 1L</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3.5 text-[12px] text-gray-500 font-medium">Minuman</td>
                                        <td class="px-3 py-3.5 text-center font-mono font-bold text-gray-800">452</td>
                                        <td class="pl-3 pr-6 py-3.5 text-right font-mono font-medium text-gray-800 whitespace-nowrap">Rp 8.136.000</td>
                                    </tr>

                                    <!-- Top 2 -->
                                    <tr class="bg-[#f4f7fb]/80 hover:bg-[#edf2f9] transition-colors">
                                        <td class="pl-6 pr-3 py-3.5">
                                            <div class="flex items-center gap-2.5">
                                                <span class="w-4 h-4 rounded-full bg-slate-300 text-white flex items-center justify-center shrink-0">
                                                    <span class="material-symbols-rounded filled text-[11px]">star</span>
                                                </span>
                                                <span class="font-bold text-gray-900 leading-snug">Minyak Goreng Bimoli 2L</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3.5 text-[12px] text-gray-500 font-medium">Kebutuhan Pokok</td>
                                        <td class="px-3 py-3.5 text-center font-mono font-bold text-gray-800">380</td>
                                        <td class="pl-3 pr-6 py-3.5 text-right font-mono font-medium text-gray-800 whitespace-nowrap">Rp 14.820.000</td>
                                    </tr>

                                    <!-- Top 3 -->
                                    <tr class="bg-[#f4f7fb]/80 hover:bg-[#edf2f9] transition-colors">
                                        <td class="pl-6 pr-3 py-3.5">
                                            <div class="flex items-center gap-2.5">
                                                <span class="w-4 h-4 rounded-full bg-amber-300 text-white flex items-center justify-center shrink-0">
                                                    <span class="material-symbols-rounded filled text-[11px]">star</span>
                                                </span>
                                                <span class="font-bold text-gray-900 leading-snug">Indomie Goreng (Dukos)</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3.5 text-[12px] text-gray-500 font-medium">Mie Instan</td>
                                        <td class="px-3 py-3.5 text-center font-mono font-bold text-gray-800">320</td>
                                        <td class="pl-3 pr-6 py-3.5 text-right font-mono font-medium text-gray-800 whitespace-nowrap">Rp 960.000</td>
                                    </tr>

                                    <!-- Row 4 -->
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="pl-6 pr-3 py-3.5">
                                            <div class="pl-6 font-bold text-gray-800 leading-snug">Gula Pasir 1kg</div>
                                        </td>
                                        <td class="px-3 py-3.5 text-[12px] text-gray-500 font-medium">Kebutuhan Pokok</td>
                                        <td class="px-3 py-3.5 text-center font-mono font-bold text-gray-800">210</td>
                                        <td class="pl-3 pr-6 py-3.5 text-right font-mono font-medium text-gray-800 whitespace-nowrap">Rp 3.150.000</td>
                                    </tr>

                                    <!-- Row 5 -->
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="pl-6 pr-3 py-3.5">
                                            <div class="pl-6 font-bold text-gray-800 leading-snug">Sabun Mandi Lifebuoy</div>
                                        </td>
                                        <td class="px-3 py-3.5 text-[12px] text-gray-500 font-medium">Personal Care</td>
                                        <td class="px-3 py-3.5 text-center font-mono font-bold text-gray-800">185</td>
                                        <td class="pl-3 pr-6 py-3.5 text-right font-mono font-medium text-gray-800 whitespace-nowrap">Rp 832.500</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Right: Ringkasan Performa Kasir -->
                    <div class="col-span-12 lg:col-span-6 bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex flex-col justify-between">
                        <div>
                            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                                <h2 class="text-[15px] font-bold text-gray-900">Ringkasan Performa Kasir</h2>
                                <span class="text-[11px] font-semibold text-gray-400">12 Terminal Kasir</span>
                            </div>

                            <div class="overflow-x-auto min-w-0 w-full max-h-[255px] overflow-y-auto">
                                <table class="w-full text-left border-collapse min-w-[500px]">
                                    <thead class="sticky top-0 z-10 bg-[#f8f9fa] shadow-[0_1px_0_0_#f3f4f6]">
                                        <tr class="border-b border-gray-100 text-gray-500 text-[11px] font-bold uppercase tracking-wider whitespace-nowrap">
                                            <th class="pl-6 pr-3 py-3.5 text-left w-[180px]">No. Kasir</th>
                                            <th class="px-3 py-3.5 text-center w-[100px]">Transaksi</th>
                                            <th class="px-3 py-3.5 text-right w-[140px]">Total Penjualan</th>
                                            <th class="pl-3 pr-6 py-3.5 text-center w-[80px]">Void</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-[13px]">
                                        @php
                                            $kasirData = [
                                                ['no' => 1,  'trx' => '1.240', 'total' => 'Rp 192.200.000', 'void' => 2,  'bg' => 'bg-[#dbeafe]', 'text' => 'text-[#1e40af]'],
                                                ['no' => 2,  'trx' => '1.050', 'total' => 'Rp 160.750.000', 'void' => 12, 'bg' => 'bg-[#e0e7ff]', 'text' => 'text-[#3730a3]'],
                                                ['no' => 3,  'trx' => '830',   'total' => 'Rp 130.000.000', 'void' => 0,  'bg' => 'bg-[#ffedd5]', 'text' => 'text-[#9a3412]'],
                                                ['no' => 4,  'trx' => '790',   'total' => 'Rp 121.450.000', 'void' => 1,  'bg' => 'bg-green-100', 'text' => 'text-green-700'],
                                                ['no' => 5,  'trx' => '745',   'total' => 'Rp 114.800.000', 'void' => 0,  'bg' => 'bg-[#dbeafe]', 'text' => 'text-[#1e40af]'],
                                                ['no' => 6,  'trx' => '680',   'total' => 'Rp 102.300.000', 'void' => 3,  'bg' => 'bg-[#e0e7ff]', 'text' => 'text-[#3730a3]'],
                                                ['no' => 7,  'trx' => '620',   'total' => 'Rp 95.600.000',  'void' => 0,  'bg' => 'bg-[#ffedd5]', 'text' => 'text-[#9a3412]'],
                                                ['no' => 8,  'trx' => '590',   'total' => 'Rp 89.100.000',  'void' => 1,  'bg' => 'bg-green-100', 'text' => 'text-green-700'],
                                                ['no' => 9,  'trx' => '540',   'total' => 'Rp 81.500.000',  'void' => 0,  'bg' => 'bg-[#dbeafe]', 'text' => 'text-[#1e40af]'],
                                                ['no' => 10, 'trx' => '495',   'total' => 'Rp 74.250.000',  'void' => 2,  'bg' => 'bg-[#e0e7ff]', 'text' => 'text-[#3730a3]'],
                                                ['no' => 11, 'trx' => '430',   'total' => 'Rp 64.900.000',  'void' => 0,  'bg' => 'bg-[#ffedd5]', 'text' => 'text-[#9a3412]'],
                                                ['no' => 12, 'trx' => '390',   'total' => 'Rp 58.400.000',  'void' => 1,  'bg' => 'bg-green-100', 'text' => 'text-green-700'],
                                            ];
                                        @endphp
                                        @foreach ($kasirData as $kasir)
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="pl-6 pr-3 py-3.5">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 rounded-full {{ $kasir['bg'] }} {{ $kasir['text'] }} font-extrabold text-[11px] flex items-center justify-center shrink-0">
                                                        K{{ $kasir['no'] }}
                                                    </div>
                                                    <span class="font-bold text-gray-900">Kasir {{ $kasir['no'] }}</span>
                                                </div>
                                            </td>
                                            <td class="px-3 py-3.5 text-center font-mono font-semibold text-gray-800">{{ $kasir['trx'] }}</td>
                                            <td class="px-3 py-3.5 text-right font-mono font-medium text-gray-800 whitespace-nowrap">{{ $kasir['total'] }}</td>
                                            <td class="pl-3 pr-6 py-3.5 text-center">
                                                @if ($kasir['void'] >= 10)
                                                    <span class="inline-block bg-red-100 text-red-600 font-mono font-bold text-[11px] px-2 py-0.5 rounded">{{ $kasir['void'] }}</span>
                                                @else
                                                    <span class="inline-block bg-gray-100 text-gray-700 font-mono font-bold text-[11px] px-2 py-0.5 rounded">{{ $kasir['void'] }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- Info Notice -->
                            <div class="px-6 py-3 bg-[#f8fafc] border-t border-gray-100 flex items-center justify-center gap-1.5 text-[11px] text-gray-500">
                                <span class="material-symbols-rounded text-[14px] text-gray-400">info</span>
                                <span>Angka 'Void' yang tinggi memerlukan peninjauan keamanan lebih lanjut.</span>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>

        <!-- Bottom Action Bar -->
        <footer class="bg-white border-t border-gray-200 px-8 py-4 flex items-center justify-between shrink-0">
            <span class="text-[12.5px] font-medium text-gray-500">
                Data diperbarui terakhir: 24 Mei 2024, 14:30 WIB
            </span>
            <div class="flex items-center gap-3">
                <button type="button" @click="window.print()" class="px-4 py-2 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-[12.5px] font-bold text-gray-700 flex items-center gap-2 shadow-sm transition-colors cursor-pointer">
                    <span class="material-symbols-rounded text-[17px]">print</span>
                    <span>Cetak Laporan</span>
                </button>
                <button type="button" @click="window.print()" class="px-4 py-2 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-[12.5px] font-bold text-gray-700 flex items-center gap-2 shadow-sm transition-colors cursor-pointer">
                    <span class="material-symbols-rounded text-[17px]">table_view</span>
                    <span>Ekspor Excel</span>
                </button>
                <button type="button" @click="window.print()" class="px-4 py-2 rounded-lg bg-[#0052cc] hover:bg-[#0043a8] text-[12.5px] font-bold text-white flex items-center gap-2 shadow-sm transition-colors cursor-pointer">
                    <span class="material-symbols-rounded text-[17px]">picture_as_pdf</span>
                    <span>Ekspor PDF</span>
                </button>
            </div>
        </footer>

    </main>
</div>
@endsection
