@extends('layouts.admin', ['title' => 'Experiences & add-ons', 'active' => 'catalog'])

@section('content')
    <x-admin.page-header title="Experiences & add-ons" description="What guests can book, the extras they can add, promo codes and departure harbors.">
        <x-slot:actions><x-button variant="dark" data-soon icon-left="plus">New experience</x-button></x-slot:actions>
    </x-admin.page-header>

    <x-admin.card title="Experiences" flush class="mt-6">
        <x-admin.table>
            <x-slot:head><th scope="col">Experience</th><th scope="col">Durations</th><th scope="col">Start times</th><th scope="col">Days</th><th scope="col" class="text-right">Package fee</th></x-slot:head>
            @foreach ($experiences as $e)
                <tr>
                    <td><div class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-brass-100 text-brass-700"><i data-lucide="{{ $e->icon }}" class="h-4 w-4"></i></span><span class="font-bold text-navy-950">{{ $e->name }}</span></div></td>
                    <td>{{ implode(', ', $e->durations) }} hrs</td>
                    <td class="text-xs">{{ implode(' · ', array_map(fn ($t) => \Carbon\CarbonImmutable::createFromFormat('H:i', $t)->format('g:i A'), $e->time_slots)) }}</td>
                    <td>{{ $e->allowed_weekdays ? implode(', ', array_map(fn ($d) => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$d], $e->allowed_weekdays)) : 'Every day' }}</td>
                    <td class="text-right font-semibold text-navy-950">{{ $e->package_fee_cents ? money($e->package_fee_cents, 'never') : '—' }}</td>
                </tr>
            @endforeach
        </x-admin.table>
    </x-admin.card>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <x-admin.card title="Add-ons" flush>
            <x-admin.table min="min-w-[32rem]">
                <x-slot:head><th scope="col">Add-on</th><th scope="col">Price</th></x-slot:head>
                @foreach ($addons as $a)
                    <tr>
                        <td><span class="font-bold text-navy-950">{{ $a->name }}</span><span class="block text-xs text-navy-400">{{ $a->description }}</span></td>
                        <td class="whitespace-nowrap font-semibold text-navy-950">{{ money($a->price_cents, 'never') }}{{ $a->unit === 'per_guest' ? ' / guest' : '' }}</td>
                    </tr>
                @endforeach
            </x-admin.table>
        </x-admin.card>
        <div class="min-w-0 space-y-6">
            <x-admin.card title="Promo codes" flush>
                <x-admin.table min="min-w-[28rem]">
                    <x-slot:head><th scope="col">Code</th><th scope="col">Discount</th><th scope="col">Applies to</th></x-slot:head>
                    @foreach ($promos as $promo)
                        <tr><td class="font-mono font-bold text-navy-950">{{ $promo['code'] }}</td><td>{{ $promo['label'] }}</td><td>{{ ucfirst($promo['applies_to']) }}</td></tr>
                    @endforeach
                </x-admin.table>
            </x-admin.card>
            <x-admin.card title="Harbors">
                <ul class="space-y-3">
                    @foreach ($harbors as $h)
                        <li class="flex items-start gap-3"><i data-lucide="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-brass-500"></i><span class="text-sm"><span class="font-bold text-navy-950">{{ $h->name }}</span><span class="block text-xs text-navy-500">{{ $h->area }} · {{ $h->notes }}</span></span></li>
                    @endforeach
                </ul>
            </x-admin.card>
        </div>
    </div>
@endsection
