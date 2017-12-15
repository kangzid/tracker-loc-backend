<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Use fake storage for tests so files are never written to real storage folder
        Storage::fake('private');
        Storage::fake('local');
        Storage::fake('public');
    }
}
