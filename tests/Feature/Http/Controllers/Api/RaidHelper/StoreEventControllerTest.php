<?php

namespace Tests\Feature\Http\Controllers\Api\RaidHelper;

use App\Jobs\RaidHelper\SyncEvent;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoreEventControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.raidhelper.webhook_key' => 'test-secret']);

        Bus::fake();
    }

    /** @return array<string, mixed> */
    private function minimalEventBody(): array
    {
        return [
            'id' => '111222333444555001',
            'channelId' => '100000000000000001',
            'leaderId' => '200000000000000001',
            'leaderName' => 'Raid Leader',
            'title' => 'Weekly Raid',
            'description' => '',
            'startTime' => 1700000000,
            'endTime' => 1700007200,
            'closingTime' => 1699999800,
            'lastUpdated' => 1699999000,
            'color' => '0,0,0',
        ];
    }

    #[Test]
    public function it_returns_202_and_dispatches_sync_event_for_a_valid_event_create_webhook(): void
    {
        $response = $this->postJson(
            '/api/raidhelper/event-create',
            $this->minimalEventBody(),
            ['Authorization' => 'test-secret'],
        );

        $response->assertStatus(202);
        Bus::assertDispatched(SyncEvent::class);
    }

    #[Test]
    public function it_rejects_requests_with_an_invalid_authorization_header(): void
    {
        $response = $this->postJson(
            '/api/raidhelper/event-create',
            $this->minimalEventBody(),
            ['Authorization' => 'wrong-secret'],
        );

        $response->assertUnauthorized();
        Bus::assertNotDispatched(SyncEvent::class);
    }

    #[Test]
    public function it_returns_422_for_a_malformed_event_body(): void
    {
        $response = $this->postJson(
            '/api/raidhelper/event-create',
            [],
            ['Authorization' => 'test-secret'],
        );

        $response->assertUnprocessable();
        Bus::assertNotDispatched(SyncEvent::class);
    }
}
