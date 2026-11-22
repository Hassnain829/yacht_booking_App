<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog (PLAN §6.3): experiences (packages), which boats offer them, and add-ons.
 * Bookings keep their own price snapshot, so editing these never changes an old booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiences', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            $table->string('category', 60)->nullable();
            $table->string('icon', 40)->nullable();
            $table->string('image_path', 255)->nullable();
            $table->string('tagline', 255)->nullable();
            $table->text('description')->nullable();
            $table->json('includes')->nullable(); // ["Golden-hour departure", …]
            $table->json('durations'); // hours offered, e.g. [2, 3, 4]
            $table->json('time_slots'); // start times (Chicago), e.g. ["17:30", "18:00"]
            $table->json('allowed_weekdays')->nullable(); // null = every day; else [0…6]
            $table->date('season_starts_on')->nullable();
            $table->date('season_ends_on')->nullable();
            $table->unsignedInteger('package_fee_cents')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('seo_title', 120)->nullable();
            $table->string('seo_description', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort']);
        });

        Schema::create('boat_experience', function (Blueprint $table) {
            $table->foreignId('boat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('experience_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('package_fee_override_cents')->nullable(); // null = the experience's fee
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->primary(['boat_id', 'experience_id']);
            $table->index('experience_id');
        });

        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->string('icon', 40)->nullable();
            $table->unsignedInteger('price_cents');
            $table->string('unit', 10)->default('flat'); // flat | per_guest
            $table->unsignedSmallInteger('max_quantity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addons');
        Schema::dropIfExists('boat_experience');
        Schema::dropIfExists('experiences');
    }
};
