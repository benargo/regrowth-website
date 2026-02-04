<?php

namespace Tests\Feature\Dashboard;

use App\Models\TBC\Phase;
use App\Models\User;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PhaseGuildTagsUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_guild_tags_requires_authentication(): void
    {
        $phase = Phase::factory()->create();

        $response = $this->put(route('dashboard.phases.guild-tags.update', $phase), [
            'guild_tag_ids' => [],
        ]);

        $response->assertRedirect('/login');
    }

    public function test_update_guild_tags_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $phase = Phase::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.phases.guild-tags.update', $phase), [
            'guild_tag_ids' => [],
        ]);

        $response->assertForbidden();
    }

    public function test_update_guild_tags_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.phases.guild-tags.update', $phase), [
            'guild_tag_ids' => [],
        ]);

        $response->assertForbidden();
    }

    public function test_update_guild_tags_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $phase = Phase::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.phases.guild-tags.update', $phase), [
            'guild_tag_ids' => [],
        ]);

        $response->assertForbidden();
    }

    public function test_update_guild_tags_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        $phase = Phase::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.phases.guild-tags.update', $phase), [
            'guild_tag_ids' => [],
        ]);

        $response->assertRedirect();
    }

    public function test_update_guild_tags_associates_tags_with_phase(): void
    {
        $user = User::factory()->officer()->create();
        $phase = Phase::factory()->create();
        $tag1 = GuildTag::factory()->create();
        $tag2 = GuildTag::factory()->create();

        $this->actingAs($user)->put(route('dashboard.phases.guild-tags.update', $phase), [
            'guild_tag_ids' => [$tag1->id, $tag2->id],
        ]);

        $tag1->refresh();
        $tag2->refresh();

        $this->assertEquals($phase->id, $tag1->tbc_phase_id);
        $this->assertEquals($phase->id, $tag2->tbc_phase_id);
    }

    public function test_update_guild_tags_removes_previous_associations(): void
    {
        $user = User::factory()->officer()->create();
        $phase = Phase::factory()->create();
        $existingTag = GuildTag::factory()->withPhase($phase)->create();
        $newTag = GuildTag::factory()->create();

        $this->actingAs($user)->put(route('dashboard.phases.guild-tags.update', $phase), [
            'guild_tag_ids' => [$newTag->id],
        ]);

        $existingTag->refresh();
        $newTag->refresh();

        $this->assertNull($existingTag->tbc_phase_id);
        $this->assertEquals($phase->id, $newTag->tbc_phase_id);
    }

    public function test_update_guild_tags_can_clear_all_associations(): void
    {
        $user = User::factory()->officer()->create();
        $phase = Phase::factory()->create();
        $tag1 = GuildTag::factory()->withPhase($phase)->create();
        $tag2 = GuildTag::factory()->withPhase($phase)->create();

        $this->actingAs($user)->put(route('dashboard.phases.guild-tags.update', $phase), [
            'guild_tag_ids' => [],
        ]);

        $tag1->refresh();
        $tag2->refresh();

        $this->assertNull($tag1->tbc_phase_id);
        $this->assertNull($tag2->tbc_phase_id);
    }

    public function test_update_guild_tags_validates_guild_tag_ids_is_required(): void
    {
        $user = User::factory()->officer()->create();
        $phase = Phase::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.phases.guild-tags.update', $phase), []);

        $response->assertSessionHasErrors(['guild_tag_ids']);
    }

    public function test_update_guild_tags_validates_guild_tag_ids_must_be_array(): void
    {
        $user = User::factory()->officer()->create();
        $phase = Phase::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.phases.guild-tags.update', $phase), [
            'guild_tag_ids' => 'not-an-array',
        ]);

        $response->assertSessionHasErrors(['guild_tag_ids']);
    }

    public function test_update_guild_tags_validates_guild_tag_ids_must_exist(): void
    {
        $user = User::factory()->officer()->create();
        $phase = Phase::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.phases.guild-tags.update', $phase), [
            'guild_tag_ids' => [99999],
        ]);

        $response->assertSessionHasErrors(['guild_tag_ids.0']);
    }

    public function test_update_guild_tags_does_not_affect_tags_from_other_phases(): void
    {
        $user = User::factory()->officer()->create();
        $phase1 = Phase::factory()->create();
        $phase2 = Phase::factory()->create();
        $tagForPhase1 = GuildTag::factory()->withPhase($phase1)->create();
        $tagForPhase2 = GuildTag::factory()->withPhase($phase2)->create();

        $this->actingAs($user)->put(route('dashboard.phases.guild-tags.update', $phase1), [
            'guild_tag_ids' => [],
        ]);

        $tagForPhase1->refresh();
        $tagForPhase2->refresh();

        $this->assertNull($tagForPhase1->tbc_phase_id);
        $this->assertEquals($phase2->id, $tagForPhase2->tbc_phase_id);
    }

    public function test_update_guild_tags_clears_phases_cache(): void
    {
        $user = User::factory()->officer()->create();
        $phase = Phase::factory()->create();

        Cache::put('phases.tbc.index', 'cached-data');
        $this->assertTrue(Cache::has('phases.tbc.index'));

        $this->actingAs($user)->put(route('dashboard.phases.guild-tags.update', $phase), [
            'guild_tag_ids' => [],
        ]);

        $this->assertFalse(Cache::has('phases.tbc.index'));
    }
}
