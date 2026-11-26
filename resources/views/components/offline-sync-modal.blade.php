<!-- Global Offline Sync Management Modal -->
<div x-data="{
        formatRupiah(num) {
            return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
        },
        get pendingItems() {
            return ($store.offlineSync?.allPendingItems && $store.offlineSync?.allPendingItems.length > 0)
                ? $store.offlineSync.allPendingItems
                : ($store.offlineSync?.pendingTransactions || []);
        },
        get totalAmount() {
            return this.pendingItems.reduce((acc, it) => acc + Number(it.amount || it.grand_total || it.total || 0), 0);
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
         class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-4xl flex flex-col max-h-[90vh] overflow-hidden transform transition-all"
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
                    <p class="text-xs text-slate-400">Pengelolaan data offline lokal (sesi kasir, kas laci & transaksi) pada peramban ini</p>
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
                          :class="($store.network && $store.network.isOnline) 
                            ? 'bg-emerald-500 animate-pulse' 
                            : (($store.network && $store.network.isReconnecting) ? 'bg-amber-500 animate-ping' : 'bg-rose-500')"></span>
                    <span class="text-sm font-black"
                          :class="($store.network && $store.network.isOnline) 
                            ? 'text-emerald-700' 
                            : (($store.network && $store.network.isReconnecting) ? 'text-amber-700' : 'text-rose-700')"
                          x-text="($store.network && $store.network.isOnline) 
                            ? 'ONLINE (Terhubung)' 
                            : (($store.network && $store.network.isReconnecting) ? 'MENGHUBUNGKAN...' : 'OFFLINE (Terputus)')"></span>
                </div>
                <span class="text-[11px] text-gray-500 mt-0.5 block font-mono"
                      x-text="($store.network && $store.network.isOnline) 
                        ? ('Ping: ' + ($store.network.latency || 1) + 'ms') 
                        : (($store.network && $store.network.offlineReason === 'server_offline') ? 'Wi-Fi Aktif / Server Tidak Merespons' : 'Kabel LAN / Wi-Fi Terputus')"></span>
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
                <span class="text-[11px] text-gray-500 mt-0.5 block">Total omzet / kas belum terkirim</span>
            </div>
        </div>

        <!-- Body: List of Pending Transactions -->
        <div class="p-6 overflow-y-auto flex-1 space-y-4">
            <div class="flex items-center justify-between">
                <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[16px] text-[#0052cc]">receipt_long</span>
                    <span>Daftar Data Antrean Offline di Laci Peramban</span>
                </h4>
                <div class="flex items-center gap-3">
                    <template x-if="pendingItems.length > 0">
                        <button type="button"
                                @click="$store.offlineSync?.clearAll()"
                                class="text-xs font-bold text-rose-600 hover:text-rose-700 flex items-center gap-1 cursor-pointer">
                            <span class="material-symbols-rounded text-[15px]">delete_sweep</span>
                            <span>Bersihkan Semua Data Uji</span>
                        </button>
                    </template>
                    <template x-if="pendingItems.length > 0">
                        <button type="button"
                                @click="$store.offlineSync?.exportBackup()"
                                class="text-xs font-bold text-[#0052cc] hover:text-[#0043a8] flex items-center gap-1 cursor-pointer">
                            <span class="material-symbols-rounded text-[15px]">download</span>
                            <span>Unduh Cadangan JSON</span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Empty State -->
            <template x-if="pendingItems.length === 0">
                <div class="py-12 px-4 text-center border-2 border-dashed border-gray-200 rounded-2xl bg-gray-50/50">
                    <div class="w-14 h-14 mx-auto rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mb-3">
                        <span class="material-symbols-rounded text-[32px]">cloud_done</span>
                    </div>
                    <h5 class="text-sm font-bold text-gray-800">Semua Data Offline Telah Bersih & Tersinkronkan</h5>
                    <p class="text-xs text-gray-500 max-w-md mx-auto mt-1">
                        Tidak ada transaksi penjualan, sesi kasir, maupun mutasi laci yang tertunda di peramban ini. Seluruh data telah aman tersimpan di database server.
                    </p>
                </div>
            </template>

            <!-- Table of Items -->
            <template x-if="pendingItems.length > 0">
                <div class="border border-gray-200 rounded-xl overflow-hidden shadow-2xs">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-100 text-gray-600 uppercase text-[10px] font-bold tracking-wider">
                            <tr>
                                <th class="px-4 py-3">Tipe & Identitas Data</th>
                                <th class="px-4 py-3">Waktu Input</th>
                                <th class="px-4 py-3">Terminal / Kasir</th>
                                <th class="px-4 py-3">Metode</th>
                                <th class="px-4 py-3 text-right">Nominal</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <template x-for="item in pendingItems" :key="item.offline_id">
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-1.5 mb-0.5">
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold border"
                                                  :class="item.badge_color || 'bg-blue-50 text-blue-700 border-blue-200'"
                                                  x-text="item.type_label || (item.item_type === 'shift' ? 'Sesi Shift' : (item.item_type === 'movement' ? 'Kas Laci' : 'Transaksi'))"></span>
                                        </div>
                                        <div class="font-mono font-bold text-[#0052cc]" x-text="item.title || item.invoice_number"></div>
                                        <template x-if="item.last_error">
                                            <div class="text-[10px] text-rose-600 font-medium truncate max-w-xs" x-text="'Catatan: ' + item.last_error"></div>
                                        </template>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500" x-text="new Date(item.created_at || Date.now()).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' WIB'"></td>
                                    <td class="px-4 py-3 font-medium text-gray-800">
                                        <div x-text="item.subtitle || (item.terminal_code + ' • ' + (item.cashier_name || 'Kasir'))"></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                              :class="item.payment_method === 'TUNAI' ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700'"
                                              x-text="item.payment_method || '-'"></span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(item.amount || item.grand_total || item.total)"></td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="inline-flex items-center gap-1">
                                            <template x-if="item.item_type === 'transaction'">
                                                <button type="button"
                                                        @click="$store.offlineSync?.syncSingle(item.offline_id)"
                                                        :disabled="!($store.network && $store.network.isOnline) || $store.offlineSync?.isSyncing"
                                                        class="px-2 py-1 bg-blue-50 hover:bg-blue-100 disabled:opacity-40 text-[#0052cc] rounded-lg text-[11px] font-bold transition-all cursor-pointer inline-flex items-center gap-1">
                                                    <span class="material-symbols-rounded text-[13px]">send</span>
                                                    <span>Kirim</span>
                                                </button>
                                            </template>
                                            <button type="button"
                                                    @click="$store.offlineSync?.deleteItem(item.item_type || 'transaction', item.offline_id)"
                                                    class="p-1 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                                                    title="Hapus dari antrean lokal">
                                                <span class="material-symbols-rounded text-[16px]">delete</span>
                                            </button>
                                        </div>
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
