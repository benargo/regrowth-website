<?php

namespace Tests\Feature\Http\Controllers\Api\RaidHelper;

use App\Jobs\RaidHelper\SyncEvent;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\EventWebhookBody;
use Tests\TestCase;

#[Group('raiding')]
#[Group('raidhelper-integration')]
class SyncEventControllerTest extends TestCase
{
    use EventWebhookBody;

    protected function setUp(): void
    {
        parent::setUp();

        Log::spy();
        Bus::fake();
    }

    #[Test]
    #[DataProvider('eventEndpoints')]
    public function it_returns_202_and_dispatches_sync_event_for_a_valid_webhook(string $url): void
    {
        $response = $this->postJson(
            $url,
            $this->eventBody,
            ['Authorization' => 'test_webhook_key'],
        );

        $response->assertStatus(202);
        Bus::assertDispatched(SyncEvent::class);
    }

    #[Group('error-handling')]
    #[Test]
    #[DataProvider('eventEndpoints')]
    public function it_still_processes_the_webhook_when_raid_helper_adds_unknown_keys(string $url): void
    {
        $body = array_merge($this->eventBody, [
            'creator' => ['id' => '241299706695778305', 'name' => 'Fizzywigs'],
            'coLeaders' => [],
        ]);

        $response = $this->postJson($url, $body, ['Authorization' => 'test_webhook_key']);

        $response->assertStatus(202);
        Bus::assertDispatched(SyncEvent::class);
    }

    #[Group('authorization')]
    #[Test]
    #[DataProvider('eventEndpoints')]
    public function it_rejects_requests_with_an_invalid_authorization_header(string $url): void
    {
        $response = $this->postJson(
            $url,
            $this->eventBody,
            ['Authorization' => 'wrong_webhook_key'],
        );

        $response->assertUnauthorized();
        Bus::assertNotDispatched(SyncEvent::class);
    }

    /** @return array<string, array{string}> */
    public static function eventEndpoints(): array
    {
        return [
            'event-create' => ['/api/raidhelper/event-create'],
            'event-update' => ['/api/raidhelper/event-update'],
        ];
    }
}
