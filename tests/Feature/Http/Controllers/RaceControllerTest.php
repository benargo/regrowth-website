<?php

namespace Tests\Feature\Http\Controllers;

use App\Actions\Characters\RefreshCharacterPortrait;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\RacePlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class RaceControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_the_race_page_for_guests(): void
    {
        GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::FOREVER]);

        $this->get(route('race.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Race/Index')
                ->has('race.racers.data')
            );
    }

    #[Test]
    public function it_sends_placement_characters_unwrapped(): void
    {
        $forever = GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::FOREVER]);
        $character = Character::factory()->forGameVersion($forever)->create(['level' => 60, 'name' => 'Zed']);
        RacePlacement::factory()->create([
            'game_version_id' => $forever->id,
            'character_id' => $character->id,
            'position' => 1,
        ]);

        $this->get(route('race.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Race/Index')
                ->where('race.placements.data.0.character.name', 'Zed')
            );
    }

    #[Test]
    public function it_sends_every_holder_of_a_tied_medal(): void
    {
        $forever = GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::FOREVER]);

        foreach (['Borin', 'Aelin'] as $name) {
            $character = Character::factory()->forGameVersion($forever)->create(['level' => 60, 'name' => $name]);
            RacePlacement::factory()->create([
                'game_version_id' => $forever->id,
                'character_id' => $character->id,
                'position' => 1,
            ]);
        }

        $this->get(route('race.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('race.placements.data', 2)
                ->where('race.placements.data.0.medal', 'gold')
                ->where('race.placements.data.0.character.name', 'Aelin')
                ->where('race.placements.data.1.medal', 'gold')
                ->where('race.placements.data.1.character.name', 'Borin')
            );
    }

    #[Test]
    public function it_renders_with_no_race_when_forever_is_missing(): void
    {
        $this->get(route('race.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Race/Index')->where('race', null));
    }

    #[Test]
    public function it_does_not_show_the_realm_tagline(): void
    {
        config(['services.blizzard.realm.name' => 'Thunderstrike']);
        $forever = GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::FOREVER]);
        $placed = Character::factory()->forGameVersion($forever)->create(['level' => 60, 'name' => 'Zed']);
        Character::factory()->forGameVersion($forever)->create(['level' => 58, 'name' => 'Yan']);
        RacePlacement::factory()->create([
            'game_version_id' => $forever->id,
            'character_id' => $placed->id,
            'position' => 1,
        ]);

        $page = json_encode($this->get(route('race.index'))->viewData('page'));

        $this->assertStringContainsString('"name":"Zed"', $page);
        $this->assertStringContainsString('"name":"Yan"', $page);
        $this->assertStringNotContainsString('Thunderstrike', $page);
    }

    #[Test]
    public function it_queues_a_portrait_fetch_for_each_racer_without_a_portrait(): void
    {
        Queue::fake();
        Storage::fake('s3');
        $forever = GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::FOREVER]);
        $withoutPortrait = Character::factory()->forGameVersion($forever)->create();
        $this->withPortrait(Character::factory()->forGameVersion($forever)->create());

        $this->get(route('race.index'))->assertOk();

        RefreshCharacterPortrait::assertPushed(1);
        RefreshCharacterPortrait::assertPushed(fn (RefreshCharacterPortrait $action, array $parameters): bool => $parameters[0]->is($withoutPortrait));
    }

    #[Test]
    public function it_does_not_requeue_a_racer_whose_portrait_is_known_missing(): void
    {
        Queue::fake();
        Storage::fake('s3');
        $forever = GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::FOREVER]);
        $known = Character::factory()->forGameVersion($forever)->create();
        $known->rememberPortraitMissing();
        $unknown = Character::factory()->forGameVersion($forever)->create();

        $this->get(route('race.index'))->assertOk();

        RefreshCharacterPortrait::assertPushed(1);
        RefreshCharacterPortrait::assertPushed(fn (RefreshCharacterPortrait $action, array $parameters): bool => $parameters[0]->is($unknown));
    }

    #[Test]
    public function it_queues_nothing_when_forever_is_missing(): void
    {
        Queue::fake();

        $this->get(route('race.index'))->assertOk();

        RefreshCharacterPortrait::assertNotPushed();
    }

    #[Test]
    public function it_does_not_send_character_portraits_until_requested(): void
    {
        GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::FOREVER]);

        $this->get(route('race.index'))
            ->assertInertia(fn (Assert $page) => $page->missing('character_portraits'));
    }

    #[Test]
    public function it_maps_racers_to_their_portraits_on_request_without_rebuilding_the_race(): void
    {
        Queue::fake();
        Storage::fake('s3');
        $forever = GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::FOREVER]);
        $withPortrait = $this->withPortrait(Character::factory()->forGameVersion($forever)->create(['name' => 'Amy']));
        $withoutPortrait = Character::factory()->forGameVersion($forever)->create(['name' => 'Bob']);
        Character::factory()->forGameVersion(GameVersion::factory()->create())->create();

        $this->get(route('race.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('character_portraits', fn (Assert $reload) => $reload
                    ->where('character_portraits', [
                        $withPortrait->id => $withPortrait->getFirstMediaUrl(Character::MEDIA_COLLECTION),
                        $withoutPortrait->id => null,
                    ])
                    ->missing('race')
                )
            );
    }

    private function withPortrait(Character $character): Character
    {
        $character->addMediaFromString('BINARY')
            ->usingFileName("character_{$character->id}.jpg")
            ->toMediaCollection(Character::MEDIA_COLLECTION);

        return $character;
    }
}
