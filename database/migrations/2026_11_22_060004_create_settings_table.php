<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site settings the admin can change (PLAN §6.7): business info, booking and pricing rules, owner money
 * rules. One row per key; the value is JSON. Read through a cached service (Phase 3.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique(); // e.g. "tax_rate_bp", "payout_delay_hours"
            $table->json('value');
            $table->string('group', 40)->index(); // business | booking | owners
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
