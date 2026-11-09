@props([
    'total' => '12',
    'shown' => '5',
    'currentPage' => '1',
    'totalPages' => '3'
])

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-[12.5px] text-slate-500">
    <div>
        {{ $slot }}
    </div>

    <div class="flex items-center gap-1 self-end sm:self-auto">
        <button type="button" 
                class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-medium transition-colors"
                @click="if (typeof prevPage === 'function') prevPage();">
            <span class="material-symbols-rounded text-[16px] align-middle">chevron_left</span>
            <span class="hidden sm:inline">Sebelumnya</span>
        </button>

        <div class="flex items-center gap-1">
            <template x-for="p in [1, 2, 3]" :key="p">
                <button type="button"
                        @click="if (typeof setPage === 'function') setPage(p); else currentPage = p;"
                        :class="(typeof currentPage !== 'undefined' && currentPage === p) || p === 1 ? 'bg-[#0052cc] text-white font-bold border-[#0052cc]' : 'bg-white text-slate-700 hover:bg-slate-100 border-slate-200'"
                        class="w-7 h-7 rounded-lg border text-xs flex items-center justify-center transition-colors font-medium"
                        x-text="p">
                </button>
            </template>
        </div>

        <button type="button" 
                class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-medium transition-colors"
                @click="if (typeof nextPage === 'function') nextPage();">
            <span class="hidden sm:inline">Selanjutnya</span>
            <span class="material-symbols-rounded text-[16px] align-middle">chevron_right</span>
        </button>
    </div>
</div>
