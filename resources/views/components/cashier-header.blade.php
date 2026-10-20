<header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
    <div class="flex items-center gap-4">
        <h2 class="text-lg font-bold text-gray-800">{{ $title }}</h2>
        {{ $slot ?? '' }}
    </div>
    <div class="flex items-center gap-5">
        <button class="text-gray-400 hover:text-gray-600 transition-colors relative">
            <span class="material-symbols-rounded text-[22px]">notifications</span>
            <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full border border-white"></span>
        </button>
        <button class="text-gray-400 hover:text-gray-600 transition-colors">
            <span class="material-symbols-rounded text-[22px]">sync</span>
        </button>
        <div class="h-6 w-px bg-gray-200 mx-1"></div>
        <div class="flex items-center gap-2 text-green-600 bg-green-50 px-3 py-1.5 rounded-full border border-green-100">
            <span class="material-symbols-rounded text-[18px]">wifi</span>
            <span class="text-[11px] font-bold tracking-wide">Terhubung</span>
        </div>
    </div>
</header>
