@extends('layouts.owner-admin', ['active' => 'profile'])

@section('title', 'Profil Pemilik & Preferensi Akun - ILP POS Owner Console')

@section('header')
<x-admin.header title="Owner Console" :showSearch="false">
    <x-slot:leftSlot>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ url('/owner') }}" class="text-xs font-black uppercase text-slate-800 tracking-wider hover:text-[#0052cc] transition-colors">Owner Console</a>
            <span class="text-slate-300">&gt;</span>
            <span class="text-xs font-bold text-[#0052cc]">Profil Pemilik &amp; Preferensi</span>
            <span class="ml-2 inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10.5px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200">
                <span class="material-symbols-rounded text-[13px]">shield_person</span>
                <span>Role: Owner / Superadmin</span>
            </span>
        </div>
    </x-slot:leftSlot>

    <div class="flex items-center gap-2">
        <a href="{{ url('/owner') }}" 
           class="px-3.5 py-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-2xs inline-flex items-center gap-1.5 transition-colors">
            <span class="material-symbols-rounded text-[16px]">dashboard</span>
            <span>Kembali ke Dashboard</span>
        </a>
    </div>
</x-admin.header>
@endsection

@section('admin_content')
<div x-data="ownerProfileApp()" 
     x-init="init()"
     class="max-w-5xl mx-auto space-y-6">

    <!-- Page Title -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl md:text-[28px] font-black text-slate-900 tracking-tight">Profil &amp; Preferensi</h1>
        </div>
    </div>

    <form @submit.prevent="saveProfile()" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- Left Column: Identity & Avatar Card (lg:col-span-4) -->
            <div class="lg:col-span-4 space-y-6">
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-6 text-center space-y-4">
                    <div class="relative w-28 h-28 mx-auto">
                        <img :src="profile.avatar" 
                             :alt="profile.name" 
                             class="w-28 h-28 rounded-full object-cover border-4 border-blue-50 shadow-md mx-auto"
                             onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=Dewi+Sartika&background=0052cc&color=fff&bold=true'">
                        <span class="absolute bottom-1 right-1 w-4 h-4 bg-emerald-500 rounded-full ring-2 ring-white" title="Status Online"></span>
                    </div>

                    <div>
                        <h3 class="text-lg font-black text-slate-900" x-text="profile.name">Dewi Sartika</h3>
                        <p class="text-xs font-bold text-[#0052cc] mt-0.5" x-text="profile.title || 'Pemilik Usaha & Direktur Koperasi'"></p>
                        <p class="text-[11px] text-slate-400 mt-1 font-mono" x-text="profile.email"></p>
                    </div>

                    <div class="pt-3 border-t border-slate-100 space-y-2 text-left text-xs">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Ganti URL Foto Avatar</label>
                            <input type="url" 
                                   x-model="profile.avatar" 
                                   placeholder="https://..." 
                                   class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none font-mono">
                        </div>
                        <div class="flex items-center gap-1.5 pt-1">
                            <button type="button" 
                                    @click="profile.avatar = 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150&auto=format&fit=crop&q=80'"
                                    class="flex-1 py-1 px-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-[11px] font-bold text-slate-600 transition-colors">
                                Foto 1
                            </button>
                            <button type="button" 
                                    @click="profile.avatar = 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=150&auto=format&fit=crop&q=80'"
                                    class="flex-1 py-1 px-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-[11px] font-bold text-slate-600 transition-colors">
                                Foto 2
                            </button>
                            <button type="button" 
                                    @click="profile.avatar = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(profile.name) + '&background=0052cc&color=fff&bold=true'"
                                    class="flex-1 py-1 px-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-[11px] font-bold text-slate-600 transition-colors">
                                Inisial
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Role & Authority Card (Read-only) -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-5 space-y-3">
                    <div class="flex items-center gap-2 text-xs font-black uppercase text-slate-700 tracking-wider">
                        <span class="material-symbols-rounded text-[#0052cc] text-[18px]">verified_user</span>
                        <span>Otoritas Role (Tetap)</span>
                    </div>

                    <div class="p-3 rounded-xl bg-blue-50/60 border border-blue-200 text-xs space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-600 font-medium">Tingkat Hak Akses</span>
                            <span class="px-2 py-0.5 rounded-full bg-[#0052cc] text-white font-extrabold text-[10px]">OWNER / ROOT</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-600 font-medium">Cakupan Cabang</span>
                            <span class="font-bold text-slate-800">Seluruh Lokasi (3 Cabang + DC)</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-600 font-medium">Akses Finansial</span>
                            <span class="font-bold text-emerald-600">Penuh (Laba Rugi &amp; Plafon)</span>
                        </div>
                    </div>

                    <p class="text-[11px] text-slate-400 italic">
                        * Hak akses Owner bersifat permanen dan tidak dapat diubah oleh peran Manager atau Kasir.
                    </p>
                </div>
            </div>

            <!-- Right Column: Details & Preferences Form (lg:col-span-8) -->
            <div class="lg:col-span-8 space-y-6">
                <!-- Card 1: Data Identitas Diri -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-6 space-y-4">
                    <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                        <span class="material-symbols-rounded text-[#0052cc] text-[20px]">person</span>
                        <h3 class="text-base font-bold text-slate-900 tracking-tight">Data Informasi Pribadi</h3>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nama Lengkap *</label>
                            <input type="text" 
                                   x-model="profile.name" 
                                   required 
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 font-bold focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Jabatan / Gelar Koperasi</label>
                            <input type="text" 
                                   x-model="profile.title" 
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Alamat Email Operasional *</label>
                            <input type="email" 
                                   x-model="profile.email" 
                                   required 
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 font-mono focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nomor WhatsApp / Telepon</label>
                            <input type="text" 
                                   x-model="profile.phone" 
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 font-mono focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block font-bold text-slate-700 mb-1">Nomor Induk Kependudukan (NIK) Direksi</label>
                            <input type="text" 
                                   x-model="profile.nik" 
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 font-mono focus:bg-white focus:border-[#0052cc] focus:outline-none">
                        </div>
                    </div>
                </div>

                <!-- Card 2: Preferensi Tampilan Antarmuka -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-6 space-y-4">
                    <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                        <span class="material-symbols-rounded text-[#0052cc] text-[20px]">tune</span>
                        <h3 class="text-base font-bold text-slate-900 tracking-tight">Preferensi Dashboard &amp; Notifikasi</h3>
                    </div>

                    <div class="space-y-3.5 text-xs">
                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                            <div>
                                <span class="font-bold text-slate-800 block">Fokus Cabang Default</span>
                                <span class="text-slate-500 text-[11px]">Tentukan cabang awal yang dimuat pada grafik dan laporan</span>
                            </div>
                            <select x-model="profile.defaultBranch" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white font-bold text-slate-700 outline-none text-xs">
                                <option value="all">Semua Cabang (Konsolidasi)</option>
                                <option value="Pusat">Pusat</option>
                                <option value="Cabang A">Cabang A</option>
                                <option value="Cabang B">Cabang B</option>
                            </select>
                        </div>

                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                            <div>
                                <span class="font-bold text-slate-800 block">Notifikasi Stok Rendah Pop-up</span>
                                <span class="text-slate-500 text-[11px]">Tampilkan banner peringatan saat ada SKU yang habis / menipis</span>
                            </div>
                            <input type="checkbox" x-model="profile.alertLowStock" class="w-4 h-4 rounded text-[#0052cc] cursor-pointer">
                        </div>

                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                            <div>
                                <span class="font-bold text-slate-800 block">Mode Tampilan Tabel Kompak</span>
                                <span class="text-slate-500 text-[11px]">Gunakan jarak baris lebih rapat pada tabel audit</span>
                            </div>
                            <input type="checkbox" x-model="profile.compactTable" class="w-4 h-4 rounded text-[#0052cc] cursor-pointer">
                        </div>
                    </div>
                </div>

                <!-- Submit Button Bar -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" 
                            @click="resetToDefault()"
                            class="px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold text-xs transition-colors cursor-pointer">
                        Reset Default
                    </button>
                    <button type="submit" 
                            class="px-6 py-2.5 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs shadow-sm shadow-blue-600/20 transition-all cursor-pointer inline-flex items-center gap-2">
                        <span class="material-symbols-rounded text-[18px]">save</span>
                        <span>Simpan Perubahan Profil</span>
                    </button>
                </div>
            </div>

        </div>
    </form>

</div>

<script>
function ownerProfileApp() {
    return {
        profile: {
            name: 'Dewi Sartika',
            title: 'Pemilik Usaha & Direktur Koperasi',
            email: 'owner@coop.id',
            phone: '0812-9841-2201',
            nik: '327501550230056',
            avatar: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150&auto=format&fit=crop&q=80',
            defaultBranch: 'all',
            alertLowStock: true,
            compactTable: false
        },

        init() {
            try {
                const raw = localStorage.getItem('pos_ilp_owner_profile_v1');
                if (raw) {
                    const parsed = JSON.parse(raw);
                    if (parsed && typeof parsed === 'object') {
                        this.profile = { ...this.profile, ...parsed };
                    }
                }
            } catch (e) {
                console.error('Failed to load owner profile:', e);
            }
        },

        saveProfile() {
            try {
                localStorage.setItem('pos_ilp_owner_profile_v1', JSON.stringify(this.profile));
                if (typeof triggerToast === 'function') {
                    triggerToast('Profil dan preferensi Owner berhasil diperbarui!', 'success');
                }
            } catch (e) {
                console.error('Failed to save owner profile:', e);
                if (typeof triggerToast === 'function') {
                    triggerToast('Gagal menyimpan profil: ' + e.message, 'error');
                }
            }
        },

        resetToDefault() {
            this.profile = {
                name: 'Dewi Sartika',
                title: 'Pemilik Usaha & Direktur Koperasi',
                email: 'owner@coop.id',
                phone: '0812-9841-2201',
                nik: '327501550230056',
                avatar: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150&auto=format&fit=crop&q=80',
                defaultBranch: 'all',
                alertLowStock: true,
                compactTable: false
            };
            this.saveProfile();
        }
    };
}
</script>
@endsection
