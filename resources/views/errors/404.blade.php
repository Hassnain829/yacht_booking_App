@extends('errors.layout')

@section('title', 'Page not found')
@section('code', '404')
@section('heading', "We couldn't find that page")
@section('message', 'The link may be old, or the page has moved. Our fleet and experiences are just a click away.')

@section('actions')
    <x-button :href="route('yachts.index')" size="xl" icon="arrow-right">See our yachts</x-button>
    <x-button :href="route('home')" variant="light" size="xl">Back to home</x-button>
@endsection
