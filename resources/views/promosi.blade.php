@extends('layouts.app')

@section('title', 'Manajemen Promo & Diskon - ILP POS')

@section('content')
<div class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">
    
    <!-- Sidebar -->
    <x-manager-sidebar active="promosi" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        
        <!-- Top Header -->
        <header class="h-[68px] bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-8">
                <span class="text-[18px] font-black text-[#0052cc] tracking-tight">ILP POS</span>
                <nav class="flex items-center gap-6 text-[13.5px] font-medium text-gray-600">
                    <a href="#" class="hover:text-gray-900 transition-colors">Bulk Import</a>
                    <a href="#" class="hover:text-gray-900 transition-colors">Update Prices</a>
                    <a href="#" class="hover:text-gray-900 transition-colors">Export Logs</a>
                </nav>
            </div>

            <div class="flex items-center gap-4">
                <button class="bg-[#0052cc] hover:bg-[#0043a8] text-white px-4 py-2 rounded-lg font-bold text-[12.5px] shadow-sm transition-colors">
                    Tambah Promo Baru
                </button>
                <button class="text-gray-500 hover:text-gray-800 transition-colors flex items-center justify-center p-1">
                    <span class="material-symbols-rounded text-[22px]">notifications</span>
                </button>
                <div class="flex items-center gap-2 bg-gray-100 border border-gray-200 rounded-full pl-3.5 pr-1.5 py-1">
                    <span class="text-[12.5px] font-bold text-gray-800">Admin</span>
                    <div class="w-6 h-6 rounded-full bg-gray-800 text-white flex items-center justify-center">
                        <span class="material-symbols-rounded text-[16px]">person</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-8">
            <div class="max-w-[1400px] mx-auto">
                
                <!-- Page Title -->
                <div class="mb-6">
                    <h1 class="text-[26px] font-black text-gray-900 tracking-tight leading-tight">Manajemen Promo & Diskon</h1>
                    <p class="text-[14px] text-gray-500 mt-1">Optimalkan penjualan dengan strategi harga yang cerdas.</p>
                </div>

                <!-- 4 Summary Cards -->
                <div class="grid grid-cols-4 gap-5 mb-7">
                    <!-- Card 1: PROMO AKTIF -->
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm flex flex-col justify-between min-h-[132px]">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">PROMO AKTIF</span>
                            <span class="material-symbols-rounded text-green-600 text-[20px]">bolt</span>
                        </div>
                        <div class="text-[30px] font-black text-gray-900 font-mono leading-none my-2">12</div>
                        <div class="flex items-center gap-1 text-[12px] font-semibold text-green-600">
                            <span class="material-symbols-rounded text-[15px]">trending_up</span>
                            <span>+3 minggu ini</span>
                        </div>
                    </div>

                    <!-- Card 2: MENDATANG -->
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm flex flex-col justify-between min-h-[132px]">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">MENDATANG</span>
                            <span class="material-symbols-rounded text-[#0052cc] text-[19px]">calendar_today</span>
                        </div>
                        <div class="text-[30px] font-black text-gray-900 font-mono leading-none my-2">05</div>
                        <div class="text-[12px] font-medium text-gray-500">
                            Terjadwal mulai besok
                        </div>
                    </div>

                    <!-- Card 3: KEDALUWARSA -->
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm flex flex-col justify-between min-h-[132px]">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">KEDALUWARSA</span>
                            <span class="material-symbols-rounded text-red-600 text-[20px]">history</span>
                        </div>
                        <div class="text-[30px] font-black text-gray-900 font-mono leading-none my-2">28</div>
                        <div class="text-[12px] font-medium text-gray-500">
                            Dalam 30 hari terakhir
                        </div>
                    </div>

                    <!-- Card 4: AUTO-DISCOUNT -->
                    <div class="bg-[#e0eaff] rounded-2xl border border-blue-200 p-5 shadow-sm flex flex-col justify-between min-h-[132px] relative overflow-hidden">
                        <div class="flex items-center justify-between relative z-10">
                            <span class="text-[11px] font-bold text-gray-700 tracking-wider uppercase">AUTO-DISCOUNT</span>
                            <span class="material-symbols-rounded text-[#0052cc] text-[20px]">verified</span>
                        </div>
                        <div class="text-[30px] font-black text-[#002b6b] font-mono leading-none my-2 relative z-10">84</div>
                        <div class="text-[12px] font-semibold text-[#0052cc] relative z-10">
                            Item diskon otomatis
                        </div>
                        <!-- Decorative Watermark Timer Icon -->
                        <span class="material-symbols-rounded text-[#0052cc]/15 text-[110px] absolute -right-4 -bottom-6 pointer-events-none select-none">timer</span>
                    </div>
                </div>

                <!-- Main Grid: Left Table (8 cols) & Right Widgets (4 cols) -->
                <div class="grid grid-cols-12 gap-6 items-start">
                    
                    <!-- Left Column: Daftar Promosi Berjalan -->
                    <div class="col-span-8 bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex flex-col">
                        
                        <!-- Card Header -->
                        <div class="px-6 py-5 flex items-center justify-between gap-4">
                            <h2 class="text-[16px] font-bold text-gray-900">Daftar Promosi Berjalan</h2>
                            <div class="flex items-center gap-2.5">
                                <div class="relative w-[240px]">
                                    <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                                    <input type="text" placeholder="Cari promo..." class="w-full pl-9 pr-3 py-1.5 rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-[#0052cc]/20 focus:border-[#0052cc] text-[12.5px] text-gray-700 placeholder-gray-400">
                                </div>
                                <button class="w-9 h-9 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 flex items-center justify-center text-gray-600 transition-colors">
                                    <span class="material-symbols-rounded text-[18px]">filter_list</span>
                                </button>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-[#f8f9fa] border-y border-gray-100 text-gray-600 text-[11.5px] font-bold">
                                        <th class="pl-8 pr-4 py-3.5 w-[32%]">Nama Promo</th>
                                        <th class="px-4 py-3.5 w-[20%]">Tipe</th>
                                        <th class="px-4 py-3.5 w-[18%]">Periode</th>
                                        <th class="px-4 py-3.5 w-[18%]">Status</th>
                                        <th class="pl-4 pr-8 py-3.5 text-center w-[12%]">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-[13px]">
                                    <!-- Row 1 -->
                                    <tr class="hover:bg-gray-50/70 transition-colors">
                                        <td class="pl-8 pr-4 py-5 align-middle">
                                            <div class="font-bold text-gray-900 text-[13.5px] leading-snug">
                                                Promo<br>Gajian<br>Mantap
                                            </div>
                                            <div class="text-[11.5px] text-gray-500 mt-1 leading-tight">
                                                Min. belanja<br>Rp 250k
                                            </div>
                                        </td>
                                        <td class="px-4 py-5 align-middle">
                                            <span class="inline-block bg-[#dbeafe] text-[#1e40af] text-[11px] font-bold px-3.5 py-1.5 rounded-full text-center leading-tight">
                                                Persentase<br>(10%)
                                            </span>
                                        </td>
                                        <td class="px-4 py-5 align-middle font-mono text-[12px] text-gray-700 leading-relaxed">
                                            25 Mei<br>- 02<br>Jun
                                        </td>
                                        <td class="px-4 py-5 align-middle">
                                            <div class="inline-flex items-center gap-1.5 text-green-600 font-semibold text-[12.5px]">
                                                <span class="w-2 h-2 rounded-full bg-green-600"></span>
                                                Aktif
                                            </div>
                                        </td>
                                        <td class="pl-4 pr-8 py-5 align-middle">
                                            <div class="flex flex-col items-center gap-2.5 text-gray-600">
                                                <button class="hover:text-[#0052cc] transition-colors">
                                                    <span class="material-symbols-rounded text-[17px]">edit</span>
                                                </button>
                                                <button class="hover:text-red-600 transition-colors">
                                                    <span class="material-symbols-rounded text-[17px]">delete</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Row 2 -->
                                    <tr class="hover:bg-gray-50/70 transition-colors">
                                        <td class="pl-8 pr-4 py-5 align-middle">
                                            <div class="font-bold text-gray-900 text-[13.5px] leading-snug">
                                                BOGO: Susu<br>Cair Ultra
                                            </div>
                                            <div class="text-[11.5px] text-gray-500 mt-1">
                                                Beli 2 Gratis 1
                                            </div>
                                        </td>
                                        <td class="px-4 py-5 align-middle">
                                            <span class="inline-block bg-[#ffedd5] text-[#9a3412] text-[11px] font-bold px-3 py-1 rounded-full">
                                                Bundle/BOGO
                                            </span>
                                        </td>
                                        <td class="px-4 py-5 align-middle font-mono text-[12px] text-gray-700 leading-relaxed">
                                            01 Jun<br>- 15<br>Jun
                                        </td>
                                        <td class="px-4 py-5 align-middle">
                                            <div class="inline-flex items-center gap-1.5 text-[#0052cc] font-semibold text-[12.5px]">
                                                <span class="w-2 h-2 rounded-full bg-[#0052cc]"></span>
                                                Mendatang
                                            </div>
                                        </td>
                                        <td class="pl-4 pr-8 py-5 align-middle">
                                            <div class="flex flex-col items-center gap-2.5 text-gray-600">
                                                <button class="hover:text-[#0052cc] transition-colors">
                                                    <span class="material-symbols-rounded text-[17px]">edit</span>
                                                </button>
                                                <button class="hover:text-red-600 transition-colors">
                                                    <span class="material-symbols-rounded text-[17px]">delete</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Row 3 -->
                                    <tr class="hover:bg-gray-50/70 transition-colors">
                                        <td class="pl-8 pr-4 py-5 align-middle">
                                            <div class="font-bold text-gray-900 text-[13.5px] leading-snug">
                                                Potongan<br>Flat<br>Deterjen
                                            </div>
                                            <div class="text-[11.5px] text-gray-500 mt-1 leading-tight">
                                                Diskon Rp<br>5.000
                                            </div>
                                        </td>
                                        <td class="px-4 py-5 align-middle">
                                            <span class="inline-block bg-gray-200 text-gray-700 text-[11px] font-bold px-3 py-1 rounded-full">
                                                Fixed Amount
                                            </span>
                                        </td>
                                        <td class="px-4 py-5 align-middle font-mono text-[12px] text-gray-700 leading-relaxed">
                                            01 Mei<br>- 15<br>Mei
                                        </td>
                                        <td class="px-4 py-5 align-middle">
                                            <div class="inline-flex items-center gap-1.5 text-gray-400 font-semibold text-[12.5px]">
                                                <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                                                Berakhir
                                            </div>
                                        </td>
                                        <td class="pl-4 pr-8 py-5 align-middle text-center">
                                            <button class="text-gray-500 hover:text-[#0052cc] transition-colors">
                                                <span class="material-symbols-rounded text-[18px]">refresh</span>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Table Footer / Pagination -->
                        <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
                            <span class="text-[12px] text-gray-500 font-medium">Menampilkan 1-10 dari 45 promo</span>
                            <div class="flex items-center gap-2">
                                <button class="px-3.5 py-1.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-[12px] font-semibold text-gray-700 transition-colors">
                                    Sebelumnya
                                </button>
                                <button class="px-3.5 py-1.5 rounded-lg bg-[#0052cc] hover:bg-[#0043a8] text-[12px] font-semibold text-white shadow-sm transition-colors">
                                    Berikutnya
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- Right Column: Widgets -->
                    <div class="col-span-4 space-y-5">
                        
                        <!-- Widget 1: Diskon Otomatis Expired -->
                        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                            <!-- Header with warm tint -->
                            <div class="bg-[#fff9f2] p-5 border-b border-orange-100/60">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-rounded text-amber-800 text-[20px]">timer</span>
                                    <h3 class="text-[15px] font-bold text-gray-900">Diskon Otomatis Expired</h3>
                                </div>
                                <p class="text-[12px] text-gray-500 mt-1.5 leading-relaxed">
                                    Kurangi kerugian stok dengan diskon berjenjang otomatis.
                                </p>
                            </div>

                            <!-- Rules Body -->
                            <div class="p-5 space-y-3">
                                <!-- Rule 1 -->
                                <div class="bg-[#f3f5f8] rounded-xl px-4 py-3 flex items-center justify-between">
                                    <div>
                                        <h4 class="text-[12.5px] font-bold text-gray-900">Near Expiry (H-7)</h4>
                                        <p class="text-[11px] text-gray-500 mt-0.5">Diskon untuk produk sisa 7 hari</p>
                                    </div>
                                    <span class="text-[16px] font-black text-amber-800 font-mono">40%</span>
                                </div>

                                <!-- Rule 2 -->
                                <div class="bg-[#f3f5f8] rounded-xl px-4 py-3 flex items-center justify-between">
                                    <div>
                                        <h4 class="text-[12.5px] font-bold text-gray-900">Critical (H-3)</h4>
                                        <p class="text-[11px] text-gray-500 mt-0.5">Diskon pembersihan stok</p>
                                    </div>
                                    <span class="text-[16px] font-black text-red-600 font-mono">70%</span>
                                </div>

                                <!-- Add Rule Button -->
                                <button class="w-full py-2.5 border border-dashed border-gray-300 rounded-xl flex items-center justify-center gap-2 text-[12px] font-bold text-gray-700 hover:bg-gray-50 transition-colors">
                                    <span class="material-symbols-rounded text-[18px] text-gray-500">add_circle</span>
                                    Konfigurasi Aturan Baru
                                </button>

                                <!-- Auto-Print Checkbox -->
                                <label class="flex items-center gap-2.5 pt-2 cursor-pointer select-none">
                                    <input type="checkbox" checked class="w-4 h-4 rounded text-[#0052cc] border-gray-300 focus:ring-[#0052cc]">
                                    <span class="text-[12px] font-medium text-gray-700">Aktifkan Auto-Print Label Diskon</span>
                                </label>
                            </div>
                        </div>

                        <!-- Widget 2: Rekomendasi Promo -->
                        <div class="bg-[#0052cc] rounded-2xl p-6 text-white shadow-md relative overflow-hidden">
                            <!-- Background Decorative Graphic -->
                            <span class="material-symbols-rounded text-white/15 text-[84px] absolute -right-2 -top-2 pointer-events-none select-none">auto_awesome</span>
                            
                            <h3 class="text-[15px] font-bold relative z-10">Rekomendasi Promo</h3>
                            <p class="text-[12px] text-blue-100 mt-2 leading-relaxed relative z-10">
                                Analisis data menunjukkan peningkatan penjualan 22% jika Anda menerapkan 'Bundle Sabun & Deterjen' pada akhir pekan ini.
                            </p>
                            <button class="mt-4 bg-white hover:bg-blue-50 text-[#0052cc] font-bold text-[12px] px-4 py-2 rounded-lg shadow-sm transition-colors relative z-10">
                                Buat Promo Sekarang
                            </button>
                        </div>

                        <!-- Widget 3: Aktivitas Terakhir -->
                        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                            <h3 class="text-[14px] font-bold text-gray-900 mb-4">Aktivitas Terakhir</h3>
                            
                            <div class="space-y-4">
                                <!-- Activity 1 -->
                                <div class="flex items-start gap-3">
                                    <div class="w-7 h-7 rounded-full bg-green-50 text-green-600 flex items-center justify-center shrink-0 mt-0.5">
                                        <span class="material-symbols-rounded text-[15px]">check</span>
                                    </div>
                                    <div>
                                        <p class="text-[12px] font-medium text-gray-800 leading-snug">Promo Sembako Murah diaktifkan oleh Admin.</p>
                                        <span class="text-[11px] text-gray-400 block mt-0.5">10 menit yang lalu</span>
                                    </div>
                                </div>

                                <!-- Activity 2 -->
                                <div class="flex items-start gap-3">
                                    <div class="w-7 h-7 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 mt-0.5">
                                        <span class="material-symbols-rounded text-[15px]">history</span>
                                    </div>
                                    <div>
                                        <p class="text-[12px] font-medium text-gray-800 leading-snug">Aturan H-7 Expired diubah menjadi 50%.</p>
                                        <span class="text-[11px] text-gray-400 block mt-0.5">2 jam yang lalu</span>
                                    </div>
                                </div>

                                <!-- Activity 3 -->
                                <div class="flex items-start gap-3">
                                    <div class="w-7 h-7 rounded-full bg-red-50 text-red-500 flex items-center justify-center shrink-0 mt-0.5">
                                        <span class="material-symbols-rounded text-[15px]">close</span>
                                    </div>
                                    <div>
                                        <p class="text-[12px] font-medium text-gray-800 leading-snug">Promo Ramadan Kareem telah berakhir.</p>
                                        <span class="text-[11px] text-gray-400 block mt-0.5">Kemarin, 23:59</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

            </div>
        </div>
    </main>
</div>
@endsection
