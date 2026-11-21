{{-- Admin page title row. <x-admin.page-header title="Bookings" description="…" :back="['All bookings', $url]"><x-slot:actions>…</x-slot:actions></x-admin.page-header> --}}
@props(['title', 'description' => null, 'back' => null])

<div {{ $attributes->class('flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back[1] }}" class="mb-3 inline-flex items-center gap-1.5 text-sm font-bold text-navy-500 hover:text-navy-950"><i data-lucide="arrow-left" class="h-4 w-4"></i>{{ $back[0] }}</a>
        @endif
        <h1 class="font-display text-3xl text-navy-950 md:text-4xl">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1.5 max-w-2xl text-sm text-navy-500">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
