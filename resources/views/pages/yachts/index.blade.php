@extends('layouts.app', [
    'title' => 'Yachts',
    'description' => 'Browse our Chicago charter fleet — motor yachts, sailboats, a party catamaran and a superyacht. Search by date, group size and experience; captain included on every boat.',
    'page' => 'yachts',
])

@php
    $business = config('site.business');
    $chipOn = 'shrink-0 rounded-full px-4 py-2.5 text-sm font-bold transition bg-navy-950 text-white';
    $chipOff = 'shrink-0 rounded-full px-4 py-2.5 text-sm font-bold transition border border-navy-200 text-navy-700 hover:border-navy-950';
    $selectClass = 'w-full appearance-none rounded-full border border-navy-200 bg-white py-2.5 pl-4 pr-9 text-sm font-semibold text-navy-800 focus:border-navy-950 focus:outline-hidden';
    $guestOptions = App\Support\FleetFilters::GUESTS;
    $visibleCount = $yachts->where('visible', true)->count();
    // The mockup says "Six hand-picked yachts…"; the number follows the live fleet (3.5).
    $fleetCount = $yachts->count();
    $countWord = ['One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve'][$fleetCount - 1] ?? null;
    $fleetIntro = match (true) {
        $fleetCount === 0 => 'Hand-picked yachts for every kind of Chicago day',
        $countWord !== null => $countWord.' hand-picked '.($fleetCount === 1 ? 'yacht' : 'yachts').' for every kind of Chicago day',
        default => $fleetCount.' hand-picked yachts for every kind of Chicago day',
    }.' — each one inspected, insured and captained by a licensed local pro.';
@endphp

@section('content')
    <x-page-hero image="images/yacht-skyline-majesty.jpg" crumb="Yachts" image-class="opacity-60" overlay-class="from-navy-950/80 via-navy-950/50 to-navy-950"
                 :intro="$fleetIntro">
        Our <em>Fleet</em>
    </x-page-hero>

    {{-- Filters (4.4, D21): a GET form (works without JS); resources/js/pages/yachts.js filters instantly and
         reloads only for a date, whose free boats come from the availability calendar. Sticky from lg up; on
         phones the taller bar scrolls away with the page. --}}
    <section class="z-30 border-b border-navy-100 bg-white/90 backdrop-blur-lg lg:sticky lg:top-20">
        <div class="container">
            <form id="fleet-filters" action="{{ route('yachts.index') }}" method="get" class="space-y-3 py-4">
                <input type="hidden" name="type" value="{{ $filters['type'] }}">
                <div class="grid grid-cols-2 gap-2 lg:grid-cols-[1.1fr_1fr_1.4fr_1fr_auto]">
                    <label class="relative">
                        <span class="sr-only">Date</span>
                        <input type="date" name="date" value="{{ $filters['date'] }}" min="{{ $today }}" max="{{ $lastDate }}" aria-label="Date" class="{{ $selectClass }} pr-4">
                    </label>
                    <label class="relative">
                        <span class="sr-only">Guests</span>
                        <select name="guests" class="{{ $selectClass }}">
                            <option value="0">Any group size</option>
                            @foreach ($guestOptions as $count)
                                <option value="{{ $count }}" @selected($filters['guests'] === $count)>{{ $count }}+ guests</option>
                            @endforeach
                        </select>
                        <i data-lucide="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400"></i>
                    </label>
                    <label class="relative">
                        <span class="sr-only">Experience</span>
                        <select name="experience" class="{{ $selectClass }}">
                            <option value="">Any experience</option>
                            @foreach ($experiences as $experience)
                                <option value="{{ $experience->slug }}" @selected($filters['experience'] === $experience->slug)>{{ $experience->name }}</option>
                            @endforeach
                        </select>
                        <i data-lucide="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400"></i>
                    </label>
                    <label class="relative">
                        <span class="sr-only">Trip length</span>
                        <select name="hours" class="{{ $selectClass }}">
                            <option value="0">Any length</option>
                            @foreach (App\Support\FleetFilters::HOURS as $hours)
                                <option value="{{ $hours }}" @selected($filters['hours'] === $hours)>{{ $hours }} hours</option>
                            @endforeach
                        </select>
                        <i data-lucide="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400"></i>
                    </label>
                    <button type="button" id="more-toggle" aria-expanded="{{ $moreCount ? 'true' : 'false' }}" aria-controls="more-filters"
                            class="col-span-2 inline-flex items-center justify-center gap-2 rounded-full border border-navy-200 px-5 py-2.5 text-sm font-bold text-navy-800 transition hover:border-navy-950 aria-expanded:border-navy-950 lg:col-span-1">
                        <i data-lucide="sliders-horizontal" class="h-4 w-4"></i>More filters<span id="more-count" @class(['rounded-full bg-navy-950 px-2 py-0.5 text-[11px] text-white', 'hidden' => ! $moreCount])>{{ $moreCount }}</span>
                    </button>
                </div>

                <div id="more-filters" @if (! $moreCount) hidden @endif class="grid gap-4 rounded-2xl border border-navy-100 bg-white p-4 sm:grid-cols-3">
                    <label class="relative block">
                        <span class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Harbor</span>
                        <span class="relative mt-1.5 block"><select name="harbor" class="{{ $selectClass }}">
                            <option value="">All harbors</option>
                            @foreach ($harbors as $harbor)
                                <option value="{{ $harbor->slug }}" @selected($filters['harbor'] === $harbor->slug)>{{ $harbor->name }}</option>
                            @endforeach
                        </select><i data-lucide="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400"></i></span>
                    </label>
                    <label class="relative block">
                        <span class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Price</span>
                        <span class="relative mt-1.5 block"><select name="price" class="{{ $selectClass }}">
                            <option value="">Any price</option>
                            @foreach (App\Support\FleetFilters::PRICES as $value => [$label])
                                <option value="{{ $value }}" @selected($filters['price'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select><i data-lucide="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400"></i></span>
                    </label>
                    <label class="relative block">
                        <span class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Boat length</span>
                        <span class="relative mt-1.5 block"><select name="length" class="{{ $selectClass }}">
                            <option value="">Any length</option>
                            @foreach (App\Support\FleetFilters::LENGTHS as $value => [$label])
                                <option value="{{ $value }}" @selected($filters['length'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select><i data-lucide="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400"></i></span>
                    </label>
                    @if ($amenities->isNotEmpty())
                        <fieldset class="sm:col-span-3">
                            <legend class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">On board</legend>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($amenities as $amenity)
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-full px-3.5 py-2 text-sm font-semibold text-navy-700 ring-1 ring-navy-200 has-[:checked]:bg-navy-950 has-[:checked]:text-white has-[:checked]:ring-navy-950">
                                        <input type="checkbox" name="amenities[]" value="{{ $amenity->ulid }}" @checked(in_array($amenity->ulid, $filters['amenities'], true)) class="sr-only">{{ $amenity->name }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif
                </div>

                <div class="flex items-center justify-between gap-3">
                    <div class="no-scrollbar -mx-1 flex min-w-0 gap-2 overflow-x-auto px-1" id="type-chips" role="group" aria-label="Yacht type" data-chip-on="{{ $chipOn }}" data-chip-off="{{ $chipOff }}">
                        @foreach ($types as $type)
                            <button type="submit" name="type" value="{{ $type }}" data-type="{{ $type }}" aria-pressed="{{ $filters['type'] === $type ? 'true' : 'false' }}"
                                    class="{{ $filters['type'] === $type ? $chipOn : $chipOff }}">{{ $type }}</button>
                        @endforeach
                    </div>
                    <label class="relative shrink-0">
                        <span class="sr-only">Sort</span>
                        <select name="sort" class="{{ $selectClass }}">
                            @foreach (App\Support\FleetFilters::SORTS as $value => $label)
                                <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <i data-lucide="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400"></i>
                    </label>
                </div>
                <noscript><button type="submit" class="rounded-full bg-navy-950 px-5 py-2.5 text-sm font-bold text-white">Apply</button></noscript>
            </form>
        </div>
    </section>

    {{-- Results --}}
    <section class="py-16 md:py-20">
        <div class="container">
            <div class="mb-8 flex items-center justify-between gap-4">
                <p class="text-sm font-semibold text-navy-500" aria-live="polite"><span id="result-count" class="text-navy-950">{{ $visibleCount }}</span> {{ $filters['date'] ? 'yachts free on '.Carbon\CarbonImmutable::parse($filters['date'])->format('D, M j') : 'yachts available' }}</p>
                <a href="{{ route('yachts.index') }}" id="reset-filters" @class(['text-sm font-bold text-brass-600 hover:text-brass-700', 'hidden' => ! $isFiltered])>Reset filters</a>
            </div>
            <div id="yacht-grid" class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($yachts as $row)
                    @php($yacht = $row['yacht'])
                    <x-yacht-card :yacht="$yacht" :delay="$loop->index % 3" :query="$bookingQuery"
                                  :free="$row['free'] ? 'Free on '.Carbon\CarbonImmutable::parse($filters['date'])->format('M j').' · '.trans_choice('{1} 1 start time|[2,*] :count start times', $row['free']) : null"
                                  data-type="{{ $yacht->category }}" data-harbor="{{ $yacht->harbor?->slug }}" data-guests="{{ $yacht->max_guests }}"
                                  data-price="{{ $yacht->hourly_rate_cents }}" data-reviews="{{ $yacht->reviews_count }}" data-length="{{ $yacht->length_ft }}"
                                  data-experiences="{{ implode(' ', $yacht->experiences) }}" data-amenities="{{ implode(' ', $yacht->amenity_ids ?? []) }}"
                                  data-lengths="{{ json_encode($yacht->lengths) }}"
                                  :hidden="! $row['visible']" />
                @endforeach
            </div>
            <div id="empty-state" @class(['rounded-3xl border-2 border-dashed border-navy-200 p-14 text-center', 'hidden' => $visibleCount > 0])>
                <i data-lucide="sailboat" class="mx-auto h-10 w-10 text-navy-300"></i>
                <p class="mt-4 font-display text-2xl text-navy-950">No yachts match those filters</p>
                <p class="mt-2 text-sm text-navy-500">{{ $filters['date'] ? 'Try another date or fewer filters' : 'Try a different group size or experience' }} — or call us and we'll find the right boat.</p>
            </div>
        </div>
    </section>

    {{-- Compare --}}
    <section class="pb-24">
        <div class="container">
            <div class="reveal overflow-hidden rounded-3xl bg-white shadow-card ring-1 ring-navy-100">
                <div class="flex flex-col justify-between gap-4 border-b border-navy-100 p-7 md:flex-row md:items-center">
                    <div>
                        <h2 class="font-display text-3xl text-navy-950">Compare at a glance</h2>
                        <p class="mt-1 text-sm text-navy-500">Weekday hourly rates. Fri–Sun +{{ App\Support\Money::percent(app(App\Services\PublicCatalog::class)->pricing()['weekend_multiplier_bp'] - 10000) }}. Captain &amp; crew fee shown separately.</p>
                    </div>
                    <a href="{{ route('pricing') }}" class="inline-flex items-center gap-2 text-sm font-bold text-brass-600 hover:text-brass-700">Full pricing details <i data-lucide="arrow-right" class="h-4 w-4"></i></a>
                </div>
                <div class="no-scrollbar relative overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <caption class="sr-only">Fleet comparison: type, capacity, harbor and hourly rates</caption>
                        <thead class="bg-navy-50 text-[11px] font-bold uppercase tracking-[0.18em] text-navy-500">
                            <tr>
                                <th scope="col" class="px-7 py-4">Yacht</th><th scope="col" class="px-4 py-4">Type</th><th scope="col" class="px-4 py-4">Guests</th>
                                <th scope="col" class="px-4 py-4">Harbor</th><th scope="col" class="px-4 py-4">From / hr</th><th scope="col" class="px-4 py-4">Captain / hr</th>
                                <th scope="col" class="px-7 py-4"><span class="sr-only">Book</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-navy-100">
                            @foreach ($compare as $yacht)
                                <tr class="transition hover:bg-navy-50/60">
                                    <th scope="row" class="px-7 py-4 font-normal"><a href="{{ route('yachts.show', $yacht->slug) }}" class="flex items-center gap-4"><img src="{{ asset($yacht->cover) }}" alt="" loading="lazy" class="h-12 w-16 rounded-xl object-cover"><span class="font-display text-lg text-navy-950">{{ $yacht->title }}</span></a></th>
                                    <td class="px-4 py-4 text-navy-600">{{ $yacht->category }} · {{ $yacht->length_ft }} ft</td>
                                    <td class="px-4 py-4 font-semibold text-navy-800">{{ $yacht->max_guests }}</td>
                                    <td class="px-4 py-4 text-navy-600">{{ $yacht->harbor?->name }}</td>
                                    <td class="px-4 py-4 font-extrabold text-navy-950">{{ money($yacht->hourly_rate_cents) }}</td>
                                    <td class="px-4 py-4 text-navy-600">{{ money($yacht->captain_rate_cents) }}</td>
                                    <td class="px-7 py-4 text-right">
                                        @if ($yacht->booking_enabled)
                                            <a href="{{ route('yachts.show', [$yacht->slug, ...$bookingQuery]) }}#book" class="inline-flex rounded-full bg-navy-950 px-4 py-2 text-xs font-bold text-white hover:bg-brass-400 hover:text-navy-950">Book<span class="sr-only"> {{ $yacht->title }}</span></a>
                                        @else
                                            <span class="inline-flex rounded-full bg-navy-100 px-4 py-2 text-xs font-bold text-navy-400">Unavailable</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="reveal mt-10 flex flex-col items-center justify-between gap-6 rounded-3xl bg-navy-950 p-8 text-white md:flex-row md:p-10">
                <div class="flex items-center gap-5">
                    <span class="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-brass-400 text-navy-950"><i data-lucide="life-buoy" class="h-6 w-6"></i></span>
                    <div><p class="font-display text-2xl">Not sure which yacht fits?</p><p class="text-sm text-white/60">Tell us your group size and occasion — we'll recommend the perfect match.</p></div>
                </div>
                <div class="flex gap-3">
                    <a href="tel:{{ $business['phone_href'] }}" class="inline-flex items-center gap-2 rounded-full bg-brass-400 px-6 py-3 text-sm font-bold text-navy-950 hover:bg-brass-300"><i data-lucide="phone" class="h-4 w-4"></i>Call</a>
                    <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-6 py-3 text-sm font-bold hover:bg-white hover:text-navy-950">Message us</a>
                </div>
            </div>
        </div>
    </section>
@endsection
