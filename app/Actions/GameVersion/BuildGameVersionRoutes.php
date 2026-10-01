<?php

namespace App\Actions\GameVersion;

use App\Enums\GameVersionSetupStep;
use App\Models\GameVersion;
use Illuminate\Support\Facades\URL;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Resolves the URLs the game version management pages link to, so the
 * shared dataset components receive plain URLs rather than route names.
 * Each page gets only the URLs it uses.
 */
class BuildGameVersionRoutes
{
    use AsAction;

    /**
     * Every setup step in wizard order. Once the game version exists, each
     * step links to its setup page; before then (the Create page) the
     * progress trail shows them as plain text.
     *
     * @return list<array{value: string, label: string, component: string, href?: string}>
     */
    public function steps(?GameVersion $gameVersion = null): array
    {
        return collect(GameVersionSetupStep::cases())
            ->map(fn (GameVersionSetupStep $step): array => $gameVersion === null
                ? $step->toOption()
                : [...$step->toOption(), 'href' => $this->setupUrl($gameVersion, $step)])
            ->all();
    }

    /**
     * @return array{create: string}
     */
    public function forIndex(): array
    {
        return [
            'create' => URL::route('management.game-versions.create'),
        ];
    }

    /**
     * @return array{index: string, store: string}
     */
    public function forCreate(): array
    {
        return [
            'index' => URL::route('management.game-versions.index'),
            'store' => URL::route('management.game-versions.store'),
        ];
    }

    /**
     * @return array{index: string, update: string, review: string}
     */
    public function forEdit(GameVersion $gameVersion): array
    {
        return [
            'index' => URL::route('management.game-versions.index'),
            'update' => URL::route('management.game-versions.update', $gameVersion),
            'review' => URL::route('management.game-versions.review', $gameVersion),
        ];
    }

    /**
     * The URLs around one setup step. Back leads to the previous step, or the
     * details form from the first step. Onward leads to the next step, or the
     * review page from the last step or when the step was opened from there.
     *
     * @return array{update: string, edit: string, review: string, previous: string, next: string}
     */
    public function forSetup(GameVersion $gameVersion, GameVersionSetupStep $step, bool $returnToReview = false): array
    {
        $editUrl = URL::route('management.game-versions.edit', $gameVersion);
        $reviewUrl = URL::route('management.game-versions.review', $gameVersion);
        $previousStep = $step->previous();
        $nextStep = $returnToReview ? null : $step->next();

        return [
            'update' => URL::route('management.game-versions.update', $gameVersion),
            'edit' => $editUrl,
            'review' => $reviewUrl,
            'previous' => $previousStep ? $this->setupUrl($gameVersion, $previousStep) : $editUrl,
            'next' => $nextStep ? $this->setupUrl($gameVersion, $nextStep) : $reviewUrl,
        ];
    }

    /**
     * @return array{index: string, edit: string, review: string}
     */
    public function forReview(GameVersion $gameVersion): array
    {
        return [
            'index' => URL::route('management.game-versions.index'),
            'edit' => URL::route('management.game-versions.edit', $gameVersion),
            'review' => URL::route('management.game-versions.review', $gameVersion),
        ];
    }

    /**
     * The actions on a game version's card on the Index page.
     *
     * @return array{edit: string, destroy: string}
     */
    public function links(GameVersion $gameVersion): array
    {
        return [
            'edit' => URL::route('management.game-versions.edit', $gameVersion),
            'destroy' => URL::route('management.game-versions.destroy', $gameVersion),
        ];
    }

    private function setupUrl(GameVersion $gameVersion, GameVersionSetupStep $step): string
    {
        return URL::route('management.game-versions.setup', [$gameVersion, $step]);
    }
}
