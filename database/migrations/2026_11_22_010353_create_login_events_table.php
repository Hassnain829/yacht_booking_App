<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security log per account (PLAN §5, §8.5): logins, failed logins, lockouts, 2FA and password events.
 * Rows older than a year are pruned (LoginEvent::prunable, scheduled `model:prune`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null: unknown email
            $table->string('email', 254)->nullable();
            $table->string('event', 40)->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->char('device_id', 64)->nullable(); // SHA-256 of the device cookie, never the cookie itself
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['user_id', 'event', 'created_at']);
            $table->index(['user_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_events');
    }
};
