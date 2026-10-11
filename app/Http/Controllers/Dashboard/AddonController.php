<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\GuildRosterManager\CheckUploadFreshness;
use App\Http\Controllers\Controller;
use App\Models\GameVersion;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('view-officer-dashboard')]
class AddonController extends Controller
{
    public function __construct(
        protected CheckUploadFreshness $checkUploadFreshness,
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
            'grmFreshness' => Inertia::defer(fn () => $this->getGrmFreshness(), 'freshness'),
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
            'grmFreshness' => Inertia::defer(fn () => $this->getGrmFreshness(), 'freshness'),
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
     * Check the GRM upload freshness of every game version that owns a current guild roster.
     *
     * @return list<array<string, mixed>>
     */
    protected function getGrmFreshness(): array
    {
        return GameVersion::currentRosters()
            ->map(fn (GameVersion $gameVersion): array => $this->checkUploadFreshness->handle($gameVersion))
            ->values()
            ->all();
    }
}
