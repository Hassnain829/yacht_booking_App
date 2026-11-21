{{--
    Error pages (Laravel picks resources/views/errors/{status}.blade.php automatically).
    Deliberately simple: no view composers, no database — so a broken page can still show this.
    Never shows exception details (APP_DEBUG=false in production).
--}}
@php
    $title = trim($__env->yieldContent('title'));
@endphp
<!DOCTYPE html>
<html lang="en" class="js">
@include('partials.head', ['title' => $title, 'robots' => 'noindex'])
<body class="bg-navy-950 font-sans text-white antialiased" data-page="error">
    <main id="main" class="relative isolate grid min-h-svh place-items-center overflow-hidden px-5 py-24">
        <img src="{{ asset('images/wake.jpg') }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover opacity-25">
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-navy-950/70 via-navy-950/80 to-navy-950"></div>
        <div class="absolute inset-x-0 top-0 flex h-20 items-center justify-center">
            <x-logo size="sm" />
        </div>
        <div class="w-full max-w-xl text-center">
            <p class="font-display text-7xl text-brass-300 md:text-8xl">@yield('code')</p>
            <h1 class="mt-4 font-display text-4xl md:text-5xl md:leading-none">@yield('heading')</h1>
            <p class="mx-auto mt-5 max-w-md leading-relaxed text-white/70">@yield('message')</p>
            <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                @section('actions')
                    <x-button :href="route('home')" size="xl" icon="arrow-right">Back to home</x-button>
                    <x-button :href="route('contact')" variant="light" size="xl">Contact us</x-button>
                @show
            </div>
            <p class="mt-10 text-xs text-white/40">{{ config('site.business.phone') }} · {{ config('site.business.email') }}</p>
        </div>
    </main>
</body>
</html>
