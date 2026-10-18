@extends('layouts.app')

@section('title', 'Pilih Shift - ILP POS')

@section('content')
<div class="bg-[#f8f9fa] min-h-screen flex flex-col text-gray-800 w-full font-sans" x-data="{ selectedShift: 'kasir_1' }">

    <!-- Topbar -->
    <header class="h-14 bg-[#f8f9fa] border-b border-gray-200 flex items-center justify-between px-6 shrink-0">
        <div class="flex items-center gap-4">
            <span class="material-symbols-rounded text-gray-400 text-[20px]">store</span>
            <span class="font-bold text-gray-400 tracking-wider text-sm">ILP POS</span>
            <div class="h-4 w-px bg-gray-300"></div>
            <span class="font-mono text-gray-400 text-sm tracking-wider">TERMINAL #04</span>
        </div>
        <div class="flex items-center gap-6 text-sm">
            <div class="flex flex-col text-right leading-tight">
                <span class="text-gray-500 font-medium text-xs">Selasa, 21 Juli 2026</span>
                <span class="text-[#0052cc] font-mono font-bold">16.14.15</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="material-symbols-rounded text-gray-500 text-[20px]">schedule</span>
                <span class="material-symbols-rounded text-green-500 text-[20px]">wifi</span>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 flex p-6 gap-6 w-full max-w-[1200px] mx-auto mt-4">
        
        <!-- Left Column -->
        <div class="w-[340px] shrink-0 flex flex-col gap-6">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 flex flex-col gap-6">
                <!-- Identity Header -->
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center shrink-0">
                        <span class="material-symbols-rounded text-[#0052cc] text-3xl">badge</span>
                    </div>
                    <div>
                        <h2 class="text-[#0052cc] font-bold text-base">Identifikasi Peran</h2>
                        <p class="text-gray-500 text-[13px] leading-tight mt-1">Sistem menunggu otentikasi kasir</p>
                    </div>
                </div>

                <!-- Info Cards -->
                <div class="flex flex-col gap-3 mt-2">
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-500 tracking-wider">LEVEL AKSES</span>
                        <span class="bg-[#004aad] text-white text-[10px] font-bold px-3 py-1.5 rounded-full tracking-wider">KASIR UTAMA</span>
                    </div>
                    
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-500 tracking-wider">LOKASI</span>
                        <span class="font-bold text-gray-800 text-sm">Minimarket - Sektor A</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="flex-1 flex flex-col gap-6">
            <!-- Welcome Banner -->
            <div class="bg-[#0052cc] rounded-2xl h-44 relative overflow-hidden flex flex-col justify-center px-8 shadow-sm">
                <!-- Background overlay effect simulating supermarket shelves -->
                <div class="absolute inset-0 opacity-20 mix-blend-overlay" style="background-image: url('https://images.unsplash.com/photo-1578916171728-46686eac8d58?q=80&w=1000&auto=format&fit=crop'); background-size: cover; background-position: center;"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-[#0052cc] via-[#0052cc]/80 to-transparent"></div>
                
                <div class="relative z-10 text-white">
                    <h2 class="text-lg font-medium mb-1">Selamat Datang</h2>
                    <p class="text-blue-100 text-sm">Silahkan pilih shift masuk yang sesuai dengan jadwal anda</p>
                </div>
            </div>

            <!-- Shift Selection -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 flex-1 p-8 flex flex-col">
                <h3 class="text-gray-700 font-medium text-sm mb-6">Pilih Kasir</h3>

                <div class="flex flex-col gap-3 overflow-y-auto pr-2" style="max-height: 380px;">
                    <template x-for="i in 12" :key="i">
                        <label class="relative flex items-center p-4 rounded-lg border-[1.5px] cursor-pointer transition-all"
                               :class="selectedShift === 'kasir_' + i ? 'border-[#0052cc] bg-[#eef2ff]' : 'border-gray-200 hover:border-blue-300 bg-white'">
                            <input type="radio" name="kasir" :value="'kasir_' + i" x-model="selectedShift" class="hidden">
                            <span class="text-[15px] font-bold text-gray-700" :class="selectedShift === 'kasir_' + i ? 'text-gray-900' : ''" x-text="'Kasir ' + i"></span>
                        </label>
                    </template>
                </div>

                <!-- Action Buttons -->
                <div class="mt-8 flex justify-center w-full">
                    <a href="{{ url('/pos') }}" class="bg-[#16a34a] hover:bg-[#15803d] text-white font-bold py-2.5 px-8 rounded-lg transition-colors flex items-center justify-center gap-2 text-sm shadow-sm">
                        <span class="material-symbols-rounded text-[20px]">login</span>
                        Masuk
                    </a>
                </div>
            </div>
        </div>
    </main>
</div>
@endsection
