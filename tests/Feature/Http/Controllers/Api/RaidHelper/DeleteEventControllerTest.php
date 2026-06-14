<?php

namespace Tests\Feature\Http\Controllers\Api\RaidHelper;

use App\Jobs\RaidHelper\DeleteEvent;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\EventWebhookBody;
use Tests\TestCase;

#[Group('raiding')]
#[Group('raidhelper-integration')]
class DeleteEventControllerTest extends TestCase
{
    use EventWebhookBody;

    protected function setUp(): void
    {
        parent::setUp();

        Log::spy();
        Bus::fake();
    }

    #[Test]
    public function it_returns_202_and_dispatches_delete_event_for_a_valid_event_delete_webhook(): void
    {
        $response = $this->postJson(
            '/api/raidhelper/event-delete',
            $this->eventBody,
            ['Authorization' => 'test_webhook_key'],
        );

        $response->assertStatus(202);
        Bus::assertDispatched(DeleteEvent::class, fn ($job) => $job->raidHelperEventId === '111222333444555001');
    }

    #[Group('authorization')]
    #[Test]
    public function it_rejects_requests_with_an_invalid_authorization_header(): void
    {
        $response = $this->postJson(
            '/api/raidhelper/event-delete',
            $this->eventBody,
            ['Authorization' => 'wrong_webhook_key'],
        );

        $response->assertUnauthorized();
        Bus::assertNotDispatched(DeleteEvent::class);
    }

    #[Test]
    public function it_returns_400_when_payload_is_invalid(): void
    {
        $response = $this->postJson(
            '/api/raidhelper/event-delete',
            [],
            ['Authorization' => 'test_webhook_key'],
        );

        $response->assertBadRequest();
        Bus::assertNotDispatched(DeleteEvent::class);
    }
}
