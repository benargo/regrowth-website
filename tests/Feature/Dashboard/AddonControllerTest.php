<?php

namespace Tests\Feature\Dashboard;

use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\ItemPriority;
use App\Models\LootCouncil\Priority;
use App\Models\User;
use App\Models\WarcraftLogs\GuildTag;
use App\Services\Blizzard\Data\GuildMember;
use App\Services\Blizzard\GuildService as BlizzardGuildService;
use App\Services\WarcraftLogs\Data\PlayerAttendanceStats;
use App\Services\WarcraftLogs\GuildService as WarcraftLogsGuildService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\LazyCollection;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class AddonControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock WarcraftLogs GuildService to return empty tags by default
        // This prevents API calls during tests that don't specifically test attendance
        $wclGuildService = Mockery::mock(WarcraftLogsGuildService::class);
        $wclGuildService->shouldReceive('getGuildTags')
            ->andReturn(collect())
            ->byDefault();

        $this->app->instance(WarcraftLogsGuildService::class, $wclGuildService);
    }

    // ==========================================
    // Authentication & Authorization Tests
    // ==========================================

    public function test_export_requires_authentication(): void
    {
        $response = $this->get(route('dashboard.addon.export'));

        $response->assertRedirect('/login');
    }

    public function test_export_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertForbidden();
    }

    public function test_export_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertForbidden();
    }

    public function test_export_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertForbidden();
    }

    public function test_export_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertOk();
    }

    public function test_export_json_requires_authentication(): void
    {
        $response = $this->get(route('dashboard.addon.export.json'));

        $response->assertRedirect('/login');
    }

    public function test_export_json_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertForbidden();
    }

    public function test_export_json_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertForbidden();
    }

    public function test_export_json_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertForbidden();
    }

    public function test_export_json_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertOk();
    }

    public function test_export_schema_requires_authentication(): void
    {
        $response = $this->get(route('dashboard.addon.export.schema'));

        $response->assertRedirect('/login');
    }

    public function test_export_schema_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertForbidden();
    }

    public function test_export_schema_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertForbidden();
    }

    public function test_export_schema_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertForbidden();
    }

    public function test_export_schema_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertOk();
    }

    // ==========================================
    // Export Endpoint Tests
    // ==========================================

    public function test_export_renders_inertia_page_with_base64_data(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/Base64')
            ->has('exportedData')
        );
    }

    public function test_export_returns_valid_base64_encoded_data(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $decoded = base64_decode($exportedData, true);
        $this->assertNotFalse($decoded, 'exportedData is not valid base64');
        $json = json_decode($decoded, true);
        $this->assertIsArray($json, 'Decoded data is not valid JSON');
    }

    public function test_export_includes_system_info_with_user_data(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode(base64_decode($exportedData), true);

        $this->assertArrayHasKey('system', $data);
        $this->assertArrayHasKey('date_generated', $data['system']);
        $this->assertArrayHasKey('user', $data['system']);
        $this->assertEquals($user->id, $data['system']['user']['id']);
        $this->assertEquals($user->displayName, $data['system']['user']['name']);
    }

    public function test_export_includes_priorities_that_have_items(): void
    {
        $user = User::factory()->officer()->create();

        $priorityWithItems = Priority::factory()->create(['title' => 'Tank']);
        $priorityWithoutItems = Priority::factory()->create(['title' => 'Healer']);
        $item = Item::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priorityWithItems->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode(base64_decode($exportedData), true);
        $priorityIds = collect($data['priorities'])->pluck('id')->toArray();

        $this->assertContains($priorityWithItems->id, $priorityIds);
        $this->assertNotContains($priorityWithoutItems->id, $priorityIds);
    }

    public function test_export_includes_items_that_have_priorities(): void
    {
        $user = User::factory()->officer()->create();

        $itemWithPriorities = Item::factory()->create();
        $itemWithoutPriorities = Item::factory()->create();
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $itemWithPriorities->id,
            'priority_id' => $priority->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode(base64_decode($exportedData), true);
        $itemIds = collect($data['items'])->pluck('item_id')->toArray();

        $this->assertContains($itemWithPriorities->id, $itemIds);
        $this->assertNotContains($itemWithoutPriorities->id, $itemIds);
    }

    public function test_export_includes_item_priorities_with_weight(): void
    {
        $user = User::factory()->officer()->create();

        $item = Item::factory()->create();
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
            'weight' => 75,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode(base64_decode($exportedData), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

        $this->assertNotNull($itemData);
        $itemPriorityData = collect($itemData['priorities'])->firstWhere('priority_id', $priority->id);
        $this->assertNotNull($itemPriorityData);
        $this->assertEquals(75, $itemPriorityData['weight']);
    }

    // ==========================================
    // Export JSON Endpoint Tests
    // ==========================================

    public function test_export_json_renders_inertia_page_with_json_data(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/JSON')
            ->has('exportedData')
        );
    }

    public function test_export_json_returns_valid_json_string(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $json = json_decode($exportedData, true);
        $this->assertIsArray($json, 'exportedData is not valid JSON');
    }

    public function test_export_json_returns_pretty_printed_json(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $this->assertStringContainsString("\n", $exportedData);
    }

    public function test_export_json_includes_complete_data_structure(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode($exportedData, true);

        $this->assertArrayHasKey('system', $data);
        $this->assertArrayHasKey('priorities', $data);
        $this->assertArrayHasKey('items', $data);
    }

    // ==========================================
    // Export Schema Endpoint Tests
    // ==========================================

    public function test_export_schema_renders_inertia_page_with_schema(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/Schema')
            ->has('schema')
        );
    }

    public function test_export_schema_includes_json_schema_metadata(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema', fn (Assert $schema) => $schema
                ->where('$schema', 'https://json-schema.org/draft/2020-12/schema')
                ->has('$id')
                ->where('title', 'Regrowth Loot Tool Export Schema')
                ->has('description')
                ->where('type', 'object')
                ->has('properties')
            )
        );
    }

    public function test_export_schema_defines_system_properties(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.system')
            ->has('schema.properties.system.properties.date_generated')
            ->has('schema.properties.system.properties.user')
        );
    }

    public function test_export_schema_defines_priorities_properties(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.priorities')
            ->where('schema.properties.priorities.type', 'array')
        );
    }

    public function test_export_schema_defines_items_properties(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.items')
            ->where('schema.properties.items.type', 'array')
        );
    }

    public function test_export_schema_defines_players_properties(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.players')
            ->where('schema.properties.players.type', 'array')
        );
    }

    public function test_export_schema_defines_player_attendance_properties(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.players.items.properties.name')
            ->has('schema.properties.players.items.properties.attendance')
            ->has('schema.properties.players.items.properties.attendance.properties.first_attendance')
            ->has('schema.properties.players.items.properties.attendance.properties.attended')
            ->has('schema.properties.players.items.properties.attendance.properties.total')
            ->has('schema.properties.players.items.properties.attendance.properties.percentage')
        );
    }

    public function test_export_schema_id_contains_version_1_1_1(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $schema = $response->original->getData()['page']['props']['schema'];

        $this->assertStringContainsString('v=1.1.1', $schema['$id']);
    }

    // ==========================================
    // Clean Notes Tests
    // ==========================================

    public function test_export_cleans_notes_by_removing_wowhead_links(): void
    {
        $user = User::factory()->officer()->create();

        $item = Item::factory()->create(['notes' => 'Get !wh[Thunderfury](item=19019) from the boss']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode(base64_decode($exportedData), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

        $this->assertEquals('Get Thunderfury from the boss', $itemData['notes']);
    }

    public function test_export_cleans_notes_by_removing_markdown_links(): void
    {
        $user = User::factory()->officer()->create();

        $item = Item::factory()->create(['notes' => 'Check [this guide](https://example.com) for details']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode(base64_decode($exportedData), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

        $this->assertEquals('Check this guide for details', $itemData['notes']);
    }

    public function test_export_cleans_notes_by_removing_bold_formatting(): void
    {
        $user = User::factory()->officer()->create();

        $item = Item::factory()->create(['notes' => 'This is **very important** information']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode(base64_decode($exportedData), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

        $this->assertEquals('This is very important information', $itemData['notes']);
    }

    public function test_export_cleans_notes_by_removing_italic_formatting(): void
    {
        $user = User::factory()->officer()->create();

        $item = Item::factory()->create(['notes' => 'This is *emphasized* text']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode(base64_decode($exportedData), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

        $this->assertEquals('This is emphasized text', $itemData['notes']);
    }

    public function test_export_cleans_notes_by_removing_underline_formatting(): void
    {
        $user = User::factory()->officer()->create();

        $item = Item::factory()->create(['notes' => 'This is __underlined__ text']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode(base64_decode($exportedData), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

        $this->assertEquals('This is underlined text', $itemData['notes']);
    }

    public function test_export_cleans_notes_by_removing_inline_code(): void
    {
        $user = User::factory()->officer()->create();

        $item = Item::factory()->create(['notes' => 'Use the `command` here']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode(base64_decode($exportedData), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

        $this->assertEquals('Use the command here', $itemData['notes']);
    }

    public function test_export_cleans_notes_by_removing_headers(): void
    {
        $user = User::factory()->officer()->create();

        $item = Item::factory()->create(['notes' => '## Section Title']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode(base64_decode($exportedData), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

        $this->assertEquals('Section Title', $itemData['notes']);
    }

    public function test_export_cleans_notes_by_removing_strikethrough(): void
    {
        $user = User::factory()->officer()->create();

        $item = Item::factory()->create(['notes' => 'This is ~~deleted~~ text']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode(base64_decode($exportedData), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

        $this->assertEquals('This is deleted text', $itemData['notes']);
    }

    public function test_export_returns_empty_string_for_null_notes(): void
    {
        $user = User::factory()->officer()->create();

        $item = Item::factory()->create(['notes' => null]);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode(base64_decode($exportedData), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

        $this->assertEquals('', $itemData['notes']);
    }

    public function test_export_normalizes_whitespace_in_notes(): void
    {
        $user = User::factory()->officer()->create();

        $item = Item::factory()->create(['notes' => "Multiple   spaces\nand\nnewlines"]);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode(base64_decode($exportedData), true);
        $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

        $this->assertEquals('Multiple spaces and newlines', $itemData['notes']);
    }

    // ==========================================
    // Priority Data Tests
    // ==========================================

    public function test_export_includes_priority_icon_from_media(): void
    {
        $user = User::factory()->officer()->create();

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

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode(base64_decode($exportedData), true);
        $priorityData = collect($data['priorities'])->firstWhere('id', $priority->id);

        $this->assertEquals('spell_nature_strength', $priorityData['icon']);
    }

    public function test_export_returns_null_icon_when_media_name_missing(): void
    {
        $user = User::factory()->officer()->create();

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

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode(base64_decode($exportedData), true);
        $priorityData = collect($data['priorities'])->firstWhere('id', $priority->id);

        $this->assertNull($priorityData['icon']);
    }

    // ==========================================
    // Player Attendance Data Tests
    // ==========================================

    public function test_export_includes_empty_players_when_no_attendance_tags_exist(): void
    {
        $user = User::factory()->officer()->create();

        // Ensure no guild tags exist
        GuildTag::query()->delete();

        // Mock the WarcraftLogs GuildService to return empty tags
        $wclGuildService = Mockery::mock(WarcraftLogsGuildService::class);
        $wclGuildService->shouldReceive('getGuildTags')
            ->andReturn(collect());

        $this->app->instance(WarcraftLogsGuildService::class, $wclGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode(base64_decode($exportedData), true);

        $this->assertArrayHasKey('players', $data);
        $this->assertEmpty($data['players']);
    }

    public function test_export_includes_empty_players_when_no_tags_count_attendance(): void
    {
        $user = User::factory()->officer()->create();

        // Create a guild tag that doesn't count attendance
        GuildTag::factory()->doesNotCountAttendance()->create();

        // Mock the WarcraftLogs GuildService
        $wclGuildService = Mockery::mock(WarcraftLogsGuildService::class);
        $wclGuildService->shouldReceive('getGuildTags')
            ->andReturn(GuildTag::all());

        $this->app->instance(WarcraftLogsGuildService::class, $wclGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode(base64_decode($exportedData), true);

        $this->assertArrayHasKey('players', $data);
        $this->assertEmpty($data['players']);
    }

    public function test_export_includes_player_attendance_data_when_tags_exist(): void
    {
        $user = User::factory()->officer()->create();

        // Create a guild tag that counts attendance
        $tag = GuildTag::factory()->countsAttendance()->create();

        // Mock the services
        $wclGuildService = Mockery::mock(WarcraftLogsGuildService::class);
        $wclGuildService->shouldReceive('getGuildTags')
            ->andReturn(collect([$tag]));
        $wclGuildService->shouldReceive('getAttendanceLazy')
            ->andReturn(LazyCollection::make([]));
        $wclGuildService->shouldReceive('calculateAttendanceStats')
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

        $this->app->instance(WarcraftLogsGuildService::class, $wclGuildService);
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode(base64_decode($exportedData), true);

        $this->assertArrayHasKey('players', $data);
        $this->assertCount(1, $data['players']);
        $this->assertEquals('TestPlayer', $data['players'][0]['name']);
        $this->assertArrayHasKey('attendance', $data['players'][0]);
        $this->assertEquals(8, $data['players'][0]['attendance']['attended']);
        $this->assertEquals(10, $data['players'][0]['attendance']['total']);
        $this->assertEquals(80.0, $data['players'][0]['attendance']['percentage']);
    }

    public function test_export_player_attendance_includes_first_attendance_date(): void
    {
        $user = User::factory()->officer()->create();

        $tag = GuildTag::factory()->countsAttendance()->create();
        $firstAttendance = Carbon::parse('2025-01-15 20:00:00');

        $wclGuildService = Mockery::mock(WarcraftLogsGuildService::class);
        $wclGuildService->shouldReceive('getGuildTags')
            ->andReturn(collect([$tag]));
        $wclGuildService->shouldReceive('getAttendanceLazy')
            ->andReturn(LazyCollection::make([]));
        $wclGuildService->shouldReceive('calculateAttendanceStats')
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

        $this->app->instance(WarcraftLogsGuildService::class, $wclGuildService);
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode(base64_decode($exportedData), true);

        $this->assertArrayHasKey('first_attendance', $data['players'][0]['attendance']);
        $this->assertNotNull($data['players'][0]['attendance']['first_attendance']);
    }

    public function test_export_json_includes_players_data(): void
    {
        $user = User::factory()->officer()->create();

        $tag = GuildTag::factory()->countsAttendance()->create();

        $wclGuildService = Mockery::mock(WarcraftLogsGuildService::class);
        $wclGuildService->shouldReceive('getGuildTags')
            ->andReturn(collect([$tag]));
        $wclGuildService->shouldReceive('getAttendanceLazy')
            ->andReturn(LazyCollection::make([]));
        $wclGuildService->shouldReceive('calculateAttendanceStats')
            ->andReturn(collect([
                new PlayerAttendanceStats(
                    name: 'TestPlayer',
                    firstAttendance: Carbon::now(),
                    totalReports: 5,
                    reportsAttended: 4,
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

        $this->app->instance(WarcraftLogsGuildService::class, $wclGuildService);
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $exportedData = $response->original->getData()['page']['props']['exportedData'];
        $data = json_decode($exportedData, true);

        $this->assertArrayHasKey('players', $data);
        $this->assertCount(1, $data['players']);
    }
}
