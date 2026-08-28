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
    public function it_auto_assigns_the_next_sort_order_per_event(): void
    {
        $event = Event::factory()->create();
        $bossOne = Boss::factory()->create();
        $bossTwo = Boss::factory()->create();

        $event->bosses()->attach($bossOne->id);
        $event->bosses()->attach($bossTwo->id);

        $orders = EventBoss::where('event_id', $event->id)
            ->orderBy('boss_id')
            ->pluck('sort_order', 'boss_id');

        $this->assertSame(1, $orders[$bossOne->id]);
        $this->assertSame(2, $orders[$bossTwo->id]);
    }

    #[Test]
    public function it_overrides_an_explicitly_provided_sort_order_on_create(): void
    {
        $event = Event::factory()->create();
        $boss = Boss::factory()->create();

        $event->bosses()->attach($boss->id, ['sort_order' => 5]);

        $this->assertSame(1, EventBoss::where('event_id', $event->id)->value('sort_order'));
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
