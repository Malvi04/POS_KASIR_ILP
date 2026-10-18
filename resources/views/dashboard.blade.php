@extends('layouts.app')

@section('title', 'Dashboard Manager - ILP POS')

@section('content')
<div class="flex h-screen w-full bg-[#f4f7fb] font-sans text-gray-800 overflow-hidden">
    <!-- Sidebar -->
    <x-manager-sidebar active="dashboard" />

    <!-- Main Workspace -->
    <main class="flex-1 flex flex-col h-screen overflow-y-auto relative">
        
        <!-- Top Navigation -->
        <header class="h-20 bg-[#f4f7fb] flex items-center justify-between px-8 shrink-0 sticky top-0 z-10">
            <!-- Search -->
            <div class="relative w-[400px]">
                <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                <input type="text" placeholder="Cari laporan atau produk..." 
                       class="w-full bg-white border-none rounded-full py-2.5 pl-11 pr-4 text-[14px] text-gray-700 placeholder-gray-400 focus:ring-2 focus:ring-[#0052cc] outline-none shadow-sm">
            </div>

            <!-- Right Actions -->
            <div class="flex items-center gap-6">
                <button class="relative text-gray-600 hover:text-gray-900 transition-colors">
                    <span class="material-symbols-rounded text-[24px]">notifications</span>
                    <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full border border-[#f4f7fb]"></span>
                </button>
                <button class="text-gray-600 hover:text-gray-900 transition-colors">
                    <span class="material-symbols-rounded text-[24px]">help</span>
                </button>
                <div class="h-6 w-px bg-gray-300"></div>
                <span class="font-bold text-[#0052cc] text-[15px]">MarketFlow POS</span>
            </div>
        </header>

        <div class="px-8 pb-8 flex flex-col gap-6">
            
            <!-- 4 Metric Cards -->
            <div class="grid grid-cols-4 gap-6">
                <!-- Card 1 -->
                <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm flex flex-col justify-between h-[130px]">
                    <div class="flex justify-between items-start">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                            <span class="material-symbols-rounded text-[#0052cc] text-[20px]">payments</span>
                        </div>
                        <div class="bg-green-100 text-green-700 px-2 py-1 rounded-md flex items-center gap-1">
                            <span class="material-symbols-rounded text-[14px]">trending_up</span>
                            <span class="text-[12px] font-bold">12%</span>
                        </div>
                    </div>
                    <div>
                        <p class="text-[13px] text-gray-500 font-medium mb-1">Total Penjualan Hari Ini</p>
                        <h3 class="text-[20px] font-mono font-bold text-gray-900">Rp 12.450.000</h3>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm flex flex-col justify-between h-[130px]">
                    <div class="flex justify-between items-start">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                            <span class="material-symbols-rounded text-[#0052cc] text-[20px]">receipt_long</span>
                        </div>
                        <div class="bg-green-100 text-green-700 px-2 py-1 rounded-md flex items-center gap-1">
                            <span class="material-symbols-rounded text-[14px]">trending_up</span>
                            <span class="text-[12px] font-bold">8%</span>
                        </div>
                    </div>
                    <div>
                        <p class="text-[13px] text-gray-500 font-medium mb-1">Jumlah Transaksi</p>
                        <h3 class="text-[20px] font-mono font-bold text-gray-900">184</h3>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm flex flex-col justify-between h-[130px]">
                    <div class="flex justify-between items-start">
                        <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center">
                            <span class="material-symbols-rounded text-green-600 text-[20px]">how_to_reg</span>
                        </div>
                    </div>
                    <div>
                        <p class="text-[13px] text-gray-500 font-medium mb-1">Kasir Aktif</p>
                        <div class="flex items-baseline gap-2">
                            <h3 class="text-[20px] font-mono font-bold text-gray-900">6</h3>
                            <span class="text-[15px] font-mono font-bold text-gray-400">/ 8</span>
                        </div>
                    </div>
                </div>

                <!-- Card 4 -->
                <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm flex flex-col justify-between h-[130px]">
                    <div class="flex justify-between items-start">
                        <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center">
                            <span class="material-symbols-rounded text-red-500 text-[20px]">warning</span>
                        </div>
                        <span class="text-[12px] font-extrabold text-red-600 tracking-wider">KRITIS</span>
                    </div>
                    <div>
                        <p class="text-[13px] text-gray-500 font-medium mb-1">Peringatan Stok Rendah</p>
                        <h3 class="text-[20px] font-mono font-bold text-gray-900">12 Item</h3>
                    </div>
                </div>
            </div>

            <!-- Middle Section -->
            <div class="flex gap-6">
                <!-- Left: Chart -->
                <div class="flex-[2] bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex flex-col">
                    <div class="flex justify-between items-start mb-8">
                        <div>
                            <h2 class="text-[16px] font-bold text-gray-900">Tren Penjualan Mingguan</h2>
                            <p class="text-[13px] text-gray-500 mt-1">Statistik performa gerai 7 hari terakhir</p>
                        </div>
                        <button class="flex items-center gap-2 bg-gray-50 hover:bg-gray-100 text-gray-700 px-3 py-1.5 rounded-lg text-[13px] font-medium transition-colors border border-gray-200">
                            Minggu Ini
                            <span class="material-symbols-rounded text-[18px]">expand_more</span>
                        </button>
                    </div>
                    
                    <!-- Simulated Bar Chart -->
                    <div class="flex-1 flex items-end justify-between px-2 gap-4 h-[180px] mt-auto">
                        <div class="w-full flex flex-col items-center gap-3">
                            <div class="w-full bg-[#dbeafe] rounded-t-sm h-[80px]"></div>
                            <span class="text-[10px] font-bold text-gray-500">SEN</span>
                        </div>
                        <div class="w-full flex flex-col items-center gap-3">
                            <div class="w-full bg-[#dbeafe] rounded-t-sm h-[60px]"></div>
                            <span class="text-[10px] font-bold text-gray-500">SEL</span>
                        </div>
                        <div class="w-full flex flex-col items-center gap-3">
                            <div class="w-full bg-[#dbeafe] rounded-t-sm h-[100px]"></div>
                            <span class="text-[10px] font-bold text-gray-500">RAB</span>
                        </div>
                        <div class="w-full flex flex-col items-center gap-3">
                            <div class="w-full bg-[#dbeafe] rounded-t-sm h-[150px]"></div>
                            <span class="text-[10px] font-bold text-gray-500">KAM</span>
                        </div>
                        <div class="w-full flex flex-col items-center gap-3">
                            <div class="w-full bg-[#dbeafe] rounded-t-sm h-[120px]"></div>
                            <span class="text-[10px] font-bold text-gray-500">JUM</span>
                        </div>
                        <div class="w-full flex flex-col items-center gap-3">
                            <div class="w-full bg-[#dbeafe] rounded-t-sm h-[90px]"></div>
                            <span class="text-[10px] font-bold text-gray-500">SAB</span>
                        </div>
                        <div class="w-full flex flex-col items-center gap-3">
                            <div class="w-full bg-[#5c8ce6] rounded-t-sm h-[170px] shadow-sm"></div>
                            <span class="text-[10px] font-bold text-gray-800">MIN</span>
                        </div>
                    </div>
                </div>

                <!-- Right: Peringatan Kritis -->
                <div class="flex-1 bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex flex-col">
                    <div class="flex items-center gap-2 mb-6">
                        <span class="material-symbols-rounded text-orange-500 text-[20px]">error</span>
                        <h2 class="text-[16px] font-bold text-gray-900">Peringatan Kritis</h2>
                    </div>

                    <div class="flex flex-col gap-3 flex-1">
                        <!-- Alert Item 1 -->
                        <div class="border-l-[3px] border-red-500 bg-[#fff5f5] p-3.5 rounded-r-xl rounded-l-sm">
                            <span class="text-[10px] font-black text-red-600 tracking-wider">STOK KRITIS</span>
                            <h4 class="text-[13px] font-bold text-gray-900 mt-1">Susu UHT Full Cream 1L</h4>
                            <p class="text-[12px] text-gray-600 mt-0.5">Tersisa 3 unit di rak utama.</p>
                        </div>
                        <!-- Alert Item 2 -->
                        <div class="border-l-[3px] border-yellow-400 bg-[#fffbeb] p-3.5 rounded-r-xl rounded-l-sm">
                            <span class="text-[10px] font-black text-yellow-600 tracking-wider">KEDALUWARSA SEGERA</span>
                            <h4 class="text-[13px] font-bold text-gray-900 mt-1">Roti Gandum Spesial</h4>
                            <p class="text-[12px] text-gray-600 mt-0.5">6 item berakhir dalam 24 jam.</p>
                        </div>
                        <!-- Alert Item 3 -->
                        <div class="border-l-[3px] border-red-500 bg-[#fff5f5] p-3.5 rounded-r-xl rounded-l-sm">
                            <span class="text-[10px] font-black text-red-600 tracking-wider">STOK KOSONG</span>
                            <h4 class="text-[13px] font-bold text-gray-900 mt-1">Minyak Goreng 2L</h4>
                            <p class="text-[12px] text-gray-600 mt-0.5">Segera lakukan re-order vendor.</p>
                        </div>
                    </div>

                    <button class="w-full mt-4 bg-white border border-[#0052cc] text-[#0052cc] hover:bg-blue-50 font-bold py-2.5 rounded-xl transition-colors text-[13px]">
                        Lihat Semua Inventaris
                    </button>
                </div>
            </div>

            <!-- Bottom Section: Aktivitas Kasir -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-[16px] font-bold text-gray-900">Aktivitas Kasir Real-time</h2>
                    <div class="flex gap-2">
                        <div class="flex items-center gap-1.5 bg-gray-50 border border-gray-200 px-3 py-1 rounded-full">
                            <div class="w-2 h-2 rounded-full bg-green-500"></div>
                            <span class="text-[11px] font-bold text-gray-600">Online: 6</span>
                        </div>
                        <div class="flex items-center gap-1.5 bg-gray-50 border border-gray-200 px-3 py-1 rounded-full">
                            <div class="w-2 h-2 rounded-full bg-gray-300"></div>
                            <span class="text-[11px] font-bold text-gray-600">Offline: 2</span>
                        </div>
                    </div>
                </div>

                <div class="w-full overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="pb-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider pl-4">Petugas</th>
                                <th class="pb-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Nomor Kasir</th>
                                <th class="pb-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Total Transaksi</th>
                                <th class="pb-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="pb-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider pr-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-[14px]">
                            <!-- Row 1 -->
                            <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                <td class="py-4 pl-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-blue-100 text-[#0052cc] flex items-center justify-center font-bold text-[12px]">BN</div>
                                        <span class="font-bold text-gray-900">Budi Nugraha</span>
                                    </div>
                                </td>
                                <td class="py-4 text-gray-600 font-semibold text-[13px]">Kasir 1</td>
                                <td class="py-4 font-mono font-medium text-gray-700 text-[13px]">Rp 3.420.000</td>
                                <td class="py-4">
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div>
                                        <span class="text-[13px] font-bold text-green-600">Aktif</span>
                                    </div>
                                </td>
                                <td class="py-4 pr-4 text-center">
                                    <button class="text-gray-400 hover:text-gray-700 transition-colors">
                                        <span class="material-symbols-rounded text-[20px]">more_vert</span>
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Row 2 -->
                            <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                <td class="py-4 pl-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center font-bold text-[12px]">AS</div>
                                        <span class="font-bold text-gray-900">Ani Safitri</span>
                                    </div>
                                </td>
                                <td class="py-4 text-gray-600 font-semibold text-[13px]">Kasir 2</td>
                                <td class="py-4 font-mono font-medium text-gray-700 text-[13px]">Rp 2.150.000</td>
                                <td class="py-4">
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div>
                                        <span class="text-[13px] font-bold text-green-600">Aktif</span>
                                    </div>
                                </td>
                                <td class="py-4 pr-4 text-center">
                                    <button class="text-gray-400 hover:text-gray-700 transition-colors">
                                        <span class="material-symbols-rounded text-[20px]">more_vert</span>
                                    </button>
                                </td>
                            </tr>

                            <!-- Row 3 -->
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-4 pl-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center font-bold text-[12px]">DP</div>
                                        <span class="font-bold text-gray-900">Dedi Permana</span>
                                    </div>
                                </td>
                                <td class="py-4 text-gray-600 font-semibold text-[13px]">Kasir 3</td>
                                <td class="py-4 font-mono font-medium text-gray-700 text-[13px]">Rp 0</td>
                                <td class="py-4">
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-1.5 h-1.5 rounded-full bg-gray-300"></div>
                                        <span class="text-[13px] font-bold text-gray-500">Break</span>
                                    </div>
                                </td>
                                <td class="py-4 pr-4 text-center">
                                    <button class="text-gray-400 hover:text-gray-700 transition-colors">
                                        <span class="material-symbols-rounded text-[20px]">more_vert</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        
        <!-- Floating Action Button -->
        <button class="fixed bottom-8 right-8 w-14 h-14 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-105 active:scale-95 z-20">
            <span class="material-symbols-rounded text-[28px]">add</span>
        </button>

    </main>
</div>
@endsection
