@extends('layouts.app')

@section('title', ($title ?? 'Menu Kasir') . ' - ILP POS')

@section('content')
<div class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">
    <!-- Sidebar -->
    <x-cashier-sidebar :active="$active ?? ''" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        <!-- Top Header -->
        <x-cashier-header :title="$title ?? 'Menu Kasir'">
            <div class="flex items-center gap-2 ml-2">
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200 flex items-center gap-1">
                    <span class="material-symbols-rounded text-[14px]">schedule</span>
                    <span>Tahap: {{ $badge ?? 'Modul Berikutnya' }}</span>
                </span>
            </div>
        </x-cashier-header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-8 flex flex-col">
            <div class="max-w-[1200px] w-full mx-auto flex flex-col flex-1">

                <!-- Breadcrumbs -->
                <div class="flex items-center gap-2 text-[11px] font-bold text-gray-400 tracking-wider mb-4 uppercase">
                    <span>ROLE KASIR</span>
                    <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                    <span>{{ $category ?? 'MODUL' }}</span>
                    <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                    <span class="text-[#0052cc]">{{ $title ?? 'HALAMAN' }}</span>
                </div>

                <!-- Main Placeholder Card -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-xs p-8 flex flex-col items-center justify-center text-center my-auto min-h-[460px]">
                    <div class="w-20 h-20 rounded-2xl bg-blue-50 border border-blue-100 text-[#0052cc] flex items-center justify-center mb-5 shadow-xs">
                        <span class="material-symbols-rounded text-[40px]">{{ $icon ?? 'construction' }}</span>
                    </div>

                    <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-[#eef2ff] text-[#0052cc] mb-3">
                        Navigasi & Routing Terhubung
                    </span>

                    <h2 class="text-2xl font-black text-gray-900 tracking-tight">{{ $title ?? 'Fitur Kasir' }}</h2>
                    <p class="text-sm text-gray-500 max-w-xl mt-2 leading-relaxed font-medium">
                        {{ $description ?? 'Halaman ini telah terdaftar di routing aplikasi dan terintegrasi penuh ke dalam navigasi Sidebar Kasir.' }}
                    </p>

                    <!-- Feature Spec Box -->
                    <div class="mt-6 p-4 rounded-xl bg-slate-50 border border-gray-200/80 max-w-lg w-full text-left">
                        <div class="flex items-center gap-2 text-xs font-bold text-gray-700 mb-2">
                            <span class="material-symbols-rounded text-[18px] text-[#0052cc]">info</span>
                            <span>Rencana Implementasi:</span>
                        </div>
                        <ul class="text-[12px] text-gray-600 space-y-1.5 pl-6 list-disc font-medium">
                            <li>Route resmi: <code class="text-[#0052cc] bg-blue-50 px-1 py-0.5 rounded font-mono">{{ request()->path() }}</code></li>
                            <li>Status: Menunggu eksekusi {{ $badge ?? 'modul lanjutan' }} sesuai approval.</li>
                            <li>Tampilan UI dan data akan mempertahankan styling standar ILP POS.</li>
                        </ul>
                    </div>

                    <!-- Quick Navigation -->
                    <div class="flex items-center gap-3 mt-8">
                        <a href="{{ url('/pos') }}" class="px-5 py-2.5 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs flex items-center gap-2 shadow-sm transition-colors">
                            <span class="material-symbols-rounded text-[18px]">point_of_sale</span>
                            <span>Kembali ke POS</span>
                        </a>
                        <a href="{{ url('/kasir/sesi') }}" class="px-5 py-2.5 rounded-xl bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 font-bold text-xs flex items-center gap-2 transition-colors">
                            <span class="material-symbols-rounded text-[18px]">swap_horiz</span>
                            <span>Buka Sesi Kasir</span>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>
@endsection
