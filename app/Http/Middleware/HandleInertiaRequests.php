<?php

namespace App\Http\Middleware;

use App\Http\Resources\UserResource;
use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Item;
use App\Models\TBC\Phase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
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
                'can' => [
                    'accessDashboard' => $user?->can('access-dashboard') ?? false,
                    'accessLoot' => $user?->can('viewAny', Item::class) ?? false,
                    'viewAllComments' => $user?->can('viewAll', Comment::class) ?? false,
                ],
                'impersonating' => $request->session()->has('impersonating_user_id'),
            ],
            'phases' => Cache::remember('phases.tbc.index', now()->addYear(), fn () => Phase::all()),
            'flash' => [
                'error' => fn () => $request->session()->get('error'),
                'success' => fn () => $request->session()->get('success'),
            ],
        ];
    }
}
