<?php

namespace Tests\Feature\Api\Discord;

use App\Jobs\Discord\AttachAvatarToUser;
use App\Jobs\Discord\ResolveGuildMember;
use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Laravel\Facades\Saloon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('discord-integration')]
#[Group('raiding')]
class ResolveDiscordMemberControllerTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DiscordRole::factory()->officer()->create()
            ->givePermissionTo(Permission::firstOrCreate(['name' => 'create-planned-absences', 'guard_name' => 'web']));

        Queue::fake([ResolveGuildMember::class, AttachAvatarToUser::class]);
        $this->fakeDiscord([]);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_returns_200_ready_when_the_member_already_has_a_user(): void
    {
        User::factory()->create(['id' => '999999999999999999']);

        $this->actingAs(User::factory()->officer()->create())
            ->postJson(route('api.discord.members.resolve', '999999999999999999'))
            ->assertOk()
            ->assertExactJson(['status' => 'ready']);

        Queue::assertNotPushed(ResolveGuildMember::class);
    }

    #[Test]
    public function it_queues_a_high_priority_resolution_and_returns_202_for_an_unknown_member(): void
    {
        $officer = User::factory()->officer()->create();

        $this->actingAs($officer)
            ->postJson(route('api.discord.members.resolve', '999999999999999999'))
            ->assertStatus(202)
            ->assertExactJson(['status' => 'pending']);

        Queue::assertPushedOn('high', ResolveGuildMember::class, fn (ResolveGuildMember $job): bool => $job->memberId === '999999999999999999'
            && $job->requestedBy === $officer->id);
        Saloon::assertNothingSent();
    }

    #[Test]
    #[Group('authorization')]
    public function it_returns_403_for_users_who_cannot_create_planned_absences(): void
    {
        $this->actingAs(User::factory()->raider()->create())
            ->postJson(route('api.discord.members.resolve', '999999999999999999'))
            ->assertForbidden();

        Queue::assertNotPushed(ResolveGuildMember::class);
    }

    #[Test]
    #[Group('authorization')]
    public function it_returns_401_for_guests(): void
    {
        $this->postJson(route('api.discord.members.resolve', '999999999999999999'))
            ->assertUnauthorized();

        Queue::assertNotPushed(ResolveGuildMember::class);
    }

    #[Test]
    #[Group('validation')]
    public function it_returns_404_for_a_non_numeric_member_id(): void
    {
        $this->actingAs(User::factory()->officer()->create())
            ->postJson('/api/discord/members/abc/resolve')
            ->assertNotFound();

        Queue::assertNotPushed(ResolveGuildMember::class);
    }
}
