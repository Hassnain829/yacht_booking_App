<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.4: harbors and add-ons get a Trash (soft deletes, like boats, experiences and captains), and every
 * boat gets the admin's "Allow trip extensions" switch (PLAN R17, §7.9), on by default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('harbors', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('addons', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('boats', function (Blueprint $table) {
            $table->boolean('extensions_enabled')->default(true)->after('buffer_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('boats', function (Blueprint $table) {
            $table->dropColumn('extensions_enabled');
        });

        Schema::table('addons', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('harbors', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
