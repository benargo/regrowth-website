<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\PlayableSpecRole;
use App\Http\Resources\PlayableSpecializationResource;
use App\Models\Character;
use App\Models\PlayableClass;
use App\Models\PlayableSpecialization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayableSpecializationResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $spec = PlayableSpecialization::factory()->create();

        $array = (new PlayableSpecializationResource($spec))->resolve(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('role', $array);
        $this->assertArrayHasKey('icon_url', $array);
    }

    #[Test]
    public function it_returns_correct_scalar_fields(): void
    {
        $class = PlayableClass::factory()->create();
        $spec = PlayableSpecialization::factory()
            ->for($class, 'playableClass')
            ->create(['name' => 'Holy', 'role' => PlayableSpecRole::healer]);

        $array = (new PlayableSpecializationResource($spec))->resolve(new Request);

        $this->assertSame($spec->id, $array['id']);
        $this->assertSame('Holy', $array['name']);
        $this->assertSame(PlayableSpecRole::healer->value, $array['role']);
    }

    #[Test]
    public function it_returns_null_icon_url_when_no_media_attached(): void
    {
        $spec = PlayableSpecialization::factory()->create();

        $array = (new PlayableSpecializationResource($spec))->resolve(new Request);

        $this->assertNull($array['icon_url']);
    }

    #[Test]
    public function it_omits_is_raid_spec_when_not_loaded_via_pivot_table(): void
    {
        $spec = PlayableSpecialization::factory()->create();

        $array = (new PlayableSpecializationResource($spec))->resolve(new Request);

        $this->assertArrayNotHasKey('is_raid_spec', $array);
    }

    #[Test]
    public function it_includes_is_raid_spec_when_loaded_via_character(): void
    {
        $class = PlayableClass::factory()->create();
        $spec = PlayableSpecialization::factory()->for($class, 'playableClass')->create();
        $character = Character::factory()->withPlayableClass($class)->create();
        $character->specializations()->attach($spec, ['is_raid_spec' => true]);

        $loadedCharacter = $character->load('specializations');
        $loadedSpec = $loadedCharacter->specializations->first();

        $array = (new PlayableSpecializationResource($loadedSpec))->resolve(new Request);

        $this->assertArrayHasKey('is_raid_spec', $array);
        $this->assertTrue($array['is_raid_spec']);
    }
}
