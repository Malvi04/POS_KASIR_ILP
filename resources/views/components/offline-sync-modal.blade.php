<!-- Global Offline Sync Management Modal -->
<div x-data="{
        formatRupiah(num) {
            return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
        },
        get pendingItems() {
            return $store.offlineSync?.pendingTransactions || [];
        },
        get totalAmount() {
            return this.pendingItems.reduce((acc, it) => acc + Number(it.grand_total || it.total || 0), 0);
        }
     }"
     x-cloak
     x-show="$store.offlineSync?.modalOpen"
     @keydown.escape.window="$store.offlineSync?.closeModal()"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">

    <div @click.outside="$store.offlineSync?.closeModal()"
         class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-3xl flex flex-col max-h-[90vh] overflow-hidden transform transition-all"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95">

        <!-- Modal Header -->
        <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-500/20 text-blue-400 flex items-center justify-center border border-blue-500/30">
                    <span class="material-symbols-rounded text-[24px]">cloud_sync</span>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white tracking-tight">Pusat Sinkronisasi & Resiliensi Offline</h3>
                    <p class="text-xs text-slate-400">Pengelolaan transaksi lokal kasir & sinkronisasi database server</p>
                </div>
            </div>
            <button type="button"
                    @click="$store.offlineSync?.closeModal()"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors cursor-pointer">
                <span class="material-symbols-rounded text-[20px]">close</span>
            </button>
        </div>

        <!-- Status & Stats Banner -->
        <div class="p-6 bg-slate-50 border-b border-gray-200 grid grid-cols-1 sm:grid-cols-3 gap-4 shrink-0">
            <!-- Card 1: Network Status -->
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-2xs">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-1">STATUS KONEKSI</span>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full"
                          :class="($store.network && $store.network.isOnline) ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'"></span>
                    <span class="text-sm font-black"
                          :class="($store.network && $store.network.isOnline) ? 'text-emerald-700' : 'text-rose-700'"
                          x-text="($store.network && $store.network.isOnline) ? 'ONLINE (Terhubung)' : 'OFFLINE (Terputus)'"></span>
                </div>
                <span class="text-[11px] text-gray-500 mt-0.5 block font-mono"
                      x-text="($store.network && $store.network.isOnline) ? ('Ping: ' + ($store.network.latency || 1) + 'ms') : 'Kabel LAN / Wi-Fi terputus'"></span>
            </div>

            <!-- Card 2: Pending Count -->
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-2xs">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-1">ANTREAN OFFLINE</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-xl font-black text-gray-900"
                          x-text="($store.offlineSync?.totalPending || 0) + ' Data'">0 Data</span>
                    <template x-if="($store.offlineSync?.totalPending || 0) > 0">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Perlu Sync</span>
                    </template>
                </div>
                <span class="text-[11px] text-gray-500 mt-0.5 block">Tersimpan di laci browser kasir</span>
            </div>

            <!-- Card 3: Total Amount Pending -->
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-2xs">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-1">NOMINAL BELUM SYNC</span>
                <span class="text-xl font-black text-gray-900 font-mono block"
                      x-text="formatRupiah(totalAmount)">Rp 0</span>
                <span class="text-[11px] text-gray-500 mt-0.5 block">Total omzet belum terkirim</span>
            </div>
        </div>

        <!-- Body: List of Pending Transactions -->
        <div class="p-6 overflow-y-auto flex-1 space-y-4">
            <div class="flex items-center justify-between">
                <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[16px] text-[#0052cc]">receipt_long</span>
                    <span>Daftar Transaksi Kasir Belum Tersinkron</span>
                </h4>
                <template x-if="pendingItems.length > 0">
                    <button type="button"
                            @click="$store.offlineSync?.exportBackup()"
                            class="text-xs font-bold text-[#0052cc] hover:text-[#0043a8] flex items-center gap-1 cursor-pointer">
                        <span class="material-symbols-rounded text-[15px]">download</span>
                        <span>Unduh File Cadangan JSON</span>
                    </button>
                </template>
            </div>

            <!-- Empty State -->
            <template x-if="pendingItems.length === 0">
                <div class="py-12 px-4 text-center border-2 border-dashed border-gray-200 rounded-2xl bg-gray-50/50">
                    <div class="w-14 h-14 mx-auto rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mb-3">
                        <span class="material-symbols-rounded text-[32px]">cloud_done</span>
                    </div>
                    <h5 class="text-sm font-bold text-gray-800">Semua Transaksi Tersinkronisasi</h5>
                    <p class="text-xs text-gray-500 max-w-md mx-auto mt-1">
                        Tidak ada transaksi penjualan kasir yang tertunda. Seluruh data transaksi, mutasi laci, dan stok telah tersimpan aman di server pusat.
                    </p>
                </div>
            </template>

            <!-- Table of Items -->
            <template x-if="pendingItems.length > 0">
                <div class="border border-gray-200 rounded-xl overflow-hidden shadow-2xs">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-100 text-gray-600 uppercase text-[10px] font-bold tracking-wider">
                            <tr>
                                <th class="px-4 py-3">No. Faktur Offline</th>
                                <th class="px-4 py-3">Waktu Transaksi</th>
                                <th class="px-4 py-3">Terminal / Kasir</th>
                                <th class="px-4 py-3">Metode</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <template x-for="item in pendingItems" :key="item.offline_id">
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-4 py-3 font-mono font-bold text-[#0052cc]" x-text="item.invoice_number"></td>
                                    <td class="px-4 py-3 text-gray-500" x-text="new Date(item.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' WIB'"></td>
                                    <td class="px-4 py-3 font-medium text-gray-800">
                                        <div x-text="item.terminal_code"></div>
                                        <div class="text-[10px] text-gray-400" x-text="item.cashier_name"></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                              :class="item.payment_method === 'TUNAI' ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700'"
                                              x-text="item.payment_method"></span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(item.grand_total)"></td>
                                    <td class="px-4 py-3 text-center">
                                        <button type="button"
                                                @click="$store.offlineSync?.syncSingle(item.offline_id)"
                                                :disabled="!($store.network && $store.network.isOnline) || $store.offlineSync?.isSyncing"
                                                class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 disabled:opacity-40 text-[#0052cc] rounded-lg text-[11px] font-bold transition-all cursor-pointer inline-flex items-center gap-1">
                                            <span class="material-symbols-rounded text-[14px]">send</span>
                                            <span>Kirim</span>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        <!-- Modal Footer Actions -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-3 shrink-0">
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <span class="material-symbols-rounded text-[16px] text-emerald-600">verified</span>
                <span>Otomatis sinkron saat koneksi internet / server pulih.</span>
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto">
                <button type="button"
                        @click="$store.offlineSync?.closeModal()"
                        class="flex-1 sm:flex-none px-4 py-2 border border-gray-300 hover:bg-gray-100 text-gray-700 rounded-xl text-xs font-bold transition-all cursor-pointer">
                    Tutup
                </button>

                <button type="button"
                        @click="$store.offlineSync?.syncNow()"
                        :disabled="!($store.network && $store.network.isOnline) || $store.offlineSync?.isSyncing || pendingItems.length === 0"
                        class="flex-1 sm:flex-none px-5 py-2 bg-[#0052cc] hover:bg-[#0043a8] disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center justify-center gap-2 cursor-pointer">
                    <span class="material-symbols-rounded text-[16px]"
                          :class="$store.offlineSync?.isSyncing ? 'animate-spin' : ''">sync</span>
                    <span x-text="$store.offlineSync?.isSyncing ? 'Sedang Menyinkronkan...' : 'Sinkronkan Semua Sekarang'">Sinkronkan Semua Sekarang</span>
                </button>
            </div>
        </div>

    </div>
</div>
