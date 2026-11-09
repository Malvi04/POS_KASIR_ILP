@props([
    'title' => '',
    'value' => '',
    'subtitle' => '',
    'change' => '',
    'changeType' => 'up', // 'up', 'down', 'neutral'
    'icon' => '',
    'color' => 'blue' // 'blue', 'green', 'amber', 'rose', 'slate'
])

@php
    $textColors = [
        'blue' => 'text-[#0052cc]',
        'green' => 'text-emerald-600',
        'amber' => 'text-amber-600',
        'rose' => 'text-rose-600',
        'slate' => 'text-slate-800'
    ];
    $iconBg = [
        'blue' => 'bg-blue-50 text-[#0052cc]',
        'green' => 'bg-emerald-50 text-emerald-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'rose' => 'bg-rose-50 text-rose-600',
        'slate' => 'bg-slate-100 text-slate-700'
    ];
@endphp

<div class="relative bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs overflow-hidden flex flex-col justify-between transition-all hover:shadow-xs hover:border-slate-300">
    <!-- Header: Title & Optional Icon -->
    <div class="flex items-start justify-between gap-2">
        <span class="text-[11.5px] font-bold uppercase tracking-wider text-slate-500">{{ $title }}</span>
        @if($icon)
            <div class="w-9 h-9 rounded-xl flex items-center justify-center {{ $iconBg[$color] ?? 'bg-blue-50 text-[#0052cc]' }} shrink-0">
                <span class="material-symbols-rounded text-[20px]">{{ $icon }}</span>
            </div>
        @endif
    </div>

    <!-- Value & Details -->
    <div class="mt-3">
        <div class="text-2xl md:text-[28px] font-extrabold tracking-tight {{ $textColors[$color] ?? 'text-slate-900' }} leading-none">
            {{ $value }}
        </div>
        
        @if($change || $subtitle)
            <div class="mt-2.5 flex items-center gap-2 text-[12px] flex-wrap">
                @if($change)
                    <span class="inline-flex items-center gap-0.5 font-bold {{ $changeType === 'up' ? 'text-emerald-600' : ($changeType === 'down' ? 'text-rose-600' : 'text-slate-500') }}">
                        @if($changeType === 'up')
                            <span class="material-symbols-rounded text-[14px]">trending_up</span>
                        @elseif($changeType === 'down')
                            <span class="material-symbols-rounded text-[14px]">trending_down</span>
                        @endif
                        {{ $change }}
                    </span>
                @endif
                @if($subtitle)
                    <span class="text-slate-500 font-medium">{{ $subtitle }}</span>
                @endif
            </div>
        @endif

        {{ $slot ?? '' }}
    </div>
</div>
