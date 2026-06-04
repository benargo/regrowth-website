<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\Character;

use App\Http\Integrations\Blizzard\Data\Characters\CharacterProfileData;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Spatie\LaravelData\Optional;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

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

    #[Test]
    public function it_builds_a_slugged_endpoint_from_raw_realm_and_character_names(): void
    {
        $request = new GetCharacterProfileRequest('Wild Growth', 'Ben Argo');

        $this->assertSame('/profile/wow/character/wild-growth/ben-argo', $request->resolveEndpoint());
    }

    #[Test]
    public function it_accepts_already_slugged_inputs_unchanged(): void
    {
        $request = new GetCharacterProfileRequest('thunderstrike', 'thunderlord');

        $this->assertSame('/profile/wow/character/thunderstrike/thunderlord', $request->resolveEndpoint());
    }

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
        $expected = $connector->namespace('profile');

        $connector->send(new GetCharacterProfileRequest('thunderstrike', 'foo'));

        Saloon::assertSent(function ($request, $response) use ($expected) {
            return $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === $expected;
        });
    }
}
