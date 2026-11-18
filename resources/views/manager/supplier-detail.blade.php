@extends('layouts.app')

@section('title', 'Detail Supplier - Manager ILP POS')

@section('content')
@php
    $authUser = auth()->user();
    $canManage = $authUser ? !$authUser->isOwnerOrSuperadmin() : true;
@endphp

<div x-data="{
        toastMessage: '',
        toastType: 'success',
        showToast: false,
        toastTimer: null,
        triggerToast(msg, type = 'success') {
            this.toastMessage = msg;
            this.toastType = type;
            this.showToast = true;
            if (this.toastTimer) clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => { this.showToast = false; }, 3800);
        }
     }"
     x-init="window.triggerToast = (msg, type = 'success') => triggerToast(msg, type)"
     class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">

    <!-- Sidebar Manager -->
    <x-manager-sidebar :active="'suppliers'" />

    <!-- Main Workspace -->
    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden relative">

        <!-- Top Header Bar -->
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 sm:px-6 z-10 shrink-0 select-none">
            <div class="flex items-center gap-2 min-w-0">
                <span class="text-xs font-bold text-gray-400 uppercase tracking-wider hidden sm:inline">Persediaan</span>
                <span class="material-symbols-rounded text-gray-400 text-sm hidden sm:inline">chevron_right</span>
                <a href="{{ url('/suppliers') }}" class="text-xs font-bold text-gray-500 hover:text-[#0052cc] hidden sm:inline">Master Supplier</a>
                <span class="material-symbols-rounded text-gray-400 text-sm hidden sm:inline">chevron_right</span>
                <h1 class="text-sm sm:text-base font-bold text-gray-800 truncate">Detail Supplier</h1>
            </div>

            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <x-notification-bell role="MANAGER" />
                <button type="button"
                        @click="$dispatch('refresh-supplier-detail')"
                        class="p-2 rounded-xl text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors cursor-pointer"
                        title="Sinkronisasi Data">
                    <span class="material-symbols-rounded text-[20px]">sync</span>
                </button>
                <x-realtime-network-badge />
            </div>
        </header>

        <!-- Scrollable Body -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 md:p-8 bg-[#f8f9fa]">
            <div class="max-w-[1440px] mx-auto">
                @include('suppliers.partials._detail_content', [
                    'supplierId' => $supplierId,
                    'canManage' => $canManage,
                    'listUrl' => url('/suppliers'),
                ])
            </div>
        </div>
    </main>

    <!-- TOAST NOTIFICATION -->
    <div x-show="showToast"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-4"
         style="display: none;"
         class="fixed bottom-8 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 px-5 py-3 rounded-2xl shadow-xl text-xs font-bold text-white border"
         :class="{
             'bg-emerald-600 border-emerald-500': toastType === 'success',
             'bg-rose-600 border-rose-500': toastType === 'error',
             'bg-amber-600 border-amber-500': toastType === 'warning',
             'bg-blue-600 border-blue-500': toastType === 'info'
         }">
        <span class="material-symbols-rounded text-[20px]"
              x-text="toastType === 'success' ? 'check_circle' : (toastType === 'error' ? 'error' : (toastType === 'warning' ? 'warning' : 'info'))"></span>
        <span x-text="toastMessage"></span>
    </div>
</div>
@endsection
