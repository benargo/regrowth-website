<?php

namespace App\Actions\Navigation;

use App\Actions\Comments\ListLatestDiscussions;
use App\Actions\Events\DescribeViewerSignup;
use App\Actions\Events\FindNextRaidingEvent;
use App\Contracts\Actions\Navigation\CachesNavigationData;
use App\Data\Navigation\NavigationData;
use App\Data\Navigation\NextRaidData;
use App\Models\GameVersion;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Builds the data the Raiding and Loot flyouts show: the version's next raid,
 * the viewer's place in it, and the latest discussions, cached for five
 * minutes per viewer and flushed with the raiding and events caches.
 */
class BuildNavigationData implements CachesNavigationData
{
    use AsAction;

    public function handle(?User $user, ?GameVersion $gameVersion): NavigationData
    {
        return NavigationData::from(Cache::tags([self::CACHE_TAG, 'raiding', 'events'])->remember(
            sprintf(self::KEY_FORMAT, $gameVersion->id ?? 'none', $user->id ?? 'guest'),
            now()->addMinutes(self::TTL_MINUTES),
            fn (): array => $this->build($user, $gameVersion)->toArray(),
        ));
    }

    /**
     * Builds the navigation data for the viewer and version, uncached.
     */
    private function build(?User $user, ?GameVersion $gameVersion): NavigationData
    {
        $event = FindNextRaidingEvent::run($gameVersion);

        $nextRaid = $event === null ? null : NextRaidData::fromEvent(
            $event,
            $user === null ? null : DescribeViewerSignup::run($event, $user),
        );

        return new NavigationData(
            nextRaid: $nextRaid,
            latestDiscussions: ListLatestDiscussions::run($gameVersion)->all(),
        );
    }
}
