@extends('layouts.app')

@section('title', 'Manajemen Anggota - Vigilant POS')

@section('content')
<!-- Sidebar -->
<aside class="w-64 bg-slate-50 border-r border-gray-200 flex flex-col justify-between shrink-0">
    <div>
        <div class="p-6">
            <h1 class="text-2xl font-bold text-blue-600">Vigilant POS</h1>
            <p class="text-sm text-gray-500 mt-1">Worker Coop Admin</p>
        </div>
        
        <nav class="mt-4 flex flex-col gap-1">
            <a href="/dashboard" class="flex items-center gap-3 px-6 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors">
                <span class="material-symbols-rounded text-gray-400">dashboard</span>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="/inventory" class="flex items-center gap-3 px-6 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors">
                <span class="material-symbols-rounded text-gray-400">inventory_2</span>
                <span class="font-medium">Products</span>
            </a>
            <a href="/members" class="flex items-center gap-3 px-6 py-3 bg-blue-50 text-blue-600 border-l-4 border-blue-600 relative">
                <span class="material-symbols-rounded filled text-blue-600">group</span>
                <span class="font-medium">Members</span>
            </a>
            <a href="/reports" class="flex items-center gap-3 px-6 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors">
                <span class="material-symbols-rounded text-gray-400">bar_chart</span>
                <span class="font-medium">Reports</span>
            </a>
            <a href="/settings" class="flex items-center gap-3 px-6 py-3 text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors">
                <span class="material-symbols-rounded text-gray-400">settings</span>
                <span class="font-medium">Settings</span>
            </a>
        </nav>
    </div>
</aside>

<!-- Main Content -->
<main class="flex-1 flex flex-col bg-slate-50 relative h-full">
    <!-- Header -->
    <header class="h-20 border-b border-gray-200 flex items-center justify-between px-8 bg-white shrink-0">
        <div class="flex items-center">
            <h2 class="text-xl font-bold text-gray-800">Manajemen Anggota</h2>
            <div class="h-6 w-px bg-gray-300 mx-6"></div>
            <div class="flex items-center gap-6 text-sm font-medium">
                <a href="#" class="text-blue-600 font-bold border-b-2 border-blue-600 pb-1 -mb-1">Daftar Anggota</a>
                <a href="#" class="text-gray-500 hover:text-gray-700">Plafon Kredit</a>
            </div>
        </div>
        <div class="flex items-center gap-5">
            <button class="text-gray-500 hover:text-gray-700">
                <span class="material-symbols-rounded">notifications</span>
            </button>
            <button class="text-gray-500 hover:text-gray-700">
                <span class="material-symbols-rounded">wifi</span>
            </button>
            <button class="text-gray-500 hover:text-gray-700">
                <span class="material-symbols-rounded">sync</span>
            </button>
        </div>
    </header>

    <!-- Scrollable content area -->
    <div class="flex-1 overflow-auto p-8 flex flex-col">
        
        <!-- Summary Cards -->
        <div class="mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm inline-flex flex-col min-w-[240px]">
                <p class="text-xs font-bold text-gray-500 tracking-wider mb-2 uppercase">TOTAL ANGGOTA</p>
                <div class="flex items-baseline gap-3">
                    <h3 class="text-3xl font-bold text-blue-600">1,284</h3>
                    <span class="text-green-500 text-xs font-bold">+12 bln ini</span>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="flex gap-4 mb-6">
            <div class="relative flex-1">
                <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                <input type="text" placeholder="Cari NIK atau Nama Anggota..." class="w-full pl-11 pr-4 py-2.5 rounded-lg border border-gray-200 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm">
            </div>
            <button class="flex items-center gap-2 px-5 py-2.5 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors shadow-sm">
                <span class="material-symbols-rounded text-[20px]">filter_list</span>
                Filter
            </button>
        </div>

        <!-- Data Table -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden flex flex-col mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 text-xs font-bold tracking-wider uppercase">
                        <tr>
                            <th class="px-6 py-4 rounded-tl-xl">NAMA ANGGOTA</th>
                            <th class="px-6 py-4">NIK / ID</th>
                            <th class="px-6 py-4">DEPARTEMEN</th>
                            <th class="px-6 py-4 text-right">SISA PLAFON</th>
                            <th class="px-6 py-4 text-right">TOTAL PIUTANG</th>
                            <th class="px-6 py-4 text-center">STATUS</th>
                            <th class="px-6 py-4 text-center rounded-tr-xl"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        <!-- Row 1 -->
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-xs">BP</div>
                                    <div class="font-bold text-gray-900 leading-tight">Bambang<br>Pamungkas</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 font-mono text-gray-600 text-[13px]">327501002230004</td>
                            <td class="px-6 py-4">Logistik &<br>Gudang</td>
                            <td class="px-6 py-4 text-right font-mono">
                                <span class="font-bold text-gray-800">Rp 4.500.000</span>
                            </td>
                            <td class="px-6 py-4 text-right font-mono">
                                <span class="font-bold text-orange-600">Rp 500.000</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-1 bg-green-100 text-green-700 rounded-full text-[10px] font-bold tracking-wide">AKTIF</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <a href="#" class="flex flex-col items-center text-blue-600 hover:text-blue-700 font-bold text-[11px]">
                                    <span class="material-symbols-rounded text-[18px]">visibility</span>
                                    Detail<br>Piutang
                                </a>
                            </td>
                        </tr>
                        
                        <!-- Row 2 -->
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-xs">SL</div>
                                    <div class="font-bold text-gray-900">Siti Lestari</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 font-mono text-gray-600 text-[13px]">327501440230012</td>
                            <td class="px-6 py-4">Administrasi</td>
                            <td class="px-6 py-4 text-right font-mono">
                                <span class="font-bold text-gray-800">Rp 1.200.000</span>
                            </td>
                            <td class="px-6 py-4 text-right font-mono">
                                <span class="font-bold text-orange-600">Rp 3.800.000</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-1 bg-green-100 text-green-700 rounded-full text-[10px] font-bold tracking-wide">AKTIF</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <a href="#" class="flex flex-col items-center text-blue-600 hover:text-blue-700 font-bold text-[11px]">
                                    <span class="material-symbols-rounded text-[18px]">visibility</span>
                                    Detail<br>Piutang
                                </a>
                            </td>
                        </tr>

                        <!-- Row 3 - Blocked -->
                        <tr class="hover:bg-gray-50 transition-colors bg-gray-50/50">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3 opacity-60">
                                    <div class="w-8 h-8 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-bold text-xs">AS</div>
                                    <div class="font-bold text-gray-500 leading-tight">Andri<br>Setiawan</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 font-mono text-gray-400 text-[13px]">327501002230099</td>
                            <td class="px-6 py-4 text-gray-400">Produksi A</td>
                            <td class="px-6 py-4 text-right font-mono text-gray-400">
                                Rp 0
                            </td>
                            <td class="px-6 py-4 text-right font-mono">
                                <span class="font-bold text-red-600">Rp 5.000.000</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center px-2 py-1 bg-red-100 text-red-600 rounded-full text-[10px] font-bold tracking-wide">DIBLOKIR</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <a href="#" class="flex flex-col items-center text-blue-600 hover:text-blue-700 font-bold text-[11px]">
                                    <span class="material-symbols-rounded text-[18px]">visibility</span>
                                    Detail<br>Piutang
                                </a>
                            </td>
                        </tr>

                        <!-- Row 4 -->
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center font-bold text-xs">DI</div>
                                    <div class="font-bold text-gray-900">Dewi Indah</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 font-mono text-gray-600 text-[13px]">327501550230056</td>
                            <td class="px-6 py-4">Maintenance</td>
                            <td class="px-6 py-4 text-right font-mono">
                                <span class="font-bold text-gray-800">Rp 5.000.000</span>
                            </td>
                            <td class="px-6 py-4 text-right font-mono text-orange-600 font-bold">
                                Rp 0
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-1 bg-green-100 text-green-700 rounded-full text-[10px] font-bold tracking-wide">AKTIF</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <a href="#" class="flex flex-col items-center text-blue-600 hover:text-blue-700 font-bold text-[11px]">
                                    <span class="material-symbols-rounded text-[18px]">visibility</span>
                                    Detail<br>Piutang
                                </a>
                            </td>
                        </tr>

                        <!-- Row 5 -->
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-xs">RH</div>
                                    <div class="font-bold text-gray-900 leading-tight">Raka<br>Hendra</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 font-mono text-gray-600 text-[13px]">327501009930011</td>
                            <td class="px-6 py-4">Logistik &<br>Gudang</td>
                            <td class="px-6 py-4 text-right font-mono">
                                <span class="font-bold text-gray-800">Rp 2.150.000</span>
                            </td>
                            <td class="px-6 py-4 text-right font-mono">
                                <span class="font-bold text-orange-600">Rp 2.850.000</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-1 bg-green-100 text-green-700 rounded-full text-[10px] font-bold tracking-wide">AKTIF</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <a href="#" class="flex flex-col items-center text-blue-600 hover:text-blue-700 font-bold text-[11px]">
                                    <span class="material-symbols-rounded text-[18px]">visibility</span>
                                    Detail<br>Piutang
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination / Footer -->
            <div class="bg-slate-50 border-t border-gray-200 px-6 py-3 flex items-center justify-between">
                <p class="text-sm text-gray-500 font-medium">Menampilkan 1-5 dari 1,284 anggota</p>
                <div class="flex items-center gap-2 text-sm">
                    <button class="w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 transition-colors disabled:opacity-50">
                        <span class="material-symbols-rounded text-[18px]">chevron_left</span>
                    </button>
                    <button class="w-8 h-8 flex items-center justify-center rounded bg-blue-700 text-white font-bold shadow-sm">1</button>
                    <button class="w-8 h-8 flex items-center justify-center rounded text-gray-700 hover:bg-gray-200 font-medium transition-colors">2</button>
                    <button class="w-8 h-8 flex items-center justify-center rounded text-gray-700 hover:bg-gray-200 font-medium transition-colors">3</button>
                    <span class="text-gray-500 px-1 font-medium">...</span>
                    <button class="w-8 h-8 flex items-center justify-center rounded text-gray-700 hover:bg-gray-200 font-medium transition-colors">257</button>
                    <button class="w-8 h-8 flex items-center justify-center rounded text-gray-700 hover:bg-gray-200 transition-colors">
                        <span class="material-symbols-rounded text-[18px]">chevron_right</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Bottom Footer -->
        <div class="mt-auto border-t border-gray-200 pt-6 flex justify-between items-center text-xs text-gray-500">
            <div class="flex items-center gap-4">
                <span>Vigilant POS v2.4.1</span>
                <span>Terakhir sinkronisasi: 2 menit yang lalu</span>
            </div>
            <div class="flex items-center gap-6">
                <a href="#" class="hover:text-gray-800 border-b border-gray-400 pb-0.5">Pusat Bantuan</a>
                <a href="#" class="hover:text-gray-800 border-b border-gray-400 pb-0.5">Kebijakan Privasi</a>
            </div>
        </div>
    </div>
</main>
@endsection
