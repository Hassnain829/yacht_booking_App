<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5.3 + 5.4 (PLAN §6.6): saved cards, refunds (approved by the admin, D23) and disputes.
 *
 * - users.stripe_customer_id: the guest's Stripe Customer (saved cards hang off it).
 * - payments: the card that paid (a saved one, or the new one), whether the guest asked to save it, the provider's
 *   processing fee (kept back from a refund, D23) and how much of the payment has been refunded.
 * - customer_payment_methods: a display cache of the cards saved with the provider — never a card number.
 * - refunds: one per refund, from the guest's request (waiting for the admin) or the admin's own.
 * - disputes: chargebacks reported by the provider's webhook.
 *
 * MySQL allows index names of 64 characters at most, so the saved cards' indexes are named here. A first run on
 * MySQL stopped half-way on a longer automatic name; MySQL can't roll back table changes, so up() carries on from
 * whatever that run added (the columns already there are kept, the half-built cards table — never used — is rebuilt).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'stripe_customer_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('stripe_customer_id', 100)->nullable()->unique()->after('marketing_opt_in');
            });
        }

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'provider_payment_method_id')) {
                $table->string('provider_payment_method_id', 100)->nullable()->after('provider_charge_id');
            }
            if (! Schema::hasColumn('payments', 'save_card')) {
                $table->boolean('save_card')->default(false)->after('provider_payment_method_id');
            }
            if (! Schema::hasColumn('payments', 'fee_cents')) {
                $table->unsignedInteger('fee_cents')->nullable()->after('amount_cents'); // the provider's processing fee
            }
            if (! Schema::hasColumn('payments', 'amount_refunded_cents')) {
                $table->unsignedInteger('amount_refunded_cents')->default(0)->after('fee_cents');
            }
        });

        Schema::dropIfExists('customer_payment_methods'); // only ever half-built by that first MySQL run
        Schema::create('customer_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 10); // stripe | fake (local / tests only)
            $table->string('provider_payment_method_id', 100);
            $table->string('brand', 20);
            $table->string('last4', 4);
            $table->unsignedTinyInteger('exp_month');
            $table->unsignedSmallInteger('exp_year');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['provider', 'provider_payment_method_id'], 'cpm_provider_method_unique');
            $table->index(['user_id', 'provider'], 'cpm_user_provider_index');
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount_cents'); // what goes back to the card
            $table->unsignedInteger('fee_cents')->default(0); // the card processing fee kept back (D23)
            $table->unsignedTinyInteger('policy_pct')->nullable(); // the policy's refund % when the guest cancelled
            $table->string('reason', 20); // RefundReason
            $table->string('status', 20); // RefundStatus
            $table->string('guest_reason', 20)->nullable(); // why the guest cancelled (CancelReasons)
            $table->string('note', 1000)->nullable(); // the guest's words, or the admin's reason
            $table->string('decision_note', 500)->nullable(); // the admin's reason when approving / declining
            $table->string('provider_refund_id', 100)->nullable()->unique();
            $table->string('failure_reason', 255)->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['booking_id', 'status']);
        });

        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 10);
            $table->string('provider_dispute_id', 100)->unique();
            $table->unsignedInteger('amount_cents');
            $table->string('reason', 60)->nullable();
            $table->string('status', 40);
            $table->dateTime('evidence_due_by')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('customer_payment_methods');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['provider_payment_method_id', 'save_card', 'fee_cents', 'amount_refunded_cents']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['stripe_customer_id']);
            $table->dropColumn('stripe_customer_id');
        });
    }
};
