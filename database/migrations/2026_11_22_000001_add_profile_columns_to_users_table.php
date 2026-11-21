<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Phase 2.1 — the profile fields the sign-up form collects (PLAN §6.1).
 * `name` is split into first_name / last_name; every user gets a public ULID (never expose numeric ids).
 * role / status / last_login_* arrive in Phase 2.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->ulid('ulid')->nullable()->after('id');
            $table->string('first_name', 60)->nullable()->after('ulid');
            $table->string('last_name', 60)->nullable()->after('first_name');
            $table->string('phone', 30)->nullable()->after('email_verified_at');
            $table->boolean('marketing_opt_in')->default(false)->after('password');
        });

        // Existing rows: split the old name, give each a ULID.
        DB::table('users')->orderBy('id')->each(function (object $user) {
            $parts = preg_split('/\s+/', trim((string) $user->name), 2);
            DB::table('users')->where('id', $user->id)->update([
                'ulid' => strtolower((string) Str::ulid()),
                'first_name' => Str::limit($parts[0] ?? '', 60, ''),
                'last_name' => Str::limit($parts[1] ?? '', 60, ''),
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->ulid('ulid')->nullable(false)->change();
            $table->string('first_name', 60)->nullable(false)->change();
            $table->string('last_name', 60)->nullable(false)->change();
            $table->unique('ulid');
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
        });

        DB::table('users')->orderBy('id')->each(function (object $user) {
            DB::table('users')->where('id', $user->id)->update(['name' => trim($user->first_name.' '.$user->last_name)]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->dropUnique(['ulid']);
            $table->dropColumn(['ulid', 'first_name', 'last_name', 'phone', 'marketing_opt_in']);
        });
    }
};
