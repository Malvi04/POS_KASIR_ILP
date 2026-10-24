<!-- resources/views/kasir/partials/_opening-modal.blade.php -->

<!-- MODAL PEMBUKAAN SESI KASIR -->
<div x-show="showOpeningModal"
     style="display: none;"
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     @keydown.escape.window="if (!showDenomModal) { showOpeningModal = false }">
     
    <div x-show="showOpeningModal" 
         x-transition.opacity.duration.300ms
         class="absolute inset-0 bg-gray-900/50 backdrop-blur-xs" 
         @click="if (!showDenomModal) { showOpeningModal = false }"></div>

    <div x-show="showOpeningModal"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-200/90 flex flex-col max-h-[92vh]">
         
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-slate-50/70 shrink-0">
            <div>
                <h2 class="text-base font-black text-gray-900" x-text="selectedTerminalObj && selectedTerminalObj.status === 'HANDOVER_PENDING' ? 'Terima Laci & Buka Sesi' : 'Buka Sesi Kasir'"></h2>
                <div class="flex items-center gap-2 mt-1 text-xs text-gray-600">
                    <span class="font-bold text-[#0052cc]" x-text="formatShiftLabel(selectedShift)">Kasir 01</span>
                    <span>•</span>
                    <span class="font-bold text-gray-800" x-text="selectedStaffObj.name"></span>
                </div>
            </div>
            <button type="button" @click="showOpeningModal = false" class="text-gray-400 hover:text-gray-600 rounded-lg p-1 hover:bg-gray-100 cursor-pointer">
                <span class="material-symbols-rounded text-[22px]">close</span>
            </button>
        </div>

        <!-- Body Form -->
        <div class="p-6 space-y-4 bg-white overflow-y-auto flex-1">

            <!-- Error PIN Alert -->
            <div x-show="openingPinError" class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-bold flex items-center gap-2">
                <span class="material-symbols-rounded text-[18px]">error</span>
                <span x-text="openingPinError"></span>
            </div>

            <!-- INFO SERAH TERIMA RINGKAS -->
            <template x-if="selectedTerminalObj && selectedTerminalObj.status === 'HANDOVER_PENDING' && selectedTerminalObj.handover">
                <div class="p-3.5 rounded-xl bg-amber-50/90 border border-amber-200 flex items-center justify-between text-xs">
                    <div>
                        <span class="text-[10px] font-bold text-amber-800 uppercase block">Operan Laci Dari</span>
                        <span class="font-bold text-gray-900" x-text="selectedTerminalObj.handover.fromCashierName + ' (' + selectedTerminalObj.handover.endedAt + ')'"></span>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] font-bold text-amber-800 uppercase block">Uang Fisik di Laci</span>
                        <span class="font-mono font-black text-emerald-700 text-sm" x-text="formatRupiah(selectedTerminalObj.handover.actualCash)"></span>
                    </div>
                </div>
            </template>

            <!-- Kas Opening Field -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-gray-700">Uang Awal Laci (Rp)</label>
                    <button type="button"
                            x-show="selectedTerminalObj && selectedTerminalObj.status === 'HANDOVER_PENDING' && selectedTerminalObj.handover"
                            @click="kasOpening = formatRupiahDecimal(selectedTerminalObj.handover.actualCash)"
                            class="text-[11px] font-bold text-[#0052cc] hover:underline cursor-pointer">
                        Sesuaikan Operan
                    </button>
                </div>
                <div class="flex items-center gap-2.5">
                    <div class="relative flex-1 flex items-center">
                        <input type="text"
                               x-model="kasOpening"
                               @focus="if(kasOpening === '0,00') kasOpening = ''"
                               @blur="handleKasOpeningBlur()"
                               placeholder="0,00"
                               class="w-full pl-4 pr-10 py-2.5 rounded-xl border border-gray-200 bg-gray-50 font-mono text-base font-bold text-gray-900 focus:bg-white focus:border-[#0052cc] outline-none">
                        <button type="button" 
                                x-show="kasOpening && kasOpening !== '0,00'" 
                                @click="clearKasOpening()" 
                                class="absolute right-3 text-gray-400 hover:text-gray-600 p-1 rounded-md cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">close</span>
                        </button>
                    </div>

                    <button type="button"
                            @click="openDenomModal('opening')"
                            class="h-[44px] px-3.5 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-xl flex items-center gap-1.5 text-[#0052cc] cursor-pointer shrink-0">
                        <span class="material-symbols-rounded text-[20px]">payments</span>
                        <span class="text-xs font-bold">Pecahan</span>
                    </button>
                </div>
            </div>

            <!-- Verifikasi Akun & PIN -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3.5 rounded-xl bg-slate-50 border border-gray-200">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Petugas</label>
                    <select x-model="selectedStaffId" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-white text-xs font-bold text-gray-900 outline-none focus:border-[#0052cc]">
                        <template x-for="st in staffList" :key="st.id">
                            <option :value="st.id" x-text="st.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">PIN Pribadi</label>
                    <input type="password"
                           x-model="staffPinInput"
                           placeholder="123456"
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-white font-mono text-xs font-bold text-gray-900 outline-none focus:border-[#0052cc]">
                    <p class="text-[10px] text-amber-700 font-medium mt-1">Simulasi Auth: Demo PIN <span class="font-mono font-bold">123456</span></p>
                </div>
            </div>

            <!-- Catatan Opening -->
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5">Catatan (Opsional)</label>
                <input type="text"
                       x-model="catatanOpening"
                       placeholder="Tambahkan catatan jika perlu..."
                       class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-800 focus:bg-white focus:border-[#0052cc] outline-none">
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                <button type="button"
                        @click="discardOpening()"
                        :disabled="isOpeningSubmitting"
                        class="px-5 py-2.5 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 font-bold text-xs rounded-xl cursor-pointer">
                    Batal
                </button>
                <button type="button"
                        @click="submitOpening()"
                        :disabled="isOpeningSubmitting"
                        :class="isOpeningSubmitting ? 'opacity-60 cursor-not-allowed' : 'hover:bg-[#0043a8] cursor-pointer'"
                        class="px-6 py-2.5 bg-[#0052cc] text-white font-bold text-xs rounded-xl shadow-sm flex items-center gap-2 transition-all">
                    <template x-if="isOpeningSubmitting">
                        <svg class="animate-spin -ml-1 mr-1 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </template>
                    <template x-if="!isOpeningSubmitting">
                        <span class="material-symbols-rounded text-[18px]">lock_open</span>
                    </template>
                    <span x-text="isOpeningSubmitting ? 'Membuka Sesi...' : 'Buka Sesi Kasir'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL 2: KALKULATOR PECAHAN UANG -->
<div x-show="showDenomModal"
     style="display: none;"
     class="fixed inset-0 z-[95] flex items-center justify-center p-4"
     @keydown.escape.window="showDenomModal = false">
     
    <div x-show="showDenomModal" 
         x-transition.opacity.duration.250ms
         class="absolute inset-0 bg-gray-900/60 backdrop-blur-xs" 
         @click="showDenomModal = false"></div>

    <div x-show="showDenomModal"
         class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-200/90 flex flex-col">
         
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-white">
            <h3 class="text-base font-bold text-gray-900">Hitung Pecahan Uang</h3>
            <button type="button" @click="showDenomModal = false" class="text-gray-400 hover:text-gray-600 rounded-lg p-1 hover:bg-gray-100 cursor-pointer">
                <span class="material-symbols-rounded text-[22px]">close</span>
            </button>
        </div>

        <div class="p-6 overflow-y-auto max-h-[60vh] bg-white">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3.5">
                <div class="space-y-3.5">
                    <template x-for="item in currentDenomList.filter(d => d.col === 'left')" :key="item.id">
                        <div class="flex items-center justify-between">
                            <div class="inline-flex items-center border border-gray-300 rounded-lg overflow-hidden bg-white">
                                <button type="button" @click="decrementDenom(item)" class="w-8 h-8 flex items-center justify-center bg-gray-50 hover:bg-gray-100 text-gray-600 font-bold border-r border-gray-200 cursor-pointer">−</button>
                                <input type="number" min="0" x-model.number="item.count" @input="calcDenomTotal()" class="w-12 h-8 text-center text-sm font-bold bg-white text-gray-900 outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                <button type="button" @click="incrementDenom(item)" class="w-8 h-8 flex items-center justify-center bg-gray-50 hover:bg-gray-100 text-gray-600 font-bold border-l border-gray-200 cursor-pointer">+</button>
                            </div>
                            <span class="text-[13px] font-semibold text-gray-800 font-mono ml-3 text-right flex-1" x-text="item.label"></span>
                        </div>
                    </template>
                </div>

                <div class="space-y-3.5">
                    <template x-for="item in currentDenomList.filter(d => d.col === 'right')" :key="item.id">
                        <div class="flex items-center justify-between">
                            <div class="inline-flex items-center border border-gray-300 rounded-lg overflow-hidden bg-white">
                                <button type="button" @click="decrementDenom(item)" class="w-8 h-8 flex items-center justify-center bg-gray-50 hover:bg-gray-100 text-gray-600 font-bold border-r border-gray-200 cursor-pointer">−</button>
                                <input type="number" min="0" x-model.number="item.count" @input="calcDenomTotal()" class="w-12 h-8 text-center text-sm font-bold bg-white text-gray-900 outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                <button type="button" @click="incrementDenom(item)" class="w-8 h-8 flex items-center justify-center bg-gray-50 hover:bg-gray-100 text-gray-600 font-bold border-l border-gray-200 cursor-pointer">+</button>
                            </div>
                            <span class="text-[13px] font-semibold text-gray-800 font-mono ml-3 text-right flex-1" x-text="item.label"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50/80 flex items-center justify-between">
            <button type="button" 
                    @click="confirmDenom()" 
                    class="px-5 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs rounded-xl shadow-sm cursor-pointer flex items-center gap-1.5">
                <span class="material-symbols-rounded text-[18px]">check</span>
                <span>Gunakan Total</span>
            </button>
            <div class="text-right">
                <span class="text-xs font-bold text-gray-500 uppercase mr-1">Total:</span>
                <span class="text-base font-bold font-mono text-gray-900" x-text="'Rp ' + formatRupiahDecimal(denomTotal)">Rp 0,00</span>
            </div>
        </div>
    </div>
</div>
