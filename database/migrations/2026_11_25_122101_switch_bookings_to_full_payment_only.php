<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4.3 (PLAN D22, user decision 2026-11-25): every booking is paid in full at checkout. There is no deposit,
 * no balance charged later and no request to book, so the payment-plan and balance columns go, and so do the
 * deposit % and balance-days settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['payment_plan', 'balance_due_cents', 'balance_due_at']);
        });

        DB::table('settings')->whereIn('key', ['deposit_bp', 'balance_due_days'])->delete();
    }

    /** The columns come back with their old defaults; the two settings fall back to nothing (no longer read). */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_plan', 10)->default('deposit')->after('heard_from');
            $table->unsignedInteger('balance_due_cents')->default(0)->after('amount_refunded_cents');
            $table->dateTime('balance_due_at')->nullable()->after('balance_due_cents');
        });
    }
};
