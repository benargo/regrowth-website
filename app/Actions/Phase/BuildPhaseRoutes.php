<?php

namespace App\Actions\Phase;

use App\Actions\Datasets\BuildDatasetRoutes;

/**
 * Resolves the URLs the phase management pages link to.
 */
class BuildPhaseRoutes extends BuildDatasetRoutes
{
    protected function routePrefix(): string
    {
        return 'management.phases';
    }

    protected function setupSteps(): ?string
    {
        return null;
    }

    /**
     * @return array{create: string, reorder: string}
     */
    public function forIndex(): array
    {
        return [
            ...parent::forIndex(),
            'reorder' => $this->route('reorder'),
        ];
    }
}
