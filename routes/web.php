<?php

use App\Http\Controllers\Account\ProfileController as AccountProfileController;
use App\Http\Controllers\Account\TripController as AccountTripController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FinanceController as AdminFinanceController;
use App\Http\Controllers\Admin\FleetController as AdminFleetController;
use App\Http\Controllers\Admin\PeopleController as AdminPeopleController;
use App\Http\Controllers\Admin\SystemController as AdminSystemController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ExperienceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\Owner\BoatController as OwnerBoatController;
use App\Http\Controllers\Owner\BookingController as OwnerBookingController;
use App\Http\Controllers\Owner\CalendarController as OwnerCalendarController;
use App\Http\Controllers\Owner\ComplaintController as OwnerComplaintController;
use App\Http\Controllers\Owner\DashboardController as OwnerDashboardController;
use App\Http\Controllers\Owner\EarningsController as OwnerEarningsController;
use App\Http\Controllers\Owner\PayoutController as OwnerPayoutController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\YachtController;
use App\Support\Demo\DemoCatalog;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
|
| Every public URL exists with its final name. Phase 1 pages run on demo data
| (App\Support\Demo); later phases swap the data source, not the URLs.
|
*/

Route::get('/', HomeController::class)->name('home');
Route::get('/yachts', [YachtController::class, 'index'])->name('yachts.index');
Route::get('/yachts/{slug}', [YachtController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('yachts.show');
Route::get('/experiences', [ExperienceController::class, 'index'])->name('experiences.index');
Route::get('/pricing', [PageController::class, 'pricing'])->name('pricing');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');

// Booking flow (Phase 1: demo data, no payment). Phase 4/5: holds, Stripe, /checkout/{booking:ulid}.
Route::get('/book', [BookingController::class, 'create'])->name('booking.create');
Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
Route::post('/checkout/promo', [CheckoutController::class, 'promo'])->middleware('throttle:promo')->name('checkout.promo');
Route::get('/booking/confirmation', [CheckoutController::class, 'confirmation'])->name('booking.confirmation');

// Owner screens (Phase 1: one demo owner, no login). Phase 2: auth + owner role; Phase 3/7: the database.
Route::get('/list-your-boat', [ListingController::class, 'create'])->name('list-your-boat');
Route::prefix('owner')->name('owner.')->group(function () {
    Route::get('/', OwnerDashboardController::class)->name('dashboard');
    Route::get('/boats', [OwnerBoatController::class, 'index'])->name('boats.index');
    Route::get('/bookings', [OwnerBookingController::class, 'index'])->name('bookings.index');
    Route::get('/calendar', [OwnerCalendarController::class, 'index'])->name('calendar');
    Route::get('/earnings', [OwnerEarningsController::class, 'index'])->name('earnings');
    Route::get('/payouts', [OwnerPayoutController::class, 'index'])->name('payouts.index');
    Route::get('/complaints', [OwnerComplaintController::class, 'index'])->name('complaints.index');
});

// Customer account (Phase 1: one demo customer). Phase 2: auth + verified; Phase 4: BookingPolicy (other people's trips → 404).
Route::prefix('account')->name('account.')->group(function () {
    Route::get('/', [AccountProfileController::class, 'show'])->name('profile');
    Route::get('/trips', [AccountTripController::class, 'index'])->name('trips.index');
    Route::get('/trips/{ulid}', [AccountTripController::class, 'show'])->where('ulid', '[0-9A-HJKMNP-TV-Z]{26}')->name('trips.show');
});

// Admin (Phase 1: static screens on demo data). Phase 2: auth + verified + 2FA + admin role;
// every sensitive action sits behind `password.confirm`, needs a reason and writes the audit log.
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{ref}', [AdminBookingController::class, 'show'])->where('ref', 'CYB-[A-Z0-9]{6}')->name('bookings.show');
    Route::get('/boats', [AdminFleetController::class, 'boats'])->name('boats.index');
    Route::get('/listings', [AdminFleetController::class, 'listings'])->name('listings.index');
    Route::get('/listings/{ulid}', [AdminFleetController::class, 'listing'])->where('ulid', '[0-9A-HJKMNP-TV-Z]{26}')->name('listings.show');
    Route::get('/captains', [AdminPeopleController::class, 'captains'])->name('captains.index');
    Route::get('/users', [AdminPeopleController::class, 'users'])->name('users.index');
    Route::get('/inbox', [AdminPeopleController::class, 'inbox'])->name('inbox.index');
    Route::get('/payouts', [AdminFinanceController::class, 'payouts'])->name('payouts.index');
    Route::get('/payments', [AdminFinanceController::class, 'payments'])->name('payments.index');
    Route::get('/complaints', [AdminFinanceController::class, 'complaints'])->name('complaints.index');
    Route::get('/catalog', [AdminSystemController::class, 'catalog'])->name('catalog');
    Route::get('/settings', [AdminSystemController::class, 'settings'])->name('settings');
    Route::get('/audit-log', [AdminSystemController::class, 'audit'])->name('audit.index');
});

// Legal pages (Phase 10: editable in the admin CMS).
Route::view('/terms', 'pages.legal.terms')->name('legal.terms');
Route::view('/privacy', 'pages.legal.privacy')->name('legal.privacy');
Route::view('/cancellation-policy', 'pages.legal.cancellation')->name('legal.cancellation');

/*
|--------------------------------------------------------------------------
| Auth screens — PHASE 1 PREVIEW ONLY (GET, no logic)
|--------------------------------------------------------------------------
|
| Same URLs and names Fortify registers in Phase 2. Then these lines are deleted and
| FortifyServiceProvider points Fortify::loginView() etc. at the same Blade views.
|
*/
Route::view('/login', 'auth.login')->name('login');
Route::view('/register', 'auth.register')->name('register');
Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');
Route::view('/reset-password/{token}', 'auth.reset-password')->where('token', '[A-Za-z0-9]{1,100}')->name('password.reset');
Route::view('/email/verify', 'auth.verify-email')->name('verification.notice');
Route::view('/two-factor-challenge', 'auth.two-factor-challenge')->name('two-factor.login');
Route::view('/user/confirm-password', 'auth.confirm-password')->name('password.confirm');

/*
|--------------------------------------------------------------------------
| Local-only design check pages (never registered in production)
|--------------------------------------------------------------------------
*/
if (app()->isLocal()) {
    Route::view('/dev/theme', 'dev.theme')->name('dev.theme');
    Route::get('/dev/components', fn (DemoCatalog $catalog) => view('dev.components', [
        'yachts' => $catalog->yachts(),
    ]))->name('dev.components');
}
