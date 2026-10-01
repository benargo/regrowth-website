<?php

namespace App\Actions\GameVersion;

use App\Actions\Datasets\BuildDatasetRoutes;
use App\Enums\GameVersionSetupStep;

/**
 * Resolves the URLs the game version management pages link to.
 */
class BuildGameVersionRoutes extends BuildDatasetRoutes
{
    protected function routePrefix(): string
    {
        return 'management.game-versions';
    }

    protected function setupSteps(): ?string
    {
        return GameVersionSetupStep::class;
    }
}
