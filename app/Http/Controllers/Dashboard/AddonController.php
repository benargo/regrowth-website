<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Models\GuildRank;
use App\Services\WarcraftLogs\GuildTags;
use Carbon\Carbon;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('view-officer-dashboard')]
class AddonController extends Controller
{
    public function __construct(
        protected BlizzardConnector $blizzardConnector,
        protected GuildTags $guildTags,
        protected FilesystemManager $storage,
    ) {}

    public function exportBase64(Request $request): Response
    {
        return Inertia::render('Manage/Addon/Export', [
            'exportedData' => Inertia::defer(function () use ($request): string {
                $data = $this->getExportedData($request);

                if ($data === null) {
                    return '';
                }

                return base64_encode(json_encode($data));
            }),
            'grmFreshness' => Inertia::defer(fn () => $this->getGrmFreshness()),
        ]);
    }

    public function exportJson(Request $request): Response
    {
        return Inertia::render('Manage/Addon/ExportJson', [
            'exportedData' => Inertia::defer(function () use ($request): string {
                $data = $this->getExportedData($request);

                if ($data === null) {
                    return '';
                }

                return json_encode($data, JSON_PRETTY_PRINT);
            }),
            'grmFreshness' => Inertia::defer(fn () => $this->getGrmFreshness()),
        ]);
    }

    /**
     * Get the exported data from storage, injecting user context.
     */
    protected function getExportedData(Request $request): ?array
    {
        $json = $this->storage->disk('local')->get('addon/export.json');

        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);

        $data['system']['user'] = [
            'id' => $request->user()->id,
            'name' => $request->user()->displayName,
        ];

        return $data;
    }

    /**
     * Get the freshness status of the GRM data.
     */
    protected function getGrmFreshness(): array
    {
        $dataIsStale = false;
        $timestamp = Carbon::createFromTimestamp(0);

        // Check the last modified time of the GRM upload file
        $disk = $this->storage->disk('local');

        if ($disk->exists('grm/uploads/latest.csv')) {
            $fileLastModifiedTime = $disk->lastModified('grm/uploads/latest.csv');
            $timestamp = Carbon::createFromTimestamp($fileLastModifiedTime);
        }

        // Check if the roster data is significantly different from the roster data at the time of the last GRM upload
        $roster = $this->blizzardConnector->send(new GetGuildRosterRequest(
            $this->blizzardConnector->defaultRealmSlug(),
            $this->blizzardConnector->defaultGuildSlug(),
        ))->dto();
        $raiderRankPositions = GuildRank::whereLike('name', '%Raider%')->pluck('sort_order');

        $raiderCount = collect($roster->members)
            ->filter(fn ($member) => $raiderRankPositions->contains($member->rank))
            ->count();

        // Count the number of raiders in the GRM upload file.
        $grmRaidersCount = 0;
        if ($disk->exists('grm/uploads/latest.csv')) {
            $file = $disk->get('grm/uploads/latest.csv');

            // Find which column contains the rank information by reading the first line to find 'Rank'
            $lines = explode("\n", $file);
            $header = str_getcsv(array_shift($lines));
            $rankColumnIndex = null;
            foreach ($header as $index => $columnName) {
                if (stripos($columnName, 'Rank') !== false) {
                    $rankColumnIndex = $index;
                    break;
                }
            }

            // Count the number of individuals with 'Raider' in their rank
            foreach ($lines as $line) {
                $columns = str_getcsv($line);
                if ($rankColumnIndex !== null
                    && isset($columns[$rankColumnIndex])
                    && stripos($columns[$rankColumnIndex], 'Raider') !== false) {
                    $grmRaidersCount++;
                }
            }
        }

        // Compare the two counts
        if (abs($raiderCount - $grmRaidersCount) >= 3) {
            // If the difference is 3 or more, consider the GRM data stale
            $dataIsStale = true;
        }

        return [
            'lastModified' => $timestamp,
            'dataIsStale' => $dataIsStale,
            'blzRaiderCount' => $raiderCount,
            'grmRaiderCount' => $grmRaidersCount,
        ];
    }
}
