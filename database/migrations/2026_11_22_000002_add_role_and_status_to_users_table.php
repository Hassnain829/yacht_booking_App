<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2.2 — role + status (PLAN §4.3, §6.1). Plain strings (portable SQL); the PHP enums
 * App\Enums\Role and App\Enums\UserStatus define the allowed values. Soft deletes keep booking history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('customer')->after('marketing_opt_in')->index();
            $table->string('status', 20)->default('active')->after('role')->index();
            $table->string('status_reason', 500)->nullable()->after('status');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);
            $table->dropSoftDeletes();
            $table->dropColumn(['role', 'status', 'status_reason', 'last_login_at', 'last_login_ip']);
        });
    }
};
