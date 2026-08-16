<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\LootCouncillorCollection;
use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class LootCouncillorCollectionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $characters = Character::factory()->count(2)->main()->lootCouncillor()->withUniqueName()->create();

        $array = (new LootCouncillorCollection($characters))->resolve(new Request);

        $this->assertArrayHasKey('data', $array);
        $this->assertArrayHasKey('meta', $array);
        $this->assertSame(['total', 'mains', 'alts'], array_keys($array['meta']));
    }

    #[Test]
    public function it_only_includes_mains_in_data(): void
    {
        $main = Character::factory()->main()->lootCouncillor()->withUniqueName()->create();
        Character::factory()->lootCouncillor()->withUniqueName()->create();

        $characters = Character::all();

        $array = (new LootCouncillorCollection($characters))->resolve(new Request);

        $this->assertCount(1, $array['data']);
        $this->assertSame($main->id, $array['data'][0]['id']);
    }

    #[Test]
    public function it_sorts_mains_by_name(): void
    {
        Character::factory()->main()->lootCouncillor()->create(['name' => 'Zarok']);
        Character::factory()->main()->lootCouncillor()->create(['name' => 'Anduin']);

        $characters = Character::all();

        $array = (new LootCouncillorCollection($characters))->resolve(new Request);

        $this->assertSame(['Anduin', 'Zarok'], array_column($array['data'], 'name'));
    }

    #[Test]
    public function it_counts_total_as_mains_plus_alts(): void
    {
        Character::factory()->count(2)->main()->lootCouncillor()->withUniqueName()->create();
        Character::factory()->count(3)->lootCouncillor()->withUniqueName()->create();

        $characters = Character::all();

        $array = (new LootCouncillorCollection($characters))->resolve(new Request);

        $this->assertSame(5, $array['meta']['total']);
    }

    #[Test]
    public function it_counts_mains_separately_from_alts(): void
    {
        Character::factory()->count(2)->main()->lootCouncillor()->withUniqueName()->create();
        Character::factory()->count(3)->lootCouncillor()->withUniqueName()->create();

        $characters = Character::all();

        $array = (new LootCouncillorCollection($characters))->resolve(new Request);

        $this->assertSame(2, $array['meta']['mains']);
        $this->assertSame(3, $array['meta']['alts']);
    }

    #[Test]
    public function it_returns_empty_data_and_zeroed_meta_for_an_empty_collection(): void
    {
        $array = (new LootCouncillorCollection(Character::query()->get()))->resolve(new Request);

        $this->assertSame([], $array['data']);
        $this->assertSame(['total' => 0, 'mains' => 0, 'alts' => 0], $array['meta']);
    }

    #[Test]
    public function it_is_not_wrapped_in_a_data_key_by_default_wrapper(): void
    {
        $this->assertNull(LootCouncillorCollection::$wrap);
    }
}
