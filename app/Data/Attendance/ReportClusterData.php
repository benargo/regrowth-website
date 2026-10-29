<?php

namespace App\Data\Attendance;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Data;

/**
 * One raid: a single report, or reports joined by report links.
 */
final class ReportClusterData extends Data
{
    /**
     * @param  string  $id  a single report id, or the sorted report ids joined with '+'
     * @param  string|null  $code  the sorted non-null report codes joined with '+', or null when every report is manual
     * @param  CarbonInterface  $startTime  the earliest report's start time
     * @param  string|null  $zoneName  the earliest report's zone name
     * @param  Collection<int, PlayerPresenceData>  $players  merged presence across the cluster, keyed by character id
     */
    public function __construct(
        public readonly string $id,
        #[Hidden]
        public readonly ?string $code,
        public readonly CarbonInterface $startTime,
        public readonly ?string $zoneName,
        #[Hidden]
        public readonly Collection $players,
    ) {}
}
