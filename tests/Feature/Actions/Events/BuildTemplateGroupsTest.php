<?php

namespace Tests\Feature\Actions\Events;

use App\Actions\Events\BuildTemplateGroups;
use App\Models\Event;
use App\Models\Raid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class BuildTemplateGroupsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_no_groups_for_no_templates(): void
    {
        $this->assertSame([], BuildTemplateGroups::run(new Collection));
    }

    #[Test]
    public function it_returns_no_groups_when_no_templates_have_raids(): void
    {
        Event::factory()->template()->create();

        $this->assertSame([], $this->buildGroups());
    }

    #[Test]
    #[Group('happy-path')]
    public function it_groups_templates_by_raid(): void
    {
        $raid = Raid::factory()->create();
        $template = Event::factory()->template()->create(['title' => 'SSC Setup']);
        $template->raids()->attach($raid, ['sort_order' => 1]);

        $groups = $this->buildGroups();

        $this->assertCount(1, $groups);
        $this->assertSame($raid->id, $groups[0]['raid']['id']);
        $this->assertSame(['SSC Setup'], array_column($groups[0]['templates'], 'title'));
    }

    #[Test]
    public function it_limits_each_template_to_the_template_scope(): void
    {
        $template = Event::factory()->template()->create();
        $template->raids()->attach(Raid::factory()->create(), ['sort_order' => 1]);

        $groups = $this->buildGroups();

        $this->assertSame(['id', 'title', 'updated_at', 'raids'], array_keys($groups[0]['templates'][0]));
    }

    #[Test]
    public function it_places_a_template_under_every_raid_it_belongs_to(): void
    {
        $raidA = Raid::factory()->create();
        $raidB = Raid::factory()->create();
        $template = Event::factory()->template()->create(['title' => 'Multi-Raid Template']);
        $template->raids()->attach([$raidA->id => ['sort_order' => 1], $raidB->id => ['sort_order' => 2]]);

        $groups = $this->buildGroups();

        $this->assertSame([$raidA->id, $raidB->id], array_map(fn (array $group) => $group['raid']['id'], $groups));
        $this->assertSame(['Multi-Raid Template'], array_column($groups[0]['templates'], 'title'));
        $this->assertSame(['Multi-Raid Template'], array_column($groups[1]['templates'], 'title'));
    }

    #[Test]
    public function it_places_multiple_templates_under_the_same_raid(): void
    {
        $raid = Raid::factory()->create();
        Event::factory()->template()->create(['title' => 'Alpha'])->raids()->attach($raid, ['sort_order' => 1]);
        Event::factory()->template()->create(['title' => 'Beta'])->raids()->attach($raid, ['sort_order' => 1]);

        $groups = $this->buildGroups();

        $this->assertCount(1, $groups);
        $this->assertSame(['Alpha', 'Beta'], array_column($groups[0]['templates'], 'title'));
    }

    #[Test]
    public function it_orders_groups_by_the_first_template_each_raid_appears_in(): void
    {
        $raidA = Raid::factory()->create();
        $raidB = Raid::factory()->create();
        Event::factory()->template()->create(['title' => 'Alpha'])->raids()->attach($raidB, ['sort_order' => 1]);
        Event::factory()->template()->create(['title' => 'Beta'])->raids()->attach($raidA, ['sort_order' => 1]);

        $groups = $this->buildGroups();

        $this->assertSame([$raidB->id, $raidA->id], array_map(fn (array $group) => $group['raid']['id'], $groups));
    }

    /**
     * Build the groups for every template, ordered by title, and resolve them.
     *
     * @return list<array{raid: array<string, mixed>, templates: list<array<string, mixed>>}>
     */
    private function buildGroups(): array
    {
        $request = new Request;
        $templates = Event::templates()->with('raids')->orderBy('title')->get();

        return array_map(fn (array $group): array => [
            'raid' => $group['raid']->resolve($request),
            'templates' => $group['templates']->resolve($request),
        ], BuildTemplateGroups::run($templates));
    }
}
