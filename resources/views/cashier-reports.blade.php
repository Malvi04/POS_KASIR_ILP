@extends('layouts.app')

@section('title', 'Laporan Kasir - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    selectedSessionFilter: 'all',
    selectedMethodFilter: 'all',
    selectedStatusFilter: 'all',
    transactions: [
        { id: '#TRX-99204', sessionId: 'CS-20261019-K01-003', terminal: 'Kasir 01', time: '11:42', cashier: 'Budi Kurniawan', method: 'CAMPURAN', cashPortion: 100000, nonCashPortion: 45000, total: 145000, status: 'SELESAI' },
        { id: '#TRX-99203', sessionId: 'CS-20261019-K01-003', terminal: 'Kasir 01', time: '11:20', cashier: 'Budi Kurniawan', method: 'TUNAI', cashPortion: 84000, nonCashPortion: 0, total: 84000, status: 'SELESAI' },
        { id: '#TRX-99202', sessionId: 'CS-20261019-K01-003', terminal: 'Kasir 01', time: '10:35', cashier: 'Budi Kurniawan', method: 'QRIS', cashPortion: 0, nonCashPortion: 245500, total: 245500, status: 'SELESAI' },
        { id: '#TRX-99201', sessionId: 'CS-20261019-K01-002', terminal: 'Kasir 01', time: '09:48', cashier: 'Andi Pratama', method: 'TUNAI', cashPortion: 120000, nonCashPortion: 0, total: 120000, status: 'SELESAI' },
        { id: '#TRX-99200', sessionId: 'CS-20261019-K01-002', terminal: 'Kasir 01', time: '09:15', cashier: 'Andi Pratama', method: 'KREDIT ANGGOTA', cashPortion: 0, nonCashPortion: 240000, total: 240000, status: 'SELESAI' },
        { id: '#TRX-99199', sessionId: 'CS-20261019-K01-002', terminal: 'Kasir 01', time: '08:40', cashier: 'Andi Pratama', method: 'QRIS', cashPortion: 0, nonCashPortion: 450000, total: 450000, status: 'DIBATALKAN' },
        { id: '#TRX-99198', sessionId: 'CS-20261019-K01-001', terminal: 'Kasir 01', time: '07:30', cashier: 'Budi Kurniawan', method: 'TUNAI', cashPortion: 310000, nonCashPortion: 0, total: 310000, status: 'SELESAI' }
    ],
    get filteredTransactions() {
        return this.transactions.filter(t => {
            if (this.selectedSessionFilter === 'active' && t.sessionId !== this.activeSession.sessionId && t.sessionId !== 'CS-20261019-K01-003') return false;
            if (this.selectedSessionFilter !== 'all' && this.selectedSessionFilter !== 'active' && t.sessionId !== this.selectedSessionFilter) return false;
            if (this.selectedMethodFilter !== 'all' && t.method !== this.selectedMethodFilter) return false;
            if (this.selectedStatusFilter !== 'all' && t.status !== this.selectedStatusFilter) return false;
            return true;
        });
    }
}"
x-init="initOpening()"
@pos-session-sync.window="initOpening()"
class="flex flex-col h-screen w-full font-sans">
    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar -->
        <x-cashier-sidebar active="reports" />
        
        <!-- Main Area -->
        <div class="flex-1 flex flex-col min-w-0 bg-[#fafafa]">
            <!-- Header -->
            <x-cashier-header title="Laporan Penjualan" />
            
            <!-- Content -->
            <main class="flex-1 overflow-y-auto p-8 bg-[#f8f9fb]">
                
                <!-- 4 Clean Stat Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                    <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-xs">
                        <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-wider">Omzet Sesi</span>
                        <h2 class="text-2xl font-black text-[#1e40af] mt-1.5 font-mono" x-text="formatRupiah(totalSessionOmzet)">Rp 2.120.000</h2>
                        <span class="text-xs font-bold text-gray-500 mt-1 block" x-text="activeSession.trxCount + ' Transaksi'">28 Transaksi</span>
                    </div>
                    
                    <div class="bg-white rounded-xl p-5 border border-emerald-200 shadow-xs">
                        <span class="text-[11px] font-extrabold text-emerald-700 uppercase tracking-wider">Tunai Laci</span>
                        <h2 class="text-2xl font-black text-emerald-700 mt-1.5 font-mono" x-text="formatRupiah((Number(activeSession.cashSalesNet)||0) + (Number(activeSession.splitCashPortion)||0))">Rp 970.000</h2>
                        <span class="text-xs font-bold text-emerald-600 mt-1 block">Uang Fisik</span>
                    </div>
                    
                    <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-xs">
                        <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-wider">QRIS / Debit</span>
                        <h2 class="text-2xl font-black text-gray-900 mt-1.5 font-mono" x-text="formatRupiah(totalNonCashSales)">Rp 700.000</h2>
                        <span class="text-xs font-bold text-[#0052cc] mt-1 block">Non-Tunai</span>
                    </div>

                    <div class="bg-white rounded-xl p-5 border border-orange-200 shadow-xs">
                        <span class="text-[11px] font-extrabold text-orange-700 uppercase tracking-wider">Kredit Anggota</span>
                        <h2 class="text-2xl font-black text-orange-700 mt-1.5 font-mono" x-text="formatRupiah(activeSession.creditSales)">Rp 450.000</h2>
                        <span class="text-xs font-bold text-orange-600 mt-1 block">Piutang</span>
                    </div>
                </div>
                
                <!-- Filter Section -->
                <div class="bg-white rounded-t-xl p-5 border border-gray-200 shadow-xs flex flex-col gap-4 border-b-0">
                     <div class="flex items-end gap-4 flex-wrap">
                         <div class="flex flex-col gap-1.5">
                             <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Sesi Kasir</label>
                             <div class="relative">
                                 <select x-model="selectedSessionFilter" class="appearance-none pl-3.5 pr-9 py-2 border border-gray-200 rounded-lg text-xs font-bold text-gray-800 min-w-[220px] outline-none bg-white cursor-pointer focus:border-[#0052cc]">
                                     <option value="all">Semua Sesi Hari Ini</option>
                                     <option value="active">Sesi Aktif Saat Ini</option>
                                     <option value="CS-20261019-K01-002">CS-20261019-K01-002 (Andi)</option>
                                     <option value="CS-20261019-K01-001">CS-20261019-K01-001 (Budi)</option>
                                 </select>
                                 <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[18px]">expand_more</span>
                             </div>
                         </div>
                         
                         <div class="flex flex-col gap-1.5">
                             <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Metode Bayar</label>
                             <div class="relative">
                                 <select x-model="selectedMethodFilter" class="appearance-none pl-3.5 pr-9 py-2 border border-gray-200 rounded-lg text-xs font-bold text-gray-800 w-[160px] outline-none bg-white cursor-pointer focus:border-[#0052cc]">
                                     <option value="all">Semua</option>
                                     <option value="TUNAI">Tunai</option>
                                     <option value="QRIS">QRIS / Debit</option>
                                     <option value="KREDIT ANGGOTA">Kredit Anggota</option>
                                     <option value="CAMPURAN">Campuran</option>
                                 </select>
                                 <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[18px]">expand_more</span>
                             </div>
                         </div>
                         
                         <div class="flex flex-col gap-1.5">
                             <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Status</label>
                             <div class="flex bg-[#f8fafc] p-1 rounded-lg border border-gray-200">
                                 <button type="button" @click="selectedStatusFilter = 'all'" :class="selectedStatusFilter === 'all' ? 'bg-white border border-blue-200 text-[#1d4ed8] shadow-2xs' : 'text-gray-500'" class="px-3.5 py-1 font-bold text-xs rounded cursor-pointer">Semua</button>
                                 <button type="button" @click="selectedStatusFilter = 'SELESAI'" :class="selectedStatusFilter === 'SELESAI' ? 'bg-white border border-blue-200 text-[#1d4ed8] shadow-2xs' : 'text-gray-500'" class="px-3.5 py-1 font-bold text-xs rounded cursor-pointer">Selesai</button>
                                 <button type="button" @click="selectedStatusFilter = 'DIBATALKAN'" :class="selectedStatusFilter === 'DIBATALKAN' ? 'bg-white border border-blue-200 text-[#1d4ed8] shadow-2xs' : 'text-gray-500'" class="px-3.5 py-1 font-bold text-xs rounded cursor-pointer">Batal</button>
                             </div>
                         </div>
                     </div>
                </div>
                
                <!-- Table -->
                <div class="bg-white rounded-b-xl border border-gray-200 shadow-xs overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#f8fafc] border-b border-gray-200">
                                <th class="px-5 py-3.5 text-[10px] font-black text-gray-500 uppercase tracking-widest">No. Transaksi</th>
                                <th class="px-5 py-3.5 text-[10px] font-black text-gray-500 uppercase tracking-widest">ID Sesi</th>
                                <th class="px-5 py-3.5 text-[10px] font-black text-gray-500 uppercase tracking-widest">Petugas</th>
                                <th class="px-5 py-3.5 text-[10px] font-black text-gray-500 uppercase tracking-widest">Jam</th>
                                <th class="px-5 py-3.5 text-[10px] font-black text-gray-500 uppercase tracking-widest">Metode</th>
                                <th class="px-5 py-3.5 text-[10px] font-black text-gray-500 uppercase tracking-widest text-right">Total</th>
                                <th class="px-5 py-3.5 text-[10px] font-black text-gray-500 uppercase tracking-widest text-center">Status</th>
                                <th class="px-5 py-3.5 text-[10px] font-black text-gray-500 uppercase tracking-widest text-center">Struk</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-xs">
                            <template x-for="trx in filteredTransactions" :key="trx.id">
                                <tr class="hover:bg-blue-50/20 transition-colors">
                                    <td class="px-5 py-3.5 font-mono font-bold text-[#0052cc]" x-text="trx.id"></td>
                                    <td class="px-5 py-3.5 font-mono text-gray-700" x-text="trx.sessionId"></td>
                                    <td class="px-5 py-3.5 font-bold text-gray-900" x-text="trx.cashier"></td>
                                    <td class="px-5 py-3.5 text-gray-600" x-text="trx.time"></td>
                                    <td class="px-5 py-3.5">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold"
                                              :class="{
                                                  'bg-emerald-50 text-emerald-700 border border-emerald-200': trx.method === 'TUNAI',
                                                  'bg-blue-50 text-[#0052cc] border border-blue-200': trx.method === 'QRIS',
                                                  'bg-orange-50 text-orange-700 border border-orange-200': trx.method === 'KREDIT ANGGOTA',
                                                  'bg-purple-50 text-purple-700 border border-purple-200': trx.method === 'CAMPURAN'
                                              }"
                                              x-text="trx.method"></span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(trx.total)"></td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="px-2.5 py-0.5 rounded text-[10px] font-black uppercase"
                                              :class="trx.status === 'SELESAI' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-600 border border-red-200'"
                                              x-text="trx.status"></span>
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <a href="{{ url('/cetak-struk') }}" class="text-[#0052cc] hover:text-blue-800 inline-flex p-1">
                                            <span class="material-symbols-rounded text-[18px]">print</span>
                                        </a>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                
            </main>
        </div>
    </div>
</div>
@endsection
