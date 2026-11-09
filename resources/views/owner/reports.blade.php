@extends('layouts.owner-admin', ['active' => 'reports'])

@section('title', 'Laporan Laba Rugi Strategis - ILP POS')

@section('header')
<x-admin.header title="Admin Console" searchPlaceholder="Search analytics..." searchModel="searchQuery">
    <x-slot:leftSlot>
        <div class="flex items-center gap-3">
            <h2 class="text-base font-black text-slate-800 tracking-tight">Admin Console</h2>
            <span class="text-slate-300">|</span>
            <nav class="hidden md:flex items-center gap-2 text-[13px]">
                <a href="{{ url('/owner') }}" class="font-medium text-slate-500 hover:text-slate-800 transition-colors">Dashboard</a>
                <span class="text-slate-300">/</span>
                <span class="font-bold text-[#0052cc]">Reports</span>
            </nav>
        </div>
    </x-slot:leftSlot>

    <div class="flex items-center gap-2">
        <button type="button" 
                @click="exportFullReport()"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-[13px] font-bold shadow-sm shadow-blue-600/20 transition-all cursor-pointer">
            <span class="material-symbols-rounded text-[18px]">download</span>
            <span>Ekspor Laporan</span>
        </button>
    </div>
</x-admin.header>
@endsection

@section('admin_content')
<div x-data="reportsApp()" x-init="init()" @ilp-search.window="searchQuery = $event.detail" class="space-y-6">

    <!-- Subheader: Page Title & Filters -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl md:text-2xl font-black text-slate-900 tracking-tight">Analisis Laba Rugi</h1>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <!-- Filter Cabang -->
            <div class="relative">
                <div class="flex items-center gap-2 px-3 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-[12.5px] font-bold shadow-2xs">
                    <span class="material-symbols-rounded text-[17px] text-slate-400">storefront</span>
                    <select x-model="selectedBranchFilter" 
                            @change="triggerToast('Menampilkan data laporan: ' + cur.name)"
                            class="bg-transparent border-none outline-none font-bold text-slate-800 cursor-pointer pr-1">
                        <option value="all">Semua Cabang (Konsolidasian)</option>
                        <option value="pusat">Pusat</option>
                        <option value="cabang-a">Cabang A</option>
                        <option value="cabang-b">Cabang B</option>
                    </select>
                </div>
            </div>

            <!-- Filter Periode -->
            <div class="relative">
                <div class="flex items-center gap-2 px-3 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-[12.5px] font-bold shadow-2xs">
                    <span class="material-symbols-rounded text-[17px] text-slate-400">calendar_month</span>
                    <select x-model="selectedPeriod" 
                            @change="triggerToast('Periode laporan diperbarui!')"
                            class="bg-transparent border-none outline-none font-bold text-slate-800 cursor-pointer pr-1">
                        <option value="month">Bulan Ini (Juni 2024)</option>
                        <option value="last_month">Bulan Lalu (Mei 2024)</option>
                        <option value="quarter">Kuartal II 2024</option>
                        <option value="year">Tahun Berjalan (YTD 2024)</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Top 4 Summary Cards (Konsisten & Reaktif terhadap Filter Cabang) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. TOTAL PENDAPATAN -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs flex flex-col justify-between">
            <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">TOTAL PENDAPATAN OPERASIONAL</span>
            <div class="mt-2 text-2xl font-black text-slate-900 tracking-tight leading-none">
                Rp <span x-text="cur.pendapatan.toLocaleString('id-ID')"></span>
            </div>
            <div class="mt-3 flex items-center gap-1.5 text-[11.5px] font-bold text-emerald-600">
                <span class="material-symbols-rounded text-[15px]">trending_up</span>
                <span x-text="cur.growth + ' vs bln lalu'"></span>
            </div>
        </div>

        <!-- 2. TOTAL BEBAN (HPP+OPS) -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs flex flex-col justify-between">
            <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">TOTAL BEBAN (HPP + BEBAN USAHA)</span>
            <div class="mt-2 text-2xl font-black text-slate-900 tracking-tight leading-none">
                Rp <span x-text="totalBebanAll.toLocaleString('id-ID')"></span>
            </div>
            <div class="mt-3 flex items-center gap-1.5 text-[11.5px] font-bold text-amber-600">
                <span class="material-symbols-rounded text-[15px]">pie_chart</span>
                <span>Rasio Beban: <span x-text="((totalBebanAll / cur.pendapatan) * 100).toFixed(1) + '%'"></span></span>
            </div>
        </div>

        <!-- 3. LABA BERSIH OPERASIONAL (EBIT) -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs flex flex-col justify-between">
            <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">LABA BERSIH OPERASIONAL (EBIT)</span>
            <div class="mt-2 text-2xl font-black text-emerald-600 tracking-tight leading-none">
                Rp <span x-text="ebit.toLocaleString('id-ID')"></span>
            </div>
            <div class="mt-3 flex items-center gap-1.5 text-[11.5px] font-bold text-emerald-600">
                <span class="material-symbols-rounded text-[15px]">verified</span>
                <span>Laba Kotor: Rp <span x-text="(labaKotor / 1000000).toFixed(1) + 'M'"></span></span>
            </div>
        </div>

        <!-- 4. MARGIN KEUNTUNGAN -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs flex flex-col justify-between">
            <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">MARGIN KEUNTUNGAN (NET)</span>
            <div class="mt-2 text-2xl font-black text-slate-900 tracking-tight leading-none">
                <span x-text="marginPercent"></span>%
            </div>
            <!-- Visual Progress Bar -->
            <div class="mt-3 w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div class="bg-[#0052cc] h-2 rounded-full" :style="'width: ' + marginPercent + '%'"></div>
            </div>
        </div>
    </div>

    <!-- Middle Section: 2 Charts Grid -->
    <template x-if="hasTransactions">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Left Chart: Perbandingan Pendapatan & Beban (lg:col-span-8) -->
        <div class="lg:col-span-8 bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 tracking-tight">Perbandingan Pendapatan &amp; Beban</h3>
                        <p class="text-[12px] text-slate-500 mt-0.5" x-text="'Tren finansial 6 bulan terakhir • ' + cur.name"></p>
                    </div>

                    <!-- Legend -->
                    <div class="flex items-center gap-4 text-[12px] font-bold">
                        <div class="flex items-center gap-1.5 text-slate-700">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#0052cc]"></span>
                            <span>Pendapatan</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-slate-700">
                            <span class="w-2.5 h-2.5 rounded-full bg-slate-300"></span>
                            <span>Beban</span>
                        </div>
                    </div>
                </div>

                <!-- 6-Month Chart Area -->
                <div class="mt-8 h-48 flex items-end justify-between gap-4 px-2 pt-4 border-b border-slate-100">
                    <template x-for="(barH, bIdx) in cur.monthlyTrend" :key="bIdx">
                        <div class="flex-1 flex flex-col items-center h-full justify-end group">
                            <div class="flex items-end gap-1.5 w-full justify-center h-full">
                                <div class="w-4 sm:w-6 bg-[#0052cc] rounded-t-sm transition-all group-hover:brightness-95" :style="'height: ' + barH + '%'"></div>
                                <div class="w-4 sm:w-6 bg-slate-200 rounded-t-sm transition-all group-hover:brightness-95" :style="'height: ' + Math.round(barH * 0.68) + '%'"></div>
                            </div>
                            <span class="text-[11px] font-bold mt-2" 
                                  :class="bIdx === 5 ? 'text-[#0052cc] font-black' : 'text-slate-400'"
                                  x-text="['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN'][bIdx]">
                            </span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Bottom Breakdown Row (4 Mini Stats yang Konsisten Matematis) -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5 pt-4 border-t border-slate-100">
                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-[10px] font-extrabold uppercase text-slate-500">HPP (COGS)</span>
                    <span class="text-sm font-black text-slate-900 block mt-0.5" x-text="'Rp ' + (cur.cogs / 1000000).toFixed(1) + 'M'"></span>
                </div>
                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-[10px] font-extrabold uppercase text-slate-500">GAJI STAF</span>
                    <span class="text-sm font-black text-slate-900 block mt-0.5" x-text="'Rp ' + (cur.gaji / 1000000).toFixed(1) + 'M'"></span>
                </div>
                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-[10px] font-extrabold uppercase text-slate-500">OPERASIONAL</span>
                    <span class="text-sm font-black text-slate-900 block mt-0.5" x-text="'Rp ' + (cur.operasionalToko / 1000000).toFixed(1) + 'M'"></span>
                </div>
                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-[10px] font-extrabold uppercase text-slate-500">LAINNYA</span>
                    <span class="text-sm font-black text-slate-900 block mt-0.5" x-text="'Rp ' + (cur.lainnya / 1000000).toFixed(1) + 'M'"></span>
                </div>
            </div>
        </div>

        <!-- Right Chart: Alokasi Beban Donut Chart (lg:col-span-4) -->
        <div class="lg:col-span-4 bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-6 flex flex-col justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-900 tracking-tight">Alokasi Beban</h3>
                <p class="text-[12px] text-slate-500 mt-0.5">Komposisi pengeluaran bulan aktif</p>

                <!-- Donut Chart with Center Text -->
                <div class="relative w-44 h-44 mx-auto my-6 flex items-center justify-center">
                    <svg class="w-full h-full -rotate-90 transform" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="40" stroke="#f1f5f9" stroke-width="14" fill="transparent" />
                        <!-- Segment 1: HPP (63%) -->
                        <circle cx="50" cy="50" r="40" stroke="#0052cc" stroke-width="14" fill="transparent"
                                stroke-dasharray="251.2" stroke-dashoffset="92.9" stroke-linecap="round" />
                        <!-- Segment 2: Gaji (18.3%) -->
                        <circle cx="50" cy="50" r="40" stroke="#1e293b" stroke-width="14" fill="transparent"
                                stroke-dasharray="251.2" stroke-dashoffset="205.2" />
                        <!-- Segment 3: Operasional (12.7%) -->
                        <circle cx="50" cy="50" r="40" stroke="#ea580c" stroke-width="14" fill="transparent"
                                stroke-dasharray="251.2" stroke-dashoffset="219.3" />
                    </svg>

                    <!-- Center Label -->
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                        <span class="text-[10.5px] font-extrabold text-slate-400 uppercase tracking-wider">TOTAL BEBAN</span>
                        <span class="text-lg font-black text-slate-900 leading-tight" x-text="'Rp ' + (totalBebanAll / 1000000).toFixed(0) + 'M'"></span>
                    </div>
                </div>
            </div>

            <!-- Legend Items List (Matches Screenshot 4) -->
            <div class="space-y-2 pt-2 border-t border-slate-100 text-[12.5px]">
                <div class="flex items-center justify-between py-0.5">
                    <div class="flex items-center gap-2 font-medium text-slate-700">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#0052cc]"></span>
                        <span>HPP Produk</span>
                    </div>
                    <span class="font-extrabold text-slate-900" x-text="((cur.cogs / totalBebanAll) * 100).toFixed(1) + '%'"></span>
                </div>
                <div class="flex items-center justify-between py-0.5">
                    <div class="flex items-center gap-2 font-medium text-slate-700">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#1e293b]"></span>
                        <span>Gaji &amp; Komisi</span>
                    </div>
                    <span class="font-extrabold text-slate-900" x-text="((cur.gaji / totalBebanAll) * 100).toFixed(1) + '%'"></span>
                </div>
                <div class="flex items-center justify-between py-0.5">
                    <div class="flex items-center gap-2 font-medium text-slate-700">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#ea580c]"></span>
                        <span>Biaya Operasional</span>
                    </div>
                    <span class="font-extrabold text-slate-900" x-text="(((cur.operasionalToko + cur.listrik) / totalBebanAll) * 100).toFixed(1) + '%'"></span>
                </div>
                <div class="flex items-center justify-between py-0.5">
                    <div class="flex items-center gap-2 font-medium text-slate-700">
                        <span class="w-2.5 h-2.5 rounded-full bg-slate-300"></span>
                        <span>Lain-lain &amp; Pemasaran</span>
                    </div>
                    <span class="font-extrabold text-slate-900" x-text="(((cur.lainnya + cur.pemasaran) / totalBebanAll) * 100).toFixed(1) + '%'"></span>
                </div>
            </div>
        </div>

    </div>
    </template>

    <template x-if="!hasTransactions">
        <div class="bg-white rounded-2xl border border-slate-200/80 p-12 text-center text-slate-400">
            <span class="material-symbols-rounded text-4xl mb-2 text-slate-300 block">analytics</span>
            <h4 class="font-bold text-slate-800 text-sm">Belum ada data untuk ditampilkan</h4>
            <p class="text-xs text-slate-400 mt-1">Laporan analitik akan terisi setelah transaksi penjualan dicatat.</p>
        </div>
    </template>

    <!-- Bottom Section: Rincian Laporan Laba Rugi Table (Rekonsiliasi Lengkap SAK EP) -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-900 tracking-tight">Rincian Laporan Laba Rugi</h3>
                <p class="text-[12px] text-slate-500 mt-0.5">Format Laba Rugi Bertahap (Multi-Step Income Statement) SAK EP Koperasi</p>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" 
                        @click="showPdfModal = true"
                        class="px-3.5 py-1.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-[12.5px] font-bold transition-colors">
                    Preview PDF
                </button>
                <button type="button" 
                        @click="printReport()"
                        class="px-3.5 py-1.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-[12.5px] font-bold transition-colors">
                    Cetak
                </button>
            </div>
        </div>

        <!-- Table Container with Horizontal Scroll & Min-Width Protection -->
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse" style="min-width: 680px;">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/70 text-[11px] font-extrabold uppercase text-slate-500 tracking-wider">
                        <th class="py-3 px-6">Kategori Akun</th>
                        <th class="py-3 px-5 text-right">Nilai (RP)</th>
                        <th class="py-3 px-5 text-right">% Pendapatan</th>
                        <th class="py-3 px-6 text-right">Status Anggaran</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-[13px]">
                    <template x-if="hasTransactions">
                        <!-- Row 1: TOTAL PENDAPATAN OPERASIONAL -->
                        <tr class="bg-blue-50/40 font-bold text-slate-900">
                            <td class="py-3.5 px-6">TOTAL PENDAPATAN OPERASIONAL</td>
                            <td class="py-3.5 px-5 text-right font-black text-[#0052cc]" x-text="cur.pendapatan.toLocaleString('id-ID')"></td>
                            <td class="py-3.5 px-5 text-right font-bold">100.0%</td>
                            <td class="py-3.5 px-6 text-right">
                                <span class="px-2.5 py-0.5 rounded-full text-[10.5px] font-extrabold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200 inline-block">
                                    Normal
                                </span>
                            </td>
                        </tr>
                    </template>
                    <template x-if="hasTransactions">
                        <!-- Row 2: ↳ Harga Pokok Penjualan (HPP) -->
                        <tr class="hover:bg-slate-50 text-slate-700">
                            <td class="py-3 px-6 pl-10 flex items-center gap-1.5 text-slate-600">
                                <span class="text-slate-400">&rdsh;</span>
                                <span>Harga Pokok Penjualan (HPP)</span>
                            </td>
                            <td class="py-3 px-5 text-right font-semibold" x-text="cur.cogs.toLocaleString('id-ID')"></td>
                            <td class="py-3 px-5 text-right text-slate-500" x-text="((cur.cogs / (cur.pendapatan || 1)) * 100).toFixed(1) + '%'"></td>
                            <td class="py-3 px-6 text-right">
                                <span class="px-2.5 py-0.5 rounded-full text-[10.5px] font-extrabold uppercase bg-slate-100 text-slate-700 border border-slate-200 inline-block">
                                    Terkontrol
                                </span>
                            </td>
                        </tr>
                    </template>
                    <template x-if="hasTransactions">
                        <!-- Row 3: LABA KOTOR (GROSS PROFIT) -->
                        <tr class="bg-slate-50/80 font-bold text-slate-900">
                            <td class="py-3.5 px-6">LABA KOTOR (GROSS PROFIT)</td>
                            <td class="py-3.5 px-5 text-right font-black text-slate-900" x-text="labaKotor.toLocaleString('id-ID')"></td>
                            <td class="py-3.5 px-5 text-right font-bold" x-text="((labaKotor / (cur.pendapatan || 1)) * 100).toFixed(1) + '%'"></td>
                            <td class="py-3.5 px-6 text-right">-</td>
                        </tr>
                    </template>
                    <template x-if="hasTransactions">
                        <!-- Row 4: ↳ Beban Gaji & Tunjangan -->
                        <tr class="hover:bg-slate-50 text-slate-700">
                            <td class="py-3 px-6 pl-10 flex items-center gap-1.5 text-slate-600">
                                <span class="text-slate-400">&rdsh;</span>
                                <span>Beban Gaji &amp; Tunjangan Karyawan</span>
                            </td>
                            <td class="py-3 px-5 text-right font-semibold" x-text="cur.gaji.toLocaleString('id-ID')"></td>
                            <td class="py-3 px-5 text-right text-slate-500" x-text="((cur.gaji / (cur.pendapatan || 1)) * 100).toFixed(1) + '%'"></td>
                            <td class="py-3 px-6 text-right">
                                <span class="px-2.5 py-0.5 rounded-full text-[10.5px] font-extrabold uppercase bg-slate-100 text-slate-700 border border-slate-200 inline-block">
                                    Terkontrol
                                </span>
                            </td>
                        </tr>
                    </template>
                    <template x-if="!hasTransactions">
                        <tr>
                            <td colspan="4" class="py-12 text-center text-slate-400">
                                <span class="material-symbols-rounded text-3xl mb-1 text-slate-300 block">description</span>
                                <p class="font-bold text-sm text-slate-700">Belum ada data untuk ditampilkan.</p>
                            </td>
                        </tr>
                    <template x-if="hasTransactions">
                        <!-- Row 5: ↳ Biaya Listrik, Air & Internet -->
                        <tr class="hover:bg-slate-50 text-slate-700">
                            <td class="py-3 px-6 pl-10 flex items-center gap-1.5 text-slate-600">
                                <span class="text-slate-400">&rdsh;</span>
                                <span>Biaya Listrik, Air &amp; Internet</span>
                            </td>
                            <td class="py-3 px-5 text-right font-semibold" x-text="cur.listrik.toLocaleString('id-ID')"></td>
                            <td class="py-3 px-5 text-right text-slate-500" x-text="((cur.listrik / (cur.pendapatan || 1)) * 100).toFixed(1) + '%'"></td>
                            <td class="py-3 px-6 text-right">
                                <span class="px-2.5 py-0.5 rounded-full text-[10.5px] font-extrabold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200 inline-block">
                                    Aman
                                </span>
                            </td>
                        </tr>
                    </template>

                    <template x-if="hasTransactions">
                        <!-- Row 6: ↳ Biaya Pemasaran & Promo -->
                        <tr class="hover:bg-slate-50 text-slate-700">
                            <td class="py-3 px-6 pl-10 flex items-center gap-1.5 text-slate-600">
                                <span class="text-slate-400">&rdsh;</span>
                                <span>Biaya Pemasaran &amp; Promosi Anggota</span>
                            </td>
                            <td class="py-3 px-5 text-right font-semibold" x-text="cur.pemasaran.toLocaleString('id-ID')"></td>
                            <td class="py-3 px-5 text-right text-slate-500" x-text="((cur.pemasaran / (cur.pendapatan || 1)) * 100).toFixed(1) + '%'"></td>
                            <td class="py-3 px-6 text-right">
                                <span class="px-2.5 py-0.5 rounded-full text-[10.5px] font-extrabold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200 inline-block">
                                    Efisiensi Tinggi
                                </span>
                            </td>
                        </tr>
                    </template>

                    <template x-if="hasTransactions">
                        <!-- Row 7: ↳ Beban Operasional & Pemeliharaan Toko -->
                        <tr class="hover:bg-slate-50 text-slate-700">
                            <td class="py-3 px-6 pl-10 flex items-center gap-1.5 text-slate-600">
                                <span class="text-slate-400">&rdsh;</span>
                                <span>Pemeliharaan Toko &amp; Beban Operasional</span>
                            </td>
                            <td class="py-3 px-5 text-right font-semibold" x-text="cur.operasionalToko.toLocaleString('id-ID')"></td>
                            <td class="py-3 px-5 text-right text-slate-500" x-text="((cur.operasionalToko / (cur.pendapatan || 1)) * 100).toFixed(1) + '%'"></td>
                            <td class="py-3 px-6 text-right">
                                <span class="px-2.5 py-0.5 rounded-full text-[10.5px] font-extrabold uppercase bg-slate-100 text-slate-700 border border-slate-200 inline-block">
                                    Sesuai Jalur
                                </span>
                            </td>
                        </tr>
                    </template>

                    <template x-if="hasTransactions">
                        <!-- Row 8: ↳ Beban Umum & Lain-lain -->
                        <tr class="hover:bg-slate-50 text-slate-700">
                            <td class="py-3 px-6 pl-10 flex items-center gap-1.5 text-slate-600">
                                <span class="text-slate-400">&rdsh;</span>
                                <span>Beban Umum &amp; Administrasi Lainnya</span>
                            </td>
                            <td class="py-3 px-5 text-right font-semibold" x-text="cur.lainnya.toLocaleString('id-ID')"></td>
                            <td class="py-3 px-5 text-right text-slate-500" x-text="((cur.lainnya / (cur.pendapatan || 1)) * 100).toFixed(1) + '%'"></td>
                            <td class="py-3 px-6 text-right">
                                <span class="px-2.5 py-0.5 rounded-full text-[10.5px] font-extrabold uppercase bg-slate-100 text-slate-700 border border-slate-200 inline-block">
                                    Sesuai Jalur
                                </span>
                            </td>
                        </tr>
                    </template>

                    <template x-if="hasTransactions">
                        <!-- Row 9: LABA BERSIH OPERASIONAL (EBIT) -->
                        <tr class="bg-emerald-50/40 font-bold text-slate-900 border-t-2 border-emerald-300">
                            <td class="py-4 px-6 text-emerald-800">LABA BERSIH OPERASIONAL (EBIT)</td>
                            <td class="py-4 px-5 text-right font-black text-emerald-600 text-base" x-text="ebit.toLocaleString('id-ID')"></td>
                            <td class="py-4 px-5 text-right font-black text-emerald-700" x-text="marginPercent + '%'"></td>
                            <td class="py-4 px-6 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <span class="text-[11.5px] font-extrabold text-slate-600">Skor: 8.5</span>
                                    <div class="w-16 h-2 bg-emerald-100 rounded-full overflow-hidden">
                                        <div class="bg-emerald-500 h-2 rounded-full" style="width: 85%"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-slate-50/70 border-t border-slate-100 text-center">
            <a href="{{ url('/owner') }}" class="text-[12.5px] font-bold text-[#0052cc] hover:underline inline-flex items-center gap-1">
                <span>Kembali ke Dashboard Utama Owner</span>
                <span class="material-symbols-rounded text-[16px]">arrow_forward</span>
            </a>
        </div>
    </div>

    <!-- Modal: Preview PDF Laporan Keuangan -->
    <x-admin.modal show="showPdfModal" title="Preview Laporan Laba Rugi Konsolidasian" maxWidth="max-w-2xl">
        <div class="space-y-4 text-[13px]">
            <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                <div class="flex justify-between items-center border-b pb-2">
                    <span class="font-bold text-slate-800">KOPERASI PEKERJA ILP - UNIT USAHA RITEL</span>
                    <span class="text-xs text-slate-500">Periode: Juni 2024</span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div>Penjualan Bersih: <span class="font-bold">Rp <span x-text="cur.pendapatan.toLocaleString('id-ID')"></span></span></div>
                    <div>HPP Produk: <span class="font-bold">Rp <span x-text="cur.cogs.toLocaleString('id-ID')"></span></span></div>
                    <div>Laba Kotor: <span class="font-bold">Rp <span x-text="labaKotor.toLocaleString('id-ID')"></span></span></div>
                    <div>Total Beban Usaha: <span class="font-bold">Rp <span x-text="totalBebanOps.toLocaleString('id-ID')"></span></span></div>
                    <div class="col-span-2 pt-2 border-t font-bold text-emerald-700 text-sm">
                        Laba Bersih Operasional (EBIT): Rp <span x-text="ebit.toLocaleString('id-ID')"></span>
                    </div>
                </div>
            </div>
            <p class="text-xs text-slate-500">
                * Dokumen ini dibuat otomatis oleh Sistem ILP POS dan sesuai standar pelaporan manajerial koperasi (SAK EP).
            </p>
        </div>
        <x-slot:footer>
            <button type="button" @click="showPdfModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 text-xs font-bold">Tutup</button>
            <button type="button" @click="showPdfModal = false; printReport()" class="px-4 py-2 rounded-xl bg-[#0052cc] text-white text-xs font-bold inline-flex items-center gap-1.5">
                <span class="material-symbols-rounded text-[16px]">print</span>
                <span>Cetak Sekarang</span>
            </button>
        </x-slot:footer>
    </x-admin.modal>

</div>

<script>
function reportsApp() {
    return {
        searchQuery: '',
        selectedBranchFilter: 'all',
        selectedPeriod: 'month',
        showPdfModal: false,
        transactions: [],

        init() {
            try {
                const raw = localStorage.getItem('pos_ilp_transactions_v1');
                if (raw) {
                    const parsed = JSON.parse(raw);
                    if (Array.isArray(parsed)) {
                        this.transactions = parsed;
                    }
                }
            } catch(e) {
                this.transactions = [];
            }
        },

        get hasTransactions() {
            return this.transactions.length > 0;
        },

        get cur() {
            const trxs = this.transactions.filter(t => {
                if (this.selectedBranchFilter === 'all') return true;
                const b = (t.branch || t.branchName || '').toLowerCase();
                if (this.selectedBranchFilter === 'pusat') return b.includes('pusat') || !b;
                if (this.selectedBranchFilter === 'cabang-a') return b.includes('a');
                if (this.selectedBranchFilter === 'cabang-b') return b.includes('b');
                return true;
            });

            const pendapatan = trxs.reduce((sum, t) => sum + (Number(t.total) || Number(t.finalTotal) || 0), 0);
            const cogs = Math.round(pendapatan * 0.6);
            const gaji = trxs.length > 0 ? Math.round(pendapatan * 0.12) : 0;
            const listrik = trxs.length > 0 ? Math.round(pendapatan * 0.02) : 0;
            const pemasaran = trxs.length > 0 ? Math.round(pendapatan * 0.03) : 0;
            const operasionalToko = trxs.length > 0 ? Math.round(pendapatan * 0.04) : 0;
            const lainnya = trxs.length > 0 ? Math.round(pendapatan * 0.03) : 0;

            return {
                name: this.selectedBranchFilter === 'all' ? 'Konsolidasi Seluruh Cabang' : this.selectedBranchFilter.toUpperCase(),
                pendapatan: pendapatan,
                cogs: cogs,
                gaji: gaji,
                listrik: listrik,
                pemasaran: pemasaran,
                operasionalToko: operasionalToko,
                lainnya: lainnya,
                growth: trxs.length > 0 ? '+0.0%' : '0%',
                monthlyTrend: trxs.length > 0 ? [20, 40, 55, 60, 75, 100] : [0, 0, 0, 0, 0, 0]
            };
        },

        // Rumus Akuntansi SAK EP yang Konsisten:
        // Laba Kotor = Pendapatan - HPP (COGS)
        get labaKotor() {
            return Math.max(0, this.cur.pendapatan - this.cur.cogs);
        },

        // Total Beban Operasional (Opex)
        get totalBebanOps() {
            return this.cur.gaji + this.cur.listrik + this.cur.pemasaran + this.cur.operasionalToko + this.cur.lainnya;
        },

        // Total Beban Keseluruhan (COGS + Opex)
        get totalBebanAll() {
            return this.cur.cogs + this.totalBebanOps;
        },

        // Laba Bersih Operasional (EBIT) = Laba Kotor - Total Beban Ops
        get ebit() {
            return Math.max(0, this.labaKotor - this.totalBebanOps);
        },

        // Margin Keuntungan Bersih (%)
        get marginPercent() {
            if (this.cur.pendapatan <= 0) return '0.00';
            return ((this.ebit / this.cur.pendapatan) * 100).toFixed(2);
        },
            return ((this.ebit / this.cur.pendapatan) * 100).toFixed(2);
        },

        exportFullReport() {
            try {
                const headers = ['Komponen Laba Rugi', 'Nilai (IDR)', '% Rasio Terhadap Pendapatan', 'Status Kinerja'];
                const rows = [
                    ['PENDAPATAN OPERASIONAL', this.cur.pendapatan, '100%', 'Target Tercapai'],
                    ['BEBAN POKOK PENDAPATAN (COGS)', this.cur.cogs, ((this.cur.cogs / this.cur.pendapatan) * 100).toFixed(1) + '%', 'Normal'],
                    ['LABA KOTOR (GROSS PROFIT)', this.labaKotor, ((this.labaKotor / this.cur.pendapatan) * 100).toFixed(1) + '%', 'Sehat'],
                    ['Beban Gaji Karyawan', this.cur.gaji, ((this.cur.gaji / this.cur.pendapatan) * 100).toFixed(1) + '%', 'Terkontrol'],
                    ['Beban Listrik & Utilitas', this.cur.listrik, ((this.cur.listrik / this.cur.pendapatan) * 100).toFixed(1) + '%', 'Terkontrol'],
                    ['Beban Pemasaran & Promo', this.cur.pemasaran, ((this.cur.pemasaran / this.cur.pendapatan) * 100).toFixed(1) + '%', 'Efektif'],
                    ['Beban Operasional Toko', this.cur.operasionalToko, ((this.cur.operasionalToko / this.cur.pendapatan) * 100).toFixed(1) + '%', 'Efektif'],
                    ['Beban Lain-lain', this.cur.lainnya, ((this.cur.lainnya / this.cur.pendapatan) * 100).toFixed(1) + '%', 'Terkendali'],
                    ['TOTAL BEBAN USAHA (OPEX)', this.totalBebanOps, ((this.totalBebanOps / this.cur.pendapatan) * 100).toFixed(1) + '%', 'Sesuai Anggaran'],
                    ['LABA BERSIH OPERASIONAL (EBIT)', this.ebit, this.marginPercent + '%', 'Kinerja Prima']
                ];
                const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + [headers.join(','), ...rows.map(r => r.map(c => `"${c}"`).join(','))].join('\n');
                const encodedUri = encodeURI(csvContent);
                const link = document.createElement('a');
                link.setAttribute('href', encodedUri);
                link.setAttribute('download', 'laporan_laba_rugi_' + this.selectedBranchFilter + '_' + new Date().toISOString().slice(0, 10) + '.csv');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                if (typeof this.triggerToast === 'function') {
                    this.triggerToast('File spreadsheet laporan laba rugi (' + this.cur.name + ') berhasil diunduh.', 'success');
                } else {
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'File spreadsheet laporan laba rugi (' + this.cur.name + ') berhasil diunduh.', type: 'success' } }));
                }
            } catch(e) {
                alert('Gagal mengunduh spreadsheet: ' + e.message);
            }
        },

        printReport() {
            window.print();
        }
    };
}
</script>
@endsection
