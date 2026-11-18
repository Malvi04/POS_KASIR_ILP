@extends('layouts.owner-admin', ['active' => 'suppliers'])

@section('title', 'Pemantauan Master Supplier - Owner ILP POS')

@section('header')
<x-admin.header title="Master Supplier" :showSearch="false">
    <x-slot:leftSlot>
        <div class="flex items-center gap-3">
            <h2 class="text-base font-black text-slate-800 tracking-tight">Admin Console</h2>
            <span class="text-slate-300">|</span>
            <nav class="hidden md:flex items-center gap-2 text-[13px]">
                <a href="{{ url('/owner') }}" class="font-medium text-slate-500 hover:text-slate-800 transition-colors">Dashboard</a>
                <span class="text-slate-300">/</span>
                <span class="font-bold text-[#0052cc]">Master Supplier</span>
            </nav>
        </div>
    </x-slot:leftSlot>
</x-admin.header>
@endsection

@section('admin_content')
    @include('suppliers.partials._list_content', [
        'canManage' => false,
        'detailBaseUrl' => url('/owner/suppliers'),
    ])
@endsection
