@extends('layouts.app')

@section('title', 'Kupon & Stok Khusus - ILP POS')

@section('content')
<div class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800">
    
    <!-- Sidebar -->
    <x-cashier-sidebar active="kupon" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        <!-- Top Header -->
        <x-cashier-header title="Kupon & Stok Khusus" />

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-8">
            <div class="max-w-[1400px] mx-auto space-y-6">
                
                <!-- Page Header -->
                <div class="flex justify-between items-start">
                    <div>
                        <div class="flex items-center gap-2 text-[10px] font-bold text-gray-500 tracking-wider mb-2">
                            <span>OPERASIONAL CABANG</span>
                            <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                            <span>INVENTARIS & LOGISTIK</span>
                            <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                            <span class="text-blue-600">STOK KHUSUS & RETUR</span>
                        </div>
                        <h1 class="text-2xl font-black text-gray-900 tracking-tight">Stok Khusus, Kupon</h1>
                        <p class="text-sm text-gray-500 mt-1 font-medium">Pencatatan inventaris non-regular (BBM, Beras Subsidi, Galon, Pulsa) & Pengajuan retur barang ke gudang pusat.</p>
                    </div>
                    <div class="flex gap-4">
                        <div class="bg-white border border-gray-200 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-sm">
                            <div class="w-2 h-2 rounded-full bg-green-500"></div>
                            <div class="flex flex-col">
                                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">SHIFT AKTIF</span>
                                <span class="text-xs font-bold text-gray-900">Terminal #01 (Pagi)</span>
                            </div>
                        </div>
                        <div class="bg-green-50 border border-green-100 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-sm">
                            <span class="material-symbols-rounded text-green-600 text-[20px]">sync_alt</span>
                            <div class="flex flex-col">
                                <span class="text-[10px] font-bold text-green-600 uppercase tracking-wider">SINKRONISASI</span>
                                <span class="text-xs font-bold text-green-700">Terkoneksi ERP</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex items-center gap-4 border-b border-gray-200 pb-px">
                    <button class="flex items-center gap-2 px-5 py-3 border-b-2 border-blue-600 text-blue-700 font-bold bg-blue-50/50 rounded-t-lg transition-colors">
                        <span class="material-symbols-rounded text-[18px]">receipt_long</span>
                        Stok Khusus & Kupon
                        <span class="bg-blue-600 text-white text-[10px] px-2 py-0.5 rounded-full ml-1">4 Kategori</span>
                    </button>
                    <button class="flex items-center gap-2 px-5 py-3 border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:bg-gray-50 rounded-t-lg font-bold transition-colors">
                        <span class="material-symbols-rounded text-[18px]">local_shipping</span>
                        Retur Toko ke Gudang
                        <span class="bg-orange-100 text-orange-700 text-[10px] px-2 py-0.5 rounded-full ml-1">Biaya Rp 500k</span>
                    </button>
                    <button class="flex items-center gap-2 px-5 py-3 border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:bg-gray-50 rounded-t-lg font-bold transition-colors">
                        <span class="material-symbols-rounded text-[18px]">history_edu</span>
                        Riwayat & Berita Acara
                        <span class="bg-gray-200 text-gray-700 text-[10px] px-2 py-0.5 rounded-full ml-1">12 Dokumen</span>
                    </button>
                </div>

                <!-- Summary Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                    <!-- Card 1 -->
                    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm flex flex-col justify-between hover:border-blue-300 transition-colors">
                        <div>
                            <div class="flex justify-between items-start mb-3">
                                <span class="text-[11px] font-bold text-gray-500 tracking-wider">KUPON BBM & ARMADA</span>
                                <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                                    <span class="material-symbols-rounded text-[18px]">local_gas_station</span>
                                </div>
                            </div>
                            <h3 class="text-xl font-black text-gray-900 tracking-tight">340 Lembar</h3>
                            <p class="text-blue-600 font-bold text-sm mt-0.5">850 Liter Tersisa</p>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100 flex justify-between items-end">
                            <span class="text-xs text-gray-500 font-medium">Alokasi Bulan<br>Berjalan</span>
                            <span class="text-xs font-bold text-green-600 text-right">68% Kuota<br>Aman</span>
                        </div>
                    </div>

                    <!-- Card 2 -->
                    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm flex flex-col justify-between hover:border-blue-300 transition-colors">
                        <div>
                            <div class="flex justify-between items-start mb-3">
                                <span class="text-[11px] font-bold text-gray-500 tracking-wider">BERAS KHUSUS / SUBSIDI</span>
                                <div class="w-8 h-8 rounded-lg bg-green-50 text-green-600 flex items-center justify-center">
                                    <span class="material-symbols-rounded text-[18px]">agriculture</span>
                                </div>
                            </div>
                            <h3 class="text-xl font-black text-gray-900 tracking-tight">125 Sak (5kg)</h3>
                            <p class="text-gray-500 font-medium text-sm mt-0.5">Tersedia di Gudang Toko</p>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100 flex justify-between items-end">
                            <span class="text-xs text-gray-500 font-medium">Klaim<br>Minggu Ini</span>
                            <span class="text-xs font-bold text-blue-600 text-right">42 Sak<br>Terdistribusi</span>
                        </div>
                    </div>

                    <!-- Card 3 -->
                    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm flex flex-col justify-between hover:border-blue-300 transition-colors">
                        <div>
                            <div class="flex justify-between items-start mb-3">
                                <span class="text-[11px] font-bold text-gray-500 tracking-wider">GALON AIR MINERAL</span>
                                <div class="w-8 h-8 rounded-lg bg-cyan-50 text-cyan-600 flex items-center justify-center">
                                    <span class="material-symbols-rounded text-[18px]">water_drop</span>
                                </div>
                            </div>
                            <h3 class="text-xl font-black text-gray-900 tracking-tight">54 Isi <span class="text-sm font-medium text-gray-400">/ 28 Kosong</span></h3>
                            <p class="text-gray-500 font-medium text-sm mt-0.5">Siap Tukar Suplier: 28 Unit</p>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100 flex justify-between items-end">
                            <span class="text-xs text-gray-500 font-medium">Total Deposit<br>Aset</span>
                            <span class="text-xs font-bold text-gray-900 text-right">82 Tabung<br>Galon</span>
                        </div>
                    </div>

                    <!-- Card 4 -->
                    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm flex flex-col justify-between hover:border-blue-300 transition-colors">
                        <div>
                            <div class="flex justify-between items-start mb-3">
                                <span class="text-[11px] font-bold text-gray-500 tracking-wider">DEPOSIT PULSA & LISTRIK</span>
                                <div class="w-8 h-8 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center">
                                    <span class="material-symbols-rounded text-[18px]">bolt</span>
                                </div>
                            </div>
                            <h3 class="text-xl font-black text-gray-900 tracking-tight">Rp 8.450.000</h3>
                            <p class="text-gray-500 font-medium text-sm mt-0.5">Saldo Kasir Terintegrasi</p>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100 flex justify-between items-end">
                            <span class="text-xs text-gray-500 font-medium">Aktivitas<br>Hari Ini</span>
                            <span class="text-xs font-bold text-green-600 text-right">38 Transaksi<br>Sukses</span>
                        </div>
                    </div>
                </div>

                <!-- Action Bar (Filters & Buttons) -->
                <div class="flex flex-wrap items-center justify-between gap-4 bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="relative w-[300px]">
                            <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                            <input type="text" placeholder="Cari Ref, No Kupon, Karyawan..." class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </div>
                        <select class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-2 text-sm font-medium text-gray-700 focus:outline-none">
                            <option>Semua Komoditas</option>
                            <option>BBM & Armada</option>
                            <option>Beras Subsidi</option>
                            <option>Galon Air</option>
                        </select>
                        <select class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-2 text-sm font-medium text-gray-700 focus:outline-none">
                            <option>Semua Status</option>
                            <option>Tervalidasi</option>
                            <option>Siap Ambil</option>
                            <option>Selesai</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-3">
                        <button class="bg-[#0052cc] hover:bg-blue-700 text-white px-5 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors shadow-sm">
                            <span class="material-symbols-rounded text-[18px]">add_box</span>
                            Catat Kupon Baru
                        </button>
                        <button class="bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-200 px-5 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors">
                            <span class="material-symbols-rounded text-[18px]">inventory</span>
                            Terima Pasokan Khusus
                        </button>
                    </div>
                </div>

                <!-- Data Table Container -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden flex flex-col">
                    <!-- Table Header Info -->
                    <div class="px-6 py-5 border-b border-gray-200 flex justify-between items-end">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Buku Log Registrasi & Penukaran</h3>
                            <p class="text-sm text-gray-500 font-medium">Daftar verifikasi kupon operasional dan komoditas khusus hari ini.</p>
                        </div>
                        <p class="text-sm font-mono text-gray-500">Menampilkan 5 dari 142 entri</p>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 text-[11px] font-bold tracking-wider uppercase">
                                <tr>
                                    <th class="px-6 py-4">NO. REF / KUPON</th>
                                    <th class="px-6 py-4">KOMODITAS / JENIS</th>
                                    <th class="px-6 py-4">PENERIMA / KOPERASI</th>
                                    <th class="px-6 py-4 text-right">QTY / VOLUME</th>
                                    <th class="px-6 py-4">WAKTU REGISTRASI</th>
                                    <th class="px-6 py-4">KASIR VERIFIKATOR</th>
                                    <th class="px-6 py-4 text-center">STATUS</th>
                                    <th class="px-6 py-4 text-center">AKSI</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <!-- Row 1 -->
                                <tr class="hover:bg-blue-50/30 transition-colors">
                                    <td class="px-6 py-4">
                                        <a href="#" class="font-bold text-blue-600 hover:underline">#KPN-BBM-8841</a>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-start gap-3">
                                            <div class="w-2 h-2 rounded-full bg-blue-500 mt-1.5 shrink-0"></div>
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-800 leading-tight">BBM Pertalite</span>
                                                <span class="font-medium text-gray-500 text-xs">Armada</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-gray-800">Andi Pratama</span>
                                            <span class="text-xs text-gray-500">Driver Logistik #03</span>
                                            <span class="text-[10px] text-gray-400 font-mono mt-0.5">(B 9481 UQ)</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="font-bold text-gray-900">20 Liter</span>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-gray-600 text-xs">
                                        24 Okt,<br>08:30 WIB
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-start gap-2">
                                            <span class="material-symbols-rounded text-gray-400 text-[16px] mt-0.5">person</span>
                                            <div class="flex flex-col">
                                                <span class="font-medium text-gray-700 leading-tight">Siti</span>
                                                <span class="font-medium text-gray-700 leading-tight">Rahmawati</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-1 bg-green-100 text-green-700 rounded-full text-[10px] font-bold tracking-wide">Tervalidasi</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button class="text-gray-400 hover:text-blue-600 transition-colors p-1 rounded-lg">
                                            <span class="material-symbols-rounded text-[20px]">receipt_long</span>
                                        </button>
                                    </td>
                                </tr>

                                <!-- Row 2 -->
                                <tr class="hover:bg-blue-50/30 transition-colors">
                                    <td class="px-6 py-4">
                                        <a href="#" class="font-bold text-blue-600 hover:underline">#KPN-BRS-1092</a>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-start gap-3">
                                            <div class="w-2 h-2 rounded-full bg-green-500 mt-1.5 shrink-0"></div>
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-800 leading-tight">Beras Subsidi</span>
                                                <span class="font-medium text-gray-500 text-xs">Karyawan</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-gray-800">Dedi Mulyadi</span>
                                            <span class="text-xs text-gray-500">Divisi Gudang - NIK</span>
                                            <span class="text-[10px] text-gray-400 font-mono mt-0.5">#4421</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="font-bold text-gray-900">1 Sak (5 kg)</span>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-gray-600 text-xs">
                                        24 Okt,<br>09:12 WIB
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-start gap-2">
                                            <span class="material-symbols-rounded text-gray-400 text-[16px] mt-0.5">person</span>
                                            <div class="flex flex-col">
                                                <span class="font-medium text-gray-700 leading-tight">Budi</span>
                                                <span class="font-medium text-gray-700 leading-tight">Santoso</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-1 bg-orange-100 text-orange-700 rounded-full text-[10px] font-bold tracking-wide">Siap Ambil</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button class="text-gray-400 hover:text-green-600 transition-colors p-1 rounded-lg">
                                            <span class="material-symbols-rounded text-[20px]">check_circle</span>
                                        </button>
                                    </td>
                                </tr>

                                <!-- Row 3 -->
                                <tr class="hover:bg-blue-50/30 transition-colors">
                                    <td class="px-6 py-4">
                                        <a href="#" class="font-bold text-blue-600 hover:underline">#GLN-TKR-0043</a>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-start gap-3">
                                            <div class="w-2 h-2 rounded-full bg-cyan-500 mt-1.5 shrink-0"></div>
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-800 leading-tight">Tukar Galon</span>
                                                <span class="font-medium text-gray-500 text-xs">Galon Aqua 19L</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-gray-800">Kantin ILP Central</span>
                                            <span class="text-xs text-gray-500">Langganan Mitra</span>
                                            <span class="text-[10px] text-gray-400 font-mono mt-0.5">Internal</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="font-bold text-gray-900">6 Unit Galon</span>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-gray-600 text-xs">
                                        24 Okt,<br>09:45 WIB
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-start gap-2">
                                            <span class="material-symbols-rounded text-gray-400 text-[16px] mt-0.5">person</span>
                                            <div class="flex flex-col">
                                                <span class="font-medium text-gray-700 leading-tight">Siti</span>
                                                <span class="font-medium text-gray-700 leading-tight">Rahmawati</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-1 bg-green-100 text-green-700 rounded-full text-[10px] font-bold tracking-wide">Selesai</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button class="text-gray-400 hover:text-blue-600 transition-colors p-1 rounded-lg">
                                            <span class="material-symbols-rounded text-[20px]">print</span>
                                        </button>
                                    </td>
                                </tr>

                                <!-- Row 4 -->
                                <tr class="hover:bg-blue-50/30 transition-colors">
                                    <td class="px-6 py-4">
                                        <a href="#" class="font-bold text-blue-600 hover:underline">#PLN-DIG-9218</a>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-start gap-3">
                                            <div class="w-2 h-2 rounded-full bg-orange-600 mt-1.5 shrink-0"></div>
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-800 leading-tight">Token PLN</span>
                                                <span class="font-medium text-gray-500 text-xs">Prabayar 100k</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-gray-800">Operasional Freezer #02</span>
                                            <span class="text-xs text-gray-500">Meter: 3201-9981-</span>
                                            <span class="text-[10px] text-gray-400 font-mono mt-0.5">4412</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="font-bold text-gray-900">Rp 102.500</span>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-gray-600 text-xs">
                                        24 Okt,<br>10:15 WIB
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-start gap-2">
                                            <span class="material-symbols-rounded text-gray-400 text-[16px] mt-0.5">person</span>
                                            <div class="flex flex-col">
                                                <span class="font-medium text-gray-700 leading-tight">Budi</span>
                                                <span class="font-medium text-gray-700 leading-tight">Santoso</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-1 bg-green-100 text-green-700 rounded-full text-[10px] font-bold tracking-wide">Selesai</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button class="text-gray-400 hover:text-blue-600 transition-colors p-1 rounded-lg">
                                            <span class="material-symbols-rounded text-[20px]">receipt</span>
                                        </button>
                                    </td>
                                </tr>

                                <!-- Row 5 -->
                                <tr class="hover:bg-blue-50/30 transition-colors">
                                    <td class="px-6 py-4">
                                        <a href="#" class="font-bold text-blue-600 hover:underline">#KPN-BBM-8842</a>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-start gap-3">
                                            <div class="w-2 h-2 rounded-full bg-blue-500 mt-1.5 shrink-0"></div>
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-800 leading-tight">BBM Bio Solar</span>
                                                <span class="font-medium text-gray-500 text-xs">Genset Toko</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-gray-800">Tim Maintenance Listrik</span>
                                            <span class="text-xs text-gray-500">Pemeliharaan Rutin</span>
                                            <span class="text-[10px] text-gray-400 font-mono mt-0.5">Genset</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="font-bold text-gray-900">35 Liter</span>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-gray-600 text-xs">
                                        24 Okt,<br>10:40 WIB
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-start gap-2">
                                            <span class="material-symbols-rounded text-gray-400 text-[16px] mt-0.5">person</span>
                                            <div class="flex flex-col">
                                                <span class="font-medium text-gray-700 leading-tight">Siti</span>
                                                <span class="font-medium text-gray-700 leading-tight">Rahmawati</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-1 bg-green-100 text-green-700 rounded-full text-[10px] font-bold tracking-wide">Tervalidasi</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button class="text-gray-400 hover:text-blue-600 transition-colors p-1 rounded-lg">
                                            <span class="material-symbols-rounded text-[20px]">receipt_long</span>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>
@endsection
