<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\PlayableClassResource;
use App\Models\PlayableClass;
use App\Models\PlayableSpecialization;
use App\Support\MediaLibrary\UnknownBlizzardIcon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class PlayableClassResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $playableClass = PlayableClass::factory()->create();

        $array = (new PlayableClassResource($playableClass))->resolve(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('slug', $array);
        $this->assertArrayHasKey('icon_url', $array);
    }

    #[Test]
    public function it_returns_correct_id(): void
    {
        $playableClass = PlayableClass::factory()->create();

        $array = (new PlayableClassResource($playableClass))->resolve(new Request);

        $this->assertSame($playableClass->id, $array['id']);
    }

    #[Test]
    public function it_returns_correct_name(): void
    {
        $playableClass = PlayableClass::factory()->create(['name' => 'Warrior']);

        $array = (new PlayableClassResource($playableClass))->resolve(new Request);

        $this->assertSame('Warrior', $array['name']);
    }

    #[Test]
    public function it_returns_correct_slug(): void
    {
        $playableClass = PlayableClass::factory()->create(['name' => 'Death Knight']);

        $array = (new PlayableClassResource($playableClass))->resolve(new Request);

        $this->assertSame('death-knight', $array['slug']);
    }

    #[Test]
    public function it_returns_the_questionmark_icon_url_when_no_media_attached(): void
    {
        Storage::fake('s3');
        $playableClass = PlayableClass::factory()->create();

        $array = (new PlayableClassResource($playableClass))->resolve(new Request);

        $this->assertSame(app(UnknownBlizzardIcon::class)->url(), $array['icon_url']);
    }

    #[Test]
    public function it_does_not_expose_extra_keys(): void
    {
        $playableClass = PlayableClass::factory()->create();

        $array = (new PlayableClassResource($playableClass))->resolve(new Request);

        $this->assertCount(4, $array);
    }

    #[Test]
    public function it_returns_icon_url_when_media_attached(): void
    {
        Storage::fake('s3');

        $playableClass = PlayableClass::factory()->create();
        $playableClass->addMediaFromString('BINARY')
            ->usingFileName('classicon_7.jpg')
            ->withCustomProperties(['size' => 56])
            ->toMediaCollection('blizzard_icons');

        $array = (new PlayableClassResource($playableClass))->toArray(new Request);

        $this->assertNotNull($array['icon_url']);
        $this->assertSame(Storage::disk('s3')->url('blizzard-cdn/icons/56/classicon_7.jpg'), $array['icon_url']);
    }

    #[Test]
    public function it_omits_specializations_when_not_loaded(): void
    {
        $playableClass = PlayableClass::factory()->create();

        $array = (new PlayableClassResource($playableClass))->resolve(new Request);

        $this->assertArrayNotHasKey('specializations', $array);
    }

    #[Test]
    public function it_includes_specializations_when_loaded(): void
    {
        $playableClass = PlayableClass::factory()->create();
        PlayableSpecialization::factory()->count(3)->for($playableClass, 'playableClass')->create();

        $playableClass->load('specializations');

        $array = (new PlayableClassResource($playableClass))->resolve(new Request);

        $this->assertArrayHasKey('specializations', $array);
        $this->assertCount(3, $array['specializations']);
    }

    #[Test]
    public function it_does_not_expose_loot_priorities_even_when_loaded(): void
    {
        $playableClass = PlayableClass::factory()->withLootPriorities(1)->create();
        $playableClass->load('lootPriorities');

        $array = (new PlayableClassResource($playableClass))->resolve(new Request);

        $this->assertArrayNotHasKey('loot_priorities', $array);
    }

    // ==================== attendance scope ====================

    #[Test]
    public function the_attendance_scope_emits_only_the_id_name_and_icon_url_even_with_specializations_loaded(): void
    {
        $playableClass = PlayableClass::factory()->create();
        PlayableSpecialization::factory()->count(3)->for($playableClass, 'playableClass')->create();

        $playableClass->load('specializations');

        $array = (new PlayableClassResource($playableClass))->scope('attendance')->resolve(new Request);

        $this->assertSame(['id', 'name', 'icon_url'], array_keys($array));
    }
}
