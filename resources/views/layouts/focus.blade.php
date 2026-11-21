{{--
    Focus layout: a slim header and no footer, for full-screen flows (List Your Boat wizard).
    Same params as layouts.app (title, description, page, robots); the header comes from @section('header').
--}}
<!DOCTYPE html>
<html lang="en" class="js scroll-smooth">
@include('partials.head')
<body class="{{ $bodyClass ?? 'bg-white' }} font-sans text-navy-900 antialiased" data-page="{{ $page ?? '' }}">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[200] focus:rounded-full focus:bg-brass-400 focus:px-5 focus:py-3 focus:text-sm focus:font-bold focus:text-navy-950">Skip to content</a>

    @yield('header')

    <main id="main" tabindex="-1" class="outline-hidden {{ $mainClass ?? '' }}">
        @yield('content')
    </main>

    @stack('modals')
</body>
</html>
