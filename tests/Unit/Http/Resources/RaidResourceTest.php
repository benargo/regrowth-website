<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\RaidBackground;
use App\Http\Resources\RaidResource;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class RaidResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_id(): void
    {
        $raid = Raid::factory()->create();

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertSame($raid->id, $array['id']);
    }

    #[Test]
    public function it_returns_name(): void
    {
        $raid = Raid::factory()->create(['name' => 'Karazhan']);

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertSame('Karazhan', $array['name']);
    }

    #[Test]
    public function it_does_not_return_difficulty(): void
    {
        $raid = Raid::factory()->heroic()->create();

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertArrayNotHasKey('difficulty', $array);
    }

    #[Test]
    public function it_returns_max_players(): void
    {
        $raid = Raid::factory()->twentyFivePlayer()->create();

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertSame(25, $array['max_players']);
    }

    #[Test]
    public function it_includes_phase_number_when_phase_loaded(): void
    {
        $raid = Raid::factory()->create();
        $raid->load('phase');

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertArrayHasKey('phase_number', $array);
        $this->assertSame($raid->phase->number, $array['phase_number']);
    }

    #[Test]
    public function it_excludes_phase_number_when_phase_not_loaded(): void
    {
        $raid = Raid::factory()->create();

        $array = (new RaidResource($raid))->resolve(new Request);

        $this->assertArrayNotHasKey('phase_number', $array);
    }

    #[Test]
    public function it_includes_bosses_when_loaded(): void
    {
        $raid = Raid::factory()->withBosses(3)->create();
        $raid->load('bosses');

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertArrayHasKey('bosses', $array);
        $this->assertCount(3, $array['bosses']);
    }

    #[Test]
    public function it_excludes_bosses_when_not_loaded(): void
    {
        $raid = Raid::factory()->withBosses(3)->create();

        $array = (new RaidResource($raid))->resolve(new Request);

        $this->assertArrayNotHasKey('bosses', $array);
    }

    #[Test]
    public function it_returns_slug(): void
    {
        $raid = Raid::factory()->create(['name' => 'Karazhan']);

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertArrayHasKey('slug', $array);
        $this->assertSame($raid->slug, $array['slug']);
    }

    #[Test]
    public function it_returns_max_loot_councillors_when_set(): void
    {
        $raid = Raid::factory()->withLootCouncillors(3)->create();

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertSame(3, $array['max_loot_councillors']);
    }

    #[Test]
    public function it_returns_null_for_max_loot_councillors_when_not_set(): void
    {
        $raid = Raid::factory()->create();

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertNull($array['max_loot_councillors']);
    }

    #[Test]
    public function it_returns_color(): void
    {
        $raid = Raid::factory()->create();

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertArrayHasKey('color', $array);
        $this->assertSame($raid->color, $array['color']);
    }

    #[Test]
    public function it_returns_background_css_class_value(): void
    {
        $raid = Raid::factory()->withBackground(RaidBackground::Karazhan)->create();

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertSame('bg-raid-karazhan', $array['background']);
    }

    #[Test]
    public function it_returns_null_for_background_when_not_set(): void
    {
        $raid = Raid::factory()->create();

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertNull($array['background']);
    }

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $raid = Raid::factory()->create();

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('slug', $array);
        $this->assertArrayHasKey('color', $array);
        $this->assertArrayHasKey('background', $array);
        $this->assertArrayHasKey('max_players', $array);
        $this->assertArrayHasKey('max_loot_councillors', $array);
    }
}
