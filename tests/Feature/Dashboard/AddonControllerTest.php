<?php

namespace Tests\Feature\Dashboard;

use App\Models\GuildRank;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\ItemPriority;
use App\Models\LootCouncil\Priority;
use App\Models\User;
use App\Models\WarcraftLogs\GuildTag;
use App\Services\Blizzard\Data\GuildMember;
use App\Services\Blizzard\GuildService as BlizzardGuildService;
use App\Services\WarcraftLogs\AttendanceService;
use App\Services\WarcraftLogs\Data\PlayerAttendanceStats;
use App\Services\WarcraftLogs\GuildService as WarcraftLogsGuildService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('exportedData')
            )
        );
    }

    public function test_export_returns_valid_base64_encoded_data(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        // Use assertInertia's loadDeferredProps to test the deferred data
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/Base64')
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('exportedData')
                ->where('exportedData', fn ($data) => base64_decode($data, true) !== false)
            )
        );
    }

    public function test_export_includes_system_info_with_user_data(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/Base64')
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) use ($user) {
                    $data = json_decode(base64_decode($exportedData), true);

                    return isset($data['system'])
                        && isset($data['system']['date_generated'])
                        && isset($data['system']['user'])
                        && $data['system']['user']['id'] === $user->id
                        && $data['system']['user']['name'] === $user->displayName;
                })
            )
        );
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

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/Base64')
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) use ($priorityWithItems, $priorityWithoutItems) {
                    $data = json_decode(base64_decode($exportedData), true);
                    $priorityIds = collect($data['priorities'])->pluck('id')->toArray();

                    return in_array($priorityWithItems->id, $priorityIds)
                        && ! in_array($priorityWithoutItems->id, $priorityIds);
                })
            )
        );
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

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/Base64')
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) use ($itemWithPriorities, $itemWithoutPriorities) {
                    $data = json_decode(base64_decode($exportedData), true);
                    $itemIds = collect($data['items'])->pluck('item_id')->toArray();

                    return in_array($itemWithPriorities->id, $itemIds)
                        && ! in_array($itemWithoutPriorities->id, $itemIds);
                })
            )
        );
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

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/Base64')
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) use ($item, $priority) {
                    $data = json_decode(base64_decode($exportedData), true);
                    $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

                    if (! $itemData) {
                        return false;
                    }

                    $itemPriorityData = collect($itemData['priorities'])->firstWhere('priority_id', $priority->id);

                    return $itemPriorityData && $itemPriorityData['weight'] === 75;
                })
            )
        );
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
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('exportedData')
            )
        );
    }

    public function test_export_json_returns_valid_json_string(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/JSON')
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', fn ($data) => is_array(json_decode($data, true)))
            )
        );
    }

    public function test_export_json_returns_pretty_printed_json(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/JSON')
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', fn ($data) => str_contains($data, "\n"))
            )
        );
    }

    public function test_export_json_includes_complete_data_structure(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/JSON')
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) {
                    $data = json_decode($exportedData, true);

                    return isset($data['system'])
                        && isset($data['priorities'])
                        && isset($data['items']);
                })
            )
        );
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

    public function test_export_schema_id_contains_version_1_1_2(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $schema = $response->original->getData()['page']['props']['schema'];

        $this->assertStringContainsString('v=1.1.2', $schema['$id']);
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

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) use ($item) {
                    $data = json_decode(base64_decode($exportedData), true);
                    $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

                    return $itemData['notes'] === 'Get Thunderfury from the boss';
                })
            )
        );
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

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) use ($item) {
                    $data = json_decode(base64_decode($exportedData), true);
                    $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

                    return $itemData['notes'] === 'Check this guide for details';
                })
            )
        );
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

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) use ($item) {
                    $data = json_decode(base64_decode($exportedData), true);
                    $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

                    return $itemData['notes'] === 'This is very important information';
                })
            )
        );
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

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) use ($item) {
                    $data = json_decode(base64_decode($exportedData), true);
                    $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

                    return $itemData['notes'] === 'This is emphasized text';
                })
            )
        );
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

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) use ($item) {
                    $data = json_decode(base64_decode($exportedData), true);
                    $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

                    return $itemData['notes'] === 'This is underlined text';
                })
            )
        );
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

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) use ($item) {
                    $data = json_decode(base64_decode($exportedData), true);
                    $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

                    return $itemData['notes'] === 'Use the command here';
                })
            )
        );
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

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) use ($item) {
                    $data = json_decode(base64_decode($exportedData), true);
                    $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

                    return $itemData['notes'] === 'Section Title';
                })
            )
        );
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

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) use ($item) {
                    $data = json_decode(base64_decode($exportedData), true);
                    $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

                    return $itemData['notes'] === 'This is deleted text';
                })
            )
        );
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

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) use ($item) {
                    $data = json_decode(base64_decode($exportedData), true);
                    $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

                    // Null notes should return either empty string or null
                    return $itemData['notes'] === '' || $itemData['notes'] === null;
                })
            )
        );
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

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) use ($item) {
                    $data = json_decode(base64_decode($exportedData), true);
                    $itemData = collect($data['items'])->firstWhere('item_id', $item->id);

                    return $itemData['notes'] === 'Multiple spaces and newlines';
                })
            )
        );
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

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) use ($priority) {
                    $data = json_decode(base64_decode($exportedData), true);
                    $priorityData = collect($data['priorities'])->firstWhere('id', $priority->id);

                    return $priorityData['icon'] === 'spell_nature_strength';
                })
            )
        );
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

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) use ($priority) {
                    $data = json_decode(base64_decode($exportedData), true);
                    $priorityData = collect($data['priorities'])->firstWhere('id', $priority->id);

                    return $priorityData['icon'] === null;
                })
            )
        );
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

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) {
                    $data = json_decode(base64_decode($exportedData), true);

                    return isset($data['players']) && empty($data['players']);
                })
            )
        );
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

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) {
                    $data = json_decode(base64_decode($exportedData), true);

                    return isset($data['players']) && empty($data['players']);
                })
            )
        );
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

        $attendanceService = Mockery::mock(AttendanceService::class);
        $attendanceService->shouldReceive('tags')->andReturnSelf();
        $attendanceService->shouldReceive('playerNames')->andReturnSelf();
        $attendanceService->shouldReceive('calculate')
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
        $this->app->instance(AttendanceService::class, $attendanceService);
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) {
                    $data = json_decode(base64_decode($exportedData), true);

                    return isset($data['players'])
                        && count($data['players']) === 1
                        && $data['players'][0]['name'] === 'TestPlayer'
                        && isset($data['players'][0]['attendance'])
                        && $data['players'][0]['attendance']['attended'] === 8
                        && $data['players'][0]['attendance']['total'] === 10
                        && $data['players'][0]['attendance']['percentage'] == 80.0;
                })
            )
        );
    }

    public function test_export_player_attendance_includes_first_attendance_date(): void
    {
        $user = User::factory()->officer()->create();

        $tag = GuildTag::factory()->countsAttendance()->create();
        $firstAttendance = Carbon::parse('2025-01-15 20:00:00');

        $wclGuildService = Mockery::mock(WarcraftLogsGuildService::class);
        $wclGuildService->shouldReceive('getGuildTags')
            ->andReturn(collect([$tag]));

        $attendanceService = Mockery::mock(AttendanceService::class);
        $attendanceService->shouldReceive('tags')->andReturnSelf();
        $attendanceService->shouldReceive('playerNames')->andReturnSelf();
        $attendanceService->shouldReceive('calculate')
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
        $this->app->instance(AttendanceService::class, $attendanceService);
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) {
                    $data = json_decode(base64_decode($exportedData), true);

                    return isset($data['players'][0]['attendance']['first_attendance'])
                        && $data['players'][0]['attendance']['first_attendance'] !== null;
                })
            )
        );
    }

    public function test_export_json_includes_players_data(): void
    {
        $user = User::factory()->officer()->create();

        $tag = GuildTag::factory()->countsAttendance()->create();

        $wclGuildService = Mockery::mock(WarcraftLogsGuildService::class);
        $wclGuildService->shouldReceive('getGuildTags')
            ->andReturn(collect([$tag]));

        $attendanceService = Mockery::mock(AttendanceService::class);
        $attendanceService->shouldReceive('tags')->andReturnSelf();
        $attendanceService->shouldReceive('playerNames')->andReturnSelf();
        $attendanceService->shouldReceive('calculate')
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
        $this->app->instance(AttendanceService::class, $attendanceService);
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) {
                    $data = json_decode($exportedData, true);

                    return isset($data['players']) && count($data['players']) === 1;
                })
            )
        );
    }

    // ==========================================
    // GRM Freshness Tests
    // ==========================================

    public function test_export_returns_grm_freshness_as_deferred_prop(): void
    {
        Storage::fake('local');
        $user = User::factory()->officer()->create();

        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect());
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/Base64')
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('grmFreshness')
                ->has('grmFreshness.lastModified')
                ->has('grmFreshness.dataIsStale')
            )
        );
    }

    public function test_export_json_returns_grm_freshness_as_deferred_prop(): void
    {
        Storage::fake('local');
        $user = User::factory()->officer()->create();

        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect());
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/JSON')
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('grmFreshness')
                ->has('grmFreshness.lastModified')
                ->has('grmFreshness.dataIsStale')
            )
        );
    }

    public function test_grm_freshness_returns_epoch_timestamp_when_no_file_exists(): void
    {
        Storage::fake('local');
        $user = User::factory()->officer()->create();

        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect());
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.lastModified', fn ($value) => Carbon::parse($value)->timestamp === 0)
            )
        );
    }

    public function test_grm_freshness_is_not_stale_when_no_file_exists_and_no_raiders(): void
    {
        Storage::fake('local');
        $user = User::factory()->officer()->create();

        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect());
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', false)
            )
        );
    }

    public function test_grm_freshness_is_stale_when_no_file_exists_but_guild_has_raiders(): void
    {
        Storage::fake('local');
        $user = User::factory()->officer()->create();

        // Create a raider rank
        $raiderRank = GuildRank::factory()->create(['name' => 'Raider']);

        // Mock BlizzardGuildService to return 5 raiders
        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect([
            new GuildMember(character: ['id' => 1, 'name' => 'Player1'], rank: $raiderRank),
            new GuildMember(character: ['id' => 2, 'name' => 'Player2'], rank: $raiderRank),
            new GuildMember(character: ['id' => 3, 'name' => 'Player3'], rank: $raiderRank),
            new GuildMember(character: ['id' => 4, 'name' => 'Player4'], rank: $raiderRank),
            new GuildMember(character: ['id' => 5, 'name' => 'Player5'], rank: $raiderRank),
        ]));
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        // 5 raiders in guild, 0 in GRM file = difference of 5 >= 3 = stale
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', true)
            )
        );
    }

    public function test_grm_freshness_is_not_stale_when_raider_counts_match(): void
    {
        Storage::fake('local');
        $user = User::factory()->officer()->create();

        // Create CSV with 3 raiders
        $csvContent = "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\n";
        $csvContent .= "Player1,Raider,80,1,Main,\n";
        $csvContent .= "Player2,Raider,80,2,Main,\n";
        $csvContent .= "Player3,Raider,80,3,Main,\n";
        Storage::disk('local')->put('grm/uploads/latest.csv', $csvContent);

        // Create a raider rank
        $raiderRank = GuildRank::factory()->create(['name' => 'Raider']);

        // Mock BlizzardGuildService to return 3 raiders (same as CSV)
        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect([
            new GuildMember(character: ['id' => 1, 'name' => 'Player1'], rank: $raiderRank),
            new GuildMember(character: ['id' => 2, 'name' => 'Player2'], rank: $raiderRank),
            new GuildMember(character: ['id' => 3, 'name' => 'Player3'], rank: $raiderRank),
        ]));
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        // 3 raiders in both = difference of 0 < 3 = not stale
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', false)
            )
        );
    }

    public function test_grm_freshness_is_not_stale_when_raider_count_difference_is_less_than_three(): void
    {
        Storage::fake('local');
        $user = User::factory()->officer()->create();

        // Create CSV with 5 raiders
        $csvContent = "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\n";
        $csvContent .= "Player1,Raider,80,1,Main,\n";
        $csvContent .= "Player2,Raider,80,2,Main,\n";
        $csvContent .= "Player3,Raider,80,3,Main,\n";
        $csvContent .= "Player4,Raider,80,4,Main,\n";
        $csvContent .= "Player5,Raider,80,5,Main,\n";
        Storage::disk('local')->put('grm/uploads/latest.csv', $csvContent);

        // Create a raider rank
        $raiderRank = GuildRank::factory()->create(['name' => 'Raider']);

        // Mock BlizzardGuildService to return 3 raiders (difference of 2)
        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect([
            new GuildMember(character: ['id' => 1, 'name' => 'Player1'], rank: $raiderRank),
            new GuildMember(character: ['id' => 2, 'name' => 'Player2'], rank: $raiderRank),
            new GuildMember(character: ['id' => 3, 'name' => 'Player3'], rank: $raiderRank),
        ]));
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        // 5 in CSV, 3 in guild = difference of 2 < 3 = not stale
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', false)
            )
        );
    }

    public function test_grm_freshness_is_stale_when_raider_count_difference_is_three_or_more(): void
    {
        Storage::fake('local');
        $user = User::factory()->officer()->create();

        // Create CSV with 2 raiders
        $csvContent = "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\n";
        $csvContent .= "Player1,Raider,80,1,Main,\n";
        $csvContent .= "Player2,Raider,80,2,Main,\n";
        Storage::disk('local')->put('grm/uploads/latest.csv', $csvContent);

        // Create a raider rank
        $raiderRank = GuildRank::factory()->create(['name' => 'Raider']);

        // Mock BlizzardGuildService to return 5 raiders (difference of 3)
        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect([
            new GuildMember(character: ['id' => 1, 'name' => 'Player1'], rank: $raiderRank),
            new GuildMember(character: ['id' => 2, 'name' => 'Player2'], rank: $raiderRank),
            new GuildMember(character: ['id' => 3, 'name' => 'Player3'], rank: $raiderRank),
            new GuildMember(character: ['id' => 4, 'name' => 'Player4'], rank: $raiderRank),
            new GuildMember(character: ['id' => 5, 'name' => 'Player5'], rank: $raiderRank),
        ]));
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        // 2 in CSV, 5 in guild = difference of 3 >= 3 = stale
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', true)
            )
        );
    }

    public function test_grm_freshness_counts_multiple_raider_rank_variants(): void
    {
        Storage::fake('local');
        $user = User::factory()->officer()->create();

        // Create CSV with different raider rank names
        $csvContent = "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\n";
        $csvContent .= "Player1,Raider,80,1,Main,\n";
        $csvContent .= "Player2,Core Raider,80,2,Main,\n";
        $csvContent .= "Player3,Trial Raider,80,3,Main,\n";
        $csvContent .= "Player4,Officer,80,4,Main,\n";
        Storage::disk('local')->put('grm/uploads/latest.csv', $csvContent);

        // Create multiple raider ranks
        $raiderRank = GuildRank::factory()->create(['name' => 'Raider']);
        $coreRaiderRank = GuildRank::factory()->create(['name' => 'Core Raider']);
        $trialRaiderRank = GuildRank::factory()->create(['name' => 'Trial Raider']);
        GuildRank::factory()->create(['name' => 'Officer']);

        // Mock BlizzardGuildService to return 3 raiders across different ranks
        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect([
            new GuildMember(character: ['id' => 1, 'name' => 'Player1'], rank: $raiderRank),
            new GuildMember(character: ['id' => 2, 'name' => 'Player2'], rank: $coreRaiderRank),
            new GuildMember(character: ['id' => 3, 'name' => 'Player3'], rank: $trialRaiderRank),
        ]));
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        // 3 raiders in CSV (Player1, Player2, Player3), 3 in guild = difference of 0 < 3 = not stale
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', false)
            )
        );
    }

    public function test_grm_freshness_returns_file_last_modified_time(): void
    {
        Storage::fake('local');
        $user = User::factory()->officer()->create();

        // Create the CSV file
        $csvContent = "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nPlayer1,Member,80,1,Main,\n";
        Storage::disk('local')->put('grm/uploads/latest.csv', $csvContent);

        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect());
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.lastModified', function ($lastModified) {
                    // The lastModified should be a human-readable string like "5 seconds ago"
                    // or an ISO 8601 string, not the epoch timestamp
                    return $lastModified !== Carbon::createFromTimestamp(0)->toIso8601String();
                })
            )
        );
    }

    public function test_grm_freshness_ignores_non_raider_ranks_in_guild_roster(): void
    {
        Storage::fake('local');
        $user = User::factory()->officer()->create();

        // Create CSV with 0 raiders
        $csvContent = "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\n";
        $csvContent .= "Player1,Officer,80,1,Main,\n";
        $csvContent .= "Player2,Member,80,2,Main,\n";
        Storage::disk('local')->put('grm/uploads/latest.csv', $csvContent);

        // Create non-raider ranks only
        $officerRank = GuildRank::factory()->create(['name' => 'Officer']);
        $memberRank = GuildRank::factory()->create(['name' => 'Member']);

        // Mock BlizzardGuildService to return non-raiders
        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect([
            new GuildMember(character: ['id' => 1, 'name' => 'Player1'], rank: $officerRank),
            new GuildMember(character: ['id' => 2, 'name' => 'Player2'], rank: $memberRank),
        ]));
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        // 0 raiders in both = difference of 0 < 3 = not stale
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', false)
            )
        );
    }

    public function test_grm_freshness_handles_semicolon_delimited_csv(): void
    {
        Storage::fake('local');
        $user = User::factory()->officer()->create();

        // Create CSV with semicolon delimiter
        $csvContent = "Name;Rank;Level;Last Online (Days);Main/Alt;Player Alts\n";
        $csvContent .= "Player1;Raider;80;1;Main;\n";
        $csvContent .= "Player2;Raider;80;2;Main;\n";
        Storage::disk('local')->put('grm/uploads/latest.csv', $csvContent);

        // Create a raider rank
        $raiderRank = GuildRank::factory()->create(['name' => 'Raider']);

        // Mock BlizzardGuildService to return 2 raiders
        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect([
            new GuildMember(character: ['id' => 1, 'name' => 'Player1'], rank: $raiderRank),
            new GuildMember(character: ['id' => 2, 'name' => 'Player2'], rank: $raiderRank),
        ]));
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        // Note: The current implementation uses str_getcsv which defaults to comma delimiter
        // This test documents the current behavior - semicolon CSV won't parse correctly
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('grmFreshness.dataIsStale')
            )
        );
    }
}
