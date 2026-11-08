@extends('layouts.app')

@section('title', 'Detail Profil Anggota & Riwayat Kasbon - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    member: null,
    mutations: [],
    branches: [
        'Cabang 1 - Mill / Pabrik',
        'Cabang 2 - Kebun Barat',
        'Cabang 3 - Kebun Timur'
    ],
    isLoading: true,
    isSaving: false,

    // Plafon Modal
    showLimitModal: false,
    newCreditLimit: 5000000,
    limitNotes: '',

    // Settlement Modal
    showSettlementModal: false,
    settlementAmount: 0,
    settlementType: 'PAYMENT_SETTLEMENT',
    settlementNotes: '',

    // Toast
    toastMessage: '',
    toastType: 'success',
    showToast: false,

    async initDetail() {
        const urlParams = new URLSearchParams(window.location.search);
        const memberId = urlParams.get('id') || urlParams.get('nik');

        if (!memberId) {
            // Default to first member
            await this.loadFirstMember();
            return;
        }

        await this.loadMember(memberId);
    },

    async loadFirstMember() {
        try {
            const res = await fetch('/api/members?per_page=1', { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            if (json.success && json.data.length > 0) {
                await this.loadMember(json.data[0].id);
            }
        } catch (e) {
            this.triggerToast('Gagal memuat data awal anggota.', 'error');
        }
    },

    async loadMember(idOrNik) {
        this.isLoading = true;
        try {
            const res = await fetch(`/api/members/${idOrNik}`, {
                headers: { 'Accept': 'application/json' }
            });

            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.member = json.data;
                    this.newCreditLimit = json.data.credit_limit;
                    await this.loadHistory(json.data.id);
                }
            } else {
                this.triggerToast('Anggota tidak ditemukan pada sistem.', 'error');
            }
        } catch (e) {
            this.triggerToast('Terjadi kesalahan jaringan saat memuat profil.', 'error');
        } finally {
            this.isLoading = false;
        }
    },

    async loadHistory(memberId) {
        try {
            const res = await fetch(`/api/members/${memberId}/debt-history?per_page=20`, {
                headers: { 'Accept': 'application/json' }
            });
            const json = await res.json();
            if (json.success) {
                this.mutations = json.data;
            }
        } catch (e) {
            console.error('Error loading history:', e);
        }
    },

    async saveProfileChanges() {
        if (!this.member) return;
        this.isSaving = true;

        try {
            const res = await fetch(`/api/members/${this.member.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    name: this.member.name,
                    department: this.member.department,
                    branch: this.member.branch,
                    phone: this.member.phone,
                    status: this.member.status,
                    notes: this.member.notes
                })
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.triggerToast(json.message);
                await this.loadMember(this.member.id);
            } else {
                this.triggerToast(json.message || 'Gagal menyimpan profil.', 'error');
            }
        } catch (e) {
            this.triggerToast('Gagal terhubung ke API.', 'error');
        } finally {
            this.isSaving = false;
        }
    },

    async updateCreditLimit() {
        if (!this.member) return;
        const amt = Number(this.newCreditLimit);
        if (amt < Number(this.member.current_debt)) {
            this.triggerToast('Plafon baru tidak boleh lebih kecil dari saldo kasbon berjalan!', 'error');
            return;
        }

        this.isSaving = true;
        try {
            const res = await fetch(`/api/members/${this.member.id}/credit-limit`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    credit_limit: amt,
                    notes: this.limitNotes || 'Penyesuaian batas maksimal kasbon bulanan',
                    processed_by: 'Manager Koperasi'
                })
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.showLimitModal = false;
                this.triggerToast(json.message);
                await this.loadMember(this.member.id);
            } else {
                this.triggerToast(json.message || 'Gagal mengubah plafon.', 'error');
            }
        } catch (e) {
            this.triggerToast('Koneksi terputus.', 'error');
        } finally {
            this.isSaving = false;
        }
    },

    async processSettlement() {
        if (!this.member) return;
        const amt = Number(this.settlementAmount);
        if (amt <= 0 || amt > Number(this.member.current_debt)) {
            this.triggerToast('Nominal pembayaran tidak valid.', 'error');
            return;
        }

        this.isSaving = true;
        try {
            const res = await fetch(`/api/members/${this.member.id}/settlement`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    amount: amt,
                    type: this.settlementType,
                    notes: this.settlementNotes || 'Pelunasan piutang kasbon',
                    processed_by: 'Finance Koperasi ILP'
                })
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.showSettlementModal = false;
                this.triggerToast(json.message);
                await this.loadMember(this.member.id);
            } else {
                this.triggerToast(json.message || 'Gagal memproses pembayaran.', 'error');
            }
        } catch (e) {
            this.triggerToast('Koneksi terputus.', 'error');
        } finally {
            this.isSaving = false;
        }
    },

    triggerToast(msg, type = 'success') {
        this.toastMessage = msg;
        this.toastType = type;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3500);
    }
}"
x-init="initOpening(); initDetail();"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden relative">
    
    <!-- Sidebar -->
    <x-manager-sidebar active="members" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        
        <!-- Top Header -->
        <header class="h-[72px] bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-3">
                <a href="{{ url('/members') }}" class="p-2 rounded-lg text-gray-400 hover:text-gray-800 hover:bg-gray-100 transition-colors">
                    <span class="material-symbols-rounded">arrow_back</span>
                </a>
                <div>
                    <h1 class="text-[18px] font-black text-gray-900 tracking-tight">Detail Profil Karyawan &amp; Kasbon</h1>
                    <p class="text-xs text-gray-400">Master Data Koperasi Multi-Cabang PT Indo Lampung Perkasa</p>
                </div>
            </div>

            <div class="flex items-center gap-4 text-gray-500">
                <a href="{{ url('/members') }}" class="px-4 py-2 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50">
                    Kembali ke Database
                </a>
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-8 pb-[100px]">
            <!-- Loading State -->
            <template x-if="isLoading">
                <div class="flex items-center justify-center p-16">
                    <div class="flex items-center gap-3 text-gray-500">
                        <span class="material-symbols-rounded animate-spin text-[32px] text-[#0052cc]">progress_activity</span>
                        <span class="font-bold text-sm">Memuat profil dan mutasi kasbon anggota...</span>
                    </div>
                </div>
            </template>

            <!-- Main Content Card when Loaded -->
            <template x-if="!isLoading && member">
                <div class="space-y-6 max-w-[1400px] mx-auto">
                    
                    <div class="flex flex-col lg:flex-row gap-6 items-start">
                        <!-- Left: Form Profil Karyawan -->
                        <div class="flex-1 w-full bg-white rounded-3xl border border-gray-200/90 shadow-xs p-6 md:p-8">
                            <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-100">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-2xl bg-blue-50 text-[#0052cc] flex items-center justify-center font-bold">
                                        <span class="material-symbols-rounded text-[26px]">badge</span>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-black text-gray-900" x-text="member.name"></h3>
                                        <span class="text-xs font-mono text-gray-400" x-text="'Kode: ' + member.member_code"></span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold border"
                                          :class="member.status === 'AKTIF' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200'"
                                          x-text="member.status">
                                    </span>
                                </div>
                            </div>

                            <div class="space-y-4 text-xs">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Nama Lengkap Karyawan</label>
                                        <input type="text" x-model="member.name" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 font-semibold text-xs focus:outline-none focus:border-[#0052cc]">
                                    </div>

                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">NIK Karyawan (Nomor Induk)</label>
                                        <input type="text" x-model="member.nik" readonly class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 font-mono font-bold text-xs text-gray-600">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Cabang Penugasan (Multi-Cabang)</label>
                                        <select x-model="member.branch" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 bg-white font-bold text-xs focus:outline-none focus:border-[#0052cc]">
                                            <template x-for="b in branches" :key="b">
                                                <option :value="b" x-text="b"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Departemen / Divisi</label>
                                        <input type="text" x-model="member.department" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 font-semibold text-xs focus:outline-none focus:border-[#0052cc]">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">No. WhatsApp / Telepon</label>
                                        <input type="text" x-model="member.phone" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 font-medium text-xs focus:outline-none focus:border-[#0052cc]">
                                    </div>

                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Status Keanggotaan Koperasi</label>
                                        <select x-model="member.status" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 bg-white font-bold text-xs focus:outline-none focus:border-[#0052cc]">
                                            <option value="AKTIF">AKTIF (Dapat Kasbon)</option>
                                            <option value="DIBLOKIR">DIBLOKIR (Kredit Ditangguhkan)</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label class="block font-bold text-gray-700 mb-1">Catatan Tambahan</label>
                                    <textarea x-model="member.notes" rows="3" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-xs focus:outline-none focus:border-[#0052cc]"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Plafon Kasbon Card & Actions -->
                        <div class="w-full lg:w-[440px] flex flex-col gap-5 shrink-0">
                            
                            <!-- Plafon Kredit Card -->
                            <div class="rounded-3xl border border-gray-200/90 shadow-sm overflow-hidden bg-white">
                                <div class="bg-gradient-to-br from-[#0052cc] to-[#00388d] p-6 text-white flex flex-col gap-4">
                                    <div class="flex justify-between items-start">
                                        <div class="w-10 h-10 rounded-xl bg-white/15 flex items-center justify-center backdrop-blur-xs">
                                            <span class="material-symbols-rounded text-white text-[22px]">account_balance_wallet</span>
                                        </div>
                                        <span class="text-[10px] font-bold tracking-widest uppercase bg-white/20 px-3 py-1 rounded-full"
                                              x-text="'Plafon Terpakai: ' + member.debt_utilization_percent + '%'"></span>
                                    </div>
                                    
                                    <div>
                                        <p class="text-xs text-blue-100 mb-1">Total Kasbon Berjalan Saat Ini</p>
                                        <h3 class="text-3xl font-mono font-black tracking-tight" x-text="formatRupiah(member.current_debt)"></h3>
                                    </div>

                                    <!-- Progress Bar -->
                                    <div class="w-full bg-black/20 rounded-full h-2 overflow-hidden">
                                        <div class="bg-amber-400 h-full rounded-full transition-all"
                                             :style="'width: ' + member.debt_utilization_percent + '%'"></div>
                                    </div>

                                    <div class="w-full h-px bg-white/15"></div>

                                    <div class="flex justify-between text-xs">
                                        <div>
                                            <p class="text-[11px] text-blue-200">Batas Plafon Bulanan</p>
                                            <span class="font-mono font-bold text-sm" x-text="formatRupiah(member.credit_limit)"></span>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-[11px] text-blue-200">Sisa Plafon Tersedia</p>
                                            <span class="font-mono font-bold text-sm text-emerald-300" x-text="formatRupiah(member.remaining_credit)"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="p-6 bg-white space-y-3">
                                    <button type="button"
                                            @click="showLimitModal = true"
                                            class="w-full py-2.5 px-4 bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition-colors">
                                        <span class="material-symbols-rounded text-[18px]">tune</span>
                                        <span>Atur Ulang Batas Plafon Bulanan</span>
                                    </button>

                                    <button type="button"
                                            @click="settlementAmount = member.current_debt; showSettlementModal = true"
                                            :disabled="member.current_debt <= 0"
                                            class="w-full py-2.5 px-4 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                        <span class="material-symbols-rounded text-[18px]">price_check</span>
                                        <span>Catat Pelunasan / Potong Gaji</span>
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Riwayat Transaksi Kasbon & Mutasi Piutang -->
                    <div class="bg-white rounded-3xl border border-gray-200/90 shadow-xs overflow-hidden p-6 md:p-8">
                        <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-100">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center">
                                    <span class="material-symbols-rounded text-[22px]">history_edu</span>
                                </div>
                                <div>
                                    <h3 class="text-base font-black text-gray-900">Riwayat Mutasi &amp; Transaksi Kasbon</h3>
                                    <p class="text-xs text-gray-400">Jejak audit terpusat transaksi kasbon dari seluruh cabang</p>
                                </div>
                            </div>
                            <span class="text-xs font-bold text-gray-500" x-text="mutations.length + ' Riwayat Tercatat'"></span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs min-w-[750px]">
                                <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 font-bold uppercase tracking-wider text-[11px]">
                                    <tr>
                                        <th class="px-5 py-3">WAKTU & TANGGAL</th>
                                        <th class="px-5 py-3">INVOICE / JENIS MUTASI</th>
                                        <th class="px-5 py-3">CABANG TRANSAKSI</th>
                                        <th class="px-5 py-3 text-right">NOMINAL</th>
                                        <th class="px-5 py-3 text-right">SALDO SEBELUM</th>
                                        <th class="px-5 py-3 text-right">SALDO AKHIR</th>
                                        <th class="px-5 py-3">PETUGAS / KASIR</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-gray-700">
                                    <template x-if="mutations.length === 0">
                                        <tr>
                                            <td colspan="7" class="px-5 py-8 text-center text-gray-400">
                                                Belum ada mutasi transaksi kasbon untuk anggota ini.
                                            </td>
                                        </tr>
                                    </template>
                                    <template x-for="mut in mutations" :key="mut.id">
                                        <tr class="hover:bg-gray-50/60">
                                            <td class="px-5 py-3 whitespace-nowrap text-gray-500 font-mono"
                                                x-text="new Date(mut.created_at).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })">
                                            </td>
                                            <td class="px-5 py-3 whitespace-nowrap">
                                                <span class="font-mono font-bold text-gray-900 block" x-text="mut.invoice_number || '-'"></span>
                                                <span class="text-[10px] px-2 py-0.5 rounded font-bold"
                                                      :class="mut.mutation_type === 'KASBON_POS' ? 'bg-amber-50 text-amber-700' : (mut.mutation_type === 'LIMIT_ADJUSTMENT' ? 'bg-purple-50 text-purple-700' : 'bg-emerald-50 text-emerald-700')"
                                                      x-text="mut.mutation_type">
                                                </span>
                                            </td>
                                            <td class="px-5 py-3 whitespace-nowrap text-gray-600 font-semibold" x-text="mut.branch"></td>
                                            <td class="px-5 py-3 text-right font-mono font-black whitespace-nowrap"
                                                :class="mut.mutation_type === 'KASBON_POS' ? 'text-amber-600' : 'text-emerald-600'"
                                                x-text="(mut.mutation_type === 'KASBON_POS' ? '+ ' : '- ') + formatRupiah(mut.amount)">
                                            </td>
                                            <td class="px-5 py-3 text-right font-mono text-gray-400 whitespace-nowrap" x-text="formatRupiah(mut.previous_debt)"></td>
                                            <td class="px-5 py-3 text-right font-mono font-bold text-gray-800 whitespace-nowrap" x-text="formatRupiah(mut.new_debt)"></td>
                                            <td class="px-5 py-3 whitespace-nowrap text-gray-500" x-text="mut.processed_by || '-'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </template>
        </div>

        <!-- Bottom Action Bar -->
        <div class="absolute bottom-0 left-0 right-0 h-[72px] bg-white border-t border-gray-200 flex items-center justify-between px-8 z-10 shadow-lg">
            <div class="flex items-center gap-2 text-gray-500 text-xs">
                <span class="material-symbols-rounded text-[18px] text-[#0052cc]">sync</span>
                <span>Data terintegrasi secara terpusat antar 3 cabang (Mill &amp; Kebun).</span>
            </div>
            <div class="flex gap-3">
                <a href="{{ url('/members') }}" class="px-5 py-2 rounded-xl text-gray-700 font-bold text-xs border border-gray-300 hover:bg-gray-50">
                    Batal
                </a>
                <button type="button"
                        @click="saveProfileChanges()"
                        :disabled="isSaving || isLoading"
                        class="px-6 py-2 bg-[#0052cc] text-white font-bold text-xs rounded-xl shadow-xs hover:bg-[#0043a8] flex items-center gap-1.5">
                    <span x-show="isSaving" class="material-symbols-rounded text-sm animate-spin">progress_activity</span>
                    <span>Simpan Perubahan Profil</span>
                </button>
            </div>
        </div>
    </main>

    <!-- Modal Atur Plafon -->
    <div x-show="showLimitModal"
         class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-gray-200"
             @click.outside="showLimitModal = false">
            <h3 class="text-base font-black text-gray-900 mb-1">Ubah Batas Plafon Kasbon</h3>
            <p class="text-xs text-gray-400 mb-4">Pengaturan batas kasbon bulanan per anggota.</p>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Plafon Bulanan Baru (Rp)</label>
                    <input type="number"
                           x-model="newCreditLimit"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-300 font-mono font-bold text-sm text-purple-700 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Alasan Penyesuaian</label>
                    <input type="text"
                           x-model="limitNotes"
                           placeholder="Alasan perubahan limit"
                           class="w-full px-3.5 py-2 rounded-xl border border-gray-300 text-xs focus:outline-none">
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="showLimitModal = false" class="px-4 py-2 border rounded-xl text-xs font-bold text-gray-600">Batal</button>
                    <button type="button" @click="updateCreditLimit()" :disabled="isSaving" class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold">Simpan Plafon</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Settlement -->
    <div x-show="showSettlementModal"
         class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-gray-200"
             @click.outside="showSettlementModal = false">
            <h3 class="text-base font-black text-gray-900 mb-1">Catat Pelunasan / Potong Gaji</h3>
            <p class="text-xs text-gray-400 mb-4">Pemotongan piutang kasbon berjalan.</p>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Nominal Pembayaran (Rp)</label>
                    <input type="number"
                           x-model="settlementAmount"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-300 font-mono font-bold text-sm text-emerald-700 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Metode Pelunasan</label>
                    <select x-model="settlementType" class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs font-semibold">
                        <option value="PAYMENT_SETTLEMENT">Pelunasan Tunai / Transfer</option>
                        <option value="PAYROLL_DEDUCTION">Potong Gaji Rutin Payroll PT ILP</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Keterangan Bukti</label>
                    <input type="text"
                           x-model="settlementNotes"
                           placeholder="Nomor bukti / keterangan transfer"
                           class="w-full px-3.5 py-2 rounded-xl border border-gray-300 text-xs focus:outline-none">
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="showSettlementModal = false" class="px-4 py-2 border rounded-xl text-xs font-bold text-gray-600">Batal</button>
                    <button type="button" @click="processSettlement()" :disabled="isSaving" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold">Proses Pelunasan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div x-show="showToast"
         class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-2xl shadow-xl border text-xs font-bold"
         :class="toastType === 'error' ? 'bg-red-900 text-white border-red-800' : 'bg-gray-900 text-white border-gray-800'"
         style="display: none;">
        <span class="material-symbols-rounded text-emerald-400" x-show="toastType === 'success'">check_circle</span>
        <span class="material-symbols-rounded text-red-400" x-show="toastType === 'error'">error</span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
