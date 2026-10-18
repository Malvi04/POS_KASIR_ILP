@extends('layouts.app')

@section('title', 'Manajemen Anggota - ILP POS')

@section('content')
<div class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">
    
    <!-- Sidebar -->
    <x-manager-sidebar active="members" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        
        <!-- Top Header -->
        <header class="h-[72px] bg-[#f8f9fa] border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <h1 class="text-[18px] font-black text-gray-900 tracking-tight">Management Anggota</h1>

            <div class="flex items-center gap-6 text-gray-500">
                <button class="hover:text-gray-900 transition-colors relative">
                    <span class="material-symbols-rounded text-[24px]">notifications</span>
                </button>
                <button class="hover:text-gray-900 transition-colors">
                    <span class="material-symbols-rounded text-[24px]">autorenew</span>
                </button>
                <div class="h-6 w-px bg-gray-300"></div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-rounded text-green-500 text-[20px]">wifi</span>
                    <span class="text-[14px] font-semibold text-gray-600">Terhubung</span>
                </div>
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-8 flex flex-col">
            
            <!-- Summary Cards -->
            <div class="grid grid-cols-4 gap-6 mb-6">
                <!-- Card 1 -->
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm flex flex-col">
                    <p class="text-[11px] font-bold text-gray-500 tracking-wider mb-2 uppercase">TOTAL ANGGOTA</p>
                    <div class="flex items-baseline gap-2 mt-auto">
                        <h3 class="text-[28px] font-bold text-[#0052cc] leading-none">1,284</h3>
                        <span class="text-green-600 text-[11px] font-bold">+12 bln ini</span>
                    </div>
                </div>
                
                <!-- Card 2 -->
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm flex flex-col">
                    <p class="text-[11px] font-bold text-gray-500 tracking-wider mb-2 uppercase">TOTAL PIUTANG AKTIF</p>
                    <div class="flex items-baseline gap-2 mt-auto">
                        <span class="text-[16px] font-bold text-[#b45309]">Rp</span>
                        <h3 class="text-[24px] font-mono font-bold text-[#b45309] leading-none tracking-tight">482.5M</h3>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm flex flex-col">
                    <p class="text-[11px] font-bold text-gray-500 tracking-wider mb-2 uppercase">PLAFON TERSEDIA</p>
                    <div class="flex items-baseline gap-2 mt-auto">
                        <span class="text-[16px] font-bold text-gray-800">Rp</span>
                        <h3 class="text-[24px] font-mono font-bold text-gray-800 leading-none tracking-tight">1.2B</h3>
                    </div>
                </div>

                <!-- Card 4 -->
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm flex flex-col">
                    <p class="text-[11px] font-bold text-gray-500 tracking-wider mb-2 uppercase">STATUS KREDIT SEHAT</p>
                    <div class="flex items-baseline gap-2 mt-auto text-green-600">
                        <h3 class="text-[28px] font-bold leading-none">98.2%</h3>
                        <span class="material-symbols-rounded text-[20px] font-bold">trending_up</span>
                    </div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="flex gap-4 mb-6">
                <div class="relative flex-1">
                    <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                    <input type="text" placeholder="Cari NIK atau Nama Anggota..." class="w-full pl-11 pr-4 py-2.5 rounded-lg border border-gray-200 bg-[#f8f9fa] focus:outline-none focus:ring-2 focus:ring-[#0052cc]/20 focus:border-[#0052cc] text-[13px] shadow-sm">
                </div>
                <div class="flex gap-3 shrink-0">
                    <button class="flex items-center gap-2 px-5 py-2.5 bg-white border border-gray-200 rounded-lg text-[13px] font-bold text-gray-700 hover:bg-gray-50 transition-colors shadow-sm">
                        <span class="material-symbols-rounded text-[18px]">filter_list</span>
                        Filter
                    </button>
                    <button class="flex items-center gap-2 px-5 py-2.5 bg-[#0052cc] border border-[#0052cc] rounded-lg text-[13px] font-bold text-white hover:bg-[#0043a8] transition-colors shadow-sm">
                        <span class="material-symbols-rounded text-[18px]">person_add</span>
                        Tambah Anggota
                    </button>
                </div>
            </div>

            <!-- Data Table -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden flex flex-col mb-6">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-[13px] whitespace-nowrap">
                        <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 text-[11px] font-bold tracking-wider uppercase">
                            <tr>
                                <th class="px-6 py-4">NAMA ANGGOTA</th>
                                <th class="px-6 py-4">NIK / ID</th>
                                <th class="px-6 py-4">DEPARTEMEN</th>
                                <th class="px-6 py-4 text-center">SISA<br>PLAFON</th>
                                <th class="px-6 py-4 text-center">TOTAL<br>PIUTANG</th>
                                <th class="px-6 py-4 text-center">STATUS</th>
                                <th class="px-6 py-4"></th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            <!-- Row 1 -->
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-[#dbeafe] text-[#0052cc] flex items-center justify-center font-bold text-[11px] shrink-0">BP</div>
                                        <div class="font-bold text-gray-900 leading-tight">Bambang<br>Pamungkas</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 font-mono font-medium text-gray-600">327501002230004</td>
                                <td class="px-6 py-4 text-gray-600 leading-tight">Logistik &<br>Gudang</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex flex-col items-end">
                                        <span class="text-[10px] text-gray-500 font-bold tracking-wider">Rp</span>
                                        <span class="font-mono font-bold text-gray-800 text-[14px]">4.500.000</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex flex-col items-end">
                                        <span class="text-[10px] text-[#b45309] font-bold tracking-wider opacity-80">Rp</span>
                                        <span class="font-mono font-bold text-[#b45309] text-[14px]">500.000</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-full text-[10px] font-bold tracking-widest uppercase">AKTIF</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <a href="{{ url('/member-detail') }}" class="flex items-center gap-1.5 justify-center text-[#0052cc] hover:text-[#0043a8] font-bold text-[11px] transition-colors">
                                        <span class="material-symbols-rounded text-[18px]">visibility</span>
                                        <div class="text-left leading-tight">Detail<br>Piutang</div>
                                    </a>
                                </td>
                            </tr>
                            
                            <!-- Row 2 -->
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-orange-100 text-orange-700 flex items-center justify-center font-bold text-[11px] shrink-0">SL</div>
                                        <div class="font-bold text-gray-900">Siti Lestari</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 font-mono font-medium text-gray-600">327501440230012</td>
                                <td class="px-6 py-4 text-gray-600">Administrasi</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex flex-col items-end">
                                        <span class="text-[10px] text-gray-500 font-bold tracking-wider">Rp</span>
                                        <span class="font-mono font-bold text-gray-800 text-[14px]">1.200.000</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex flex-col items-end">
                                        <span class="text-[10px] text-[#b45309] font-bold tracking-wider opacity-80">Rp</span>
                                        <span class="font-mono font-bold text-[#b45309] text-[14px]">3.800.000</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-full text-[10px] font-bold tracking-widest uppercase">AKTIF</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <a href="{{ url('/member-detail') }}" class="flex items-center gap-1.5 justify-center text-[#0052cc] hover:text-[#0043a8] font-bold text-[11px] transition-colors">
                                        <span class="material-symbols-rounded text-[18px]">visibility</span>
                                        <div class="text-left leading-tight">Detail<br>Piutang</div>
                                    </a>
                                </td>
                            </tr>

                            <!-- Row 3 - Blocked -->
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-bold text-[11px] shrink-0">AS</div>
                                        <div class="font-bold text-gray-500 leading-tight">Andri<br>Setiawan</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 font-mono font-medium text-gray-400">327501082230899</td>
                                <td class="px-6 py-4 text-gray-400">Produksi A</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex flex-col items-end opacity-60">
                                        <span class="text-[10px] text-gray-400 font-bold tracking-wider">Rp</span>
                                        <span class="font-mono font-bold text-gray-400 text-[14px]">0</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex flex-col items-end">
                                        <span class="text-[10px] text-red-600 font-bold tracking-wider opacity-80">Rp</span>
                                        <span class="font-mono font-bold text-red-600 text-[14px]">5.000.000</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-3 py-1 bg-red-100 text-red-600 rounded-full text-[10px] font-bold tracking-widest uppercase">DIBLOKIR</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <a href="{{ url('/member-detail') }}" class="flex items-center gap-1.5 justify-center text-[#0052cc] hover:text-[#0043a8] font-bold text-[11px] transition-colors">
                                        <span class="material-symbols-rounded text-[18px]">visibility</span>
                                        <div class="text-left leading-tight">Detail<br>Piutang</div>
                                    </a>
                                </td>
                            </tr>

                            <!-- Row 4 -->
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-purple-100 text-purple-700 flex items-center justify-center font-bold text-[11px] shrink-0">DI</div>
                                        <div class="font-bold text-gray-900">Dewi Indah</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 font-mono font-medium text-gray-600">327501550230056</td>
                                <td class="px-6 py-4 text-gray-600">Maintenance</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex flex-col items-end">
                                        <span class="text-[10px] text-gray-500 font-bold tracking-wider">Rp</span>
                                        <span class="font-mono font-bold text-gray-800 text-[14px]">5.000.000</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex flex-col items-end">
                                        <span class="text-[10px] text-[#b45309] font-bold tracking-wider opacity-80">Rp</span>
                                        <span class="font-mono font-bold text-[#b45309] text-[14px]">0</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-full text-[10px] font-bold tracking-widest uppercase">AKTIF</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <a href="#" class="flex items-center gap-1.5 justify-center text-[#0052cc] hover:text-[#0043a8] font-bold text-[11px] transition-colors">
                                        <span class="material-symbols-rounded text-[18px]">visibility</span>
                                        <div class="text-left leading-tight">Detail<br>Piutang</div>
                                    </a>
                                </td>
                            </tr>

                            <!-- Row 5 -->
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-[#dbeafe] text-[#0052cc] flex items-center justify-center font-bold text-[11px] shrink-0">RH</div>
                                        <div class="font-bold text-gray-900 leading-tight">Raka<br>Hendra</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 font-mono font-medium text-gray-600">327501009930011</td>
                                <td class="px-6 py-4 text-gray-600 leading-tight">Logistik &<br>Gudang</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex flex-col items-end">
                                        <span class="text-[10px] text-gray-500 font-bold tracking-wider">Rp</span>
                                        <span class="font-mono font-bold text-gray-800 text-[14px]">2.150.000</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex flex-col items-end">
                                        <span class="text-[10px] text-[#b45309] font-bold tracking-wider opacity-80">Rp</span>
                                        <span class="font-mono font-bold text-[#b45309] text-[14px]">2.850.000</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-full text-[10px] font-bold tracking-widest uppercase">AKTIF</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <a href="#" class="flex items-center gap-1.5 justify-center text-[#0052cc] hover:text-[#0043a8] font-bold text-[11px] transition-colors">
                                        <span class="material-symbols-rounded text-[18px]">visibility</span>
                                        <div class="text-left leading-tight">Detail<br>Piutang</div>
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="bg-[#f8f9fa] border-t border-gray-200 px-6 py-4 flex items-center justify-between">
                    <p class="text-[13px] text-gray-600 font-medium">Menampilkan 1-5 dari 1,284 anggota</p>
                    <div class="flex items-center gap-2 text-[13px]">
                        <button class="w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 transition-colors disabled:opacity-50">
                            <span class="material-symbols-rounded text-[20px]">chevron_left</span>
                        </button>
                        <button class="w-8 h-8 flex items-center justify-center rounded bg-[#0052cc] text-white font-bold shadow-sm">1</button>
                        <button class="w-8 h-8 flex items-center justify-center rounded text-gray-700 hover:bg-gray-200 font-bold transition-colors">2</button>
                        <button class="w-8 h-8 flex items-center justify-center rounded text-gray-700 hover:bg-gray-200 font-bold transition-colors">3</button>
                        <span class="text-gray-500 px-1 font-bold">...</span>
                        <button class="w-8 h-8 flex items-center justify-center rounded text-gray-700 hover:bg-gray-200 font-bold transition-colors">257</button>
                        <button class="w-8 h-8 flex items-center justify-center rounded text-gray-700 hover:bg-gray-200 transition-colors">
                            <span class="material-symbols-rounded text-[20px]">chevron_right</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bottom Footer -->
            <div class="mt-auto border-t border-gray-300 pt-6 flex justify-between items-center text-[12px] font-medium text-gray-500">
                <div class="flex items-center gap-4">
                    <span>Vigilant POS v2.4.1</span>
                    <span>Terakhir sinkronisasi: 2 menit yang lalu</span>
                </div>
                <div class="flex items-center gap-6">
                    <a href="#" class="hover:text-gray-800 border-b border-gray-400 pb-0.5 transition-colors">Pusat Bantuan</a>
                    <a href="#" class="hover:text-gray-800 border-b border-gray-400 pb-0.5 transition-colors">Kebijakan Privasi</a>
                </div>
            </div>

        </div>
    </main>
</div>
@endsection
