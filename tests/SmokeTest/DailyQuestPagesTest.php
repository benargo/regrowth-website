<?php

namespace Tests\SmokeTest;

use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DailyQuestPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 6, 'is_visible' => true]
        );
        $officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'view-officer-dashboard', 'guard_name' => 'web']));
        $officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'set-daily-quests', 'guard_name' => 'web']));
        $officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'audit-daily-quests', 'guard_name' => 'web']));

        Storage::fake('public');

        Saloon::fake([
            FetchAssetRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);
    }

    #[Test]
    public function daily_quests_form_page_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('management.daily-quests.form'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function daily_quests_audit_page_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('management.daily-quests.audit'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function daily_quests_audit_page_requires_officer(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('management.daily-quests.audit'));

        $response->assertForbidden();
    }
}
