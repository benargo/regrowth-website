<?php

namespace Tests\Feature\Actions\Datasets;

use App\Actions\Datasets\BuildDatasetRoutes;
use App\Models\GameVersion;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class BuildDatasetRoutesTest extends TestCase
{
    #[Test]
    public function it_has_no_steps_for_a_dataset_without_a_setup_wizard(): void
    {
        $routes = $this->routesWithoutWizard();

        $this->assertSame([], $routes->steps());
        $this->assertSame([], $routes->steps(GameVersion::factory()->make(['id' => 7])));
    }

    #[Test]
    public function it_builds_urls_from_the_subclass_route_prefix(): void
    {
        $routes = $this->routesWithoutWizard();

        $this->assertSame(['create' => 'http://localhost/manage/game-versions/create'], $routes->forIndex());
        $this->assertSame([
            'edit' => 'http://localhost/manage/game-versions/7/edit',
            'destroy' => 'http://localhost/manage/game-versions/7',
        ], $routes->links(GameVersion::factory()->make(['id' => 7])));
    }

    // ==================== helpers ====================

    /**
     * A dataset's routes with no setup wizard, borrowing the game version
     * routes because they are the only dataset routes registered.
     */
    private function routesWithoutWizard(): BuildDatasetRoutes
    {
        return new class extends BuildDatasetRoutes
        {
            protected function routePrefix(): string
            {
                return 'management.game-versions';
            }

            protected function setupSteps(): ?string
            {
                return null;
            }
        };
    }
}
