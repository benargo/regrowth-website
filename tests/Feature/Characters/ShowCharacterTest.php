<?php

namespace Tests\Feature\Characters;

use App\Actions\Characters\RefreshCharacterPortrait;
use App\Contracts\HasCharacterMedia;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterMediaRequest;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Saloon\Laravel\Facades\Saloon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class ShowCharacterTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    private GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->gameVersion = GameVersion::factory()->create(['realm' => 'Thunderstrike', 'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY]);
    }

    #[Test]
    public function show_redirects_to_canonical_url_when_slug_is_missing(): void
    {
        $character = Character::factory()->withPlayableClass()->withRank()->create();

        $response = $this->get(route('characters.show', ['character' => $character]));

        $response->assertRedirect(route('characters.show', [
            'character' => $character,
            'slug' => $this->characterSlug($character),
        ]));
        $response->assertStatus(303);
    }

    #[Test]
    public function show_redirects_to_canonical_url_when_slug_is_wrong(): void
    {
        $character = Character::factory()->withPlayableClass()->withRank()->create();

        $response = $this->get(route('characters.show', [
            'character' => $character,
            'slug' => 'wrong-slug',
        ]));

        $response->assertRedirect(route('characters.show', [
            'character' => $character,
            'slug' => $this->characterSlug($character),
        ]));
        $response->assertStatus(303);
    }

    #[Test]
    public function show_is_accessible_without_authentication(): void
    {
        Storage::fake('public');

        $character = Character::factory()->withPlayableClass()->withRank()->create();
        $character->addMediaFromString('BINARY')
            ->usingFileName('portrait.jpg')
            ->toMediaCollection(HasCharacterMedia::MEDIA_COLLECTION);

        $response = $this->get(route('characters.show', [
            'character' => $character,
            'slug' => $this->characterSlug($character),
        ]));

        $response->assertOk();
    }

    #[Test]
    public function show_renders_character_overview(): void
    {
        $character = Character::factory()->withPlayableClass()->withRank()->create();
        $user = $this->member();

        $this->mockGetCharacterMedia();
        $this->applyBlizzardMocks();

        $response = $this->actingAs($user)->get(route('characters.show', [
            'character' => $character,
            'slug' => $this->characterSlug($character),
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Roster/Characters/Show')
            ->has('character.data')
            ->missing('recent_reports')
            ->loadDeferredProps(fn (Assert $reload) => $reload->has('recent_reports'))
        );
    }

    #[Test]
    public function show_renders_when_character_gender_is_null(): void
    {
        $character = Character::factory()->withPlayableClass()->withRank()->withPlayableRace()->create(['gender' => null]);

        $this->mockGetCharacterMedia();
        $this->applyBlizzardMocks();

        $response = $this->get(route('characters.show', [
            'character' => $character,
            'slug' => $this->characterSlug($character),
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Roster/Characters/Show')
            ->where('character.data.gender', null)
        );
    }

    // ==================== portrait dispatch ====================

    #[Test]
    public function show_queues_a_portrait_refresh_without_calling_blizzard_during_the_request(): void
    {
        Queue::fake();
        $character = Character::factory()->for($this->gameVersion)->withPlayableClass()->withRank()->create();
        $this->mockGetCharacterMedia();
        $this->applyBlizzardMocks();

        $this->get(route('characters.show', [
            'character' => $character,
            'slug' => $this->characterSlug($character),
        ]))->assertOk();

        RefreshCharacterPortrait::assertPushed(fn (RefreshCharacterPortrait $action, array $parameters): bool => $parameters[0]->is($character));
        Saloon::assertNotSent(GetCharacterMediaRequest::class);
    }

    // ==================== show — roster link ====================

    #[Test]
    public function show_links_back_to_the_characters_own_game_version_roster(): void
    {
        $forever = GameVersion::factory()->fetchableRoster()->create(['slug' => 'forever', 'realm' => 'Dreamscythe']);
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'newer', 'realm' => 'Nightslayer', 'release_date' => Carbon::now()->subDay()]);
        $character = Character::factory()->for($forever)->withPlayableClass()->withRank()->create();
        $this->mockGetCharacterMedia();
        $this->applyBlizzardMocks();

        $response = $this->get(route('characters.show', [$character, $character->slug]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('roster_url', route('roster.index', ['game_version' => 'forever']))
        );
    }

    #[Group('edge-case')]
    #[Test]
    #[TestWith([false])]
    #[TestWith([true])]
    public function show_links_back_to_the_default_roster_when_the_characters_version_has_no_current_roster(bool $hasGameVersion): void
    {
        $character = Character::factory()->withPlayableClass()->withRank()->create([
            'game_version_id' => $hasGameVersion ? $this->gameVersion->id : null,
        ]);
        $this->gameVersion->update(['release_date' => Carbon::now()->addMonth()]);
        $this->mockGetCharacterMedia();
        $this->applyBlizzardMocks();

        $response = $this->get(route('characters.show', [$character, $character->slug]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('roster_url', route('roster.index'))
        );
    }

    // ==================== helpers ====================

    private function member(): User
    {
        return User::factory()->withPermissions('view-officer-dashboard')->create();
    }

    private function characterSlug(Character $character): string
    {
        return Str::slug($character->name);
    }
}
