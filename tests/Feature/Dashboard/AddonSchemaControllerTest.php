<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

#[Group('platform')]
class AddonSchemaControllerTest extends DashboardTestCase
{
    // ==========================================
    // Authentication & Authorization Tests
    // ==========================================

    #[Test]
    public function export_schema_requires_authentication(): void
    {
        $response = $this->get(route('management.addon.export.schema'));

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function export_schema_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('management.addon.export.schema'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function export_schema_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('management.addon.export.schema'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function export_schema_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('management.addon.export.schema'));

        $response->assertForbidden();
    }

    #[Test]
    public function export_schema_allows_officer_users(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.addon.export.schema'));

        $response->assertOk();
    }

    // ==========================================
    // Export Schema Endpoint Tests
    // ==========================================

    #[Test]
    public function export_schema_renders_inertia_page_with_schema(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Addon/ExportSchema')
            ->has('schema')
        );
    }

    #[Test]
    public function export_schema_includes_json_schema_metadata(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.addon.export.schema'));

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

    #[Test]
    public function export_schema_defines_system_properties(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.system')
            ->has('schema.properties.system.properties.date_generated')
            ->has('schema.properties.system.properties.user')
        );
    }

    #[Test]
    public function export_schema_defines_priorities_properties(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.priorities')
            ->where('schema.properties.priorities.type', 'array')
        );
    }

    #[Test]
    public function export_schema_defines_items_properties(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.items')
            ->where('schema.properties.items.type', 'array')
        );
    }

    #[Test]
    public function export_schema_defines_players_properties(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.players')
            ->where('schema.properties.players.type', 'array')
        );
    }

    #[Test]
    public function export_schema_defines_player_attendance_properties(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.players.items.properties.name')
            ->has('schema.properties.players.items.properties.attendance')
            ->has('schema.properties.players.items.properties.attendance.properties.first_attendance')
            ->has('schema.properties.players.items.properties.attendance.properties.attended')
            ->has('schema.properties.players.items.properties.attendance.properties.total')
            ->has('schema.properties.players.items.properties.attendance.properties.percentage')
        );
    }

    #[Test]
    public function export_schema_defines_councillors_properties(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.councillors')
            ->where('schema.properties.councillors.type', 'array')
            ->has('schema.properties.councillors.items.properties.id')
            ->has('schema.properties.councillors.items.properties.name')
            ->has('schema.properties.councillors.items.properties.rank')
        );
    }

    #[Test]
    public function export_schema_id_contains_version_1_2_0(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.addon.export.schema'));

        $schema = $response->original->getData()['page']['props']['schema'];

        $this->assertStringContainsString('v=1.2.0', $schema['$id']);
    }
}
