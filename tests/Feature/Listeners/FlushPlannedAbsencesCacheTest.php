<?php

namespace Tests\Feature\Listeners;

use App\Events\PlannedAbsenceCreated;
use App\Events\PlannedAbsenceDeleted;
use App\Events\PlannedAbsenceUpdated;
use App\Listeners\FlushPlannedAbsencesCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FlushPlannedAbsencesCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::tags(['planned_absences'])->flush();
    }

    public function test_planned_absence_created_event_flushes_cache(): void
    {
        Cache::tags(['planned_absences'])->put('test_key', 'test_value', now()->addMinutes(5));

        $this->assertTrue(Cache::tags(['planned_absences'])->has('test_key'));

        $listener = new FlushPlannedAbsencesCache;
        $listener->handle(new PlannedAbsenceCreated);

        $this->assertFalse(Cache::tags(['planned_absences'])->has('test_key'));
    }

    public function test_planned_absence_updated_event_flushes_cache(): void
    {
        Cache::tags(['planned_absences'])->put('test_key', 'test_value', now()->addMinutes(5));

        $this->assertTrue(Cache::tags(['planned_absences'])->has('test_key'));

        $listener = new FlushPlannedAbsencesCache;
        $listener->handle(new PlannedAbsenceUpdated);

        $this->assertFalse(Cache::tags(['planned_absences'])->has('test_key'));
    }

    public function test_planned_absence_deleted_event_flushes_cache(): void
    {
        Cache::tags(['planned_absences'])->put('test_key', 'test_value', now()->addMinutes(5));

        $this->assertTrue(Cache::tags(['planned_absences'])->has('test_key'));

        $listener = new FlushPlannedAbsencesCache;
        $listener->handle(new PlannedAbsenceDeleted);

        $this->assertFalse(Cache::tags(['planned_absences'])->has('test_key'));
    }

    public function test_flushes_multiple_cache_entries(): void
    {
        Cache::tags(['planned_absences'])->put('key_one', 'value_one', now()->addMinutes(5));
        Cache::tags(['planned_absences'])->put('key_two', 'value_two', now()->addMinutes(5));

        $listener = new FlushPlannedAbsencesCache;
        $listener->handle(new PlannedAbsenceCreated);

        $this->assertFalse(Cache::tags(['planned_absences'])->has('key_one'));
        $this->assertFalse(Cache::tags(['planned_absences'])->has('key_two'));
    }

    public function test_does_not_flush_unrelated_cache_tags(): void
    {
        Cache::tags(['other_tag'])->put('unrelated_key', 'unrelated_value', now()->addMinutes(5));

        $listener = new FlushPlannedAbsencesCache;
        $listener->handle(new PlannedAbsenceCreated);

        $this->assertTrue(Cache::tags(['other_tag'])->has('unrelated_key'));
    }
}
