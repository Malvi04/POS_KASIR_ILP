@extends('layouts.app')

@props([
    'title' => 'ILP POS - Owner/Admin Console',
    'active' => 'dashboard'
])

@section('title', $title)

@section('content')
<div class="flex h-screen w-full bg-[#f8fafc] font-sans text-slate-800 overflow-hidden select-none"
     x-data="{
         toastMessage: '',
         toastType: 'info',
         showToast: false,
         triggerToast(msg, type = 'info') {
             this.toastMessage = msg;
             this.toastType = type;
             this.showToast = true;
             setTimeout(() => { this.showToast = false; }, 3200);
         }
     }"
     x-init="window.triggerToast = (msg, type = 'info') => triggerToast(msg, type)">

    <!-- Shared Sidebar -->
    <x-admin.sidebar :active="$active" />

    <!-- Main Workspace Area -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden min-w-0 bg-[#f8fafc]">
        @yield('header')

        <!-- Scrollable Page Content -->
        <main class="flex-1 overflow-y-auto min-w-0 p-6 md:p-8 bg-[#f8fafc] relative">
            <div class="max-w-[1520px] mx-auto space-y-6">
                @yield('admin_content')
            </div>

            <!-- Global Toast Notification Banner -->
            <div x-cloak 
                 x-show="showToast" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-3"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 translate-y-3"
                 class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-2xl shadow-xl bg-slate-900 text-white text-[13px] font-medium border border-slate-700/60 max-w-md">
                <span class="material-symbols-rounded text-[20px]" 
                      :class="toastType === 'success' ? 'text-emerald-400' : (toastType === 'error' ? 'text-rose-400' : 'text-blue-400')">
                    info
                </span>
                <span x-text="toastMessage" class="flex-1"></span>
                <button type="button" @click="showToast = false" class="text-slate-400 hover:text-white">
                    <span class="material-symbols-rounded text-[18px]">close</span>
                </button>
            </div>
        </main>
    </div>
</div>
@endsection
