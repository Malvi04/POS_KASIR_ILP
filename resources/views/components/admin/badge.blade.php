@props([
    'type' => 'success', // 'success', 'warning', 'danger', 'info', 'neutral', 'purple'
    'size' => 'md',      // 'sm', 'md'
    'dot' => false
])

@php
    $styles = [
        'success' => 'bg-emerald-50 text-emerald-700 border-emerald-200/80',
        'warning' => 'bg-amber-50 text-amber-700 border-amber-200/80',
        'danger' => 'bg-rose-50 text-rose-700 border-rose-200/80',
        'info' => 'bg-blue-50 text-[#0052cc] border-blue-200/80',
        'purple' => 'bg-purple-50 text-purple-700 border-purple-200/80',
        'neutral' => 'bg-slate-100 text-slate-700 border-slate-200/80',
    ];

    $dotColors = [
        'success' => 'bg-emerald-500',
        'warning' => 'bg-amber-500',
        'danger' => 'bg-rose-500',
        'info' => 'bg-[#0052cc]',
        'purple' => 'bg-purple-500',
        'neutral' => 'bg-slate-400',
    ];

    $sizeClasses = [
        'sm' => 'px-2 py-0.5 text-[10.5px]',
        'md' => 'px-2.5 py-1 text-[11.5px]'
    ];
@endphp

<span class="inline-flex items-center gap-1.5 rounded-full font-bold uppercase tracking-wider border {{ $styles[$type] ?? $styles['neutral'] }} {{ $sizeClasses[$size] ?? $sizeClasses['md'] }}">
    @if($dot)
        <span class="w-1.5 h-1.5 rounded-full {{ $dotColors[$type] ?? $dotColors['neutral'] }}"></span>
    @endif
    {{ $slot }}
</span>
