<?php

use App\Http\Controllers\Account\ProfileController as AccountProfileController;
use App\Http\Controllers\Account\SessionController as AccountSessionController;
use App\Http\Controllers\Account\TripController as AccountTripController;
use App\Http\Controllers\Account\TwoFactorController as AccountTwoFactorController;
use App\Http\Controllers\Admin\AddonController as AdminAddonController;
use App\Http\Controllers\Admin\AmenityController as AdminAmenityController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\BoatController as AdminBoatController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\CancellationPolicyController as AdminPolicyController;
use App\Http\Controllers\Admin\CaptainController as AdminCaptainController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ExperienceController as AdminExperienceController;
use App\Http\Controllers\Admin\FinanceController as AdminFinanceController;
use App\Http\Controllers\Admin\FleetController as AdminFleetController;
use App\Http\Controllers\Admin\HarborController as AdminHarborController;
use App\Http\Controllers\Admin\PeopleController as AdminPeopleController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\SystemController as AdminSystemController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\BookingCardController;
use App\Http\Controllers\BookingConfirmationController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ExperienceController;
use App\Http\Controllers\FileController;
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
use App\Http\Controllers\SeoController;
use App\Http\Controllers\StripeWebhookController;
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
// The boat page's booking card (4.3, D21): read-only JSON from AvailabilityService and QuoteService.
Route::get('/yachts/{slug}/availability', [BookingCardController::class, 'availability'])->where('slug', '[a-z0-9-]+')
    ->middleware('throttle:availability')->name('yachts.availability');
Route::get('/yachts/{slug}/quote', [BookingCardController::class, 'quote'])->where('slug', '[a-z0-9-]+')
    ->middleware('throttle:quote')->name('yachts.quote');
Route::get('/experiences', [ExperienceController::class, 'index'])->name('experiences.index');
Route::get('/pricing', [PageController::class, 'pricing'])->name('pricing');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');

// Search engines (3.6): the sitemap follows the live catalog; robots.txt blocks everything outside production.
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

// Booking flow. Since 4.3 a trip is picked on the boat page (D21): the old /book wizard URL only redirects there
// (or to the fleet when no boat is given).
Route::get('/book', BookingController::class)->name('booking.create');

// Checkout (4.5 – 4.6, D22: paid in full at checkout). A guest signs in on the start page; "Book now" holds the
// time (the double-booking lock) while they pay. Only their own booking (BookingPolicy: else 404). The booking is
// confirmed on the provider's word: the webhook, or the server asking it — never the browser.
Route::get('/checkout', [CheckoutController::class, 'start'])->name('checkout.start');
Route::middleware(['auth', 'active'])->group(function () {
    $booking = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';
    Route::post('/checkout', [CheckoutController::class, 'hold'])->middleware(['throttle:hold', 'idempotent'])->name('checkout.hold');
    Route::controller(CheckoutController::class)->prefix('checkout/{booking}')->name('checkout.')->where(['booking' => $booking])->group(function () {
        Route::get('/', 'show')->name('show');
        Route::get('/price', 'price')->middleware('throttle:quote')->name('price');
        Route::post('/promo', 'applyPromo')->middleware('throttle:promo')->name('promo');
        Route::delete('/promo', 'removePromo')->middleware('throttle:checkout')->name('promo.remove');
        Route::post('/pay', 'pay')->middleware(['throttle:payment', 'idempotent'])->name('pay');
        Route::get('/complete', 'complete')->name('complete');
        Route::get('/status', 'status')->middleware('throttle:payment-status')->name('status');
        Route::post('/test-card', 'testCard')->middleware(['throttle:payment', 'idempotent'])->name('test-card'); // fake gateway only (404 otherwise)
    });
    Route::get('/bookings/{booking}/confirmation', [BookingConfirmationController::class, 'show'])->where('booking', $booking)->name('bookings.confirmation');
    Route::get('/bookings/{booking}/calendar.ics', [BookingConfirmationController::class, 'calendar'])->where('booking', $booking)->middleware('throttle:files')->name('bookings.calendar');
});
Route::post('/stripe/webhook', StripeWebhookController::class)->middleware('throttle:webhooks')->name('stripe.webhook');

// Owner screens — signed-in, active, verified (PLAN §4.5). Page content is still demo data until Phase 6/7.
Route::get('/list-your-boat', [ListingController::class, 'create'])
    ->middleware(['auth', 'active', 'verified', 'can:list-boats'])->name('list-your-boat');
Route::prefix('owner')->name('owner.')->middleware(['auth', 'active', 'verified', 'can:access-owner-area'])->group(function () {
    Route::get('/', OwnerDashboardController::class)->name('dashboard');
    Route::get('/boats', [OwnerBoatController::class, 'index'])->name('boats.index');
    Route::get('/bookings', [OwnerBookingController::class, 'index'])->name('bookings.index');
    Route::get('/calendar', [OwnerCalendarController::class, 'index'])->name('calendar');
    Route::get('/earnings', [OwnerEarningsController::class, 'index'])->name('earnings');
    Route::get('/payouts', [OwnerPayoutController::class, 'index'])->name('payouts.index');
    Route::get('/complaints', [OwnerComplaintController::class, 'index'])->name('complaints.index');
});

// Customer account — signed-in and active. Content is demo data until Phase 4 (BookingPolicy: other people's trips → 404).
Route::prefix('account')->name('account.')->middleware(['auth', 'active'])->group(function () {
    Route::get('/', [AccountProfileController::class, 'show'])->name('profile');
    Route::delete('/', [AccountProfileController::class, 'destroy'])->middleware('throttle:account-security')->name('destroy');
    Route::get('/trips', [AccountTripController::class, 'index'])->name('trips.index');
    Route::get('/trips/{ulid}', [AccountTripController::class, 'show'])->where('ulid', '[0-9A-HJKMNP-TV-Z]{26}')->name('trips.show');

    // Security (PLAN §8.1). The 2FA page shows the setup key and recovery codes, so it needs a recent
    // password confirmation; its buttons post to Fortify's /user/two-factor-* routes.
    Route::get('/two-factor', [AccountTwoFactorController::class, 'show'])->middleware('password.confirm')->name('two-factor');
    Route::delete('/sessions', [AccountSessionController::class, 'destroyOthers'])
        ->middleware(['password.confirm', 'throttle:account-security'])->name('sessions.destroy-others');
});

// Private files (insurance, licenses, complaint photos — PLAN §9): only after MediaPolicy allows it, else 404.
Route::get('/files/{media:uuid}', FileController::class)->whereUuid('media')
    ->middleware(['auth', 'active', 'throttle:files'])->name('files.show');

// Admin — signed-in, active, verified, admin role, 30-minute idle logout (PLAN §4.5). Two-factor login is the
// admin's choice (Settings → Security; user decision 2026-11-22), so no `2fa` here.
// Saving forms: `throttle:admin`. Sensitive actions (Trash / restore / delete permanently, settings, account
// status): a reason + the password in the dialog (SensitiveActionRequest), `throttle:admin-sensitive`, audit log.
// Bookings, listings, inbox and finance screens still show demo data until Phases 4–9.
Route::prefix('admin')->name('admin.')->middleware(['auth', 'active', 'verified', 'can:access-admin', 'admin.idle'])->group(function () {
    /**
     * The standard admin record routes (PLAN 3.4): list, add, edit, Trash, restore, delete permanently.
     * `{param}` is bound by its route key (slug or ulid); the Trash routes also find records in the Trash.
     */
    $records = function (string $prefix, string $param, string $controller, string $pattern) {
        Route::controller($controller)->prefix($prefix)->name($prefix.'.')->where([$param => $pattern])->group(function () use ($param) {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->middleware('throttle:admin')->name('store');
            Route::get("/{{$param}}/edit", 'edit')->withTrashed()->name('edit');
            Route::put("/{{$param}}", 'update')->middleware('throttle:admin')->name('update');
            Route::delete("/{{$param}}", 'trash')->middleware('throttle:admin-sensitive')->name('trash');
            Route::post("/{{$param}}/restore", 'restore')->withTrashed()->middleware('throttle:admin-sensitive')->name('restore');
            Route::delete("/{{$param}}/permanent", 'destroy')->withTrashed()->middleware('throttle:admin-sensitive')->name('destroy');
        });
    };
    $slug = '[a-z0-9]+(?:-[a-z0-9]+)*';
    $ulid = '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}';

    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{ref}', [AdminBookingController::class, 'show'])->where('ref', 'CYB-[A-Z0-9]{6}')->name('bookings.show');

    // Fleet (3.4.1, 3.4.3–3.4.5)
    $records('boats', 'boat', AdminBoatController::class, $ulid);
    Route::controller(AdminBoatController::class)->prefix('boats/{boat}')->name('boats.')->where(['boat' => $ulid])->scopeBindings()->group(function () use ($ulid) {
        Route::post('/status', 'status')->middleware('throttle:admin')->name('status');
        Route::patch('/bookable', 'bookable')->middleware('throttle:admin')->name('bookable');
        Route::post('/photos', 'storePhotos')->middleware('throttle:uploads')->name('photos.store');
        Route::patch('/photos/{photo}', 'updatePhoto')->whereUuid('photo')->middleware('throttle:admin')->name('photos.update');
        Route::delete('/photos/{photo}', 'destroyPhoto')->whereUuid('photo')->middleware('throttle:admin')->name('photos.destroy');
        Route::post('/blocks', 'storeBlock')->middleware('throttle:admin')->name('blocks.store');
        Route::delete('/blocks/{block}', 'destroyBlock')->where('block', $ulid)->middleware('throttle:admin')->name('blocks.destroy');
    });
    $records('harbors', 'harbor', AdminHarborController::class, $slug);
    Route::controller(AdminAmenityController::class)->prefix('amenities')->name('amenities.')->where(['amenity' => $ulid])->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->middleware('throttle:admin')->name('store');
        Route::get('/{amenity}/edit', 'edit')->name('edit');
        Route::put('/{amenity}', 'update')->middleware('throttle:admin')->name('update');
        Route::delete('/{amenity}', 'destroy')->middleware('throttle:admin-sensitive')->name('destroy');
    });
    Route::get('/listings', [AdminFleetController::class, 'listings'])->name('listings.index');
    Route::get('/listings/{ulid}', [AdminFleetController::class, 'listing'])->where('ulid', '[0-9A-HJKMNP-TV-Z]{26}')->name('listings.show');

    // Catalog (3.4.2, 3.4.5). Promo codes: demo screen until 4.7.
    $records('experiences', 'experience', AdminExperienceController::class, $slug);
    $records('addons', 'addon', AdminAddonController::class, $slug);
    Route::get('/promo-codes', [AdminSystemController::class, 'promos'])->name('promos.index');
    Route::controller(AdminPolicyController::class)->prefix('cancellation-policies')->name('policies.')->where(['policy' => $slug])->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{policy}/edit', 'edit')->name('edit');
        Route::put('/{policy}', 'update')->middleware('throttle:admin-sensitive')->name('update');
    });

    // Captains (3.4.6): profiles. Accounts, applications and trip offers: Phase 8.
    $records('captains', 'captain', AdminCaptainController::class, $ulid);
    Route::controller(AdminCaptainController::class)->prefix('captains/{captain}/documents')->name('captains.documents.')->where(['captain' => $ulid])->group(function () {
        Route::post('/', 'storeDocument')->middleware('throttle:uploads')->name('store');
        Route::delete('/{document}', 'destroyDocument')->whereUuid('document')->middleware('throttle:admin')->name('destroy');
    });
    // Users (3.4.7): status and two-factor reset take a reason + the password.
    Route::controller(AdminUserController::class)->prefix('users')->name('users.')->where(['user' => $ulid])->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{user}', 'show')->name('show');
        Route::post('/{user}/status', 'status')->middleware('throttle:admin-sensitive')->name('status');
        Route::post('/{user}/two-factor-reset', 'resetTwoFactor')->middleware('throttle:admin-sensitive')->name('two-factor');
    });
    Route::get('/inbox', [AdminPeopleController::class, 'inbox'])->name('inbox.index');
    Route::get('/payouts', [AdminFinanceController::class, 'payouts'])->name('payouts.index');
    Route::get('/payments', [AdminFinanceController::class, 'payments'])->name('payments.index');
    Route::get('/complaints', [AdminFinanceController::class, 'complaints'])->name('complaints.index');
    Route::get('/settings', [AdminSettingsController::class, 'show'])->name('settings');
    Route::put('/settings', [AdminSettingsController::class, 'update'])->middleware('throttle:admin-sensitive')->name('settings.update');
    Route::get('/audit-log', [AdminAuditLogController::class, 'index'])->name('audit.index');
});

// Legal pages (Phase 10: editable in the admin CMS).
Route::view('/terms', 'pages.legal.terms')->name('legal.terms');
Route::view('/privacy', 'pages.legal.privacy')->name('legal.privacy');
Route::view('/cancellation-policy', 'pages.legal.cancellation')->name('legal.cancellation');

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
|
| Login, register, password reset, email verification, password confirmation,
| two-factor login and logout are registered by Fortify (config/fortify.php,
| FortifyServiceProvider).
|
*/

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
