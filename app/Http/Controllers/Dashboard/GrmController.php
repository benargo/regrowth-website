<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Http\Requests\Dashboard\UploadGrmDataRequest;
use App\Http\Resources\GameVersionResource;
use App\Jobs\ProcessGrmUpload;
use App\Models\GameVersion;
use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

#[Authorize('view-officer-dashboard')]
class GrmController extends Controller
{
    protected Filesystem $storage;

    public function __construct(
        protected BlizzardConnector $blizzardConnector,
    ) {
        $this->storage = Storage::disk('local');

        // Make the directory, if it doesn't exist
        $this->storage->makeDirectory('grm/uploads');
        $this->storage->makeDirectory('grm/archives');
    }

    /**
     * Show the GRM data upload form.
     */
    public function showUploadForm(Request $request)
    {
        if ($this->storage->exists('grm/uploads/latest.csv')) {
            $lastModified = Carbon::createFromTimestamp(
                $this->storage->lastModified('grm/uploads/latest.csv')
            )->format('l, j F Y \a\t H:i');
        } else {
            $lastModified = null;
        }

        $gameVersionId = $request->integer('game_version_id') ?: null;

        return Inertia::render('Manage/GrmUpload/Form', [
            'lastUploadTimestamp' => $lastModified,
            'gameVersions' => GameVersionResource::collection(
                GameVersion::whereNotNull('blizzard_namespace')->orderBy('release_date')->get(['id', 'title', 'theme'])
            )->resolve($request),
            'memberCount' => Inertia::defer(function () use ($gameVersionId) {
                $gameVersion = $gameVersionId
                    ? GameVersion::whereNotNull('blizzard_namespace')->find($gameVersionId)
                    : GameVersion::whereNotNull('blizzard_namespace')->orderBy('release_date')->first();

                return $this->resolveMemberCount($gameVersion);
            }),
        ]);
    }

    /**
     * Resolve the guild member count for the given game version.
     */
    protected function resolveMemberCount(?GameVersion $gameVersion): ?int
    {
        if ($gameVersion?->realm === null) {
            return null;
        }

        return count($this->blizzardConnector->send(new GetGuildRosterRequest(
            $gameVersion->realm,
            $this->blizzardConnector->defaultGuildSlug(),
            $gameVersion->blizzard_namespace,
        ))->dto()->members);
    }

    #[Authorize('edit-datasets')]
    public function handleUpload(UploadGrmDataRequest $request)
    {
        $grmData = $request->input('grm_data');
        $parsedData = $request->getParsedCsvData();

        // Archive and save the raw CSV
        $this->storage->put('grm/archives/'.Carbon::now()->format('Y-m-d_H-i-s').'.csv', $grmData);
        $this->storage->put('grm/uploads/latest.csv', $grmData);

        // Dispatch the processing job; progress is delivered live over the
        // uploading user's private broadcast channel.
        ProcessGrmUpload::dispatch($parsedData, $request->user()->id, $request->integer('game_version_id'))->withoutDelay();

        return redirect()->route('management.grm-upload.form')->with('success', 'GRM data uploaded successfully. Processing will continue in the background.');
    }
}
