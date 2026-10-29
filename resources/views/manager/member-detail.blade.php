@extends('layouts.app')

@section('title', 'Detail Profil Anggota - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    members: [],
    member: null,
    adjustAmount: 0,
    toastMessage: '',
    showToast: false,

    initDetail() {
        try {
            const raw = localStorage.getItem('pos_ilp_members_v1');
            if (raw) {
                this.members = JSON.parse(raw);
            }
        } catch (e) {}

        const urlParams = new URLSearchParams(window.location.search);
        const nikParam = urlParams.get('nik');

        if (this.members && this.members.length > 0) {
            if (nikParam) {
                this.member = this.members.find(m => m.nik === nikParam) || this.members[0];
            } else {
                this.member = this.members[0];
            }
        } else {
            this.member = {
                id: 'MBR-001',
                nik: '1274092100340001',
                name: 'Bambang Pamungkas',
                dept: 'Logistik & Gudang',
                phone: '81234567890',
                creditLimit: 5000000,
                currentDebt: 1250000,
                status: 'AKTIF'
            };
        }
    },

    saveChanges() {
        if (!this.member) return;
        const idx = this.members.findIndex(m => m.id === this.member.id || m.nik === this.member.nik);
        if (idx !== -1) {
            this.members[idx] = JSON.parse(JSON.stringify(this.member));
        } else {
            this.members.push(JSON.parse(JSON.stringify(this.member)));
        }
        localStorage.setItem('pos_ilp_members_v1', JSON.stringify(this.members));
        this.triggerToast('Perubahan profil anggota berhasil disimpan!');
    },

    applyDebtAdjustment(type) {
        const amt = Number(this.adjustAmount) || 0;
        if (amt <= 0) {
            this.triggerToast('Masukkan nominal penyesuaian yang valid.', 'error');
            return;
        }

        if (type === 'add') {
            this.member.currentDebt = (Number(this.member.currentDebt) || 0) + amt;
            this.triggerToast(`Piutang berhasil ditambah sebesar ${this.formatRupiah(amt)}.`);
        } else if (type === 'reduce') {
            this.member.currentDebt = Math.max(0, (Number(this.member.currentDebt) || 0) - amt);
            this.triggerToast(`Piutang berhasil dikurangi sebesar ${this.formatRupiah(amt)}.`);
        }
        this.adjustAmount = 0;
        this.saveChanges();
    },

    triggerToast(msg, type = 'success') {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3200);
    }
}"
x-init="initOpening(); initDetail();"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden relative">
    
    <!-- Sidebar -->
    <x-manager-sidebar active="member_detail" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        
        <!-- Top Header -->
        <header class="h-[72px] bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-3">
                <a href="{{ url('/members') }}" class="p-2 rounded-lg text-gray-400 hover:text-gray-800 hover:bg-gray-100 transition-colors">
                    <span class="material-symbols-rounded">arrow_back</span>
                </a>
                <h1 class="text-[18px] font-black text-gray-900 tracking-tight">Detail Profil Anggota Kopkar</h1>
            </div>

            <div class="flex items-center gap-6 text-gray-500">
                <a href="{{ url('/members') }}" class="px-4 py-2 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50">
                    Kembali ke Daftar
                </a>
            </div>
        </header>

        <!-- Subheader -->
        <div class="px-8 py-4 flex items-center justify-between shrink-0 bg-[#f8f9fa]">
            <h2 class="text-sm font-bold text-gray-700">Manajemen Hak Akses &amp; Plafon Piutang</h2>
            <div class="bg-[#eef2ff] text-[#0052cc] px-4 py-1 rounded-full text-xs font-mono font-bold" x-text="'ID: ' + (member?.id || '-')"></div>
        </div>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto px-8 pb-[100px]">
            <div class="flex flex-col lg:flex-row gap-6 items-start">
                
                <!-- Left: Informasi Personal -->
                <div class="flex-1 w-full bg-white rounded-2xl border border-gray-200 shadow-xs p-6 md:p-8">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]">person</span>
                        </div>
                        <h3 class="text-sm font-bold text-gray-900">Informasi Personal Anggota</h3>
                    </div>

                    <div class="space-y-4 text-xs">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Nama Lengkap</label>
                            <input type="text" x-model="member.name" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 font-medium text-xs">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">NIK / ID Karyawan</label>
                                <input type="text" x-model="member.nik" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 font-mono font-bold text-xs">
                            </div>
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Departemen</label>
                                <select x-model="member.dept" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 bg-white font-semibold text-xs">
                                    <option value="Logistik & Gudang">Logistik &amp; Gudang</option>
                                    <option value="Produksi Utama">Produksi Utama</option>
                                    <option value="Administrasi">Administrasi</option>
                                    <option value="Maintenance & Utility">Maintenance &amp; Utility</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Nomor Telepon / WhatsApp</label>
                            <input type="text" x-model="member.phone" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 font-medium text-xs">
                        </div>
                    </div>
                </div>

                <!-- Right: Credit Cards & Adjustment -->
                <div class="w-full lg:w-[420px] flex flex-col gap-6 shrink-0">
                    
                    <!-- Top Blue Card -->
                    <div class="rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                        <div class="bg-[#0052cc] p-6 text-white flex flex-col gap-4">
                            <div class="flex justify-between items-start">
                                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center backdrop-blur-xs">
                                    <span class="material-symbols-rounded text-white text-[22px]">account_balance_wallet</span>
                                </div>
                                <span class="text-[10px] font-bold tracking-widest uppercase bg-white/20 px-2.5 py-0.5 rounded-full" x-text="'STATUS: ' + (member?.status || 'AKTIF')"></span>
                            </div>
                            
                            <div>
                                <p class="text-xs text-blue-100 mb-0.5">Total Piutang Belanja Saat Ini</p>
                                <h3 class="text-2xl font-mono font-black" x-text="formatRupiah(member?.currentDebt || 0)"></h3>
                            </div>

                            <div class="w-full h-px bg-white/20"></div>

                            <div class="flex justify-between text-xs">
                                <div>
                                    <p class="text-[10.5px] text-blue-200">Plafon Bulanan</p>
                                    <span class="font-mono font-bold" x-text="formatRupiah(member?.creditLimit || 0)"></span>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10.5px] text-blue-200">Sisa Plafon</p>
                                    <span class="font-mono font-bold text-emerald-300" x-text="formatRupiah(Math.max(0, (member?.creditLimit || 0) - (member?.currentDebt || 0)))"></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Manual Adjustment Section -->
                        <div class="bg-white p-6 flex flex-col gap-3 text-xs">
                            <div class="flex justify-between items-center">
                                <h4 class="font-bold text-gray-900">Penyesuaian Saldo Piutang</h4>
                                <span class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded">Manual Adjustment</span>
                            </div>
                            
                            <div>
                                <label class="block text-[11px] text-gray-500 font-medium mb-1">Nominal Penyesuaian (Rp):</label>
                                <input type="number"
                                       step="10000"
                                       x-model.number="adjustAmount"
                                       placeholder="0"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 font-mono font-bold text-xs">
                            </div>

                            <div class="flex gap-2 pt-1">
                                <button type="button"
                                        @click="applyDebtAdjustment('add')"
                                        class="flex-1 py-2 bg-blue-50 hover:bg-blue-100 text-[#0052cc] rounded-xl font-bold text-xs flex items-center justify-center gap-1 cursor-pointer">
                                    <span class="material-symbols-rounded text-[16px]">add_circle</span>
                                    <span>Tambah Piutang</span>
                                </button>
                                <button type="button"
                                        @click="applyDebtAdjustment('reduce')"
                                        class="flex-1 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-xl font-bold text-xs flex items-center justify-center gap-1 cursor-pointer">
                                    <span class="material-symbols-rounded text-[16px]">remove_circle</span>
                                    <span>Kurangi Piutang</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        
        <!-- Bottom Action Bar -->
        <div class="absolute bottom-0 left-0 right-0 h-[72px] bg-white border-t border-gray-200 flex items-center justify-between px-8 z-10 shadow-lg">
            <div class="flex items-center gap-2 text-gray-500 text-xs">
                <span class="material-symbols-rounded text-[18px] text-[#0052cc]">info</span>
                <span>Perubahan data profil dan plafon langsung tersimpan ke basis data anggota.</span>
            </div>
            <div class="flex gap-3">
                <a href="{{ url('/members') }}" class="px-5 py-2 rounded-xl text-gray-700 font-bold text-xs border border-gray-300 hover:bg-gray-50">
                    Batal
                </a>
                <button type="button"
                        @click="saveChanges()"
                        class="px-6 py-2 bg-[#0052cc] text-white font-bold text-xs rounded-xl shadow-xs hover:bg-[#0043a8] cursor-pointer">
                    Simpan Perubahan
                </button>
            </div>
        </div>
    </main>

    <!-- TOAST -->
    <div x-show="showToast"
         x-transition.opacity
         style="display: none;"
         class="fixed top-5 right-5 z-50 px-5 py-3 rounded-2xl bg-gray-900 text-white font-bold text-xs shadow-xl flex items-center gap-2">
        <span class="material-symbols-rounded text-[18px] text-green-400">check_circle</span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
