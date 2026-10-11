<?php

namespace Tests\Feature;

use App\Jobs\ProcessGrmUpload;
use App\Models\Character;
use App\Models\Phase;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

#[Group('platform')]
class DeprecatedRoutesTest extends DashboardTestCase
{
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
        Queue::fake([ProcessGrmUpload::class]);
        Storage::fake('local');

        $response = $this->actingAs($this->officer)->post('/manage/grm-upload', [
            'grm_data' => 'ignored',
        ]);

        $response->assertGone();
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

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
}
