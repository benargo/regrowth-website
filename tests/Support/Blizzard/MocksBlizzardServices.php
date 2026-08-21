<?php

namespace Tests\Support\Blizzard;

use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchCharacterPortraitRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use Illuminate\Support\Facades\Storage;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;

trait MocksBlizzardServices
{
    /**
     * @return array<string, mixed>
     */
    protected function makeTokenResponse(): array
    {
        return ['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600];
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
     * Fake the oauth token plus a GetCharacterProfileRequest response.
     *
     * Pass $status/$body to simulate an error response (e.g. 404 not found)
     * instead of the happy-path profile.
     *
     * @param  array<string, mixed>|null  $body
     */
    protected function mockCharacterProfileService(string $gender = 'Male', int $status = 200, ?array $body = null): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(body: $this->makeTokenResponse(), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(
                body: $body ?? $this->makeCharacterProfileResponse($gender),
                status: $status,
            ),
        ]);
    }

    /**
     * Fake a FetchCharacterPortraitRequest response in isolation (no token/profile fake).
     *
     * Use when the job under test must not send GetCharacterProfileRequest at
     * all (e.g. the character's gender is already set), so the test can assert
     * Saloon::assertNotSent(GetCharacterProfileRequest::class).
     */
    protected function mockCharacterPortraitFetch(int $status = 200, string $body = 'BINARY'): void
    {
        Saloon::fake([
            FetchCharacterPortraitRequest::class => MockResponse::make(body: $body, status: $status),
        ]);
    }

    /**
     * Fake the full happy-path chain for AttachPortraitToCharacter: oauth
     * token + GetCharacterProfileRequest + FetchCharacterPortraitRequest.
     *
     * @param  array<string, mixed>|null  $profileBody
     */
    protected function mockCharacterPortraitService(string $gender = 'Male', ?array $profileBody = null, int $profileStatus = 200): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(body: $this->makeTokenResponse(), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(
                body: $profileBody ?? $this->makeCharacterProfileResponse($gender),
                status: $profileStatus,
            ),
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);
    }

    /**
     * Fake all Blizzard service requests.
     *
     * When $itemData is empty, GetItemRequest is handled by a callback that
     * extracts the item ID from the request URL — suitable for feature tests
     * that create multiple items and need per-item responses.
     *
     * When $itemData is provided, GetItemRequest returns a static body merged
     * over sensible defaults — suitable for unit tests targeting a single item.
     *
     * @param  array<string, mixed>  $itemData
     */
    protected function mockItemService(array $itemData = []): void
    {
        Storage::fake('public');

        $itemRequest = $itemData
            ? MockResponse::make(body: array_merge([
                'name' => 'Test Item',
                'item_class' => ['name' => 'Armor'],
                'item_subclass' => ['name' => 'Plate'],
                'quality' => ['type' => 'EPIC', 'name' => 'Epic'],
                'inventory_type' => ['name' => 'Head'],
            ], $itemData), status: 200)
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

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make($this->makeTokenResponse()),
            GetItemRequest::class => $itemRequest,
            GetItemMediaRequest::class => MockResponse::make(body: ['id' => 0, 'assets' => []], status: 200),
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);
    }
}
