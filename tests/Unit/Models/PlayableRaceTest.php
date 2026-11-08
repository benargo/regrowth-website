<?php

namespace Tests\Unit\Models;

use App\Contracts\HasShadowAvatars;
use App\Enums\Faction;
use App\Enums\Gender;
use App\Models\GameVersion;
use App\Models\PlayableRace;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('characters')]
class PlayableRaceTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return PlayableRace::class;
    }

    #[Test]
    public function it_uses_playable_races_table(): void
    {
        $model = new PlayableRace;

        $this->assertSame('playable_races', $model->getTable());
    }

    #[Test]
    public function it_does_not_use_auto_incrementing_primary_key(): void
    {
        $model = new PlayableRace;

        $this->assertSame('id', $model->getKeyName());
        $this->assertFalse($model->getIncrementing());
    }

    #[Test]
    public function it_does_not_have_timestamps(): void
    {
        $model = new PlayableRace;

        $this->assertFalse($model->usesTimestamps());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new PlayableRace;

        $this->assertFillable($model, [
            'id',
            'name',
            'faction',
        ]);
    }

    #[Test]
    public function it_declares_fillable_via_attribute(): void
    {
        $model = new PlayableRace;

        $this->assertFillableAttribute($model, [
            'id',
            'name',
            'faction',
        ]);
    }

    #[Test]
    public function it_casts_faction_to_faction_enum(): void
    {
        $model = new PlayableRace;

        $this->assertCasts($model, ['faction' => Faction::class]);
    }

    #[Test]
    public function it_can_be_created_with_required_attributes(): void
    {
        $playableRace = $this->create(['name' => 'Human']);

        $this->assertTableHas(['name' => 'Human']);
        $this->assertModelExists($playableRace);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $playableRace = $this->create();

        $this->assertNotEmpty($playableRace->name);
        $this->assertModelExists($playableRace);
    }

    // ==================== characters ====================

    #[Test]
    public function characters_returns_has_many_relationship(): void
    {
        $playableRace = new PlayableRace;

        $this->assertInstanceOf(HasMany::class, $playableRace->characters());
    }

    #[Test]
    public function characters_returns_empty_collection_when_none_associated(): void
    {
        $playableRace = $this->create();

        $this->assertCount(0, $playableRace->characters);
    }

    // ==================== shadow avatars ====================

    #[Group('media')]
    #[Test]
    public function shadow_avatar_url_returns_the_avatar_for_the_given_gender(): void
    {
        Storage::fake('s3');
        $playableRace = $this->create(['id' => 6]);
        $playableRace->addMediaFromString('MALE')->usingFileName('6-0.jpg')
            ->withCustomProperties(['gender' => Gender::MALE->id()])
            ->toMediaCollection(HasShadowAvatars::MEDIA_COLLECTION);
        $playableRace->addMediaFromString('FEMALE')->usingFileName('6-1.jpg')
            ->withCustomProperties(['gender' => Gender::FEMALE->id()])
            ->toMediaCollection(HasShadowAvatars::MEDIA_COLLECTION);

        $this->assertStringEndsWith('blizzard-cdn/shadow/avatar/6-1.jpg', $playableRace->shadowAvatarUrl(Gender::FEMALE));
        $this->assertTrue($playableRace->hasShadowAvatar(Gender::MALE));
    }

    #[Group('media')]
    #[Test]
    public function shadow_avatar_url_returns_null_when_the_gender_is_not_stored(): void
    {
        Storage::fake('s3');
        $playableRace = $this->create(['id' => 6]);
        $playableRace->addMediaFromString('MALE')->usingFileName('6-0.jpg')
            ->withCustomProperties(['gender' => Gender::MALE->id()])
            ->toMediaCollection(HasShadowAvatars::MEDIA_COLLECTION);

        $this->assertNull($playableRace->shadowAvatarUrl(Gender::FEMALE));
        $this->assertFalse($playableRace->hasShadowAvatar(Gender::FEMALE));
    }

    #[Group('media')]
    #[Test]
    public function has_all_shadow_avatars_returns_true_when_every_gender_is_stored(): void
    {
        Storage::fake('s3');
        $playableRace = $this->create(['id' => 6]);
        $this->addShadowAvatar($playableRace, Gender::MALE);
        $this->addShadowAvatar($playableRace, Gender::FEMALE);

        $this->assertTrue($playableRace->fresh()->hasAllShadowAvatars());
    }

    #[Group('media')]
    #[Test]
    public function has_all_shadow_avatars_returns_false_when_a_gender_is_missing(): void
    {
        Storage::fake('s3');
        $playableRace = $this->create(['id' => 6]);
        $this->addShadowAvatar($playableRace, Gender::MALE);

        $this->assertFalse($playableRace->fresh()->hasAllShadowAvatars());
    }

    #[Group('media')]
    #[Test]
    public function has_all_shadow_avatars_counts_loaded_media_without_querying(): void
    {
        Storage::fake('s3');
        $playableRace = $this->create(['id' => 6]);
        $this->addShadowAvatar($playableRace, Gender::MALE);
        $this->addShadowAvatar($playableRace, Gender::FEMALE);
        $loadedRace = $playableRace->fresh()->load('media');

        $this->expectsDatabaseQueryCount(0);

        $this->assertTrue($loadedRace->hasAllShadowAvatars());
    }

    private function addShadowAvatar(PlayableRace $playableRace, Gender $gender): void
    {
        $playableRace->addMediaFromString(strtoupper($gender->value))
            ->usingFileName("{$playableRace->id}-{$gender->id()}.jpg")
            ->withCustomProperties(['gender' => $gender->id()])
            ->toMediaCollection(HasShadowAvatars::MEDIA_COLLECTION);
    }

    // ==================== gameVersions ====================

    #[Test]
    public function game_versions_returns_belongs_to_many_relationship(): void
    {
        $playableRace = new PlayableRace;

        $this->assertInstanceOf(BelongsToMany::class, $playableRace->gameVersions());
    }

    #[Test]
    public function game_versions_includes_attached_game_version(): void
    {
        $playableRace = $this->create();
        $gameVersion = GameVersion::factory()->create();

        $playableRace->gameVersions()->attach($gameVersion);

        $this->assertTrue($playableRace->fresh()->gameVersions->contains($gameVersion));
    }
}
