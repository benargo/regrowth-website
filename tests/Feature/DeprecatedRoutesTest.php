<?php

namespace Tests\Feature;

use App\Jobs\Discord\AttachAvatarToUser;
use App\Jobs\ProcessGrmUpload;
use App\Models\Boss;
use App\Models\Character;
use App\Models\Phase;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\DashboardTestCase;

#[Group('platform')]
class DeprecatedRoutesTest extends DashboardTestCase
{
    // ==================== icons ====================

    #[Test]
    #[Group('deprecated')]
    public function the_icon_proxy_route_is_gone(): void
    {
        $this->assertFalse(Route::has('icons.show'));
        $this->get('/icons/56/inv_bracer_02.jpg')->assertGone();
    }

    // ==================== councillors ====================

    #[Test]
    #[Group('deprecated')]
    public function add_councillor_endpoint_is_gone(): void
    {
        $response = $this->actingAs($this->officer)
            ->post('/manage/addon/settings/councillors', [
                'character_name' => 'Anyone',
            ]);

        $response->assertGone();
    }

    #[Test]
    #[Group('deprecated')]
    public function remove_councillor_endpoint_is_gone(): void
    {
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->actingAs($this->officer)
            ->delete("/manage/addon/settings/councillors/{$character->id}");

        $response->assertGone();
    }

    // ==================== grm upload ====================

    #[Test]
    #[Group('deprecated')]
    public function grm_upload_form_redirects_to_the_grm_import_form(): void
    {
        $response = $this->actingAs($this->officer)->get('/manage/grm-upload');

        $response->assertMovedPermanently();
        $response->assertRedirect(route('management.grm.create'));
    }

    #[Test]
    #[Group('deprecated')]
    public function grm_upload_post_endpoint_is_gone(): void
    {
        Queue::fake([ProcessGrmUpload::class, AttachAvatarToUser::class]);
        Storage::fake('local');

        $response = $this->actingAs($this->officer)->post('/manage/grm-upload', [
            'grm_data' => 'ignored',
        ]);

        $response->assertGone();
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // ==================== guild tags ====================

    #[Test]
    #[Group('deprecated')]
    public function phase_guild_tags_endpoint_is_gone(): void
    {
        $phase = Phase::factory()->create();
        $guildTag = GuildTag::factory()->create();

        $response = $this->actingAs($this->officer)->put("/manage/phases/{$phase->id}/guild-tags", [
            'guild_tag_ids' => [$guildTag->id],
        ]);

        $response->assertGone();
    }

    #[Test]
    #[Group('deprecated')]
    public function guild_tag_count_attendance_endpoint_is_gone(): void
    {
        $guildTag = GuildTag::factory()->doesNotCountAttendance()->create();

        $response = $this->actingAs($this->officer)->patch("/datasets/guild-tags/{$guildTag->id}/count-attendance", [
            'count_attendance' => true,
        ]);

        $response->assertGone();
        $this->assertFalse($guildTag->fresh()->count_attendance);
    }

    // ==================== daily quests ====================

    #[Test]
    #[Group('deprecated')]
    public function public_daily_quests_page_is_gone(): void
    {
        $this->get('/daily-quests')->assertGone();
    }

    #[Test]
    #[Group('deprecated')]
    public function daily_quests_form_is_gone(): void
    {
        $this->actingAs($this->officer)->get('/manage/daily-quests')->assertGone();
    }

    #[Test]
    #[Group('deprecated')]
    public function daily_quests_form_is_gone_for_guests(): void
    {
        $this->get('/manage/daily-quests')->assertGone();
    }

    #[Test]
    #[Group('deprecated')]
    public function daily_quests_store_endpoint_is_gone(): void
    {
        Saloon::fake([]);

        $this->actingAs($this->officer)->post('/manage/daily-quests', ['cooking_quest_id' => 1])->assertGone();

        Saloon::assertNothingSent();
        $this->assertDatabaseCount('discord_notifications', 0);
    }

    #[Test]
    #[Group('deprecated')]
    public function daily_quests_audit_page_is_gone(): void
    {
        $this->actingAs($this->officer)->get('/manage/daily-quests/audit')->assertGone();
    }

    #[Test]
    #[Group('deprecated')]
    public function daily_quests_audit_page_is_gone_for_guests(): void
    {
        $this->get('/manage/daily-quests/audit')->assertGone();
    }

    // ==================== phases ====================

    #[Test]
    #[Group('deprecated')]
    public function old_phase_start_date_endpoint_is_gone(): void
    {
        $phase = Phase::factory()->create();

        $response = $this->actingAs($this->officer)->put("/manage/phases/{$phase->id}", [
            'start_date' => '2026-06-01T19:00',
        ]);

        $response->assertGone();
    }

    // ==================== boss strategies ====================

    #[Test]
    #[Group('deprecated')]
    public function the_boss_strategies_index_redirects_to_the_raiding_raids_index(): void
    {
        $response = $this->get('/raiding/boss-strategies');

        $response->assertMovedPermanently();
        $response->assertRedirect(route('raiding.raids.index'));
    }

    #[Test]
    #[Group('deprecated')]
    public function a_boss_strategy_redirects_to_the_raiding_boss_page(): void
    {
        $boss = Boss::factory()->create(['name' => 'Moroes']);

        $response = $this->get("/raiding/boss-strategies/{$boss->id}/an-old-slug");

        $response->assertMovedPermanently();
        $response->assertRedirect(route('raiding.bosses.show', ['boss' => $boss, 'slug' => 'moroes']));
    }

    #[Test]
    #[Group('deprecated')]
    public function boss_strategies_management_index_redirects_to_the_bosses_index(): void
    {
        $response = $this->actingAs($this->officer)->get('/manage/boss-strategies');

        $response->assertMovedPermanently();
        $response->assertRedirect(route('management.bosses.index'));
    }

    #[Test]
    #[Group('deprecated')]
    public function boss_strategy_edit_page_redirects_to_the_boss_edit_page(): void
    {
        $boss = Boss::factory()->create();

        $response = $this->actingAs($this->officer)->get("/manage/boss-strategies/{$boss->id}/{$boss->slug}");

        $response->assertMovedPermanently();
        $response->assertRedirect(route('management.bosses.edit', $boss));
    }

    #[Test]
    #[Group('deprecated')]
    public function boss_strategy_update_endpoint_is_gone(): void
    {
        $boss = Boss::factory()->create(['notes' => 'Keep me.']);

        $response = $this->actingAs($this->officer)->patch("/manage/boss-strategies/{$boss->id}", ['notes' => 'Changed.']);

        $response->assertGone();
        $this->assertSame('Keep me.', $boss->fresh()->notes);
    }
}
