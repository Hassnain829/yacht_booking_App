<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4.2 — the booking tables (PLAN §6.5), created ahead of the hold / checkout step because availability
 * (AvailabilityService) has to read the trips already on a boat. All times are UTC; money in cents.
 *
 * Columns that later phases fill are here already, so the table isn't rebuilt then: the captain fields (Phase 8),
 * promo_code_id (its table comes with promo codes in 4.4 / 4.7) and booking_items.booking_extension_id
 * (trip extensions, 8.5). Their foreign keys are added with those tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('reference', 12)->unique(); // CYB-7K3P9Q, what the customer and the captain quote
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete(); // set at checkout
            $table->foreignId('boat_id')->constrained()->restrictOnDelete();
            $table->foreignId('experience_id')->nullable()->constrained()->nullOnDelete();

            // Captain (Phase 8: offers, direct assignment, acknowledge)
            $table->foreignId('captain_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('captain_assigned_at')->nullable();
            $table->string('captain_assigned_via', 10)->nullable(); // offer | direct
            $table->timestamp('captain_notified_at')->nullable();
            $table->timestamp('captain_acknowledged_at')->nullable();
            $table->text('captain_notes')->nullable(); // by the admin, shown to the captain; never customer personal data

            $table->string('status', 20); // BookingStatus
            $table->string('payment_status', 20)->default('unpaid'); // PaymentStatus

            // Trip
            $table->dateTime('starts_at');
            $table->dateTime('ends_at'); // moves only when a paid extension is added (8.5)
            $table->unsignedTinyInteger('hours');
            $table->unsignedSmallInteger('guests');
            $table->string('occasion', 60)->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('admin_notes')->nullable();

            // Contact snapshot (filled at checkout)
            $table->string('contact_first_name', 60)->nullable();
            $table->string('contact_last_name', 60)->nullable();
            $table->string('contact_email', 160)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('heard_from', 60)->nullable();

            // Money (QuoteService)
            $table->string('payment_plan', 10)->default('deposit'); // deposit | full
            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->unsignedInteger('discount_cents')->default(0);
            $table->unsignedInteger('tax_cents')->default(0);
            $table->unsignedSmallInteger('gratuity_bp')->default(0);
            $table->unsignedInteger('gratuity_cents')->default(0);
            $table->unsignedInteger('total_cents')->default(0);
            $table->unsignedInteger('amount_paid_cents')->default(0);
            $table->unsignedInteger('amount_refunded_cents')->default(0);
            $table->unsignedInteger('balance_due_cents')->default(0);
            $table->dateTime('balance_due_at')->nullable();

            // Snapshots: an old booking never changes when prices or policies change
            $table->unsignedBigInteger('promo_code_id')->nullable(); // foreign key with the promo_codes table
            $table->json('policy_snapshot')->nullable();
            $table->json('pricing_snapshot')->nullable();

            // Lifecycle (PLAN §7.3)
            $table->dateTime('hold_expires_at')->nullable(); // a hold, or a request waiting for the owner
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancelled_by_role', 10)->nullable(); // customer | owner | admin | system
            $table->text('cancellation_reason')->nullable();
            $table->string('source', 10)->default('web'); // web | admin | phone
            $table->timestamps();

            $table->index(['boat_id', 'starts_at']);
            $table->index(['boat_id', 'status']);
            $table->index(['status', 'hold_expires_at']);
            $table->index(['captain_id', 'starts_at']);
        });

        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('booking_extension_id')->nullable(); // the lines of a trip extension (8.5)
            $table->string('type', 12); // BookingItemType
            $table->foreignId('addon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 120);
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->integer('unit_amount_cents'); // a discount line is negative
            $table->integer('amount_cents');
            $table->string('payee', 10); // Payee
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['booking_id', 'sort']);
            $table->index('booking_extension_id');
        });

        Schema::create('booking_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['booking_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_status_history');
        Schema::dropIfExists('booking_items');
        Schema::dropIfExists('bookings');
    }
};
