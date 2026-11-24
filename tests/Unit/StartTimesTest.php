<?php

namespace Tests\Unit;

use App\Support\StartTimes;
use PHPUnit\Framework\TestCase;

/** Phase 4.2 — the start-time grid: every half hour 5:00 AM – 10:00 PM, Chicago time. */
class StartTimesTest extends TestCase
{
    public function test_the_grid_and_the_default_set(): void
    {
        $grid = StartTimes::grid();

        $this->assertCount(35, $grid);
        $this->assertSame(['05:00', '05:30', '22:00'], [$grid[0], $grid[1], end($grid)]);
        $this->assertSame(['08:00', 21, '18:00'], [StartTimes::defaults()[0], count(StartTimes::defaults()), last(StartTimes::defaults())]);
        $this->assertSame(['21:00', '21:30', '22:00'], StartTimes::between('21:00', '23:30'), 'kept inside the grid');
    }

    public function test_only_grid_times_survive_once_and_in_order(): void
    {
        $this->assertSame(['09:00', '17:30'], StartTimes::normalize(['17:30', '09:00', '09:00', '09:15', '23:00', '04:30', 7, null]));
        $this->assertTrue(StartTimes::onGrid('12:30'));
        $this->assertFalse(StartTimes::onGrid('12:45'));
    }

    public function test_labels_and_summaries_read_like_the_admin_form(): void
    {
        $this->assertSame(['5:00 AM', '12:00 PM', '6:30 PM'], [StartTimes::label('05:00'), StartTimes::label('12:00'), StartTimes::label('18:30')]);
        $this->assertSame('8:00 AM – 6:00 PM', StartTimes::summary(StartTimes::defaults()));
        $this->assertSame('10:00 AM, 5:30 PM – 6:30 PM', StartTimes::summary(['18:30', '10:00', '17:30', '18:00']));
        $this->assertSame('', StartTimes::summary([]));
        $this->assertSame(510, StartTimes::minutes('08:30:00'));
    }
}
