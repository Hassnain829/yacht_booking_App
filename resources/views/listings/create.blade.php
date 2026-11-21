{{--
    List Your Boat wizard (mockup list-your-boat.html). Rendered by resources/js/pages/list-boat.js.
    Phase 3: auth required; each step PATCHes the draft (ListingController), photos go through MediaService.
--}}
@extends('layouts.focus', [
    'title' => 'List Your Boat',
    'description' => 'List your boat or yacht on Chicago Yacht Booking and earn from charters with licensed captains, secure payments and full control of your calendar.',
    'page' => 'list-boat',
    'robots' => 'noindex',
    'mainClass' => 'pb-20 pt-16',
])

@section('header')
    <header class="fixed inset-x-0 top-0 z-50 bg-navy-950 text-white shadow-lg shadow-navy-950/20">
        <div class="flex h-16 items-center justify-between gap-4 px-4 md:px-8">
            <x-logo size="sm" />
            <div class="flex items-center gap-4 text-sm md:gap-6">
                <p class="hidden items-center gap-2 text-white/70 md:flex" title="Rough estimate based on similar boats in Chicago">
                    Estimated monthly earnings: <strong id="earnings" class="text-brass-300">—</strong>
                </p>
                <a href="{{ route('contact') }}" target="_blank" rel="noopener" class="hidden font-semibold text-white/80 hover:text-white sm:inline">Support</a>
                <button id="save-exit" type="button" class="rounded-full border border-white/20 px-4 py-2 text-sm font-bold transition hover:bg-white hover:text-navy-950">Save and exit</button>
            </div>
        </div>
    </header>
@endsection

@section('content')
    <div id="wizard" data-listing="{{ json_encode($wizard) }}">
        <noscript>
            <div class="container max-w-2xl py-20 text-center">
                <h1 class="font-display text-3xl text-navy-950">Please turn on JavaScript</h1>
                <p class="mt-3 text-navy-600">The listing wizard needs JavaScript. You can also <a href="{{ route('contact') }}" class="font-bold text-brass-600 underline">contact our fleet team</a> and we'll list your boat with you.</p>
            </div>
        </noscript>
    </div>
@endsection

@push('modals')
    <div id="wizard-bar" class="fixed inset-x-0 bottom-0 z-40 border-t border-navy-100 bg-white/95 backdrop-blur"></div>
    <input type="file" id="photo-input" accept="image/jpeg,image/png,image/gif,image/webp" multiple class="hidden" aria-hidden="true" tabindex="-1">
@endpush
