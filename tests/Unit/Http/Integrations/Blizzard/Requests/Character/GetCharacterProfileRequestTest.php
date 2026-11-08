<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\Character;

use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Data\Characters\CharacterProfileData;
use App\Http\Integrations\Blizzard\Exceptions\RealmRequiredException;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Spatie\LaravelData\Optional;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class GetCharacterProfileRequestTest extends BlizzardTestCase
{
    #[Test]
    public function it_casts_response_to_character_profile_data(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetCharacterProfileRequest::class => MockResponse::make(body: [
                'id' => 12345,
                'name' => 'Foo',
                'gender' => ['type' => 'MALE', 'name' => 'Male'],
                'faction' => ['type' => 'ALLIANCE', 'name' => 'Alliance'],
                'race' => ['key' => ['href' => 'r'], 'name' => 'Human', 'id' => 1],
                'character_class' => ['key' => ['href' => 'c'], 'name' => 'Warrior', 'id' => 1],
                'realm' => ['key' => ['href' => 'rm'], 'name' => 'Thunderstrike', 'id' => 1234],
                'level' => 60,
                'last_login_timestamp' => 1716900000000,
                'average_item_level' => 220,
                'equipped_item_level' => 220,
            ], status: 200),
        ]);

        $response = $this->makeConnector()->send(new GetCharacterProfileRequest('thunderstrike', 'foo'));

        /** @var CharacterProfileData $dto */
        $dto = $response->dto();
        $this->assertInstanceOf(CharacterProfileData::class, $dto);
        $this->assertSame(12345, $dto->id);
        $this->assertSame('Foo', $dto->name);
        $this->assertSame('Warrior', $dto->characterClass->name);
        $this->assertSame(60, $dto->level);
        $this->assertSame(220, $dto->averageItemLevel);
        $this->assertInstanceOf(Optional::class, $dto->guild);
    }

    #[Test]
    public function it_lowercases_character_name_in_url(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetCharacterProfileRequest::class => MockResponse::make(body: [
                'id' => 1, 'name' => 'Foo',
                'gender' => ['type' => 'MALE', 'name' => 'Male'],
                'faction' => ['type' => 'ALLIANCE', 'name' => 'Alliance'],
                'race' => ['key' => ['href' => 'r'], 'name' => 'Human', 'id' => 1],
                'character_class' => ['key' => ['href' => 'c'], 'name' => 'Warrior', 'id' => 1],
                'realm' => ['key' => ['href' => 'rm'], 'name' => 'Thunderstrike', 'id' => 1234],
                'level' => 1, 'last_login_timestamp' => 0,
                'average_item_level' => 1, 'equipped_item_level' => 1,
            ], status: 200),
        ]);

        $this->makeConnector()->send(new GetCharacterProfileRequest('thunderstrike', 'FooBar'));

        Saloon::assertSent(fn (GetCharacterProfileRequest $r) => str_ends_with($r->resolveEndpoint(), '/foobar')
        );
    }

    // ==================== resolveEndpoint ====================

    #[Test]
    public function it_builds_a_slugged_endpoint_from_raw_realm_and_character_names(): void
    {
        $request = new GetCharacterProfileRequest('Wild Growth', 'Ben Argo');

        $this->assertSame('/profile/wow/character/wild-growth/ben-argo', $request->resolveEndpoint());
    }

    #[Test]
    public function it_keeps_diacritics_in_the_character_name(): void
    {
        $request = new GetCharacterProfileRequest('Thunderstrike', 'Ízepo');

        $this->assertSame('/profile/wow/character/thunderstrike/ízepo', $request->resolveEndpoint());
    }

    #[Test]
    public function it_accepts_already_slugged_inputs_unchanged(): void
    {
        $request = new GetCharacterProfileRequest('thunderstrike', 'thunderlord');

        $this->assertSame('/profile/wow/character/thunderstrike/thunderlord', $request->resolveEndpoint());
    }

    // ==================== boot ====================

    #[Test]
    public function it_sets_profile_namespace_header(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetCharacterProfileRequest::class => MockResponse::make(body: [
                'id' => 1, 'name' => 'Foo',
                'gender' => ['type' => 'MALE', 'name' => 'Male'],
                'faction' => ['type' => 'ALLIANCE', 'name' => 'Alliance'],
                'race' => ['key' => ['href' => 'r'], 'name' => 'Human', 'id' => 1],
                'character_class' => ['key' => ['href' => 'c'], 'name' => 'Warrior', 'id' => 1],
                'realm' => ['key' => ['href' => 'rm'], 'name' => 'Thunderstrike', 'id' => 1234],
                'level' => 1, 'last_login_timestamp' => 0,
                'average_item_level' => 1, 'equipped_item_level' => 1,
            ], status: 200),
        ]);

        $connector = $this->makeConnector();
        $expected = BlizzardNamespace::default()->forProfileRequests($connector->getRegion());

        $connector->send(new GetCharacterProfileRequest('thunderstrike', 'foo'));

        Saloon::assertSent(function ($request, $response) use ($expected) {
            return $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === $expected;
        });
    }

    #[Test]
    #[Group('happy-path')]
    public function an_explicit_namespace_overrides_the_default(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetCharacterProfileRequest::class => MockResponse::make(body: [
                'id' => 1, 'name' => 'Foo',
                'gender' => ['type' => 'MALE', 'name' => 'Male'],
                'faction' => ['type' => 'ALLIANCE', 'name' => 'Alliance'],
                'race' => ['key' => ['href' => 'r'], 'name' => 'Human', 'id' => 1],
                'character_class' => ['key' => ['href' => 'c'], 'name' => 'Warrior', 'id' => 1],
                'realm' => ['key' => ['href' => 'rm'], 'name' => 'Thunderstrike', 'id' => 1234],
                'level' => 1, 'last_login_timestamp' => 0,
                'average_item_level' => 1, 'equipped_item_level' => 1,
            ], status: 200),
        ]);

        $this->makeConnector()->send(
            new GetCharacterProfileRequest('thunderstrike', 'foo', BlizzardNamespace::ERA),
        );

        Saloon::assertSent(function ($request, $response) {
            return $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === 'profile-classic1x-eu';
        });
    }

    // ==================== cache ====================

    #[Test]
    public function it_caches_for_six_hours_by_default(): void
    {
        $this->assertSame(21600, (new GetCharacterProfileRequest('thunderstrike', 'Alpha'))->cacheExpiryInSeconds());
    }

    #[Test]
    public function it_caches_for_the_given_number_of_seconds(): void
    {
        $request = new GetCharacterProfileRequest(null, 'Alpha', BlizzardNamespace::FOREVER, cacheExpiryInSeconds: 50);

        $this->assertSame(50, $request->cacheExpiryInSeconds());
    }

    // ==================== realm ====================

    #[Test]
    #[Group('validation')]
    public function it_throws_when_realm_is_null_for_a_namespace_that_requires_one(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
        ]);

        $this->expectException(RealmRequiredException::class);

        $this->makeConnector()->send(
            new GetCharacterProfileRequest(null, 'foo', BlizzardNamespace::RETAIL),
        );
    }

    #[Test]
    public function it_omits_the_realm_segment_when_forever_has_no_realm(): void
    {
        $request = new GetCharacterProfileRequest(null, 'Thrall Stormrage', BlizzardNamespace::FOREVER);

        $this->assertSame('/profile/wow/character/thrall-stormrage', $request->resolveEndpoint());
    }

    #[Test]
    public function it_ignores_a_realm_given_in_the_forever_namespace(): void
    {
        $request = new GetCharacterProfileRequest('Thunderstrike', 'Thrall Stormrage', BlizzardNamespace::FOREVER);

        $this->assertSame('/profile/wow/character/thrall-stormrage', $request->resolveEndpoint());
    }

    #[Test]
    public function it_sends_without_a_realm_in_the_forever_namespace(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetCharacterProfileRequest::class => MockResponse::make(body: [], status: 200),
        ]);

        $this->makeConnector()->send(new GetCharacterProfileRequest(null, 'Thrall Stormrage', BlizzardNamespace::FOREVER));

        Saloon::assertSent(GetCharacterProfileRequest::class);
    }

    // ==================== namespace shapes ====================

    #[Test]
    #[Group('happy-path')]
    public function it_casts_a_forever_profile_with_a_surname_and_no_realm(): void
    {
        $this->fakeProfile($this->profileBody(surname: 'Stormrage', realm: false));

        /** @var CharacterProfileData $dto */
        $dto = $this->makeConnector()
            ->send(new GetCharacterProfileRequest(null, 'Thrall Stormrage', BlizzardNamespace::FOREVER))
            ->dto();

        $this->assertSame('Thrall', $dto->name);
        $this->assertSame('Stormrage', $dto->surname);
        $this->assertInstanceOf(Optional::class, $dto->realm);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_casts_a_profile_with_a_realm_and_no_surname(): void
    {
        $this->fakeProfile($this->profileBody());

        /** @var CharacterProfileData $dto */
        $dto = $this->makeConnector()
            ->send(new GetCharacterProfileRequest('thunderstrike', 'thrall', BlizzardNamespace::ANNIVERSARY))
            ->dto();

        $this->assertInstanceOf(Optional::class, $dto->surname);
        $this->assertSame('Thunderstrike', $dto->realm->name);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function fakeProfile(array $body): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetCharacterProfileRequest::class => MockResponse::make(body: $body, status: 200),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function profileBody(?string $surname = null, bool $realm = true): array
    {
        return [
            'id' => 1,
            'name' => 'Thrall',
            'gender' => ['type' => 'MALE', 'name' => 'Male'],
            'faction' => ['type' => 'HORDE', 'name' => 'Horde'],
            'race' => ['key' => ['href' => 'r'], 'name' => 'Orc', 'id' => 2],
            'character_class' => ['key' => ['href' => 'c'], 'name' => 'Shaman', 'id' => 7],
            'level' => 60,
            'last_login_timestamp' => 1716900000000,
            'average_item_level' => 220,
            'equipped_item_level' => 220,
            ...($surname === null ? [] : ['surname' => $surname]),
            ...($realm ? ['realm' => ['key' => ['href' => 'rm'], 'name' => 'Thunderstrike', 'id' => 1234]] : []),
        ];
    }
}
