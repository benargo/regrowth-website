<?php

namespace App\Http\Controllers\Addons;

use App\Actions\Addon\ReadAddonExport;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Actions\GuildRosterManager\CheckUploadFreshness;
use App\Http\Controllers\Controller;
use App\Http\Resources\GameVersionResource;
use App\Models\GameVersion;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('view-officer-dashboard')]
class LootToolController extends Controller
{
    public function __construct(
        protected CheckUploadFreshness $checkUploadFreshness,
    ) {}

    /**
     * Render the current game version's addon export, with the JSON deferred and the Base64 optional.
     */
    public function __invoke(Request $request): Response
    {
        $gameVersion = ResolveCurrentGameVersion::run();
        $hasExport = $gameVersion?->ownsCurrentRoster() ?? false;

        return Inertia::render('Addons/LootTool/Export', [
            'gameVersion' => $gameVersion === null ? null : new GameVersionResource($gameVersion),
            'hasExport' => $hasExport,
            'json' => Inertia::defer(function () use ($request, $gameVersion, $hasExport): string {
                $data = $this->exportData($request, $gameVersion, $hasExport);

                return $data === null ? '' : json_encode($data, JSON_PRETTY_PRINT);
            }),
            'base64' => Inertia::optional(function () use ($request, $gameVersion, $hasExport): string {
                $data = $this->exportData($request, $gameVersion, $hasExport);

                return $data === null ? '' : base64_encode(json_encode($data));
            }),
            'grmFreshness' => Inertia::defer(fn () => $hasExport ? [$this->checkUploadFreshness->handle($gameVersion)] : [], 'freshness'),
        ]);
    }

    /**
     * Read the game version's export for the authenticated user, or null when it has none.
     *
     * @return array<string, mixed>|null
     */
    protected function exportData(Request $request, ?GameVersion $gameVersion, bool $hasExport): ?array
    {
        return $hasExport ? ReadAddonExport::run($gameVersion, $request->user()) : null;
    }
}
