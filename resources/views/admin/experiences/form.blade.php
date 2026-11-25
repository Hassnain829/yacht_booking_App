@extends('layouts.admin', ['title' => $experience->exists ? $experience->name : 'Add an experience', 'active' => 'experiences'])

@php
    $editing = $experience->exists;
    $trashed = $editing && $experience->trashed();
    $window = App\Support\StartTimes::normalize($experience->time_slots ?? []);
    $chosenDays = old('weekdays') !== null ? array_map('intval', (array) old('weekdays')) : ($experience->allowed_weekdays ?? []);
    $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $box = 'flex items-center gap-2 rounded-xl px-3.5 py-2.5 text-sm font-semibold ring-1 ring-navy-200 has-[:checked]:bg-navy-950 has-[:checked]:text-white has-[:checked]:ring-navy-950';
@endphp

@section('content')
    <x-admin.page-header :title="$editing ? $experience->name : 'Add an experience'" :back="['All experiences', route('admin.experiences.index', $trashed ? ['view' => 'trash'] : [])]"
        :description="$editing ? trans_choice('{0} No boat offers it yet.|{1} Offered by 1 boat.|[2,*] Offered by :count boats.', $experience->boats_count) : 'Guests can pick an experience on a boat\'s booking card. It is free, and guests book the boat by the hour; the experience sets when it departs and on which days. The extras you tick below are suggested at checkout, and charged only when the guest picks them.'">
        @if ($trashed)
            <x-slot:actions><x-admin.pill tone="danger">In the Trash</x-admin.pill></x-slot:actions>
        @endif
    </x-admin.page-header>

    @if ($trashed)
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <x-admin.card title="Details" class="lg:col-span-2">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Category</dt><dd class="mt-1 text-navy-900">{{ $categories[$experience->category] ?? $experience->category }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Web address</dt><dd class="mt-1 font-mono text-navy-900">{{ $experience->slug }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Tagline</dt><dd class="mt-1 text-navy-900">{{ $experience->tagline ?: '—' }}</dd></div>
                </dl>
                <p class="mt-5 text-xs text-navy-500">Restore the experience to edit it. While it is in the Trash, boats don't offer it and guests can't book it.</p>
            </x-admin.card>
            <x-admin.lifecycle :record="$experience" label="experience" :delete-blockers="$deleteBlockers" :routes="[
                'restore' => route('admin.experiences.restore', $experience),
                'destroy' => route('admin.experiences.destroy', $experience),
            ]" />
        </div>
    @else
        <form method="POST" action="{{ $editing ? route('admin.experiences.update', $experience) : route('admin.experiences.store') }}" enctype="multipart/form-data" class="mt-6 grid gap-6 lg:grid-cols-3">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="min-w-0 space-y-6 lg:col-span-2">
                <x-admin.card title="Details">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.input name="name" label="Name" :value="$experience->name" required maxlength="120" placeholder="e.g. Sunset Skyline Cruise" class="sm:col-span-2" />
                        <x-form.select name="category" label="Category" :options="$categories" :value="$experience->category" required />
                        <x-form.select name="icon" label="Icon" :options="$icons" :value="$experience->icon" required hint="Shown on the experience cards." />
                        <x-form.input name="slug" label="Web address" :value="$experience->slug" maxlength="80" placeholder="made from the name" hint="Lowercase letters, numbers and dashes. Leave empty to make it from the name." class="sm:col-span-2" />
                        <x-form.input name="tagline" label="Tagline" :value="$experience->tagline" maxlength="255" placeholder="e.g. Golden hour over Lake Michigan" class="sm:col-span-2" />
                        <x-form.textarea name="description" label="Description" :value="$experience->description" rows="5" maxlength="5000" class="sm:col-span-2" />
                        <x-form.textarea name="includes_text" label="What's included (one per line)" :value="implode(PHP_EOL, $experience->includes ?? [])" rows="5" maxlength="3000" placeholder="e.g. Golden-hour departure" hint="Up to 12 lines, one item per line." :error="$errors->first('includes') ?: $errors->first('includes.*')" class="sm:col-span-2" />
                    </div>
                </x-admin.card>

                <x-admin.card title="When it runs">
                    <div class="space-y-6">
                        <fieldset>
                            <legend class="text-sm font-bold text-navy-800">Departure window</legend>
                            <p class="mt-1 text-xs text-navy-500">Guests can start this experience at any of the boat's start times inside this window, Chicago time (e.g. a sunset cruise 5:00 PM – 7:30 PM, the fireworks 8:00 PM – 9:00 PM). They choose how many hours to book: the experience has no length of its own.</p>
                            <div class="mt-3 grid gap-5 sm:grid-cols-2">
                                <x-form.select name="departs_from" label="First departure" :options="$startTimes" :value="$window[0] ?? '10:00'" required />
                                <x-form.select name="departs_until" label="Last departure" :options="$startTimes" :value="$window === [] ? '18:00' : end($window)" required />
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="text-sm font-bold text-navy-800">Days</legend>
                            <p class="mt-1 text-xs text-navy-500">Tick none (or all) for every day.</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($days as $number => $label)
                                    <label class="{{ $box }}"><input type="checkbox" name="weekdays[]" value="{{ $number }}" @checked(in_array($number, $chosenDays, true)) class="h-4 w-4 accent-brass-400">{{ $label }}</label>
                                @endforeach
                            </div>
                            @if ($errors->first('weekdays') ?: $errors->first('weekdays.*'))<p data-field-error class="mt-1.5 text-xs font-semibold text-red-600">{{ $errors->first('weekdays') ?: $errors->first('weekdays.*') }}</p>@endif
                        </fieldset>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-form.input name="season_starts_on" type="date" label="Season starts (optional)" :value="$experience->season_starts_on?->toDateString()" />
                            <x-form.input name="season_ends_on" type="date" label="Season ends (optional)" :value="$experience->season_ends_on?->toDateString()" hint="Outside the season it can't be booked." />
                        </div>
                    </div>
                </x-admin.card>

                <x-admin.card title="Optional extras" id="extras" class="scroll-mt-32">
                    <p class="mb-4 text-xs text-navy-500">The experience itself is free. Tick what suits it (décor for a party, a photographer, catering…): guests who pick this experience see these first at checkout, and pay for one only if they tick it. Prices are set in <a href="{{ route('admin.addons.index') }}" class="font-bold text-navy-950 underline">Add-ons</a>, where you can also add a new one.</p>
                    @if ($addons->isEmpty())
                        <p class="text-sm text-navy-500">No add-ons yet.</p>
                    @else
                        <div class="flex flex-wrap gap-2">
                            @foreach ($addons as $addon)
                                <label class="{{ $box }}">
                                    <input type="checkbox" name="extras[]" value="{{ $addon->slug }}" @checked(in_array($addon->slug, (array) $chosenExtras, true)) class="h-4 w-4 accent-brass-400">
                                    {{ $addon->name }} · {{ money($addon->price_cents) }}{{ $addon->unit === \App\Enums\AddonUnit::PerGuest ? ' / guest' : '' }}
                                    @unless ($addon->is_active)<x-admin.pill tone="muted">Hidden</x-admin.pill>@endunless
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-3 text-xs text-navy-400">A hidden add-on is skipped until you show it again.</p>
                    @endif
                    @if ($errors->first('extras') ?: $errors->first('extras.*'))<p data-field-error class="mt-1.5 text-xs font-semibold text-red-600">{{ $errors->first('extras') ?: $errors->first('extras.*') }}</p>@endif
                </x-admin.card>

                <x-admin.card title="Search engines (optional)">
                    <div class="grid gap-5">
                        <x-form.input name="seo_title" label="Page title" :value="$experience->seo_title" maxlength="120" hint="Leave empty to use the name." />
                        <x-form.textarea name="seo_description" label="Description" :value="$experience->seo_description" rows="2" maxlength="255" hint="About 150 characters." />
                    </div>
                </x-admin.card>
            </div>

            <x-admin.form-aside>
                <x-admin.card title="Photo">
                    <x-form.file name="image" label="{{ $experience->image_path ? 'Replace the photo' : 'Photo' }}" :preview="$experience->imageUrl()" :preview-alt="$experience->name" remove-label="Remove the photo"
                        hint="JPG, PNG, WEBP or GIF up to {{ (int) config('media.max_kb') / 1024 }} MB, at least {{ config('media.image.min_width') }} × {{ config('media.image.min_height') }} px. Saved as WebP; location data is removed." />
                </x-admin.card>

                <x-admin.card title="On the site">
                    <div class="space-y-5">
                        <x-form.checkbox name="is_active" hidden-zero :checked="$experience->is_active">Show this experience<span class="block text-xs text-navy-500">Hidden experiences can't be booked.</span></x-form.checkbox>
                        <x-form.checkbox name="is_featured" hidden-zero :checked="$experience->is_featured">Feature it on the home page</x-form.checkbox>
                        <x-form.input name="sort" label="Order" type="number" :value="$experience->sort" required min="0" max="9999" hint="Lower numbers come first." />
                    </div>
                </x-admin.card>

                <x-admin.form-actions :submit="$editing ? 'Save changes' : 'Add experience'" :cancel="route('admin.experiences.index')" />

                @if ($editing)
                    <x-admin.lifecycle :record="$experience" label="experience" :trash-blockers="$trashBlockers" :routes="['trash' => route('admin.experiences.trash', $experience)]" />
                @endif
            </x-admin.form-aside>

            <x-admin.form-bar :submit="$editing ? 'Save changes' : 'Add experience'" :cancel="route('admin.experiences.index')" />
        </form>
    @endif
@endsection
