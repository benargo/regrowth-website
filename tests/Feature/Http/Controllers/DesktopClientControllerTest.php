<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\DesktopClient;
use App\Models\DesktopClientToken;
use App\Models\DiscordRole;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\Support\DashboardTestCase;

#[Group('auth')]
class DesktopClientControllerTest extends DashboardTestCase
{
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

    #[Test]
    public function it_lists_devices_for_officers(): void
    {
        $older = DesktopClient::factory()->linked()->create(['last_seen_at' => now()->subDay()]);
        $newer = DesktopClient::factory()->for($this->officer)->linked()->create(['last_seen_at' => now()]);

        $this->actingAs($this->officer)
            ->get(route('management.desktop-clients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manage/DesktopClients/Index')
                ->has('devices.data', 2)
                ->where('devices.data.0.id', $newer->id)
                ->where('devices.data.0.isLinked', true)
                ->where('devices.data.0.meta.canRevoke', true)
                ->where('devices.data.1.id', $older->id)
                ->where('devices.data.1.meta.canRevoke', false));
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_members(): void
    {
        $this->actingAs(User::factory()->member()->create())
            ->get(route('management.desktop-clients.index'))
            ->assertForbidden();
    }

    #[Test]
    public function it_lets_the_owner_revoke(): void
    {
        $desktopClient = DesktopClient::factory()->for($this->officer)->linked()->create();

        $this->actingAs($this->officer)
            ->delete(route('management.desktop-clients.destroy', $desktopClient))
            ->assertRedirect()
            ->assertSessionHas('success', "Signed out of {$desktopClient->name}.");

        $desktopClient = DesktopClient::with('accessToken.refreshToken')->find($desktopClient->id);

        $this->assertTrue($desktopClient->accessToken->revoked);
        $this->assertTrue($desktopClient->accessToken->refreshToken->revoked);
        $this->assertFalse($desktopClient->is_linked);
    }

    #[Test]
    public function it_revokes_every_token_bound_to_the_device(): void
    {
        $desktopClient = DesktopClient::factory()->for($this->officer)->create();
        $oldBinding = DesktopClientToken::factory()->for($desktopClient)->create();
        $currentBinding = DesktopClientToken::factory()->for($desktopClient)->create();
        $desktopClient->update(['oauth_access_token_id' => $currentBinding->access_token_id]);

        foreach ([$oldBinding, $currentBinding] as $binding) {
            RefreshToken::forceCreate([
                'id' => Str::random(80),
                'access_token_id' => $binding->access_token_id,
                'revoked' => false,
                'expires_at' => now()->addDays(90),
            ]);
        }

        $this->actingAs($this->officer)
            ->delete(route('management.desktop-clients.destroy', $desktopClient))
            ->assertRedirect();

        foreach ([$oldBinding, $currentBinding] as $binding) {
            $accessToken = Token::with('refreshToken')->findOrFail($binding->access_token_id);

            $this->assertTrue($accessToken->revoked);
            $this->assertTrue($accessToken->refreshToken->revoked);
        }
    }

    #[Test]
    public function it_keeps_the_device_row_when_revoking_a_device_with_no_token(): void
    {
        $desktopClient = DesktopClient::factory()->for($this->officer)->create(['oauth_access_token_id' => null]);

        $this->actingAs($this->officer)
            ->delete(route('management.desktop-clients.destroy', $desktopClient))
            ->assertRedirect();

        $this->assertDatabaseHas('desktop_clients', ['id' => $desktopClient->id]);
    }

    #[Test]
    public function it_lets_a_user_with_revoke_any_desktop_client_revoke_any_device(): void
    {
        $user = $this->userWithPermissions(['view-officer-dashboard', 'revoke-any-desktop-client']);
        $desktopClient = DesktopClient::factory()->linked()->create();

        $this->actingAs($user)
            ->delete(route('management.desktop-clients.destroy', $desktopClient))
            ->assertRedirect();

        $this->assertFalse(DesktopClient::with('accessToken.refreshToken')->find($desktopClient->id)->is_linked);
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_revoking_another_officers_device(): void
    {
        $desktopClient = DesktopClient::factory()->linked()->create();

        $this->actingAs($this->officer)
            ->delete(route('management.desktop-clients.destroy', $desktopClient))
            ->assertForbidden();

        $this->assertTrue(DesktopClient::with('accessToken.refreshToken')->find($desktopClient->id)->is_linked);
    }

    #[Test]
    #[Group('account')]
    public function it_signs_out_the_owners_client_from_the_account_page_without_any_permission(): void
    {
        $user = User::factory()->create();
        $client = DesktopClient::factory()->linked()->create(['user_id' => $user->id, 'name' => 'Raid laptop']);

        $response = $this->actingAs($user)
            ->from(route('account.index'))
            ->delete(route('account.desktop-clients.destroy', $client));

        $response->assertRedirect(route('account.index'));
        $response->assertSessionHas('success', 'Signed out of Raid laptop.');
        $this->assertTrue(DesktopClient::with('accessToken')->find($client->id)->accessToken->revoked);
    }

    #[Test]
    #[Group('account')]
    #[Group('authorization')]
    public function it_forbids_signing_out_another_users_client_from_the_account_page(): void
    {
        $client = DesktopClient::factory()->linked()->create();

        $response = $this->actingAs(User::factory()->create())->delete(route('account.desktop-clients.destroy', $client));

        $response->assertForbidden();
        $this->assertFalse(DesktopClient::with('accessToken')->find($client->id)->accessToken->revoked);
    }

    #[Test]
    #[Group('account')]
    public function it_signs_out_all_of_the_users_clients(): void
    {
        config(['services.grm.desktop_oauth_client_id' => Client::factory()->create()->getKey()]);
        $user = User::factory()->create();
        $clients = DesktopClient::factory()->linked()->count(2)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('account.desktop-clients.destroy-all'));

        $response->assertRedirect(route('account.index'));
        $response->assertSessionHas('success', 'Signed out of all desktop apps.');

        foreach ($clients as $client) {
            $client = DesktopClient::with('accessToken.refreshToken')->find($client->id);

            $this->assertTrue($client->accessToken->revoked);
            $this->assertTrue($client->accessToken->refreshToken->revoked);
        }
    }

    #[Test]
    #[Group('account')]
    public function it_signs_out_a_client_whose_access_token_has_no_refresh_token(): void
    {
        config(['services.grm.desktop_oauth_client_id' => Client::factory()->create()->getKey()]);
        $user = User::factory()->create();
        $client = DesktopClient::factory()->linked()->create(['user_id' => $user->id]);
        $client->accessToken->refreshToken->delete();

        $this->actingAs($user)->delete(route('account.desktop-clients.destroy-all'));

        $this->assertTrue(DesktopClient::with('accessToken')->find($client->id)->accessToken->revoked);
    }

    #[Test]
    #[Group('account')]
    #[Group('authorization')]
    public function it_leaves_other_users_clients_signed_in_when_signing_out_all_clients(): void
    {
        config(['services.grm.desktop_oauth_client_id' => Client::factory()->create()->getKey()]);
        $user = User::factory()->create();
        DesktopClient::factory()->linked()->create(['user_id' => $user->id]);
        $other = DesktopClient::factory()->linked()->create();

        $this->actingAs($user)->delete(route('account.desktop-clients.destroy-all'));

        $other = DesktopClient::with('accessToken.refreshToken')->find($other->id);

        $this->assertFalse($other->accessToken->revoked);
        $this->assertFalse($other->accessToken->refreshToken->revoked);
    }

    #[Test]
    #[Group('account')]
    public function it_revokes_every_desktop_token_of_the_user_directly_when_signing_out_all_clients(): void
    {
        config(['services.grm.desktop_oauth_client_id' => Client::factory()->create()->getKey()]);
        $user = User::factory()->create();
        $orphanedToken = $this->accessTokenFor($user, config('services.grm.desktop_oauth_client_id'));
        $otherClientToken = $this->accessTokenFor($user, Client::factory()->create()->getKey());

        $this->actingAs($user)->delete(route('account.desktop-clients.destroy-all'));

        $this->assertTrue($orphanedToken->fresh()->revoked);
        $this->assertTrue($orphanedToken->refreshToken->fresh()->revoked);
        $this->assertFalse($otherClientToken->fresh()->revoked);
        $this->assertFalse($otherClientToken->refreshToken->fresh()->revoked);
    }

    #[Test]
    #[Group('account')]
    public function it_keeps_the_desktop_client_rows_when_signing_out_all_clients(): void
    {
        config(['services.grm.desktop_oauth_client_id' => Client::factory()->create()->getKey()]);
        $user = User::factory()->create();
        $client = DesktopClient::factory()->linked()->create(['user_id' => $user->id]);

        $this->actingAs($user)->delete(route('account.desktop-clients.destroy-all'));

        $this->assertDatabaseHas('desktop_clients', ['id' => $client->id]);
    }

    #[Test]
    #[Group('account')]
    #[Group('authorization')]
    public function it_redirects_guests_to_login_when_signing_out_all_clients(): void
    {
        $this->delete(route('account.desktop-clients.destroy-all'))->assertRedirect('/login');
    }

    // ==================== helpers ====================

    private function accessTokenFor(User $user, string $clientId): Token
    {
        $token = Token::forceCreate([
            'id' => Str::random(80),
            'user_id' => $user->id,
            'client_id' => $clientId,
            'name' => null,
            'scopes' => ['grm:upload', 'grm:targets'],
            'revoked' => false,
            'expires_at' => now()->addHour(),
        ]);

        RefreshToken::forceCreate([
            'id' => Str::random(80),
            'access_token_id' => $token->id,
            'revoked' => false,
            'expires_at' => now()->addDays(90),
        ]);

        return $token;
    }
}
