<?php

namespace Tests\Feature\Api\GuildRosterManager;

use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Models\DesktopClient;
use App\Models\DesktopClientToken;
use App\Models\DiscordRole;
use App\Models\Event;
use App\Models\GameVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\AccessToken;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\TransientToken;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

#[Group('characters')]
class TargetControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = '9d4c2f3e-6a1b-4c8e-9f0a-1b2c3d4e5f60';

    #[Test]
    public function it_lists_current_rosters_with_a_client_folder(): void
    {
        GameVersion::factory()->fetchableRoster()->withWowClientFolder('_forever_')->create([
            'title' => 'Forever',
            'slug' => 'forever',
            'guild_name' => 'Regrowth',
            'blizzard_namespace' => BlizzardNamespace::FOREVER,
        ]);
        GameVersion::factory()->fetchableRoster()->create();

        $user = $this->uploader();

        Passport::actingAs($user, ['grm:targets']);

        $this->bindToken($user, DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]));

        $this->getJson(route('api.grm.targets'), $this->deviceHeaders())
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.api+json')
            ->assertJson(['data' => [[
                'id' => 'forever',
                'type' => 'targets',
                'attributes' => [
                    'title' => 'Forever',
                    'clientFolder' => '_forever_',
                    'guildKey' => 'Regrowth',
                    'savedVariablesGlob' => '_forever_/WTF/Account/*/SavedVariables/Guild_Roster_Manager.lua',
                ],
            ]]])
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_returns_the_clock_tolerance(): void
    {
        GameVersion::factory()->fetchableRoster()->withWowClientFolder('_forever_')->create();
        $user = $this->uploader();
        Passport::actingAs($user, ['grm:targets']);
        $this->bindToken($user, DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]));

        $this->getJson(route('api.grm.targets'), $this->deviceHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.attributes.clockToleranceSeconds', 60);

        config(['services.grm.clock_tolerance_seconds' => 90]);

        $this->getJson(route('api.grm.targets'), $this->deviceHeaders())
            ->assertJsonPath('data.0.attributes.clockToleranceSeconds', 90);
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_a_token_without_the_targets_scope(): void
    {
        $user = $this->uploader();
        Passport::actingAs($user, ['grm:upload']);
        $this->bindToken($user, DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]));

        $this->getJson(route('api.grm.targets'), $this->deviceHeaders())->assertForbidden();
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_a_token_whose_user_lost_upload_grm_rosters(): void
    {
        $user = $this->userWithPermissions(['use-desktop-client']);
        Passport::actingAs($user, ['grm:targets']);
        $this->bindToken($user, DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]));

        $this->getJson(route('api.grm.targets'), $this->deviceHeaders())->assertForbidden();
    }

    #[Test]
    #[Group('authorization')]
    public function it_rejects_guests(): void
    {
        $this->getJson(route('api.grm.targets'), $this->deviceHeaders())->assertUnauthorized();
    }

    #[Test]
    public function it_records_the_access_token_id_from_a_real_bearer_token(): void
    {
        $user = $this->uploader();
        $desktopClient = DesktopClient::factory()->for($user)->create(['id' => self::CLIENT_ID]);
        app(ClientRepository::class)->createPersonalAccessGrantClient('test');
        $token = $user->createToken('test', ['grm:targets']);
        DesktopClientToken::factory()->for($desktopClient)->create(['access_token_id' => $token->token->id]);

        $this->withToken($token->accessToken)
            ->getJson(route('api.grm.targets'), $this->deviceHeaders())
            ->assertOk();

        $this->assertSame(
            $token->token->id,
            DesktopClient::query()->where('id', self::CLIENT_ID)->value('oauth_access_token_id'),
        );
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_a_device_token_on_event_routes(): void
    {
        $user = $this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters', 'manage-raid-plans']);
        $event = Event::factory()->create();
        $url = route('api.events.groups.store', $event);

        $this->actingAs($user->withAccessToken(new TransientToken), 'api')
            ->postJson($url, ['name' => 'Healers'])
            ->assertCreated();

        app(ClientRepository::class)->createPersonalAccessGrantClient('test');
        $token = $user->createToken('test', ['grm:upload']);

        $this->app['auth']->forgetGuards();

        $this->withToken($token->accessToken)
            ->postJson($url, ['name' => 'Healers'], $this->deviceHeaders())
            ->assertForbidden();
    }

    // ==================== helpers ====================

    /**
     * @return array<string, string>
     */
    private function deviceHeaders(): array
    {
        return [
            'X-Desktop-Client-Id' => self::CLIENT_ID,
            'X-Desktop-Client-Name' => 'Test PC',
        ];
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

    private function uploader(): User
    {
        return $this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters']);
    }
}
