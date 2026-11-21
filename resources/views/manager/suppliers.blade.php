@extends('layouts.app')

@section('title', 'Master Supplier - Manager ILP POS')

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
        <header class="h-20 bg-[#f8f9fa] border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-3">
                <h1 class="text-[20px] font-bold text-gray-900">Master Supplier</h1>
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200 flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[14px]">store</span>
                    <span>Pemasok &amp; Pengadaan</span>
                </span>
            </div>

            <div class="flex items-center gap-4 text-gray-500">
                <x-notification-bell role="MANAGER" />
                <button type="button"
                        @click="$dispatch('refresh-suppliers')"
                        class="hover:text-gray-900 transition-colors cursor-pointer"
                        title="Sinkronkan Data">
                    <span class="material-symbols-rounded text-[24px]" :class="$store.network?.isChecking ? 'animate-spin text-[#0052cc]' : ''">autorenew</span>
                </button>
                <div class="h-6 w-px bg-gray-300"></div>
                <x-realtime-network-badge />
            </div>
        </header>

        <!-- Scrollable Body -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 md:p-8 bg-[#f8f9fa]">
            <div class="max-w-[1440px] mx-auto">
                @include('suppliers.partials._list_content', [
                    'canManage' => $canManage,
                    'detailBaseUrl' => url('/suppliers'),
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
