@extends('layouts.owner-admin', ['active' => 'suppliers'])

@section('title', 'Detail Master Supplier - Owner ILP POS')

@section('header')
<x-admin.header title="Detail Supplier" :showSearch="false">
    <x-slot:leftSlot>
        <div class="flex items-center gap-3">
            <h2 class="text-base font-black text-slate-800 tracking-tight">Admin Console</h2>
            <span class="text-slate-300">|</span>
            <nav class="hidden md:flex items-center gap-2 text-[13px]">
                <a href="{{ url('/owner') }}" class="font-medium text-slate-500 hover:text-slate-800 transition-colors">Dashboard</a>
                <span class="text-slate-300">/</span>
                <a href="{{ url('/owner/suppliers') }}" class="font-medium text-slate-500 hover:text-slate-800 transition-colors">Master Supplier</a>
                <span class="text-slate-300">/</span>
                <span class="font-bold text-[#0052cc]">Detail</span>
            </nav>
        </div>
    </x-slot:leftSlot>
</x-admin.header>
@endsection

@section('admin_content')
    @include('suppliers.partials._detail_content', [
        'supplierId' => $supplierId,
        'canManage' => false,
        'listUrl' => url('/owner/suppliers'),
    ])
@endsection
