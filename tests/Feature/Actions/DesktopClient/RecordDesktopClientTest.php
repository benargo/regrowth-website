<?php

namespace Tests\Feature\Actions\DesktopClient;

use App\Actions\DesktopClient\RecordDesktopClient;
use App\Models\DesktopClient;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('auth')]
class RecordDesktopClientTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_never_creates_a_device(): void
    {
        $user = User::factory()->create();

        try {
            RecordDesktopClient::run($user, fake()->uuid(), 'Raid PC', null);
            $this->fail('Expected a ModelNotFoundException.');
        } catch (ModelNotFoundException) {
            $this->assertSame(0, DesktopClient::count());
        }
    }

    #[Test]
    #[Group('happy-path')]
    public function it_updates_the_name_token_and_last_seen_for_a_known_id(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();
        $existing = DesktopClient::factory()->create([
            'user_id' => $user->id,
            'name' => 'Old name',
            'oauth_access_token_id' => null,
            'last_seen_at' => now()->subDay(),
        ]);

        $client = RecordDesktopClient::run($user, $existing->id, 'New name', 'token-1');

        $this->assertSame(1, DesktopClient::count());
        $this->assertSame('New name', $client->name);
        $this->assertSame('token-1', $client->oauth_access_token_id);
        $this->assertTrue($client->last_seen_at->equalTo(now()));
    }

    #[Test]
    #[Group('authorization')]
    public function it_refuses_a_device_id_owned_by_another_user(): void
    {
        $owner = DesktopClient::factory()->create();
        $other = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        try {
            RecordDesktopClient::run($other, $owner->id, 'Hijack', null);
        } finally {
            $this->assertSame($owner->user_id, $owner->fresh()->user_id);
        }
    }
}
