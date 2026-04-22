<?php

namespace Tests\Unit\Observers;

use App\Models\PlannedAbsence;
use App\Observers\PlannedAbsenceObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlannedAbsenceObserverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function created_flushes_attendance_cache_tag(): void
    {
        Cache::tags(['attendance'])->put('test-key', 'test-value', 60);

        $observer = new PlannedAbsenceObserver;
        $observer->created(PlannedAbsence::factory()->make());

        $this->assertNull(Cache::tags(['attendance'])->get('test-key'));
    }

    #[Test]
    public function updated_flushes_attendance_cache_tag(): void
    {
        Cache::tags(['attendance'])->put('test-key', 'test-value', 60);

        $observer = new PlannedAbsenceObserver;
        $observer->updated(PlannedAbsence::factory()->make());

        $this->assertNull(Cache::tags(['attendance'])->get('test-key'));
    }

    #[Test]
    public function deleted_flushes_attendance_cache_tag(): void
    {
        Cache::tags(['attendance'])->put('test-key', 'test-value', 60);

        $observer = new PlannedAbsenceObserver;
        $observer->deleted(PlannedAbsence::factory()->make());

        $this->assertNull(Cache::tags(['attendance'])->get('test-key'));
    }

    #[Test]
    public function restored_flushes_attendance_cache_tag(): void
    {
        Cache::tags(['attendance'])->put('test-key', 'test-value', 60);

        $observer = new PlannedAbsenceObserver;
        $observer->restored(PlannedAbsence::factory()->make());

        $this->assertNull(Cache::tags(['attendance'])->get('test-key'));
    }

    #[Test]
    public function created_only_flushes_attendance_tag_not_other_tags(): void
    {
        Cache::tags(['other-tag'])->put('other-key', 'other-value', 60);

        $observer = new PlannedAbsenceObserver;
        $observer->created(PlannedAbsence::factory()->make());

        $this->assertSame('other-value', Cache::tags(['other-tag'])->get('other-key'));
    }

    #[Test]
    public function observer_is_registered_on_planned_absence_model(): void
    {
        $attributes = (new \ReflectionClass(PlannedAbsence::class))
            ->getAttributes(ObservedBy::class);

        $this->assertNotEmpty($attributes);

        $observerClasses = $attributes[0]->getArguments()[0];
        $this->assertContains(PlannedAbsenceObserver::class, $observerClasses);
    }
}
