<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Captains (PLAN §6.4). The email is required: trip assignments are emailed to it (never with customer
 * personal data). The license number is encrypted; pay_rate_cents is internal. License documents go to
 * `media` on the private disk. Captain unavailability comes with assignment (Phase 4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captains', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('photo_path', 255)->nullable();
            $table->text('bio')->nullable();
            $table->string('email', 254)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('license_type', 10); // oupv | master | other
            $table->text('license_number')->nullable(); // encrypted
            $table->date('license_expires_on')->nullable();
            $table->unsignedSmallInteger('max_passengers_licensed');
            $table->unsignedTinyInteger('years_experience')->nullable();
            $table->json('languages')->nullable();
            $table->unsignedInteger('pay_rate_cents')->default(0); // internal, per hour
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('captains');
    }
};
