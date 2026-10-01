@extends('layouts.app')

@section('title', 'Cetak Struk - ILP POS')

@section('content')
<div class="flex flex-col min-h-screen w-full bg-[#f8f9fa] font-sans">
    
    <!-- Top Header -->
    <x-cashier-header title="Cetak Struk" />

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto bg-slate-50 relative">
        <div class="min-h-full py-12 px-4 flex justify-center items-start">
            
            <!-- Receipt Card -->
            <div class="bg-white w-full max-w-[400px] rounded-xl shadow-lg border border-gray-200 p-8 flex flex-col shrink-0 relative">
            
            <!-- Receipt Header -->
            <div class="text-center mb-5">
                <h2 class="text-2xl font-black text-[#0052cc] tracking-tight mb-1">MINIMARKET ILP</h2>
                <p class="text-[13px] font-bold text-gray-600">Cabang Utama - Jakarta Selatan</p>
                <p class="text-[12px] font-medium text-gray-400">Jl. Sudirman No. 45, Jakarta</p>
            </div>

            <!-- Separator -->
            <div class="border-t-[1.5px] border-dashed border-gray-200 my-4"></div>

            <!-- Transaction Meta -->
            <div class="flex flex-col gap-1.5 text-[13px] font-mono mb-2">
                <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">ID Transaksi:</span>
                    <span class="text-gray-900 font-bold">#TRX-99201</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">Tanggal:</span>
                    <span class="text-gray-900 font-bold">20 Mei 2024, 14:20</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">Kasir:</span>
                    <span class="text-gray-900 font-bold">Andi Pratama</span>
                </div>
            </div>

            <!-- Separator -->
            <div class="border-t-[1.5px] border-dashed border-gray-200 my-4"></div>

            <!-- Items -->
            <div class="flex flex-col gap-4 text-[13px] font-mono">
                <!-- Item 1 -->
                <div class="flex flex-col">
                    <div class="flex justify-between">
                        <span class="font-bold text-gray-800">Indomie Goreng Spcl</span>
                        <span class="font-bold text-gray-900">Rp 9.600</span>
                    </div>
                    <span class="text-gray-500 font-medium">3 x Rp 3.200</span>
                </div>
                
                <!-- Item 2 -->
                <div class="flex flex-col">
                    <div class="flex justify-between">
                        <span class="font-bold text-gray-800">Oreo Vanilla 133g</span>
                        <span class="font-bold text-gray-900">Rp 8.200</span>
                    </div>
                    <div class="flex flex-col text-gray-400 font-medium italic">
                        <span>1 x Rp 9.500, Disc -1.300</span>
                    </div>
                </div>

                <!-- Item 3 -->
                <div class="flex flex-col">
                    <div class="flex justify-between">
                        <span class="font-bold text-gray-800">Teh Pucuk 350ml</span>
                        <span class="font-bold text-gray-900">Rp 8.000</span>
                    </div>
                    <span class="text-gray-500 font-medium">2 x Rp 4.000</span>
                </div>
            </div>

            <!-- Separator -->
            <div class="border-t-[1.5px] border-dashed border-gray-200 my-4"></div>

            <!-- Subtotals -->
            <div class="flex flex-col gap-2 text-[13px] font-mono mb-4">
                <div class="flex justify-between">
                    <span class="text-gray-700 font-bold">Subtotal</span>
                    <span class="text-gray-900 font-bold">Rp 27.100</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-orange-500 font-bold">Diskon</span>
                    <span class="text-orange-500 font-bold">-Rp 1.300</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-700 font-bold">Pajak (PPN 11%)</span>
                    <span class="text-gray-900 font-bold">Rp 2.838</span>
                </div>
            </div>

            <!-- Grand Total -->
            <div class="flex justify-between items-center mb-6">
                <span class="text-[20px] font-black text-[#0052cc] tracking-wide">TOTAL</span>
                <span class="text-[20px] font-black text-[#0052cc] font-mono">Rp 28.638</span>
            </div>

            <!-- Payment Info -->
            <div class="bg-[#f3f4f6] rounded-lg p-3 flex flex-col gap-1.5 text-[13px] font-mono border border-gray-200">
                <div class="flex justify-between">
                    <span class="text-gray-800 font-bold">Bayar (Tunai)</span>
                    <span class="text-gray-900 font-bold">Rp 50.000</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-green-600 font-bold">Kembalian</span>
                    <span class="text-green-600 font-bold">Rp 21.362</span>
                </div>
            </div>

            <!-- Separator -->
            <div class="border-t-[1.5px] border-dashed border-gray-200 my-6"></div>

            <!-- QR Code Box -->
            <div class="flex flex-col items-center mb-6">
                <div class="border-[1.5px] border-[#0052cc] rounded-xl p-3 mb-2 bg-white">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=S72-99201-B2-X90" alt="QR Code" class="w-20 h-20 opacity-80" style="filter: grayscale(100%) contrast(150%);">
                </div>
                <span class="text-[11px] font-bold text-gray-500">Digital Receipt Validation</span>
                <span class="text-[9px] font-mono text-gray-400 tracking-widest mt-0.5">S72-99201-B2-X90</span>
            </div>

            <!-- Thank You -->
            <div class="text-center">
                <p class="text-[12px] font-bold text-gray-800">Terima Kasih Telah Berbelanja di Minimarket Pro!</p>
                <p class="text-[10px] font-bold text-gray-500 mt-1">Layanan Pelanggan: 0800-1-CLOUDLINKPOS</p>
            </div>
        </div>

        <!-- Floating Action Buttons -->
        <div class="fixed bottom-16 right-8 flex flex-col gap-4">
            <button class="w-14 h-14 bg-[#0052cc] hover:bg-blue-700 text-white rounded-full shadow-xl flex items-center justify-center transition-transform hover:scale-105" onclick="window.print()">
                <span class="material-symbols-rounded text-2xl">print</span>
            </button>
            <button class="w-14 h-14 bg-white border border-gray-200 text-blue-600 hover:bg-blue-50 rounded-full shadow-lg flex items-center justify-center transition-transform hover:scale-105">
                <span class="material-symbols-rounded text-2xl">share</span>
            </button>
        </div>
        </div>
    </main>

    <!-- Bottom Footer -->
    <footer class="h-14 bg-[#f1f5f9] border-t border-gray-200 flex items-center justify-between px-8 text-xs font-bold shrink-0">
        <span class="text-gray-500">© 2024 Minimarket Pro. Secure Retail Management.</span>
        <div class="flex gap-6 text-gray-500">
            <a href="#" class="hover:text-gray-800 underline underline-offset-2">Privacy Policy</a>
            <a href="#" class="hover:text-gray-800 underline underline-offset-2">Terms of Service</a>
            <a href="#" class="hover:text-gray-800 underline underline-offset-2">Contact Support</a>
        </div>
    </footer>
</div>
@endsection
