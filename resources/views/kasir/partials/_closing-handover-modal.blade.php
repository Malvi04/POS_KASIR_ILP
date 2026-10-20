<!-- resources/views/kasir/partials/_closing-handover-modal.blade.php -->

<!-- MODAL: CATAT PERGERAKAN KAS LACI -->
<div x-show="showCashMovementModal"
     style="display: none;"
     class="fixed inset-0 z-[80] flex items-center justify-center p-4"
     @keydown.escape.window="showCashMovementModal = false">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-xs" @click="showCashMovementModal = false"></div>

    <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden z-10">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50/70">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-rounded text-[#0052cc]">account_balance_wallet</span>
                <h3 class="text-base font-bold text-gray-900">Catat Kas Laci</h3>
            </div>
            <button type="button" @click="showCashMovementModal = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100 cursor-pointer">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>

        <div class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-2">Jenis Transaksi Kas</label>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="newMovement.type = 'CASH_DROP'"
                            :class="newMovement.type === 'CASH_DROP' ? 'border-[#0052cc] bg-blue-50/70 text-[#0052cc]' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                            class="p-2.5 rounded-xl border text-left transition-all cursor-pointer flex items-center gap-2">
                        <span class="material-symbols-rounded text-[18px]">savings</span>
                        <span class="text-xs font-bold">Setor Brankas</span>
                    </button>
                    <button type="button" @click="newMovement.type = 'KAS_MASUK'"
                            :class="newMovement.type === 'KAS_MASUK' ? 'border-emerald-600 bg-emerald-50/70 text-emerald-700' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                            class="p-2.5 rounded-xl border text-left transition-all cursor-pointer flex items-center gap-2">
                        <span class="material-symbols-rounded text-[18px]">add_card</span>
                        <span class="text-xs font-bold">Kas Masuk (+)</span>
                    </button>
                    <button type="button" @click="newMovement.type = 'PENGELUARAN_TUNAI'"
                            :class="newMovement.type === 'PENGELUARAN_TUNAI' ? 'border-amber-600 bg-amber-50/70 text-amber-700' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                            class="p-2.5 rounded-xl border text-left transition-all cursor-pointer flex items-center gap-2">
                        <span class="material-symbols-rounded text-[18px]">outbox</span>
                        <span class="text-xs font-bold">Kas Keluar (-)</span>
                    </button>
                    <button type="button" @click="newMovement.type = 'RETUR_TUNAI'"
                            :class="newMovement.type === 'RETUR_TUNAI' ? 'border-red-600 bg-red-50/70 text-red-700' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                            class="p-2.5 rounded-xl border text-left transition-all cursor-pointer flex items-center gap-2">
                        <span class="material-symbols-rounded text-[18px]">assignment_return</span>
                        <span class="text-xs font-bold">Retur Tunai (-)</span>
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5">Nominal (Rp)</label>
                <div class="relative">
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-bold text-gray-400">Rp</span>
                    <input type="text"
                           x-model="newMovement.amount"
                           placeholder="0"
                           class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 font-mono text-base font-bold text-gray-900 focus:bg-white focus:border-[#0052cc] outline-none">
                </div>
                <div class="flex gap-2 mt-2">
                    <button type="button" @click="newMovement.amount = '50000'" class="px-2.5 py-1 text-xs font-bold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg cursor-pointer">50.000</button>
                    <button type="button" @click="newMovement.amount = '100000'" class="px-2.5 py-1 text-xs font-bold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg cursor-pointer">100.000</button>
                    <button type="button" @click="newMovement.amount = '300000'" class="px-2.5 py-1 text-xs font-bold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg cursor-pointer">300.000</button>
                    <button type="button" @click="newMovement.amount = '500000'" class="px-2.5 py-1 text-xs font-bold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg cursor-pointer">500.000</button>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5">Keterangan</label>
                <input type="text"
                       x-model="newMovement.note"
                       placeholder="Tulis keterangan..."
                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-xs text-gray-800 focus:bg-white focus:border-[#0052cc] outline-none">
            </div>
        </div>

        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-2.5">
            <button type="button" @click="showCashMovementModal = false" class="px-4 py-2 rounded-xl border border-gray-300 bg-white text-gray-700 text-xs font-bold hover:bg-gray-50 cursor-pointer">
                Batal
            </button>
            <button type="button" @click="submitCashMovement()" class="px-5 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-xs font-bold shadow-sm cursor-pointer">
                Simpan
            </button>
        </div>
    </div>
</div>

<!-- MODAL TUTUP SESI KASIR -->
<div x-show="closingWizard.open"
     style="display: none;"
     class="fixed inset-0 z-[85] flex items-center justify-center p-4"
     @keydown.escape.window="if (!showDenomModal) closingWizard.open = false">
    <div class="absolute inset-0 bg-slate-900/55 backdrop-blur-xs" @click="if (!showDenomModal) closingWizard.open = false"></div>

    <div class="relative w-full max-w-xl bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden z-10 flex flex-col max-h-[92vh]">
        <!-- Top Header -->
        <div class="px-6 py-4 border-b border-gray-200 bg-slate-50 flex items-center justify-between shrink-0">
            <div>
                <h2 class="text-base font-black text-gray-900">Tutup Sesi Kasir</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    <span class="font-bold text-gray-700" x-text="activeSession.terminalLabel"></span> •
                    <span class="font-bold text-gray-700" x-text="activeSession.cashierName"></span> •
                    <span class="font-mono text-[#0052cc]" x-text="activeSession.sessionId"></span>
                </p>
            </div>
            <button type="button" @click="closingWizard.open = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-200/60 cursor-pointer">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>

        <!-- Stepper -->
        <div class="px-6 py-3 bg-white border-b border-gray-100 flex items-center justify-between text-xs font-bold shrink-0">
            <div class="flex items-center gap-2" :class="closingWizard.step >= 1 ? 'text-[#0052cc]' : 'text-gray-400'">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px]"
                      :class="closingWizard.step === 1 ? 'bg-[#0052cc] text-white' : 'bg-emerald-100 text-emerald-700'">1</span>
                <span>Hitung Fisik Laci</span>
            </div>
            <div class="h-px flex-1 bg-gray-200 mx-4"></div>
            <div class="flex items-center gap-2" :class="closingWizard.step >= 2 ? 'text-[#0052cc]' : 'text-gray-400'">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px]"
                      :class="closingWizard.step === 2 ? 'bg-[#0052cc] text-white' : 'bg-gray-100 text-gray-500'">2</span>
                <span>Rekonsiliasi &amp; Tutup</span>
            </div>
        </div>

        <!-- Body -->
        <div class="p-6 overflow-y-auto flex-1 space-y-4">

            <!-- Error Banner -->
            <div x-show="closingWizard.errorMsg" class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-bold flex items-center gap-2">
                <span class="material-symbols-rounded text-[18px]">error</span>
                <span x-text="closingWizard.errorMsg"></span>
            </div>

            <!-- STEP 1: INPUT KAS FISIK -->
            <div x-show="closingWizard.step === 1" class="space-y-4">
                <!-- Pending / Hold Cart Check -->
                <div x-show="activeSession.heldBillsCount > 0" class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 space-y-2">
                    <div class="text-amber-800 font-bold text-xs">
                        Keranjang Tunda (<span x-text="activeSession.heldBillsCount"></span>):
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer text-xs font-bold"
                               :class="closingWizard.holdAction === 'inherit' ? 'border-[#0052cc] bg-white text-[#0052cc]' : 'border-amber-200 bg-amber-50/40 text-gray-700'">
                            <input type="radio" value="inherit" x-model="closingWizard.holdAction" class="text-[#0052cc]">
                            <span>Teruskan ke Kasir Berikutnya</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer text-xs font-bold"
                               :class="closingWizard.holdAction === 'cancel' ? 'border-red-600 bg-white text-red-600' : 'border-amber-200 bg-amber-50/40 text-gray-700'">
                            <input type="radio" value="cancel" x-model="closingWizard.holdAction" class="text-red-600">
                            <span>Batalkan Semua</span>
                        </label>
                    </div>
                </div>

                <!-- Input Uang Fisik Laci -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Total Uang Tunai Fisik di Laci
                    </label>
                    <div class="flex items-center gap-2.5">
                        <div class="relative flex-1">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-base font-bold text-gray-400">Rp</span>
                            <input type="text"
                                   x-model="closingWizard.actualCashStr"
                                   @input="handleActualCashInput()"
                                   placeholder="0"
                                   class="w-full pl-12 pr-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50 font-mono text-2xl font-black text-gray-900 focus:bg-white focus:border-[#0052cc] outline-none">
                        </div>
                        <button type="button"
                                @click="openDenomModal('closing')"
                                class="h-[52px] px-4 bg-blue-50 hover:bg-blue-100 text-[#0052cc] border border-blue-200 rounded-xl font-bold text-xs flex items-center gap-2 shrink-0 cursor-pointer">
                            <span class="material-symbols-rounded text-[20px]">calculate</span>
                            <span>Pecahan</span>
                        </button>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button type="button"
                                @click="setQuickActualCash(expectedClosingCash)"
                                class="px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 text-[11px] font-bold cursor-pointer">
                            Sesuai Sistem (<span x-text="formatRupiah(expectedClosingCash)"></span>)
                        </button>
                        <button type="button"
                                @click="setQuickActualCash(expectedClosingCash - 10000)"
                                class="px-2.5 py-1 rounded-lg bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 text-[11px] font-bold cursor-pointer">
                            Kurang Rp 10.000
                        </button>
                        <button type="button"
                                @click="setQuickActualCash(expectedClosingCash + 5000)"
                                class="px-2.5 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 text-[11px] font-bold cursor-pointer">
                            Lebih Rp 5.000
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 2: REKONSILIASI & TUTUP -->
            <div x-show="closingWizard.step === 2" class="space-y-4">
                <div class="bg-slate-50 border border-gray-200 rounded-2xl p-4 space-y-2 text-xs">
                    <div class="flex justify-between text-gray-600">
                        <span>Uang Awal</span>
                        <span class="font-mono font-bold text-gray-900" x-text="formatRupiah(activeSession.openingCash)"></span>
                    </div>
                    <div class="flex justify-between text-emerald-700">
                        <span>(+) Penjualan Tunai</span>
                        <span class="font-mono font-bold" x-text="'+ ' + formatRupiah((Number(activeSession.cashSalesNet)||0) + (Number(activeSession.splitCashPortion)||0))"></span>
                    </div>
                    <div class="flex justify-between text-emerald-700">
                        <span>(+) Kas Masuk</span>
                        <span class="font-mono font-bold" x-text="'+ ' + formatRupiah(activeSession.cashIn)"></span>
                    </div>
                    <div class="flex justify-between text-red-600">
                        <span>(-) Retur &amp; Kas Keluar</span>
                        <span class="font-mono font-bold" x-text="'- ' + formatRupiah((Number(activeSession.cashReturn)||0) + (Number(activeSession.cashOut)||0))"></span>
                    </div>
                    <div class="flex justify-between text-amber-700">
                        <span>(-) Setor Brankas</span>
                        <span class="font-mono font-bold" x-text="'- ' + formatRupiah(activeSession.cashDrop)"></span>
                    </div>

                    <div class="pt-2 border-t border-gray-300 flex justify-between items-center text-sm">
                        <span class="font-black text-gray-900">Kas Sistem</span>
                        <span class="font-mono font-black text-[#0052cc]" x-text="formatRupiah(expectedClosingCash)"></span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="font-black text-gray-900">Kas Fisik Laci</span>
                        <span class="font-mono font-black text-gray-900" x-text="formatRupiah(closingWizard.actualCashNum)"></span>
                    </div>

                    <!-- Selisih -->
                    <div class="p-3 rounded-xl flex items-center justify-between mt-2"
                         :class="cashVariance === 0 ? 'bg-emerald-100/80 border border-emerald-300 text-emerald-900' : 'bg-red-100/80 border border-red-300 text-red-900'">
                        <span class="font-black text-xs uppercase" x-text="cashVariance === 0 ? 'SALDO SESUAI' : 'SELISIH KAS'"></span>
                        <span class="font-mono font-black text-base" x-text="cashVariance > 0 ? '+' + formatRupiah(cashVariance) : formatRupiah(cashVariance)"></span>
                    </div>
                </div>

                <!-- Form Alasan Selisih -->
                <div x-show="cashVariance !== 0" class="p-3.5 rounded-xl bg-red-50/60 border border-red-200 space-y-2">
                    <label class="block text-xs font-bold text-red-800">Keterangan Selisih (Wajib)</label>
                    <select x-model="closingWizard.varianceCategory" class="w-full px-3 py-2 rounded-lg border border-red-200 bg-white text-xs font-bold text-gray-800 outline-none">
                        <option value="Selisih Kembalian Tunai">Selisih Kembalian Tunai</option>
                        <option value="Pembulatan Uang Receh">Pembulatan Uang Receh</option>
                        <option value="Lupa Catat Pengeluaran Laci">Lupa Catat Kas Keluar</option>
                        <option value="Perlu Hitung Ulang Manager">Perlu Pengecekan Manager</option>
                    </select>
                    <input type="text"
                           x-model="closingWizard.varianceNote"
                           placeholder="Tulis keterangan selisih..."
                           class="w-full px-3 py-2 rounded-lg border border-red-200 bg-white text-xs text-gray-800 outline-none">
                </div>

                <!-- Catatan Laci (Opsional) -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Catatan Laci (Opsional)</label>
                    <input type="text"
                           x-model="closingWizard.handoverNote"
                           placeholder="Tulis catatan untuk sesi berikutnya..."
                           class="w-full px-3.5 py-2 rounded-xl border border-gray-200 bg-gray-50 text-xs text-gray-800 outline-none focus:bg-white focus:border-[#0052cc]">
                </div>
            </div>

        </div>

        <!-- Footer Buttons -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between shrink-0">
            <div>
                <button type="button"
                        x-show="closingWizard.step === 2"
                        @click="closingWizard.step = 1; closingWizard.errorMsg = ''"
                        class="px-4 py-2 rounded-xl border border-gray-300 bg-white text-gray-700 text-xs font-bold hover:bg-gray-100 flex items-center gap-1 cursor-pointer">
                    <span class="material-symbols-rounded text-[16px]">arrow_back</span>
                    Kembali
                </button>
            </div>

            <div class="flex items-center gap-2.5">
                <button type="button"
                        @click="closingWizard.open = false"
                        class="px-4 py-2 rounded-xl border border-gray-300 bg-white text-gray-600 text-xs font-bold hover:bg-gray-100 cursor-pointer">
                    Batal
                </button>

                <button type="button"
                        x-show="closingWizard.step === 1"
                        @click="lockBlindCountAndProceed()"
                        class="px-5 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-xs font-bold shadow-sm flex items-center gap-1.5 cursor-pointer">
                    <span>Lanjut Rekonsiliasi</span>
                    <span class="material-symbols-rounded text-[16px]">arrow_forward</span>
                </button>

                <button type="button"
                        x-show="closingWizard.step === 2"
                        @click="completeCloseAndHandover()"
                        class="px-5 py-2 rounded-xl bg-[#16a34a] hover:bg-[#15803d] text-white text-xs font-bold shadow-sm flex items-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-rounded text-[16px]">logout</span>
                    <span>Tutup Sesi Kasir</span>
                </button>
            </div>
        </div>
    </div>
</div>
