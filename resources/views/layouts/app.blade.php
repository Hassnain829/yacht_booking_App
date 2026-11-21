{{--
    Public site layout (header + main + footer).

    @extends('layouts.app', [
        'title' => 'About Us',            // browser tab: "About Us — Chicago Yacht Booking" (omit on the home page)
        'description' => '…',             // meta description (defaults to the business tagline)
        'page' => 'about',                // <body data-page> → loads resources/js/pages/about.js if it exists
        'headerSolid' => false,           // true on pages without a dark hero (checkout, owner pages)
        'mobileBar' => false,             // true when the page has a fixed bottom bar on mobile (adds footer padding)
        'mainClass' => '',                // extra classes on <main> (e.g. 'pb-28 lg:pb-0', as in the mockup)
        'robots' => 'noindex',            // optional: private pages (owner, account, checkout)
    ])
    @section('content') … @endsection
--}}
<!DOCTYPE html>
{{-- `js` is set on the server so .reveal content never flashes; noscript.css undoes it when JS is off. --}}
<html lang="en" class="js scroll-smooth">
@include('partials.head')
<body class="bg-shell font-sans text-navy-900 antialiased" data-page="{{ $page ?? '' }}">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[200] focus:rounded-full focus:bg-brass-400 focus:px-5 focus:py-3 focus:text-sm focus:font-bold focus:text-navy-950">Skip to content</a>

    @include('partials.header', ['solid' => $headerSolid ?? false])

    <main id="main" tabindex="-1" class="outline-hidden {{ $mainClass ?? '' }}">
        @yield('content')
    </main>

    @include('partials.footer', ['mobileBar' => $mobileBar ?? false])

    @stack('modals')
</body>
</html>
