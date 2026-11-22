<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Vite;

abstract class TestCase extends BaseTestCase
{
    /** A hot file that never exists: pages render with the built assets even while `npm run dev` is running. */
    public const NO_HOT_FILE = 'framework/testing/vite-hot-disabled';

    protected function setUp(): void
    {
        parent::setUp();

        Vite::useHotFile(storage_path(self::NO_HOT_FILE));
    }
}
