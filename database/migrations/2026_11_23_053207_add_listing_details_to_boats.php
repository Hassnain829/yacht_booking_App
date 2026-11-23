<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Flexible boat listings (PLAN 3.7):
 * - `highlights`: a boat's own selling points ("Onboard generator"), typed on the boat form. Until now they
 *   were shared amenity rows in a "highlight" group; each boat's ticked highlights are copied into its own
 *   list and those shared rows are removed (rollback puts them back).
 * - `engine_model`: e.g. "Volvo Penta 4.3L GXi".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boats', function (Blueprint $table) {
            $table->json('highlights')->nullable()->after('description');
            $table->string('engine_model', 80)->nullable()->after('engine_type');
        });

        $rows = DB::table('amenity_boat')
            ->join('amenities', 'amenities.id', '=', 'amenity_boat.amenity_id')
            ->where('amenities.group', 'highlight')
            ->orderBy('amenities.sort')
            ->orderBy('amenities.id')
            ->get(['amenity_boat.boat_id', 'amenities.name']);

        foreach ($rows->groupBy('boat_id') as $boatId => $highlights) {
            DB::table('boats')->where('id', $boatId)->update(['highlights' => json_encode($highlights->pluck('name')->values()->all())]);
        }

        $ids = DB::table('amenities')->where('group', 'highlight')->pluck('id');
        DB::table('amenity_boat')->whereIn('amenity_id', $ids)->delete();
        DB::table('amenities')->whereIn('id', $ids)->delete();
    }

    public function down(): void
    {
        $now = now();
        foreach (DB::table('boats')->whereNotNull('highlights')->get(['id', 'highlights']) as $boat) {
            foreach (array_values((array) json_decode((string) $boat->highlights, true)) as $sort => $name) {
                $id = DB::table('amenities')->where('group', 'highlight')->where('name', $name)->value('id')
                    ?? DB::table('amenities')->insertGetId(['ulid' => strtolower((string) Str::ulid()), 'name' => mb_substr((string) $name, 0, 80), 'group' => 'highlight', 'sort' => $sort, 'created_at' => $now, 'updated_at' => $now]);
                DB::table('amenity_boat')->insertOrIgnore(['amenity_id' => $id, 'boat_id' => $boat->id]);
            }
        }

        Schema::table('boats', function (Blueprint $table) {
            $table->dropColumn(['highlights', 'engine_model']);
        });
    }
};
