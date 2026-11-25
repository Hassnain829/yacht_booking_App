<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4.3 (user decision 2026-11-25): an experience is free, as on Boatsetter. The admin can add paid extras to
 * an experience (party décor, a photographer, any facility): add-ons from Admin → Add-ons, priced there, and
 * charged on every booking of that experience. The experience package fee and the per-boat fee override go.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_experience', function (Blueprint $table) {
            $table->foreignId('experience_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->primary(['experience_id', 'addon_id']);
            $table->index('addon_id');
        });

        Schema::table('experiences', function (Blueprint $table) {
            $table->dropColumn('package_fee_cents');
        });

        Schema::table('boat_experience', function (Blueprint $table) {
            $table->dropColumn('package_fee_override_cents');
        });
    }

    /** The fees come back empty (0 / no override); the extras links are lost. */
    public function down(): void
    {
        Schema::table('boat_experience', function (Blueprint $table) {
            $table->unsignedInteger('package_fee_override_cents')->nullable()->after('experience_id');
        });

        Schema::table('experiences', function (Blueprint $table) {
            $table->unsignedInteger('package_fee_cents')->default(0)->after('season_ends_on');
        });

        Schema::dropIfExists('addon_experience');
    }
};
