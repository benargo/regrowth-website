<?php

namespace Tests\Feature\Actions\Boss;

use App\Actions\Boss\BuildBossRoutes;
use App\Models\Boss;
use App\Models\Raid;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class BuildBossRoutesTest extends TestCase
{
    #[Test]
    public function it_builds_the_index_urls(): void
    {
        $this->assertSame(
            ['create' => 'http://localhost/manage/bosses/create'],
            BuildBossRoutes::make()->forIndex(),
        );
    }

    #[Test]
    public function it_builds_the_create_urls_cancelling_to_the_index(): void
    {
        $this->assertSame([
            'index' => 'http://localhost/manage/bosses',
            'store' => 'http://localhost/manage/bosses',
            'cancel' => 'http://localhost/manage/bosses',
        ], BuildBossRoutes::make()->forCreate());
    }

    #[Test]
    public function it_cancels_a_create_form_opened_from_the_raid_setup_back_to_the_bosses_step(): void
    {
        $raid = Raid::factory()->make(['id' => 3, 'phase_id' => null]);

        $this->assertSame(
            'http://localhost/manage/raids/3/setup/bosses',
            BuildBossRoutes::make()->forCreate($raid, BuildBossRoutes::FROM_RAID_SETUP)['cancel'],
        );
    }

    #[Test]
    public function it_cancels_a_create_form_opened_from_the_raid_edit_page_back_to_its_bosses_section(): void
    {
        $raid = Raid::factory()->make(['id' => 3, 'phase_id' => null]);

        $this->assertSame(
            'http://localhost/manage/raids/3/edit',
            BuildBossRoutes::make()->forCreate($raid, BuildBossRoutes::FROM_RAID_EDIT)['cancel'],
        );
    }

    #[Test]
    public function it_builds_the_edit_urls_with_the_raid_and_no_review_url(): void
    {
        $this->assertSame([
            'index' => 'http://localhost/manage/bosses',
            'update' => 'http://localhost/manage/bosses/7',
            'raid' => 'http://localhost/manage/raids/3/edit',
        ], BuildBossRoutes::make()->forEdit($this->boss()));
    }

    #[Test]
    public function it_leads_a_stored_boss_to_its_edit_page(): void
    {
        $this->assertSame('http://localhost/manage/bosses/7/edit', BuildBossRoutes::make()->afterStore($this->boss()));
    }

    #[Test]
    public function it_leads_a_boss_stored_from_the_raid_setup_back_to_the_bosses_step(): void
    {
        $this->assertSame(
            'http://localhost/manage/raids/3/setup/bosses',
            BuildBossRoutes::make()->afterStore($this->boss(), BuildBossRoutes::FROM_RAID_SETUP),
        );
    }

    #[Test]
    public function it_leads_a_boss_stored_from_the_raid_edit_page_back_to_its_bosses_section(): void
    {
        $this->assertSame(
            'http://localhost/manage/raids/3/edit',
            BuildBossRoutes::make()->afterStore($this->boss(), BuildBossRoutes::FROM_RAID_EDIT),
        );
    }

    #[Test]
    public function it_has_no_setup_steps(): void
    {
        $this->assertSame([], BuildBossRoutes::make()->steps());
    }

    // ==================== helpers ====================

    private function boss(): Boss
    {
        return Boss::factory()->make(['id' => 7, 'raid_id' => 3]);
    }
}
