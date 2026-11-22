<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every uploaded file (PLAN §6.7, §9). Only the path relative to the disk is stored; `disk` is "public"
 * (photos) or "local" (private documents, served only through a Policy-checked controller).
 * Written only by MediaService (Phase 3.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->nullableMorphs('model'); // short morph names, e.g. "boat"
            $table->string('collection', 40); // photos | cover | license | coi | complaint …
            $table->string('disk', 20);
            $table->string('path', 255);
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('variants')->nullable(); // {"thumb": "path", "large": "path"}
            $table->string('alt', 255)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['model_type', 'model_id', 'collection', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
