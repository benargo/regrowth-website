<?php

namespace Tests\Feature\Actions\Phase;

use App\Actions\Phase\BuildPhaseRoutes;
use App\Models\Phase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class BuildPhaseRoutesTest extends TestCase
{
    #[Test]
    public function it_builds_the_index_urls_with_the_reorder_url(): void
    {
        $this->assertSame([
            'create' => 'http://localhost/manage/phases/create',
            'reorder' => 'http://localhost/manage/phases/order',
        ], (new BuildPhaseRoutes)->forIndex());
    }

    #[Test]
    public function it_builds_the_edit_urls_without_a_review_url(): void
    {
        $this->assertSame([
            'index' => 'http://localhost/manage/phases',
            'update' => 'http://localhost/manage/phases/7',
        ], (new BuildPhaseRoutes)->forEdit(Phase::factory()->make(['id' => 7])));
    }

    #[Test]
    public function it_leads_a_stored_phase_to_its_edit_page(): void
    {
        $this->assertSame(
            'http://localhost/manage/phases/7/edit',
            (new BuildPhaseRoutes)->afterStore(Phase::factory()->make(['id' => 7])),
        );
    }

    #[Test]
    public function it_has_no_setup_steps(): void
    {
        $this->assertSame([], (new BuildPhaseRoutes)->steps());
    }
}
