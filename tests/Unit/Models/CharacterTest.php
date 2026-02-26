<?php

namespace Tests\Unit\Models;

use App\Events\AddonSettingsProcessed;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\WarcraftLogs\Report;
use App\Services\Blizzard\CharacterService;
use App\Services\Blizzard\Data\GuildMember;
use App\Services\Blizzard\GuildService;
use App\Services\Blizzard\PlayableClassService;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class CharacterTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Character::class;
    }

    #[Test]
    public function it_uses_characters_table(): void
    {
        $model = new Character;

        $this->assertSame('characters', $model->getTable());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new Character;

        $this->assertFillable($model, [
            'id',
            'name',
            'is_main',
            'is_loot_councillor',
            'reached_level_cap_at',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new Character;

        $this->assertCasts($model, [
            'is_main' => 'boolean',
            'is_loot_councillor' => 'boolean',
            'reached_level_cap_at' => 'datetime',
        ]);
    }

    #[Test]
    public function it_dispatches_events_on_updated_and_deleted(): void
    {
        $model = new Character;

        $this->assertSame([
            'updated' => AddonSettingsProcessed::class,
            'deleted' => AddonSettingsProcessed::class,
        ], $model->dispatchesEvents());
    }

    #[Test]
    public function it_uses_auto_incrementing_primary_key(): void
    {
        $model = new Character;

        $this->assertSame('id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
    }

    #[Test]
    public function it_can_create_a_character(): void
    {
        $character = $this->create([
            'name' => 'Thrall',
        ]);

        $this->assertTableHas([
            'name' => 'Thrall',
        ]);
        $this->assertModelExists($character);
    }

    #[Test]
    public function it_has_timestamps(): void
    {
        $character = $this->create();

        $this->assertNotNull($character->created_at);
        $this->assertNotNull($character->updated_at);
    }

    #[Test]
    public function it_can_be_created_as_main(): void
    {
        $character = $this->factory()->main()->create();

        $this->assertTrue($character->is_main);
    }

    #[Test]
    public function it_can_be_created_as_loot_councillor(): void
    {
        $character = $this->factory()->lootCouncillor()->create();

        $this->assertTrue($character->is_loot_councillor);
    }

    #[Test]
    public function it_is_not_a_loot_councillor_by_default(): void
    {
        $character = $this->create();

        $this->assertFalse($character->is_loot_councillor);
    }

    // #[Test]
    // public function it_can_be_created_with_reached_level_cap(): void
    // {
    //     $character = $this->factory()->reachedLevelCap()->create();
    //
    //     $this->assertNotNull($character->reached_level_cap_at);
    // }

    #[Test]
    public function reached_level_cap_at_is_null_by_default(): void
    {
        $character = $this->create();

        $this->assertNull($character->reached_level_cap_at);
    }

    #[Test]
    public function it_can_be_created_with_rank(): void
    {
        $character = $this->factory()->withRank()->create();

        $this->assertNotNull($character->rank_id);
        $this->assertInstanceOf(GuildRank::class, $character->rank);
    }

    #[Test]
    public function rank_returns_belongs_to_relationship(): void
    {
        $character = new Character;

        $this->assertInstanceOf(BelongsTo::class, $character->rank());
    }

    #[Test]
    public function rank_returns_associated_guild_rank(): void
    {
        $rank = GuildRank::factory()->create(['position' => 0, 'name' => 'Guild Master']);
        $character = $this->create(['rank_id' => $rank->id]);

        $this->assertInstanceOf(GuildRank::class, $character->rank);
        $this->assertSame($rank->id, $character->rank->id);
    }

    #[Test]
    public function rank_returns_null_when_no_rank_assigned(): void
    {
        $character = $this->create(['rank_id' => null]);

        $this->assertNull($character->rank);
    }

    #[Test]
    public function linked_characters_returns_belongs_to_many_relationship(): void
    {
        $character = new Character;

        $this->assertInstanceOf(BelongsToMany::class, $character->linkedCharacters());
    }

    #[Test]
    public function it_can_link_characters_together(): void
    {
        $mainCharacter = $this->factory()->main()->create(['name' => 'MainChar']);
        $altCharacter = $this->create(['name' => 'AltChar']);

        $altCharacter->linkedCharacters()->attach($mainCharacter->id);

        $this->assertCount(1, $altCharacter->linkedCharacters);
        $this->assertSame($mainCharacter->id, $altCharacter->linkedCharacters->first()->id);
    }

    #[Test]
    public function linked_characters_returns_empty_collection_when_no_links_exist(): void
    {
        $character = $this->create();

        $this->assertCount(0, $character->linkedCharacters);
    }

    #[Test]
    public function main_character_returns_linked_character_with_is_main_true(): void
    {
        $mainCharacter = $this->factory()->main()->create(['name' => 'MainChar']);
        $altCharacter = $this->create(['name' => 'AltChar']);

        $altCharacter->linkedCharacters()->attach($mainCharacter->id);

        $this->assertNotNull($altCharacter->mainCharacter);
        $this->assertSame($mainCharacter->id, $altCharacter->mainCharacter->id);
        $this->assertTrue($altCharacter->mainCharacter->is_main);
    }

    #[Test]
    public function main_character_returns_null_when_no_linked_characters_exist(): void
    {
        $character = $this->create();

        $this->assertNull($character->mainCharacter);
    }

    #[Test]
    public function main_character_returns_null_when_no_linked_character_is_main(): void
    {
        $linkedCharacter = $this->create(['name' => 'LinkedChar', 'is_main' => false]);
        $character = $this->create(['name' => 'Character']);

        $character->linkedCharacters()->attach($linkedCharacter->id);

        $this->assertNull($character->mainCharacter);
    }

    #[Test]
    public function deleting_character_cascades_to_character_links(): void
    {
        Event::fake([AddonSettingsProcessed::class]);

        $mainCharacter = $this->factory()->main()->create(['name' => 'MainChar']);
        $altCharacter = $this->create(['name' => 'AltChar']);

        $altCharacter->linkedCharacters()->attach($mainCharacter->id);

        $this->assertDatabaseHas('character_links', [
            'character_id' => $mainCharacter->id,
            'linked_character_id' => $altCharacter->id,
        ]);

        $altCharacter->delete();

        $this->assertDatabaseMissing('character_links', [
            'linked_character_id' => $altCharacter->id,
        ]);
    }

    #[Test]
    public function rank_is_set_to_null_when_guild_rank_is_deleted(): void
    {
        $rank = GuildRank::factory()->create(['position' => 0, 'name' => 'Officer']);
        $character = $this->create(['rank_id' => $rank->id]);

        $this->assertSame($rank->id, $character->rank_id);

        $rank->delete();

        $character->refresh();
        $this->assertNull($character->rank_id);
    }

    #[Test]
    public function prunable_returns_builder_instance(): void
    {
        $this->mockGuildServiceWithMembers([]);

        $character = new Character;

        $this->assertInstanceOf(Builder::class, $character->prunable());
    }

    #[Test]
    public function prunable_includes_characters_not_in_guild_and_older_than_14_days(): void
    {
        $this->mockGuildServiceWithMembers([]);

        $prunableCharacter = $this->create([
            'name' => 'OldNonMember',
            'updated_at' => now()->subDays(15),
        ]);

        $character = new Character;
        $prunableIds = $character->prunable()->pluck('id')->toArray();

        $this->assertContains($prunableCharacter->id, $prunableIds);
    }

    #[Test]
    public function prunable_excludes_characters_that_are_guild_members(): void
    {
        $guildMember = $this->create([
            'name' => 'GuildMember',
            'updated_at' => now()->subDays(30),
        ]);

        $this->mockGuildServiceWithMembers([
            ['character' => ['id' => $guildMember->id, 'name' => 'GuildMember'], 'rank' => 0],
        ]);

        $character = new Character;
        $prunableIds = $character->prunable()->pluck('id')->toArray();

        $this->assertNotContains($guildMember->id, $prunableIds);
    }

    #[Test]
    public function prunable_excludes_characters_updated_within_14_days(): void
    {
        $this->mockGuildServiceWithMembers([]);

        $recentCharacter = $this->create([
            'name' => 'RecentNonMember',
            'updated_at' => now()->subDays(13),
        ]);

        $character = new Character;
        $prunableIds = $character->prunable()->pluck('id')->toArray();

        $this->assertNotContains($recentCharacter->id, $prunableIds);
    }

    #[Test]
    public function prunable_excludes_characters_updated_exactly_14_days_ago(): void
    {
        $this->mockGuildServiceWithMembers([]);

        $boundaryCharacter = $this->create([
            'name' => 'BoundaryCharacter',
            'updated_at' => now()->subDays(14),
        ]);

        $character = new Character;
        $prunableIds = $character->prunable()->pluck('id')->toArray();

        $this->assertContains($boundaryCharacter->id, $prunableIds);
    }

    #[Test]
    public function prunable_correctly_filters_mixed_scenarios(): void
    {
        $guildMemberOld = $this->create([
            'name' => 'GuildMemberOld',
            'updated_at' => now()->subDays(30),
        ]);

        $guildMemberRecent = $this->create([
            'name' => 'GuildMemberRecent',
            'updated_at' => now()->subDays(5),
        ]);

        $nonMemberOld = $this->create([
            'name' => 'NonMemberOld',
            'updated_at' => now()->subDays(20),
        ]);

        $nonMemberRecent = $this->create([
            'name' => 'NonMemberRecent',
            'updated_at' => now()->subDays(7),
        ]);

        $this->mockGuildServiceWithMembers([
            ['character' => ['id' => $guildMemberOld->id, 'name' => 'GuildMemberOld'], 'rank' => 0],
            ['character' => ['id' => $guildMemberRecent->id, 'name' => 'GuildMemberRecent'], 'rank' => 5],
        ]);

        $character = new Character;
        $prunableIds = $character->prunable()->pluck('id')->toArray();

        $this->assertNotContains($guildMemberOld->id, $prunableIds, 'Guild member (old) should not be prunable');
        $this->assertNotContains($guildMemberRecent->id, $prunableIds, 'Guild member (recent) should not be prunable');
        $this->assertContains($nonMemberOld->id, $prunableIds, 'Non-member (old) should be prunable');
        $this->assertNotContains($nonMemberRecent->id, $prunableIds, 'Non-member (recent) should not be prunable');
    }

    #[Test]
    public function prunable_returns_empty_when_all_characters_are_guild_members(): void
    {
        $character1 = $this->create([
            'name' => 'Member1',
            'updated_at' => now()->subDays(30),
        ]);

        $character2 = $this->create([
            'name' => 'Member2',
            'updated_at' => now()->subDays(60),
        ]);

        $this->mockGuildServiceWithMembers([
            ['character' => ['id' => $character1->id, 'name' => 'Member1'], 'rank' => 0],
            ['character' => ['id' => $character2->id, 'name' => 'Member2'], 'rank' => 1],
        ]);

        $character = new Character;

        $this->assertCount(0, $character->prunable()->get());
    }

    #[Test]
    public function prunable_returns_empty_when_all_characters_are_recent(): void
    {
        $this->mockGuildServiceWithMembers([]);

        $this->create([
            'name' => 'Recent1',
            'updated_at' => now()->subDays(1),
        ]);

        $this->create([
            'name' => 'Recent2',
            'updated_at' => now()->subDays(10),
        ]);

        $character = new Character;

        $this->assertCount(0, $character->prunable()->get());
    }

    #[Test]
    public function playable_class_returns_array_with_id_name_and_icon_url(): void
    {
        $character = $this->create(['name' => 'Thrall']);

        $this->mock(CharacterService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getProfile')
                ->with('Thrall')
                ->andReturn(['character_class' => ['id' => 1]]);
        });

        $this->mock(PlayableClassService::class, function (MockInterface $mock) {
            $mock->shouldReceive('find')
                ->with(1)
                ->andReturn(['name' => 'Warrior']);

            $mock->shouldReceive('iconUrl')
                ->with(1)
                ->andReturn('https://example.com/warrior.png');
        });

        $result = $character->playableClass;

        $this->assertIsArray($result);
        $this->assertSame(1, $result['id']);
        $this->assertSame('Warrior', $result['name']);
        $this->assertSame('https://example.com/warrior.png', $result['icon_url']);
    }

    #[Test]
    public function playable_class_returns_null_when_character_profile_has_no_class(): void
    {
        $character = $this->create(['name' => 'Thrall']);

        $this->mock(CharacterService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getProfile')
                ->with('Thrall')
                ->andReturn([]);
        });

        $this->assertNull($character->playableClass);
    }

    #[Test]
    public function playable_class_icon_url_is_null_when_service_returns_null(): void
    {
        $character = $this->create(['name' => 'Thrall']);

        $this->mock(CharacterService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getProfile')
                ->with('Thrall')
                ->andReturn(['character_class' => ['id' => 1]]);
        });

        $this->mock(PlayableClassService::class, function (MockInterface $mock) {
            $mock->shouldReceive('find')
                ->with(1)
                ->andReturn(['name' => 'Warrior']);

            $mock->shouldReceive('iconUrl')
                ->with(1)
                ->andReturn(null);
        });

        $result = $character->playableClass;

        $this->assertIsArray($result);
        $this->assertNull($result['icon_url']);
    }

    #[Test]
    public function playable_class_returns_null_and_logs_warning_when_request_exception_is_thrown(): void
    {
        $character = $this->create(['name' => 'Thrall']);

        $exception = new RequestException(new Response(new GuzzleResponse(500)));

        $this->mock(CharacterService::class, function (MockInterface $mock) use ($exception) {
            $mock->shouldReceive('getProfile')
                ->with('Thrall')
                ->andThrow($exception);
        });

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message) => str_contains($message, 'Thrall'));

        $this->assertNull($character->playableClass);
    }

    #[Test]
    public function warcraft_logs_reports_returns_belongs_to_many_relationship(): void
    {
        $character = new Character;

        $this->assertInstanceOf(BelongsToMany::class, $character->warcraftLogsReports());
    }

    #[Test]
    public function it_can_attach_warcraft_logs_reports(): void
    {
        $character = $this->create();
        $report = Report::factory()->create();

        $character->warcraftLogsReports()->attach($report->code);

        $this->assertCount(1, $character->warcraftLogsReports);
        $this->assertSame($report->code, $character->warcraftLogsReports->first()->code);
    }

    #[Test]
    public function warcraft_logs_reports_returns_empty_collection_when_none_attached(): void
    {
        $character = $this->create();

        $this->assertCount(0, $character->warcraftLogsReports);
    }

    /**
     * Mock the GuildService to return specific members.
     *
     * @param  array<int, array{character: array{id: int, name: string}, rank: int}>  $memberData
     */
    protected function mockGuildServiceWithMembers(array $memberData): void
    {
        $members = collect($memberData)->map(fn (array $data) => new GuildMember($data['character'], $data['rank']));

        $this->mock(GuildService::class, function (MockInterface $mock) use ($members) {
            $mock->shouldReceive('members')->andReturn($members);
        });
    }
}
