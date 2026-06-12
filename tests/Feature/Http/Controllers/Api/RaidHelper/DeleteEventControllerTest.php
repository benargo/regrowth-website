<?php

namespace Tests\Feature\Http\Controllers\Api\RaidHelper;

use App\Jobs\RaidHelper\DeleteEvent;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeleteEventControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.raidhelper.webhook_key' => 'test-secret']);

        Bus::fake();
    }

    #[Test]
    public function it_returns_202_and_dispatches_delete_event_for_a_valid_event_delete_webhook(): void
    {
        $response = $this->postJson(
            '/api/raidhelper/event-delete',
            ['id' => '111222333444555001'],
            ['Authorization' => 'test-secret'],
        );

        $response->assertStatus(202);
        Bus::assertDispatched(DeleteEvent::class, fn ($job) => $job->raidHelperEventId === '111222333444555001');
    }

    #[Test]
    public function it_rejects_requests_with_an_invalid_authorization_header(): void
    {
        $response = $this->postJson(
            '/api/raidhelper/event-delete',
            ['id' => '111222333444555001'],
            ['Authorization' => 'wrong-secret'],
        );

        $response->assertUnauthorized();
        Bus::assertNotDispatched(DeleteEvent::class);
    }

    #[Test]
    public function it_returns_422_when_id_is_missing(): void
    {
        $response = $this->postJson(
            '/api/raidhelper/event-delete',
            [],
            ['Authorization' => 'test-secret'],
        );

        $response->assertUnprocessable();
        Bus::assertNotDispatched(DeleteEvent::class);
    }
}
