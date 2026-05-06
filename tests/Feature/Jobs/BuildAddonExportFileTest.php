<?php

namespace Tests\Feature\Jobs;

use App\Jobs\BuildAddonExportFile;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\GuildTag;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\ItemPriority;
use App\Models\LootCouncil\Priority;
use App\Models\Raids\Report;
use App\Services\Attendance\Calculator;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BuildAddonExportFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // Calculator::wholeGuild() throws when no counting ranks exist; give every test a baseline rank so the job can run.
        GuildRank::factory()->create(['count_attendance' => true]);
    }

    // ==========================================
    // Job Contract Tests
    // ==========================================

    #[Test]
    public function it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new BuildAddonExportFile);
    }

    #[Test]
    public function it_has_correct_job_tags(): void
    {
        $this->assertEquals(['regrowth-addon', 'regrowth-addon:build'], (new BuildAddonExportFile)->tags());
    }

    #[Test]
    public function it_applies_rate_limited_with_redis_middleware(): void
    {
        $middleware = (new BuildAddonExportFile)->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(RateLimitedWithRedis::class, $middleware[0]);
    }

    // ==========================================
    // Storage Tests
    // ==========================================

    #[Test]
    public function it_writes_export_data_to_storage(): void
    {
        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        Storage::disk('local')->assertExists('addon/export.json');
    }

    #[Test]
    public function it_writes_valid_json_to_storage(): void
    {
        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $content = Storage::disk('local')->get('addon/export.json');
        $this->assertNotNull(json_decode($content, true));
    }

    // ==========================================
    // Data Structure Tests
    // ==========================================

    #[Test]
    public function it_includes_all_sections_in_output(): void
    {
        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $this->assertArrayHasKey('system', $data);
        $this->assertArrayHasKey('priorities', $data);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('players', $data);
        $this->assertArrayHasKey('councillors', $data);
    }

    #[Test]
    public function it_includes_system_date_generated_as_unix_timestamp(): void
    {
        Carbon::setTestNow('2025-06-01 12:00:00');

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $this->assertArrayHasKey('date_generated', $data['system']);
        $this->assertIsInt($data['system']['date_generated']);
        $this->assertEquals(Carbon::now()->unix(), $data['system']['date_generated']);
    }

    #[Test]
    public function it_defaults_to_empty_sections_when_no_data_exists(): void
    {
        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $this->assertEmpty($data['priorities']);
        $this->assertEmpty($data['items']);
        $this->assertEmpty($data['players']);
        $this->assertEmpty($data['councillors']);
    }

    // ==========================================
    // Priority Data Tests
    // ==========================================

    #[Test]
    public function it_only_includes_priorities_with_items(): void
    {
        $priorityWithItems = Priority::factory()->create(['title' => 'Tank']);
        $priorityWithoutItems = Priority::factory()->create(['title' => 'Healer']);
        $item = Item::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priorityWithItems->id]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $ids = collect($data['priorities'])->pluck('id')->toArray();
        $this->assertContains($priorityWithItems->id, $ids);
        $this->assertNotContains($priorityWithoutItems->id, $ids);
    }

    #[Test]
    public function it_includes_priority_icon_from_media(): void
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
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $priorityData = collect($data['priorities'])->firstWhere('id', $priority->id);
        $this->assertEquals('spell_nature_strength', $priorityData['icon']);
    }

    #[Test]
    public function it_returns_null_icon_when_media_name_missing(): void
    {
        $priority = Priority::factory()->create([
            'title' => 'Tank',
            'media' => ['media_type' => 'spell', 'media_id' => 12345],
        ]);
        $item = Item::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $priorityData = collect($data['priorities'])->firstWhere('id', $priority->id);
        $this->assertNull($priorityData['icon']);
    }

    #[Test]
    public function it_includes_priority_id_and_name(): void
    {
        $priority = Priority::factory()->create(['title' => 'Warlock']);
        $item = Item::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $priorityData = collect($data['priorities'])->firstWhere('id', $priority->id);
        $this->assertEquals($priority->id, $priorityData['id']);
        $this->assertEquals('Warlock', $priorityData['name']);
    }

    // ==========================================
    // Item Data Tests
    // ==========================================

    #[Test]
    public function it_only_includes_items_with_priorities(): void
    {
        $itemWithPriorities = Item::factory()->create();
        $itemWithoutPriorities = Item::factory()->create();
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $itemWithPriorities->id, 'priority_id' => $priority->id]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $itemIds = collect($data['items'])->pluck('item_id')->toArray();
        $this->assertContains($itemWithPriorities->id, $itemIds);
        $this->assertNotContains($itemWithoutPriorities->id, $itemIds);
    }

    #[Test]
    public function it_includes_item_priorities_with_weight(): void
    {
        $item = Item::factory()->create();
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
            'weight' => 75,
        ]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $itemPriorityData = collect($itemData['priorities'])->firstWhere('priority_id', $priority->id);
        $this->assertEquals(75, $itemPriorityData['weight']);
    }

    // ==========================================
    // Clean Notes Tests
    // ==========================================

    #[Test]
    public function it_cleans_notes_by_removing_wowhead_links(): void
    {
        $item = Item::factory()->create(['notes' => 'Get !wh[Thunderfury](item=19019) from the boss']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertEquals('Get Thunderfury from the boss', $itemData['notes']);
    }

    #[Test]
    public function it_cleans_notes_by_removing_markdown_links(): void
    {
        $item = Item::factory()->create(['notes' => 'Check [this guide](https://example.com) for details']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertEquals('Check this guide for details', $itemData['notes']);
    }

    #[Test]
    public function it_cleans_notes_by_removing_bold_formatting(): void
    {
        $item = Item::factory()->create(['notes' => 'This is **very important** information']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertEquals('This is very important information', $itemData['notes']);
    }

    #[Test]
    public function it_cleans_notes_by_removing_italic_formatting(): void
    {
        $item = Item::factory()->create(['notes' => 'This is *emphasized* text']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertEquals('This is emphasized text', $itemData['notes']);
    }

    #[Test]
    public function it_cleans_notes_by_removing_underline_formatting(): void
    {
        $item = Item::factory()->create(['notes' => 'This is __underlined__ text']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertEquals('This is underlined text', $itemData['notes']);
    }

    #[Test]
    public function it_cleans_notes_by_removing_inline_code(): void
    {
        $item = Item::factory()->create(['notes' => 'Use the `command` here']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertEquals('Use the command here', $itemData['notes']);
    }

    #[Test]
    public function it_cleans_notes_by_removing_headers(): void
    {
        $item = Item::factory()->create(['notes' => '## Section Title']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertEquals('Section Title', $itemData['notes']);
    }

    #[Test]
    public function it_cleans_notes_by_removing_strikethrough(): void
    {
        $item = Item::factory()->create(['notes' => 'This is ~~deleted~~ text']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertEquals('This is deleted text', $itemData['notes']);
    }

    #[Test]
    public function it_returns_null_for_null_notes(): void
    {
        $item = Item::factory()->create(['notes' => null]);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertNull($itemData['notes']);
    }

    #[Test]
    public function it_normalizes_whitespace_in_notes(): void
    {
        $item = Item::factory()->create(['notes' => "Multiple   spaces\nand\nnewlines"]);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);
        $this->assertEquals('Multiple spaces and newlines', $itemData['notes']);
    }

    // ==========================================
    // Attendance Data Tests
    // ==========================================

    #[Test]
    public function it_caches_empty_attendance_when_no_ranks_count_attendance(): void
    {
        GuildRank::factory()->doesNotCountAttendance()->create();

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $this->assertEmpty($data['players']);
    }

    #[Test]
    public function it_caches_empty_attendance_when_no_ranks_exist(): void
    {
        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $this->assertEmpty($data['players']);
    }

    #[Test]
    public function it_returns_empty_players_and_logs_warning_when_calculator_throws_empty_collection_exception(): void
    {
        GuildRank::where('count_attendance', true)->delete();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'BuildAddonExportFile: no counting ranks configured, skipping attendance data.'
                    && isset($context['error']);
            });
        Log::shouldReceive('info')->once();

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $this->assertEmpty($data['players']);
    }

    #[Test]
    public function it_caches_empty_attendance_when_no_qualifying_reports_exist(): void
    {
        $rank = GuildRank::factory()->create();
        Character::factory()->create(['rank_id' => $rank->id]);
        GuildTag::factory()->doesNotCountAttendance()->create();

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $this->assertEmpty($data['players']);
    }

    #[Test]
    public function it_builds_attendance_from_database_pivot(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['name' => 'TestPlayer', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-15 20:00:00')]);
        $report->characters()->attach($character->id, ['presence' => 1]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $this->assertCount(1, $data['players']);
        $playerData = collect($data['players'])->firstWhere('name', 'TestPlayer');
        $this->assertNotNull($playerData);
        $this->assertEquals(1, $playerData['attendance']['attended']);
        $this->assertEquals(1, $playerData['attendance']['total']);
        $this->assertEquals(100.0, $playerData['attendance']['percentage']);
    }

    #[Test]
    public function it_maps_character_id_from_model(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['name' => 'TestPlayer', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::now()->subDays(1)]);
        $report->characters()->attach($character->id, ['presence' => 1]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $playerData = collect($data['players'])->firstWhere('name', 'TestPlayer');
        $this->assertEquals($character->id, $playerData['id']);
    }

    #[Test]
    public function it_includes_first_attendance_date(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['name' => 'TestPlayer', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-15 20:00:00')]);
        $report->characters()->attach($character->id, ['presence' => 1]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $playerData = collect($data['players'])->firstWhere('name', 'TestPlayer');
        $this->assertNotNull($playerData['attendance']['first_attendance']);
    }

    #[Test]
    public function it_excludes_characters_from_non_counting_ranks(): void
    {
        $countingRank = GuildRank::factory()->create();
        $nonCountingRank = GuildRank::factory()->doesNotCountAttendance()->create();
        $countingChar = Character::factory()->create(['name' => 'CountingPlayer', 'rank_id' => $countingRank->id]);
        $nonCountingChar = Character::factory()->create(['name' => 'NonCountingPlayer', 'rank_id' => $nonCountingRank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::now()->subDays(1)]);
        $report->characters()->attach($countingChar->id, ['presence' => 1]);
        $report->characters()->attach($nonCountingChar->id, ['presence' => 1]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $names = collect($data['players'])->pluck('name')->toArray();
        $this->assertContains('CountingPlayer', $names);
        $this->assertNotContains('NonCountingPlayer', $names);
    }

    #[Test]
    public function it_excludes_reports_from_non_counting_guild_tags(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['name' => 'TestPlayer', 'rank_id' => $rank->id]);
        $countingTag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $nonCountingTag = GuildTag::factory()->doesNotCountAttendance()->withoutPhase()->create();
        $countingReport = Report::factory()->withGuildTag($countingTag)->create(['start_time' => Carbon::parse('2025-01-15 20:00:00')]);
        $nonCountingReport = Report::factory()->withGuildTag($nonCountingTag)->create(['start_time' => Carbon::parse('2025-01-22 20:00:00')]);
        $countingReport->characters()->attach($character->id, ['presence' => 1]);
        $nonCountingReport->characters()->attach($character->id, ['presence' => 1]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $playerData = collect($data['players'])->firstWhere('name', 'TestPlayer');
        $this->assertEquals(1, $playerData['attendance']['total']);
    }

    // ==========================================
    // Councillor Data Tests
    // ==========================================

    #[Test]
    public function it_caches_empty_collection_when_no_councillors_exist(): void
    {
        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $this->assertEmpty($data['councillors']);
    }

    #[Test]
    public function it_includes_councillors_when_they_exist(): void
    {
        $rank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Officer']);
        Character::factory()->lootCouncillor()->create(['name' => 'Councillor1', 'rank_id' => $rank->id]);
        Character::factory()->lootCouncillor()->create(['name' => 'Councillor2', 'rank_id' => $rank->id]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $this->assertCount(2, $data['councillors']);
    }

    #[Test]
    public function it_includes_councillor_id_name_and_rank(): void
    {
        $rank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Officer']);
        $character = Character::factory()->lootCouncillor()->create(['name' => 'TestCouncillor', 'rank_id' => $rank->id]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $councillor = collect($data['councillors'])->firstWhere('id', $character->id);
        $this->assertNotNull($councillor);
        $this->assertEquals('TestCouncillor', $councillor['name']);
        $this->assertEquals('Officer', $councillor['rank']);
    }

    #[Test]
    public function it_includes_null_rank_for_councillor_without_rank(): void
    {
        Character::factory()->lootCouncillor()->create(['name' => 'UnrankedCouncillor', 'rank_id' => null]);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $councillor = collect($data['councillors'])->firstWhere('name', 'UnrankedCouncillor');
        $this->assertNull($councillor['rank']);
    }

    #[Test]
    public function it_orders_councillors_by_name(): void
    {
        Character::factory()->lootCouncillor()->create(['name' => 'Zara']);
        Character::factory()->lootCouncillor()->create(['name' => 'Alice']);
        Character::factory()->lootCouncillor()->create(['name' => 'Milo']);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $names = collect($data['councillors'])->pluck('name')->toArray();
        $this->assertEquals(['Alice', 'Milo', 'Zara'], $names);
    }

    #[Test]
    public function it_excludes_non_councillor_characters(): void
    {
        Character::factory()->lootCouncillor()->create(['name' => 'IsCouncillor']);
        Character::factory()->create(['name' => 'NotCouncillor']);

        app(BuildAddonExportFile::class)->handle(app(Calculator::class));

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $names = collect($data['councillors'])->pluck('name')->toArray();
        $this->assertContains('IsCouncillor', $names);
        $this->assertNotContains('NotCouncillor', $names);
    }

    // ==========================================
    // Failure Handling Tests
    // ==========================================

    #[Test]
    public function it_logs_error_on_failure(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'BuildAddonExportFile job failed.'
                    && isset($context['error']);
            });

        $job = new BuildAddonExportFile;
        $job->failed(new \RuntimeException('Test failure'));
    }
}
