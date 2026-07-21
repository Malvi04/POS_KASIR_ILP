@extends('layouts.app')

@section('title', 'Dashboard - ILP POS')

@section('content')
<div class="flex flex-col h-screen w-full bg-[#f4f6f9] font-sans">
    <!-- Header -->
    <header class="h-16 px-8 flex items-center justify-between shrink-0 pt-2">
        <h1 class="text-[22px] font-bold text-[#6a87c1] tracking-tight ml-2">ILP POS</h1>
        <div class="flex items-center gap-5 text-[#a0abbd] mr-2">
            <button class="hover:text-gray-600 transition-colors flex items-center">
                <span class="material-symbols-rounded" style="font-size: 24px;">wifi</span>
            </button>
            <button class="hover:text-gray-600 transition-colors flex items-center">
                <span class="material-symbols-rounded" style="font-size: 24px;">notifications</span>
            </button>
        </div>
    </header>

    <!-- Main Content Area -->
    <div class="flex flex-1 overflow-hidden mt-4">
        <!-- Sidebar -->
        <aside class="w-64 shrink-0 flex flex-col px-6">
            <nav class="flex flex-col gap-2">
                <a href="#" class="flex items-center gap-4 px-4 py-3 bg-[#e2ecf8] text-[#6a87c1] rounded-xl font-semibold text-[15px]">
                    <span class="material-symbols-rounded" style="font-size: 22px;">dashboard</span>
                    Dashboard
                </a>
                <a href="#" class="flex items-center gap-4 px-4 py-3 text-[#a0abbd] hover:bg-gray-200/50 rounded-xl font-medium text-[15px] transition-colors">
                    <span class="material-symbols-rounded" style="font-size: 22px;">inventory_2</span>
                    Inventory
                </a>
                <a href="#" class="flex items-center gap-4 px-4 py-3 text-[#a0abbd] hover:bg-gray-200/50 rounded-xl font-medium text-[15px] transition-colors">
                    <span class="material-symbols-rounded" style="font-size: 22px;">receipt_long</span>
                    Sales
                </a>
            </nav>
        </aside>

        <!-- Main Workspace -->
        <main class="flex-1 flex px-2 gap-10 overflow-hidden">
            <!-- Left Area (Skeleton Content) -->
            <div class="flex-1 pt-1 ml-4">
                <div class="flex flex-col gap-5">
                    <div class="h-4 bg-[#e5e9f0] rounded-sm w-[95%]"></div>
                    <div class="h-4 bg-[#e5e9f0] rounded-sm w-[75%]"></div>
                    <div class="h-4 bg-[#e5e9f0] rounded-sm w-[50%]"></div>
                </div>
            </div>

            <!-- Right Area (Order Summary Card) -->
            <div class="w-[360px] shrink-0 mr-8">
                <div class="border-[1.5px] border-[#e5e9f0] rounded-xl p-6 bg-transparent">
                    <h2 class="text-[14px] text-[#a0abbd] font-medium mb-8">Ringkasan Pesanan</h2>
                    
                    <div class="flex justify-between items-center mb-6">
                        <span class="text-[#a0abbd] text-[15px]">Subtotal</span>
                        <span class="text-[#5b6579] font-medium text-[15px]">Rp 245.000</span>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-[#6a87c1] font-bold text-[16px]">Total</span>
                        <span class="text-[#6a87c1] font-bold text-[16px]">Rp 245.000</span>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
@endsection
