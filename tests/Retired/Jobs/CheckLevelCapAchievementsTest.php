<?php

namespace Tests\Retired\Jobs;

use App\Jobs\CheckLevelCapAchievements;
use App\Models\Character;
use App\Notifications\DiscordNotifiable;
use App\Notifications\LevelCapAchieved;
use App\Services\Blizzard\Data\GuildMember;
use App\Services\Blizzard\GuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class CheckLevelCapAchievementsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_detects_new_level_70_members_and_sends_notification(): void
    {
        Notification::fake();

        $character = Character::factory()->create([
            'id' => 12345,
            'name' => 'TestCharacter',
            'reached_level_cap_at' => null,
        ]);

        $guildMember = $this->createGuildMember(12345, 'TestCharacter', 70);

        $guildService = $this->mockGuildService(collect([$guildMember]));

        $job = new CheckLevelCapAchievements;
        $job->handle($guildService);

        $character->refresh();
        $this->assertNotNull($character->reached_level_cap_at);

        Notification::assertSentTo(
            new DiscordNotifiable('tbc_chat'),
            LevelCapAchieved::class,
            function (LevelCapAchieved $notification) {
                return $notification->characterNames === ['TestCharacter'];
            }
        );
    }

    public function test_it_ignores_members_below_level_70(): void
    {
        Notification::fake();

        $character = Character::factory()->create([
            'id' => 12345,
            'name' => 'LowLevelChar',
            'reached_level_cap_at' => null,
        ]);

        $guildMember = $this->createGuildMember(12345, 'LowLevelChar', 69);

        $guildService = $this->mockGuildService(collect([$guildMember]));

        $job = new CheckLevelCapAchievements;
        $job->handle($guildService);

        $character->refresh();
        $this->assertNull($character->reached_level_cap_at);

        Notification::assertNothingSent();
    }

    public function test_it_ignores_members_already_tracked_as_level_70(): void
    {
        Notification::fake();

        $character = Character::factory()->create([
            'id' => 12345,
            'name' => 'AlreadyMaxLevel',
            'reached_level_cap_at' => now()->subDays(7),
        ]);

        $guildMember = $this->createGuildMember(12345, 'AlreadyMaxLevel', 70);

        $guildService = $this->mockGuildService(collect([$guildMember]));

        $job = new CheckLevelCapAchievements;
        $job->handle($guildService);

        Notification::assertNothingSent();
    }

    public function test_it_handles_empty_roster_gracefully(): void
    {
        Notification::fake();

        $guildService = $this->mockGuildService(collect([]));

        $job = new CheckLevelCapAchievements;
        $job->handle($guildService);

        Notification::assertNothingSent();
    }

    public function test_it_handles_multiple_new_level_70_members(): void
    {
        Notification::fake();

        $char1 = Character::factory()->create([
            'id' => 111,
            'name' => 'Arthas',
            'reached_level_cap_at' => null,
        ]);

        $char2 = Character::factory()->create([
            'id' => 222,
            'name' => 'Jaina',
            'reached_level_cap_at' => null,
        ]);

        $char3 = Character::factory()->create([
            'id' => 333,
            'name' => 'Thrall',
            'reached_level_cap_at' => null,
        ]);

        $members = collect([
            $this->createGuildMember(111, 'Arthas', 70),
            $this->createGuildMember(222, 'Jaina', 70),
            $this->createGuildMember(333, 'Thrall', 70),
        ]);

        $guildService = $this->mockGuildService($members);

        $job = new CheckLevelCapAchievements;
        $job->handle($guildService);

        $this->assertNotNull($char1->fresh()->reached_level_cap_at);
        $this->assertNotNull($char2->fresh()->reached_level_cap_at);
        $this->assertNotNull($char3->fresh()->reached_level_cap_at);

        Notification::assertSentTo(
            new DiscordNotifiable('tbc_chat'),
            LevelCapAchieved::class,
            function (LevelCapAchieved $notification) {
                return count($notification->characterNames) === 3
                    && in_array('Arthas', $notification->characterNames)
                    && in_array('Jaina', $notification->characterNames)
                    && in_array('Thrall', $notification->characterNames);
            }
        );
    }

    public function test_it_only_notifies_for_characters_that_exist_in_database(): void
    {
        Notification::fake();

        // Only create one character in the database
        $character = Character::factory()->create([
            'id' => 111,
            'name' => 'ExistingChar',
            'reached_level_cap_at' => null,
        ]);

        // But return two level 70 members from the API
        $members = collect([
            $this->createGuildMember(111, 'ExistingChar', 70),
            $this->createGuildMember(222, 'NonExistentChar', 70),
        ]);

        $guildService = $this->mockGuildService($members);

        $job = new CheckLevelCapAchievements;
        $job->handle($guildService);

        $character->refresh();
        $this->assertNotNull($character->reached_level_cap_at);

        Notification::assertSentTo(
            new DiscordNotifiable('tbc_chat'),
            LevelCapAchieved::class,
            function (LevelCapAchieved $notification) {
                return $notification->characterNames === ['ExistingChar'];
            }
        );
    }

    public function test_middleware_includes_without_overlapping(): void
    {
        $job = new CheckLevelCapAchievements;
        $middleware = $job->middleware();

        $hasWithoutOverlapping = collect($middleware)->contains(
            fn ($m) => $m instanceof \Illuminate\Queue\Middleware\WithoutOverlapping
        );

        $this->assertTrue($hasWithoutOverlapping);
    }

    /**
     * Create a mock GuildMember instance.
     */
    protected function createGuildMember(int $id, string $name, int $level): GuildMember
    {
        return new GuildMember(
            character: [
                'id' => $id,
                'name' => $name,
                'level' => $level,
                'realm' => ['id' => 1, 'slug' => 'thunderstrike'],
                'playable_class' => ['id' => 11],
                'playable_race' => ['id' => 4],
                'faction' => ['type' => 'ALLIANCE'],
            ],
            rank: 0,
        );
    }

    /**
     * Create a mock GuildService that returns the given members.
     */
    protected function mockGuildService(Collection $members): GuildService
    {
        $guildService = Mockery::mock(GuildService::class);
        $guildService->shouldReceive('shouldUpdateCharacters')
            ->with(true)
            ->once()
            ->andReturnSelf();
        $guildService->shouldReceive('roster')
            ->once()
            ->andReturn(['members' => []]);
        $guildService->shouldReceive('members')
            ->once()
            ->andReturn($members);

        return $guildService;
    }
}
