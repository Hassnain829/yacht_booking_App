@extends('layouts.app', ['title' => 'Cancellation Policy', 'description' => 'How cancellations and refunds work for yacht charters booked with Chicago Yacht Booking: Flexible, Moderate and Strict policies, weather cancellations and owner cancellations.', 'page' => 'legal'])

@php
    $b = config('site.business');
    $policies = collect(config('listing.cancellation'))->keyBy('id');
    $pricing = config('demo.pricing');
    $toc = ['overview' => 'Overview', 'policies' => 'The three policies', 'how' => 'How your refund is calculated', 'weather' => 'Weather and safety', 'owner' => 'If the boat owner cancels', 'changes' => 'Changing your date', 'how-to' => 'How to cancel', 'timing' => 'When the money arrives'];
@endphp

@section('content')
    <x-legal-page crumb="Cancellation Policy" image="images/chicago-river-boats.jpg" updated="2026-11-21" :toc="$toc"
                  intro="Plans change. Here's exactly what you get back, and when — no surprises.">
        <x-slot:heading>Cancellation <em>Policy</em></x-slot:heading>

        <h2 id="overview">Overview</h2>
        <p>Every boat on {{ $b['name'] }} uses one of three cancellation policies: <strong>Flexible</strong>, <strong>Moderate</strong> or <strong>Strict</strong>. You see the boat's policy before you pay, on your confirmation email and on your trip page. The policy that applies is the one shown when you booked, even if the boat changes its policy later.</p>
        <p>Your refund depends on how many hours are left between the moment you cancel and the start of your trip (Chicago time). Crew gratuity is always refunded in full.</p>

        <h2 id="policies">The three policies</h2>
        <table>
            <thead><tr><th scope="col">Policy</th><th scope="col">Full refund</th><th scope="col">50% refund</th><th scope="col">No refund</th></tr></thead>
            <tbody>
                <tr><th scope="row">Flexible</th><td>24 hours or more before the start</td><td>—</td><td>Less than 24 hours before</td></tr>
                <tr><th scope="row">Moderate</th><td>5 days (120 hours) or more before</td><td>3 to 5 days before</td><td>Less than 3 days before</td></tr>
                <tr><th scope="row">Strict</th><td>30 days (720 hours) or more before</td><td>15 to 30 days before</td><td>Less than 15 days before</td></tr>
            </tbody>
        </table>
        <ul>
            @foreach ($policies as $policy)
                <li><strong>{{ $policy['name'] }}:</strong> {{ implode(' ', $policy['points']) }}</li>
            @endforeach
        </ul>

        <h2 id="how">How your refund is calculated</h2>
        <p>Every trip is paid in full at checkout. When you cancel:</p>
        <ul>
            <li>We work out how much of the trip price comes back to you under the boat's policy (for example 100% or 50%). Gratuity is always refunded in full.</li>
            <li>The refund goes back to the card you paid with.</li>
        </ul>
        <p><strong>Example:</strong> a trip costs $1,000 and you paid the $1,000 at checkout. If you cancel in the 50% window, $500 comes back to you. If you cancel in the full-refund window, all $1,000 comes back.</p>

        <h2 id="weather">Weather and safety</h2>
        <p>Your captain has the final say on safety. If the captain or our team cancels a trip because of weather, water conditions or any other safety reason, you choose between a <strong>full refund</strong> of everything you paid or a <strong>free new date</strong>. We'll contact you as early as possible — usually the day before.</p>
        <p>A light rain or a cloudy sky alone is not a safety cancellation. If you decide not to sail in conditions the captain considers safe, the normal policy applies.</p>

        <h2 id="owner">If the boat owner cancels</h2>
        <p>If a boat owner cancels a confirmed trip, you get a <strong>full refund</strong> of everything you paid, whatever the policy. Our team will also try to find you a similar boat for the same date.</p>

        <h2 id="changes">Changing your date</h2>
        <p>Date changes are free up to 14 days before your charter, subject to availability. Contact us and we'll move your booking; any price difference is shown before you confirm.</p>

        <h2 id="how-to">How to cancel</h2>
        <p>Open <strong>My trips</strong> in your account, choose the trip and select <strong>Cancel trip</strong>. You'll see the exact refund before you confirm. You can also call us at <a href="tel:{{ $b['phone_href'] }}">{{ $b['phone'] }}</a> or email <a href="mailto:{{ $b['email'] }}">{{ $b['email'] }}</a>.</p>

        <h2 id="timing">When the money arrives</h2>
        <p>Refunds go back to the card you paid with. We send them the same day; your bank usually shows them within 5–10 business days.</p>
    </x-legal-page>
@endsection
