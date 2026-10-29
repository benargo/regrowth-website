<?php

namespace Tests\Feature\Actions\Characters;

use App\Actions\Characters\FetchBattleNetAccountCharacters;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Exceptions\ApiException;
use App\Http\Integrations\Blizzard\Middleware\EagerlyMirrorAssets;
use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\Requests\Account\GetAccountProfileSummaryRequest;
use App\Http\Integrations\ServedFromCache;
use App\Models\Character;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class FetchBattleNetAccountCharactersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(BlizzardConnector::class, new BlizzardConnector(
            clientId: 'test_id',
            clientSecret: 'test_secret',
            region: Region::EU,
            locale: Region::EU->defaultLocale(),
            redirectUri: 'https://regrowth.test/auth/battlenet/callback',
            eagerlyMirrorAssets: $this->app->make(EagerlyMirrorAssets::class),
            servedFromCache: $this->app->make(ServedFromCache::class),
        ));
    }

    #[Test]
    #[Group('happy-path')]
    public function it_returns_roster_characters_on_the_account_in_that_version_only(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create(['blizzard_namespace' => BlizzardNamespace::ANNIVERSARY]);
        $otherVersion = GameVersion::factory()->fetchableRoster()->create();
        $mine = Character::factory()->forGameVersion($gameVersion)->create(['id' => 52963250, 'name' => 'Auburn']);
        Character::factory()->forGameVersion($otherVersion)->create(['id' => 79938434, 'name' => 'Noonian']);
        Character::factory()->forGameVersion($gameVersion)->create(['id' => 11111111, 'name' => 'Stranger']);
        Saloon::fake([GetAccountProfileSummaryRequest::class => MockResponse::make($this->profileSummaryBody([[[52963250, 'Auburn'], [79938434, 'Noonian'], [22222222, 'Unsynced']]]))]);

        $characters = FetchBattleNetAccountCharacters::run('user-token', $gameVersion);

        $this->assertSame([$mine->id], $characters->modelKeys());
    }

    #[Test]
    public function it_sends_the_versions_profile_namespace(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create(['blizzard_namespace' => BlizzardNamespace::ANNIVERSARY]);
        Saloon::fake([GetAccountProfileSummaryRequest::class => MockResponse::make($this->profileSummaryBody([[]]))]);

        FetchBattleNetAccountCharacters::run('user-token', $gameVersion);

        Saloon::assertSent(fn (Request $request, Response $response): bool => $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === 'profile-classicann-eu');
    }

    #[Test]
    public function it_collects_characters_from_every_wow_account(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $zephyr = Character::factory()->forGameVersion($gameVersion)->create(['id' => 52963250, 'name' => 'Zephyr']);
        $auburn = Character::factory()->forGameVersion($gameVersion)->create(['id' => 79938434, 'name' => 'Auburn']);
        Saloon::fake([GetAccountProfileSummaryRequest::class => MockResponse::make($this->profileSummaryBody([[[52963250, 'Zephyr']], [[79938434, 'Auburn']]]))]);

        $characters = FetchBattleNetAccountCharacters::run('user-token', $gameVersion);

        $this->assertSame([$auburn->id, $zephyr->id], $characters->modelKeys());
    }

    #[Test]
    #[Group('error-handling')]
    public function it_returns_nothing_when_the_account_has_no_characters_in_the_namespace(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        Saloon::fake([GetAccountProfileSummaryRequest::class => MockResponse::make(['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'], 404)]);

        $this->assertTrue(FetchBattleNetAccountCharacters::run('user-token', $gameVersion)->isEmpty());
    }

    #[Test]
    #[Group('error-handling')]
    public function it_throws_when_the_token_is_rejected(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        Saloon::fake([GetAccountProfileSummaryRequest::class => MockResponse::make(['code' => 401, 'type' => 'BLZWEBAPI00000401', 'detail' => 'Unauthorized'], 401)]);

        $this->expectException(ApiException::class);

        FetchBattleNetAccountCharacters::run('user-token', $gameVersion);
    }

    // ==================== helpers ====================

    /**
     * A trimmed `/profile/user/wow` response.
     *
     * @param  list<list<array{0: int, 1: string}>>  $wowAccounts  Each WoW account's `[id, name]` pairs
     * @return array<string, mixed>
     */
    private function profileSummaryBody(array $wowAccounts): array
    {
        return [
            '_links' => [
                'self' => ['href' => 'https://eu.api.blizzard.com/profile/user/wow?namespace=profile-classicann-eu'],
                'user' => ['href' => 'https://eu.api.blizzard.com/profile/user'],
                'profile' => ['href' => 'https://eu.api.blizzard.com/profile/user/wow?namespace=profile-classicann-eu'],
            ],
            'id' => 1922840,
            'wow_accounts' => collect($wowAccounts)->map(fn (array $characters, int $index): array => [
                'id' => 8637580 + $index,
                'characters' => collect($characters)->map(fn (array $character): array => $this->character(...$character))->all(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function character(int $id, string $name): array
    {
        $slug = strtolower($name);

        return [
            'character' => ['href' => "https://eu.api.blizzard.com/profile/wow/character/thunderstrike/{$slug}?namespace=profile-classicann-eu"],
            'protected_character' => ['href' => "https://eu.api.blizzard.com/profile/user/wow/protected-character/6409-{$id}?namespace=profile-classicann-eu"],
            'name' => $name,
            'id' => $id,
            'realm' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/realm/6409?namespace=dynamic-classicann-eu'],
                'name' => 'Thunderstrike',
                'id' => 6409,
                'slug' => 'thunderstrike',
            ],
            'playable_class' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-class/5?namespace=static-2.5.6_68184-classicann-eu'],
                'name' => 'Priest',
                'id' => 5,
            ],
            'playable_race' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-race/1?namespace=static-2.5.6_68184-classicann-eu'],
                'name' => 'Human',
                'id' => 1,
            ],
            'gender' => ['type' => 'FEMALE', 'name' => 'Female'],
            'faction' => ['type' => 'ALLIANCE', 'name' => 'Alliance'],
            'level' => 70,
        ];
    }
}
