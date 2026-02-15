<?php

namespace Tests\Feature\Dashboard;

use App\Events\AddonSettingsProcessed;
use App\Models\TBC\Phase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PhaseUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake(AddonSettingsProcessed::class);
    }

    public function test_update_phase_requires_authentication(): void
    {
        $phase = Phase::factory()->create();

        $response = $this->put(route('dashboard.phases.update', $phase), [
            'start_date' => '2025-06-15T14:00',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_update_phase_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $phase = Phase::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.phases.update', $phase), [
            'start_date' => '2025-06-15T14:00',
        ]);

        $response->assertForbidden();
    }

    public function test_update_phase_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.phases.update', $phase), [
            'start_date' => '2025-06-15T14:00',
        ]);

        $response->assertForbidden();
    }

    public function test_update_phase_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $phase = Phase::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.phases.update', $phase), [
            'start_date' => '2025-06-15T14:00',
        ]);

        $response->assertForbidden();
    }

    public function test_update_phase_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        $phase = Phase::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.phases.update', $phase), [
            'start_date' => '2025-06-15T14:00',
        ]);

        $response->assertRedirect();
    }

    public function test_update_phase_saves_start_date_to_database(): void
    {
        $user = User::factory()->officer()->create();
        $phase = Phase::factory()->create();

        $this->actingAs($user)->put(route('dashboard.phases.update', $phase), [
            'start_date' => '2025-06-15T14:00',
        ]);

        $phase->refresh();
        // Paris is UTC+2 in summer, so 14:00 Paris = 12:00 UTC
        $this->assertEquals('2025-06-15 12:00:00', $phase->start_date->utc()->format('Y-m-d H:i:s'));
    }

    public function test_update_phase_allows_null_to_clear_start_date(): void
    {
        $user = User::factory()->officer()->create();
        $phase = Phase::factory()->started()->create();

        $response = $this->actingAs($user)->put(route('dashboard.phases.update', $phase), [
            'start_date' => null,
        ]);

        $response->assertRedirect();
        $phase->refresh();
        $this->assertNull($phase->start_date);
    }

    public function test_update_phase_allows_empty_string_to_clear_start_date(): void
    {
        $user = User::factory()->officer()->create();
        $phase = Phase::factory()->started()->create();

        $response = $this->actingAs($user)->put(route('dashboard.phases.update', $phase), [
            'start_date' => '',
        ]);

        $response->assertRedirect();
        $phase->refresh();
        $this->assertNull($phase->start_date);
    }

    public function test_update_phase_validates_date_format(): void
    {
        $user = User::factory()->officer()->create();
        $phase = Phase::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.phases.update', $phase), [
            'start_date' => 'not-a-date',
        ]);

        $response->assertSessionHasErrors(['start_date']);
    }

    public function test_update_phase_converts_paris_winter_timezone_to_utc(): void
    {
        $user = User::factory()->officer()->create();
        $phase = Phase::factory()->create();

        // Winter time in Paris (UTC+1): 15:00 Paris = 14:00 UTC
        $this->actingAs($user)->put(route('dashboard.phases.update', $phase), [
            'start_date' => '2025-01-15T15:00',
        ]);

        $phase->refresh();
        $this->assertEquals('2025-01-15 14:00:00', $phase->start_date->utc()->format('Y-m-d H:i:s'));
    }

    public function test_update_phase_clears_phases_cache(): void
    {
        $user = User::factory()->officer()->create();
        $phase = Phase::factory()->create();

        // Pre-populate with valid cached data so the middleware doesn't fail
        Cache::put('phases.tbc.index', Phase::all());
        $this->assertTrue(Cache::has('phases.tbc.index'));

        $this->actingAs($user)->put(route('dashboard.phases.update', $phase), [
            'start_date' => '2025-06-15T14:00',
        ]);

        $this->assertFalse(Cache::has('phases.tbc.index'));
    }
}
