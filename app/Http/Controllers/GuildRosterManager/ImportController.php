<?php

namespace App\Http\Controllers\GuildRosterManager;

use App\Actions\GuildRosterManager\CheckUploadStatus;
use App\Actions\GuildRosterManager\CountGuildMembers;
use App\Actions\GuildRosterManager\FindLatestUpload;
use App\Actions\GuildRosterManager\StoreUpload;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuildRosterManager\StoreImportRequest;
use App\Http\Resources\GameVersionResource;
use App\Models\GameVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('view-officer-dashboard')]
class ImportController extends Controller
{
    /**
     * Show the GRM upload form for the game version named in the query string,
     * or for the newest current roster.
     */
    public function create(
        Request $request,
        FindLatestUpload $findLatestUpload,
        CountGuildMembers $countGuildMembers,
        CheckUploadStatus $checkUploadStatus,
    ): Response {
        $gameVersions = GameVersion::currentRosters()->values();
        $gameVersion = $this->selectedGameVersion($request, $gameVersions);

        return Inertia::render('Manage/GRM/Create', [
            'gameVersion' => $gameVersion ? (new GameVersionResource($gameVersion))->resolve($request) : null,
            'gameVersions' => GameVersionResource::collection($gameVersions)->resolve($request),
            'lastUploadTimestamp' => $gameVersion
                ? $findLatestUpload->handle($gameVersion)?->lastModified->format('l, j F Y \a\t H:i')
                : null,
            'memberCount' => Inertia::defer(fn (): ?int => $gameVersion ? $countGuildMembers->handle($gameVersion) : null),
            'uploadStatuses' => Inertia::defer(fn (): array => $gameVersions
                ->mapWithKeys(fn (GameVersion $version): array => [$version->slug => $checkUploadStatus->handle($version)])
                ->all(), 'statuses'),
        ]);
    }

    /**
     * Pick the game version named by `?game_version=`, or the newest current
     * roster when none is named. A slug that isn't a current roster is a 404.
     *
     * @param  Collection<int, GameVersion>  $gameVersions
     */
    protected function selectedGameVersion(Request $request, Collection $gameVersions): ?GameVersion
    {
        if (! $request->filled('game_version')) {
            return $gameVersions->first();
        }

        $gameVersion = $gameVersions->firstWhere('slug', $request->string('game_version')->toString());

        abort_if($gameVersion === null, 404);

        return $gameVersion;
    }

    /**
     * Store an uploaded GRM export and queue it for processing.
     */
    #[Authorize('edit-datasets')]
    public function store(StoreImportRequest $request, StoreUpload $storeUpload): RedirectResponse
    {
        $gameVersion = $request->gameVersion();

        $storeUpload->handle(
            $gameVersion,
            $request->validated('grm_data'),
            $request->getParsedCsvData(),
            $request->user(),
        );

        return redirect()->route('management.grm.create', ['game_version' => $gameVersion->slug])
            ->with('success', 'GRM data uploaded successfully. Processing will continue in the background.');
    }
}
