<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\Gender;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\PlannedAbsenceResource;
use App\Http\Resources\UserResource;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use App\Models\PlayableSpecialization;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class CharacterResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterResource($character))->toArray(new Request);

        $this->assertSame([
            'id',
            'name',
            'slug',
            'level',
            'is_main',
            'is_loot_councillor',
            'portrait_url',
            'planned_absences',
            'playable_class',
            'playable_race',
            'gender',
            'pivot',
            'rank',
            'specializations',
            'linked_characters',
            'user',
        ], array_keys($array));
    }

    #[Test]
    public function it_returns_correct_scalar_fields(): void
    {
        $character = Character::factory()->main()->create(['name' => 'Arthas', 'level' => 70]);

        $array = (new CharacterResource($character))->toArray(new Request);

        $this->assertSame($character->id, $array['id']);
        $this->assertSame('Arthas', $array['name']);
        $this->assertSame('arthas', $array['slug']);
        $this->assertSame(70, $array['level']);
        $this->assertTrue($array['is_main']);
        $this->assertFalse($array['is_loot_councillor']);
    }

    // ==================== gender ====================

    #[Test]
    public function it_returns_null_gender_when_unset(): void
    {
        $character = Character::factory()->create(['gender' => null]);

        $array = (new CharacterResource($character))->toArray(new Request);

        $this->assertNull($array['gender']);
    }

    #[Test]
    public function it_returns_gender_enum_value_when_set(): void
    {
        $character = Character::factory()->create(['gender' => Gender::MALE]);

        $array = (new CharacterResource($character))->toArray(new Request);

        $this->assertSame(Gender::MALE, $array['gender']);
    }

    // ==================== playable class relation ====================

    #[Test]
    public function it_omits_playable_class_when_not_loaded(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterResource($character))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['playable_class']);
    }

    #[Test]
    public function it_includes_playable_class_attributes_when_loaded(): void
    {
        $playableClass = PlayableClass::factory()->create(['name' => 'Warrior']);
        $character = Character::factory()->withPlayableClass($playableClass)->create();

        $array = (new CharacterResource($character->load('playableClass')))->toArray(new Request);

        $this->assertIsArray($array['playable_class']);
        $this->assertSame($playableClass->id, $array['playable_class']['id']);
        $this->assertSame('Warrior', $array['playable_class']['name']);
        $this->assertSame('warrior', $array['playable_class']['slug']);
        $this->assertNull($array['playable_class']['icon_url']);
    }

    #[Test]
    public function it_returns_null_playable_class_when_loaded_but_unset(): void
    {
        $character = Character::factory()->create(['playable_class_id' => null]);

        $array = (new CharacterResource($character->load('playableClass')))->toArray(new Request);

        $this->assertNull($array['playable_class']);
    }

    // ==================== playable race relation ====================

    #[Test]
    public function it_omits_playable_race_when_not_loaded(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterResource($character))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['playable_race']);
    }

    #[Test]
    public function it_includes_playable_race_attributes_when_loaded(): void
    {
        $playableRace = PlayableRace::factory()->create(['name' => 'Orc']);
        $character = Character::factory()->withPlayableRace($playableRace)->create();

        $array = (new CharacterResource($character->load('playableRace')))->toArray(new Request);

        $this->assertSame($playableRace->id, $array['playable_race']['id']);
        $this->assertSame('Orc', $array['playable_race']['name']);
    }

    // ==================== planned absences relation ====================

    #[Test]
    public function it_omits_planned_absences_when_not_loaded(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterResource($character))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['planned_absences']);
    }

    #[Test]
    public function it_includes_planned_absences_as_resource_collection_when_loaded(): void
    {
        $character = Character::factory()->create();
        PlannedAbsence::factory()->count(2)->create(['character_id' => $character->id]);

        $array = (new CharacterResource($character->load('plannedAbsences')))->toArray(new Request);

        $this->assertInstanceOf(AnonymousResourceCollection::class, $array['planned_absences']);
        $this->assertCount(2, $array['planned_absences']);
        $this->assertContainsOnlyInstancesOf(PlannedAbsenceResource::class, $array['planned_absences']);
    }

    // ==================== pivot data ====================

    #[Test]
    public function it_omits_pivot_when_not_from_pivot_table(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterResource($character))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['pivot']);
    }

    #[Test]
    public function it_includes_presence_pivot_when_loaded_via_report(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $character = Character::factory()->create();
        $report->characters()->attach($character, ['presence' => 3]);

        $loadedCharacter = $report->load('characters')->characters->first();

        $array = (new CharacterResource($loadedCharacter))->toArray(new Request);

        $this->assertSame(['presence' => 3, 'is_loot_councillor' => false], $array['pivot']);
    }

    // ==================== rank relation ====================

    #[Test]
    public function it_omits_rank_when_not_loaded(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterResource($character))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['rank']);
    }

    #[Test]
    public function it_includes_guild_rank_attributes_when_loaded(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->for($rank, 'rank')->create();

        $array = (new CharacterResource($character->load('rank')))->toArray(new Request);

        $this->assertSame($rank->id, $array['rank']['id']);
    }

    #[Test]
    public function it_returns_null_rank_when_loaded_but_unset(): void
    {
        $character = Character::factory()->create(['rank_id' => null]);

        $array = (new CharacterResource($character->load('rank')))->toArray(new Request);

        $this->assertNull($array['rank']);
    }

    // ==================== specializations relation ====================

    #[Test]
    public function it_omits_specializations_when_not_loaded(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterResource($character))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['specializations']);
    }

    #[Test]
    public function it_includes_specializations_as_array_when_loaded(): void
    {
        $class = PlayableClass::factory()->create();
        [$raidSpec, $offSpec] = PlayableSpecialization::factory()->count(2)->for($class, 'playableClass')->create()->all();
        $character = Character::factory()->withPlayableClass($class)->create();
        $character->specializations()->attach($raidSpec, ['is_raid_spec' => true]);
        $character->specializations()->attach($offSpec, ['is_raid_spec' => false]);

        $array = (new CharacterResource($character->load('specializations')))->toArray(new Request);

        $isRaidSpecById = array_column($array['specializations'], 'is_raid_spec', 'id');
        ksort($isRaidSpecById);
        $expected = [$raidSpec->id => true, $offSpec->id => false];
        ksort($expected);
        $this->assertSame($expected, $isRaidSpecById);
    }

    // ==================== portrait url ====================

    #[Test]
    public function it_omits_portrait_url_when_media_is_not_loaded(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterResource($character))->resolve(new Request);

        $this->assertArrayNotHasKey('portrait_url', $array);
    }

    #[Test]
    public function it_returns_null_portrait_url_when_no_media_attached(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterResource($character->load('media')))->toArray(new Request);

        $this->assertNull($array['portrait_url']);
    }

    #[Test]
    public function it_returns_storage_portrait_url_when_media_attached(): void
    {
        Storage::fake('public');

        $character = Character::factory()->create();
        $character->addMediaFromString('BINARY')
            ->usingFileName('character_15678.jpg')
            ->withCustomProperties(['size' => 135])
            ->toMediaCollection(Character::MEDIA_COLLECTION);

        $array = (new CharacterResource($character->load('media')))->toArray(new Request);

        $this->assertStringContainsString('/storage/', $array['portrait_url']);
        $this->assertStringContainsString('character_15678.jpg', $array['portrait_url']);
        $this->assertStringNotContainsString('/icons/', $array['portrait_url']);
    }

    // ==================== linked characters relation ====================

    #[Test]
    public function it_omits_linked_characters_when_not_loaded(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterResource($character))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['linked_characters']);
    }

    #[Test]
    public function it_includes_linked_characters_as_array_when_loaded(): void
    {
        $main = Character::factory()->create();
        $alt = Character::factory()->create();
        $alt->linkedCharacters()->attach($main->id);

        $array = (new CharacterResource($alt->load('linkedCharacters')))->toArray(new Request);

        $this->assertSame([$main->id], array_column($array['linked_characters'], 'id'));
    }

    // ==================== user relation ====================

    #[Test]
    public function it_omits_user_when_not_loaded(): void
    {
        $character = Character::factory()->forUser()->create();

        $array = (new CharacterResource($character))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['user']);
    }

    #[Test]
    public function it_includes_user_when_loaded(): void
    {
        $user = User::factory()->create();
        $character = Character::factory()->forUser($user)->create();

        $request = new Request;
        $array = (new CharacterResource($character->load('user.discordRoles')))->toArray($request);

        $this->assertInstanceOf(UserResource::class, $array['user']);
        $this->assertSame($user->id, $array['user']->resolve($request)['id']);
    }

    #[Test]
    public function it_returns_null_user_when_loaded_but_unassigned(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterResource($character->load('user')))->toArray(new Request);

        $this->assertNull($array['user']);
    }

    // ==================== attendance scope ====================

    #[Test]
    public function the_attendance_scope_emits_only_the_keys_the_matrix_reads_even_with_linked_characters_loaded(): void
    {
        $character = $this->loadedCharacter();
        $character->setRelation('linkedCharacters', Character::factory()->count(1)->create());

        $array = (new CharacterResource($character))->scope('attendance')->resolve(new Request);

        $this->assertSame(['id', 'name', 'playable_class', 'rank'], array_keys($array));
    }

    #[Test]
    public function the_attendance_scope_emits_only_the_rank_and_class_fields_the_matrix_reads(): void
    {
        $character = $this->loadedCharacter(rankName: 'Raider', className: 'Druid');

        $array = (new CharacterResource($character))->scope('attendance')->resolve(new Request);

        $this->assertSame(['name' => 'Raider', 'slug' => 'raider'], $array['rank']);
        $this->assertSame(['id', 'name', 'icon_url'], array_keys($array['playable_class']));
        $this->assertSame('Druid', $array['playable_class']['name']);
    }

    #[Test]
    public function the_attendance_scope_omits_rank_and_playable_class_when_not_loaded(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterResource($character))->scope('attendance')->resolve(new Request);

        $this->assertSame(['id', 'name'], array_keys($array));
    }

    /**
     * A character with its rank, playable class and media loaded.
     */
    private function loadedCharacter(string $rankName = 'Raider', string $className = 'Warrior'): Character
    {
        $rank = GuildRank::factory()->create(['name' => $rankName]);
        $playableClass = PlayableClass::factory()->create(['name' => $className]);

        return Character::factory()
            ->for($rank, 'rank')
            ->withPlayableClass($playableClass)
            ->create()
            ->load(['rank', 'playableClass', 'media']);
    }
}
