{{-- Shared <head> for every layout. Expects $title?, $description?, $ogImage?, $robots? --}}
@php
    $siteName = config('site.business.name');
    $fullTitle = isset($title) ? $title.' — '.$siteName : $siteName.' — Private Yacht Charters on Lake Michigan';
    $metaDescription = $description ?? 'Book private yacht charters in Chicago. Sunset skyline cruises, Playpen swim parties, Navy Pier fireworks and river architecture tours — licensed captain included.';
@endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    @isset($robots)<meta name="robots" content="{{ $robots }}">@endisset
    <meta name="theme-color" content="#07111e">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="en_US">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset($ogImage ?? 'images/hero-poster.jpg') }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="sitemap" type="application/xml" href="{{ route('sitemap') }}">

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <noscript><link rel="stylesheet" href="{{ asset('css/noscript.css') }}"></noscript>
    @stack('head')
</head>
