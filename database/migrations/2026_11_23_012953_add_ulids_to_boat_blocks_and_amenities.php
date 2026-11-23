<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Phase 3.4: boat blocks and amenities get a public id (`ulid`), so the admin's (and later the owner's) URLs
 * and forms never show the numeric id (CLAUDE.md). Rows that already exist get one too.
 */
return new class extends Migration
{
    private const TABLES = ['boat_blocks', 'amenities'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->ulid('ulid')->nullable()->after('id');
            });

            DB::table($name)->whereNull('ulid')->orderBy('id')->each(function (object $row) use ($name) {
                DB::table($name)->where('id', $row->id)->update(['ulid' => strtolower((string) Str::ulid())]);
            });

            Schema::table($name, function (Blueprint $table) {
                $table->unique('ulid');
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['ulid']);
                $table->dropColumn('ulid');
            });
        }
    }
};
