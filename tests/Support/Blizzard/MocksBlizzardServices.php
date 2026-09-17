<?php

namespace Tests\Support\Blizzard;

use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchCharacterMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use Illuminate\Support\Facades\Storage;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;

trait MocksBlizzardServices
{
    /** @var string */
    const TOKEN_MOCK_KEY = 'eu.battle.net/oauth/token';

    /** @var array<string, mixed> */
    const TOKEN_MOCK_RESPONSE = [
        'access_token' => 'test_token',
        'token_type' => 'bearer',
        'expires_in' => 3600,
    ];

    /** @var array<string, mixed> */
    protected array $pendingBlizzardMocks = [];

    /**
     * Saloon::fake() replaces any previously registered fakes, so mockX()
     * helpers must not call it directly — they accumulate into
     * $pendingBlizzardMocks and this flushes them all in one call.
     */
    protected function applyBlizzardMocks(): void
    {
        Saloon::fake($this->pendingBlizzardMocks);
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeCharacterProfileResponse(string $gender = 'Male', int $classId = 1, int $raceId = 1): array
    {
        return [
            'id' => 1,
            'name' => 'Testcharacter',
            'gender' => ['type' => strtoupper($gender), 'name' => $gender],
            'faction' => ['type' => 'HORDE', 'name' => 'Horde'],
            'race' => ['key' => ['href' => "https://example.test/race/{$raceId}"], 'name' => 'Orc', 'id' => $raceId],
            'character_class' => ['key' => ['href' => "https://example.test/class/{$classId}"], 'name' => 'Shaman', 'id' => $classId],
            'realm' => ['key' => ['href' => 'https://example.test/realm/1'], 'name' => 'Thunderstrike', 'id' => 1, 'slug' => 'thunderstrike'],
            'level' => 70,
            'last_login_timestamp' => 0,
            'average_item_level' => 0,
            'equipped_item_level' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $responseData
     */
    protected function mockGetCharacterProfile(string $gender = 'Male', array $responseData = [], int $status = 200): void
    {
        $this->pendingBlizzardMocks = array_merge($this->pendingBlizzardMocks, [
            self::TOKEN_MOCK_KEY => MockResponse::make(body: self::TOKEN_MOCK_RESPONSE, status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(
                body: array_merge($this->makeCharacterProfileResponse($gender), $responseData),
                status: $status,
            ),
        ]);
    }

    protected function mockFetchCharacterMedia(int $status = 200, string $body = 'BINARY'): void
    {
        $this->pendingBlizzardMocks = array_merge($this->pendingBlizzardMocks, [
            self::TOKEN_MOCK_KEY => MockResponse::make(body: self::TOKEN_MOCK_RESPONSE, status: 200),
            FetchCharacterMediaRequest::class => MockResponse::make(body: $body, status: $status),
        ]);
    }

    /**
     * @param  array<string, mixed>  $responseData
     */
    protected function mockGetCharacterMedia(array $responseData = [], int $status = 200): void
    {
        $this->pendingBlizzardMocks = array_merge($this->pendingBlizzardMocks, [
            self::TOKEN_MOCK_KEY => MockResponse::make(body: self::TOKEN_MOCK_RESPONSE, status: 200),
            GetCharacterMediaRequest::class => MockResponse::make(body: array_merge([
                'character' => ['key' => ['href' => 'https://example.test/character'], 'name' => 'Caldru', 'id' => 1, 'realm' => ['key' => ['href' => 'https://example.test/realm'], 'name' => 'Thunderstrike', 'id' => 1, 'slug' => 'thunderstrike']],
                'assets' => [
                    ['key' => 'avatar', 'value' => 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg'],
                    ['key' => 'inset', 'value' => 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-inset.jpg'],
                    ['key' => 'main-raw', 'value' => 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-main-raw.png'],
                ],
            ], $responseData), status: $status),
        ]);
    }

    /**
     * @param  array<string, mixed>  $responseData
     */
    protected function mockGetGuildRoster(array $responseData = []): void
    {
        $this->pendingBlizzardMocks = array_merge($this->pendingBlizzardMocks, [
            self::TOKEN_MOCK_KEY => MockResponse::make(body: self::TOKEN_MOCK_RESPONSE, status: 200),
            GetGuildRosterRequest::class => MockResponse::make(body: array_merge([
                'guild' => [
                    'key' => ['href' => 'https://example.test/guild'],
                    'name' => 'Wild Growth',
                    'id' => 1,
                    'realm' => ['key' => ['href' => 'https://example.test/realm'], 'name' => 'Thunderstrike', 'id' => 1, 'slug' => 'thunderstrike'],
                ],
                'members' => [],
            ], $responseData), status: 200),
        ]);
    }

    /**
     * With $responseData empty, GetItemRequest extracts the item ID from the
     * request URL per call — for tests creating multiple items.
     *
     * @param  array<string, mixed>  $responseData
     */
    protected function mockGetItem(array $responseData = []): void
    {
        Storage::fake('public');

        $itemRequest = $responseData
            ? MockResponse::make(body: array_merge([
                'name' => 'Test Item',
                'item_class' => ['name' => 'Armor'],
                'item_subclass' => ['name' => 'Plate'],
                'quality' => ['type' => 'EPIC', 'name' => 'Epic'],
                'inventory_type' => ['name' => 'Head'],
            ], $responseData), status: 200)
            : function (PendingRequest $pendingRequest): MockResponse {
                $path = parse_url($pendingRequest->getUrl(), PHP_URL_PATH) ?: '';
                $segments = explode('/', trim($path, '/'));
                $itemId = (int) ($segments[array_key_last($segments)] ?? 0);

                return MockResponse::make(body: [
                    'id' => $itemId,
                    'name' => "Test Item {$itemId}",
                    'quality' => ['type' => 'EPIC', 'name' => 'Epic'],
                    'level' => 1,
                    'required_level' => 1,
                    'media' => ['key' => ['href' => "https://example.test/media/{$itemId}"]],
                    'item_class' => ['key' => ['href' => 'https://example.test/item-class/2'], 'name' => 'Armor', 'id' => 2],
                    'item_subclass' => ['key' => ['href' => 'https://example.test/item-subclass/2-7'], 'name' => 'Plate', 'id' => 7],
                    'inventory_type' => ['type' => 'HEAD', 'name' => 'Head'],
                    'purchase_price' => 0,
                    'sell_price' => 0,
                ], status: 200);
            };

        $this->pendingBlizzardMocks = array_merge($this->pendingBlizzardMocks, [
            self::TOKEN_MOCK_KEY => MockResponse::make(self::TOKEN_MOCK_RESPONSE),
            GetItemRequest::class => $itemRequest,
            GetItemMediaRequest::class => MockResponse::make(body: ['id' => 0, 'assets' => []], status: 200),
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);
    }

    /**
     * Queues a standard Blizzard "not found" response for the given request
     * class, overriding any previously queued mock for it.
     *
     * @param  class-string  $requestClass
     */
    protected function mockNotFoundResponse(string $requestClass): void
    {
        $this->pendingBlizzardMocks[$requestClass] = MockResponse::make(
            body: ['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'],
            status: 404,
        );
    }
}
