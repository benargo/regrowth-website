<?php

namespace App\Http\Controllers\GuildRosterManager;

use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Actions\GuildRosterManager\CheckUploadStatus;
use App\Actions\GuildRosterManager\CountGuildMembers;
use App\Actions\GuildRosterManager\FindLatestUpload;
use App\Actions\GuildRosterManager\StoreUpload;
use App\Http\Controllers\Controller;
use App\Http\Middleware\RemembersCurrentGameVersion;
use App\Http\Requests\GuildRosterManager\StoreImportRequest;
use App\Http\Resources\GameVersionResource;
use App\Models\GameVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('view-officer-dashboard')]
class ImportController extends Controller
{
    /**
     * Show the GRM upload form for the user's current game version.
     */
    #[Middleware(RemembersCurrentGameVersion::class)]
    public function create(
        Request $request,
        ResolveCurrentGameVersion $resolveCurrentGameVersion,
        FindLatestUpload $findLatestUpload,
        CountGuildMembers $countGuildMembers,
        CheckUploadStatus $checkUploadStatus,
    ): Response {
        $gameVersions = GameVersion::currentRosters()->load('media')->values();
        $gameVersion = $resolveCurrentGameVersion->handle($gameVersions);

        return Inertia::render('Manage/GRM/Create', [
            'gameVersion' => $gameVersion ? (new GameVersionResource($gameVersion))->resolve($request) : null,
            'gameVersions' => GameVersionResource::collection($gameVersions)->resolve($request),
            'lastUploadTimestamp' => fn (): ?string => $gameVersion
                ? $findLatestUpload->handle($gameVersion)?->lastModified->format('l, j F Y \a\t H:i')
                : null,
            'memberCount' => Inertia::defer(fn (): ?int => $gameVersion ? $countGuildMembers->handle($gameVersion) : null),
            'uploadStatuses' => Inertia::defer(
                fn (): array => $gameVersions
                    ->mapWithKeys(fn (GameVersion $version): array => [$version->slug => $checkUploadStatus->handle($version)])
                    ->all(),
                'statuses',
            ),
        ]);
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
