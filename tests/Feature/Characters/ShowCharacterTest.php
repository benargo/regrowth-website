<?php

namespace Tests\Feature\Characters;

use App\Contracts\HasCharacterMedia;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterMediaRequest;
use App\Jobs\AttachPortraitToCharacter;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
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
            ->has('character')
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
            ->where('character.gender', null)
        );
    }

    // ==================== portrait dispatch ====================

    #[Test]
    public function show_dispatches_portrait_job_when_character_has_no_media(): void
    {
        Bus::fake([AttachPortraitToCharacter::class]);

        $character = Character::factory()->for($this->gameVersion)->withPlayableClass()->withRank()->create();

        $this->mockGetCharacterMedia();
        $this->applyBlizzardMocks();

        $this->get(route('characters.show', [
            'character' => $character,
            'slug' => $this->characterSlug($character),
        ]))->assertOk();

        Bus::assertDispatched(AttachPortraitToCharacter::class, fn ($job) => $job->characterId === $character->id);
    }

    #[Test]
    public function show_does_not_dispatch_portrait_job_when_character_already_has_media(): void
    {
        Bus::fake([AttachPortraitToCharacter::class]);
        Storage::fake('public');

        $character = Character::factory()->withPlayableClass()->withRank()->create();
        $character->addMediaFromString('BINARY')
            ->usingFileName('portrait.jpg')
            ->toMediaCollection(HasCharacterMedia::MEDIA_COLLECTION);

        $this->get(route('characters.show', [
            'character' => $character,
            'slug' => $this->characterSlug($character),
        ]))->assertOk();

        Bus::assertNotDispatched(AttachPortraitToCharacter::class);
    }

    #[Group('error-handling')]
    #[Test]
    public function show_does_not_error_and_does_not_dispatch_when_blizzard_media_request_fails(): void
    {
        Bus::fake([AttachPortraitToCharacter::class]);

        $character = Character::factory()->for($this->gameVersion)->withPlayableClass()->withRank()->create();

        $this->mockGetCharacterMedia(['type' => 'BLZWEBAPI00000404'], 404);
        $this->applyBlizzardMocks();

        $this->get(route('characters.show', [
            'character' => $character,
            'slug' => $this->characterSlug($character),
        ]))->assertOk();

        Bus::assertNotDispatched(AttachPortraitToCharacter::class);
    }

    #[Test]
    public function show_looks_up_the_portrait_on_the_characters_game_version_realm_and_namespace(): void
    {
        Bus::fake([AttachPortraitToCharacter::class]);
        $gameVersion = GameVersion::factory()->create(['realm' => 'Living Flame', 'blizzard_namespace' => BlizzardNamespace::ERA]);
        $character = Character::factory()->withPlayableClass()->withRank()->create(['game_version_id' => $gameVersion->id]);
        $this->mockGetCharacterMedia();
        $this->applyBlizzardMocks();

        $this->actingAs($this->member())->get(route('characters.show', [$character, $character->slug]))->assertOk();

        Saloon::assertSent(fn ($request, $response) => $request instanceof GetCharacterMediaRequest
            && str_contains($request->resolveEndpoint(), '/living-flame/')
            && $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === BlizzardNamespace::ERA->forProfileRequests(Region::from(config('services.blizzard.region'))));
    }

    #[Group('edge-case')]
    #[Test]
    public function show_sends_no_media_request_when_the_character_has_no_realm(): void
    {
        Bus::fake([AttachPortraitToCharacter::class]);
        $character = Character::factory()->withPlayableClass()->withRank()->create(['game_version_id' => null]);
        $this->mockGetCharacterMedia();
        $this->applyBlizzardMocks();

        $this->actingAs($this->member())->get(route('characters.show', [$character, $character->slug]))->assertOk();

        Saloon::assertNotSent(GetCharacterMediaRequest::class);
        Bus::assertNotDispatched(AttachPortraitToCharacter::class);
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
