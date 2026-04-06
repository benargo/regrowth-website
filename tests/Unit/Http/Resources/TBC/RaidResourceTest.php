<?php

namespace Tests\Unit\Http\Resources\TBC;

use App\Http\Resources\TBC\RaidResource;
use App\Models\TBC\Phase;
use App\Models\TBC\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
    public function it_returns_difficulty(): void
    {
        $raid = Raid::factory()->heroic()->create();

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertSame('Heroic', $array['difficulty']);
    }

    #[Test]
    public function it_returns_max_players(): void
    {
        $raid = Raid::factory()->twentyFivePlayer()->create();

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertSame(25, $array['max_players']);
    }

    #[Test]
    public function it_includes_phase_when_loaded(): void
    {
        $raid = Raid::factory()->create();
        $raid->load('phase');

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertArrayHasKey('phase', $array);
        $this->assertInstanceOf(Phase::class, $array['phase']);
    }

    #[Test]
    public function it_excludes_phase_when_not_loaded(): void
    {
        $raid = Raid::factory()->create();

        $array = (new RaidResource($raid))->resolve(new Request);

        $this->assertArrayNotHasKey('phase', $array);
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
    public function it_includes_items_when_loaded(): void
    {
        $raid = Raid::factory()->withItems(2)->create();
        $raid->load('items');

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertArrayHasKey('items', $array);
        $this->assertCount(2, $array['items']);
    }

    #[Test]
    public function it_excludes_items_when_not_loaded(): void
    {
        $raid = Raid::factory()->withItems(2)->create();

        $array = (new RaidResource($raid))->resolve(new Request);

        $this->assertArrayNotHasKey('items', $array);
    }

    #[Test]
    public function it_includes_comments_when_loaded(): void
    {
        $raid = Raid::factory()->withComments(2)->create();
        $raid->load('comments');

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertArrayHasKey('comments', $array);
        $this->assertCount(2, $array['comments']);
    }

    #[Test]
    public function it_excludes_comments_when_not_loaded(): void
    {
        $raid = Raid::factory()->withComments(2)->create();

        $array = (new RaidResource($raid))->resolve(new Request);

        $this->assertArrayNotHasKey('comments', $array);
    }

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $raid = Raid::factory()->create();

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('difficulty', $array);
        $this->assertArrayHasKey('max_players', $array);
    }
}
