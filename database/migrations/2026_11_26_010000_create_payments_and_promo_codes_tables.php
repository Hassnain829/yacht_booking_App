<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4.5 — hold + checkout with the full payment taken at checkout (D22; user decision 2026-11-26: the payment
 * is received in 4.5, not stubbed — Stripe's core, planned for 5.1 / 5.2, comes forward).
 *
 *  - promo_codes / promo_redemptions (PLAN §6.5): the codes guests type at checkout; a redemption is written when a
 *    booking is paid. Admin screens in 4.8.
 *  - payments (§6.6): one row per attempt to pay a booking (always the full amount), with the provider's intent.
 *  - webhook_events (§6.6): every provider event once (unique event id), so a replayed webhook does nothing.
 *  - bookings.hold_key: the browser's idempotency key for a hold, so a double click makes one booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('code', 30)->unique(); // upper case, e.g. WELCOME10
            $table->string('type', 10); // PromoType: percent | fixed
            $table->unsignedInteger('value'); // percent: basis points (1000 = 10%); fixed: cents
            $table->string('applies_to', 10)->default('charter'); // PromoAppliesTo: charter | subtotal
            $table->unsignedInteger('min_subtotal_cents')->nullable();
            $table->unsignedInteger('max_discount_cents')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable(); // in total
            $table->unsignedSmallInteger('max_per_user')->nullable();
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('promo_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete(); // one code per booking
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('discount_cents');
            $table->timestamps();

            $table->index(['promo_code_id', 'user_id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreign('promo_code_id')->references('id')->on('promo_codes')->nullOnDelete();
            $table->string('hold_key', 64)->nullable()->unique()->after('source');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 10); // stripe | fake (local / tests only)
            $table->string('provider_intent_id', 100)->nullable()->unique();
            $table->string('provider_charge_id', 100)->nullable();
            $table->string('kind', 10)->default('full'); // full | extension (8.5) | extra
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('usd');
            $table->string('status', 20); // PaymentIntentStatus
            $table->string('failure_code', 60)->nullable();
            $table->string('failure_message', 255)->nullable();
            $table->string('card_brand', 20)->nullable(); // display only (PCI SAQ-A: never the number)
            $table->string('card_last4', 4)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 10);
            $table->string('event_id', 100)->unique();
            $table->string('type', 80);
            $table->json('payload');
            $table->dateTime('received_at');
            $table->dateTime('processed_at')->nullable();
            $table->string('error', 255)->nullable();

            $table->index(['type', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payments');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['promo_code_id']);
            $table->dropUnique(['hold_key']);
            $table->dropColumn('hold_key');
        });
        Schema::dropIfExists('promo_redemptions');
        Schema::dropIfExists('promo_codes');
    }
};
