@extends('layouts.app')

@section('title', 'Manajemen Absensi - ILP POS')

@section('content')
<div class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">
    
    <!-- Sidebar -->
    <x-manager-sidebar active="absensi" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        
        <!-- Top Header -->
        <header class="h-[72px] bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center h-full">
                <h1 class="text-[18px] font-black text-gray-900 mr-8 tracking-tight">Manajemen Absensi</h1>
                
                <nav class="flex h-full gap-8">
                    <a href="#" class="h-full flex items-center text-[#0052cc] font-bold text-[14px] border-b-[3px] border-[#0052cc] pt-[3px]">
                        Rekap Harian
                    </a>
                    <a href="#" class="h-full flex items-center text-gray-500 font-medium text-[14px] border-b-[3px] border-transparent hover:text-gray-800 transition-colors pt-[3px]">
                        Jadwal Shift
                    </a>
                    <a href="#" class="h-full flex items-center text-gray-500 font-medium text-[14px] border-b-[3px] border-transparent hover:text-gray-800 transition-colors pt-[3px]">
                        Pengajuan Izin
                    </a>
                </nav>
            </div>

            <div class="flex items-center gap-5 text-gray-500">
                <button class="hover:text-gray-900 transition-colors relative">
                    <span class="material-symbols-rounded text-[24px]">notifications</span>
                    <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full border border-white"></span>
                </button>
                <button class="hover:text-gray-900 transition-colors">
                    <span class="material-symbols-rounded text-[24px]">help_outline</span>
                </button>
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-8">
            
            <!-- Metric Cards -->
            <div class="grid grid-cols-2 gap-6 mb-6">
                <!-- Card 1 -->
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-[#eef2ff] text-[#0052cc] flex items-center justify-center shrink-0">
                        <span class="material-symbols-rounded text-[28px]">groups</span>
                    </div>
                    <div>
                        <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">TOTAL STAF</span>
                        <h3 class="text-3xl font-bold text-gray-900 mt-1">24</h3>
                    </div>
                </div>
                <!-- Card 2 -->
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-green-50 text-green-600 flex items-center justify-center shrink-0">
                        <span class="material-symbols-rounded text-[28px]">check_circle</span>
                    </div>
                    <div>
                        <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">HADIR HARI INI</span>
                        <h3 class="text-3xl font-bold text-gray-900 mt-1">21</h3>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm mb-6 flex flex-wrap items-end justify-between gap-4">
                <div class="flex items-end gap-6">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-bold text-gray-600">Tanggal</label>
                        <input type="text" value="10/27/2023" class="w-[160px] px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:border-[#0052cc] text-[13px] text-gray-700 bg-white">
                    </div>
                    
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-bold text-gray-600">Shift</label>
                        <div class="relative">
                            <select class="appearance-none w-[180px] bg-white border border-gray-200 rounded-lg px-3 py-2 pr-8 text-[13px] text-gray-700 focus:outline-none focus:border-[#0052cc]">
                                <option>Semua Shift</option>
                                <option>Shift Pagi</option>
                                <option>Shift Sore</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[18px]">expand_more</span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-bold text-gray-600">Lokasi</label>
                        <div class="relative">
                            <select class="appearance-none w-[180px] bg-white border border-gray-200 rounded-lg px-3 py-2 pr-8 text-[13px] text-gray-700 focus:outline-none focus:border-[#0052cc]">
                                <option>Semua Cabang</option>
                                <option>Cabang Pusat</option>
                                <option>Cabang Barat</option>
                                <option>Cabang Selatan</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[18px]">expand_more</span>
                        </div>
                    </div>
                </div>

                <button class="bg-[#0052cc] hover:bg-[#0043a8] text-white px-5 py-2.5 rounded-lg font-medium text-[13px] flex items-center justify-center gap-2 shadow-sm transition-colors shrink-0">
                    <span class="material-symbols-rounded text-[18px]">download</span>
                    Export Data Absensi
                </button>
            </div>

            <!-- Main Content Area (Table & Chart) -->
            <div class="flex flex-col xl:flex-row gap-6 items-start">
                
                <!-- Table Card -->
                <div class="flex-[3] w-full bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">
                    <div class="p-5 flex items-center justify-between border-b border-gray-100">
                        <h2 class="text-[16px] font-bold text-gray-900">Log Absensi Hari Ini</h2>
                        <div class="relative w-[240px]">
                            <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                            <input type="text" placeholder="Cari nama staf..." class="w-full pl-9 pr-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:border-[#0052cc] text-[13px] text-gray-700 bg-white shadow-sm">
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200">
                                    <th class="py-3 px-6 text-[12px] font-bold text-gray-500">Kasir</th>
                                    <th class="py-3 px-6 text-[12px] font-bold text-gray-500">Nama Staf</th>
                                    <th class="py-3 px-6 text-[12px] font-bold text-gray-500 text-center">Masuk</th>
                                    <th class="py-3 px-6 text-[12px] font-bold text-gray-500 text-center">Pulang</th>
                                    <th class="py-3 px-6 text-[12px] font-bold text-gray-500">Lokasi</th>
                                </tr>
                            </thead>
                            <tbody class="text-[13px]">
                                <!-- Row 1 -->
                                <tr class="border-b border-gray-100">
                                    <td class="py-4 px-6 font-bold text-gray-800">Kasir 1</td>
                                    <td class="py-4 px-6">
                                        <div class="font-bold text-gray-800 leading-tight">Andi<br>Setiawan</div>
                                    </td>
                                    <td class="py-4 px-6 text-center font-mono font-bold text-gray-800">06:55</td>
                                    <td class="py-4 px-6 text-center font-mono font-bold text-gray-800">15:05</td>
                                    <td class="py-4 px-6 text-gray-600 leading-tight">Cabang<br>Pusat</td>
                                </tr>
                                
                                <!-- Row 2 -->
                                <tr class="border-b border-gray-100">
                                    <td class="py-4 px-6 font-bold text-gray-800">Kasir 2</td>
                                    <td class="py-4 px-6">
                                        <div class="font-bold text-gray-800 leading-tight">Bambang<br>Pamungkas</div>
                                    </td>
                                    <td class="py-4 px-6 text-center font-mono font-bold text-red-600">07:15</td>
                                    <td class="py-4 px-6 text-center font-mono font-bold text-gray-800">-</td>
                                    <td class="py-4 px-6 text-gray-600 leading-tight">Cabang<br>Barat</td>
                                </tr>
                                
                                <!-- Row 3 -->
                                <tr class="border-b border-gray-100">
                                    <td class="py-4 px-6 font-bold text-gray-800">Kasir 3</td>
                                    <td class="py-4 px-6">
                                        <div class="font-bold text-gray-800 leading-tight">Citra Lestari</div>
                                    </td>
                                    <td class="py-4 px-6 text-center font-mono font-bold text-gray-800">-</td>
                                    <td class="py-4 px-6 text-center font-mono font-bold text-gray-800">-</td>
                                    <td class="py-4 px-6 text-gray-600 leading-tight">Cabang<br>Selatan</td>
                                </tr>
                                
                                <!-- Row 4 -->
                                <tr class="border-b border-gray-100">
                                    <td class="py-4 px-6 font-bold text-gray-800">Kasir 4</td>
                                    <td class="py-4 px-6">
                                        <div class="font-bold text-gray-800 leading-tight">Dedi<br>Nurcahyo</div>
                                    </td>
                                    <td class="py-4 px-6 text-center font-mono font-bold text-gray-800">06:45</td>
                                    <td class="py-4 px-6 text-center font-mono font-bold text-gray-800">-</td>
                                    <td class="py-4 px-6 text-gray-600 leading-tight">Cabang<br>Pusat</td>
                                </tr>
                                
                                <!-- Row 5 -->
                                <tr class="border-b border-gray-100">
                                    <td class="py-4 px-6 font-bold text-gray-800">Kasir 5</td>
                                    <td class="py-4 px-6">
                                        <div class="font-bold text-gray-800 leading-tight">Eka<br>Ramadhan</div>
                                    </td>
                                    <td class="py-4 px-6 text-center font-mono font-bold text-gray-800">07:02</td>
                                    <td class="py-4 px-6 text-center font-mono font-bold text-gray-800">-</td>
                                    <td class="py-4 px-6 text-gray-600 leading-tight">Cabang<br>Selatan</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="bg-gray-50 border-t border-gray-200 p-4 flex items-center justify-between">
                        <span class="text-[12px] text-gray-500 font-medium">Menampilkan 5 dari 24 entri</span>
                        <div class="flex items-center gap-1.5">
                            <button class="px-3 py-1.5 rounded bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium text-[12px]">Prev</button>
                            <button class="w-8 h-8 rounded bg-[#0052cc] text-white font-bold text-[12px] shadow-sm flex items-center justify-center">1</button>
                            <button class="w-8 h-8 rounded bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium text-[12px] flex items-center justify-center">2</button>
                            <button class="px-3 py-1.5 rounded bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium text-[12px]">Next</button>
                        </div>
                    </div>
                </div>

                <!-- Right Chart -->
                <div class="flex-1 w-full xl:max-w-[320px] bg-white rounded-xl border border-gray-200 shadow-sm p-6 shrink-0">
                    <h3 class="text-[11px] font-black text-gray-500 uppercase tracking-wider mb-6">TREN KEHADIRAN (30 HARI)</h3>
                    
                    <div class="h-[140px] flex items-end justify-between gap-1 w-full mt-auto">
                        <div class="w-full bg-blue-200 rounded-t-sm h-[50%]"></div>
                        <div class="w-full bg-[#dbeafe] rounded-t-sm h-[80%]"></div>
                        <div class="w-full bg-[#93c5fd] rounded-t-sm h-[45%]"></div>
                        <div class="w-full bg-[#dbeafe] rounded-t-sm h-[90%]"></div>
                        <div class="w-full bg-blue-300 rounded-t-sm h-[85%]"></div>
                        <div class="w-full bg-blue-500 rounded-t-sm h-[75%]"></div>
                        <div class="w-full bg-[#dbeafe] rounded-t-sm h-[70%]"></div>
                        <div class="w-full bg-[#60a5fa] rounded-t-sm h-[55%]"></div>
                        <div class="w-full bg-[#2563eb] rounded-t-sm h-[100%]"></div>
                        <div class="w-full bg-blue-400 rounded-t-sm h-[65%]"></div>
                    </div>
                </div>

            </div>

        </div>
    </main>

</div>
@endsection
