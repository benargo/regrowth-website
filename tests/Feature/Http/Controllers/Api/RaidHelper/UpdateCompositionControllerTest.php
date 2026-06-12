<?php

namespace Tests\Feature\Http\Controllers\Api\RaidHelper;

use App\Jobs\RaidHelper\SyncComposition;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateCompositionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.raidhelper.webhook_key' => 'test-secret']);

        Bus::fake();
    }

    /** @return array<string, mixed> */
    private function minimalCompBody(): array
    {
        return [
            'eventId' => '111222333444555001',
            'id' => 'comp-111',
            'title' => 'Test Comp',
            'editPermissions' => 'managers',
            'showRoles' => true,
            'showClasses' => true,
            'groupCount' => 5,
            'slotCount' => 25,
            'groups' => [],
            'dividers' => [],
            'classes' => [],
            'slots' => [],
        ];
    }

    #[Test]
    public function it_returns_202_and_dispatches_sync_composition_for_a_valid_comp_update_webhook(): void
    {
        $response = $this->postJson(
            route('api.raidhelper.comp-update'),
            $this->minimalCompBody(),
            ['Authorization' => 'test-secret'],
        );

        $response->assertStatus(202);
        Bus::assertDispatched(SyncComposition::class, fn ($job) => $job->raidHelperEventId === '111222333444555001');
    }

    #[Test]
    public function it_rejects_requests_with_an_invalid_authorization_header(): void
    {
        $response = $this->postJson(
            route('api.raidhelper.comp-update'),
            $this->minimalCompBody(),
            ['Authorization' => 'wrong-secret'],
        );

        $response->assertUnauthorized();
        Bus::assertNotDispatched(SyncComposition::class);
    }

    #[Test]
    public function it_returns_422_when_event_id_is_missing(): void
    {
        $body = $this->minimalCompBody();
        unset($body['eventId']);

        $response = $this->postJson(
            route('api.raidhelper.comp-update'),
            $body,
            ['Authorization' => 'test-secret'],
        );

        $response->assertUnprocessable();
        Bus::assertNotDispatched(SyncComposition::class);
    }
}
