@extends('layouts.app', [
    'title' => 'My Boats',
    'page' => 'owner-boats',
    'headerSolid' => true,
    'robots' => 'noindex',
    'mainClass' => 'min-h-screen pb-24 pt-28',
])

@section('content')
    <x-owner.shell active="boats" heading="My boats">
        <x-slot:actions>
            <a href="{{ route('list-your-boat', ['new' => 1]) }}" class="inline-flex items-center justify-center gap-2 rounded-full border-2 border-navy-950 px-6 py-3 text-sm font-bold uppercase tracking-wider text-navy-950 transition hover:bg-navy-950 hover:text-white"><i data-lucide="plus" class="h-4 w-4"></i>List a new boat</a>
        </x-slot:actions>

        {{-- Drafts started on this device (Phase 1 demo, localStorage) are added at the top by pages/owner-boats.js. --}}
        <div id="boat-list" class="mt-10 space-y-5" data-boats-url="{{ route('list-your-boat') }}">
            @forelse ($boats as $boat)
                @include('owner.partials.boat-card', ['boat' => $boat])
            @empty
                <div class="rounded-[2rem] border-2 border-dashed border-navy-200 bg-white px-6 py-16 text-center" data-empty>
                    <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-navy-50 text-navy-400"><i data-lucide="sailboat" class="h-8 w-8"></i></span>
                    <h2 class="mt-5 font-display text-3xl text-navy-950">You haven't listed a boat yet</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm text-navy-500">List your boat in about 15 minutes and start earning from Chicago charters — with vetted captains and secure payouts.</p>
                    <x-button :href="route('list-your-boat', ['new' => 1])" size="xl" icon="arrow-right" class="mt-8">List your boat</x-button>
                </div>
            @endforelse
            @if ($boats->isNotEmpty())
                <p class="pt-2 text-center text-xs text-navy-400" data-boat-count="{{ $boats->count() }}">{{ $boats->count() }} {{ Str::plural('boat', $boats->count()) }}</p>
            @endif
        </div>

        <template id="local-boat-template">
            @include('owner.partials.boat-card', ['boat' => new Illuminate\Support\Fluent([
                'ulid' => 'LOCAL', 'status' => 'draft', 'status_label' => App\Support\Demo\DemoOwner::BOAT_STATUSES['draft']['label'],
                'status_class' => App\Support\Demo\DemoOwner::BOAT_STATUSES['draft']['class'], 'cover' => null, 'photos_count' => 0,
                'updated_at' => now(), 'display_title' => '', 'meta' => '', 'next_up' => '', 'booking_enabled' => false,
            ])])
        </template>
    </x-owner.shell>
@endsection
