@extends('layouts.admin', ['title' => 'Listing reviews', 'active' => 'listings'])

@section('content')
    <x-admin.page-header title="Listing reviews" description="Owner boats waiting for approval. Check details, photos, insurance and pricing, then approve, ask for changes or reject." />

    <nav class="mt-6 inline-flex gap-1 rounded-full bg-white p-1 text-xs font-bold shadow-xs ring-1 ring-navy-100" aria-label="Listing status">
        @foreach (['pending' => 'Waiting for review', 'draft' => 'Drafts', 'approved' => 'Approved'] as $key => $label)
            <a href="{{ route('admin.listings.index', $key === 'pending' ? [] : ['status' => $key]) }}" @class(['rounded-full px-4 py-2', 'bg-navy-950 text-white' => $status === $key, 'text-navy-500 hover:text-navy-950' => $status !== $key]) @if ($status === $key) aria-current="page" @endif>{{ $label }} <span class="opacity-60">{{ $counts[$key] ?? 0 }}</span></a>
        @endforeach
    </nav>

    <div class="mt-6 space-y-4">
        @forelse ($listings as $listing)
            <article class="flex flex-col gap-5 rounded-2xl bg-white p-4 shadow-xs ring-1 ring-navy-100 sm:flex-row sm:items-center sm:p-5">
                <div class="aspect-[16/10] w-full shrink-0 overflow-hidden rounded-xl bg-navy-50 sm:aspect-auto sm:h-24 sm:w-36">
                    @if ($listing->cover)
                        <img src="{{ asset($listing->cover) }}" alt="" loading="lazy" class="h-full w-full object-cover">
                    @else
                        <div class="grid h-full place-items-center text-navy-300"><i data-lucide="image-off" class="h-6 w-6"></i></div>
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-bold text-navy-950">{{ $listing->display_title }}</p>
                    <p class="mt-0.5 truncate text-sm text-navy-500">{{ $listing->meta ?: 'Details not added yet' }}</p>
                    <p class="mt-1 text-xs text-navy-400">{{ $listing->owner }} · updated {{ $listing->updated_at->format('M j') }} · {{ $listing->photos_count }} photos · {{ $listing->insurance_type === 'commercial' ? 'Commercial insurance' : 'Recreational insurance' }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <x-admin.pill :tone="match ($listing->status) { 'pending' => 'warning', 'approved' => 'success', default => 'muted' }">{{ $listing->status_label }}</x-admin.pill>
                    <x-button :href="route('admin.listings.show', $listing->ulid)" :variant="$listing->status === 'pending' ? 'dark' : 'outline'" size="sm">{{ $listing->status === 'pending' ? 'Review' : 'Open' }}</x-button>
                </div>
            </article>
        @empty
            <p class="rounded-2xl bg-white p-10 text-center text-sm text-navy-500 ring-1 ring-navy-100">Nothing here right now.</p>
        @endforelse
    </div>
@endsection
