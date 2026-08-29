<?php

namespace Tests\Unit\Models;

use App\Models\Boss;
use App\Models\Event;
use App\Models\EventBoss;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Spatie\EloquentSortable\Sortable;
use Tests\Support\ModelTestCase;

#[Group('raiding')]
class EventBossTest extends ModelTestCase
{
    use RefreshDatabase;

    protected function modelClass(): string
    {
        return Event::class;
    }

    #[Test]
    public function it_extends_pivot(): void
    {
        $this->assertInstanceOf(Pivot::class, new EventBoss);
    }

    #[Test]
    public function it_implements_sortable(): void
    {
        $this->assertInstanceOf(Sortable::class, new EventBoss);
    }

    #[Test]
    public function it_uses_pivot_events_bosses_table(): void
    {
        $pivot = new EventBoss;

        $this->assertSame('pivot_events_bosses', $pivot->getTable());
    }

    #[Test]
    public function it_casts_sort_order_to_integer(): void
    {
        $pivot = new EventBoss;

        $this->assertCasts($pivot, [
            'sort_order' => 'integer',
        ]);
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $this->assertFillable(new EventBoss, [
            'event_id',
            'boss_id',
            'sort_order',
        ]);
    }

    #[Test]
    public function it_declares_fillable_via_attribute(): void
    {
        $this->assertFillableAttribute(new EventBoss, [
            'event_id',
            'boss_id',
            'sort_order',
        ]);
    }

    #[Test]
    public function it_scopes_the_sort_query_to_the_event(): void
    {
        $event = Event::factory()->create();
        $otherEvent = Event::factory()->create();

        $pivot = new EventBoss;
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
        $boss = Boss::factory()->create();

        $event->bosses()->attach($boss->id);

        $this->assertSame(0, EventBoss::where('event_id', $event->id)->value('sort_order'));
    }

    #[Test]
    public function it_keeps_an_explicitly_provided_sort_order_on_create(): void
    {
        $event = Event::factory()->create();
        $boss = Boss::factory()->create();

        $event->bosses()->attach($boss->id, ['sort_order' => 5]);

        $this->assertSame(5, EventBoss::where('event_id', $event->id)->value('sort_order'));
    }

    #[Test]
    public function it_persists_explicit_sort_orders_verbatim_when_syncing_a_mixed_set(): void
    {
        $event = Event::factory()->create();
        [$bossOne, $bossTwo, $bossThree] = Boss::factory()->count(3)->create();

        $event->bosses()->sync([
            $bossOne->id => ['sort_order' => 1],
            $bossThree->id => ['sort_order' => 2],
        ]);

        $event->bosses()->sync([
            $bossOne->id => ['sort_order' => 1],
            $bossTwo->id => ['sort_order' => 2],
            $bossThree->id => ['sort_order' => 3],
        ]);

        $orders = EventBoss::where('event_id', $event->id)->pluck('sort_order', 'boss_id');

        $this->assertSame(1, $orders[$bossOne->id]);
        $this->assertSame(2, $orders[$bossTwo->id]);
        $this->assertSame(3, $orders[$bossThree->id]);
    }

    // ==================== event ====================

    #[Test]
    public function event_method_returns_belongs_to(): void
    {
        $returnType = (new ReflectionMethod(EventBoss::class, 'event'))->getReturnType();

        $this->assertSame(BelongsTo::class, $returnType->getName());
    }

    #[Test]
    public function event_relation_returns_the_associated_event(): void
    {
        $event = Event::factory()->create();
        $boss = Boss::factory()->create();
        $event->bosses()->attach($boss->id);

        $pivot = EventBoss::where('event_id', $event->id)->first();

        $this->assertInstanceOf(Event::class, $pivot->event);
        $this->assertSame($event->id, $pivot->event->id);
    }

    // ==================== boss ====================

    #[Test]
    public function boss_method_returns_belongs_to(): void
    {
        $returnType = (new ReflectionMethod(EventBoss::class, 'boss'))->getReturnType();

        $this->assertSame(BelongsTo::class, $returnType->getName());
    }

    #[Test]
    public function boss_relation_returns_the_associated_boss(): void
    {
        $event = Event::factory()->create();
        $boss = Boss::factory()->create();
        $event->bosses()->attach($boss->id);

        $pivot = EventBoss::where('boss_id', $boss->id)->first();

        $this->assertInstanceOf(Boss::class, $pivot->boss);
        $this->assertSame($boss->id, $pivot->boss->id);
    }
}
