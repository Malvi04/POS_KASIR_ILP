<!-- resources/views/kasir/partials/_payment-modal.blade.php -->
<div x-show="paymentModal.open"
     style="display: none;"
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     @keydown.escape.window="paymentModal.open = false">
     
    <!-- Backdrop -->
    <div x-show="paymentModal.open" 
         x-transition.opacity.duration.300ms
         class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" 
         @click="paymentModal.open = false"></div>

    <!-- Modal Content -->
    <div x-show="paymentModal.open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col">
         
        <!-- Header -->
        <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-start">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Konfirmasi Pembayaran</h2>
                <p class="text-sm text-gray-500 mt-1">Pilih metode dan masukkan nominal pembayaran</p>
            </div>
            <button @click="paymentModal.open = false" class="text-gray-400 hover:text-gray-600 transition-colors rounded-lg p-1 hover:bg-gray-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Body -->
        <div class="flex-1 flex flex-col">
            <!-- Tabs -->
            <div class="px-6 pt-5 pb-4">
                <div class="flex bg-gray-100 p-1.5 rounded-xl">
                    <button @click="paymentModal.method = 'tunai'"
                            :class="paymentModal.method === 'tunai' ? 'bg-white shadow-sm text-blue-700 font-bold' : 'text-gray-600 hover:text-gray-800 font-semibold'"
                            class="flex-1 py-2.5 rounded-lg flex items-center justify-center gap-2 text-sm transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Tunai
                    </button>
                    <button @click="paymentModal.method = 'qris'"
                            :class="paymentModal.method === 'qris' ? 'bg-white shadow-sm text-blue-700 font-bold' : 'text-gray-600 hover:text-gray-800 font-semibold'"
                            class="flex-1 py-2.5 rounded-lg flex items-center justify-center gap-2 text-sm transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                        QRIS
                    </button>
                    <button @click="paymentModal.method = 'kredit'"
                            :class="paymentModal.method === 'kredit' ? 'bg-white shadow-sm text-blue-700 font-bold' : 'text-gray-600 hover:text-gray-800 font-semibold'"
                            class="flex-1 py-2.5 rounded-lg flex items-center justify-center gap-2 text-sm transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        Kredit Anggota
                    </button>
                </div>
            </div>

            <!-- Tab Content: Tunai -->
            <div x-show="paymentModal.method === 'tunai'" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="px-6 pb-6 flex flex-col md:flex-row gap-6">
                 
                <!-- Left: Input & Quick Amounts -->
                <div class="flex-1">
                    <label class="block text-[11px] font-bold text-gray-500 tracking-[0.1em] uppercase mb-2">TUNAI DITERIMA</label>
                    <div class="relative mb-5">
                        <span class="absolute left-5 top-1/2 -translate-y-1/2 text-gray-800 font-semibold text-lg">Rp</span>
                        <input type="text" 
                               x-model="paymentModal.uangDiterima" 
                               @input="calcKembalian()"
                               class="w-full pl-14 pr-4 py-3.5 rounded-xl border border-gray-200 bg-[#f8f9fa] text-3xl font-mono text-[#004aad] tracking-wide focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all outline-none"
                               placeholder="0">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <button @click="setUang(30000)" class="py-3.5 rounded-xl border border-gray-200 bg-white text-gray-700 font-medium text-sm hover:border-blue-500 hover:text-blue-600 transition-colors focus:ring-2 focus:ring-blue-500/20">Rp 30.000</button>
                        <button @click="setUang(50000)" class="py-3.5 rounded-xl border border-gray-200 bg-white text-gray-700 font-medium text-sm hover:border-blue-500 hover:text-blue-600 transition-colors focus:ring-2 focus:ring-blue-500/20">Rp 50.000</button>
                        <button @click="setUang(100000)" class="py-3.5 rounded-xl border border-gray-200 bg-white text-gray-700 font-medium text-sm hover:border-blue-500 hover:text-blue-600 transition-colors focus:ring-2 focus:ring-blue-500/20">Rp 100.000</button>
                        <button @click="setUang(grandTotal)" class="py-3.5 rounded-xl border border-gray-200 bg-white text-gray-700 font-medium text-sm hover:border-blue-500 hover:text-blue-600 transition-colors focus:ring-2 focus:ring-blue-500/20">Uang Pas</button>
                    </div>
                </div>
                
                <!-- Right: Summary Box -->
                <div class="w-full md:w-[280px] bg-[#f8f9fa] rounded-xl p-5 border border-gray-100 flex flex-col justify-center">
                    <div class="flex justify-between items-center text-sm text-gray-600 mb-3">
                        <span>Total Tagihan</span>
                        <span class="font-mono text-gray-900 tracking-wide" x-text="'Rp ' + formatNumber(grandTotal)">Rp 28.638</span>
                    </div>
                    <div class="flex justify-between items-center text-sm text-gray-600 pb-4 border-b border-gray-200 border-dashed mb-4">
                        <span>Diterima</span>
                        <span class="font-mono text-gray-900 tracking-wide" x-text="'Rp ' + formatNumber(paymentModal.uangDiterima || 0)">Rp 50.000</span>
                    </div>
                    
                    <div>
                        <span class="block text-[11px] font-bold text-gray-500 tracking-[0.1em] uppercase mb-1">KEMBALIAN</span>
                        <span class="text-3xl font-bold font-mono tracking-wide" 
                              :class="paymentModal.kembalian >= 0 ? 'text-[#00b050]' : 'text-red-500'"
                              x-text="'Rp ' + formatNumber(Math.abs(paymentModal.kembalian))">Rp 21.362</span>
                        <p x-show="paymentModal.kembalian < 0" class="text-xs text-red-500 mt-1">Uang tidak cukup</p>
                    </div>
                </div>
            </div>

            <!-- Tab Content: QRIS -->
            <div x-show="paymentModal.method === 'qris'" 
                 style="display: none;"
                 class="px-6 pb-6 flex flex-col items-center justify-center text-center">
                 <div class="w-48 h-48 bg-gray-100 rounded-xl border-2 border-dashed border-gray-300 flex items-center justify-center mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                    </svg>
                 </div>
                 <p class="text-gray-600 font-medium text-lg mb-1">Menunggu Pembayaran QRIS</p>
                 <p class="text-sm text-gray-500">Total Tagihan: <span class="font-bold text-gray-900 font-mono" x-text="'Rp ' + formatNumber(grandTotal)"></span></p>
            </div>

            <!-- Tab Content: Kredit Anggota -->
            <div x-show="paymentModal.method === 'kredit'" 
                 style="display: none;"
                 class="px-6 pb-6">
                 <!-- Digunakan includenya berdasarkan plan: -->
                 {{-- @include('kasir.partials._kredit-panel') --}}
                 <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 text-center text-gray-500 text-sm font-medium">
                    [Area Pencarian Anggota & Verifikasi Plafon]
                 </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-white border-t border-gray-100 flex flex-col-reverse sm:flex-row justify-between items-center sm:justify-end gap-3 rounded-b-2xl">
            <button @click="paymentModal.open = false" 
                    class="w-full sm:w-auto px-8 py-3 rounded-xl border border-gray-200 bg-white text-gray-700 font-bold hover:bg-gray-50 hover:text-gray-900 transition-colors focus:ring-4 focus:ring-gray-200 outline-none">
                Batal
            </button>
            <button x-show="paymentModal.method !== 'kredit'"
                    @click="processPayment(paymentModal.method)" 
                    :disabled="(paymentModal.method === 'tunai' && paymentModal.kembalian < 0) || (paymentModal.method === 'tunai' && !paymentModal.uangDiterima)"
                    class="w-full sm:w-auto px-8 py-3 rounded-xl bg-[#004aad] hover:bg-[#003882] text-white font-bold flex items-center justify-center gap-2 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed focus:ring-4 focus:ring-blue-500/30 outline-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Selesaikan & Cetak Struk
            </button>

            <a x-show="paymentModal.method === 'kredit'" 
               style="display: none;"
               href="{{ url('/proses-kredit') }}"
               class="w-full sm:w-auto px-8 py-3 rounded-xl bg-[#004aad] hover:bg-[#003882] text-white font-bold flex items-center justify-center gap-2 transition-colors shadow-sm focus:ring-4 focus:ring-blue-500/30 outline-none">
                <span class="material-symbols-rounded text-[20px]">arrow_forward</span>
                Lanjut Proses Kredit Anggota
            </a>
        </div>
    </div>
</div>
