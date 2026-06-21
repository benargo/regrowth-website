<?php

namespace Tests\Support\Blizzard;

use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
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
