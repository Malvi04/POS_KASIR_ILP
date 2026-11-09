@extends('layouts.owner-admin', ['active' => 'dashboard'])

@section('title', 'Executive Owner Console - ILP POS')

@section('header')
<x-admin.header title="Owner Console" searchPlaceholder="Cari data cabang, laporan, atau metrik..." searchModel="searchQuery">
    <x-slot:leftSlot>
        <div class="flex items-center gap-2">
            <h2 class="text-base font-black text-slate-800 tracking-tight">Owner Console</h2>
            <span class="text-slate-300">|</span>
            <span class="text-xs px-2.5 py-0.5 rounded-full bg-blue-50 text-[#0052cc] border border-blue-200 font-bold">
                Executive Dashboard
            </span>
        </div>
    </x-slot:leftSlot>

    <a href="{{ url('/owner/reports') }}" 
       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-[13px] font-bold shadow-sm shadow-blue-600/20 transition-all cursor-pointer">
        <span class="material-symbols-rounded text-[18px]">bar_chart</span>
        <span>Buka Laporan Laba Rugi</span>
    </a>
</x-admin.header>
@endsection

@section('admin_content')
<div x-data="ownerDashboardApp()" 
     x-init="init()"
     @ilp-search.window="searchQuery = $event.detail"
     class="space-y-6">

    <!-- 1. Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Executive Dashboard</h1>
            <p class="text-xs text-slate-500 mt-1">Konsolidasi performa operasional toko dan gudang.</p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="{{ url('/owner/cabang') }}" class="px-3.5 py-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs transition-colors flex items-center gap-1.5 shadow-2xs">
                <span class="material-symbols-rounded text-[16px]">storefront</span>
                <span>Cabang &amp; Gudang</span>
            </a>
            <a href="{{ url('/owner/reports') }}" class="px-3.5 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs transition-all shadow-sm flex items-center gap-1.5">
                <span class="material-symbols-rounded text-[16px]">bar_chart</span>
                <span>Laporan Laba Rugi</span>
            </a>
        </div>
    </div>

    <!-- 2. Four Key Executive KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- KPI 1: Total Penjualan Konsolidasi Hari Ini -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">TOTAL PENJUALAN HARI INI</span>
                <span class="w-8 h-8 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center">
                    <span class="material-symbols-rounded text-[18px]">payments</span>
                </span>
            </div>
            <div class="text-2xl font-black text-slate-900 font-mono mt-2 tracking-tight" x-text="formatRupiah(totalSalesToday)">Rp 0</div>
            <div class="flex items-center gap-1.5 text-xs font-bold text-slate-500 mt-2">
                <span class="material-symbols-rounded text-[15px]" :class="totalTrxToday > 0 ? 'text-emerald-600' : 'text-slate-400'">receipt_long</span>
                <span x-text="totalTrxToday + ' Transaksi Tercatat'"></span>
            </div>
        </div>

        <!-- KPI 2: Laba Bersih Konsolidasi (SAK EP) -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">ESTIMASI LABA BERSIH</span>
                <span class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <span class="material-symbols-rounded text-[18px]">account_balance</span>
                </span>
            </div>
            <div class="text-2xl font-black text-slate-900 font-mono mt-2 tracking-tight" x-text="formatRupiah(totalLabaYtd)">Rp 0</div>
            <div class="flex items-center gap-1.5 text-xs font-bold text-slate-500 mt-2">
                <span class="material-symbols-rounded text-[15px] text-blue-600">pie_chart</span>
                <span x-text="totalSalesToday > 0 ? 'Margin Operasional' : 'Belum Ada Penjualan'"></span>
            </div>
        </div>

        <!-- KPI 3: Jaringan Fasilitas Toko -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">JARINGAN CABANG TOKO</span>
                <span class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                    <span class="material-symbols-rounded text-[18px]">storefront</span>
                </span>
            </div>
            <div class="text-2xl font-black text-slate-900 font-mono mt-2 tracking-tight" x-text="branches.length + ' Cabang Toko'">3 Cabang Toko</div>
            <div class="flex items-center gap-1.5 text-xs font-bold text-slate-600 mt-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>+ 1 Gudang Pusat Terpisah</span>
            </div>
        </div>

        <!-- KPI 4: Stok Rendah & Restock Alert -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">PERINGATAN STOK</span>
                <span class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <span class="material-symbols-rounded text-[18px]">inventory_2</span>
                </span>
            </div>
            <div class="text-2xl font-black font-mono mt-2 tracking-tight" :class="criticalItems.length > 0 ? 'text-amber-500' : 'text-slate-800'" x-text="criticalItems.length + ' Produk'">0 Produk</div>
            <div class="w-full bg-slate-100 h-1.5 rounded-full mt-3 overflow-hidden">
                <div class="bg-amber-500 h-full rounded-full" :style="'width: ' + Math.min(100, criticalItems.length * 10) + '%;'"></div>
            </div>
        </div>
    </div>

    <!-- 3. Direct Navigation to 5 Owner Modules -->
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-black text-slate-800 tracking-tight uppercase">Modul Utama Owner Console</h2>
            <span class="text-xs text-slate-400 font-medium">5 Modul Terintegrasi</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
            <!-- Modul 1: Cabang -->
            <a href="{{ url('/owner/cabang') }}" class="group bg-white rounded-2xl p-4 border border-slate-200/80 hover:border-[#0052cc] hover:shadow-md transition-all flex flex-col justify-between">
                <div class="space-y-2.5">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] group-hover:bg-[#0052cc] group-hover:text-white transition-colors flex items-center justify-center">
                        <span class="material-symbols-rounded text-[22px]">storefront</span>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900 group-hover:text-[#0052cc] transition-colors">Cabang &amp; Gudang</h3>
                        <p class="text-[11.5px] text-slate-500 mt-0.5 leading-snug">Pusat, Cabang A, B &amp; Gudang Pusat</p>
                    </div>
                </div>
                <div class="mt-4 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] font-bold text-[#0052cc]">
                    <span>Buka Modul</span>
                    <span class="material-symbols-rounded text-[14px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </div>
            </a>

            <!-- Modul 2: Laporan Laba Rugi -->
            <a href="{{ url('/owner/reports') }}" class="group bg-white rounded-2xl p-4 border border-slate-200/80 hover:border-[#0052cc] hover:shadow-md transition-all flex flex-col justify-between">
                <div class="space-y-2.5">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition-colors flex items-center justify-center">
                        <span class="material-symbols-rounded text-[22px]">bar_chart</span>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900 group-hover:text-emerald-600 transition-colors">Laba Rugi</h3>
                        <p class="text-[11.5px] text-slate-500 mt-0.5 leading-snug">SAK EP, EBIT, &amp; Neraca Ringkas</p>
                    </div>
                </div>
                <div class="mt-4 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] font-bold text-emerald-600">
                    <span>Lihat Laporan</span>
                    <span class="material-symbols-rounded text-[14px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </div>
            </a>

            <!-- Modul 3: User & Hak Akses -->
            <a href="{{ url('/owner/kelola-akun') }}" class="group bg-white rounded-2xl p-4 border border-slate-200/80 hover:border-[#0052cc] hover:shadow-md transition-all flex flex-col justify-between">
                <div class="space-y-2.5">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 group-hover:bg-purple-600 group-hover:text-white transition-colors flex items-center justify-center">
                        <span class="material-symbols-rounded text-[22px]">admin_panel_settings</span>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900 group-hover:text-purple-600 transition-colors">User &amp; Akses</h3>
                        <p class="text-[11.5px] text-slate-500 mt-0.5 leading-snug">RBAC Kasir, Manager, &amp; Owner</p>
                    </div>
                </div>
                <div class="mt-4 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] font-bold text-purple-600">
                    <span>Kelola Akses</span>
                    <span class="material-symbols-rounded text-[14px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </div>
            </a>

            <!-- Modul 4: Mitra UMKM -->
            <a href="{{ url('/owner/umkm-partners') }}" class="group bg-white rounded-2xl p-4 border border-slate-200/80 hover:border-[#0052cc] hover:shadow-md transition-all flex flex-col justify-between">
                <div class="space-y-2.5">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 group-hover:bg-amber-600 group-hover:text-white transition-colors flex items-center justify-center">
                        <span class="material-symbols-rounded text-[22px]">handshake</span>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900 group-hover:text-amber-600 transition-colors">Mitra UMKM</h3>
                        <p class="text-[11.5px] text-slate-500 mt-0.5 leading-snug">Mitra Konsinyasi &amp; Restock Alert</p>
                    </div>
                </div>
                <div class="mt-4 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] font-bold text-amber-600">
                    <span>Kelola Mitra</span>
                    <span class="material-symbols-rounded text-[14px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </div>
            </a>

            <!-- Modul 5: Members -->
            <a href="{{ url('/owner/members') }}" class="group bg-white rounded-2xl p-4 border border-slate-200/80 hover:border-[#0052cc] hover:shadow-md transition-all flex flex-col justify-between">
                <div class="space-y-2.5">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 group-hover:bg-sky-600 group-hover:text-white transition-colors flex items-center justify-center">
                        <span class="material-symbols-rounded text-[22px]">group</span>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900 group-hover:text-sky-600 transition-colors">Anggota Koperasi</h3>
                        <p class="text-[11.5px] text-slate-500 mt-0.5 leading-snug">Tata Kelola Plafon &amp; Piutang</p>
                    </div>
                </div>
                <div class="mt-4 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] font-bold text-sky-600">
                    <span>Tata Kelola</span>
                    <span class="material-symbols-rounded text-[14px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </div>
            </a>
        </div>
    </div>

    <!-- 4. Grafik Penjualan Hari Ini (Pergerakan Berdasarkan Waktu & Perbandingan 3 Cabang) -->
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
            <div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-rounded text-[#0052cc] text-[22px]">query_stats</span>
                    <h3 class="text-base font-black text-slate-900 tracking-tight">Grafik Penjualan Cabang Toko Hari Ini</h3>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                    Periode Operasional: <span class="font-bold text-slate-800" x-text="todayDateStr"></span> &bull; Pergerakan penjualan per jam dan perbandingan cabang toko
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-[11px] font-bold px-2.5 py-1 rounded-full border"
                      :class="isRealtimeSource ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'">
                    <span x-text="isRealtimeSource ? 'Sumber: Transaksi POS Hari Ini' : 'Data Operasional Terverifikasi'"></span>
                </span>
            </div>
        </div>

        <template x-if="totalSalesToday > 0">
            <div class="space-y-4">
                <!-- Metric Summary Chips for Today's Sales per Branch -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <template x-for="item in branchSalesData" :key="item.branch">
                        <div class="p-3.5 rounded-xl border border-slate-200/70 bg-slate-50/50 flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full" :class="item.colorDot"></span>
                                    <span class="font-black text-xs text-slate-800" x-text="item.branch"></span>
                                </div>
                                <span class="text-[11px] font-mono font-bold text-slate-500" x-text="item.trxCount + ' Trx'"></span>
                            </div>
                            <div class="mt-2 text-lg font-black font-mono text-[#0052cc]" x-text="formatRupiah(item.revenue)"></div>
                            <div class="w-full bg-slate-200 h-1.5 rounded-full mt-2.5 overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500" 
                                     :class="item.barColor"
                                     :style="'width: ' + item.percentage + '%'"></div>
                            </div>
                            <div class="flex items-center justify-between text-[10px] text-slate-400 font-semibold mt-1.5">
                                <span x-text="'Porsi: ' + item.percentage + '%'"></span>
                                <span x-text="'Avg: ' + formatRupiah(item.avgTicket)"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="totalSalesToday === 0">
            <div class="py-12 text-center text-slate-400 bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                <span class="material-symbols-rounded text-3xl text-slate-300 block mb-1">query_stats</span>
                <span class="text-xs font-bold text-slate-700 block">Belum ada transaksi hari ini.</span>
                <span class="text-[11px] text-slate-400">Statistik penjualan cabang akan tampil setelah kasir melakukan transaksi.</span>
            </div>
        </template>
    </div>

    <!-- 5. Two Column Layout: Status Operasional Cabang & Peringatan Stok Rendah -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        
        <!-- Left 2 Cols: Status Operasional Cabang (Hanya Pusat, Cabang A, Cabang B) -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="material-symbols-rounded text-[#0052cc] text-[20px]">storefront</span>
                    <div>
                        <h3 class="text-sm font-black text-slate-800">Status Operasional Cabang Toko</h3>
                        <p class="text-[11.5px] text-slate-500">Menampilkan 3 gerai toko ritel koperasi (Gudang Pusat dipisahkan di panel fasilitas logistik)</p>
                    </div>
                </div>
                <a href="{{ url('/owner/cabang') }}" class="text-xs font-bold text-[#0052cc] hover:underline flex items-center gap-1">
                    <span>Detail Cabang</span>
                    <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                </a>
            </div>

            <!-- Tabel Cabang Toko (Tanpa Label Geografis) -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-[12.5px]">
                    <thead class="bg-slate-50 text-slate-500 font-bold border-b border-slate-200">
                        <tr>
                            <th class="py-2.5 px-3">Nama Cabang</th>
                            <th class="py-2.5 px-3">Tipe</th>
                            <th class="py-2.5 px-3">Penanggung Jawab</th>
                            <th class="py-2.5 px-3 text-right">Penjualan Hari Ini</th>
                            <th class="py-2.5 px-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="b in filteredBranches" :key="b.id">
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <!-- Hanya Nama Cabang: Pusat, Cabang A, Cabang B -->
                                <td class="py-3 px-3 font-bold text-slate-900" x-text="b.name"></td>
                                <td class="py-3 px-3 text-slate-600">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-700" x-text="b.type"></span>
                                </td>
                                <td class="py-3 px-3 text-slate-600" x-text="b.manager"></td>
                                <td class="py-3 px-3 text-right font-mono font-bold text-slate-800" x-text="formatRupiah(b.rawSales)"></td>
                                <td class="py-3 px-3 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold border"
                                          :class="b.statusColor"
                                          x-text="b.status"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Panel Terpisah: Gudang Logistik Pusat -->
            <div class="pt-3 border-t border-slate-100">
                <div class="p-3.5 rounded-xl bg-slate-50/80 border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[20px]">warehouse</span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-black text-xs text-slate-900" x-text="warehouse.name"></span>
                                <span class="text-[10px] px-1.5 py-0.2 rounded font-bold uppercase bg-indigo-50 text-indigo-700 border border-indigo-200">Gudang Logistik</span>
                            </div>
                            <span class="text-[11px] text-slate-500" x-text="'Penanggung Jawab: ' + warehouse.manager + ' &bull; Fasilitas Distribusi Konsolidasi'"></span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 self-end sm:self-auto">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-blue-50 text-blue-700 border border-blue-200">
                            DC OPERASIONAL
                        </span>
                        <a href="{{ url('/owner/cabang') }}" class="text-xs font-bold text-[#0052cc] hover:underline">Kelola &rarr;</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right 1 Col: Peringatan Stok Rendah & Tombol Buka Panel Products & Stok -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs flex flex-col justify-between space-y-4">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2 text-amber-600 font-black text-sm">
                        <span class="material-symbols-rounded text-[18px]">warning</span>
                        <span>Stok Rendah (12 Produk)</span>
                    </div>
                    <span class="text-[11px] font-bold px-2 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200">Reorder</span>
                </div>
                <p class="text-xs text-slate-500 mb-4 leading-relaxed">
                    Produk dengan stok di bawah batas aman minimum. Lakukan monitoring stok gudang dan cabang.
                </p>

                <div class="space-y-2.5">
                    <template x-for="item in criticalItems" :key="item.sku">
                        <div class="p-2.5 rounded-xl border border-slate-100 bg-slate-50/60 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-slate-800 truncate" x-text="item.nama"></div>
                                <div class="text-[10px] text-slate-500 truncate" x-text="item.vendor"></div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-xs font-black font-mono text-amber-600" x-text="item.stok + ' ' + item.unit"></div>
                                <span class="text-[9.5px] font-bold text-slate-400" x-text="'Min: ' + item.min"></span>
                            </div>
                        </div>
                    </template>
                    <template x-if="criticalItems.length === 0">
                        <div class="py-6 text-center text-xs text-slate-400">
                            <span class="material-symbols-rounded text-2xl text-slate-300 block mb-1">inventory_2</span>
                            Tidak ada produk dengan stok menipis.
                        </div>
                    </template>
                </div>
            </div>

            <!-- Tombol Wajib: Buka Panel Product & Stok -->
            <div class="pt-4 border-t border-slate-100 space-y-2">
                <a href="{{ url('/owner/products?filter=low_stock') }}" 
                   class="w-full py-2.5 px-3 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-xs font-bold flex items-center justify-center gap-2 shadow-sm transition-all cursor-pointer">
                    <span class="material-symbols-rounded text-[17px]">inventory_2</span>
                    <span>Buka Panel Product &amp; Stok</span>
                </a>

                <a href="{{ url('/owner/umkm-partners') }}" class="w-full py-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold flex items-center justify-center gap-1.5 transition-colors">
                    <span class="material-symbols-rounded text-[16px]">handshake</span>
                    <span>Koordinasi Pasokan UMKM</span>
                </a>
            </div>
        </div>

    </div>

</div>

<script>
function ownerDashboardApp() {
    return {
        searchQuery: '',
        todayDateStr: '',
        isRealtimeSource: false,
        totalSalesToday: 0,
        totalTrxToday: 0,
        totalLabaYtd: 0,

        branches: [],
        warehouse: {
            id: 'gudang-pusat',
            name: 'Gudang Pusat',
            type: 'Gudang Logistik',
            manager: 'Kepala Gudang',
            status: 'OPERASIONAL DC',
            statusColor: 'bg-blue-50 text-blue-700 border-blue-200'
        },

        criticalItems: [],
        branchSalesData: [],

        init() {
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            const now = new Date();
            this.todayDateStr = days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();

            this.loadBranches();
            this.loadCriticalItems();
            this.loadSalesData();
        },

        loadBranches() {
            try {
                const stored = localStorage.getItem('pos_ilp_branches_v1');
                if (stored) {
                    const parsed = JSON.parse(stored);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        this.branches = parsed.filter(b => b.facilityType !== 'warehouse').map(b => ({
                            id: b.id,
                            name: b.name,
                            type: 'Cabang Toko',
                            manager: b.manager || 'Manager Cabang',
                            rawSales: 0,
                            trxCount: 0,
                            status: b.status || 'AKTIF',
                            statusColor: b.status === 'AKTIF' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'
                        }));
                        return;
                    }
                }
            } catch(e) {}
            this.branches = [
                { id: 'pusat', name: 'Pusat', type: 'Cabang Toko', manager: 'Manager Pusat', rawSales: 0, trxCount: 0, status: 'AKTIF', statusColor: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
                { id: 'cabang-a', name: 'Cabang A', type: 'Cabang Toko', manager: 'Manager Cabang A', rawSales: 0, trxCount: 0, status: 'AKTIF', statusColor: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
                { id: 'cabang-b', name: 'Cabang B', type: 'Cabang Toko', manager: 'Manager Cabang B', rawSales: 0, trxCount: 0, status: 'AKTIF', statusColor: 'bg-emerald-50 text-emerald-700 border-emerald-200' }
            ];
        },

        loadCriticalItems() {
            try {
                const rawProds = localStorage.getItem('pos_ilp_inventory_products_v1');
                if (rawProds) {
                    const prods = JSON.parse(rawProds);
                    if (Array.isArray(prods)) {
                        this.criticalItems = prods
                            .filter(p => Number(p.stock) <= Number(p.minStock || 10))
                            .slice(0, 5)
                            .map(p => ({
                                sku: p.sku || p.barcode,
                                nama: p.name,
                                vendor: p.brand || p.category || 'Vendor',
                                stok: Number(p.stock) || 0,
                                min: Number(p.minStock) || 10,
                                unit: p.unit || 'Pcs',
                                status: Number(p.stock) === 0 ? 'Habis' : 'Menipis'
                            }));
                        return;
                    }
                }
            } catch(e) {}
            this.criticalItems = [];
        },

        loadSalesData() {
            try {
                const storedTrx = localStorage.getItem('pos_ilp_transactions_v1');
                if (storedTrx) {
                    const parsed = JSON.parse(storedTrx);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        let salesMap = {
                            'Pusat': { revenue: 0, trxCount: 0, colorDot: 'bg-[#0052cc]', barColor: 'bg-[#0052cc]' },
                            'Cabang A': { revenue: 0, trxCount: 0, colorDot: 'bg-emerald-500', barColor: 'bg-emerald-500' },
                            'Cabang B': { revenue: 0, trxCount: 0, colorDot: 'bg-amber-500', barColor: 'bg-amber-500' }
                        };

                        let foundValid = false;
                        parsed.forEach(t => {
                            if (t.status !== 'VOID' && t.status !== 'REFUND') {
                                const bName = (t.branch || 'Pusat').includes('A') ? 'Cabang A' : ((t.branch || '').includes('B') ? 'Cabang B' : 'Pusat');
                                const total = Number(t.total) || Number(t.grandTotal) || 0;
                                if (salesMap[bName]) {
                                    salesMap[bName].revenue += total;
                                    salesMap[bName].trxCount++;
                                    foundValid = true;
                                }
                            }
                        });

                        if (foundValid) {
                            this.isRealtimeSource = true;
                            const totalRev = Object.values(salesMap).reduce((acc, curr) => acc + curr.revenue, 0);
                            this.totalSalesToday = totalRev;
                            this.totalTrxToday = Object.values(salesMap).reduce((acc, curr) => acc + curr.trxCount, 0);
                            this.totalLabaYtd = Math.round(totalRev * 0.25);

                            this.branchSalesData = Object.keys(salesMap).map(k => {
                                const rev = salesMap[k].revenue;
                                const cnt = salesMap[k].trxCount;
                                const pct = this.totalSalesToday > 0 ? Math.round((rev / this.totalSalesToday) * 100) : 0;
                                return {
                                    branch: k,
                                    revenue: rev,
                                    trxCount: cnt,
                                    percentage: pct,
                                    colorDot: salesMap[k].colorDot,
                                    barColor: salesMap[k].barColor,
                                    avgTicket: cnt > 0 ? Math.round(rev / cnt) : 0
                                };
                            });
                            return;
                        }
                    }
                }
            } catch(e) {}

            this.isRealtimeSource = true;
            this.totalSalesToday = 0;
            this.totalTrxToday = 0;
            this.totalLabaYtd = 0;
            this.branchSalesData = [];
        },

        get filteredBranches() {
            if (!this.searchQuery.trim()) return this.branches;
            const q = this.searchQuery.toLowerCase();
            return this.branches.filter(b => 
                b.name.toLowerCase().includes(q) || 
                b.manager.toLowerCase().includes(q)
            );
        },

        formatRupiah(val) {
            return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
        }
    };
}
</script>

        get filteredBranches() {
            if (!this.searchQuery.trim()) return this.branches;
            const q = this.searchQuery.toLowerCase();
            return this.branches.filter(b => 
                b.name.toLowerCase().includes(q) || 
                b.manager.toLowerCase().includes(q)
            );
        },

        formatRupiah(val) {
            return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
        }
    };
}
</script>
@endsection
