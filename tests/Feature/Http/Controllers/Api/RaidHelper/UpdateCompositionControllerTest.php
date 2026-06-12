<?php

namespace Tests\Feature\Http\Controllers\Api\RaidHelper;

use App\Jobs\RaidHelper\SyncComposition;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateCompositionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.raidhelper.webhook_key' => 'test-secret']);

        Bus::fake();
    }

    #[Test]
    public function it_returns_202_and_dispatches_sync_composition_for_a_valid_comp_update_webhook(): void
    {
        Event::factory()->create(['raid_helper_event_id' => 'comp-111']);

        $response = $this->postJson(
            '/api/raidhelper/comp-update',
            $this->minimalCompBody(),
            ['Authorization' => 'test-secret'],
        );

        $response->assertStatus(202);
        Bus::assertDispatched(SyncComposition::class, fn ($job) => $job->raidHelperEventId === 'comp-111');
    }

    #[Test]
    public function it_rejects_requests_with_an_invalid_authorization_header(): void
    {
        $response = $this->postJson(
            '/api/raidhelper/comp-update',
            $this->minimalCompBody(),
            ['Authorization' => 'wrong-secret'],
        );

        $response->assertUnauthorized();
        Bus::assertNotDispatched(SyncComposition::class);
    }

    #[Test]
    public function it_returns_400_when_request_contains_an_unknown_top_level_field(): void
    {
        $body = array_merge($this->minimalCompBody(), ['unknownField' => 'value']);

        $response = $this->postJson(
            '/api/raidhelper/comp-update',
            $body,
            ['Authorization' => 'test-secret'],
        );

        $response->assertStatus(400);
        Bus::assertNotDispatched(SyncComposition::class);
    }

    #[Test]
    public function it_returns_400_when_a_required_composition_field_is_missing(): void
    {
        $body = $this->minimalCompBody();
        unset($body['title']);

        $response = $this->postJson(
            '/api/raidhelper/comp-update',
            $body,
            ['Authorization' => 'test-secret'],
        );

        $response->assertStatus(400);
        Bus::assertNotDispatched(SyncComposition::class);
    }

    #[Test]
    public function it_returns_400_when_id_is_missing(): void
    {
        $body = $this->minimalCompBody();
        unset($body['id']);

        $response = $this->postJson(
            '/api/raidhelper/comp-update',
            $body,
            ['Authorization' => 'test-secret'],
        );

        $response->assertStatus(400);
        Bus::assertNotDispatched(SyncComposition::class);
    }

    #[Test]
    public function it_returns_400_when_id_does_not_exist_in_the_database(): void
    {
        $response = $this->postJson(
            '/api/raidhelper/comp-update',
            $this->minimalCompBody(),
            ['Authorization' => 'test-secret'],
        );

        $response->assertStatus(400);
        Bus::assertNotDispatched(SyncComposition::class);
    }

    /** @return array<string, mixed> */
    private function minimalCompBody(): array
    {
        return [
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
}
