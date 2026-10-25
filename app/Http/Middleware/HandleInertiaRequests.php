<?php

namespace App\Http\Middleware;

use App\Actions\GameVersion\ListSelectableGameVersions;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Actions\Navigation\BuildNavigationData;
use App\Contracts\Actions\Navigation\CachesNavigationData;
use App\Data\Navigation\NavigationData;
use App\Http\Resources\GameVersionResource;
use App\Http\Resources\UserPermissionResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\OnceProp;

class HandleInertiaRequests extends Middleware implements CachesNavigationData
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? (new UserResource($user))->resolve($request) : null,
                'permissions' => $user ? (new UserPermissionResource($user))->resolve($request) : [],
                'impersonating' => fn () => Session::has('impersonating_user_id'),
            ],
            'discordInviteUrl' => fn () => config('guild.discord_invite_url'),
            'flash' => [
                'error' => fn () => session('error'),
                'success' => fn () => session('success'),
            ],
            'gameVersions' => fn (): AnonymousResourceCollection => GameVersionResource::collection(ListSelectableGameVersions::run())
                ->scope('switcher')
                ->additional(['meta' => ['current' => ResolveCurrentGameVersion::run()?->getKey()]]),
            'navigation' => function () use ($user): OnceProp {
                $gameVersion = ResolveCurrentGameVersion::run();

                return Inertia::once(fn (): NavigationData => BuildNavigationData::run($user, $gameVersion))
                    ->as(sprintf(self::KEY_FORMAT, $gameVersion->id ?? 'none', $user?->id ?? 'guest'))
                    ->until(now()->addMinutes(self::TTL_MINUTES));
            },
            'warcraftLogsUrl' => fn (): ?string => ResolveCurrentGameVersion::run()?->warcraftLogsGuild?->logs_url,
        ];
    }
}
