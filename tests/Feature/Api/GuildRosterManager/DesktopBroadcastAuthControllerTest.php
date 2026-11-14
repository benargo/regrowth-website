<?php

namespace Tests\Feature\Api\GuildRosterManager;

use App\Models\DesktopClient;
use App\Models\DesktopClientToken;
use App\Models\DiscordRole;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;
use Laravel\Passport\TransientToken;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

#[Group('characters')]
#[Group('auth')]
class DesktopBroadcastAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = '9d4c2f3e-6a1b-4c8e-9f0a-1b2c3d4e5f60';

    private User $uploader;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => config('broadcasting.connections.reverb.key', 'test-app-key'),
            'broadcasting.connections.reverb.secret' => config('broadcasting.connections.reverb.secret', 'test-app-secret'),
            'broadcasting.connections.reverb.app_id' => config('broadcasting.connections.reverb.app_id', 'test-app-id'),
        ]);
        app()->forgetInstance(BroadcastManager::class);
        app()->forgetInstance(Broadcaster::class);

        require base_path('routes/channels.php');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->uploader = $this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters']);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_authorises_the_bound_device_on_its_own_channel(): void
    {
        Passport::actingAs($this->uploader, ['grm:upload']);
        $this->bindToken($this->uploader, DesktopClient::factory()->for($this->uploader)->create(['id' => self::CLIENT_ID]));

        $this->authChannel('private-desktop-clients.'.self::CLIENT_ID)
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    #[Test]
    #[Group('authorization')]
    public function it_refuses_another_devices_channel(): void
    {
        $otherDevice = DesktopClient::factory()->create(['user_id' => $this->uploader->id]);
        Passport::actingAs($this->uploader, ['grm:upload']);
        $this->bindToken($this->uploader, DesktopClient::factory()->for($this->uploader)->create(['id' => self::CLIENT_ID]));

        $this->authChannel("private-desktop-clients.{$otherDevice->id}")->assertForbidden();
    }

    #[Test]
    #[Group('authorization')]
    public function it_refuses_a_channel_that_is_not_a_desktop_client_channel(): void
    {
        Passport::actingAs($this->uploader, ['grm:upload']);
        $this->bindToken($this->uploader, DesktopClient::factory()->for($this->uploader)->create(['id' => self::CLIENT_ID]));

        $this->authChannel("private-App.Models.User.{$this->uploader->id}")->assertForbidden();
        $this->authChannel('private-loot-priorities')->assertForbidden();
    }

    #[Test]
    #[Group('authorization')]
    public function it_refuses_a_transient_cookie_token(): void
    {
        $this->actingAs($this->uploader->withAccessToken(new TransientToken), 'api');

        $this->authChannel('private-desktop-clients.'.self::CLIENT_ID)->assertForbidden();
    }

    #[Test]
    #[Group('authorization')]
    public function it_refuses_a_user_who_lost_upload_grm_rosters(): void
    {
        $user = $this->userWithPermissions(['use-desktop-client']);
        $desktopClient = DesktopClient::factory()->create(['id' => self::CLIENT_ID, 'user_id' => $user->id]);
        Passport::actingAs($user, ['grm:upload']);
        $this->bindToken($user, $desktopClient);

        $this->authChannel('private-desktop-clients.'.self::CLIENT_ID)->assertForbidden();
    }

    // ==================== helpers ====================

    private function authChannel(string $channel): TestResponse
    {
        return $this->postJson(route('api.grm.broadcasting.auth'), [
            'socket_id' => '123.456',
            'channel_name' => $channel,
        ], [
            'X-Desktop-Client-Id' => self::CLIENT_ID,
            'X-Desktop-Client-Name' => 'Test PC',
        ]);
    }

    /**
     * Bind a new access token of the user to the desktop client and make it the user's current token.
     */
    private function bindToken(User $user, DesktopClient $desktopClient): void
    {
        $binding = DesktopClientToken::factory()->for($desktopClient)->create();

        $user->withAccessToken(new AccessToken([
            ...$user->currentAccessToken()->toArray(),
            'oauth_access_token_id' => $binding->access_token_id,
        ]));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $role = DiscordRole::factory()->grantsPermissions()->create();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $role->givePermissionTo($permission);
        }

        $user = User::factory()->create();
        $user->discordRoles()->attach($role->id);
        $user->load('discordRoles.permissions');

        return $user;
    }
}
