<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\EventTemplateCollection;
use App\Models\Event;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventTemplateCollectionTest extends TestCase
{
    use RefreshDatabase;

    // ==================== toArray ====================

    #[Test]
    public function it_returns_templates_and_raid_groups_keys(): void
    {
        $array = (new EventTemplateCollection(collect()))->toArray(new Request);

        $this->assertArrayHasKey('templates', $array);
        $this->assertArrayHasKey('raidGroups', $array);
    }

    #[Test]
    public function it_returns_empty_templates_and_raid_groups_for_empty_collection(): void
    {
        $array = (new EventTemplateCollection(collect()))->toArray(new Request);

        $this->assertSame([], $array['templates']);
        $this->assertSame([], $array['raidGroups']);
    }

    #[Test]
    public function it_includes_each_template_in_templates_array(): void
    {
        $templates = Event::factory()->template()->count(3)->create();

        $array = (new EventTemplateCollection($templates))->toArray(new Request);

        $this->assertCount(3, $array['templates']);
    }

    #[Test]
    public function it_returns_correct_template_shape(): void
    {
        $template = Event::factory()->template()->create(['title' => 'Weekly Raid Template']);

        $array = (new EventTemplateCollection(collect([$template])))->toArray(new Request);

        $item = $array['templates'][0];
        $this->assertSame($template->id, $item['id']);
        $this->assertSame('Weekly Raid Template', $item['title']);
        $this->assertArrayHasKey('updated_at', $item);
        $this->assertArrayHasKey('raids', $item);
    }

    #[Test]
    public function it_includes_raids_for_each_template(): void
    {
        $template = Event::factory()->template()->create();
        $raid = Raid::factory()->create();
        $template->raids()->attach($raid);

        $array = (new EventTemplateCollection(collect([$template])))->toArray(new Request);

        $this->assertCount(1, $array['templates'][0]['raids']);
    }

    #[Test]
    public function it_returns_iso8601_updated_at_in_templates(): void
    {
        $template = Event::factory()->template()->create();

        $array = (new EventTemplateCollection(collect([$template])))->toArray(new Request);

        $updatedAt = $array['templates'][0]['updated_at'];
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $updatedAt);
    }

    // ==================== buildRaidGroups ====================

    #[Test]
    public function it_returns_empty_raid_groups_when_no_templates_have_raids(): void
    {
        $template = Event::factory()->template()->create();

        $array = (new EventTemplateCollection(collect([$template])))->toArray(new Request);

        $this->assertSame([], $array['raidGroups']);
    }

    #[Test]
    public function it_groups_templates_by_raid(): void
    {
        $raid1 = Raid::factory()->create();
        $raid2 = Raid::factory()->create();

        $template1 = Event::factory()->template()->create();
        $template2 = Event::factory()->template()->create();
        $template1->raids()->attach($raid1);
        $template2->raids()->attach($raid2);

        $array = (new EventTemplateCollection(collect([$template1, $template2])))->toArray(new Request);

        $this->assertCount(2, $array['raidGroups']);
    }

    #[Test]
    public function it_returns_correct_raid_group_shape(): void
    {
        $raid = Raid::factory()->create();
        $template = Event::factory()->template()->create();
        $template->raids()->attach($raid);

        $array = (new EventTemplateCollection(collect([$template])))->toArray(new Request);

        $group = $array['raidGroups'][0];
        $this->assertArrayHasKey('raid', $group);
        $this->assertArrayHasKey('templates', $group);
        $this->assertSame($raid->id, $group['raid']['id']);
    }

    #[Test]
    public function it_places_template_under_all_raids_it_belongs_to(): void
    {
        $raid1 = Raid::factory()->create();
        $raid2 = Raid::factory()->create();
        $template = Event::factory()->template()->create(['title' => 'Multi-Raid Template']);
        $template->raids()->attach([$raid1->id, $raid2->id]);

        $array = (new EventTemplateCollection(collect([$template])))->toArray(new Request);

        $this->assertCount(2, $array['raidGroups']);

        $groupedTitles = array_map(fn ($group) => $group['templates'][0]['title'], $array['raidGroups']);
        $this->assertContains('Multi-Raid Template', $groupedTitles);
    }

    #[Test]
    public function it_places_multiple_templates_under_the_same_raid(): void
    {
        $raid = Raid::factory()->create();
        $template1 = Event::factory()->template()->create();
        $template2 = Event::factory()->template()->create();
        $template1->raids()->attach($raid);
        $template2->raids()->attach($raid);

        $array = (new EventTemplateCollection(collect([$template1, $template2])))->toArray(new Request);

        $this->assertCount(1, $array['raidGroups']);
        $this->assertCount(2, $array['raidGroups'][0]['templates']);
    }
}
