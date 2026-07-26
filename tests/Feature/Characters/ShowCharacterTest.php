<?php

namespace Tests\Feature\Characters;

use App\Contracts\HasCharacterMedia;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterMediaRequest;
use App\Jobs\AttachPortraitToCharacter;
use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
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

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
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
        $character = Character::factory()->withPlayableClass()->withRank()->create();

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

    // ==================== Portrait dispatch ====================

    #[Test]
    public function show_dispatches_portrait_job_when_character_has_no_media(): void
    {
        Bus::fake([AttachPortraitToCharacter::class]);

        $character = Character::factory()->withPlayableClass()->withRank()->create();

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(body: $this->makeTokenResponse(), status: 200),
            GetCharacterMediaRequest::class => MockResponse::make(body: $this->makeMediaResponse(), status: 200),
        ]);

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

        $character = Character::factory()->withPlayableClass()->withRank()->create();

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(body: $this->makeTokenResponse(), status: 200),
            GetCharacterMediaRequest::class => MockResponse::make(body: ['type' => 'BLZWEBAPI00000404'], status: 404),
        ]);

        $this->get(route('characters.show', [
            'character' => $character,
            'slug' => $this->characterSlug($character),
        ]))->assertOk();

        Bus::assertNotDispatched(AttachPortraitToCharacter::class);
    }

    // ==================== Helpers ====================

    private function member(): User
    {
        return User::factory()->withPermissions('view-officer-dashboard')->create();
    }

    private function characterSlug(Character $character): string
    {
        return Str::slug($character->name);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeMediaResponse(): array
    {
        return [
            '_links' => ['self' => ['href' => 'https://eu.api.blizzard.com']],
            'character' => [
                'key' => ['href' => 'https://eu.api.blizzard.com'],
                'name' => 'Testcharacter',
                'id' => 1,
            ],
            'assets' => [
                ['key' => 'avatar', 'value' => 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg'],
            ],
        ];
    }
}
