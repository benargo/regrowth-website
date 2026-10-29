<?php

namespace Tests\Feature\WarcraftLogs;

use App\Models\User;
use App\Models\WarcraftLogs\Guild;
use App\Models\WarcraftLogs\GuildTag;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

#[Group('raiding')]
#[Group('warcraftlogs-integration')]
class GuildTagControllerTest extends DashboardTestCase
{
    #[Test]
    public function it_redirects_guests_to_login(): void
    {
        [$guild, $guildTag] = $this->guildWithTag();

        $response = $this->patch($this->toggleUrl($guild, $guildTag), ['count_attendance' => true]);

        $response->assertRedirect(route('login'));
    }

    #[Test]
    #[Group('happy-path')]
    public function it_turns_count_attendance_on_and_returns_to_the_guild(): void
    {
        [$guild, $guildTag] = $this->guildWithTag(countsAttendance: false);

        $response = $this->actingAs($this->userWith('update-warcraft-logs-tags'))
            ->from(route('management.warcraftlogs.guilds.show', $guild))
            ->patch($this->toggleUrl($guild, $guildTag), ['count_attendance' => true]);

        $response->assertRedirect(route('management.warcraftlogs.guilds.show', $guild));
        $this->assertTrue($guildTag->fresh()->count_attendance);
    }

    #[Test]
    public function it_turns_count_attendance_off(): void
    {
        [$guild, $guildTag] = $this->guildWithTag(countsAttendance: true);

        $this->actingAs($this->userWith('update-warcraft-logs-tags'))
            ->patch($this->toggleUrl($guild, $guildTag), ['count_attendance' => false]);

        $this->assertFalse($guildTag->fresh()->count_attendance);
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_a_user_without_update_tags(): void
    {
        [$guild, $guildTag] = $this->guildWithTag(countsAttendance: false);

        $response = $this->actingAs($this->userWith('view-warcraft-logs-guilds', 'update-warcraft-logs-guilds'))
            ->patch($this->toggleUrl($guild, $guildTag), ['count_attendance' => true]);

        $response->assertForbidden();
        $this->assertFalse($guildTag->fresh()->count_attendance);
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_a_user_with_only_edit_datasets(): void
    {
        [$guild, $guildTag] = $this->guildWithTag(countsAttendance: false);

        $response = $this->actingAs($this->officer)
            ->patch($this->toggleUrl($guild, $guildTag), ['count_attendance' => true]);

        $response->assertForbidden();
        $this->assertFalse($guildTag->fresh()->count_attendance);
    }

    #[Test]
    #[Group('edge-case')]
    public function it_returns_404_for_a_tag_in_another_guild(): void
    {
        $guild = Guild::factory()->create();
        $otherGuildsTag = GuildTag::factory()->forGuild()->doesNotCountAttendance()->create();

        $response = $this->actingAs($this->userWith('update-warcraft-logs-tags'))
            ->patch($this->toggleUrl($guild, $otherGuildsTag), ['count_attendance' => true]);

        $response->assertNotFound();
        $this->assertFalse($otherGuildsTag->fresh()->count_attendance);
    }

    #[Test]
    #[Group('validation')]
    public function it_requires_count_attendance(): void
    {
        [$guild, $guildTag] = $this->guildWithTag();

        $response = $this->actingAs($this->userWith('update-warcraft-logs-tags'))->patch($this->toggleUrl($guild, $guildTag), []);

        $response->assertInvalid(['count_attendance' => 'The count attendance field is required.']);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_count_attendance_that_is_not_a_boolean(): void
    {
        [$guild, $guildTag] = $this->guildWithTag(countsAttendance: false);

        $response = $this->actingAs($this->userWith('update-warcraft-logs-tags'))
            ->patch($this->toggleUrl($guild, $guildTag), ['count_attendance' => 'sometimes']);

        $response->assertInvalid(['count_attendance' => 'The count attendance field must be true or false.']);
        $this->assertFalse($guildTag->fresh()->count_attendance);
    }

    // ==================== helpers ====================

    /**
     * @return array{0: Guild, 1: GuildTag}
     */
    private function guildWithTag(bool $countsAttendance = false): array
    {
        $guild = Guild::factory()->create();
        $guildTag = GuildTag::factory()->forGuild($guild)->create(['count_attendance' => $countsAttendance]);

        return [$guild, $guildTag];
    }

    private function toggleUrl(Guild $guild, GuildTag $guildTag): string
    {
        return route('management.warcraftlogs.guilds.tags.toggle-attendance', [$guild, $guildTag]);
    }

    private function userWith(string ...$permissions): User
    {
        return User::factory()->withPermissions(...$permissions)->create();
    }
}
