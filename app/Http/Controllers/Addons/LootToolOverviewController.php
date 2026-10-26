<?php

namespace App\Http\Controllers\Addons;

use App\Contracts\HasBlizzardIcons;
use App\Http\Controllers\Controller;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use App\Support\MediaLibrary\UnknownBlizzardIcon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class LootToolOverviewController extends Controller implements HasBlizzardIcons
{
    /**
     * The Blizzard icon shown beside the example item tooltip.
     */
    private const string ITEM_ICON = 'inv_jewelry_necklace_29naxxramas';

    /**
     * Render the addon overview with the example item's icon and the addon's CurseForge
     * and GitHub links. The icon is fetched from the Blizzard render CDN into storage
     * when it is not already stored, and the questionmark icon is used when it cannot
     * be stored.
     */
    public function __invoke(RenderConnector $renderConnector, UnknownBlizzardIcon $unknownIcon): Response
    {
        $disk = Storage::disk(config('services.blizzard.filesystem', 'public'));
        $iconPath = self::STORAGE_PATH_PREFIX.'/icons/'.self::DEFAULT_MEDIA_SIZE.'/'.self::ITEM_ICON.'.'.self::DEFAULT_MEDIA_FILE_EXTENSION;

        $hasIcon = $disk->exists($iconPath);

        if (! $hasIcon) {
            try {
                $renderConnector->send(new FetchIconRequest(self::ITEM_ICON));
                $hasIcon = $disk->exists($iconPath);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return Inertia::render('Addons/LootTool/Overview', [
            'curseforgeUrl' => config('guild.loot-tool.curseforge_url'),
            'githubUrl' => config('guild.loot-tool.github_url'),
            'itemIconUrl' => $hasIcon ? $disk->url($iconPath) : $unknownIcon->url(),
        ]);
    }
}
