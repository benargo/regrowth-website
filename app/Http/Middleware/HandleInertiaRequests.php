<?php

namespace App\Http\Middleware;

use App\Actions\GameVersion\ListSelectableGameVersions;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Actions\Navigation\BuildNavigationData;
use App\Contracts\Actions\Navigation\CachesNavigationData;
use App\Data\Navigation\NavigationData;
use App\Http\Resources\GameVersionResource;
use App\Http\Resources\UserResource;
use App\Models\Permission;
use App\Models\User;
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
                'user' => fn (): ?UserResource => $user?->toResource(),
                'permissions' => fn (): array => $user === null ? [] : $this->permissionNames($user),
                'impersonating' => fn () => Session::has('impersonating_user_id'),
            ],
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
        ];
    }

    /**
     * The names of every permission the user holds; admins hold them all.
     *
     * @return list<string>
     */
    protected function permissionNames(User $user): array
    {
        if ($user->is_admin) {
            return Permission::pluck('name')->all();
        }

        return $user->permissions()->pluck('name')->all();
    }
}
