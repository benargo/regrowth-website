<?php

namespace Tests\Browser;

use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\GuildRank;
use App\Models\User;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Report;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\DuskTestCase;

class AttendanceMatrixTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => 'view-attendance-dashboard', 'guard_name' => 'web']);
        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 5, 'is_visible' => true]
        );
        $officerRole->givePermissionTo($permission);
    }

    protected function makeOfficer(): User
    {
        return User::factory()->officer()->create();
    }

    protected function makeAttendanceData(int $presence = 1): Character
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris'),
        ]);
        $report->characters()->attach($character->id, ['presence' => $presence]);

        return $character;
    }

    /**
     * Set a React-controlled date input value by bypassing the synthetic event system.
     * Required because Selenium's sendKeys does not reliably trigger React's onChange on date inputs.
     */
    protected function setReactDateInput(Browser $browser, string $selector, string $value): void
    {
        $escapedSelector = addslashes($selector);
        $escapedValue = addslashes($value);

        $browser->script("
            const input = document.querySelector('{$escapedSelector}');
            if (input) {
                const nativeSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
                nativeSetter.call(input, '{$escapedValue}');
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        ");
    }

    // ======================= Loading & Skeleton =======================

    public function test_skeleton_is_shown_while_matrix_loads(): void
    {
        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->assertPresent('@matrix-skeleton');
        });
    }

    public function test_matrix_table_appears_after_data_loads(): void
    {
        $this->makeAttendanceData();
        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->assertPresent('@matrix-table')
                ->assertMissing('@matrix-skeleton');
        });
    }

    // ======================= Data Display =======================

    public function test_matrix_table_shows_character_names(): void
    {
        $character = $this->makeAttendanceData();
        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user, $character) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->assertSeeIn('@matrix-table', $character->name);
        });
    }

    public function test_present_attendance_shows_check_icon(): void
    {
        $this->makeAttendanceData(presence: 1);
        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->assertPresent('@presence-present');
        });
    }

    public function test_late_attendance_shows_couch_icon(): void
    {
        $this->makeAttendanceData(presence: 2);
        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->assertPresent('@presence-late');
        });
    }

    public function test_absent_attendance_shows_circle_icon(): void
    {
        $rank = GuildRank::factory()->create();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();

        // Thrall attends raid 1 but misses raid 2 → the raid-2 cell shows the absence (circle) icon.
        $report1 = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris'),
        ]);
        $report2 = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-01-08 20:00', 'Europe/Paris'),
        ]);
        $report1->characters()->attach($thrall->id, ['presence' => 1]);

        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->assertPresent('@presence-absent');
        });
    }

    public function test_attendance_percentage_is_shown(): void
    {
        $rank = GuildRank::factory()->create();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();

        // Thrall attends 1 of 2 raids → 50.00%.
        $report1 = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris'),
        ]);
        $report2 = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-01-08 20:00', 'Europe/Paris'),
        ]);
        $report1->characters()->attach($thrall->id, ['presence' => 1]);

        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->assertSeeIn('@matrix-table', '50.00%');
        });
    }

    public function test_cells_before_first_attendance_are_empty(): void
    {
        $rank = GuildRank::factory()->create();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();

        // Thrall attends both raids; Jaina only joins on raid 2.
        $report1 = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris'),
        ]);
        $report2 = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-01-08 20:00', 'Europe/Paris'),
        ]);
        $report1->characters()->attach($thrall->id, ['presence' => 1]);
        $report2->characters()->attach($thrall->id, ['presence' => 1]);
        $report2->characters()->attach($jaina->id, ['presence' => 1]);

        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                // Rows are sorted alphabetically: Jaina (first), Thrall (last).
                // Columns are newest-first: td:nth-child(3) = report2 (Jan 8), td:nth-child(4) = report1 (Jan 1).
                // Jaina's td:nth-child(4) corresponds to report1 (before she first attended), so no presence icon.
                ->assertMissing('table tbody tr:first-child td:nth-child(4) [dusk]');
        });
    }

    // ======================= Empty State =======================

    public function test_empty_state_is_shown_when_no_attendance_data_exists(): void
    {
        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitUntilMissing('@matrix-skeleton', 30)
                ->assertSee('No attendance data available.')
                ->assertMissing('@matrix-table');
        });
    }

    // ======================= Client-side Filters =======================

    public function test_character_name_search_filters_rows(): void
    {
        $rank = GuildRank::factory()->create();
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris'),
        ]);
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $report->characters()->attach($thrall->id, ['presence' => 1]);
        $report->characters()->attach($jaina->id, ['presence' => 1]);

        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->assertSeeIn('@matrix-table', 'Thrall')
                ->assertSeeIn('@matrix-table', 'Jaina')
                ->type('@filter-character-name', 'Thr')
                ->assertSeeIn('@matrix-table', 'Thrall')
                ->assertDontSeeIn('@matrix-table', 'Jaina');
        });
    }

    public function test_character_name_search_clear_button_restores_all_rows(): void
    {
        $rank = GuildRank::factory()->create();
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris'),
        ]);
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $report->characters()->attach($thrall->id, ['presence' => 1]);
        $report->characters()->attach($jaina->id, ['presence' => 1]);

        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->type('@filter-character-name', 'Thr')
                ->assertDontSeeIn('@matrix-table', 'Jaina')
                ->click('@clear-character-name-search')
                ->assertSeeIn('@matrix-table', 'Thrall')
                ->assertSeeIn('@matrix-table', 'Jaina');
        });
    }

    public function test_character_name_search_finds_normalised_names_with_ascii_query(): void
    {
        $rank = GuildRank::factory()->create();
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris'),
        ]);
        $smorgaas = Character::factory()->create(['name' => 'Smörgås', 'rank_id' => $rank->id]);
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report->characters()->attach($smorgaas->id, ['presence' => 1]);
        $report->characters()->attach($thrall->id, ['presence' => 1]);

        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->assertSeeIn('@matrix-table', 'Smörgås')
                ->assertSeeIn('@matrix-table', 'Thrall')
                // Typing the ASCII equivalent 'smor' should still match 'Smörgås'.
                ->type('@filter-character-name', 'smor')
                ->assertSeeIn('@matrix-table', 'Smörgås')
                ->assertDontSeeIn('@matrix-table', 'Thrall');
        });
    }

    public function test_character_name_search_finds_normalised_names_with_diacritic_query(): void
    {
        $rank = GuildRank::factory()->create();
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris'),
        ]);
        $smorgaas = Character::factory()->create(['name' => 'Smörgås', 'rank_id' => $rank->id]);
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report->characters()->attach($smorgaas->id, ['presence' => 1]);
        $report->characters()->attach($thrall->id, ['presence' => 1]);

        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->assertSeeIn('@matrix-table', 'Smörgås')
                ->assertSeeIn('@matrix-table', 'Thrall')
                // Typing a diacritic query 'smör' should also match 'Smörgås'.
                ->type('@filter-character-name', 'smör')
                ->assertSeeIn('@matrix-table', 'Smörgås')
                ->assertDontSeeIn('@matrix-table', 'Thrall');
        });
    }

    public function test_class_filter_none_hides_all_characters_from_table(): void
    {
        $this->makeAttendanceData();
        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->click('@filter-class')
                ->press('None')
                ->waitUntilMissing('@matrix-table')
                ->assertSee('No attendance data available.')
                ->assertMissing('@matrix-table');
        });
    }

    public function test_class_filter_all_restores_all_characters(): void
    {
        $this->makeAttendanceData();
        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->click('@filter-class')
                ->press('None')
                ->waitUntilMissing('@matrix-table')
                ->press('All')
                ->waitFor('@matrix-table', 30)
                ->assertPresent('@matrix-table');
        });
    }

    public function test_rank_filter_none_hides_all_characters_from_table(): void
    {
        $this->makeAttendanceData();
        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->click('@filter-rank')
                ->press('None')
                ->waitUntilMissing('@matrix-table')
                ->assertSee('No attendance data available.')
                ->assertMissing('@matrix-table');
        });
    }

    public function test_rank_filter_all_restores_all_characters(): void
    {
        $this->makeAttendanceData();
        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->click('@filter-rank')
                ->press('None')
                ->waitUntilMissing('@matrix-table')
                ->press('All')
                ->waitFor('@matrix-table', 30)
                ->assertPresent('@matrix-table');
        });
    }

    // ======================= Server-side Filter Interactions =======================

    public function test_guild_tag_filter_triggers_reload_and_filters_data(): void
    {
        $rank = GuildRank::factory()->create();

        // Names are controlled so that 'Alpha' sorts before 'Beta' in the dropdown.
        $tagAlpha = GuildTag::factory()->countsAttendance()->withoutPhase()->create(['name' => 'Alpha']);
        $tagBeta = GuildTag::factory()->countsAttendance()->withoutPhase()->create(['name' => 'Beta']);

        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);

        $reportAlpha = Report::factory()->withGuildTag($tagAlpha)->create([
            'start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris'),
        ]);
        $reportBeta = Report::factory()->withGuildTag($tagBeta)->create([
            'start_time' => Carbon::parse('2025-01-08 20:00', 'Europe/Paris'),
        ]);
        $reportAlpha->characters()->attach($thrall->id, ['presence' => 1]);
        $reportBeta->characters()->attach($jaina->id, ['presence' => 1]);

        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->assertSeeIn('@matrix-table', 'Thrall')
                ->assertSeeIn('@matrix-table', 'Jaina')
                // Open the guild tag dropdown and uncheck the 'Beta' tag (last-child, sorted alphabetically).
                // This leaves only 'Alpha' selected, filtering out Jaina who attended under Beta.
                ->click('@filter-guild-tag')
                ->waitFor('button[dusk="filter-guild-tag"] + div')
                ->click('button[dusk="filter-guild-tag"] + div .py-1 label:last-child')
                // Wait for the skeleton (reload started) and then for the table (reload finished).
                ->waitUntilMissing('@matrix-table', 5)
                ->waitFor('@matrix-table', 30)
                ->assertSeeIn('@matrix-table', 'Thrall')
                ->assertDontSeeIn('@matrix-table', 'Jaina');
        });
    }

    // ======================= Zone Filter =======================

    public function test_zone_dropdown_shows_all_zones_checked_by_default(): void
    {
        $rank = GuildRank::factory()->create();
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report = Report::factory()->withGuildTag($tag)->withZone(1001, 'Black Temple')->create([
            'start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris'),
        ]);
        $report->characters()->attach($thrall->id, ['presence' => 1]);

        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->assertSeeIn('@filter-zone', 'All Zones');
        });
    }

    public function test_zone_filter_unchecking_a_zone_filters_data(): void
    {
        $rank = GuildRank::factory()->create();
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();

        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);

        // Black Temple (BT) sorts before Serpentshrine Cavern (SSC) alphabetically.
        $reportBT = Report::factory()->withGuildTag($tag)->withZone(1001, 'Black Temple')->create([
            'start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris'),
        ]);
        $reportSSC = Report::factory()->withGuildTag($tag)->withZone(1002, 'Serpentshrine Cavern')->create([
            'start_time' => Carbon::parse('2025-01-08 20:00', 'Europe/Paris'),
        ]);
        $reportBT->characters()->attach($thrall->id, ['presence' => 1]);
        $reportSSC->characters()->attach($jaina->id, ['presence' => 1]);

        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->assertSeeIn('@matrix-table', 'Thrall')
                ->assertSeeIn('@matrix-table', 'Jaina')
                // Open the zone dropdown and uncheck 'Serpentshrine Cavern' (last child, alphabetically after BT).
                // This leaves only 'Black Temple' selected, filtering out Jaina who attended SSC.
                ->click('@filter-zone')
                ->waitFor('button[dusk="filter-zone"] + div')
                ->click('button[dusk="filter-zone"] + div .py-1 label:last-child')
                // Wait for the skeleton (reload started) and then for the table (reload finished).
                ->waitUntilMissing('@matrix-table', 5)
                ->waitFor('@matrix-table', 30)
                ->assertSeeIn('@matrix-table', 'Thrall')
                ->assertDontSeeIn('@matrix-table', 'Jaina');
        });
    }

    public function test_zone_filter_none_hides_all_data(): void
    {
        $rank = GuildRank::factory()->create();
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report = Report::factory()->withGuildTag($tag)->withZone(1001, 'Black Temple')->create([
            'start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris'),
        ]);
        $report->characters()->attach($thrall->id, ['presence' => 1]);

        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->click('@filter-zone')
                ->waitFor('button[dusk="filter-zone"] + div')
                ->press('None')
                ->waitUntilMissing('@matrix-table', 5)
                ->assertSee('No attendance data available.')
                ->assertMissing('@matrix-table');
        });
    }

    public function test_zone_filter_all_restores_data(): void
    {
        $rank = GuildRank::factory()->create();
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report = Report::factory()->withGuildTag($tag)->withZone(1001, 'Black Temple')->create([
            'start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris'),
        ]);
        $report->characters()->attach($thrall->id, ['presence' => 1]);

        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->click('@filter-zone')
                ->waitFor('button[dusk="filter-zone"] + div')
                ->press('None')
                ->waitUntilMissing('@matrix-table', 5)
                ->press('All')
                ->waitFor('@matrix-table', 30)
                ->assertPresent('@matrix-table');
        });
    }

    public function test_since_date_filter_modal_applies_and_reloads(): void
    {
        $rank = GuildRank::factory()->create();
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);

        $oldReport = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris'),
        ]);
        $newReport = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-02-01 20:00', 'Europe/Paris'),
        ]);
        $oldReport->characters()->attach($thrall->id, ['presence' => 1]);
        $newReport->characters()->attach($jaina->id, ['presence' => 1]);

        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->assertSeeIn('@matrix-table', 'Thrall')
                ->assertSeeIn('@matrix-table', 'Jaina')
                ->click('@filter-since-date')
                ->waitFor('#modal');

            $this->setReactDateInput($browser, '#modal input[type="date"]', '2025-01-15');

            $browser->press('Apply')
                ->waitUntilMissing('@matrix-table', 5)
                ->waitFor('@matrix-table', 30)
                // since_date = 2025-01-15 excludes Thrall's Jan 1 report; only Jaina's Feb 1 report remains.
                ->assertDontSeeIn('@matrix-table', 'Thrall')
                ->assertSeeIn('@matrix-table', 'Jaina')
                // The "set" badge confirms the filter is active.
                ->assertSeeIn('@filter-since-date', 'set');
        });
    }

    public function test_before_date_filter_modal_applies_and_reloads(): void
    {
        $rank = GuildRank::factory()->create();
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);

        $oldReport = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris'),
        ]);
        $newReport = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-02-01 20:00', 'Europe/Paris'),
        ]);
        $oldReport->characters()->attach($thrall->id, ['presence' => 1]);
        $newReport->characters()->attach($jaina->id, ['presence' => 1]);

        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->assertSeeIn('@matrix-table', 'Thrall')
                ->assertSeeIn('@matrix-table', 'Jaina')
                ->click('@filter-before-date')
                ->waitFor('#modal');

            $this->setReactDateInput($browser, '#modal input[type="date"]', '2025-01-15');

            $browser->press('Apply')
                ->waitUntilMissing('@matrix-table', 5)
                ->waitFor('@matrix-table', 30)
                // before_date = 2025-01-15 excludes Jaina's Feb 1 report; only Thrall's Jan 1 report remains.
                ->assertSeeIn('@matrix-table', 'Thrall')
                ->assertDontSeeIn('@matrix-table', 'Jaina')
                ->assertSeeIn('@filter-before-date', 'set');
        });
    }

    public function test_date_filter_modal_cancel_does_not_trigger_reload(): void
    {
        $this->makeAttendanceData();
        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                ->click('@filter-since-date')
                ->waitFor('#modal');

            $this->setReactDateInput($browser, '#modal input[type="date"]', '2025-06-01');

            $browser->press('Cancel')
                // Modal closes, no reload is triggered.
                ->assertMissing('#modal')
                ->assertMissing('@matrix-skeleton')
                // The "set" badge must not appear since the date was never applied.
                ->assertDontSeeIn('@filter-since-date', 'set');
        });
    }

    public function test_before_date_filter_clear_removes_the_applied_filter(): void
    {
        $rank = GuildRank::factory()->create();
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);

        $oldReport = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris'),
        ]);
        $newReport = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-02-01 20:00', 'Europe/Paris'),
        ]);
        $oldReport->characters()->attach($thrall->id, ['presence' => 1]);
        $newReport->characters()->attach($jaina->id, ['presence' => 1]);

        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                // Apply a before_date filter to exclude Jaina.
                ->click('@filter-before-date')
                ->waitFor('#modal');

            $this->setReactDateInput($browser, '#modal input[type="date"]', '2025-01-15');

            $browser->press('Apply')
                ->waitFor('@matrix-table', 30)
                ->assertDontSeeIn('@matrix-table', 'Jaina')
                ->assertSeeIn('@filter-before-date', 'set')
                // Open the filter again and clear the date.
                ->click('@filter-before-date')
                ->waitFor('#modal')
                ->press('Clear')
                // Clearing triggers a reload; both characters should be visible again.
                ->waitFor('@matrix-table', 30)
                ->assertDontSeeIn('@filter-before-date', 'set')
                ->assertSeeIn('@matrix-table', 'Thrall')
                ->assertSeeIn('@matrix-table', 'Jaina');
        });
    }

    public function test_date_filter_clear_removes_the_applied_filter(): void
    {
        $rank = GuildRank::factory()->create();
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $thrall = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);

        $oldReport = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris'),
        ]);
        $newReport = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-02-01 20:00', 'Europe/Paris'),
        ]);
        $oldReport->characters()->attach($thrall->id, ['presence' => 1]);
        $newReport->characters()->attach($jaina->id, ['presence' => 1]);

        $user = $this->makeOfficer();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('raids.attendance.matrix'))
                ->waitFor('@matrix-table', 30)
                // Apply a since_date filter to exclude Thrall.
                ->click('@filter-since-date')
                ->waitFor('#modal');

            $this->setReactDateInput($browser, '#modal input[type="date"]', '2025-01-15');

            $browser->press('Apply')
                ->waitFor('@matrix-table', 30)
                ->assertDontSeeIn('@matrix-table', 'Thrall')
                ->assertSeeIn('@filter-since-date', 'set')
                // Open the filter again and clear the date.
                ->click('@filter-since-date')
                ->waitFor('#modal')
                ->press('Clear')
                // Clearing triggers a reload; both characters should be visible again.
                ->waitFor('@matrix-table', 30)
                ->assertDontSeeIn('@filter-since-date', 'set')
                ->assertSeeIn('@matrix-table', 'Thrall')
                ->assertSeeIn('@matrix-table', 'Jaina');
        });
    }
}
