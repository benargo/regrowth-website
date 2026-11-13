<?php

namespace Tests\Feature\Models;

use App\Models\DesktopClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('auth')]
class DesktopClientTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function is_linked_is_true_for_a_linked_client(): void
    {
        $desktopClient = DesktopClient::factory()->linked()->create();

        $this->assertTrue($desktopClient->load('accessToken.refreshToken')->is_linked);
    }

    #[Test]
    public function is_linked_is_false_after_the_token_is_revoked(): void
    {
        $desktopClient = DesktopClient::factory()->linked()->create();
        $desktopClient->accessToken->forceFill(['revoked' => true])->save();

        $this->assertFalse($desktopClient->load('accessToken.refreshToken')->is_linked);
    }

    #[Test]
    public function is_linked_is_false_when_the_refresh_token_has_expired(): void
    {
        $desktopClient = DesktopClient::factory()->linked()->create();
        $desktopClient->accessToken->refreshToken->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->assertFalse($desktopClient->load('accessToken.refreshToken')->is_linked);
    }

    #[Test]
    public function is_linked_is_false_without_a_token(): void
    {
        $desktopClient = DesktopClient::factory()->create();

        $this->assertFalse($desktopClient->load('accessToken.refreshToken')->is_linked);
    }

    #[Test]
    public function it_scopes_to_linked_clients(): void
    {
        $linked = DesktopClient::factory()->linked()->create();

        $revoked = DesktopClient::factory()->linked()->create();
        $revoked->accessToken->forceFill(['revoked' => true])->save();

        $expired = DesktopClient::factory()->linked()->create();
        $expired->accessToken->refreshToken->forceFill(['expires_at' => now()->subMinute()])->save();

        DesktopClient::factory()->create();

        $this->assertSame([$linked->id], DesktopClient::linked()->pluck('id')->all());
    }
}
