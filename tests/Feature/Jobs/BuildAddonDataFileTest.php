<?php

namespace Tests\Feature\Jobs;

use App\Jobs\BuildAddonDataFile;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\ItemPriority;
use App\Models\LootCouncil\Priority;
use App\Models\WarcraftLogs\GuildTag;
use App\Services\Attendance\Calculators\GuildAttendanceCalculator;
use App\Services\Blizzard\Data\GuildMember;
use App\Services\Blizzard\GuildService as BlizzardGuildService;
use App\Services\WarcraftLogs\Attendance;
use App\Services\WarcraftLogs\Data\GuildAttendance;
use App\Services\WarcraftLogs\Data\PlayerAttendance;
use App\Services\WarcraftLogs\Data\PlayerAttendanceStats;
use App\Services\WarcraftLogs\GuildTags;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class BuildAddonDataFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * Run the job with default mocked services.
     */
    protected function runJob(?GuildTags $guildTags = null, ?Attendance $attendance = null): void
    {
        if ($guildTags === null) {
            $guildTags = Mockery::mock(GuildTags::class);
            $guildTags->shouldReceive('toCollection')->andReturn(collect());
        }

        if ($attendance === null) {
            $attendance = Mockery::mock(Attendance::class);
        }

        $job = new BuildAddonDataFile;
        $job->handle($guildTags, $attendance);
    }

    /**
     * Run the job and return the stored data.
     *
     * @return array<string, mixed>
     */
    protected function runJobAndGetData(?GuildTags $guildTags = null, ?Attendance $attendance = null): array
    {
        $this->runJob($guildTags, $attendance);

        return json_decode(Storage::disk('local')->get('addon/export.json'), true);
    }

    // ==========================================
    // Storage Output Tests
    // ==========================================

    public function test_it_writes_export_data_to_storage(): void
    {
        $this->runJob();

        Storage::disk('local')->assertExists('addon/export.json');
    }

    public function test_it_writes_valid_json_to_storage(): void
    {
        $this->runJob();

        $content = Storage::disk('local')->get('addon/export.json');
        $this->assertNotNull(json_decode($content, true));
    }

    // ==========================================
    // Data Structure Tests
    // ==========================================

    public function test_exported_data_contains_system_section_with_date_generated(): void
    {
        Carbon::setTestNow('2025-06-01 12:00:00');

        $data = $this->runJobAndGetData();

        $this->assertArrayHasKey('system', $data);
        $this->assertArrayHasKey('date_generated', $data['system']);
        $this->assertIsInt($data['system']['date_generated']);
        $this->assertEquals(Carbon::now()->unix(), $data['system']['date_generated']);
    }

    public function test_exported_data_does_not_contain_user_info(): void
    {
        $data = $this->runJobAndGetData();

        $this->assertArrayNotHasKey('user', $data['system']);
    }

    public function test_exported_data_contains_all_sections(): void
    {
        $data = $this->runJobAndGetData();

        $this->assertArrayHasKey('priorities', $data);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('players', $data);
        $this->assertArrayHasKey('councillors', $data);
    }

    // ==========================================
    // Priority Tests
    // ==========================================

    public function test_it_includes_priorities_that_have_items(): void
    {
        $priorityWithItems = Priority::factory()->create(['title' => 'Tank']);
        $priorityWithoutItems = Priority::factory()->create(['title' => 'Healer']);
        $item = Item::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priorityWithItems->id,
        ]);

        $data = $this->runJobAndGetData();

        $priorityIds = collect($data['priorities'])->pluck('id')->toArray();
        $this->assertContains($priorityWithItems->id, $priorityIds);
        $this->assertNotContains($priorityWithoutItems->id, $priorityIds);
    }

    public function test_it_includes_priority_icon_from_media(): void
    {
        $priority = Priority::factory()->create([
            'title' => 'Tank',
            'media' => [
                'media_type' => 'spell',
                'media_id' => 12345,
                'media_name' => 'spell_nature_strength',
            ],
        ]);
        $item = Item::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $data = $this->runJobAndGetData();

        $priorityData = collect($data['priorities'])->firstWhere('id', $priority->id);
        $this->assertEquals('spell_nature_strength', $priorityData['icon']);
    }

    public function test_it_returns_null_icon_when_media_name_missing(): void
    {
        $priority = Priority::factory()->create([
            'title' => 'Tank',
            'media' => [
                'media_type' => 'spell',
                'media_id' => 12345,
            ],
        ]);
        $item = Item::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $data = $this->runJobAndGetData();

        $priorityData = collect($data['priorities'])->firstWhere('id', $priority->id);
        $this->assertNull($priorityData['icon']);
    }

    // ==========================================
    // Item Tests
    // ==========================================

    public function test_it_includes_items_that_have_priorities(): void
    {
        $itemWithPriorities = Item::factory()->create();
        $itemWithoutPriorities = Item::factory()->create();
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $itemWithPriorities->id,
            'priority_id' => $priority->id,
        ]);

        $data = $this->runJobAndGetData();

        $itemIds = collect($data['items'])->pluck('item_id')->toArray();
        $this->assertContains($itemWithPriorities->id, $itemIds);
        $this->assertNotContains($itemWithoutPriorities->id, $itemIds);
    }

    public function test_it_includes_item_priorities_with_weight(): void
    {
        $item = Item::factory()->create();
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
            'weight' => 75,
        ]);

        $data = $this->runJobAndGetData();

        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertNotNull($itemData);

        $itemPriorityData = collect($itemData['priorities'])->firstWhere('priority_id', $priority->id);
        $this->assertEquals(75, $itemPriorityData['weight']);
    }

    // ==========================================
    // Clean Notes Tests
    // ==========================================

    public function test_it_cleans_notes_by_removing_wowhead_links(): void
    {
        $item = Item::factory()->create(['notes' => 'Get !wh[Thunderfury](item=19019) from the boss']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $data = $this->runJobAndGetData();

        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertEquals('Get Thunderfury from the boss', $itemData['notes']);
    }

    public function test_it_cleans_notes_by_removing_markdown_links(): void
    {
        $item = Item::factory()->create(['notes' => 'Check [this guide](https://example.com) for details']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $data = $this->runJobAndGetData();

        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertEquals('Check this guide for details', $itemData['notes']);
    }

    public function test_it_cleans_notes_by_removing_bold_formatting(): void
    {
        $item = Item::factory()->create(['notes' => 'This is **very important** information']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $data = $this->runJobAndGetData();

        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertEquals('This is very important information', $itemData['notes']);
    }

    public function test_it_cleans_notes_by_removing_italic_formatting(): void
    {
        $item = Item::factory()->create(['notes' => 'This is *emphasized* text']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $data = $this->runJobAndGetData();

        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertEquals('This is emphasized text', $itemData['notes']);
    }

    public function test_it_cleans_notes_by_removing_underline_formatting(): void
    {
        $item = Item::factory()->create(['notes' => 'This is __underlined__ text']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $data = $this->runJobAndGetData();

        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertEquals('This is underlined text', $itemData['notes']);
    }

    public function test_it_cleans_notes_by_removing_inline_code(): void
    {
        $item = Item::factory()->create(['notes' => 'Use the `command` here']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $data = $this->runJobAndGetData();

        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertEquals('Use the command here', $itemData['notes']);
    }

    public function test_it_cleans_notes_by_removing_headers(): void
    {
        $item = Item::factory()->create(['notes' => '## Section Title']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $data = $this->runJobAndGetData();

        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertEquals('Section Title', $itemData['notes']);
    }

    public function test_it_cleans_notes_by_removing_strikethrough(): void
    {
        $item = Item::factory()->create(['notes' => 'This is ~~deleted~~ text']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $data = $this->runJobAndGetData();

        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertEquals('This is deleted text', $itemData['notes']);
    }

    public function test_it_returns_null_for_null_notes(): void
    {
        $item = Item::factory()->create(['notes' => null]);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $data = $this->runJobAndGetData();

        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertNull($itemData['notes']);
    }

    public function test_it_normalizes_whitespace_in_notes(): void
    {
        $item = Item::factory()->create(['notes' => "Multiple   spaces\nand\nnewlines"]);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $data = $this->runJobAndGetData();

        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertEquals('Multiple spaces and newlines', $itemData['notes']);
    }

    // ==========================================
    // Player Attendance Tests
    // ==========================================

    public function test_it_includes_empty_players_when_no_attendance_tags_exist(): void
    {
        $guildTags = Mockery::mock(GuildTags::class);
        $guildTags->shouldReceive('toCollection')->andReturn(collect());

        $data = $this->runJobAndGetData($guildTags);

        $this->assertEmpty($data['players']);
    }

    public function test_it_includes_empty_players_when_no_tags_count_attendance(): void
    {
        $tag = GuildTag::factory()->doesNotCountAttendance()->create();

        $guildTags = Mockery::mock(GuildTags::class);
        $guildTags->shouldReceive('toCollection')->andReturn(collect([$tag]));

        $data = $this->runJobAndGetData($guildTags);

        $this->assertEmpty($data['players']);
    }

    public function test_it_includes_player_attendance_data_when_tags_exist(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->create();

        $guildTags = Mockery::mock(GuildTags::class);
        $guildTags->shouldReceive('toCollection')->andReturn(collect([$tag]));

        $attendance = Mockery::mock(Attendance::class);
        $attendance->shouldReceive('tags')->andReturnSelf();
        $attendance->shouldReceive('playerNames')->andReturnSelf();
        $attendance->shouldReceive('get')
            ->andReturn(collect([
                new GuildAttendance(
                    code: 'report1',
                    startTime: Carbon::parse('2025-01-15 20:00:00'),
                    players: [new PlayerAttendance(name: 'TestPlayer', presence: 1)],
                ),
            ]));

        $calculator = Mockery::mock(GuildAttendanceCalculator::class);
        $calculator->shouldReceive('calculate')
            ->andReturn(collect([
                new PlayerAttendanceStats(
                    name: 'TestPlayer',
                    firstAttendance: Carbon::parse('2025-01-15 20:00:00'),
                    totalReports: 10,
                    reportsAttended: 8,
                    percentage: 80.0
                ),
            ]));

        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')
            ->andReturn(collect([
                new GuildMember(
                    character: ['id' => 1, 'name' => 'TestPlayer'],
                    rank: 1,
                ),
            ]));

        $this->app->instance(GuildAttendanceCalculator::class, $calculator);
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $data = $this->runJobAndGetData($guildTags, $attendance);

        $this->assertCount(1, $data['players']);
        $this->assertEquals('TestPlayer', $data['players'][0]['name']);
        $this->assertEquals(8, $data['players'][0]['attendance']['attended']);
        $this->assertEquals(10, $data['players'][0]['attendance']['total']);
        $this->assertEquals(80.0, $data['players'][0]['attendance']['percentage']);
    }

    public function test_it_includes_first_attendance_date_in_player_data(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->create();
        $firstAttendance = Carbon::parse('2025-01-15 20:00:00');

        $guildTags = Mockery::mock(GuildTags::class);
        $guildTags->shouldReceive('toCollection')->andReturn(collect([$tag]));

        $attendance = Mockery::mock(Attendance::class);
        $attendance->shouldReceive('tags')->andReturnSelf();
        $attendance->shouldReceive('playerNames')->andReturnSelf();
        $attendance->shouldReceive('get')
            ->andReturn(collect([
                new GuildAttendance(
                    code: 'report1',
                    startTime: $firstAttendance,
                    players: [new PlayerAttendance(name: 'TestPlayer', presence: 1)],
                ),
            ]));

        $calculator = Mockery::mock(GuildAttendanceCalculator::class);
        $calculator->shouldReceive('calculate')
            ->andReturn(collect([
                new PlayerAttendanceStats(
                    name: 'TestPlayer',
                    firstAttendance: $firstAttendance,
                    totalReports: 5,
                    reportsAttended: 5,
                    percentage: 100.0
                ),
            ]));

        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')
            ->andReturn(collect([
                new GuildMember(
                    character: ['id' => 1, 'name' => 'TestPlayer'],
                    rank: 1,
                ),
            ]));

        $this->app->instance(GuildAttendanceCalculator::class, $calculator);
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $data = $this->runJobAndGetData($guildTags, $attendance);

        $this->assertNotNull($data['players'][0]['attendance']['first_attendance']);
    }

    // ==========================================
    // Councillor Tests
    // ==========================================

    public function test_it_includes_empty_councillors_when_none_exist(): void
    {
        $data = $this->runJobAndGetData();

        $this->assertEmpty($data['councillors']);
    }

    public function test_it_includes_councillors_when_they_exist(): void
    {
        $rank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Officer']);
        Character::factory()->lootCouncillor()->create(['name' => 'Councillor1', 'rank_id' => $rank->id]);
        Character::factory()->lootCouncillor()->create(['name' => 'Councillor2', 'rank_id' => $rank->id]);

        $data = $this->runJobAndGetData();

        $this->assertCount(2, $data['councillors']);
    }

    public function test_it_includes_councillor_id_name_and_rank(): void
    {
        $rank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Officer']);
        $character = Character::factory()->lootCouncillor()->create(['name' => 'TestCouncillor', 'rank_id' => $rank->id]);

        $data = $this->runJobAndGetData();

        $councillor = collect($data['councillors'])->firstWhere('id', $character->id);
        $this->assertNotNull($councillor);
        $this->assertEquals('TestCouncillor', $councillor['name']);
        $this->assertEquals('Officer', $councillor['rank']);
    }

    public function test_it_includes_null_rank_for_councillor_without_rank(): void
    {
        Character::factory()->lootCouncillor()->create(['name' => 'UnrankedCouncillor', 'rank_id' => null]);

        $data = $this->runJobAndGetData();

        $councillor = collect($data['councillors'])->firstWhere('name', 'UnrankedCouncillor');
        $this->assertNull($councillor['rank']);
    }

    public function test_it_orders_councillors_by_name(): void
    {
        Character::factory()->lootCouncillor()->create(['name' => 'Zara']);
        Character::factory()->lootCouncillor()->create(['name' => 'Alice']);
        Character::factory()->lootCouncillor()->create(['name' => 'Milo']);

        $data = $this->runJobAndGetData();

        $names = collect($data['councillors'])->pluck('name')->toArray();
        $this->assertEquals(['Alice', 'Milo', 'Zara'], $names);
    }

    public function test_it_excludes_non_councillor_characters(): void
    {
        Character::factory()->lootCouncillor()->create(['name' => 'IsCouncillor']);
        Character::factory()->create(['name' => 'NotCouncillor']);

        $data = $this->runJobAndGetData();

        $names = collect($data['councillors'])->pluck('name')->toArray();
        $this->assertContains('IsCouncillor', $names);
        $this->assertNotContains('NotCouncillor', $names);
    }

    // ==========================================
    // Failure Handling
    // ==========================================

    public function test_it_logs_error_on_failure(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'BuildAddonDataFile failed.'
                    && isset($context['error'])
                    && isset($context['trace']);
            });

        $job = new BuildAddonDataFile;
        $job->failed(new \RuntimeException('Test failure'));
    }
}
