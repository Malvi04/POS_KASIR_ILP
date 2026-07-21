@extends('layouts.app')

@section('title', 'Reports - Vigilant POS')

@section('content')
<div class="flex flex-col h-screen w-full font-sans">
    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-[240px] shrink-0 flex flex-col justify-between bg-[#f4f6f9] border-r border-[#e2e8f0]">
             <!-- Top Part -->
             <div>
                 <div class="px-6 py-6 pt-8">
                     <h1 class="text-[20px] font-extrabold text-[#1e3a8a] tracking-tight leading-tight">Vigilant POS</h1>
                     <p class="text-[13px] font-semibold text-[#64748b] mt-1">Worker Coop Admin</p>
                 </div>
                 
                 <nav class="flex flex-col gap-2 px-4 mt-4">
                     <a href="#" class="flex items-center gap-4 px-4 py-3 text-[#64748b] hover:bg-gray-200/50 rounded-xl font-medium text-[14.5px] transition-colors">
                         <span class="material-symbols-rounded" style="font-size: 22px;">dashboard</span>
                         Dashboard
                     </a>
                     <!-- Active State -->
                     <a href="#" class="flex items-center gap-4 px-4 py-3 bg-[#e0e7ff] text-[#2764c2] rounded-xl font-bold text-[14.5px] relative overflow-hidden">
                         <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-[#2764c2] rounded-r-md"></div>
                         <span class="material-symbols-rounded filled" style="font-size: 22px;">bar_chart</span>
                         Reports
                     </a>
                     <a href="#" class="flex items-center gap-4 px-4 py-3 text-[#64748b] hover:bg-gray-200/50 rounded-xl font-medium text-[14.5px] transition-colors">
                         <span class="material-symbols-rounded" style="font-size: 22px;">inventory_2</span>
                         Products
                     </a>
                     <a href="#" class="flex items-center gap-4 px-4 py-3 text-[#64748b] hover:bg-gray-200/50 rounded-xl font-medium text-[14.5px] transition-colors">
                         <span class="material-symbols-rounded" style="font-size: 22px;">group</span>
                         Members
                     </a>
                     <a href="#" class="flex items-center gap-4 px-4 py-3 text-[#64748b] hover:bg-gray-200/50 rounded-xl font-medium text-[14.5px] transition-colors">
                         <span class="material-symbols-rounded" style="font-size: 22px;">settings</span>
                         Settings
                     </a>
                 </nav>
             </div>
             
             <!-- User profile bottom -->
             <div class="p-4 border-t border-[#e2e8f0]">
                 <div class="flex items-center gap-3">
                     <div class="w-10 h-10 rounded-full bg-[#3b82f6] text-white flex items-center justify-center font-bold text-sm">AD</div>
                     <div class="flex flex-col">
                         <span class="text-[13px] font-bold text-gray-900 leading-tight">Admin</span>
                         <span class="text-[11px] text-[#64748b] font-medium mt-0.5">Vigilant Terminal 01</span>
                     </div>
                 </div>
             </div>
        </aside>
        
        <!-- Main Area -->
        <div class="flex-1 flex flex-col min-w-0 bg-[#fafafa]">
            <!-- Header -->
            <header class="h-16 px-8 flex items-center justify-between border-b border-[#e2e8f0] bg-white shrink-0">
                <!-- Search -->
                <div class="relative w-80">
                    <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" style="font-size: 20px;">search</span>
                    <input type="text" placeholder="Cari transaksi..." class="w-full pl-10 pr-4 py-2 bg-[#f8fafc] border border-[#e2e8f0] rounded-lg text-sm focus:outline-none focus:border-blue-500 text-gray-700 font-medium placeholder-gray-400">
                </div>
                
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-4 text-gray-500">
                        <button class="hover:text-gray-900 transition-colors"><span class="material-symbols-rounded" style="font-size: 22px;">notifications</span></button>
                        <button class="hover:text-gray-900 transition-colors"><span class="material-symbols-rounded" style="font-size: 22px;">wifi</span></button>
                        <button class="hover:text-gray-900 transition-colors"><span class="material-symbols-rounded" style="font-size: 22px;">sync</span></button>
                    </div>
                    
                    <div class="h-5 w-px bg-gray-300"></div>
                    
                    <div class="flex items-center gap-2 text-[13px]">
                        <span class="font-bold text-gray-600">Reports</span>
                        <span class="material-symbols-rounded text-gray-400" style="font-size: 16px;">chevron_right</span>
                        <span class="font-bold text-[#2764c2]">Riwayat Penjualan</span>
                    </div>
                </div>
            </header>
            
            <!-- Content -->
            <main class="flex-1 overflow-y-auto p-8 bg-[#f8f9fb]">
                
                <!-- Cards Row -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <!-- Card 1 -->
                    <div class="bg-white rounded-[14px] p-6 border border-gray-200 shadow-sm relative overflow-hidden flex flex-col justify-center min-h-[140px]">
                        <span class="text-[13px] font-bold text-gray-500 mb-1 z-10">Total Omzet Hari Ini</span>
                        <h2 class="text-[32px] font-black text-[#1e40af] mb-1 z-10 tracking-tight">Rp 4.250.000</h2>
                        <div class="flex items-center gap-1 text-[11px] font-bold text-[#16a34a] z-10">
                            <span class="material-symbols-rounded" style="font-size: 14px;">trending_up</span>
                            +12.5% dari kemarin
                        </div>
                        <span class="material-symbols-rounded absolute -right-6 -bottom-6 text-[110px] text-gray-100/80 z-0">payments</span>
                    </div>
                    
                    <!-- Card 2 -->
                    <div class="bg-white rounded-[14px] p-6 border border-gray-200 shadow-sm flex flex-col justify-center min-h-[140px]">
                        <span class="text-[13px] font-bold text-gray-500 mb-1">Jumlah Transaksi</span>
                        <h2 class="text-[32px] font-black text-gray-900 mb-2 tracking-tight">142</h2>
                        <div class="flex items-center gap-3">
                            <div class="flex -space-x-2">
                                <div class="w-6 h-6 rounded-full bg-blue-600 border-2 border-white"></div>
                                <div class="w-6 h-6 rounded-full bg-blue-300 border-2 border-white"></div>
                                <div class="w-6 h-6 rounded-full bg-gray-200 border-2 border-white"></div>
                            </div>
                            <span class="text-[11px] font-bold text-gray-500">Antrian Lancar</span>
                        </div>
                    </div>
                    
                    <!-- Card 3 -->
                    <div class="bg-white rounded-[14px] p-6 border border-gray-200 shadow-sm flex flex-col justify-center min-h-[140px]">
                        <span class="text-[13px] font-bold text-gray-500 mb-1">Metode Bayar Populer</span>
                        <h2 class="text-[24px] font-black text-gray-900 mb-4 tracking-tight">QRIS</h2>
                        <div class="w-[85%] h-2 bg-gray-100 rounded-full mb-3 overflow-hidden">
                            <div class="h-full bg-[#1e40af] rounded-full" style="width: 65%"></div>
                        </div>
                        <span class="text-[10px] font-bold text-gray-400">65% Transaksi hari ini via QRIS</span>
                    </div>
                </div>
                
                <!-- Filter Section -->
                <div class="bg-white rounded-t-xl p-5 border border-gray-200 shadow-sm flex flex-col gap-4 border-b-0 pb-5">
                     <div class="flex items-end gap-6 flex-wrap">
                         <!-- Date Range -->
                         <div class="flex flex-col gap-2">
                             <label class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Rentang Tanggal</label>
                             <div class="flex items-center gap-3">
                                 <div class="relative">
                                     <input type="text" value="dd/mm/yyyy" class="pl-4 pr-10 py-2 border border-gray-200 rounded-lg text-sm font-semibold text-gray-700 w-[140px] outline-none bg-white">
                                     <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-600" style="font-size: 18px;">calendar_today</span>
                                 </div>
                                 <span class="text-[13px] text-gray-400 font-bold">s/d</span>
                                 <div class="relative">
                                     <input type="text" value="dd/mm/yyyy" class="pl-4 pr-10 py-2 border border-gray-200 rounded-lg text-sm font-semibold text-gray-700 w-[140px] outline-none bg-white">
                                     <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-600" style="font-size: 18px;">calendar_today</span>
                                 </div>
                             </div>
                         </div>
                         
                         <!-- Payment Method -->
                         <div class="flex flex-col gap-2">
                             <label class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Metode Bayar</label>
                             <div class="relative">
                                 <select class="appearance-none pl-4 pr-10 py-2 border border-gray-200 rounded-lg text-sm font-semibold text-gray-700 w-[160px] outline-none bg-white cursor-pointer">
                                     <option>Semua Metode</option>
                                 </select>
                                 <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-600 pointer-events-none" style="font-size: 20px;">expand_more</span>
                             </div>
                         </div>
                         
                         <!-- Status -->
                         <div class="flex flex-col gap-2">
                             <label class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Status</label>
                             <div class="flex bg-[#f8fafc] p-1 rounded-lg border border-gray-200">
                                 <button class="px-5 py-1.5 bg-white border border-blue-200 text-[#1d4ed8] font-bold text-[12px] rounded shadow-sm">Semua</button>
                                 <button class="px-5 py-1.5 text-gray-500 font-bold text-[12px] hover:text-gray-800 transition-colors">Selesai</button>
                                 <button class="px-5 py-1.5 text-gray-500 font-bold text-[12px] hover:text-gray-800 transition-colors">Batal</button>
                             </div>
                         </div>
                     </div>
                     
                     <div class="flex items-center gap-3 pt-1">
                         <button class="flex items-center justify-center gap-2 px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-[13px] rounded-lg transition-colors border border-gray-200 shadow-sm">
                             <span class="material-symbols-rounded" style="font-size: 18px;">download</span> Ekspor CSV
                         </button>
                         <button class="flex items-center justify-center gap-2 px-5 py-2.5 bg-[#0047cc] hover:bg-blue-800 text-white font-bold text-[13px] rounded-lg shadow-md transition-colors">
                             <span class="material-symbols-rounded" style="font-size: 18px;">filter_alt</span> Terapkan Filter
                         </button>
                     </div>
                </div>
                
                <!-- Table -->
                <div class="bg-white rounded-b-xl border border-gray-200 shadow-sm overflow-hidden border-t-0">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#f8fafc] border-b border-gray-200 border-t border-gray-200">
                                <th class="px-6 py-4 text-[10px] font-black text-gray-500 uppercase tracking-widest w-[15%]">ID Transaksi</th>
                                <th class="px-6 py-4 text-[10px] font-black text-gray-500 uppercase tracking-widest w-[15%]">Waktu</th>
                                <th class="px-6 py-4 text-[10px] font-black text-gray-500 uppercase tracking-widest w-[20%]">Kasir</th>
                                <th class="px-6 py-4 text-[10px] font-black text-gray-500 uppercase tracking-widest w-[15%]">Metode Bayar</th>
                                <th class="px-6 py-4 text-[10px] font-black text-gray-500 uppercase tracking-widest w-[15%]">Total Belanja</th>
                                <th class="px-6 py-4 text-[10px] font-black text-gray-500 uppercase tracking-widest w-[10%]">Status</th>
                                <th class="px-6 py-4 text-[10px] font-black text-gray-500 uppercase tracking-widest w-[10%] text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <!-- Row 1 -->
                            <tr class="hover:bg-blue-50/20 transition-colors">
                                <td class="px-6 py-4">
                                    <span class="font-bold text-[#3b82f6] text-[13px]">#TRX-99201</span>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <div class="flex flex-col text-[12px] font-medium">
                                        <span class="text-gray-800">14:25, 24 Okt</span>
                                        <span class="text-gray-500 mt-0.5">2023</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-7 h-7 rounded-full bg-blue-100 text-[#1e40af] flex items-center justify-center text-[10px] font-bold shrink-0">BK</div>
                                        <div class="flex flex-col text-[12px]">
                                            <span class="font-bold text-gray-800">Budi</span>
                                            <span class="text-gray-500">Kurniawan</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 bg-[#eff6ff] text-[#1d4ed8] text-[9px] font-extrabold rounded-full border border-blue-100">QRIS</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-medium text-gray-700 text-[13px]">Rp 245.500</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 bg-[#f0fdf4] text-[#15803d] text-[9px] font-black tracking-widest rounded uppercase border border-green-200">SELESAI</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <button class="text-[#3b82f6] hover:text-blue-800 transition-colors p-1 rounded-lg">
                                        <span class="material-symbols-rounded" style="font-size: 18px;">print</span>
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Row 2 -->
                            <tr class="hover:bg-blue-50/20 transition-colors">
                                <td class="px-6 py-4">
                                    <span class="font-bold text-[#3b82f6] text-[13px]">#TRX-99200</span>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <div class="flex flex-col text-[12px] font-medium">
                                        <span class="text-gray-800">14:10, 24 Okt</span>
                                        <span class="text-gray-500 mt-0.5">2023</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-7 h-7 rounded-full bg-blue-100 text-[#1e40af] flex items-center justify-center text-[10px] font-bold shrink-0">BK</div>
                                        <div class="flex flex-col text-[12px]">
                                            <span class="font-bold text-gray-800">Budi</span>
                                            <span class="text-gray-500">Kurniawan</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 bg-gray-200/60 text-gray-600 text-[9px] font-extrabold rounded-full">TUNAI</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-medium text-gray-700 text-[13px]">Rp 12.000</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 bg-[#f0fdf4] text-[#15803d] text-[9px] font-black tracking-widest rounded uppercase border border-green-200">SELESAI</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <button class="text-[#3b82f6] hover:text-blue-800 transition-colors p-1 rounded-lg">
                                        <span class="material-symbols-rounded" style="font-size: 18px;">print</span>
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Row 3 (Dibatalkan) -->
                            <tr class="hover:bg-blue-50/20 transition-colors bg-gray-50/30">
                                <td class="px-6 py-4">
                                    <span class="font-bold text-[#93c5fd] text-[13px]">#TRX-99199</span>
                                </td>
                                <td class="px-6 py-4 text-gray-400">
                                    <div class="flex flex-col text-[12px] font-medium opacity-70">
                                        <span class="text-gray-500">13:55, 24 Okt</span>
                                        <span class="text-gray-400 mt-0.5">2023</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3 opacity-60">
                                        <div class="w-7 h-7 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center text-[10px] font-bold shrink-0">BK</div>
                                        <div class="flex flex-col text-[12px]">
                                            <span class="font-bold text-gray-500">Budi</span>
                                            <span class="text-gray-400">Kurniawan</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 bg-gray-100 text-gray-400 text-[9px] font-extrabold rounded-full opacity-70 border border-gray-200">QRIS</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-medium text-gray-400 text-[13px] opacity-80">Rp 450.000</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 bg-red-100/50 text-[#dc2626] text-[9px] font-black tracking-widest rounded uppercase border border-red-200/50">DIBATALKAN</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <button class="text-gray-400 hover:text-gray-600 transition-colors p-1 rounded-lg">
                                        <span class="material-symbols-rounded" style="font-size: 18px;">visibility_off</span>
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Row 4 -->
                            <tr class="hover:bg-blue-50/20 transition-colors">
                                <td class="px-6 py-4">
                                    <span class="font-bold text-[#3b82f6] text-[13px]">#TRX-99198</span>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <div class="flex flex-col text-[12px] font-medium">
                                        <span class="text-gray-800">13:42, 24 Okt</span>
                                        <span class="text-gray-500 mt-0.5">2023</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[10px] font-bold shrink-0">ST</div>
                                        <div class="flex flex-col text-[12px]">
                                            <span class="font-bold text-gray-800">Siti</span>
                                            <span class="text-gray-500">Aminah</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="inline-flex flex-col items-center bg-[#ffedd5] text-[#c2410c] px-3 py-1 rounded-xl border border-[#fed7aa] leading-[1.1]">
                                        <span class="text-[8px] font-extrabold uppercase">KREDIT</span>
                                        <span class="text-[8px] font-extrabold uppercase">ANGGOTA</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-medium text-gray-700 text-[13px]">Rp 1.120.000</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 bg-[#f0fdf4] text-[#15803d] text-[9px] font-black tracking-widest rounded uppercase border border-green-200">SELESAI</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <button class="text-[#3b82f6] hover:text-blue-800 transition-colors p-1 rounded-lg">
                                        <span class="material-symbols-rounded" style="font-size: 18px;">print</span>
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Row 5 -->
                            <tr class="hover:bg-blue-50/20 transition-colors">
                                <td class="px-6 py-4">
                                    <span class="font-bold text-[#3b82f6] text-[13px]">#TRX-99197</span>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <div class="flex flex-col text-[12px] font-medium">
                                        <span class="text-gray-800">13:30, 24 Okt</span>
                                        <span class="text-gray-500 mt-0.5">2023</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[10px] font-bold shrink-0">ST</div>
                                        <div class="flex flex-col text-[12px]">
                                            <span class="font-bold text-gray-800">Siti</span>
                                            <span class="text-gray-500">Aminah</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 bg-[#eff6ff] text-[#1d4ed8] text-[9px] font-extrabold rounded-full border border-blue-100">QRIS</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-medium text-gray-700 text-[13px]">Rp 88.000</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 bg-[#f0fdf4] text-[#15803d] text-[9px] font-black tracking-widest rounded uppercase border border-green-200">SELESAI</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <button class="text-[#3b82f6] hover:text-blue-800 transition-colors p-1 rounded-lg">
                                        <span class="material-symbols-rounded" style="font-size: 18px;">print</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <!-- Pagination Footer -->
                    <div class="px-6 py-4 bg-[#f8fafc] border-t border-gray-200 flex items-center justify-between">
                        <span class="text-[#64748b] font-bold text-[11px]">Menampilkan 1-10 dari 142 transaksi</span>
                        
                        <div class="flex items-center gap-1">
                            <button class="w-7 h-7 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 transition-colors">
                                <span class="material-symbols-rounded" style="font-size: 16px;">chevron_left</span>
                            </button>
                            <button class="w-7 h-7 flex items-center justify-center rounded bg-[#0047cc] text-white font-bold text-[11px] shadow-sm">1</button>
                            <button class="w-7 h-7 flex items-center justify-center rounded text-gray-500 hover:bg-gray-200 transition-colors text-[11px] font-bold">2</button>
                            <button class="w-7 h-7 flex items-center justify-center rounded text-gray-500 hover:bg-gray-200 transition-colors text-[11px] font-bold">3</button>
                            <span class="w-7 h-7 flex items-center justify-center text-gray-400 text-[12px] font-bold tracking-widest">...</span>
                            <button class="w-7 h-7 flex items-center justify-center rounded text-gray-500 hover:bg-gray-200 transition-colors text-[11px] font-bold">15</button>
                            <button class="w-7 h-7 flex items-center justify-center rounded text-gray-500 hover:bg-gray-200 transition-colors">
                                <span class="material-symbols-rounded" style="font-size: 16px;">chevron_right</span>
                            </button>
                        </div>
                    </div>
                </div>
                
            </main>
        </div>
    </div>
</div>
@endsection
