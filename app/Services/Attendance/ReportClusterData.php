<?php

namespace App\Services\Attendance;

use App\Models\Raids\Report;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

final class ReportClusterData extends Data
{
    /**
     * @param  Collection<int, Report>  $reports  eager-loaded with characters (with pivot.presence) and zone
     */
    public function __construct(
        public readonly Collection $reports,
    ) {
        if ($this->reports->isEmpty()) {
            throw new InvalidArgumentException('ReportClusterData requires at least one report.');
        }
    }

    /**
     * A single report id, or the sorted report ids joined with '+' for a merged cluster.
     */
    public function id(): string
    {
        if ($this->reports->count() === 1) {
            return $this->reports->first()->id;
        }

        return $this->reports->pluck('id')->sort()->values()->implode('+');
    }

    /**
     * A single code, sorted non-null codes joined with '+', or null when all reports are manual.
     */
    public function code(): ?string
    {
        $codes = $this->reports->pluck('code')->filter()->sort()->values();

        return $codes->isEmpty() ? null : $codes->implode('+');
    }

    public function startTime(): Carbon
    {
        return $this->reports->sortBy('start_time')->first()->start_time;
    }

    public function zoneName(): ?string
    {
        return $this->reports->sortBy('start_time')->first()->zone?->name;
    }

    public function isMerged(): bool
    {
        return $this->reports->count() > 1;
    }

    /**
     * Merged per-character presence across the cluster, keyed by character name. Higher
     * presencePriority wins (present beats late beats absent).
     *
     * @return Collection<string, PlayerPresenceData>
     */
    public function players(): Collection
    {
        $merged = [];

        foreach ($this->reports as $report) {
            foreach ($report->characters as $character) {
                $name = $character->name;
                $presence = (int) $character->pivot->presence;

                if (! isset($merged[$name])
                    || Calculator::presencePriority($presence) > Calculator::presencePriority($merged[$name]->presence)) {
                    $merged[$name] = new PlayerPresenceData($character, $presence);
                }
            }
        }

        return collect($merged);
    }

    /**
     * @return array{id: string, code: string|null, startTime: string, zoneName: string|null, players: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'code' => $this->code(),
            'startTime' => $this->startTime()->toISOString(),
            'zoneName' => $this->zoneName(),
            'players' => $this->players()->values()->map(fn (PlayerPresenceData $p) => $p->toArray())->all(),
        ];
    }

    /**
     * @return array{id: string, code: string|null, startTime: string, zoneName: string|null, players: array<int, array<string, mixed>>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
