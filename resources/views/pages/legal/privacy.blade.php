@extends('layouts.app', ['title' => 'Privacy Policy', 'description' => 'What personal information Chicago Yacht Booking collects, how we use it and the choices you have.', 'page' => 'legal'])

@php
    $b = config('site.business');
    $toc = ['collect' => 'What we collect', 'use' => 'How we use it', 'share' => 'Who we share it with', 'cookies' => 'Cookies', 'keep' => 'How long we keep it', 'security' => 'Security', 'choices' => 'Your choices', 'children' => 'Children', 'contact' => 'Contact'];
@endphp

@section('content')
    <x-legal-page crumb="Privacy Policy" image="images/chicago-architecture.jpg" updated="2026-11-21" :toc="$toc"
                  intro="We collect only what we need to run your trip — and we never sell your information.">
        <x-slot:heading>Privacy <em>Policy</em></x-slot:heading>

        <h2 id="collect">What we collect</h2>
        <ul>
            <li><strong>Account and booking details:</strong> your name, email, phone number, the trips you book, guest count, occasion and any notes you add.</li>
            <li><strong>Payment details:</strong> payments are handled by Stripe. We receive only the card brand, the last four digits and the expiry date — never the full card number or security code.</li>
            <li><strong>Boat owners:</strong> listing details, boat photos, insurance documents and payout information (bank details are held by Stripe).</li>
            <li><strong>Messages and reports:</strong> what you send through the contact form, complaint reports and any photos you attach.</li>
            <li><strong>Technical data:</strong> IP address, browser type and the pages you visit, used to keep the site secure and working.</li>
        </ul>

        <h2 id="use">How we use it</h2>
        <ul>
            <li>To take bookings and payments, send confirmations, reminders and receipts, and handle cancellations and refunds.</li>
            <li>To run your trip with the captain and, for listed boats, the boat owner.</li>
            <li>To answer your messages and review complaints.</li>
            <li>To prevent fraud and keep accounts secure.</li>
            <li>To send offers — only if you opted in. You can unsubscribe at any time.</li>
        </ul>

        <h2 id="share">Who we share it with</h2>
        <ul>
            <li><strong>Captains</strong> receive trip details only (date, time, harbor, boat, guest count and our notes). They do not receive your contact details from us.</li>
            <li><strong>Boat owners</strong> of a boat you book see your first name, last initial and the trip details they need to prepare the boat.</li>
            <li><strong>Service providers</strong> that run parts of our service for us — Stripe (payments), our email provider and our hosting provider — under contracts that protect your data.</li>
            <li><strong>Authorities</strong>, when the law requires it.</li>
        </ul>
        <p>We never sell or rent your personal information.</p>

        <h2 id="cookies">Cookies</h2>
        <p>We use essential cookies to keep you logged in, protect forms against forgery and remember small choices (such as closing the promo banner). We don't use advertising cookies.</p>

        <h2 id="keep">How long we keep it</h2>
        <p>We keep account details while your account is open. Booking and payment records are kept for as long as tax and accounting law requires. Complaint photos are deleted once a report has been resolved and the related payouts are settled.</p>

        <h2 id="security">Security</h2>
        <p>Your connection to our site is encrypted, passwords are stored as one-way hashes, private documents are never publicly accessible, and access to admin tools is protected with two-factor login.</p>

        <h2 id="choices">Your choices</h2>
        <ul>
            <li>See and update your details in your account at any time.</li>
            <li>Ask us for a copy of your data, or to delete your account, by emailing <a href="mailto:{{ $b['email'] }}">{{ $b['email'] }}</a>.</li>
            <li>Unsubscribe from offers with the link in any marketing email.</li>
        </ul>

        <h2 id="children">Children</h2>
        <p>Our service is for adults. Children can join a trip booked by an adult, but we don't knowingly collect personal information from anyone under 18.</p>

        <h2 id="contact">Contact</h2>
        <p>Questions about privacy? Email <a href="mailto:{{ $b['email'] }}">{{ $b['email'] }}</a> or write to {{ $b['name'] }}, {{ $b['office'] }}.</p>
    </x-legal-page>
@endsection
