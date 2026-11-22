<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Last changed" on the Password card (Phase 2.5). Set by the User model whenever the password changes;
 * existing accounts start from their sign-up date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_changed_at')->nullable()->after('password');
        });

        DB::table('users')->update(['password_changed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_changed_at');
        });
    }
};
