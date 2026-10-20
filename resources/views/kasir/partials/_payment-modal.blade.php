<!-- resources/views/kasir/partials/_payment-modal.blade.php -->
<div x-show="paymentModal.open"
     style="display: none;"
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     @keydown.escape.window="paymentModal.open = false">
     
    <div x-show="paymentModal.open" 
         x-transition.opacity.duration.300ms
         class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" 
         @click="paymentModal.open = false"></div>

    <div x-show="paymentModal.open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col">
         
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-slate-50/60">
            <div class="flex items-center gap-2.5">
                <h2 class="text-lg font-bold text-gray-900">Pembayaran</h2>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-blue-100 text-[#0052cc]" x-text="activeSession.terminalLabel + ' • ' + activeSession.cashierName"></span>
            </div>
            <button @click="paymentModal.open = false" class="text-gray-400 hover:text-gray-600 rounded-lg p-1 hover:bg-gray-100 cursor-pointer">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>

        <!-- Body -->
        <div class="flex-1 flex flex-col">
            <!-- 4 Payment Method Tabs -->
            <div class="px-6 pt-4 pb-4">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 bg-gray-100 p-1.5 rounded-xl">
                    <button type="button" @click="paymentModal.method = 'tunai'"
                            :class="paymentModal.method === 'tunai' ? 'bg-white shadow-xs text-[#0052cc] font-bold' : 'text-gray-600 hover:text-gray-800 font-semibold'"
                            class="py-2.5 rounded-lg flex items-center justify-center gap-1.5 text-xs transition-all cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">payments</span>
                        <span>Tunai</span>
                    </button>
                    <button type="button" @click="paymentModal.method = 'qris'"
                            :class="paymentModal.method === 'qris' ? 'bg-white shadow-xs text-[#0052cc] font-bold' : 'text-gray-600 hover:text-gray-800 font-semibold'"
                            class="py-2.5 rounded-lg flex items-center justify-center gap-1.5 text-xs transition-all cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">qr_code_scanner</span>
                        <span>QRIS / Debit</span>
                    </button>
                    <button type="button" @click="paymentModal.method = 'kredit'"
                            :class="paymentModal.method === 'kredit' ? 'bg-white shadow-xs text-[#0052cc] font-bold' : 'text-gray-600 hover:text-gray-800 font-semibold'"
                            class="py-2.5 rounded-lg flex items-center justify-center gap-1.5 text-xs transition-all cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">badge</span>
                        <span>Kredit Anggota</span>
                    </button>
                    <button type="button" @click="paymentModal.method = 'campuran'; calcSplitPayment()"
                            :class="paymentModal.method === 'campuran' ? 'bg-white shadow-xs text-[#0052cc] font-bold' : 'text-gray-600 hover:text-gray-800 font-semibold'"
                            class="py-2.5 rounded-lg flex items-center justify-center gap-1.5 text-xs transition-all cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">call_split</span>
                        <span>Campuran</span>
                    </button>
                </div>
            </div>

            <!-- Tab Content 1: Tunai -->
            <div x-show="paymentModal.method === 'tunai'" 
                 class="px-6 pb-6 flex flex-col md:flex-row gap-6">
                 
                <div class="flex-1">
                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-2">Uang Diterima</label>
                    <div class="relative mb-4">
                        <span class="absolute left-5 top-1/2 -translate-y-1/2 text-gray-800 font-semibold text-lg">Rp</span>
                        <input type="text" 
                               x-model="paymentModal.uangDiterima" 
                               @input="calcKembalian()"
                               class="w-full pl-14 pr-4 py-3.5 rounded-xl border border-gray-200 bg-[#f8f9fa] text-3xl font-mono text-[#004aad] tracking-wide focus:bg-white focus:border-blue-500 outline-none"
                               placeholder="0">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2.5">
                        <button type="button" @click="setUang(30000)" class="py-2.5 rounded-xl border border-gray-200 bg-white text-gray-700 font-bold text-xs hover:border-blue-500 hover:text-blue-600 cursor-pointer">Rp 30.000</button>
                        <button type="button" @click="setUang(50000)" class="py-2.5 rounded-xl border border-gray-200 bg-white text-gray-700 font-bold text-xs hover:border-blue-500 hover:text-blue-600 cursor-pointer">Rp 50.000</button>
                        <button type="button" @click="setUang(100000)" class="py-2.5 rounded-xl border border-gray-200 bg-white text-gray-700 font-bold text-xs hover:border-blue-500 hover:text-blue-600 cursor-pointer">Rp 100.000</button>
                        <button type="button" @click="setUang(grandTotal)" class="py-2.5 rounded-xl border border-blue-200 bg-blue-50 text-[#0052cc] font-bold text-xs hover:bg-blue-100 cursor-pointer">Uang Pas (<span x-text="'Rp ' + formatNumber(grandTotal)"></span>)</button>
                    </div>
                </div>
                
                <div class="w-full md:w-[260px] bg-[#f8f9fa] rounded-xl p-5 border border-gray-200 flex flex-col justify-between">
                    <div class="space-y-2.5">
                        <div class="flex justify-between items-center text-xs text-gray-600">
                            <span>Total Tagihan</span>
                            <span class="font-mono font-bold text-gray-900" x-text="'Rp ' + formatNumber(grandTotal)">Rp 28.638</span>
                        </div>
                        <div class="flex justify-between items-center text-xs text-gray-600 pb-3 border-b border-gray-200 border-dashed">
                            <span>Tunai Diterima</span>
                            <span class="font-mono font-bold text-gray-900" x-text="'Rp ' + formatNumber(paymentModal.uangDiterima || 0)">Rp 50.000</span>
                        </div>
                    </div>
                    
                    <div class="pt-2">
                        <span class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Kembalian</span>
                        <span class="text-2xl font-black font-mono" 
                              :class="paymentModal.kembalian >= 0 ? 'text-[#00b050]' : 'text-red-500'"
                              x-text="'Rp ' + formatNumber(Math.abs(paymentModal.kembalian))">Rp 21.362</span>
                        <p x-show="paymentModal.kembalian < 0" class="text-[11px] text-red-500 font-bold mt-1">Nominal belum cukup</p>
                    </div>
                </div>
            </div>

            <!-- Tab Content 2: QRIS / Debit -->
            <div x-show="paymentModal.method === 'qris'" 
                 style="display: none;"
                 class="px-6 pb-6 flex flex-col items-center justify-center text-center">
                 <div class="w-36 h-36 bg-gray-100 rounded-xl border-2 border-dashed border-gray-300 flex items-center justify-center mb-3">
                    <span class="material-symbols-rounded text-6xl text-[#0052cc]">qr_code_2</span>
                 </div>
                 <p class="text-gray-800 font-bold text-sm">Scan QRIS / EDC Debit</p>
                 <p class="text-xs text-gray-500 mt-0.5">Total: <span class="font-bold text-gray-900 font-mono" x-text="'Rp ' + formatNumber(grandTotal)"></span></p>
            </div>

            <!-- Tab Content 3: Kredit Anggota -->
            <div x-show="paymentModal.method === 'kredit'" 
                 style="display: none;"
                 class="px-6 pb-6">
                 <div class="bg-orange-50/70 border border-orange-200 rounded-xl p-5 text-center space-y-1.5">
                    <span class="material-symbols-rounded text-3xl text-orange-600">credit_score</span>
                    <h4 class="font-bold text-gray-900 text-sm">Kredit Anggota Kopkar ILP</h4>
                    <p class="text-xs text-gray-600">Lanjutkan ke verifikasi anggota untuk mencatat piutang.</p>
                 </div>
            </div>

            <!-- Tab Content 4: Pembayaran Campuran -->
            <div x-show="paymentModal.method === 'campuran'"
                 style="display: none;"
                 class="px-6 pb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="p-4 rounded-xl border border-gray-200 bg-gray-50">
                        <label class="block text-[11px] font-extrabold text-emerald-700 uppercase mb-1.5">Nominal Tunai</label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-bold text-gray-500">Rp</span>
                            <input type="number"
                                   x-model.number="paymentModal.splitCash"
                                   @input="calcSplitPayment()"
                                   class="w-full pl-10 pr-3 py-2.5 rounded-lg border border-gray-300 bg-white font-mono text-base font-bold text-gray-900 outline-none focus:border-[#0052cc]">
                        </div>
                        <div class="flex gap-1.5 mt-2">
                            <button type="button" @click="paymentModal.splitCash = 10000; calcSplitPayment()" class="px-2 py-1 text-[11px] font-bold bg-white border border-gray-200 rounded cursor-pointer">Rp 10.000</button>
                            <button type="button" @click="paymentModal.splitCash = 20000; calcSplitPayment()" class="px-2 py-1 text-[11px] font-bold bg-white border border-gray-200 rounded cursor-pointer">Rp 20.000</button>
                        </div>
                    </div>

                    <div class="p-4 rounded-xl border border-gray-200 bg-gray-50">
                        <label class="block text-[11px] font-extrabold text-[#0052cc] uppercase mb-1.5">Sisa QRIS / Debit</label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-bold text-gray-500">Rp</span>
                            <input type="text"
                                   readonly
                                   :value="formatNumber(paymentModal.splitNonCash)"
                                   class="w-full pl-10 pr-3 py-2.5 rounded-lg border border-gray-200 bg-slate-100 font-mono text-base font-bold text-[#0052cc] outline-none">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-white border-t border-gray-100 flex justify-end gap-3 rounded-b-2xl">
            <button type="button" @click="paymentModal.open = false" 
                    class="px-5 py-2.5 rounded-xl border border-gray-200 bg-white text-gray-700 font-bold text-xs hover:bg-gray-50 cursor-pointer">
                Batal
            </button>
            <button type="button"
                    x-show="paymentModal.method !== 'kredit'"
                    @click="processPayment(paymentModal.method)" 
                    :disabled="(paymentModal.method === 'tunai' && paymentModal.kembalian < 0) || (paymentModal.method === 'tunai' && !paymentModal.uangDiterima)"
                    class="px-6 py-2.5 rounded-xl bg-[#004aad] hover:bg-[#003882] text-white font-bold text-xs flex items-center gap-2 shadow-sm disabled:opacity-50 cursor-pointer">
                <span class="material-symbols-rounded text-[18px]">print</span>
                <span>Bayar &amp; Cetak Struk</span>
            </button>

            <a x-show="paymentModal.method === 'kredit'" 
               style="display: none;"
               href="{{ url('/proses-kredit') }}"
               class="px-6 py-2.5 rounded-xl bg-[#004aad] hover:bg-[#003882] text-white font-bold text-xs flex items-center gap-2 shadow-sm">
                <span class="material-symbols-rounded text-[18px]">arrow_forward</span>
                Lanjut Kredit
            </a>
        </div>
    </div>
</div>
