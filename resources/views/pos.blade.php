@extends('layouts.app')

@section('title', 'POS - ILP POS')

@section('content')
<div x-data="{ showHoldModal: false, showPaymentModal: false }" class="flex w-full h-full relative text-gray-800">
    <!-- Sidebar -->
    <aside class="w-64 bg-slate-100 border-r border-gray-200 flex flex-col justify-between shrink-0">
        <div>
            <div class="p-6">
                <h1 class="text-3xl font-extrabold text-blue-800 tracking-tight">ILP POS</h1>
            </div>
            
            <nav class="mt-2 px-4 flex flex-col gap-2">
                <a href="#" class="flex items-center gap-3 px-4 py-3 bg-blue-200/50 text-blue-800 rounded-lg font-bold">
                    <span class="material-symbols-rounded filled text-blue-700">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a href="#" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-200/50 rounded-lg font-medium transition-colors">
                    <span class="material-symbols-rounded text-gray-500">inventory_2</span>
                    <span>Inventory</span>
                </a>
                <a href="#" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-200/50 rounded-lg font-medium transition-colors">
                    <span class="material-symbols-rounded text-gray-500">point_of_sale</span>
                    <span>Sales</span>
                </a>
                <a href="#" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-200/50 rounded-lg font-medium transition-colors">
                    <span class="material-symbols-rounded text-gray-500">group</span>
                    <span>Customers</span>
                </a>
                <a href="#" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-200/50 rounded-lg font-medium transition-colors">
                    <span class="material-symbols-rounded text-gray-500">bar_chart</span>
                    <span>Reports</span>
                </a>
            </nav>
        </div>

        <div class="px-4 pb-6 flex flex-col gap-2">
            <a href="#" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-200/50 rounded-lg font-medium transition-colors">
                <span class="material-symbols-rounded text-gray-500">settings</span>
                <span>Settings</span>
            </a>
            <a href="#" class="flex items-center gap-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg font-medium transition-colors">
                <span class="material-symbols-rounded text-red-500">logout</span>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Section -->
    <main class="flex-1 flex flex-col bg-slate-50 min-w-0">
        <!-- Header -->
        <header class="h-20 border-b border-gray-200 bg-slate-100 flex items-center justify-between px-8 shrink-0">
            <div class="flex flex-col">
                <span class="text-xs font-bold text-gray-500 tracking-wider">TERMINAL</span>
                <h2 class="text-lg font-bold text-gray-800">#01 - Minimarket Retail</h2>
            </div>
            
            <div class="flex items-center gap-6">
                <!-- User Profile -->
                <div class="flex items-center gap-3 bg-gray-200/60 px-4 py-2 rounded-lg">
                    <span class="material-symbols-rounded text-blue-600 bg-white p-1 rounded-full shadow-sm">person</span>
                    <div class="flex flex-col">
                        <span class="text-sm font-bold text-gray-800 leading-tight">Andi Pratama</span>
                        <span class="text-[10px] font-bold text-gray-500 tracking-wider">SHIFT PAGI (07:00 - 15:00)</span>
                    </div>
                </div>
                
                <div class="flex items-center gap-4 text-gray-500">
                    <button class="hover:text-gray-700 relative">
                        <span class="material-symbols-rounded">notifications</span>
                        <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full border border-slate-100"></span>
                    </button>
                    <button class="hover:text-gray-700">
                        <span class="material-symbols-rounded">wifi</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Content Area -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Left Side: Products -->
            <div class="flex-1 flex flex-col p-6 overflow-hidden">
                
                <!-- Search Box -->
                <div class="bg-gray-200/50 rounded-xl p-4 mb-6">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-bold text-gray-800 text-lg">Input Barcode / Produk</h3>
                        <span class="text-[10px] font-bold text-gray-500 tracking-wider bg-gray-200 px-2 py-1 rounded">SHORTCUT: F2</span>
                    </div>
                    <div class="relative">
                        <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">barcode_scanner</span>
                        <input type="text" placeholder="Scan barcode atau ketik nama produk..." class="w-full pl-12 pr-4 py-4 rounded-xl border border-gray-300 bg-gray-100/50 shadow-inner focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white transition-colors text-sm font-medium placeholder-gray-500">
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex gap-2 overflow-x-auto pb-4 scrollbar-hide shrink-0">
                    <button class="px-5 py-2 bg-blue-700 text-white font-bold rounded-full text-sm shadow-sm whitespace-nowrap">Semua</button>
                    <button class="px-5 py-2 bg-white border border-gray-200 text-gray-600 font-bold rounded-full text-sm hover:bg-gray-50 whitespace-nowrap shadow-sm">Minuman</button>
                    <button class="px-5 py-2 bg-white border border-gray-200 text-gray-600 font-bold rounded-full text-sm hover:bg-gray-50 whitespace-nowrap shadow-sm">Snack</button>
                    <button class="px-5 py-2 bg-white border border-gray-200 text-gray-600 font-bold rounded-full text-sm hover:bg-gray-50 whitespace-nowrap shadow-sm">Kebutuhan Pokok</button>
                </div>

                <!-- Product Grid -->
                <div class="flex-1 overflow-y-auto mt-2 pb-4">
                    <div class="grid grid-cols-3 xl:grid-cols-4 gap-4">
                        <!-- Product 1 -->
                        <div class="bg-white border border-gray-200 rounded-xl p-3 shadow-sm hover:shadow-md cursor-pointer transition-shadow flex flex-col h-48 relative overflow-hidden group">
                            <div class="h-24 bg-gray-100 rounded-lg mb-3 flex items-center justify-center p-2">
                                <span class="material-symbols-rounded text-gray-300 text-4xl">cookie</span>
                            </div>
                            <div class="flex-1 flex flex-col justify-between">
                                <h4 class="font-bold text-gray-800 text-sm leading-tight line-clamp-2">Oreo Vanilla 133g</h4>
                                <div class="flex items-center justify-between mt-1">
                                    <div class="flex flex-col">
                                        <span class="text-blue-700 font-bold text-sm">Rp 8.200</span>
                                        <span class="text-xs text-gray-400 line-through">Rp 9.500</span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between mt-2 text-[10px] font-bold">
                                    <span class="text-gray-500">STOK: 12 PCS</span>
                                    <span class="text-red-500">EXPIRED H-3</span>
                                </div>
                            </div>
                        </div>

                        <!-- Product 2 -->
                        <div class="bg-white border border-gray-200 rounded-xl p-3 shadow-sm hover:shadow-md cursor-pointer transition-shadow flex flex-col h-48 relative">
                            <div class="h-24 bg-gray-100 rounded-lg mb-3 flex items-center justify-center p-2">
                                <span class="material-symbols-rounded text-gray-300 text-4xl">ramen_dining</span>
                            </div>
                            <div class="flex-1 flex flex-col justify-between">
                                <h4 class="font-bold text-gray-800 text-sm leading-tight line-clamp-2">Indomie Goreng Spcl</h4>
                                <div class="mt-1">
                                    <span class="text-blue-700 font-bold text-sm">Rp 3.200</span>
                                </div>
                                <div class="flex items-center justify-between mt-2 text-[10px] font-bold">
                                    <span class="text-gray-500">STOK: 512 PCS</span>
                                    <span class="text-green-500 bg-green-50 px-1.5 py-0.5 rounded">POPULAR</span>
                                </div>
                            </div>
                        </div>

                        <!-- See All -->
                        <div class="bg-gray-100 border-2 border-dashed border-gray-300 rounded-xl p-4 shadow-sm hover:bg-gray-200 cursor-pointer transition-colors flex flex-col items-center justify-center h-48 text-gray-500">
                            <span class="material-symbols-rounded text-3xl mb-2">add_circle</span>
                            <span class="text-xs font-bold tracking-wider">LIHAT SEMUA</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Cart -->
            <div class="w-[400px] bg-slate-200/50 border-l border-gray-300 flex flex-col shadow-inner z-10 shrink-0">
                <!-- Cart Header -->
                <div class="p-6 border-b border-gray-300/60 flex items-start justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Keranjang Belanja</h2>
                        <p class="text-sm text-gray-500 font-medium mt-1">No. Transaksi: #TRX-99201</p>
                    </div>
                    <button class="text-red-500 hover:bg-red-50 p-2 rounded-lg transition-colors">
                        <span class="material-symbols-rounded">delete_sweep</span>
                    </button>
                </div>

                <!-- Cart Items -->
                <div class="flex-1 overflow-y-auto p-4 space-y-3">
                    
                    <!-- Item 1 -->
                    <div class="bg-white p-3 rounded-xl shadow-sm border border-gray-100 flex gap-3">
                        <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center shrink-0">
                             <span class="material-symbols-rounded text-gray-400">ramen_dining</span>
                        </div>
                        <div class="flex-1 flex flex-col justify-between">
                            <div class="flex justify-between items-start">
                                <h4 class="font-bold text-gray-800 text-sm leading-tight">Indomie Goreng Spcl</h4>
                                <span class="font-bold text-gray-900 text-sm">Rp 9.600</span>
                            </div>
                            <div class="flex justify-between items-end mt-2">
                                <span class="text-xs text-gray-500 font-medium">3 x Rp 3.200</span>
                                <div class="flex items-center bg-gray-100 rounded-lg p-0.5">
                                    <button class="w-7 h-7 flex items-center justify-center text-gray-600 hover:bg-white rounded shadow-sm transition-colors font-bold">-</button>
                                    <span class="w-8 text-center font-bold text-sm">3</span>
                                    <button class="w-7 h-7 flex items-center justify-center text-gray-600 hover:bg-white rounded shadow-sm transition-colors font-bold">+</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Item 2 -->
                    <div class="bg-white p-3 rounded-xl shadow-sm border border-gray-100 flex gap-3 relative">
                        <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center shrink-0">
                             <span class="material-symbols-rounded text-gray-400">cookie</span>
                        </div>
                        <div class="flex-1 flex flex-col justify-between">
                            <div class="flex justify-between items-start">
                                <h4 class="font-bold text-gray-800 text-sm leading-tight">Oreo Vanilla 133g</h4>
                                <span class="font-bold text-gray-900 text-sm">Rp 8.200</span>
                            </div>
                            <div class="flex justify-between items-end mt-2">
                                <div class="flex flex-col">
                                    <span class="text-xs text-gray-500 font-medium line-through decoration-red-500">1 x Rp 9.500</span>
                                    <span class="text-[10px] text-red-600 font-bold">DISC EXPIRED -Rp 1.300</span>
                                </div>
                                <div class="flex items-center bg-gray-100 rounded-lg p-0.5">
                                    <button class="w-7 h-7 flex items-center justify-center text-gray-600 hover:bg-white rounded shadow-sm transition-colors font-bold">-</button>
                                    <span class="w-8 text-center font-bold text-sm">1</span>
                                    <button class="w-7 h-7 flex items-center justify-center text-gray-600 hover:bg-white rounded shadow-sm transition-colors font-bold">+</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Item 3 -->
                    <div class="bg-white p-3 rounded-xl shadow-sm border border-gray-100 flex gap-3">
                        <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center shrink-0">
                             <span class="material-symbols-rounded text-gray-400">local_cafe</span>
                        </div>
                        <div class="flex-1 flex flex-col justify-between">
                            <div class="flex justify-between items-start">
                                <h4 class="font-bold text-gray-800 text-sm leading-tight">Teh Pucuk 350ml</h4>
                                <span class="font-bold text-gray-900 text-sm">Rp 8.000</span>
                            </div>
                            <div class="flex justify-between items-end mt-2">
                                <span class="text-xs text-gray-500 font-medium">2 x Rp 4.000</span>
                                <div class="flex items-center bg-gray-100 rounded-lg p-0.5">
                                    <button class="w-7 h-7 flex items-center justify-center text-gray-600 hover:bg-white rounded shadow-sm transition-colors font-bold">-</button>
                                    <span class="w-8 text-center font-bold text-sm">2</span>
                                    <button class="w-7 h-7 flex items-center justify-center text-gray-600 hover:bg-white rounded shadow-sm transition-colors font-bold">+</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Totals & Actions -->
                <div class="bg-gray-100 border-t border-gray-300 p-6 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
                    <div class="space-y-2 mb-4 text-sm">
                        <div class="flex justify-between text-gray-600 font-medium">
                            <span>Subtotal</span>
                            <span>Rp 27.100</span>
                        </div>
                        <div class="flex justify-between text-red-500 font-bold">
                            <span>Diskon Harga</span>
                            <span>-Rp 1.300</span>
                        </div>
                        <div class="flex justify-between text-gray-600 font-medium">
                            <span>Pajak (PPN 11%)</span>
                            <span>Rp 2.838</span>
                        </div>
                    </div>

                    <div class="border-t border-gray-300 pt-4 mb-6">
                        <div class="flex justify-between items-end">
                            <span class="text-xs font-bold text-gray-500 tracking-wider">TOTAL PEMBAYARAN</span>
                            <span class="text-xs font-bold text-gray-500">6 Items</span>
                        </div>
                        <div class="text-4xl font-black text-blue-800 mt-1">
                            <span class="text-2xl mr-1 text-blue-600">Rp</span>28.638
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button @click="showHoldModal = true" class="flex-1 flex flex-col items-center justify-center gap-1 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 py-3 rounded-xl shadow-sm font-bold transition-colors">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-rounded">pause_circle</span>
                                <span>Tahan Bill</span>
                            </div>
                            <span class="text-[10px] text-gray-400 tracking-widest">F3</span>
                        </button>
                        <button @click="showPaymentModal = true" class="flex-[1.5] flex flex-col items-center justify-center gap-1 bg-[#0b3c9a] hover:bg-blue-800 text-white py-3 rounded-xl shadow-md font-bold transition-colors">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-rounded">payments</span>
                                <span>Bayar</span>
                            </div>
                            <span class="text-[10px] text-blue-300 tracking-widest">F4</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Overlay for Modals -->
    <div x-show="showHoldModal || showPaymentModal" 
         x-transition.opacity
         style="display: none;" 
         class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-40 flex items-center justify-center">
        
        <!-- MODAL 1: Daftar Bill Tertahan -->
        <div x-show="showHoldModal" 
             @click.away="showHoldModal = false"
             x-transition.scale.origin.bottom
             class="bg-white rounded-2xl shadow-2xl w-[600px] overflow-hidden flex flex-col"
             style="display: none;">
            
            <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-gray-50/50">
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Daftar Bill Tertahan</h3>
                    <p class="text-sm text-gray-500 mt-1">Kelola transaksi yang sedang ditangguhkan</p>
                </div>
                <button @click="showHoldModal = false" class="text-gray-400 hover:text-gray-700 transition-colors">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>

            <div class="p-6 space-y-4 max-h-[400px] overflow-y-auto bg-gray-50/30">
                <!-- Bill 1 -->
                <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center justify-between shadow-sm hover:border-blue-300 transition-colors cursor-pointer group">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <span class="material-symbols-rounded">pause</span>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-900 text-lg">Budi Santoso</h4>
                            <div class="flex items-center gap-2 text-sm text-gray-500 font-mono mt-1">
                                <span>10:45</span>
                                <span>•</span>
                                <span>12 Items</span>
                                <span>•</span>
                                <span class="font-bold text-gray-700">Rp 145.000</span>
                            </div>
                        </div>
                    </div>
                    <button class="bg-[#0b3c9a] hover:bg-blue-800 text-white font-bold py-2.5 px-6 rounded-lg shadow-sm">
                        Recall
                    </button>
                </div>

                <!-- Bill 2 -->
                <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center justify-between shadow-sm hover:border-blue-300 transition-colors cursor-pointer group">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <span class="material-symbols-rounded">pause</span>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-900 text-lg">Walk-in Customer</h4>
                            <div class="flex items-center gap-2 text-sm text-gray-500 font-mono mt-1">
                                <span>11:12</span>
                                <span>•</span>
                                <span>4 Items</span>
                                <span>•</span>
                                <span class="font-bold text-gray-700">Rp 42.500</span>
                            </div>
                        </div>
                    </div>
                    <button class="bg-[#0b3c9a] hover:bg-blue-800 text-white font-bold py-2.5 px-6 rounded-lg shadow-sm">
                        Recall
                    </button>
                </div>

                <!-- Bill 3 -->
                <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center justify-between shadow-sm hover:border-blue-300 transition-colors cursor-pointer group">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <span class="material-symbols-rounded">pause</span>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-900 text-lg">Siti Aminah</h4>
                            <div class="flex items-center gap-2 text-sm text-gray-500 font-mono mt-1">
                                <span>11:30</span>
                                <span>•</span>
                                <span>8 Items</span>
                                <span>•</span>
                                <span class="font-bold text-gray-700">Rp 89.200</span>
                            </div>
                        </div>
                    </div>
                    <button class="bg-[#0b3c9a] hover:bg-blue-800 text-white font-bold py-2.5 px-6 rounded-lg shadow-sm">
                        Recall
                    </button>
                </div>
            </div>

            <div class="p-6 border-t border-gray-200 bg-gray-50 flex justify-between items-center">
                <p class="text-sm text-gray-500">Gunakan tombol panah untuk navigasi</p>
                <button @click="showHoldModal = false" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold py-2.5 px-6 rounded-lg shadow-sm">
                    Tutup
                </button>
            </div>
        </div>

        <!-- MODAL 2: Konfirmasi Pembayaran -->
        <div x-show="showPaymentModal" 
             @click.away="showPaymentModal = false"
             x-transition.scale.origin.bottom
             class="bg-white rounded-2xl shadow-2xl w-[750px] overflow-hidden flex flex-col"
             style="display: none;">
            
            <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-gray-50/50">
                <div>
                    <h3 class="text-2xl font-bold text-blue-800">Konfirmasi Pembayaran</h3>
                    <p class="text-sm text-gray-500 mt-1">Pilih metode dan masukkan nominal pembayaran</p>
                </div>
                <button @click="showPaymentModal = false" class="text-gray-400 hover:text-gray-700 transition-colors">
                    <span class="material-symbols-rounded text-3xl">close</span>
                </button>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-gray-200 px-6 pt-4 bg-gray-50/30">
                <button class="px-8 py-3 font-bold text-blue-700 border-b-2 border-blue-600 flex items-center gap-2">
                    <span class="material-symbols-rounded">payments</span> Tunai
                </button>
                <button class="px-8 py-3 font-bold text-gray-500 hover:text-gray-700 border-b-2 border-transparent flex items-center gap-2">
                    <span class="material-symbols-rounded">qr_code_2</span> QRIS
                </button>
                <button class="px-8 py-3 font-bold text-gray-500 hover:text-gray-700 border-b-2 border-transparent flex items-center gap-2">
                    <span class="material-symbols-rounded">credit_card</span> Kredit Anggota
                </button>
            </div>

            <div class="flex p-6 gap-8">
                <!-- Left: Input Area -->
                <div class="flex-1 flex flex-col gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-xs font-bold text-gray-500 tracking-wider">TUNAI DITERIMA</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xl font-bold text-blue-800">Rp</span>
                            <input type="text" value="50.000" class="w-full pl-14 pr-4 py-4 rounded-xl border-2 border-blue-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 text-3xl font-mono font-bold text-blue-800 text-right shadow-inner outline-none transition-all">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3 mt-2">
                        <button class="py-3 px-4 border border-gray-200 hover:border-blue-300 hover:bg-blue-50 rounded-xl font-mono font-bold text-lg text-gray-700 transition-colors shadow-sm bg-white">
                            Rp 30.000
                        </button>
                        <button class="py-3 px-4 border border-gray-200 hover:border-blue-300 hover:bg-blue-50 rounded-xl font-mono font-bold text-lg text-gray-700 transition-colors shadow-sm bg-white">
                            Rp 50.000
                        </button>
                        <button class="py-3 px-4 border border-gray-200 hover:border-blue-300 hover:bg-blue-50 rounded-xl font-mono font-bold text-lg text-gray-700 transition-colors shadow-sm bg-white">
                            Rp 100.000
                        </button>
                        <button class="py-3 px-4 border border-gray-200 hover:border-blue-300 hover:bg-blue-50 rounded-xl font-bold text-lg text-gray-700 transition-colors shadow-sm bg-white">
                            Uang Pas
                        </button>
                    </div>
                </div>

                <!-- Right: Summary -->
                <div class="w-64 bg-gray-50 rounded-xl p-5 border border-gray-200 flex flex-col justify-center gap-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-600">Total Tagihan</span>
                        <span class="font-mono font-bold text-gray-900">Rp 28.638</span>
                    </div>
                    <div class="flex justify-between items-center border-b border-gray-200 pb-4">
                        <span class="text-sm font-medium text-gray-600">Diterima</span>
                        <span class="font-mono font-bold text-gray-900">Rp 50.000</span>
                    </div>
                    
                    <div class="pt-2">
                        <span class="text-xs font-bold text-gray-500 tracking-wider">KEMBALIAN</span>
                        <div class="text-3xl font-mono font-black text-green-600 mt-1">
                            <span class="text-lg mr-1 text-green-500">Rp</span>21.362
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-6 border-t border-gray-200 flex justify-center gap-4 bg-gray-50/50">
                <button @click="showPaymentModal = false" class="bg-white border border-gray-300 hover:bg-gray-100 text-gray-700 font-bold py-3 px-8 rounded-xl shadow-sm transition-colors w-48">
                    Batal
                </button>
                <button class="bg-[#0b3c9a] hover:bg-blue-800 text-white font-bold py-3 px-8 rounded-xl shadow-md transition-colors flex items-center justify-center gap-2 flex-1">
                    <span class="material-symbols-rounded">print</span>
                    Selesaikan & Cetak Struk
                </button>
            </div>
        </div>

    </div>
</div>
@endsection
