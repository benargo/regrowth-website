<?php

namespace App\Actions\Datasets;

use App\Contracts\Datasets\SetupStep;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Resolves the URLs a dataset's management pages link to, so the shared
 * dataset components receive plain URLs rather than route names. Each page
 * gets only the URLs it uses.
 */
abstract class BuildDatasetRoutes
{
    use AsAction;

    /**
     * The route name prefix, e.g. "management.game-versions".
     */
    abstract protected function routePrefix(): string;

    /**
     * The enum of the dataset's setup wizard steps, or null when the dataset
     * has no wizard.
     *
     * @return class-string<SetupStep&BackedEnum>|null
     */
    abstract protected function setupSteps(): ?string;

    /**
     * Every setup step in wizard order.
     *
     * @return list<array{value: string, label: string, component: string, href?: string}>
     */
    public function steps(?Model $record = null): array
    {
        $steps = $this->setupSteps();

        if ($steps === null) {
            return [];
        }

        return collect($steps::cases())
            ->map(fn (SetupStep $step): array => $record === null
                ? $step->toOption()
                : [...$step->toOption(), 'href' => $this->setupUrl($record, $step)])
            ->all();
    }

    /**
     * @return array{create: string}
     */
    public function forIndex(): array
    {
        return [
            'create' => $this->route('create'),
        ];
    }

    /**
     * @return array{index: string, store: string}
     */
    public function forCreate(): array
    {
        return [
            'index' => $this->route('index'),
            'store' => $this->route('store'),
        ];
    }

    /**
     * @return array{index: string, update: string, review: string}
     */
    public function forEdit(Model $record): array
    {
        return [
            'index' => $this->route('index'),
            'update' => $this->route('update', $record),
            'review' => $this->route('review', $record),
        ];
    }

    /**
     * The URLs around one setup step. Back leads to the previous step, or the
     * details form from the first step. Onward leads to the next step, or the
     * review page from the last step or when the step was opened from there.
     *
     * @return array{update: string, edit: string, review: string, previous: string, next: string}
     */
    public function forSetup(Model $record, SetupStep $step, bool $returnToReview = false): array
    {
        $editUrl = $this->route('edit', $record);
        $reviewUrl = $this->route('review', $record);
        $previousStep = $step->previous();
        $nextStep = $returnToReview ? null : $step->next();

        return [
            'update' => $this->route('update', $record),
            'edit' => $editUrl,
            'review' => $reviewUrl,
            'previous' => $previousStep ? $this->setupUrl($record, $previousStep) : $editUrl,
            'next' => $nextStep ? $this->setupUrl($record, $nextStep) : $reviewUrl,
        ];
    }

    /**
     * @return array{index: string, edit: string, review: string}
     */
    public function forReview(Model $record): array
    {
        return [
            'index' => $this->route('index'),
            'edit' => $this->route('edit', $record),
            'review' => $this->route('review', $record),
        ];
    }

    /**
     * The actions on a record's card on the Index page.
     *
     * @return array{edit: string, destroy: string}
     */
    public function links(Model $record): array
    {
        return [
            'edit' => $this->route('edit', $record),
            'destroy' => $this->route('destroy', $record),
        ];
    }

    protected function setupUrl(Model $record, SetupStep $step): string
    {
        return $this->route('setup', [$record, $step]);
    }

    /**
     * Resolve one of the dataset's named routes.
     */
    protected function route(string $name, mixed $parameters = []): string
    {
        return URL::route("{$this->routePrefix()}.{$name}", $parameters);
    }
}
