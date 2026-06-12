<?php

namespace Tests\Feature\Middleware;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VerifyRaidHelperWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('raidhelper.webhook')->post('/_test/raidhelper-webhook', fn () => response()->json(['ok' => true]));
    }

    #[Test]
    public function it_rejects_requests_with_no_authorization_header(): void
    {
        config(['services.raidhelper.webhook_key' => 'secret-key']);

        $response = $this->postJson('/_test/raidhelper-webhook');

        $response->assertStatus(401);
    }

    #[Test]
    public function it_rejects_requests_with_a_wrong_authorization_header(): void
    {
        config(['services.raidhelper.webhook_key' => 'secret-key']);

        $response = $this->postJson('/_test/raidhelper-webhook', [], ['Authorization' => 'wrong-key']);

        $response->assertStatus(401);
    }

    #[Test]
    public function it_rejects_requests_when_the_webhook_key_is_not_configured(): void
    {
        config(['services.raidhelper.webhook_key' => null]);

        $response = $this->postJson('/_test/raidhelper-webhook', [], ['Authorization' => 'secret-key']);

        $response->assertStatus(401);
    }

    #[Test]
    public function it_passes_requests_with_a_correct_authorization_header(): void
    {
        config(['services.raidhelper.webhook_key' => 'secret-key']);

        $response = $this->postJson('/_test/raidhelper-webhook', [], ['Authorization' => 'secret-key']);

        $response->assertOk()
            ->assertJson(['ok' => true]);
    }
}
