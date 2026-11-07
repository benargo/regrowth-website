<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\Character;

use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Data\Characters\CharacterMediaData;
use App\Http\Integrations\Blizzard\Exceptions\CharacterNotFoundException;
use App\Http\Integrations\Blizzard\Exceptions\RealmRequiredException;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterMediaRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Spatie\LaravelData\Optional;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

#[Group('blizzard-integration')]
class GetCharacterMediaRequestTest extends BlizzardTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function sampleApiResponse(): array
    {
        return [
            '_links' => [
                'self' => [
                    'href' => 'https://eu.api.blizzard.com/profile/wow/character/thunderstrike/wastedhippy/character-media?namespace=profile-classicann-eu',
                ],
            ],
            'character' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/profile/wow/character/thunderstrike/wastedhippy?namespace=profile-classicann-eu'],
                'name' => 'Wastedhippy',
                'id' => 51042439,
                'realm' => [
                    'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/realm/6409?namespace=dynamic-classicann-eu'],
                    'name' => 'Thunderstrike',
                    'id' => 6409,
                    'slug' => 'thunderstrike',
                ],
            ],
            'assets' => [
                ['key' => 'avatar', 'value' => 'https://render.worldofwarcraft.com/classicann-eu/character/thunderstrike/135/51042439-avatar.jpg'],
            ],
        ];
    }

    #[Test]
    public function it_casts_response_to_character_media_data(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetCharacterMediaRequest::class => MockResponse::make(body: $this->sampleApiResponse(), status: 200),
        ]);

        $response = $this->makeConnector()->send(new GetCharacterMediaRequest('thunderstrike', 'wastedhippy'));

        /** @var CharacterMediaData $dto */
        $dto = $response->dto();
        $this->assertInstanceOf(CharacterMediaData::class, $dto);
        $this->assertSame('Wastedhippy', $dto->character->name);
        $this->assertSame(51042439, $dto->character->id);
        $this->assertCount(1, $dto->assets);
        $this->assertSame('avatar', $dto->assets[0]->key);
    }

    #[Test]
    public function it_builds_a_slugged_endpoint_from_raw_realm_and_character_names(): void
    {
        $request = new GetCharacterMediaRequest('Wild Growth', 'Ben Argo');

        $this->assertSame('/profile/wow/character/wild-growth/ben-argo/character-media', $request->resolveEndpoint());
    }

    #[Test]
    public function it_keeps_diacritics_in_the_character_name(): void
    {
        $request = new GetCharacterMediaRequest('Thunderstrike', 'Ízepo');

        $this->assertSame('/profile/wow/character/thunderstrike/ízepo/character-media', $request->resolveEndpoint());
    }

    #[Test]
    public function it_accepts_already_slugged_inputs_unchanged(): void
    {
        $request = new GetCharacterMediaRequest('thunderstrike', 'wastedhippy');

        $this->assertSame('/profile/wow/character/thunderstrike/wastedhippy/character-media', $request->resolveEndpoint());
    }

    #[Test]
    public function it_sets_profile_namespace_header(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetCharacterMediaRequest::class => MockResponse::make(body: $this->sampleApiResponse(), status: 200),
        ]);

        $connector = $this->makeConnector();
        $expected = BlizzardNamespace::default()->forProfileRequests($connector->getRegion());

        $connector->send(new GetCharacterMediaRequest('thunderstrike', 'wastedhippy'));

        Saloon::assertSent(function ($request, $response) use ($expected) {
            return $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === $expected;
        });
    }

    #[Group('error-handling')]
    #[Test]
    public function it_throws_character_not_found_exception_on_404(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetCharacterMediaRequest::class => MockResponse::make(body: ['type' => 'BLZWEBAPI00000404'], status: 404),
        ]);

        $this->expectException(CharacterNotFoundException::class);

        $this->makeConnector()->send(new GetCharacterMediaRequest('thunderstrike', 'unknown'));
    }

    #[Test]
    public function it_omits_the_realm_segment_when_forever_has_no_realm(): void
    {
        $request = new GetCharacterMediaRequest(null, 'Thrall Stormrage', BlizzardNamespace::FOREVER);

        $this->assertSame('/profile/wow/character/thrall-stormrage/character-media', $request->resolveEndpoint());
    }

    #[Test]
    public function it_ignores_a_realm_given_in_the_forever_namespace(): void
    {
        $request = new GetCharacterMediaRequest('Thunderstrike', 'Thrall Stormrage', BlizzardNamespace::FOREVER);

        $this->assertSame('/profile/wow/character/thrall-stormrage/character-media', $request->resolveEndpoint());
    }

    #[Test]
    public function it_sends_without_a_realm_in_the_forever_namespace(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetCharacterMediaRequest::class => MockResponse::make(body: [], status: 200),
        ]);

        $this->makeConnector()->send(new GetCharacterMediaRequest(null, 'Thrall Stormrage', BlizzardNamespace::FOREVER));

        Saloon::assertSent(GetCharacterMediaRequest::class);
    }

    #[Test]
    #[Group('validation')]
    public function it_throws_when_realm_is_null_for_a_namespace_that_requires_one(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
        ]);

        $this->expectException(RealmRequiredException::class);

        $this->makeConnector()->send(
            new GetCharacterMediaRequest(null, 'foo', BlizzardNamespace::RETAIL),
        );
    }

    // ==================== namespace shapes ====================

    #[Test]
    #[Group('happy-path')]
    public function it_keeps_the_surname_of_a_forever_character(): void
    {
        $this->fakeMedia(surname: 'Stormrage', realm: false);

        /** @var CharacterMediaData $dto */
        $dto = $this->makeConnector()
            ->send(new GetCharacterMediaRequest(null, 'Thrall Stormrage', BlizzardNamespace::FOREVER))
            ->dto();

        $this->assertSame('Stormrage', $dto->character->surname);
        $this->assertInstanceOf(Optional::class, $dto->character->realm);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_keeps_the_realm_of_an_anniversary_character(): void
    {
        $this->fakeMedia(surname: null, realm: true);

        /** @var CharacterMediaData $dto */
        $dto = $this->makeConnector()
            ->send(new GetCharacterMediaRequest('thunderstrike', 'wastedhippy', BlizzardNamespace::ANNIVERSARY))
            ->dto();

        $this->assertSame('thunderstrike', $dto->character->realm->slug);
        $this->assertInstanceOf(Optional::class, $dto->character->surname);
    }

    private function fakeMedia(?string $surname, bool $realm): void
    {
        $body = $this->sampleApiResponse();

        if (! $realm) {
            unset($body['character']['realm']);
        }

        if ($surname !== null) {
            $body['character']['surname'] = $surname;
        }

        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetCharacterMediaRequest::class => MockResponse::make(body: $body, status: 200),
        ]);
    }
}
