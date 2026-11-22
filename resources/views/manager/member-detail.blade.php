@extends('layouts.app')

@section('title', 'Detail Profil Anggota & Riwayat Kasbon - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    member: null,
    mutations: [],
    branches: [
        'Cabang Pusat',
        'Cabang A',
        'Cabang B'
    ],
    isLoading: true,
    isSaving: false,

    // Autocomplete Recommendation State
    nameInputValue: '',
    nikInputValue: '',
    emptySearchInput: '',
    searchType: null, // 'name' | 'nik' | 'empty_state'
    searchQuery: '',
    suggestions: [],
    isSearching: false,
    showSuggestions: false,
    noResults: false,
    selectedSuggestionIndex: -1,
    searchAbortController: null,

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
        this.isLoading = true;
        const urlParams = new URLSearchParams(window.location.search);
        let memberId = urlParams.get('id') || urlParams.get('nik');

        if (!memberId) {
            try {
                memberId = localStorage.getItem('pos_last_selected_member_id');
            } catch (e) {}
        }

        if (memberId) {
            await this.loadMember(memberId);
            if (this.member) {
                return;
            }
        }

        await this.loadFirstMember();
    },

    async loadFirstMember() {
        this.isLoading = true;
        try {
            const res = await fetch('/api/members?per_page=1', { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const json = await res.json();
                if (json.success && Array.isArray(json.data) && json.data.length > 0) {
                    await this.loadMember(json.data[0].id);
                    return;
                }
            }
            this.member = null;
            this.nameInputValue = '';
            this.nikInputValue = '';
        } catch (e) {
            console.error('Error loading first member:', e);
            this.member = null;
            this.nameInputValue = '';
            this.nikInputValue = '';
            this.triggerToast('Gagal memuat data awal anggota.', 'error');
        } finally {
            this.isLoading = false;
        }
    },

    async loadMember(idOrNik) {
        if (!idOrNik) {
            this.member = null;
            this.nameInputValue = '';
            this.nikInputValue = '';
            this.isLoading = false;
            return;
        }
        this.isLoading = true;
        try {
            const res = await fetch(`/api/members/${encodeURIComponent(idOrNik)}`, {
                headers: { 'Accept': 'application/json' }
            });

            if (res.ok) {
                const json = await res.json();
                if (json.success && json.data) {
                    this.member = json.data;
                    this.newCreditLimit = json.data.credit_limit;
                    this.nameInputValue = json.data.name;
                    this.nikInputValue = (json.data.nik !== null && json.data.nik !== undefined) ? String(json.data.nik) : '';
                    try {
                        localStorage.setItem('pos_last_selected_member_id', String(json.data.id));
                    } catch (e) {}
                    try {
                        const currentUrl = new URL(window.location.href);
                        if (currentUrl.searchParams.get('id') !== String(json.data.id)) {
                            currentUrl.searchParams.set('id', String(json.data.id));
                            currentUrl.searchParams.delete('nik');
                            window.history.replaceState({}, '', currentUrl.pathname + '?' + currentUrl.searchParams.toString());
                        }
                    } catch (e) {}
                    await this.loadHistory(json.data.id);
                } else {
                    this.member = null;
                    this.nameInputValue = '';
                    this.nikInputValue = '';
                    this.triggerToast(json.message || 'Anggota tidak ditemukan pada sistem.', 'error');
                }
            } else {
                this.member = null;
                this.nameInputValue = '';
                this.nikInputValue = '';
                this.triggerToast('Anggota tidak ditemukan pada sistem.', 'error');
            }
        } catch (e) {
            console.error('Error loading member:', e);
            this.member = null;
            this.nameInputValue = '';
            this.nikInputValue = '';
            this.triggerToast('Terjadi kesalahan jaringan saat memuat profil.', 'error');
        } finally {
            this.isLoading = false;
        }
    },

    async onSearchInput(type, value) {
        this.searchType = type;
        const query = (value !== null && value !== undefined) ? String(value).trim() : '';
        this.searchQuery = query;
        this.selectedSuggestionIndex = -1;

        if (!query || query.length === 0) {
            this.suggestions = [];
            this.showSuggestions = false;
            this.noResults = false;
            return;
        }

        this.isSearching = true;
        this.showSuggestions = true;
        this.noResults = false;

        if (this.searchAbortController) {
            this.searchAbortController.abort();
        }
        this.searchAbortController = new AbortController();

        try {
            const res = await fetch(`/api/members?search=${encodeURIComponent(query)}&per_page=10`, {
                headers: { 'Accept': 'application/json' },
                signal: this.searchAbortController.signal
            });

            if (res.ok) {
                const json = await res.json();
                if (json.success && Array.isArray(json.data)) {
                    this.suggestions = json.data;
                    this.noResults = (this.suggestions.length === 0);
                } else {
                    this.suggestions = [];
                    this.noResults = true;
                }
            } else {
                this.suggestions = [];
                this.noResults = true;
            }
        } catch (e) {
            if (e.name !== 'AbortError') {
                console.error('Search error:', e);
                this.suggestions = [];
                this.noResults = true;
            }
        } finally {
            this.isSearching = false;
        }
    },

    async selectMemberFromSuggestion(m) {
        if (!m || !m.id) return;
        this.showSuggestions = false;
        this.searchType = null;
        this.suggestions = [];
        this.noResults = false;
        this.nameInputValue = m.name;
        this.nikInputValue = (m.nik !== null && m.nik !== undefined) ? String(m.nik) : '';
        this.emptySearchInput = '';
        await this.loadMember(m.id);
        this.triggerToast(`Data anggota [${m.name}] berhasil dimuat.`);
    },

    handleKeydown(e, type) {
        if (!this.showSuggestions) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (this.suggestions.length > 0) {
                this.selectedSuggestionIndex = (this.selectedSuggestionIndex + 1) % this.suggestions.length;
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (this.suggestions.length > 0) {
                this.selectedSuggestionIndex = (this.selectedSuggestionIndex - 1 + this.suggestions.length) % this.suggestions.length;
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (this.selectedSuggestionIndex >= 0 && this.suggestions[this.selectedSuggestionIndex]) {
                this.selectMemberFromSuggestion(this.suggestions[this.selectedSuggestionIndex]);
            } else if (this.suggestions.length > 0) {
                this.selectMemberFromSuggestion(this.suggestions[0]);
            } else {
                this.triggerToast('Anggota tidak terdaftar di database dan tidak dapat dipilih.', 'error');
                this.closeSuggestions();
            }
        } else if (e.key === 'Escape') {
            this.closeSuggestions();
        }
    },

    closeSuggestions() {
        this.showSuggestions = false;
        this.searchType = null;
        this.suggestions = [];
        this.noResults = false;
        if (this.member) {
            this.nameInputValue = this.member.name;
            this.nikInputValue = (this.member.nik !== null && this.member.nik !== undefined) ? String(this.member.nik) : '';
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
                    processed_by: @js(auth()->user()?->name ?? 'Manager')
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
                    processed_by: @js(auth()->user()?->name ?? 'Manager')
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
        if (!msg || typeof msg !== 'string' || !msg.trim()) return;
        this.toastMessage = msg;
        this.toastType = type;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3500);
    },

    formatMutationNominal(mut) {
        if (!mut) return 'Rp 0';
        const fmt = (val) => {
            const n = Math.abs(Number(val) || 0);
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(n);
        };

        if (mut.mutation_type === 'LIMIT_ADJUSTMENT') {
            const prev = Number(mut.previous_limit ?? 0);
            const next = Number(mut.new_limit ?? 0);
            const diff = next - prev;
            if (diff > 0) {
                return '+ ' + fmt(diff);
            } else if (diff < 0) {
                return '- ' + fmt(Math.abs(diff));
            } else {
                return 'Rp 0';
            }
        }

        const amt = Number(mut.amount) || 0;
        if (mut.mutation_type === 'KASBON_POS') {
            return '+ ' + fmt(amt);
        }

        return '- ' + fmt(amt);
    },

    getMutationNominalClass(mut) {
        if (!mut) return 'text-gray-700';

        if (mut.mutation_type === 'LIMIT_ADJUSTMENT') {
            const prev = Number(mut.previous_limit ?? 0);
            const next = Number(mut.new_limit ?? 0);
            const diff = next - prev;
            if (diff > 0) {
                return 'text-emerald-600'; // Plafon dinaikkan (+)
            } else if (diff < 0) {
                return 'text-rose-600'; // Plafon diturunkan (-)
            }
            return 'text-gray-500'; // Plafon tidak berubah
        }

        if (mut.mutation_type === 'KASBON_POS') {
            return 'text-amber-600';
        }

        return 'text-emerald-600';
    }
}"
x-init="initOpening(); initDetail();"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden relative">
    
    <!-- Sidebar -->
    <x-manager-sidebar active="member_detail" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden relative">
        
        <!-- Top Header -->
        <header class="h-20 bg-[#f8f9fa] border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-3">
                <a href="{{ url('/members') }}" class="p-2 rounded-xl hover:bg-gray-200 text-gray-500 transition-colors cursor-pointer" title="Kembali ke Daftar Anggota">
                    <span class="material-symbols-rounded text-[20px]">arrow_back</span>
                </a>
                <h1 class="text-[20px] font-bold text-gray-900">Detail &amp; Riwayat Anggota</h1>
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200 flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[14px]">badge</span>
                    <span>Profil &amp; Kasbon</span>
                </span>
            </div>

            <div class="flex items-center gap-4 text-gray-500">
                <a href="{{ url('/members') }}"
                   class="hidden sm:flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-gray-200 hover:border-blue-200 hover:bg-blue-50 hover:text-[#0052cc] text-xs font-bold transition-colors cursor-pointer"
                   title="Buka Daftar Anggota">
                    <span class="material-symbols-rounded text-[18px]">groups</span>
                    <span>Pilih Anggota</span>
                </a>
                <x-notification-bell role="MANAGER" />
                <button type="button" @click="window.PosRealtime?.syncNow?.(); initDetail();" class="hover:text-gray-900 transition-colors cursor-pointer" title="Sinkronkan Data">
                    <span class="material-symbols-rounded text-[24px]" :class="$store.network?.isChecking ? 'animate-spin text-[#0052cc]' : ''">autorenew</span>
                </button>
                <div class="h-6 w-px bg-gray-300"></div>
                <x-realtime-network-badge />
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-8">
            <!-- Loading State -->
            <template x-if="isLoading">
                <div class="flex items-center justify-center p-16">
                    <div class="flex items-center gap-3 text-gray-500">
                        <span class="material-symbols-rounded animate-spin text-[32px] text-[#0052cc]">progress_activity</span>
                        <span class="font-bold text-sm">Memuat profil dan mutasi kasbon anggota...</span>
                    </div>
                </div>
            </template>

            <!-- Empty State when Not Loading and No Member Found / No Members Exist -->
            <template x-if="!isLoading && !member">
                <div class="max-w-[700px] mx-auto my-12 bg-white rounded-3xl border border-gray-200/90 shadow-xs p-8 sm:p-12 text-center">
                    <div class="w-20 h-20 rounded-3xl bg-blue-50 text-[#0052cc] flex items-center justify-center mx-auto mb-6 shadow-inner">
                        <span class="material-symbols-rounded text-[40px]">person_search</span>
                    </div>
                    <h3 class="text-lg font-black text-gray-900 mb-2">Belum Ada Anggota yang Dipilih</h3>
                    <p class="text-xs sm:text-sm text-gray-500 max-w-md mx-auto mb-6 leading-relaxed">
                        Ketik nama lengkap atau NIK untuk mencari anggota dari daftar anggota, atau pilih langsung dari halaman daftar anggota.
                    </p>

                    <!-- Search Input in Empty State with Autocomplete Recommendations -->
                    <div class="max-w-md mx-auto mb-8 relative text-left" @click.outside="if (searchType === 'empty_state') closeSuggestions()">
                        <div class="relative">
                            <input type="text"
                                   x-model="emptySearchInput"
                                   @input.debounce.250ms="onSearchInput('empty_state', emptySearchInput)"
                                   @focus="onSearchInput('empty_state', emptySearchInput)"
                                   @keydown="handleKeydown($event, 'empty_state')"
                                   placeholder="Ketik nama lengkap atau NIK anggota..."
                                   autocomplete="off"
                                   class="w-full pl-4 pr-10 py-3 rounded-2xl border border-gray-300 font-semibold text-xs focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/10 shadow-xs bg-white">
                            
                            <div class="absolute right-3.5 top-1/2 -translate-y-1/2 flex items-center pointer-events-none text-gray-400">
                                <span x-show="isSearching && searchType === 'empty_state'" class="material-symbols-rounded text-[20px] text-[#0052cc] animate-spin">progress_activity</span>
                                <span x-show="!(isSearching && searchType === 'empty_state')" class="material-symbols-rounded text-[20px]">person_search</span>
                            </div>
                        </div>

                        <!-- Recommendations dropdown for empty state -->
                        <div x-cloak
                             x-show="showSuggestions && searchType === 'empty_state'"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl border border-gray-200/90 shadow-2xl z-50 overflow-hidden divide-y divide-gray-100 text-left">
                            <div class="px-3.5 py-2 bg-gray-50 flex items-center justify-between text-[11px] text-gray-500 font-semibold">
                                <span class="text-gray-700 flex items-center gap-1.5"><span class="material-symbols-rounded text-[16px] text-[#0052cc]">groups</span>Rekomendasi Anggota</span>
                                <span x-show="suggestions.length > 0" class="text-[10px] font-mono text-gray-400" x-text="suggestions.length + ' ditemukan'"></span>
                            </div>
                            <div class="max-h-60 overflow-y-auto divide-y divide-gray-50">
                                <template x-for="(sug, idx) in suggestions" :key="sug.id">
                                    <div @click="selectMemberFromSuggestion(sug)"
                                         @mouseenter="selectedSuggestionIndex = idx"
                                         :class="selectedSuggestionIndex === idx ? 'bg-blue-50/90' : 'hover:bg-gray-50'"
                                         class="px-3.5 py-2.5 flex items-center justify-between gap-3 cursor-pointer transition-colors group">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <div class="w-8 h-8 rounded-xl bg-blue-50 text-[#0052cc] group-hover:bg-[#0052cc] group-hover:text-white flex items-center justify-center font-bold text-xs shrink-0 transition-colors">
                                                <span class="material-symbols-rounded text-[18px]">person</span>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-bold text-gray-900 text-xs truncate group-hover:text-[#0052cc]" x-text="sug.name"></div>
                                                <div class="text-[10.5px] text-gray-500 font-mono truncate">
                                                    <span>NIK: </span><span class="font-bold text-gray-700" x-text="sug.nik || '-'"></span>
                                                    <span class="mx-1">•</span>
                                                    <span>Kode: </span><span x-text="sug.member_code"></span>
                                                    <span class="mx-1" x-show="sug.department">•</span>
                                                    <span class="font-sans text-gray-600" x-text="sug.department || ''"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="shrink-0 flex items-center gap-2">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
                                                  :class="sug.status === 'AKTIF' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200'"
                                                  x-text="sug.status">
                                            </span>
                                            <span class="material-symbols-rounded text-[16px] text-gray-300 group-hover:text-[#0052cc]">arrow_forward</span>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="noResults">
                                    <div class="p-4 text-center">
                                        <div class="w-8 h-8 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center mx-auto mb-1.5">
                                            <span class="material-symbols-rounded text-[18px]">person_off</span>
                                        </div>
                                        <h4 class="font-bold text-gray-800 text-xs">Anggota Tidak Ditemukan</h4>
                                        <p class="text-[11px] text-gray-500 mt-0.5">Nama/NIK ini tidak terdaftar di database dan tidak dapat dipilih.</p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-center gap-3">
                        <a href="{{ url('/members') }}"
                           class="px-5 py-2.5 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold shadow-xs transition-colors flex items-center gap-2 cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">groups</span>
                            <span>Buka Daftar Anggota</span>
                        </a>
                        <button type="button"
                                @click="initDetail()"
                                class="px-5 py-2.5 border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-xl text-xs font-bold transition-colors flex items-center gap-2 cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">refresh</span>
                            <span>Muat Ulang</span>
                        </button>
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
                                    <!-- 1. Nama Lengkap Karyawan Autocomplete Field -->
                                    <div class="relative" @click.outside="if (searchType === 'name') closeSuggestions()">
                                        <div class="flex items-center justify-between mb-1">
                                            <label class="block font-bold text-gray-700">Nama Lengkap Karyawan</label>
                                            <span class="text-[10px] font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md flex items-center gap-1">
                                                <span class="material-symbols-rounded text-[12px]">search</span>
                                                <span>Rekomendasi Anggota</span>
                                            </span>
                                        </div>
                                        <div class="relative">
                                            <input type="text"
                                                   x-model="nameInputValue"
                                                   @input.debounce.250ms="onSearchInput('name', nameInputValue)"
                                                   @focus="onSearchInput('name', nameInputValue)"
                                                   @keydown="handleKeydown($event, 'name')"
                                                   placeholder="Ketik nama karyawan..."
                                                   autocomplete="off"
                                                   class="w-full pl-3.5 pr-9 py-2.5 rounded-xl border border-gray-300 font-semibold text-xs text-gray-800 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/10 bg-white transition-all">
                                            
                                            <div class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center pointer-events-none text-gray-400">
                                                <span x-show="isSearching && searchType === 'name'" class="material-symbols-rounded text-[18px] text-[#0052cc] animate-spin">progress_activity</span>
                                                <span x-show="!(isSearching && searchType === 'name')" class="material-symbols-rounded text-[18px]">search</span>
                                            </div>
                                        </div>

                                        <!-- Autocomplete Recommendation Dropdown for Name -->
                                        <div x-cloak
                                             x-show="showSuggestions && searchType === 'name'"
                                             x-transition:enter="transition ease-out duration-100"
                                             x-transition:enter-start="opacity-0 translate-y-1"
                                             x-transition:enter-end="opacity-100 translate-y-0"
                                             x-transition:leave="transition ease-in duration-75"
                                             x-transition:leave-start="opacity-100 translate-y-0"
                                             x-transition:leave-end="opacity-0 translate-y-1"
                                             class="absolute left-0 right-0 top-full mt-1.5 bg-white rounded-2xl border border-gray-200/90 shadow-2xl z-50 overflow-hidden divide-y divide-gray-100">
                                            
                                            <!-- Dropdown Header -->
                                            <div class="px-3.5 py-2 bg-gray-50 flex items-center justify-between text-[11px] text-gray-500 font-semibold">
                                                <div class="flex items-center gap-1.5 text-gray-700">
                                                    <span class="material-symbols-rounded text-[16px] text-[#0052cc]">groups</span>
                                                    <span>Rekomendasi dari Daftar Anggota</span>
                                                </div>
                                                <span x-show="suggestions.length > 0" class="text-[10px] font-mono text-gray-400" x-text="suggestions.length + ' ditemukan'"></span>
                                            </div>

                                            <!-- Suggestion Items List -->
                                            <div class="max-h-60 overflow-y-auto divide-y divide-gray-50">
                                                <template x-for="(sug, idx) in suggestions" :key="sug.id">
                                                    <div @click="selectMemberFromSuggestion(sug)"
                                                         @mouseenter="selectedSuggestionIndex = idx"
                                                         :class="selectedSuggestionIndex === idx ? 'bg-blue-50/90' : 'hover:bg-gray-50'"
                                                         class="px-3.5 py-2.5 flex items-center justify-between gap-3 cursor-pointer transition-colors group">
                                                        <div class="flex items-center gap-2.5 min-w-0">
                                                            <div class="w-8 h-8 rounded-xl bg-blue-50 text-[#0052cc] group-hover:bg-[#0052cc] group-hover:text-white flex items-center justify-center font-bold text-xs shrink-0 transition-colors">
                                                                <span class="material-symbols-rounded text-[18px]">person</span>
                                                            </div>
                                                            <div class="min-w-0">
                                                                <div class="font-bold text-gray-900 text-xs truncate group-hover:text-[#0052cc]" x-text="sug.name"></div>
                                                                <div class="text-[10.5px] text-gray-500 font-mono truncate">
                                                                    <span>NIK: </span><span class="font-bold text-gray-700" x-text="sug.nik || '-'"></span>
                                                                    <span class="mx-1">•</span>
                                                                    <span>Kode: </span><span x-text="sug.member_code"></span>
                                                                    <span class="mx-1" x-show="sug.department">•</span>
                                                                    <span class="font-sans text-gray-600" x-text="sug.department || ''"></span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="shrink-0 flex items-center gap-2">
                                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
                                                                  :class="sug.status === 'AKTIF' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200'"
                                                                  x-text="sug.status">
                                                            </span>
                                                            <span class="material-symbols-rounded text-[16px] text-gray-300 group-hover:text-[#0052cc]">arrow_forward</span>
                                                        </div>
                                                    </div>
                                                </template>

                                                <!-- No Results State (Cannot be chosen) -->
                                                <template x-if="noResults">
                                                    <div class="p-4 text-center">
                                                        <div class="w-9 h-9 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mx-auto mb-2">
                                                            <span class="material-symbols-rounded text-[20px]">person_off</span>
                                                        </div>
                                                        <h4 class="font-bold text-gray-800 text-xs">Anggota Tidak Ditemukan</h4>
                                                        <p class="text-[11px] text-gray-500 mt-0.5 leading-relaxed">
                                                            Nama "<span class="font-bold text-gray-700" x-text="searchQuery"></span>" tidak ada di database <strong>Daftar Anggota</strong> sehingga <u>tidak dapat dipilih</u>.
                                                        </p>
                                                    </div>
                                                </template>
                                            </div>

                                            <!-- Dropdown Footer -->
                                            <div class="px-3.5 py-1.5 bg-gray-50/80 flex items-center justify-between text-[10px] text-gray-400">
                                                <span>Gunakan panah ↑ ↓ dan Enter untuk memilih</span>
                                                <span class="text-gray-500">Esc untuk tutup</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 2. NIK Karyawan Autocomplete Field -->
                                    <div class="relative" @click.outside="if (searchType === 'nik') closeSuggestions()">
                                        <div class="flex items-center justify-between mb-1">
                                            <label class="block font-bold text-gray-700">NIK Karyawan (Nomor Induk)</label>
                                            <span class="text-[10px] font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md flex items-center gap-1">
                                                <span class="material-symbols-rounded text-[12px]">search</span>
                                                <span>Rekomendasi NIK</span>
                                            </span>
                                        </div>
                                        <div class="relative">
                                            <input type="text"
                                                   x-model="nikInputValue"
                                                   @input.debounce.250ms="onSearchInput('nik', nikInputValue)"
                                                   @focus="onSearchInput('nik', nikInputValue)"
                                                   @keydown="handleKeydown($event, 'nik')"
                                                   placeholder="Ketik NIK karyawan..."
                                                   autocomplete="off"
                                                   class="w-full pl-3.5 pr-9 py-2.5 rounded-xl border border-gray-300 font-mono font-bold text-xs text-gray-800 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/10 bg-white transition-all">
                                            
                                            <div class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center pointer-events-none text-gray-400">
                                                <span x-show="isSearching && searchType === 'nik'" class="material-symbols-rounded text-[18px] text-[#0052cc] animate-spin">progress_activity</span>
                                                <span x-show="!(isSearching && searchType === 'nik')" class="material-symbols-rounded text-[18px]">badge</span>
                                            </div>
                                        </div>

                                        <!-- Autocomplete Recommendation Dropdown for NIK -->
                                        <div x-cloak
                                             x-show="showSuggestions && searchType === 'nik'"
                                             x-transition:enter="transition ease-out duration-100"
                                             x-transition:enter-start="opacity-0 translate-y-1"
                                             x-transition:enter-end="opacity-100 translate-y-0"
                                             x-transition:leave="transition ease-in duration-75"
                                             x-transition:leave-start="opacity-100 translate-y-0"
                                             x-transition:leave-end="opacity-0 translate-y-1"
                                             class="absolute left-0 right-0 top-full mt-1.5 bg-white rounded-2xl border border-gray-200/90 shadow-2xl z-50 overflow-hidden divide-y divide-gray-100">
                                            
                                            <!-- Dropdown Header -->
                                            <div class="px-3.5 py-2 bg-gray-50 flex items-center justify-between text-[11px] text-gray-500 font-semibold">
                                                <div class="flex items-center gap-1.5 text-gray-700">
                                                    <span class="material-symbols-rounded text-[16px] text-[#0052cc]">badge</span>
                                                    <span>Rekomendasi NIK dari Daftar Anggota</span>
                                                </div>
                                                <span x-show="suggestions.length > 0" class="text-[10px] font-mono text-gray-400" x-text="suggestions.length + ' ditemukan'"></span>
                                            </div>

                                            <!-- Suggestion Items List -->
                                            <div class="max-h-60 overflow-y-auto divide-y divide-gray-50">
                                                <template x-for="(sug, idx) in suggestions" :key="sug.id">
                                                    <div @click="selectMemberFromSuggestion(sug)"
                                                         @mouseenter="selectedSuggestionIndex = idx"
                                                         :class="selectedSuggestionIndex === idx ? 'bg-blue-50/90' : 'hover:bg-gray-50'"
                                                         class="px-3.5 py-2.5 flex items-center justify-between gap-3 cursor-pointer transition-colors group">
                                                        <div class="flex items-center gap-2.5 min-w-0">
                                                            <div class="w-8 h-8 rounded-xl bg-blue-50 text-[#0052cc] group-hover:bg-[#0052cc] group-hover:text-white flex items-center justify-center font-mono font-bold text-xs shrink-0 transition-colors">
                                                                <span class="material-symbols-rounded text-[18px]">badge</span>
                                                            </div>
                                                            <div class="min-w-0">
                                                                <div class="font-mono font-bold text-gray-900 text-xs truncate group-hover:text-[#0052cc]">
                                                                    <span class="text-gray-500 font-normal">NIK: </span><span x-text="sug.nik || '-'"></span>
                                                                </div>
                                                                <div class="text-[10.5px] text-gray-600 truncate">
                                                                    <span class="font-bold text-gray-800" x-text="sug.name"></span>
                                                                    <span class="mx-1">•</span>
                                                                    <span>Kode: </span><span class="font-mono" x-text="sug.member_code"></span>
                                                                    <span class="mx-1" x-show="sug.department">•</span>
                                                                    <span x-text="sug.department || ''"></span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="shrink-0 flex items-center gap-2">
                                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
                                                                  :class="sug.status === 'AKTIF' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200'"
                                                                  x-text="sug.status">
                                                            </span>
                                                            <span class="material-symbols-rounded text-[16px] text-gray-300 group-hover:text-[#0052cc]">arrow_forward</span>
                                                        </div>
                                                    </div>
                                                </template>

                                                <!-- No Results State (Cannot be chosen) -->
                                                <template x-if="noResults">
                                                    <div class="p-4 text-center">
                                                        <div class="w-9 h-9 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mx-auto mb-2">
                                                            <span class="material-symbols-rounded text-[20px]">person_off</span>
                                                        </div>
                                                        <h4 class="font-bold text-gray-800 text-xs">NIK Tidak Ditemukan</h4>
                                                        <p class="text-[11px] text-gray-500 mt-0.5 leading-relaxed">
                                                            NIK "<span class="font-mono font-bold text-gray-700" x-text="searchQuery"></span>" tidak ada di database <strong>Daftar Anggota</strong> sehingga <u>tidak dapat dipilih</u>.
                                                        </p>
                                                    </div>
                                                </template>
                                            </div>

                                            <!-- Dropdown Footer -->
                                            <div class="px-3.5 py-1.5 bg-gray-50/80 flex items-center justify-between text-[10px] text-gray-400">
                                                <span>Gunakan panah ↑ ↓ dan Enter untuk memilih</span>
                                                <span class="text-gray-500">Esc untuk tutup</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Cabang Penugasan (Multi-Cabang)</label>
                                        <input type="text" :value="member.branch || '-'" readonly class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50/70 font-semibold text-xs text-gray-700 outline-none cursor-default">
                                    </div>

                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Departemen / Divisi</label>
                                        <input type="text" :value="member.department || '-'" readonly class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50/70 font-semibold text-xs text-gray-700 outline-none cursor-default">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">No. WhatsApp / Telepon</label>
                                        <input type="text" :value="member.phone || '-'" readonly class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50/70 font-medium text-xs text-gray-700 outline-none cursor-default">
                                    </div>

                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Status Keanggotaan Koperasi</label>
                                        <input type="text" :value="member.status === 'AKTIF' ? 'AKTIF (Dapat Kasbon)' : 'DIBLOKIR (Kredit Ditangguhkan)'" readonly class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50/70 font-bold text-xs text-gray-700 outline-none cursor-default">
                                    </div>
                                </div>

                                <div>
                                    <label class="block font-bold text-gray-700 mb-1">Catatan Tambahan</label>
                                    <textarea :value="member.notes || '-'" readonly rows="3" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50/70 text-xs text-gray-700 outline-none resize-none cursor-default"></textarea>
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
                                        <th class="px-5 py-3">PETUGAS</th>
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
                                                <span class="font-mono font-bold text-gray-900 block"
                                                      x-text="mut.invoice_number ? mut.invoice_number : (mut.mutation_type === 'LIMIT_ADJUSTMENT' ? 'PENYESUAIAN PLAFON' : '-')">
                                                </span>
                                                <div class="flex items-center gap-1.5 mt-0.5">
                                                    <span class="text-[10px] px-2 py-0.5 rounded font-bold uppercase"
                                                          :class="mut.mutation_type === 'KASBON_POS' ? 'bg-amber-50 text-amber-700' : (mut.mutation_type === 'LIMIT_ADJUSTMENT' ? 'bg-purple-50 text-purple-700' : 'bg-emerald-50 text-emerald-700')"
                                                          x-text="mut.mutation_type === 'LIMIT_ADJUSTMENT' ? 'UBAH PLAFON' : (mut.mutation_type === 'KASBON_POS' ? 'KASBON POS' : (mut.mutation_type === 'PAYROLL_DEDUCTION' ? 'POTONG GAJI' : (mut.mutation_type === 'SETTLEMENT' ? 'PELUNASAN' : mut.mutation_type)))">
                                                    </span>
                                                    <span x-show="mut.notes" class="text-[10px] text-gray-400 truncate max-w-[200px]" :title="mut.notes" x-text="mut.notes"></span>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3 whitespace-nowrap text-gray-600 font-semibold" x-text="mut.branch"></td>
                                            <td class="px-5 py-3 text-right font-mono font-black whitespace-nowrap"
                                                :class="getMutationNominalClass(mut)"
                                                x-text="formatMutationNominal(mut)">
                                            </td>
                                            <td class="px-5 py-3 text-right font-mono text-gray-500 whitespace-nowrap">
                                                <span x-text="formatRupiah(mut.mutation_type === 'LIMIT_ADJUSTMENT' ? mut.previous_limit : mut.previous_debt)"></span>
                                                <span class="block text-[9px] font-sans"
                                                      :class="mut.mutation_type === 'LIMIT_ADJUSTMENT' ? 'text-purple-600 font-bold' : 'text-gray-400'"
                                                      x-text="mut.mutation_type === 'LIMIT_ADJUSTMENT' ? 'Plafon Lama' : 'Saldo Kasbon'">
                                                </span>
                                            </td>
                                            <td class="px-5 py-3 text-right font-mono font-bold whitespace-nowrap">
                                                <span :class="mut.mutation_type === 'LIMIT_ADJUSTMENT' ? 'text-purple-700' : 'text-gray-800'"
                                                      x-text="formatRupiah(mut.mutation_type === 'LIMIT_ADJUSTMENT' ? mut.new_limit : mut.new_debt)">
                                                </span>
                                                <span class="block text-[9px] font-sans"
                                                      :class="mut.mutation_type === 'LIMIT_ADJUSTMENT' ? 'text-purple-600 font-bold' : 'text-gray-400'"
                                                      x-text="mut.mutation_type === 'LIMIT_ADJUSTMENT' ? 'Plafon Baru' : 'Sisa Kasbon'">
                                                </span>
                                            </td>
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
    <div x-cloak
         x-show="showToast && !!toastMessage"
         class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-2xl shadow-xl border text-xs font-bold"
         :class="toastType === 'error' ? 'bg-red-900 text-white border-red-800' : 'bg-gray-900 text-white border-gray-800'"
         style="display: none;">
        <span class="material-symbols-rounded text-emerald-400" x-show="toastType === 'success'">check_circle</span>
        <span class="material-symbols-rounded text-red-400" x-show="toastType === 'error'">error</span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
