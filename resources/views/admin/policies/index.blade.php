@extends('layouts.admin', ['title' => 'Cancellation policies', 'active' => 'policies'])

@section('content')
    <x-admin.page-header title="Cancellation policies" description="How much guests get back when they cancel, by how many hours before the trip. Each boat uses one policy; a booking keeps the rules it was made with." />

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        @foreach ($policies as $policy)
            <x-admin.card :title="$policy->name">
                <x-slot:actions>
                    @if ($policy->badge)<x-admin.pill tone="brass">{{ $policy->badge }}</x-admin.pill>@endif
                    @unless ($policy->is_active)<x-admin.pill tone="muted">Not offered</x-admin.pill>@endunless
                </x-slot:actions>
                <ul class="space-y-2 text-sm">
                    @foreach ($policy->rules as $rule)
                        <li class="flex items-center justify-between gap-3 rounded-xl bg-navy-50 px-3 py-2"><span class="text-navy-600">{{ $rule['min_hours_before'] > 0 ? $rule['min_hours_before'].'+ hours before' : ($loop->first ? 'Any time' : 'Under '.$policy->rules[$loop->index - 1]['min_hours_before'].' hours before') }}</span><span class="font-bold text-navy-950">{{ $rule['refund_pct'] }}% back</span></li>
                    @endforeach
                </ul>
                <p class="mt-4 text-xs text-navy-500">{{ trans_choice('{0} No boat uses it.|{1} Used by 1 boat.|[2,*] Used by :count boats.', $policy->boats_count) }}</p>
                <x-button :href="route('admin.policies.edit', $policy)" variant="outline" class="mt-4" icon-left="pencil">Edit</x-button>
            </x-admin.card>
        @endforeach
    </div>
@endsection
