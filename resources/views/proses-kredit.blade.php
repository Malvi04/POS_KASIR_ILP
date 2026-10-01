@extends('layouts.app')

@section('title', 'Proses Kredit Anggota - ILP POS')

@section('content')
<div class="flex flex-col h-screen w-full bg-white font-sans text-gray-800">
    <!-- Header -->
    <header class="h-16 border-b border-gray-200 px-6 flex items-center justify-between shrink-0 bg-white">
        <div class="flex items-center gap-4">
            <a href="{{ url('/pos') }}" class="text-gray-500 hover:text-gray-800 p-2 rounded-full hover:bg-gray-100 transition-colors">
                <span class="material-symbols-rounded">arrow_back</span>
            </a>
            <h1 class="text-blue-600 font-medium text-[15px]">Proses Kredit Anggota</h1>
        </div>
        <div class="flex items-center gap-6">
            <div class="flex flex-col text-right">
                <span class="text-[10px] font-bold text-gray-500 tracking-wider">TOTAL TRANSAKSI</span>
                <span class="text-blue-600 font-mono font-bold text-[15px]">Rp 28.638</span>
            </div>
            <div class="bg-green-100/50 text-green-600 border border-green-200 px-4 py-1.5 rounded-full flex items-center gap-1.5 font-bold text-xs tracking-wide">
                <span class="material-symbols-rounded text-[16px]">wifi</span>
                ONLINE
            </div>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
        <!-- Left Panel (Gray) -->
        <div class="w-7/12 bg-[#f8f9fa] p-8 overflow-y-auto border-r border-gray-200">
            <label class="text-[11px] font-bold text-gray-500 tracking-wider mb-2 block">CARI ANGGOTA (NIK / NAMA)</label>
            <div class="relative mb-6">
                <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">person_search</span>
                <input type="text" placeholder="Masukkan nomor induk atau nama lengkap..." class="w-full pl-12 pr-4 py-3.5 rounded-xl border border-gray-200 bg-white focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 shadow-[0_2px_4px_rgba(0,0,0,0.02)] text-sm transition-all">
            </div>

            <!-- Selected Member Card -->
            <div class="bg-white rounded-2xl border-[1.5px] border-[#0052cc] p-6 relative shadow-sm">
                <button class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 transition-colors">
                    <span class="material-symbols-rounded text-[20px]">cancel</span>
                </button>

                <!-- Profile Info -->
                <div class="mb-6">
                    <div>
                        <h2 class="text-[16px] font-bold text-gray-800">Santoso</h2>
                        <div class="flex items-center gap-1.5 text-gray-500 mt-1">
                            <span class="material-symbols-rounded text-[14px]">badge</span>
                            <span class="text-xs font-medium">NIK: 1029384756</span>
                        </div>
                        <p class="text-xs font-medium text-gray-500 mt-0.5">Departemen: Logistik & Distribusi - Area 4</p>
                    </div>
                </div>

                <!-- Plafon Info -->
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-[#f8f9fa] rounded-xl p-4 border border-gray-100">
                        <span class="text-[10px] font-bold text-gray-500 tracking-wider">PLAFON BULANAN</span>
                        <p class="font-mono font-bold text-gray-700 mt-1.5 text-[15px]">Rp 2.500.000</p>
                    </div>
                    <div class="bg-[#f8f9fa] rounded-xl p-4 border border-gray-100">
                        <span class="text-[10px] font-bold text-gray-500 tracking-wider">SISA PLAFON SAAT INI</span>
                        <p class="font-mono font-bold text-[#0052cc] mt-1.5 text-[15px]">Rp 1.120.000</p>
                    </div>
                </div>

                <!-- Validation Box -->
                <div class="bg-[#f2fbf5] rounded-xl p-5 border border-green-100 relative overflow-hidden">
                    <!-- Blue accent line -->
                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0052cc]"></div>
                    
                    <div class="flex justify-between items-center mb-5">
                        <h3 class="font-bold text-gray-800 text-sm">Validasi Transaksi</h3>
                        <span class="bg-[#e6f4ea] text-[#137333] text-[10px] font-bold px-2.5 py-1 rounded-full border border-[#ceead6] flex items-center gap-1 tracking-wider">
                            <span class="material-symbols-rounded text-[14px]">check_circle</span>
                            SALDO CUKUP
                        </span>
                    </div>

                    <div class="flex justify-between text-[13px] text-gray-500 mb-2.5 font-medium">
                        <span>Sisa Plafon</span>
                        <span class="font-mono font-bold text-gray-700">Rp 1.120.000</span>
                    </div>
                    <div class="flex justify-between text-[13px] text-gray-500 mb-4 pb-4 border-b border-gray-200/80 border-dashed font-medium">
                        <span>Tagihan Baru</span>
                        <span class="font-mono font-bold text-red-500">- Rp 28.638</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="font-bold text-gray-900 text-[13px]">Estimasi Sisa Akhir</span>
                        <span class="font-mono font-bold text-[#137333] text-[15px]">Rp 1.091.362</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel (White) -->
        <div class="w-5/12 bg-white p-8 flex flex-col border-l border-gray-100">
            <h3 class="font-bold text-gray-700 mb-6 text-sm">Ringkasan Transaksi</h3>
            
            <!-- Items -->
            <div class="flex-1 overflow-y-auto space-y-5 pr-2">
                <!-- Item 1 -->
                <div class="flex justify-between items-start border-b border-gray-100 pb-4">
                    <div>
                        <h4 class="font-bold text-gray-800 text-[13px]">Indomie Goreng Spcl</h4>
                        <p class="text-[11px] font-bold text-[#0052cc] tracking-wide mt-1">3 unit x Rp 3.200</p>
                    </div>
                    <span class="font-mono font-bold text-gray-600 text-[13px]">Rp 9.600</span>
                </div>
                <!-- Item 2 -->
                <div class="flex justify-between items-start border-b border-gray-100 pb-4">
                    <div>
                        <h4 class="font-bold text-gray-800 text-[13px]">Oreo Vanilla 133g</h4>
                        <p class="text-[11px] font-bold text-[#0052cc] tracking-wide mt-1">1 unit x Rp 9.500</p>
                    </div>
                    <span class="font-mono font-bold text-gray-600 text-[13px]">Rp 8.200</span>
                </div>
                <!-- Item 3 -->
                <div class="flex justify-between items-start border-b border-gray-100 pb-4">
                    <div>
                        <h4 class="font-bold text-gray-800 text-[13px]">Teh Pucuk 350ml</h4>
                        <p class="text-[11px] font-bold text-[#0052cc] tracking-wide mt-1">2 unit x Rp 4.000</p>
                    </div>
                    <span class="font-mono font-bold text-gray-600 text-[13px]">Rp 8.000</span>
                </div>
            </div>

            <!-- Footer Section -->
            <div class="border-t border-gray-200 pt-6 mt-4">
                <div class="flex justify-between items-center mb-6">
                    <span class="font-medium text-gray-500 text-[13px]">Total Tagihan</span>
                    <span class="font-mono font-bold text-[#0052cc] text-[16px]">Rp 28.638</span>
                </div>

                <div class="flex flex-col gap-3">
                    <button class="w-full bg-[#004aad] hover:bg-blue-800 text-white font-bold py-3.5 rounded-xl shadow-sm transition-colors flex items-center justify-center gap-2 text-[13px]">
                        <span class="material-symbols-rounded text-[20px]">payments</span>
                        Konfirmasi Potong Plafon
                    </button>
                    <a href="{{ url('/pos') }}" class="w-full bg-[#f3f4f6] hover:bg-gray-200 text-gray-700 font-bold py-3.5 rounded-xl transition-colors flex items-center justify-center text-[13px] text-center">
                        Batal & Kembali ke Kasir
                    </a>
                </div>
                
                <div class="mt-5 flex items-center justify-center gap-1.5 text-gray-500">
                    <span class="material-symbols-rounded text-[14px]">verified_user</span>
                    <span class="text-[10px] font-bold tracking-wider uppercase">TRANSAKSI INI AKAN DICATAT SEBAGAI POTONG GAJI</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
