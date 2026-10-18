@extends('layouts.app')

@section('title', 'Detail Profil Anggota - ILP POS')

@section('content')
<div class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">
    
    <!-- Sidebar -->
    <x-manager-sidebar active="members" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        
        <!-- Top Header -->
        <header class="h-[72px] bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <h1 class="text-[18px] font-black text-gray-900 tracking-tight">Management Anggota</h1>

            <div class="flex items-center gap-6 text-gray-500">
                <button class="hover:text-gray-900 transition-colors relative">
                    <span class="material-symbols-rounded text-[24px]">notifications</span>
                </button>
                <button class="hover:text-gray-900 transition-colors">
                    <span class="material-symbols-rounded text-[24px]">autorenew</span>
                </button>
                <div class="h-6 w-px bg-gray-300"></div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-rounded text-green-500 text-[20px]">wifi</span>
                    <span class="text-[14px] font-semibold text-gray-600">Terhubung</span>
                </div>
            </div>
        </header>

        <!-- Subheader -->
        <div class="px-8 py-5 flex items-center justify-between shrink-0">
            <h2 class="text-[16px] font-bold text-gray-700">Detail Profil Anggota</h2>
            <div class="bg-[#eef2ff] text-[#0052cc] px-4 py-1.5 rounded-full text-[13px] font-bold">ID: AG-2024-0812</div>
        </div>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto px-8 pb-[100px]">
            <div class="flex gap-6 items-start">
                
                <!-- Left: Informasi Personal -->
                <div class="flex-1 bg-white rounded-xl border border-gray-200 shadow-sm p-8">
                    <div class="flex items-center gap-3 mb-8">
                        <div class="w-10 h-10 rounded-lg bg-[#eef2ff] text-[#0052cc] flex items-center justify-center">
                            <span class="material-symbols-rounded text-[22px]">person</span>
                        </div>
                        <h3 class="text-[15px] font-bold text-gray-800">Informasi Personal</h3>
                    </div>

                    <div class="flex flex-col gap-6">
                        <!-- Nama Lengkap -->
                        <div class="flex flex-col gap-2">
                            <label class="text-[13px] font-medium text-gray-600">Nama Lengkap</label>
                            <input type="text" value="Bambang Pamungkas" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] text-[14px] text-gray-800 bg-white">
                        </div>

                        <!-- NIK and Departemen -->
                        <div class="flex gap-4">
                            <div class="flex-1 flex flex-col gap-2">
                                <label class="text-[13px] font-medium text-gray-600">NIK / ID Karyawan</label>
                                <input type="text" value="1274092100340001" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] text-[14px] text-gray-800 bg-white font-mono">
                            </div>
                            <div class="flex-1 flex flex-col gap-2">
                                <label class="text-[13px] font-medium text-gray-600">Departemen</label>
                                <div class="relative">
                                    <select class="appearance-none w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 pr-10 text-[14px] text-gray-800 focus:outline-none focus:border-[#0052cc]">
                                        <option>Produksi Utama</option>
                                        <option>Logistik</option>
                                    </select>
                                    <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                                </div>
                            </div>
                        </div>

                        <!-- Nomor Telepon -->
                        <div class="flex flex-col gap-2">
                            <label class="text-[13px] font-medium text-gray-600">Nomor Telepon</label>
                            <div class="flex">
                                <span class="inline-flex items-center px-4 py-2.5 rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-[14px]">+62</span>
                                <input type="text" value="81234567890" class="flex-1 px-4 py-2.5 rounded-r-lg border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] text-[14px] text-gray-800 bg-white font-mono z-10 outline-none">
                            </div>
                        </div>

                        <!-- Alamat Korespondensi -->
                        <div class="flex flex-col gap-2">
                            <label class="text-[13px] font-medium text-gray-600">Alamat Korespondensi (Opsional)</label>
                            <textarea rows="4" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] text-[14px] text-gray-800 bg-white resize-none outline-none">Jl. Melati No. 45, Kompleks Industri Sentosa, Jawa Barat</textarea>
                        </div>
                    </div>
                </div>

                <!-- Right: Credit Cards -->
                <div class="w-[420px] flex flex-col gap-6 shrink-0">
                    
                    <!-- Top Blue Card -->
                    <div class="rounded-2xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">
                        <!-- Blue Section -->
                        <div class="bg-[#0052cc] p-6 text-white flex flex-col gap-5">
                            <div class="flex justify-between items-start">
                                <div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center backdrop-blur-sm">
                                    <span class="material-symbols-rounded text-white text-[22px]">account_balance_wallet</span>
                                </div>
                                <span class="text-[10px] font-bold tracking-widest uppercase opacity-90 mt-1">CREDIT STATUS: ACTIVE</span>
                            </div>
                            
                            <div class="mt-2">
                                <p class="text-[13px] text-blue-100 mb-1">Piutang Saat Ini</p>
                                <h3 class="text-3xl font-mono font-bold tracking-tight">Rp 1.250.000</h3>
                            </div>

                            <div class="w-full h-px bg-white/20 my-1"></div>

                            <div class="flex gap-12">
                                <div>
                                    <p class="text-[11px] text-blue-200 mb-1">Plafon Bulanan</p>
                                    <span class="font-mono font-bold text-[14px]">Rp 5.000.000</span>
                                </div>
                                <div>
                                    <p class="text-[11px] text-blue-200 mb-1">Sisa Plafon</p>
                                    <span class="font-mono font-bold text-[14px]">Rp 3.750.000</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- White Section -->
                        <div class="bg-white p-6 flex flex-col gap-4">
                            <div class="flex justify-between items-center mb-1">
                                <h4 class="text-[14px] font-bold text-gray-800">Input Tagihan Saat Ini</h4>
                                <div class="flex items-center gap-1 text-[#d97706]">
                                    <span class="material-symbols-rounded text-[14px]">error</span>
                                    <span class="text-[10px] font-bold">Manual Adjustment</span>
                                </div>
                            </div>
                            
                            <div class="flex items-center border border-gray-300 rounded-lg px-4 py-2.5 bg-white">
                                <span class="text-[14px] font-medium text-gray-500 mr-2">Rp</span>
                                <input type="text" value="0" class="w-full focus:outline-none font-mono text-[14px] text-gray-800">
                            </div>
                            
                            <p class="text-[11px] text-gray-500 italic leading-snug">Gunakan untuk penyesuaian piutang manual atau migrasi data anggota dari sistem lama.</p>
                            
                            <h4 class="text-[13px] font-bold text-gray-700 mt-2">Jenis Penyesuaian</h4>
                            <div class="flex gap-3">
                                <button class="flex-1 flex items-center justify-center gap-1.5 border-[1.5px] border-[#0052cc] bg-[#eef2ff] text-[#0052cc] rounded-lg py-2 font-bold text-[13px] transition-colors">
                                    <span class="material-symbols-rounded text-[18px]">add_circle</span>
                                    Tambah Piutang
                                </button>
                                <button class="flex-1 flex items-center justify-center gap-1.5 border-[1.5px] border-gray-200 bg-white text-gray-500 rounded-lg py-2 font-bold text-[13px] transition-colors hover:bg-gray-50">
                                    <span class="material-symbols-rounded text-[18px]">remove_circle_outline</span>
                                    Kurangi Piutang
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Card (Ringkasan Terakhir) -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 flex flex-col">
                        <div class="flex justify-between items-center mb-6">
                            <h4 class="text-[14px] font-bold text-gray-800">Ringkasan Terakhir</h4>
                            <a href="#" class="text-[11px] font-bold text-[#0052cc] hover:underline">Lihat Semua</a>
                        </div>
                        
                        <div class="flex flex-col gap-5">
                            <!-- Item 1 -->
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-full bg-green-50 text-green-600 flex items-center justify-center shrink-0">
                                        <span class="material-symbols-rounded text-[18px]">shopping_bag</span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-[13px] font-bold text-gray-800">Belanja Toko #8821</span>
                                        <span class="text-[11px] text-gray-500 mt-0.5">12 Aug 2024 • 14:20</span>
                                    </div>
                                </div>
                                <span class="font-mono font-bold text-red-600 text-[13px] mt-1 shrink-0">- Rp 45.000</span>
                            </div>
                            <!-- Item 2 -->
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-full bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                                        <span class="material-symbols-rounded text-[18px]">payments</span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-[13px] font-bold text-gray-800">Pembayaran Payroll</span>
                                        <span class="text-[11px] text-gray-500 mt-0.5">01 Aug 2024 • 09:00</span>
                                    </div>
                                </div>
                                <span class="font-mono font-bold text-green-600 text-[13px] mt-1 shrink-0">+ Rp 1.500.000</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        
        <!-- Bottom Action Bar -->
        <div class="absolute bottom-0 left-0 right-0 h-[80px] bg-white border-t border-gray-200 flex items-center justify-between px-8 z-10 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
            <div class="flex items-center gap-2 text-[#d97706]">
                <span class="material-symbols-rounded text-[20px]">error</span>
                <span class="text-[13px] text-gray-600">Pastikan data NIK dan Nama sesuai dengan identitas resmi.</span>
            </div>
            <div class="flex gap-4">
                <button class="px-6 py-2.5 text-gray-600 font-bold text-[14px] hover:bg-gray-50 rounded-lg transition-colors">Batal</button>
                <button class="px-6 py-2.5 bg-[#0052cc] text-white font-bold text-[14px] rounded-lg shadow-sm hover:bg-[#0043a8] transition-colors">Simpan Perubahan</button>
            </div>
        </div>
    </main>

</div>
@endsection
