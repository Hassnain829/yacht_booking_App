@extends('layouts.app', ['title' => 'Terms of Service', 'description' => 'The terms for booking yacht charters and listing boats on Chicago Yacht Booking.', 'page' => 'legal'])

@php
    $b = config('site.business');
    $pricing = config('demo.pricing');
    $toc = ['about' => 'About us', 'accounts' => 'Your account', 'bookings' => 'Bookings and payment', 'cancellations' => 'Cancellations and refunds', 'on-board' => 'On board', 'problems' => 'If something goes wrong', 'owners' => 'Boat owners', 'liability' => 'Liability', 'changes' => 'Changes to these terms', 'contact' => 'Contact'];
@endphp

@section('content')
    <x-legal-page crumb="Terms of Service" image="images/chicago-navy-pier.jpg" updated="2026-11-21" :toc="$toc"
                  intro="The rules for booking a charter or listing your boat with us — in plain English.">
        <x-slot:heading>Terms of <em>Service</em></x-slot:heading>

        <h2 id="about">About us</h2>
        <p>{{ $b['name'] }} ("we", "us") runs {{ $b['domain'] }}, a booking service for private yacht charters in Chicago. Some boats belong to our own fleet; others are listed by independent boat owners. Every charter is run by a licensed USCG captain. By using the site or making a booking, you agree to these terms.</p>

        <h2 id="accounts">Your account</h2>
        <ul>
            <li>You must be at least 18 to create an account or book a trip, and the details you give us must be true and up to date.</li>
            <li>Keep your password private. You are responsible for bookings made from your account. Tell us straight away if you think someone else has used it.</li>
            <li>We may suspend an account that is used for fraud, abuse or anything that puts guests, crews or boats at risk.</li>
        </ul>

        <h2 id="bookings">Bookings and payment</h2>
        <ul>
            <li>A booking is confirmed once payment succeeds and you receive a confirmation email with a booking reference.</li>
            <li>The price you see at checkout includes the yacht, the licensed captain (shown as its own line), any paid extras of the experience you chose, your add-ons and taxes. Crew gratuity is optional and goes to the crew in full.</li>
            <li>The full price is paid at checkout, and your booking is confirmed straight away. Nothing more is charged before the trip.</li>
            <li>Card payments are processed by Stripe. We never see or store your full card number.</li>
            <li>The guest count you book is the maximum on board. It can never exceed the boat's USCG capacity.</li>
        </ul>

        <h2 id="cancellations">Cancellations and refunds</h2>
        <p>Each boat has a Flexible, Moderate or Strict policy. The full rules — including weather cancellations and what happens if an owner cancels — are in our <a href="{{ route('legal.cancellation') }}">Cancellation Policy</a>.</p>

        <h2 id="on-board">On board</h2>
        <ul>
            <li>The captain is in charge of the boat and has the final say on safety, routes and conditions. Please follow the captain's and crew's instructions.</li>
            <li>Guests drinking alcohol must be 21 or older. No glass bottles, illegal substances or anything that could harm people or the boat.</li>
            <li>Please arrive 20 minutes early. Trips start and end at the booked times; a late arrival does not extend the trip.</li>
            <li>The person who booked is responsible for their group, and for damage the group causes beyond normal wear.</li>
        </ul>

        <h2 id="problems">If something goes wrong</h2>
        <p>If something about the boat or the trip wasn't right, report it from your trip page within 72 hours after the trip ends. Our team reviews every report, may contact the boat owner, and will let you know the outcome. Where appropriate we offer a partial or full refund.</p>

        <h2 id="owners">Boat owners</h2>
        <ul>
            <li>Owners list boats through our listing process. Every listing is reviewed by our team before it goes live, and owners must keep valid insurance for every trip.</li>
            <li>Owners set their prices; we may suggest changes for the Chicago market before approval. We keep a commission on the charter price, shown to the owner before they submit a listing.</li>
            <li>Earnings become available 72 hours after each trip. If a guest reports a problem in that time, the trip's earnings are held until our team has reviewed it, and a deduction may apply.</li>
            <li>Owners must honour confirmed bookings. If an owner cancels, the guest gets a full refund and we may deduct a penalty from the owner's earnings.</li>
        </ul>

        <h2 id="liability">Liability</h2>
        <p>Boating has natural risks. We take care to work only with licensed captains and insured boats, but to the extent the law allows, we are not responsible for indirect losses, or for delays and cancellations caused by weather, government action or other events outside our control. Nothing in these terms limits liability that cannot be limited by law.</p>
        <p>These terms are governed by the laws of the State of Illinois.</p>

        <h2 id="changes">Changes to these terms</h2>
        <p>We may update these terms. The version shown when you booked applies to that booking. We'll tell you about important changes by email.</p>

        <h2 id="contact">Contact</h2>
        <p>{{ $b['name'] }} · {{ $b['office'] }} · <a href="mailto:{{ $b['email'] }}">{{ $b['email'] }}</a> · <a href="tel:{{ $b['phone_href'] }}">{{ $b['phone'] }}</a></p>
    </x-legal-page>
@endsection
