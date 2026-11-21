@extends('layouts.app', [
    'title' => 'Complaints',
    'page' => 'owner-complaints',
    'headerSolid' => true,
    'robots' => 'noindex',
    'mainClass' => 'min-h-screen pb-24 pt-28',
])

@section('content')
    <x-owner.shell active="complaints" heading="Complaints" intro="Guests can report a problem up to {{ $money['complaint_window_hours'] }} hours after a trip. While a report is open, that trip's earnings are on hold. Reply with your side of the story — our team reviews both before deciding.">
        <div class="mt-10 space-y-5">
            @forelse ($complaints as $complaint)
                <article id="complaint-{{ $complaint->ulid }}" class="scroll-mt-28 rounded-[1.75rem] bg-white p-5 shadow-card ring-1 ring-navy-100 md:p-7">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-[11px] font-extrabold uppercase tracking-wider {{ $complaint->status_class }}">{{ $complaint->status_label }}</span>
                        <span class="text-xs text-navy-400">Reported {{ $complaint->opened_at->format('M j, Y · g:i A') }}</span>
                    </div>
                    <h2 class="mt-3 font-display text-2xl text-navy-950">{{ $complaint->category_label }}</h2>
                    <p class="mt-1 text-sm text-navy-500">{{ $complaint->booking->experience }} · {{ $complaint->booking->boat->short_title }} · {{ $complaint->booking->starts_at->format('D, M j') }} · {{ $complaint->booking->ref }}</p>

                    <figure class="mt-5 rounded-2xl bg-navy-50/70 p-5">
                        <blockquote class="text-sm leading-relaxed text-navy-800">“{{ $complaint->description }}”</blockquote>
                        <figcaption class="mt-3 flex flex-wrap items-center gap-3 text-xs font-semibold text-navy-500">
                            <span>— {{ $complaint->booking->customer }}</span>
                            @if ($complaint->photos)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-2.5 py-1 ring-1 ring-navy-100"><i data-lucide="image" class="h-3.5 w-3.5 text-brass-500"></i>{{ $complaint->photos }} {{ Str::plural('photo', $complaint->photos) }} attached</span>
                            @endif
                        </figcaption>
                    </figure>

                    @if ($complaint->is_open)
                        <p class="mt-4 flex items-start gap-2 text-xs font-semibold text-amber-800"><i data-lucide="shield-alert" class="h-4 w-4 shrink-0"></i>{{ money($complaint->booking->net_cents, 'always') }} of earnings for this trip is on hold until our team resolves the report.</p>
                    @endif

                    <div class="mt-5" data-response>
                        @if ($complaint->owner_response)
                            <div class="rounded-2xl border border-navy-100 p-5">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-navy-400">Your response</p>
                                <p class="mt-2 text-sm leading-relaxed text-navy-700">{{ $complaint->owner_response }}</p>
                            </div>
                        @elseif ($complaint->is_open)
                            <form class="space-y-3" data-response-form novalidate>
                                <label for="response-{{ $complaint->ulid }}" class="text-sm font-bold text-navy-800">Your response</label>
                                <textarea id="response-{{ $complaint->ulid }}" rows="4" maxlength="2000" required placeholder="What happened on the trip? Our team reads every response before deciding." class="w-full rounded-xl border border-navy-200 bg-white px-4 py-3 text-sm text-navy-950 placeholder:text-navy-300 focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100"></textarea>
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <span class="text-xs text-navy-400"><span data-count>2000</span> characters left</span>
                                    <x-button type="submit" variant="dark">Send response</x-button>
                                </div>
                            </form>
                        @endif
                    </div>

                    @if (! $complaint->is_open)
                        <div @class(['mt-5 flex gap-3 rounded-2xl p-5 text-sm ring-1', 'bg-red-50 text-red-900 ring-red-200' => $complaint->deduction_cents > 0, 'bg-emerald-50 text-emerald-900 ring-emerald-200' => $complaint->deduction_cents === 0])>
                            <i data-lucide="{{ $complaint->deduction_cents > 0 ? 'circle-minus' : 'circle-check' }}" class="h-5 w-5 shrink-0"></i>
                            <div>
                                <p class="font-bold">{{ $complaint->deduction_cents > 0 ? money($complaint->deduction_cents, 'always').' deducted from your earnings' : 'No deduction' }} · {{ $complaint->resolved_at->format('M j, Y') }}</p>
                                <p class="mt-1 leading-relaxed opacity-90">{{ $complaint->resolution_note }}</p>
                            </div>
                        </div>
                    @endif
                </article>
            @empty
                <div class="rounded-[2rem] border-2 border-dashed border-navy-200 bg-white px-6 py-14 text-center">
                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-navy-50 text-navy-400"><i data-lucide="smile" class="h-7 w-7"></i></span>
                    <h2 class="mt-4 font-display text-2xl text-navy-950">No complaints</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm text-navy-500">Nice work — no guest has reported a problem.</p>
                </div>
            @endforelse
        </div>
    </x-owner.shell>
@endsection
