<?php

namespace Tests\Feature\Actions\Characters;

use App\Actions\Characters\MapCharacterPortraits;
use App\Contracts\HasCharacterMedia;
use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class MapCharacterPortraitsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_maps_each_character_to_its_portrait_url(): void
    {
        Storage::fake('s3');
        $character = Character::factory()->create();
        $character->addMediaFromString('BINARY')
            ->usingFileName('portrait.jpg')
            ->toMediaCollection(HasCharacterMedia::MEDIA_COLLECTION);

        $result = MapCharacterPortraits::run(Character::whereKey($character->id));

        $this->assertEquals(
            (object) [$character->id => $character->fresh()->getFirstMediaUrl(HasCharacterMedia::MEDIA_COLLECTION)],
            $result,
        );
        $this->assertNotEmpty($result->{$character->id});
    }

    #[Test]
    public function it_maps_a_character_without_a_portrait_to_null(): void
    {
        $character = Character::factory()->create();

        $result = MapCharacterPortraits::run(Character::whereKey($character->id));

        $this->assertEquals((object) [$character->id => null], $result);
    }

    #[Test]
    public function it_maps_only_the_given_query(): void
    {
        $included = Character::factory()->create();
        $excluded = Character::factory()->create();

        $result = MapCharacterPortraits::run(Character::whereKey($included->id));

        $this->assertTrue(property_exists($result, (string) $included->id));
        $this->assertFalse(property_exists($result, (string) $excluded->id));
    }

    #[Test]
    public function it_returns_an_empty_object_for_no_characters(): void
    {
        $this->assertEquals((object) [], MapCharacterPortraits::run(Character::whereKey([])));
    }
}
