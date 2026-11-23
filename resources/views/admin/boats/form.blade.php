@extends('layouts.admin', ['title' => $boat->exists ? $boat->title : 'Add a house boat', 'active' => 'boats'])

@use('App\Enums\BoatStatus')
@use('App\Support\Admin\BoatOptions')
@use('App\Support\Money')

@php
    $editing = $boat->exists;
    $trashed = $editing && $boat->trashed();
    $submitted = old('title') !== null; // the form came back with errors: show what was typed
    $box = 'flex items-center gap-2 rounded-xl px-3.5 py-2.5 text-sm font-semibold ring-1 ring-navy-200 has-[:checked]:bg-navy-950 has-[:checked]:text-white has-[:checked]:ring-navy-950';
    $tz = config('app.display_timezone');
    $tones = ['published' => 'success', 'paused' => 'muted', 'suspended' => 'danger', 'archived' => 'muted', 'draft' => 'navy', 'pending_review' => 'warning', 'changes_requested' => 'warning', 'approved' => 'brass', 'rejected' => 'danger'];

    $chosenDurations = array_map('intval', $submitted ? (array) old('durations', []) : ($boat->durations ?? []));
    $notAllowed = $submitted ? (array) old('not_allowed', []) : ($boat->rules['not_allowed'] ?? []);
    $chosenAmenities = $submitted ? (array) old('amenities', []) : ($editing ? $boat->amenities->pluck('ulid')->all() : []);
    $offers = $editing ? $boat->experiences->mapWithKeys(fn ($e) => [$e->slug => $e->pivot->package_fee_override_cents])->all() : [];
    $rules = $editing ? $boat->availabilityRules->keyBy('weekday') : collect();
    $markup = $boat->weekend_multiplier_bp === null ? '' : (string) ($boat->weekend_multiplier_bp - 10000);
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
@endphp

@section('content')
    <x-admin.page-header :title="$editing ? $boat->title : 'Add a house boat'" :back="['All boats', route('admin.boats.index', $trashed ? ['view' => 'trash'] : [])]"
        :description="$editing ? ($boat->isHouse() ? 'House boat' : 'Owner boat · '.($boat->owner?->name ?? 'owner')).' · '.$boat->category.($boat->length_ft ? ' · '.$boat->length_ft.' ft' : '') : 'Your own boat. Save it first, then add photos, blocks and publish it.'">
        @if ($editing)
            <x-slot:actions>
                @if ($trashed)
                    <x-admin.pill tone="danger">In the Trash</x-admin.pill>
                @else
                    <x-admin.pill :tone="$tones[$boat->status->value] ?? 'navy'">{{ $boat->status === BoatStatus::Published ? 'Live on the site' : $boat->status->label() }}</x-admin.pill>
                    @unless ($boat->booking_enabled)<x-admin.pill tone="warning">Unavailable tag</x-admin.pill>@endunless
                    @if ($boat->status === BoatStatus::Published)
                        <x-button variant="outline" data-open-modal="hide-boat" icon-left="eye-off">Hide from site</x-button>
                    @else
                        <form method="POST" action="{{ route('admin.boats.status', $boat) }}">
                            @csrf
                            <input type="hidden" name="action" value="publish">
                            <x-button type="submit" variant="dark" icon-left="eye">Publish</x-button>
                        </form>
                    @endif
                @endif
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    @if ($errors->has('action') || $errors->has('note'))
        <div class="mt-6 rounded-2xl bg-red-50 p-4 text-sm font-semibold text-red-700 ring-1 ring-red-200" role="alert">{{ $errors->first('action') ?: $errors->first('note') }}</div>
    @endif

    @if ($trashed)
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <x-admin.card title="Details" class="lg:col-span-2">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Type</dt><dd class="mt-1 text-navy-900">{{ $boat->category }} · {{ $boat->length_ft }} ft · up to {{ $boat->max_guests }} guests</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Hourly rate</dt><dd class="mt-1 text-navy-900">{{ $boat->hourly_rate_cents ? money($boat->hourly_rate_cents) : '—' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Photos</dt><dd class="mt-1 text-navy-900">{{ $boat->photos->count() }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Web address</dt><dd class="mt-1 font-mono text-navy-900">{{ $boat->slug }}</dd></div>
                </dl>
                <p class="mt-5 text-xs text-navy-500">Restore the boat to edit it. While it is in the Trash it is hidden everywhere and can't be booked.</p>
            </x-admin.card>
            <x-admin.lifecycle :record="$boat" label="boat" :delete-blockers="$deleteBlockers" :routes="[
                'restore' => route('admin.boats.restore', $boat),
                'destroy' => route('admin.boats.destroy', $boat),
            ]" />
        </div>
    @else
        {{-- Jump to a section (3.7): the form is long, so each card has an anchor. --}}
        <nav class="sticky top-16 z-20 -mx-4 mt-6 flex gap-2 overflow-x-auto border-b border-navy-100 bg-shell/95 px-4 py-3 backdrop-blur md:-mx-8 md:px-8" aria-label="Boat form sections">
            @foreach (['details' => 'Details', 'specs' => 'Specs', 'location' => 'Location', 'pricing' => 'Pricing', 'rules' => 'Rules', 'features' => 'Features', 'experiences' => 'Experiences', 'hours' => 'Hours'] + ($editing ? ['photos' => 'Photos', 'blocks' => 'Blocked times'] : []) as $anchor => $label)
                <a href="#{{ $anchor }}" class="shrink-0 rounded-full bg-white px-3.5 py-1.5 text-xs font-bold text-navy-600 ring-1 ring-navy-100 hover:text-navy-950 hover:ring-navy-300">{{ $label }}</a>
            @endforeach
        </nav>

        <form method="POST" action="{{ $editing ? route('admin.boats.update', $boat) : route('admin.boats.store') }}" class="mt-6 grid gap-6 lg:grid-cols-3">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="min-w-0 space-y-6 lg:col-span-2">
                <x-admin.card title="Details" id="details" class="scroll-mt-32">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.input name="title" label="Name" :value="$boat->title" required maxlength="120" placeholder="e.g. The Azure" class="sm:col-span-2" />
                        <x-form.input name="slug" label="Web address" :value="$boat->slug" maxlength="120" placeholder="made from the name" hint="Leave empty to make it from the name." />
                        <x-form.input name="badge" label="Badge (optional)" :value="$boat->badge" maxlength="60" placeholder="e.g. Most popular" />
                        <x-form.input name="short" label="Short description" :value="$boat->short" maxlength="255" placeholder="One line for the boat cards" class="sm:col-span-2" />
                        <x-form.textarea name="highlights_text" label="Highlights (one per line)" :value="implode(PHP_EOL, $boat->highlights ?? [])" rows="6" maxlength="1500"
                            placeholder="e.g. Onboard generator" hint="Shown as the Amenities list on the boat page. Up to 12 short lines — what makes this boat special." :error="$errors->first('highlights') ?: $errors->first('highlights.*')" class="sm:col-span-2" />
                        <x-form.textarea name="description" label="Description" :value="$boat->description" rows="6" maxlength="5000" class="sm:col-span-2" />
                    </div>
                </x-admin.card>

                <x-admin.card title="Specs" id="specs" class="scroll-mt-32">
                    <div class="grid gap-5 sm:grid-cols-3">
                        <x-form.select name="type" label="Type" :options="['power' => 'Power', 'sail' => 'Sail']" :value="$boat->type?->value" required />
                        <x-form.select name="category" label="Category" :options="BoatOptions::categories($boat->category)" :value="$boat->category" required placeholder="Choose…" />
                        <x-form.input name="length_ft" type="number" label="Length (ft)" :value="$boat->length_ft" required min="10" max="400" />
                        <x-form.input name="max_guests" type="number" label="Max guests" :value="$boat->max_guests" required min="1" max="150" hint="Captained charters: 6 at most without a Coast Guard COI." />
                        <x-form.input name="make" label="Make" :value="$boat->make" maxlength="80" />
                        <x-form.input name="model" label="Model" :value="$boat->model" maxlength="80" />
                        <x-form.input name="year" type="number" label="Year" :value="$boat->year" min="1950" :max="now()->year + 1" />
                        <x-form.input name="staterooms" type="number" label="Staterooms" :value="$boat->staterooms" min="0" max="50" />
                        <x-form.input name="sleeps" type="number" label="Sleeps" :value="$boat->sleeps" min="0" max="100" />
                        <x-form.input name="beds" type="number" label="Beds" :value="$boat->beds" min="0" max="100" />
                        <x-form.input name="heads" type="number" label="Bathrooms" :value="$boat->heads" min="0" max="50" />
                        <x-form.input name="engine_count" type="number" label="Engines" :value="$boat->engine_count" min="0" max="10" />
                        <x-form.select name="engine_type" label="Engine type" :options="BoatOptions::engineTypes()" :value="$boat->engine_type" placeholder="—" />
                        <x-form.input name="engine_model" label="Engine make & model" :value="$boat->engine_model" maxlength="80" placeholder="e.g. Volvo Penta 4.3L GXi" />
                        <x-form.input name="horsepower" type="number" label="Horsepower" :value="$boat->horsepower" min="0" max="20000" />
                        <x-form.input name="engine_year" type="number" label="Engine year" :value="$boat->engine_year" min="1950" :max="now()->year + 1" />
                        <x-form.input name="registration_no" label="Registration no." :value="$boat->registration_no" maxlength="60" class="sm:col-span-2" />
                        <x-form.checkbox name="uscg_inspected" hidden-zero :checked="(bool) $boat->uscg_inspected" class="self-end pb-3">USCG inspected (COI)<span class="block text-xs text-navy-500">Needed for more than 6 guests.</span></x-form.checkbox>
                    </div>
                </x-admin.card>

                <x-admin.card title="Location" id="location" class="scroll-mt-32">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.select name="harbor" label="Departs from" :options="$harbors" :value="$boat->harbor?->slug ?? \App\Models\Harbor::withTrashed()->whereKey($boat->harbor_id)->value('slug')" placeholder="No harbor" hint="Manage the list in Fleet → Harbors." class="sm:col-span-2" />
                        <x-form.input name="street" label="Street (optional)" :value="$boat->street" maxlength="255" class="sm:col-span-2" />
                        <x-form.input name="city" label="City" :value="$boat->city" maxlength="120" />
                        <div class="grid grid-cols-2 gap-5">
                            <x-form.select name="state" label="State" :options="BoatOptions::states()" :value="$boat->state" placeholder="—" />
                            <x-form.input name="zip" label="ZIP" :value="$boat->zip" maxlength="10" inputmode="numeric" />
                        </div>
                        <x-form.input name="lat" label="Latitude (optional)" :value="$boat->lat" inputmode="decimal" maxlength="12" />
                        <x-form.input name="lng" label="Longitude (optional)" :value="$boat->lng" inputmode="decimal" maxlength="12" />
                    </div>
                </x-admin.card>

                <x-admin.card title="Pricing" id="pricing" class="scroll-mt-32">
                    <div class="space-y-6">
                        <div class="grid gap-5 sm:grid-cols-3">
                            <x-form.input name="hourly_rate" label="Hourly rate (USD)" :value="Money::input($boat->hourly_rate_cents)" required inputmode="decimal" maxlength="10" />
                            <x-form.select name="weekend_markup" label="Weekend (Fri–Sun)" :options="BoatOptions::weekendMarkups()" :value="$markup" placeholder="Use the site setting" />
                            <x-form.input name="min_hours" type="number" label="Minimum hours" :value="$boat->min_hours" required min="1" max="12" />
                            <x-form.input name="captain_rate" label="Captain & crew (USD / hour)" :value="Money::input($boat->captain_rate_cents)" required inputmode="decimal" maxlength="10" hint="Shown to guests as its own line." />
                            <x-form.input name="cleaning_fee" label="Fuel & cleaning (USD)" :value="Money::input($boat->cleaning_fee_cents)" required inputmode="decimal" maxlength="10" />
                            <x-form.input name="crew_count" type="number" label="Crew" :value="$boat->crew_count" required min="1" max="20" />
                        </div>
                        <fieldset>
                            <legend class="text-sm font-bold text-navy-800">Trip lengths this boat offers</legend>
                            <p class="mt-1 text-xs text-navy-500">None ticked: the lengths of each experience. A fixed price replaces hourly × hours for that length (optional).</p>
                            <div class="mt-3 grid gap-2 sm:grid-cols-3">
                                @foreach (BoatOptions::DURATIONS as $hours)
                                    <div class="flex items-center gap-2 rounded-xl p-2 ring-1 ring-navy-100">
                                        <label class="flex flex-1 items-center gap-2 text-sm font-semibold"><input type="checkbox" name="durations[]" value="{{ $hours }}" @checked(in_array($hours, $chosenDurations, true)) class="h-4 w-4 accent-navy-950">{{ $hours }} h</label>
                                        <label for="price-{{ $hours }}" class="sr-only">Fixed price for {{ $hours }} hours (USD)</label>
                                        <input id="price-{{ $hours }}" name="duration_prices[{{ $hours }}]" inputmode="decimal" maxlength="10" placeholder="Fixed $" value="{{ old('duration_prices.'.$hours, Money::input($boat->duration_prices[(string) $hours] ?? null)) }}" class="w-24 rounded-lg border border-navy-200 px-2 py-1.5 text-sm focus:border-navy-950 focus:outline-hidden">
                                    </div>
                                @endforeach
                            </div>
                            @foreach (['durations', 'durations.*', 'duration_prices.*'] as $key)
                                @if ($errors->first($key))<p class="mt-1.5 text-xs font-semibold text-red-600">{{ $errors->first($key) }}</p>@break @endif
                            @endforeach
                        </fieldset>
                    </div>
                </x-admin.card>

                <x-admin.card title="Booking rules" id="rules" class="scroll-mt-32">
                    <div class="space-y-6">
                        <div class="grid gap-5 sm:grid-cols-3">
                            <x-form.select name="cancellation_policy" label="Cancellation policy" :options="$policies" :value="$boat->cancellationPolicy?->slug" required placeholder="Choose…" />
                            <x-form.select name="advance_notice_minutes" label="Book at least" :options="collect(BoatOptions::ADVANCE_NOTICE)->map(fn ($l, $m) => $m ? $l.' ahead' : 'Any time')->all()" :value="$boat->advance_notice_minutes ?? 0" required />
                            <x-form.select name="buffer_minutes" label="Gap between trips" :options="BoatOptions::BUFFERS" :value="$boat->buffer_minutes ?? 0" required />
                        </div>
                        <x-form.checkbox name="allow_multiple_bookings" hidden-zero :checked="(bool) $boat->allow_multiple_bookings">Allow more than one booking a day</x-form.checkbox>
                        <fieldset>
                            <legend class="text-sm font-bold text-navy-800">Not allowed on board</legend>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach (BoatOptions::rules() as $rule)
                                    <label class="{{ $box }}"><input type="checkbox" name="not_allowed[]" value="{{ $rule }}" @checked(in_array($rule, $notAllowed, true)) class="h-4 w-4 accent-brass-400">{{ $rule }}</label>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>
                </x-admin.card>

                <x-admin.card title="Operation">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.select name="captain_source" label="Captain" :options="['platform' => 'Our captains (assigned per trip)', 'owner' => 'The owner captains']" :value="$boat->captain_source?->value" required />
                        <x-form.input name="fuel_policy" label="Fuel policy (optional)" :value="$boat->fuel_policy" maxlength="60" placeholder="e.g. Included" />
                        <x-form.checkbox name="renter_operated_allowed" hidden-zero :checked="(bool) $boat->renter_operated_allowed">The renter may drive it<span class="block text-xs text-navy-500">Only boats up to {{ config('listing.rules_engine.renter_max_length_ft') }} ft.</span></x-form.checkbox>
                        <x-form.checkbox name="fishing_charter" hidden-zero :checked="(bool) $boat->fishing_charter">Fishing charter</x-form.checkbox>
                    </div>
                </x-admin.card>

                <x-admin.card title="Experiences offered" id="experiences" class="scroll-mt-32">
                    @if ($experiences->isEmpty())
                        <p class="text-sm text-navy-500">No experiences yet. <a href="{{ route('admin.experiences.create') }}" class="font-bold text-navy-950 underline">Add one</a> first.</p>
                    @else
                        <p class="mb-4 text-xs text-navy-500">Leave the fee empty to use the experience's own package fee.</p>
                        <div class="divide-y divide-navy-100">
                            @foreach ($experiences as $experience)
                                @php
                                    $offered = $submitted ? (bool) old('experiences.'.$experience->slug.'.offered') : array_key_exists($experience->slug, $offers);
                                    $fee = old('experiences.'.$experience->slug.'.fee', Money::input($offers[$experience->slug] ?? null));
                                @endphp
                                <div class="flex flex-wrap items-center gap-3 py-2.5">
                                    <label class="flex min-w-0 flex-1 items-center gap-3 text-sm font-semibold text-navy-900">
                                        <input type="hidden" name="experiences[{{ $experience->slug }}][offered]" value="0">
                                        <input type="checkbox" name="experiences[{{ $experience->slug }}][offered]" value="1" @checked($offered) class="h-4 w-4 accent-navy-950">
                                        <span class="truncate">{{ $experience->name }}</span>
                                        @unless ($experience->is_active)<x-admin.pill tone="muted">Hidden</x-admin.pill>@endunless
                                    </label>
                                    <span class="text-xs text-navy-400">{{ $experience->package_fee_cents ? money($experience->package_fee_cents) : 'No fee' }}</span>
                                    <label for="fee-{{ $experience->slug }}" class="sr-only">Package fee for {{ $experience->name }} on this boat (USD)</label>
                                    <input id="fee-{{ $experience->slug }}" name="experiences[{{ $experience->slug }}][fee]" inputmode="decimal" maxlength="10" placeholder="Fee $" value="{{ $fee }}" class="w-24 rounded-lg border border-navy-200 px-2 py-1.5 text-sm focus:border-navy-950 focus:outline-hidden">
                                </div>
                            @endforeach
                        </div>
                        @if ($errors->first('experiences') ?: $errors->first('experiences.*'))<p class="mt-2 text-xs font-semibold text-red-600">{{ $errors->first('experiences') ?: collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'experiences.'))->flatten()->first() }}</p>@endif
                    @endif
                </x-admin.card>

                <x-admin.card title="Features & amenities" id="features" class="scroll-mt-32">
                    <p class="mb-4 text-xs text-navy-500">Tick what this boat has. Missing something? Type it in the list's box (separate several with commas); it is added to that list for every boat.</p>
                    <div class="space-y-6">
                        @foreach ($groupLabels as $group => $label)
                            <fieldset>
                                <legend class="text-sm font-bold text-navy-800">{{ $label }}</legend>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($amenityGroups[$group] ?? [] as $amenity)
                                        <label class="{{ $box }}"><input type="checkbox" name="amenities[]" value="{{ $amenity->ulid }}" @checked(in_array($amenity->ulid, $chosenAmenities, true)) class="h-4 w-4 accent-brass-400">{{ $amenity->name }}</label>
                                    @endforeach
                                </div>
                                <label for="new-{{ $group }}" class="sr-only">Add to {{ $label }}</label>
                                <input id="new-{{ $group }}" name="new_amenities[{{ $group }}]" value="{{ old('new_amenities.'.$group) }}" maxlength="300" placeholder="+ Add a missing item to {{ strtolower($label) }}"
                                       class="mt-2 w-full rounded-xl border border-dashed border-navy-200 bg-white px-3.5 py-2 text-sm text-navy-900 placeholder:text-navy-400 focus:border-navy-950 focus:outline-hidden sm:max-w-md">
                                @if ($errors->first('new_amenity_names.'.$group) ?: $errors->first('new_amenity_names.'.$group.'.*'))
                                    <p class="mt-1.5 text-xs font-semibold text-red-600">{{ $errors->first('new_amenity_names.'.$group) ?: $errors->first('new_amenity_names.'.$group.'.*') }}</p>
                                @endif
                            </fieldset>
                        @endforeach
                        <p class="text-xs text-navy-500">Rename or remove items in Fleet → Amenities.</p>
                    </div>
                </x-admin.card>

                <x-admin.card title="Weekly opening hours" id="hours" class="scroll-mt-32">
                    <p class="mb-4 text-xs text-navy-500">Chicago time. Trips must start and end inside these hours.</p>
                    <div class="divide-y divide-navy-100">
                        @foreach ($days as $number => $day)
                            @php
                                $rule = $rules->get($number);
                                $open = $submitted ? (bool) old('hours.'.$number.'.open') : ($rule ? $rule->is_open : true);
                                $opens = old('hours.'.$number.'.opens', $rule && $rule->opens_at ? substr((string) $rule->opens_at, 0, 5) : BoatOptions::OPENS);
                                $closes = old('hours.'.$number.'.closes', $rule && $rule->closes_at ? substr((string) $rule->closes_at, 0, 5) : BoatOptions::CLOSES);
                                $dayError = $errors->first('hours.'.$number.'.opens') ?: $errors->first('hours.'.$number.'.closes');
                            @endphp
                            <div class="flex flex-wrap items-center gap-3 py-2.5">
                                <label class="flex w-36 items-center gap-2 text-sm font-semibold text-navy-900">
                                    <input type="hidden" name="hours[{{ $number }}][open]" value="0">
                                    <input type="checkbox" name="hours[{{ $number }}][open]" value="1" @checked($open) class="h-4 w-4 accent-navy-950">{{ $day }}
                                </label>
                                <label for="opens-{{ $number }}" class="sr-only">{{ $day }} opens</label>
                                <input id="opens-{{ $number }}" type="time" name="hours[{{ $number }}][opens]" value="{{ $opens }}" class="rounded-lg border border-navy-200 px-2 py-1.5 text-sm focus:border-navy-950 focus:outline-hidden">
                                <span class="text-navy-400">–</span>
                                <label for="closes-{{ $number }}" class="sr-only">{{ $day }} closes</label>
                                <input id="closes-{{ $number }}" type="time" name="hours[{{ $number }}][closes]" value="{{ $closes }}" class="rounded-lg border border-navy-200 px-2 py-1.5 text-sm focus:border-navy-950 focus:outline-hidden">
                                @if ($dayError)<p class="w-full text-xs font-semibold text-red-600">{{ $dayError }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                </x-admin.card>
            </div>

            <div class="min-w-0 space-y-6">
                @if ($checklist)
                    @php($done = collect($checklist['items'])->where(0, true)->count())
                    <x-admin.card title="Listing checklist">
                        <x-slot:actions><span class="text-xs font-bold text-navy-500">{{ $done }} / {{ count($checklist['items']) }}</span></x-slot:actions>
                        <ul class="space-y-2.5">
                            @foreach ($checklist['items'] as [$ok, $label, $hint])
                                <li class="flex items-start gap-2.5 text-sm">
                                    <i data-lucide="{{ $ok ? 'circle-check' : 'circle-alert' }}" @class(['mt-0.5 h-4 w-4 shrink-0', 'text-emerald-600' => $ok, 'text-amber-500' => ! $ok])></i>
                                    <span><span @class(['font-semibold', 'text-navy-950' => ! $ok, 'text-navy-500' => $ok])>{{ $label }}</span>@unless ($ok)<span class="block text-xs text-navy-500">{{ $hint }}</span>@endunless</span>
                                </li>
                            @endforeach
                        </ul>
                        @foreach ($checklist['warnings'] as $warning)
                            <p class="mt-4 flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-xs leading-relaxed text-amber-900 ring-1 ring-amber-200"><i data-lucide="triangle-alert" class="h-4 w-4 shrink-0"></i>{{ $warning }}</p>
                        @endforeach
                        @if ($done === count($checklist['items']) && $boat->status !== BoatStatus::Published)
                            <p class="mt-4 text-xs font-semibold text-emerald-700">Everything is in place — publish it with the button at the top.</p>
                        @endif
                    </x-admin.card>
                @else
                    <x-admin.card title="How to add a boat">
                        <ol class="list-decimal space-y-1.5 pl-5 text-sm text-navy-600">
                            <li>Fill in the details, price and features, and save.</li>
                            <li>Add at least {{ config('listing.rules_engine.min_photos') }} photos (the form opens again after saving).</li>
                            <li>Follow the checklist, then press <strong>Publish</strong>.</li>
                        </ol>
                    </x-admin.card>
                @endif

                <x-admin.card title="On the site">
                    <div class="space-y-5">
                        <x-form.checkbox name="booking_enabled" hidden-zero :checked="$boat->booking_enabled">Bookable<span class="block text-xs text-navy-500">Untick to show the “Unavailable” tag: the boat stays on the site but can't be booked.</span></x-form.checkbox>
                        <x-form.input name="unavailable_reason" label="Why it's unavailable (optional)" :value="$boat->unavailable_reason" maxlength="255" placeholder="e.g. In for maintenance until June" />
                        <x-form.checkbox name="extensions_enabled" hidden-zero :checked="$boat->extensions_enabled">Allow trip extensions<span class="block text-xs text-navy-500">Guests can buy extra hours during the trip when the boat and captain are free.</span></x-form.checkbox>
                        <x-form.checkbox name="is_featured" hidden-zero :checked="(bool) $boat->is_featured">Feature it on the home page</x-form.checkbox>
                        <x-form.input name="sort" label="Order" type="number" :value="$boat->sort" required min="0" max="9999" hint="Lower numbers come first." />
                        <div class="grid grid-cols-2 gap-4">
                            <x-form.input name="rating_avg" label="Rating" :value="$boat->rating_avg" inputmode="decimal" maxlength="4" placeholder="4.9" />
                            <x-form.input name="reviews_count" label="Reviews" type="number" :value="$boat->reviews_count ?? 0" required min="0" max="100000" />
                        </div>
                        <p class="-mt-2 text-xs text-navy-500">Shown on the boat until guest reviews exist.</p>
                        @unless ($editing)
                            <x-form.checkbox name="publish" hidden-zero :checked="true">Publish now<span class="block text-xs text-navy-500">Untick to save it as a draft.</span></x-form.checkbox>
                        @endunless
                    </div>
                </x-admin.card>

                <div class="flex flex-col gap-2 sm:flex-row lg:flex-col">
                    <x-button type="submit" variant="dark" size="lg">{{ $editing ? 'Save changes' : 'Add boat' }}</x-button>
                    <x-button :href="route('admin.boats.index')" variant="outline" size="lg">Cancel</x-button>
                </div>

                @if ($editing)
                    <x-admin.lifecycle :record="$boat" label="boat" :trash-blockers="$trashBlockers" :routes="['trash' => route('admin.boats.trash', $boat)]" />
                @endif
            </div>
        </form>

        @if ($editing)
            {{-- Photos and blocks have their own forms (outside the boat form). --}}
            <div class="mt-6 grid gap-6 lg:grid-cols-3">
                <x-admin.card title="Photos" id="photos" class="scroll-mt-32 lg:col-span-2">
                    <x-slot:actions><span class="text-xs font-semibold text-navy-400">{{ $boat->photos->count() }} / {{ \App\Services\BoatPhotoService::MAX_PHOTOS }} · the first is the cover</span></x-slot:actions>
                    <form method="POST" action="{{ route('admin.boats.photos.store', $boat) }}" enctype="multipart/form-data" class="flex flex-col gap-3 rounded-xl bg-navy-50 p-4 sm:flex-row sm:items-end">
                        @csrf
                        <x-form.file name="photos[]" id="f-photos" label="Add photos (up to 10 at a time)" multiple class="flex-1" :error="$errors->first('photos') ?: collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'photos.'))->flatten()->first()"
                            hint="JPG, PNG, WEBP or GIF up to {{ config('listing.rules_engine.max_photo_mb') }} MB each, at least {{ config('media.image.min_width') }} × {{ config('media.image.min_height') }} px." />
                        <x-button type="submit" variant="dark" icon-left="upload">Upload</x-button>
                    </form>
                    @if ($boat->photos->isEmpty())
                        <p class="mt-5 text-sm text-navy-500">No photos yet. Boats need at least {{ config('listing.rules_engine.min_photos') }} good photos to look right on the site.</p>
                    @else
                        <ul class="mt-5 grid gap-4 sm:grid-cols-2">
                            @foreach ($boat->photos as $index => $photo)
                                <li class="overflow-hidden rounded-xl ring-1 ring-navy-100">
                                    <div class="relative">
                                        <img src="{{ $photo->url('card') }}" alt="{{ $photo->alt }}" loading="lazy" class="aspect-[3/2] w-full object-cover">
                                        @if ($index === 0)<span class="absolute left-2 top-2 rounded-full bg-brass-400 px-2.5 py-0.5 text-[11px] font-extrabold text-navy-950">Cover</span>@endif
                                    </div>
                                    <div class="space-y-2 p-3">
                                        <form method="POST" action="{{ route('admin.boats.photos.update', [$boat, $photo->uuid]) }}" class="flex gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <label for="alt-{{ $photo->uuid }}" class="sr-only">Description of photo {{ $index + 1 }}</label>
                                            <input id="alt-{{ $photo->uuid }}" name="alt" value="{{ $photo->alt }}" maxlength="160" placeholder="What's in the photo" class="min-w-0 flex-1 rounded-lg border border-navy-200 px-2.5 py-1.5 text-sm focus:border-navy-950 focus:outline-hidden">
                                            <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-bold text-navy-700 ring-1 ring-navy-200 hover:bg-navy-50">Save</button>
                                        </form>
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            @foreach ([['first', 'Make cover', 'star', $index === 0], ['up', 'Move up', 'arrow-up', $index === 0], ['down', 'Move down', 'arrow-down', $loop->last]] as [$move, $moveLabel, $icon, $off])
                                                <form method="POST" action="{{ route('admin.boats.photos.update', [$boat, $photo->uuid]) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="move" value="{{ $move }}">
                                                    <button type="submit" @disabled($off) class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-bold text-navy-700 ring-1 ring-navy-200 hover:bg-navy-50 disabled:pointer-events-none disabled:opacity-40" aria-label="{{ $moveLabel }}: photo {{ $index + 1 }}"><i data-lucide="{{ $icon }}" class="h-3.5 w-3.5"></i>{{ $move === 'first' ? 'Cover' : '' }}</button>
                                                </form>
                                            @endforeach
                                            <form method="POST" action="{{ route('admin.boats.photos.destroy', [$boat, $photo->uuid]) }}" class="ml-auto">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-bold text-red-700 ring-1 ring-red-200 hover:bg-red-50" aria-label="Delete photo {{ $index + 1 }}"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i>Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-admin.card>

                <x-admin.card title="Blocked times" id="blocks" class="scroll-mt-32">
                    <p class="text-xs text-navy-500">The boat can't be booked during these times (Chicago time).</p>
                    @if ($blocks->isEmpty())
                        <p class="mt-4 text-sm text-navy-500">No upcoming blocks.</p>
                    @else
                        <ul class="mt-4 divide-y divide-navy-100">
                            @foreach ($blocks as $block)
                                <li class="flex items-start gap-3 py-3">
                                    <div class="min-w-0 flex-1 text-sm">
                                        <p class="font-bold text-navy-950">{{ $block->type->label() }}</p>
                                        <p class="text-xs text-navy-500">{{ $block->starts_at->timezone($tz)->format('M j, g:i A') }} – {{ $block->ends_at->timezone($tz)->format('M j, g:i A') }}</p>
                                        @if ($block->reason)<p class="text-xs text-navy-400">{{ $block->reason }}</p>@endif
                                    </div>
                                    <form method="POST" action="{{ route('admin.boats.blocks.destroy', [$boat, $block]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg p-1.5 text-red-700 hover:bg-red-50" aria-label="Remove this block"><i data-lucide="x" class="h-4 w-4"></i></button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <form method="POST" action="{{ route('admin.boats.blocks.store', $boat) }}" class="mt-4 space-y-3 rounded-xl bg-navy-50 p-4">
                        @csrf
                        <x-form.input name="starts_at" id="block-starts" type="datetime-local" label="From" required :error="$errors->block->first('starts_at')" />
                        <x-form.input name="ends_at" id="block-ends" type="datetime-local" label="Until" required :error="$errors->block->first('ends_at')" />
                        <x-form.select name="type" id="block-type" label="Why" :options="$blockTypes" required :error="$errors->block->first('type')" />
                        <x-form.input name="reason" id="block-reason" label="Note (optional)" maxlength="255" :error="$errors->block->first('reason')" />
                        <x-button type="submit" variant="dark" size="block" icon-left="calendar-x">Block this time</x-button>
                    </form>
                </x-admin.card>
            </div>

            @push('modals')
                <x-admin.modal id="hide-boat" title="Hide {{ $boat->title }} from the site" :open="$errors->has('note')">
                    <form method="POST" action="{{ route('admin.boats.status', $boat) }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="action" value="hide">
                        <p class="text-sm text-navy-600">{{ $boat->isHouse() ? 'The boat disappears from the site until you publish it again.' : "The owner's boat disappears from the site and the owner can't publish it again until you do. The owner will be told why." }}</p>
                        <x-form.textarea name="note" id="hide-note" :label="$boat->isHouse() ? 'Note (optional)' : 'Reason (sent to the owner)'" rows="3" maxlength="500" :required="! $boat->isHouse()" />
                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <x-button type="button" variant="outline" data-close-modal>Cancel</x-button>
                            <x-button type="submit" variant="dark" icon-left="eye-off">Hide from site</x-button>
                        </div>
                    </form>
                </x-admin.modal>
            @endpush
        @endif
    @endif
@endsection
