@extends('layouts.app')

@section('title', 'Retur Barang - ILP POS')

@section('content')
<div class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800">
    
    <!-- Sidebar -->
    <x-cashier-sidebar active="retur" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        <!-- Top Header -->
        <x-cashier-header title="Retur Barang" />

        <!-- Scrollable Area -->
        <div class="flex-1 overflow-auto p-8">
            <div class="max-w-[1400px] min-w-[900px] mx-auto space-y-5">
                
                <!-- Breadcrumb & Title -->
                <div>
                    <div class="flex items-center gap-2 text-[10px] font-bold text-gray-500 tracking-wider mb-2">
                        <span>OPERASIONAL KASIR</span>
                        <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                        <span>INVENTARIS TOKO</span>
                        <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                        <span class="text-blue-600">FORM RETUR KE GUDANG</span>
                    </div>
                    <h1 class="text-2xl font-black text-gray-900 tracking-tight">Input Retur Barang (Toko <span class="material-symbols-rounded relative top-1 text-[24px]">arrow_forward</span> Gudang DC)</h1>
                </div>

                <!-- Info Cards & Notice -->
                <div class="flex flex-col gap-4">
                    <div class="flex gap-4">
                        <div class="bg-white border border-gray-200 rounded-xl px-4 py-3 flex items-center gap-3 shadow-sm">
                            <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                                <span class="material-symbols-rounded">person</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-sm font-bold text-gray-900">Siti Rahmawati</span>
                                <span class="text-[10px] font-bold text-gray-500 tracking-wider">TERMINAL #01 • SHIFT PAGI</span>
                            </div>
                        </div>
                        <div class="bg-white border border-gray-200 rounded-xl px-4 py-3 flex items-center gap-3 shadow-sm">
                            <div class="w-10 h-10 rounded-lg bg-green-50 text-green-600 flex items-center justify-center">
                                <span class="material-symbols-rounded">cloud_sync</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-[10px] font-bold text-green-600 tracking-wider">ONLINE TERHUBUNG</span>
                                <span class="text-xs font-bold text-gray-900">ERP DC CAKUNG V4.2</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-[#f0f4ff] border border-blue-200 rounded-xl p-4 flex items-center justify-between shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-lg bg-[#0052cc] text-white flex items-center justify-center shrink-0">
                                <span class="material-symbols-rounded">local_shipping</span>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 text-sm">Kebijakan Ritase Armada Retur Cabang Toko</h3>
                                <p class="text-[13px] text-gray-600 mt-0.5">Biaya sewa ritase logistik antar cabang &rarr; gudang DC terstandarisasi flat <span class="font-bold text-blue-600">Rp 500.000 / pengiriman truk</span>. Kasir fokus verifikasi fisik, scan SKU, input alasan, dan foto kemasan.</p>
                            </div>
                        </div>
                        <div class="bg-white px-3 py-1.5 rounded-lg border border-gray-200 text-[11px] font-bold text-gray-700 tracking-wider shrink-0">
                            BATCH AKTIF: #BTH-RET-20250519-01
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-5 gap-4 min-w-[900px]">
                    <!-- Stat 1 -->
                    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm relative">
                        <span class="material-symbols-rounded absolute right-4 top-4 text-gray-300 text-[20px]">inventory_2</span>
                        <span class="text-[10px] font-bold text-gray-500 tracking-wider">DRAFT MUAT HARI INI</span>
                        <div class="flex items-baseline gap-2 mt-2">
                            <h3 class="text-2xl font-black text-gray-900">18</h3>
                            <span class="text-xs font-bold text-gray-500">Pcs / Kemasan</span>
                        </div>
                        <div class="flex items-center gap-1 mt-2 text-[10px] font-bold text-green-600">
                            <span class="material-symbols-rounded text-[14px]">check_circle</span>
                            <span>4 Kategori SKU Masuk</span>
                        </div>
                    </div>
                    <!-- Stat 2 -->
                    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm relative">
                        <span class="material-symbols-rounded absolute right-4 top-4 text-red-300 text-[20px]">broken_image</span>
                        <span class="text-[10px] font-bold text-red-600 tracking-wider">RUSAK / BOCOR</span>
                        <div class="flex items-baseline gap-2 mt-2">
                            <h3 class="text-2xl font-black text-red-600">8</h3>
                            <span class="text-xs font-bold text-gray-500">SKU</span>
                        </div>
                        <p class="text-[10px] font-bold text-gray-500 mt-2 leading-tight">Bocor karton, tutup pecah, kemasan sobek</p>
                    </div>
                    <!-- Stat 3 -->
                    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm relative">
                        <span class="material-symbols-rounded absolute right-4 top-4 text-orange-300 text-[20px]">event_busy</span>
                        <span class="text-[10px] font-bold text-orange-600 tracking-wider">KEDALUWARSA / ED</span>
                        <div class="flex items-baseline gap-2 mt-2">
                            <h3 class="text-2xl font-black text-orange-600">7</h3>
                            <span class="text-xs font-bold text-gray-500">SKU</span>
                        </div>
                        <p class="text-[10px] font-bold text-gray-500 mt-2 leading-tight">Mendekati H-7 s/d H-14 batas pajang</p>
                    </div>
                    <!-- Stat 4 -->
                    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm relative">
                        <span class="material-symbols-rounded absolute right-4 top-4 text-blue-300 text-[20px]">move_up</span>
                        <span class="text-[10px] font-bold text-blue-600 tracking-wider">MUTASI OVERSTOCK</span>
                        <div class="flex items-baseline gap-2 mt-2">
                            <h3 class="text-2xl font-black text-blue-600">3</h3>
                            <span class="text-xs font-bold text-gray-500">SKU</span>
                        </div>
                        <p class="text-[10px] font-bold text-gray-500 mt-2 leading-tight">Slow-moving rak promo habis masa</p>
                    </div>
                    <!-- Stat 5 -->
                    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm relative border-l-4 border-l-green-500">
                        <span class="material-symbols-rounded absolute right-4 top-4 text-gray-300 text-[20px]">payments</span>
                        <span class="text-[10px] font-bold text-gray-500 tracking-wider">ESTIMASI NILAI RETUR</span>
                        <div class="mt-2">
                            <h3 class="text-lg font-black text-gray-900 font-mono">Rp 1.845.000</h3>
                        </div>
                        <p class="text-[10px] font-bold text-gray-500 mt-1 leading-tight">Nilai Klaim Pokok Beli Toko</p>
                        <div class="flex items-center gap-1 mt-2 text-[10px] font-bold text-green-600">
                            <span class="material-symbols-rounded text-[14px]">shield</span>
                            <span>Klaim Dijamin DC</span>
                        </div>
                    </div>
                </div>

                <!-- Main Work Area -->
                <div class="grid grid-cols-12 gap-6 min-w-[900px]">
                    
                    <!-- Left: Input Form -->
                    <div class="col-span-5 bg-white border border-gray-200 rounded-xl shadow-sm p-6 flex flex-col gap-5">
                        
                        <!-- Form Header -->
                        <div class="flex items-start justify-between">
                            <div class="flex gap-3">
                                <div class="w-10 h-10 rounded-lg bg-blue-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                                    <span class="material-symbols-rounded text-[20px]">qr_code_scanner</span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-900">Form Scan Cepat Barang</h3>
                                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mt-0.5">INPUT ITEM SATU PER SATU</p>
                                </div>
                            </div>
                            <div class="bg-gray-100 px-2 py-1 rounded text-[10px] font-bold text-gray-500 text-center">
                                Shortcut<br>[F2]
                            </div>
                        </div>

                        <!-- Barcode Input -->
                        <div>
                            <div class="flex justify-between items-end mb-1.5">
                                <label class="text-[11px] font-bold text-gray-700 tracking-wider">BARCODE / SCAN EAN-13</label>
                                <a href="#" class="text-[11px] font-bold text-blue-600 hover:underline flex items-center gap-1"><span class="material-symbols-rounded text-[14px]">bolt</span>Contoh Cepat</a>
                            </div>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">qr_code</span>
                                    <input type="text" value="8992388102315" class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg font-mono text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                </div>
                                <button class="bg-[#0052cc] hover:bg-blue-700 text-white px-5 rounded-lg font-bold text-sm transition-colors shadow-sm">DETEKSI</button>
                            </div>
                        </div>

                        <!-- Product Detail Card (No Photo) -->
                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 flex gap-4">
                            <div class="w-16 h-16 bg-white border border-gray-200 rounded-lg flex flex-col items-center justify-center shrink-0 text-blue-600 shadow-sm">
                                <span class="material-symbols-rounded text-[24px]">oil_barrel</span>
                            </div>
                            <div class="flex-1">
                                <span class="text-[10px] font-bold text-blue-600 tracking-wider">MINYAK GORENG & BAHAN POKOK</span>
                                <h4 class="font-bold text-gray-900 text-sm leading-tight mt-1">Minyak Goreng Sawit 2 Liter Pouch</h4>
                                <div class="flex items-center gap-4 mt-2">
                                    <div class="flex flex-col">
                                        <span class="text-[10px] text-gray-500 font-bold">SKU:</span>
                                        <span class="text-xs font-mono font-bold text-gray-700">MYK-0021</span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-[10px] text-gray-500 font-bold">Hrg Beli:</span>
                                        <span class="text-xs font-mono font-bold text-gray-700">Rp 34.500</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Qty & Satuan -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-[11px] font-bold text-gray-700 tracking-wider block mb-1.5">KUANTITAS / JUMLAH FISIK</label>
                                <div class="flex items-center justify-between border border-gray-200 rounded-lg bg-white p-1">
                                    <button class="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded transition-colors"><span class="material-symbols-rounded text-[20px]">remove</span></button>
                                    <span class="font-bold text-gray-900">2</span>
                                    <button class="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded transition-colors"><span class="material-symbols-rounded text-[20px]">add</span></button>
                                </div>
                            </div>
                            <div>
                                <label class="text-[11px] font-bold text-gray-700 tracking-wider block mb-1.5">SATUAN ECER / KARTON</label>
                                <div class="relative">
                                    <select class="w-full pl-3 pr-8 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-[13px] font-bold text-gray-700 focus:outline-none appearance-none">
                                        <option>Pcs (Pieces/Bungkus)</option>
                                        <option>Karton / Dus</option>
                                    </select>
                                    <span class="material-symbols-rounded absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">expand_more</span>
                                </div>
                            </div>
                        </div>

                        <!-- Alasan Retur -->
                        <div>
                            <label class="text-[11px] font-bold text-gray-700 tracking-wider block mb-2">ALASAN / KONDISI RETUR FISIK</label>
                            <div class="grid grid-cols-2 gap-3">
                                <!-- Option 1 (Selected) -->
                                <label class="border-2 border-red-500 bg-red-50 rounded-lg p-3 cursor-pointer relative flex items-start gap-2">
                                    <input type="radio" name="alasan" class="mt-0.5 w-3.5 h-3.5 text-red-600 focus:ring-red-500" checked>
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-red-700 leading-tight mb-1">Rusak / Bocor / Pecah</span>
                                        <span class="text-[10px] text-gray-600 font-medium leading-tight">Sobek handling rak, kemasan penyok</span>
                                    </div>
                                </label>
                                <!-- Option 2 -->
                                <label class="border border-gray-200 bg-gray-50 hover:bg-orange-50 hover:border-orange-200 rounded-lg p-3 cursor-pointer relative flex items-start gap-2 transition-colors">
                                    <input type="radio" name="alasan" class="mt-0.5 w-3.5 h-3.5 text-orange-500 focus:ring-orange-500 border-gray-300">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-orange-600 leading-tight mb-1">Kedaluwarsa / H-14 ED</span>
                                        <span class="text-[10px] text-gray-500 font-medium leading-tight">Sudah expired / batas pajang lewat</span>
                                    </div>
                                </label>
                                <!-- Option 3 -->
                                <label class="border border-gray-200 bg-gray-50 hover:bg-blue-50 hover:border-blue-200 rounded-lg p-3 cursor-pointer relative flex items-start gap-2 transition-colors">
                                    <input type="radio" name="alasan" class="mt-0.5 w-3.5 h-3.5 text-blue-600 focus:ring-blue-500 border-gray-300">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-blue-600 leading-tight mb-1">Mutasi Overstock</span>
                                        <span class="text-[10px] text-gray-500 font-medium leading-tight">Stok beku tidak bergerak di toko</span>
                                    </div>
                                </label>
                                <!-- Option 4 -->
                                <label class="border border-gray-200 bg-gray-50 hover:bg-gray-100 rounded-lg p-3 cursor-pointer relative flex items-start gap-2 transition-colors">
                                    <input type="radio" name="alasan" class="mt-0.5 w-3.5 h-3.5 text-gray-600 focus:ring-gray-500 border-gray-300">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-gray-700 leading-tight mb-1">Cacat Segel Pabrik</span>
                                        <span class="text-[10px] text-gray-500 font-medium leading-tight">Barcode tidak terbaca / salah cetak</span>
                                    </div>
                                </label>
                            </div>
                        </div>



                        <!-- Catatan Temuan -->
                        <div>
                            <label class="text-[11px] font-bold text-gray-700 tracking-wider block mb-1.5">CATATAN TEMUAN KASIR</label>
                            <textarea rows="3" class="w-full bg-gray-50 border border-gray-200 rounded-lg p-3 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-gray-700 resize-none">Kardus minyak rembes di bagian dasar, isi berkurang sekitar 10% karena sobek handling rak.</textarea>
                        </div>

                        <!-- Buttons -->
                        <div class="flex gap-3 mt-2">
                            <button class="flex-1 bg-[#0052cc] hover:bg-blue-700 text-white py-3 rounded-xl font-bold flex items-center justify-center gap-2 shadow-sm transition-colors">
                                <span class="material-symbols-rounded text-[20px]">add_task</span>
                                Masukkan ke Draft Retur
                            </button>
                            <button class="w-12 h-12 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl flex items-center justify-center shrink-0 transition-colors">
                                <span class="material-symbols-rounded">refresh</span>
                            </button>
                        </div>
                    </div>

                    <!-- Right: Table & Summary -->
                    <div class="col-span-7 flex flex-col gap-5">
                        
                        <!-- Table Card -->
                        <div class="bg-white border border-gray-200 rounded-xl shadow-sm flex flex-col">
                            <div class="px-6 py-4 flex justify-between items-center border-b border-gray-200">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded bg-gray-100 flex items-center justify-center text-gray-500">
                                        <span class="material-symbols-rounded text-[18px]">view_list</span>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h3 class="font-bold text-gray-900">Daftar Muatan Batch Retur Siap Kirim</h3>
                                            <span class="bg-blue-100 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded">4 SKU TERDAFTAR</span>
                                        </div>
                                        <p class="text-[10px] font-bold text-gray-500 tracking-wider uppercase mt-0.5">TUJUAN: CENTRAL DISTRIBUTION CENTER (DC CAKUNG 01)</p>
                                    </div>
                                </div>
                                <button class="border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-bold text-[11px] px-3 py-1.5 rounded flex items-center gap-1 transition-colors">
                                    <span class="material-symbols-rounded text-[16px]">print</span> LABEL BARCODE RETUR
                                </button>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-[13px] whitespace-nowrap">
                                    <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 text-[10px] font-bold tracking-wider uppercase">
                                        <tr>
                                            <th class="px-5 py-3">PRODUK & SKU</th>
                                            <th class="px-5 py-3">KATEGORI / ALASAN</th>
                                            <th class="px-5 py-3 text-center">QTY</th>
                                            <th class="px-5 py-3 text-right">NILAI KLAIM</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <!-- Row 1 -->
                                        <tr class="hover:bg-blue-50/30 transition-colors">
                                            <td class="px-5 py-3">
                                                <div class="flex gap-3 items-center">
                                                    <div class="w-10 h-10 rounded border border-gray-200 bg-white flex items-center justify-center text-gray-400 shrink-0">
                                                        <span class="material-symbols-rounded text-[20px]">local_drink</span>
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span class="font-bold text-gray-800">Susu UHT Full Cream 1000ml</span>
                                                        <span class="text-[11px] font-mono text-gray-500 mt-0.5">SKU-SUSU-0914</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3">
                                                <div class="flex flex-col items-start gap-1">
                                                    <span class="bg-red-50 text-red-600 border border-red-200 text-[10px] font-bold px-2 py-0.5 rounded flex items-center gap-1"><span class="material-symbols-rounded text-[12px]">broken_image</span> RUSAK / BOCOR</span>
                                                    <span class="text-[11px] text-gray-600">Tutup jebol di display</span>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3 text-center">
                                                <div class="flex flex-col items-center">
                                                    <span class="font-black text-gray-900 text-base">4</span>
                                                    <span class="text-[10px] text-gray-500 font-bold">Karton</span>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3 text-right">
                                                <div class="flex flex-col items-end">
                                                    <span class="font-mono font-bold text-gray-900">Rp 540.000</span>
                                                    <span class="text-[10px] text-gray-400">@ 135.000 / karton</span>
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Row 2 -->
                                        <tr class="hover:bg-blue-50/30 transition-colors">
                                            <td class="px-5 py-3">
                                                <div class="flex gap-3 items-center">
                                                    <div class="w-10 h-10 rounded border border-gray-200 bg-white flex items-center justify-center text-gray-400 shrink-0">
                                                        <span class="material-symbols-rounded text-[20px]">bakery_dining</span>
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span class="font-bold text-gray-800">Roti Tawar Gandum Spesial</span>
                                                        <span class="text-[11px] font-mono text-gray-500 mt-0.5">SKU-ROTI-0102</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3">
                                                <div class="flex flex-col items-start gap-1">
                                                    <span class="bg-orange-50 text-orange-600 border border-orange-200 text-[10px] font-bold px-2 py-0.5 rounded flex items-center gap-1"><span class="material-symbols-rounded text-[12px]">event_busy</span> KEDALUWARSA ED</span>
                                                    <span class="text-[11px] text-orange-600 font-bold">Batas ED: 20 Mei (H-1)</span>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3 text-center">
                                                <div class="flex flex-col items-center">
                                                    <span class="font-black text-gray-900 text-base">5</span>
                                                    <span class="text-[10px] text-gray-500 font-bold">Pack</span>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3 text-right">
                                                <div class="flex flex-col items-end">
                                                    <span class="font-mono font-bold text-gray-900">Rp 85.000</span>
                                                    <span class="text-[10px] text-gray-400">@ 17.000 / pack</span>
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Row 3 -->
                                        <tr class="hover:bg-blue-50/30 transition-colors">
                                            <td class="px-5 py-3">
                                                <div class="flex gap-3 items-center">
                                                    <div class="w-10 h-10 rounded border border-gray-200 bg-white flex items-center justify-center text-gray-400 shrink-0">
                                                        <span class="material-symbols-rounded text-[20px]">cookie</span>
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span class="font-bold text-gray-800">Biskuit Butter Cookies Kaleng</span>
                                                        <span class="text-[11px] font-mono text-gray-500 mt-0.5">SKU-BSK-4420</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3">
                                                <div class="flex flex-col items-start gap-1">
                                                    <span class="bg-blue-50 text-blue-600 border border-blue-200 text-[10px] font-bold px-2 py-0.5 rounded flex items-center gap-1"><span class="material-symbols-rounded text-[12px]">move_up</span> MUTASI OVERSTOCK</span>
                                                    <span class="text-[11px] text-gray-600">Sisa stok musiman lebaran</span>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3 text-center">
                                                <div class="flex flex-col items-center">
                                                    <span class="font-black text-gray-900 text-base">3</span>
                                                    <span class="text-[10px] text-gray-500 font-bold">Kaleng</span>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3 text-right">
                                                <div class="flex flex-col items-end">
                                                    <span class="font-mono font-bold text-gray-900">Rp 195.000</span>
                                                    <span class="text-[10px] text-gray-400">@ 65.000 / kaleng</span>
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Row 4 -->
                                        <tr class="bg-blue-50/50 hover:bg-blue-50 transition-colors">
                                            <td class="px-5 py-3">
                                                <div class="flex gap-3 items-center">
                                                    <div class="w-10 h-10 rounded border border-gray-200 bg-white flex items-center justify-center text-gray-400 shrink-0">
                                                        <span class="material-symbols-rounded text-[20px]">oil_barrel</span>
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span class="font-bold text-gray-800">Minyak Goreng Sawit 2L</span>
                                                        <span class="text-[11px] font-mono text-gray-500 mt-0.5">SKU-MYK-0021</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3">
                                                <div class="flex flex-col items-start gap-1">
                                                    <span class="bg-red-50 text-red-600 border border-red-200 text-[10px] font-bold px-2 py-0.5 rounded flex items-center gap-1"><span class="material-symbols-rounded text-[12px]">broken_image</span> RUSAK / BOCOR</span>
                                                    <span class="text-[11px] text-gray-600">Rembes lipatan kemasan</span>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3 text-center">
                                                <div class="flex flex-col items-center">
                                                    <span class="font-black text-gray-900 text-base">6</span>
                                                    <span class="text-[10px] text-gray-500 font-bold">Pouch</span>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3 text-right">
                                                <div class="flex flex-col items-end">
                                                    <span class="font-mono font-bold text-gray-900">Rp 1.025.000</span>
                                                    <span class="text-[10px] text-gray-400">@ 170.833 / pouch</span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Summary Section -->
                        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
                            <div class="flex flex-col md:flex-row justify-between gap-6">
                                <div class="flex-1 space-y-4">
                                    <div class="flex justify-between items-end border-b border-gray-100 pb-3">
                                        <div class="flex flex-col">
                                            <span class="text-[11px] font-bold text-gray-500 tracking-wider">SUBTOTAL NILAI BARANG</span>
                                            <span class="font-mono text-xl font-bold text-gray-900 mt-1">Rp 1.845.000</span>
                                        </div>
                                    </div>
                                    <div class="flex justify-between items-center border-b border-gray-100 pb-3">
                                        <div class="flex flex-col">
                                            <span class="text-[11px] font-bold text-gray-500 tracking-wider">BIAYA EKSPEDISI ARMADA TRUK</span>
                                            <span class="font-mono text-sm font-bold text-red-600 mt-1">- Rp 500.000</span>
                                        </div>
                                        <div class="bg-green-50 border border-green-200 text-green-700 px-3 py-1.5 rounded-lg flex items-center gap-1.5 text-[10px] font-bold shadow-sm">
                                            <span class="material-symbols-rounded text-[14px]">check</span> VALIDASI DC TERSINKRON
                                        </div>
                                    </div>
                                    <div class="flex justify-between items-end pt-2">
                                        <div class="flex flex-col">
                                            <span class="text-[12px] font-bold text-blue-600 tracking-wider">ESTIMASI NET KLAIM PENGEMBALIAN</span>
                                            <span class="font-mono text-2xl font-black text-blue-600 mt-1">Rp 1.345.000</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action / Auth Box -->
                        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4 flex flex-row gap-4 items-center justify-between">
                            <div class="flex items-center gap-3 shrink-0">
                                <div class="w-10 h-10 rounded-xl bg-gray-100 text-gray-600 flex items-center justify-center shrink-0">
                                    <span class="material-symbols-rounded text-[20px]">shield_person</span>
                                </div>
                                <div class="flex flex-col">
                                    <h3 class="font-bold text-gray-900 text-[13px] leading-tight">Otorisasi Supervisor &<br>Finalisasi Batch</h3>
                                    <p class="text-[9px] font-bold text-gray-500 tracking-wider uppercase leading-snug mt-1">PERSETUJUAN KEPALA<br>TOKO SEBELUM MUAT TRUK</p>
                                </div>
                                <div class="bg-gray-100 px-3 py-1.5 rounded-lg text-[10px] font-medium text-gray-700 leading-snug shrink-0">
                                    Shift: Budi<br>Santoso
                                </div>
                            </div>
                            
                            <div class="flex items-stretch gap-2 shrink-0">
                                <div class="flex flex-col gap-2">
                                    <button class="bg-gray-50 hover:bg-gray-100 text-gray-800 rounded-lg px-3 py-2 flex items-center gap-2 transition-colors flex-1 border border-gray-200">
                                        <span class="material-symbols-rounded text-gray-600 text-[18px]">save</span>
                                        <span class="text-[11px] font-bold leading-tight text-left">Simpan<br>Draft</span>
                                    </button>
                                    <button class="bg-gray-50 hover:bg-gray-100 text-gray-800 rounded-lg px-3 py-2 flex items-center gap-2 transition-colors flex-1 border border-gray-200">
                                        <span class="material-symbols-rounded text-gray-600 text-[18px]">print</span>
                                        <span class="text-[11px] font-bold leading-tight text-left">Cetak BAST<br>Sementara</span>
                                    </button>
                                </div>
                                <button class="bg-[#0052cc] hover:bg-blue-700 text-white rounded-lg px-4 py-2 flex items-center gap-2 transition-colors shadow-sm">
                                    <span class="material-symbols-rounded text-[24px]">lock_open_right</span>
                                    <span class="text-[13px] font-bold leading-tight text-left">Kunci &<br>Ajukan ke<br>Gudang</span>
                                </button>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </main>
</div>
@endsection
