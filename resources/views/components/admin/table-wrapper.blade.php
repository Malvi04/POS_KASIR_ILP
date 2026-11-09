@props([
    'title' => '',
    'subtitle' => '',
    'minWidth' => '720px'
])

<div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden flex flex-col">
    @if($title || isset($headerActions))
        <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            @if($title)
                <div>
                    <h3 class="text-[15px] font-bold text-slate-900 tracking-tight">{{ $title }}</h3>
                    @if($subtitle)
                        <p class="text-[12px] text-slate-500 mt-0.5">{{ $subtitle }}</p>
                    @endif
                </div>
            @endif

            @isset($headerActions)
                <div class="flex items-center gap-2 flex-wrap">
                    {{ $headerActions }}
                </div>
            @endisset
        </div>
    @endif

    <!-- Table Container with Horizontal Scroll & Min-Width Protection -->
    <div class="w-full overflow-x-auto">
        <table class="w-full text-left border-collapse" style="min-width: {{ $minWidth }};">
            {{ $slot }}
        </table>
    </div>

    @isset($footer)
        <div class="px-6 py-3.5 border-t border-slate-100 bg-slate-50/50">
            {{ $footer }}
        </div>
    @endisset
</div>
