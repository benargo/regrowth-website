<?php

namespace Tests\Feature\Jobs\Addon;

use App\Enums\Presence;
use App\Jobs\Addon\BuildExportFile;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\Item;
use App\Models\ItemPriority;
use App\Models\LootPriority;
use App\Models\Phase;
use App\Models\PlannedAbsence;
use App\Models\Report;
use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsAttendanceFixtures;
use Tests\TestCase;

#[Group('platform')]
class BuildExportFileTest extends TestCase
{
    use BuildsAttendanceFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('addon');
    }

    #[Test]
    public function it_throws_when_the_export_cannot_be_written(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $disk = \Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        $manager = \Mockery::mock(FilesystemManager::class);
        $manager->shouldReceive('disk')->andReturn($disk);
        Log::spy();

        try {
            (new BuildExportFile($gameVersion->id))->handle($manager);
            $this->fail('Expected a RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString((string) $gameVersion->id, $e->getMessage());
        }

        Log::shouldNotHaveReceived('info', ['Addon export data file built successfully.', \Mockery::any()]);
    }

    // ==================== scheduling ====================

    #[Test]
    public function it_schedules_one_delayed_build_per_current_roster_id(): void
    {
        $current = GameVersion::factory()->fetchableRoster()->create();
        $notCurrent = GameVersion::factory()->create(['blizzard_namespace' => null]);

        BuildExportFile::scheduleForGameVersionIds([$current->id, $current->id, null, $notCurrent->id]);

        Queue::assertPushed(BuildExportFile::class, 1);
        Queue::assertPushed(
            BuildExportFile::class,
            fn (BuildExportFile $job): bool => $job->gameVersionId === $current->id && $job->delay !== null,
        );
    }

    #[Test]
    public function it_schedules_a_build_for_a_current_roster_id_given_as_a_string(): void
    {
        $current = GameVersion::factory()->fetchableRoster()->create();

        BuildExportFile::scheduleForGameVersionIds([(string) $current->id]);

        Queue::assertPushed(
            BuildExportFile::class,
            fn (BuildExportFile $job): bool => $job->gameVersionId === $current->id,
        );
    }

    #[Test]
    public function it_coalesces_repeated_schedules_into_one_pending_build(): void
    {
        $current = GameVersion::factory()->fetchableRoster()->create();

        BuildExportFile::schedule($current->id);
        BuildExportFile::schedule($current->id);

        Queue::assertPushed(BuildExportFile::class, 1);
    }

    // ==================== job contract ====================

    #[Test]
    public function it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new BuildExportFile(1));
    }

    #[Test]
    public function it_is_unique_per_game_version_until_processing(): void
    {
        $job = new BuildExportFile(42);

        $this->assertInstanceOf(ShouldBeUniqueUntilProcessing::class, $job);
        $this->assertSame('42', $job->uniqueId());
        $this->assertSame(600, $job->uniqueFor);
    }

    #[Test]
    public function it_has_correct_job_tags(): void
    {
        $this->assertSame(['regrowth-addon', 'regrowth-addon:build', 'game-version:42'], (new BuildExportFile(42))->tags());
    }

    // ==================== storage ====================

    #[Test]
    public function it_writes_the_export_to_the_private_disk_under_the_game_version_id(): void
    {
        Storage::fake('local');
        $version = $this->exportableVersion();

        $this->buildExport($version);

        Storage::disk('addon')->assertExists("{$version->id}/export.json");
        Storage::disk('local')->assertMissing('addon/export.json');
    }

    #[Test]
    public function it_writes_valid_json_to_storage(): void
    {
        $version = $this->exportableVersion();

        app()->call([new BuildExportFile($version->id), 'handle']);

        $content = Storage::disk('addon')->get("{$version->id}/export.json");
        $this->assertNotNull(json_decode($content, true));
    }

    #[Test]
    public function it_does_nothing_when_the_game_version_no_longer_exists(): void
    {
        app()->call([new BuildExportFile(999999), 'handle']);

        $this->assertSame([], Storage::disk('addon')->allFiles());
    }

    // ==================== data structure ====================

    #[Test]
    public function it_includes_all_sections_in_output(): void
    {
        $version = $this->exportableVersion();

        $data = $this->buildExport($version);
        $this->assertArrayHasKey('system', $data);
        $this->assertArrayHasKey('phases', $data);
        $this->assertArrayHasKey('priorities', $data);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('players', $data);
        $this->assertArrayHasKey('councillors', $data);
    }

    #[Test]
    public function it_includes_the_game_version_in_system(): void
    {
        $version = $this->exportableVersion();

        $data = $this->buildExport($version);

        $this->assertSame(['slug' => $version->slug, 'name' => $version->title], $data['system']['game_version']);
    }

    #[Test]
    public function it_includes_system_date_generated_as_unix_timestamp(): void
    {
        Carbon::setTestNow('2025-06-01 12:00:00');

        $version = $this->exportableVersion();

        $data = $this->buildExport($version);
        $this->assertArrayHasKey('date_generated', $data['system']);
        $this->assertIsInt($data['system']['date_generated']);
        $this->assertEquals(Carbon::now()->unix(), $data['system']['date_generated']);
    }

    #[Test]
    public function it_defaults_to_empty_sections_when_no_data_exists(): void
    {
        $version = $this->exportableVersion();

        $data = $this->buildExport($version);
        $this->assertEmpty($data['phases']);
        $this->assertEmpty($data['priorities']);
        $this->assertEmpty($data['items']);
        $this->assertEmpty($data['players']);
        $this->assertEmpty($data['councillors']);
    }

    // ==================== phase data ====================

    #[Test]
    public function it_includes_phase_id_number_and_start_date(): void
    {
        $version = $this->exportableVersion();
        $phase = Phase::factory()->for($version)->create(['number' => 2, 'start_date' => Carbon::parse('2025-01-15 20:00:00')]);

        $data = $this->buildExport($version);
        $phaseData = collect($data['phases'])->firstWhere('id', $phase->id);
        $this->assertNotNull($phaseData);
        $this->assertEquals(2, $phaseData['number']);
        $this->assertIsInt($phaseData['start_date']);
        $this->assertEquals($phase->start_date->unix(), $phaseData['start_date']);
    }

    #[Test]
    public function it_returns_null_for_phase_without_start_date(): void
    {
        $version = $this->exportableVersion();
        $phase = Phase::factory()->for($version)->unscheduled()->create();

        $data = $this->buildExport($version);
        $phaseData = collect($data['phases'])->firstWhere('id', $phase->id);
        $this->assertNull($phaseData['start_date']);
    }

    #[Test]
    public function it_lists_phases_in_release_order(): void
    {
        $version = $this->exportableVersion();
        Phase::factory()->for($version)->unscheduled()->create(['number' => 4]);
        Phase::factory()->for($version)->create(['number' => 1, 'start_date' => '2026-05-15 00:00:00']);
        Phase::factory()->for($version)->create(['number' => 3, 'start_date' => '2026-01-01 00:00:00']);

        $data = $this->buildExport($version);
        $this->assertEquals([3, 1, 4], collect($data['phases'])->pluck('number')->toArray());
    }

    // ==================== priority data ====================

    #[Test]
    public function it_only_includes_priorities_with_items(): void
    {
        $version = $this->exportableVersion();
        $priorityWithItems = LootPriority::factory()->create(['title' => 'Tank']);
        $priorityWithoutItems = LootPriority::factory()->create(['title' => 'Healer']);
        $item = Item::factory()->for($version)->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priorityWithItems->id]);

        $data = $this->buildExport($version);
        $ids = collect($data['priorities'])->pluck('id')->toArray();
        $this->assertContains($priorityWithItems->id, $ids);
        $this->assertNotContains($priorityWithoutItems->id, $ids);
    }

    #[Test]
    public function it_includes_priority_icon_from_media(): void
    {
        $version = $this->exportableVersion();
        $priority = LootPriority::factory()->create(['title' => 'Tank']);
        $priority->addMediaFromString('BINARY')
            ->usingFileName('spell_nature_strength.jpg')
            ->toMediaCollection('blizzard_icons');
        $item = Item::factory()->for($version)->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        $data = $this->buildExport($version);
        $priorityData = collect($data['priorities'])->firstWhere('id', $priority->id);
        $this->assertEquals('spell_nature_strength', $priorityData['icon']);
    }

    #[Test]
    public function it_returns_null_icon_when_no_media_attached(): void
    {
        $version = $this->exportableVersion();
        $priority = LootPriority::factory()->create(['title' => 'Tank']);
        $item = Item::factory()->for($version)->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        $data = $this->buildExport($version);
        $priorityData = collect($data['priorities'])->firstWhere('id', $priority->id);
        $this->assertNull($priorityData['icon']);
    }

    #[Test]
    public function it_includes_priority_id_and_name(): void
    {
        $version = $this->exportableVersion();
        $priority = LootPriority::factory()->create(['title' => 'Warlock']);
        $item = Item::factory()->for($version)->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        $data = $this->buildExport($version);
        $priorityData = collect($data['priorities'])->firstWhere('id', $priority->id);
        $this->assertEquals($priority->id, $priorityData['id']);
        $this->assertEquals('Warlock', $priorityData['name']);
    }

    #[Test]
    public function it_excludes_meme_type_priorities(): void
    {
        $version = $this->exportableVersion();
        $standardPriority = LootPriority::factory()->create(['type' => 'Role', 'title' => 'Tank']);
        $memePriority = LootPriority::factory()->create(['type' => 'Meme', 'title' => 'Disenchant']);
        $item = Item::factory()->for($version)->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $standardPriority->id]);
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $memePriority->id]);

        $data = $this->buildExport($version);
        $ids = collect($data['priorities'])->pluck('id')->toArray();
        $this->assertContains($standardPriority->id, $ids);
        $this->assertNotContains($memePriority->id, $ids);
    }

    // ==================== item data ====================

    #[Test]
    public function it_only_includes_items_with_priorities(): void
    {
        $version = $this->exportableVersion();
        $itemWithPriorities = Item::factory()->for($version)->create();
        $itemWithoutPriorities = Item::factory()->for($version)->create();
        $priority = LootPriority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $itemWithPriorities->id, 'priority_id' => $priority->id]);

        $data = $this->buildExport($version);
        $itemIds = collect($data['items'])->pluck('item_id')->toArray();
        $this->assertContains($itemWithPriorities->blizzard_id, $itemIds);
        $this->assertNotContains($itemWithoutPriorities->blizzard_id, $itemIds);
    }

    #[Test]
    public function it_includes_item_priorities_with_weight(): void
    {
        $version = $this->exportableVersion();
        $item = Item::factory()->for($version)->create();
        $priority = LootPriority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
            'weight' => 75,
        ]);

        $data = $this->buildExport($version);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->blizzard_id);
        $itemPriorityData = collect($itemData['priorities'])->firstWhere('priority_id', $priority->id);
        $this->assertEquals(75, $itemPriorityData['weight']);
    }

    #[Test]
    public function it_excludes_meme_priorities_from_item_priorities(): void
    {
        $version = $this->exportableVersion();
        $item = Item::factory()->for($version)->create();
        $standardPriority = LootPriority::factory()->create(['type' => 'Spec', 'title' => 'Fire Mage']);
        $memePriority = LootPriority::factory()->create(['type' => 'Meme', 'title' => 'Bakas']);
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $standardPriority->id]);
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $memePriority->id]);

        $data = $this->buildExport($version);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->blizzard_id);
        $priorityIds = collect($itemData['priorities'])->pluck('priority_id')->toArray();
        $this->assertContains($standardPriority->id, $priorityIds);
        $this->assertNotContains($memePriority->id, $priorityIds);
    }

    #[Test]
    public function it_only_exports_phases_of_the_game_version(): void
    {
        $version = $this->exportableVersion();
        $other = $this->exportableVersion();
        $phase = Phase::factory()->for($version)->create();
        Phase::factory()->for($other)->create();

        $this->assertSame([$phase->id], collect($this->buildExport($version)['phases'])->pluck('id')->all());
    }

    #[Test]
    public function it_only_exports_items_of_the_game_version(): void
    {
        $version = $this->exportableVersion();
        $other = $this->exportableVersion();
        $priority = LootPriority::factory()->create();
        $item = Item::factory()->for($version)->create();
        $otherItem = Item::factory()->for($other)->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);
        ItemPriority::factory()->create(['item_id' => $otherItem->id, 'priority_id' => $priority->id]);

        $this->assertSame([$item->blizzard_id], collect($this->buildExport($version)['items'])->pluck('item_id')->all());
    }

    #[Test]
    public function it_sends_the_blizzard_item_id_as_item_id(): void
    {
        $version = $this->exportableVersion();
        $item = Item::factory()->for($version)->create(['blizzard_id' => 30311]);
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => LootPriority::factory()->create()->id]);

        $data = $this->buildExport($version);

        $this->assertSame(30311, $data['items'][0]['item_id']);
    }

    #[Test]
    public function it_only_exports_priorities_that_have_items_in_the_game_version(): void
    {
        $version = $this->exportableVersion();
        $other = $this->exportableVersion();
        $mine = LootPriority::factory()->create();
        $theirs = LootPriority::factory()->create();
        ItemPriority::factory()->create(['item_id' => Item::factory()->for($version)->create()->id, 'priority_id' => $mine->id]);
        ItemPriority::factory()->create(['item_id' => Item::factory()->for($other)->create()->id, 'priority_id' => $theirs->id]);

        $this->assertSame([$mine->id], collect($this->buildExport($version)['priorities'])->pluck('id')->all());
    }

    #[Test]
    public function it_only_exports_councillors_of_the_game_version(): void
    {
        $version = $this->exportableVersion();
        $other = $this->exportableVersion();
        Character::factory()->for($version)->lootCouncillor()->create(['name' => 'Mine']);
        Character::factory()->for($other)->lootCouncillor()->create(['name' => 'Theirs']);

        $this->assertSame(['Mine'], collect($this->buildExport($version)['councillors'])->pluck('name')->all());
    }

    // ==================== clean notes ====================

    #[Test]
    public function it_cleans_notes_by_removing_wowhead_links(): void
    {
        $version = $this->exportableVersion();
        $item = Item::factory()->for($version)->create(['notes' => 'Get !wh[Thunderfury](item=19019) from the boss']);
        $priority = LootPriority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        $data = $this->buildExport($version);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->blizzard_id);
        $this->assertEquals('Get Thunderfury from the boss', $itemData['notes']);
    }

    #[Test]
    public function it_cleans_notes_by_removing_markdown_links(): void
    {
        $version = $this->exportableVersion();
        $item = Item::factory()->for($version)->create(['notes' => 'Check [this guide](https://example.com) for details']);
        $priority = LootPriority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        $data = $this->buildExport($version);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->blizzard_id);
        $this->assertEquals('Check this guide for details', $itemData['notes']);
    }

    #[Test]
    public function it_cleans_notes_by_removing_bold_formatting(): void
    {
        $version = $this->exportableVersion();
        $item = Item::factory()->for($version)->create(['notes' => 'This is **very important** information']);
        $priority = LootPriority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        $data = $this->buildExport($version);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->blizzard_id);
        $this->assertEquals('This is very important information', $itemData['notes']);
    }

    #[Test]
    public function it_cleans_notes_by_removing_italic_formatting(): void
    {
        $version = $this->exportableVersion();
        $item = Item::factory()->for($version)->create(['notes' => 'This is *emphasized* text']);
        $priority = LootPriority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        $data = $this->buildExport($version);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->blizzard_id);
        $this->assertEquals('This is emphasized text', $itemData['notes']);
    }

    #[Test]
    public function it_cleans_notes_by_removing_underline_formatting(): void
    {
        $version = $this->exportableVersion();
        $item = Item::factory()->for($version)->create(['notes' => 'This is __underlined__ text']);
        $priority = LootPriority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        $data = $this->buildExport($version);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->blizzard_id);
        $this->assertEquals('This is underlined text', $itemData['notes']);
    }

    #[Test]
    public function it_cleans_notes_by_removing_inline_code(): void
    {
        $version = $this->exportableVersion();
        $item = Item::factory()->for($version)->create(['notes' => 'Use the `command` here']);
        $priority = LootPriority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        $data = $this->buildExport($version);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->blizzard_id);
        $this->assertEquals('Use the command here', $itemData['notes']);
    }

    #[Test]
    public function it_cleans_notes_by_removing_headers(): void
    {
        $version = $this->exportableVersion();
        $item = Item::factory()->for($version)->create(['notes' => '## Section Title']);
        $priority = LootPriority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        $data = $this->buildExport($version);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->blizzard_id);
        $this->assertEquals('Section Title', $itemData['notes']);
    }

    #[Test]
    public function it_cleans_notes_by_removing_strikethrough(): void
    {
        $version = $this->exportableVersion();
        $item = Item::factory()->for($version)->create(['notes' => 'This is ~~deleted~~ text']);
        $priority = LootPriority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        $data = $this->buildExport($version);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->blizzard_id);
        $this->assertEquals('This is deleted text', $itemData['notes']);
    }

    #[Test]
    public function it_returns_null_for_null_notes(): void
    {
        $version = $this->exportableVersion();
        $item = Item::factory()->for($version)->create(['notes' => null]);
        $priority = LootPriority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        $data = $this->buildExport($version);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->blizzard_id);
        $this->assertNull($itemData['notes']);
    }

    #[Test]
    public function it_normalizes_whitespace_in_notes(): void
    {
        $version = $this->exportableVersion();
        $item = Item::factory()->for($version)->create(['notes' => "Multiple   spaces\nand\nnewlines"]);
        $priority = LootPriority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        $data = $this->buildExport($version);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->blizzard_id);
        $this->assertEquals('Multiple spaces and newlines', $itemData['notes']);
    }

    // ==================== attendance data ====================

    #[Test]
    public function it_writes_no_players_when_no_ranks_count_attendance(): void
    {
        $gameVersion = $this->createAttendanceVersion();
        $this->createRank($gameVersion, false);

        $data = $this->buildExport($gameVersion);
        $this->assertEmpty($data['players']);
    }

    #[Test]
    public function it_writes_no_players_when_no_ranks_exist(): void
    {
        $version = $this->exportableVersion();

        $data = $this->buildExport($version);
        $this->assertEmpty($data['players']);
    }

    #[Test]
    public function it_logs_a_warning_and_writes_no_players_when_the_version_has_no_counting_ranks(): void
    {
        $gameVersion = $this->createAttendanceVersion();
        $this->createRank($gameVersion, false);
        $this->createReport($gameVersion, '2025-01-15 20:00:00');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'BuildExportFile: no counting ranks configured, skipping attendance data.';
            });
        Log::shouldReceive('info')->once();

        $data = $this->buildExport($gameVersion);
        $this->assertEmpty($data['players']);
    }

    #[Test]
    public function it_writes_no_players_when_no_qualifying_reports_exist(): void
    {
        $gameVersion = $this->createAttendanceVersion();
        $rank = $this->createRank($gameVersion);
        $this->createCharacter($gameVersion, $rank);
        $this->createReport($gameVersion, '2025-01-15 20:00:00', $this->createTag($gameVersion, false));

        $data = $this->buildExport($gameVersion);
        $this->assertEmpty($data['players']);
    }

    #[Test]
    public function it_builds_attendance_from_database_pivot(): void
    {
        $gameVersion = $this->createAttendanceVersion();
        $character = $this->createCharacter($gameVersion, $this->createRank($gameVersion), ['name' => 'TestPlayer']);
        $report = $this->createReport($gameVersion, Carbon::parse('2025-01-15 20:00:00'));
        $this->attend($report, $character);

        $data = $this->buildExport($gameVersion);
        $this->assertCount(1, $data['players']);
        $playerData = collect($data['players'])->firstWhere('name', 'TestPlayer');
        $this->assertNotNull($playerData);
        $this->assertEquals(1, $playerData['attendance']['attended']);
        $this->assertEquals(1, $playerData['attendance']['total']);
        $this->assertEquals(100.0, $playerData['attendance']['percentage']);
    }

    #[Test]
    public function it_leaves_reports_without_a_game_version_out_of_attendance(): void
    {
        $gameVersion = $this->createAttendanceVersion();
        $character = $this->createCharacter($gameVersion, $this->createRank($gameVersion), ['name' => 'TestPlayer']);
        $counted = $this->createReport($gameVersion, Carbon::parse('2025-01-15 20:00:00'));
        $this->attend($counted, $character);

        $unversioned = Report::factory()
            ->withGuildTag($this->createTag($gameVersion))
            ->create(['start_time' => Carbon::parse('2025-01-22 20:00:00')]);
        $this->attend($unversioned, $character);

        $this->assertNull($unversioned->game_version_id);

        $data = $this->buildExport($gameVersion);
        $playerData = collect($data['players'])->firstWhere('name', 'TestPlayer');
        $this->assertEquals(1, $playerData['attendance']['attended']);
        $this->assertEquals(1, $playerData['attendance']['total']);
    }

    #[Test]
    public function it_maps_character_id_from_model(): void
    {
        $gameVersion = $this->createAttendanceVersion();
        $character = $this->createCharacter($gameVersion, $this->createRank($gameVersion), ['name' => 'TestPlayer']);
        $this->attend($this->createReport($gameVersion, Carbon::parse('2025-01-15 20:00:00')), $character);

        $data = $this->buildExport($gameVersion);
        $playerData = collect($data['players'])->firstWhere('name', 'TestPlayer');
        $this->assertEquals($character->id, $playerData['id']);
    }

    #[Test]
    public function it_includes_first_attendance_date_as_unix_timestamp(): void
    {
        $gameVersion = $this->createAttendanceVersion();
        $character = $this->createCharacter($gameVersion, $this->createRank($gameVersion), ['name' => 'TestPlayer']);
        $report = $this->createReport($gameVersion, Carbon::parse('2025-01-15 20:00:00'));
        $this->attend($report, $character);

        $data = $this->buildExport($gameVersion);
        $playerData = collect($data['players'])->firstWhere('name', 'TestPlayer');
        $this->assertIsInt($playerData['attendance']['first_attendance']);
        $this->assertEquals($report->start_time->unix(), $playerData['attendance']['first_attendance']);
    }

    #[Test]
    public function it_excludes_characters_from_non_counting_ranks(): void
    {
        $gameVersion = $this->createAttendanceVersion();
        $countingChar = $this->createCharacter($gameVersion, $this->createRank($gameVersion), ['name' => 'CountingPlayer']);
        $nonCountingChar = $this->createCharacter($gameVersion, $this->createRank($gameVersion, false), ['name' => 'NonCountingPlayer']);
        $report = $this->createReport($gameVersion, Carbon::parse('2025-01-15 20:00:00'));
        $this->attend($report, $countingChar);
        $this->attend($report, $nonCountingChar);

        $data = $this->buildExport($gameVersion);
        $names = collect($data['players'])->pluck('name')->toArray();
        $this->assertContains('CountingPlayer', $names);
        $this->assertNotContains('NonCountingPlayer', $names);
    }

    #[Test]
    public function it_excludes_reports_from_non_counting_guild_tags(): void
    {
        $gameVersion = $this->createAttendanceVersion();
        $character = $this->createCharacter($gameVersion, $this->createRank($gameVersion), ['name' => 'TestPlayer']);
        $countingReport = $this->createReport($gameVersion, Carbon::parse('2025-01-15 20:00:00'));
        $nonCountingReport = $this->createReport($gameVersion, Carbon::parse('2025-01-22 20:00:00'), $this->createTag($gameVersion, false));
        $this->attend($countingReport, $character);
        $this->attend($nonCountingReport, $character);

        $data = $this->buildExport($gameVersion);
        $playerData = collect($data['players'])->firstWhere('name', 'TestPlayer');
        $this->assertEquals(1, $playerData['attendance']['total']);
    }

    #[Test]
    public function it_writes_todays_player_fields_for_a_known_fixture(): void
    {
        $gameVersion = $this->createAttendanceVersion();
        $rank = $this->createRank($gameVersion);
        $thrall = $this->createCharacter($gameVersion, $rank, ['name' => 'Thrall']);
        $jaina = $this->createCharacter($gameVersion, $rank, ['name' => 'Jaina']);
        $first = $this->createReport($gameVersion, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $second = $this->createReport($gameVersion, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        $third = $this->createReport($gameVersion, Carbon::parse('2025-01-15 20:00', 'Europe/Paris'));
        $this->attend($first, $thrall);
        $this->attend($second, $thrall, Presence::Benched);
        $this->attend($third, $thrall, Presence::Absent);
        $this->attend($second, $jaina);
        PlannedAbsence::factory()->create(['character_id' => $jaina->id, 'start_date' => '2025-01-15', 'end_date' => '2025-01-15']);

        $players = collect($this->buildExport($gameVersion)['players'])->keyBy('name');
        $this->assertSame(['first_attendance' => $first->start_time->unix(), 'attended' => 2, 'total' => 3, 'percentage' => 66.67], $players['Thrall']['attendance']);
        $this->assertSame(['first_attendance' => $second->start_time->unix(), 'attended' => 1, 'total' => 1, 'percentage' => 100], $players['Jaina']['attendance']);
        $this->assertSame(['Jaina', 'Thrall'], $players->keys()->all());
    }

    #[Test]
    public function it_builds_players_from_the_exported_game_version(): void
    {
        $older = $this->createAttendanceVersion(['release_date' => '2024-01-01']);
        $newer = $this->createAttendanceVersion(['release_date' => '2024-06-01']);
        $this->attend($this->createReport($older, '2025-01-01 20:00'), $this->createCharacter($older, $this->createRank($older), ['name' => 'Old']));
        $this->attend($this->createReport($newer, '2025-01-01 20:00'), $this->createCharacter($newer, $this->createRank($newer), ['name' => 'New']));

        $this->assertSame(['Old'], collect($this->buildExport($older)['players'])->pluck('name')->all());
    }

    #[Test]
    public function it_exports_no_players_when_the_game_version_has_no_counted_attendance(): void
    {
        $counted = $this->createAttendanceVersion();
        $this->attend($this->createReport($counted, '2025-01-01 20:00'), $this->createCharacter($counted, $this->createRank($counted), ['name' => 'Counted']));
        $version = $this->exportableVersion();

        $this->assertSame([], $this->buildExport($version)['players']);
    }

    // ==================== councillor data ====================

    #[Test]
    public function it_caches_empty_collection_when_no_councillors_exist(): void
    {
        $version = $this->exportableVersion();

        $data = $this->buildExport($version);
        $this->assertEmpty($data['councillors']);
    }

    #[Test]
    public function it_includes_councillors_when_they_exist(): void
    {
        $version = $this->exportableVersion();
        $rank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Officer']);
        Character::factory()->for($version)->lootCouncillor()->create(['name' => 'Councillor1', 'rank_id' => $rank->id]);
        Character::factory()->for($version)->lootCouncillor()->create(['name' => 'Councillor2', 'rank_id' => $rank->id]);

        $data = $this->buildExport($version);
        $this->assertCount(2, $data['councillors']);
    }

    #[Test]
    public function it_includes_councillor_id_name_and_rank(): void
    {
        $version = $this->exportableVersion();
        $rank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Officer']);
        $character = Character::factory()->for($version)->lootCouncillor()->create(['name' => 'TestCouncillor', 'rank_id' => $rank->id]);

        $data = $this->buildExport($version);
        $councillor = collect($data['councillors'])->firstWhere('id', $character->id);
        $this->assertNotNull($councillor);
        $this->assertEquals('TestCouncillor', $councillor['name']);
        $this->assertEquals('Officer', $councillor['rank']);
    }

    #[Test]
    public function it_includes_null_rank_for_councillor_without_rank(): void
    {
        $version = $this->exportableVersion();
        Character::factory()->for($version)->lootCouncillor()->create(['name' => 'UnrankedCouncillor', 'rank_id' => null]);

        $data = $this->buildExport($version);
        $councillor = collect($data['councillors'])->firstWhere('name', 'UnrankedCouncillor');
        $this->assertNull($councillor['rank']);
    }

    #[Test]
    public function it_orders_councillors_by_name(): void
    {
        $version = $this->exportableVersion();
        Character::factory()->for($version)->lootCouncillor()->create(['name' => 'Zara']);
        Character::factory()->for($version)->lootCouncillor()->create(['name' => 'Alice']);
        Character::factory()->for($version)->lootCouncillor()->create(['name' => 'Milo']);

        $data = $this->buildExport($version);
        $names = collect($data['councillors'])->pluck('name')->toArray();
        $this->assertEquals(['Alice', 'Milo', 'Zara'], $names);
    }

    #[Test]
    public function it_excludes_non_councillor_characters(): void
    {
        $version = $this->exportableVersion();
        Character::factory()->for($version)->lootCouncillor()->create(['name' => 'IsCouncillor']);
        Character::factory()->for($version)->create(['name' => 'NotCouncillor']);

        $data = $this->buildExport($version);
        $names = collect($data['councillors'])->pluck('name')->toArray();
        $this->assertContains('IsCouncillor', $names);
        $this->assertNotContains('NotCouncillor', $names);
    }

    // ==================== failure handling ====================

    #[Test]
    public function it_logs_error_on_failure(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'BuildExportFile job failed.'
                    && isset($context['error'])
                    && $context['game_version_id'] === 7;
            });

        $job = new BuildExportFile(7);
        $job->failed(new \RuntimeException('Test failure'));
    }

    // ==================== helpers ====================

    /**
     * Run the job for the given game version and return the decoded export.
     *
     * @return array<string, mixed>
     */
    protected function buildExport(GameVersion $gameVersion): array
    {
        app()->call([new BuildExportFile($gameVersion->id), 'handle']);

        return json_decode(Storage::disk('addon')->get("{$gameVersion->id}/export.json"), true);
    }

    /**
     * Create a game version that is eligible for export.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function exportableVersion(array $attributes = []): GameVersion
    {
        return GameVersion::factory()->fetchableRoster()->create($attributes);
    }
}
