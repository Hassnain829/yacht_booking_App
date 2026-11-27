<?php

namespace Tests\Feature\Phase5;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * MySQL / MariaDB refuse an index name longer than 64 characters, and stop a migration half-way (they can't roll
 * back table changes). The tests run on SQLite, which has no such limit — so every index name is checked here.
 * Found on 2026-11-27: the saved cards' automatic unique index name was 67 characters.
 */
class IndexNameLengthTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_index_name_fits_mysql(): void
    {
        $tooLong = [];
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                if (strlen($index['name']) > 64) {
                    $tooLong[] = "{$table}: {$index['name']} (".strlen($index['name']).')';
                }
            }
        }

        $this->assertSame([], $tooLong, 'Give these indexes a shorter name in their migration.');
    }
}
