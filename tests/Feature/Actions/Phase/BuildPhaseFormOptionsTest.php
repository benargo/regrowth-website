<?php

namespace Tests\Feature\Actions\Phase;

use App\Actions\Phase\BuildPhaseFormOptions;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class BuildPhaseFormOptionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_the_game_versions_newest_release_first(): void
    {
        $older = GameVersion::factory()->create(['title' => 'Era', 'release_date' => '2019-08-27']);
        $newer = GameVersion::factory()->create(['title' => 'Anniversary', 'release_date' => '2026-02-06']);

        $this->assertSame([
            'game_versions' => [
                ['value' => $newer->id, 'label' => 'Anniversary'],
                ['value' => $older->id, 'label' => 'Era'],
            ],
        ], BuildPhaseFormOptions::run());
    }
}
