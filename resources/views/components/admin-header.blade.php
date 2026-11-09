@props([
    'title' => 'Admin Console',
    'searchPlaceholder' => 'Cari sesuatu...',
    'showSearch' => true,
    'searchModel' => 'searchQuery'
])

<x-admin.header :title="$title" :searchPlaceholder="$searchPlaceholder" :showSearch="$showSearch" :searchModel="$searchModel">
    @isset($leftSlot)
        <x-slot:leftSlot>{{ $leftSlot }}</x-slot:leftSlot>
    @endisset
    @isset($searchSlot)
        <x-slot:searchSlot>{{ $searchSlot }}</x-slot:searchSlot>
    @endisset
    {{ $slot ?? '' }}
</x-admin.header>
