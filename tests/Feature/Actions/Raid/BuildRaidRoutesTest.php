<?php

namespace Tests\Feature\Actions\Raid;

use App\Actions\Raid\BuildRaidRoutes;
use App\Models\Raid;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class BuildRaidRoutesTest extends TestCase
{
    #[Test]
    public function it_builds_the_index_urls(): void
    {
        $this->assertSame(
            ['create' => 'http://localhost/manage/raids/create'],
            (new BuildRaidRoutes)->forIndex(),
        );
    }

    #[Test]
    public function it_builds_the_edit_urls_with_a_review_url(): void
    {
        $this->assertSame([
            'index' => 'http://localhost/manage/raids',
            'update' => 'http://localhost/manage/raids/7',
            'review' => 'http://localhost/manage/raids/7/review',
        ], (new BuildRaidRoutes)->forEdit($this->raid()));
    }

    #[Test]
    public function it_leads_a_stored_raid_to_its_bosses_step(): void
    {
        $this->assertSame(
            'http://localhost/manage/raids/7/setup/bosses',
            (new BuildRaidRoutes)->afterStore($this->raid()),
        );
    }

    #[Test]
    public function it_lists_the_bosses_step_with_its_setup_url(): void
    {
        $this->assertSame([
            ['value' => 'bosses', 'label' => 'Bosses', 'component' => 'BossesSection', 'href' => 'http://localhost/manage/raids/7/setup/bosses'],
        ], (new BuildRaidRoutes)->steps($this->raid()));
    }

    #[Test]
    public function it_builds_the_card_links(): void
    {
        $this->assertSame([
            'edit' => 'http://localhost/manage/raids/7/edit',
            'destroy' => 'http://localhost/manage/raids/7',
        ], (new BuildRaidRoutes)->links($this->raid()));
    }

    // ==================== helpers ====================

    private function raid(): Raid
    {
        return Raid::factory()->make(['id' => 7, 'phase_id' => 1]);
    }
}
