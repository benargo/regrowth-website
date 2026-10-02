<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Exceptions\RealmRequiredException;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Http\Requests\Dashboard\UploadGrmDataRequest;
use App\Http\Resources\GameVersionResource;
use App\Jobs\ProcessGrmUpload;
use App\Models\GameVersion;
use App\Traits\GuildRosterManager\ResolvesUploadPath;
use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

#[Authorize('view-officer-dashboard')]
class GrmController extends Controller
{
    use ResolvesUploadPath;

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
        $gameVersionId = $request->integer('game_version_id') ?: null;

        $gameVersions = GameVersion::whereNotNull('blizzard_namespace')->orderBy('release_date')->get(['id', 'title', 'slug', 'theme', 'realm', 'guild_name', 'blizzard_namespace']);

        $selectedGameVersion = $gameVersionId
            ? $gameVersions->firstWhere('id', $gameVersionId)
            : $gameVersions->first();

        return Inertia::render('Manage/GrmUpload/Form', [
            'lastUploadTimestamp' => fn () => $this->resolveLastUploadTimestamp($selectedGameVersion),
            'gameVersions' => GameVersionResource::collection($gameVersions)->resolve($request),
            'memberCount' => Inertia::defer(fn () => $this->resolveMemberCount($selectedGameVersion)),
        ]);
    }

    /**
     * Resolve when the given game version's GRM data was last uploaded.
     */
    protected function resolveLastUploadTimestamp(?GameVersion $gameVersion): ?string
    {
        if ($gameVersion === null || ! $this->storage->exists($this->grmUploadPath($gameVersion))) {
            return null;
        }

        return Carbon::createFromTimestamp($this->storage->lastModified($this->grmUploadPath($gameVersion)))
            ->format('l, j F Y \a\t H:i');
    }

    /**
     * Resolve the guild member count for the given game version.
     */
    protected function resolveMemberCount(?GameVersion $gameVersion): ?int
    {
        if ($gameVersion === null) {
            return null;
        }

        try {
            return count($this->blizzardConnector->send(new GetGuildRosterRequest(
                $gameVersion->realm_slug,
                $gameVersion->guild_slug,
                $gameVersion->blizzard_namespace,
            ))->dto()->members);
        } catch (RealmRequiredException) {
            return null;
        }
    }

    #[Authorize('edit-datasets')]
    public function handleUpload(UploadGrmDataRequest $request)
    {
        $grmData = $request->input('grm_data');
        $parsedData = $request->getParsedCsvData();

        // Archive and save the raw CSV
        $this->storage->put('grm/archives/'.Carbon::now()->format('Y-m-d_H-i-s').'.csv', $grmData);
        $gameVersion = GameVersion::findOrFail($request->integer('game_version_id'));
        $this->storage->put($this->grmUploadPath($gameVersion), $grmData);

        // Dispatch the processing job; progress is delivered live over the
        // uploading user's private broadcast channel.
        ProcessGrmUpload::dispatch($parsedData, $request->user()->id, $request->integer('game_version_id'))->withoutDelay();

        return redirect()->route('management.grm-upload.form')->with('success', 'GRM data uploaded successfully. Processing will continue in the background.');
    }
}
