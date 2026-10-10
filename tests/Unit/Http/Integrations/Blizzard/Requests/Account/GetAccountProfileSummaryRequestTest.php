<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\Account;

use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Data\Account\AccountProfileSummaryData;
use App\Http\Integrations\Blizzard\Exceptions\ApiException;
use App\Http\Integrations\Blizzard\Requests\Account\GetAccountProfileSummaryRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetClientCredentialsTokenBasicAuthRequest;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

#[Group('blizzard-integration')]
class GetAccountProfileSummaryRequestTest extends BlizzardTestCase
{
    #[Test]
    public function it_targets_the_account_profile_summary_endpoint(): void
    {
        $request = new GetAccountProfileSummaryRequest('user-token');

        $this->assertSame('/profile/user/wow', $request->resolveEndpoint());
    }

    #[Test]
    #[Group('happy-path')]
    public function it_casts_the_response_to_account_profile_summary_data(): void
    {
        Saloon::fake([
            GetAccountProfileSummaryRequest::class => MockResponse::make($this->profileSummaryBody()),
        ]);

        /** @var AccountProfileSummaryData $summary */
        $summary = $this->makeConnector()
            ->send(new GetAccountProfileSummaryRequest('user-token'))
            ->dto();

        $this->assertInstanceOf(AccountProfileSummaryData::class, $summary);
        $this->assertSame(1922840, $summary->id);
        $this->assertCount(1, $summary->wowAccounts);
        $this->assertSame(8637580, $summary->wowAccounts[0]->id);
        $this->assertCount(2, $summary->wowAccounts[0]->characters);

        $character = $summary->wowAccounts[0]->characters[1];
        $this->assertSame('Noonian', $character->name);
        $this->assertSame(79938434, $character->id);
        $this->assertSame('thunderstrike', $character->realm->slug);
        $this->assertSame(6409, $character->realm->id);
        $this->assertSame('Mage', $character->playableClass->name);
        $this->assertSame(10, $character->playableRace->id);
        $this->assertSame('HORDE', $character->faction['type']);
        $this->assertSame(1, $character->level);
        $this->assertSame(
            'https://eu.api.blizzard.com/profile/user/wow/protected-character/6409-79938434?namespace=profile-classicann-eu',
            (string) $character->protectedCharacter->href,
        );
    }

    #[Test]
    public function it_treats_a_wow_account_without_characters_as_empty(): void
    {
        $body = $this->profileSummaryBody();
        $body['wow_accounts'][] = ['id' => 9999999];

        Saloon::fake([
            GetAccountProfileSummaryRequest::class => MockResponse::make($body),
        ]);

        /** @var AccountProfileSummaryData $summary */
        $summary = $this->makeConnector()
            ->send(new GetAccountProfileSummaryRequest('user-token'))
            ->dto();

        $this->assertSame([], $summary->wowAccounts[1]->characters);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_authenticates_with_the_users_token_not_the_client_credentials_token(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            GetAccountProfileSummaryRequest::class => MockResponse::make($this->profileSummaryBody()),
        ]);

        $this->makeConnector()->send(new GetAccountProfileSummaryRequest('user-token'));

        Saloon::assertNotSent(GetClientCredentialsTokenBasicAuthRequest::class);
        Saloon::assertSent(fn ($request, $response) => $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer user-token');
    }

    #[Test]
    public function it_sets_the_default_profile_namespace_header(): void
    {
        Saloon::fake([
            GetAccountProfileSummaryRequest::class => MockResponse::make($this->profileSummaryBody()),
        ]);

        $connector = $this->makeConnector();
        $expected = BlizzardNamespace::default()->forProfileRequests($connector->getRegion());

        $connector->send(new GetAccountProfileSummaryRequest('user-token'));

        Saloon::assertSent(fn ($request, $response) => $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === $expected);
    }

    #[Test]
    public function an_explicit_namespace_overrides_the_default(): void
    {
        Saloon::fake([
            GetAccountProfileSummaryRequest::class => MockResponse::make($this->profileSummaryBody()),
        ]);

        $this->makeConnector()->send(new GetAccountProfileSummaryRequest('user-token', BlizzardNamespace::ERA));

        Saloon::assertSent(fn ($request, $response) => $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === 'profile-classic1x-eu');
    }

    #[Test]
    #[Group('happy-path')]
    public function it_caches_the_response_per_token(): void
    {
        Saloon::fake([
            GetAccountProfileSummaryRequest::class => MockResponse::make($this->profileSummaryBody()),
        ]);

        $connector = $this->makeConnector();

        $first = $connector->send(new GetAccountProfileSummaryRequest('user-token'));
        $second = $connector->send(new GetAccountProfileSummaryRequest('user-token'));

        $this->assertFalse($first->isCached());
        $this->assertTrue($second->isCached());
    }

    #[Test]
    public function it_does_not_share_the_cache_between_tokens(): void
    {
        Saloon::fake([
            GetAccountProfileSummaryRequest::class => MockResponse::make($this->profileSummaryBody()),
        ]);

        $connector = $this->makeConnector();

        $connector->send(new GetAccountProfileSummaryRequest('user-token'));
        $otherAccount = $connector->send(new GetAccountProfileSummaryRequest('other-user-token'));

        $this->assertFalse($otherAccount->isCached());
    }

    #[Test]
    public function it_does_not_share_the_cache_between_namespaces(): void
    {
        Saloon::fake([
            GetAccountProfileSummaryRequest::class => MockResponse::make($this->profileSummaryBody()),
        ]);

        $connector = $this->makeConnector();

        $connector->send(new GetAccountProfileSummaryRequest('user-token', BlizzardNamespace::ANNIVERSARY));
        $era = $connector->send(new GetAccountProfileSummaryRequest('user-token', BlizzardNamespace::ERA));

        $this->assertFalse($era->isCached());
    }

    #[Test]
    #[Group('error-handling')]
    public function it_throws_and_does_not_cache_when_the_token_is_rejected(): void
    {
        Saloon::fake([
            MockResponse::make(['code' => 401, 'type' => 'BLZWEBAPI00000401', 'detail' => 'Unauthorized'], 401),
            MockResponse::make($this->profileSummaryBody()),
        ]);

        $connector = $this->makeConnector();

        try {
            $connector->send(new GetAccountProfileSummaryRequest('user-token'));
            $this->fail('Expected an ApiException for a 401.');
        } catch (ApiException $exception) {
            $this->assertSame(401, $exception->getResponse()->status());
        }

        $retry = $connector->send(new GetAccountProfileSummaryRequest('user-token'));

        $this->assertFalse($retry->isCached());
    }

    // ==================== helpers ====================

    /**
     * A trimmed copy of a real `/profile/user/wow` response: one WoW account,
     * one Alliance and one Horde character.
     *
     * @return array<string, mixed>
     */
    private function profileSummaryBody(): array
    {
        return [
            '_links' => [
                'self' => ['href' => 'https://eu.api.blizzard.com/profile/user/wow?namespace=profile-classicann-eu'],
                'user' => ['href' => 'https://eu.api.blizzard.com/profile/user'],
                'profile' => ['href' => 'https://eu.api.blizzard.com/profile/user/wow?namespace=profile-classicann-eu'],
            ],
            'id' => 1922840,
            'wow_accounts' => [
                [
                    'id' => 8637580,
                    'characters' => [
                        $this->character(52963250, 'Auburn', ['Priest', 5], ['Human', 1], 'FEMALE', 'ALLIANCE', 1),
                        $this->character(79938434, 'Noonian', ['Mage', 8], ['Blood Elf', 10], 'FEMALE', 'HORDE', 1),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array{0: string, 1: int}  $class
     * @param  array{0: string, 1: int}  $race
     * @return array<string, mixed>
     */
    private function character(int $id, string $name, array $class, array $race, string $gender, string $faction, int $level): array
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
                'key' => ['href' => "https://eu.api.blizzard.com/data/wow/playable-class/{$class[1]}?namespace=static-2.5.6_68184-classicann-eu"],
                'name' => $class[0],
                'id' => $class[1],
            ],
            'playable_race' => [
                'key' => ['href' => "https://eu.api.blizzard.com/data/wow/playable-race/{$race[1]}?namespace=static-2.5.6_68184-classicann-eu"],
                'name' => $race[0],
                'id' => $race[1],
            ],
            'gender' => ['type' => $gender, 'name' => ucfirst(strtolower($gender))],
            'faction' => ['type' => $faction, 'name' => ucfirst(strtolower($faction))],
            'level' => $level,
        ];
    }
}
