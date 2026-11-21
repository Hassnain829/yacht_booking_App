{{--
    One listing in "My boats" (mockup my-boats.js). The same markup is cloned from <template> by
    pages/owner-boats.js for drafts saved on this device; `data-f` marks the fields it fills (textContent only).
--}}
@php
    $isDraft = $boat->status === 'draft';
    $editUrl = route('list-your-boat', array_filter(['listing' => $boat->ulid, 'step' => $isDraft ? null : 'review']));
@endphp
<article class="flex flex-col gap-5 rounded-[1.75rem] bg-white p-4 shadow-card ring-1 ring-navy-100 sm:flex-row sm:p-5" data-boat="{{ $boat->ulid }}">
    <div class="relative aspect-[16/10] w-full shrink-0 overflow-hidden rounded-2xl bg-navy-50 sm:aspect-auto sm:h-36 sm:w-56">
        @if ($boat->cover)
            <img src="{{ asset($boat->cover) }}" alt="" loading="lazy" class="h-full w-full object-cover" data-f="cover">
        @else
            <div class="grid h-full place-items-center text-navy-300" data-f="no-cover"><i data-lucide="image-off" class="h-8 w-8"></i></div>
        @endif
        <span class="absolute bottom-2 left-2 rounded-md bg-navy-950/75 px-2 py-0.5 text-[10px] font-bold text-white backdrop-blur" data-f="photos">{{ $boat->photos_count }} {{ Str::plural('photo', $boat->photos_count) }}</span>
    </div>
    <div class="flex min-w-0 flex-1 flex-col">
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-full px-2.5 py-1 text-[11px] font-extrabold uppercase tracking-wider {{ $boat->status_class }}" data-f="status">{{ $boat->status_label }}</span>
            <span class="text-xs text-navy-400" data-f="updated">Updated {{ $boat->updated_at->format('M j, Y') }} · ID {{ substr($boat->ulid, -8) }}</span>
        </div>
        <h2 class="mt-2 truncate font-display text-2xl text-navy-950" data-f="title">{{ $boat->display_title }}</h2>
        <p class="mt-1 truncate text-sm text-navy-500" data-f="meta">{{ $boat->meta ?: 'Details not added yet' }}</p>
        @if ($isDraft)
            <p class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-navy-600"><i data-lucide="list-checks" class="h-4 w-4 text-brass-500"></i><span data-f="next">Next up: {{ $boat->next_up }}</span></p>
        @elseif ($boat->status === 'pending')
            <p class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-navy-600"><i data-lucide="clock" class="h-4 w-4 text-brass-500"></i>Our team reviews new listings within 2 business days</p>
        @elseif ($boat->status === 'approved')
            <p class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-navy-600">
                @if ($boat->booking_enabled)
                    <i data-lucide="circle-check" class="h-4 w-4 text-emerald-600"></i>Live on the site · taking bookings
                @else
                    <i data-lucide="eye-off" class="h-4 w-4 text-navy-400"></i>Approved · not taking bookings right now
                @endif
            </p>
        @endif
        <div class="mt-4 flex flex-wrap items-center justify-end gap-2 sm:mt-auto sm:pt-4">
            @if ($boat->status === 'approved')
                <a href="{{ route('owner.calendar', ['boat' => $boat->ulid]) }}" class="rounded-full px-4 py-2.5 text-xs font-bold text-navy-600 hover:bg-navy-50 hover:text-navy-950">Calendar</a>
            @endif
            <button type="button" data-delete class="rounded-full px-4 py-2.5 text-xs font-bold text-navy-400 hover:bg-red-50 hover:text-red-600">Delete</button>
            <x-button :href="$editUrl" class="uppercase tracking-wider" data-f="edit">{{ $isDraft ? 'Continue listing' : 'Edit listing' }}</x-button>
        </div>
    </div>
</article>
