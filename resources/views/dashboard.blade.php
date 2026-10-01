@extends('layouts.app')

@section('title', 'Dashboard - ILP POS')

@section('content')
<div class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800">
    <!-- Sidebar -->
    <x-cashier-sidebar active="dashboard" />

    <!-- Main Workspace -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        <x-cashier-header title="Dashboard" />
        <div class="flex flex-1 mt-8">
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
        </div>
    </main>
</div>
@endsection
