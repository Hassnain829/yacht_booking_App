@extends('layouts.app', [
    'title' => 'Confirming your payment',
    'page' => 'checkout-complete',
    'robots' => 'noindex',
    'headerSolid' => true,
    'mainClass' => 'pb-24 pt-28',
])

{{--
    Back from the card step (PLAN 4.5). The server asked the provider already; while the bank is still working, this
    page asks again (resources/js/pages/checkout-complete.js → GET status) and moves on to the confirmation. A failed
    card goes back to checkout; a hold that ended explains it. Never confirmed from the redirect itself.
--}}
@section('content')
    <section class="pt-10">
        <div class="container max-w-2xl">
            <div class="rounded-[2rem] bg-white p-8 text-center shadow-card ring-1 ring-navy-100 md:p-12" data-checkout-complete data-status-url="{{ $statusUrl }}" data-state="{{ $state }}">
                @if ($state === 'processing')
                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-brass-100 text-brass-700"><span class="spinner"></span></span>
                    <h1 class="mt-5 font-display text-3xl text-navy-950 md:text-4xl">Confirming your payment…</h1>
                    <p class="mt-3 text-navy-500">This usually takes a few seconds. Please keep this page open — you'll see your confirmation here.</p>
                    <p data-complete-slow hidden class="mt-4 text-sm text-navy-500">Still working on it. If it takes more than a minute, check your email or <a href="{{ route('contact') }}" class="font-bold text-navy-950 underline">contact us</a> with reference <strong>{{ $booking->reference }}</strong>.</p>
                @elseif ($state === 'failed')
                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-red-50 text-red-600"><i data-lucide="circle-alert" class="h-7 w-7"></i></span>
                    <h1 class="mt-5 font-display text-3xl text-navy-950 md:text-4xl">The payment didn't go through</h1>
                    <p class="mt-3 text-navy-500">{{ $failure }} Nothing was charged. Your time is still held — try again or use another card.</p>
                    <x-button :href="route('checkout.show', $booking)" icon="arrow-right" class="mt-8">Back to checkout</x-button>
                @else
                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-navy-50 text-navy-600"><i data-lucide="timer-off" class="h-7 w-7"></i></span>
                    <h1 class="mt-5 font-display text-3xl text-navy-950 md:text-4xl">This booking is closed</h1>
                    <p class="mt-3 text-navy-500">The hold on {{ $booking->boat->title }} has ended. If a payment went through, we'll refund it — or call us with reference <strong>{{ $booking->reference }}</strong>.</p>
                    <x-button :href="route('yachts.show', $booking->boat->slug).'#book'" icon="arrow-right" class="mt-8">Back to {{ $booking->boat->title }}</x-button>
                @endif
            </div>
        </div>
    </section>
@endsection
