@extends('layouts.admin', ['title' => 'Amenities', 'active' => 'amenities'])

@section('content')
    <x-admin.page-header title="Amenities" description="The lists boats pick from in the boat form and the owner wizard. Highlights are the lines shown on a boat's page.">
        <x-slot:actions>
            <x-button :href="route('admin.amenities.create')" variant="dark" icon-left="plus">Add an amenity</x-button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        @foreach ($labels as $group => $label)
            <x-admin.card :title="$label" flush>
                <x-slot:actions><a href="{{ route('admin.amenities.create', ['group' => $group]) }}" class="inline-flex items-center gap-1 text-sm font-bold text-navy-600 hover:text-navy-950"><i data-lucide="plus" class="h-4 w-4"></i>Add</a></x-slot:actions>
                @if (($groups[$group] ?? collect())->isEmpty())
                    <p class="p-5 text-sm text-navy-500">Nothing in this list yet.</p>
                @else
                    <ul class="divide-y divide-navy-100">
                        @foreach ($groups[$group] as $amenity)
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-navy-50 text-navy-500"><i data-lucide="{{ $amenity->icon ?: 'check' }}" class="h-4 w-4"></i></span>
                                <a href="{{ route('admin.amenities.edit', $amenity) }}" class="min-w-0 flex-1 truncate text-sm font-semibold text-navy-950 hover:underline">{{ $amenity->name }}</a>
                                <span class="text-xs text-navy-400">{{ trans_choice('{0} no boats|{1} 1 boat|[2,*] :count boats', $amenity->boats_count) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-admin.card>
        @endforeach
    </div>
@endsection
