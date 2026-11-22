<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fleet (PLAN §6.2): harbors, cancellation policies, boats (house + owner, one table), amenities,
 * weekly opening hours and blocks. Money in cents, rates in basis points, times in UTC.
 * Boat photos live in `media` (collection "photos"); owner_profiles and insurance come with Phase 6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harbors', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            $table->string('area', 120)->nullable();
            $table->string('address', 255)->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->string('map_query', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('image_path', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort']);
        });

        Schema::create('cancellation_policies', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique(); // flexible | moderate | strict
            $table->string('name', 80);
            $table->string('badge', 60)->nullable();
            $table->json('points'); // the sentences shown to guests
            $table->json('rules'); // [{"min_hours_before": 120, "refund_pct": 100}, …] — what the refund engine uses
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('boats', function (Blueprint $table) {
            // Identity and status
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('slug', 120)->unique();
            $table->string('ownership', 10)->index(); // house | owner
            $table->foreignId('owner_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->boolean('booking_enabled')->default(true); // false = the "Unavailable" tag
            $table->string('unavailable_reason', 255)->nullable();

            // Description
            $table->string('title', 120);
            $table->string('short', 255)->nullable();
            $table->text('description')->nullable();

            // Specs
            $table->string('type', 10)->nullable(); // power | sail
            $table->string('category', 60)->nullable();
            $table->string('make', 80)->nullable();
            $table->string('model', 80)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedSmallInteger('length_ft')->nullable();
            $table->string('registration_no', 60)->nullable();
            $table->boolean('uscg_inspected')->default(false);
            $table->unsignedSmallInteger('max_guests')->default(6);
            $table->unsignedTinyInteger('staterooms')->nullable();
            $table->unsignedTinyInteger('sleeps')->nullable();
            $table->unsignedTinyInteger('beds')->nullable();
            $table->unsignedTinyInteger('heads')->nullable();
            $table->unsignedTinyInteger('engine_count')->nullable();
            $table->string('engine_type', 40)->nullable();
            $table->unsignedSmallInteger('horsepower')->nullable();
            $table->unsignedSmallInteger('engine_year')->nullable();

            // Location
            $table->foreignId('harbor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location_type', 60)->nullable();
            $table->string('street', 255)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 60)->nullable();
            $table->string('zip', 20)->nullable();
            $table->char('country', 2)->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();

            // Rules and booking settings
            $table->foreignId('cancellation_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->json('rules')->nullable();
            $table->unsignedInteger('advance_notice_minutes')->default(0);
            $table->boolean('allow_multiple_bookings')->default(false);
            $table->unsignedSmallInteger('buffer_minutes')->default(0);

            // Operation
            $table->boolean('renter_operated_allowed')->default(false);
            $table->string('captain_source', 10)->default('platform'); // owner | platform
            $table->boolean('fishing_charter')->default(false);
            $table->string('fuel_policy', 60)->nullable();

            // Pricing (cents / basis points)
            $table->unsignedInteger('hourly_rate_cents')->nullable();
            $table->unsignedSmallInteger('weekend_multiplier_bp')->nullable(); // null = the site setting
            $table->unsignedTinyInteger('min_hours')->default(2);
            $table->json('durations')->nullable();
            $table->json('duration_prices')->nullable();
            $table->unsignedInteger('cleaning_fee_cents')->default(0);
            $table->unsignedInteger('captain_rate_cents')->default(0); // customer-facing, per hour
            $table->unsignedTinyInteger('crew_count')->default(1);

            // Display (entered by the admin until reviews exist)
            $table->string('badge', 60)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->unsignedInteger('reviews_count')->default(0);

            // Listing workflow
            $table->string('wizard_step', 30)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->json('pending_changes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'booking_enabled']);
            $table->index(['is_featured', 'sort']);
        });

        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('group', 20); // highlight | feature | extra | navigation
            $table->string('icon', 40)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['group', 'name']);
        });

        Schema::create('amenity_boat', function (Blueprint $table) {
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boat_id')->constrained()->cascadeOnDelete();
            $table->primary(['amenity_id', 'boat_id']);
            $table->index('boat_id');
        });

        Schema::create('boat_availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boat_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0 = Sunday … 6 = Saturday
            $table->boolean('is_open')->default(true);
            $table->time('opens_at')->nullable(); // Chicago local time of day
            $table->time('closes_at')->nullable();
            $table->timestamps();

            $table->unique(['boat_id', 'weekday']);
        });

        Schema::create('boat_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boat_id')->nullable()->constrained()->cascadeOnDelete(); // null = every boat
            $table->dateTime('starts_at'); // UTC
            $table->dateTime('ends_at');
            $table->string('type', 20); // owner_block | maintenance | blackout | weather
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['boat_id', 'starts_at']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boat_blocks');
        Schema::dropIfExists('boat_availability_rules');
        Schema::dropIfExists('amenity_boat');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('boats');
        Schema::dropIfExists('cancellation_policies');
        Schema::dropIfExists('harbors');
    }
};
