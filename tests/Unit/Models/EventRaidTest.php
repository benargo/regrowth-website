<?php

namespace Tests\Unit\Models;

use App\Models\Event;
use App\Models\EventRaid;
use App\Models\Raid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Spatie\EloquentSortable\Sortable;
use Tests\Support\ModelTestCase;

#[Group('raiding')]
class EventRaidTest extends ModelTestCase
{
    use RefreshDatabase;

    protected function modelClass(): string
    {
        return Event::class;
    }

    #[Test]
    public function it_extends_pivot(): void
    {
        $this->assertInstanceOf(Pivot::class, new EventRaid);
    }

    #[Test]
    public function it_implements_sortable(): void
    {
        $this->assertInstanceOf(Sortable::class, new EventRaid);
    }

    #[Test]
    public function it_uses_pivot_events_raids_table(): void
    {
        $pivot = new EventRaid;

        $this->assertSame('pivot_events_raids', $pivot->getTable());
    }

    #[Test]
    public function it_casts_sort_order_to_integer(): void
    {
        $pivot = new EventRaid;

        $this->assertCasts($pivot, [
            'sort_order' => 'integer',
        ]);
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $this->assertFillable(new EventRaid, [
            'event_id',
            'raid_id',
            'sort_order',
        ]);
    }

    #[Test]
    public function it_declares_fillable_via_attribute(): void
    {
        $this->assertFillableAttribute(new EventRaid, [
            'event_id',
            'raid_id',
            'sort_order',
        ]);
    }

    #[Test]
    public function it_scopes_the_sort_query_to_the_event(): void
    {
        $event = Event::factory()->create();
        $otherEvent = Event::factory()->create();

        $pivot = new EventRaid;
        $pivot->event_id = $event->id;

        $sql = $pivot->buildSortQuery()->toSql();
        $bindings = $pivot->buildSortQuery()->getBindings();

        $this->assertStringContainsString('event_id', $sql);
        $this->assertContains($event->id, $bindings);
        $this->assertNotContains($otherEvent->id, $bindings);
    }

    #[Test]
    public function it_does_not_auto_assign_a_sort_order_on_create(): void
    {
        $event = Event::factory()->create();
        $raid = Raid::factory()->create();

        $event->raids()->attach($raid->id);

        $this->assertSame(0, EventRaid::where('event_id', $event->id)->value('sort_order'));
    }

    #[Test]
    public function it_keeps_an_explicitly_provided_sort_order_on_create(): void
    {
        $event = Event::factory()->create();
        $raid = Raid::factory()->create();

        $event->raids()->attach($raid->id, ['sort_order' => 5]);

        $this->assertSame(5, EventRaid::where('event_id', $event->id)->value('sort_order'));
    }

    #[Test]
    public function it_persists_explicit_sort_orders_verbatim_when_syncing_a_mixed_set(): void
    {
        $event = Event::factory()->create();
        [$raidOne, $raidTwo, $raidThree] = Raid::factory()->count(3)->create();

        $event->raids()->sync([
            $raidOne->id => ['sort_order' => 1],
            $raidThree->id => ['sort_order' => 2],
        ]);

        $event->raids()->sync([
            $raidOne->id => ['sort_order' => 1],
            $raidTwo->id => ['sort_order' => 2],
            $raidThree->id => ['sort_order' => 3],
        ]);

        $orders = EventRaid::where('event_id', $event->id)->pluck('sort_order', 'raid_id');

        $this->assertSame(1, $orders[$raidOne->id]);
        $this->assertSame(2, $orders[$raidTwo->id]);
        $this->assertSame(3, $orders[$raidThree->id]);
    }

    // ==================== event ====================

    #[Test]
    public function event_method_returns_belongs_to(): void
    {
        $returnType = (new ReflectionMethod(EventRaid::class, 'event'))->getReturnType();

        $this->assertSame(BelongsTo::class, $returnType->getName());
    }

    #[Test]
    public function event_relation_returns_the_associated_event(): void
    {
        $event = Event::factory()->create();
        $raid = Raid::factory()->create();
        $event->raids()->attach($raid->id);

        $pivot = EventRaid::where('event_id', $event->id)->first();

        $this->assertInstanceOf(Event::class, $pivot->event);
        $this->assertSame($event->id, $pivot->event->id);
    }

    // ==================== raid ====================

    #[Test]
    public function raid_method_returns_belongs_to(): void
    {
        $returnType = (new ReflectionMethod(EventRaid::class, 'raid'))->getReturnType();

        $this->assertSame(BelongsTo::class, $returnType->getName());
    }

    #[Test]
    public function raid_relation_returns_the_associated_raid(): void
    {
        $event = Event::factory()->create();
        $raid = Raid::factory()->create();
        $event->raids()->attach($raid->id);

        $pivot = EventRaid::where('raid_id', $raid->id)->first();

        $this->assertInstanceOf(Raid::class, $pivot->raid);
        $this->assertSame($raid->id, $pivot->raid->id);
    }
}
