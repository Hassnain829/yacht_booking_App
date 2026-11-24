<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4.2 (user decision 2026-11-24, Boatsetter-style): a boat's weekly availability becomes the **start times**
 * it can depart at — every half hour from 05:00 to 22:00 (Chicago time) — instead of opening and closing hours.
 * Only the start has to be one of them; the trip may end later.
 *
 * Existing hours are converted so the old promise still holds for the shortest trip: every half hour from the
 * opening time up to closing time minus the boat's minimum trip length (08:00–23:00 with a 2-hour minimum →
 * 08:00 … 21:00). Rolling back turns the start times into hours again (first start → last start + minimum).
 *
 * Also adds boats.weekend_fixed_prices (user decision 2026-11-24): the admin may add the weekend rate to a boat's
 * fixed prices too (QuoteService).
 */
return new class extends Migration
{
    private const FIRST = 5 * 60;

    private const LAST = 22 * 60;

    public function up(): void
    {
        Schema::table('boat_availability_rules', function (Blueprint $table) {
            $table->json('start_times')->nullable()->after('is_open'); // ["08:00","08:30",…], Chicago time
        });

        $minimums = DB::table('boats')->pluck('min_hours', 'id');
        DB::table('boat_availability_rules')->orderBy('id')->each(function (object $rule) use ($minimums) {
            $times = [];
            if ($rule->is_open && $rule->opens_at !== null && $rule->closes_at !== null) {
                $first = max(self::FIRST, (int) ceil(self::minutes($rule->opens_at) / 30) * 30);
                $last = min(self::LAST, self::minutes($rule->closes_at) - 60 * max(1, (int) ($minimums[$rule->boat_id] ?? 1)));
                for ($m = $first; $m <= $last; $m += 30) {
                    $times[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
                }
                if ($times === [] && $first <= self::LAST) {
                    $times[] = sprintf('%02d:%02d', intdiv($first, 60), $first % 60); // a short day keeps its first start
                }
            }
            DB::table('boat_availability_rules')->where('id', $rule->id)->update([
                'start_times' => json_encode($times),
                'is_open' => $rule->is_open && $times !== [],
            ]);
        });

        Schema::table('boat_availability_rules', function (Blueprint $table) {
            $table->dropColumn(['opens_at', 'closes_at']);
        });

        Schema::table('boats', function (Blueprint $table) {
            $table->boolean('weekend_fixed_prices')->default(false)->after('duration_prices');
        });
    }

    public function down(): void
    {
        Schema::table('boats', function (Blueprint $table) {
            $table->dropColumn('weekend_fixed_prices');
        });

        Schema::table('boat_availability_rules', function (Blueprint $table) {
            $table->time('opens_at')->nullable()->after('is_open');
            $table->time('closes_at')->nullable()->after('opens_at');
        });

        $minimums = DB::table('boats')->pluck('min_hours', 'id');
        DB::table('boat_availability_rules')->orderBy('id')->each(function (object $rule) use ($minimums) {
            $times = json_decode((string) $rule->start_times, true) ?: [];
            sort($times);
            $open = $rule->is_open && $times !== [];
            $closes = $open ? min(23 * 60 + 59, self::minutes(end($times)) + 60 * max(1, (int) ($minimums[$rule->boat_id] ?? 1))) : null;
            DB::table('boat_availability_rules')->where('id', $rule->id)->update([
                'is_open' => $open,
                'opens_at' => $open ? $times[0].':00' : null,
                'closes_at' => $closes !== null ? sprintf('%02d:%02d:00', intdiv($closes, 60), $closes % 60) : null,
            ]);
        });

        Schema::table('boat_availability_rules', function (Blueprint $table) {
            $table->dropColumn('start_times');
        });
    }

    /** "08:30" or "08:30:00" → minutes after midnight. */
    private static function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
};
