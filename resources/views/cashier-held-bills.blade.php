@extends('layouts.app')

@section('title', 'Transaksi Ditahan - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    searchQuery: '',
    showDeleteModal: false,
    showRecallModal: false,
    selectedBill: null,
    deleteReason: 'Pelanggan tidak kembali ke kasir (barang dikembalikan ke rak)',
    toastMessage: '',
    showToast: false,

    heldBills: [
        {
            id: '#HOLD-001',
            customerName: 'Budi Santoso',
            customerType: 'Anggota Koperasi',
            holdTime: '10:45 WIB',
            elapsedMinutes: 18,
            sessionLabel: 'Kasir 01',
            isHandover: true,
            handoverFrom: 'Andi Pratama',
            note: 'Pelanggan keluar sejenak mengambil dompet di kendaraan.',
            totalAmount: 145000,
            itemCount: 12,
            items: [
                { name: 'Indomie Goreng Spesial 85g', qty: 5, price: 3500, total: 17500 },
                { name: 'Ultra Milk Full Cream 1L', qty: 2, price: 19200, total: 38400 },
                { name: 'Beras Pandan Wangi 5kg', qty: 1, price: 82000, total: 82000 },
                { name: 'Aqua Mineral 600ml', qty: 4, price: 5500, total: 22000 }
            ]
        },
        {
            id: '#HOLD-002',
            customerName: 'Ibu Rina (Pelanggan Umum)',
            customerType: 'Pelanggan Umum',
            holdTime: '11:10 WIB',
            elapsedMinutes: 8,
            sessionLabel: 'Kasir 01',
            isHandover: false,
            handoverFrom: null,
            note: 'Pelanggan menambah pembelian minyak goreng dan biskuit di rak lorong B.',
            totalAmount: 57000,
            itemCount: 3,
            items: [
                { name: 'Chitato Sapi Panggang 68g', qty: 2, price: 12800, total: 25600 },
                { name: 'Sunlight Jeruk Nipis 755ml', qty: 1, price: 15500, total: 15500 },
                { name: 'Teh Pucuk Harum 350ml', qty: 2, price: 4000, total: 8000 }
            ]
        },
        {
            id: '#HOLD-003',
            customerName: 'Pak Agus (Member Koperasi)',
            customerType: 'Anggota Koperasi',
            holdTime: '11:25 WIB',
            elapsedMinutes: 3,
            sessionLabel: 'Kasir 01',
            isHandover: false,
            handoverFrom: null,
            note: 'Pelanggan melakukan verifikasi NIK dan pengecekan sisa plafon kredit di layanan anggota.',
            totalAmount: 82000,
            itemCount: 4,
            items: [
                { name: 'Gula Pasir Gulaku 1kg', qty: 2, price: 18000, total: 36000 },
                { name: 'Biskuit Roma Kelapa 300g', qty: 2, price: 13500, total: 27000 },
                { name: 'Aqua Mineral 600ml', qty: 2, price: 5500, total: 11000 }
            ]
        }
    ],

    get filteredBills() {
        return this.heldBills.filter(b => {
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                const matchId = b.id.toLowerCase().includes(q);
                const matchName = b.customerName.toLowerCase().includes(q);
                const matchItems = b.items.some(it => it.name.toLowerCase().includes(q));
                return matchId || matchName || matchItems;
            }
            return true;
        });
    },

    get totalHeldAmount() {
        return this.heldBills.reduce((acc, b) => acc + (b.totalAmount || 0), 0);
    },

    openRecallConfirm(bill) {
        this.selectedBill = bill;
        this.showRecallModal = true;
    },

    openDeleteConfirm(bill) {
        this.selectedBill = bill;
        this.deleteReason = 'Pelanggan tidak kembali ke kasir (barang dikembalikan ke rak)';
        this.showDeleteModal = true;
    },

    proceedRecall() {
        if (!this.selectedBill) return;

        // Remove from held queue
        const idx = this.heldBills.findIndex(b => b.id === this.selectedBill.id);
        if (idx !== -1) {
            this.heldBills.splice(idx, 1);
            this.activeSession.heldBillsCount = Math.max(0, this.heldBills.length);
            this.persistAllState();
        }

        this.showRecallModal = false;
        // Redirect to POS with recalled state
        window.location.href = '/pos';
    },

    proceedDelete() {
        if (!this.selectedBill) return;

        const idx = this.heldBills.findIndex(b => b.id === this.selectedBill.id);
        if (idx !== -1) {
            const billId = this.selectedBill.id;
            this.heldBills.splice(idx, 1);
            this.activeSession.heldBillsCount = Math.max(0, this.heldBills.length);
            this.persistAllState();
            this.showDeleteModal = false;
            this.showToastNotice(`Bill ${billId} berhasil dibatalkan dan dihapus.`);
        }
    },

    showToastNotice(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3600);
    }
}"
x-init="initOpening()"
@pos-session-sync.window="initOpening()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">

    <!-- Sidebar Kasir -->
    <x-cashier-sidebar active="transaksi_ditahan" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        <!-- Top Header -->
        <x-cashier-header title="Transaksi Ditahan (Held Bills)">
            <div class="flex items-center gap-2 ml-2">
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200 flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[14px]">pause_circle</span>
                    <span x-text="heldBills.length + ' Bill Tertahan'">3 Bill Tertahan</span>
                </span>
            </div>
        </x-cashier-header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-6 lg:p-8 space-y-6">
            <div class="max-w-[1400px] mx-auto space-y-6">

                <!-- Breadcrumb & Header Title -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-[11px] font-bold text-gray-400 tracking-wider mb-1 uppercase">
                            <span>OPERASIONAL KASIR</span>
                            <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                            <span>TRANSAKSI</span>
                            <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                            <span class="text-[#0052cc]">TRANSAKSI DITAHAN (HELD BILLS)</span>
                        </div>
                        <h1 class="text-2xl font-black text-gray-900 tracking-tight">Antrean Transaksi Tertahan</h1>
                        <p class="text-xs text-gray-500 mt-1 font-medium">Daftar transaksi tertahan dan recall keranjang belanja ke layar POS.</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="{{ url('/pos') }}" class="px-4 py-2.5 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-2">
                            <span class="material-symbols-rounded text-[18px]">point_of_sale</span>
                            <span>Kembali ke POS</span>
                        </a>
                    </div>
                </div>

                <!-- 4 KPI Stat Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-2xl border border-amber-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-amber-500">
                        <div>
                            <span class="text-[10px] font-bold text-amber-700 tracking-wider uppercase">TOTAL BILL TERTANGGUH</span>
                            <h3 class="text-2xl font-black text-amber-600 mt-1" x-text="heldBills.length + ' Antrean'">3 Antrean</h3>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Menunggu recall kasir</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">pause</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 tracking-wider uppercase">ESTIMASI TOTAL NILAI</span>
                            <h3 class="text-xl font-black text-gray-900 font-mono mt-1" x-text="formatRupiah(totalHeldAmount)">Rp 284.000</h3>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Nilai keranjang tertahan</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">shopping_bag</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 tracking-wider uppercase">DITAHAN SESI INI</span>
                            <h3 class="text-2xl font-black text-gray-900 mt-1" x-text="heldBills.filter(b => !b.isHandover).length + ' Bill'">2 Bill</h3>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Diinput oleh Budi Kurniawan</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-slate-100 text-gray-700 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">schedule</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-purple-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-purple-500">
                        <div>
                            <span class="text-[10px] font-bold text-purple-700 tracking-wider uppercase">HANDOVER SESI LALU</span>
                            <h3 class="text-2xl font-black text-purple-700 mt-1" x-text="heldBills.filter(b => b.isHandover).length + ' Bill'">1 Bill</h3>
                            <span class="text-[11px] text-purple-600 font-medium mt-0.5 block">Serah terima dari Andi P.</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">sync_alt</span>
                        </div>
                    </div>
                </div>

                <!-- Search Filter Bar -->
                <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs flex items-center justify-between gap-4">
                    <div class="relative flex-1 max-w-xl">
                        <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="Cari nomor hold (#HOLD-...), nama pelanggan, atau nama produk..."
                               class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 text-[13px] shadow-xs font-medium bg-slate-50/50">
                    </div>
                </div>

                <!-- Held Bills Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <template x-for="bill in filteredBills" :key="bill.id">
                        <div class="bg-white rounded-2xl border border-gray-200 shadow-xs p-5 flex flex-col justify-between hover:border-blue-300 transition-all group">

                            <!-- Card Header -->
                            <div>
                                <div class="flex items-start justify-between pb-3 border-b border-gray-100">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                                            <span class="material-symbols-rounded text-[20px]">pause</span>
                                        </div>
                                        <div>
                                            <span class="font-mono font-bold text-gray-900 text-sm" x-text="bill.id"></span>
                                            <div class="flex items-center gap-1 text-[10px] text-gray-400 mt-0.5">
                                                <span class="material-symbols-rounded text-[12px]">schedule</span>
                                                <span x-text="bill.holdTime + ' (' + bill.elapsedMinutes + ' mnt lalu)'"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <template x-if="bill.isHandover">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-purple-50 text-purple-700 border border-purple-200">
                                            Handover Sesi
                                        </span>
                                    </template>
                                </div>

                                <!-- Customer & Reason Info -->
                                <div class="mt-3.5 space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <h3 class="font-bold text-gray-900 text-sm" x-text="bill.customerName"></h3>
                                        <span class="text-[10.5px] text-[#0052cc] font-semibold" x-text="bill.customerType"></span>
                                    </div>
                                    <p class="text-xs text-gray-500 bg-slate-50 p-2.5 rounded-xl border border-gray-100 leading-relaxed italic" x-text="bill.note"></p>
                                </div>

                                <!-- Items Preview -->
                                <div class="mt-4 pt-3 border-t border-gray-100 space-y-1 text-xs">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-1">RINGKASAN ITEM BELANJA</span>
                                    <template x-for="(it, idx) in bill.items" :key="idx">
                                        <div class="flex justify-between text-gray-700 py-0.5">
                                            <span class="truncate pr-2 font-medium" x-text="it.qty + 'x ' + it.name"></span>
                                            <span class="font-mono text-gray-500 shrink-0" x-text="formatRupiah(it.total)"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Card Footer: Total & Actions -->
                            <div class="mt-5 pt-4 border-t border-gray-100 flex flex-col gap-3">
                                <div class="flex justify-between items-baseline">
                                    <span class="text-xs font-bold text-gray-400 uppercase">TOTAL TERTANGGUH</span>
                                    <span class="font-mono text-lg font-black text-gray-900" x-text="formatRupiah(bill.totalAmount)"></span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button type="button"
                                            @click="openDeleteConfirm(bill)"
                                            class="p-2.5 rounded-xl border border-gray-200 text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer shrink-0"
                                            title="Batalkan dan Hapus Bill">
                                        <span class="material-symbols-rounded text-[18px]">delete</span>
                                    </button>

                                    <button type="button"
                                            @click="openRecallConfirm(bill)"
                                            class="flex-1 px-4 py-2.5 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                                        <span class="material-symbols-rounded text-[18px]">play_arrow</span>
                                        <span>Lanjutkan Bill (Recall)</span>
                                    </button>
                                </div>
                            </div>

                        </div>
                    </template>

                    <template x-if="filteredBills.length === 0">
                        <div class="col-span-full bg-white rounded-2xl border border-gray-200 p-12 text-center text-gray-400">
                            <span class="material-symbols-rounded text-[40px] text-gray-300 mb-2">pause_circle</span>
                            <p class="font-bold text-gray-600 text-sm">Tidak ada transaksi tertahan saat ini.</p>
                            <p class="text-xs text-gray-400 mt-1">Gunakan tombol F3 (Tahan Bill) di layar POS saat pelanggan ingin mengambil barang tambahan.</p>
                        </div>
                    </template>
                </div>

            </div>
        </div>
    </main>

    <!-- MODAL 1: KONFIRMASI RECALL BILL KE POS -->
    <template x-teleport="body">
        <div x-show="showRecallModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showRecallModal"
                 @click.away="showRecallModal = false"
                 x-transition.scale.origin.bottom
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-gray-200 p-6 flex flex-col">

                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center font-bold shrink-0">
                        <span class="material-symbols-rounded text-[22px]">play_arrow</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 text-base">Lanjutkan Transaksi ke POS?</h3>
                        <p class="text-xs text-gray-500 font-mono" x-text="selectedBill?.id + ' • ' + selectedBill?.customerName"></p>
                    </div>
                </div>

                <div class="p-3.5 bg-slate-50 border border-gray-200 rounded-xl text-xs space-y-1.5 mb-5">
                    <div class="flex justify-between text-gray-600">
                        <span>Total Nilai Keranjang:</span>
                        <strong class="font-mono text-gray-900" x-text="formatRupiah(selectedBill?.totalAmount)"></strong>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Validasi Harga & Sesi:</span>
                        <strong class="text-emerald-600">Sesuai & Aktif</strong>
                    </div>
                </div>

                <p class="text-xs text-gray-500 mb-6 leading-relaxed">
                    Keranjang belanja akan langsung dimuat kembali ke antrean aktif terminal POS kasir Anda.
                </p>

                <div class="flex items-center justify-end gap-2">
                    <button type="button"
                            @click="showRecallModal = false"
                            class="px-4 py-2 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="button"
                            @click="proceedRecall()"
                            class="px-5 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-xs">
                        <span class="material-symbols-rounded text-[16px]">point_of_sale</span>
                        <span>Buka di Layar POS</span>
                    </button>
                </div>

            </div>
        </div>
    </template>

    <!-- MODAL 2: KONFIRMASI HAPUS / BATALKAN BILL -->
    <template x-teleport="body">
        <div x-show="showDeleteModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showDeleteModal"
                 @click.away="showDeleteModal = false"
                 x-transition.scale.origin.bottom
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-gray-200 p-6 flex flex-col">

                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold shrink-0">
                        <span class="material-symbols-rounded text-[22px]">delete_forever</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 text-base">Hapus Transaksi Tertahan?</h3>
                        <p class="text-xs text-gray-500 font-mono" x-text="selectedBill?.id + ' • Nilai: ' + formatRupiah(selectedBill?.totalAmount)"></p>
                    </div>
                </div>

                <div class="space-y-3 mb-5">
                    <label class="text-xs font-bold text-gray-700 block">Alasan Pembatalan Bill:</label>
                    <select x-model="deleteReason"
                            class="w-full px-3 py-2 bg-slate-50 border border-gray-200 rounded-xl text-xs font-medium text-gray-800 focus:outline-none focus:border-[#0052cc]">
                        <option value="Pelanggan tidak kembali ke kasir (barang dikembalikan ke rak)">Pelanggan tidak kembali (kembalikan ke rak)</option>
                        <option value="Pelanggan membatalkan seluruh transaksi belanja">Pelanggan membatalkan transaksi</option>
                        <option value="Duplikasi input kasir">Duplikasi keranjang bill</option>
                    </select>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button type="button"
                            @click="showDeleteModal = false"
                            class="px-4 py-2 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50">
                        Jangan Hapus
                    </button>
                    <button type="button"
                            @click="proceedDelete()"
                            class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-xs">
                        <span class="material-symbols-rounded text-[16px]">check</span>
                        <span>Konfirmasi Batalkan</span>
                    </button>
                </div>

            </div>
        </div>
    </template>

    <!-- TOAST -->
    <div x-show="showToast"
         x-transition.opacity
         style="display: none;"
         class="fixed bottom-6 right-6 z-50 bg-gray-900 text-white px-5 py-3.5 rounded-2xl shadow-xl flex items-center gap-3 border border-gray-800 text-xs font-semibold">
        <span class="material-symbols-rounded text-emerald-400 text-[20px]">check_circle</span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
