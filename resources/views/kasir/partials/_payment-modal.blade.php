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
                <h2 class="text-lg font-bold text-gray-900">Pembayaran Kasir</h2>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-blue-100 text-[#0052cc]" x-text="(activeSession.terminalLabel || 'Kasir 01') + ' • ' + (activeOperator ? activeOperator.name : activeSession.cashierName)"></span>
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
                        <span>QRIS / Transfer</span>
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
                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-2">Uang Tunai Diterima</label>
                    <div class="relative mb-4">
                        <span class="absolute left-5 top-1/2 -translate-y-1/2 text-gray-800 font-semibold text-lg">Rp</span>
                        <input type="text" 
                               x-model="paymentModal.uangDiterima" 
                               @input="calcKembalian()"
                               class="w-full pl-14 pr-4 py-3.5 rounded-xl border border-gray-200 bg-[#f8f9fa] text-3xl font-mono text-[#004aad] tracking-wide focus:bg-white focus:border-blue-500 outline-none"
                               placeholder="0">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2.5">
                        <button type="button" @click="setUang(20000)" class="py-2.5 rounded-xl border border-gray-200 bg-white text-gray-700 font-bold text-xs hover:border-blue-500 hover:text-blue-600 cursor-pointer">Rp 20.000</button>
                        <button type="button" @click="setUang(50000)" class="py-2.5 rounded-xl border border-gray-200 bg-white text-gray-700 font-bold text-xs hover:border-blue-500 hover:text-blue-600 cursor-pointer">Rp 50.000</button>
                        <button type="button" @click="setUang(100000)" class="py-2.5 rounded-xl border border-gray-200 bg-white text-gray-700 font-bold text-xs hover:border-blue-500 hover:text-blue-600 cursor-pointer">Rp 100.000</button>
                        <button type="button" @click="setUang(grandTotal)" class="py-2.5 rounded-xl border border-blue-200 bg-blue-50 text-[#0052cc] font-bold text-xs hover:bg-blue-100 cursor-pointer">Uang Pas (<span x-text="'Rp ' + formatNumber(grandTotal)"></span>)</button>
                    </div>
                </div>
                
                <div class="w-full md:w-[260px] bg-[#f8f9fa] rounded-xl p-5 border border-gray-200 flex flex-col justify-between">
                    <div class="space-y-2.5">
                        <div class="flex justify-between items-center text-xs text-gray-600">
                            <span>Total Tagihan</span>
                            <span class="font-mono font-bold text-gray-900" x-text="'Rp ' + formatNumber(grandTotal)">Rp 0</span>
                        </div>
                        <div class="flex justify-between items-center text-xs text-gray-600 pb-3 border-b border-gray-200 border-dashed">
                            <span>Tunai Diterima</span>
                            <span class="font-mono font-bold text-gray-900" x-text="'Rp ' + formatNumber(paymentModal.uangDiterima || 0)">Rp 0</span>
                        </div>
                    </div>
                    
                    <div class="pt-2">
                        <span class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Kembalian Otomatis</span>
                        <span class="text-2xl font-black font-mono" 
                              :class="paymentModal.kembalian >= 0 ? 'text-[#00b050]' : 'text-red-500'"
                              x-text="'Rp ' + formatNumber(Math.abs(paymentModal.kembalian))">Rp 0</span>
                        <p x-show="paymentModal.kembalian < 0" class="text-[11px] text-red-500 font-bold mt-1">Uang diterima masih kurang</p>
                    </div>
                </div>
            </div>

            <!-- Tab Content 2: QRIS / Transfer -->
            <div x-show="paymentModal.method === 'qris'" 
                 style="display: none;"
                 class="px-6 pb-6 flex flex-col items-center justify-center text-center">
                 <div class="w-32 h-32 bg-gray-100 rounded-xl border-2 border-dashed border-gray-300 flex items-center justify-center mb-3">
                    <span class="material-symbols-rounded text-6xl text-[#0052cc]">qr_code_2</span>
                 </div>
                 <p class="text-gray-800 font-bold text-sm">QRIS Dinamis &amp; Transfer Bank</p>
                 <p class="text-xs text-gray-500 mt-0.5">Total Tagihan: <span class="font-bold text-gray-900 font-mono" x-text="'Rp ' + formatNumber(grandTotal)"></span></p>

                 <!-- Nomor Referensi Transfer -->
                 <div class="w-full max-w-sm mt-4 text-left">
                     <label class="block text-[11px] font-bold text-gray-600 uppercase mb-1">Nomor Referensi Transaksi Transfer / RRN</label>
                     <input type="text"
                            x-model="paymentModal.qrisRef"
                            placeholder="Contoh: QRIS-893021 / TRF-BCA-102938"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 font-mono text-xs font-bold text-gray-800 focus:border-[#0052cc] outline-none">
                     <p class="text-[10px] text-gray-400 mt-1">Dicatat otomatis pada laporan transaksi dan struk.</p>
                 </div>
            </div>

            <!-- Tab Content 3: Kredit Anggota (Kasbon Potong Gaji) -->
            <div x-show="paymentModal.method === 'kredit'" 
                 style="display: none;"
                 class="px-6 pb-6 space-y-4">
                 
                 <div class="bg-indigo-50/70 border border-indigo-200 rounded-xl p-4 flex items-center justify-between">
                     <div class="flex items-center gap-3">
                         <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center">
                             <span class="material-symbols-rounded text-[22px]">badge</span>
                         </div>
                         <div>
                             <h4 class="font-bold text-gray-900 text-xs">Validasi Otomatis Kredit Anggota (Kasbon)</h4>
                             <p class="text-[11px] text-gray-500">Pengecekan plafon kredit real-time ke database anggota.</p>
                         </div>
                     </div>
                     <span class="font-mono font-bold text-xs text-indigo-900" x-text="'Tagihan: Rp ' + formatNumber(grandTotal)"></span>
                 </div>

                 <!-- Input NIK & Cek Button -->
                 <div class="flex gap-2">
                     <div class="relative flex-1">
                         <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">person_search</span>
                         <input type="text"
                                x-model="memberValidation.nik"
                                @keydown.enter.prevent="validateMemberNik()"
                                placeholder="Masukkan NIK Anggota Koperasi..."
                                class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 font-mono text-xs font-bold text-gray-800 outline-none focus:border-[#0052cc]">
                     </div>
                     <button type="button"
                             @click="validateMemberNik()"
                             :disabled="memberValidation.loading"
                             class="px-4 py-2.5 bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold rounded-xl text-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                         <span class="material-symbols-rounded text-[16px]" x-show="!memberValidation.loading">verified</span>
                         <span x-text="memberValidation.loading ? 'Memeriksa...' : 'Validasi NIK'"></span>
                     </button>
                 </div>

                 <!-- Member Search Helper -->
                 <div class="flex items-center justify-between text-[11px] text-gray-500">
                     <span>Validasi limit plafon &amp; status aktif anggota koperasi secara real-time.</span>
                     <a href="{{ url('/kasir/members') }}" target="_blank" class="text-[#0052cc] hover:underline font-bold flex items-center gap-0.5">
                         <span>Katalog Anggota</span>
                         <span class="material-symbols-rounded text-[14px]">open_in_new</span>
                     </a>
                 </div>

                 <!-- Member Details & Plafon Status -->
                 <template x-if="memberValidation.data">
                     <div class="p-4 rounded-xl border space-y-3"
                          :class="memberValidation.isEligible ? 'bg-emerald-50/60 border-emerald-300' : 'bg-rose-50/60 border-rose-300'">
                         <div class="flex justify-between items-start">
                             <div>
                                 <h5 class="font-extrabold text-xs text-gray-900" x-text="memberValidation.data.name"></h5>
                                 <p class="text-[11px] text-gray-600 font-mono" x-text="'NIK: ' + memberValidation.data.nik + ' • ' + (memberValidation.data.department || '-')"></p>
                             </div>
                             <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold tracking-wide"
                                   :class="memberValidation.isEligible ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                   x-text="memberValidation.isEligible ? 'DISETUJUI (PLAFON CUKUP)' : 'DITOLAK SISTEM'"></span>
                         </div>

                         <div class="grid grid-cols-3 gap-2 text-center pt-2 border-t border-gray-200/60">
                             <div class="bg-white/80 p-2 rounded-lg border border-gray-200">
                                 <span class="text-[9px] font-bold text-gray-400 uppercase block">Total Plafon</span>
                                 <span class="font-mono font-bold text-xs text-gray-800" x-text="formatRupiah(memberValidation.data.credit_limit)"></span>
                             </div>
                             <div class="bg-white/80 p-2 rounded-lg border border-gray-200">
                                 <span class="text-[9px] font-bold text-gray-400 uppercase block">Sisa Plafon Saat Ini</span>
                                 <span class="font-mono font-extrabold text-xs text-blue-700" x-text="formatRupiah(memberValidation.data.remaining_credit)"></span>
                             </div>
                             <div class="bg-white/80 p-2 rounded-lg border border-gray-200">
                                 <span class="text-[9px] font-bold text-gray-400 uppercase block">Sisa Setelah Belanja</span>
                                 <span class="font-mono font-black text-xs"
                                       :class="memberValidation.isEligible ? 'text-emerald-700' : 'text-rose-600'"
                                       x-text="formatRupiah(memberValidation.data.projected_remaining)"></span>
                             </div>
                         </div>

                         <!-- Alert Message -->
                         <div class="text-[11px] font-bold"
                              :class="memberValidation.isEligible ? 'text-emerald-800' : 'text-rose-700'"
                              x-text="memberValidation.message"></div>
                     </div>
                 </template>
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
                    @click="processPayment(paymentModal.method)" 
                    :disabled="(paymentModal.method === 'tunai' && paymentModal.kembalian < 0) || (paymentModal.method === 'tunai' && !paymentModal.uangDiterima) || (paymentModal.method === 'kredit' && (!memberValidation.data || !memberValidation.isEligible))"
                    class="px-6 py-2.5 rounded-xl bg-[#004aad] hover:bg-[#003882] text-white font-bold text-xs flex items-center gap-2 shadow-sm disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer">
                <span class="material-symbols-rounded text-[18px]">print</span>
                <span x-text="paymentModal.method === 'kredit' ? 'Konfirmasi Kredit & Cetak Struk' : 'Bayar & Cetak Struk'"></span>
            </button>
        </div>
    </div>
</div>
