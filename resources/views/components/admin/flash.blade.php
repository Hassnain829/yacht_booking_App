{{-- The message after an admin action, shown at the top of the page: session `status` (done) or `error` (it didn't work, e.g. a refund the provider refused). --}}
@if (session('status'))
    <div {{ $attributes->class('mb-6 flex items-start gap-3 rounded-2xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 ring-1 ring-emerald-200') }} role="status">
        <i data-lucide="circle-check" class="mt-0.5 h-4 w-4 shrink-0"></i>
        <p>{{ session('status') }}</p>
    </div>
@endif
@if (session('error'))
    <div {{ $attributes->class('mb-6 flex items-start gap-3 rounded-2xl bg-red-50 p-4 text-sm font-semibold text-red-700 ring-1 ring-red-200') }} role="alert">
        <i data-lucide="circle-alert" class="mt-0.5 h-4 w-4 shrink-0"></i>
        <p>{{ session('error') }}</p>
    </div>
@endif
