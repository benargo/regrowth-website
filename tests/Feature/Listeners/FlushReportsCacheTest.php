<?php

namespace Tests\Feature\Listeners;

use App\Events\ReportCreated;
use App\Events\ReportLinkDeleted;
use App\Events\ReportLinkSaved;
use App\Events\ReportUpdated;
use App\Listeners\FlushReportsCache;
use App\Models\Raids\Report;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FlushReportsCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::tags(['reports'])->flush();
    }

    #[Test]
    public function report_created_event_flushes_cache(): void
    {
        Cache::tags(['reports'])->put('test_key', 'test_value', now()->addMinutes(5));

        $this->assertTrue(Cache::tags(['reports'])->has('test_key'));

        $listener = new FlushReportsCache;
        $listener->handle(new ReportCreated(Report::factory()->make()));

        $this->assertFalse(Cache::tags(['reports'])->has('test_key'));
    }

    #[Test]
    public function report_updated_event_flushes_cache(): void
    {
        Cache::tags(['reports'])->put('test_key', 'test_value', now()->addMinutes(5));

        $this->assertTrue(Cache::tags(['reports'])->has('test_key'));

        $listener = new FlushReportsCache;
        $listener->handle(new ReportUpdated(Report::factory()->make()));

        $this->assertFalse(Cache::tags(['reports'])->has('test_key'));
    }

    #[Test]
    public function report_link_saved_event_flushes_cache(): void
    {
        Cache::tags(['reports'])->put('test_key', 'test_value', now()->addMinutes(5));

        $this->assertTrue(Cache::tags(['reports'])->has('test_key'));

        $listener = new FlushReportsCache;
        $listener->handle(new ReportLinkSaved);

        $this->assertFalse(Cache::tags(['reports'])->has('test_key'));
    }

    #[Test]
    public function report_link_deleted_event_flushes_cache(): void
    {
        Cache::tags(['reports'])->put('test_key', 'test_value', now()->addMinutes(5));

        $this->assertTrue(Cache::tags(['reports'])->has('test_key'));

        $listener = new FlushReportsCache;
        $listener->handle(new ReportLinkDeleted);

        $this->assertFalse(Cache::tags(['reports'])->has('test_key'));
    }

    #[Test]
    public function flushes_multiple_cache_entries(): void
    {
        Cache::tags(['reports'])->put('key_one', 'value_one', now()->addMinutes(5));
        Cache::tags(['reports'])->put('key_two', 'value_two', now()->addMinutes(5));

        $listener = new FlushReportsCache;
        $listener->handle(new ReportCreated(Report::factory()->make()));

        $this->assertFalse(Cache::tags(['reports'])->has('key_one'));
        $this->assertFalse(Cache::tags(['reports'])->has('key_two'));
    }

    #[Test]
    public function does_not_flush_unrelated_cache_tags(): void
    {
        Cache::tags(['other_tag'])->put('unrelated_key', 'unrelated_value', now()->addMinutes(5));

        $listener = new FlushReportsCache;
        $listener->handle(new ReportCreated(Report::factory()->make()));

        $this->assertTrue(Cache::tags(['other_tag'])->has('unrelated_key'));
    }
}
