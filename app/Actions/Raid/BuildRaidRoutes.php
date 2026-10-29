<?php

namespace App\Actions\Raid;

use App\Actions\Datasets\BuildDatasetRoutes;
use App\Enums\RaidSetupStep;

/**
 * Resolves the URLs the raid management pages link to.
 */
class BuildRaidRoutes extends BuildDatasetRoutes
{
    protected function routePrefix(): string
    {
        return 'management.raids';
    }

    protected function setupSteps(): ?string
    {
        return RaidSetupStep::class;
    }
}
