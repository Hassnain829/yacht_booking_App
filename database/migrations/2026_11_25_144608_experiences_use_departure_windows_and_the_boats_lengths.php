<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 4.4 fixes (user decisions 2026-11-25, after their QA):
 *  - An experience has no trip length of its own: the guest books the boat by the hour (its lengths) and gets the
 *    experience for that time. `experiences.durations` goes.
 *  - An experience departs inside a window (a sunset cruise in the evening, the fireworks at night): the guest
 *    picks any of the boat's start times inside it. `time_slots` keeps every half hour of the window, so a list
 *    with gaps ("10:00, 13:00, 16:00") becomes the whole window ("10:00 … 16:00").
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('experiences')->orderBy('id')->each(function (object $experience) {
            $times = array_values(array_filter((array) json_decode((string) $experience->time_slots, true), 'is_string'));
            if ($times === []) {
                return;
            }
            sort($times);
            DB::table('experiences')->where('id', $experience->id)->update(['time_slots' => json_encode(self::window($times[0], end($times)))]);
        });

        Schema::table('experiences', function (Blueprint $table) {
            $table->dropColumn('durations');
        });
    }

    public function down(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->json('durations')->nullable()->after('includes'); // hours offered, e.g. [2, 3, 4]
        });

        DB::table('experiences')->update(['durations' => json_encode([2, 3, 4])]);
    }

    /**
     * Every half hour from `$first` to `$last` (both on the 05:00–22:00 start-time grid).
     *
     * @return list<string>
     */
    private static function window(string $first, string $last): array
    {
        $minutes = fn (string $time) => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
        $times = [];
        for ($m = max(300, $minutes($first)); $m <= min(1320, $minutes($last)); $m += 30) {
            $times[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        }

        return $times;
    }
};
